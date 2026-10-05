# Multilingual theme coverage audit

**Audit date:** 2026-08-28
**Scope:** `wp-content/themes/hello-elementor-child/` (every PHP file, recursively)
**Contract cross-checked:** `wp-content/plugins/pera-multilingual/`
**Mode:** audit only; no production changes

## Executive summary

The theme is **not fully multilingual-covered**. The audit found good and extensive adoption of `pera_ml_ui()` in the main marketing templates (1,690 literal calls were found), but several independently sufficient coverage failures remain:

1. **Critical discovery gaps:** the plugin intentionally omits multiple live root templates, all files under `parts/_archive/`, and all files under `inc/_archive/`. Calls in those files cannot be pre-registered by the static UI scan.
2. **High-impact raw UI:** the client portal/authentication templates contain substantial visitor-facing English routed through neither `pera_ml_ui()` nor another Pera ML runtime translation path.
3. **High-impact structured-content bypasses:** taxonomy names/descriptions/meta and some post meta are read directly in archives, cards, post templates and SEO/schema output. WordPress gettext calls do not integrate with the Pera ML stored UI registry.
4. **High-impact Bodrum field gap:** `single-bodrum-property.php` renders many `bp_*` ACF text fields, but none are in the plugin's approved property field contract, so the ACF format-value translation layer leaves them in canonical English.
5. **Semantic identity defect:** one UI key is reused for two different source strings (`Price ↑` and `Price ↓`). Because keyed identities are based on the key rather than source, the two registrations contend for one identity/source hash.
6. **Static-only limitations:** the scanner accepts only literal source + literal key arguments and has no extraction path for gettext, raw HTML, concatenated output, localized JavaScript data, database-driven labels, or files outside its allowlist.

**Overall rating: High risk / incomplete coverage.** English pages generally work because English is the canonical fallback. On `zh`, `ar`, and `de` routes, the failures present as mixed-language UI, untranslated taxonomy/editorial fields, and English SEO/schema values.

## Scope and method

The review enumerated all **142 PHP files** beneath the child theme, including root templates, `parts/`, `partials/`, `inc/`, `inc/modules/`, `inc/modules/analytics/`, bootstrap code, admin code and the two underscored archive directories. The plugin implementation and tests were read to establish the actual runtime contract, rather than assuming gettext or ACF is translated automatically.

Checks performed:

- enumerated PHP files with `find ... -type f -name '*.php'`;
- searched all files for `pera_ml_*`, `get_field()`, `get_post_meta()`, `get_term_meta()`, term-name access, core post fields, raw HTML text, PHP string output and gettext output;
- compared every discovery-eligible path with `Pera_ML_Theme_UI_Discovery::approved_directories()` and its recursive exclusion behavior;
- extracted literal `pera_ml_ui(source, key)` pairs and grouped them by semantic key to find source/key conflicts;
- traced the plugin's content filters, ACF filters, approved post/property fields, taxonomy field allowlists, vocabulary behavior, UI identity algorithm and discovery parser;
- distinguished admin-only/debug/development strings from public/frontend output. Admin-only English is recorded as out of visitor scope, not reported as a production visitor defect.

### Translation contract established from the plugin

- `pera_ml_ui()` is the stored visitor-UI path and registers strings at runtime.
- Static discovery recognizes only a direct global `pera_ml_ui()` call whose **first two arguments are string literals**, whose key begins `theme.`, and whose file is in the approved scope.
- Core `the_title`, `the_content`, and `get_the_excerpt` filters translate canonical public post content on frontend requests. Direct access can evade those exact paths.
- ACF format-value filters cover only the approved names in `Pera_ML_Fields::definitions()` plus the controlled property fields.
- `pera_ml_term()` is required for translated taxonomy name/description reads; `pera_ml_term_meta()` is required for approved taxonomy metadata.
- `pera_ml_vocab()`/controlled-array ACF handling covers reviewed vocabulary, but arbitrary labels and raw `$term->name` do not inherit it.
- Missing/stale translations deliberately fall back to English. Thus helper presence means eligibility, not proof that every target-language row is populated.

## Findings

### ML-01 — Static discovery omits live frontend templates (Critical)

`Pera_ML_Theme_UI_Discovery::approved_directories()` scans `inc/`, `partials/`, and `parts/`, plus a hand-maintained root-file list. Its recursive iterator explicitly rejects any directory named `_archive`. The comment expressly says not to broaden scanning to every root PHP file.

