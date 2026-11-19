<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/deployment.php';
require_once __DIR__.'/worker.php';
require_once __DIR__.'/assessment.php';
require_once __DIR__.'/ses-operator.php';
require_once __DIR__.'/providers/ses-sdk-transport.php';
require_once __DIR__.'/recipient-preferences.php';

/** Explicit, nonsecret activation pins. Merely installing this file grants no authority. */
function peracrm_campaign_production_configuration(?PeraCRM_Campaign_Deployment $deployment=null): ?array
{
    if (!defined('PERACRM_CAMPAIGN_DISPATCH')) { return null; }
    $c=PERACRM_CAMPAIGN_DISPATCH;
    if (!is_array($c)) { throw new RuntimeException('production_configuration'); }
    $keys=array_keys($c); sort($keys);
    if ($keys!==['credentials_file','daily_cap','deployment_fingerprint','enabled','job_ids','per_second_cap','principal_arn','sender_email']
        || !is_bool($c['enabled']) || !is_int($c['per_second_cap']) || $c['per_second_cap']<1 || $c['per_second_cap']>1000
        || !is_int($c['daily_cap']) || $c['daily_cap']<1 || $c['daily_cap']>10000000
        || !is_array($c['job_ids']) || !array_is_list($c['job_ids']) || count($c['job_ids'])>25
        || ($c['enabled'] && !$c['job_ids'])
        || count(array_unique($c['job_ids'],SORT_REGULAR))!==count($c['job_ids'])
        || array_filter($c['job_ids'],static fn($id)=>!is_int($id) || $id<1)
        || !is_string($c['sender_email']) || !filter_var($c['sender_email'],FILTER_VALIDATE_EMAIL)
        || strtolower($c['sender_email'])!==$c['sender_email']
        || !is_string($c['credentials_file']) || strlen($c['credentials_file'])>512 || !str_starts_with($c['credentials_file'],'/')
        || !is_string($c['deployment_fingerprint']) || preg_match('/\A[a-f0-9]{64}\z/',$c['deployment_fingerprint'])!==1) {
        throw new RuntimeException('production_configuration');
    }
    $deployment=$deployment??peracrm_campaign_deployment_service();
    if (!$deployment || !hash_equals($deployment->fingerprint(),$c['deployment_fingerprint'])) { throw new RuntimeException('production_scope'); }
    $account=$deployment->binding()['account_id'];
    if (!is_string($c['principal_arn']) || preg_match('~\Aarn:aws:iam::'.$account.':(?:user|role)/[A-Za-z0-9+=,.@_/-]{1,256}\z~',$c['principal_arn'])!==1) {
        throw new RuntimeException('production_principal');
    }
    return $c;
}

/** Verification endpoints are independent of CLI credentials and dispatch. */
function peracrm_campaign_production_endpoints(?PeraCRM_Campaign_Deployment $deployment=null): bool
{
    if (!defined('PERACRM_CAMPAIGN_PUBLIC_ENDPOINTS')) { return false; }
    try {
        $c=PERACRM_CAMPAIGN_PUBLIC_ENDPOINTS;
        if (!is_array($c) || count($c)!==2 || ($c['enabled']??null)!==true
            || !is_string($c['deployment_fingerprint']??null)) { return false; }
        $deployment=$deployment??peracrm_campaign_deployment_service();
        return $deployment && hash_equals($deployment->fingerprint(),$c['deployment_fingerprint']);
    } catch (Throwable $e) { return false; }
}

