<?php
require_once __DIR__.'/prepared-worker-fixture.php';
require_once WP_PLUGIN_DIR.'/peracrm/tests/deployment-fixture.php';
require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/internal-campaign-runtime.php';

worker_fixture_target(static function () {
    global $wpdb;
    $wpdb->query('SET timestamp=0');
    wp_set_current_user((int)get_option('campaign_fixture_administrator'));
    $config=peracrm_campaign_configuration(true); $oldConfig=$config['document'];
    $doc=$oldConfig;
    $doc['senders']=[['id'=>'rehearsal','name'=>'Rehearsal fixture','email'=>'info@peraproperty.com','reply_to'=>'info@peraproperty.com','approved'=>true]];
    $doc['footer']='Synthetic test footer'; $doc['footer_approved']=true;
    prepared_expect(!is_wp_error(peracrm_campaign_configuration_save($config['revision'],$doc)),'rehearsal synthetic sender approved');
    $topic='rehearsal'.bin2hex(random_bytes(6));
    prepared_expect(!is_wp_error(peracrm_campaigns_save_subscriber('mus7y@yahoo.co.uk',['preferred_language'=>'en','preferences'=>[$topic]])),
        'rehearsal test subscriber created');
    prepared_expect(!is_wp_error(peracrm_campaigns_record_consent('mus7y@yahoo.co.uk','subscribed',[
        'source'=>'synthetic_fixture','source_ref'=>'rehearsal','evidence_ref'=>'synthetic','policy_version'=>'v1','occurred_at'=>gmdate('Y-m-d H:i:s')])),
        'rehearsal synthetic consent recorded');
    $draft=['internal_name'=>'Controlled rehearsal fixture','subject'=>'Controlled rehearsal fixture',
        'sender_id'=>'rehearsal','language'=>'en','main_text'=>'Immutable synthetic internal rehearsal',
        'audience'=>['version'=>1,'topic'=>$topic]];
    $d=peracrm_campaign_draft_save(0,0,$draft);
    prepared_expect(!is_wp_error($d),'rehearsal draft saved');
    $review=peracrm_campaign_snapshot_review($d['id'],1,$d['document']);
    $s=peracrm_campaign_snapshot_capture($d['id'],1,$d['document'],$review['review_proof'],wp_create_nonce('peracrm_campaign_snapshot_'.$d['id'].'_1'));
    $nonce=wp_create_nonce('peracrm_campaign_freeze_'.$d['id']);
    $a=peracrm_campaign_freeze_start($d['id'],1,(int)$s['id'],$s['content_hash'],$d['document'],$nonce);
    while (!(int)$a['complete']) { $a=peracrm_campaign_freeze_continue($d['id'],(int)$a['id'],(int)$a['version'],$nonce); }
    $a=peracrm_campaign_freeze_publish($d['id'],(int)$a['id'],(int)$a['version'],$nonce);
    $r=peracrm_campaign_frozen_get($d['id'],1);
    $nonce=wp_create_nonce('peracrm_campaign_queue_'.$d['id']);
    $j=peracrm_campaign_queue_prepare($d['id'],(int)$r['id'],$nonce);
    while (!(int)$j['complete']) { $j=peracrm_campaign_queue_materialize($d['id'],(int)$j['id'],(int)$j['version'],$nonce); }
    prepared_expect((int)$j['expected_count']===1,'rehearsal real queue contains exactly one recipient');

    $deployment='rehearsal-fixture';
    prepared_setup($deployment);
    $manifest=campaign_deployment_manifest(get_current_blog_id(),$deployment);
    $adapter=new PeraCRM_Campaign_Deployment($manifest,campaign_deployment_material(time()),static fn()=>time(),campaign_deployment_runtime($manifest));
    $transport=new class implements PeraCRM_Campaign_SES_Transport {
        public int $calls=0;
        public string $unsubscribe='';
        public function send(array $request,array $options): array {
            ++$this->calls;
            $this->unsubscribe=trim($request['Content']['Simple']['Headers'][0]['Value'],'<>');
            prepared_expect($request['Destination']===['ToAddresses'=>['mus7y@yahoo.co.uk']]
                && count($request['EmailTags'])===4 && count($request['Content']['Simple']['Headers'])===2,
                'rehearsal uses real campaign correlation tags and one-click headers');
            return ['complete'=>true,'authenticated'=>true,'status'=>200,'message_id'=>'rehearsal-fixture-id'];
        }
    };
    $service=new PeraCRM_Internal_Campaign_Runtime($adapter,$transport);
    $job=(int)$j['id'];
    wp_set_current_user(0);
    prepared_reject(fn()=>$service->proposal($job));
    wp_set_current_user((int)get_option('campaign_fixture_administrator'));
    $proposal=$service->proposal($job);
    prepared_expect($proposal['recipient']==='mus7y@yahoo.co.uk' && $proposal['count']===1 && !$proposal['dispatch_enabled'],
        'exact one-recipient proposal grants no dispatch');
    prepared_expect($service->send($job,$proposal['binding'])===['state'=>'disabled'] && $transport->calls===0,
        'rehearsal has an independent explicit opt-in');
    define('PERACRM_CAMPAIGN_REHEARSAL',true);
    prepared_reject(fn()=>(new PeraCRM_Internal_Campaign_Runtime($adapter))->send($job,$proposal['binding']));
    prepared_expect(PeraCRM_Internal_Campaign_Runtime::status()['state']==='unused',
        'native sending requires configured HTTP dependencies before consuming authority');
    prepared_reject(fn()=>$service->send($job,str_repeat('0',64)));
    prepared_expect(PeraCRM_Internal_Campaign_Runtime::status()['state']==='unused' && $transport->calls===0,
        'stale proposal does not consume authority');

    $table=peracrm_table('peracrm_campaign_deliveries');
    $wpdb->update($table,['mailbox'=>'customer@example.test'],['job_id'=>$job]);
    prepared_reject(fn()=>$service->proposal($job));
    prepared_expect($transport->calls===0,'other mailbox rejected before any provider call');
    $wpdb->update($table,['mailbox'=>'mus7y@yahoo.co.uk'],['job_id'=>$job]);
    $result=$service->send($job,$service->proposal($job)['binding']);
    prepared_expect($result['delivery_state']==='accepted' && $result['state']==='completed' && $transport->calls===1,
        'one prepared real-ledger recipient sent via injected transport');
    prepared_reject(fn()=>$service->send($job,$proposal['binding']));
    prepared_expect($transport->calls===1 && !peracrm_campaign_queue_dispatch_allowed(),
        'replay cannot send twice or enable bulk dispatch');

    $query=parse_url($transport->unsubscribe,PHP_URL_QUERY);
    parse_str($query,$params);
    $token=$params['token']??'';
    $withdrawal=$adapter->internalWithdrawal();
    prepared_expect($withdrawal->request('GET',false,$token,'',[])[0]===200
        && peracrm_campaigns_is_eligible('mus7y@yahoo.co.uk',['topic'=>$topic]),
        'real prepared token displays without changing eligibility');
    prepared_expect($withdrawal->request('POST',true,$token,'application/x-www-form-urlencoded','List-Unsubscribe=One-Click')[0]===200
        && !peracrm_campaigns_is_eligible('mus7y@yahoo.co.uk',['topic'=>$topic]),
        'real campaign one-click token withdraws permission and blocks future eligibility');

    $config=peracrm_campaign_configuration(true);
    prepared_expect(!is_wp_error(peracrm_campaign_configuration_save($config['revision'],$oldConfig)),'rehearsal fixture restores configuration');
});
