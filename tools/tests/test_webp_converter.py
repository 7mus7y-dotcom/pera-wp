"""Run with python3 tools/tests/test_webp_converter.py; no real uploads touched."""
import os
from pathlib import Path
import subprocess
import tempfile

root = Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory() as directory:
    work = Path(directory)
    uploads = work / 'uploads'
    uploads.mkdir()
    temp = work / 'tmp'
    temp.mkdir()
    fixtures = {'pair.jpg': 'jpg', 'pair.jpeg': 'jpeg', 'pair.webp': 'ambiguous',
                'old.PNG': 'old', 'old.webp': 'legacy', 'new image.png': 'new',
                'zero.jpg': 'zero', 'zero.jpg.webp': ''}
    for name, content in fixtures.items():
        (uploads / name).write_text(content)
    (uploads / 'old.webp').chmod(0o600)
    for source in ['pair.jpg', 'pair.jpeg', 'new image.png', 'zero.jpg']:
        (uploads / source).chmod(0o777)
    script = work / 'convert-webp.sh'
    script.write_text((root / 'convert-webp.sh').read_text().replace(
        'uploads="$HOME/public_html/wp-content/uploads"', f'uploads="{uploads}"'))
    encoder = work / 'convert'
    encoder.write_text('#!/usr/bin/env bash\nif [[ "$1" == *fail.jpg ]]; then exit 1; fi\ncp -- "$1" "${5#webp:}"\n')
    encoder.chmod(0o755)
    env = dict(os.environ, PATH=f'{work}:' + os.environ['PATH'], TMPDIR=str(temp))
    def run(*args):
        return subprocess.run(['bash', str(script), '--all', *args], env=env,
                              capture_output=True, text=True)
    legacy_before = (uploads / 'old.webp').stat()
    dry = run('--dry-run')
    assert dry.returncode == 0, dry.stderr
    assert 'Pending found: 4' in dry.stdout
    assert dry.stdout.count('Would ensure permissions:') == 1
    assert f'Would ensure permissions: {uploads / "old.webp"}' in dry.stdout
    assert (uploads / 'old.webp').stat().st_mode & 0o7777 == 0o600
    assert not list(temp.iterdir())
    assert set(p.name for p in uploads.iterdir()) == set(fixtures)
    real = run()
    assert real.returncode == 0, real.stderr
    assert 'Converted: 4' in real.stdout
    for source in ['pair.jpg', 'pair.jpeg', 'new image.png', 'zero.jpg']:
        assert (uploads / (source + '.webp')).read_text() == fixtures[source]
        assert (uploads / (source + '.webp')).stat().st_mode & 0o7777 == 0o644
        assert (uploads / source).stat().st_mode & 0o7777 == 0o777
    assert (uploads / 'pair.webp').read_text() == 'ambiguous'
    assert not (uploads / 'old.PNG.webp').exists()
    assert (uploads / 'old.webp').read_text() == 'legacy'
    assert (uploads / 'old.webp').stat().st_mode & 0o7777 == 0o644
    assert (uploads / 'old.webp').stat().st_ino == legacy_before.st_ino
    assert (uploads / 'old.webp').stat().st_mtime_ns == legacy_before.st_mtime_ns
    outputs = [uploads / (source + '.webp') for source in
               ['pair.jpg', 'pair.jpeg', 'new image.png', 'zero.jpg']]
    before = {p: (p.read_bytes(), p.stat().st_ino, p.stat().st_mtime_ns) for p in outputs}
    for path, mode in zip(outputs, [0o600, 0o777, 0o4644, 0o644]):
        path.chmod(mode)
    dry = run('--dry-run')
    assert dry.returncode == 0, dry.stderr
    assert dry.stdout.count('Would ensure permissions:') == 3
    assert 'Would convert:' not in dry.stdout
    assert outputs[0].stat().st_mode & 0o7777 == 0o600
    rerun = run()
    assert rerun.returncode == 0, rerun.stderr
    assert 'Converted: 0' in rerun.stdout
    for path in outputs:
        assert path.stat().st_mode & 0o7777 == 0o644
        assert (path.read_bytes(), path.stat().st_ino, path.stat().st_mtime_ns) == before[path]
    assert 'Would ensure permissions:' not in run('--dry-run').stdout
    month = uploads / '2026' / '09'
    month.mkdir(parents=True)
    (month / 'month.jpg').write_text('month')
    (month / 'month.jpg.webp').write_text('existing')
    (month / 'month.jpg.webp').chmod(0o600)
    selected = run('--month', '2026/09', '--dry-run')
    assert selected.returncode == 0, selected.stderr
    assert selected.stdout.count('Would ensure permissions:') == 1
    assert 'Pending found: 0' in selected.stdout
    selected = run('--month', '2026/09')
    assert selected.returncode == 0, selected.stderr
    assert (month / 'month.jpg.webp').stat().st_mode & 0o7777 == 0o644
    assert (month / 'month.jpg.webp').read_text() == 'existing'
    (uploads / 'fail.jpg').write_text('fail')
    failure = run()
    assert failure.returncode == 1
    assert not (uploads / 'fail.jpg.webp').exists()
    assert not list(uploads.glob('*.tmp.*'))
    assert not list(temp.iterdir())
print('PASS: collisions, legacy reuse, dry run, spaces, uppercase, empty outputs, rerun, permissions, month selection, failure cleanup')
