#!/usr/bin/env python3
"""Read-only HTTP audit of property catalogue URLs. Python 3 standard library only."""
import argparse
import csv
import json
import os
import time
from datetime import datetime, timezone
from html.parser import HTMLParser
from urllib.error import HTTPError, URLError
from urllib.parse import urljoin, urlparse
from urllib.request import Request, build_opener, HTTPRedirectHandler

class Tags(HTMLParser):
    def __init__(self):
        super().__init__()
        self.canonicals = []
        self.hreflang = []
        self.robots = []
        self.h1 = []
        self._in_h1 = False
    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == "link":
            rel = a.get("rel", "").lower().split()
            if "canonical" in rel and a.get("href"):
                self.canonicals.append(a["href"])
            if "alternate" in rel and a.get("hreflang") and a.get("href"):
                self.hreflang.append({"lang": a["hreflang"], "url": a["href"]})
        elif tag == "meta":
            if a.get("name", "").lower() in ("robots", "googlebot"):
                self.robots.append({"name": a["name"].lower(), "content": a.get("content", "")})
        elif tag == "h1":
            self._in_h1 = True
    def handle_data(self, data):
        if self._in_h1:
            self.h1.append(data.strip())
    def handle_endtag(self, tag):
        if tag == "h1":
            self._in_h1 = False

class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

def fetch(url, timeout, max_redirects):
    opener = build_opener(NoRedirect)
    hops = []
    for _ in range(max_redirects + 1):
        req = Request(url, headers={"User-Agent": "PeraPropertySEOAudit/1.0 (site-owner read-only)", "Accept": "text/html"})
        try:
            response = opener.open(req, timeout=timeout)
        except HTTPError as exc:
            response = exc
        with response:
            status = response.status
            headers = response.headers
            loc = headers.get("Location")
            hops.append({"url": url, "status": status, "location": loc})
            if status in (301, 302, 303, 307, 308) and loc:
                nxt = urljoin(url, loc)
                if urlparse(nxt).hostname not in ("www.peraproperty.com", "peraproperty.com"):
                    return {"error": "off-domain redirect", "redirects": hops}
                url = nxt
                continue
            body = response.read(2_000_000)
            return {"final_url": url, "http_status": status, "redirects": hops,
                    "headers": dict(headers.items()), "html": body.decode("utf-8", "replace")}
    return {"error": "redirect limit exceeded", "redirects": hops}

def audit(record, timeout, max_redirects):
    result = {"post_id": record["wp_id"], "inventory_url": record["inventory_url"],
              "checked_at": datetime.now(timezone.utc).isoformat(), "verification": "unverified"}
    try:
        fetched = fetch(record["inventory_url"], timeout, max_redirects)
        result["redirects"] = fetched.get("redirects", [])
        if "error" in fetched:
            result["error"] = fetched["error"]
            return result
        result["final_url"] = fetched["final_url"]
        result["http_status"] = fetched["http_status"]
        headers = {k.lower(): v for k, v in fetched["headers"].items()}
        result["x_robots_tag"] = headers.get("x-robots-tag")
        result["content_type"] = headers.get("content-type")
        parser = Tags()
        parser.feed(fetched["html"])
        result["canonical_tags"] = parser.canonicals
        result["hreflang"] = parser.hreflang
        result["robots_meta"] = parser.robots
        result["h1"] = " ".join(x for x in parser.h1 if x)[:300]
        directives = " ".join([result["x_robots_tag"] or ""] + [m["content"] for m in parser.robots]).lower()
        result["noindex_detected"] = "noindex" in directives
        result["verification"] = "http-observed" if result["http_status"] == 200 else "http-non-200"
        result["issues"] = []
        if result["http_status"] != 200:
            result["issues"].append("non-200")
        if not parser.canonicals:
            result["issues"].append("canonical-missing")
        elif len(parser.canonicals) > 1:
            result["issues"].append("multiple-canonicals")
        elif urljoin(result["final_url"], parser.canonicals[0]).rstrip("/") != result["final_url"].rstrip("/"):
            result["issues"].append("canonical-differs-from-final-url")
        if result["noindex_detected"]:
            result["issues"].append("noindex")
        if not parser.hreflang:
            result["issues"].append("hreflang-not-observed")
    except (HTTPError, URLError, TimeoutError, OSError, ValueError) as exc:
        result["error"] = str(exc)[:350]
    return result

def main():
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--catalogue", default="docs/knowledge-catalogue/property-cpt.json")
    ap.add_argument("--output", default="/tmp/pera-property-seo-audit.json")
    ap.add_argument("--limit", type=int, default=15, help="Max URLs; 0 means all")
    ap.add_argument("--delay", type=float, default=1.0, help="Seconds between requests")
    ap.add_argument("--timeout", type=float, default=15.0)
    ap.add_argument("--max-redirects", type=int, default=5)
    args = ap.parse_args()
    with open(args.catalogue, encoding="utf-8") as f:
        data = json.load(f)
    records = data["records"]
    if args.limit < 0 or args.delay < 0:
        ap.error("limit and delay must be nonnegative")
    if args.limit:
        # Deterministic spread across catalogue rather than only oldest records.
        if args.limit < len(records):
            n = args.limit
            records = [records[round(i * (len(records) - 1) / max(n - 1, 1))] for i in range(n)]
    output = []
    for i, rec in enumerate(records):
        if i:
            time.sleep(args.delay)
        item = audit(rec, args.timeout, args.max_redirects)
        output.append(item)
        print(f'{i+1}/{len(records)} ID {rec["wp_id"]}: {item.get("http_status", item.get("error", "unknown"))}', flush=True)
    result = {"checked_at": datetime.now(timezone.utc).isoformat(), "source": args.catalogue,
              "checked_count": len(output), "results": output}
    fd = os.open(args.output, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
    with os.fdopen(fd, "w", encoding="utf-8") as f:
        json.dump(result, f, indent=2, ensure_ascii=False)
        f.write("\n")
    print(f"Saved {len(output)} observations to {args.output}")

if __name__ == "__main__":
    main()
