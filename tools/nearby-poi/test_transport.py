"""Transport evidence and schema migration regressions; no downloads or real DB claims."""
import copy
import datetime as dt
import hashlib
import json
import os
import pathlib
import sqlite3
import subprocess
import tempfile
import types
import unittest
from unittest.mock import patch
import build
import transport
from test_refresh import fixture, POLICY
from validate import validate

ROOT = pathlib.Path(__file__).resolve().parents[2]
PHP = os.environ.get('PHP', 'php')


def route(mode, ref='', **extra):
    return dict(id='relation/42', role='stop', tags=dict(type='route', route=mode, ref=ref, **extra))


def normalize(tags, routes=(), identity=1):
    element = dict(type='node', id=identity, tags=dict(name='Synthetic station', **tags), routes=list(routes), lat=41.06, lon=28.99)
    result = subprocess.run([PHP, str(ROOT/'tools/nearby-poi/normalize.php')], input=json.dumps(element)+'\n',
        text=True, capture_output=True, timeout=10, check=True)
    return json.loads(result.stdout)


class TransportTests(unittest.TestCase):
    def test_station_and_direct_relation_lines(self):
        for ref in ('M1A', 'M1B', 'M2', 'M4', 'M7', 'M11'):
            place = normalize(dict(railway='station', subway='yes', ref=ref))[0]
            self.assertEqual(place['transport']['lines'], [ref]); transport.validate_transport(place)
        multi = normalize(dict(railway='station',subway='yes',ref='M1A;M1B'))[0]
        self.assertEqual(multi['transport']['lines'],['M1A','M1B'])
        place = normalize(dict(railway='station'), [route('subway','M2'), route('subway','M7')])[0]
        self.assertEqual(place['group'], 'metro'); self.assertEqual(place['transport']['lines'], ['M2','M7'])
        self.assertEqual(transport.group(place['transport']['sources'][0]['tags'], place['transport']['sources'][0]['routes']), 'metro')
        transport.validate_transport(place)
        for tags, routes in [(dict(railway='station',subway='yes',route_names='M11 planned'), []),
                             (dict(railway='station'), [route('subway',name='M11 Airport')]),
                             (dict(railway='station',subway='yes',ref='Station M4'), [])]:
            place = normalize(tags,routes)[0]
            self.assertEqual(place['transport']['lines'], [])

    def test_tram_funicular_and_conventional_rail(self):
        for ref in ('T1','T4','T5'):
            place = normalize(dict(railway='tram_stop'), [route('tram',ref)])[0]
            self.assertEqual((place['group'],place['transport']['lines']), ('tram',[ref])); transport.validate_transport(place)
        place = normalize(dict(railway='station'), [route('funicular','F2')])[0]
        self.assertEqual((place['group'],place['transport']['lines']), ('funicular',['F2']))
        self.assertEqual(normalize(dict(railway='station'),[route('train','B2')])[0]['group'],'rail')
        self.assertEqual(normalize(dict(public_transport='platform',tram='yes'))[0]['group'],'tram')

    def test_brt_identity_not_bus_prose(self):
        stop = dict(highway='bus_stop',network='İETT',route_names='To Metrobüs destination',description='Metrobüs connection')
        for routes in ([],[route('bus','500T',network='İETT',name='Metrobüs terminal')]):
            place=normalize(stop,routes)[0]; self.assertEqual(place['group'],'bus'); transport.validate_transport(place)
        for tags,routes in [(dict(highway='bus_stop',network='Metrobüs'),[]),
                            (dict(highway='bus_stop'),[route('bus','34G',network='IETT Metrobüs')])]:
            place=normalize(tags,routes)[0]; self.assertEqual(place['group'],'metrobus'); transport.validate_transport(place)
        self.assertEqual(normalize(dict(railway='station'),[route('train','B1',network='Marmaray')])[0]['group'],'marmaray')

    def test_nonoperational_facilities_and_routes(self):
        for extra in ({'construction':'yes'}, {'proposed:railway':'station'}, {'disused':'yes'}, {'abandoned':'yes'},
                      {'railway:disused':'yes'}, {'station':'construction'}, {'status':'planned'}, {'opening_date':'2100'}, {'operational':'no'}):
            tags=dict(railway='station',subway='yes',**extra)
            self.assertFalse(transport.operational(transport.tags_of(tags))); self.assertEqual(normalize(tags),[])
        self.assertEqual(normalize(dict(highway='construction',bus='yes')),[])
        place=normalize(dict(railway='station',subway='yes'),[route('subway','M2'),route('subway','M11',construction='yes')])[0]
        self.assertEqual(place['transport']['lines'],['M2']); transport.validate_transport(place)
        forged=copy.deepcopy(place); forged['transport']['lines'].append('M11')
        with self.assertRaisesRegex(ValueError,'verified evidence'): transport.validate_transport(forged)
        master=route('subway','M4');master['tags']['type']='route_master'
        self.assertEqual(normalize(dict(railway='station'),[master])[0]['group'],'rail')
        # Track membership with a direction role does not establish a stop or line.
        direct=route('subway','M4'); direct['role']='forward'
        self.assertEqual(normalize(dict(railway='station'),[direct])[0]['group'],'rail')

    def test_dedup_unions_verified_lines_and_keeps_evidence(self):
        db=sqlite3.connect(':memory:');build.schema(db)
        first=normalize(dict(railway='station',subway='yes',ref='M2'),identity=1)[0]
        second=normalize(dict(railway='station',subway='yes',ref='M7'),identity=2)[0]
        build.store_place(db,first);build.store_place(db,second)
        place=json.loads(db.execute('SELECT payload FROM pois').fetchone()[0])
        self.assertEqual(place['aliases'],['node/2']);self.assertEqual(place['transport']['lines'],['M2','M7'])
        self.assertEqual(len(place['transport']['sources']),2);transport.validate_transport(place)
        with tempfile.TemporaryDirectory() as tmp:
            path=pathlib.Path(tmp)/'dataset.ndjson';meta=build.write_artifact(db,path,dict(schema=2,classification_version=2))
            self.assertEqual(meta['schema'],2);self.assertEqual(json.loads(path.read_text().splitlines()[1])['transport'],place['transport'])

    def test_schema_migration_keeps_drift_guards(self):
        legacy_policy=json.loads((ROOT/'wp-content/themes/hello-elementor-child/inc/data/nearby-poi-policy-v1.json').read_text())
        with tempfile.TemporaryDirectory() as tmp:
            old=pathlib.Path(tmp)/'v1.ndjson';new=pathlib.Path(tmp)/'v2.ndjson'
            fixture(old,schema=1);fixture(new)
            baseline=validate(old,legacy_policy,bootstrap=True)
            with self.assertRaisesRegex(ValueError,'migration'):validate(new,POLICY,previous=baseline)
            report=validate(new,POLICY,previous=baseline,migration=True)
            self.assertTrue(report['classification_migration']);self.assertEqual(report['dataset_schema'],2)
            wrong=copy.deepcopy(baseline);wrong['groups']['bus']=100000
            with self.assertRaisesRegex(ValueError,'transport total'):validate(new,POLICY,previous=wrong,migration=True)
            wrong=copy.deepcopy(baseline);wrong['groups']['schools']=100000
            with self.assertRaisesRegex(ValueError,'schools'):validate(new,POLICY,previous=wrong,migration=True)
            with self.assertRaisesRegex(ValueError,'migration'):validate(new,POLICY,bootstrap=True,migration=True)

    def test_real_builder_route_pass_with_synthetic_osmium_objects(self):
        class Tags(list):
            def __init__(self, values): super().__init__([types.SimpleNamespace(k=k,v=v) for k,v in values.items()]);self.values=values
            def get(self,k,default=''):return self.values.get(k,default)
        node=types.SimpleNamespace(id=1,tags=Tags(dict(name='Synthetic interchange',railway='station')),
            location=types.SimpleNamespace(lat=41.06,lon=28.99,valid=lambda:True))
        relations=[types.SimpleNamespace(id=i,tags=Tags(dict(type='route',route='subway',ref=ref)),
            members=[types.SimpleNamespace(type='n',ref=1,role='stop')]) for i,ref in [(42,'M2'),(43,'M7')]]
        class Handler:
            def apply_file(self,*args,**kwargs):
                if hasattr(self,'node'):self.node(node)
                if hasattr(self,'relation'):
                    for rel in relations:self.relation(rel)
        with tempfile.TemporaryDirectory() as tmp,patch.dict('sys.modules',{'osmium':types.SimpleNamespace(SimpleHandler=Handler)}),patch('build.subprocess.check_output',return_value='(27.7,40.6,30.2,41.8)'):
            args=types.SimpleNamespace(input=str(pathlib.Path(tmp)/'SYNTHETIC.pbf'),output=str(pathlib.Path(tmp)/'dataset.ndjson'),
                php=PHP,bounds=POLICY['bounds'],source='synthetic fixture',source_sha256='0'*64,source_timestamp=dt.datetime.now(dt.timezone.utc).isoformat(),version='synthetic_routes')
            build.build(args)
            meta,place=[json.loads(line) for line in pathlib.Path(args.output).read_text().splitlines()]
            self.assertEqual(meta['schema'],2);self.assertEqual(place['transport']['lines'],['M2','M7']);transport.validate_transport(place)


if __name__=='__main__':unittest.main()
