#!/usr/bin/env python3
"""Validate the repository-only AI SEO catalogue without network access."""
import csv
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
BASE = ROOT / "docs" / "knowledge-catalogue"
data = json.loads((BASE / "topic-map.json").read_text(encoding="utf-8"))
assert data["version"] >= 2
topics = data["topics"]
assert len(topics) == 29, f"Unexpected topic count: {len(topics)}"
assert len({t["id"] for t in topics}) == len(topics), "Duplicate topic IDs"
sources = {}
for kind, path in data["sources"].items():
    with (ROOT / path).open(encoding="utf-8", newline="") as file:
        sources[kind] = list(csv.DictReader(file))
post_ids = {int(p["ID"]) for p in sources["blog"]}
page_ids = {int(p["ID"]) for p in sources["pages"]}
taxonomy_urls = {p["permalink"] for p in sources["taxonomies"]}
for topic in topics:
    assert topic["canonical_url"] is None or topic["canonical_url"].startswith("https://")
    for match in topic.get("inventory_matches", []):
        assert match["post_id"] in post_ids, (topic["id"], match)
    for match in topic.get("page_matches", []):
        assert match["post_id"] in page_ids, (topic["id"], match)
    for match in topic.get("taxonomy_matches", []):
        assert match["inventory_url"] in taxonomy_urls, (topic["id"], match)
    assert topic.get("entity_ids"), topic["id"]

# Validate the reconciled Property CPT JSON independently of the older 125-row CSV.
properties = json.loads((BASE / "property-cpt.json").read_text(encoding="utf-8"))
records = properties["records"]
assert properties["count"] == len(records) == 130, "Property CPT count mismatch"
ids = [r["wp_id"] for r in records]
assert len(ids) == len(set(ids)), "Duplicate property IDs"
assert 59791 not in ids, "Draft property 59791 must be excluded"
known_entities = {"org:pera-property", "place:istanbul", "process:turkish-citizenship-property", "service:property-management", "concept:property-purchase"}
registry = (BASE / "entity-registry.md").read_text(encoding="utf-8")
import re
known_entities.update(re.findall(r"`(area:[a-z0-9-]+)`", registry))
district_mapped = 0
for record in records:
    assert record["canonical_url"] == record["inventory_url"], record["wp_id"]
    assert record["canonical_verification"] == "http-observed-2026-11-17", record["wp_id"]
    assert record["http_status"] == 200, record["wp_id"]
    assert not record["seo_audit_issues"], record["wp_id"]
    assert {"org:pera-property", "place:istanbul"}.issubset(record["entity_ids"]), record["wp_id"]
    assert set(record["entity_ids"]).issubset(known_entities), record["wp_id"]
    district_mapped += any(e.startswith("area:") for e in record["entity_ids"])
assert district_mapped == 128, f"Expected 128 district-mapped records; got {district_mapped}"
print(f"OK: {len(records)} reconciled property records, {district_mapped} district entity links, verified HTTP canonicals")

print(f"OK: {len(topics)} topics, {len(sources['blog'])} blog posts, "
      f"{len(sources['pages'])} pages, {len(sources['properties'])} properties, "
      f"{len(sources['taxonomies'])} taxonomy terms")
print("Note: property canonical URLs are based on the dated owner-run HTTP audit; this validator does not itself fetch URLs or prove Google indexing.")
