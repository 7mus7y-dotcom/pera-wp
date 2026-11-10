<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/worker.php';
require_once __DIR__.'/assessment.php';
require_once __DIR__.'/ses-operator.php';
require_once __DIR__.'/sns-http.php';
require_once __DIR__.'/deployment.php';
require_once __DIR__.'/providers/ses-operator-api.php';
require_once __DIR__.'/providers/ses-sdk-transport.php';

/** Explicit dependency, not a scheduler or production worker factory. */
final class PeraCRM_Internal_Campaign_Worker extends PeraCRM_Campaign_Worker
{
    public function __construct(private int $jobId,private string $deployment,private Closure $authority,private Closure $permit) {}
    protected function authorize(): void { ($this->authority)(); }
    protected function gate(): bool { $this->authorize(); return ($this->permit)()===true; }
    protected function acceptanceDeployment(): string { return $this->deployment; }
    protected function table(string $entity): string
    {
        $this->authorize();
        return peracrm_table(match($entity) {
            'jobs'=>'peracrm_campaign_queue_jobs','deliveries'=>'peracrm_campaign_deliveries',
            'events'=>'peracrm_campaign_worker_events',default=>throw new RuntimeException('rehearsal_table'),
        });
    }
    protected function job(int $id): array
    {
        if ($id!==$this->jobId) { throw new RuntimeException('rehearsal_job'); }
        $job=parent::job($id);
        global $wpdb;
        $rows=$wpdb->get_results($wpdb->prepare('SELECT mailbox FROM '.$this->table('deliveries').' WHERE job_id=%d LIMIT 2 FOR UPDATE',$id),ARRAY_A);
        peracrm_campaigns_db_require($wpdb->last_error==='' && is_array($rows));
        if (count($rows)!==1 || $rows[0]['mailbox']!==PeraCRM_Internal_Campaign_Runtime::MAILBOX
            || (int)$job['expected_count']!==1 || (int)$job['prepared_count']!==1) { throw new RuntimeException('rehearsal_recipient'); }
        return $job;
    }
    protected function delivery(int $job,int $id): array
    {
        if ($job!==$this->jobId) { throw new RuntimeException('rehearsal_job'); }
        $row=parent::delivery($job,$id);
        if ($row['mailbox']!==PeraCRM_Internal_Campaign_Runtime::MAILBOX) { throw new RuntimeException('rehearsal_recipient'); }
        return $row;
    }
}

/** Fixed one-recipient rehearsal. The ordinary dispatch gate is never opened. */
final class PeraCRM_Internal_Campaign_Runtime
{
    public const MAILBOX='mus7y@yahoo.co.uk';
    private const KEY='peracrm_internal_campaign_rehearsal_v1';
    private bool $active=false;
    private ?string $journal=null;
    private int $started=0;
    private int $actor;
    private string $fingerprint;

