#!/usr/bin/env python3
"""Run every existing standalone PHP test; report identical base failures explicitly.

Usage: python3 wp-content/plugins/pera-multilingual/tests/run-regressions.py
CI supplies BASE_SHA. Without it every failing test fails the run. No baseline
failure is ignored unless its exit code AND normalized diagnostics match exactly.
"""
import os
from pathlib import Path
import subprocess
import tempfile

root = Path(__file__).resolve().parents[4]
tests = Path('wp-content/plugins/pera-multilingual/tests')
base_sha = os.environ.get('BASE_SHA', '')


def run(path, directory):
    result = subprocess.run(['php', str(path)], cwd=directory,
                            stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True)
    return result.returncode, result.stdout.replace(str(directory), '<repo>')


with tempfile.TemporaryDirectory(prefix='pera-ml-base-') as temporary:
    baseline = Path(temporary)
    if base_sha:
        # Git validates the commit selector; no shell interpolation or credentials.
        archive = subprocess.run(['git', 'archive', '--format=tar', base_sha], cwd=root,
                                 check=True, stdout=subprocess.PIPE)
        subprocess.run(['tar', '-x', '-C', str(baseline)], input=archive.stdout, check=True)
    passed = existing = failed = 0
    for absolute in sorted(root.joinpath(tests).glob('*-test.php')):
        path = absolute.relative_to(root)
        if path.name == 'geo-redirect-test.php':
            continue  # All configuration modes run in their own strict CI step.
        status, output = run(path, root)
        print(output, end='')
        if status == 0:
            passed += 1
        elif base_sha and (baseline / path).is_file() and (status, output) == run(path, baseline):
            existing += 1
            print(f'::warning file={path}::Existing base failure (identical exit code and diagnostics)')
        else:
            failed += 1
            print(f'::error file={path}::Regression or new failure (exit {status})')
    print(f'Existing suite: {passed} passed, {existing} identical base failures, {failed} regressions')
    raise SystemExit(1 if failed else 0)
