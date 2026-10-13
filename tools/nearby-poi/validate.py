#!/usr/bin/env python3
"""Fail-closed acceptance of an OSM-derived artifact. No synthetic coverage claims."""
import argparse
import collections
import datetime as dt
import hashlib
import json
import math
import pathlib
import re
import sqlite3
import tempfile
from build import distance
from record_text import validate_text
from transport import validate_transport, GROUPS as TRANSPORT_GROUPS

GROUPS = {'metro': 'transport', 'marmaray': 'transport', 'metrobus': 'transport', 'rail': 'transport',
          'tram': 'transport', 'funicular': 'transport', 'bus': 'transport', 'ferry': 'transport', 'schools': 'education', 'universities': 'education',
          'hospitals': 'healthcare', 'clinics': 'healthcare', 'pharmacies': 'healthcare',
          'parks': 'parks', 'sport': 'parks', 'malls': 'shopping', 'supermarkets': 'shopping'}


def transport_sample(place):
    evidence = place.get('transport')
    if not evidence: return place.get('classification_tags', {})
    # Reports are bounded summaries; the artifact retains every original evidence field.
    fields = ('railway', 'station', 'subway', 'tram', 'funicular', 'network', 'ref', 'route')
    short = lambda tags: {k: tags[k][:40] for k in fields if k in tags}
    return dict(lines=evidence['lines'], sources=[dict(id=s['id'], tags=short(s['tags']),
        routes=[dict(id=r['id'], role=r['role'], tags=short(r['tags'])) for r in s['routes'][:2]])
        for s in evidence['sources'][:2]], summary_only=True)


def file_sha256(path):
    with pathlib.Path(path).open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()


def timestamp(value):
    parsed = dt.datetime.fromisoformat(value.replace('Z', '+00:00'))
    if parsed.tzinfo is None:
        raise ValueError('Timestamp requires timezone')
    return parsed.astimezone(dt.timezone.utc)


def check_source(info, policy, now=None):
    now = now or dt.datetime.now(dt.timezone.utc)
    data = info['data']
    if not data.get('objects_ordered') or data.get('multiple_versions') is not False or data['count']['nodes'] < 1000:
        raise ValueError('Incomplete, unsorted or historical source')
    bbox = data['bbox']
    if len(bbox) == 2:
        bbox = bbox[0] + bbox[1]
    w, s, e, n = policy['bounds']
    if not (bbox[0] <= w and bbox[1] <= s and bbox[2] >= e and bbox[3] >= n):
        raise ValueError('Source bounds do not span metropolitan envelope')
    source_date = info['header'].get('option', {}).get('osmosis_replication_timestamp') or info['header'].get('osmosis_replication_timestamp')
    if not source_date:
        raise ValueError('Source snapshot timestamp missing; do not substitute last object edit time')
    age = (now - timestamp(source_date)).total_seconds()/86400
    if not -1 <= age <= policy['max_source_age_days']:
        raise ValueError('Stale or future source snapshot')
    return source_date


def compare(new, previous, policy, migration=False):
    if not previous.get('passed') or previous.get('schema') != 1:
        raise ValueError('Previous acceptance report is invalid')
    if timestamp(new['source_timestamp']) < timestamp(previous['source_timestamp']):
        raise ValueError('Source snapshot regressed')
    transition = previous.get('classification_version', 1) == 1 and new['classification_version'] == 2
    if transition != migration:
        raise ValueError('Explicit classification migration required only for v1 -> v2')
    for label, count, old in [('total', new['count'], previous['count'])] + [
            ('group '+group, new['groups'].get(group, 0), previous['groups'].get(group, 0))
            for group in GROUPS if not migration or group not in TRANSPORT_GROUPS] + [
            ('district '+district, new['districts'][district]['count'], previous['districts'][district]['count'])
            for district in policy['districts']] + ([('transport total', sum(new['groups'].get(g, 0) for g in TRANSPORT_GROUPS), sum(previous['groups'].get(g, 0) for g in TRANSPORT_GROUPS))] if migration else []):
        if old >= 5 and not policy['minimum_ratio'] <= count/old <= policy['maximum_ratio']:
            raise ValueError('Suspicious count change: '+label)


