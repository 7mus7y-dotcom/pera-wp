"""Monthly pipeline contracts with explicitly SYNTHETIC data; no real OSM claims."""
import copy
import datetime as dt
import hashlib
import json
import pathlib
import sqlite3
import subprocess
import tempfile
import unittest
from unittest.mock import patch
import build
import refresh
from validate import check_source, validate
import transport

POLICY = json.loads((refresh.ROOT.parents[1]/'wp-content/themes/hello-elementor-child/inc/data/nearby-poi-policy.json').read_text())
NOW = dt.datetime.now(dt.timezone.utc)
SOURCE_TIME = NOW.isoformat()


def fixture(path, version='synthetic_test', drop=None, previous=None, schema=2):
    policy = POLICY if schema == 2 else json.loads((refresh.ROOT.parents[1]/'wp-content/themes/hello-elementor-child/inc/data/nearby-poi-policy-v1.json').read_text())
    db = sqlite3.connect(':memory:'); build.schema(db); number = 0
    for district, origin in policy['districts'].items():
        for group in policy['required_groups']:
            for i in range(5):
                number += 1
                place = {'id': 'node/'+str(number), 'point': [origin[0]+i*.000001, origin[1]],
                         'group': group, 'names': {'name': 'Synthetic '+district+' '+group+' '+str(i), 'name:tr': 'Örnek'}}
                if schema == 2 and group in transport.GROUPS:
                    tags = {'metro': {'railway':'station','subway':'yes','ref':'M2'}, 'tram': {'railway':'tram_stop','ref':'T1'},
                        'funicular': {'railway':'station','station':'funicular','ref':'F2'}, 'marmaray': {'railway':'station','network':'Marmaray'},
                        'metrobus': {'highway':'bus_stop','network':'Metrobüs'}, 'rail': {'railway':'station'},
                        'bus': {'highway':'bus_stop'}, 'ferry': {'amenity':'ferry_terminal'}}[group]
                    place['transport'] = dict(sources=[dict(id=place['id'],tags=tags,routes=[])],lines=[])
                    place['transport']['lines'] = transport.lines(place['transport'],group)
                if not drop or not drop(place):
                    build.store_place(db, place)
    return build.write_artifact(db, pathlib.Path(path), dict(source=refresh.SOURCES['geofabrik'][0],
        source_sha256='0'*64, source_timestamp=SOURCE_TIME, version=version, bounds=POLICY['bounds'],
        schema=schema, classification_version=schema, refresh_period=NOW.strftime('%Y-%m')))


