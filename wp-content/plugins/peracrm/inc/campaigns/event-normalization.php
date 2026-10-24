<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/event-receipts.php';
require_once __DIR__.'/providers/ses-events.php';

/** Durable normalization prerequisite only. All results remain quarantined.
 * Reads existing authenticated receipts, never caller payloads or verified flags.
 * No attempt matching, message association, worker state or suppression authority.
 */
final class PeraCRM_SES_Quarantine
{
    private int $blog;
    private string $scope;
    private string $topic;
    private string $policy;
    private PeraCRM_SES_Event_Normalizer $normalizer;
    private bool $busy=false;
    public function __construct(PeraCRM_SNS_Verifier $verifier, string $deployment, int $blog, array $providerScope)
    {
        $this->normalizer=new PeraCRM_SES_Event_Normalizer($providerScope);
        if (preg_match('/\A[A-Za-z0-9_-]{1,64}\z/',$deployment)!==1 || $blog<=0
            || $providerScope['account_id']!==$verifier->scope()['account']
            || $providerScope['region']!==$verifier->scope()['region']) { throw new PeraCRM_SNS_Error('sns_configuration'); }
        $this->blog=$blog; $this->topic=$verifier->scope()['topic'];
        $ordered=[];
        foreach (['provider','account_id','region','configuration_set','configuration_version'] as $key) { $ordered[$key]=$providerScope[$key]; }
        $this->policy=hash('sha256',json_encode($ordered,JSON_THROW_ON_ERROR));
        $this->scope=hash('sha256',json_encode(['provider'=>'ses_sns','deployment'=>$deployment,'blog'=>$blog]+$verifier->scope(),JSON_THROW_ON_ERROR));
    }
    private static function db($result)
    {
        global $wpdb;
        if ($result===false || $wpdb->last_error!=='') { throw new PeraCRM_SNS_Error('sns_storage'); }
        return $result;
    }
    /** A valid JSON message cannot contain the raw newline-delimited MessageId
     * header inside a JSON string. Invalid/ambiguous prefixes fail closed. Validate
     * the complete canonical suffix too; never recover by searching for JSON braces.
     */
    private function message(array $receipt): string
    {
        $canonical=$receipt['canonical_payload'];
        if ($receipt['authentication_version']!=='sns-v2-sha256' || strlen($canonical)>263168
            || !hash_equals($receipt['payload_hash'],hash('sha256',$canonical))
            || !str_starts_with($canonical,"Message\n")) { throw new PeraCRM_SNS_Error('sns_storage'); }
        $marker="\nMessageId\n".$receipt['message_id']."\n";
        $offset=strpos($canonical,$marker,8);
        if ($offset===false) { throw new PeraCRM_SES_Event_Error('invalid_event'); }
        $suffix=substr($canonical,$offset+strlen($marker));
        $pattern='~\A(?:Subject\n.{0,100}\n)?Timestamp\n\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{3})?Z\nTopicArn\n'
            .preg_quote($this->topic,'~').'\nType\nNotification\n\z~sD';
        if (preg_match($pattern,$suffix)!==1) { throw new PeraCRM_SNS_Error('sns_storage'); }
        return substr($canonical,8,$offset-8);
    }
    /** Idempotent immutable insert-or-compare, on the original SQL session only.
     * A wpdb reconnect can replay a write with autocommit on. SQL session fencing
     * makes that replay a no-op, so no partial facts escape transaction rollback.
     */
    private function insert(string $table, array $data, string $guard, int $connection, array $key): array
    {
        global $wpdb;
        $columns=implode(',',array_keys($data)); $values=implode(',',array_fill(0,count($data),'%s'));
        $noop=array_key_first($key);
        self::db($wpdb->query($wpdb->prepare("INSERT INTO {$table} ({$columns}) SELECT {$values}
            WHERE CONNECTION_ID()=%d AND @peracrm_ses_normalization_guard=%s
            ON DUPLICATE KEY UPDATE {$noop}={$noop}",array_merge(array_values($data),[$connection,$guard]))));
        $where=implode(' AND ',array_map(static fn($column)=>$column.'=%s',array_keys($key)));
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE {$where} FOR UPDATE",array_values($key)),ARRAY_A);
        self::db($row?:false);
        foreach ($data as $column=>$value) {
            if ($column==='recorded_at') { continue; }
            if ((string)$row[$column] !== (string)$value) { throw new PeraCRM_SES_Event_Error('event_conflict'); }
        }
        return $row;
    }
    /** Process one durable receipt; never return applied/accepted/delivered authority. */
    public function normalize(string $id): string
    {
        if (preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/',$id)!==1) {
            throw new PeraCRM_SNS_Error('sns_invalid');
        }
        global $wpdb;
        $started=false; $owned=false; $previous=$wpdb->suppress_errors(true);
        try {
            if ($this->busy || get_current_blog_id()!==$this->blog
                || (is_multisite() && (int)peracrm_get_target_blog_id()!==$this->blog)
                || !peracrm_campaigns_schema_ready() || (int)self::db($wpdb->get_var('SELECT @@autocommit'))!==1) {
                throw new PeraCRM_SNS_Error('sns_storage');
            }
            self::db($wpdb->query('SAVEPOINT peracrm_ses_top_level_probe'));
            if ($wpdb->query('RELEASE SAVEPOINT peracrm_ses_top_level_probe')!==false) { throw new PeraCRM_SNS_Error('sns_storage'); }
            self::db($wpdb->query('START TRANSACTION')); $started=true; $this->busy=true; $owned=true;
            self::db($wpdb->query('SAVEPOINT peracrm_ses_owned_transaction'));
            $connection=(int)self::db($wpdb->get_var('SELECT CONNECTION_ID()'));
            if ($connection<=0) { throw new PeraCRM_SNS_Error('sns_storage'); }
            // Capturing a replacement session ID must not make a lost START look
            // like our transaction. A real savepoint must still belong to it.
            self::db($wpdb->query('RELEASE SAVEPOINT peracrm_ses_owned_transaction'));
            $guard=bin2hex(random_bytes(32));
            self::db($wpdb->query($wpdb->prepare('SET @peracrm_ses_normalization_guard=IF(CONNECTION_ID()=%d,%s,NULL)',$connection,$guard)));
            $receipt=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_sns_receipts')
                .' WHERE scope_hash=%s AND message_id=%s FOR UPDATE',$this->scope,$id),ARRAY_A);
            self::db($receipt?:false);
            if ($receipt['authentication_version']!=='sns-v2-sha256'
                || !hash_equals($receipt['payload_hash'],hash('sha256',$receipt['canonical_payload']))) { throw new PeraCRM_SNS_Error('sns_storage'); }
            $links=peracrm_table('peracrm_campaign_ses_normalizations');
            $key=['scope_hash'=>$this->scope,'receipt_id'=>$id];
            $prior=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$links} WHERE scope_hash=%s AND receipt_id=%s FOR UPDATE",$this->scope,$id),ARRAY_A);
            self::db(true);
            if ($prior) {
                if ($prior['payload_hash']!==$receipt['payload_hash'] || $prior['normalization_version']!=='ses-v1'
                    || $prior['policy_hash']!==$this->policy) { throw new PeraCRM_SNS_Error('sns_storage'); }
                $result=$prior['quarantine_reason'];
            } else {
                $facts=[]; $result=$receipt['quarantine_reason'];
                // Freshness quarantine is preserved. No automatic redrive of old/future receipts.
                if ($result==='application_pending') {
                    try { $facts=$this->normalizer->normalize($this->message($receipt)); $result='matching_pending'; }
                    catch (PeraCRM_SES_Event_Error $error) { $result=$error->getMessage(); }
                }
                // First compare ALL existing identities before inserting any new fact. A
                // conflicting batch is quarantined without partial candidate publication.
                $events=peracrm_table('peracrm_campaign_ses_events');
                foreach ($facts as $fact) {
                    $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$events} WHERE scope_hash=%s AND event_key=%s FOR UPDATE",$this->scope,$fact['key']),ARRAY_A);
                    self::db(true);
                    if ($row && ($row['fact_json']!==$fact['json'] || $row['fact_hash']!==hash('sha256',$fact['json']))) {
                        $facts=[]; $result='event_conflict'; break;
                    }
                }
                $now=gmdate('Y-m-d H:i:s');
                foreach ($facts as $fact) {
                    $this->insert($events,['scope_hash'=>$this->scope,'event_key'=>$fact['key'],
                        'fact_json'=>$fact['json'],'fact_hash'=>hash('sha256',$fact['json']),'recorded_at'=>$now],$guard,$connection,
                        ['scope_hash'=>$this->scope,'event_key'=>$fact['key']]);
                }
                $this->insert($links,$key+['payload_hash'=>$receipt['payload_hash'],'policy_hash'=>$this->policy,'normalization_version'=>'ses-v1',
                    'quarantine_reason'=>$result,'event_keys_json'=>json_encode(array_column($facts,'key'),JSON_THROW_ON_ERROR),
                    'recorded_at'=>$now],$guard,$connection,$key);
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
