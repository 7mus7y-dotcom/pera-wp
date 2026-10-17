<?php
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Fixture only.'); }
$mode = $args[0] ?? '';
$manager = peracrm_with_target_blog(static function () { return (int) get_option('campaign_fixture_manager'); });
wp_set_current_user($manager);
if ($mode === 'import') {
    $stream = fopen('php://temp','w+');
    fwrite($stream,"email,state,source,source_ref,evidence_ref,policy_version,occurred_at\nrace-phase2@example.test,subscribed,archive,src,consent,v1,2020-01-01 00:00:00\n"); rewind($stream);
    $batch = peracrm_subscribers_preview($stream,array_flip(['email','state','source','source_ref','evidence_ref','policy_version','occurred_at']),true,true); fclose($stream);
    peracrm_subscribers_apply_batch($batch);
} elseif ($mode === 'withdraw') {
    peracrm_campaigns_staff_mutate('race-phase2@example.test', static function ($email,$subscriber,$suppression,$actor) {
        file_put_contents('/tmp/peracrm-phase2-held','ready');
        usleep(1500000);
        peracrm_campaigns_repository_suppress($email,'unsubscribe',peracrm_campaigns_evidence(['source'=>'fixture-race']),$actor);
    });
} else {
    if (peracrm_campaigns_is_eligible('race-phase2@example.test')) { throw new RuntimeException('Concurrent import overrode withdrawal.'); }
    echo "PASS Phase 2 independent connections: concurrent import cannot override withdrawal\n";
}