**Omitted root PHP templates/files include:**

- `home-page-test.php`
- `page-client-forgot-password.php`
- `page-client-login.php`
- `page-client-portal.php`
- `page-portfolio-theme-token.php`
- `page-portfolio-token.php`
- `page-v2-query-test.php`
- `page-zh-citizenship.php`
- `test.php`
- `functions.php` (reasonable as bootstrap, but it can still define frontend output)

**Also omitted recursively:**

- `parts/_archive/property-card.php`
- `inc/_archive/published-term-counts.php`

The allowlist also names `archive/single-property-v2.php`, but no such PHP file exists in the audited tree. This is stale inventory evidence.

**Impact:** literal `pera_ml_ui()` calls in omitted live templates are registered only if an English request executes the exact branch. Offline/admin discovery cannot guarantee inventory completeness. Raw/gettext strings in the same files are never discovered at all. `page-client-*` is demonstrably frontend-facing, so treating all omitted files as test/dev is incorrect.

**Recommendation (not implemented):** make discovery scope derive from the complete deployed template inventory, explicitly classify exclusions, remove the blanket `_archive` exclusion, and add a CI assertion comparing all frontend PHP files against the allowlist.

### ML-02 — Client portal and authentication UI bypass Pera ML (High)

`page-client-portal.php` contains raw English for the page heading, explanatory/status copy, field labels, select options, budget labels and actions (`My Account`, `Manage your profile…`, `Your profile has been updated.`, `First name`, `Preferred contact`, `Select`, `Save profile`, `Go to favourites`, etc.). None goes through `pera_ml_ui()`.

`page-client-login.php`, `page-client-forgot-password.php`, and `page-register.php` use a mixture of raw HTML English and `__()`/`esc_html_e()` in the `hello-elementor-child` text domain. The Pera multilingual plugin does not hook gettext and static UI discovery only parses `pera_ml_ui()`, so gettext is **not** a Pera ML translation path. Examples include login state/status copy, form labels/buttons, reset instructions, registration copy, and `SINCE 2016`.

These templates are visitor-facing even though login-gated. The omission therefore produces English auth/account flows on translated routes.

**Recommendation:** migrate visitor strings to literal, semantic `pera_ml_ui()` registrations (or deliberately add a tested gettext bridge) and put all three auth/account templates into discovery scope.

### ML-03 — Test/dev templates contain extensive raw frontend English (Medium; deployment-dependent)

`home-page-test.php` contains dozens of directly rendered English strings: hero copy, filters, budget choices, CTAs, feature headings, buyer-journey steps, district descriptors and service copy. It also outputs `$term->name` directly. `page-v2-query-test.php`, `test.php`, and portfolio token/theme-token pages expose smaller amounts of frontend/debug copy.

If these templates are selectable/published in production, this becomes **High** severity. If they are strictly non-routable developer artifacts, they should be excluded by an explicit and testable classification rather than an implicit filename assumption.

### ML-04 — Taxonomy names and descriptions bypass `pera_ml_term()` (High)

Confirmed visitor-facing bypasses include:

- `home-page.php`: location filter outputs `$term->name` directly.
- `archive.php`: category cards and formatted aria labels use `$cat->name`; category descriptions are emitted directly.
- `parts/post-card.php`: category name uses `$primary_cat->name`.
- `single-post.php`: primary category and tag names use raw `->name`.
- `parts/home-featured-property.php`: region and bedroom/special values include raw term names.
- `parts/_archive/property-card.php`: bedroom name is raw.
- `single-property.php`: bedroom name is raw.
- `inc/latest-offers-card.php`: raw region/district term-name access is used while assembling card data.
- `inc/ajax-property-archive.php`: raw term names are serialized in response/filter data.
- `inc/theme-helpers.php`: `short_label` falls back to raw term name and related-property formatted strings interpolate raw district names.
- SEO/schema helpers (`inc/schema.php`, `inc/seo-property.php`, `inc/seo-property-archive.php`) repeatedly serialize raw term names into structured data, breadcrumbs, archive metadata and image alt fallback.

Some occurrences have a `function_exists('pera_ml_term')` fallback and are covered when the plugin is active; the instances listed above include unconditional raw reads or raw interpolation. Taxonomy vocabulary fallback only occurs inside `pera_ml_term()`; direct `->name` reads bypass both stored term translations and controlled vocabulary.

