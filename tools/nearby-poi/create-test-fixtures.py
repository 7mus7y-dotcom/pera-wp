#!/usr/bin/env python3
"""Generate TEMPORARY SYNTHETIC maintenance-test fixtures, never production OSM data."""
import argparse
import json
import pathlib
from test_refresh import fixture, POLICY
from validate import validate

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('directory')
args = parser.parse_args()
root = pathlib.Path(args.directory); root.mkdir(parents=True, exist_ok=True)
fixture(root/'dataset.poi.ndjson', 'synthetic_first')
report = validate(root/'dataset.poi.ndjson', POLICY, bootstrap=True)
(root/'report.json').write_text(json.dumps(report))
fixture(root/'next.poi.ndjson', 'synthetic_second')
new = validate(root/'next.poi.ndjson', POLICY, previous=report)
(root/'next-report.json').write_text(json.dumps(new))
legacy_policy = json.loads((pathlib.Path(__file__).resolve().parents[2]/'wp-content/themes/hello-elementor-child/inc/data/nearby-poi-policy-v1.json').read_text())
fixture(root/'legacy.poi.ndjson', 'synthetic_legacy', schema=1)
legacy = validate(root/'legacy.poi.ndjson', legacy_policy, bootstrap=True)
(root/'legacy-report.json').write_text(json.dumps(legacy))
fixture(root/'migration.poi.ndjson', 'synthetic_migration')
migration = validate(root/'migration.poi.ndjson', POLICY, previous=legacy, migration=True)
(root/'migration-report.json').write_text(json.dumps(migration))
print('Created 880 synthetic records per artifact. These are contract fixtures, not OSM coverage evidence.')
