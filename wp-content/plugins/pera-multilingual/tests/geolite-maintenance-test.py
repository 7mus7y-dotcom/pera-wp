"""Hermetic updater fault tests; never contact MaxMind or use production paths/secrets."""
from pathlib import Path
import os
import shutil
import signal
import stat
import subprocess
import tempfile
import time

SCRIPT = Path(__file__).resolve().parents[1] / 'tools/update-geolite2-country.sh'
SECRET = 'FAKE_TEST_LICENSE_NEVER_LOG_THIS'
checks = 0


def executable(path, text):
    path.write_text(text)
    path.chmod(0o700)


with tempfile.TemporaryDirectory(prefix='pera-maintenance-') as temporary:
    root = Path(temporary)
    private = root / 'private data'
    private.mkdir(mode=0o700)
    staging = private / 'staging'
    staging.mkdir(mode=0o700)
    config = private / 'GeoIP.conf'
    config.write_text('LicenseKey ' + SECRET + '\n')
    config.chmod(0o600)
    active = private / 'GeoLite2-Country.mmdb'
    staged = staging / active.name
    updater = root / 'updater'
    php = root / 'php'
    executable(updater, '''#!/bin/bash
printf '%s\\n' "$FAKE_SECRET" >&2
[[ $1 == -f && $2 == "$PERA_GEO_DIRECTORY/GeoIP.conf" && $3 == -d && $4 == "$PERA_GEO_DIRECTORY/staging" ]] || exit 99
if [[ ${TEST_CASE:-} == update-fail ]]; then exit 9; fi
if [[ ${TEST_CASE:-} == update-timeout ]]; then sleep 10; fi
if [[ ${TEST_CASE:-} == overlap ]]; then touch "$PERA_GEO_DIRECTORY/started"; sleep 2; fi
if [[ ${TEST_CASE:-} == missing ]]; then exit 0; fi
if [[ ${TEST_CASE:-} == staged-symlink ]]; then ln -s "$PERA_GEO_DIRECTORY/GeoIP.conf" "$4/GeoLite2-Country.mmdb"; exit 0; fi
printf 'new-database' > "$4/GeoLite2-Country.mmdb"
''')
    executable(php, '''#!/bin/bash
printf '%s\\n' "$FAKE_SECRET" >&2
[[ $1 == */tools/validate-geoip-db.php ]] || exit 99
if [[ ${TEST_CASE:-} == validation-fail ]]; then exit 8; fi
if [[ ${TEST_CASE:-} == validation-timeout ]]; then sleep 10; fi
if [[ $2 == *candidate* ]]; then
  [[ $(stat -c %a "$2") == 600 ]] || exit 97
  [[ $(stat -c %d "$2") == $(stat -c %d "$PERA_GEO_DIRECTORY") ]] || exit 98
  if [[ ${TEST_CASE:-} == snapshot-fail ]]; then exit 8; fi
  if [[ ${TEST_CASE:-} == promotion-fail ]]; then chmod 500 "$PERA_GEO_DIRECTORY"; fi
else
  if [[ ${TEST_CASE:-} == snapshot-change ]]; then printf 'corrupt-after-validation' > "$2"; exit 0; fi
fi
[[ $(cat "$2") == new-database ]] || exit 8
''')
    env = dict(os.environ, PERA_GEO_DIRECTORY=str(private), PERA_GEO_UPDATER=str(updater),
               PERA_GEO_PHP=str(php), FAKE_SECRET=SECRET)

    def reset():
        private.chmod(0o700)
        for path in (staged, active, private / 'started'):
            if path.is_symlink() or path.is_file():
                path.unlink()
        for path in private.glob('.GeoLite2-Country.candidate.*'):
            path.unlink()
        active.write_bytes(b'last-known-good')
        active.chmod(0o600)

    def inspect(result, expected, case, preserved=True):
        global checks
        assert result.returncode == expected, (case, result.returncode, result.stderr)
        assert SECRET not in result.stdout + result.stderr, (case, 'credential leaked to output')
        log = private / 'maintenance.log'
        if log.exists():
            assert SECRET not in log.read_text(), (case, 'credential leaked to log')
            assert stat.S_IMODE(log.stat().st_mode) == 0o600
        assert active.read_bytes() == (b'last-known-good' if preserved else b'new-database'), case
        checks += 1

    def run(case, expected, preserved=True, extra=None):
        reset()
        before = active.stat().st_ino
        result = subprocess.run(['/bin/bash', str(SCRIPT)], env=dict(env, TEST_CASE=case, **(extra or {})),
                                text=True, capture_output=True, timeout=12)
        private.chmod(0o700)
        inspect(result, expected, case, preserved)
        if preserved:
            assert active.stat().st_ino == before, (case, 'last-good inode changed')
        if case != 'promotion-fail':
            assert not list(private.glob('.GeoLite2-Country.candidate.*')), (case, 'temporary leak')
        return result

    run('success', 0, preserved=False)
    assert stat.S_IMODE(active.stat().st_mode) == 0o600
    run('cgi-refused', 10, extra={'REQUEST_METHOD': 'GET'})
    run('update-fail', 20)
    run('missing', 30)
    run('staged-symlink', 30)
    run('validation-fail', 30)
    run('snapshot-fail', 30)
    run('snapshot-change', 30)
    run('update-timeout', 20, extra={'PERA_GEO_UPDATE_TIMEOUT': '1'})
    run('validation-timeout', 30, extra={'PERA_GEO_VALIDATE_TIMEOUT': '1'})
    # Permission fault works under normal user/cPanel permissions; root bypasses it.
    if os.geteuid() != 0:
        run('promotion-fail', 40)

    reset()
    config.chmod(0o644)
    result = subprocess.run(['/bin/bash', str(SCRIPT)], env=env, text=True, capture_output=True, timeout=5)
    inspect(result, 10, 'unsafe-config-permissions')
    config.chmod(0o600)
    reset()
    original = root / 'original-db'
    original.write_bytes(b'last-known-good')
    active.unlink()
    active.symlink_to(original)
    result = subprocess.run(['/bin/bash', str(SCRIPT)], env=env, text=True, capture_output=True, timeout=5)
    inspect(result, 10, 'active-symlink')
    assert original.read_bytes() == b'last-known-good'
    reset()
    first = subprocess.Popen(['/bin/bash', str(SCRIPT)], env=dict(env, TEST_CASE='overlap'),
                             text=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    try:
        for attempt in range(100):
            if (private / 'started').exists():
                break
            if first.poll() is not None:
                raise AssertionError(first.communicate())
            time.sleep(.01)
        assert (private / 'started').exists(), 'first run did not reach locked updater'
        second = subprocess.run(['/bin/bash', str(SCRIPT)], env=env, text=True, capture_output=True, timeout=5)
        inspect(second, 75, 'overlap')
        out, err = first.communicate(timeout=10)
        assert first.returncode == 0 and SECRET not in out + err
        assert active.read_bytes() == b'new-database'
        checks += 1
    finally:
        if first.poll() is None:
            first.terminate()
            first.communicate(timeout=5)

    reset()
    interrupted = subprocess.Popen(['/bin/bash', str(SCRIPT)], env=dict(env, TEST_CASE='overlap'),
                                   text=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    try:
        for attempt in range(100):
            if (private / 'started').exists():
                break
            time.sleep(.01)
        assert (private / 'started').exists()
        interrupted.send_signal(signal.SIGTERM)
        out, err = interrupted.communicate(timeout=5)
        inspect(subprocess.CompletedProcess([], interrupted.returncode, out, err), 143, 'terminated-update')
        assert not list(private.glob('.GeoLite2-Country.candidate.*'))
    finally:
        if interrupted.poll() is None:
            interrupted.kill()
            interrupted.communicate(timeout=5)

    # Optional CI acceptance: the actual PHP reader/validator and synthetic MMDB.
    fixture = os.environ.get('PERA_ML_TEST_MMDB')
    if fixture and shutil.which('php'):
        reset()
        executable(updater, '#!/bin/bash\ncp -- "$PERA_ML_TEST_MMDB" "$4/GeoLite2-Country.mmdb"\n')
        result = subprocess.run(['/bin/bash', str(SCRIPT)], env=dict(env, PERA_GEO_PHP=shutil.which('php')),
                                text=True, capture_output=True, timeout=12)
        assert result.returncode == 0, result.stderr
        assert active.read_bytes() == Path(fixture).read_bytes()
        assert not list(private.glob('.GeoLite2-Country.candidate.*'))
        checks += 1

print(f'GeoLite2 maintenance: {checks} scenarios passed (failure preservation, private logging, locking, timeouts, atomic snapshot promotion)')
