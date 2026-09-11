# Pera Currency: current-architecture audit and implementation specification

**Audit date:** 2026-09-02

**Scope:** current repository checkout; architecture only. No currency plugin exists and no production change is part of this audit.

**Status:** this document supersedes the earlier version of this file. It is a design input, not evidence that the design has been implemented.

## 1. Decision summary

The original invariant survives the fresh audit: **property inventory, derived metadata, filtering, sorting, and structured commercial data remain USD**. Conversion belongs only at an explicitly marked visitor-facing presentation boundary. In particular, `v2_units[*].v2_price_usd_min/max` is canonical source data and post-level `v2_price_usd_min/max` is its derived USD query index. Neither is a translation nor a display-currency value.

Language and currency are independent axes:

```
canonical WordPress object + route language (en|de|ar|zh) + display currency (USD|GBP|EUR|TRY)
```

Thus `/de/property/example/?min_price=300000` means German copy, a canonical USD 300,000 lower bound, and whichever display currency the visitor selected. A language link changes only the first axis; a currency control changes only the third. There is no language-to-currency mapping or geolocation in V1.

The safest cache architecture is a **canonical-USD HTML shell plus client rehydration**:

* SSR always emits usable USD text and immutable raw USD values on approved price nodes.
* Pera Currency's small browser module reads its own first-party preference, converts from raw USD exactly once, and updates marked text without changing URLs or language.
* AJAX requests explicitly carry independently validated `pera_ml_lang` and `display_currency`; the currency value is presentation context only. Responses should still contain raw USD attributes so a cached/stale response can be reconciled in the browser.
* Schema, Open Graph/SEO price fragments, canonical links, and `hreflang` never depend on a visitor cookie or local storage.

This avoids four-way page-cache fragmentation, prevents one visitor's currency leaking into another visitor's HTML, preserves useful output without JavaScript, and lets either plugin be deactivated independently. A brief USD-to-selected-currency change after paint is preferable to cache corruption; CSS may reserve stable width, but must not hide prices pending JS.

## 2. Current Pera Multilingual architecture

### 2.1 Executable behavior

Pera Multilingual 0.2.0 is a standalone, stored, server-rendered overlay. English remains the only WordPress object. Its router detects an enabled leading `de`, `ar`, or `zh` segment at `plugins_loaded`, strips it before WordPress/theme routing, and restores the public request URI at `parse_request`. Query strings are not reinterpreted. English has no prefix. System/admin/REST/AJAX paths are excluded.

The active language is request-scoped, not WordPress-locale-scoped. `language_attributes` is replaced with the plugin language and direction, body classes identify the language/RTL state, and Arabic loads `assets/css/rtl.css`; the plugin does **not** call `switch_to_locale()`. Therefore `number_format_i18n()` still follows the installed WordPress locale and is not a dependable de/ar/zh monetary formatter.

Translations live in `{prefix}pera_ml_translations`, keyed by object, field, language, source hash and status. Reads never call a provider. Missing/stale rows fall back to canonical English. Core title/content/excerpt filters, approved ACF format-value filters, term helpers, a reviewed vocabulary, and UI-string storage supply SSR translations while retaining canonical posts, terms and ACF values.

Current public entry points are:

| API | Supported use |
|---|---|
| `pera_ml_current_language()` | Read request language. Theme may use this for independently localized configuration. Currency core does not need it. |
| `pera_ml_ui($source, $key, $language = null)` | Read/register stored visitor UI copy with English fallback. |
| `pera_ml_field(...)`, `pera_ml_term(...)`, `pera_ml_term_meta(...)`, `pera_ml_vocab(...)` | Translate approved property/content semantics without provider traffic. Currency must not call these. |
| `pera_ml_url($url, $language = null)`, `pera_ml_home_url(...)` | Produce route-preserving localized URLs. |
| `pera_ml_language_switcher(...)` | Generic switcher output. The theme currently owns its more tailored header switcher. |

`Pera_ML_Plugin::instance()->router()/registry()` are public methods in code, but the theme header currently reaches into those objects. For new cross-plugin work, prefer the procedural API above. A missing seam is a public `pera_ml_url_for_language()`/enabled-language accessor; that is a multilingual architecture issue, not permission for Pera Currency to inspect classes or translation tables.

### 2.2 Content, navigation, AJAX and SEO coverage

