<?php
define('ABSPATH',__DIR__);
function get_current_blog_id() { return 1; }
function peracrm_get_target_blog_id() { return 1; }
function is_multisite() { return true; }
function home_url($path='') { return $GLOBALS['withdrawal_origin']??'https://example.org/'; }
function peracrm_campaigns_schema_ready() { return $GLOBALS['withdrawal_schema']??true; }
require __DIR__.'/deployment-fixture.php';
require_once dirname(__DIR__).'/inc/campaigns/recipient-withdrawal.php';
function internal_withdrawal_expect(bool $ok,string $label): void {
    if (!$ok) { throw new RuntimeException('FAIL internal withdrawal: '.$label); }
    echo 'PASS internal withdrawal: '.$label."\n";
}
internal_withdrawal_expect(peracrm_campaign_recipient_withdrawal_service()===null,'disabled without explicit opt-in');
define('PERACRM_CAMPAIGN_WITHDRAWAL_HTTP',true);
internal_withdrawal_expect(peracrm_campaign_recipient_withdrawal_service()===null,'missing deployment fails unavailable');
define('PERACRM_CAMPAIGN_DEPLOYMENT',campaign_deployment_manifest(1,'verification-only'));
define('PERACRM_CAMPAIGN_INSTANCE_ID','fixture-instance-01');
$material=campaign_deployment_material(time());
define('PERACRM_CAMPAIGN_MATERIAL',['signing'=>$material['signing']]);
internal_withdrawal_expect(peracrm_campaign_recipient_withdrawal_service() instanceof PeraCRM_CampaignRecipientWithdrawal,
    'scoped signing-only configuration constructs the verifier');
$GLOBALS['withdrawal_origin']='https://different.example.org/';
internal_withdrawal_expect(peracrm_campaign_recipient_withdrawal_service()===null,'origin drift cannot activate the service');
$GLOBALS['withdrawal_origin']='https://example.org/';
$GLOBALS['withdrawal_schema']=false;
internal_withdrawal_expect(peracrm_campaign_recipient_withdrawal_service()===null,'unready schema cannot activate the service');
