<?php
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required'); }
require_once WP_PLUGIN_DIR.'/peracrm/tests/sns-fixture.php';
$mode=$args[0]??'';
peracrm_with_target_blog(static function () use ($mode) {
    if ($mode==='prepare') {
        $v=sns_values(); $config=['enabled'=>true,'blog'=>get_current_blog_id(),'deployment'=>'sns-provision-cli-'.bin2hex(random_bytes(8))]
            +$v['scope']+['endpoint'=>set_url_scheme(home_url('/peracrm-ses-sns/'),'https')];
        update_option('sns_provision_http_fixture',['config'=>$config,'pem'=>$v['pem']],false); return;
    }
    $status=json_decode(file_get_contents('/tmp/peracrm-sns-provision-cli-status'),true,8,JSON_THROW_ON_ERROR);
    sns_expect(count($status)===1 && $status[0]['state']==='armed' && $status[0]['actor']===(int)get_user_by('login','fixture-admin')->ID,
        'real CLI explicit administrator arm/status');
    sns_expect(!str_contains(json_encode($status),'Token'),'real CLI status excludes tokens');
    delete_option('sns_provision_http_fixture');
});