/** Local pins only; no network, credentials or token writes. */
function peracrm_campaign_production_http_ready(PeraCRM_Campaign_Deployment $deployment): bool
{
    try {
        if (!peracrm_campaign_production_endpoints($deployment)
            || !peracrm_campaign_recipient_withdrawal_service() || !peracrm_campaign_recipient_preferences_service()
            || !peracrm_sns_http_service() || !defined('PERACRM_SES_EVENT_OPERATIONS')) { return false; }
        $b=$deployment->binding();
        foreach (['account'=>'account_id','blog'=>'blog_id','deployment'=>'deployment','region'=>'region',
            'configuration_set'=>'configuration_set','configuration_version'=>'configuration_version'] as $key=>$field) {
            if ((PERACRM_SES_SNS_HTTP[$key]??null)!==$b[$field]
                || (PERACRM_SES_EVENT_OPERATIONS[$key]??null)!==$b[$field]) { return false; }
        }
        return (PERACRM_SES_SNS_HTTP['topic']??null)===(PERACRM_SES_EVENT_OPERATIONS['topic']??null)
            && !(defined('PERACRM_SES_SNS_PROVISIONING') && (PERACRM_SES_SNS_PROVISIONING['enabled']??false));
    } catch (Throwable $e) { return false; }
}

/** Read a protected, explicit credential file only in an authorized CLI process.
 * No ambient AWS chain, web lookup, fallback identity, logging or serialized secrets. */
function peracrm_campaign_production_deployment(): ?PeraCRM_Campaign_Deployment
{
    if (!defined('PERACRM_CAMPAIGN_DISPATCH')) { return null; }
    PeraCRM_SES_Operator::authorize();
    $base=peracrm_campaign_deployment_service();
    $c=peracrm_campaign_production_configuration($base);
    if (!$base || !$c) { throw new RuntimeException('production_configuration'); }
    $path=@realpath($c['credentials_file']); $public=realpath(ABSPATH);
    if (!$path || !$public || is_link($c['credentials_file']) || !is_file($path)
        || $path===$public || str_starts_with($path,$public.DIRECTORY_SEPARATOR)
        || (fileperms($path)&0077)!==0 || (fileperms(dirname($path))&0077)!==0
        || (function_exists('posix_geteuid') && fileowner($path)!==posix_geteuid())) {
        throw new RuntimeException('production_identity_file');
    }
    $handle=@fopen($path,'rb');
    if (!$handle) { throw new RuntimeException('production_identity_file'); }
    try {
        $stat=fstat($handle);
        if (!$stat || $stat['size']<2 || $stat['size']>8192 || ($stat['mode']&0077)!==0
            || ($stat['mode']&0170000)!==0100000
            || (function_exists('posix_geteuid') && $stat['uid']!==posix_geteuid())
            || $stat['ino']!==fileinode($path) || $stat['dev']!==(stat($path)['dev']??null)) { throw new RuntimeException('production_identity_file'); }
        $raw=stream_get_contents($handle,8193);
    } finally { fclose($handle); }
    try {
        $s=json_decode($raw,true,8,JSON_THROW_ON_ERROR);
        if (isset($s['Credentials'])) { $s=$s['Credentials']; }
        if (!is_array($s) || !is_string($s['Expiration']??null)) { throw new RuntimeException(); }
        $expires=strtotime($s['Expiration']);
        if (!$expires || $expires<=time()+60) { throw new RuntimeException(); }
        return $base->withProductionIdentity(['account_id'=>$base->binding()['account_id'],'version'=>PERACRM_CAMPAIGN_DEPLOYMENT['identity_version'],
            'access_key'=>$s['AccessKeyId']??null,'secret_key'=>$s['SecretAccessKey']??null,
            'session_token'=>$s['SessionToken']??null,'not_before'=>time()-60,'expires_at'=>$expires]);
    } catch (Throwable $e) { throw new RuntimeException('production_identity'); }
    finally { unset($raw,$s); }
}

