<?php
require __DIR__.'/campaign-scheduler-test.php';
require_once $root.'/inc/campaigns/sending-budget.php';
function budget_reject(callable $callback,string $code): void {
    try { $callback(); } catch (PeraCRM_Sending_Budget_Error $error) {
        campaign_expect($error->getMessage()===$code && $error->getPrevious()===null,'safe budget rejection '.$code); return;
    }
    throw new RuntimeException('Expected budget rejection');
}
foreach ([['1',1,100,200,100],[1.5,1,100,200,100],[true,1,100,200,100],[0,1,100,200,100],[1,0,100,200,100],[1,1,101,200,100],[1,1,100,100,100],[1,1,100,3701,100]] as $quota) {
    budget_reject(fn()=>PeraCRM_Sending_Budget::validateQuota(...$quota),'budget_quota');
}
PeraCRM_Sending_Budget::validateQuota(1,1,100,3700,100);
campaign_expect(peracrm_sending_budget_service()===null && !peracrm_campaign_queue_dispatch_allowed(),'budget and dispatch factories remain disabled');
foreach ([[],['version'=>'0','confirm'=>'breaker-only'],['version'=>'1','confirm'=>'force'],['version'=>'1','confirm'=>'breaker-only','attempt'=>'private']] as $options) {
    $exit=0; try { (WP_CLI::$commands['peracrm campaigns budget-recover'])([],$options); } catch (Campaign_CLI_Exit $e) { $exit=$e->getCode(); }
    campaign_expect($exit===3,'invalid recovery rejected');
}
$GLOBALS['campaign_actor']=1; $GLOBALS['campaign_impersonating']=false;
foreach (['peracrm_view_campaign_reports','peracrm_manage_campaigns','peracrm_manage_subscribers'] as $cap) { $GLOBALS['campaign_roles'][2]['administrator']->add_cap($cap); }
foreach (['budget-health'=>[],'budget-recover'=>['version'=>'1','confirm'=>'breaker-only']] as $op=>$opts) {
    $exit=0; try { (WP_CLI::$commands['peracrm campaigns '.$op])([],$opts); } catch (Campaign_CLI_Exit $e) { $exit=$e->getCode(); }
    campaign_expect($exit===2 && json_decode(WP_CLI::$output,true)['state']==='disabled','authorized budget CLI remains disabled');
}
$GLOBALS['campaign_impersonating']=true;
$exit=0; try { (WP_CLI::$commands['peracrm campaigns budget-recover'])([],['version'=>'1','confirm'=>'breaker-only']); } catch (Campaign_CLI_Exit $e) { $exit=$e->getCode(); }
campaign_expect($exit===3,'impersonation cannot recover breaker');
echo "PASS sending budget policy and guarded disabled CLI\n";
