<?php
require __DIR__.'/campaign-scheduler-test.php';
require_once __DIR__.'/../inc/campaigns/suppression-sync.php';
function sync_reject(callable $callback,string $code): void
{
    try { $callback(); } catch (PeraCRM_SES_Sync_Error $error) {
        campaign_expect($error->getMessage()===$code && $error->getPrevious()===null,'fixed safe error '.$code); return;
    }
    throw new RuntimeException('Expected sync rejection');
}
$pins=['account_id'=>'123456789012','region'=>'eu-west-1','deployment'=>'sync-test','blog_id'=>2];
$entry=['EmailAddress'=>'Mixed@Example.test','Reason'=>'BOUNCE','LastUpdateTime'=>time()-10];
$page=['SuppressedDestinationSummaries'=>[$entry],'NextToken'=>'Case+Token/='];
campaign_expect(PeraCRM_SES_Suppression_HTTP::validatePage($page)===$page,'mailbox and cursor spelling retained');
foreach ([['Reason'=>'future'],['EmailAddress'=>'x@example.test '],['LastUpdateTime'=>INF],['TenantName'=>'other']] as $change) {
    $bad=$page; $bad['SuppressedDestinationSummaries']=[array_replace($entry,$change)];
    sync_reject(fn()=>PeraCRM_SES_Suppression_HTTP::validatePage($bad),isset($change['Reason'])?'sync_unsupported_reason':'sync_invalid_page');
}
$bad=$page; $bad['SuppressedDestinationSummaries']=array_fill(0,26,$entry);
sync_reject(fn()=>PeraCRM_SES_Suppression_HTTP::validatePage($bad),'sync_invalid_page');
foreach (['',str_repeat('x',4097),"private\nvalue"] as $cursor) { sync_reject(fn()=>PeraCRM_SES_Suppression_HTTP::cursor($cursor),'sync_invalid_cursor'); }
$credentials=new PeraCRM_Campaign_SES_Credentials('123456789012','AKIA'.str_repeat('X',16),str_repeat('s',40));
$calls=0;
$reader=new PeraCRM_SES_Suppression_HTTP($pins,$credentials,static function ($request,$options) use (&$calls,$page) {
    $calls++;
    campaign_expect($request->getMethod()==='GET' && str_contains((string)$request->getUri(),'NextToken=Case%2BToken%2F%3D')
        && !str_contains((string)$request->getUri(),'TenantName') && str_contains($request->getHeaderLine('Authorization'),'/eu-west-1/ses/aws4_request'),'signed fixed region account-level GET');
    campaign_expect($options===['timeout_ms'=>3000,'connect_timeout_ms'=>1000,'verify'=>true,'redirects'=>false,'proxy'=>false,
        'max_body_bytes'=>32768,'max_header_bytes'=>16384,'retries'=>0],'read execution policy');
    return $calls===1?['complete'=>false]:['complete'=>true,'authenticated'=>true,'status'=>200,'body'=>json_encode($page)];
});
campaign_expect($reader->page('Case+Token/=')===$page && $calls===2,'separate two-attempt read policy');
$calls=0; $reader=new PeraCRM_SES_Suppression_HTTP($pins,$credentials,static function () use (&$calls) { $calls++; return ['complete'=>false]; });
sync_reject(fn()=>$reader->page(null),'sync_read_failed'); campaign_expect($calls===2,'read retry exhaustion bounded');
foreach (['{"SuppressedDestinationSummaries":[],"NextToken":"a","Next\u0054oken":"b"}',
    '{"SuppressedDestinationSummaries":[{"EmailAddress":"a@example.test","EmailAddress":"b@example.test","Reason":"BOUNCE","LastUpdateTime":1}]}'] as $body) {
    $reader=new PeraCRM_SES_Suppression_HTTP($pins,$credentials,static fn()=>['complete'=>true,'authenticated'=>true,'status'=>200,'body'=>$body]);
    sync_reject(fn()=>$reader->page(null),'sync_invalid_page');
}
campaign_expect(peracrm_ses_suppression_sync_service()===null,'production sync unconditionally disabled');
foreach ([[],['pages'=>'11','confirm'=>'account-deny-only'],['pages'=>'1','confirm'=>'force'],['pages'=>'1','confirm'=>'account-deny-only','restart'=>true]] as $options) {
    $exit=0; try { (WP_CLI::$commands['peracrm campaigns suppression-sync'])([],$options); } catch (Campaign_CLI_Exit $e) { $exit=$e->getCode(); }
    campaign_expect($exit===3 && !str_contains(WP_CLI::$output,'@'),'CLI rejects invalid bounds/confirmation/restart');
}
$GLOBALS['campaign_actor']=1; $GLOBALS['campaign_impersonating']=false;
$GLOBALS['campaign_roles'][2]['administrator']->add_cap('peracrm_manage_campaigns');
$GLOBALS['campaign_roles'][2]['administrator']->add_cap('peracrm_manage_subscribers');
$exit=0; try { (WP_CLI::$commands['peracrm campaigns suppression-sync'])([],['pages'=>'1','confirm'=>'account-deny-only']); } catch (Campaign_CLI_Exit $e) { $exit=$e->getCode(); }
campaign_expect($exit===2 && json_decode(WP_CLI::$output,true)['state']==='disabled','authorized explicit sync remains production disabled');
echo "PASS suppression sync policy, signature, retries, duplicate-key and disabled CLI regressions\n";
