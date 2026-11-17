#!/usr/bin/env python3
"""Read-only sample check of reciprocal hreflang on property pages.

Uses existing property HTTP audit JSON. Does not change WordPress or catalogue.
"""
import argparse
import json
import os
import time
from collections import Counter
from urllib.parse import urlparse, urljoin

from importlib.machinery import SourceFileLoader
from pathlib import Path

seo = SourceFileLoader("pera_property_seo", str(Path(__file__).with_name("verify-property-seo.py"))).load_module()

def norm(url):
    p = urlparse(url)
    return (p.scheme.lower(), p.netloc.lower(), p.path.rstrip("/"), p.query)

def main():
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--audit", required=True, help="Full 130-property audit JSON")
    ap.add_argument("--limit", type=int, default=12, help="Evenly spaced source properties")
    ap.add_argument("--delay", type=float, default=1)
    ap.add_argument("--timeout", type=float, default=15)
    ap.add_argument("--output", default="/tmp/pera-hreflang-audit.json")
    args = ap.parse_args()
    if args.limit < 1 or args.delay < 0:
        ap.error("limit must be positive; delay nonnegative")
    with open(args.audit, encoding="utf-8") as f:
        rows = json.load(f)["results"]
    n = min(args.limit, len(rows))
    sampled = [rows[round(i * (len(rows)-1) / max(n-1, 1))] for i in range(n)]
    out = []
    for row in sampled:
        source = row["final_url"]
        links = row.get("hreflang", [])
        # Inspect one URL per language on each sampled property.
        seen = set()
        for item in links:
            lang = item.get("lang", "").lower()
            if lang in seen or lang == "x-default":
                continue
            seen.add(lang)
            target = urljoin(source, item["url"])
            entry = {"post_id": row["post_id"], "source": source,
                     "language": lang, "target": target, "issues": []}
            if urlparse(target).hostname not in ("www.peraproperty.com", "peraproperty.com"):
                entry["issues"].append("external-hreflang-target")
                out.append(entry)
                continue
            try:
                fetched = seo.fetch(target, args.timeout, 5)
                if "error" in fetched:
                    entry["issues"].append(fetched["error"])
                else:
                    entry["http_status"] = fetched["http_status"]
                    entry["final_url"] = fetched["final_url"]
                    parser = seo.Tags()
                    parser.feed(fetched["html"])
                    entry["canonical_tags"] = parser.canonicals
                    entry["backlinks"] = [x for x in parser.hreflang
                                          if norm(urljoin(fetched["final_url"], x["url"])) == norm(source)]
                    if fetched["http_status"] != 200:
                        entry["issues"].append("target-non-200")
                    if norm(fetched["final_url"]) != norm(target):
                        entry["issues"].append("target-redirect")
                    if len(parser.canonicals) != 1 or norm(urljoin(fetched["final_url"], parser.canonicals[0])) != norm(fetched["final_url"]):
                        entry["issues"].append("target-canonical-not-self")
                    if not entry["backlinks"]:
                        entry["issues"].append("reciprocal-hreflang-missing")
                    elif not any(x["lang"].lower().startswith("en") for x in entry["backlinks"]):
                        entry["issues"].append("reciprocal-source-language-unexpected")
            except Exception as exc:
                entry["issues"].append("fetch-error")
                entry["error"] = str(exc)[:200]
            out.append(entry)
            print(f'ID {row["post_id"]} {lang}: {entry.get("http_status", "ERROR")} {entry["issues"]}', flush=True)
            time.sleep(args.delay)
    result = {"sampled_properties": n, "checked_links": len(out),
              "issue_counts": dict(Counter(i for r in out for i in r["issues"])), "results": out}
    fd = os.open(args.output, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
    with os.fdopen(fd, "w", encoding="utf-8") as f:
        json.dump(result, f, indent=2, ensure_ascii=False)
    print(f'Saved {len(out)} checks to {args.output}')
    print('Issues:', result["issue_counts"])

if __name__ == "__main__":
    main()
