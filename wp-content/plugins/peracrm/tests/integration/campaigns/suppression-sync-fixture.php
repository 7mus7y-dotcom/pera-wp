<?php
require_once __DIR__.'/ses-suppression-fixture.php';
require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/suppression-sync.php';
final class PeraCRM_Sync_Fixture_Reader implements PeraCRM_SES_Suppression_Reader
{
    private array $pins;
    private Closure $read;
    public function __construct(array $pins,Closure $read) { $this->pins=$pins; $this->read=$read; }
    public function scope(): array { return $this->pins; }
    public function page(?string $cursor): array { return ($this->read)($cursor); }
}
function sync_pins(): array
{
    return ['account_id'=>'123456789012','region'=>'eu-west-1','deployment'=>'sync-'.bin2hex(random_bytes(8)),'blog_id'=>get_current_blog_id()];
}
function sync_page(string $mailbox,?string $next=null,string $reason='BOUNCE'): array
{
    return ['SuppressedDestinationSummaries'=>[['EmailAddress'=>$mailbox,'Reason'=>$reason,'LastUpdateTime'=>1700000000]],'NextToken'=>$next];
}
function sync_service(array $pins,Closure $reader): PeraCRM_SES_Suppression_Sync
{
    return new PeraCRM_SES_Suppression_Sync($pins,new PeraCRM_Sync_Fixture_Reader($pins,$reader));
}
function sync_reject_sql(callable $callback,string $code): void
{
    try { $callback(); } catch (PeraCRM_SES_Sync_Error $error) { sns_expect($error->getMessage()===$code && $error->getPrevious()===null,'fixed '.$code); return; }
    throw new RuntimeException('Expected sync failure');
}
function sync_denials(): string
{
    $out=[];
    foreach (['peracrm_email_suppressions','peracrm_email_suppression_events','peracrm_campaign_ses_sync_facts','peracrm_campaign_ses_sync_pages'] as $table) { $out[$table]=suppress_rows($table); }
    return hash('sha256',wp_json_encode($out));
}
