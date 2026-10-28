<?php
require_once __DIR__.'/internal-test-fixture.php';
require_once WP_PLUGIN_DIR.'/peracrm/tests/deployment-fixture.php';
worker_fixture_target(static function () {
    global $wpdb;
    wp_set_current_user((int)get_option('campaign_fixture_administrator'));
    $previous=peracrm_campaign_configuration(true); $doc=$previous['document'];
    $doc['senders']=[['id'=>'deployment','name'=>'Synthetic deployment','email'=>'sender@example.test','reply_to'=>'reply@example.test','approved'=>true]];
    $doc['footer']='Synthetic deployment footer'; $doc['footer_approved']=true;
    prepared_expect(!is_wp_error(peracrm_campaign_configuration_save($previous['revision'],$doc)),'deployment fixture explicitly approves synthetic configuration');
    try {
        foreach (['accepted','expiry','clone'] as $scenario) {
            $job=internal_test_source(); $deployment='adapter-'.bin2hex(random_bytes(8));
            prepared_service($deployment); $now=prepared_clock();
            $manifest=campaign_deployment_manifest(get_current_blog_id(),$deployment); $material=campaign_deployment_material($now);
            if ($scenario==='expiry') { $material['signing']['expires_at']=$now+40; $material['encryption']['expires_at']=$now+40; }
            $runtime=campaign_deployment_runtime($manifest);
            if ($scenario==='clone') { $runtime=static fn()=>['instance_id'=>'cloned-instance-01','origin'=>'https://example.org','blog_id'=>get_current_blog_id()]; }
            $adapter=new PeraCRM_Campaign_Deployment($manifest,$material,static fn()=>prepared_clock(),$runtime);
            $readiness=new PeraCRM_Campaign_Readiness($adapter->binding(),null,static fn()=>prepared_clock(),$adapter);
            $queries=[]; $watch=static function ($sql) use (&$queries) { $queries[]=$sql; return $sql; };
            add_filter('query',$watch);
            try { $report=$readiness->assess(); } finally { remove_filter('query',$watch); }
            foreach ($queries as $sql) {
                prepared_expect(!preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|START|COMMIT|ALTER|CREATE|DROP|SET)\b/i',$sql)
                    && !str_contains($sql,'FOR UPDATE') && !str_contains($sql,'GET_LOCK'),'adapter readiness performs only reads');
            }
            prepared_expect(!$report['production_ready'] && !$report['dispatch_enabled'],'adapter never grants production readiness');
            if ($scenario==='clone') {
                prepared_expect($report['checks']['deployment_scope']['state']==='unavailable' && $report['configuration_fingerprint']===null,'clone cannot bind evidence');
                prepared_reject(fn()=>$adapter->preparation()); continue;
            }
            prepared_expect($report['checks']['provider_identity']['state']==='configured'
                && $report['checks']['suppression_sync']['state']==='validated'
                && $report['checks']['budget_breaker_latch']['state']==='validated','adapter reports local identity and shared sync/budget checks');
            foreach (PeraCRM_Campaign_Readiness::EXTERNAL as $name=>$source) {
                prepared_expect($report['checks'][$name]['state']==='unverified','configured dependencies never fabricate external evidence');
            }
            $fingerprint=$report['configuration_fingerprint'];
            $readiness->record($fingerprint,'retention','operator_attestation',hash('sha256','redacted synthetic artifact'),wp_create_nonce('peracrm_readiness_'.$fingerprint));
            $changed=new PeraCRM_Campaign_Deployment(array_replace($manifest,['identity_version'=>2]),$material,static fn()=>prepared_clock(),campaign_deployment_runtime($manifest));
            $changedReadiness=new PeraCRM_Campaign_Readiness($changed->binding(),null,static fn()=>prepared_clock(),$changed);
            prepared_expect($changedReadiness->assess()['checks']['retention']['state']==='unverified'
                && (bool)array_filter($changedReadiness->auditPage()['groups'],static fn($group)=>$group['fingerprint']===$fingerprint),
                'deployment revisions invalidate applicability while preserving the exact old audit');
            $transport=new PeraCRM_Prepared_Mock($deployment); $w=worker_fixture(); $claim=$w->claim($job,1)[0];
            $p=$adapter->preparation(); $preflight=new PeraCRM_Campaign_Worker_Preflight($adapter->diagnosticBudget(),static fn()=>prepared_clock());
            $filter=null;
            if ($scenario==='expiry') { $filter=prepared_interrupt(static fn()=>worker_fixture_advance(21)); }
            try {
                if ($scenario==='expiry') { prepared_reject(fn()=>$w->submitPrepared($claim,$p,new PeraCRM_Campaign_SES_Mapper(prepared_scope(),$transport),$preflight)); }
                else { prepared_expect($w->submitPrepared($claim,$p,new PeraCRM_Campaign_SES_Mapper(prepared_scope(),$transport),$preflight)['state']==='accepted','deployment adapters exercise complete mock submission'); }
            } finally { if ($filter) { remove_filter('query',$filter); } }
            $records=prepared_records($deployment);
            if ($scenario==='expiry') {
                prepared_expect($transport->calls===0 && count($records['submission_outputs'])===1,'expiry after durable preparation stops handoff');
                worker_fixture_advance(20);
                $store=new PeraCRM_CampaignRecipientPersistence($deployment,get_current_blog_id());
                $metadata=$store->attempt($claim['attempt_key']);
                $binding=['blog_id'=>get_current_blog_id(),'subscriber_id'=>(int)$claim['subscriber_id'],'mailbox'=>$claim['mailbox'],
                    'token_epoch'=>$metadata['token_epoch'],'attempt_key'=>$claim['attempt_key'],'snapshot_hash'=>$claim['content_hash']];
                [$recovered]=$adapter->recoverOutput($binding,$metadata);
                prepared_expect($recovered->hash()===$metadata['envelope_hash'] && prepared_records($deployment)===$records
                    && $transport->calls===0,'expired active ring retains exact historical recovery without issuance or transport');
                prepared_reject(fn()=>$adapter->preparation());
            } else {
                prepared_expect($transport->calls===1,'healthy adapter invokes injected mock once');
                $metadata=(new PeraCRM_CampaignRecipientPersistence($deployment,get_current_blog_id()))->attempt($claim['attempt_key']);
                $binding=['blog_id'=>get_current_blog_id(),'subscriber_id'=>(int)$claim['subscriber_id'],'mailbox'=>$claim['mailbox'],
                    'token_epoch'=>$metadata['token_epoch'],'attempt_key'=>$claim['attempt_key'],'snapshot_hash'=>$claim['content_hash']];
                $rotated=$material; $rotated['encryption']['active']='e2'; $rotated['encryption']['keys']['e2']=str_repeat('f',32);
                $rotatedManifest=array_replace($manifest,['encryption_key_id'=>'e2','configuration_version'=>2,'configuration_set'=>'rotated-fixture']);
                $rotation=new PeraCRM_Campaign_Deployment($rotatedManifest,$rotated,static fn()=>prepared_clock(),campaign_deployment_runtime($manifest));
                [$restored]=$rotation->recoverOutput($binding,$metadata);
                prepared_expect($restored->hash()===$metadata['envelope_hash'],'rotated key and advanced configuration recover exact prior-version bytes');
                $tampered=$metadata;
                $tampered['scope']['configuration_set']='invented-historical-set';
                prepared_reject(fn()=>$rotation->recoverOutput($binding,$tampered));
                unset($rotated['encryption']['keys']['e1']);
                $missing=new PeraCRM_Campaign_Deployment($rotatedManifest,$rotated,static fn()=>prepared_clock(),campaign_deployment_runtime($manifest));
                prepared_reject(fn()=>$missing->recoverOutput($binding,$metadata));
                prepared_expect(prepared_records($deployment)===$records && $transport->calls===1,'missing key never replaces output or resubmits');
            }
            $safe=wp_json_encode($readiness->assess()).print_r($adapter,true);
            foreach (['synthetic-session-only',str_repeat('Z',40),str_repeat('s',32),str_repeat('e',32),'internal-test-1@'] as $secret) {
                prepared_expect(!str_contains($safe,$secret),'diagnostics exclude material and recipients');
            }
        }
        // Invalid explicit deployment config cannot silently fall back to legacy pins.
        define('PERACRM_SES_EVENT_OPERATIONS',['account'=>'123456789012','region'=>'eu-west-1',
            'topic'=>'arn:aws:sns:eu-west-1:123456789012:fixture','configuration_set'=>'fixture',
            'configuration_version'=>1,'deployment'=>'legacy-fixture','blog'=>get_current_blog_id()]);
        define('PERACRM_CAMPAIGN_DEPLOYMENT',['enabled'=>true]);
        $blocked=peracrm_campaign_readiness_service()->assess();
        prepared_expect($blocked['checks']['deployment_scope']['state']==='unavailable' && $blocked['configuration_fingerprint']===null,
            'malformed deployment is unavailable, never an activation switch or fallback');
        prepared_expect(peracrm_sending_budget_service()===null && peracrm_ses_suppression_sync_service()===null
            && !peracrm_campaign_queue_dispatch_allowed(),'all production dispatch/sync/budget factories remain disabled');
    } finally {
        $current=peracrm_campaign_configuration(true);
        prepared_expect(!is_wp_error(peracrm_campaign_configuration_save($current['revision'],$previous['document'])),'restore configuration after adapter fixtures');
    }
});
echo "PASS deployment adapter SQL integration\n";
