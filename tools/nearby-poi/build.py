#!/usr/bin/env python3
"""Offline PBF -> validated, checksummed, compact NDJSON. Requires pyosmium.
No build dependency is installed or run on the production WordPress host.
"""
import argparse
import datetime
import hashlib
import json
import math
import pathlib
import re
import sqlite3
import subprocess
import tempfile
import unicodedata
from record_text import validate_text

TAGS = {'railway', 'public_transport', 'highway', 'amenity', 'leisure', 'shop',
        'train', 'subway', 'station', 'bus', 'network', 'operator', 'brand', 'name',
        'route_names', 'route_subway'}


def tags_of(obj):
    return {t.k: t.v for t in obj.tags if t.k in TAGS or t.k.startswith('name:')}


def wanted(tags):
    return (tags.get('railway') in ('station', 'halt') or tags.get('public_transport') == 'station'
            or tags.get('highway') == 'bus_stop' or tags.get('bus') == 'yes'
            or tags.get('amenity') in ('ferry_terminal', 'school', 'kindergarten', 'college',
                'university', 'hospital', 'clinic', 'doctors', 'pharmacy')
            or tags.get('leisure') in ('park', 'garden', 'nature_reserve', 'sports_centre',
                'stadium', 'pitch', 'fitness_centre') or tags.get('shop') in ('mall', 'supermarket'))


def distance(a, b):
    lat1, lat2 = math.radians(a[0]), math.radians(b[0])
    h = math.sin((lat2-lat1)/2)**2 + math.cos(lat1)*math.cos(lat2)*math.sin(math.radians(b[1]-a[1])/2)**2
    return 6371000 * 2 * math.asin(math.sqrt(min(1, h)))


def store_place(db, place):
    """Conservative same-subgroup/same-name/<100m dedup; retain aliases and translations."""
    validate_text(place)
    lat, lon = place['point']
    if not math.isfinite(lat) or not math.isfinite(lon) or abs(lat) > 90 or abs(lon) > 180:
        raise ValueError('Invalid coordinates')
    identity = unicodedata.normalize('NFC', place['names']['name']).casefold().strip()
    # Latitude band is enough for dedup: exact spherical distance checks the longitude.
    for osm_id, payload in db.execute('SELECT id,payload FROM pois WHERE grp=? AND identity=? AND lat BETWEEN ? AND ?',
                                      (place['group'], identity, lat-.001, lat+.001)):
        old = json.loads(payload)
        if distance(old['point'], place['point']) < 100:
            for key, value in place['names'].items():
                old['names'].setdefault(key, value)
            old.setdefault('aliases', []).append(place['id'])
            validate_text(old)  # Merged translations/aliases must still fit the importer columns.
            db.execute('UPDATE pois SET payload=? WHERE id=?', (json.dumps(old, ensure_ascii=False), osm_id))
            return False
    db.execute('INSERT INTO pois VALUES(?,?,?,?,?,?)',
               (place['id'], place['group'], identity, lat, lon, json.dumps(place, ensure_ascii=False)))
    return True


def write_artifact(db, output, metadata):
    """Only publish an artifact after its complete body and digest exist."""
    body = output.with_suffix('.body.tmp')
    digest = hashlib.sha256()
    count = 0
    try:
        with body.open('wb') as stream:
            for (payload,) in db.execute('SELECT payload FROM pois ORDER BY id'):
                place = json.loads(payload)
                validate_text(place)
                line = (json.dumps(place, ensure_ascii=False, separators=(',', ':'))+'\n').encode()
                if len(line) > 65536:
                    raise ValueError('OSM '+place['id']+': record exceeds importer limit (65536 bytes)')
                stream.write(line)
                digest.update(line)
                count += 1
        metadata.update(schema=1, license='ODbL-1.0', count=count, sha256=digest.hexdigest(),
                        attribution='© OpenStreetMap contributors',
                        generated_at=datetime.datetime.now(datetime.timezone.utc).isoformat())
        temporary = output.with_suffix('.tmp')
        with temporary.open('wb') as stream, body.open('rb') as records:
            stream.write((json.dumps(metadata, ensure_ascii=False)+'\n').encode())
            while chunk := records.read(1024*1024):
                stream.write(chunk)
        temporary.replace(output)
        return metadata
    finally:
        body.unlink(missing_ok=True)


