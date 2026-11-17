# Property CPT catalogue

`property-cpt.json` now contains **130 property records** reconciled from the repository's 125-record `docs/property-inventory.csv` snapshot and the user-supplied WP-CLI export of **131 published properties** dated 17 November 2026.

The six export-only records have been added: **22720, 34223, 60071, 60100, 60258, 60339**. **59791** was published at export time but the owner subsequently made it a **draft**, so it is excluded from this published-property catalogue. **No new WordPress export or refresh was performed**. The underlying CSV remains a historical 125-record snapshot, not the canonical source for this reconciled JSON.

## Geographic and entity relationships

Every record is associated with `org:pera-property` and `place:istanbul`. Where its existing WordPress district taxonomy matches a registered district entity, the record additionally has a corresponding `area:...` ID in `entity_ids`. **84 records** currently match an existing district entity; **46** retain their district taxonomy labels without an entity ID beyond the organisation and city. These are **registry coverage gaps**, not necessarily incorrect district assignments.

The mapping uses only explicit taxonomy labels. For example, the `Eyup` district taxonomy maps to the registry's `area:eyup`, while `Istanbul` is treated as the city, not a district. Neighbourhood or marketing names must not be inferred from listing titles.

## Data confidence and limitations

- `inventory_url` is a WordPress export/repository permalink, **not** a verified canonical URL. `canonical_url: null` means pending HTTP verification.
- `listing_status: published-at-export` reflects the historical export, not proof that a unit is still available for sale. Property 59791 is explicitly excluded following the owner's status change.
- Existing ACF fields remain null **pending structured import**, not because those fields are absent from WordPress. The private export has not been committed to the public repository.
- The owner confirmed that incomplete min/max V2 repeater fields are **intentional for resale units**. Do not automatically fill them or flag them as errors.
- A `Citizenship` taxonomy term is **not proof of individual property eligibility**; `citizenship_eligibility_verified` remains null.
- The repository CSV and JSON now intentionally differ: CSV is the older 125-row source snapshot; JSON is the reconciled 130-property working catalogue.

## Next technical verification

1. Verify actual HTTP status, redirects, canonical tags, indexability and hreflang on representative listing URLs, then expand coverage.
2. Enrich public ACF fields from the already-provided export using a validated field-key mapping. Keep resale single-value prices distinct from project ranges, and never publish confidential metadata.
3. Expand district entity registry only after checking actual district taxonomy values and whether the area is an administrative district or neighbourhood.
4. Update the CSV and validation scripts together when choosing a single authoritative source for future catalogue rebuilds.

See `property-export-audit-2026-11-17.md` for export reconciliation and owner-reviewed pricing exceptions.