**Recommendation:** resolve each display term once through `pera_ml_term()` and reuse that translated value in visible text, aria labels, AJAX payloads and schema/SEO. Use `pera_ml_term($term, 'description')` for descriptions.

### ML-05 — Taxonomy metadata bypasses `pera_ml_term_meta()` (High)

`archive-property.php` directly reads and renders taxonomy metadata such as:

- `district_archive_subtitle`
- `district_archive_body`
- `archive_subtitle`
- `archive_body_content`
- `term_excerpt` / `pera_term_excerpt`
- `archive_h1_title`

Only a subset is translated by the ACF filter, and generic `get_term_meta()` reads never pass through it. More importantly, the plugin taxonomy contract approves district `district_archive_*` and region/property-tag `archive_*`, yet these direct reads do not consistently call `pera_ml_term_meta()`.

`archive.php` similarly reads category excerpt metadata directly, but category excerpt fields are **not present in the plugin taxonomy allowlist**, so there is no available stored term-meta translation contract for them. `parts/related-taxonomy-card.php` reads excerpt metadata directly and renders it. `inc/taxonomy-meta.php` public helper paths return canonical excerpt values only.

**Impact:** translated archives can show English H1 overrides, introductions, body copy and related-card excerpts; unsupported category fields cannot be generated/health-checked even if renderer calls were corrected.

**Recommendation:** route approved fields through `pera_ml_term_meta()` and extend the plugin's reviewed taxonomy contract for genuinely visitor-facing category/property metadata before using the helper.

### ML-06 — Bodrum template ACF text is outside the translated-field contract (High)

`single-bodrum-property.php` reads and renders a large dedicated `bp_*` field family directly: display title, tagline, status badge, location/configuration, intro heading/text, amenities, dual-use heading/text, operations note, CTA labels and enquiry gating note, among others.

None of those `bp_*` names appears in `Pera_ML_Fields::property_fields()`. Consequently, the plugin does not register an ACF format-value filter for them, does not include them in translation status/generation, and `get_field()` returns canonical values. `pera_ml_ui()` only covers fallback labels, not populated ACF values.

**Recommendation:** inventory scalar versus structured/media `bp_*` fields, add visitor-facing scalar/rich-text fields to the approved translation contract, and explicitly apply controlled vocabulary where amenities are enumerated.

### ML-07 — Other ACF/meta fields have partial or missing eligibility (High)

Important cases:

- `page-about-new.php` reads team `name`, `position`, and `description` with `get_field()`. The generic ACF filters do not reliably identify these relationship/repeater/current-loop contexts as approved public objects, and `position`/`description` are not page-approved fields.
- `archive-property.php` settings-page fields (`archive_h1`, subtitle/intro/bottom/CTA/WhatsApp) use an options/settings ID. `identify_acf_object()` does not support option-page IDs, so ACF format filtering cannot translate them. Passing such values through `pera_ml_field()` would also require a positive object ID and an approved post-type contract.
- `home-page.php` homepage fields (`homepage_hero_subtext`, `homepage_listing_intro`, `homepage_bottom_seo_text`, `faq`) are not in the page contract.
- `parts/home-featured-property.php` uses `project_summary` (covered for a property) but also derives content with direct `get_post_field('post_content')`; that direct call bypasses `the_content`'s exact canonical filter path.
- `parts/post-card.php` reads `post_subtitle` directly. Although legacy definitions register an ACF filter, `get_post_meta()` does not invoke ACF formatting or `pera_ml_field()`.
- Attachment alt metadata is read directly throughout property templates/SEO. Attachment alt is visitor-facing and has no defined translation/storage contract.
- `inc/v2-units-index.php` renders `v2_custom_text`; this one is eligible through the ACF filter for a property, but any direct meta/index payload (`v2_index_flat`) remains canonical structured data and must not be assumed translated.

**Recommendation:** document each visitor field's object type, storage key and rendering helper; avoid relying on ACF hooks for option IDs or `get_post_meta()` reads; explicitly translate derived excerpts and alt text if they are part of target-language output.

### ML-08 — SEO and schema output has parallel untranslated paths (High)

The plugin's content filters do not automatically cover strings manually assembled in theme SEO/schema modules. `inc/schema.php`, `inc/seo-property.php`, and `inc/seo-property-archive.php` use direct post fields, raw term names and raw metadata to form FAQ, breadcrumb, archive, offer, image-alt and property structured data.

