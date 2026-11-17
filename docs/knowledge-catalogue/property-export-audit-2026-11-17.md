# Live WordPress property export reconciliation — 17 November 2026

## Scope and provenance
- Source: user-supplied `pera-property-export.json`, exported `2026-11-17T07:40:54+00:00`.
- Compared against repository `docs/property-inventory.csv` on branch `docs/ai-seo-knowledge-catalogue`.
- This is a comparison of a WordPress export and repository snapshot, **not** an HTTP crawl or verification of search-engine indexability. No production changes were made.

## Reconciliation
| Measure | Result |
| --- | ---: |
| Exported property posts | 131 |
| Exported posts with status `publish` | 131 |
| Unique post IDs | 131 |
| Existing CSV property rows | 125 |
| IDs present in export but absent from CSV | 6 |
| CSV IDs absent from export | 0 |
| Missing or duplicate exported permalinks | 0 |

### Six properties absent from the repository inventory
| Post ID | Title |
| --- | --- |
| 22720 | Taksim 360 Restored Historic Residences and Offices by Taksim Square |
| 34223 | Otto Ataşehir Boutique Residences Beside Metro and Financial Center |
| 60071 | Top-Floor 3-Bedroom Sea View Apartment at Deniz Istanbul |
| 60100 | Low-Density Forest Apartments Near the Metro in Göktürk |
| 60258 | Large Furnished Studio for Sale at Extensa Bomonti |
| 60339 | Private Forest Villas with Pools in Polonezköy |

## ACF observations
- 128 of 131 posts have a nonempty `v2_units` repeater; 307 repeater rows total.
- Three posts have no `v2_units`: **39557** (Townhouses in green Bahcesehir), **40298** (Peaceful villa compound in the hills of Bahcesehir), **59791** (New Build Apartments for Sale in Topkapi, Istanbul). The first two have legacy `price_usd` values; post 59791 has neither a repeater nor a legacy USD price in this export.
- 41 posts lack the legacy `price_usd` field; this is **not** automatically a defect because the newer repeater may be authoritative.
- 128 posts lack a scalar `bedrooms` value; project inventory generally uses repeater bedroom counts, so do not treat these as 128 errors.
- Six repeater rows have one or more empty bedroom, minimum gross size, or minimum USD price fields. Affected post IDs include **11663, 11920, 42878, 56011**; inspect these in WordPress before making a corrective edit. Some rows contain maximum-only values or blank placeholders.
- `sold_out` is not truthy on any of the 131 exported posts; confirm whether the export represents all statuses and whether sold status is maintained through another mechanism before drawing conclusions.

## Editorial review after export (user-confirmed, 17 November 2026)
- **59791** has since been changed from published to **draft**. It was published when the export was taken; the next published-only inventory should exclude it.
- **40298 and 59791** now have prices. The export is a historical snapshot and does not reflect those subsequent edits.
- Incomplete min/max V2 fields for resale units are **intentional**; they are not pricing errors and should not be auto-filled. The user has confirmed the flagged incomplete listings are acceptable.
- **39557** has not been separately updated or verified in this review; its missing V2 repeater is not, by itself, evidence of an error.
- A future editor/data-model improvement may distinguish single resale prices and sizes from project pricing ranges. This is outside the current SEO catalogue scope.

## Recommended next steps
1. Refresh the published-only property inventory after the draft change, preserving the existing CSV schema and taxonomy labels. Reconcile the six previously missing IDs, but do not republish post 59791.
2. Treat missing V2 range fields on resale units as intentional; record the distinction between range-based project inventory and single-unit resales.
3. Defer any V2 editor/schema redesign to a separate task; do not auto-fill missing resale range values.
4. Verify canonical URLs, robots, redirects, schema, and hreflang by HTTP before declaring any property live-verified.

**Data handling:** The JSON export itself is not committed to the repository; only aggregate findings and public listing IDs/titles are recorded.
