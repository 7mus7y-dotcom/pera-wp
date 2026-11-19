<?php
if (!defined('ABSPATH')) { exit; }
if (!defined('WP_CLI') || !WP_CLI) { return; }

/** Common output contract: 0 read/completed; 2 disabled; 3 invalid/forbidden; 4 blocked/overlap. */
function peracrm_campaign_cli($operation, $args, $assoc)
{
    $exit=0;
    try {
        $allowed=in_array($operation,['due','event-redrive'],true) ? ($operation==='event-redrive'?['format','limit','confirm']:['format','limit']) : (str_starts_with($operation,'fixture-') ? ['format','confirm-disposable'] : ['format']);
        if (in_array($operation,['quota-refresh','internal-probe'],true)) { $allowed=['format','confirm']; }
        if (in_array($operation,['dispatch-run','dispatch-recover'],true)) { $allowed=['format','job','limit','confirm']; }
        if ($operation==='dispatch-refresh') { $allowed=['format','restart','confirm']; }
        if ($operation==='dispatch-scheduled') { $allowed=['format','confirm']; }
        if ($operation==='budget-recover') { $allowed=['format','version','confirm']; }
        if ($operation==='suppression-sync') { $allowed=['format','pages','confirm','restart']; }
        if ($operation==='rehearsal-proposal') { $allowed=['format','job']; }
        if ($operation==='rehearsal-send') { $allowed=['format','job','binding','confirm']; }
        if ($operation==='draft-test') { $allowed=['format','campaign','revision','to','request-id','confirm']; }
        if ($operation==='retention') { $allowed=['format','limit','confirm']; }
        if ($args || array_diff(array_keys($assoc), $allowed)) { throw new InvalidArgumentException(); }
        $format=$assoc['format'] ?? 'json';
        if (!in_array($format,['json','text'],true)) { throw new InvalidArgumentException(); }
        $service=new PeraCRM_Campaign_Operations();
        if ($operation==='draft-test-process') {
            require_once dirname(__DIR__).'/campaigns/draft-test-queue.php';
            $data=peracrm_campaign_draft_test_queue_process();
            if (in_array($data['state'],['failed','unknown','rejected','expired'],true)) { $exit=4; }
        } elseif ($operation==='draft-test') {
            require_once dirname(__DIR__).'/campaigns/draft-test.php';
            foreach (['campaign','revision'] as $key) { if (!is_string($assoc[$key] ?? null) || !preg_match('/\A[1-9][0-9]{0,9}\z/',$assoc[$key])) { throw new InvalidArgumentException(); } }
            if (!is_string($assoc['to'] ?? null) || !is_string($assoc['request-id'] ?? null)
                || (isset($assoc['confirm']) && $assoc['confirm']!=='send-one-test')) { throw new InvalidArgumentException(); }
            $data=peracrm_campaign_draft_test((int)$assoc['campaign'],(int)$assoc['revision'],$assoc['to'],$assoc['request-id'],isset($assoc['confirm']));
            if (!in_array($data['state'],['preview','accepted'],true)) { $exit=4; }
        } elseif ($operation==='retention') {
            require_once dirname(__DIR__).'/campaigns/retention.php';
            if (isset($assoc['confirm']) && $assoc['confirm']!=='retire-expired-diagnostics') { throw new InvalidArgumentException(); }
            $raw=$assoc['limit']??'25';
            if (!is_string($raw) || preg_match('/\A(?:[1-9]|1[0-9]|2[0-5])\z/',$raw)!==1) { throw new InvalidArgumentException(); }
            $data=peracrm_campaign_retention(isset($assoc['confirm']),(int)$raw);
        } elseif ($operation==='client-mailing-audit') {
            $data=peracrm_client_mailing_audit();
        } elseif ($operation==='readiness') {
            $data=peracrm_campaign_readiness_service()->assess();
            $exit=$data['production_ready']?0:4;
        } elseif (str_starts_with($operation,'dispatch-')) {
            require_once dirname(__DIR__).'/campaigns/production-runtime.php';
            PeraCRM_SES_Operator::authorize();
            $expected=$operation==='dispatch-refresh'?'refresh-provider-state':($operation==='dispatch-recover'?'recover-without-resend':'send-approved-job');
            if (($assoc['confirm']??null)!==$expected) { throw new InvalidArgumentException(); }
            $production=peracrm_campaign_production_service();
            if (!$production) { $data=['state'=>'disabled','dispatch_enabled'=>false]; $exit=2; }
            elseif ($operation==='dispatch-refresh') {
                if (isset($assoc['restart']) && $assoc['restart']!=='abandon-cursor') { throw new InvalidArgumentException(); }
                $data=$production->refresh(isset($assoc['restart']));
            } elseif ($operation==='dispatch-scheduled') { $data=$production->scheduled(); }
            else {
                if (!is_string($assoc['job']??null) || preg_match('/\A[1-9][0-9]{0,9}\z/',$assoc['job'])!==1
                    || !is_string($assoc['limit']??'1') || preg_match('/\A(?:[1-9]|1[0-9]|2[0-5])\z/',$assoc['limit']??'1')!==1) { throw new InvalidArgumentException(); }
                $data=$production->run((int)$assoc['job'],(int)($assoc['limit']??1),$operation==='dispatch-recover');
            }
            if (!in_array($data['state']??null,['completed','time_bound','refreshed','idle'],true)) { $exit=($data['state']??null)==='disabled'?2:4; }
        } elseif ($operation==='status' || $operation==='health') {
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
        } elseif ($operation==='suppression-health' || $operation==='suppression-sync') {
            $pages=null; $restart=false;
            if ($operation==='suppression-sync') {
                if (($assoc['confirm']??null)!=='account-deny-only' || !is_string($assoc['pages']??null)
                    || preg_match('/\A(?:[1-9]|10)\z/',$assoc['pages'])!==1
                    || (isset($assoc['restart']) && $assoc['restart']!=='abandon-cursor')) { throw new InvalidArgumentException(); }
                $pages=(int)$assoc['pages']; $restart=isset($assoc['restart']);
            }
            $data=$service->suppression_sync($pages,$restart);
            if ($data['state']==='disabled') { $exit=2; }
            elseif ($data['state']==='failed' || !empty($data['stale'])) { $exit=4; }
        } elseif (in_array($operation,['rehearsal-proposal','rehearsal-send','rehearsal-status'],true)) {
            $job=0;
            if ($operation!=='rehearsal-status') {
                if (!is_string($assoc['job']??null) || preg_match('/\A[1-9][0-9]{0,9}\z/',$assoc['job'])!==1) { throw new InvalidArgumentException(); }
                $job=(int)$assoc['job'];
            }
            if ($operation==='rehearsal-send' && (($assoc['confirm']??null)!=='send-one-to-yahoo'
                || !is_string($assoc['binding']??null) || preg_match('/\A[a-f0-9]{64}\z/',$assoc['binding'])!==1)) { throw new InvalidArgumentException(); }
            $data=$service->rehearsal($operation,$job,$assoc['binding']??'');
            if (($data['state']??null)==='disabled') { $exit=2; }
            elseif ($operation==='rehearsal-send' && ($data['delivery_state']??null)!=='accepted') { $exit=4; }
        } elseif ($operation==='internal-probe') {
            $confirmation=$assoc['confirm']??null;
            $attempt=match ($confirmation) {
                'send-one-to-info'=>1,
                'authorize-second-send-to-info'=>2,
                'authorize-probe-after-access-denied'=>3,
                default=>throw new InvalidArgumentException(),
            };
            $data=$service->internal_probe($attempt);
            $exit=match ($data['state']) { 'accepted'=>0,'disabled'=>2,default=>4 };
        } elseif ($operation==='internal-probe-status') {
            $data=$service->internal_probe(1,true);
        } elseif ($operation==='quota-refresh') {
            if (($assoc['confirm']??null)!=='refresh-account-quota') { throw new InvalidArgumentException(); }
            $data=$service->quota_refresh();
            if ($data['state']==='disabled') { $exit=2; }
        } elseif ($operation==='budget-health' || $operation==='budget-recover') {
            $version=null;
            if ($operation==='budget-recover') {
                if (($assoc['confirm']??null)!=='breaker-only' || !is_string($assoc['version']??null)
                    || preg_match('/\A[1-9][0-9]{0,8}\z/',$assoc['version'])!==1) { throw new InvalidArgumentException(); }
                $version=(int)$assoc['version'];
            }
            $data=$service->sending_budget($version);
            if (($data['state']??'')==='disabled') { $exit=2; }
            elseif (empty($data['quota_fresh']) || !empty($data['cooldown_active']) || ($data['breaker']??'closed')==='operator') { $exit=4; }
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
    catch (Throwable $e) {
        $code=$e->getMessage();
        $safeSync=$operation==='dispatch-refresh' && $e instanceof PeraCRM_SES_Sync_Error;
        $safeRuntime=(str_starts_with($operation,'dispatch-') || $operation==='draft-test')
            && preg_match('/\A(?:production|operator|deployment|budget|draft_test)_[a-z_]{1,64}\z/',$code);
        $data=['error'=>$safeSync || $safeRuntime ? $code : 'campaign_command_rejected']; $exit=3;
    }
    // Never print exception/SQL/provider payloads. Only allowlisted service summaries reach stdout.
    WP_CLI::line(($format ?? 'json')==='text' ? 'PeraCRM campaigns: '.wp_json_encode($data) : wp_json_encode($data,JSON_PRETTY_PRINT));
    if ($exit) { WP_CLI::halt($exit); }
}
foreach (['draft-test-process','draft-test','client-mailing-audit','retention','dispatch-run','dispatch-recover','dispatch-refresh','dispatch-scheduled','readiness','status','health','due','event-health','event-redrive','suppression-health','suppression-sync','budget-health','budget-recover','quota-refresh','internal-probe','internal-probe-status','rehearsal-proposal','rehearsal-send','rehearsal-status','fixture-run','fixture-recover'] as $operation) {
    WP_CLI::add_command('peracrm campaigns '.$operation, static function ($args,$assoc) use ($operation) { peracrm_campaign_cli($operation,$args,$assoc); });
}
