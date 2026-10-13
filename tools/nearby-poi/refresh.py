#!/usr/bin/env python3
"""Acquire/build/validate monthly OSM data on a build runner, never production."""
import argparse
import datetime as dt
import hashlib
import json
import pathlib
import re
import shutil
import subprocess
from validate import check_source, validate

SOURCES = {
    'geofabrik': ('https://download.geofabrik.de/europe/turkey-latest.osm.pbf',
                  'https://download.geofabrik.de/europe/turkey-latest.osm.pbf.md5', 'turkey-latest.osm.pbf'),
    'bbbike': ('https://download.bbbike.org/osm/bbbike/Istanbul/Istanbul.osm.pbf',
               'https://download.bbbike.org/osm/bbbike/Istanbul/CHECKSUM.txt', 'Istanbul.osm.pbf')}
ROOT = pathlib.Path(__file__).resolve().parent


def fetch(url, destination):
    temporary = destination.with_suffix(destination.suffix+'.part')
    try:
        subprocess.run(['curl', '--fail', '--location', '--proto', '=https', '--proto-redir', '=https',
            '--retry', '3', '--connect-timeout', '20', '--max-time', '1200', url, '-o', str(temporary)], check=True)
        temporary.replace(destination)
    finally:
        temporary.unlink(missing_ok=True)


def verify_download(path, checksum_text, filename):
    expected = next((line.split()[0] for line in checksum_text.splitlines()
                     if len(line.split()) >= 2 and line.split()[-1].lstrip('*').split('/')[-1] == filename), None)
    if not expected or not re.fullmatch('[a-fA-F0-9]{32}', expected):
        raise ValueError('Source MD5 entry missing')
    with path.open('rb') as stream:
        actual = hashlib.file_digest(stream, 'md5').hexdigest()
    if expected.lower() != actual:
        raise ValueError('Source checksum mismatch (including latest-file rotation)')


def refresh(directory, version, source='geofabrik', previous=None, bootstrap=False, force=False):
    directory = pathlib.Path(directory)
    directory.mkdir(parents=True, exist_ok=True)
    policy = json.loads((ROOT.parents[1]/'wp-content/themes/hello-elementor-child/inc/data/nearby-poi-policy.json').read_text())
    period = dt.datetime.now(dt.timezone.utc).strftime('%Y-%m')
    if previous and previous.get('passed') and previous.get('refresh_period') == period and not force:
        result = dict(skipped=True, reason='This month already accepted', version=previous['version'])
        (directory/'result.json').write_text(json.dumps(result)+'\n')
        return result
    if not previous and not bootstrap:
        raise ValueError('Missing accepted baseline; first dispatch requires bootstrap approval')
    if shutil.disk_usage(directory).free < 8 * 1024**3:
        raise ValueError('Offline runner requires at least 8 GiB free disk before download')
    url, checksum_url, filename = SOURCES[source]
    pbf = directory/filename
    sums = directory/'source-checksum.txt'
    fetch(url, pbf); fetch(checksum_url, sums)
    verify_download(pbf, sums.read_text(), filename)
    info = json.loads(subprocess.check_output(['osmium', 'fileinfo', '-e', '-j', str(pbf)], text=True))
    (directory/'source-info.json').write_text(json.dumps(info, indent=2)+'\n')
    source_date = check_source(info, policy)
    with pbf.open('rb') as stream:
        download_sha256 = hashlib.file_digest(stream, 'sha256').hexdigest()
    if source == 'geofabrik':
        cropped = directory/'istanbul-reviewed.osm.pbf'
        # Padding around the accepted envelope preserves edge-way/relation members.
        w, s, e, n = policy['bounds']
        subprocess.run(['osmium', 'extract', '--strategy=smart', '--bbox='+','.join(map(str, (w-.05,s-.05,e+.05,n+.05))),
                        str(pbf), '-o', str(cropped)], check=True)
        pbf.unlink()  # Only the city input/location index needs to coexist with build scratch.
        pbf = cropped
    with pbf.open('rb') as stream:
        input_sha256 = hashlib.file_digest(stream, 'sha256').hexdigest()
    artifact = directory/'dataset.poi.ndjson'
    subprocess.run(['python3', str(ROOT/'build.py'), str(pbf), str(artifact), '--source='+url,
                    '--source-sha256='+input_sha256, '--source-timestamp='+source_date,
                    '--version='+version, '--refresh-period='+period], check=True)
    report = validate(artifact, policy, previous, bootstrap)
    report['download_sha256'] = download_sha256
    report['source_integrity'] = 'HTTPS + published MD5 + full PBF parse; SHA-256 records identity, not a source signature'
    (directory/'report.json').write_text(json.dumps(report, ensure_ascii=False, indent=2)+'\n')
    # Include an ODbL access/attribution notice with every distributed database.
    (directory/'LICENSE.txt').write_text('Pera Property filtered Istanbul POI database\n'
        '© OpenStreetMap contributors. Database licensed under ODbL 1.0.\n'
        'https://www.openstreetmap.org/copyright\nhttps://opendatacommons.org/licenses/odbl/1-0/\n'
        'The accompanying dataset.poi.ndjson is the entire filtered derivative database.\n'
        f'Source: {url}\nSource snapshot: {source_date}\nVersion: {version}\n')
    (directory/'SHA256SUMS').write_text('\n'.join(hashlib.sha256((directory/name).read_bytes()).hexdigest()+'  '+name
        for name in ('dataset.poi.ndjson', 'report.json', 'LICENSE.txt'))+'\n')
    summary = (f"Version: {version}\nSource snapshot: {source_date}\nBuild: {report['generated_at']}\n"
               f"POIs: {report['count']}\nArtifact bytes: {report['artifact_bytes']}\n"
               f"District checks: {len(report['districts'])} passed\nGroups: {json.dumps(report['groups'])}\n"
               'Acceptance: passed. Transport samples in report require first-time human spot review.\n')
    (directory/'summary.txt').write_text(summary)
    result = dict(skipped=False, version=version)
    (directory/'result.json').write_text(json.dumps(result)+'\n')
    return result


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--directory', required=True); parser.add_argument('--version', required=True)
    parser.add_argument('--source', choices=SOURCES, default='geofabrik')
    parser.add_argument('--previous'); parser.add_argument('--bootstrap', action='store_true'); parser.add_argument('--force', action='store_true')
    args = parser.parse_args()
    print(json.dumps(refresh(args.directory, args.version, args.source,
        json.loads(pathlib.Path(args.previous).read_text()) if args.previous else None, args.bootstrap, args.force)))
