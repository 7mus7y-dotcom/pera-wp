#!/usr/bin/env python3
"""Offline, hash-verified subset packaging. Never downloads or resolves dependencies.

Usage: package.py --verify
       package.py --sources /path/to/upstream-sources
Source layout: upstream-sources/aws/aws-sdk-php/src/Signature/... etc.
"""
import argparse
import hashlib
import json
import pathlib
import re

ROOT = pathlib.Path(__file__).resolve().parent
parser = argparse.ArgumentParser(description=__doc__)
mode = parser.add_mutually_exclusive_group(required=True)
mode.add_argument('--verify', action='store_true')
mode.add_argument('--sources', type=pathlib.Path)
args = parser.parse_args()
lock = json.loads((ROOT / 'dependencies.lock.json').read_text())
def isolate(text):
    return re.sub(r'(?<![A-Za-z0-9_\\])(?:Aws|AWS|GuzzleHttp|Psr)(?=\\|;)',
                  lambda match: lock['prefix'] + match.group(), text)


for source in ['namespace Aws;', 'use Aws\\Signature\\SignatureV4;',
               'namespace GuzzleHttp\\Psr7;', 'namespace Psr\\Http\\Message;']:
    name = source.split(' ', 1)[1]
    expected_declaration = source.split(' ', 1)[0] + ' ' + lock['prefix'] + name
    if isolate(source) != expected_declaration:
        raise SystemExit('Namespace isolation self-test failed')

expected = set()
for package in lock['packages']:
    for entry in package['files']:
        target = ROOT / entry['target']
        expected.add(target.resolve())
        if args.sources:
            source = (args.sources / package['name'] / entry['source']).read_bytes()
            if hashlib.sha256(source).hexdigest() != entry['sha256']:
                raise SystemExit('Upstream digest mismatch: ' + package['name'] + '/' + entry['source'])
            text = source.decode('utf-8')
            if target.suffix == '.php':
                text = isolate(text)
            text = ''.join(line.rstrip(' \t\r\n') + '\n' for line in text.splitlines())
            packaged = text.encode('utf-8')
            if hashlib.sha256(packaged).hexdigest() != entry['packaged_sha256']:
                raise SystemExit('Packaging transformation mismatch: ' + entry['target'])
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(packaged)
        if hashlib.sha256(target.read_bytes()).hexdigest() != entry['packaged_sha256']:
            raise SystemExit('Packaged digest mismatch: ' + entry['target'])
actual = {p.resolve() for base in ['src', 'licenses'] for p in (ROOT / base).rglob('*') if p.is_file()}
if actual != expected:
    raise SystemExit('Unlisted or missing packaged files')
print('PASS isolated SES dependency lock (' + str(len(expected)) + ' files)')
