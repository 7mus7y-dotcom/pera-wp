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
