# Property CPT HTTP audit — 17 November 2026

## Evidence and scope

Owner ran the read-only `verify-property-seo.py` script against all **130** records in the reconciled `property-cpt.json` catalogue from the production hosting terminal. Source is the **terminal summary supplied by the owner**, not the raw `/tmp/pera-property-seo-audit-full.json` file. The audit used HTTP GET requests and parsed the returned HTML.

| Metric | Observed |
| --- | ---: |
| Property URLs checked | 130 |
| HTTP 200 | 130 |
| Canonical tags missing | 0 |
| Pages with noindex detected | 0 |
| URLs redirected | 0 |
| Pages with hreflang present | 130 |
| Script-reported issues | 0 |

The script checks that a single canonical tag exists and matches the final requested URL after normalizing a trailing slash. Thus zero script-reported issues supports **no canonical mismatch detected by this script**. It does not independently validate whether the canonical target is appropriate for every language, or whether Google indexes it.

## Verification boundaries

- This result verifies **HTTP observations** for the 130 URLs in the working catalogue at audit time. It does **not** verify that all properties are still for sale.
- The script does not validate reciprocal hreflang, language/region-code correctness, robots.txt allow/disallow rules, XML sitemap inclusion, rendered JS content, structured data, or Google Search Console indexation.
- The summary does not contain the individual observed canonical URLs or per-page hreflang values. **Do not populate `canonical_url` in `property-cpt.json` from the summary alone.** The full JSON audit file is needed for that step.
- The 130-record set excludes post **59791**, made draft by the owner after the original 131-property export. No new WordPress inventory refresh was performed.

## Next actions

1. Obtain the generated `/tmp/pera-property-seo-audit-full.json` or a sanitized export of `post_id`, `final_url`, `canonical_tags`, `robots_meta` and `hreflang` for source-backed per-property canonical enrichment.
2. Validate representative reciprocal hreflang pairs and language-specific canonicals.
3. Check sitemap and robots.txt separately, then optionally use Search Console for actual indexing evidence.
4. Complete taxonomy/entity mapping without inferring administrative geography from marketing labels.

No production content or configuration changes were made during this audit.