* **Canonical objects/storage:** one English post/term/ACF source; structured translations are separate rows. Numeric `v2_units`, `price_list_kd`, IDs, URLs and canonical checkboxes are intentionally excluded from translation fields.
* **Templates:** normal theme templates render the same canonical object. Translated core values arrive through filters; direct ACF/meta needs an approved filter or an explicit `pera_ml_*` helper. Theme UI is extensively wrapped in `pera_ml_ui()`.
* **Taxonomies/archives/cards:** terms remain canonical; labels/descriptions are translated through term/vocabulary helpers. The shared property card uses translated titles/locations because the AJAX language context is set before the theme AJAX callback renders it.
* **Homepage/singles:** core and approved ACF fields are translated server-side. Pricing numeric fields remain untouched, correctly.
* **Header/navigation:** `header.php` places a compact desktop language disclosure before admin/CRM/search/menu icons and a full mobile language list near the top of the off-canvas menu. Menu objects are cloned and translated; internal URLs are language-localized.
* **Language switching:** the header builds the destination from the current request URI and `url_for_language()`, so path and query string survive. A currency stored outside the URL naturally survives too.
* **AJAX:** archive JS posts `pera_ml_lang` plus `pera_ml_nonce` to canonical `admin-ajax.php`. Pera Multilingual's priority-zero handler validates the nonce/language and sets request-scoped router state before the theme's `pera_filter_properties_v2` callback. Returned card HTML is therefore translated and its permalink localized.
* **SEO:** the plugin emits reciprocal alternates plus `x-default`, supplies a translated singular document title when stored, and overrides Rank Math canonical on translated routes. The theme owns most metadata/JSON-LD. Currency must not modify any of those URLs or outputs.
* **JavaScript/dynamic HTML:** translation is primarily SSR. JS strings embedded by templates must be supplied translated by PHP. AJAX-inserted card HTML inherits the explicit request language; merely changing DOM text client-side is not a translation strategy.
* **RTL:** Arabic sets `dir=rtl` and conservative RTL CSS. Monetary nodes should use `dir="ltr"`/`unicode-bidi:isolate` so a leading symbol and ASCII digits retain order inside Arabic prose.
* **Caching:** language is safely represented by a distinct path, but repository docs still call production page-cache rules and purge-on-import operational work. No repository-wide page-cache configuration proves cookie variation. Currency design must consequently assume that public HTML may be cached by URL alone.

### 2.3 Composition and dependency graph

The safe graph is not a plugin chain:

```
Pera Multilingual ── public language/text/URL API ─┐
                                                   ├─ Theme/property presentation
Theme property domain ─ canonical USD semantics ──┤
                                                   └─ Pera Currency public money API
Pera Currency ─ FX, preference, conversion, formatting (no ML/ACF/taxonomy/CRM knowledge)
```

Both plugins depend only on WordPress. The **theme composes** translated semantic text (`From`, `Price`, accessibility copy), property-domain decisions (project/resale, selected unit, range), and currency formatting. Pera Multilingual must not know FX providers; Pera Currency must not know translations or property storage. Optional calls are always guarded with `function_exists()` and canonical fallbacks.

## 3. Current price model

### 3.1 Field classification

| Data | Class | Current meaning | Currency rule |
|---|---|---|---|
| `v2_units` | canonical | Active ACF unit rows; bedroom, size, availability and USD min/max per row. | Never convert in storage. |
| `v2_units[*].v2_price_usd_min/max` | canonical | USD numeric source. Missing max falls back to min. | Input to display conversion only. |
| post meta `v2_price_usd_min/max` | derived + query/index | Rebuilt on ACF save; global bounds, overlap filter and price sort use it. | Always USD; never visitor-specific. |
| `pera_v2_units_aggregate*()` results `price_min/max` | derived domain | Request-time aggregation, optionally by selected bedrooms/unit. | Still numeric USD. |
| `pera_v2_units_get_display_data()` result | mixed view model | Selected row, aggregates, `price_min/max`, and preformatted `price_text`. | Numeric members stay USD; `price_text` is the main seam to replace/deprecate in favor of late formatting. |
| `price_text` in map/card contexts | display | Already formatted `$...`/`From $...`; not safe to reconvert. | Emit raw USD pair plus format at the last boundary. |
| archive `min_price/max_price`, slider bounds and history | query/index | Integer GET/AJAX USD bounds with range-overlap SQL. | Always canonical USD in every language/currency. |
| map marker `price_min/max` | canonical client dataset | USD used for client filtering. | Keep USD. Add/display a separately formatted label. |
| legacy archive `parts/_archive/property-card.php` source | legacy display | Legacy `price_usd` path and hard-coded `From $`. | Convert only if runtime use is confirmed; do not make it canonical. |
| `pera_latest_offers_*`, portal unit currencies, CRM deal/commission currencies | separate business data | Offer imports and explicitly currency-tagged commercial records. | Outside V1; values are not necessarily USD and must never be blindly converted. |
| citizenship USD 400,000/500,000, budget examples | editorial/legal | Regulatory threshold or prose/form example. | Never automatic property-price conversion. |
| JSON-LD Offer `price`/`priceCurrency` | SEO | Deterministic offer derived from USD unit data. | Keep numeric USD and `USD`. |

The active V2 implementation confirms USD is universal **for property inventory/search**. This does not establish that CRM deals, portal units, latest offers, user-entered budgets, or editorial numbers are USD-convertible domains. They remain explicitly excluded from V1.

### 3.2 Hard-coded presentation classification

* **A — property display targets:** `$` in `pera_v2_units_format_price_text()`, the single-property hero, shared V2 card, home featured card, map `price_text`, archive slider summary, unit-range table, and legacy card if live.
* **B — query/index (retain USD):** V2 index fields and reindexer; bounds transient; archive query builder/sort; hidden `min_price/max_price`; homepage budget presets; map raw values/filter comparisons.
* **C — SEO/schema (retain USD):** `inc/seo-property-offer.php` and price-bearing theme SEO helpers. SEO prose constructed from `price_text` should use a dedicated canonical-USD formatter rather than a visitor formatter.
* **D — editorial/legal (retain authored text):** citizenship pages and schema FAQs containing USD 400,000/500,000; `e.g. $350,000`; generic copy mentioning price. Translation token protection already preserves monetary tokens.
* **E — business/CRM/latest-offer:** `inc/latest-offers-card.php`, portfolio/client pages, Pera Portal's explicit per-unit currency, and Peracrm deal/commission columns. Exclude until a separate source-currency contract is approved.

