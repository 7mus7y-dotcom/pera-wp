<?php
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required'); }
require_once WP_PLUGIN_DIR.'/peracrm/tests/sns-fixture.php';
$mode=$args[0]??''; $scenario=$args[1]??'';
peracrm_with_target_blog(static function () use ($mode,$scenario) {
    global $wpdb;
    if ($mode==='prepare') {
        $v=sns_values(); $v['deployment']='sns-race-'.bin2hex(random_bytes(8));
        update_option('sns_race_fixture',$v,false); return;
    }
    $v=get_option('sns_race_fixture');
    if ($mode==='verify') {
        $a=json_decode(file_get_contents('/tmp/peracrm-sns-winner'),true);
        $b=json_decode(file_get_contents('/tmp/peracrm-sns-loser'),true);
        sns_expect($a['connection']!==$b['connection'] && $b['duration']>0.5,'independent connections serialize receipt');
        sns_expect($a['result']==='quarantined' && $b['result']===($scenario==='conflict'?'sns_conflict':'quarantined'),'concurrent '.$scenario.' result');
        return;
    }
    $store=new PeraCRM_SNS_Receipts(sns_verifier($v),$v['deployment'],get_current_blog_id());
    $connection=(int)$wpdb->get_var('SELECT CONNECTION_ID()'); $start=microtime(true);
    if ($mode==='winner') {
        add_filter('query',static function ($sql) {
            if ($sql==='COMMIT') { file_put_contents('/tmp/peracrm-sns-held','held'); usleep(1500000); }
            return $sql;
        });
    }
    $body=sns_body($v,$mode==='loser' && $scenario==='conflict'?['Message'=>'conflict']:[]);
    try { $result=$store->receive($body,$v['now']); }
    catch (PeraCRM_SNS_Error $error) { $result=$error->getMessage(); }
    file_put_contents('/tmp/peracrm-sns-'.$mode,json_encode(['connection'=>$connection,'result'=>$result,'duration'=>microtime(true)-$start]));
});
