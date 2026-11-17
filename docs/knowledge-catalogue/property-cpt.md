# Property CPT catalogue

`property-cpt.json` now contains **130 property records** reconciled from the repository's 125-record `docs/property-inventory.csv` snapshot and the user-supplied WP-CLI export of **131 published properties** dated 17 November 2026.

The six export-only records have been added: **22720, 34223, 60071, 60100, 60258, 60339**. **59791** was published at export time but the owner subsequently made it a **draft**, so it is excluded from this published-property catalogue. **No new WordPress export or refresh was performed**. The underlying CSV remains a historical 125-record snapshot, not the canonical source for this reconciled JSON.

## Geographic and entity relationships

Every record is associated with `org:pera-property` and `place:istanbul`. Where its existing WordPress district taxonomy matches a registered district entity, the record additionally has a corresponding `area:...` ID in `entity_ids`. **128 records** match an explicit district taxonomy to a registered district entity. **Two records** (39557 and 40298) have Bahçeşehir region taxonomy only and link to the Bahçeşehir market-area entity, without inferring an administrative district.

The mapping uses only explicit taxonomy labels. For example, the `Eyup` district taxonomy maps to the registry's `area:eyup`, while `Istanbul` is treated as the city, not a district. Neighbourhood or marketing names must not be inferred from listing titles.

## Data confidence and limitations

- The 130 `canonical_url` values are supported by the owner-run HTTP audit dated 2026-11-17; the original `inventory_url` fields remain source permalinks. This is point-in-time evidence, not a guarantee of Google indexing.
- `listing_status: published-at-export` reflects the historical export, not proof that a unit is still available for sale. Property 59791 is explicitly excluded following the owner's status change.
- Existing ACF fields remain null **pending structured import**, not because those fields are absent from WordPress. The private export has not been committed to the public repository.
- The owner confirmed that incomplete min/max V2 repeater fields are **intentional for resale units**. Do not automatically fill them or flag them as errors.
- A `Citizenship` taxonomy term is **not proof of individual property eligibility**; `citizenship_eligibility_verified` remains null.
- The repository CSV and JSON now intentionally differ: CSV is the older 125-row source snapshot; JSON is the reconciled 130-property working catalogue.

## Remaining follow-up

1. The full 130-property HTTP audit passed; a 12-property / 60-target reciprocal hreflang sample also passed. See the dated verification reports.
2. Optional: enrich public ACF fields from the already-provided export, without inferring resale price ranges or publishing confidential metadata.
3. The historical 125-row CSV intentionally differs from the reconciled 130-property JSON. Update both and the validator together only when adopting a single authoritative rebuild workflow.
4. Google Search Console indexation and full translated-language coverage remain separate checks.

See `property-export-audit-2026-11-17.md` for export reconciliation and owner-reviewed pricing exceptions.
