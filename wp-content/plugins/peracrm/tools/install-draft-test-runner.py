#!/usr/bin/env python3
"""Install the independent draft-test runner once, under the site owner account."""
import argparse
import json
import os
from pathlib import Path
import re
import shlex
import shutil
import subprocess
import tempfile


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--path', required=True, type=Path)
    parser.add_argument('--url', required=True)
    parser.add_argument('--user', required=True, type=int)
    parser.add_argument('--to', required=True)
    parser.add_argument('--private-dir', type=Path)
    args = parser.parse_args()
    if args.user < 1 or not re.fullmatch(r'https://[^\s\x00-\x1f]+', args.url):
        parser.error('A valid HTTPS URL and administrator user ID are required.')
    if not re.fullmatch(r"[a-z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-z0-9.-]+\.[a-z]{2,}", args.to):
        parser.error('Use a normalized lowercase test email address.')
    site = args.path.resolve(strict=True)
    private = (args.private_dir or Path.home() / 'private-peracrm-ses').absolute()
    if private.is_symlink() or (private.resolve() == site or site in private.resolve().parents):
        parser.error('The private directory must be outside WordPress and not a symlink.')
    wp, flock, crontab = (shutil.which(name) for name in ('wp', 'flock', 'crontab'))
    if not all((wp, flock, crontab)):
        parser.error('wp, flock and crontab must be installed.')
    os.umask(0o077)
    private.mkdir(mode=0o700, parents=True, exist_ok=True)
    if private.stat().st_uid != os.geteuid() or private.stat().st_mode & 0o077:
        parser.error('The private directory must be owned by this account with mode 700.')
    base = [wp, '--path=' + str(site), '--url=' + args.url, '--user=' + str(args.user)]
    def run(command):
        return subprocess.run(command, text=True, capture_output=True, check=True).stdout.strip()
    # Check cron access before configuring or writing the runner.
    previous = subprocess.run([crontab, '-l'], text=True, capture_output=True)
    if previous.returncode and 'no crontab for' not in previous.stderr.lower():
        raise SystemExit('Cannot read the existing crontab; no changes made.')
    fingerprint = run(base + ['eval', 'peracrm_with_target_blog(function () { PeraCRM_SES_Operator::authorize(); $d=peracrm_campaign_deployment_service(); if (!$d) WP_CLI::error("Deployment unavailable."); echo $d->fingerprint(); });'])
    if not re.fullmatch(r'[a-f0-9]{64}', fingerprint):
        raise SystemExit('Deployment fingerprint unavailable; no runner installed.')
    worker = private / 'campaign-test-runner.sh'
    lock = private / 'campaign-test-runner.lock'
    for path in (worker, lock, private / 'campaign-test-last.json'):
        if path.is_symlink():
            raise SystemExit('Runner path is a symlink; stopped.')
    setting = "array('enabled'=>true,'deployment_fingerprint'=>'" + fingerprint + "','recipients'=>array(" + "'" + args.to.replace("'", "\\'") + "'))"
    run([wp, '--path=' + str(site), 'config', 'set', 'PERACRM_CAMPAIGN_TEST_SEND', setting, '--raw'])
    command = shlex.join(base + ['peracrm', 'campaigns', 'draft-test-process', '--format=json'])
    script = '#!/bin/sh\nset -u\numask 077\n' + command + '\n'
    with tempfile.NamedTemporaryFile(mode='w', dir=private, delete=False) as handle:
        handle.write(script)
        staged = Path(handle.name)
    staged.chmod(0o700)
    staged.replace(worker)
    marker = '# peracrm-draft-test-runner'
    lines = [line for line in previous.stdout.splitlines() if not line.rstrip().endswith(marker)]
    lines.append('* * * * * ' + shlex.join([flock, '-n', str(lock), str(worker)]) + ' >/dev/null 2>&1 ' + marker)
    lines[-1] = lines[-1].replace('%', r'\%')
    current = subprocess.run([crontab, '-l'], text=True, capture_output=True)
    if current.returncode != previous.returncode or current.stdout != previous.stdout:
        raise SystemExit('Crontab changed during setup; existing cron left untouched. Re-run the installer.')
    subprocess.run([crontab, '-'], input='\n'.join(lines) + '\n', text=True, check=True)
    # Initialize the heartbeat. This may also process one already requested test.
    result = subprocess.run(base + ['peracrm', 'campaigns', 'draft-test-process', '--format=json'], text=True, capture_output=True)
    print(json.dumps({'installed': True, 'test_inbox': args.to, 'worker': str(worker),
                      'schedule': 'Every minute', 'initial_worker_exit': result.returncode}, indent=2))
    if result.returncode:
        print('Runner installed; inspect the test status in the editor or run draft-test-process for its diagnostic result.')

if __name__ == '__main__':
    main()
