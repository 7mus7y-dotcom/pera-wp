<?php
require_once __DIR__.'/withdrawal-fixture.php';
require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/recipient-preferences.php';
function recipient_preferences_service(): PeraCRM_CampaignRecipientPreferences
{
    return new PeraCRM_CampaignRecipientPreferences(withdrawal_store(),withdrawal_keys(),'fixture-withdrawal');
}
function recipient_preferences_post(array $seed, array $profile=['language'=>'tr','topics'=>['news']]): array
{
    $service=recipient_preferences_service();
    return $service->request('POST',$seed['token'],'application/x-www-form-urlencoded',
        ['token'=>$seed['token'],'proof'=>$service->proof($seed['token'],time(),str_repeat('a',48))]+$profile,
        'http://fixture.test','http://fixture.test','same-origin',str_repeat('a',48));
}