## 4. Rendering-surface inventory

| Surface | File/function | Numeric source + current formatter | Mode / multilingual effect | V1 currency and plugin-off behavior |
|---|---|---|---|---|
| Main/taxonomy property archives | `archive-property.php`; main loop | indexed/aggregated V2 USD; shared card; slider uses `$` + `number_format_i18n()` | SSR; Pera ML translates archive copy/terms/card text and localizes links | Convert marked card/visible slider labels only. Hidden/GET values stay USD. Currency off = USD; ML off = English. |
| Archive AJAX results | `inc/ajax-property-archive.php` → `parts/property-card-v2.php` | canonical V2 rows → aggregate → `pera_v2_units_format_price_text()` | Server HTML inserted by JS; Pera ML priority-zero handler supplies language | Add validated display context/raw attributes, but filter remains USD. Missing context defaults USD; ML absence must not affect currency. |
| Shared cards, related, homepage grids, favourites where template is reused | `parts/property-card-v2.php` | V2 unit rows/derived index → shared formatter | SSR or AJAX; title, term and UI semantics translated | Theme passes USD numbers to currency API; browser rehydrates raw data. Each optional plugin falls back independently. |
| Single-property hero | `single-property.php` | selected/aggregate V2 USD; direct `$` + `number_format_i18n()` | SSR; translated content and semantic labels | Replace direct symbol boundary with wrapper; project/resale and selected-unit logic stays theme-owned. |
| Single-property unit table / selected units | `inc/v2-units-index.php` range renderer and `single-property.php` | row USD min/max → shared formatter | SSR; `Price (USD)` and `From` currently use `pera_ml_ui()` | Price numbers converted; label becomes translated neutral `Price` or selected code. Never mutate rows. |
| Homepage featured apartment/villa | `parts/home-featured-property.php`, `parts/featured-*.php` | display-data `price_text` | SSR; title/location/UI translated | Prefer numeric USD view model + common formatter; fallback USD. |
| Homepage search budget | `home-page.php` | preset values → hidden `min_price/max_price` | SSR + JS; label translated | Visible selected range converts; form submits original USD. No drift: always derive from stored canonical endpoints. |
| Property Map marker/overlay/list | `page-property-map.php`, `js/property-map.js` | marker raw USD min/max plus formatted `price_text` | PHP JSON + JS filter/overlay; titles/terms/UI translated in PHP | Keep raw values/filter input contract USD. Add raw attributes and deterministic JS formatter for visible overlay/list labels. |
| Property Map selected AJAX card | map JS `loadMarkerCard()` → archive AJAX action/shared card | canonical property ID and V2 USD | Explicit language/nonce already posted; HTML inserted | Post currency independently, validate, emit raw USD. Absent/invalid = USD. |
| Luxury/citizenship property pages | `page-luxury-property.php`, `page-citizenship-properties.php` | any listing cards use shared V2 card; prose thresholds authored | SSR and shared cards; Pera ML translates copy | Convert genuine listing cards only; never convert editorial/legal thresholds. |
| Protected/client property views | `page-client-portal.php`, favourites/portfolio templates | mixed property cards, budgets, offers | Generally no-cache/protected; multilingual coverage varies | Shared canonical property cards may convert; user budgets, portal/CRM/offer data stay separate. |
| Latest-offer cards/popups | `inc/latest-offers-card.php`, `partials/latest-offers-*` | imported list/cash values → `$` formatter | SSR/map popup; UI text translated | Explicitly out of V1: add source-currency model first. |
| Property schema/SEO | `inc/seo-property-offer.php`, `inc/seo-property.php`, `inc/seo-all.php` | canonical V2 USD | SSR metadata; translated title/description may vary | Always USD and cache deterministic, irrespective of displayed currency. |

No standalone `single-bodrum-property.php` monetary hit was found in the current search; it should receive regression coverage, not speculative integration. The disabled/test `home-page-test.php` and dormant legacy/special-offer templates are inventory items, not automatic production targets.

## 5. Archive contract and end-to-end flow

Current flow:

```
GET /{lang?}/property/?min_price=300000&max_price=600000
  -> archive sanitizes absint values
  -> shared query builder compares v2_price_usd_max >= min and v2_price_usd_min <= max
  -> price sort uses v2_price_usd_min
  -> SSR card template
  -> on interaction JS serializes same canonical values and Pera ML language+nonce
  -> admin-ajax.php
  -> Pera ML validates/sets language
  -> theme callback rebuilds same WP_Query and renders shared card HTML
  -> JS inserts results and updates history/query string
```

Required URL invariant:

* `min_price`, `max_price` are non-negative integer **USD** amounts everywhere.
* Search/sort/pagination parameters keep their existing meaning on `/property/`, `/de/property/`, `/ar/property/`, and `/zh/property/`.
* A language switch preserves query parameters via the router. A currency switch must not navigate and must not rewrite them.
* Currency preference does not belong in the canonical URL in V1. Do not introduce `?currency=` on normal links; if accepted as a one-time share/selection input later, strip it from canonical/hreflang and redirect or normalize it after persisting.
* Filtered archive URLs remain governed by current theme noindex/canonical policy. Currency never creates a new indexable facet.

### Filter UX contract