Some approved ACF values are translated through `get_field()` filters, but direct `get_post_meta()` fallbacks are canonical, and raw term names never reach `pera_ml_term()`. This can make visible content translated while JSON-LD/Open Graph/archive metadata stays English or disagrees semantically.

**Recommendation:** establish shared translated resolvers used by HTML and SEO/schema and add target-language integration tests that assert no canonical term/meta text leaks into generated head/JSON-LD output.

### ML-09 — Semantic key collision: price sort directions (High)

`page-citizenship-properties.php` registers both `Price ↑` and `Price ↓` with the same key:

`theme.template.page_citizenship_properties.price`

`Pera_ML_UI::identity()` derives a keyed identity from the original key, not the source string. Therefore these two sources map to the same identity. Whichever registration runs last changes the registry source/hash; a stored translation for one direction becomes stale or can be shown for the other direction depending on registration/order/state.

A scanner extraction found 1,690 literal calls, 1,621 distinct keys and 56 keys reused at least once. Most reuse is legitimate same-source reuse. This price pair is the confirmed differing-source collision. The apparent apostrophe difference for `theme.contact_cta.whatsapp_message` is an extraction-escaping artifact: both PHP literals evaluate to the same source and are not a semantic collision.

**Recommendation:** use distinct keys such as `.price_ascending` and `.price_descending`; add a discovery/CI failure whenever one normalized identity has more than one evaluated source.

### ML-10 — Suspicious semantic reuse and context coupling (Medium)

Several keys are deliberately reused for identical English sources within a template (for example Gallery, Map, Location, Scroll left/right, and repeated CTA text). While not currently conflicting, a single translation is forced across noun/button/heading/aria contexts. Languages may require different morphology or phrasing by context.

Cross-file reuse such as `theme.contact_cta.whatsapp_message` also couples independent components. This is safe only while both context and canonical source remain identical.

**Recommendation:** retain reuse only for demonstrably identical grammatical context; otherwise namespace keys by component and role (`heading`, `button`, `aria_label`, `message`).

### ML-11 — Dynamic strings static discovery cannot see (High)

The static parser misses all of the following even when frontend-rendered:

- raw HTML text and PHP `echo`/concatenated strings;
- WordPress gettext (`__`, `_e`, `esc_html__`, `esc_html_e`, `esc_attr__`, `esc_attr_e`);
- database-driven ACF/meta/term values;
- interpolated term/user/property values unless their components are separately resolved;
- strings passed through arrays into partials or `wp_localize_script()`/inline scripts;
- any `pera_ml_ui()` call whose source or key is a variable/expression;
- calls with a key not beginning `theme.`;
- direct namespaced, method or static calls (by parser design);
- branch-only runtime registrations that static discovery skipped because the file is out of scope.

No variable/expression-first-argument `pera_ml_ui()` call was found by the targeted search in the current theme, which is positive. The material dynamic risk instead comes from raw/database-driven output and omitted files. Runtime registration is not a substitute: a target-language visitor may reach a string before an English execution has registered it, and background translation workflows only know registered inventory.

### ML-12 — Language-specific Chinese template is a maintenance fork (Medium)

`page-zh-citizenship.php` and Chinese partials contain hard-coded Chinese. They avoid raw English in much of their own output, but they are outside the root discovery allowlist and form a parallel, language-specific content implementation rather than the plugin's canonical-source/stored-translation model.

This creates drift risk, prevents unified missing/stale health reporting, and does not help Arabic/German coverage. Any embedded shared English labels, term values or component output can still leak.

**Recommendation:** explicitly document this as a temporary exception and test routing, or consolidate it into the canonical template plus stored translations.

### ML-13 — URL localization is inconsistent (Medium)

Many templates use hard-coded site-relative or absolute internal URLs and raw `home_url()`/`get_permalink()` output without `pera_ml_url()`. The router may filter some WordPress permalink generation, but hard-coded footer/page links and literal paths cannot be assumed language-prefixed. This can move users from translated pages back to canonical English routes.

Though URL coverage is adjacent to string coverage, it materially affects multilingual visitor flow and should be included in end-to-end coverage tests.

### ML-14 — Fallbacks hide missing plugin/translation coverage (Medium)

The common pattern `function_exists('pera_ml_term') ? ... : $term->name` is operationally resilient but silently emits English if the plugin is unavailable. Likewise every Pera ML helper intentionally returns canonical English for missing/stale storage rows. Static source review can prove routing eligibility, not translation completeness.

