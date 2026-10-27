<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/assessment.php';
require_once __DIR__.'/worker-content.php';
require_once __DIR__.'/worker.php';

/** Synthetic-only orchestration. Deliberately no bootstrap, hooks, factory or CLI.
 * Real internal delivery requires a separate reviewed implementation/authorization.
 */
final class PeraCRM_Campaign_Internal_Test
{
    // Reserved addresses, fixed in reviewed code; no settings/filter/request override.
    public const MAILBOXES=['internal-test-1@example.test','internal-test-2@example.test'];
    public const RECIPIENT_CAP=2;
    public const RUN_CAP=3; // lifetime per deployment/target blog, never refunded
    public const AUTH_SECONDS=300;
    private PeraCRM_Campaign_Readiness $readiness;
    private PeraCRM_Campaign_Worker $worker;
    private PeraCRM_Campaign_Worker_Preparation $preparation;
    private PeraCRM_Campaign_Worker_Preflight $preflight;
    private PeraCRM_Campaign_Internal_Test_Transport $transport;
    private Closure $clock;
    private ?array $lock=null;
    public function __construct(PeraCRM_Campaign_Readiness $readiness,PeraCRM_Campaign_Worker $worker,
        PeraCRM_Campaign_Worker_Preparation $preparation,PeraCRM_Campaign_Worker_Preflight $preflight,
        PeraCRM_Campaign_Internal_Test_Transport $transport,Closure $clock)
    {
        $this->readiness=$readiness; $this->worker=$worker; $this->preparation=$preparation;
        $this->preflight=$preflight; $this->transport=$transport; $this->clock=$clock;
    }
    private function actor(): int
    {
        $actor=peracrm_get_actor_user_id();
        if ($actor<1 || peracrm_is_impersonating_crm_user()) { throw new RuntimeException('internal_test_authority'); }
        foreach (['manage_options','peracrm_manage_campaigns','peracrm_manage_subscribers','peracrm_view_campaign_reports'] as $cap) {
            if (!user_can($actor,$cap)) { throw new RuntimeException('internal_test_authority'); }
        }
        $scope=$this->readiness->administratorScope();
        if (!$scope || $scope['blog_id']!==get_current_blog_id() || $scope['deployment']!==$this->preparation->deployment()
            || array_diff_assoc($this->preparation->scope(),$scope)) { throw new RuntimeException('internal_test_scope'); }
        $this->preflight->requireScope($this->preparation);
        return $actor;
    }
    private function now(): int
    {
        $now=($this->clock)();
        if (!is_int($now) || $now<1) { throw new RuntimeException('internal_test_clock'); }
        return $now;
    }
    private function key(): string
    {
        return 'peracrm_internal_test_v1_'.hash('sha256',json_encode([
            $this->preparation->deployment(),get_current_blog_id()],JSON_THROW_ON_ERROR));
    }
    private function ledger(): array
    {
        $value=peracrm_campaigns_schema_option($this->key(),[]);
        if (!is_array($value) || count($value)>self::RUN_CAP) { throw new RuntimeException('internal_test_storage'); }
        return $value;
    }
    private function exclusive(Closure $operation)
    {
        return peracrm_with_target_blog(function () use ($operation) {
            $this->actor();
            global $wpdb;
            peracrm_campaigns_db_require((int)$wpdb->get_var('SELECT @@autocommit')===1);
            $quiet=$wpdb->suppress_errors(true);
            try {
                peracrm_campaigns_db_require($wpdb->query('SAVEPOINT peracrm_internal_test_probe'));
                if ($wpdb->query('RELEASE SAVEPOINT peracrm_internal_test_probe')!==false
                    || $wpdb->last_error!=='SAVEPOINT peracrm_internal_test_probe does not exist') {
                    throw new RuntimeException('internal_test_transaction');
                }
            } finally { $wpdb->suppress_errors($quiet); }
            $name='peracrm-test-'.substr(hash('sha256',$wpdb->options.$this->key()),0,48);
            $connection=(int)$wpdb->get_var('SELECT CONNECTION_ID()');
            if ((string)$wpdb->get_var($wpdb->prepare('SELECT IF(IS_USED_LOCK(%s)=CONNECTION_ID(),0,GET_LOCK(%s,0))',$name,$name))!=='1') {
                throw new RuntimeException('internal_test_overlap');
            }
            $this->lock=[$name,$connection];
            try { return $operation(); }
            finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$name)); $this->lock=null; }
        });
    }
    private function requireLock(): void
    {
        global $wpdb;
        if (!$this->lock || (int)$wpdb->get_var('SELECT CONNECTION_ID()')!==$this->lock[1]
            || (int)$wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)',$this->lock[0]))!==$this->lock[1]) {
            throw new RuntimeException('internal_test_storage');
        }
    }
    /** One append-only journal document, atomically CASed; no enclosing transaction. */
    private function save(array $old,array $new): void
    {
        global $wpdb;
        $this->requireLock();
        $key=$this->key();
        if (!$old) { $ok=add_option($key,$new,'',false); }
        else {
            $ok=$wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s AND BINARY option_value=BINARY %s",
                maybe_serialize($new),$key,maybe_serialize($old)))===1;
        }
        wp_cache_delete($key,'options');
        if (!$ok) { throw new RuntimeException('internal_test_storage'); }
        $this->requireLock();
    }
    private function event(array &$run,string $event): void
    {
        $run['audit'][]=['event'=>$event,'actor'=>$this->actor(),'at'=>$this->now()];
    }
    private function fingerprint(bool $starting): string
    {
        $report=$this->readiness->assess();
        // Synthetic execution has no external provider, host, inbox or event intake.
        // Do not manufacture observations to satisfy production prerequisites.
        $required=['schema','envelope_renderer','identity_configuration','suppression_sync'];
        if ($starting) { $required[]='budget_breaker_latch'; }
        foreach ($required as $name) {
            if (($report['checks'][$name]['state']??null)!=='validated') { throw new RuntimeException('internal_test_readiness'); }
        }
        if ($report['production_ready']!==false || $report['dispatch_enabled']!==false
            || !is_string($report['configuration_fingerprint'])) { throw new RuntimeException('internal_test_readiness'); }
        return $report['configuration_fingerprint'];
    }
    private function manifest(int $job): array
    {
        $snapshot=$this->worker->internalTestSnapshot($job);
        $j=$snapshot['job']; $rows=$snapshot['rows'];
        if (count($rows)<1 || count($rows)>self::RECIPIENT_CAP || (int)$j['expected_count']!==count($rows)
            || (int)$j['prepared_count']!==count($rows) || (int)$j['complete']!==1 || (int)$j['submission_complete']!==0
            || !in_array($j['state'],['queued','scheduled'],true)) { throw new RuntimeException('internal_test_job'); }
        $seen=[];
        foreach ($rows as $row) {
            if (!in_array($row['mailbox'],self::MAILBOXES,true) || isset($seen[$row['mailbox']])
                || $row['state']!=='held' || (int)$row['attempt_count']!==0 || $row['owner_token']!==''
                || $row['lease_until']!==null) { throw new RuntimeException('internal_test_recipient'); }
            $seen[$row['mailbox']]=true;
        }
        return ['hash'=>hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR)),
            'count'=>count($rows),'ids'=>array_map(static fn($r)=>(int)$r['id'],$rows)];
    }
    /** Read-only proposal. Nonce binds job, immutable manifest and current config. */
    public function proposal(int $job): array
    {
        return peracrm_with_target_blog(function () use ($job) {
            $this->actor();
            if ($job<1) { throw new InvalidArgumentException('internal_test_job'); }
            $manifest=$this->manifest($job); $fingerprint=$this->fingerprint(true);
            $binding=hash('sha256',json_encode([$job,$manifest,$fingerprint],JSON_THROW_ON_ERROR));
            return ['binding'=>$binding,'count'=>$manifest['count'],'mode'=>'synthetic_only',
                'production_ready'=>false,'inbox_verified'=>false];
        });
    }
    public function authorize(int $job,string $binding,string $nonce,string $confirmation): string
    {
        return $this->exclusive(function () use ($job,$binding,$nonce,$confirmation) {
            $actor=$this->actor();
            if ($confirmation!=='synthetic-only' || !wp_verify_nonce($nonce,'peracrm_internal_test_'.$binding)
                || !hash_equals($this->proposal($job)['binding'],$binding)) { throw new RuntimeException('internal_test_confirmation'); }
            $old=$this->ledger();
            if (count($old)>=self::RUN_CAP) { throw new RuntimeException('internal_test_cap'); }
            foreach ($old as $run) {
                if ($run['job']===$job || in_array($run['state'],['authorized','running','interrupted'],true)) {
                    throw new RuntimeException('internal_test_duplicate');
                }
            }
            $id=bin2hex(random_bytes(16));
            $run=['job'=>$job,'binding'=>$binding,'fingerprint'=>$this->fingerprint(true),'manifest'=>$this->manifest($job),
                'actor'=>$actor,'authorized_at'=>$this->now(),'state'=>'authorized','claims'=>[],'results'=>[],'audit'=>[]];
            if (!hash_equals($binding,hash('sha256',json_encode([$job,$run['manifest'],$run['fingerprint']],JSON_THROW_ON_ERROR)))) {
                throw new RuntimeException('internal_test_stale');
            }
            $this->event($run,'authorized'); $new=$old; $new[$id]=$run; $this->save($old,$new);
            return $id;
        });
    }
    private function bound(array $run,bool $starting): void
    {
        $this->requireLock();
        if ($this->actor()!==$run['actor'] || $this->now()<$run['authorized_at']
            || $this->now()-$run['authorized_at']>=self::AUTH_SECONDS
            || !hash_equals($run['fingerprint'],$this->fingerprint($starting))) { throw new RuntimeException('internal_test_stale'); }
    }
    /** Consumes authority before claiming. No replay/resume, even after proven rejection. */
    public function execute(string $id): array
    {
        return $this->exclusive(function () use ($id) {
            $this->actor(); $old=$this->ledger(); $run=$old[$id]??null;
            if (!$run || $run['state']!=='authorized') { throw new RuntimeException('internal_test_duplicate'); }
            $this->bound($run,true);
            if ($this->manifest($run['job'])!==$run['manifest']) { throw new RuntimeException('internal_test_stale'); }
            $run['state']='running'; $this->event($run,'execution_started');
            $new=$old; $new[$id]=$run; $this->save($old,$new);
            // Every progress CAS precedes the next side effect. Failed progress
            // leaves running/interrupted authority consumed, never a retry permit.
            for ($i=0;$i<$run['manifest']['count'];$i++) {
                $this->bound($run,$i===0);
                $claims=$this->worker->claim($run['job'],1);
                if (count($claims)!==1 || !in_array((int)$claims[0]['id'],$run['manifest']['ids'],true)
                    || !in_array($claims[0]['mailbox'],self::MAILBOXES,true)) { throw new RuntimeException('internal_test_claim'); }
                $claim=$claims[0]; $run['claims'][]=$claim; $this->event($run,'claimed');
                $old=$new; $new[$id]=$run; $this->save($old,$new);
                $mapper=new PeraCRM_Campaign_SES_Mapper($this->preparation->scope(),
                    $this->transport->guarded(function () use ($run) { $this->bound($run,false); }));
                $result=$this->worker->submitPrepared($claim,$this->preparation,$mapper,$this->preflight);
                $run['results'][]=['id'=>(int)$result['id'],'state'=>$result['state']];
                $this->event($run,'outcome_recorded');
                $old=$new; $new[$id]=$run; $this->save($old,$new);
                if ($result['state']!=='accepted') { break; }
            }
            $run['state']='completed'; $this->event($run,'execution_completed');
            $old=$new; $new[$id]=$run; $this->save($old,$new);
            return ['mode'=>'synthetic_only','results'=>$run['results'],'inbox_verified'=>false,'production_ready'=>false];
        });
    }
    /** Explicit recovery nonce; expiry is not renewed into execution authority.
     * Existing worker recovery requires original owner, expired lease, current
     * sync/quota/readiness and durable unknown fencing before latch release.
     */
    public function recover(string $id,string $nonce): void
    {
        $this->exclusive(function () use ($id,$nonce) {
            $this->actor();
            if (!wp_verify_nonce($nonce,'peracrm_internal_test_recover_'.$id)) { throw new RuntimeException('internal_test_confirmation'); }
            $this->fingerprint(false);
            $old=$this->ledger(); $run=$old[$id]??null;
            if (!$run || !in_array($run['state'],['authorized','running','interrupted'],true)) { throw new RuntimeException('internal_test_recovery'); }
            $new=$old;
            if ($run['state']!=='interrupted') {
                $run['state']='interrupted'; $this->event($run,'recovery_started');
                $new[$id]=$run; $this->save($old,$new);
            }
            foreach ($run['claims'] as $claim) {
                // A pending latch identifies the original claim; never guess or
                // consume a never-begun reservation using accounting recovery.
                try { $this->preflight->requireRecoveryReadiness($claim); }
                catch (PeraCRM_Sending_Budget_Error $e) {
                    if ($e->getMessage()!=='budget_attempt') { throw $e; }
                    continue;
                }
                $this->worker->recoverPrepared($claim,$this->preparation,$this->preflight);
            }
            if ($this->preflight->hasPendingSubmission()) { throw new RuntimeException('internal_test_pending'); }
            $this->worker->recover($run['job'],self::RECIPIENT_CAP);
            $rows=$this->worker->internalTestSnapshot($run['job'])['rows'];
            foreach ($rows as $row) {
                if (in_array($row['state'],['claimed','inflight'],true)) { throw new RuntimeException('internal_test_lease'); }
            }
            $run['results']=array_map(static fn($row)=>['id'=>(int)$row['id'],'state'=>$row['state']],$rows);
            $run['state']='recovered'; $this->event($run,'recovery_completed');
            $old=$new; $new[$id]=$run; $this->save($old,$new);
        });
    }
    public function audit(): array
    {
        return peracrm_with_target_blog(function () {
            $this->actor(); $out=[];
            foreach ($this->ledger() as $id=>$run) {
                $out[$id]=array_intersect_key($run,array_flip(['job','actor','authorized_at','state','audit','results']));
            }
            return $out;
        });
    }
    public function __debugInfo(): array { return ['service'=>'internal_test','mode'=>'synthetic_only']; }
    public function __serialize(): array { throw new LogicException('internal_test_unavailable'); }
}

