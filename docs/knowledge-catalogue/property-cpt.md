# Property CPT catalogue

`property-cpt.json` contains **125 property records** imported from the repository's `docs/property-inventory.csv` snapshot. Each record retains the WordPress ID, title, slug, inventory permalink and existing taxonomy terms.

## Data confidence

- Taxonomy values reflect the repository export, not a fresh WordPress query.
- `canonical_url: null` means not independently verified, **not** absent.
- ACF values are deliberately null until exported from WordPress.
- The `Citizenship` taxonomy term is **not** proof that an individual property is eligible for citizenship; `citizenship_eligibility_verified` remains null.
- `listing_status: unverified` means the export does not prove currently for sale or published.

## ACF fields to enrich

- Project identity/name, property type and development relationship
- Bedrooms, gross/net sizes, single resale asking price
- Project `v2_units` repeater: bedrooms, gross size min/max, USD price min/max, index key
- Sold-out/status flag and publication status
- Verified citizenship suitability and evidence (if independently documented)
- Multilingual translation relationships and live canonical URLs

## Read-only WordPress export specification

Use WP-CLI on production **only to read**, export CSV or JSON to a protected temporary path, and transfer the resulting file for import into this PR. Select the `property` CPT; include ID, post status, permalink, project name, `bedrooms`, `size`, `v2_units`, `sold_out` and other confirmed ACF keys. Confirm field names against the repository ACF export before scripting. Do not publish private seller information, client data or unpublished listing content in this public GitHub repository. Restrict catalogue enrichment to public listing records.

## Maintenance

Rebuild from a fresh inventory periodically, using WordPress IDs as stable identifiers; compare added/removed IDs and changed URLs. Never use a guessed permalink as a canonical tag or imply live availability from the snapshot.