**Recommendation:** production monitoring/health checks should report helper fallback rates and assert the required plugin is active; target-language smoke tests should verify stored rows for critical templates.

## Positive coverage observed

- Main templates and parts have broad literal `pera_ml_ui()` adoption.
- The UI identity adds an original-key digest, preventing normalized aliases such as punctuation variants from merging accidentally.
- Core public post title/content/excerpt filters are scoped away from admin, feeds and REST.
- Property text fields and controlled arrays have explicit allowlists; facilities/target-buyer/key-advantage arrays use reviewed vocabulary rather than provider translation.
- `inc/modules/faqs.php` correctly routes taxonomy FAQ metadata through `pera_ml_term_meta()` when available.
- Many property/district/region displays correctly use `pera_ml_term()` and many internal URLs correctly use `pera_ml_url()`.
- The discovery parser tracks dynamic calls skipped, which is useful, but that counter should be surfaced as a failing audit signal rather than informational telemetry.

## Prioritized remediation plan (no fixes made)

1. **P0:** resolve the price-direction semantic key collision.
2. **P0:** bring client login/forgot-password/portal/register visitor UI into the Pera ML UI contract and discovery scope.
3. **P0:** define and translate the rendered `bp_*` field contract.
4. **P1:** remove taxonomy/meta bypasses in archives, cards, AJAX, schema and SEO; extend taxonomy allowlists where required.
5. **P1:** cover option-page/homepage/team/attachment-alt fields with explicit object/storage contracts.
6. **P1:** replace the discovery allowlist blind spots with complete inventory + explicit exclusions; add collision and unscanned-template CI checks.
7. **P2:** decide whether test/token/language-specific templates are deployable; remove, restrict, or fully cover them.
8. **P2:** unify translated URL handling and add `zh`/`ar`/`de` rendered-page plus JSON-LD regression tests.

## Acceptance criteria for a future clean audit

- Every deployed frontend PHP file is discovery-scanned or explicitly documented/tested as exempt.
- Every static visitor string is a literal semantic `pera_ml_ui()` call (or covered by an explicitly integrated alternative).
- No UI identity maps to multiple evaluated canonical sources.
- All visitor-facing post/ACF/meta fields have an approved translation contract and are read through a covered path.
- All taxonomy names/descriptions/meta use `pera_ml_term()` / `pera_ml_term_meta()`; enumerated labels use controlled vocabulary.
- AJAX, HTML, accessibility attributes, emails/messages intended for visitors, SEO metadata and JSON-LD use the same translated resolvers.
- Health inventory is complete before traffic and reports no missing/stale critical strings for `zh`, `ar`, or `de`.
- Automated rendered tests show no unexpected canonical English on representative target-language routes.

## Complete PHP inventory reviewed (142 files)

The following is the full recursive audit inventory. Inclusion here means inspected/searched; it does not imply the file renders visitor output.

