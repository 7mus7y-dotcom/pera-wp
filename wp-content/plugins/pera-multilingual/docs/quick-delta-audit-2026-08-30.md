# Pera Property multilingual quick delta audit

**Date:** 2026-08-30  
**Audited revision:** `afcfb396` (merge of PR #1304)  
**Scope:** `wp-content/plugins/pera-multilingual/` and `wp-content/themes/hello-elementor-child/`

This is a concise delta review against findings ML-01 through ML-14, not a new full architectural audit. Current source was treated as authoritative. Bodrum `bp_*` fields and obsolete page ID 46854 are excluded as directed.

## Current multilingual status

| Finding | Original severity | Current status | Remaining priority |
|---|---:|---|---|
| ML-01 — Template/frontend discovery omissions | Critical | **PARTIALLY RESOLVED – material work remains** | P1 |
| ML-02 — Client portal/auth UI bypass | High | **PARTIALLY RESOLVED – material work remains** | P0 |
| ML-03 — Test/dev templates with raw English | Medium | **OPEN** | P2 |
| ML-04 — Raw taxonomy names/descriptions | High | **PARTIALLY RESOLVED – material work remains** | P1 |
| ML-05 — Raw taxonomy ACF/meta | High | **PARTIALLY RESOLVED – material work remains** | P1 |
| ML-06 — Bodrum `bp_*` fields | High | **INTENTIONALLY DEFERRED** | Ignored; scheduled deletion |
| ML-07 — Other ACF/meta translation gaps | High | **PARTIALLY RESOLVED – material work remains** | P1 |
| ML-08 — Parallel untranslated SEO/schema paths | High | **PARTIALLY RESOLVED – material work remains** | P1 |
| ML-09 — Price sort semantic-key collision | High | **RESOLVED** | None |
| ML-10 — Suspicious semantic-key reuse | Medium | **RESOLVED** | None |
| ML-11 — Static UI discovery blind spots | High | **OPEN** | P1 |
| ML-12 — Hard-coded Chinese maintenance fork | Medium | **OPEN** | P2 |
| ML-13 — URL localisation/language preservation | Medium | **PARTIALLY RESOLVED – material work remains** | P1 |
| ML-14 — English fallback masking missing/stale coverage | Medium | **PARTIALLY RESOLVED – material work remains** | P0 |

## Finding-by-finding delta

### ML-01 — PARTIALLY RESOLVED – material work remains

- **Remaining issue:** Auth/login/register templates are in the approved UI inventory, but `Pera_ML_Theme_UI_Discovery::approved_directories()` still deliberately excludes arbitrary root, language-specific, test/dev, and `_archive` templates. The live `page-portfolio-token.php` is absent.
- **Files/functions:** `includes/class-theme-ui-discovery.php` (`approved_directories()`, `php_files()`); `page-portfolio-token.php`, `home-page-test.php`, `page-v2-query-test.php`, `test.php`, `page-zh-citizenship.php`, and `_archive` paths.
- **Affects:** Visible frontend UI and Translation Health completeness.
- **Risk today:** High for inventory trust; medium for ordinary navigation.
- **Next remediation:** Recursively classify frontend-capable PHP and report excluded/raw/dynamic cases rather than silently omitting them.
- **Architecture:** Modest discovery/inventory extension; existing storage/rendering can remain.

### ML-02 — PARTIALLY RESOLVED – material work remains

- **Remaining issue:** Login, forgotten-password, portal, and registration UI now use Pera ML, but the public portfolio-token interface still renders raw English/gettext for its heading, personalised lead, count, expiry, view controls, empty state, table, FAQs, and invalid-token state.
- **Files/functions:** `page-portfolio-token.php` and related renderers in `inc/portfolio-token.php`.
- **Affects:** Visible client-facing frontend UI.
- **Risk today:** High: translated client journeys can end at an English-only shared portfolio.
- **Next remediation:** Convert the portfolio UI to semantic `pera_ml_ui()` calls with translated placeholder formats, then add it to discovery/tests.
- **Architecture:** Reuse existing UI helpers/contracts.

### ML-03 — OPEN

- **Remaining issue:** Dev/test templates retain extensive raw English and direct taxonomy output.
- **Files/functions:** `home-page-test.php`, `page-v2-query-test.php`, `test.php`, and retained archived variants.
- **Affects:** Visible UI if assigned/routed; otherwise maintenance only.
- **Risk today:** Low to medium depending on reachability.
- **Next remediation:** Delete/isolate unused templates or convert any retained public template through normal helpers.
- **Architecture:** Existing helpers suffice; discovery classification is the only broader need.

### ML-04 — PARTIALLY RESOLVED – material work remains

- **Remaining issue:** Major live cards, archives, singles, and AJAX paths now use `pera_ml_term()`, whose retrieval is stale/blank-safe. Direct term names remain in SEO breadcrumbs, guide schema, generated property SEO helpers, and archived/test paths.
- **Files/functions:** `inc/seo-all.php`, `inc/schema.php`, `inc/seo-property.php`, `inc/seo-property-archive.php`, `inc/seo-helpers.php`, `parts/_archive/property-card.php`, and `home-page-test.php`.
- **Affects:** Mainly SEO/schema, plus isolated test/archive UI.
- **Risk today:** Medium-high because machine-readable output can mix translated content with English taxonomy labels.
- **Next remediation:** Route rendered term-derived SEO/schema labels through `pera_ml_term()` and classify non-rendering reads separately.
- **Architecture:** Reuse `pera_ml_term()`.

### ML-05 — PARTIALLY RESOLVED – material work remains

- **Remaining issue:** Supported property-taxonomy archive subtitle/body and taxonomy FAQ fields are routed, but the taxonomy contract omits category/`pera_term_excerpt`, taxonomy `seo_title`, taxonomy `seo_meta_description`, `archive_h1`, and some card metadata. `parts/related-taxonomy-card.php` renders a direct `pera_term_excerpt` read and falls back through raw `term_description()`.
- **Files/functions:** `parts/related-taxonomy-card.php`, category handling in `archive.php`, `inc/taxonomy-meta.php`, `inc/seo-all.php`, and `inc/seo-helpers.php`.
- **Affects:** Visible translated data and SEO/schema.
- **Risk today:** Medium-high.
- **Next remediation:** Extend the reviewed taxonomy contract and Translation Health, then route textual reads through `pera_ml_term_meta()`.
- **Architecture:** Reuse existing term-meta and health contracts.

### ML-06 — INTENTIONALLY DEFERRED

Bodrum `bp_*` fields remain outside the multilingual contract. They are intentionally ignored because the Bodrum implementation is scheduled for deletion and are not a deployment blocker.

### ML-07 — PARTIALLY RESOLVED – material work remains

- **Remaining issue:** Homepage scalar fields, `post_subtitle`, and homepage FAQ are covered. Remaining gaps include About/team `name`, `position`, and `description`; attachment alt/caption/description; option/global/archive-setting text; and other page-specific arrays such as property-map area descriptions.
- **Files/functions:** `page-about-new.php`, `parts/about-pera.php`, `home-page.php`, `page-property-map.php`, attachment rendering, and archive/settings helpers.
- **Affects:** Visible translated data, accessibility metadata, and potentially SEO image metadata.
- **Risk today:** Medium-high.
- **Next remediation:** Add narrow contracts for live team fields and reviewed attachment/global text rather than indiscriminately translating all metadata/options.
- **Architecture:** Existing concepts suffice for posts; attachments/options need small source adapters in Translation Health.

### ML-08 — PARTIALLY RESOLVED – material work remains

- **Remaining issue:** Supported post/page/property `seo_title`, `seo_meta_description`, and `seo_faq_v2` paths are improved. Taxonomy SEO remains outside the contract, with direct term SEO-meta reads and raw category names in schema breadcrumbs.
- **Files/functions:** `pera_seo_all_get_term_manual_text_field()` in `inc/seo-all.php`; property archive term SEO helpers in `inc/seo-helpers.php`; generated SEO in `inc/seo-property*.php`; breadcrumb builders in `inc/seo-all.php` and `inc/schema.php`.
- **Affects:** SEO/schema.
- **Risk today:** High for translated taxonomy landing pages; medium site-wide.
- **Next remediation:** Contract taxonomy SEO fields, use canonical unformatted sources, route them through `pera_ml_term_meta()`, and use `pera_ml_term()` in schema.
- **Architecture:** Reuse the PR #1304 field pattern and current term helpers.

### ML-09 — RESOLVED

`Price ↑` and `Price ↓` now use distinct `price_ascending` and `price_descending` semantic keys in `page-citizenship-properties.php`.

### ML-10 — RESOLVED

The literal-call scan found no semantic key mapped to materially different decoded English sources. The only apparent duplicate was the same apostrophe represented with different PHP escaping.

### ML-11 — OPEN

- **Remaining issue:** Static discovery understands only explicit literal `pera_ml_ui( literal, literal-key )` calls. It does not inventory raw HTML, gettext, DB-backed strings, concatenated/dynamic calls, or unapproved templates.
- **Files/functions:** `Pera_ML_Theme_UI_Discovery::parse()` and `approved_directories()`; examples include `page-portfolio-token.php` and the raw property-map “View properties in …” link.
- **Affects:** Visible frontend UI and Translation Health completeness.
- **Risk today:** High as a governance blind spot: a green UI inventory cannot prove that no untranslated visitor copy exists.
- **Next remediation:** Add non-destructive lint/reporting for raw HTML, gettext, dynamic calls, and excluded frontend files while retaining automatic registration only for explicit semantic calls.
- **Architecture:** Discovery/lint extension only.

### ML-12 — OPEN

- **Remaining issue:** `page-zh-citizenship.php` remains a large hard-coded Chinese fork with separate partials and some English-only controls.
- **Files/functions:** `page-zh-citizenship.php`, `partials/faq-zh-citizenship.php`, and `partials/wechat-cta-zh.php`.
- **Affects:** Visible content/UI, SEO, routing, and maintenance.
- **Risk today:** Medium because compliance, pricing, form, SEO, and content changes can drift from the canonical page.
- **Next remediation:** Freeze and retire the fork after parity QA, then serve Chinese through the canonical template/contracts.
- **Architecture:** Existing architecture is sufficient, with content migration and possibly a few structured contracts.

### ML-13 — PARTIALLY RESOLVED – material work remains

- **Remaining issue:** Footer and logo/home URLs preserve the active language, but many live CTAs still use raw `home_url()` or literal root-relative `/…/` links. Examples remain in the homepage, 404, single post, service pages, auth/client flows, schema breadcrumbs, forms/redirects, and pagination fallbacks.
- **Files/functions:** `home-page.php`, `404.php`, `single-post.php`, `page-rent-with-pera.php`, `page-sell-with-pera.php`, auth/client templates, `inc/schema.php`, `inc/seo-all.php`, `inc/enquiry.php`, and archive pagination helpers.
- **Affects:** URL/routing and translated-session continuity.
- **Risk today:** Medium-high.
- **Next remediation:** Use `pera_ml_url( home_url( ... ) )` consistently for visitor navigation while separately reviewing canonical, admin, analytics, and operational redirects.
- **Architecture:** Reuse `pera_ml_url()` and `pera_ml_home_url()`.

### ML-14 — PARTIALLY RESOLVED – material work remains

- **Remaining issue:** Field and term helpers reject missing, stale, non-current, and blank rows. However, `Pera_ML_Content::translated()` returns any stored `translated_text` without checking `is_stale`, status, or blank content. Stale/blank title, content, or excerpt rows can therefore render.
- **Files/functions:** `Pera_ML_Content::translated()` in `includes/class-content.php`.
- **Affects:** Visible translated content/data and SEO consumers of filtered content.
- **Risk today:** High/P0.
- **Next remediation:** Apply the field helper’s stale/status/nonblank acceptance rule and add title/content/excerpt regressions for stale, pending, blank, and source-hash mismatch rows.
- **Architecture:** Reuse current storage metadata and fallback rules.

## Small regression scan summary

- Raw rendered term output is now concentrated in SEO/schema and archived/test paths rather than primary property cards.
- Related-taxonomy excerpts and taxonomy SEO title/description remain direct metadata reads.
- About/team structured fields remain outside the field contract.
- The public portfolio page and property-map card action contain material raw English UI.
- Taxonomy SEO values and category breadcrumb labels remain parallel untranslated paths.
- Raw visitor-facing `/…/` and unwrapped `home_url()` links can still lose the active language.
- No genuine duplicate semantic UI key with different English sources was found.
- `Pera_ML_Content::translated()` can emit stale/non-current/blank storage rows.
- Public portfolio UI, team fields, attachment text, option/global text, category excerpts, and taxonomy SEO are obvious Translation Health blind spots.

## What is genuinely left

### P0 – fix before deployment

1. Make core title/content/excerpt retrieval stale/status/blank-safe.
2. Convert the public portfolio-token frontend to Pera ML and include it in discovery/health tests.

### P1 – should fix soon

1. Complete taxonomy SEO/schema contracts and term-name routing.
2. Cover remaining taxonomy display metadata such as `pera_term_excerpt`.
3. Perform a visitor-navigation URL preservation pass.
4. Add static discovery lint/reporting for excluded/raw/gettext/dynamic UI.
5. Add reviewed structured contracts for team and attachment/global text.

### P2 – cleanup / architecture / optional

1. Delete or isolate test/dev and obsolete `_archive` implementations.
2. Retire the hard-coded Chinese citizenship fork after canonical parity.
3. Delete obsolete page ID 46854 as planned.
4. Delete Bodrum as planned; do not remediate its multilingual fields.

## Recommended next PR

**Make core content and public portfolio translations stale-safe and inventory-complete.**

Keep the next implementation PR narrowly scoped to:

1. Updating `Pera_ML_Content::translated()` so stale, non-current, missing, and blank rows fall back to canonical English.
2. Adding regressions for title/content/excerpt retrieval.
3. Converting `page-portfolio-token.php` and directly rendered portfolio UI to `pera_ml_ui()`.
4. Adding `page-portfolio-token.php` to discovery and coverage tests.

This removes the clearest stale-translation rendering defect and the remaining high-impact client-facing English bypass without expanding into the broader taxonomy/SEO work.

The current implementation is **not yet sufficiently complete for an unqualified controlled deployment** because stale core content can render and public portfolio links remain English-only. After the P0 PR above, it should be suitable for a controlled deployment with multilingual frontend QA while taxonomy SEO, URL preservation, and discovery work continue as P1 follow-ups.

## Verification performed

- `git status --short --branch`
- `git log --oneline -12`
- Recursive source inventory and focused `rg` scans for term output, taxonomy/post metadata, SEO fields, UI calls, URL construction, and direct storage access.
- PHP syntax lint across the plugin and child theme.
- All 29 standalone plugin PHP test scripts passed.
