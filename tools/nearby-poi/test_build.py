"""Artifact/dedup/production-normalizer tests; no claim of real OSM coverage."""
import hashlib
import json
import os
import pathlib
import sqlite3
import subprocess
import tempfile
import unittest
import build


class BuildTests(unittest.TestCase):
    def test_artifact_and_duplicates(self):
        db = sqlite3.connect(':memory:')
        build.schema(db)
        place = {'id': 'node/1', 'point': [41.06, 28.99], 'group': 'schools',
                 'names': {'name': 'Synthetic School', 'name:tr': 'Örnek okul'}}
        self.assertTrue(build.store_place(db, place))
        duplicate = dict(place, id='way/2', names={'name': 'Synthetic School', 'name:ar': 'مدرسة'})
        self.assertFalse(build.store_place(db, duplicate))
        far = dict(place, id='node/3', point=[41.08, 28.99])
        self.assertTrue(build.store_place(db, far))
        with tempfile.TemporaryDirectory() as directory:
            output = pathlib.Path(directory)/'test.poi.ndjson'
            metadata = build.write_artifact(db, output, {'source': 'synthetic fixture', 'version': 'test'})
            header, body = output.read_bytes().split(b'\n', 1)
            self.assertEqual(metadata['count'], 2)
            self.assertEqual(json.loads(header)['sha256'], hashlib.sha256(body).hexdigest())
            first = json.loads(body.splitlines()[0])
            self.assertEqual(first['aliases'], ['way/2'])
            self.assertEqual(first['names']['name:ar'], 'مدرسة')
            self.assertEqual(first['names']['name:tr'], 'Örnek okul')
            self.assertEqual(list(pathlib.Path(directory).iterdir()), [output])

    def test_php_classifier(self):
        adapter = pathlib.Path(__file__).with_name('normalize.php')
        for tags, expected in [({'railway': 'station', 'subway': 'yes'}, 'metro'),
                               ({'railway': 'station', 'network': 'Marmaray'}, 'marmaray'),
                               ({'highway': 'bus_stop', 'network': 'Metrobüs'}, 'metrobus'),
                               ({'amenity': 'ferry_terminal'}, 'ferry'),
                               ({'railway': 'station'}, 'rail'),
                               ({'shop': 'supermarket'}, 'supermarkets')]:
            tags['name'] = 'Synthetic source name'
            obj = {'type': 'node', 'id': 1, 'lat': 41, 'lon': 29, 'tags': tags}
            result = subprocess.run([os.environ.get('PHP', 'php'), str(adapter)], input=json.dumps(obj)+'\n',
                                    capture_output=True, text=True, check=True)
            self.assertEqual(json.loads(result.stdout)[0]['group'], expected)

    def test_empty_artifact_and_invalid_coordinates(self):
        db = sqlite3.connect(':memory:')
        build.schema(db)
        with self.assertRaises(ValueError):
            build.store_place(db, {'id': 'node/1', 'point': [float('nan'), 29], 'group': 'bus', 'names': {'name': 'Invalid'}})
        with tempfile.TemporaryDirectory() as directory:
            meta = build.write_artifact(db, pathlib.Path(directory)/'empty.ndjson', {'source': 'test', 'version': 'empty'})
            self.assertEqual(meta['count'], 0)
            self.assertEqual(meta['sha256'], hashlib.sha256(b'').hexdigest())


if __name__ == '__main__':
    unittest.main()
