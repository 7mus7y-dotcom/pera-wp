<?php
require_once __DIR__.'/scheduler-fixture.php';
$s=new PeraCRM_Fixture_Scheduler(); $mode=$args[0] ?? '';
if ($mode==='prepare') { $job=worker_fixture_reset(3); worker_fixture_target(static function () use ($job) { update_option('campaign_scheduler_race_job',$job,false); }); return; }
if ($mode==='winner') {
    $s->fake=new PeraCRM_Fake_Campaign_Provider();
    $s->fake->on_submit=static function () { if (!is_file('/tmp/peracrm-scheduler-held')) { file_put_contents('/tmp/peracrm-scheduler-held','locked'); peracrm_fixture_wait_for('/tmp/peracrm-scheduler-loser-done'); } };
    $r=$s->invoke();
    if ($r['processed']!==3 || $s->fake->calls!==3) { throw new RuntimeException('Winner processing bound failed.'); }
    return;
}
if ($mode==='loser') {
    $r=$s->invoke(); file_put_contents('/tmp/peracrm-scheduler-loser-done','done');
    if ($r['result']!=='overlap' || $r['processed']!==0) { throw new RuntimeException('Overlap was not fenced.'); }
    return;
}
if ($mode==='crash') {
    global $wpdb; $name=$s->lock();
    if ((string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$name))!=='1') { throw new RuntimeException('Crash lock unavailable.'); }
    $job=worker_fixture_target(static function () { return (int)get_option('campaign_scheduler_race_job'); });
    worker_fixture()->claim($job,1); exit(74);
}
if ($mode==='recover') {
    $job=worker_fixture_target(static function () { return (int)get_option('campaign_scheduler_race_job'); });
    worker_fixture_expire($job);
    $r=$s->invoke();
    if ($r['result']!=='completed' || $r['recovered']!==1 || $r['processed']!==3) { throw new RuntimeException('Interrupted scheduler did not release connection lock/recover safely.'); }
    echo "PASS scheduler race: process death releases lock; fenced recovery succeeds\n"; return;
}
if ($mode==='verify') {
    $job=worker_fixture_target(static function () { return (int)get_option('campaign_scheduler_race_job'); });
    if (count(array_filter(worker_fixture_rows($job),static function ($r) { return $r['state']==='accepted' && (int)$r['attempt_count']===1; }))!==3 || $s->invoke()['processed']!==0) { throw new RuntimeException('Overlap duplicated submission.'); }
    echo "PASS scheduler race: independent overlapping invocation is rejected; no accepted resend\n";
}
