# Pera Multilingual: new-language translation guide

This is the operational guide for an LLM or developer adding and filling a new target language in Pera Property.

It documents the current architecture. Do not infer translation coverage from what is visible in WordPress alone: visitor copy comes from several distinct source contracts.

## 1. Architecture and source of truth

English is canonical. Translated routes resolve the same WordPress objects and read stored translations from `{prefix}pera_ml_translations`. Frontend rendering must never call a translation provider.

The main contracts are:

- `includes/class-language-registry.php` — enabled languages, prefixes, direction, hreflang and language-specific instructions.
- `includes/class-translation-status.php` — canonical post/page/CPT source inventory.
- `includes/class-fields.php` — approved ACF/meta and taxonomy field contracts.
- `includes/class-translation-health.php` — site-wide Missing/Stale/Current inventory.
- `includes/class-translation-health-orchestrator.php` — translates one server-approved Health row.
- `includes/class-ui.php` and `class-ui-registry.php` — stored template/interface strings.
- `includes/class-theme-ui-discovery.php` — deterministic discovery of literal theme `pera_ml_ui()` registrations.
- `includes/class-vocabulary.php` — reviewed controlled vocabulary.
- `tools/pera-translate-latest.php` — preferred CLI for Health-driven bulk/targeted generation.
- `tools/register-theme-ui-strings.php` — discovers literal `pera_ml_ui()` calls in approved theme files.

Translation Health is the authoritative operational inventory for stored translatable rows. A clean Health queue does **not** prove that every visible English literal in PHP has been instrumented.

## 2. Before adding a new language

Audit the registry and all language-specific assumptions before generating content.

A new language normally requires review of:

1. `class-language-registry.php`: code, English/native/compact names, URL prefix, LTR/RTL direction, hreflang and provider instructions.
2. Any public-launch gate. A language may be internally enabled for generation while intentionally absent from selectors/hreflang.
3. `class-vocabulary.php`: every controlled property/facility term needs the new language if that vocabulary is expected to render translated.
4. Provider validation: target-script/source-echo checks and language-specific translation instructions.
5. Date/month formatting and other locale-specific presentation.
6. RTL assets/layout when the new language is RTL.
7. Tests for routing, selector, hreflang, provider validation, vocabulary, dates and public gating.

Do not make a language publicly discoverable merely because translations are being generated.

## 3. Content classes

### A. WordPress static pages

Translation Health uses:

`object_type=page`

The canonical page contract includes non-empty/required core fields such as `post_content`, `post_title`, `post_excerpt`, plus approved page meta including SEO fields and supported structured FAQ/homepage fields.

Preview one page:

```bash
wp eval-file wp-content/plugins/pera-multilingual/tools/pera-translate-latest.php dry-run status=missing object_type=page object_id=PAGE_ID language=LANG
```

Translate it:

```bash
wp eval-file wp-content/plugins/pera-multilingual/tools/pera-translate-latest.php status=missing object_type=page object_id=PAGE_ID language=LANG
```

Important: a WordPress Page can use a custom PHP template whose visible body is not stored in `post_content`. In that case the page command only translates the page object's own contract. Template copy belongs to the UI-string layer described below.

Example: `page-citizenship.php` contains extensive template copy wrapped in `pera_ml_ui()`; that copy is UI inventory, not `object_type=page`.

### B. Blog posts

Use:

`object_type=post`

```bash
wp eval-file wp-content/plugins/pera-multilingual/tools/pera-translate-latest.php dry-run status=missing object_type=post language=LANG
```

The post contract includes core content/title/excerpt and approved SEO/FAQ/meta fields.

### C. Property CPT

Use:

`object_type=property`

```bash
wp eval-file wp-content/plugins/pera-multilingual/tools/pera-translate-latest.php dry-run status=missing object_type=property language=LANG
```

For one property:

```bash
wp eval-file wp-content/plugins/pera-multilingual/tools/pera-translate-latest.php dry-run status=missing object_type=property object_id=PROPERTY_ID language=LANG
```

Property scalar ACF/meta coverage is defined explicitly in `Pera_ML_Fields::property_fields()`. Do not infer arbitrary ACF fields into the provider contract.

