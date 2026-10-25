<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/event-matching.php';
require_once __DIR__.'/repository.php';

/** Internal, unbootstrapped application of authenticated matched feedback only.
 * No transport, consent, subscriber/identity mutation, worker or retry authority.
 */
final class PeraCRM_SES_Suppression
{
    public const POLICY='ses-suppression-v1';
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
        new PeraCRM_SES_Matcher($verifier,$deployment,$blog,$provider);
        $ordered=[];
        foreach (['provider','account_id','region','configuration_set','configuration_version'] as $key) { $ordered[$key]=$provider[$key]; }
        $this->deployment=$deployment; $this->blog=$blog; $this->provider=$ordered;
        $this->scope=hash('sha256',json_encode(['provider'=>'ses_sns','deployment'=>$deployment,'blog'=>$blog]+$verifier->scope(),JSON_THROW_ON_ERROR));
        $this->policy=hash('sha256',json_encode($ordered,JSON_THROW_ON_ERROR));
        $this->providerScope=PeraCRM_Campaign_Acceptance::namespace($deployment,$blog,$ordered);
    }
    public function __debugInfo(): array { return ['redacted'=>true]; }
    public function __serialize(): array { throw new LogicException('suppression_serialization_disabled'); }
    public function __unserialize(array $data): void { throw new LogicException('suppression_serialization_disabled'); }
    private static function db($result)
    {
        global $wpdb;
        if ($result===false || $wpdb->last_error!=='') { throw new PeraCRM_SNS_Error('sns_storage'); }
        return $result;
    }
    /** Complaint recipients are candidates. Exact single-recipient immutable
     * matching is additionally mandatory; this pure policy alone grants nothing.
     * Absent subtype is SES Complaint feedback; unknown subtypes stay quarantined.
     * not-spam is never complaint authority and never clears an existing denial.
     */
    public static function reason(array $fact): ?string
    {
        if (($fact['type']??null)==='Bounce' && ($fact['bounce_type']??null)==='Permanent') { return 'hard_bounce'; }
        if (($fact['type']??null)==='Complaint' && in_array($fact['subtype']??null,
            ['abuse','auth-failure','fraud','other','virus','unspecified'],true)) { return 'complaint'; }
        return null;
    }
    private function insert(string $name, array $data, string $guard, int $connection, bool $dedupe=true): void
    {
        global $wpdb;
        $columns=implode(',',array_keys($data)); $values=implode(',',array_fill(0,count($data),'%s'));
        $noop=array_key_first($data); $duplicate=$dedupe?" ON DUPLICATE KEY UPDATE {$noop}={$noop}":'';
        self::db($wpdb->query($wpdb->prepare('INSERT INTO '.peracrm_table($name)." ({$columns}) SELECT {$values}
            WHERE CONNECTION_ID()=%d AND @peracrm_ses_suppression_guard=%s".$duplicate,
            array_merge(array_values($data),[$connection,$guard]))));
        $this->connection($connection);
    }
    private function connection(int $expected): void
    {
        global $wpdb;
        if ((int)self::db($wpdb->get_var('SELECT CONNECTION_ID()'))!==$expected) { throw new PeraCRM_SNS_Error('sns_storage'); }
    }
    /** Load durable provenance, never a caller's JSON/verified boolean. Advisory
     * reads discover mailbox locks; all authority is reloaded with current locks.
     */
    private function facts(string $id, bool $locked): array
    {
        global $wpdb;
        $lock=$locked?' FOR UPDATE':'';
        $receipt=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_sns_receipts')
            .' WHERE scope_hash=%s AND message_id=%s'.$lock,$this->scope,$id),ARRAY_A);
        self::db($receipt?:false);
        $link=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_ses_normalizations')
            .' WHERE scope_hash=%s AND receipt_id=%s'.$lock,$this->scope,$id),ARRAY_A);
        self::db($link?:false);
        if ($receipt['authentication_version']!=='sns-v2-sha256' || $receipt['quarantine_reason']!=='application_pending'
            || !hash_equals($receipt['payload_hash'],hash('sha256',$receipt['canonical_payload']))
            || $link['payload_hash']!==$receipt['payload_hash'] || $link['normalization_version']!=='ses-v1'
            || $link['policy_hash']!==$this->policy || $link['quarantine_reason']!=='matching_pending') { throw new PeraCRM_SNS_Error('sns_storage'); }
        $keys=json_decode($link['event_keys_json'],true,8,JSON_THROW_ON_ERROR);
        if (!is_array($keys) || !array_is_list($keys) || count($keys)<1 || count($keys)>50
            || count(array_unique($keys))!==count($keys)) { throw new PeraCRM_SNS_Error('sns_storage'); }
        sort($keys,SORT_STRING); $out=[];
        foreach ($keys as $key) {
            if (!is_string($key) || preg_match('/\A[a-f0-9]{64}\z/',$key)!==1) { throw new PeraCRM_SNS_Error('sns_storage'); }
            $event=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_ses_events')
                .' WHERE scope_hash=%s AND event_key=%s'.$lock,$this->scope,$key),ARRAY_A);
            self::db($event?:false);
            if (!hash_equals($event['fact_hash'],hash('sha256',$event['fact_json']))) { throw new PeraCRM_SNS_Error('sns_storage'); }
            $out[$key]=['event'=>$event,'fact'=>json_decode($event['fact_json'],true,16,JSON_THROW_ON_ERROR)];
        }
        return $out;
    }
    private function match(string $key, string $hash, bool $locked): ?array
    {
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_ses_matches')
            .' WHERE scope_hash=%s AND event_key=%s ORDER BY sequence DESC LIMIT 1'.($locked?' FOR UPDATE':''),$this->scope,$key),ARRAY_A);
        self::db(true);
        if ($row && ($row['fact_hash']!==$hash || (int)$row['sequence']<1 || (int)$row['sequence']>PeraCRM_SES_Matcher::MAX_PASSES)) { throw new PeraCRM_SNS_Error('sns_storage'); }
        return $row;
    }
    /** No current subscriber ID lookup: denial belongs to the historical mailbox,
     * including after deletion/replacement. Association and every conflict are
     * checked under the immutable attempt lock shared with response/event writers.
     */
    private function verified(array $fact, array $match): bool
    {
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_submission_attempts')
            .' WHERE deployment=%s AND blog_id=%d AND attempt_key=%s FOR UPDATE',
            $this->deployment,$this->blog,$fact['tags']['attempt']),ARRAY_A);
        self::db($row?:false);
        if (!hash_equals($row['binding_hash'],hash('sha256',$row['binding_json'])) || $row['binding_hash']!==$match['binding_hash']) { throw new PeraCRM_SNS_Error('sns_storage'); }
        $binding=json_decode($row['binding_json'],true,16,JSON_THROW_ON_ERROR);
        if (PeraCRM_SES_Matcher::bindingDecision($fact,$binding,$this->provider,$this->blog)!=='matched'
            || $row['attempt_key']!==$binding['attempt_key'] || $row['delivery_key']!==$binding['delivery_key']) { throw new PeraCRM_SNS_Error('sns_storage'); }
        $messages=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_provider_messages')
            .' WHERE (provider_scope=%s AND message_id=%s) OR (deployment=%s AND blog_id=%d AND attempt_key=%s) FOR UPDATE',
            $this->providerScope,$fact['message_id'],$this->deployment,$this->blog,$row['attempt_key']),ARRAY_A);
        self::db($messages);
        if (count($messages)!==1) { return false; }
        foreach (['provider_scope'=>$this->providerScope,'message_id'=>$fact['message_id'],'deployment'=>$this->deployment,
            'blog_id'=>$this->blog,'attempt_key'=>$row['attempt_key'],'binding_hash'=>$row['binding_hash']] as $field=>$expected) {
            if ((string)$messages[0][$field] !== (string)$expected) { return false; }
        }
        $evidence=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_acceptance_evidence')
            .' WHERE deployment=%s AND blog_id=%d AND attempt_key=%s FOR UPDATE',$this->deployment,$this->blog,$row['attempt_key']),ARRAY_A);
        self::db($evidence);
        // Pre-v34 matched decisions/associations remain valid; newer conflicting
        // evidence can only quarantine, never strengthen suppression authority.
        foreach ($evidence as $item) {
            if ($item['decision']!=='accepted' || $item['binding_hash']!==$row['binding_hash']
                || $item['provider_scope']!==$this->providerScope || $item['message_id']!==$fact['message_id']) { return false; }
        }
        return true;
    }
    public function apply(string $id): array
    {
        if (preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/',$id)!==1) { throw new PeraCRM_SNS_Error('sns_invalid'); }
        global $wpdb;
        $started=false; $owned=false; $previous=$wpdb->suppress_errors(true);
        try {
            if ($this->busy || get_current_blog_id()!==$this->blog
                || (is_multisite() && (int)peracrm_get_target_blog_id()!==$this->blog)
                || !peracrm_campaigns_schema_ready() || (int)self::db($wpdb->get_var('SELECT @@autocommit'))!==1) { throw new PeraCRM_SNS_Error('sns_storage'); }
            self::db($wpdb->query('SAVEPOINT peracrm_suppress_top_level_probe'));
            if ($wpdb->query('RELEASE SAVEPOINT peracrm_suppress_top_level_probe')!==false
                || $wpdb->last_error!=='SAVEPOINT peracrm_suppress_top_level_probe does not exist') { throw new PeraCRM_SNS_Error('sns_storage'); }
            self::db($wpdb->query('START TRANSACTION')); $started=true; $owned=true; $this->busy=true;
            self::db($wpdb->query('SAVEPOINT peracrm_suppress_owned_transaction'));
            $connection=(int)self::db($wpdb->get_var('SELECT CONNECTION_ID()'));
            if ($connection<=0) { throw new PeraCRM_SNS_Error('sns_storage'); }
            self::db($wpdb->query('RELEASE SAVEPOINT peracrm_suppress_owned_transaction'));
            $guard=bin2hex(random_bytes(32));
            self::db($wpdb->query($wpdb->prepare('SET @peracrm_ses_suppression_guard=IF(CONNECTION_ID()=%d,%s,NULL)',$connection,$guard)));
            // Mailbox first, sorted across a bounded receipt. No event/attempt lock
            // may precede it: withdrawal/preparation also start at this mutex.
            $advisory=$this->facts($id,false); $mailboxes=[];
            foreach ($advisory as $key=>$item) {
                $match=$this->match($key,$item['event']['fact_hash'],false);
                if ($match && $match['decision']==='matched' && self::reason($item['fact'])!==null) {
                    $mailbox=$item['fact']['recipient'];
                    if (!peracrm_campaign_submission_mailbox($mailbox) || strtolower($mailbox)!==$mailbox) { throw new PeraCRM_SNS_Error('sns_storage'); }
                    $mailboxes[$mailbox]=true;
                }
            }
            ksort($mailboxes,SORT_STRING);
            $insert=fn($name,$data)=>$this->insert($name,$data,$guard,$connection);
            foreach ($mailboxes as $mailbox=>$_) { peracrm_campaigns_repository_lock_mailbox($mailbox,$insert); }
            $facts=$this->facts($id,true); $result=[];
            foreach ($facts as $key=>$item) {
                $fact=$item['fact']; $event=$item['event']; $match=$this->match($key,$event['fact_hash'],true);
                if (!$match || $match['decision']!=='matched') { $result[$key]=$match['decision']??'matching_pending'; continue; }
                $reason=self::reason($fact);
                if ($reason===null) { $result[$key]='unsupported_feedback'; continue; }
                if (!isset($mailboxes[$fact['recipient']]) || $advisory[$key]!==$item) { throw new PeraCRM_SNS_Error('sns_storage'); }
                if (!$this->verified($fact,$match)) { $result[$key]='message_conflict'; continue; }
                $now=gmdate('Y-m-d H:i:s');
                $occurred=(new DateTimeImmutable($fact['occurred_at']))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                $data=['scope_hash'=>$this->scope,'event_key'=>$key,'receipt_id'=>$id,'fact_hash'=>$event['fact_hash'],
                    'binding_hash'=>$match['binding_hash'],'provider_scope'=>$this->providerScope,'message_id'=>$fact['message_id'],
                    'mailbox'=>$fact['recipient'],'reason'=>$reason,'policy_version'=>self::POLICY,'occurred_at'=>$occurred,'recorded_at'=>$now];
                $table=peracrm_table('peracrm_campaign_ses_applications');
                $prior=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE scope_hash=%s AND event_key=%s FOR UPDATE",$this->scope,$key),ARRAY_A);
                self::db(true);
                if (!$prior) {
                    $suppression=peracrm_table('peracrm_email_suppressions');
                    // Allowlisted reason; no clearing, consent or subscriber mutation.
                    self::db($wpdb->query($wpdb->prepare("UPDATE {$suppression} SET {$reason}=1, version=version+1, updated_at=%s
                        WHERE normalized_email=%s AND {$reason}=0 AND CONNECTION_ID()=%d AND @peracrm_ses_suppression_guard=%s",
                        $now,$fact['recipient'],$connection,$guard)));
                    $this->connection($connection);
                    $this->insert('peracrm_email_suppression_events',['normalized_email'=>$fact['recipient'],'reason'=>$reason,
                        'source'=>'ses_authenticated','source_ref'=>$this->scope.':'.$key,'actor_user_id'=>0,
                        'occurred_at'=>$occurred,'recorded_at'=>$now],$guard,$connection,false);
                    $insert('peracrm_campaign_ses_applications',$data);
                    $prior=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE scope_hash=%s AND event_key=%s FOR UPDATE",$this->scope,$key),ARRAY_A);
                    self::db($prior?:false);
                }
                foreach ($data as $field=>$expected) {
                    if (!in_array($field,['receipt_id','recorded_at'],true) && (string)$prior[$field] !== (string)$expected) { throw new PeraCRM_SNS_Error('sns_storage'); }
                }
                $insert('peracrm_campaign_ses_application_receipts',['scope_hash'=>$this->scope,'receipt_id'=>$id,'event_key'=>$key,'recorded_at'=>$now]);
                $result[$key]='applied';
            }
            $this->connection($connection);
            self::db($wpdb->query('COMMIT')); $started=false;
            $this->connection($connection);
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
