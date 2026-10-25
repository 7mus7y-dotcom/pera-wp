<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/event-processing.php';

class PeraCRM_Campaign_Operations_Blocked extends RuntimeException {}

/** Read-only, target-blog queue facts. Fixture subclasses live exclusively in tests. */
class PeraCRM_Campaign_Operations
{
    private static array $active = [];
    protected function context(callable $callback)
    {
        if (is_multisite() && peracrm_get_target_blog_id()<1) { throw new PeraCRM_Campaign_Operations_Blocked('Campaign target unavailable.'); }
        return peracrm_with_target_blog(static function () use ($callback) {
            global $wpdb;
            $previous=$wpdb->suppress_errors(true);
            try { return $callback(); }
            catch (InvalidArgumentException $e) { throw $e; }
            catch (Throwable $e) { throw new PeraCRM_Campaign_Operations_Blocked('Campaign operation blocked.'); }
            finally { $wpdb->suppress_errors($previous); }
        });
    }
    protected function table(string $entity): string
    {
        return peracrm_table(['jobs'=>'peracrm_campaign_queue_jobs','deliveries'=>'peracrm_campaign_deliveries'][$entity]);
    }
    protected function metadata_key(): string { return 'peracrm_campaign_scheduler_last'; }
    protected function authorize_read(): void
    {
        // Readiness must be reportable even when the module is unavailable.
        $allowed = peracrm_with_target_blog(static function () {
            $actor = peracrm_get_actor_user_id();
            return $actor > 0 && user_can($actor, 'peracrm_view_campaign_reports') && !peracrm_is_impersonating_crm_user();
        });
        if (!$allowed) { throw new RuntimeException('Campaign report authority required.'); }
    }
    protected function ready(): bool { return peracrm_campaigns_module_enabled(); }
    protected function dispatch(): bool { return peracrm_campaign_queue_dispatch_allowed(); }
    protected function clock(): float { return hrtime(true) / 1e9; }
    protected function timestamp(): string
    {
        global $wpdb;
        $value = $wpdb->get_var('SELECT UTC_TIMESTAMP()');
        peracrm_campaigns_db_require($value ?: false); return $value;
    }
    protected function count(string $table, string $where): int
    {
        global $wpdb;
        $value = $wpdb->get_var('SELECT COUNT(*) FROM '.$table.' WHERE '.$where);
        peracrm_campaigns_db_require($value !== null); return (int)$value;
    }
    /** Due jobs have at least one currently claimable delivery, not just a cron event. */
    protected function due_where(string $now): string
    {
        global $wpdb;
        return $wpdb->prepare("j.state IN ('queued','scheduled') AND j.complete=1 AND j.submission_complete=0 AND (j.scheduled_at IS NULL OR j.scheduled_at<=%s)
            AND EXISTS (SELECT 1 FROM ".$this->table('deliveries')." d WHERE d.job_id=j.id AND d.state IN ('held','retry') AND d.attempt_count<4 AND (d.next_attempt_at IS NULL OR d.next_attempt_at<=%s))
            AND (SELECT COUNT(*) FROM ".$this->table('deliveries')." capacity WHERE capacity.job_id=j.id AND capacity.state IN ('claimed','inflight'))<10", $now, $now);
    }
    public function due(int $limit = 10): array
    {
        $this->authorize_read();
        if ($limit < 1 || $limit > 25) { throw new InvalidArgumentException('Limit must be 1–25.'); }
        return $this->context(function () use ($limit) {
            if (!$this->ready()) { throw new RuntimeException('Campaign schema unavailable.'); }
            return $this->discover($limit);
        });
    }
    protected function discover(int $limit): array
    {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT j.id,j.state,j.scheduled_at FROM '.$this->table('jobs').' j WHERE '.$this->due_where($this->timestamp()).' ORDER BY j.id LIMIT '.(int)$limit, ARRAY_A);
        peracrm_campaigns_db_require(true);
        return array_map(static function ($r) { return ['job_id'=>(int)$r['id'],'state'=>$r['state'],'scheduled_at'=>$r['scheduled_at']]; }, $rows);
    }
    /** Persisted metadata is never echoed as an arbitrary option payload. */
    protected function last_invocation(): ?array
    {
        $raw=get_option($this->metadata_key(),null);
        $allowed=['disabled','blocked_readiness','blocked_gate','blocked_execution','blocked_lock','time_bound','completed'];
        if (!is_array($raw) || !in_array($raw['result'] ?? '',$allowed,true)) { return null; }
        $out=['result'=>$raw['result'],'invoked_at'=>null];
        if (is_string($raw['invoked_at'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$raw['invoked_at'])) { $out['invoked_at']=$raw['invoked_at']; }
        foreach (['processed','accepted','recovered','jobs','successful'] as $key) { $out[$key]=max(0,min(10,(int)($raw[$key] ?? 0))); }
        return $out;
    }
    public function health(): array
    {
        $this->authorize_read();
        return $this->context(function () {
            $ready = $this->ready(); $dispatch = $this->dispatch();
            $schema=peracrm_campaigns_schema_ready();
            $module=!defined('PERACRM_CAMPAIGNS_ENABLED') || PERACRM_CAMPAIGNS_ENABLED;
            $out = ['dispatch_enabled'=>$dispatch,'schema_ready'=>$schema,'module_enabled'=>(bool)$module,'queue_ready'=>$ready,'scheduler_state'=>$dispatch ? 'never_invoked' : 'disabled',
                'last_invocation'=>$this->last_invocation(),'last_successful_processing'=>null,
                'last_provider_acceptance'=>null,'delivery_verified'=>false,'counts'=>null,
                'events'=>['configured'=>false,'provider_health'=>'not_observed','delivery_verified'=>false],
                'diagnostics'=>array_values(array_filter([$dispatch ? null : 'dispatch_disabled',$schema ? null : 'schema_unavailable',$module ? null : 'module_disabled',(!$ready && $schema && $module) ? 'readiness_blocked' : null]))];
            if ($dispatch && $out['last_invocation']) {
                $r=$out['last_invocation'];
                $out['scheduler_state']=str_starts_with($r['result'],'blocked') ? 'blocked' : (($r['successful'] ?? 0)>0 ? 'processed' : 'invoked');
            }
            if ($out['last_invocation'] && str_starts_with($out['last_invocation']['result'],'blocked')) { $out['diagnostics'][]=$out['last_invocation']['result']; }
            try { $eventService=peracrm_ses_processing_service(); if ($eventService) { $out['events']=$eventService->health(); } }
            catch (Throwable $e) { $out['events']=['configured'=>true,'error'=>'event_read_blocked','provider_health'=>'not_observed','delivery_verified'=>false]; $out['diagnostics'][]='event_read_blocked'; }
            if (!$ready) { return $out; }
            try {
                $j=$this->table('jobs'); $d=$this->table('deliveries'); $now=$this->timestamp();
                $counts=[];
                foreach (['queued','scheduled','paused','cancelled','preparing','failed'] as $state) { $counts[$state.'_jobs']=$this->count($j,"state='{$state}'"); }
                $counts['due_jobs']=$this->count($j.' j',$this->due_where($now));
                global $wpdb;
                foreach (['active'=>'>','expired'=>'<='] as $label=>$op) {
                    $counts[$label.'_leases']=$this->count($d,$wpdb->prepare("state IN ('claimed','inflight') AND lease_until {$op} %s",$now));
                }
                $counts['unknown_outcomes']=$this->count($d,"state='unknown'");
                $counts['retry_backlog']=$this->count($d,"state='retry'");
                $counts['due_retries']=$this->count($d,$wpdb->prepare("state='retry' AND attempt_count<4 AND (next_attempt_at IS NULL OR next_attempt_at<=%s)",$now));
                // Indexed descending reads; acceptance is not inbox delivery or scheduler success.
                $last=$wpdb->get_var("SELECT updated_at FROM {$d} WHERE state='accepted' ORDER BY updated_at DESC LIMIT 1");
                peracrm_campaigns_db_require(true);
                $out['last_provider_acceptance']=$last;
                $success=get_option($this->metadata_key().'_success',null);
                $out['last_successful_processing']=is_string($success) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$success) ? $success : null;
                $out['counts']=$counts;
                if ($counts['unknown_outcomes']) { $out['diagnostics'][]='unknown_requires_reconciliation'; }
                if ($counts['expired_leases']) { $out['diagnostics'][]='expired_leases'; }
            } catch (Throwable $e) { $out['scheduler_state']='blocked'; $out['diagnostics'][]='queue_read_failed'; }
            return $out;
        });
    }
    public function events(?int $limit=null): array
    {
        $this->authorize_read();
        return $this->context(function () use ($limit) {
            if ($limit!==null) {
                $actor=peracrm_get_actor_user_id();
                if ($actor<1 || !user_can($actor,'peracrm_manage_campaigns') || peracrm_is_impersonating_crm_user()) { throw new InvalidArgumentException(); }
            }
            $service=peracrm_ses_processing_service();
            if (!$service) { return ['configured'=>false,'provider_health'=>'not_observed','delivery_verified'=>false]; }
            return $limit===null?$service->health():$service->redrive($limit);
        });
    }
    /** Production has no concrete worker/provider. Gate precedes every delivery read/write. */
    public function invoke(bool $recover_only = false, array $bounds = []): array
    {
        return $this->context(function () use ($recover_only,$bounds) {
            $result=['result'=>'disabled','processed'=>0,'accepted'=>0,'recovered'=>0,'jobs'=>0,'successful'=>0];
            if (!$this->dispatch()) { return $this->record($result); }
            return $this->execute($recover_only,$bounds,$result);
        });
    }
    protected function worker(): PeraCRM_Campaign_Worker { throw new LogicException('No production worker.'); }
    protected function provider(): PeraCRM_Campaign_Provider { throw new LogicException('No production provider.'); }
    protected function lock_name(): string { return 'peracrm-scheduler-'.substr(hash('sha256',$this->table('jobs')),0,40); }
    protected function record(array $result): array
    {
        $result['invoked_at']=$this->timestamp();
        update_option($this->metadata_key(),$result,false);
        if ($result['successful'] > 0) { update_option($this->metadata_key().'_success',$result['invoked_at'],false); }
        return $result;
    }
    protected function execute(bool $recover_only, array $bounds, array $result): array
    {
        global $wpdb;
        $max=['jobs'=>3,'recipients'=>10,'seconds'=>25,'recovery'=>10];
        foreach ($bounds as $key=>$value) {
            if (!isset($max[$key]) || !is_int($value) || $value<1 || $value>$max[$key]) { throw new InvalidArgumentException('Invalid scheduler bounds.'); }
            $max[$key]=$value;
        }
        if (!$this->ready()) { $result['result']='blocked_readiness'; return $this->record($result); }
        $name=$this->lock_name();
        if (isset(self::$active[$name])) { $result['result']='overlap'; return $result; }
        $locked=$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$name));
        if ((string)$locked!=='1') { $result['result']=$locked===null ? 'blocked_lock' : 'overlap'; return $result; } // Do not overwrite the active invocation.
        self::$active[$name]=true;
        $started=$this->clock();
        try {
            $worker=$this->worker();
            // Recovery covers paused/cancelled jobs too, with worker fences and no history deletion.
            $recovery=$wpdb->get_col('SELECT DISTINCT job_id FROM '.$this->table('deliveries')." WHERE state IN ('claimed','inflight') AND lease_until<=UTC_TIMESTAMP() LIMIT ".$max['jobs']);
            peracrm_campaigns_db_require(true);
            $visited=[];
            foreach ($recovery as $id) {
                if ($this->clock()-$started >= $max['seconds'] || $result['recovered'] >= $max['recovery']) { break; }
                $visited[(int)$id]=true;
                $result['recovered']+=count($worker->recover((int)$id,$max['recovery']-$result['recovered']));
            }
            if (!$recover_only) {
                foreach ($this->discover($max['jobs']) as $job) {
                    $id=$job['job_id'];
                    if (!isset($visited[$id]) && count($visited)>=$max['jobs']) { break; }
                    $visited[$id]=true;
                    while ($result['processed']<$max['recipients'] && $this->clock()-$started+20<=$max['seconds']) {
                        if (!$this->dispatch() || !$this->ready()) { $result['result']='blocked_gate'; break 2; }
                        $rows=$worker->run($id,$this->provider(),1);
                        if (!$rows) { break; }
                        $result['processed']+=count($rows);
                        foreach ($rows as $row) {
                            $result['accepted']+=(int)($row['state']==='accepted');
                            $result['successful']+=(int)in_array($row['state'],['accepted','excluded','failed'],true);
                        }
                    }
                }
            }
            $result['jobs']=count($visited);
            if ($result['result']==='disabled') { $result['result']=$this->clock()-$started+20 > $max['seconds'] && !$recover_only ? 'time_bound' : 'completed'; }
        } catch (Throwable $e) { $result['result']='blocked_execution'; }
        finally { unset(self::$active[$name]); $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$name)); }
        return $this->record($result);
    }
}
