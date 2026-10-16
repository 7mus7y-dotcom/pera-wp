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
    dry = run('--dry-run')
    assert dry.returncode == 0, dry.stderr
    assert 'Pending found: 4' in dry.stdout
    assert set(p.name for p in uploads.iterdir()) == set(fixtures)
    real = run()
    assert real.returncode == 0, real.stderr
    assert 'Converted: 4' in real.stdout
    for source in ['pair.jpg', 'pair.jpeg', 'new image.png', 'zero.jpg']:
        assert (uploads / (source + '.webp')).read_text() == fixtures[source]
    assert (uploads / 'pair.webp').read_text() == 'ambiguous'
    assert not (uploads / 'old.PNG.webp').exists()
    assert 'Converted: 0' in run().stdout
    (uploads / 'fail.jpg').write_text('fail')
    failure = run()
    assert failure.returncode == 1
    assert not (uploads / 'fail.jpg.webp').exists()
    assert not list(uploads.glob('*.tmp.*'))
    assert not list(temp.iterdir())
print('PASS: collisions, legacy reuse, dry run, spaces, uppercase, empty outputs, rerun, failure cleanup')
