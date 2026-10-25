<?php
require_once __DIR__.'/ses-suppression-fixture.php';
require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/sns-http.php';
function sns_http_request(string $body,string $method='POST',string $type='text/plain',string $failure=''): array
{
    $headers='Content-Type: '.$type."\r\n".'X-Fixture-Failure: '.$failure."\r\n";
    $context=stream_context_create(['http'=>['method'=>$method,'header'=>$headers,'content'=>$body,'ignore_errors'=>true,'timeout'=>20,'follow_location'=>0]]);
    $response=file_get_contents(home_url('/peracrm-ses-sns/'),false,$context);
    $wire=$http_response_header??[];
    preg_match('/\AHTTP\/\S+ ([0-9]{3})/',$wire[0]??'',$status);
    sns_expect($response!==false && isset($status[1]),'actual HTTP response');
    sns_expect(in_array('Cache-Control: no-store, private, max-age=0',$wire,true),'HTTP no-store');
    sns_expect($response===($method==='HEAD'?'':((int)$status[1]===202?'Accepted.':'Request unavailable.')),'generic HTTP body');
    if ((int)$status[1]===503) { sns_expect(in_array('Retry-After: 60',$wire,true),'retry response header'); }
    return [(int)$status[1],$response];
}
peracrm_with_target_blog(static function () {
    global $wpdb;
    delete_option('sns_http_fixture');
    sns_expect(sns_http_request('{}')[0]===404,'HTTP disabled by default');
    $v=sns_values(); $v['deployment']='http-'.bin2hex(random_bytes(8));
    $scope=ses_event_scope();
    $config=['enabled'=>true,'blog'=>get_current_blog_id(),'deployment'=>$v['deployment'],'account'=>$v['scope']['account'],
        'region'=>$v['scope']['region'],'topic'=>$v['scope']['topic'],'configuration_set'=>$scope['configuration_set'],'configuration_version'=>$scope['configuration_version']];
    $fixture=['config'=>$config,'pem'=>$v['pem']];
    foreach (['enabled'=>false,'blog'=>get_current_blog_id()+1,'account'=>'999999999999','region'=>'us-east-1','deployment'=>'bad deployment'] as $key=>$value) {
        $bad=$fixture; $bad['config'][$key]=$value; update_option('sns_http_fixture',$bad,false);
        sns_expect(sns_http_request('{}')[0]===404,'HTTP deployment misconfiguration '.$key);
    }
    update_option('sns_http_fixture',$fixture,false);
    foreach (['GET','HEAD','PUT','DELETE','OPTIONS'] as $method) { sns_expect(sns_http_request('',$method)[0]===405,'HTTP method '.$method); }
    sns_expect(sns_http_request('{}','POST','application/xml')[0]===415,'HTTP unsupported content type');
    foreach (['{','null','[]',str_repeat('[',20).str_repeat(']',20)] as $body) { sns_expect(sns_http_request($body)[0]===400,'HTTP malformed envelope'); }
    sns_expect(sns_http_request(str_repeat('x',262145))[0]===413,'HTTP oversized body');
    $seed=ses_match_seed($v['deployment'],1700); $event=suppress_event($seed,'Bounce');
    $body=ses_event_body($v,$event,ses_event_id(1700)); $forged=json_decode($body,true); $forged['Message']='forged';
    $before=suppress_atomic();
    sns_expect(sns_http_request(json_encode($forged))[0]===403 && suppress_atomic()===$before,'HTTP forgery no storage');
    $wrong=sns_body($v,['TopicArn'=>'arn:aws:sns:eu-west-1:999999999999:fixture']);
    sns_expect(sns_http_request($wrong)[0]===400 && suppress_atomic()===$before,'HTTP wrong scope no storage');
    foreach (['SubscriptionConfirmation','UnsubscribeConfirmation'] as $type) { sns_expect(sns_http_request(sns_body($v,['Type'=>$type]))[0]===400,'HTTP no subscription confirmation'); }
    sns_expect(sns_http_request($body,'POST','text/plain','certificate')[0]===503 && suppress_atomic()===$before,'HTTP certificate dependency retry without receipt');
    foreach (['receipt','normalization','matching','application','application-commit'] as $n=>$point) {
        $seed=ses_match_seed($v['deployment'],1710+$n); $event=suppress_event($seed,'Bounce'); $body=ses_event_body($v,$event,ses_event_id(1710+$n));
        sns_expect(sns_http_request($body,'POST','text/plain',$point)[0]===503,'HTTP partial pipeline retries '.$point);
        $row=peracrm_campaigns_repository_identity($seed['binding']['mailbox']);
        sns_expect(empty($row['hard_bounce']),'HTTP failure does not claim committed application '.$point);
        sns_expect(sns_http_request($body)[0]===202,'HTTP duplicate resumes '.$point);
        $row=peracrm_campaigns_repository_identity($seed['binding']['mailbox']);
        sns_expect((int)$row['hard_bounce']===1,'suppression committed before HTTP success '.$point);
        $stable=suppress_atomic(); sns_expect(sns_http_request($body)[0]===202 && suppress_atomic()===$stable,'HTTP replay exact no-op '.$point);
    }
    // Same signed notification ID with changed signed content conflicts durably.
    $changed=ses_event_body($v,['eventType'=>'FutureUnsupported'],ses_event_id(1710));
    sns_expect(sns_http_request($changed)[0]===409,'HTTP signed ID collision');
    $old=$v; $old['now']-=90000;
    sns_expect(sns_http_request(ses_event_body($old,$event,ses_event_id(1720)))[0]===202,'HTTP stale event remains durable quarantine');
    foreach (['text/plain; charset=UTF-8','application/json','text/xml'] as $n=>$type) {
        $body=ses_event_body($v,['eventType'=>'FutureUnsupported'],ses_event_id(1730+$n));
        sns_expect(sns_http_request($body,'POST',$type)[0]===202,'HTTP durable unsupported quarantine '.$type);
    }
    $missing=ses_match_seed($v['deployment'],1740,false); $body=ses_event_body($v,suppress_event($missing,'Bounce'),ses_event_id(1740));
    sns_expect(sns_http_request($body)[0]===202,'HTTP durable unmatched quarantine');
    delete_option('sns_http_fixture');
});
