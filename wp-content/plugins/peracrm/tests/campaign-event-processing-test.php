<?php
require __DIR__.'/campaign-scheduler-test.php';
// Exercise real command routing/authorization before any processing storage access.
foreach ([['event-redrive',[]],['event-redrive',['limit'=>'26','confirm'=>'authenticated-events']],
    ['event-redrive',['limit'=>'1','confirm'=>'force']],['event-health',['payload'=>'private@example.test']]] as [$op,$options]) {
    $exit=0; try { (WP_CLI::$commands['peracrm campaigns '.$op])([],$options); } catch (Campaign_CLI_Exit $e) { $exit=$e->getCode(); }
    campaign_expect($exit===3 && !str_contains(WP_CLI::$output,'@'),'guarded bounded event command rejects invalid input');
}
$GLOBALS['campaign_actor']=0;
foreach (['event-health','event-redrive'] as $op) {
    $exit=0; try { (WP_CLI::$commands['peracrm campaigns '.$op])([],['limit'=>'1','confirm'=>'authenticated-events']); } catch (Campaign_CLI_Exit $e) { $exit=$e->getCode(); }
    campaign_expect($exit===3,'anonymous event command rejected');
}
$GLOBALS['campaign_actor']=1;
$GLOBALS['campaign_roles'][2]['administrator']->add_cap('peracrm_view_campaign_reports');
$GLOBALS['campaign_roles'][2]['administrator']->remove_cap('peracrm_manage_campaigns');
$exit=0; try { (WP_CLI::$commands['peracrm campaigns event-redrive'])([],['limit'=>'1','confirm'=>'authenticated-events']); } catch (Campaign_CLI_Exit $e) { $exit=$e->getCode(); }
campaign_expect($exit===3,'report capability alone cannot redrive');
$exit=0; try { (WP_CLI::$commands['peracrm campaigns event-health'])([],[]); } catch (Campaign_CLI_Exit $e) { $exit=$e->getCode(); }
campaign_expect($exit===2 && json_decode(WP_CLI::$output,true)['configured']===false,'inspection does not activate missing deployment configuration');
$GLOBALS['campaign_impersonating']=true;
$exit=0; try { (WP_CLI::$commands['peracrm campaigns event-health'])([],[]); } catch (Campaign_CLI_Exit $e) { $exit=$e->getCode(); }
campaign_expect($exit===3,'impersonated event inspection rejected');
$GLOBALS['campaign_impersonating']=false;
require_once __DIR__.'/sns-fixture.php';
$v=sns_values();
define('PERACRM_SES_EVENT_OPERATIONS',['deployment'=>'fixture','blog'=>2,'account'=>$v['scope']['account'],'region'=>$v['scope']['region'],'topic'=>$v['scope']['topic'],'configuration_set'=>'fixture','configuration_version'=>1]);
$service=peracrm_with_target_blog(static fn()=>peracrm_ses_processing_service());
campaign_expect($service instanceof PeraCRM_SES_Processing && !str_contains(print_r($service,true),'scope'),'internal factory is configured and redacts diagnostics');
$denied=false; try { peracrm_ses_processing_service(); } catch (PeraCRM_SNS_Error $e) { $denied=true; }
campaign_expect($denied,'factory rejects wrong current blog');
campaign_expect(!defined('PERACRM_SES_SNS_HTTP') && !peracrm_campaign_queue_dispatch_allowed(),'operations configuration does not activate intake or sending');
