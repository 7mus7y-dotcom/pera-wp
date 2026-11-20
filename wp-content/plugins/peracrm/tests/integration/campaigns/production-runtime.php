<?php
/** Disposable MySQL + normal production services, with all AWS/HTTP calls injected. */
require_once __DIR__.'/prepared-worker-fixture.php';
require_once WP_PLUGIN_DIR.'/peracrm/tests/deployment-fixture.php';
require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/production-runtime.php';
function production_source(string $mailbox,int $members=1): int
{
    $topic='production'.bin2hex(random_bytes(8));
    prepared_expect(!is_wp_error(peracrm_campaigns_save_subscriber($mailbox,['preferred_language'=>'en','preferences'=>[$topic]])), 'production synthetic subscriber');
    prepared_expect(!is_wp_error(peracrm_campaigns_record_consent($mailbox,'subscribed',[
        'source'=>'synthetic_fixture','source_ref'=>'production','evidence_ref'=>'synthetic','policy_version'=>'v1','occurred_at'=>gmdate('Y-m-d H:i:s')])), 'production synthetic consent');
    for ($i=1;$i<$members;$i++) {
        $extra='batch-'.$i.'@campaign-fixture.org';
        prepared_expect(!is_wp_error(peracrm_campaigns_save_subscriber($extra,['preferred_language'=>'en','preferences'=>[$topic]])), 'batch subscriber');
        prepared_expect(!is_wp_error(peracrm_campaigns_record_consent($extra,'subscribed',[
            'source'=>'synthetic_fixture','source_ref'=>'batch','evidence_ref'=>'synthetic','policy_version'=>'v1','occurred_at'=>gmdate('Y-m-d H:i:s')])), 'batch consent');
    }
    $d=peracrm_campaign_draft_save(0,0,['internal_name'=>'Production fixture','subject'=>'Production fixture',
        'sender_id'=>'production','language'=>'en','main_text'=>'Synthetic production worker message','audience'=>['version'=>1,'topic'=>$topic]]);
    prepared_expect(!is_wp_error($d),'production draft saved');
    $review=peracrm_campaign_snapshot_review($d['id'],1,$d['document']);
    $s=peracrm_campaign_snapshot_capture($d['id'],1,$d['document'],$review['review_proof'],wp_create_nonce('peracrm_campaign_snapshot_'.$d['id'].'_1'));
    $nonce=wp_create_nonce('peracrm_campaign_freeze_'.$d['id']);
    $a=peracrm_campaign_freeze_start($d['id'],1,(int)$s['id'],$s['content_hash'],$d['document'],$nonce);
    while (!(int)$a['complete']) { $a=peracrm_campaign_freeze_continue($d['id'],(int)$a['id'],(int)$a['version'],$nonce); }
    $a=peracrm_campaign_freeze_publish($d['id'],(int)$a['id'],(int)$a['version'],$nonce);
    $r=peracrm_campaign_frozen_get($d['id'],1); $nonce=wp_create_nonce('peracrm_campaign_queue_'.$d['id']);
    $j=peracrm_campaign_queue_prepare($d['id'],(int)$r['id'],$nonce);
    while (!(int)$j['complete']) { $j=peracrm_campaign_queue_materialize($d['id'],(int)$j['id'],(int)$j['version'],$nonce); }
    prepared_expect((int)$j['expected_count']===$members,'production exact normal frozen queue');
    return (int)$j['id'];
}
worker_fixture_target(static function () {
    global $wpdb;
    $wpdb->query('SET timestamp=0'); wp_set_current_user((int)get_option('campaign_fixture_administrator'));
    prepared_expect(!peracrm_campaign_queue_dispatch_allowed() && peracrm_campaign_production_service()===null,'production installation remains disabled');
    $previous=peracrm_campaign_configuration(true); $doc=$previous['document'];
    $doc['senders']=[['id'=>'production','name'=>'Production fixture','email'=>'sender@campaign-fixture.org','reply_to'=>'reply@campaign-fixture.org','approved'=>true]];
    $doc['footer']='Synthetic fixture footer'; $doc['footer_approved']=true;
    prepared_expect(!is_wp_error(peracrm_campaign_configuration_save($previous['revision'],$doc)),'production fixture configuration');
    $jobs=[];
    foreach (['accepted','withdrawn','reserved','paused','future','cancelled','unknown','recovery','diagnostic'] as $kind) {
        $jobs[$kind]=production_source($kind==='reserved'?'production@example.test':$kind.'@campaign-fixture.org');
    }
    $jobs['batch']=production_source('batch-0@campaign-fixture.org',26);
    $deployment='production-fixture'; prepared_setup($deployment);
    $manifest=campaign_deployment_manifest(get_current_blog_id(),$deployment);
    $material=campaign_deployment_material(time()); unset($material['identity']);
    define('PERACRM_CAMPAIGN_DEPLOYMENT',$manifest); define('PERACRM_CAMPAIGN_INSTANCE_ID',$manifest['instance_id']);
    define('PERACRM_CAMPAIGN_MATERIAL',$material);
    $home=static fn($url,$path)=>'https://example.org/'.ltrim($path,'/'); add_filter('home_url',$home,999,2);
    $events=['account'=>$manifest['account_id'],'region'=>$manifest['region'],'topic'=>'arn:aws:sns:eu-west-1:123456789012:fixture',
        'deployment'=>$deployment,'blog'=>get_current_blog_id(),'configuration_set'=>'fixture','configuration_version'=>1];
    define('PERACRM_SES_EVENT_OPERATIONS',$events); define('PERACRM_SES_SNS_HTTP',$events+['enabled'=>true]);
    $base=peracrm_campaign_deployment_service(); $fingerprint=$base->fingerprint();
    define('PERACRM_CAMPAIGN_PUBLIC_ENDPOINTS',['enabled'=>true,'deployment_fingerprint'=>$fingerprint]);
    define('PERACRM_CAMPAIGN_WITHDRAWAL_HTTP',true);
    $private=sys_get_temp_dir().'/production-'.bin2hex(random_bytes(8)); mkdir($private,0700);
    $file=$private.'/credentials.json';
    file_put_contents($file,json_encode(['AccessKeyId'=>str_repeat('A',20),'SecretAccessKey'=>str_repeat('Z',40),'Expiration'=>gmdate('c',time()+3600)])); chmod($file,0600);
    $config=['enabled'=>true,'deployment_fingerprint'=>$fingerprint,'credentials_file'=>$file,
        'principal_arn'=>'arn:aws:iam::123456789012:user/ProductionFixture','sender_email'=>'sender@campaign-fixture.org',
        'job_ids'=>array_values($jobs),'per_second_cap'=>100,'daily_cap'=>5000];
    define('PERACRM_CAMPAIGN_DISPATCH',$config);
    try {
        wp_set_current_user(0); prepared_reject(fn()=>peracrm_campaign_production_deployment());
        wp_set_current_user((int)get_option('campaign_fixture_administrator'));
        chmod($file,0644); clearstatcache(); prepared_reject(fn()=>peracrm_campaign_production_deployment());
        chmod($file,0600); clearstatcache();
        rename($file,$file.'.real'); symlink($file.'.real',$file); clearstatcache();
        prepared_reject(fn()=>peracrm_campaign_production_deployment()); unlink($file); rename($file.'.real',$file); clearstatcache();
        $bytes=file_get_contents($file); $expired=json_decode($bytes,true); $expired['Expiration']=gmdate('c',time()-60);
        file_put_contents($file,json_encode($expired)); prepared_reject(fn()=>peracrm_campaign_production_deployment()); file_put_contents($file,$bytes);
        $adapter=peracrm_campaign_production_deployment();
        prepared_expect($adapter->checks()['provider_identity']==='configured' && peracrm_campaign_production_http_ready($adapter), 'protected CLI identity and credential-free public factories');
        prepared_expect($base->checks()['provider_identity']==='unavailable','CLI credentials never enter HTTP deployment material');
        $apiMode='healthy'; $principal=$config['principal_arn']; $reads=[];
        $api=static function ($request) use (&$apiMode,$principal,&$reads) {
            $url=(string)$request->getUri(); $reads[]=$url;
            if (str_contains($url,'sts.')) {
                $arn=$apiMode==='wrong-principal'?'arn:aws:iam::123456789012:user/Other':$principal;
                $body='<GetCallerIdentityResponse xmlns="https://sts.amazonaws.com/doc/2011-06-15/"><GetCallerIdentityResult><Account>123456789012</Account><Arn>'.$arn.'</Arn></GetCallerIdentityResult></GetCallerIdentityResponse>';
            } elseif (str_contains($url,'/suppression/')) { $body='{"SuppressedDestinationSummaries":[]}'; }
            else { $body=json_encode(['ProductionAccessEnabled'=>$apiMode!=='sandbox','SendingEnabled'=>true,'EnforcementStatus'=>'HEALTHY','SendQuota'=>['MaxSendRate'=>100,'Max24HourSend'=>50000,'SentLast24Hours'=>0]]); }
            return ['complete'=>true,'status'=>200,'body'=>$body];
        };
        $transport=new class implements PeraCRM_Campaign_SES_Transport {
            public int $calls=0; public string $mode='accepted'; public string $prefix='production-fixture-'; public array $requests=[];
            public function send(array $request,array $options): array {
                ++$this->calls; $this->requests[]=$request;
                if ($this->mode==='unknown') { throw new RuntimeException('synthetic transport loss'); }
                return ['complete'=>true,'authenticated'=>true,'status'=>200,'message_id'=>$this->prefix.$this->calls];
            }
        };
        $service=new PeraCRM_Campaign_Production_Runtime($adapter,$config,$api,$transport,static fn()=>null);
        prepared_reject(fn()=>$service->run(999999));
        $endpointProbe=new PeraCRM_Campaign_Production_Runtime($adapter,$config,$api,$transport);
        $endpointMethod=new ReflectionMethod(PeraCRM_Campaign_Production_Runtime::class,'endpoints');
        $probes=[];
        $http=static function ($pre,$args,$url) use (&$probes) {
            $probes[]=[$url,$args]; $status=400;
            if (str_contains($url,'/email-preferences/')) {
                parse_str(parse_url($url,PHP_URL_QUERY)??'',$query);
                // The real route selects its legacy HTTP-200 page unless pc1. selects the recipient handler.
                $status=($query['token']??null)==='pc1.production-probe-invalid'?400:200;
            }
            return ['response'=>['code'=>$status,'message'=>'Synthetic'],'headers'=>[],'body'=>'Invalid link.','cookies'=>[]];
        };
        add_filter('pre_http_request',$http,999,3);
        try { $endpointMethod->invoke($endpointProbe); }
        finally { remove_filter('pre_http_request',$http,999); }
        prepared_expect(count($probes)===3 && $transport->calls===0
            && !array_filter($probes,static fn($r)=>($r[1]['sslverify']??false)!==true || ($r[1]['redirection']??1)!==0),
            'native production probe selects recipient preferences with a fixed invalid token and TLS/no-redirect checks');
        $apiMode='wrong-principal'; prepared_reject(fn()=>$service->run($jobs['accepted']));
        $apiMode='sandbox'; prepared_reject(fn()=>$service->run($jobs['accepted'])); $apiMode='healthy';
        prepared_expect($transport->calls===0,'identity/sandbox/allowlist failures before sends');
        $refresh=$service->refresh(); prepared_expect($refresh['state']==='refreshed' && $refresh['daily']===5000,'production imports account denies and fresh bounded quota');
        $readiness=new PeraCRM_Campaign_Readiness($adapter->binding(),null,null,$adapter);
        $report=$readiness->assess();
        prepared_expect(!$report['production_ready'] && $service->run($jobs['accepted'])['state']==='blocked' && $transport->calls===0,'missing external evidence blocks before claims');
        foreach (PeraCRM_Campaign_Readiness::EXTERNAL as $name=>$source) {
            if ($name==='host_log_protections') { continue; }
            $fp=$report['configuration_fingerprint'];
            $readiness->record($fp,$name,$source,hash('sha256','synthetic-'.$name),wp_create_nonce('peracrm_readiness_'.$fp));
        }
        $report=$readiness->assess();
        prepared_expect($report['production_ready']
            && $report['checks']['host_log_protections']['state']==='unverified'
            && $report['warnings']===['host_log_protections:unverified']
            && !in_array('host_log_protections:unverified',$report['blockers'],true),
            'unverified host logs remain advisory while other send prerequisites hold');
        $lock='peracrm-production-'.substr(hash('sha256',$manifest['account_id'].'|'.$manifest['region']),0,40);
        $other=new wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);
        prepared_expect((string)$other->get_var($other->prepare('SELECT GET_LOCK(%s,0)',$lock))==='1','second connection holds production account lock');
        try { prepared_expect($service->run($jobs['accepted'])['state']==='overlap' && $transport->calls===0,'production overlap never claims or sends'); }
        finally { $other->get_var($other->prepare('SELECT RELEASE_LOCK(%s)',$lock)); $other->close(); }
        $blocked=new PeraCRM_Campaign_Production_Runtime($adapter,$config,$api,$transport,static function () { throw new RuntimeException('production_http'); });
        prepared_reject(fn()=>$blocked->run($jobs['accepted']));
        $d=peracrm_table('peracrm_campaign_deliveries'); $j=peracrm_table('peracrm_campaign_queue_jobs');
        prepared_expect((int)$wpdb->get_var($wpdb->prepare('SELECT attempt_count FROM '.$d.' WHERE job_id=%d',$jobs['accepted']))===0,'HTTP outage preserves unclaimed recipient');
        foreach (['paused','cancelled'] as $state) { $wpdb->update($j,['state'=>$state],['id'=>$jobs[$state]]); prepared_expect($service->run($jobs[$state])['processed']===0,'production respects '.$state); }
        $wpdb->update($j,['state'=>'scheduled','scheduled_at'=>gmdate('Y-m-d H:i:s',time()+86400)],['id'=>$jobs['future']]);
        prepared_expect($service->run($jobs['future'])['processed']===0,'production respects future schedule');
        peracrm_campaigns_record_consent('withdrawn@campaign-fixture.org','unsubscribed',['source'=>'synthetic_fixture','source_ref'=>'withdraw','evidence_ref'=>'synthetic','policy_version'=>'v1','occurred_at'=>gmdate('Y-m-d H:i:s')]);
        prepared_expect($service->run($jobs['withdrawn'])['excluded']===1 && $transport->calls===0,'withdrawal after freeze blocks transmission');
        prepared_expect($service->run($jobs['reserved'])['excluded']===1 && $transport->calls===0,'reserved fixture domain never sent in production');
        $result=$service->run($jobs['accepted']);
        prepared_expect($result['accepted']===1 && $transport->calls===1,'production prepared real ledger accepted once');
        $request=$transport->requests[0];
        prepared_expect($request['Destination']===['ToAddresses'=>['accepted@campaign-fixture.org']] && count($request['EmailTags'])===4 && count($request['Content']['Simple']['Headers'])===2,'production correlation and one-click headers');
        $service->run($jobs['accepted']); prepared_expect($transport->calls===1,'accepted replay cannot resend');
        $batchTransport=clone $transport; $batchTransport->calls=0; $batchTransport->prefix='production-batch-';
        $batchService=new PeraCRM_Campaign_Production_Runtime($adapter,$config,$api,$batchTransport,static fn()=>null);
        prepared_reject(fn()=>$batchService->scheduled(101));
        prepared_reject(fn()=>$batchService->run($jobs['batch'],101));
        $batchResult=$batchService->run($jobs['batch'],100);
        prepared_expect($batchResult['accepted']===26 && $batchTransport->calls===26, 'bounded production batch crosses old 25-recipient limit');
        $batchService->run($jobs['batch'],100);
        prepared_expect($batchTransport->calls===26, 'larger batch never resends accepted recipients');
        parse_str(parse_url(trim($request['Content']['Simple']['Headers'][0]['Value'],'<>'),PHP_URL_QUERY),$query);
        preg_match('~https://example\.org/email-preferences/\?[^"<>\s]+~',html_entity_decode($request['Content']['Simple']['Body']['Html']['Data'],ENT_QUOTES),$preference);
        parse_str(parse_url($preference[0]??'',PHP_URL_QUERY)??'',$profile);
        $preferences=peracrm_campaign_recipient_preferences_service();
        prepared_expect($preferences->request('GET',$profile['token']??'','',[],'https://example.org','https://example.org','same-origin')[0]===200,'generic production preferences factory verifies the prepared recipient token');
        $withdrawal=peracrm_campaign_recipient_withdrawal_service();
        prepared_expect($withdrawal->request('GET',false,$query['token'],'',[])[0]===200,'generic production withdrawal displays without mutation');
        prepared_expect($withdrawal->request('POST',true,$query['token'],'application/x-www-form-urlencoded','List-Unsubscribe=One-Click')[0]===200 && !peracrm_campaigns_is_eligible('accepted@campaign-fixture.org',[]),'generic production one-click withdrawal');
        // A requested draft test is independent of this inbox's marketing withdrawal.
        require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/draft-test.php';
        define('PERACRM_CAMPAIGN_TEST_SEND',['enabled'=>true,'deployment_fingerprint'=>$fingerprint,
            'recipients'=>['accepted@campaign-fixture.org','another@campaign-fixture.org']]);
        $draftTest=peracrm_campaign_draft_save(0,0,['internal_name'=>'Draft test','subject'=>'Draft only',
            'sender_id'=>'production','language'=>'en','main_text'=>'**Preview**','text_format'=>'basic_v1']);
        prepared_expect(!is_wp_error($draftTest),'test draft saved independently of queue');
        $protected=static function () use ($wpdb) {
            $state=[];
            foreach (['peracrm_subscribers','peracrm_email_suppressions','peracrm_subscription_events','peracrm_email_suppression_events',
                'peracrm_campaign_queue_jobs','peracrm_campaign_deliveries','peracrm_subscriber_tokens'] as $table) {
                $state[$table]=$wpdb->get_results('SELECT * FROM '.peracrm_table($table),ARRAY_A);
                peracrm_campaigns_db_require(true);
            }
            return hash('sha256',wp_json_encode($state));
        };
        $legacyTest=$draftTest['document'];
        $legacyTest['introduction']="First\r\nSecond";
        $legacyTest['main_text']="**Preview**\r\nAnother paragraph";
        $legacyBytes=wp_json_encode($legacyTest);
        $wpdb->update(peracrm_table('peracrm_campaign_drafts'),['document_json'=>$legacyBytes],['id'=>$draftTest['id']]);
        peracrm_campaigns_db_require(true);
        $browserTest=$legacyTest;
        $browserTest['introduction']="First\nSecond";
        $browserTest['main_text']="**Preview**\nAnother paragraph";
        $draftTest['document']=$browserTest;
        $beforeTest=$protected(); $testCalls=[];
        $testHTTP=static function ($url,$curl) use (&$testCalls) {
            $testCalls[]=json_decode($curl[CURLOPT_POSTFIELDS],true);
            $body='{"MessageId":"draft-test-synthetic"}';
            return ['errno'=>0,'status'=>200,'body'=>$body,'headers'=>"HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nx-amzn-RequestId: fixture-test\r\nContent-Length: ".strlen($body)."\r\n\r\n"];
        };
        $inspect=peracrm_campaign_draft_test($draftTest['id'],1,'accepted@campaign-fixture.org','fixture-preview-only',false,$api,$testHTTP);
        prepared_expect($inspect['state']==='preview' && !$testCalls && $protected()===$beforeTest,'draft test inspection has no provider or protected-ledger writes');
        prepared_reject(fn()=>peracrm_campaign_draft_test($draftTest['id'],2,'accepted@campaign-fixture.org','fixture-stale-version',true,$api,$testHTTP));
        prepared_reject(fn()=>peracrm_campaign_draft_test($draftTest['id'],1,'unlisted@campaign-fixture.org','fixture-wrong-inbox',true,$api,$testHTTP));
        $sentTest=peracrm_campaign_draft_test($draftTest['id'],1,'accepted@campaign-fixture.org','fixture-send-once',true,$api,$testHTTP);
        prepared_expect($sentTest['state']==='accepted' && count($testCalls)===1,'saved draft goes through real signer to one explicit test inbox');
        $duplicateTest=peracrm_campaign_draft_test($draftTest['id'],1,'accepted@campaign-fixture.org','fixture-send-once',true,$api,$testHTTP);
        prepared_expect($duplicateTest['already_attempted'] && count($testCalls)===1,'test request replay never resends');
        prepared_reject(fn()=>peracrm_campaign_draft_test($draftTest['id'],1,'another@campaign-fixture.org','fixture-send-once',true,$api,$testHTTP));
        prepared_expect($protected()===$beforeTest && peracrm_campaign_queue_dispatch_allowed(),'test preserves withdrawn consent, suppressions, subscriber tokens, queue and existing dispatch setting');
        require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/draft-test-queue.php';
        update_option('peracrm_draft_test_runner_health',['at'=>time()],false);
        $queueID=str_repeat('a',32);
        $queuedTest=peracrm_campaign_draft_test_queue_submit($draftTest['id'],1,'accepted@campaign-fixture.org',$queueID,$draftTest['document']);
        prepared_expect($queuedTest['state']==='queued' && count($testCalls)===1 && $protected()===$beforeTest,'frontend request queues without provider calls or protected-ledger changes');
        prepared_expect($wpdb->get_var($wpdb->prepare('SELECT document_json FROM '.peracrm_table('peracrm_campaign_drafts').' WHERE id=%d',$draftTest['id']))===$legacyBytes,'LF browser test accepts saved CRLF paragraphs without rewriting saved bytes');
        $queuedAgain=peracrm_campaign_draft_test_queue_submit($draftTest['id'],1,'accepted@campaign-fixture.org',$queueID,$draftTest['document']);
        prepared_expect($queuedAgain['request_id']===$queueID,'double-click preserves the same queued request');
        $processedTest=peracrm_campaign_draft_test_queue_process($api,$testHTTP);
        prepared_expect($processedTest['state']==='accepted' && count($testCalls)===2,'independent CLI runner sends queued test through signer');
        prepared_expect(peracrm_campaign_draft_test_queue_status($draftTest['id'],$queueID)['state']==='accepted','frontend status reports SES acceptance');
        prepared_expect(peracrm_campaign_draft_test_queue_process($api,$testHTTP)['state']==='idle' && count($testCalls)===2,'runner never retries an accepted request');
        prepared_expect(peracrm_campaign_draft_test_queue_submit($draftTest['id'],1,'accepted@campaign-fixture.org',$queueID,$draftTest['document'])['state']==='accepted','replayed frontend request returns its terminal result');
        prepared_expect($protected()===$beforeTest,'queued test preserves subscriber, consent, suppression, tokens and campaign ledgers');
        $staleID=str_repeat('b',32);
        peracrm_campaign_draft_test_queue_submit($draftTest['id'],1,'accepted@campaign-fixture.org',$staleID,$draftTest['document']);
        $nextTest=peracrm_campaign_draft_save($draftTest['id'],1,array_replace($draftTest['document'],['subject'=>'Changed saved draft']));
        prepared_expect(!is_wp_error($nextTest),'test draft can change while queued');
        $staleResult=peracrm_campaign_draft_test_queue_process($api,$testHTTP);
        prepared_expect($staleResult['state']==='failed' && $staleResult['error_code']==='draft_test_revision' && count($testCalls)===2,'changed saved revision is rejected before transport');
        prepared_reject(fn()=>peracrm_campaign_draft_test_queue_submit($draftTest['id'],2,'accepted@campaign-fixture.org',str_repeat('c',32),array_replace($nextTest['document'],['subject'=>'Unsaved browser edit'])));
        // A process lost after budget begin must recover the original owner without resending.
        $worker=new PeraCRM_Campaign_Production_Worker($adapter,$config['job_ids'],static fn()=>PeraCRM_SES_Operator::authorize());
        $claim=$worker->claim($jobs['recovery'],1)[0]; $budget=$adapter->diagnosticBudget();
        $budget->reserve($claim['attempt_key'],$claim['owner_token']); $budget->beginSubmission($claim['attempt_key'],$claim['owner_token']);
        $wpdb->update($d,['state'=>'inflight','lease_until'=>gmdate('Y-m-d H:i:s',time()-60)],['id'=>$claim['id']]);
        $recovery=$service->run($jobs['recovery'],1,true);
        prepared_expect($recovery['recovered']===1 && !$budget->health()['submission_pending'] && $budget->health()['breaker']==='operator' && $transport->calls===1,'production pending recovery never sends or refunds and preserves operator hold');
        prepared_expect($wpdb->get_var($wpdb->prepare('SELECT state FROM '.$d.' WHERE id=%d',$claim['id']))==='unknown','expired inflight remains unknown');
        prepared_expect($service->run($jobs['unknown'])['state']==='blocked' && $transport->calls===1,'operator breaker prevents further send');
        // Do not reset the breaker in production; fixture admin exercises the existing audited recovery.
        $budget->recover($budget->health()['breaker_version']);
        $transport->mode='unknown'; $result=$service->scheduled(1);
        prepared_expect(($result['job_id']??null)===$jobs['unknown'],'scheduler considers only approved due claimable jobs');
        prepared_expect($result['state']==='blocked' && $transport->calls===2,'production ambiguous outcome stops without retry');
        $service->run($jobs['unknown']); prepared_expect($transport->calls===2,'ambiguous replay never resends');
        prepared_expect(!str_contains(json_encode($result),str_repeat('Z',40)),'production output redacted');
        $budget->recover($budget->health()['breaker_version']);
        $unknownID=str_repeat('d',32);
        peracrm_campaign_draft_test_queue_submit($draftTest['id'],2,'accepted@campaign-fixture.org',$unknownID,$nextTest['document']);
        $unknownCalls=0;
        $lostHTTP=static function () use (&$unknownCalls) { $unknownCalls++; return ['errno'=>1,'status'=>0,'headers'=>'','body'=>'']; };
        $unknownTest=peracrm_campaign_draft_test_queue_process($api,$lostHTTP);
        prepared_expect($unknownTest['state']==='unknown' && $unknownCalls===1,'unknown test response is reported honestly');
        prepared_expect(peracrm_campaign_draft_test_queue_process($api,$lostHTTP)['state']==='idle' && $unknownCalls===1,'unknown test is not automatically retried');

        // The real SDK transport supplies a redacted diagnostic to the production runner.
        $budget->recover($budget->health()['breaker_version']);
        $diagnosticCalls=0;
        $diagnosticHTTP=static function () use (&$diagnosticCalls) {
            $diagnosticCalls++;
            return ['errno'=>28,'status'=>0,'headers'=>'private-response-header','body'=>'private-response-body',
                'timings_ms'=>['dns'=>20,'connect'=>0,'tls'=>0,'total'=>3000],'uploaded_bytes'=>0];
        };
        $diagnosticTransport=new PeraCRM_Campaign_SES_SDK_Transport(
            $adapter->preparation()->scope(),$adapter->credentials(),$diagnosticHTTP);
        $diagnosticService=new PeraCRM_Campaign_Production_Runtime(
            $adapter,$config,$api,$diagnosticTransport,static fn()=>null);
        $diagnosticResult=$diagnosticService->run($jobs['diagnostic'],1);
        $diagnostic=get_option('peracrm_campaign_transport_last_failure');
        prepared_expect($diagnosticResult['state']==='blocked' && $diagnosticCalls===1
            && $diagnostic['job_id']===$jobs['diagnostic']
            && $diagnostic['delivery_id']>0
            && $diagnostic['transport']['code']==='transfer_incomplete'
            && $diagnostic['transport']['errno']===28
            && $diagnostic['transport']['total_ms']===3000,
            'unknown send retains scalar transport diagnosis under its job and delivery');
        prepared_expect(!str_contains(json_encode($diagnostic),'private-response')
            && !str_contains(json_encode($diagnostic),'@')
            && !str_contains(json_encode($diagnostic),str_repeat('Z',40)),
            'persisted diagnostic excludes provider text, mailboxes and credentials');
        prepared_expect($diagnosticService->run($jobs['diagnostic'])['state']==='blocked'
            && $diagnosticCalls===1,'diagnostic capture never retries unknown delivery or clears breaker');


    } finally {
        unlink($file); rmdir($private); remove_filter('home_url',$home,999);
        $current=peracrm_campaign_configuration(true); peracrm_campaign_configuration_save($current['revision'],$previous['document']);
        foreach (['peracrm_campaign_scheduler_last','peracrm_campaign_scheduler_last_success','peracrm_campaign_transport_last_failure'] as $key) { delete_option($key); }
    }
});
echo "PASS production runtime WordPress/MySQL fixture\n";