final class PeraCRM_Campaign_Production_Worker extends PeraCRM_Campaign_Worker
{
    public function __construct(private PeraCRM_Campaign_Deployment $deployment,private array $jobs,private Closure $authority) {}
    protected function authorize(): void { ($this->authority)(); }
    protected function gate(): bool { $this->authorize(); return peracrm_campaign_queue_dispatch_allowed(); }
    protected function acceptanceDeployment(): string { return $this->deployment->binding()['deployment']; }
    protected function table(string $kind): string
    {
        $this->authorize();
        return peracrm_table(match($kind) {'jobs'=>'peracrm_campaign_queue_jobs','deliveries'=>'peracrm_campaign_deliveries',
            'events'=>'peracrm_campaign_worker_events',default=>throw new RuntimeException('production_table')});
    }
    protected function job(int $id): array
    {
        if (!in_array($id,$this->jobs,true)) { throw new RuntimeException('production_job'); }
        return parent::job($id);
    }
    protected function eligibility(array $job,array $row): string
    {
        $reason=parent::eligibility($job,$row);
        // Production can never submit the reserved fixture addresses used during setup.
        if ($reason==='eligible' && preg_match('/@(?:[^@]*\.)?(?:test|invalid|localhost|example\.(?:com|net|org))\z/i',$row['mailbox'])) {
            return 'production_test_mailbox';
        }
        return $reason;
    }
}

