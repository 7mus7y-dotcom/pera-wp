# Live verification checklist — AI SEO catalogue

This checklist is prepared for production/staging execution. **No live URL was verified during this review:** the remote website was not accessible to the audit environment. Never mark a topic `existing-verified` on the strength of the repository inventory alone.

## Safe read-only verification

1. Export the current published WordPress content on the actual site (posts, pages and property CPT), including ID, post status, modified date, permalink and SEO canonical metadata.
2. For each `inventory_url` in `topic-map.json`, request with redirects **disabled** and record HTTP status, Location and response time. Then follow redirects to record the final URL.
3. Inspect final HTML for robots meta, HTTP X-Robots-Tag, canonical URL, hreflang, H1 and schema types.
4. Check robots.txt and XML sitemaps. A sitemap entry does not establish indexation; verify in Search Console separately.
5. Identify pages sharing the same intent, especially district taxonomy archives versus editorial guides.
6. For multilingual pages, validate language-specific canonicals, reciprocal hreflang and redirects independently.

## Example commands

Run from a terminal with network access, substituting an actual inventory URL:

```bash
URL='https://www.peraproperty.com/citizenship-by-investment/'
curl -sS -I --max-redirs 0 "$URL"
curl -sS -IL --max-redirs 10 "$URL"
curl -sS https://www.peraproperty.com/robots.txt
```

`curl -I` uses HEAD and may differ from GET behaviour. For authoritative HTML checks, also issue a GET request and inspect the body.

## Acceptance criteria for a verified catalogue mapping

- Published WordPress object ID confirmed
- Final HTTP GET returns 200
- Canonical URL recorded from the final HTML
- No unexpected noindex or robots restrictions
- Topic intent and page content manually confirmed
- Last-reviewed date and evidence source recorded
- Localized equivalents reviewed if applicable

## Suggested priority checks

1. Legacy `_ID` URLs and their newer slug variants (including Kadıköy guide).
2. Citizenship hub and 2026 specialist articles (valuation, DAB, conformity, timeline).
3. District guides versus `/district/istanbul/{district}/` archives.
4. Landlord, property management and purchase-cost guides.
5. Existing `/llms.txt` and `/llms-full.txt` outputs: ensure they reference correct, public canonical URLs; these are supplementary discovery aids, not ranking guarantees.

## Required output

Produce a CSV with columns: `topic_id,post_id,inventory_url,final_url,http_status,canonical_url,robots_indexable,hreflang_checked,content_reviewed,checked_at,evidence,action`. Populate only observed facts. Any inaccessible endpoint remains `unverified`.

## Property CPT automated HTTP audit (added 2026-11-17)

The branch contains `docs/knowledge-catalogue/verify-property-seo.py`, a **read-only** Python 3 standard-library tool. It uses the existing reconciled 130-record JSON catalogue, not a fresh WordPress export. It performs GET requests (not HEAD), follows up to five same-domain redirects, extracts canonical links, robots meta, X-Robots-Tag, hreflang links and H1, and records status and redirect hops. It does **not** verify reciprocal hreflang, robots.txt rules, structured data, Google indexing, or whether an apartment is for sale.

Run on the hosting server (no branch checkout needed):

```bash
cd /home/d21882/_work/pera-wp
git fetch origin docs/ai-seo-knowledge-catalogue
git show origin/docs/ai-seo-knowledge-catalogue:docs/knowledge-catalogue/verify-property-seo.py > /tmp/pera-verify-property-seo.py
git show origin/docs/ai-seo-knowledge-catalogue:docs/knowledge-catalogue/property-cpt.json > /tmp/pera-property-cpt.json
python3 /tmp/pera-verify-property-seo.py --catalogue /tmp/pera-property-cpt.json --limit 15 --output /tmp/pera-property-seo-audit.json
```

After checking the 15-record pilot, audit all 130 with `--limit 0 --delay 1`. The output is created mode 0600; download it and review before sharing. Do not mark canonicals verified in the catalogue until the resulting observations are reviewed.