    public function __construct(private PeraCRM_Campaign_Deployment $deployment,
        private ?PeraCRM_Campaign_SES_Transport $transport=null)
    {
        $this->actor=peracrm_get_actor_user_id();
        $this->fingerprint=$deployment->fingerprint();
    }
    private function authorize(): void
    {
        PeraCRM_SES_Operator::authorize();
        $b=$this->deployment->binding();
        if (peracrm_get_actor_user_id()!==$this->actor || peracrm_campaign_queue_dispatch_allowed()
            || !peracrm_campaigns_schema_ready() || !hash_equals($this->fingerprint,$this->deployment->fingerprint())
            || get_current_blog_id()!==$b['blog_id']) { throw new RuntimeException('rehearsal_scope'); }
    }
    private function permit(): bool
    {
        if (!$this->active || !$this->journal || time()<$this->started || time()>=$this->started+120) { return false; }
        global $wpdb;
        $stored=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",self::KEY));
        return $wpdb->last_error==='' && is_string($stored) && hash_equals($this->journal,$stored);
    }
    private function worker(int $job): PeraCRM_Internal_Campaign_Worker
    {
        return new PeraCRM_Internal_Campaign_Worker($job,$this->deployment->binding()['deployment'],
            fn()=>$this->authorize(),fn()=>$this->permit());
    }
    public function proposal(int $job): array
    {
        $this->authorize();
        if ($job<1) { throw new InvalidArgumentException(); }
        $snapshot=$this->worker($job)->internalTestSnapshot($job);
        $row=$snapshot['rows'][0]??[];
        $j=$snapshot['job'];
        if (count($snapshot['rows'])!==1 || ($row['state']??null)!=='held' || (int)$row['attempt_count']!==0
            || $row['owner_token']!=='' || $row['lease_until']!==null || (int)$j['complete']!==1
            || (int)$j['submission_complete']!==0 || !in_array($j['state'],['queued','scheduled'],true)
            || (!empty($j['scheduled_at']) && strtotime($j['scheduled_at'].' UTC')>time())) {
            throw new RuntimeException('rehearsal_job');
        }
        $report=(new PeraCRM_Campaign_Readiness($this->deployment->binding(),null,null,$this->deployment))->assess();
        if (($report['checks']['identity_configuration']['state']??null)!=='validated'
            || !is_string($report['configuration_fingerprint'])) { throw new RuntimeException('rehearsal_configuration'); }
        return ['job_id'=>$job,'recipient'=>self::MAILBOX,'count'=>1,
            'binding'=>hash('sha256',json_encode([$snapshot,$this->fingerprint,$report['configuration_fingerprint']],JSON_THROW_ON_ERROR)),
            'dispatch_enabled'=>false];
    }
    public static function status(): array
    {
        PeraCRM_SES_Operator::authorize();
        global $wpdb;
        $raw=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",self::KEY));
        if ($wpdb->last_error!=='') { throw new RuntimeException('rehearsal_storage'); }
        if ($raw===null) { return ['state'=>'unused','dispatch_enabled'=>false]; }
        $row=json_decode($raw,true,8,JSON_THROW_ON_ERROR);
        if (!is_array($row)) { throw new RuntimeException('rehearsal_storage'); }
        return array_intersect_key($row,array_flip(['state','job_id','actor','started_at','finished_at','delivery_state','delivery_id']))
            +['retry_allowed'=>false,'dispatch_enabled'=>false];
    }
    public function send(int $job,string $binding): array
    {
        $this->authorize();
        if (!defined('PERACRM_CAMPAIGN_REHEARSAL') || PERACRM_CAMPAIGN_REHEARSAL!==true) { return ['state'=>'disabled']; }
        if (preg_match('/\A[a-f0-9]{64}\z/',$binding)!==1 || !hash_equals($this->proposal($job)['binding'],$binding)) {
            throw new InvalidArgumentException();
        }
        $checks=$this->deployment->checks();
        if (array_diff($checks,['validated','configured'])) { throw new RuntimeException('rehearsal_dependencies'); }
        $preparation=$this->deployment->preparation();
        $budget=$this->deployment->diagnosticBudget();
        $budget->requireDiagnosticCapacity();
        $pins=array_intersect_key($this->deployment->binding(),array_flip(['account_id','region','deployment','blog_id']));
        PeraCRM_SES_Suppression_Sync::requireComplete($pins,time(),false);
        if ($this->transport===null) {
            if (!defined('PERACRM_CAMPAIGN_WITHDRAWAL_HTTP') || PERACRM_CAMPAIGN_WITHDRAWAL_HTTP!==true
                || !peracrm_campaign_recipient_withdrawal_service() || !peracrm_sns_http_service()) {
                throw new RuntimeException('rehearsal_http_configuration');
            }
            $http=PERACRM_SES_SNS_HTTP;
            $operations=defined('PERACRM_SES_EVENT_OPERATIONS')?PERACRM_SES_EVENT_OPERATIONS:null;
            if (!is_array($http) || !is_array($operations) || ($http['topic']??null)!==($operations['topic']??null)) {
                throw new RuntimeException('rehearsal_http_configuration');
            }
            foreach (['account'=>'account_id','blog'=>'blog_id','deployment'=>'deployment','region'=>'region',
                'configuration_set'=>'configuration_set','configuration_version'=>'configuration_version'] as $key=>$field) {
                if (($http[$key]??null)!==($this->deployment->binding()[$field]??null)
                    || ($operations[$key]??null)!==($http[$key]??null)) { throw new RuntimeException('rehearsal_http_configuration'); }
            }
            $credentials=$this->deployment->credentials();
            (new PeraCRM_SES_Operator_API($pins,'PeraCRMCampaignRehearsal',$credentials))->attest();
        }
        if (!hash_equals($this->proposal($job)['binding'],$binding)) { throw new RuntimeException('rehearsal_stale'); }
        $this->consume($job,$binding);
        $this->active=true;
        try {
            $worker=$this->worker($job);
            $claims=$worker->claim($job,1);
            if (count($claims)!==1 || $claims[0]['mailbox']!==self::MAILBOX) { throw new RuntimeException('rehearsal_claim'); }
            $native=$this->transport??new PeraCRM_Campaign_SES_SDK_Transport($preparation->scope(),$this->deployment->credentials());
            $guarded=new PeraCRM_Internal_Campaign_Transport($native,fn()=>$this->authorizeSend(),$this->transport===null);
            $result=$worker->submitPrepared($claims[0],$preparation,new PeraCRM_Campaign_SES_Mapper($preparation->scope(),$guarded),
                new PeraCRM_Campaign_Worker_Preflight($budget,static fn()=>time()));
            $worker->completion($job);
            $this->finish(['state'=>'completed','delivery_id'=>(int)$result['id'],'delivery_state'=>$result['state']]);
        } catch (Throwable $error) {
            try { $this->finish(['state'=>'interrupted']); } catch (Throwable $ignored) {}
            throw new RuntimeException('rehearsal_interrupted');
        } finally { $this->active=false; }
        return self::status();
    }
    private function authorizeSend(): void
    {
        $this->authorize();
        if (!$this->permit()) { throw new RuntimeException('rehearsal_permit'); }
    }
    private function consume(int $job,string $binding): void
    {
        global $wpdb;
        if ((int)$wpdb->get_var('SELECT @@autocommit')!==1 || $wpdb->last_error!==''
            || $wpdb->query('SAVEPOINT peracrm_rehearsal_probe')===false
            || $wpdb->query('RELEASE SAVEPOINT peracrm_rehearsal_probe')!==false
            || $wpdb->last_error!=='SAVEPOINT peracrm_rehearsal_probe does not exist') { throw new RuntimeException('rehearsal_storage'); }
        $this->started=time();
        $this->journal=wp_json_encode(['state'=>'started','job_id'=>$job,'binding'=>$binding,'actor'=>$this->actor,
            'started_at'=>$this->started]);
        if (!$this->journal || $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->options} (option_name,option_value,autoload)
            VALUES (%s,%s,'no')",self::KEY,$this->journal))!==1 || $wpdb->last_error!=='') { throw new RuntimeException('rehearsal_consumed'); }
    }
    private function finish(array $outcome): void
    {
        global $wpdb;
        $next=wp_json_encode($outcome+['finished_at'=>time()]+json_decode($this->journal,true,8,JSON_THROW_ON_ERROR));
        if (!$next || $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value=%s
            WHERE option_name=%s AND BINARY option_value=%s",$next,self::KEY,$this->journal))!==1 || $wpdb->last_error!=='') {
            throw new RuntimeException('rehearsal_storage');
        }
    }
    public function __debugInfo(): array { return ['redacted'=>true]; }
    public function __serialize(): array { throw new LogicException('rehearsal_serialization_disabled'); }
}

