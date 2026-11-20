<?php
require __DIR__.'/campaign-scheduler-test.php';
require_once __DIR__.'/../inc/campaigns/delivery-review.php';
$base=['mailbox'=>'person@campaign-fixture.org','subscriber_id'=>12,'subscription_state'=>'subscribed',
    'source'=>'owner_attested_zoho_migration','source_ref'=>'zoho-1','evidence_ref'=>'consent-1','policy_version'=>'v1',
    'confirmed_at'=>'2026-10-01 12:00:00','recorded_at'=>'2026-10-01 12:00:00','withdrawn_at'=>null];
campaign_expect(peracrm_delivery_review_hints($base)===[],'valid syntax does not invent a deliverability or engagement verification');
campaign_expect(in_array('Possible placeholder: review manually',peracrm_delivery_review_hints(array_replace($base,['mailbox'=>'firstname@none.com'])),true),'placeholder domain flagged for review');
campaign_expect(in_array('Invalid email format',peracrm_delivery_review_hints(array_replace($base,['mailbox'=>'not an email'])),true),'invalid syntax flagged');
campaign_expect(in_array('Suppressed: must not send',peracrm_delivery_review_hints($base+['complaint'=>1]),true),'complaint remains a denial');
campaign_expect(in_array('Current consent needs review',peracrm_delivery_review_hints(array_replace($base,['subscription_state'=>'unknown'])),true),'unknown consent never treated as permission');
campaign_expect(is_wp_error(peracrm_delivery_review_remove('person@campaign-fixture.org',12,1,false)),'explicit confirmation required before cleanup');
$GLOBALS['campaign_actor']=0;
campaign_expect(is_wp_error(peracrm_delivery_review_remove('person@campaign-fixture.org',12,1,true)),'unauthenticated cleanup rejected');
echo "PASS delivery review advisory hints and cleanup authority\n";
