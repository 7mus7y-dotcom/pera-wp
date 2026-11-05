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
        prepared_reject(fn()=>$claim->invoke(null,str_repeat('b',64),true));
        $first=$claim->invoke(null,str_repeat('a',64));
        prepared_reject(fn()=>$claim->invoke(null,str_repeat('b',64),true));
        prepared_reject(fn()=>$claim->invoke(null,str_repeat('b',64)));
        $record=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",$name));
        prepared_expect(json_decode($record,true)['binding']===str_repeat('a',64),
            'duplicate authorization cannot replace the first committed claim');
        $finish=new ReflectionMethod(PeraCRM_SES_Internal_Probe::class,'finish');
        $finish->invoke(null,$first,['state'=>'unconfirmed','retry_allowed'=>false],false);
        $second=$claim->invoke(null,str_repeat('b',64),true);
        prepared_reject(fn()=>$claim->invoke(null,str_repeat('c',64),true));
        $finish->invoke(null,$second,['state'=>'accepted','message_id'=>'fixture-probe-id'],true);
        prepared_reject(fn()=>$finish->invoke(null,$second,['state'=>'unconfirmed'],true));
        $audit=(new PeraCRM_Campaign_Operations())->internal_probe(false,true);
        prepared_expect($audit['first']['state']==='unconfirmed' && $audit['second']['state']==='accepted'
            && $audit['second']['message_id']==='fixture-probe-id' && !isset($audit['second']['binding']),
            'status reads sanitized durable outcomes without changing either claim');
    } finally { $wpdb->suppress_errors($quiet); }
});
