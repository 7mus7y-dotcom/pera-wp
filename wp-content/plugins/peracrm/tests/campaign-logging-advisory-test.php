<?php
require __DIR__.'/campaign-scheduler-test.php';
require_once $root.'/inc/campaigns/assessment.php';
$GLOBALS['campaign_actor']=1;
$GLOBALS['campaign_impersonating']=false;
$GLOBALS['campaign_roles'][2]['administrator']->add_cap('peracrm_view_campaign_reports');
$report=(new PeraCRM_Campaign_Readiness(null))->assess();
campaign_expect($report['checks']['host_log_protections']===['state'=>'unverified','source'=>'none'],
    'logging policy does not fabricate host verification');
campaign_expect($report['warnings']===['host_log_protections:unverified']
    && !in_array('host_log_protections:unverified',$report['blockers'],true),
    'missing host log evidence is a visible non-blocking warning');
campaign_expect(in_array('dns_dkim_dmarc:unverified',$report['blockers'],true)
    && in_array('dispatch_disabled',$report['blockers'],true)
    && !$report['production_ready'] && !$report['dispatch_enabled'],
    'logging advisory never enables dispatch or waives other prerequisites');
echo "PASS logging advisory policy without dispatch activation\n";
