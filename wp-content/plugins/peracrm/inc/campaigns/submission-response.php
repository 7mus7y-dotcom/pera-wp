<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/acceptance.php';

/** Internal mapper integration. Persist authenticated positive responses independently
 * of worker leases. Never changes unknown/cancelled delivery state or issues retries.
 */
final class PeraCRM_Campaign_Submission_Response
{
    public static function record(string $deployment, PeraCRM_CampaignSubmissionV1 $submission, string $message): string
    {
        if (preg_match('/\A[a-zA-Z0-9_-]{1,64}\z/',$deployment)!==1
            || preg_match('/\A[A-Za-z0-9_-]{1,191}\z/',$message)!==1) { throw new RuntimeException('response_invalid'); }
        global $wpdb;
        $previous=$wpdb->suppress_errors(true); $started=false;
        $db=static function ($value) use ($wpdb) {
            if ($value===false || $wpdb->last_error!=='') { throw new RuntimeException('response_storage'); }
            return $value;
        };
        try {
            $fields=$submission->fields(); $blog=get_current_blog_id();
            if ($blog!==$fields['blog_id'] || (is_multisite() && (int)peracrm_get_target_blog_id()!==$blog)
                || !peracrm_campaigns_schema_ready() || (int)$db($wpdb->get_var('SELECT @@autocommit'))!==1) { throw new RuntimeException('response_storage'); }
            $db($wpdb->query('SAVEPOINT peracrm_response_top_level_probe'));
            if ($wpdb->query('RELEASE SAVEPOINT peracrm_response_top_level_probe')!==false
                || $wpdb->last_error!=='SAVEPOINT peracrm_response_top_level_probe does not exist') { throw new RuntimeException('response_storage'); }
            $db($wpdb->query('START TRANSACTION')); $started=true;
            $db($wpdb->query('SAVEPOINT peracrm_response_owned'));
            $connection=(int)$db($wpdb->get_var('SELECT CONNECTION_ID()'));
            if ($connection<=0) { throw new RuntimeException('response_storage'); }
            $db($wpdb->query('RELEASE SAVEPOINT peracrm_response_owned'));
            $guard=bin2hex(random_bytes(32));
            $db($wpdb->query($wpdb->prepare('SET @peracrm_response_guard=IF(CONNECTION_ID()=%d,%s,NULL)',$connection,$guard)));
            $insert=static function (string $name,array $data) use ($wpdb,$db,$connection,$guard) {
                $columns=implode(',',array_keys($data)); $values=implode(',',array_fill(0,count($data),'%s')); $noop=array_key_first($data);
                $db($wpdb->query($wpdb->prepare('INSERT INTO '.peracrm_table($name)." ({$columns}) SELECT {$values}
                    WHERE CONNECTION_ID()=%d AND @peracrm_response_guard=%s ON DUPLICATE KEY UPDATE {$noop}={$noop}",
                    array_merge(array_values($data),[$connection,$guard]))));
                if ((int)$db($wpdb->get_var('SELECT CONNECTION_ID()'))!==$connection) { throw new RuntimeException('response_storage'); }
            };
            $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_submission_attempts')
                .' WHERE deployment=%s AND blog_id=%d AND attempt_key=%s FOR UPDATE',$deployment,$blog,$fields['attempt_key']),ARRAY_A);
            $db($row?:false);
            if (!hash_equals($row['binding_hash'],hash('sha256',$row['binding_json']))) { throw new RuntimeException('response_storage'); }
            $binding=json_decode($row['binding_json'],true,16,JSON_THROW_ON_ERROR);
            $valid=true;
            foreach (['version','scope','blog_id','campaign_id','job_id','revision_id','snapshot_id','recipient_id','snapshot_hash','delivery_key','attempt_key','personalization_version','mailbox'] as $key) {
                if (($binding[$key]??null)!==$fields[$key]) { $valid=false; }
            }
            if ($row['delivery_key']!==$fields['delivery_key'] || $row['attempt_key']!==$fields['attempt_key']
                || ($binding['envelope_hash']??null)!==$submission->hash()) { $valid=false; }
            $source=hash('sha256',json_encode(['binding'=>$row['binding_hash'],'envelope'=>$submission->hash(),'message'=>$message],JSON_THROW_ON_ERROR));
            $data=['provider_scope'=>PeraCRM_Campaign_Acceptance::namespace($deployment,$blog,$binding['scope']),
                'message_id'=>$message,'deployment'=>$deployment,'blog_id'=>$blog,'attempt_key'=>$row['attempt_key'],
                'binding_hash'=>$row['binding_hash'],'event_scope'=>'','event_key'=>'','receipt_id'=>'','recorded_at'=>gmdate('Y-m-d H:i:s')];
            $decision=$valid?PeraCRM_Campaign_Acceptance::associate($data,$insert):'binding_conflict';
            PeraCRM_Campaign_Acceptance::evidence($data,'response',$source,$decision,$insert);
            if ((int)$db($wpdb->get_var('SELECT CONNECTION_ID()'))!==$connection) { throw new RuntimeException('response_storage'); }
            $db($wpdb->query('COMMIT')); $started=false;
            if ((int)$db($wpdb->get_var('SELECT CONNECTION_ID()'))!==$connection) { throw new RuntimeException('response_storage'); }
            return $decision;
        } catch (Throwable $error) {
            if ($started) { $wpdb->query('ROLLBACK'); }
            throw new RuntimeException('response_storage');
        } finally { $wpdb->suppress_errors($previous); }
    }
}