Maintain canonical values separately from display strings. For a USD 300,000–600,000 selection and rate 0.86, EUR labels may show `€258,000–€516,000`, while range input values, hidden fields, GET, AJAX, history, pagination and query builder remain `300000/600000`. On every currency change calculate from those original USD endpoints—never parse rendered text and never convert already converted values. The same applies to global bounds and map filters.

Because native `<input type=number>` cannot display a currency-formatted localized string while retaining a different submitted value, keep its numeric model explicitly USD in V1 and put converted values in adjacent output labels. Do not relabel an unchanged USD input as though its typed number were EUR. A future true display-currency input would require inverse conversion, rate-snapshot disclosure and URL normalization and is not recommended for V1.

### AJAX composition

Use two independent fields:

```
pera_ml_lang=de            # owned/validated by Pera Multilingual
display_currency=EUR       # owned/validated by Pera Currency
min_price=300000           # owned by theme query; always USD
max_price=600000           # owned by theme query; always USD
```

The explicit currency field is useful for deterministic fragment rendering and does not rely on cookie forwarding/caching. It must be allowlisted independently (`USD|GBP|EUR|TRY`), must not alter query construction, and defaults to USD. A Pera Currency nonce is optional for a read-only allowlisted display hint; the existing archive nonce still protects the action. The response should include raw USD attributes and a rate-snapshot/version so current-page JS can reconcile it. If fragment caching is ever added, cache canonical USD HTML or vary the fragment key by validated currency; never cache selected output under property/language alone.

## 6. Property Map design

Current PHP creates one JSON marker object per geocoded property. It includes canonical `price_min/price_max`, a preformatted `price_text`, translated title/district/type and localized URL. JS filters the marker array locally using numeric min/max, creates map markers/overlays, and requests a shared property card on selection. The map's form labels are `pera_ml_ui()` strings, while min/max are currently raw numeric fields.

Required composition:

1. Keep marker `price_min/price_max` and all comparisons USD.
2. Stop treating `price_text` as the sole truth. Provide raw min/max plus project/resale/range semantics, or a server-rendered USD fallback and raw values.
3. Format overlay/list-visible prices through the same JS contract and rate snapshot as the rest of the page.
4. Send `pera_ml_lang` exactly as today and independent `display_currency` on selected-card AJAX. Never infer one from the other.
5. Keep translated title/terms/UI and localized property URL supplied by Pera ML/theme. Currency JS changes only money nodes.
6. Give monetary spans LTR isolation under Arabic. Logical CSS properties should be used for selector/price layout.
7. Map min/max number controls retain explicit USD meaning; adjacent converted summaries may reflect selection.

## 7. Single-property responsibility seam

The current helper correctly owns unit selection and aggregation but mixes domain data and formatting in `price_text`. Split conceptually without moving domain rules into the plugin:

```
Theme: select unit -> aggregate USD min/max -> decide project/resale + From/range
Pera ML/theme: translate "From", "Price", accessibility prose
Pera Currency: convert USD number(s) -> round -> format symbol/code and digits
Theme: compose translated semantic token + formatted monetary token
```

`pera_v2_units_aggregate()` and the indexer must remain unchanged in meaning. Prefer formatting immediately before output through guarded theme wrappers rather than making low-level aggregation depend on the plugin. `pera_v2_units_format_price_text()` may become the theme-owned compatibility wrapper: it decides `From`/range and calls Pera Currency if present, otherwise its present USD formatter. Do not pass translated prose into Pera Currency and do not let Pera Currency query ACF.

## 8. Formatting contract

### 8.1 Product decision

Use one deliberately uniform international-property style across all four languages:

| Currency | Example |
|---|---|
| USD | `$450,000` |
| GBP | `£337,000` |
| EUR | `€390,000` |
| TRY | `₺18,500,000` |

Use prefix symbols, ASCII Western digits, comma thousands grouping, no decimals and no locale-dependent spaces. German therefore intentionally displays `€390,000`, not `390.000 €`; Arabic also uses Western digits, matching current numeric property/UI conventions and avoiding PHP/JS locale divergence. Wrap the complete amount in `<bdi dir="ltr">` (or equivalent isolated span) on all languages.

This is a site-specific display contract, not a claim about each language's native accounting convention. It optimizes rapid cross-language listing comparison, international-buyer familiarity, deterministic snapshots, and identical SSR/JS output. If product later chooses native locale conventions, define an explicit per-language table and implement it identically in PHP/JS; do not delegate to ambient server/browser locale.

### 8.2 Rounding and deterministic algorithm

* USD is never converted or rounded beyond its canonical whole-unit display.
* For FX currencies, compute `amount_usd * quote_per_usd`, then round to the nearest **1,000 units**, half up. Property prices are indicative and rates fluctuate; nearest 100 creates false precision and whole units add noise, especially for TRY.
* For positive non-zero amounts that round to zero, display the nearest whole unit rather than zero (not expected for property data).
* Range endpoints are converted independently from the same USD originals and same immutable rate snapshot.
* PHP and JS receive integer minor-free values and the same `rates`, `as_of`, `snapshot_id`, symbol, grouping and rounding configuration. Both implement decimal-safe half-up behavior; tests use shared fixtures. Never use PHP `number_format_i18n()` or browser-default `Intl.NumberFormat` for this contract. `Intl` may only be used with fully fixed options/locale and golden parity tests; a simple shared ASCII grouper is safer.

## 9. UI strings and selector

Ownership rule:

