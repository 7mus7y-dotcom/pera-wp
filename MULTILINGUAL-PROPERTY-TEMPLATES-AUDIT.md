# Pera Multilingual Property Template Audit — Current Main Cleanup

## Scope and decision record

This focused audit rechecked the historical findings from PRs #1308 and #1315 against current main rather than treating old review comments as current defects. The supported architecture remains canonical English content plus stored Pera ML translations for editorial copy, and deterministic formatting for dates.

**Project decision — media translation is intentionally excluded.** Attachment alt text, image alt text, gallery alt text, floor-plan alt text, and general media metadata are not translation-contract requirements. Existing canonical English alt/accessibility attributes must remain present; their existence is not an outstanding multilingual defect.

## Historical review matrix

This matrix records the requested audit against the repository state at the start of this cleanup. All six findings are **RESOLVED BY LATER WORK** on current main; this PR therefore avoids re-solving them and adds only focused regression/documentation hardening.

| Finding | Historical problem | Current-main status before this PR | Code changed here | Final proof |
|---|---|---|---|---|
| #1308-A blog taxonomy health | `category` and `post_tag` were not consistently discoverable or accepted. | **RESOLVED BY LATER WORK.** Both use `Pera_ML_Fields::supported_taxonomies()` and `taxonomy_fields()`, which Health, the orchestrator, admin generation, storage and ACF term identification already consume. | No production change; no duplicate taxonomy list added. | Health inventory tests cover missing rows for both taxonomies; orchestrator tests cover canonical name fields and now explicitly cover the `post_tag` description path. |
| #1308-B canonical Istanbul sentinel | A translated root-city name could become “{translation}, Istanbul”. | **RESOLVED BY LATER WORK.** District/region helpers decide from canonical `$term->name` before resolving display translation. | No production change. | Regression now exercises both helpers with Chinese, German and Arabic display values and requires the untranslated suffix to be absent. |
| #1308-C raw taxonomy SEO source | Normalization before lookup could disagree with the raw source hash and falsely mark translations stale. | **RESOLVED BY LATER WORK.** Raw unformatted ACF/meta is selected and passed to `pera_ml_term_meta()`; stripping and whitespace normalization follow resolution. | No production change. | Runtime regression includes markup, line breaks and repeated whitespace, verifies the exact lookup source, and verifies normalized translated rendering. |
| #1315-A listing metadata labels | Last-updated and `Ref:` labels were visitor-visible English. | **RESOLVED BY LATER WORK.** Both labels use literal, discoverable semantic `pera_ml_ui()` calls; the reference remains canonical data. | No production change. | Source regression checks both identities and the independently escaped property ID. |
| #1315-B pagination labels | Shared `paginate_links()` used literal `Prev`/`Next`. | **RESOLVED BY LATER WORK.** The one shared renderer localizes both labels and is used by normal archive and AJAX results. | No production change. | Source regression checks both UI identities and both shared-renderer consumers. |
| #1315-C property dates | WordPress's English request locale leaked English month names into routed languages. | **RESOLVED BY LATER WORK.** A narrow deterministic formatter preserves English, supplies German/Arabic month names, and uses natural Chinese numeric order without switching the request locale. | No production change. | Runtime tests assert exact English/German/Arabic/Chinese output and that date formatting never calls Pera UI/provider translation. |

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
