<?php
if (!defined('ABSPATH')) { exit; }
if (!defined('WP_CLI') || !WP_CLI) { return; }

/** Common output contract: 0 read/completed; 2 disabled; 3 invalid/forbidden; 4 blocked/overlap. */
function peracrm_campaign_cli($operation, $args, $assoc)
{
    $exit=0;
    try {
        $allowed=in_array($operation,['due','event-redrive'],true) ? ($operation==='event-redrive'?['format','limit','confirm']:['format','limit']) : (str_starts_with($operation,'fixture-') ? ['format','confirm-disposable'] : ['format']);
        if ($args || array_diff(array_keys($assoc), $allowed)) { throw new InvalidArgumentException(); }
        $format=$assoc['format'] ?? 'json';
        if (!in_array($format,['json','text'],true)) { throw new InvalidArgumentException(); }
        $service=new PeraCRM_Campaign_Operations();
        if ($operation==='status' || $operation==='health') {
            $data=$service->health();
            if (!$data['queue_ready'] || $data['scheduler_state']==='blocked' || array_diff($data['diagnostics'],['dispatch_disabled'])) { $exit=4; }
            elseif (!$data['dispatch_enabled']) { $exit=2; }
        } elseif ($operation==='event-health' || $operation==='event-redrive') {
            $limit=null;
            if ($operation==='event-redrive') {
                $raw=$assoc['limit']??null;
                if (($assoc['confirm']??null)!=='authenticated-events' || !is_string($raw) || preg_match('/\A(?:[1-9]|1[0-9]|2[0-5])\z/',$raw)!==1) { throw new InvalidArgumentException(); }
                $limit=(int)$raw;
            }
            $data=$service->events($limit);
            if (isset($data['configured']) && !$data['configured']) { $exit=2; }
            elseif (($data['result']??'')==='blocked' || !empty($data['failed_processing']) || !empty($data['manual_review_receipts']) || !empty($data['conflicting_events']) || !empty($data['policy_conflicts'])) { $exit=4; }
        } elseif ($operation==='due') {
            $limit=$assoc['limit'] ?? '10';
            if (!is_scalar($limit) || !preg_match('/^(?:[1-9]|1[0-9]|2[0-5])$/D',(string)$limit)) { throw new InvalidArgumentException(); }
            $data=['dispatch_enabled'=>peracrm_campaign_queue_dispatch_allowed(),'jobs'=>$service->due((int)$limit)];
        } else {
            // Fixture dependencies never load in ordinary WordPress. No arbitrary path/provider/mode inputs.
            if (($assoc['confirm-disposable'] ?? '')!=='campaign_fixture'
                || !defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE') || PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE !== true
                || !defined('DB_NAME') || DB_NAME!=='campaign_fixture' || wp_get_environment_type()!=='local') { throw new RuntimeException(); }
            require_once dirname(__DIR__,2).'/tests/integration/campaigns/scheduler-fixture.php';
            $data=(new PeraCRM_Fixture_Scheduler())->invoke($operation==='fixture-recover');
            if (!in_array($data['result'],['completed','time_bound'],true)) { $exit=4; }
        }
    } catch (PeraCRM_Campaign_Operations_Blocked $e) { $data=['error'=>'campaign_operation_blocked']; $exit=4; }
    catch (Throwable $e) { $data=['error'=>'campaign_command_rejected']; $exit=3; }
    // Never print exception/SQL/provider payloads. Only allowlisted service summaries reach stdout.
    WP_CLI::line(($format ?? 'json')==='text' ? 'PeraCRM campaigns: '.wp_json_encode($data) : wp_json_encode($data,JSON_PRETTY_PRINT));
    if ($exit) { WP_CLI::halt($exit); }
}
foreach (['status','health','due','event-health','event-redrive','fixture-run','fixture-recover'] as $operation) {
    WP_CLI::add_command('peracrm campaigns '.$operation, static function ($args,$assoc) use ($operation) { peracrm_campaign_cli($operation,$args,$assoc); });
}