def validate(path, policy, previous=None, bootstrap=False, now=None, migration=False):
    now = now or dt.datetime.now(dt.timezone.utc)
    path = pathlib.Path(path)
    if path.stat().st_size > policy['max_artifact_bytes']:
        raise ValueError('Artifact exceeds deployment size budget')
    counts = collections.Counter(); translations = collections.Counter()
    districts = {name: {'count': 0, 'categories': collections.Counter()} for name in policy['districts']}
    samples = {group: [] for group in ('metro', 'tram', 'funicular', 'marmaray', 'metrobus', 'ferry')}
    digest = hashlib.sha256()
    with tempfile.TemporaryDirectory() as scratch, path.open('rb') as stream:
        db = sqlite3.connect(str(pathlib.Path(scratch)/'unique.sqlite'))
        db.execute('CREATE TABLE ids(id TEXT PRIMARY KEY)')
        header = stream.readline(65537)
        if len(header) > 65536 or not header.endswith(b'\n'):
            raise ValueError('Invalid header')
        meta = json.loads(header)
        if type(meta.get('schema')) is not int or type(meta.get('classification_version')) is not int or meta.get('schema') != policy.get('dataset_schema', 1) or meta.get('license') != 'ODbL-1.0' or meta.get('classification_version') != policy['classification_version']:
            raise ValueError('Schema/license/classification mismatch')
        if not re.fullmatch(r'[A-Za-z0-9_-]{1,80}', meta.get('version', '')):
            raise ValueError('Invalid version identifier')
        if not meta.get('source', '').startswith(('https://download.bbbike.org/', 'https://download.geofabrik.de/')):
            raise ValueError('Unapproved source')
        if not re.fullmatch(r'[a-f0-9]{64}', meta.get('source_sha256', '')):
            raise ValueError('Missing source integrity digest')
        source_age = (now - timestamp(meta['source_timestamp'])).total_seconds()/86400
        if not -1 <= source_age <= policy['max_source_age_days']:
            raise ValueError('Source snapshot too old or in future')
        if not -1 <= (now - timestamp(meta['generated_at'])).total_seconds()/86400 <= 7:
            raise ValueError('Build date too old or in future')
        if meta.get('bounds') != policy['bounds']:
            raise ValueError('Artifact coverage envelope mismatch')
        total = 0; last_id = ''
        while line := stream.readline(65537):
            if len(line) > 65536 or not line.endswith(b'\n'):
                raise ValueError('Truncated/oversized record')
            digest.update(line)
            place = json.loads(line)
            if not re.fullmatch(r'(node|way|relation)/[1-9][0-9]{0,18}', place.get('id', '')):
                raise ValueError('Invalid OSM ID')
            if place['id'] <= last_id:
                raise ValueError('Artifact IDs must be unique and sorted for resumable import')
            last_id = place['id']
            db.execute('INSERT INTO ids VALUES(?)', (place['id'],))
            group = place['group']; point = place['point']; names = place['names']
            if group not in GROUPS or len(point) != 2 or any(isinstance(v, bool) or not isinstance(v, (int, float)) or not math.isfinite(v) for v in point):
                raise ValueError('Invalid group/coordinate')
            w, s, e, n = policy['bounds']
            if not s <= point[0] <= n or not w <= point[1] <= e:
                raise ValueError('POI outside accepted envelope')
            validate_text(place)
            validate_transport(place, meta['schema'])
            for key, value in names.items():
                if key != 'name':
                    translations[key] += 1
            counts[group] += 1; total += 1
            if total > policy['max_total']:
                raise ValueError('Too many records')
            for name, origin in policy['districts'].items():
                if distance(origin, point) <= 3500:
                    districts[name]['count'] += 1
                    districts[name]['categories'][GROUPS[group]] += 1
            if group in samples and len(samples[group]) < 10:
                samples[group].append(dict(id=place['id'], name=names['name'][:128], point=point,
                    url='https://www.openstreetmap.org/'+place['id'], evidence=transport_sample(place)))
    if total != meta['count'] or digest.hexdigest() != meta['sha256']:
        raise ValueError('Record count/body checksum mismatch')
    if not policy['min_total'] <= total <= policy['max_total']:
        raise ValueError('Total POI count outside accepted budget')
    for group, minimum in policy['required_groups'].items():
        if counts[group] < minimum:
            raise ValueError('Required subgroup missing/under minimum: '+group)
    for name, summary in districts.items():
        if summary['count'] < policy['district_min_pois'] or len(summary['categories']) < policy['district_min_categories']:
            raise ValueError('Insufficient named POIs/categories around district: '+name)
    report = dict(schema=1, dataset_schema=meta['schema'], classification_migration=migration, passed=True, version=meta['version'], refresh_period=meta.get('refresh_period'),
        source=meta['source'], source_timestamp=meta['source_timestamp'], source_sha256=meta['source_sha256'],
        generated_at=meta['generated_at'], validated_at=now.isoformat(), count=total, groups=dict(counts),
        districts=districts, translations=dict(translations), transport_samples=samples,
        artifact_bytes=path.stat().st_size, body_sha256=meta['sha256'],
        artifact_sha256=file_sha256(path),
        classification_version=policy['classification_version'], policy=policy,
        policy_sha256=hashlib.sha256(json.dumps(policy, sort_keys=True, separators=(',', ':')).encode()).hexdigest(),
        previous_body_sha256=previous['body_sha256'] if previous else None,
        migration_group_counts=dict(before=previous['groups'], after=dict(counts)) if migration and previous else None,
        coverage_boundary='Named POIs within 3.5 km of district reference points; not all properties or every neighbourhood.')
    if previous:
        compare(report, previous, policy, migration)
    elif migration:
        raise ValueError('Classification migration requires an accepted v1 baseline')
    elif not bootstrap:
        raise ValueError('No accepted baseline; first run requires explicit bootstrap approval')
    return report


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('artifact'); parser.add_argument('--policy', default=str(pathlib.Path(__file__).resolve().parents[2]/'wp-content/themes/hello-elementor-child/inc/data/nearby-poi-policy.json'))
    parser.add_argument('--classification-migration', action='store_true'); parser.add_argument('--previous'); parser.add_argument('--bootstrap', action='store_true'); parser.add_argument('--report', required=True)
    args = parser.parse_args()
    report = validate(args.artifact, json.loads(pathlib.Path(args.policy).read_text()),
        json.loads(pathlib.Path(args.previous).read_text()) if args.previous else None, args.bootstrap, migration=args.classification_migration)
    pathlib.Path(args.report).write_text(json.dumps(report, ensure_ascii=False, indent=2)+'\n')
    print(json.dumps({key: report[key] for key in ('version', 'source_timestamp', 'count', 'artifact_bytes', 'passed')}, ensure_ascii=False))
