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
    return new PeraCRM_Campaign_Worker_Preparation($deployment,prepared_scope(),
        $recovery?'invalid-origin':'https://example.org',$recovery?[]:['k1'=>str_repeat('s',32)],
        $recovery?'missing':'k1',new PeraCRM_CampaignOutputCipher(['e1'=>str_repeat('e',32)],'e1'));
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
final class PeraCRM_Prepared_Mock implements PeraCRM_Campaign_SES_Transport
{
    public int $calls=0; public array $requests=[]; public string $mode='accepted';
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
        if ($this->mode==='unknown') { throw new RuntimeException('synthetic connection loss'); }
        if ($this->mode==='transient') { return ['complete'=>true,'authenticated'=>true,'status'=>429,'error_code'=>'TooManyRequestsException']; }
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