class RefreshTests(unittest.TestCase):
    def test_success_and_comparison(self):
        with tempfile.TemporaryDirectory() as directory:
            path = pathlib.Path(directory)/'fixture.poi.ndjson'; fixture(path)
            report = validate(path, POLICY, bootstrap=True)
            self.assertTrue(report['passed']); self.assertEqual(report['count'], 880)
            self.assertEqual(len(report['districts']), 11)
            self.assertEqual(validate(path, POLICY, previous=report)['previous_body_sha256'], report['body_sha256'])
            with self.assertRaisesRegex(ValueError, 'baseline'):
                validate(path, POLICY)

    def test_count_drift_and_missing_district(self):
        with tempfile.TemporaryDirectory() as directory:
            root = pathlib.Path(directory); fixture(root/'old.ndjson')
            old = validate(root/'old.ndjson', POLICY, bootstrap=True)
            # Every subgroup remains above its minimum; the drift guard must still reject this.
            fixture(root/'new.ndjson', drop=lambda p: p['id'].split('/')[1].endswith(('1', '2', '3')))
            with self.assertRaises(ValueError):
                validate(root/'new.ndjson', POLICY, previous=old)
            fixture(root/'missing.ndjson', drop=lambda p: build.distance(POLICY['districts']['Beykoz'], p['point']) <= 3500)
            with self.assertRaisesRegex(ValueError, 'district'):
                validate(root/'missing.ndjson', POLICY, previous=old)

    def test_invalid_incomplete_stale_source(self):
        info = {'header': {'option': {'osmosis_replication_timestamp': SOURCE_TIME}},
                'data': {'objects_ordered': True, 'multiple_versions': False, 'count': {'nodes': 1000},
                         'bbox': [[27.7, 40.6], [30.2, 41.8]]}}
        self.assertEqual(check_source(info, POLICY), SOURCE_TIME)
        for mutate in [lambda x: x['data']['count'].update(nodes=5),
                       lambda x: x['data'].update(objects_ordered=False),
                       lambda x: x['data'].update(bbox=[[28.66, 40.90], [29.27, 41.27]]),
                       lambda x: x['header']['option'].clear(),
                       lambda x: x['header']['option'].update(osmosis_replication_timestamp=(NOW-dt.timedelta(days=30)).isoformat())]:
            bad = copy.deepcopy(info); mutate(bad)
            with self.assertRaises(ValueError):
                check_source(bad, POLICY)

    def test_unavailable_download_retains_previous_file(self):
        with tempfile.TemporaryDirectory() as directory:
            path = pathlib.Path(directory)/'source.osm.pbf'; path.write_bytes(b'prior complete source')
            def broken(*args, **kwargs):
                path.with_suffix('.pbf.part').write_bytes(b'partial'); raise subprocess.CalledProcessError(22, 'curl')
            with patch('refresh.subprocess.run', side_effect=broken), self.assertRaises(subprocess.CalledProcessError):
                refresh.fetch(refresh.SOURCES['geofabrik'][0], path)
            self.assertEqual(path.read_bytes(), b'prior complete source')
            self.assertFalse(path.with_suffix('.pbf.part').exists())

    def test_corrupt_artifact_and_download_checksum(self):
        with tempfile.TemporaryDirectory() as directory:
            path = pathlib.Path(directory)/'test.ndjson'; fixture(path)
            path.write_bytes(path.read_bytes()[:-1])
            with self.assertRaisesRegex(ValueError, 'Truncated'):
                validate(path, POLICY, bootstrap=True)
            with self.assertRaisesRegex(ValueError, 'checksum'):
                refresh.verify_download(path, '0'*32+'  test.ndjson', 'test.ndjson')

    def test_duplicate_monthly_execution_skips_download(self):
        with tempfile.TemporaryDirectory() as directory, patch('refresh.fetch') as fetch:
            previous = {'passed': True, 'refresh_period': NOW.strftime('%Y-%m'), 'version': 'synthetic_previous', 'classification_version': 2}
            self.assertTrue(refresh.refresh(directory, 'synthetic_new', previous=previous)['skipped'])
            fetch.assert_not_called()

    def test_monthly_orchestration_success_with_mocked_pbf_processing(self):
        # This exercises the orchestration/manifest only. Full PBF parsing remains a release gate.
        with tempfile.TemporaryDirectory() as directory:
            root = pathlib.Path(directory)
            def fetch(url, path):
                if url.endswith('.md5'):
                    path.write_text(hashlib.md5(b'SYNTHETIC PBF placeholder').hexdigest()+'  turkey-latest.osm.pbf\n')
                else:
                    path.write_bytes(b'SYNTHETIC PBF placeholder')
            def command(args, **kwargs):
                if args[:2] == ['osmium', 'extract']:
                    pathlib.Path(args[-1]).write_bytes(b'SYNTHETIC cropped placeholder')
                elif args[0] == 'python3':
                    fixture(args[3], 'synthetic_monthly')
                return subprocess.CompletedProcess(args, 0)
            info = {'header': {'option': {'osmosis_replication_timestamp': SOURCE_TIME}},
                    'data': {'objects_ordered': True, 'multiple_versions': False, 'count': {'nodes': 1000},
                             'bbox': [[27.7, 40.6], [30.2, 41.8]]}}
            with patch('refresh.fetch', side_effect=fetch), patch('refresh.subprocess.run', side_effect=command), \
                 patch('refresh.subprocess.check_output', return_value=json.dumps(info)), \
                 patch('refresh.shutil.disk_usage', return_value=type('Disk', (), {'free': 10*1024**3})()):
                result = refresh.refresh(root, 'synthetic_monthly', bootstrap=True)
            self.assertFalse(result['skipped'])
            report = json.loads((root/'report.json').read_text()); self.assertTrue(report['passed'])
            self.assertEqual(report['count'], 880)
            self.assertEqual(len((root/'SHA256SUMS').read_text().splitlines()), 3)
            self.assertFalse((root/'turkey-latest.osm.pbf').exists())


if __name__ == '__main__':
    unittest.main()
