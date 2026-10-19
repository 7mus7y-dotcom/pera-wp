<?php
/** Separate connections exercise service concurrency only, never a delivery worker. */
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required.'); }
$mode=$args[0] ?? ''; $scenario=$args[1] ?? '';
$pairs=['prepare-prepare'=>['prepare','prepare'],'batch-batch'=>['batch','batch'],'batch-cancel'=>['batch','cancel'],'cancel-batch'=>['cancel','batch'],'pause-schedule'=>['pause','schedule'],'cancel-resume'=>['cancel','resume']];
if (!isset($pairs[$scenario])) { throw new RuntimeException('Invalid queue race.'); }
$manager=peracrm_with_target_blog(static function () { return (int)get_option('campaign_fixture_manager'); }); wp_set_current_user($manager);
if ($mode==='prepare') {
    $source=peracrm_with_target_blog(static function () { return get_option('campaign_queue_race_source'); });
    $source=peracrm_campaign_frozen_get((int)$source[0],1);
    // Read the saved fixture document through the authorized service, not snapshot model internals.
    $original=peracrm_campaign_draft_get((int)$source['campaign_id']);
    $d=peracrm_campaign_draft_save(0,0,$original['document']);
    if (is_wp_error($d)) { throw new RuntimeException('Race draft failed.'); }
    $review=peracrm_campaign_snapshot_review($d['id'],1,$d['document']);
    $s=peracrm_campaign_snapshot_capture($d['id'],1,$d['document'],$review['review_proof'],wp_create_nonce('peracrm_campaign_snapshot_'.$d['id'].'_1'));
    $fn=wp_create_nonce('peracrm_campaign_freeze_'.$d['id']);
    $a=peracrm_campaign_freeze_start($d['id'],1,(int)$s['id'],$s['content_hash'],$d['document'],$fn);
    for ($i=0;$i<100 && !(int)$a['complete'];$i++) { $a=peracrm_campaign_freeze_continue($d['id'],(int)$a['id'],(int)$a['version'],$fn); if (is_wp_error($a)) { throw new RuntimeException('Race freeze failed.'); } }
    if (is_wp_error(peracrm_campaign_freeze_publish($d['id'],(int)$a['id'],(int)$a['version'],$fn))) { throw new RuntimeException('Race publish failed.'); }
    $r=peracrm_campaign_frozen_get($d['id'],1); $c=$d['id']; $rid=(int)$r['id']; $nonce=wp_create_nonce('peracrm_campaign_queue_'.$c); $job=null;
    if ($scenario!=='prepare-prepare') {
        $job=peracrm_campaign_queue_prepare($c,$rid,$nonce);
        if ($scenario==='pause-schedule') {
            while (!(int)$job['complete']) { $job=peracrm_campaign_queue_materialize($c,(int)$job['id'],(int)$job['version'],$nonce); if (is_wp_error($job)) { throw new RuntimeException('Race queue failed.'); } }
        }
        if ($scenario==='cancel-resume') { $job=peracrm_campaign_queue_transition($c,(int)$job['id'],(int)$job['version'],$nonce,'pause'); }
    }
    peracrm_with_target_blog(static function () use ($c,$rid,$nonce,$job) { update_option('campaign_queue_race',compact('c','rid','nonce','job'),false); }); return;
}
extract(peracrm_with_target_blog(static function () { return get_option('campaign_queue_race'); }));
if ($mode==='verify') {
    $winner=json_decode(file_get_contents('/tmp/peracrm-queue-winner'),true); $loser=json_decode(file_get_contents('/tmp/peracrm-queue-loser'),true);
    $expected=$scenario==='prepare-prepare' ? 'ok' : 'campaign_conflict';
    if ($winner['result']!=='ok' || $loser['result']!==$expected || $loser['duration']<0.5 || $winner['connection']===$loser['connection']) { throw new RuntimeException('Queue race failed '.wp_json_encode([$scenario,$winner,$loser])); }
    if ($scenario==='prepare-prepare' && $winner['id']!==$loser['id']) { throw new RuntimeException('Duplicate preparation scope.'); }
    $actual=peracrm_campaign_queue_get($c,$winner['id']);
    $total=peracrm_with_target_blog(static function () use ($actual) { global $wpdb; return (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.peracrm_table('peracrm_campaign_deliveries').' WHERE job_id=%d',$actual['id'])); });
    if ($total!==(int)$actual['prepared_count'] || ($pairs[$scenario][0]==='cancel' && $actual['state']!=='cancelled')) { throw new RuntimeException('Race changed fenced state/counts.'); }
    echo 'PASS queue race: '.$scenario.' separate connections block; no duplicate scope or stale overwrite' . "\n"; return;
}
$operation=$pairs[$scenario][$mode==='winner' ? 0 : 1];
if ($mode==='winner') {
    $held=false;
    add_filter('query',static function ($sql) use (&$held,$operation) {
        $match=$operation==='prepare' ? (str_starts_with($sql,'SELECT * FROM') && str_contains($sql,'peracrm_campaign_queue_jobs')) : (str_starts_with($sql,'UPDATE') && str_contains($sql,'peracrm_campaign_queue_jobs'));
        if (!$held && $match) { $held=true; file_put_contents('/tmp/peracrm-queue-held','held'); usleep(1200000); }
        return $sql;
    });
}
global $wpdb; $connection=(int)$wpdb->get_var('SELECT CONNECTION_ID()'); $started=microtime(true);
if ($operation==='prepare') { $result=peracrm_campaign_queue_prepare($c,$rid,$nonce); }
elseif ($operation==='batch') { $result=peracrm_campaign_queue_materialize($c,(int)$job['id'],(int)$job['version'],$nonce,100); }
else { $result=peracrm_campaign_queue_transition($c,(int)$job['id'],(int)$job['version'],$nonce,$operation,$operation==='schedule' ? '2035-01-01T12:00:00Z' : null); }
file_put_contents('/tmp/peracrm-queue-'.$mode,wp_json_encode(['result'=>is_wp_error($result) ? $result->get_error_code() : 'ok','id'=>is_wp_error($result) ? 0 : (int)$result['id'],'connection'=>$connection,'duration'=>microtime(true)-$started]));
