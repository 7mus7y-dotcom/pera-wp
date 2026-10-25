<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/event-suppression.php';

/** Local durable processing only. No certificate fetch, transport or send authority. */
final class PeraCRM_SES_Processing
{
    private int $blog;
    private string $scope;
    private string $policy;
    private string $lock;
    private PeraCRM_SES_Quarantine $normalization;
    private PeraCRM_SES_Matcher $matching;
    private PeraCRM_SES_Suppression $suppression;
    private static array $active=[];
    public function __construct(PeraCRM_SNS_Verifier $verifier,string $deployment,int $blog,array $provider)
    {
        if (isset($this->scope)) { throw new PeraCRM_SNS_Error('sns_configuration'); }
        $this->normalization=new PeraCRM_SES_Quarantine($verifier,$deployment,$blog,$provider);
        $this->matching=new PeraCRM_SES_Matcher($verifier,$deployment,$blog,$provider);
        $this->suppression=new PeraCRM_SES_Suppression($verifier,$deployment,$blog,$provider);
        $this->blog=$blog;
        $this->scope=hash('sha256',json_encode(['provider'=>'ses_sns','deployment'=>$deployment,'blog'=>$blog]+$verifier->scope(),JSON_THROW_ON_ERROR));
        $ordered=[];
        foreach (['provider','account_id','region','configuration_set','configuration_version'] as $key) { $ordered[$key]=$provider[$key]; }
        $this->policy=hash('sha256',json_encode($ordered,JSON_THROW_ON_ERROR));
        $this->lock='peracrm-events-'.substr(hash('sha256',$this->scope),0,48);
    }
    public function __debugInfo(): array { return ['redacted'=>true]; }
    public function __serialize(): array { throw new LogicException('processing_serialization_disabled'); }
    public function __unserialize(array $data): void { throw new LogicException('processing_serialization_disabled'); }
    private static function db($value)
    {
        global $wpdb;
        if ($value===false || $wpdb->last_error!=='') { throw new PeraCRM_SNS_Error('sns_storage'); }
        return $value;
    }
    private function context(): void
    {
        if ($this->blog!==get_current_blog_id() || (is_multisite() && $this->blog!==(int)peracrm_get_target_blog_id())
            || !peracrm_campaigns_schema_ready()) { throw new PeraCRM_SNS_Error('sns_storage'); }
    }
    /** Session-scoped overlap guard. Each downstream stage retains its own transaction.
     * Losing workers do no writes; lost sessions cannot publish progress on reconnect.
     */
    private function locked(callable $callback,int $wait=0)
    {
        global $wpdb;
        $quiet=$wpdb->suppress_errors(true); $owned=false;
        try {
            $this->context();
            if (isset(self::$active[$this->lock]) || (int)self::db($wpdb->get_var('SELECT @@autocommit'))!==1) { throw new PeraCRM_SNS_Error('sns_storage'); }
            self::db($wpdb->query('SAVEPOINT peracrm_processing_probe'));
            if ($wpdb->query('RELEASE SAVEPOINT peracrm_processing_probe')!==false
                || $wpdb->last_error!=='SAVEPOINT peracrm_processing_probe does not exist') { throw new PeraCRM_SNS_Error('sns_storage'); }
            $connection=(int)self::db($wpdb->get_var('SELECT CONNECTION_ID()'));
            if ((string)self::db($wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,%d)',$this->lock,$wait)))!=='1') { throw new PeraCRM_SNS_Error('sns_storage'); }
            $owned=true; self::$active[$this->lock]=true;
            $this->session($connection);
            return $callback($connection);
        } finally {
            if ($owned) { unset(self::$active[$this->lock]); $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$this->lock)); }
            $wpdb->suppress_errors($quiet);
        }
    }
    private function session(int $connection): void
    {
        global $wpdb;
        $owner=self::db($wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)',$this->lock)));
        if ((int)$owner!==$connection || (int)self::db($wpdb->get_var('SELECT CONNECTION_ID()'))!==$connection) { throw new PeraCRM_SNS_Error('sns_storage'); }
    }
    private function process(string $id,bool $redrive,int $connection): string
    {
        global $wpdb;
        $this->session($connection);
        $table=peracrm_table('peracrm_campaign_ses_progress');
        // Never create a receipt, authenticate a payload, or rewrite provenance.
        self::db($wpdb->query($wpdb->prepare("INSERT INTO {$table}
            (scope_hash,receipt_id,policy_hash,state,attempts,last_attempt_at,last_redrive_at)
            SELECT %s,%s,%s,'pending',0,UTC_TIMESTAMP(),NULL
            WHERE CONNECTION_ID()=%d AND IS_USED_LOCK(%s)=%d
            ON DUPLICATE KEY UPDATE receipt_id=receipt_id",$this->scope,$id,$this->policy,$connection,$this->lock,$connection)));
        $prior=$wpdb->get_row($wpdb->prepare("SELECT policy_hash,state FROM {$table} WHERE scope_hash=%s AND receipt_id=%s",$this->scope,$id),ARRAY_A);
        self::db($prior?:false);
        if ($prior['policy_hash']!==$this->policy) { throw new PeraCRM_SNS_Error('sns_storage'); }
        // Terminal security/manual decisions cannot be released through this service.
        if (in_array($prior['state'],['conflict','quarantined','exhausted','completed'],true)) { return $prior['state']; }
        self::db($wpdb->query($wpdb->prepare("UPDATE {$table} SET state='pending',attempts=attempts+1,last_attempt_at=UTC_TIMESTAMP()
            WHERE scope_hash=%s AND receipt_id=%s AND CONNECTION_ID()=%d AND IS_USED_LOCK(%s)=%d",
            $this->scope,$id,$connection,$this->lock,$connection)));
        $this->session($connection);
        try {
            $state='quarantined';
            $normalized=$this->normalization->normalize($id);
            $this->session($connection);
            if ($normalized==='matching_pending') {
                $matches=$this->matching->match($id,$redrive);
                $this->session($connection);
                $applied=$this->suppression->apply($id);
                $this->session($connection);
                $decisions=array_values($applied);
                if (array_diff($decisions,['applied','unsupported_feedback'])) { $state='conflict'; }
                else { $state=in_array('applied',$decisions,true)?'completed':'quarantined'; }
                if (in_array('missing_binding',$decisions,true)) {
                    $m=peracrm_table('peracrm_campaign_ses_matches');
                    $exhausted=0;
                    foreach ($matches as $key=>$decision) {
                        if ($decision==='missing_binding') {
                            $passes=(int)self::db($wpdb->get_var($wpdb->prepare("SELECT MAX(sequence) FROM {$m} WHERE scope_hash=%s AND event_key=%s",$this->scope,$key)));
                            $exhausted+=($passes>=PeraCRM_SES_Matcher::MAX_PASSES);
                        }
                    }
                    $state=$exhausted?'exhausted':'waiting';
                    // A mixed receipt's permanent conflict always wins over retry eligibility.
                    if (array_diff($decisions,['applied','unsupported_feedback','missing_binding'])) { $state='conflict'; }
                }
            } elseif ($normalized==='event_conflict') { $state='conflict'; }
            $this->finish($id,$state,$redrive,$connection);
            return $state;
        } catch (Throwable $error) {
            // If our session/lock was lost, leave durable pending progress for resume.
            $this->session($connection);
            $this->finish($id,'failed',false,$connection);
            throw new PeraCRM_SNS_Error('sns_storage');
        }
    }
    private function finish(string $id,string $state,bool $redrive,int $connection): void
    {
        global $wpdb;
        $this->session($connection);
        $success=$state==='failed'?'last_success_at':'UTC_TIMESTAMP()';
        $redriveTime=$redrive?'UTC_TIMESTAMP()':'last_redrive_at';
        self::db($wpdb->query($wpdb->prepare('UPDATE '.peracrm_table('peracrm_campaign_ses_progress')." SET state=%s,last_success_at={$success},last_redrive_at={$redriveTime}
            WHERE scope_hash=%s AND receipt_id=%s AND CONNECTION_ID()=%d AND IS_USED_LOCK(%s)=%d",
            $state,$this->scope,$id,$connection,$this->lock,$connection)));
        $this->session($connection);
    }
    public function receipt(string $id): string
    {
        if (preg_match('/\A[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\z/',$id)!==1) { throw new PeraCRM_SNS_Error('sns_invalid'); }
        return $this->locked(fn($connection)=>$this->process($id,false,$connection),5);
    }
    /** Explicit one-shot invocation: at most 25 receipts / 1,250 facts, 25s work-start deadline. */
    public function redrive(int $limit): array
    {
        if ($limit<1 || $limit>25) { throw new InvalidArgumentException(); }
        return $this->locked(function ($connection) use ($limit) {
            global $wpdb;
            $r=peracrm_table('peracrm_campaign_sns_receipts'); $p=peracrm_table('peracrm_campaign_ses_progress');
            $n=peracrm_table('peracrm_campaign_ses_normalizations');
            $start=hrtime(true); $out=['result'=>'completed','processed'=>0,'completed'=>0,'waiting'=>0,'quarantined'=>0,'conflict'=>0,'exhausted'=>0,'failed'=>0];
            $ids=self::db($wpdb->get_col($wpdb->prepare("SELECT r.message_id FROM {$r} r LEFT JOIN {$p} p ON p.scope_hash=r.scope_hash AND p.receipt_id=r.message_id
                LEFT JOIN {$n} n ON n.scope_hash=r.scope_hash AND n.receipt_id=r.message_id
                WHERE r.scope_hash=%s AND r.quarantine_reason='application_pending'
                AND (n.receipt_id IS NULL OR n.quarantine_reason='matching_pending')
                AND (p.receipt_id IS NULL OR p.state IN ('pending','failed','waiting'))
                ORDER BY COALESCE(p.last_attempt_at,'1000-01-01'),r.received_at,r.message_id LIMIT %d",$this->scope,$limit)));
            foreach ($ids as $id) {
                if ((hrtime(true)-$start)/1e9>=25) { $out['result']='time_bound'; break; }
                ++$out['processed'];
                try { $state=$this->process($id,true,$connection); }
                catch (Throwable $error) { $out['result']='blocked'; $out['failed']++; break; }
                ++$out[$state];
            }
            return $out;
        });
    }
    /** SELECT-only aggregate snapshot; no raw identifiers, facts or arbitrary stored codes. */
    public function health(): array
    {
        $this->context(); global $wpdb;
        $quiet=$wpdb->suppress_errors(true);
        try {
            $r=peracrm_table('peracrm_campaign_sns_receipts'); $p=peracrm_table('peracrm_campaign_ses_progress');
            $n=peracrm_table('peracrm_campaign_ses_normalizations'); $m=peracrm_table('peracrm_campaign_ses_matches');
            $scope=$wpdb->prepare('r.scope_hash=%s',$this->scope);
            $from="{$r} r LEFT JOIN {$p} p ON p.scope_hash=r.scope_hash AND p.receipt_id=r.message_id
                LEFT JOIN {$n} n ON n.scope_hash=r.scope_hash AND n.receipt_id=r.message_id";
            $eligible="r.quarantine_reason='application_pending' AND (n.receipt_id IS NULL OR n.quarantine_reason='matching_pending')";
            $actionable="{$eligible} AND (p.receipt_id IS NULL OR p.state IN ('pending','failed','waiting'))";
            $count=static fn($where)=>(int)self::db($wpdb->get_var("SELECT COUNT(*) FROM {$from} WHERE {$scope} AND ({$where})"));
            $out=['configured'=>true,'provider_health'=>'not_observed','delivery_verified'=>false,
                'pending_processing'=>$count("{$eligible} AND (p.receipt_id IS NULL OR p.state='pending')"),
                'failed_processing'=>$count("{$eligible} AND p.state='failed'"),
                'waiting_receipts'=>$count("{$eligible} AND p.state='waiting'"),
                'manual_review_receipts'=>$count("p.state IN ('conflict','exhausted') OR n.quarantine_reason='event_conflict'"),
                'quarantined_receipts'=>$count("r.quarantine_reason<>'application_pending' OR (n.receipt_id IS NOT NULL AND n.quarantine_reason NOT IN ('matching_pending','event_conflict')) OR p.state='quarantined'"),
                'oldest_actionable_age_seconds'=>null,'last_successful_processing'=>null,'last_successful_redrive'=>null];
            $age=self::db($wpdb->get_var("SELECT MAX(GREATEST(0,TIMESTAMPDIFF(SECOND,r.received_at,UTC_TIMESTAMP()))) FROM {$from} WHERE {$scope} AND {$actionable}"));
            $out['oldest_actionable_age_seconds']=$age===null?null:(int)$age;
            $last=$wpdb->get_row($wpdb->prepare("SELECT MAX(last_success_at) AS processing,MAX(last_redrive_at) AS redrive FROM {$p} WHERE scope_hash=%s",$this->scope),ARRAY_A);
            self::db($last?:false);
            $out['last_successful_processing']=$last['processing']; $out['last_successful_redrive']=$last['redrive'];
            foreach (['unmatched_events'=>"m.decision='missing_binding'",'conflicting_events'=>"m.decision NOT IN ('missing_binding','matched')"] as $key=>$where) {
                $out[$key]=(int)self::db($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$m} m WHERE m.scope_hash=%s AND {$where}
                    AND NOT EXISTS (SELECT 1 FROM {$m} newer WHERE newer.scope_hash=m.scope_hash AND newer.event_key=m.event_key AND newer.sequence>m.sequence)",$this->scope)));
            }
            return $out;
        } finally { $wpdb->suppress_errors($quiet); }
    }
}

/** Separate deployment-only pins allow local inspection/redrive while HTTP stays disabled. */
function peracrm_ses_processing_service(): ?PeraCRM_SES_Processing
{
    if (!defined('PERACRM_SES_EVENT_OPERATIONS')) { return null; }
    $c=PERACRM_SES_EVENT_OPERATIONS;
    if (!is_array($c)) { throw new PeraCRM_SNS_Error('sns_configuration'); }
    $keys=array_keys($c); sort($keys);
    if ($keys!==['account','blog','configuration_set','configuration_version','deployment','region','topic']
        || !is_int($c['blog']) || $c['blog']!==get_current_blog_id()) { throw new PeraCRM_SNS_Error('sns_configuration'); }
    $v=new PeraCRM_SNS_Verifier(['account'=>$c['account'],'region'=>$c['region'],'topic'=>$c['topic']],static function () { throw new LogicException('no_authentication'); });
    return new PeraCRM_SES_Processing($v,$c['deployment'],$c['blog'],['provider'=>'ses_v2','account_id'=>$c['account'],
        'region'=>$c['region'],'configuration_set'=>$c['configuration_set'],'configuration_version'=>$c['configuration_version']]);
}
