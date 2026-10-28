<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/operations.php';
require_once __DIR__.'/submission.php';
require_once __DIR__.'/deployment.php';

/**
 * Local diagnostic snapshot only. Explicit deployment material may be validated.
 * No credential acquisition, transport, activation, network calls or send permits.
 */
final class PeraCRM_Campaign_Readiness
{
    private ?array $binding;
    private ?PeraCRM_Sending_Budget $budget;
    private Closure $clock;
    private ?PeraCRM_Campaign_Deployment $deployment;
    private bool $deploymentRejected;
    public const EXTERNAL = [
        'dns_dkim_dmarc'=>'provider_observation',
        'iam_account_quotas'=>'provider_observation',
        'sns_dlq_monitoring'=>'provider_observation',
        'host_log_protections'=>'host_observation',
        'retention'=>'operator_attestation',
        'old_mailbox_withdrawal'=>'operator_attestation',
        'restore_verification'=>'host_observation',
    ];
    public function __construct(?array $binding,?PeraCRM_Sending_Budget $budget=null,?Closure $clock=null,
        ?PeraCRM_Campaign_Deployment $deployment=null,bool $deploymentRejected=false)
    {
        if ($binding!==null) {
            $keys=array_keys($binding); sort($keys);
            if ($keys!==['account_id','blog_id','configuration_set','configuration_version','deployment','provider','region']) {
                throw new InvalidArgumentException('readiness_scope');
            }
            $provider=array_diff_key($binding,['deployment'=>true,'blog_id'=>true]);
            PeraCRM_CampaignSubmissionV1::validateScope($provider);
            PeraCRM_SES_Suppression_HTTP::validateScope(array_intersect_key($binding,
                array_flip(['account_id','region','deployment','blog_id'])));
            $ordered=[];
            foreach (['provider','account_id','region','configuration_set','configuration_version','deployment','blog_id'] as $key) {
                $ordered[$key]=$binding[$key];
            }
            $binding=$ordered;
        }
        $this->binding=$binding; $this->budget=$budget; $this->clock=$clock??static fn()=>time();
        $this->deployment=$deployment; $this->deploymentRejected=$deploymentRejected;
        if ($deployment && $deployment->binding()!==$binding) { throw new RuntimeException('readiness_scope'); }
    }
    private function authorize(bool $write=false): int
    {
        $actor=peracrm_get_actor_user_id();
        if ($actor<1 || peracrm_is_impersonating_crm_user() || !user_can($actor,'peracrm_view_campaign_reports')
            || ($write && (!user_can($actor,'manage_options') || !user_can($actor,'peracrm_manage_campaigns')))) {
            throw new RuntimeException('readiness_authority');
        }
        return $actor;
    }
    private function context(): void
    {
        if ((is_multisite() && peracrm_get_target_blog_id()<1)
            || ($this->binding && ($this->binding['blog_id']!==get_current_blog_id()
            || (is_multisite() && $this->binding['blog_id']!==(int)peracrm_get_target_blog_id())))) {
            throw new RuntimeException('readiness_scope');
        }
    }
    private function now(): int
    {
        $now=($this->clock)();
        if (!is_int($now) || $now<1) { throw new RuntimeException('readiness_clock'); }
        return $now;
    }
    /** Reuse the approved CRM configuration projection; expose only a digest. */
    private function configuration(): array
    {
        $configuration=peracrm_campaign_configuration(true);
        if (!is_array($configuration['document']??null) || (int)($configuration['revision']??0)<1) {
            throw new RuntimeException('readiness_configuration');
        }
        $document=$configuration['document'];
        $approved=!empty($document['footer_approved']) && !empty($document['footer'])
            && (bool)array_filter($document['senders']??[],static fn($s)=>($s['approved']??false)===true);
        $parts=[$this->binding,$configuration,defined('PERACRM_SES_EVENT_OPERATIONS')?PERACRM_SES_EVENT_OPERATIONS:null];
        if ($this->deployment) { $parts[]=$this->deployment->fingerprint(); }
        return [hash('sha256',json_encode($parts,JSON_THROW_ON_ERROR)),$approved];
    }
    private function evidenceKey(string $fingerprint): string { return 'peracrm_readiness_v1_'.$fingerprint; }
    /**
     * Trusted internal recording API for the dependent administrator UI.
     * Nonce binds the exact configuration fingerprint shown to the operator.
     * Accept only a SHA-256 of separately retained, redacted evidence, never text/URLs.
     * The append-only option is the audit: actor/date/scope/provenance commit together.
     */
    public function record(string $fingerprint,string $prerequisite,string $provenance,string $digest,string $nonce): void
    {
        peracrm_with_target_blog(function () use ($fingerprint,$prerequisite,$provenance,$digest,$nonce) {
            $actor=$this->authorize(true); $this->context();
            if (!$this->binding || !isset(self::EXTERNAL[$prerequisite])
                || !in_array($provenance,['repository_mock','operator_attestation',self::EXTERNAL[$prerequisite]],true)
                || preg_match('/\A[a-f0-9]{64}\z/',$digest)!==1
                || !wp_verify_nonce($nonce,'peracrm_readiness_'.$fingerprint)
                || !hash_equals($this->configuration()[0],$fingerprint)) {
                throw new RuntimeException('readiness_evidence_rejected');
            }
            global $wpdb;
            $key=$this->evidenceKey($fingerprint);
            $old=get_option($key,[]);
            if (!is_array($old) || count($old)>=64) { throw new RuntimeException('readiness_evidence_limit'); }
            $new=$old;
            $new[]=['prerequisite'=>$prerequisite,'provenance'=>$provenance,'digest'=>$digest,
                'actor'=>$actor,'verified_at'=>$this->now(),'binding'=>$fingerprint,'event'=>'evidence_recorded'];
            if (!$old) {
                if (!add_option($key,$new,'',false)) { throw new RuntimeException('readiness_evidence_conflict'); }
            } else {
                // Compare-and-swap prevents concurrent attestations losing audit entries.
                $changed=$wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s AND BINARY option_value=BINARY %s",
                    maybe_serialize($new),$key,maybe_serialize($old)));
                wp_cache_delete($key,'options');
                if ($changed!==1) { throw new RuntimeException('readiness_evidence_conflict'); }
            }
        });
    }
    /** Administrator-only nonsecret pins; checks still come exclusively from assess(). */
    public function administratorScope(): ?array
    {
        return peracrm_with_target_blog(function () {
            $this->authorize(true); $this->context();
            return $this->binding;
        });
    }
    /** Ten configuration histories per page, newest-created binding first. Never prune. */
    public function auditPage(int $before=0): array
    {
        return peracrm_with_target_blog(function () use ($before) {
            $this->authorize(true); $this->context();
            if ($before<0) { throw new InvalidArgumentException('readiness_audit_cursor'); }
            global $wpdb;
            $prefix='peracrm_readiness_v1_';
            $rows=$wpdb->get_results($wpdb->prepare(
                "SELECT option_id,option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND (%d=0 OR option_id<%d) ORDER BY option_id DESC LIMIT 11",
                $wpdb->esc_like($prefix).'%', $before, $before),ARRAY_A);
            if ($wpdb->last_error || !is_array($rows)) { throw new RuntimeException('readiness_audit_unavailable'); }
            $groups=[];
            foreach (array_slice($rows,0,10) as $row) {
                $fingerprint=substr($row['option_name'],strlen($prefix));
                if (preg_match('/\A[a-f0-9]{64}\z/',$fingerprint)!==1) { throw new RuntimeException('readiness_audit_unavailable'); }
                $records=get_option($row['option_name'],[]);
                if (!is_array($records) || count($records)>64) { throw new RuntimeException('readiness_audit_unavailable'); }
                $safe=[];
                foreach ($records as $record) {
                    $required=self::EXTERNAL[$record['prerequisite']??'']??null;
                    if (!$required || ($record['binding']??null)!==$fingerprint
                        || !in_array($record['provenance']??null,['repository_mock','operator_attestation',$required],true)
                        || !is_string($record['digest']??null) || preg_match('/\A[a-f0-9]{64}\z/',$record['digest'])!==1
                        || !is_int($record['actor']??null) || $record['actor']<1
                        || !is_int($record['verified_at']??null) || $record['verified_at']<1
                        || ($record['event']??null)!=='evidence_recorded') { throw new RuntimeException('readiness_audit_unavailable'); }
                    $safe[]=array_intersect_key($record,array_flip(['prerequisite','provenance','digest','actor','verified_at','binding','event']));
                }
                $groups[]=['fingerprint'=>$fingerprint,'records'=>$safe];
            }
            return ['groups'=>$groups,'next'=>count($rows)>10?(int)$rows[9]['option_id']:0];
        });
    }
    public function assess(): array
    {
        return peracrm_with_target_blog(function () {
            $this->authorize(); $this->context();
            global $wpdb;
            $quiet=$wpdb->suppress_errors(true);
            try { return $this->snapshot(); }
            finally { $wpdb->suppress_errors($quiet); }
        });
    }
    private function snapshot(): array
    {
        $out=['production_ready'=>false,'dispatch_enabled'=>false,'snapshot_only'=>true,
            'scope_fingerprint'=>$this->binding?hash('sha256',json_encode($this->binding,JSON_THROW_ON_ERROR)):null,
            'configuration_fingerprint'=>null,'checks'=>[],'blockers'=>['dispatch_disabled']];
        $check=static function (string $name,string $state,string $source) use (&$out) {
            $out['checks'][$name]=['state'=>$state,'source'=>$source];
            if (!in_array($state,['validated','recorded','configured'],true)) { $out['blockers'][]=$name.':'.$state; }
        };
        $check('schema',peracrm_campaigns_schema_ready()?'validated':'unavailable','repository_runtime');
        $out['supported']=['envelopes'=>[1,2],'prepared_envelope'=>2,'renderer'=>'peracrm_email_v2','slot_version'=>1];
        $check('envelope_renderer','validated','repository_contract');
        if ($this->deployment) {
            foreach ($this->deployment->checks() as $name=>$state) { $check($name,$state,'deployment_configuration'); }
        } else {
            if ($this->deploymentRejected) { $check('deployment_scope','unavailable','deployment_configuration'); }
            foreach (['provider_identity','token_signing_keys','output_encryption_keys'] as $name) { $check($name,'unavailable','repository_runtime'); }
        }
        $fingerprint=null;
        if ($this->binding) {
            try {
                [$fingerprint,$approved]=$this->configuration();
                $out['configuration_fingerprint']=$fingerprint;
                $check('identity_configuration',$approved?'validated':'approval_missing','repository_runtime');
            } catch (Throwable $e) { $check('identity_configuration','unavailable','repository_runtime'); }
            $pins=array_intersect_key($this->binding,array_flip(['account_id','region','deployment','blog_id']));
            try {
                // Exact same validator as worker preflight, without row locks.
                PeraCRM_SES_Suppression_Sync::requireComplete($pins,$this->now(),false);
                $check('suppression_sync','validated','local_observation');
            } catch (Throwable $e) {
                $code=$e instanceof PeraCRM_SES_Sync_Error?$e->getMessage():'sync_storage';
                $check('suppression_sync',in_array($code,['sync_scope','sync_storage','sync_stale_cursor'],true)?$code:'sync_storage','local_observation');
            }
            try {
                $budget=$this->budget??($this->deployment?$this->deployment->diagnosticBudget():null);
                if (!$budget) { throw new RuntimeException(); }
                foreach ($pins as $key=>$value) { if (($budget->scope()[$key]??null)!==$value) { throw new PeraCRM_Sending_Budget_Error('budget_scope'); } }
                $health=$budget->health();
                $out['budget']=['submission_pending'=>(bool)$health['submission_pending'],
                    'quota_fresh'=>(bool)$health['quota_fresh'],'cooldown_active'=>(bool)$health['cooldown_active'],
                    'breaker'=>$health['breaker']];
                $budget->requireDiagnosticCapacity();
                $check('budget_breaker_latch','validated','local_observation');
            } catch (Throwable $e) {
                $code=$e instanceof PeraCRM_Sending_Budget_Error?$e->getMessage():'budget_unavailable';
                $check('budget_breaker_latch',in_array($code,['budget_scope','budget_readiness','budget_quota','budget_clock',
                    'budget_pending','budget_breaker','budget_exhausted','budget_storage'],true)?$code:'budget_unavailable','local_observation');
            }
        } else {
            foreach (['identity_configuration','suppression_sync','budget_breaker_latch'] as $name) { $check($name,'scope_unconfigured','repository_runtime'); }
        }
        // Existing health service owns event policy, errors and queue diagnostics.
        try {
            $service=peracrm_ses_processing_service();
            if ($service && $service->diagnosticScope()!==$this->binding) { throw new RuntimeException('readiness_scope'); }
            $events=$service?$service->health():['configured'=>false];
            $out['events']=[];
            foreach (['pending_processing','failed_processing','waiting_receipts','manual_review_receipts',
                'policy_conflicts','unmatched_events','conflicting_events'] as $key) {
                if (isset($events[$key])) {
                    $out['events'][$key]=max(0,(int)$events[$key]);
                    if ($out['events'][$key]>0) { $out['blockers'][]='events:'.$key; }
                }
            }
            $check('event_processing',empty($events['configured'])?'unconfigured':
                ((!empty($events['error']) || array_filter(array_intersect_key($events,array_flip([
                    'pending_processing','failed_processing','waiting_receipts','manual_review_receipts','policy_conflicts',
                    'unmatched_events','conflicting_events']))))?'attention_required':'validated'),'local_observation');
        } catch (Throwable $e) { $check('event_processing',$e->getMessage()==='readiness_scope'?'scope_mismatch':'unavailable','local_observation'); }
        $records=$fingerprint?get_option($this->evidenceKey($fingerprint),[]):[];
        foreach (self::EXTERNAL as $name=>$required) {
            $record=null;
            if (is_array($records)) {
                foreach ($records as $candidate) {
                    if (is_array($candidate) && ($candidate['prerequisite']??null)===$name) { $record=$candidate; }
                }
            }
            $state='unverified'; $source='none';
            if ($record && ($record['binding']??null)===$fingerprint && is_int($record['actor']??null) && $record['actor']>0
                && is_int($record['verified_at']??null) && $record['verified_at']>0 && $record['verified_at']<=$this->now()
                && preg_match('/\A[a-f0-9]{64}\z/',$record['digest']??'')===1) {
                $source=in_array($record['provenance']??null,['repository_mock','operator_attestation',$required],true)?$record['provenance']:'none';
                $state=$this->now()-$record['verified_at']>=30*86400?'stale':($source===$required?'recorded':'insufficient_evidence');
            }
            $check($name,$state,$source);
            if ($record && $state==='recorded') {
                $out['checks'][$name]['actor']=$record['actor'];
                $out['checks'][$name]['verified_at']=gmdate('Y-m-d H:i:s',$record['verified_at']);
            }
        }
        return $out;
    }
    public function __debugInfo(): array { return ['service'=>'campaign_readiness','redacted'=>true]; }
    public function __serialize(): array { throw new LogicException('readiness_projection_disabled'); }
}

/** Explicit deployment adapters, with unchanged legacy diagnostic-pin fallback.
 * A malformed explicit manifest never falls back; neither path enables dispatch.
 */
function peracrm_campaign_readiness_service(): PeraCRM_Campaign_Readiness
{
    if (defined('PERACRM_CAMPAIGN_DEPLOYMENT')) {
        try {
            $deployment=peracrm_campaign_deployment_service();
            return new PeraCRM_Campaign_Readiness($deployment->binding(),null,null,$deployment);
        } catch (Throwable $e) { return new PeraCRM_Campaign_Readiness(null,null,null,null,true); }
    }
    $binding=null;
    if (defined('PERACRM_SES_EVENT_OPERATIONS') && is_array(PERACRM_SES_EVENT_OPERATIONS)) {
        $c=PERACRM_SES_EVENT_OPERATIONS;
        $binding=['provider'=>'ses_v2','account_id'=>$c['account']??null,'region'=>$c['region']??null,
            'configuration_set'=>$c['configuration_set']??null,'configuration_version'=>$c['configuration_version']??null,
            'deployment'=>$c['deployment']??null,'blog_id'=>$c['blog']??null];
    }
    return new PeraCRM_Campaign_Readiness($binding,peracrm_sending_budget_service());
}
