#!/usr/bin/env python3
"""Raise an already active, single-job dispatch policy without rebuilding its queue."""
import argparse
import base64
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile

# These programs run only as the site's administrator through WP-CLI. They never send.
INSPECT = r'''<?php
peracrm_with_target_blog(function () {
    require_once PERACRM_INC.'/campaigns/production-runtime.php';
    PeraCRM_SES_Operator::authorize();
    $d=peracrm_campaign_production_deployment();
    $c=peracrm_campaign_production_configuration($d);
    if (PeraCRM_Campaign_Production_Runtime::MAX_BATCH!==100) { throw new RuntimeException('Deploy the larger worker first.'); }
    $report=peracrm_campaign_readiness_service()->assess();
    if (array_diff($report['blockers'],['dispatch_disabled'])) { throw new RuntimeException('Resolve current readiness blockers first.'); }
    echo wp_json_encode(['config'=>$c,'fingerprint'=>$report['configuration_fingerprint'],'php'=>PHP_BINARY]);
});
'''
DRAIN = r'''<?php
peracrm_with_target_blog(function () {
    require_once PERACRM_INC.'/campaigns/production-runtime.php';
    PeraCRM_SES_Operator::authorize();
    $input=json_decode(file_get_contents(__DIR__.'/input.json'),true,32,JSON_THROW_ON_ERROR);
    $d=peracrm_campaign_production_deployment(); $c=peracrm_campaign_production_configuration($d);
    $expected=$input['before']['config']; $expected['enabled']=false;
    if ($c!==$expected) { throw new RuntimeException('Dispatch configuration changed.'); }
    global $wpdb;
    $pins=array_intersect_key($d->binding(),array_flip(['account_id','region','deployment','blog_id']));
    $lock='peracrm-production-'.substr(hash('sha256',$pins['account_id'].'|'.$pins['region']),0,40);
    if ((string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,75)',$lock))!=='1') { throw new RuntimeException('Worker still active.'); }
    try {
        $role=substr($c['principal_arn'],strrpos($c['principal_arn'],'/')+1);
        $api=new PeraCRM_SES_Operator_API($pins,$role,$d->credentials());
        if (str_contains($c['principal_arn'],':user/')) { $api->attestUser($c['principal_arn']); } else { $api->attest(); }
        $account=$api->quota();
        if (($account['ProductionAccessEnabled']??null)!==true || ($account['SendingEnabled']??null)!==true
            || ($account['EnforcementStatus']??null)!=='HEALTHY') { throw new RuntimeException('Provider account is not ready.'); }
        [$second,$daily]=PeraCRM_SES_Operator::limits($account,$input['second'],$input['daily']);
        if ($second!==$input['second'] || $daily!==$input['daily']) { throw new RuntimeException('Requested caps exceed fresh available AWS quotas.'); }
        $runtime=peracrm_campaign_production_service();
        $refresh=$runtime->refresh();
        if (($refresh['state']??null)!=='refreshed') { throw new RuntimeException('Suppression refresh incomplete.'); }
        $report=peracrm_campaign_readiness_service()->assess();
        if ($report['configuration_fingerprint']!==$input['before']['fingerprint']
            || array_diff($report['blockers'],['dispatch_disabled'])) { throw new RuntimeException('Readiness changed.'); }
        $records=get_option('peracrm_readiness_v1_'.$report['configuration_fingerprint'],[]);
        if (!$records || count($records)>60) { throw new RuntimeException('Evidence history unavailable or full.'); }
        echo wp_json_encode(['records'=>$records,'scope'=>$d->binding(),
            'context_hash'=>hash('sha256',wp_json_encode([peracrm_campaign_configuration(true),PERACRM_SES_EVENT_OPERATIONS,PERACRM_CAMPAIGN_PUBLIC_ENDPOINTS])),
            'quota'=>array_intersect_key($account['SendQuota'],array_flip(['MaxSendRate','Max24HourSend','SentLast24Hours'])),
            'observed_at'=>time()]);
    } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock)); }
});
'''
FINALIZE = r'''<?php
peracrm_with_target_blog(function () {
    require_once PERACRM_INC.'/campaigns/production-runtime.php';
    PeraCRM_SES_Operator::authorize();
    $input=json_decode(file_get_contents(__DIR__.'/input.json'),true,32,JSON_THROW_ON_ERROR);
    $proof=json_decode(file_get_contents($input['proof']),true,32,JSON_THROW_ON_ERROR);
    $d=peracrm_campaign_production_deployment(); $c=peracrm_campaign_production_configuration($d);
    $expected=$proof['before']['config']; $expected['enabled']=false;
    $expected['per_second_cap']=$proof['second']; $expected['daily_cap']=$proof['daily'];
    $context=hash('sha256',wp_json_encode([peracrm_campaign_configuration(true),PERACRM_SES_EVENT_OPERATIONS,PERACRM_CAMPAIGN_PUBLIC_ENDPOINTS]));
    if ($c!==$expected || $d->binding()!==$proof['observation']['scope']
        || $context!==$proof['observation']['context_hash'] || time()-$proof['observation']['observed_at']>120) {
        throw new RuntimeException('Scope, configuration or quota observation changed.');
    }
    $service=peracrm_campaign_readiness_service(); $report=$service->assess();
    $fp=$report['configuration_fingerprint']; $old=$proof['before']['fingerprint'];
    if (!$fp || $fp===$old) { throw new RuntimeException('New rate policy binding missing.'); }
    $key='peracrm_readiness_v1_'.$fp; $records=[];
    // Rate-only review preserves original verification dates/digests and their 30-day expiry.
    // The artifact explicitly retains the original binding; this is not a new DNS/SNS/restore observation.
    foreach ($proof['observation']['records'] as $record) {
        if (($record['binding']??null)!==$old) { throw new RuntimeException('Evidence binding changed.'); }
        $record['previous_binding']=$old; $record['binding']=$fp;
        $record['rate_review_digest']=hash_file('sha256',$input['proof']);
        $record['rate_reviewed_at']=time(); $record['event']='rate_policy_reviewed'; $records[]=$record;
    }
    if (!add_option($key,$records,'',false)) { throw new RuntimeException('New evidence binding already exists; stopped.'); }
    // Fresh account/quota observation, with the provider response limited to quota scalars in the proof.
    $service->record($fp,'iam_account_quotas','provider_observation',hash_file('sha256',$input['proof']),wp_create_nonce('peracrm_readiness_'.$fp));
    $refresh=peracrm_campaign_production_service()->refresh();
    $report=$service->assess();
    if (($refresh['state']??null)!=='refreshed' || array_diff($report['blockers'],['dispatch_disabled'])) {
        throw new RuntimeException('New rate policy did not pass readiness.');
    }
    echo wp_json_encode(['fingerprint'=>$fp,'per_second'=>$refresh['per_second'],'daily'=>$refresh['daily']]);
});
'''


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--path', required=True, type=Path)
    parser.add_argument('--url', required=True)
    parser.add_argument('--user', required=True, type=int)
    parser.add_argument('--job', required=True, type=int)
    parser.add_argument('--per-second', required=True, type=int)
    parser.add_argument('--daily', required=True, type=int)
    parser.add_argument('--private-dir', type=Path)
    args = parser.parse_args()
    if not (args.url.startswith('https://') and args.user > 0 and args.job > 0
            and 1 <= args.per_second <= 1000 and 1 <= args.daily <= 10000000):
        parser.error('Use HTTPS, positive IDs and valid quota caps.')
    site = args.path.resolve(strict=True)
    private = (args.private_dir or Path.home() / 'private-peracrm-ses').absolute()
    if private.is_symlink() or not private.is_dir() or private.resolve() == site or site in private.resolve().parents:
        parser.error('An existing private directory outside WordPress is required.')
    if private.stat().st_uid != os.geteuid() or private.stat().st_mode & 0o077:
        parser.error('The private directory must belong to this account with mode 700.')
    config_file = site / 'wp-config.php'
    if config_file.is_symlink() or not config_file.is_file() or config_file.stat().st_mode & 0o077:
        parser.error('wp-config.php must be a regular private file (mode 600).')
    wp = shutil.which('wp')
    if not wp:
        parser.error('WP-CLI is required.')
    os.umask(0o077)
    base = [wp, '--path=' + str(site), '--url=' + args.url, '--user=' + str(args.user)]
    def run(command):
        result = subprocess.run(command, capture_output=True, text=True)
        if result.returncode:
            (attempt / 'wp-error.log').write_text(result.stderr)
            # Avoid dumping PHP traces or config values to terminal output.
            raise RuntimeError('WP-CLI operation failed; private attempt files retain the stage for inspection.')
        return result.stdout.strip()
    # Durable private attempt directory makes failure review possible without exposing credentials.
    attempt = Path(tempfile.mkdtemp(prefix='rate-review-', dir=private))
    def evaluate(name, program):
        script = attempt / (name + '.php')
        script.write_text(program)
        return json.loads(run(base + ['eval-file', str(script)]))
    def setting(value):
        encoded = base64.b64encode(json.dumps(value, separators=(',', ':')).encode()).decode()
        raw = "json_decode(base64_decode('" + encoded + "'),true)"
        run(base + ['config', 'set', 'PERACRM_CAMPAIGN_DISPATCH', raw, '--raw'])
    changed = False
    before = None
    try:
        before = evaluate('inspect', INSPECT)
        php = Path(before['php'])
        if not php.is_absolute() or not php.is_file() or not os.access(php, os.X_OK):
            raise RuntimeError('The working PHP interpreter is unavailable.')
        base.insert(0, str(php))
        config = before['config']
        if config['enabled'] is not True or config['job_ids'] != [args.job]:
            raise RuntimeError('Only an already enabled, single approved job can be adjusted.')
        if args.per_second < config['per_second_cap'] or args.daily < config['daily_cap']:
            raise RuntimeError('This tool only raises caps.')
        if (args.per_second, args.daily) == (config['per_second_cap'], config['daily_cap']):
            print(json.dumps({'state': 'unchanged', 'job_id': args.job}))
            return
        data = {'before': before, 'second': args.per_second, 'daily': args.daily}
        input_file = attempt / 'input.json'
        input_file.write_text(json.dumps(data))
        paused = dict(config, enabled=False)
        changed = True
        setting(paused)
        observation = evaluate('drain', DRAIN)
        proof = attempt / 'rate-review.json'
        proof.write_text(json.dumps(dict(data, observation=observation), indent=2))
        input_file.write_text(json.dumps({'proof': str(proof)}))
        faster = dict(paused, per_second_cap=args.per_second, daily_cap=args.daily)
        setting(faster)
        result = evaluate('finalize', FINALIZE)
        setting(dict(faster, enabled=True))
        changed = False
        print(json.dumps(dict(result, state='configured', job_id=args.job, batch_limit=100,
                              dispatch_enabled=True, evidence_file=str(proof),
                              recipient_records_modified=False, unknown_deliveries_retried=False), indent=2))
    except Exception:
        if changed and before:
            # Leave sending disabled on any failure. Never clear a breaker or send implicitly.
            try:
                setting(dict(before['config'], enabled=False))
                print('Rate setup failed. Original caps restored; dispatch remains disabled. Private attempt: ' + str(attempt))
            except Exception:
                print('Rate setup failed; could not restore configuration. Inspect wp-config.php. Private attempt: ' + str(attempt))
        else:
            print('Rate setup stopped before changing dispatch. Private attempt: ' + str(attempt))
        raise SystemExit(1)


if __name__ == '__main__':
    main()
