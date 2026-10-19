<?php
/** Pure contract and service-entrypoint authority checks; SQL behavior runs in real WP. */
require __DIR__.'/campaign-snapshots-test.php';
require $root.'/inc/campaigns/queue.php';
foreach (['2035-01-01 12:00:00','2035-02-30T12:00:00Z','2035-01-01T12:00:00-00:00','2035-01-01T12:00:00+14:01','2035-01-01T25:00:00Z','2020-01-01T00:00:00Z','2035-11-04T01:30:00 America/New_York',null] as $input) {
    campaign_expect(snapshot_rejects(static function () use ($input) { peracrm_campaign_queue_schedule_utc($input); }),'invalid/naive/unknown-offset/past schedule rejected');
}
campaign_expect(peracrm_campaign_queue_schedule_utc('2035-01-01T12:00:00+03:00')==='2035-01-01 09:00:00','explicit offset converts to UTC');
campaign_expect(peracrm_campaign_queue_schedule_utc('2035-11-04T01:30:00-04:00')!==peracrm_campaign_queue_schedule_utc('2035-11-04T01:30:00-05:00'),'offset explicitly disambiguates repeated local hour');
foreach ([0,3] as $actor) {
    $GLOBALS['campaign_actor']=$actor;
    campaign_expect(is_wp_error(peracrm_campaign_queue_prepare(10,1,'')),'prepare denies unauthorized actor');
    campaign_expect(is_wp_error(peracrm_campaign_queue_materialize(10,1,1,'')),'materialize denies unauthorized actor');
    foreach (['schedule','pause','resume','cancel'] as $op) { campaign_expect(is_wp_error(peracrm_campaign_queue_transition(10,1,1,'',$op)),'lifecycle authority '.$op); }
    campaign_expect(snapshot_rejects(static function () { peracrm_campaign_queue_get(10,1); }) && snapshot_rejects(static function () { peracrm_campaign_queue_page(10,1); }),'reads require campaign authority');
}
$GLOBALS['campaign_actor']=1; $GLOBALS['campaign_impersonating']=true;
campaign_expect(is_wp_error(peracrm_campaign_queue_prepare(10,1,'')) && snapshot_rejects(static function () { peracrm_campaign_queue_get(10,1); }),'impersonation denies queue reads and writes');
$GLOBALS['campaign_impersonating']=false;
foreach ([0,201,'200',-1] as $limit) { campaign_expect(is_wp_error(peracrm_campaign_queue_materialize(10,1,1,'',$limit)),'bounded integer batch contract'); }
campaign_expect(!peracrm_campaign_queue_dispatch_allowed() && is_wp_error(peracrm_campaign_queue_require_dispatch()),'unconditional dispatch deny including clones');
foreach (['queue.php','queue-schema.php'] as $file) {
    $source=file_get_contents($root.'/inc/campaigns/'.$file);
    campaign_expect(!preg_match('/\b(?:wp_mail|wp_remote_\w+|curl_exec|wp_schedule_\w+|add_action|peracrm_subscribers_issue_token)\s*\(/',$source),'queue has no mail/network/cron/hooks/token issuance');
}
echo "PASS queue standalone contracts\n";
