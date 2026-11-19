<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/draft-test.php';

function peracrm_campaign_draft_test_queue_prefix(int $id,int $actor): string
{
    return 'peracrm_test_queue_'.$actor.'_'.$id.'_';
}
function peracrm_campaign_draft_test_queue_read(string $key): ?array
{
    global $wpdb;
    $raw=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",$key));
    peracrm_campaigns_db_require(true);
    if ($raw===null) { return null; }
    $row=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
    if (!is_array($row) || ($row['blog'] ?? null)!==get_current_blog_id()) { throw new RuntimeException('draft_test_storage'); }
    return $row;
}
function peracrm_campaign_draft_test_queue_summary(array $row): array
{
    $state=$row['state'];
    // A lost process is never inferred to have failed safely; it is not retried.
    if ($state==='processing' && time()-($row['claimed_at'] ?? 0)>180) { $state='unknown'; }
    if ($state==='queued' && time()>$row['expires']) { $state='expired'; }
    return ['state'=>$state,'campaign_id'=>$row['campaign'],'revision'=>$row['revision'],
        'recipient'=>$row['recipient'],'request_id'=>$row['request_id'],'error_code'=>$row['error_code'] ?? null,
        'message_id'=>$row['message_id'] ?? null];
}
function peracrm_campaign_draft_test_queue_status(int $id,string $request_id=''): array
{
    return peracrm_with_target_blog(static function () use ($id,$request_id) {
        peracrm_campaign_draft_test_authorize();
        global $wpdb;
        $prefix=peracrm_campaign_draft_test_queue_prefix($id,peracrm_get_actor_user_id());
        if ($request_id!=='') {
            if (!preg_match('/\A[a-f0-9]{32}\z/',$request_id)) { throw new InvalidArgumentException(); }
            $key=$prefix.$request_id;
        } else {
            $key=$wpdb->get_var($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id DESC LIMIT 1",$wpdb->esc_like($prefix).'%'));
            peracrm_campaigns_db_require(true);
        }
        $row=$key ? peracrm_campaign_draft_test_queue_read($key) : null;
        if ($row && ($row['actor']!==peracrm_get_actor_user_id() || $row['campaign']!==$id)) { throw new RuntimeException('draft_test_forbidden'); }
        return $row ? peracrm_campaign_draft_test_queue_summary($row) : ['state'=>'idle'];
    });
}
function peracrm_campaign_draft_test_queue_submit(int $id,int $revision,string $recipient,string $request_id,array $document): array
{
    return peracrm_with_target_blog(static function () use ($id,$revision,$recipient,$request_id,$document) {
        peracrm_campaign_draft_test_authorize();
        global $wpdb;
        if (!preg_match('/\A[a-f0-9]{32}\z/',$request_id)) { throw new InvalidArgumentException(); }
        $capture=peracrm_campaign_draft_test_capture($id,$revision,$recipient);
        if (peracrm_campaign_draft_document($document)!==peracrm_campaign_draft_document($capture['draft']['document'])) { throw new RuntimeException('draft_test_unsaved'); }
        $actor=peracrm_get_actor_user_id();
        $key=peracrm_campaign_draft_test_queue_prefix($id,$actor).$request_id;
        $prior=peracrm_campaign_draft_test_queue_read($key);
        if ($prior) {
            if ($prior['revision']!==$revision || $prior['recipient']!==$capture['recipient'] || !hash_equals($prior['content_hash'],$capture['content_hash'])) { throw new RuntimeException('draft_test_request_reused'); }
            return peracrm_campaign_draft_test_queue_summary($prior);
        }
        $health=get_option('peracrm_draft_test_runner_health',[]);
        if (!is_array($health) || !is_int($health['at'] ?? null) || time()-$health['at']>180 || $health['at']>time()+5) { throw new RuntimeException('draft_test_runner_offline'); }
        $row=['state'=>'queued','blog'=>get_current_blog_id(),'actor'=>$actor,'campaign'=>$id,'revision'=>$revision,
            'recipient'=>$capture['recipient'],'content_hash'=>$capture['content_hash'],'request_id'=>$request_id,
            'created_at'=>time(),'expires'=>time()+900];
        $raw=wp_json_encode($row);
        // The unique option name is the idempotency key; duplicate AJAX submits cannot create another send.
        $insert=$wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,'no')",$key,$raw));
        peracrm_campaigns_db_require($insert);
        if ($insert!==1) {
            $prior=peracrm_campaign_draft_test_queue_read($key);
            if (!$prior || $prior['revision']!==$revision || $prior['recipient']!==$capture['recipient'] || !hash_equals($prior['content_hash'],$capture['content_hash'])) { throw new RuntimeException('draft_test_request_reused'); }
            return peracrm_campaign_draft_test_queue_summary($prior);
        }
        return peracrm_campaign_draft_test_queue_summary($row);
    });
}
/** Independent CLI runner: one request at a time, no campaign dispatch invocation. */
function peracrm_campaign_draft_test_queue_process(?Closure $apiTransport=null,?Closure $execute=null): array
{
    PeraCRM_SES_Operator::authorize();
    return peracrm_with_target_blog(static function () use ($apiTransport,$execute) {
        global $wpdb;
        update_option('peracrm_draft_test_runner_health',['at'=>time()],false);
        $entries=$wpdb->get_results($wpdb->prepare("SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value LIKE %s ORDER BY option_id LIMIT 1",$wpdb->esc_like('peracrm_test_queue_').'%', '%"state":"queued"%'),ARRAY_A);
        peracrm_campaigns_db_require(true);
        if (!$entries) { return ['state'=>'idle']; }
        $entry=$entries[0]; $row=json_decode($entry['option_value'],true,16,JSON_THROW_ON_ERROR);
        if (!is_array($row) || ($row['blog'] ?? null)!==get_current_blog_id()) { throw new RuntimeException('draft_test_storage'); }
        $row['state']='processing'; $row['claimed_at']=time(); $claim=wp_json_encode($row);
        $won=$wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s AND BINARY option_value=%s",$claim,$entry['option_name'],$entry['option_value']));
        peracrm_campaigns_db_require($won);
        if ($won!==1) { return ['state'=>'busy']; }
        $original=get_current_user_id();
        try {
            wp_set_current_user($row['actor']);
            peracrm_campaign_draft_test_authorize();
            if (time()>$row['expires']) { $row['state']='expired'; }
            else {
                $result=peracrm_campaign_draft_test($row['campaign'],$row['revision'],$row['recipient'],$row['request_id'],true,$apiTransport,$execute,$row['content_hash']);
                $row['state']=in_array($result['state'],['accepted','rejected'],true) ? $result['state'] : 'unknown';
                $row['message_id']=$result['message_id'] ?? null; $row['error_code']=$result['error_code'] ?? null;
            }
        } catch (Throwable $error) {
            $journal_key='peracrm_draft_test_'.hash('sha256',get_current_blog_id().'|'.$row['actor'].'|'.$row['request_id']);
            $journal_raw=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",$journal_key));
            $journal=$journal_raw ? json_decode($journal_raw,true) : null;
            $row['state']=$wpdb->last_error!=='' || $journal_raw!==null ? 'unknown' : 'failed';
            if (is_array($journal) && in_array($journal['state'] ?? '',['accepted','rejected'],true)) { $row['state']=$journal['state']; $row['message_id']=$journal['message_id'] ?? null; }
            $code=$error->getMessage();
            $row['error_code']=preg_match('/\A(?:draft_test|production|operator|deployment|budget)_[a-z_]{1,64}\z/',$code) ? $code : 'draft_test_unavailable';
        } finally { wp_set_current_user($original); }
        $row['finished_at']=time();
        peracrm_campaigns_db_require($wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s AND BINARY option_value=%s",wp_json_encode($row),$entry['option_name'],$claim))===1);
        return peracrm_campaign_draft_test_queue_summary($row);
    });
}
