<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/production-runtime.php';
require_once __DIR__.'/snapshots.php';

/** A requested operational preview, never a subscriber campaign submission. */
function peracrm_campaign_draft_test_request(array $content,string $recipient,array $scope,string $sender): array
{
    $recipient=peracrm_campaigns_normalize_email($recipient);
    if (($content['sender']['email'] ?? '')!==$sender) { throw new RuntimeException('draft_test_sender'); }
    $output=$content['output'];
    peracrm_campaign_deliverable_validate($output,peracrm_campaign_deliverable_slots());
    // No real preference token or one-click header is issued for a test.
    foreach (peracrm_campaign_deliverable_slots() as $name=>$slot) {
        $marker='{{'.$name.'}}';
        $output['html']=str_replace('<a href="'.$marker.'">'.esc_html($slot['label']).'</a>',
            '<span>'.esc_html($slot['label'].' — inactive in test email').'</span>',$output['html']);
        $output['text']=str_replace($marker,'Inactive in test email',$output['text']);
    }
    if (peracrm_campaign_deliverable_has_slot($output)) { throw new RuntimeException('draft_test_slots'); }
    $notice='TEST EMAIL — saved draft preview. Sent only to the requested test inbox. Preference links are inactive.';
    $output['html']=preg_replace('~(<body[^>]*>)~','$1<p style="padding:12px;margin:0;background:#fff3cd;color:#152640">'.$notice.'</p>',$output['html'],1);
    $part=static fn($value)=>['Data'=>$value,'Charset'=>'UTF-8'];
    return ['FromEmailAddress'=>$content['sender']['name'].' <'.$sender.'>',
        'ReplyToAddresses'=>[$content['sender']['reply_to']], 'Destination'=>['ToAddresses'=>[$recipient]],
        'Content'=>['Simple'=>['Subject'=>$part('[TEST] '.$content['model']['subject']),
            'Body'=>['Html'=>$part($output['html']),'Text'=>$part($notice."\n\n".$output['text'])]]],
        'ConfigurationSetName'=>$scope['configuration_set'],
        'EmailTags'=>[['Name'=>'peracrm_draft_test','Value'=>hash('sha256',wp_json_encode($output))]]];
}


/** Staff authority for test requests/status. Credentials are still CLI-only. */
function peracrm_campaign_draft_test_authorize(): void
{
    $actor=peracrm_get_actor_user_id();
    if ($actor<1 || peracrm_is_impersonating_crm_user() || !peracrm_campaigns_schema_ready()
        || !peracrm_campaigns_staff_can('peracrm_manage_campaigns',true)) { throw new RuntimeException('draft_test_forbidden'); }
    foreach (['manage_options','peracrm_manage_subscribers','peracrm_view_campaign_reports'] as $cap) {
        if (!user_can($actor,$cap)) { throw new RuntimeException('draft_test_forbidden'); }
    }
}

/** Target context only. Does not load SES credentials or send. */
function peracrm_campaign_draft_test_capture(int $id,int $revision,string $recipient): array
{
    peracrm_campaign_draft_test_authorize();
    global $wpdb;
        $recipient=peracrm_campaigns_normalize_email($recipient);
        $draft=peracrm_campaign_draft_get($id);
        if (!$draft || (int)$draft['revision']!==$revision || $draft['status']!=='draft') { throw new RuntimeException('draft_test_revision'); }
        $base=peracrm_campaign_deployment_service();
        $config=peracrm_campaign_production_configuration($base);
        if (!$base || !$config) { throw new RuntimeException('draft_test_configuration'); }
        $settings=defined('PERACRM_CAMPAIGN_TEST_SEND') ? PERACRM_CAMPAIGN_TEST_SEND : null;
        if (!is_array($settings) || ($settings['enabled'] ?? null)!==true
            || ($settings['deployment_fingerprint'] ?? null)!==$base->fingerprint()
            || !is_array($settings['recipients'] ?? null) || !array_is_list($settings['recipients'])
            || count($settings['recipients'])<1 || count($settings['recipients'])>5
            || !in_array($recipient,$settings['recipients'],true)) { throw new RuntimeException('draft_test_recipient_not_configured'); }
        // Explicit test permission ignores marketing unsubscribe only. Delivery-risk blocks remain denies.
        $suppression=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_email_suppressions').' WHERE normalized_email=%s',$recipient),ARRAY_A);
        peracrm_campaigns_db_require(true);
        if ($suppression && (!empty($suppression['hard_bounce']) || !empty($suppression['complaint']) || !empty($suppression['administrative']))) { throw new RuntimeException('draft_test_recipient_blocked'); }
        $content=peracrm_campaign_snapshot_candidate($draft);
        $request=peracrm_campaign_draft_test_request($content,$recipient,$base->binding(),$config['sender_email']);
        $binding=hash('sha256',wp_json_encode([$base->fingerprint(),$id,$revision,$recipient,$request]));
    return ['recipient'=>$recipient,'draft'=>$draft,'deployment'=>$base,'config'=>$config,
        'content'=>$content,'request'=>$request,'binding'=>$binding,
        'content_hash'=>hash('sha256',peracrm_campaign_snapshot_json($content))];
}

/** CLI only. No normal dispatch activation, queue writes or subscriber mutations.
 * Optional trusted PHP closures are fixture seams, never CLI/request arguments. */
