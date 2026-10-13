"""Receiver safety/orchestration in temporary directories; NO SSH or production writes."""
import hashlib
import io
import json
import os
import pathlib
import subprocess
import tarfile
import tempfile
import unittest

SCRIPT = pathlib.Path(__file__).with_name('activate.sh').read_text()


def bundle(extra=None):
    files = {'dataset.poi.ndjson': b'SYNTHETIC shell receiver placeholder\n',
             'report.json': json.dumps({'passed': True, 'version': 'synthetic_receiver'}).encode(),
             'LICENSE.txt': b'SYNTHETIC notice\n', 'summary.txt': b'SYNTHETIC summary\n'}
    files['SHA256SUMS'] = ''.join(hashlib.sha256(files[k]).hexdigest()+'  '+k+'\n'
                                   for k in ('dataset.poi.ndjson', 'report.json', 'LICENSE.txt')).encode()
    if extra:
        files.update(extra)
    output = io.BytesIO()
    with tarfile.open(fileobj=output, mode='w:gz') as archive:
        for name, data in files.items():
            entry = tarfile.TarInfo(name); entry.size = len(data); archive.addfile(entry, io.BytesIO(data))
    return output.getvalue()


class ReceiverTests(unittest.TestCase):
    def execute(self, directory, command, archive, fail_import=False):
        root = pathlib.Path(directory); private = root/'private'; public = root/'public'; public.mkdir(exist_ok=True)
        binary = root/'bin'; binary.mkdir(exist_ok=True)
        wp = binary/'wp'
        wp.write_text('''#!/usr/bin/env python3
import sys,json,pathlib,os
args=sys.argv[1:]
with open(os.environ['MOCK_LOG'],'a') as log: log.write(' '.join(args)+'\\n')
if 'option' in args: print('https://example.invalid')
elif 'import' in args:
 if os.environ.get('FAIL_IMPORT')=='true': sys.exit(9)
 pathlib.Path(os.environ['MOCK_STATE']).write_text('new')
 print(json.dumps({'complete':True,'count':770}))
else: print('{}')
'''); wp.chmod(0o700)
        curl = binary/'curl'; curl.write_text('#!/bin/sh\nexit 0\n'); curl.chmod(0o700)
        script = root/'activate.sh'
        script.write_text(SCRIPT.replace('BASE=/home/d21882/nearby-poi', 'BASE='+str(private))
                          .replace('WP=/home/d21882/public_html', 'WP='+str(public))
                          .replace('WPCLI=/usr/local/bin/wp', 'WPCLI='+str(wp)))
        env = dict(os.environ, SSH_ORIGINAL_COMMAND=command, MOCK_LOG=str(root/'calls'), MOCK_STATE=str(root/'state'),
                   FAIL_IMPORT=str(fail_import).lower(), PATH=str(binary)+':'+os.environ['PATH'])
        (root/'state').write_text('old')
        result = subprocess.run(['bash', str(script)], input=archive, capture_output=True, env=env, timeout=20)
        return result

    def test_successful_receiver_and_idempotent_manifest_delivery(self):
        with tempfile.TemporaryDirectory() as directory:
            for _ in range(2):
                result = self.execute(directory, 'receive synthetic_receiver', bundle())
                self.assertEqual(result.returncode, 0, result.stdout.decode()+result.stderr.decode())
            root = pathlib.Path(directory)
            self.assertEqual((root/'state').read_text(), 'new')
            self.assertTrue((root/'public/poi-data/synthetic_receiver/dataset.poi.ndjson').exists())
            self.assertIn('synthetic_receiver', (root/'public/poi-data/index.html').read_text())
            self.assertEqual((root/'public/poi-data').stat().st_mode & 0o777, 0o755)

    def test_failed_import_records_error_and_preserves_active_stub_state(self):
        with tempfile.TemporaryDirectory() as directory:
            result = self.execute(directory, 'receive synthetic_receiver', bundle(), fail_import=True)
            self.assertNotEqual(result.returncode, 0)
            root = pathlib.Path(directory)
            self.assertEqual((root/'state').read_text(), 'old')
            self.assertIn('record-error', (root/'calls').read_text())

    def test_rejects_command_injection_and_path_substitution(self):
        with tempfile.TemporaryDirectory() as directory:
            result = self.execute(directory, 'receive x; touch /tmp/not-authorized', bundle())
            self.assertEqual(result.returncode, 2)
            self.assertFalse((pathlib.Path(directory)/'private').exists())
            result = self.execute(directory, 'receive synthetic_receiver', bundle({'../../escape': b'bad'}))
            self.assertNotEqual(result.returncode, 0)
            self.assertNotIn('pera nearby import ', (pathlib.Path(directory)/'calls').read_text())
            self.assertFalse((pathlib.Path(directory)/'escape').exists())


if __name__ == '__main__':
    unittest.main()