* Pera Currency owns generic plugin controls and accessibility source strings through normal WordPress gettext (`Currency`, `Select currency`, `USD`, etc.). It must work alone.
* Where the **theme** renders the header integration, its labels should follow the existing theme convention: `pera_ml_ui()` when available, guarded gettext/English fallback otherwise. These stable keys are registered with Pera ML's UI registry; no provider runs on a visitor request.
* Property semantics such as `From`, `Price`, range conjunctions and project/resale language remain theme-owned and use the existing Pera ML UI-string layer. Do not duplicate them in Pera Currency.
* Currency codes/symbols are not translated. Avoid translated currency names in the compact selector.

This permits rich stored de/ar/zh theme UI without making Pera Currency require Pera Multilingual. The currency plugin's own standalone admin/settings strings stay gettext-only.

### Header placement

Current desktop order is branding/navigation, then language, admin/CRM, search and menu controls. Add a **separate compact currency disclosure immediately after language**, visually forming `EN | USD` but retaining two independent buttons/lists and ARIA labels. Do not nest currency in the language menu. Reuse disclosure behavior/patterns but use independent IDs/state so Escape/outside-click/focus handling is predictable. Links/buttons must remain keyboard reachable with visible focus, `aria-expanded`, an associated menu/listbox pattern, and at least 40–44px targets.

On mobile, add a titled Currency section immediately after the existing Language section at the top of the off-canvas navigation. Four short codes avoid translation-length pressure. Preserve DOM focus order: close → language choices → currency choices → main menu. At narrow widths, allow wrapping rather than shrinking targets. Use logical inline properties and test Arabic direction; keep code/amount tokens LTR-isolated.

No-JS currency changes cannot safely personalize cached HTML. The V1 control may require JS; its semantic fallback remains the canonical USD page. It must not be an `<a>` to an English URL.

## 10. Preference, switching and caching

### Recommended persistence

Use localStorage as the browser source of truth (`pera_currency=EUR`) and an optional same value cookie only for AJAX/server hints. Defaults to USD. On selection:

1. validate against the four-code allowlist;
2. write localStorage;
3. write a first-party cookie (`Path=/`, `SameSite=Lax`, `Secure` on HTTPS, approximately one year; not HttpOnly because JS sets it); and
4. re-render marked nodes from raw USD without navigation.

On page boot, localStorage wins; synchronize the cookie. If storage is unavailable, use a valid cookie surfaced through cache-neutral bootstrap only if available, otherwise USD. Do not use PHP sessions. Do not put preference into ordinary URLs. Cookies naturally survive `/de/` → `/ar/`, while a language link remains untouched. Disabling Pera Currency leaves an inert cookie/localStorage key that no other layer reads; re-enabling may resume the preference. A removal/uninstall option may clear server settings, but a plugin cannot reliably delete every browser's localStorage.

### Why not cookie-driven SSR

Public pages are not proven to vary cache by currency cookies. Pera ML already requires path-based language cache separation; adding cookie-personalized SSR would risk cross-user leakage or multiply every language URL by four cache variants. Therefore Option B (selected-currency SSR plus `Vary: Cookie`) is rejected for current architecture. Option A (USD then JS) is safe but insufficient for AJAX fragments unless they carry raw values. **Option C**, USD SSR + raw canonical attributes + client reconciliation, is recommended.

Avoid flicker by loading the tiny deferred module early, inlining the rate/config snapshot if CSP policy allows, reserving amount width, and updating all money nodes in one animation frame. Never conceal canonical prices while waiting. Pages remain usable and truthful in USD when JS fails.

## 11. FX architecture

### Provider and lifecycle

Use the European Central Bank's official reference-rate XML feed as primary, with USD cross rates derived from the same daily table (`target/USD`); it includes EUR, GBP, TRY and has no browser secret. Use Frankfurter as a replaceable secondary adapter because it exposes ECB-derived rates through a simpler API. Provider calls are server-only.

* Schedule refresh twice daily (12 hours) with a randomized offset; also allow a capability/nonce-protected manual refresh and WP-CLI operation.
* HTTP timeout: 5 seconds; require HTTPS, 2xx, bounded response size and parseable data.
* Validate exact requested codes, finite positive numeric rates, plausible configurable bounds, a complete atomic set, provider date not implausibly future/old, and compute/store a snapshot checksum/version. Never partially merge a response.
* Persist last-known-good snapshot in a non-autoloaded option and cache the fresh snapshot in a transient/object cache. Use a short lock to prevent a cron stampede. Do not rely on a transient as the sole durable store.
* Fresh lifetime: 24 hours. Stale-while-error: continue the last-known-good snapshot for up to 7 days while visibly/administratively reporting staleness; retries use bounded backoff. After 7 days, non-USD selections fall back to USD rather than presenting materially stale conversion.
* If no valid snapshot has ever existed, selection may remain stored but all rendering returns canonical USD and the selector communicates USD availability; multilingual continues normally.

Provider legal/availability terms must be rechecked during implementation/release. The adapter boundary permits replacement without theme or multilingual changes.

## 12. Proposed Pera Currency API

All APIs accept/return value data; none echo, inspect ACF, taxonomies, translation rows, CRM, or request language.