function peracrm_campaign_draft_test(int $id,int $revision,string $recipient,string $request_id,bool $send,?Closure $apiTransport=null,?Closure $execute=null,?string $expected_content_hash=null): array
{
    PeraCRM_SES_Operator::authorize();
    if ($id<1 || $revision<1 || !preg_match('/\A[a-zA-Z0-9_-]{8,64}\z/',$request_id)) { throw new InvalidArgumentException('draft_test_input'); }
    return peracrm_with_target_blog(static function () use ($id,$revision,$recipient,$request_id,$send,$apiTransport,$execute,$expected_content_hash) {
        global $wpdb;
        $capture=peracrm_campaign_draft_test_capture($id,$revision,$recipient);
        $recipient=$capture['recipient']; $base=$capture['deployment']; $config=$capture['config'];
        $content=$capture['content']; $request=$capture['request']; $binding=$capture['binding'];
        if ($expected_content_hash!==null && !hash_equals($expected_content_hash,$capture['content_hash'])) { throw new RuntimeException('draft_test_content_changed'); }
        $key='peracrm_draft_test_'.hash('sha256',get_current_blog_id().'|'.peracrm_get_actor_user_id().'|'.$request_id);
        $prior=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",$key));
        peracrm_campaigns_db_require(true);
        if ($prior!==null) {
            $record=json_decode($prior,true,16,JSON_THROW_ON_ERROR);
            if (($record['binding'] ?? '')!==$binding) { throw new RuntimeException('draft_test_request_reused'); }
            return ['state'=>$record['state'],'campaign_id'=>$id,'revision'=>$revision,'recipient'=>$recipient,'already_attempted'=>true];
        }
        $summary=['state'=>'preview','campaign_id'=>$id,'revision'=>$revision,'recipient'=>$recipient,
            'subject'=>$request['Content']['Simple']['Subject']['Data'],'warnings'=>$content['warnings'],
            'dispatch_enabled'=>peracrm_campaign_queue_dispatch_allowed()];
        if (!$send) { return $summary; }
        $deployment=peracrm_campaign_production_deployment();
        $principal=$config['principal_arn'];
        $pins=array_intersect_key($deployment->binding(),array_flip(['account_id','region','deployment','blog_id']));
        $api=new PeraCRM_SES_Operator_API($pins,substr($principal,strrpos($principal,'/')+1),$deployment->credentials(),$apiTransport);
        if (str_contains($principal,':user/')) { $api->attestUser($principal); } else { $api->attest(); }
        $account=$api->quota();
        if (($account['ProductionAccessEnabled'] ?? null)!==true || ($account['EnforcementStatus'] ?? null)!=='HEALTHY') { throw new RuntimeException('draft_test_account'); }
        // Refresh the shared account quota; do not clear its spend, pending latch or breaker.
        [$second,$daily]=PeraCRM_SES_Operator::limits($account,$config['per_second_cap'],$config['daily_cap']);
        $budget=$deployment->diagnosticBudget(); $now=time();
        $budget->configure($second,$daily,$now,$now+300);
        $budget->requireDiagnosticCapacity();
        // Single atomic attempt fence. A crash/unknown response can never auto-resend this request.
        $record=wp_json_encode(['binding'=>$binding,'state'=>'started','campaign_id'=>$id,'revision'=>$revision,
            'actor'=>peracrm_get_actor_user_id(),'at'=>gmdate('c')]);
        peracrm_campaigns_db_require($wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,'no')",$key,$record))===1);
        $attempt=hash('sha256',$key); $owner=bin2hex(random_bytes(32));
        $budget->reserve($attempt,$owner);
        $budget->beginSubmission($attempt,$owner);
        $transport=new PeraCRM_Campaign_SES_SDK_Transport(array_intersect_key($deployment->binding(),array_flip(['provider','account_id','region','configuration_set','configuration_version'])),$deployment->credentials(),$execute);
        $result=$transport->sendDraftTest($request);
        $state='unknown'; $code='submission_unknown';
        if (($result['complete'] ?? false)===true && ($result['authenticated'] ?? false)===true) {
            if (($result['status'] ?? null)===200 && isset($result['message_id'])) { $state='accepted'; $code='provider_accepted'; }
            elseif (in_array($result['status'] ?? null,[400,401,403,404,429],true) && isset($result['error_code'])) { $state='rejected'; $code=$result['status']===429 ? 'provider_rate_limit' : 'provider_rejected'; }
        }
        $evidence=hash('sha256',wp_json_encode([$binding,$result]));
        $budget->recordSubmission($attempt,$owner,$code,$evidence);
        $budget->settleSubmission($attempt,$owner,$code,$evidence);
        $final=wp_json_encode(['binding'=>$binding,'state'=>$state,'campaign_id'=>$id,'revision'=>$revision,
            'actor'=>peracrm_get_actor_user_id(),'at'=>gmdate('c'),'message_id'=>$result['message_id'] ?? null]);
        peracrm_campaigns_db_require($wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s AND BINARY option_value=%s",$final,$key,$record))===1);
        return array_replace($summary,['state'=>$state,'message_id'=>$result['message_id'] ?? null,'error_code'=>$result['error_code'] ?? null]);
    });
}