Controlled arrays such as facilities, target buyer type and property advantages use `pera_ml_vocab()` / the reviewed vocabulary rather than provider-generated per-object rows.

### D. Team CPT

Translation Health includes `team` objects. Current approved human-readable Team metadata is deliberately narrow and is defined by `Pera_ML_Fields::team_fields()`.

Use:

```bash
wp eval-file wp-content/plugins/pera-multilingual/tools/pera-translate-latest.php dry-run status=missing object_type=team language=LANG
```

Do not broaden Team biography/profile coverage without first changing the explicit field contract and tests.

### E. Taxonomies

Supported taxonomies are defined by `Pera_ML_Fields::supported_taxonomies()`. Current contracts include:

- `district`
- `region`
- `property_type`
- `property_tags`
- `special`
- `category`
- `post_tag`

Health exposes them as:

`object_type=taxonomy:TAXONOMY`

Example:

```bash
wp eval-file wp-content/plugins/pera-multilingual/tools/pera-translate-latest.php dry-run status=missing object_type=taxonomy:category language=LANG
```

Taxonomy fields are explicit in `Pera_ML_Fields::taxonomy_fields()`. They include canonical term name/description plus approved taxonomy-specific SEO/archive ACF meta. Relationship IDs and media fields must not be sent to the provider.

### F. Theme templates, partials, parts and inc files

Visitor-facing PHP literals do **not** automatically become page/post translations.

Theme/interface copy must be wrapped in:

```php
pera_ml_ui( 'Canonical English source', 'stable.semantic.key' )
```

The deterministic discovery tool scans the approved child-theme inventory from `Pera_ML_Theme_UI_Discovery::approved_directories()`. This includes the reviewed root templates and recursively scans:

- `inc/`
- `partials/`
- `parts/`

Run discovery before translating a new language:

```bash
wp eval-file wp-content/plugins/pera-multilingual/tools/register-theme-ui-strings.php -- --dry-run
```

Then register discovered strings:

```bash
wp eval-file wp-content/plugins/pera-multilingual/tools/register-theme-ui-strings.php
```

A healthy run should have zero unexpected dynamic/non-literal calls and, after registration, zero newly discovered/source-changed strings on a repeat run.

After registration, UI rows appear in Translation Health with `object_type=ui` and can be translated through `pera-translate-latest.php` using `language=LANG`.

**Critical limitation:** discovery finds explicit literal `pera_ml_ui()` calls. It does not magically detect arbitrary English text. Raw literals, arrays echoed directly, dynamically assembled strings, or files outside the approved discovery inventory can remain English while Translation Health reports no missing row.

Therefore every new-language rollout must include a source audit for raw visitor-facing English in templates, partials, parts and inc files.

### G. Menus and URLs

Menu labels and visitor-facing internal URLs have separate rendering/localization behavior. Do not duplicate WordPress objects per language.

Use the existing helpers:

- `pera_ml_url()`
- `pera_ml_home_url()`
- multilingual menu handling in the plugin

Review `docs/internal-link-audit.md` when auditing links. A translated string is not sufficient if its link drops the active language prefix.

### H. SEO and structured data

Do not assume translating visible body copy covers SEO. Verify the approved SEO fields, canonical/hreflang output, Open Graph/schema paths and taxonomy SEO fields.

For each new language, check rendered:

- title
- meta description
- canonical
- reciprocal hreflang
- `x-default`
- structured FAQ/schema where applicable

## 4. Preferred CLI

`tools/pera-translate-latest.php` is the preferred Health-driven CLI.

Supported filters:

- `dry-run`
- `status=missing|stale|all`
- `object_type=VALUE`
- `object_id=ID`
- `field=VALUE`
- `language=CODE`
- `limit=N` (maximum 5000)

Examples:

```bash
# Everything incomplete for one target language.
wp eval-file wp-content/plugins/pera-multilingual/tools/pera-translate-latest.php dry-run language=LANG limit=500

# Missing only.
wp eval-file wp-content/plugins/pera-multilingual/tools/pera-translate-latest.php dry-run status=missing language=LANG limit=500

# Stale only.
wp eval-file wp-content/plugins/pera-multilingual/tools/pera-translate-latest.php dry-run status=stale language=LANG limit=500

# Translate missing rows.
wp eval-file wp-content/plugins/pera-multilingual/tools/pera-translate-latest.php status=missing language=LANG limit=500
```

