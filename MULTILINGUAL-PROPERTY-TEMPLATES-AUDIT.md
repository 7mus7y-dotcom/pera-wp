# Pera Multilingual Property Template Audit — Current Main Cleanup

## Scope and decision record

This focused audit rechecked the historical findings from PRs #1308 and #1315 against current main rather than treating old review comments as current defects. The supported architecture remains canonical English content plus stored Pera ML translations for editorial copy, and deterministic formatting for dates.

**Project decision — media translation is intentionally excluded.** Attachment alt text, image alt text, gallery alt text, floor-plan alt text, and general media metadata are not translation-contract requirements. Existing canonical English alt/accessibility attributes must remain present; their existence is not an outstanding multilingual defect.

## Historical review matrix

| Finding | Current-main status before this PR | Cleanup result |
|---|---|---|
| #1308 blog taxonomy health | **Partially resolved.** `category` already used the central supported-taxonomy/name-description contract and normal Health/orchestrator/storage paths; `post_tag` did not. | Added only `post_tag` to that central contract and ACF term identification. No parallel whitelist was introduced. |
| #1308 canonical Istanbul sentinel | **Partially resolved.** Archive headings checked the canonical term first, while district/region location-name helpers returned canonical display text and did not safely combine translated display with the sentinel. | Location helpers now derive the sentinel from canonical English before obtaining the translated display. Canonical Istanbul returns only its translated term, never “{translation}, Istanbul”. |
| #1308 raw taxonomy SEO source | **Still valid on a narrow path.** The archive ACF accessor read raw data but normalized it before `pera_ml_term_meta()`; SEO title/description were also absent from the property-taxonomy field contract. | Raw ACF/meta is selected and passed to storage lookup first; stripping and whitespace normalization occur after translated/canonical resolution. |
| #1315 listing metadata labels | **Still valid.** The last-updated and `Ref:` labels were visitor-visible English. | Both labels now use statically discoverable semantic `pera_ml_ui()` calls; the numeric property reference is unchanged. |
| #1315 pagination labels | **Still valid.** The shared `paginate_links()` renderer supplied literal `Prev`/`Next`. | The shared renderer now resolves both labels through Pera ML UI, covering SSR archive and AJAX consumers without duplication. |
| #1315 property dates | **Still valid.** Single-property and unit-price copy used WordPress English month output. | Added a narrow, provider-free formatter driven by the current Pera language: unchanged English `j F Y`, localized German and Arabic month names, and Chinese `Y年n月j日`. No request-wide locale switch is used. |

## Original ML-PROP status on current main

The subsequent merged remediation work resolves the original property-template issues: taxonomy archive display and SEO fields, central taxonomy contracts, archive settings, raw-source handling, price copy and controlled values, forms, favourites, normal/AJAX property results, and internal URL localization (**ML-PROP-007**) now use the established multilingual paths. Advisor/team position (**ML-PROP-010**) is resolved by the current `Team.position` translation contract and Translation Health coverage. The historical raw `Q:` FAQ prefix has been removed and is resolved.

Any remaining Edit link/pill is an authenticated administrator affordance, not public visitor copy, and therefore has low/no public multilingual priority.

## Final verification boundaries

Dates are deterministic presentation data: they are neither stored in Translation Health nor sent to a provider. The cleanup adds no translated slugs, global locale switching, address/Bodrum work, media fields, provider changes, or deployment behavior. Canonical English frontend output is retained except for routing the identified labels through identity-aware UI lookup and centralizing date formatting.

### New UI identities

- `theme.template.single_property.last_updated_label`
- `theme.template.single_property.reference_label`
- `theme.property.pagination.previous`
- `theme.property.pagination.next`

These are literal theme calls and are discoverable by `tools/register-theme-ui-strings.php`; no translation rows are inserted by this change.