```php
pera_currency_get_supported(): array
// ['USD' => ['symbol'=>'$', ...], ...], immutable filtered allowlist.

pera_currency_get_selected(): string
// Validated request hint/cookie when safe; otherwise 'USD'. Never language-derived.

pera_currency_get_rate(string $currency, string $base = 'USD'): ?float
// 1.0 for USD/USD; validated quote-per-base from usable snapshot; null unavailable.

pera_currency_convert($amount, ?string $currency = null, array $options = []): ?int
// Numeric USD by default; target selected/explicit. Returns rounded whole target units,
// null for invalid amount/rate. Options: source='USD', rounding=1000 (internal/allowlisted).

pera_currency_format($amount_usd, ?string $currency = null, array $options = []): string
// Escaped-as-text caller contract: deterministic symbol + grouped ASCII integer.
// Invalid/unavailable target safely formats original USD; no HTML and no prose.

pera_currency_format_range($min_usd, $max_usd = null, ?string $currency = null, array $options = []): array
// Structured result, not translated sentence:
// ['min'=>'€258,000','max'=>'€516,000','currency'=>'EUR','snapshot_id'=>'...',
//  'fallback'=>false]. Theme decides From/range punctuation and markup.
```

`format_range()` should return structured data rather than a single English string; this corrects the old API's temptation to own translated semantics. Inputs accept int/float/numeric string only, reject non-finite/negative values, and normalize max < min according to an explicit error contract rather than silently swapping business data.

JS config, registered once under a namespaced immutable object/data script:

```json
{
  "base":"USD",
  "selected":"USD",
  "supported":{"USD":{"symbol":"$"},"GBP":{"symbol":"£"},"EUR":{"symbol":"€"},"TRY":{"symbol":"₺"}},
  "rates":{"USD":1,"GBP":0.75,"EUR":0.86,"TRY":41.1},
  "rounding":{"USD":1,"GBP":1000,"EUR":1000,"TRY":1000},
  "asOf":"YYYY-MM-DD",
  "snapshotId":"sha256...",
  "storageKey":"pera_currency"
}
```

The page-selected value in cacheable HTML should be USD, not cookie-derived; JS reconciles local preference. DOM contract: `data-pera-money`, `data-usd-min`, optional `data-usd-max`, and domain flags such as `data-price-mode="from|single|range"`. The theme, not currency JS, supplies translated semantics. AJAX fragments include the same raw attributes.

## 13. SEO and canonical behavior

Property Offer/Product JSON-LD remains:

```json
{"price":"450000","priceCurrency":"USD"}
```

on all language routes. Currency cookies/localStorage/AJAX parameters must never affect it. Likewise, canonical and `hreflang` URLs represent translated content routes, not presentation preferences, and must exclude currency parameters. Theme SEO description builders that currently consume display `price_text` need an explicitly canonical USD formatter so future visitor formatting cannot bleed into metadata.

Displaying an approximate visitor-selected conversion while declaring the underlying offer in canonical USD is not inherently contradictory: the visible page can label the chosen currency and may state that converted prices are indicative, while structured data accurately describes the canonical offer. The numeric property source remains available in USD, and search crawlers/cache hits receive deterministic USD SSR. Do not mark approximate converted text as a separate schema Offer.

## 14. Deactivation and failure matrix

| Pera ML | Pera Currency | Expected behavior |
|---|---|---|
| ON | ON | Language-prefixed stored translations and independently selected display currency; USD queries/schema. |
| ON | OFF | Translated routes/cards/UI continue; all active property price fallbacks render canonical USD. No missing-function fatal. |
| OFF | ON | Canonical English routes and selectable currencies; currency gettext/English labels work without ML APIs. |
| OFF | OFF | Current canonical English and USD behavior. |

| Failure | Required result |
|---|---|
| FX provider unavailable | No impact on multilingual; use valid last-good snapshot within policy. |
| Snapshot stale ≤7 days | Use it, preserve `as_of`, expose admin health warning; no request-time provider call. |
| No/expired usable rates | Format USD and keep canonical values; never show a foreign symbol with USD number. |
| Translation missing/plugin unavailable | English semantic labels; conversion still works. |
| JS unavailable | USD SSR remains readable, links/forms/filtering work canonically. |
| AJAX lacks/has invalid currency | USD response, unchanged USD query meaning. |
| AJAX lacks valid ML context while ML active | Existing ML handler rejects bad nonce; currency must not bypass it. With ML off, theme action remains English. |
| Cached page from another visitor | Identical USD shell; local browser rehydrates its own valid preference. |

No deactivation migrates or rewrites property data. Pera Currency registers no filters that change meta reads, `WP_Query`, permalink language, canonical, or translations.

## 15. Previous Audit Delta

