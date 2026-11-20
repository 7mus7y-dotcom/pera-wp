"""Exercise config update ordering and rollback without accessing real WordPress or AWS."""
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile

installer = Path(__file__).resolve().parents[1] / 'tools/configure-dispatch-rate.py'
with tempfile.TemporaryDirectory(prefix='dispatch-rate-test-') as directory:
    root = Path(directory)
    site = root / 'public html'
    private = root / 'private'
    binary = root / 'bin'
    for path in (site, private, binary):
        path.mkdir(mode=0o700)
    (site / 'wp-config.php').write_text('<?php // synthetic config')
    (site / 'wp-config.php').chmod(0o600)
    original = dict(enabled=True, job_ids=[4], per_second_cap=1, daily_cap=5000)
    state = root / 'state.json'
    state.write_text(json.dumps(original))
    common = '#!' + sys.executable + '\nimport os,sys,json,base64\nfrom pathlib import Path\nr=Path(os.environ["RATE_TEST_ROOT"])\ns=r/"state.json"\n'
    wp = common + '''
c=json.loads(s.read_text())
with (r/'calls.jsonl').open('a') as h: h.write(json.dumps(sys.argv[1:])+'\\n')
if 'eval-file' in sys.argv:
    stage=Path(sys.argv[-1]).stem
    if stage == os.environ.get('FAIL_STAGE'): raise SystemExit(3)
    if stage=='inspect': print(json.dumps({'config':c,'fingerprint':'a'*64,'php':str(r/'bin/php')}))
    elif stage=='drain':
        assert c['enabled'] is False and c['per_second_cap']==1
        print(json.dumps({'records':[], 'quota':{}, 'observed_at':123}))
    elif stage=='finalize':
        assert c['enabled'] is False and c['per_second_cap']==10 and c['daily_cap']==10000
        print(json.dumps({'fingerprint':'b'*64,'per_second':10,'daily':10000}))
    else: raise SystemExit(4)
elif 'config' in sys.argv:
    raw=sys.argv[-2]
    c=json.loads(base64.b64decode(raw.split("'")[1]))
    s.write_text(json.dumps(c))
    print('Updated')
else: raise SystemExit(5)
'''
    php = common + 'os.execv(sys.executable,[sys.executable]+sys.argv[1:])\n'
    for name, source in [('wp', wp), ('php', php)]:
        path = binary / name
        path.write_text(source)
        path.chmod(0o700)
    env = dict(os.environ, RATE_TEST_ROOT=str(root), PATH=str(binary) + os.pathsep + os.environ['PATH'])
    command = [sys.executable, str(installer), '--path', str(site), '--url', 'https://example.org',
               '--user', '1', '--job', '4', '--per-second', '10', '--daily', '10000', '--private-dir', str(private)]
    result = subprocess.run(command, capture_output=True, text=True, env=env)
    assert result.returncode == 0, result.stdout + result.stderr
    report = json.loads(result.stdout)
    assert report['state']=='configured' and report['recipient_records_modified'] is False
    assert json.loads(state.read_text()) == dict(original, per_second_cap=10, daily_cap=10000)
    proof = Path(report['evidence_file'])
    assert proof.is_file() and proof.stat().st_mode & 0o077 == 0
    assert proof.parent.stat().st_mode & 0o077 == 0
    calls = [json.loads(line) for line in (root/'calls.jsonl').read_text().splitlines()]
    assert not any('dispatch-run' in call or 'dispatch-scheduled' in call for call in calls)
    result = subprocess.run(command, capture_output=True, text=True, env=env)
    assert result.returncode == 0 and json.loads(result.stdout)['state']=='unchanged'
    for stage in ('inspect', 'drain', 'finalize'):
        state.write_text(json.dumps(original))
        result = subprocess.run(command, capture_output=True, text=True, env=dict(env, FAIL_STAGE=stage))
        assert result.returncode != 0
        assert json.loads(state.read_text()) == dict(original, enabled=(stage=='inspect'))
    state.write_text(json.dumps(dict(original, job_ids=[4, 5])))
    result = subprocess.run(command, capture_output=True, text=True, env=env)
    assert result.returncode != 0 and json.loads(state.read_text())['enabled'] is True
    (site/'wp-config.php').chmod(0o644)
    result = subprocess.run(command, capture_output=True, text=True, env=env)
    assert result.returncode != 0
print('PASS: rate setup pauses before edits, resumes only after validation, is idempotent and fails closed')
