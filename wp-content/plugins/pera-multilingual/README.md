# Pera Multilingual

Standalone, server-rendered multilingual infrastructure for Pera Property. English remains the only WordPress content object; `/zh/` and `/ar/` requests resolve that same object and read structured translations from local storage.

## Repository compatibility findings

- The active child theme is template-heavy: dedicated home/page/blog/property/taxonomy templates render ACF and custom meta directly.
- `property` and its `district`, `region`, `property_type`, `property_tags`, and `special` routes are consumed by a custom archive and AJAX filter implementation. Additional portal, portfolio, CRM, and LLMS routes exist.
- Theme bootstrap conditionally loads property helpers by request path. The router therefore strips a recognized language prefix at `plugins_loaded`, lets all theme and WordPress routing see the canonical path, then restores the browser-facing URI at `parse_request`.
- SEO, Open Graph, and JSON-LD are principally theme-owned. The plugin emits reciprocal hreflang links and supports Yoast/Rank Math canonical filters without requiring either. The current theme's URI-derived canonical naturally sees the restored translated URI.
- Elementor is the parent-theme ecosystem, but the child theme is largely custom PHP. The plugin has no Elementor dependency.

## Storage

Activation creates `{prefix}pera_ml_translations`. A dedicated table is preferable to post meta because one translation can belong to a post, term, UI string, or SEO/custom field; the table provides a single indexed language/status lookup, unique structured field rows, hashes, timestamps, and provider provenance without polluting `wp_postmeta` or requiring duplicate posts.

Rows retain source and translated text. A current source SHA-256 hash permits stale detection. Saving a source object marks its rows stale but does not delete them; the last usable translation continues to render until replaced. Object-cache entries avoid repeat row queries.

## Routing

Only a recognized, enabled first path segment is removed. WordPress resolves the remaining path with its existing rewrite table, retaining pagination, searches, archives, custom routes, and query strings. The URI is restored before template rendering. No catch-all rewrite, fake page, or translated post is created. Deactivation removes all hooks and flushes rewrite rules.

The router verifies the configured WordPress home-path boundary before conversion and supports both root and subdirectory installations. Admin, admin-AJAX, REST, cron, XML-RPC, WordPress static-resource paths, authentication endpoints, and common system files are ineligible. On translated pages, safe WordPress canonical destinations are converted back into the active language rather than globally suppressing canonical redirects; exact self-redirects and unsafe destinations are rejected.

See `docs/internal-link-audit.md` for the targeted audit of filtered permalinks versus direct `home_url()`, hard-coded, filter, navigation, and AJAX URLs in the active child theme.

## Translation and providers

`Pera_ML_Provider_Interface` supports independent adapters. OpenAI and deterministic mock adapters are included. Generation is explicit through `Pera_ML_Translator::translate_and_store()`; ordinary frontend requests **never** invoke providers. Structural validation ensures protected markup/tokens retain their order.

Prefer setting secrets in `wp-config.php`:

```php
define( 'PERA_ML_OPENAI_API_KEY', '...' );
define( 'PERA_ML_OPENAI_MODEL', 'gpt-4.1-mini' );
```

The Settings page also accepts a server-side option. Never expose the key to JavaScript.

Programmatic storage/import APIs:

```php
pera_ml_store_translation( 'post', 123, 'post_title', 'zh', 'English title', '中文标题' );
$row = pera_ml_get_translation( 'post', 123, 'post_title', 'zh', 'English title' );
```

Switcher:

```php
pera_ml_language_switcher();
echo do_shortcode( '[pera_language_switcher]' );
```

## Current milestone and production limitations

Core title, excerpt, content, approved ACF/meta, and public property-taxonomy rows render when present, with safe English fallback. Arabic supplies `lang`, `dir`, body classes, and a deliberately conservative RTL stylesheet. The property field inventory and explicit per-object generation are implemented. Remaining production work includes:

1. a background/bulk queue and full translation editor (the “automatic” setting is reserved and does not schedule jobs);
2. remaining theme UI strings, Open Graph fields, and complete structured-data/SEO integration;
3. adding translated URLs to the active sitemap provider/index;
4. validating duplicate-canonical behavior against the exact production SEO plugin stack;
5. page-cache configuration varying by full language-prefixed path and cache purging after imports;
6. visual RTL QA for header/navigation, cards, specifications, forms, filters, archives, and mobile navigation.

Missing translations and provider failures always fall back to English and do not affect the canonical site.

## 0.2 content coverage

Approved ACF strings are read through `acf/format_value/name=…` filters and `pera_ml_field()`, using `meta:<field>` rows. Taxonomies use `pera_ml_term()` without cloning canonical terms. `pera_ml_vocab()` provides filterable reviewed terms, and `pera_ml_url()` safely delegates visitor URLs to the router. See `docs/property-field-inventory.md` for the audit and exclusions.

The post editor has explicit per-language generation buttons. Each request checks `edit_post` and a per-object/language nonce, regenerates core and approved scalar fields through the provider, and reports a failure count. Property archive AJAX sends a validated language plus nonce to unprefixed `admin-ajax.php`; the request-scoped router context localizes returned card permalinks.

HTML-rich sources are tokenized before provider submission and restored only when every deterministic token occurs exactly once. The OpenAI adapter uses the Responses API, handles rate limiting and malformed output without logging response bodies, and keeps constant-based credentials/model configuration authoritative.
