# Property CPT HTTP/SEO verification — 17 November 2026

## Source and scope

User-supplied read-only audit `pera-property-seo-audit-full.json`, checked at `2026-11-17T10:49:48.772702+00:00`. The audit contains 130 distinct property IDs and corresponds exactly to the 130 entries in `property-cpt.json`. Property ID **59791** is intentionally excluded at the user's instruction.

## Observed results

| Check | Result |
| --- | --- |
| Property URLs audited | 130 |
| HTTP 200 | 130 |
| Redirects | None reported |
| Canonical tags | Exactly one per page, equal to its requested property URL |
| Robots meta | `index, follow` present |
| `noindex` detected | 0 |
| Audit-reported issues | 0 |

The per-property `canonical_url`, `canonical_verification`, `http_status`, `indexability_observation`, `redirect_observation`, and `seo_audit_issues` fields in `property-cpt.json` record these findings. This is a **point-in-time HTTP observation**, not a guarantee of search engine indexing or a current live-status refresh.

## Not verified by this audit

- Whether Google has indexed or chosen each declared canonical
- Whether every translated hreflang URL resolves and reciprocates correctly
- Whether current property availability, prices, or ACF values are accurate
- Any property omitted from the 130-record audit, including ID 59791

The source JSON was provided for verification and is not committed to the repository.