/** Final mock, no HTTP/SDK/credentials. Injected outcomes cannot select a real transport. */
final class PeraCRM_Campaign_Internal_Test_Transport implements PeraCRM_Campaign_SES_Transport
{
    private string $outcome;
    private ?Closure $guard=null;
    public int $calls=0;
    public function __construct(string $outcome='accepted')
    {
        if (!in_array($outcome,['accepted','unknown','rejected','throttled'],true)) { throw new InvalidArgumentException('internal_test_mock'); }
        $this->outcome=$outcome;
    }
    public function guarded(Closure $guard): self { $this->guard=$guard; return $this; }
    public function send(array $request,array $options): array
    {
        if (!$this->guard || count($request['Destination']['ToAddresses']??[])!==1
            || !in_array($request['Destination']['ToAddresses'][0],PeraCRM_Campaign_Internal_Test::MAILBOXES,true)) {
            throw new RuntimeException('internal_test_recipient');
        }
        ($this->guard)(); ++$this->calls;
        return match ($this->outcome) {
            'accepted'=>['complete'=>true,'authenticated'=>true,'status'=>200,'message_id'=>'synthetic-'.hash('sha256',json_encode($request['EmailTags']))],
            'rejected'=>['complete'=>true,'authenticated'=>true,'status'=>403,'error_code'=>'AccessDeniedException'],
            'throttled'=>['complete'=>true,'authenticated'=>true,'status'=>429,'error_code'=>'TooManyRequestsException'],
            default=>['complete'=>false],
        };
    }
}
