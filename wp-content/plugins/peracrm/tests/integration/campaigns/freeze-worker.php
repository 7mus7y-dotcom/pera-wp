<?php
/** Separate WP processes/connections; barriers are test-only query filters. */
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required.'); }
$mode=$args[0] ?? ''; $scenario=$args[1] ?? '';
$manager=peracrm_with_target_blog(static function () { return (int)get_option('campaign_fixture_manager'); });
wp_set_current_user($manager);
if ($mode==='restore') {
    $admin=peracrm_with_target_blog(static function () { return (int)get_option('campaign_fixture_administrator'); });
    wp_set_current_user($admin);
    $previous=peracrm_with_target_blog(static function () { return get_option('campaign_freeze_previous_configuration'); });
    $current=peracrm_campaign_configuration(true);
    if (is_wp_error(peracrm_campaign_configuration_save($current['revision'],$previous))) { throw new RuntimeException('Fixture configuration restoration failed.'); }
    echo "PASS freeze: restored pre-suite configuration for existing browser fixtures\n";
    return;
}
$pairs=['start-start'=>['start','start'],'start-save'=>['start','save'],'continue-cancel'=>['continue','cancel'],
    'cancel-continue'=>['cancel','continue'],'continue-continue'=>['continue','continue'],'publish-cancel'=>['publish','cancel'],'cancel-publish'=>['cancel','publish'],'cleanup-start'=>['cleanup','start'],'cleanup-resume'=>['cleanup','resume']];
