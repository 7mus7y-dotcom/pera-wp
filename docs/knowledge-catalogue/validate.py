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
print(f"OK: {len(topics)} topics, {len(sources['blog'])} blog posts, "
      f"{len(sources['pages'])} pages, {len(sources['properties'])} properties, "
      f"{len(sources['taxonomies'])} taxonomy terms")
print("Note: inventory validation does not establish canonical URLs or indexability.")