Always dry-run a newly scoped batch first. Live mode counts successful translations toward `limit`, stops after 10 errors, and flushes the WordPress cache when complete.

The language filter accepts only an enabled, non-source language. English is canonical and cannot be targeted.

## 5. New-language rollout sequence

Use this order:

1. Audit/add the language registry entry and language-specific code support.
2. Keep the language publicly gated if translations are not launch-ready.
3. Complete controlled vocabulary for the language.
4. Run the theme UI discovery dry-run.
5. Register any newly discovered/current-source-changed UI strings.
6. Audit approved theme templates plus `inc/`, `partials/` and `parts/` for raw visitor-facing English that bypasses `pera_ml_ui()`.
7. Fix instrumentation gaps before declaring Health authoritative for that surface.
8. Dry-run Translation Health for the new language site-wide.
9. Translate in controlled batches, preferably by object class: pages, posts, properties, Team, taxonomies, then UI.
10. Re-run missing and stale queues until clean.
11. Re-run theme UI discovery; it should be clean.
12. QA representative translated routes and all special templates.
13. Verify SEO/canonical/hreflang/schema and internal-link language preservation.
14. Perform RTL visual QA if applicable.
15. Only then remove any public-launch gate.

## 6. Auditing for coverage gaps

Translation Health answers: "Which rows in the approved contracts are missing or stale?"

It does **not** answer: "Has every English visitor-facing source in the codebase been placed into an approved contract?"

For the latter, audit the code.

At minimum inspect:

- root visitor-facing PHP templates approved by `class-theme-ui-discovery.php`
- `inc/**/*.php`
- `partials/**/*.php`
- `parts/**/*.php`
- CPT and taxonomy renderers
- AJAX-rendered fragments
- menu labels
- SEO/schema generators
- form labels, placeholders, aria labels and validation messages
- WhatsApp/email prefill copy
- hard-coded internal URLs
- PHP arrays whose values are echoed later

Classify every visitor-facing English source as one of:

1. structured post/page/CPT field;
2. approved ACF/meta field;
3. taxonomy field/meta;
4. `pera_ml_ui()` UI string;
5. reviewed `pera_ml_vocab()` controlled value;
6. intentionally non-translatable;
7. defect: raw/uncovered visitor-facing copy.

Do not solve category 7 by manually inserting translation-table rows. Instrument the canonical source first so future languages and stale detection work correctly.

## 7. Known project exclusions / cautions

Preserve deliberate project scope unless the project owner changes it:

- media/attachment/gallery/floor-plan ALT metadata is excluded from Translation Health/contracts;
- Bodrum `bp_*` content is pending deletion and should not drive translation expansion;
- addresses are deferred;
- broader Team biography/About coverage is deferred;
- old hard-coded language-specific templates should not become the model for new language support.

Also distinguish source copy from data/IDs. Media IDs, relationship IDs, prices, coordinates and similar structured values are not provider translation inputs.

## 8. Validation after a batch

After translating a scope, repeat the exact dry-run. It should return no incomplete translations for that scope.

Example:

```bash
wp eval-file wp-content/plugins/pera-multilingual/tools/pera-translate-latest.php dry-run status=missing object_type=page object_id=PAGE_ID language=LANG
wp eval-file wp-content/plugins/pera-multilingual/tools/pera-translate-latest.php dry-run status=stale object_type=page object_id=PAGE_ID language=LANG
```

Then inspect the actual translated frontend. English fallback is intentionally safe, so a page can look valid while still containing untranslated strings.

## 9. Rule for future LLMs

Do not start a new-language rollout by translating arbitrary database rows.

First establish the canonical source contract and how that source reaches the frontend. Use Translation Health and the existing orchestrator for approved structured rows, UI discovery plus `pera_ml_ui()` for template/interface copy, and reviewed vocabulary for controlled values.

When a visible English string is absent from Health, determine why. The correct fix is usually to instrument or extend the source contract, not to bypass it.
