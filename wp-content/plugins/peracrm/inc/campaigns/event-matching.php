<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/event-normalization.php';
require_once __DIR__.'/acceptance.php';

/** Internal correlation only. No factory, endpoint, worker callback or send authority.
 * Input is a durable receipt ID, never raw facts or a caller's claimed binding.
 * Historical attempts remain usable after withdrawal, cancellation and key rotation.
 */
final class PeraCRM_SES_Matcher
{
    public const MAX_PASSES=5;
    private string $deployment;
    private int $blog;
    private array $provider;
    private string $scope;
    private string $policy;
    private string $providerScope;
    private bool $busy=false;
    public function __construct(PeraCRM_SNS_Verifier $verifier, string $deployment, int $blog, array $provider)
    {
        if (isset($this->deployment)) { throw new PeraCRM_SNS_Error('sns_configuration'); }
        // Reuse the normalization configuration checks, without reading or writing.
        new PeraCRM_SES_Quarantine($verifier,$deployment,$blog,$provider);
        $this->deployment=$deployment; $this->blog=$blog;
        $ordered=[];
        foreach (['provider','account_id','region','configuration_set','configuration_version'] as $key) { $ordered[$key]=$provider[$key]; }
        $this->provider=$ordered; // strip caller-held PHP references
        $this->policy=hash('sha256',json_encode($ordered,JSON_THROW_ON_ERROR));
        $this->scope=hash('sha256',json_encode(['provider'=>'ses_sns','deployment'=>$deployment,'blog'=>$blog]+$verifier->scope(),JSON_THROW_ON_ERROR));
        // Message IDs are unique in the trusted provider namespace, across topics,
        // configuration sets and revisions. The attempt uniqueness also spans them.
        $this->providerScope=hash('sha256',json_encode(['deployment'=>$deployment,'blog'=>$blog,
            'provider'=>$provider['provider'],'account'=>$provider['account_id'],'region'=>$provider['region']],JSON_THROW_ON_ERROR));
    }
    public function __debugInfo(): array { return ['redacted'=>true]; }
    public function __serialize(): array { throw new LogicException('matching_serialization_disabled'); }
    public function __unserialize(array $data): void { throw new LogicException('matching_serialization_disabled'); }
    private static function db($result)
    {
        global $wpdb;
        if ($result===false || $wpdb->last_error!=='') { throw new PeraCRM_SNS_Error('sns_storage'); }
        return $result;
    }
    private function insert(string $name, array $data, string $guard, int $connection): void
    {
        global $wpdb;
        $table=peracrm_table($name); $columns=implode(',',array_keys($data));
        $values=implode(',',array_fill(0,count($data),'%s')); $noop=array_key_first($data);
        self::db($wpdb->query($wpdb->prepare("INSERT INTO {$table} ({$columns}) SELECT {$values}
            WHERE CONNECTION_ID()=%d AND @peracrm_ses_matching_guard=%s
            ON DUPLICATE KEY UPDATE {$noop}={$noop}",array_merge(array_values($data),[$connection,$guard]))));
        if ((int)self::db($wpdb->get_var('SELECT CONNECTION_ID()'))!==$connection) { throw new PeraCRM_SNS_Error('sns_storage'); }
    }
    /** Pure comparison against a hash-verified immutable storage projection. */
    public static function bindingDecision(array $fact, array $attempt, array $provider, int $blog): string
    {
        if (($attempt['version']??null)!==1 || ($attempt['blog_id']??null)!==$blog
            || ($attempt['personalization_version']??null)!==1) { return 'binding_conflict'; }
        foreach ($provider as $key=>$value) {
            if (($attempt['scope'][$key]??null)!==$value) { return 'wrong_scope'; }
        }
        foreach (['account'=>'account_id','region'=>'region','configuration_set'=>'configuration_set'] as $key=>$field) {
            if (($fact[$key]??null)!==$provider[$field]) { return 'wrong_scope'; }
        }
        foreach (['delivery'=>'delivery_key','attempt'=>'attempt_key','snapshot'=>'snapshot_hash','envelope'=>'envelope_hash'] as $tag=>$field) {
            if (!is_string($attempt[$field]??null) || preg_match('/\A[a-f0-9]{64}\z/',$attempt[$field])!==1
                || ($fact['tags'][$tag]??null)!==$attempt[$field]) { return 'binding_conflict'; }
        }
        $mailbox=$attempt['mailbox']??null;
        if (!peracrm_campaign_submission_mailbox($mailbox) || strtolower($mailbox)!==$mailbox
            || ($fact['recipient']??null)!==$mailbox || ($fact['destinations']??null)!==[$mailbox]) { return 'wrong_recipient'; }
        return 'matched';
    }
    private function correlate(array $fact, string $event, string $receipt, string $guard, int $connection): array
    {
        global $wpdb;
        $attempts=peracrm_table('peracrm_campaign_submission_attempts');
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$attempts} WHERE deployment=%s AND blog_id=%d AND attempt_key=%s FOR UPDATE",
            $this->deployment,$this->blog,$fact['tags']['attempt']),ARRAY_A);
        self::db(true);
        if (!$row) { return ['missing_binding','']; }
        if (!hash_equals($row['binding_hash'],hash('sha256',$row['binding_json']))) { throw new PeraCRM_SNS_Error('sns_storage'); }
        $attempt=json_decode($row['binding_json'],true,16,JSON_THROW_ON_ERROR);
        $decision=self::bindingDecision($fact,$attempt,$this->provider,$this->blog);
        if ($row['attempt_key']!==($attempt['attempt_key']??null) || $row['delivery_key']!==($attempt['delivery_key']??null)) { $decision='binding_conflict'; }
        if ($decision!=='matched') { return [$decision,$row['binding_hash']]; }
        $messages=peracrm_table('peracrm_campaign_provider_messages');
        $data=['provider_scope'=>$this->providerScope,'message_id'=>$fact['message_id'],'deployment'=>$this->deployment,
            'blog_id'=>$this->blog,'attempt_key'=>$row['attempt_key'],'binding_hash'=>$row['binding_hash'],
            'event_scope'=>$this->scope,'event_key'=>$event,'receipt_id'=>$receipt,'recorded_at'=>gmdate('Y-m-d H:i:s')];
        $insert=fn($name,$values)=>$this->insert($name,$values,$guard,$connection);
        $decision=PeraCRM_Campaign_Acceptance::associate($data,$insert);
        PeraCRM_Campaign_Acceptance::evidence($data,'event',hash('sha256',$this->scope.$event),$decision,$insert);
        return [$decision==='accepted'?'matched':$decision,$row['binding_hash']];
    }
    /** Explicit bounded redrive. Only missing bindings may be revisited, at most
     * five passes total per logical event, including duplicates across receipts.
     * No scan, scheduling, event application or automatic worker retry.
     */
    public function reprocess(string $receipt): array { return $this->match($receipt,true); }
    public function match(string $id, bool $reprocess=false): array
    {
        if (preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/',$id)!==1) { throw new PeraCRM_SNS_Error('sns_invalid'); }
        global $wpdb;
        $started=false; $owned=false; $previous=$wpdb->suppress_errors(true);
        try {
            if ($this->busy || get_current_blog_id()!==$this->blog
                || (is_multisite() && (int)peracrm_get_target_blog_id()!==$this->blog)
                || !peracrm_campaigns_schema_ready() || (int)self::db($wpdb->get_var('SELECT @@autocommit'))!==1) { throw new PeraCRM_SNS_Error('sns_storage'); }
            self::db($wpdb->query('SAVEPOINT peracrm_match_top_level_probe'));
            if ($wpdb->query('RELEASE SAVEPOINT peracrm_match_top_level_probe')!==false
                || $wpdb->last_error!=='SAVEPOINT peracrm_match_top_level_probe does not exist') { throw new PeraCRM_SNS_Error('sns_storage'); }
            self::db($wpdb->query('START TRANSACTION')); $started=true; $owned=true; $this->busy=true;
            self::db($wpdb->query('SAVEPOINT peracrm_match_owned_transaction'));
            $connection=(int)self::db($wpdb->get_var('SELECT CONNECTION_ID()'));
            if ($connection<=0) { throw new PeraCRM_SNS_Error('sns_storage'); }
            self::db($wpdb->query('RELEASE SAVEPOINT peracrm_match_owned_transaction'));
            $guard=bin2hex(random_bytes(32));
            self::db($wpdb->query($wpdb->prepare('SET @peracrm_ses_matching_guard=IF(CONNECTION_ID()=%d,%s,NULL)',$connection,$guard)));
            $receipts=peracrm_table('peracrm_campaign_sns_receipts'); $links=peracrm_table('peracrm_campaign_ses_normalizations');
            $receipt=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$receipts} WHERE scope_hash=%s AND message_id=%s FOR UPDATE",$this->scope,$id),ARRAY_A);
            self::db($receipt?:false);
            $link=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$links} WHERE scope_hash=%s AND receipt_id=%s FOR UPDATE",$this->scope,$id),ARRAY_A);
            self::db($link?:false);
            if ($receipt['authentication_version']!=='sns-v2-sha256' || $receipt['quarantine_reason']!=='application_pending'
                || !hash_equals($receipt['payload_hash'],hash('sha256',$receipt['canonical_payload']))
                || $link['payload_hash']!==$receipt['payload_hash'] || $link['normalization_version']!=='ses-v1'
                || $link['policy_hash']!==$this->policy || $link['quarantine_reason']!=='matching_pending') { throw new PeraCRM_SNS_Error('sns_storage'); }
            $keys=json_decode($link['event_keys_json'],true,8,JSON_THROW_ON_ERROR);
            if (!is_array($keys) || !array_is_list($keys) || count($keys)<1 || count($keys)>50 || count(array_unique($keys))!==count($keys)) { throw new PeraCRM_SNS_Error('sns_storage'); }
            sort($keys,SORT_STRING); $result=[];
            $events=peracrm_table('peracrm_campaign_ses_events'); $matches=peracrm_table('peracrm_campaign_ses_matches');
            foreach ($keys as $key) {
                if (!is_string($key) || preg_match('/\A[a-f0-9]{64}\z/',$key)!==1) { throw new PeraCRM_SNS_Error('sns_storage'); }
                // Logical fact lock serializes duplicate SNS IDs before decisions.
                $event=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$events} WHERE scope_hash=%s AND event_key=%s FOR UPDATE",$this->scope,$key),ARRAY_A);
                self::db($event?:false);
                if (!hash_equals($event['fact_hash'],hash('sha256',$event['fact_json']))) { throw new PeraCRM_SNS_Error('sns_storage'); }
                $prior=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$matches} WHERE scope_hash=%s AND event_key=%s ORDER BY sequence DESC LIMIT 1 FOR UPDATE",$this->scope,$key),ARRAY_A);
                self::db(true);
                if ($prior && ($prior['fact_hash']!==$event['fact_hash'] || ((int)$prior['sequence']<1 || (int)$prior['sequence']>self::MAX_PASSES))) { throw new PeraCRM_SNS_Error('sns_storage'); }
                if ($prior && (!$reprocess || $prior['decision']!=='missing_binding' || (int)$prior['sequence']>=self::MAX_PASSES)) {
                    if ($prior['decision']==='matched') {
                        // Additive evidence backfill for pre-v34 matched associations;
                        // do not replace historical matching decisions or provenance.
                        $fact=json_decode($event['fact_json'],true,16,JSON_THROW_ON_ERROR);
                        [$verified]=$this->correlate($fact,$key,$id,$guard,$connection);
                        if ($verified!=='matched') { throw new PeraCRM_SNS_Error('sns_storage'); }
                    }
                    $result[$key]=$prior['decision']; continue;
                }
                $fact=json_decode($event['fact_json'],true,16,JSON_THROW_ON_ERROR);
                [$decision,$binding]=$this->correlate($fact,$key,$id,$guard,$connection);
                $sequence=$prior?(int)$prior['sequence']+1:1;
                $data=['scope_hash'=>$this->scope,'event_key'=>$key,'sequence'=>$sequence,'receipt_id'=>$id,
                    'fact_hash'=>$event['fact_hash'],'binding_hash'=>$binding,'decision'=>$decision,'recorded_at'=>gmdate('Y-m-d H:i:s')];
                $this->insert('peracrm_campaign_ses_matches',$data,$guard,$connection);
                $saved=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$matches} WHERE scope_hash=%s AND event_key=%s AND sequence=%d FOR UPDATE",$this->scope,$key,$sequence),ARRAY_A);
                self::db($saved?:false);
                foreach ($data as $column=>$value) {
                    if ($column!=='recorded_at' && (string)$saved[$column] !== (string)$value) { throw new PeraCRM_SNS_Error('sns_storage'); }
                }
                $result[$key]=$decision;
            }
            if ((int)self::db($wpdb->get_var('SELECT CONNECTION_ID()'))!==$connection) { throw new PeraCRM_SNS_Error('sns_storage'); }
            self::db($wpdb->query('COMMIT')); $started=false;
            if ((int)self::db($wpdb->get_var('SELECT CONNECTION_ID()'))!==$connection) { throw new PeraCRM_SNS_Error('sns_storage'); }
            return $result;
        } catch (Throwable $error) {
            if ($started) { $wpdb->query('ROLLBACK'); }
            throw new PeraCRM_SNS_Error('sns_storage');
        } finally {
            if ($owned) { $this->busy=false; }
            $wpdb->suppress_errors($previous);
        }
    }
}