if (!isset($pairs[$scenario])) { throw new RuntimeException('Invalid race scenario.'); }
if ($mode==='prepare') {
    $source=peracrm_with_target_blog(static function () { return get_option('campaign_freeze_race_set'); });
    $draft=peracrm_campaign_draft_save(0,0,$source[0]['document']);
    if (is_wp_error($draft)) { throw new RuntimeException('Race draft failed.'); }
    $review=peracrm_campaign_snapshot_review($draft['id'],1,$draft['document']);
    $snapshot=peracrm_campaign_snapshot_capture($draft['id'],1,$draft['document'],$review['review_proof'],wp_create_nonce('peracrm_campaign_snapshot_'.$draft['id'].'_1'));
    if (is_wp_error($snapshot)) { throw new RuntimeException('Race capture failed.'); }
    $nonce=wp_create_nonce('peracrm_campaign_freeze_'.$draft['id']);
    $attempt=null;
    if ($pairs[$scenario][0]!=='start') {
        $attempt=peracrm_campaign_freeze_start($draft['id'],1,(int)$snapshot['id'],$snapshot['content_hash'],$draft['document'],$nonce);
        if (in_array('publish',$pairs[$scenario],true)) {
            for ($i=0;$i<100 && !(int)$attempt['complete'];$i++) {
                $attempt=peracrm_campaign_freeze_continue($draft['id'],(int)$attempt['id'],(int)$attempt['version'],$nonce);
                if (is_wp_error($attempt)) { throw new RuntimeException('Race materialization failed.'); }
            }
        }
    }
    if ($pairs[$scenario][0]==='cleanup') {
        peracrm_with_target_blog(static function () use ($attempt,$scenario) { global $wpdb; $wpdb->update(peracrm_table('peracrm_campaign_freeze_attempts'),['state'=>$scenario==='cleanup-resume' ? 'failed' : 'cancelled','updated_at'=>'2000-01-01 00:00:00'],['id'=>$attempt['id']]); });
    }
    peracrm_with_target_blog(static function () use ($draft,$snapshot,$nonce,$attempt) { update_option('campaign_freeze_race',compact('draft','snapshot','nonce','attempt'),false); });
    return;
}
$set=peracrm_with_target_blog(static function () { return get_option('campaign_freeze_race'); });
extract($set);
if ($mode==='verify') {
    $winner=json_decode(file_get_contents('/tmp/peracrm-freeze-winner'),true);
    $loser=json_decode(file_get_contents('/tmp/peracrm-freeze-loser'),true);
    $expected=$scenario==='start-start' ? 'ok' : ($scenario==='start-save' ? 'campaign_invalid' : 'campaign_conflict');
    if ($winner['result']!=='ok' || $loser['result']!==$expected || $loser['duration']<0.5 || $winner['connection']===$loser['connection']) { throw new RuntimeException('Freeze race failed '.wp_json_encode([$scenario,$winner,$loser])); }
    $current=peracrm_with_target_blog(static function () use ($draft) { global $wpdb; return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_freeze_attempts').' WHERE campaign_id=%d',$draft['id']),ARRAY_A); });
    if ($scenario==='start-start' && $winner['id']!==$loser['id']) { throw new RuntimeException('Competing starts duplicated attempt.'); }
    $state=in_array($pairs[$scenario][0],['cancel','cleanup'],true) ? 'cancelled' : ($pairs[$scenario][0]==='publish' ? 'frozen' : 'materializing');
    if ($current['state']!==$state) { throw new RuntimeException('Race state changed after stale loser.'); }
    if ($pairs[$scenario][0]==='cleanup') {
        if (!$current || peracrm_campaign_freeze_recipients($draft['id'],(int)$current['id']) || peracrm_campaign_freeze_recipients($draft['id'],(int)$current['id'],0,true)) { throw new RuntimeException('Cleanup race lost tombstone or retained payloads.'); }
        $revive=peracrm_campaign_freeze_resume($draft['id'],(int)$current['id'],(int)$current['version'],$nonce);
        if (!is_wp_error($revive)) { throw new RuntimeException('Cleaned terminal owner revived.'); }
    }
    if ($attempt) {
        $stale=peracrm_campaign_freeze_cancel($draft['id'],(int)$attempt['id'],(int)$attempt['version'],$nonce);
        if (!is_wp_error($stale)) { throw new RuntimeException('Stale owner was not fenced.'); }
    }
    echo 'PASS freeze race: '.$scenario.' separate connections block; winner commits; stale loser cannot overwrite' . "\n";
    return;
}
if (!in_array($mode,['winner','loser'],true)) { throw new RuntimeException('Invalid worker mode.'); }
$operation=$pairs[$scenario][$mode==='winner' ? 0 : 1];
if ($mode==='winner') {
    $paused=false;
    add_filter('query',static function ($sql) use (&$paused,$operation) {
        $match=$operation==='cleanup' ? str_starts_with($sql,'DELETE FROM') && str_contains($sql,'peracrm_campaign_recipient_evaluations') : ($operation==='start' ? str_starts_with($sql,'INSERT INTO') && str_contains($sql,'peracrm_campaign_freeze_attempts')
            : ($operation==='publish' ? str_starts_with($sql,'INSERT INTO') && str_contains($sql,'peracrm_campaign_frozen_revisions')
            : str_starts_with($sql,'UPDATE') && str_contains($sql,'peracrm_campaign_freeze_attempts')));
        if (!$paused && $match) { $paused=true; file_put_contents('/tmp/peracrm-freeze-held','draft and attempt locks held'); usleep(1800000); }
        return $sql;
    });
}
$start=microtime(true);
if ($operation==='cleanup') {
    $admin=peracrm_with_target_blog(static function () { return (int)get_option('campaign_fixture_administrator'); }); wp_set_current_user($admin);
    $result=peracrm_campaign_freeze_cleanup($draft['id'],(int)$attempt['id'],wp_create_nonce('peracrm_campaign_freeze_'.$draft['id']));
}
elseif ($operation==='start') { $result=peracrm_campaign_freeze_start($draft['id'],1,(int)$snapshot['id'],$snapshot['content_hash'],$draft['document'],$nonce); }
elseif ($operation==='save') { $result=peracrm_campaign_draft_save($draft['id'],1,array_replace($draft['document'],['subject'=>'Racing edit'])); }
else { $fn='peracrm_campaign_freeze_'.$operation; $result=$fn($draft['id'],(int)$attempt['id'],(int)$attempt['version'],$nonce); }
$connection=peracrm_with_target_blog(static function () { global $wpdb; return $wpdb->get_var('SELECT CONNECTION_ID()'); });
file_put_contents('/tmp/peracrm-freeze-'.$mode,wp_json_encode(['result'=>is_wp_error($result)?$result->get_error_code():'ok','id'=>is_wp_error($result)?0:(int)($result['id'] ?? 0),'duration'=>microtime(true)-$start,'connection'=>$connection]));
