<?php
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required.'); }
$mode=$args[0] ?? '';
$manager=peracrm_with_target_blog(static function () { return (int)get_option('campaign_fixture_manager'); });
$id=peracrm_with_target_blog(static function () { return (int)get_option('campaign_draft_race_id'); });
wp_set_current_user($manager);
if ($mode==='verify') {
    $a=json_decode(file_get_contents('/tmp/peracrm-draft-result-first'),true);
    $b=json_decode(file_get_contents('/tmp/peracrm-draft-result-second'),true);
    $row=peracrm_campaign_draft_get($id); $history=peracrm_campaign_draft_history($id);
    if ($a['result']!=='saved' || $b['result']!=='campaign_conflict' || $b['duration']<0.5 || (int)$row['revision']!==2 || $row['document']['subject']!=='first' || count($history)!==2) { throw new RuntimeException('Concurrent stale save failed: '.wp_json_encode([$a,$b,$row])); }
    echo "PASS Phase 3A: independent connections block and stale loser cannot overwrite winner/history\n";
    return;
}
if (!in_array($mode,['first','second'],true)) { throw new RuntimeException('Invalid worker'); }
if ($mode==='first') {
    add_filter('query',static function ($query) {
        if (str_starts_with($query,'INSERT INTO') && str_contains($query,'peracrm_campaign_draft_revisions')) {
            file_put_contents('/tmp/peracrm-draft-held','draft updated; revision insert pending');
            usleep(1800000);
        }
        return $query;
    });
}
$start=microtime(true); $result=peracrm_campaign_draft_save($id,1,['internal_name'=>'Concurrency fixture','subject'=>$mode]);
file_put_contents('/tmp/peracrm-draft-result-'.$mode,wp_json_encode(['result'=>is_wp_error($result)?$result->get_error_code():'saved','duration'=>microtime(true)-$start]));