/** Final per-request guard plus optional public unsubscribe read preflight. */
final class PeraCRM_Internal_Campaign_Transport implements PeraCRM_Campaign_SES_Transport
{
    private bool $used=false;
    public function __construct(private PeraCRM_Campaign_SES_Transport $native,private Closure $authorize,private bool $http=true) {}
    public function send(#[SensitiveParameter] array $request,array $options): array
    {
        ($this->authorize)();
        if ($this->used || ($request['Destination']??null)!==['ToAddresses'=>[PeraCRM_Internal_Campaign_Runtime::MAILBOX]]
            || preg_match('/ <info@peraproperty\.com>\z/',$request['FromEmailAddress']??'')!==1) {
            throw new RuntimeException('rehearsal_recipient');
        }
        $this->used=true;
        if ($this->http) {
            $header=$request['Content']['Simple']['Headers'][0]??[];
            $url=trim($header['Value']??'','<>');
            $parts=parse_url($url);
            if (($header['Name']??null)!=='List-Unsubscribe' || ($parts['scheme']??null)!=='https'
                || ($parts['host']??null)!=='www.peraproperty.com'
                || !in_array($parts['path']??null,['/email-unsubscribe/','/email-unsubscribe/one-click/'],true)
                || array_intersect(['port','user','pass','fragment'],array_keys($parts))) { throw new RuntimeException('rehearsal_link'); }
            // The machine route accepts POST only. Check the human confirmation
            // for this same token; GET must not withdraw permission.
            $url=str_replace('/email-unsubscribe/one-click/','/email-unsubscribe/',$url);
            $response=wp_safe_remote_get($url,['timeout'=>5,'redirection'=>0,'sslverify'=>true,'limit_response_size'=>8192]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response)!==200
                || !str_contains(wp_remote_retrieve_body($response),'Opening this page makes no changes.')) {
                throw new RuntimeException('rehearsal_link');
            }
        }
        ($this->authorize)();
        return $this->native->send($request,$options);
    }
}