| Earlier conclusion | Status | Current decision |
|---|---|---|
| USD canonical V2 units/index; query/filter/sort unchanged | **STILL VALID** | Confirmed by current reindex, bounds and query builder. |
| Convert at presentation boundaries | **STILL VALID** | Strengthened: raw USD DOM contract and structured range result. |
| Small independent plugin API | **STILL VALID** | Theme is the composer; no plugin-to-plugin dependency. |
| Server can render selected currency from cookie | **MODIFIED** | Public SSR must be canonical USD because cache cookie variation is unproven. Explicit AJAX context is allowed with raw fallback. |
| Cookie + localStorage preference | **MODIFIED** | localStorage is browser truth; cookie is only an optional synchronized server/AJAX hint and must not personalize cached pages. |
| AJAX can infer preference from cookie | **REQUIRES NEW MULTILINGUAL INTEGRATION** | Send separately validated language and display currency; neither changes USD filter values. |
| Header gets a currency selector | **REQUIRES NEW MULTILINGUAL INTEGRATION** | Separate peer control after existing desktop language disclosure and section after mobile Language; preserve route/focus/RTL. |
| `number_format_i18n()` is an adequate formatter | **OBSOLETE** | Active language does not switch WP locale; define identical ASCII PHP/JS formatting. |
| `format_range()` can return final prose | **MODIFIED** | Return structured monetary values; translated `From`/range semantics remain theme/Pera ML-owned. |
| Currency might be represented in URLs | **OBSOLETE for V1** | Preference is not an indexable/query facet; archive prices remain USD and canonical/hreflang omit currency. |
| Schema remains USD | **STILL VALID** | Now explicitly applies identically to en/de/ar/zh and excludes visitor state. |
| JS dataset/map raw values remain USD | **STILL VALID** | Add ML-aware AJAX composition and raw price attributes for reconciliation. |
| Frankfurter primary | **MODIFIED** | Recommend official ECB feed primary and Frankfurter secondary adapter. |
| Latest offers/client/quote prices might join rollout | **STILL EXCLUDED / REQUIRES DOMAIN WORK** | Current code confirms mixed/source-tagged business data; not V1 property conversion. |
| Page cache can remain currency-agnostic | **STILL VALID, now mandatory** | Hybrid USD SSR + JS selected display is the default due to multilingual route caching risk. |

## 16. Exact implementation inventory

This is a proposed future inventory, not authorization to edit files in this audit.