/** Bounded administrator CLI execution. Public requests and native WP-Cron cannot send. */
final class PeraCRM_Campaign_Production_Runtime
{
    private array $pins;
    private string $fingerprint;
    private bool $active=false;
    private ?PeraCRM_Campaign_SES_Transport $transport;
    public function __construct(private PeraCRM_Campaign_Deployment $deployment,private array $config,
        private ?Closure $apiTransport=null,?PeraCRM_Campaign_SES_Transport $transport=null,private ?Closure $endpointCheck=null)
    {
        $this->fingerprint=$deployment->fingerprint(); $this->transport=$transport;
        $this->pins=array_intersect_key($deployment->binding(),array_flip(['account_id','region','deployment','blog_id']));
    }
    private function authorize(bool $send=false): void
    {
        PeraCRM_SES_Operator::authorize();
        if (!peracrm_campaigns_module_enabled() || !hash_equals($this->fingerprint,$this->deployment->fingerprint())
            || get_current_blog_id()!==$this->pins['blog_id']) { throw new RuntimeException('production_scope'); }
        $c=peracrm_campaign_production_configuration($this->deployment);
        if ($c!==$this->config || ($send && (!$this->active || !peracrm_campaign_queue_dispatch_allowed()))) {
            throw new RuntimeException('production_gate');
        }
        $this->deployment->credentials();
    }
    private function api(): PeraCRM_SES_Operator_API
    {
        $this->authorize();
        $principal=$this->config['principal_arn'];
        $role=substr($principal,strrpos($principal,'/')+1);
        $api=new PeraCRM_SES_Operator_API($this->pins,$role,$this->deployment->credentials(),$this->apiTransport);
        if (str_contains($principal,':user/')) { $api->attestUser($principal); } else { $api->attest(); }
        return $api;
    }
    private function budget(): PeraCRM_Sending_Budget { return $this->deployment->diagnosticBudget(); }
    /** Explicit read/deny-import operation; never sends or opens dispatch. */
    public function refresh(bool $restart=false): array
    {
        $api=$this->api(); $account=$api->quota();
        if (($account['ProductionAccessEnabled']??null)!==true || ($account['EnforcementStatus']??null)!=='HEALTHY') { throw new RuntimeException('production_sandbox'); }
        $sync=(new PeraCRM_SES_Suppression_Sync($this->pins,$api->reader()))->run(1,$restart);
        if (($sync['state']??null)!=='completed' || !empty($sync['stale'])) {
            return ['state'=>'sync_incomplete','suppression'=>$sync,'dispatch_enabled'=>peracrm_campaign_queue_dispatch_allowed()];
        }
        $observed=time();
        [$second,$daily]=PeraCRM_SES_Operator::limits($account,$this->config['per_second_cap'],$this->config['daily_cap']);
        $this->budget()->configure($second,$daily,$observed,$observed+300);
        return ['state'=>'refreshed','suppression'=>$sync,'per_second'=>$second,'daily'=>$daily,
            'expires_at'=>$observed+300,'dispatch_enabled'=>peracrm_campaign_queue_dispatch_allowed()];
    }
    private function readiness(bool $recover=false): array
    {
        $report=(new PeraCRM_Campaign_Readiness($this->deployment->binding(),null,null,$this->deployment))->assess();
        // The diagnostic snapshot is not itself an activation switch.
        return array_values(array_diff($report['blockers'],$recover
            ? ['dispatch_disabled','budget_breaker_latch:budget_pending','budget_breaker_latch:budget_breaker','budget_breaker_latch:budget_exhausted']
            : ['dispatch_disabled']));
    }
    private function endpoints(): void
    {
        if (!peracrm_campaign_production_http_ready($this->deployment)) { throw new RuntimeException('production_http'); }
        if ($this->endpointCheck) { ($this->endpointCheck)(); return; }
        foreach (['/email-unsubscribe/','/email-preferences/','/peracrm-ses-sns/'] as $path) {
            $url=home_url($path);
            // A pc1 prefix selects the recipient responder instead of the legacy preferences page.
            if ($path==='/email-preferences/') { $url=add_query_arg('token','pc1.production-probe-invalid',$url); }
            $args=['timeout'=>5,'redirection'=>0,'sslverify'=>true,'limit_response_size'=>8192];
            $response=$path==='/peracrm-ses-sns/'
                ? wp_safe_remote_post($url,$args+['headers'=>['Content-Type'=>'application/json'],'body'=>'{}'])
                : wp_safe_remote_get($url,$args);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response)!==400) {
                throw new RuntimeException('production_http');
            }
        }
    }
    private function worker(): PeraCRM_Campaign_Production_Worker
    {
        return new PeraCRM_Campaign_Production_Worker($this->deployment,$this->config['job_ids'],fn()=>$this->authorize(true));
    }
    public function run(int $job,int $limit=1,bool $recover=false): array
    {
        global $wpdb;
        $quiet=$wpdb->suppress_errors(true);
        try { return $this->execute($job,$limit,$recover); }
        finally { $wpdb->suppress_errors($quiet); }
    }
    private function execute(int $job,int $limit,bool $recover): array
    {
        $this->authorize();
        if (!peracrm_campaign_queue_dispatch_allowed()) { return ['state'=>'disabled','dispatch_enabled'=>false]; }
        if ($job<1 || !in_array($job,$this->config['job_ids'],true) || $limit<1 || $limit>25) { throw new RuntimeException('production_job'); }
        // Native non-send reads authenticate the principal and bound SES account before claims.
        $api=$this->api(); $account=$api->quota();
        if (($account['ProductionAccessEnabled']??null)!==true || ($account['EnforcementStatus']??null)!=='HEALTHY') { throw new RuntimeException('production_sandbox'); }
        $now=time(); [$second,$daily]=PeraCRM_SES_Operator::limits($account,$this->config['per_second_cap'],$this->config['daily_cap']);
        $this->budget()->configure($second,$daily,$now,$now+300);
        $blockers=$this->readiness($recover);
        if ($blockers) { return ['state'=>'blocked','error_code'=>'production_readiness','blockers'=>$blockers,'dispatch_enabled'=>true]; }
        $this->endpoints();
        global $wpdb;
        $lock='peracrm-production-'.substr(hash('sha256',$this->pins['account_id'].'|'.$this->pins['region']),0,40);
        if ((string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock))!=='1') {
            return ['state'=>'overlap','dispatch_enabled'=>true];
        }
        $this->active=true; $started=hrtime(true); $out=['state'=>'completed','job_id'=>$job,'processed'=>0,'accepted'=>0,'excluded'=>0,'recovered'=>0,'dispatch_enabled'=>true];
        try {
            $worker=$this->worker(); $preparation=$this->deployment->preparation();
            $preflight=new PeraCRM_Campaign_Worker_Preflight($this->budget(),static fn()=>time());
            if ($recover) {
                $scope=hash('sha256',json_encode(['ses-budget-v1',$this->pins['account_id'],$this->pins['region']],JSON_THROW_ON_ERROR));
                $pending=$wpdb->get_row($wpdb->prepare('SELECT pending_attempt,pending_owner FROM '
                    .peracrm_table('peracrm_campaign_budget_accounts').' WHERE scope_hash=%s',$scope),ARRAY_A);
                peracrm_campaigns_db_require($pending && $wpdb->last_error==='');
                if ($pending['pending_attempt']!=='') {
                    $claim=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_deliveries')
                        .' WHERE job_id=%d AND attempt_key=%s',$job,$pending['pending_attempt']),ARRAY_A);
                    if (!$claim || $wpdb->last_error!=='') { throw new RuntimeException('production_pending_job'); }
                    // The account latch owns the original token even after a worker result cleared its lease.
                    $claim['owner_token']=$pending['pending_owner'];
                    $worker->recoverPrepared($claim,$preparation,$preflight); $out['recovered']++;
                } else {
                    // Expired claims without a begun submission have no account latch to reconcile.
                    $rows=$worker->recover($job,min(10,$limit)); $out['recovered']=count($rows);
                }
            } else {
                $this->requireFrozenSender($job);
                $native=$this->transport??new PeraCRM_Campaign_SES_SDK_Transport($preparation->scope(),$this->deployment->credentials());
                $guard=new PeraCRM_Campaign_Production_Transport($native,$this->config['sender_email'],fn()=>$this->authorize(true));
                $mapper=new PeraCRM_Campaign_SES_Mapper($preparation->scope(),$guard);
                for ($i=0;$i<$limit;$i++) {
                    if ((hrtime(true)-$started)/1e9+20>55) { $out['state']='time_bound'; break; }
                    $this->authorize(true);
                    if ($this->readiness()) { $out['state']='blocked'; $out['error_code']='production_readiness'; break; }
                    $claims=$worker->claim($job,1);
                    if (!$claims) { break; }
                    $result=$worker->submitPrepared($claims[0],$preparation,$mapper,$preflight);
                    if (($result['state']??null)==='unknown') {
                        // One bounded nonautoload diagnostic; no request, mailbox or provider payload.
                        update_option('peracrm_campaign_transport_last_failure',[
                            'recorded_at'=>gmdate('Y-m-d H:i:s'),'job_id'=>$job,
                            'delivery_id'=>(int)$claims[0]['id'],
                            'transport'=>$native instanceof PeraCRM_Campaign_SES_SDK_Transport
                                ? $native->lastDiagnostic() : null,
                        ],false);
                    }
                    $out['processed']++; $out['accepted']+=(int)($result['state']==='accepted');
                    $out['excluded']+=(int)($result['state']==='excluded');
                    if (in_array($result['state'],['unknown','failed'],true)) { $out['state']='blocked'; break; }
                    if ($i+1<$limit) { usleep((int)ceil(2100000/$second)); }
                }
            }
            $worker->completion($job);
        } catch (Throwable $e) {
            // Claims/latches/evidence are retained. No implicit retry or accounting reset.
            $out['state']='blocked'; $code=$e->getMessage();
            $out['error_code']=preg_match('/\A(?:production|operator|deployment|budget|worker)_[a-z_]{1,64}\z/',$code)?$code:'production_execution';
        } finally {
            $this->active=false; $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));
        }
        update_option('peracrm_campaign_scheduler_last',['result'=>$out['state']==='blocked'?'blocked_execution':$out['state'],
            'invoked_at'=>gmdate('Y-m-d H:i:s'),'processed'=>$out['processed'],'accepted'=>$out['accepted'],
            'recovered'=>$out['recovered'],'jobs'=>1,'successful'=>$out['accepted']+$out['excluded']],false);
        if ($out['accepted']+$out['excluded']>0) { update_option('peracrm_campaign_scheduler_last_success',gmdate('Y-m-d H:i:s'),false); }
        return $out;
    }
    private function requireFrozenSender(int $id): void
    {
        global $wpdb;
        $job=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_queue_jobs').' WHERE id=%d',$id),ARRAY_A);
        peracrm_campaigns_db_require($job && $wpdb->last_error==='');
        peracrm_campaign_queue_binding((int)$job['campaign_id'],(int)$job['frozen_revision_id'],$job);
        $snapshot=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_content_snapshots').' WHERE id=%d',(int)$job['snapshot_id']),ARRAY_A);
        peracrm_campaigns_db_require($snapshot && $wpdb->last_error==='');
        $sender=peracrm_campaign_snapshot_decode($snapshot)['payload']['content']['sender']??[];
        if (($sender['email']??null)!==$this->config['sender_email'] || ($sender['approved']??null)!==true) {
            throw new RuntimeException('production_sender');
        }
    }
    public function scheduled(int $limit=25): array
    {
        global $wpdb;
        $quiet=$wpdb->suppress_errors(true);
        try { return $this->due($limit); }
        finally { $wpdb->suppress_errors($quiet); }
    }
    private function due(int $limit): array
    {
        if ($limit<1 || $limit>25) { throw new RuntimeException('production_limit'); }
        // Configured job IDs are an operator-reviewed allowlist, never a global queue scan.
        $this->authorize();
        if (!peracrm_campaign_queue_dispatch_allowed()) { return ['state'=>'disabled','dispatch_enabled'=>false]; }
        global $wpdb;
        foreach ($this->config['job_ids'] as $id) {
            $state=$wpdb->get_row($wpdb->prepare('SELECT state,scheduled_at,complete,submission_complete FROM '
                .peracrm_table('peracrm_campaign_queue_jobs').' WHERE id=%d',$id),ARRAY_A);
            peracrm_campaigns_db_require($wpdb->last_error==='');
            if (!$state || !in_array($state['state'],['queued','scheduled'],true) || !(int)$state['complete']
                || (int)$state['submission_complete'] || ($state['scheduled_at']!==null && strtotime($state['scheduled_at'].' UTC')>time())) { continue; }
            $claimable=$wpdb->get_var($wpdb->prepare('SELECT id FROM '.peracrm_table('peracrm_campaign_deliveries')
                    ." WHERE job_id=%d AND state IN ('held','retry') AND attempt_count<4 AND (next_attempt_at IS NULL OR next_attempt_at<=UTC_TIMESTAMP()) LIMIT 1",$id));
            peracrm_campaigns_db_require($wpdb->last_error==='');
            if (!$claimable) { continue; }
            return $this->run($id,$limit);
        }
        return ['state'=>'idle','dispatch_enabled'=>true];
    }
    public function __debugInfo(): array { return ['service'=>'production_runtime','redacted'=>true]; }
    public function __serialize(): array { throw new LogicException('production_serialization_disabled'); }
}

final class PeraCRM_Campaign_Production_Transport implements PeraCRM_Campaign_SES_Transport
{
    public function __construct(private PeraCRM_Campaign_SES_Transport $native,private string $sender,private Closure $authorize) {}
    public function send(#[SensitiveParameter] array $request,array $options): array
    {
        ($this->authorize)();
        if (!str_ends_with($request['FromEmailAddress']??'',' <'.$this->sender.'>')) { throw new RuntimeException('production_sender'); }
        return $this->native->send($request,$options);
    }
}

function peracrm_campaign_production_service(): ?PeraCRM_Campaign_Production_Runtime
{
    if (!defined('PERACRM_CAMPAIGN_DISPATCH')) { return null; }
    $deployment=peracrm_campaign_production_deployment();
    $config=peracrm_campaign_production_configuration($deployment);
    return $deployment && $config ? new PeraCRM_Campaign_Production_Runtime($deployment,$config) : null;
}
