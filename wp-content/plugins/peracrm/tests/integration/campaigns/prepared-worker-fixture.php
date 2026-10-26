<?php
require_once __DIR__.'/worker-fixture.php';
require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/worker-content.php';
function prepared_expect($ok,string $label): void
{
    if (!$ok) { throw new RuntimeException('FAIL prepared worker: '.$label); }
    echo 'PASS prepared worker: '.$label."\n";
}
function prepared_reject(callable $fn): void
{
    $denied=false; try { $fn(); } catch (Throwable $error) { $denied=true; }
    prepared_expect($denied,'authorization/preparation fails closed');
}
function prepared_scope(): array
{
    return ['provider'=>'ses_v2','account_id'=>'123456789012','region'=>'eu-west-1',
        'configuration_set'=>'fixture','configuration_version'=>1];
}
function prepared_service(string $deployment,bool $recovery=false): PeraCRM_Campaign_Worker_Preparation
{
    worker_fixture_target(static fn()=>update_option('worker_evidence_deployment',$deployment,false));
    prepared_setup($deployment);
    return new PeraCRM_Campaign_Worker_Preparation($deployment,prepared_scope(),
        $recovery?'invalid-origin':'https://example.org',$recovery?[]:['k1'=>str_repeat('s',32)],
        $recovery?'missing':'k1',new PeraCRM_CampaignOutputCipher(['e1'=>str_repeat('e',32)],'e1'),static fn()=>prepared_clock());
}
function prepared_clock(): int
{
    global $wpdb; return (int)$wpdb->get_var('SELECT UNIX_TIMESTAMP()');
}
function prepared_pins(string $deployment): array
{
    return ['account_id'=>prepared_scope()['account_id'],'region'=>prepared_scope()['region'],
        'deployment'=>$deployment,'blog_id'=>get_current_blog_id()];
}
function prepared_controls(): PeraCRM_Campaign_Worker_Preflight
{
    return worker_fixture_target(static function () {
        $pins=prepared_pins((string)get_option('worker_evidence_deployment'));
        return new PeraCRM_Campaign_Worker_Preflight(new PeraCRM_Sending_Budget($pins,
            static fn()=>prepared_clock(),static fn()=>true),static fn()=>prepared_clock());
    });
}
/** Disposable fixture attestation, no HTTP reader or production activation. */
function prepared_setup(string $deployment): void
{
    worker_fixture_target(static function () use ($deployment) {
        global $wpdb;
        if (get_option('prepared_preflight_deployment')===$deployment) { return; }
        $pins=prepared_pins($deployment);
        $budgetScope=hash('sha256',json_encode(['ses-budget-v1',$pins['account_id'],$pins['region']]));
        foreach (['accounts','reservations','events','guards'] as $suffix) {
            $wpdb->delete(peracrm_table('peracrm_campaign_budget_'.$suffix),['scope_hash'=>$budgetScope]);
        }
        $now=prepared_clock();
        $budget=new PeraCRM_Sending_Budget($pins,static fn()=>prepared_clock(),static fn()=>true);
        $actor=get_current_user_id(); wp_set_current_user((int)get_option('campaign_fixture_administrator'));
        try { $budget->configure(100,10000,$now,$now+3600); } finally { wp_set_current_user($actor); }
        $scope=hash('sha256',json_encode(['policy'=>'ses-account-deny-v1']+$pins));
        $run=bin2hex(random_bytes(32)); $stamp=gmdate('Y-m-d H:i:s',$now);
        $wpdb->replace(peracrm_table('peracrm_campaign_ses_sync'),['scope_hash'=>$scope,'run_id'=>$run,
            'state'=>'completed','pages'=>1,'entries'=>0,'error_code'=>'','cursor_value'=>null,
            'started_at'=>$stamp,'updated_at'=>$stamp,'last_complete_at'=>$stamp]);
        $wpdb->insert(peracrm_table('peracrm_campaign_ses_sync_pages'),['scope_hash'=>$scope,'run_id'=>$run,
            'cursor_hash'=>hash('sha256','initial'),'recorded_at'=>$stamp]);
        update_option('prepared_preflight_deployment',$deployment,false);
    });
}
function prepared_records(string $deployment): array
{
    return worker_fixture_target(static function () use ($deployment) {
        global $wpdb; $out=[];
        foreach (['recipient_tokens','submission_attempts','submission_outputs'] as $suffix) {
            $out[$suffix]=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_'.$suffix)
                .' WHERE deployment=%s ORDER BY 1',$deployment),ARRAY_A);
        }
        return $out;
    });
}
function prepared_historical_recovery(string $deployment,array $claim): void
{
    $before=worker_fixture_rows((int)$claim['job_id']); $records=prepared_records($deployment);
    worker_fixture_target(static function () use ($deployment,$claim) {
        $store=new PeraCRM_CampaignRecipientPersistence($deployment,get_current_blog_id());
        $expected=$store->attempt($claim['attempt_key']);
        $binding=['blog_id'=>get_current_blog_id(),'subscriber_id'=>(int)$claim['subscriber_id'],
            'mailbox'=>$claim['mailbox'],'token_epoch'=>$expected['token_epoch'],
            'attempt_key'=>$claim['attempt_key'],'snapshot_hash'=>$claim['content_hash']];
        [$submission]=$store->recoverOutput($binding,$expected,new PeraCRM_CampaignOutputCipher(['e1'=>str_repeat('e',32)],'e1'),static fn()=>prepared_clock());
        prepared_expect($submission->hash()===$expected['envelope_hash'],'historical exact-byte recovery');
    });
    prepared_expect(worker_fixture_rows((int)$claim['job_id'])===$before && prepared_records($deployment)===$records,
        'historical recovery never changes worker authority or durable output');
}
final class PeraCRM_Prepared_Mock implements PeraCRM_Campaign_SES_Transport
{
    public int $calls=0; public array $requests=[]; public string $mode='accepted';
    public ?Closure $during=null;
    private string $deployment;
    public function __construct(string $deployment) { $this->deployment=$deployment; }
    public function send(array $request,array $options): array
    {
        ++$this->calls; $this->requests[]=$request;
        worker_fixture_target(function () {
            global $wpdb;
            $records=prepared_records($this->deployment);
            prepared_expect(count($records['recipient_tokens'])>=2 && count($records['submission_attempts'])>=1
                && count($records['submission_outputs'])>=1,'durable token, attempt and encrypted output before mock call');
            $quiet=$wpdb->suppress_errors(true);
            $wpdb->query('SAVEPOINT prepared_mock_probe');
            $outside=$wpdb->query('RELEASE SAVEPOINT prepared_mock_probe')===false;
            $wpdb->suppress_errors($quiet);
            prepared_expect($outside,'transport outside SQL transaction');
        });
        if ($this->during) { ($this->during)(); }
        if ($this->mode==='unknown') { throw new RuntimeException('synthetic connection loss'); }
        if ($this->mode==='transient') { return ['complete'=>true,'authenticated'=>true,'status'=>429,'error_code'=>'TooManyRequestsException']; }
        if ($this->mode==='rejected') { return ['complete'=>true,'authenticated'=>true,'status'=>403,'error_code'=>'AccessDeniedException']; }
        return ['complete'=>true,'authenticated'=>true,'status'=>200,'message_id'=>'synthetic-prepared'];
    }
}
/** Interpose only after recipient COMMIT, before the final worker transaction.
 * No production hook or sleep; independent-process cases use SQL locks below.
 */
function prepared_interrupt(callable $operation): Closure
{
    $output=false; $committed=false;
    $filter=static function ($sql) use (&$filter,&$output,&$committed,$operation) {
        if (str_starts_with($sql,'INSERT INTO') && str_contains($sql,'submission_outputs')) { $output=true; }
        if ($sql==='COMMIT' && $output) { $committed=true; }
        if ($sql==='START TRANSACTION' && $committed) {
            remove_filter('query',$filter); $operation();
        }
        return $sql;
    };
    add_filter('query',$filter); return $filter;
}
