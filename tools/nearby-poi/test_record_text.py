"""Focused name/alias parity tests. Synthetic records; no OSM downloads or DB claims."""
import copy
import json
import os
import pathlib
import sqlite3
import subprocess
import tempfile
import unittest
import build
from record_text import database_json_bytes, validate_text
from test_refresh import fixture, POLICY
from validate import validate

ROOT = pathlib.Path(__file__).resolve().parents[2]
PHP = os.environ.get('PHP', 'php')
BASE = dict(id='node/123', group='schools', point=[41.06, 28.99], names={'name': 'Örnek مدرسة'})


def php_errors(places):
    harness = r'''
define('ABSPATH', getcwd()); function add_action(...$args) {}
require getenv('POI_CODE_ROOT').'/wp-content/themes/hello-elementor-child/inc/modules/nearby-amenities.php';
$places=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
echo json_encode(array_map('pera_nearby_place_text_error',$places), JSON_THROW_ON_ERROR);
'''
    result = subprocess.run([PHP, '-r', harness], input=json.dumps(places), capture_output=True,
        text=True, env=dict(os.environ, POI_CODE_ROOT=str(ROOT)), timeout=10, check=True)
    return json.loads(result.stdout)


class RecordTextTests(unittest.TestCase):
    def test_name_namespace_and_php_normalizer_roundtrip(self):
        names = {'name': '0', 'name:zh-Hant': '繁體中文', 'name:sr-Latn': 'Škola',
            'name:be-tarask': 'Школа', 'name:zh_pinyin': 'xuéxiào', 'name:en:pronunciation': '/skuːl/',
            'name:tr:loc': 'Örnek', 'name:ar': 'مدرسة', 'name:en': 'A/B\u2028C\u2029D', 'name:fr': ''}
        obj = dict(type='node', id=123, lat=41.06, lon=28.99, tags=dict(names, amenity='school'))
        result = subprocess.run([PHP, str(ROOT/'tools/nearby-poi/normalize.php')],
            input=json.dumps(obj)+'\n', text=True, capture_output=True, timeout=10, check=True)
        place = json.loads(result.stdout)[0]
        self.assertEqual(place['names'], names)
        validate_text(place)
        self.assertEqual(php_errors([place]), [None])
        db = sqlite3.connect(':memory:'); build.schema(db); build.store_place(db, place)
        self.assertEqual(json.loads(db.execute('SELECT payload FROM pois').fetchone()[0])['names'], names)
        # Verify the storage byte calculation matches PHP's exact encoder.
        encoded = subprocess.run([PHP, '-r', 'echo strlen(json_encode(json_decode(stream_get_contents(STDIN),true),JSON_UNESCAPED_UNICODE));'],
            input=json.dumps(names), text=True, capture_output=True, timeout=10, check=True)
        self.assertEqual(database_json_bytes(names), int(encoded.stdout))

    def test_invalid_and_boundary_rules_match_php(self):
        places = []; reasons = []
        def add(names=None, aliases=None, reason=None):
            place = copy.deepcopy(BASE)
            if names is not None: place['names'] = names
            if aliases is not None: place['aliases'] = aliases
            places.append(place); reasons.append(reason)
        for key in ('name:', 'name::en', 'name:en:', 'name:en US', 'name:../en', 'name:en\n', 'name:日本語', 'other:en'):
            add(dict(BASE['names'], **{key: 'never log this value'}), reason='invalid name key')
        add({'name': 'ok', 'name:'+'x'*251: 'ok'}, reason='255 bytes')
        add({'name': 'ok', 'name:tr': 'ğ'*2048})  # Exactly 4096 UTF-8 bytes.
        add({'name': 'ok', 'name:tr': 'ğ'*2049}, reason='4096 UTF-8 bytes')
        add({'name': 'ok', 'name:en': 'x'*4097}, reason='4096 UTF-8 bytes')
        add({'name': 'ok', 'name:en': 42}, reason='must be a string')
        add({'name': ''}, reason='nonempty string')
        add({'name': '0'})
        add({'name': 'ok', **{'name:x'+str(i): 'x'*4096 for i in range(16)}}, reason='TEXT capacity')
        add({'name': 'ok', **{'name:x'+str(i): '\x00'*4096 for i in range(3)}}, reason='TEXT capacity')
        add(aliases=['way/2', 'relation/3'])
        add(aliases=['node/0'], reason='invalid alias')
        add(aliases={'x': 'node/1'}, reason='must be a list')
        add(aliases=['node/1234567890123456789']*3000, reason='TEXT capacity')
        null_alias = copy.deepcopy(BASE); null_alias['aliases'] = None
        places.append(null_alias); reasons.append('must be a list')
        errors = php_errors(places)
        for place, reason, php_error in zip(places, reasons, errors):
            with self.subTest(names=list(place['names']), reason=reason):
                if reason is None:
                    validate_text(place); self.assertIsNone(php_error)
                else:
                    with self.assertRaises(ValueError) as error: validate_text(place)
                    self.assertIn(reason, str(error.exception)); self.assertIn(reason, php_error)
                    self.assertIn('node/123', str(error.exception)); self.assertIn('node/123', php_error)
                    self.assertLess(len(str(error.exception)), 650); self.assertLess(len(php_error), 650)
                    self.assertNotIn('never log this value', str(error.exception)+php_error)

    def test_invalid_utf8_values(self):
        place = copy.deepcopy(BASE); place['names']['name:en'] = '\ud800'
        with self.assertRaisesRegex(ValueError, 'UTF-8'): validate_text(place)
        harness = r'''
define('ABSPATH',getcwd());function add_action(...$args){}
require getenv('POI_CODE_ROOT').'/wp-content/themes/hello-elementor-child/inc/modules/nearby-amenities.php';
echo pera_nearby_place_text_error(array('id'=>'node/123','names'=>array('name'=>'ok','name:en'=>"\xff")));
'''
        result = subprocess.run([PHP, '-r', harness], env=dict(os.environ, POI_CODE_ROOT=str(ROOT)), capture_output=True, text=True, timeout=10, check=True)
        self.assertIn('name:en', result.stdout); self.assertIn('UTF-8', result.stdout)

    def test_offline_php_adapter_rejects_bad_text_with_bounded_diagnostics(self):
        for key, value, reason in [('name::en', 'private value', 'invalid name key'),
                                  ('name:tr', 'ğ'*2049, '4096 UTF-8 bytes')]:
            tags = dict(BASE['names'], amenity='school'); tags[key] = value
            obj = dict(type='node', id=123, lat=41.06, lon=28.99, tags=tags)
            result = subprocess.run([PHP, str(ROOT/'tools/nearby-poi/normalize.php')],
                input=json.dumps(obj)+'\n', text=True, capture_output=True, timeout=10)
            self.assertEqual(result.returncode, 1)
            self.assertEqual(result.stdout, '')
            self.assertIn('node/123', result.stderr); self.assertIn(key, result.stderr)
            self.assertIn(reason, result.stderr); self.assertLess(len(result.stderr), 650)
            self.assertNotIn(value, result.stderr)

    def test_builder_merge_and_offline_validator_use_same_contract(self):
        db = sqlite3.connect(':memory:'); build.schema(db)
        oversized = copy.deepcopy(BASE); oversized['names']['name:tr'] = 'ğ'*2049
        with self.assertRaisesRegex(ValueError, 'node/123.*name:tr.*4096'): build.store_place(db, oversized)
        original = copy.deepcopy(BASE); original['names'].update({'name:x'+str(i): 'x'*4096 for i in range(15)})
        build.store_place(db, original)
        duplicate = copy.deepcopy(BASE); duplicate['id'] = 'way/456'; duplicate['names']['name:another'] = 'x'*4096
        with self.assertRaisesRegex(ValueError, 'TEXT capacity'): build.store_place(db, duplicate)
        self.assertNotIn('name:another', json.loads(db.execute('SELECT payload FROM pois').fetchone()[0])['names'])
        with tempfile.TemporaryDirectory() as directory:
            path = pathlib.Path(directory)/'fixture.ndjson'; fixture(path)
            header, *records = path.read_text().splitlines()
            places = [json.loads(row) for row in records]
            places[0]['names']['name:en:pronunciation'] = '/skuːl/'
            output_db = sqlite3.connect(':memory:'); build.schema(output_db)
            for place in places: build.store_place(output_db, place)
            build.write_artifact(output_db, path, json.loads(header))
            self.assertTrue(validate(path, POLICY, bootstrap=True)['passed'])
            # A rechecksummed artifact with a bad key must fail offline validation, not pass integrity alone.
            import hashlib
            meta, body = path.read_bytes().split(b'\n', 1)
            lines = body.splitlines(); bad = json.loads(lines[0]); bad['names']['name::en'] = 'hidden'
            lines[0] = json.dumps(bad, ensure_ascii=False).encode(); body = b'\n'.join(lines)+b'\n'
            meta = json.loads(meta); meta['sha256'] = hashlib.sha256(body).hexdigest()
            path.write_bytes(json.dumps(meta).encode()+b'\n'+body)
            with self.assertRaisesRegex(ValueError, bad['id']+'.*name::en.*invalid name key'):
                validate(path, POLICY, bootstrap=True)


if __name__ == '__main__':
    unittest.main()
