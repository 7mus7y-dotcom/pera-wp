<?php
require_once __DIR__.'/prepared-worker-fixture.php';
require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/operations.php';
require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/ses-internal-probe.php';

worker_fixture_target(static function () {
    global $wpdb;
    wp_set_current_user(0);
    prepared_reject(fn()=>PeraCRM_SES_Internal_Probe::run());
    wp_set_current_user((int)get_option('campaign_fixture_administrator'));
    prepared_expect((new PeraCRM_Campaign_Operations())->internal_probe()===['state'=>'disabled'],
        'internal probe defaults to disabled');
    $quiet=$wpdb->suppress_errors(true);
    try {
        $claim=new ReflectionMethod(PeraCRM_SES_Internal_Probe::class,'claim');
        $name='peracrm_ses_internal_probe_consumed_v1';
        prepared_expect(get_option($name)===false,'disabled and unauthorized paths did not consume the permit');
        $wpdb->query('START TRANSACTION');
        try { prepared_reject(fn()=>$claim->invoke(null,str_repeat('a',64))); }
        finally { $wpdb->query('ROLLBACK'); }
        prepared_reject(fn()=>$claim->invoke(null,str_repeat('b',64),2));
        $first=$claim->invoke(null,str_repeat('a',64));
        prepared_reject(fn()=>$claim->invoke(null,str_repeat('b',64),2));
        prepared_reject(fn()=>$claim->invoke(null,str_repeat('b',64)));
        $record=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",$name));
        prepared_expect(json_decode($record,true)['binding']===str_repeat('a',64),
            'duplicate authorization cannot replace the first committed claim');
        $finish=new ReflectionMethod(PeraCRM_SES_Internal_Probe::class,'finish');
        $finish->invoke(null,$first,['state'=>'unconfirmed','retry_allowed'=>false],1);
        $second=$claim->invoke(null,str_repeat('b',64),2);
        prepared_reject(fn()=>$claim->invoke(null,str_repeat('c',64),2));
        prepared_reject(fn()=>$claim->invoke(null,str_repeat('c',64),3));
        $finish->invoke(null,$second,['state'=>'rejected','http_status'=>403,'error_code'=>'AccessDeniedException'],2);
        prepared_reject(fn()=>$finish->invoke(null,$second,['state'=>'unconfirmed'],2));
        $third=$claim->invoke(null,str_repeat('c',64),3);
        prepared_reject(fn()=>$claim->invoke(null,str_repeat('d',64),3));
        prepared_reject(fn()=>$claim->invoke(null,str_repeat('d',64),4));
        $finish->invoke(null,$third,['state'=>'accepted','message_id'=>'fixture-probe-id'],3);
        $audit=(new PeraCRM_Campaign_Operations())->internal_probe(1,true);
        prepared_expect($audit['first']['state']==='unconfirmed' && $audit['second']['state']==='rejected'
            && $audit['second']['error_code']==='AccessDeniedException' && $audit['third']['state']==='accepted'
            && $audit['third']['message_id']==='fixture-probe-id' && !isset($audit['third']['binding']),
            'status preserves the first two outcomes and the one access-denial follow-up');
    } finally { $wpdb->suppress_errors($quiet); }
});