def schema(db):
    db.executescript('''CREATE TABLE refs(id TEXT PRIMARY KEY);
        CREATE TABLE routes(id TEXT, names TEXT, subway TEXT);
        CREATE INDEX routes_id ON routes(id);
        CREATE TABLE points(id TEXT PRIMARY KEY,lat REAL,lon REAL);
        CREATE TABLE pois(id TEXT PRIMARY KEY,grp TEXT,identity TEXT,lat REAL,lon REAL,payload TEXT);
        CREATE INDEX dedup ON pois(grp,identity,lat);''')


def build(args):
    import osmium
    # Computed source bounds, not merely the requested crop, gate metropolitan coverage.
    info = subprocess.check_output(['osmium', 'fileinfo', '-e', '-g', 'data.bbox', args.input], text=True)
    bbox = [float(v) for v in re.findall(r'-?\d+(?:\.\d+)?', info)]
    if len(bbox) != 4 or not (bbox[0] <= args.bounds[0] and bbox[1] <= args.bounds[1]
                             and bbox[2] >= args.bounds[2] and bbox[3] >= args.bounds[3]):
        raise ValueError('Source bounds do not cover requested metropolitan rectangle: '+info.strip())
    output = pathlib.Path(args.output)
    if output.exists():
        raise ValueError('Output exists; choose a new versioned artifact path')
    with tempfile.TemporaryDirectory() as directory:
        db = sqlite3.connect(str(pathlib.Path(directory)/'build.sqlite'))
        schema(db)
        # Relations first: direct route memberships enrich stations without retaining routes in artifact.
        class Relations(osmium.SimpleHandler):
            def relation(self, relation):
                tags = tags_of(relation)
                route = relation.tags.get('route', '')
                if route in ('subway', 'train', 'bus', 'ferry'):
                    for member in relation.members:
                        osm_id = {'n': 'node', 'w': 'way', 'r': 'relation'}[member.type]+'/'+str(member.ref)
                        db.execute('INSERT INTO routes VALUES(?,?,?)',
                                   (osm_id, ' '.join(relation.tags.get(k, '') for k in ('name','network','operator')), 'yes' if route == 'subway' else ''))
                if wanted(tags):
                    for member in relation.members:
                        osm_id = {'n': 'node', 'w': 'way', 'r': 'relation'}[member.type]+'/'+str(member.ref)
                        db.execute('INSERT OR IGNORE INTO refs VALUES(?)', (osm_id,))
        Relations().apply_file(args.input)
        db.commit()
        php = subprocess.Popen([args.php, str(pathlib.Path(__file__).with_name('normalize.php'))],
                               stdin=subprocess.PIPE, stdout=subprocess.PIPE, text=True)
        stats = {'candidate_objects': 0, 'deduplicated': 0, 'missing_geometry': 0, 'outside_bounds': 0}
        west, south, east, north = args.bounds

        def emit(kind, osm_id, tags, point):
            identity = kind+'/'+str(osm_id)
            if not wanted(tags):
                return
            if not point:
                stats['missing_geometry'] += 1
                return
            if not south <= point[0] <= north or not west <= point[1] <= east:
                stats['outside_bounds'] += 1
                return
            stats['candidate_objects'] += 1
            for names, subway in db.execute('SELECT names,subway FROM routes WHERE id=?', (identity,)):
                tags['route_names'] = tags.get('route_names', '')+' '+names
                if subway:
                    tags['route_subway'] = subway
            element = {'type': kind, 'id': osm_id, 'tags': tags, 'lat': point[0], 'lon': point[1]}
            php.stdin.write(json.dumps(element, ensure_ascii=False)+'\n')
            php.stdin.flush()
            line = php.stdout.readline()
            if not line:
                raise RuntimeError('PHP normalizer terminated')
            for place in json.loads(line):
                if place['group'] in ('metro', 'marmaray', 'metrobus', 'ferry', 'rail'):
                    place['classification_tags'] = {k: v for k, v in tags.items() if k in ('railway', 'station', 'subway', 'route_subway', 'route_names', 'network', 'operator', 'amenity', 'highway', 'bus')}
                if not store_place(db, place):
                    stats['deduplicated'] += 1

        class Facilities(osmium.SimpleHandler):
            def node(self, node):
                point = (node.location.lat, node.location.lon) if node.location.valid() else None
                tags = tags_of(node)
                if point and db.execute('SELECT 1 FROM refs WHERE id=?', ('node/'+str(node.id),)).fetchone():
                    db.execute('INSERT OR REPLACE INTO points VALUES(?,?,?)', ('node/'+str(node.id), *point))
                emit('node', node.id, tags, point)

            def way(self, way):
                tags = tags_of(way)
                identity = 'way/'+str(way.id)
                referenced = db.execute('SELECT 1 FROM refs WHERE id=?', (identity,)).fetchone()
                if not wanted(tags) and not referenced:
                    return
                # Source bounding-box centre, matching the prior Overpass centre convention.
                if not way.nodes or any(not n.location.valid() for n in way.nodes):
                    point = None
                else:
                    point = ((min(n.lat for n in way.nodes)+max(n.lat for n in way.nodes))/2,
                             (min(n.lon for n in way.nodes)+max(n.lon for n in way.nodes))/2)
                if point and referenced:
                    db.execute('INSERT OR REPLACE INTO points VALUES(?,?,?)', (identity, *point))
                emit('way', way.id, tags, point)

        try:
            Facilities().apply_file(args.input, locations=True, idx='sparse_file_array,'+str(pathlib.Path(directory)/'locations'))
            # Tagged facility relations (including multipolygons) use available direct-member centres.
            class FacilityRelations(osmium.SimpleHandler):
                def relation(self, relation):
                    tags = tags_of(relation)
                    if not wanted(tags):
                        return
                    points = []
                    for member in relation.members:
                        identity = {'n': 'node', 'w': 'way', 'r': 'relation'}[member.type]+'/'+str(member.ref)
                        p = db.execute('SELECT lat,lon FROM points WHERE id=?', (identity,)).fetchone()
                        if not p:  # Never publish a partial relation centre.
                            stats['missing_geometry'] += 1
                            return
                        points.append(p)
                    point = ((min(p[0] for p in points)+max(p[0] for p in points))/2,
                             (min(p[1] for p in points)+max(p[1] for p in points))/2) if points else None
                    emit('relation', relation.id, tags, point)
            FacilityRelations().apply_file(args.input)
        finally:
            php.stdin.close()
            php.stdout.close()
            if php.wait() != 0:
                raise RuntimeError('PHP normalizer failed')
        db.commit()
        metadata = write_artifact(db, output, dict(source=args.source, source_sha256=args.source_sha256,
            source_timestamp=args.source_timestamp, version=args.version, bounds=args.bounds, statistics=stats,
            builder='pera-nearby-v1', classification_version=1, refresh_period=getattr(args, 'refresh_period', None), geometry='source bounding-box/member-centre approximation'))
        print(json.dumps(metadata, ensure_ascii=False))


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('input')
    parser.add_argument('output')
    parser.add_argument('--source', required=True)
    parser.add_argument('--source-sha256', required=True)
    parser.add_argument('--source-timestamp', required=True)
    parser.add_argument('--version', required=True)
    parser.add_argument('--refresh-period')
    parser.add_argument('--php', default='php')
    parser.add_argument('--bounds', nargs=4, type=float, default=[27.8, 40.7, 30.1, 41.7], metavar=('W','S','E','N'))
    args = parser.parse_args()
    with open(args.input, 'rb') as source_file:
        actual_digest = hashlib.file_digest(source_file, 'sha256').hexdigest()
    if not (len(args.source_sha256) == 64 and actual_digest == args.source_sha256):
        parser.error('Input checksum mismatch')
    if not (-180 <= args.bounds[0] < args.bounds[2] <= 180 and -90 <= args.bounds[1] < args.bounds[3] <= 90):
        parser.error('Invalid bounds')
    build(args)
