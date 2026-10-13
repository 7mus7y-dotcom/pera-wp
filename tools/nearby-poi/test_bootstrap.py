"""Offline workflow isolation and diagnostic contracts; never dispatches Actions."""
import itertools
import json
import os
import pathlib
import subprocess
import tempfile
import types
import unittest
import yaml

ROOT = pathlib.Path(__file__).resolve().parents[2]


class BootstrapTests(unittest.TestCase):
    def test_delivery_truth_table_and_secret_isolation(self):
        workflow = yaml.load((ROOT/'.github/workflows/nearby-poi-monthly.yml').read_text(), Loader=yaml.BaseLoader)
        inputs = workflow['on']['workflow_dispatch']['inputs']
        self.assertEqual(inputs['deliver']['default'], 'false')
        expression = workflow['jobs']['deliver']['if'].replace('&&', ' and ').replace('||', ' or ').replace('!inputs.bootstrap', 'not inputs.bootstrap')
        for event, enabled, bootstrap, deliver, skipped in itertools.product(
                ('schedule', 'workflow_dispatch', 'push'), (False, True), (False, True), (False, True), (False, True)):
            namespace = dict(github=types.SimpleNamespace(event_name=event),
                vars=types.SimpleNamespace(POI_DELIVERY_ENABLED=str(enabled).lower()),
                inputs=types.SimpleNamespace(bootstrap=bootstrap, deliver=deliver),
                needs=types.SimpleNamespace(build=types.SimpleNamespace(outputs=types.SimpleNamespace(skipped=str(skipped).lower()))))
            actual = eval(expression, {'__builtins__': {}}, namespace)
            expected = enabled and not skipped and (event == 'schedule' or (event == 'workflow_dispatch' and deliver and not bootstrap))
            self.assertEqual(actual, expected, namespace)
        for name in ('build', 'database-acceptance'):
            job = workflow['jobs'][name]
            self.assertNotIn('environment', job)
            self.assertNotIn('secrets.', json.dumps(job))
            self.assertNotIn('ssh ', json.dumps(job))
        self.assertEqual(workflow['permissions'], {'contents': 'read'})
        evidence = workflow['jobs']['database-acceptance']['steps'][-1]
        self.assertEqual(evidence['if'], 'always()')
        self.assertIn('database-acceptance.log', evidence['with']['path'])

    def test_missing_baseline_reports_failure_without_download(self):
        with tempfile.TemporaryDirectory() as directory:
            result = subprocess.run(['python3', str(ROOT/'tools/nearby-poi/refresh.py'),
                '--directory='+directory, '--version=synthetic_failure'], capture_output=True, text=True, timeout=10)
            self.assertNotEqual(result.returncode, 0)
            report = json.loads((pathlib.Path(directory)/'failure.json').read_text())
            self.assertFalse(report['passed'])
            self.assertIn('Missing accepted baseline', report['error'])
            self.assertEqual(list(pathlib.Path(directory).iterdir()), [pathlib.Path(directory)/'failure.json'])

    def test_eval_file_uses_explicit_root_and_preserves_failure_report(self):
        # Emulate eval-file's different __DIR__; fail at preflight before any DB writes.
        harness = '''
define('ABSPATH', getcwd()); define('WP_CLI', true); define('PERA_POI_STAGING_CHECK', true);
class WP_CLI { static function add_command(...$args) {} }
function wp_json_encode($value, $flags=0) { return json_encode($value, $flags); }
function add_action(...$args) {} function add_filter(...$args) {}
function get_option($key, $default=false) { return $default; }
function add_option(...$args) { return true; } function update_option(...$args) { return true; }
function wp_cache_delete(...$args) {}
class ProbeDB { public $prefix='wp_', $options='wp_options'; function prepare($sql,...$args){return $sql;} function query($sql){return 0;} }
$wpdb=new ProbeDB();
try { eval('?>'.file_get_contents(getenv('POI_CHECK_SCRIPT'))); exit(3); }
catch (Throwable $error) { fwrite(STDERR, $error->getMessage()); exit(1); }
'''
        with tempfile.TemporaryDirectory() as directory:
            root = pathlib.Path(directory)
            for name in ('artifact', 'report'):
                (root/name).write_text('{}\n')
            env = dict(os.environ, POI_CODE_ROOT=str(ROOT), POI_CHECK_SCRIPT=str(ROOT/'tools/nearby-poi/staging-check.php'),
                POI_ARTIFACT=str(root/'artifact'), POI_REPORT=str(root/'report'), POI_DB_REPORT=str(root/'db.json'))
            result = subprocess.run([os.environ.get('PHP', 'php'), '-r', harness], env=env,
                cwd=directory, capture_output=True, text=True, timeout=10)
            self.assertEqual(result.returncode, 1, result.stderr)
            report = json.loads((root/'db.json').read_text())
            self.assertFalse(report['passed'])
            self.assertEqual(report['error'], 'Invalid report or artifact SHA-256.')


if __name__ == '__main__':
    unittest.main()