- `404.php`
- `archive-property.php`
- `archive.php`
- `attachment.php`
- `footer.php`
- `functions.php`
- `header.php`
- `home-page-test.php`
- `home-page.php`
- `home.php`
- `inc/_archive/published-term-counts.php`
- `inc/access-control.php`
- `inc/acf-fields.php`
- `inc/admin/admin-menu-order.php`
- `inc/admin/dashboard-rationalization.php`
- `inc/admin/front-end-edit-tools.php`
- `inc/admin/peracrm-diagnostics.php`
- `inc/admin/posts-list-mobile-table.php`
- `inc/admin/property-latest-offers.php`
- `inc/admin/regenerate-thumbnails-menu-compat.php`
- `inc/admin/site-settings.php`
- `inc/ajax-property-archive.php`
- `inc/blog/ajax-blog-search.php`
- `inc/blog/editorial-updated-date.php`
- `inc/blog/post-archive-order.php`
- `inc/bootstrap-modules.php`
- `inc/bootstrap.php`
- `inc/bootstrap/admin.php`
- `inc/bootstrap/always.php`
- `inc/bootstrap/frontend.php`
- `inc/bootstrap/portfolio-token.php`
- `inc/bootstrap/property-archive.php`
- `inc/client-portal.php`
- `inc/crm-client-view.php`
- `inc/crm-data.php`
- `inc/disable-hello-parent-loads.php`
- `inc/district-ancestors.php`
- `inc/enquiry-email-log.php`
- `inc/enquiry.php`
- `inc/favourites.php`
- `inc/filter-for-admin-panel.php`
- `inc/form-spam-guard.php`
- `inc/home-page-test-assets.php`
- `inc/latest-offers-card.php`
- `inc/modules/admin-bar.php`
- `inc/modules/analytics.php`
- `inc/modules/analytics/admin-page.php`
- `inc/modules/analytics/admin-post-columns.php`
- `inc/modules/analytics/ahrefs.php`
- `inc/modules/analytics/bots.php`
- `inc/modules/analytics/dashboard-widget.php`
- `inc/modules/analytics/ga4.php`
- `inc/modules/analytics/install.php`
- `inc/modules/analytics/meta.php`
- `inc/modules/analytics/queries.php`
- `inc/modules/analytics/source-classification.php`
- `inc/modules/analytics/tracker.php`
- `inc/modules/enqueue-assets.php`
- `inc/modules/enquiry-loader.php`
- `inc/modules/faqs.php`
- `inc/modules/fonts.php`
- `inc/modules/frontend-admin-bar.php`
- `inc/modules/llms.php`
- `inc/modules/login.php`
- `inc/modules/misc.php`
- `inc/modules/performance.php`
- `inc/modules/property-archive-safety.php`
- `inc/modules/property-archive-settings.php`
- `inc/modules/seo-loader.php`
- `inc/modules/sitemaps.php`
- `inc/modules/svg-sprite.php`
- `inc/modules/template-routing.php`
- `inc/modules/theme-setup.php`
- `inc/modules/v2-loader.php`
- `inc/portfolio-token.php`
- `inc/property-archive-query.php`
- `inc/property-card-helpers.php`
- `inc/property-pagination.php`
- `inc/schema.php`
- `inc/seo-all.php`
- `inc/seo-helpers.php`
- `inc/seo-property-archive.php`
- `inc/seo-property-offer.php`
- `inc/seo-property.php`
- `inc/taxonomy-meta.php`
- `inc/theme-helpers.php`
- `inc/theme-modules.php`
- `inc/v2-units-index.php`
- `inc/whatsapp-click-log.php`
- `inc/whatsapp-helpers.php`
- `inc/whatsapp.php`
- `page-about-new.php`
- `page-book-a-consultancy.php`
- `page-citizenship-properties.php`
- `page-citizenship.php`
- `page-client-forgot-password.php`
- `page-client-login.php`
- `page-client-portal.php`
- `page-contact.php`
- `page-favourites.php`
- `page-join-our-team.php`
- `page-luxury-property.php`
- `page-portfolio-theme-token.php`
- `page-portfolio-token.php`
- `page-posts.php`
- `page-privacy-policy.php`
- `page-property-map.php`
- `page-register.php`
- `page-rent-with-pera.php`
- `page-sell-with-pera.php`
- `page-v2-query-test.php`
- `page-vop-besiktas.php`
- `page-zh-citizenship.php`
- `partials/citizenship-latest-offers.php`
- `partials/faq-citizenship.php`
- `partials/faq-zh-citizenship.php`
- `partials/home-latest-offers.php`
- `partials/latest-offers-card-popup.php`
- `partials/latest-offers-card.php`
- `partials/latest-offers-section.php`
- `partials/portfolio-citizenship-cta.php`
- `partials/wechat-cta-zh.php`
- `parts/_archive/property-card.php`
- `parts/about-pera.php`
- `parts/citizenship-guide-posts.php`
- `parts/contact-cta.php`
- `parts/enquiry-form.php`
- `parts/featured-apartment.php`
- `parts/featured-villa.php`
- `parts/form-sell-rent.php`
- `parts/home-editorial-posts.php`
- `parts/home-featured-property.php`
- `parts/home-special-offers.php`
- `parts/our-services-card.php`
- `parts/post-card-featured-guide.php`
- `parts/post-card.php`
- `parts/property-card-v2.php`
- `parts/related-taxonomy-card.php`
- `single-bodrum-property.php`
- `single-post.php`
- `single-property.php`
- `test.php`

## Audit limitations

This was a repository-static audit. It did not query a production database, inspect which WordPress pages currently select each template, or measure stored translation completeness. ACF data shape and actual populated option values can increase or reduce runtime exposure, but cannot eliminate the source-level bypasses and discovery gaps identified above.
