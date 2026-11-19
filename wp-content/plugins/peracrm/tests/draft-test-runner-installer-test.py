"""Exercise installer idempotency using fake wp/crontab/flock; never touch real cron or WordPress."""
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile

installer = Path(__file__).resolve().parents[1] / 'tools/install-draft-test-runner.py'
with tempfile.TemporaryDirectory(prefix='peracrm-installer-test-') as directory:
    root = Path(directory)
    site = root / 'public html'
    site.mkdir()
    private = root / 'private % tests'
    binary = root / 'bin'
    binary.mkdir()
    cron = root / 'cron.txt'
    cron.write_text('15 2 * * * /existing-backup\n')
    common = '#!' + sys.executable + '\nimport os,sys,json\nfrom pathlib import Path\nr=Path(os.environ["INSTALLER_TEST_ROOT"])\n'
    wp = common + '''
with (r/'wp-calls.jsonl').open('a') as handle: handle.write(json.dumps(sys.argv[1:])+'\\n')
if 'eval' in sys.argv: print('a'*64)
elif 'draft-test-process' in sys.argv: print('{"state":"idle"}')
else: print('Configured')
'''
    crontab = common + '''
p=r/'cron.txt'
if sys.argv[1:] == ['-l']: print(p.read_text(),end='')
elif sys.argv[1:] == ['-']: p.write_text(sys.stdin.read())
else: raise SystemExit(9)
'''
    for name, content in [('wp', wp), ('crontab', crontab), ('flock', common)]:
        path = binary / name
        path.write_text(content)
        path.chmod(0o700)
    env = dict(os.environ, INSTALLER_TEST_ROOT=str(root), PATH=str(binary) + os.pathsep + os.environ['PATH'])
    command = [sys.executable, str(installer), '--path', str(site), '--url', 'https://example.org', '--user', '1', '--to', 'owner@example.org', '--private-dir', str(private)]
    for attempt in range(2):
        result = subprocess.run(command, capture_output=True, text=True, env=env)
        assert result.returncode == 0, result.stderr
        report = json.loads(result.stdout)
        assert report['installed'] and report['initial_worker_exit'] == 0
        lines = cron.read_text().splitlines()
        assert lines[0] == '15 2 * * * /existing-backup'
        assert sum(line.endswith('# peracrm-draft-test-runner') for line in lines) == 1
        assert r'\%' in lines[-1]
        worker = private / 'campaign-test-runner.sh'
        assert worker.stat().st_mode & 0o777 == 0o700
        assert subprocess.run(['sh', '-n', str(worker)], capture_output=True).returncode == 0
    before = (root/'wp-calls.jsonl').read_text()
    worker.unlink()
    worker.symlink_to(root/'outside-script')
    result = subprocess.run(command, capture_output=True, text=True, env=env)
    assert result.returncode != 0 and 'symlink' in result.stderr
    added_calls = (root/'wp-calls.jsonl').read_text()[len(before):]
    assert 'config' not in added_calls
    bad = command[:-2] + ['--private-dir', str(site/'unsafe')]
    result = subprocess.run(bad, capture_output=True, text=True, env=env)
    assert result.returncode != 0
    assert cron.read_text().count('# peracrm-draft-test-runner') == 1
print('PASS: installer preserves cron, is idempotent, quotes paths, protects private files and refuses unsafe locations')
