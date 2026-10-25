<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/recipient-persistence.php';
require_once __DIR__.'/providers/ses.php';

/** Worker-only storage projection. Invoke inside the worker's fenced transaction.
 * Publication is the frozen revision row, not the capture's historical state label.
 * No staff API, live draft, configuration lookup, rendering or recipient issuance.
 */
final class PeraCRM_Campaign_Worker_Content
{
    public static function resolve(array $job, array $row, array $scope, callable $authorize): array
    {
        try {
            $authorize();
            global $wpdb;
            $load = static function ($suffix, $id) use ($wpdb) {
                $value=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_'.$suffix).' WHERE id=%d',$id),ARRAY_A);
                peracrm_campaigns_db_require($value ?: false);
                return $value;
            };
            $revision=$load('frozen_revisions',(int)$job['frozen_revision_id']);
            $preparation=$load('freeze_attempts',(int)$job['attempt_id']);
            $member=$load('frozen_recipients',(int)$row['frozen_recipient_id']);
            $snapshot=$load('content_snapshots',(int)$job['snapshot_id']);
            peracrm_campaign_submission_require($row['state']==='claimed'
                && (int)$job['complete']===1 && (int)$job['submission_complete']===0
                && (int)$preparation['complete']===1 && $preparation['state']==='frozen'
                && (int)$revision['published_by']>0 && !empty($revision['published_at'])
                && (int)$member['attempt_id']===(int)$job['attempt_id']
                && (int)$revision['attempt_id']===(int)$job['attempt_id']
                && (int)$member['subscriber_id']===(int)$row['subscriber_id'] && $member['mailbox']===$row['mailbox']
                && (int)$row['job_id']===(int)$job['id']
                && (int)$row['frozen_revision_id']===(int)$revision['id']
                && (int)$row['snapshot_id']===(int)$snapshot['id']
                && (int)$snapshot['target_blog_id']===get_current_blog_id()
                && hash_equals(peracrm_campaign_queue_delivery_key((int)$revision['id'],$row['mailbox']),$row['delivery_key'])
                && (int)$row['attempt_count']>0
                && hash_equals(hash('sha256',$row['delivery_key'].'|'.(int)$row['attempt_count']),$row['attempt_key']));
            foreach ([$revision,$preparation,$snapshot] as $source) {
                peracrm_campaign_submission_require((int)$source['campaign_id']===(int)$job['campaign_id']
                    && (int)$source['draft_revision']===(int)$revision['draft_revision']
                    && hash_equals($job['content_hash'],$source['content_hash']));
            }
            foreach ([$revision,$preparation] as $source) {
                peracrm_campaign_submission_require((int)$source['snapshot_id']===(int)$snapshot['id']);
            }
            peracrm_campaign_submission_require(hash_equals($job['content_hash'],$row['content_hash']));
            $decoded=peracrm_campaign_snapshot_decode($snapshot);
            $content=$decoded['payload']['content'];
            peracrm_campaign_submission_require(($decoded['payload']['schema_version']??null)===2
                && ($content['schema_version']??null)===2 && ($content['template_version']??null)===2
                && ($content['purpose']??null)==='deliverable_template'
                && ($content['renderer_version']??null)==='peracrm_email_v2' && ($content['slot_version']??null)===1);
            PeraCRM_CampaignSubmissionV1::validateScope($scope);
            $sender=$content['sender']??[];
            peracrm_campaign_submission_require(($sender['approved']??null)===true);
            $envelope=['version'=>2,'scope'=>$scope,'blog_id'=>get_current_blog_id(),
                'campaign_id'=>(int)$job['campaign_id'],'job_id'=>(int)$job['id'],
                'revision_id'=>(int)$revision['id'],'snapshot_id'=>(int)$snapshot['id'],
                'recipient_id'=>(int)$member['id'],'snapshot_hash'=>$snapshot['content_hash'],
                'delivery_key'=>$row['delivery_key'],'attempt_key'=>$row['attempt_key'],
                'personalization_version'=>2,'mailbox'=>$member['mailbox'],
                'subject'=>$content['model']['subject']??null,'sender_name'=>$sender['name']??null,
                'sender_email'=>$sender['email']??null,'reply_to'=>$sender['reply_to']??null,'timeout_seconds'=>20];
            // Validate captured headers before any durable recipient state is created.
            new PeraCRM_CampaignSubmissionV1(array_replace($envelope,['version'=>1,'personalization_version'=>1])
                + ['html'=>'validation','text'=>'validation']);
            $authorize();
            return [$content,$envelope];
        } catch (Throwable $error) { throw new RuntimeException('frozen_content_unavailable'); }
    }
}

/** Explicit internal dependencies only; not bootstrapped and grants no send authority. */
final class PeraCRM_Campaign_Worker_Preparation
{
    private $deployment; private $scope; private $origin; private $keys; private $active; private $cipher;
    public function __construct(string $deployment, array $scope, string $origin,
        #[\SensitiveParameter] array $keys, string $active, PeraCRM_CampaignOutputCipher $cipher)
    {
        PeraCRM_CampaignSubmissionV1::validateScope($scope);
        peracrm_campaign_recipient_require(preg_match('/\A[a-zA-Z0-9_-]{1,64}\z/',$deployment)===1);
        $this->deployment=$deployment; $this->scope=[]; $this->origin=$origin;
        // Canonical defensive copies also make replay independent of scope key order.
        foreach (['provider','account_id','region','configuration_set','configuration_version'] as $key) { $this->scope[$key]=$scope[$key]; }
        $this->keys=[];
        foreach ($keys as $key=>$value) { $this->keys[$key]=$value; }
        $this->active=$active; $this->cipher=$cipher;
    }
    public function deployment(): string { return $this->deployment; }
    public function scope(): array { return $this->scope; }
    public function prepare(array $content, array $envelope, array $row): array
    {
        $store=new PeraCRM_CampaignRecipientPersistence($this->deployment,get_current_blog_id());
        $prior=$store->attempt($row['attempt_key']);
        $epoch=$prior ? $prior['token_epoch'] : $store->ensureIdentity((int)$row['subscriber_id'],$row['mailbox']);
        $binding=['blog_id'=>get_current_blog_id(),'subscriber_id'=>(int)$row['subscriber_id'],
            'mailbox'=>$row['mailbox'],'token_epoch'=>$epoch,'attempt_key'=>$row['attempt_key'],'snapshot_hash'=>$row['content_hash']];
        [$submission]=$store->prepareOutput($content,$envelope,$binding,$this->origin,$this->keys,$this->active,time(),$this->cipher);
        return [$submission,$binding];
    }
    /** Caller holds final mailbox locks. A recovered envelope is never authority. */
    public function requireIdentity(array $binding): void
    {
        global $wpdb;
        $identity=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_recipient_identities')
            .' WHERE deployment=%s AND blog_id=%d AND subscriber_id=%d FOR UPDATE',
            $this->deployment,get_current_blog_id(),$binding['subscriber_id']),ARRAY_A);
        peracrm_campaigns_db_require(true);
        peracrm_campaign_recipient_require($identity && (int)$identity['active']===1
            && $identity['mailbox']===$binding['mailbox'] && (int)$identity['token_epoch']===$binding['token_epoch']);
    }
    public function __debugInfo(): array { return ['service'=>'worker_preparation','redacted'=>true]; }
    public function __serialize(): array { throw new LogicException('worker_preparation_unavailable'); }
    public function __unserialize(array $data): void { throw new LogicException('worker_preparation_unavailable'); }
}
