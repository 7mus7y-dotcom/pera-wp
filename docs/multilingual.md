# Pera Multilingual

Scope: `wp-content/plugins/pera-multilingual/`. The plugin provides server-rendered, stored translations; public rendering must never trigger a translation provider.

## Storage and routing

`Pera_ML_Storage` stores one row per object/type/field/language in `{prefix}pera_ml_translations`, including the canonical source text/hash, translated text, status, provider, and timestamps. A changed source makes the row stale; readers return canonical English unless the stored row is current, non-empty, and hash-matched. Structured keys retain namespaces such as `meta:seo_title`.

English is unprefixed; currently registered translated prefixes are `/de/`, `/zh/`, and `/ar/` (enabled languages remain option/filter controlled). `class-router.php` temporarily strips the prefix before WordPress parsing, restores the public URI/query context, protects the translated front page, preserves prefixes through canonical redirects, and localizes standard WordPress links. Admin, AJAX, REST, cron, system paths, assets, external hosts, fragments, and `mailto:`, `tel:`, and `javascript:` links are excluded.

Use `pera_ml_url()`/`pera_ml_home_url()` for internal visitor links that WordPress link filters do not produce. Never prefix external, admin, API, asset, token-bearing operational, or non-HTTP links by string concatenation.

## Field classifications

- Scalar post/page/property/team text has explicit allowlists in `Pera_ML_Fields::definitions()`/`property_fields()`.
- Controlled property arrays (`facilities`, `target_buyer_type`, `property_key_advantages`) use vocabulary translation, not arbitrary structured translation.
- Repeater/structured numeric/media/relationship data—including `v2_units`—is not translated. Only explicitly modelled text leaves such as homepage FAQ question/answer keys are stored.
- Supported taxonomy text is explicit for `district`, `region`, `property_type`, `property_tags`, `special`, `category`, and `post_tag`; taxonomy metadata is allowlisted per taxonomy. Media/relationship fields are excluded.
- ACF display filters read the raw canonical source before applying normal ACF formatting to the translated value. Preserve that order to avoid false staleness or broken rich HTML.

For property listing/data rules see [property-cpt.md](property-cpt.md). `project_name` is translatable as a property field, but remains the only field in which the project name may be entered.

## UI strings and admin workflow

Wrap stable visitor copy in `pera_ml_ui($source, $semantic_key)`; the registry records canonical source without generating on a public request. Menus, template discovery, vocabulary, content filters, and SEO each have dedicated classes—use their APIs instead of reading the translations table directly.

Translations are generated/imported from explicit admin/offline workflows. `admin/class-admin.php`, `class-ajax.php`, translation status/health classes, and `admin/translation-*.js` own authorization, queues, status, and review. Require an appropriate capability and nonce for mutations; keep provider settings/credentials out of code and docs. After canonical content changes, expect prior rows to be stale until reviewed/regenerated.

## Tests

Tests are standalone PHP scripts in `wp-content/plugins/pera-multilingual/tests/`. Run the narrow contract set for the change, for example:

```sh
php wp-content/plugins/pera-multilingual/tests/router-test.php
php wp-content/plugins/pera-multilingual/tests/storage-test.php
php wp-content/plugins/pera-multilingual/tests/property-translation-test.php
php wp-content/plugins/pera-multilingual/tests/taxonomy-term-admin-test.php
php wp-content/plugins/pera-multilingual/tests/ui-string-test.php
php wp-content/plugins/pera-multilingual/tests/translation-health-test.php
```

For theme integration, also run the relevant `homepage-multilingual-routing`, property-template, URL-routing, and visitor-link scripts under the theme `tests/`. Manually compare English and every enabled prefix, including Arabic RTL, canonical/hreflang/head output, menus, internal links, stale fallback, and a missing translation.