| File | Current responsibility | Currency change required? | Multilingual interaction? | Proposed change |
|---|---|---:|---|---|
| `wp-content/themes/hello-elementor-child/inc/v2-units-index.php` | V2 indexing, aggregation, display-data and price formatting/range table | Yes | Existing `From`/`Price (USD)` UI strings | Preserve all numeric/index logic; make display wrapper optionally call currency API and emit raw USD; retain USD fallback; neutral translated label. |
| `wp-content/themes/hello-elementor-child/parts/property-card-v2.php` | Shared property card | Yes | Translated title/terms/UI and localized URL | Mark monetary node with raw USD/mode; compose translated semantics with formatter. |
| `wp-content/themes/hello-elementor-child/parts/_archive/property-card.php` | Legacy card | Conditional | Likely normal title/link filters | Update only if runtime tracing proves active; otherwise test/remove separately. |
| `wp-content/themes/hello-elementor-child/archive-property.php` | SSR archive, filters, slider, JS AJAX/history | Yes | Current translated strings plus explicit ML AJAX context | Keep canonical fields; convert visible summaries from canonical originals; send independent display currency. Ideally move inline currency JS to module. |
| `wp-content/themes/hello-elementor-child/inc/property-archive-query.php` | Shared USD filtering/sorting | No | Language-neutral canonical query | Add invariant tests/comments only; never consume currency. |
| `wp-content/themes/hello-elementor-child/inc/ajax-property-archive.php` | AJAX validation/query/card HTML | Yes, presentation only | Runs after Pera ML context handler | Read independently validated currency through public API; query remains USD; output raw attributes. |
| `wp-content/themes/hello-elementor-child/single-property.php` | Single hero, selected unit, unit sections | Yes | Translated content and labels | Replace direct `$` output with guarded common display seam; no domain logic moves. |
| `wp-content/themes/hello-elementor-child/parts/home-featured-property.php` | Featured home property | Yes | SSR translated card copy | Consume numeric USD instead of opaque `price_text`; mark raw node. |
| `wp-content/themes/hello-elementor-child/parts/featured-apartment.php`; `parts/featured-villa.php` | Invoke featured partial | No direct | Localized link/title through shared rendering | Regression test only. |
| `wp-content/themes/hello-elementor-child/home-page.php` | Homepage search and grids | Yes | Extensive Pera ML UI | Keep hidden preset USD; add converted visible summary and shared cards. |
| `wp-content/themes/hello-elementor-child/page-property-map.php` | Marker JSON and map/filter markup | Yes | Translated UI/terms/titles/URLs | Retain raw USD, add formatting metadata/config; clarify USD input contract. |
| `wp-content/themes/hello-elementor-child/js/property-map.js` | Client filters, overlay and selected-card AJAX | Yes | Already sends language context | Format only visible values; keep comparisons USD; send independent currency and rehydrate response. |
| `wp-content/themes/hello-elementor-child/header.php` | Desktop icons and off-canvas header | Yes | Existing desktop/mobile language selectors | Place independent currency control adjacent/after language with guarded renderer. |
| `wp-content/themes/hello-elementor-child/inc/theme-helpers.php` | Header language renderer and common helpers | Yes | Currently reaches ML plugin objects | Add theme currency renderer/fallback. Separately consider improving ML public URL API; do not couple plugins. |
| `wp-content/themes/hello-elementor-child/js/main.js` | Header disclosures/off-canvas behavior | Yes | Existing language disclosure | Generalize safely or add independent currency disclosure controller; preserve focus/Escape/outside click. |
| `wp-content/themes/hello-elementor-child/css/main.css` | Header/language/mobile/RTL-capable layout | Yes | Language control and logical layout | Minimal peer currency styles, narrow-width wrapping, focus and LTR isolation. |
| `wp-content/themes/hello-elementor-child/inc/seo-property-offer.php` | Offer JSON-LD | No behavioral conversion | Same schema on each translated route | Lock USD with tests; never call selected formatter. |
| `wp-content/themes/hello-elementor-child/inc/seo-property.php`; `inc/seo-all.php` | SEO descriptions and schemas | Possibly separation | Pera ML translates some SEO fields | Ensure any price prose uses canonical USD formatter, not selected display seam. |
| `wp-content/themes/hello-elementor-child/inc/latest-offers-card.php`; `partials/latest-offers-*` | Imported business offer cards | No V1 | UI text is translated | Explicit exclusion until source currency contract. |
| `wp-content/themes/hello-elementor-child/page-client-portal.php`; portfolio/favourites templates | Client budgets/business or shared cards | Conditional | Protected/no-cache and partial translation | Convert only reused canonical property-card nodes; exclude budgets/deals/offers. |
| `wp-content/themes/hello-elementor-child/page-citizenship.php`; `page-citizenship-properties.php`; `page-zh-citizenship.php` | Regulatory/editorial copy and some listing surfaces | Shared cards only | Pera ML/UI or legacy authored Chinese | Never convert USD thresholds; shared cards inherit normal behavior. |
| `wp-content/plugins/pera-multilingual/pera-multilingual.php` | Public ML APIs/bootstrap | Prefer no | First-class language seam | No currency knowledge. Optional future public enabled-language/URL helper is independent cleanup. |
| `wp-content/plugins/pera-multilingual/includes/class-ajax.php` | Archive language validation/context | No | Owns language only | Leave unchanged; Pera Currency validates its own field on its own hook/API. |
| `wp-content/plugins/pera-multilingual/includes/class-router.php` | Prefix routing and localized URLs | No | Must preserve path/query | Leave unchanged; currency never filters routes. |
| `wp-content/plugins/pera-multilingual/includes/class-ui.php`; `class-ui-registry.php` | Stored translated theme UI | No infrastructure change | Theme currency labels may register via `pera_ml_ui()` | Reuse existing public UI API from theme; do not add FX strings internally. |
| `wp-content/plugins/pera-multilingual/includes/class-fields.php`; `class-content.php`; `class-menu.php`; `class-seo.php` | ACF/core/menu/SEO translation | No | Independent axis | Leave untouched; numeric prices remain excluded. |
| `wp-content/plugins/pera-multilingual/assets/css/rtl.css` | Conservative Arabic overrides | Prefer no | RTL QA | Put component-specific logical/LTR styles with theme/currency component; change only if QA proves generic defect. |
| `wp-content/plugins/pera-currency/pera-currency.php` | Proposed bootstrap/constants/hooks | New | None required | Register independent services/APIs, cron, assets and guarded AJAX display context. |
| `wp-content/plugins/pera-currency/includes/class-rates.php` | Proposed snapshot/cache orchestration | New | None | Atomic validated LKG + transient + locks/staleness. |
| `wp-content/plugins/pera-currency/includes/interface-provider.php` | Proposed provider contract | New | None | Server-side quote snapshot interface. |
| `wp-content/plugins/pera-currency/includes/class-ecb-provider.php`; `class-frankfurter-provider.php` | Proposed adapters | New | None | Fetch/parse only; no browser secrets. |
| `wp-content/plugins/pera-currency/includes/class-preference.php` | Proposed validation/persistence hints | New | None | Four-code allowlist/default USD; no language inference. |
| `wp-content/plugins/pera-currency/includes/class-formatter.php` | Proposed deterministic conversion/format | New | None | Shared fixed contract, structured ranges, USD fallback. |
| `wp-content/plugins/pera-currency/assets/js/currency.js` | Proposed selector/rehydration | New | Must not navigate or translate | LocalStorage/cookie sync; raw-USD one-pass rendering; fragment observer/event hook. |
| `wp-content/plugins/pera-currency/assets/css/currency.css` | Proposed isolated component styling | New | RTL-compatible | Logical properties, focus, LTR monetary isolation; no header redesign. |
| `wp-content/plugins/pera-currency/tests/*` | Proposed unit/integration fixtures | New | Cross-axis matrix | Provider validation, PHP/JS golden parity, cache shell, AJAX, URLs, deactivation. |

## 17. Acceptance and implementation sequence

1. Freeze PHP/JS golden fixtures for conversion, rounding, grouping, ranges, invalid data and RTL isolation.
2. Implement the isolated plugin provider/snapshot/formatter APIs with USD fallback and no theme/ML knowledge.
3. Implement cache-neutral browser state and raw-USD rehydration; prove two preferences receive identical SSR HTML and different post-boot text.
4. Add the guarded theme price seam and migrate shared cards/single/home surfaces while locking schema and query tests.
5. Integrate archive visible filters and AJAX with two independent contexts; test all language × currency combinations and missing/invalid fields.
6. Integrate Property Map without changing numeric filtering.
7. Add separate desktop/mobile selector controls and complete keyboard, narrow viewport, de/ar/zh and RTL QA.
8. Run deactivation/provider/cache/no-JS drills. Do not expand into latest offers, CRM, portal or legal/editorial values without a new domain-specific decision.

The implementation is acceptable only when `min_price=300000` has the same USD meaning in every language and preference, language switching preserves selected currency without rewriting route semantics, currency switching preserves the language URL, cached SSR is always USD, and every four-state deactivation combination remains non-fatal and truthful.
