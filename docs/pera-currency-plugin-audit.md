# Pera Currency plugin: repository audit and implementation specification

**Status:** design only; no plugin code is included.  
**Audited:** 2026-08-22, against the repository state on this branch.  
**Proposed plugin:** `wp-content/plugins/pera-currency/`.

## 1. Executive summary

Pera already has the right canonical model for conversion: V2 unit prices are authored in USD, save-time code derives post-level USD bounds, and all archive/map comparisons and price sorts operate on those bounds. The plugin must be a **presentation adapter**, not a second price store. It should convert raw USD only at display boundaries and leave ACF, derived metadata, indexes, queries, URLs and structured data in USD.

The safest V1 contract is:

* `min_price` and `max_price` always mean whole USD in URLs, forms, AJAX and PHP. They must never be interpreted using visitor preference.
* A visitor currency is kept in a small first-party cookie (server rendering) mirrored to `localStorage` (instant UI/cached-page recovery). USD is the default; no geolocation.
* Server-rendered price elements retain raw USD in `data-price-usd-min` / `data-price-usd-max` and presentation semantics in data attributes. JS rerenders from those numbers after a switch and after dynamic insertion; it never parses `$450,000`.
* Archive/map visible controls may show converted values, but dedicated canonical hidden inputs and marker data remain USD. When the currency changes, preserve the economic USD interval and recalculate the visible interval.
* Product/Offer JSON-LD and all indexable SEO text remain deterministic USD.
* Theme calls are guarded and retain their current USD branch. Deactivation therefore restores current behaviour without database migration.

The narrow integration refactor should make `pera_v2_units_format_price_text()` the theme-owned semantic gateway and remove the duplicate `$fmt_usd` closure from `parts/property-card-v2.php`. It should delegate only amount/range formatting to the plugin when available. Do not move project/resale rules into the plugin.

## 2. Current canonical price architecture

```text
ACF property.v2_units[]
  v2_price_usd_min / v2_price_usd_max       (canonical authored USD)
             |
             | save_post / acf save reindex
             v
post meta v2_price_usd_min / _max            (derived aggregate USD)
  |                     |                    |
  |                     |                    +--> map numeric marker data (USD)
  |                     +--> WP_Query overlap filter and price sort (USD)
  +--> cached global slider bounds (USD)

raw V2 rows --> aggregate/select unit --> price_min / price_max (still USD)
                                       --> price_text (currently hard-coded USD)
```

`inc/v2-units-index.php` is authoritative for normalization: missing max becomes min, a lone max becomes min, reversed bounds are swapped, values are rounded to integers, row index keys are rebuilt, post-wide min/max are stored, and `v2_index_flat` is built for beds. `pera_v2_get_price_bounds()` directly aggregates published post metadata, clamps its UI result to USD 50,000–5,000,000, caches for six hours, and clears on property save.

`pera_units_get_display_data()` returns raw `price_min`, `price_max`, selected/aggregated rows, and an already formatted `price_text`. This is the correct raw-data seam. Conversion must never parse `price_text`.

No code proves every stored property has V2 rows. The retired archive partial still reads legacy `price_usd`, and Offer schema intentionally tries legacy aliases. Consequently, integration must preserve those fallbacks even though the active archive/AJAX query is explicitly V2-only.

## 3. Complete price field inventory

| Name/location | Classification | Currency/shape | Uses and recommendation |
|---|---|---|---|
| `v2_units[].v2_price_usd_min` | **Canonical** ACF repeater subfield | Numeric USD | Primary unit minimum in `inc/v2-units-index.php`, cards, singles and schema. Never rewrite/convert. |
| `v2_units[].v2_price_usd_max` | **Canonical** ACF repeater subfield | Numeric USD, optional | Unit maximum; normalized to min when absent. Never rewrite/convert. |
| `v2_units[].v2_index_key` | **Derived** ACF subfield | String containing USD bounds (`beds|size|price`) | Save-time lookup/index material. Keep USD; plugin does not inspect it. |
| post meta `v2_price_usd_min`, `v2_price_usd_max` | **Derived** | Whole USD | Aggregate across V2 rows; archive filter/sort, global bounds and map. Keep USD. |
| post meta `v2_index_flat` | **Derived** | Bedroom token string | No monetary presentation; untouched. |
| transient `pera_v2_price_bounds_v1` | **Derived cache** | `{min,max}` USD | Archive slider bounds, six-hour theme cache; untouched. |
| display arrays `price_min`, `price_max` | **Frontend/intermediate** | Numeric USD | Returned by aggregation/display helpers and marker JSON. Explicitly document as USD or add parallel `price_usd_min/max` keys when integration occurs. |
| display arrays `price_text` | **Frontend-only** | Formatted USD today | Returned by display helper and included in marker JSON. Replace at renderer seam or omit from marker JSON if unused; never use as conversion input. |
| archive `GET/POST min_price`, `max_price`; hidden `#price-min-hidden`, `#price-max-hidden` | **Transport/frontend** | Numeric USD | Canonical public contract. Preserve names and USD meaning. Visible display controls must be separate. |
| map inputs `min_price`, `max_price` | **Frontend filter** | Numeric USD today | In V1 retain canonical USD in hidden model values and expose converted visible inputs. |
| map marker `price_min`, `price_max` | **Frontend JSON** | Numeric USD | Used by JS overlap comparison; remain USD. |
| map marker `price_text` | **Frontend JSON** | Formatted USD | Present but not consumed by current `property-map.js`; can remain canonical or be removed in a later cleanup. Do not make filtering depend on it. |
| legacy ACF `price_usd` | **Legacy canonical-looking** | Numeric USD | Read by `parts/_archive/property-card.php` and schema fallback. Convert only at that legacy display boundary; keep stored value. |
| schema aliases `price`, `property_price`, `sale_price`, `price_usd`; unit aliases `price_min`, `v2_price_min` | **Legacy compatibility** | Parsed numeric, assumed USD | `inc/seo-property-offer.php` fallback candidates. Keep canonical USD and deterministic. Their mere presence in fallback code does not prove live ACF definitions. |
| latest-offer `list_price`, `cash_price` in `pera_latest_offers` property meta | **Separate canonical business data** | Numeric/string, UI implies USD | Admin-managed offer/portfolio values, not derived property listing prices. Include in display conversion only as a separately approved scope; do not silently couple V1. |
| CRM/portfolio `list_price`, `cash_price` database fields/payloads | **Separate client data** | DECIMAL/text, labels use `$` | Used by public token/client portal and CRM admin. Not sourced from property V2 prices. Keep storage/admin USD; public conversion is an explicit phase, not automatic plugin knowledge. |
| home hero hidden `min_price`, `max_price` and `data-budget` presets | **Frontend transport** | Numeric USD | Submit to archive. Labels are USD display; integrate with the same canonical-hidden/display-label rule. |
| hard-coded `$400,000` citizenship content | **Editorial/legal threshold** | USD prose | Not a listing price and must not be converted by generic DOM logic. |

There is no committed ACF JSON/PHP field-group definition for `v2_units` in the repository; the inventory is based on all executable references. Production database field definitions should be verified before implementation, especially legacy aliases.

## 4. Complete frontend rendering inventory

### Property listing surfaces

| Surface | File/function | Numeric source and semantic | Renderer | SSR/JS |
|---|---|---|---|---|
| Main and taxonomy archive cards | `archive-property.php` → `pera_render_property_card()` → `parts/property-card-v2.php` | Re-aggregates `v2_units` raw USD; project is `From min`, resale is min or range | Local `$fmt_usd`; duplicated | SSR; replaced by AJAX on filtering/pagination |
| AJAX archive/search/filter cards | `inc/ajax-property-archive.php::pera_ajax_filter_properties_v2()` | Same card partial and raw V2 USD | Same local formatter | Server HTML in JSON `grid_html`, then JS insertion |
| Map selected card | `inc/ajax-property-archive.php::pera_ajax_get_map_property_card()` | Same property card helper | Same card partial | Server HTML in JSON `card_html`, then JS insertion |
| Single hero | `single-property.php` | `pera_units_get_display_data()` returns raw selected `hero_price_min/max`; hero directly prints min and project `From` | Direct `'$' . number_format_i18n()` (not its available `$price_text`) | SSR |
| Single unit price table | `inc/v2-units-index.php::pera_v2_render_units_price_table()` | Raw aggregate per bedroom; min/range | `pera_v2_units_format_price_text()`; heading hardcodes `Price (USD)` | SSR |
| Homepage featured section | `parts/home-featured-property.php` via `parts/featured-apartment.php` / `featured-villa.php` | `pera_units_get_display_data()['price_text']` | Shared V2 formatter | SSR |
| Main/test homepage property grids | `home-page.php`, `home-page-test.php` | V2 card | Card partial | SSR |
| Homepage budget presets | `home-page.php`, `home-page-test.php`, `js/home-hero-search.js` | Fixed USD `data-budget` copied into hidden archive params | Hard-coded `$…k/$…m` labels | SSR labels + JS form behaviour |
| Favourites page and favourites AJAX | `page-favourites.php`; `inc/favourites.php` | `pera_render_property_card()` | V2 card partial | SSR and AJAX HTML |
| Related properties on single | `single-property.php` | `pera_render_property_card()` | V2 card partial | SSR |
| Related properties in editorial posts/guides | `single-post.php`; `inc/theme-helpers.php` | `pera_render_property_card()` | V2 card partial | SSR/content filter |
| Luxury property page | `page-luxury-property.php` | `pera_render_property_card()` | V2 card partial | SSR |
| Portfolio token property grid | `page-portfolio-token.php` | V2 property card | V2 card partial | SSR, access-token surface |
| Retired legacy archive card | `parts/_archive/property-card.php` | ACF `price_usd`, always `From` | Direct `$` + `number_format_i18n()` | SSR if invoked; no active caller was found |
| Old Bodrum single | `single-bodrum-property.php` | Audit found no executable property-price renderer | N/A | SSR; deliberately no integration unless data review finds one |

Taxonomy archives use `archive-property.php` through its taxonomy context rather than a separate card template. Keyword results in that archive and AJAX responses use the same V2 card. There is no independent site search price renderer in the audited theme.

### Map surfaces

`page-property-map.php` creates marker entries with raw USD `price_min/max` and formatted `price_text`. Current bubbles show title and link only—no price—while selecting a marker fetches a full server-rendered V2 card. Thus “marker price text” exists in payload but is not currently visible. Future marker labels/popups should render from raw marker USD, not `price_text`.

### Latest offers / client and quote surfaces

These are price surfaces, but not the same property-price model:

* `inc/latest-offers-card.php` parses and formats per-property `list_price`/`cash_price`; `partials/latest-offers-card.php` and `partials/latest-offers-card-popup.php` print those prepared values.
* `page-portfolio-token.php` and `page-portfolio-theme-token.php` show those offer figures and/or V2 property cards.
* `page-client-portal.php`, theme `inc/client-portal.php` and the `peracrm` plugin expose portfolio prices. The CRM schema stores separate `DECIMAL(14,2)` list/cash values; CRM admin labels explicitly show dollars.

Recommendation: V1 converts V2 property listing prices everywhere, including V2 cards embedded on protected surfaces. Treat latest-offer/CRM list/cash prices as phase 2 after confirming their currency invariant and privacy/cache requirements. Admin input and internal CRM views should remain USD. This preserves the plugin boundary: the currency plugin never reads ACF or CRM tables; the owning renderer passes numeric USD if conversion is approved.

## 5. Archive filter data-flow diagram

```text
GET archive/taxonomy URL
 ?min_price=USD&max_price=USD&sort=...
        |
archive-property.php
  sanitize absint -> qs_min/qs_max (USD)
  pera_v2_get_price_bounds() -> global USD bounds
  clamp -> slider values
  pera_property_archive_build_args_from_context()
        |
WP_Query meta overlap:
  v2_price_usd_max >= min_price
  v2_price_usd_min <= max_price
sort: meta_value_num(v2_price_usd_min)
        |
SSR grid + canonical USD hidden controls
        |
inline archive JS
  range input -> sync hidden inputs -> FormData
  POST admin-ajax.php action=pera_filter_properties_v2
        |
AJAX handler sanitize/swap/clamp USD
  same shared query builder
  -> grid_html, pagination_html, facet counts, USD price_bounds
        |
JS replaces/appends cards and facets
  URLSearchParams from active values
  history.replaceState() (not pushState)
```

Current details:

* Global bounds are SQL min/max over published `v2_price_usd_*`, then clamped and cached. PHP fallbacks differ slightly (`50,000/1,000,000` in the helper and `100,000/5,000,000` around archive/AJAX), so the helper should remain preferred.
* Two visible `range` inputs share the USD bounds and two hidden named inputs carry submissions. A summary is rendered as `$min — $max`.
* `priceTouched` prevents untouched global bounds from becoming active URL filters.
* AJAX accepts floats but ultimately supplies integer USD to the shared query builder. Pagination links preserve USD price parameters.
* Each successful AJAX update uses `history.replaceState`; there is no `popstate` restoration. Refresh works because PHP rereads the URL. Browser Back does not step through each filter change by design.

### Recommended deterministic URL contract

Keep `min_price` and `max_price` permanently USD. Do **not** add display-currency amounts to query semantics. Optional `currency=GBP` should also be avoided in V1 because currency is a preference, not result identity, and it multiplies cache/canonical URL variants. If product later requires shareable presentation, allow `display_currency=GBP` as a presentation hint only; it must never change how `min_price`/`max_price` are parsed and should be stripped from SEO canonicals.

Example: `?min_price=312500&max_price=625000` always describes the same economic USD range. A GBP visitor might see approximately `£250,000–£500,000`, subject to the active rate, but sharing or refreshing the URL cannot change results.

Conversion direction at the UI boundary:

1. Canonical hidden USD state is authoritative.
2. Render visible bounds by `USD × rate`, with whole-unit display.
3. On visible user edits, map lower bound to USD using **floor(display/rate)** and upper bound using **ceil(display/rate)** so rounding does not accidentally exclude boundary listings; clamp to canonical global USD bounds.
4. Store/submit only the resulting whole USD values.
5. Switching currency never reconverts from old displayed values. It rerenders from canonical USD, preserving the economic interval and preventing drift.

For the existing dual-range slider, the least surprising V1 is to keep range input values internally USD and convert only their labels/summary. Native range thumbs have no user-visible numeric value, so there is no benefit in changing their value domain. If editable currency-number fields are later added, pair each with canonical hidden USD inputs using the rules above.

## 6. Property Map data-flow diagram

```text
page-property-map.php query published geocoded properties
  post meta v2_price_usd_min/max
  + display helper fallback
        |
JSON #property-map-data
  id/title/coordinates/taxonomies/bedrooms
  price_min/max = USD; price_text = formatted USD (currently unused)
        |
property-map.js initializes Google Map
  FormData numeric min_price/max_price
  marker overlap comparisons entirely in browser (USD)
        |
marker click -> POST pera_get_map_property_card(property_id, nonce)
        |
server returns V2 card_html -> results panel
```

Recommended integration:

* Keep marker numeric fields and `propertyMatches()` in USD. Rename only if backward compatibility permits; otherwise document them and optionally add `price_currency: "USD"` for diagnostics.
* Replace visible map number inputs with display inputs plus hidden canonical USD values, or—simpler and safer—keep inputs canonical USD and add converted formatted summaries. Because they are currently plain number fields, the better UX is paired display inputs whose event adapter updates hidden USD.
* On currency switch, rerender display inputs from hidden USD, preserving economic bounds; call existing `applyFilters()` only if canonical values changed (normally they do not).
* If map bubbles gain prices, use raw marker numbers with the shared JS formatter and semantic attributes. The existing unused `price_text` must not be parsed.
* The map card AJAX request should include no trusted rate or amount. Server reads the preference cookie and returns the chosen presentation. After `card_html` insertion, dispatch `pera:content-inserted` or call the common enhancement function so cached/mismatched HTML is corrected from embedded raw USD.
* A switch after initialization emits `pera:currency-change`; map listeners update summaries/bubbles and any loaded selected card without rebuilding markers or the Google Map.

## 7. Single-property pricing flow

`single-property.php` sanitizes `?unit_key=<beds>` with `absint` and passes it to `pera_units_get_display_data()`. The helper aggregates by bedroom, sorts groups by cheapest USD minimum, selects the requested bed group or cheapest group, and falls back to all-unit aggregation. It returns both raw `price_min/max` and `price_text`.

The hero currently uses raw `hero_price_min` and prints only that value; it separately decides whether to show `From` based on project status. `hero_price_max` is available but its “Up to” line is commented out. The price table aggregates and prints every bedroom group as a single/range via the shared helper.

Unit choices are links/navigation driven: `unit_key` is a URL parameter processed on a new request; the audit found no client-side price mutation for unit selection. Therefore currency selection and unit selection remain orthogonal. On navigation, the cookie selects server presentation; JS corrects a cached page if necessary.

Integration must use raw `hero_price_min/max`, row `price_min/max`, and the selected/aggregated arrays. Add semantic wrapper attributes rather than parsing `price_text`. Keep `unit_key` and canonical URLs independent of currency.

## 8. Dynamic/AJAX rendering considerations

Price-bearing HTML endpoints found:

1. `pera_filter_properties_v2` returns archive `grid_html` plus pagination/facets.
2. `pera_get_map_property_card` returns `card_html`.
3. Favourites uses the shared V2 card renderer in authenticated AJAX/theme flow.
4. Latest-offer popup/card flows return or prepare separate list/cash display values (phase-2 scope).

All active property endpoints converge on `pera_render_property_card()` / `parts/property-card-v2.php`, so centralizing that partial removes most risk. Every converted output should also carry canonical raw attributes. `currency.js` exposes an idempotent `enhance(root)` and listens for:

* `DOMContentLoaded`;
* `pera:currency-change`;
* a documented `pera:content-inserted` event dispatched by archive, map and favourites after HTML insertion.

A narrowly scoped `MutationObserver` on known result containers may be a defensive fallback, not the primary mechanism. Do not observe the entire document and do not regex-replace text nodes.

AJAX consistency under full-page caching: include `currency` in AJAX POST only as an allow-listed presentation hint so the endpoint can format immediately, but never trust it for queries. Cookie remains the server default. JS always enhances inserted nodes from raw USD, making stale cache/cookie timing harmless.

## 9. SEO/schema considerations

* `inc/seo-property-offer.php` emits Product/Offer JSON-LD with a numeric selected/minimum price and explicit `priceCurrency: USD`. Leave the file and output unchanged.
* `inc/seo-property.php` builds meta description fragments from `price_text`. That path must explicitly request/use the canonical USD theme formatter, not the visitor-aware formatter; otherwise cookie-varying titles/descriptions could enter caches. Add a theme helper option such as `currency => 'USD', canonical => true` rather than invoking selected currency.
* Open Graph and ordinary meta descriptions derived from that context remain USD. Social crawlers and page caches must see one deterministic value.
* Archive SEO treats filter query strings (including min/max) as noindex/follow and reads canonical USD values. Leave its parsing unchanged.
* Editorial USD thresholds (`$400,000`, `USD 400,000`) remain literal policy/legal copy.
* Visible body prices may vary. Structured data need not mirror a visitor’s display preference; canonical USD is the authoritative offer. No exception is recommended in V1.

## 10. Proposed plugin architecture

```text
wp-content/plugins/pera-currency/
├── pera-currency.php                  # headers, constants, bootstrap, public wrappers
├── includes/
│   ├── class-plugin.php               # hook orchestration/service container
│   ├── class-currencies.php           # allow-list and symbol/minor-unit metadata
│   ├── class-rates.php                # validated cache, refresh lock, stale policy
│   ├── interface-rate-provider.php    # provider boundary
│   ├── class-frankfurter-provider.php # primary HTTP adapter
│   ├── class-converter.php            # pure USD conversion/rounding
│   ├── class-formatter.php            # amount/range formatting only
│   ├── class-preference.php           # cookie/query/AJAX preference validation
│   ├── class-settings.php             # admin status/manual refresh/provider settings
│   ├── class-assets.php               # enqueue/configuration
│   ├── class-selector.php             # generic selector markup/shortcode/action
│   └── class-rest.php                 # public read-only rates/config route
├── assets/
│   ├── currency.js
│   └── currency.css
├── tests/
│   ├── test-converter.php
│   ├── test-formatter.php
│   ├── test-preference.php
│   └── test-rates.php
└── readme.md
```

The REST class is justified for cached pages and live switching: expose only supported currencies, rates, base, fetched timestamp and stale state. No provider secret or provider-specific response is returned. Preference changes can be client-side cookie writes; no write endpoint is required.

Activation schedules refresh and attempts a non-blocking/controlled initial fetch. Deactivation unschedules plugin cron and removes only the refresh lock/transient; retain the last valid rates option and preference cookie so reactivation is seamless. Uninstall policy may remove options only from a separate `uninstall.php`, not deactivation.

## 11. Public PHP API specification

All wrappers are namespaced internally but global functions form the stable theme API. Inputs are canonical USD unless a source is explicit.

### `pera_currency_get_selected(): string`

Returns one of `USD|GBP|EUR|TRY`. Reads the validated preference cookie; defaults to `USD`. If selected non-USD has no usable rate, returns `USD` for effective rendering (the selector may separately show an unavailable state). Never returns arbitrary cookie text.

### `pera_currency_get_rate( string $currency, string $base = 'USD' ): ?float`

Returns positive units of target currency per one base unit. V1 accepts only base USD; `USD` returns `1.0`. Invalid code, unsupported base, corrupt cache or never-fetched non-USD returns `null`. A last-known valid stale rate is safe and returned with observability handled internally.

### `pera_currency_convert( int|float|string $amount_usd, ?string $currency = null, array $options = array() ): ?float`

Validates a finite, non-negative numeric USD amount; `null` currency means selected. Returns converted unformatted numeric amount. Default `round => false`; callers that need filter boundary policy can request `round => 'floor'|'ceil'|'nearest'` and `precision => 0`. Invalid amount returns `null`; missing target rate falls back to the unchanged USD numeric amount only when `fallback_usd` (default `true`) is set. It never throws during frontend rendering.

### `pera_currency_format( int|float|string $amount_usd, ?string $currency = null, array $options = array() ): string`

Converts and formats one amount. Defaults: selected currency, `decimals => 0`, nearest whole unit, symbol style, no code suffix. Output examples: `$450,000`, `£337,000`, `€390,000`, `₺18,500,000`. Invalid/non-positive handling is caller-selectable via `empty => ''` (default); no fabricated zero. Missing rate formats original USD. Escape at output (`esc_html`); API returns plain text.

### `pera_currency_format_range( int|float|string $min_usd, int|float|string|null $max_usd, ?string $currency = null, array $options = array() ): string`

Formats a single amount if max is absent/equal, otherwise `MIN–MAX` using an en dash with no spaces, e.g. `£337,000–£412,000`. It normalizes reversed valid bounds. Option `prefix` may be supplied by the theme (`From `), but project/resale decisions remain outside the plugin. Invalid min returns `''`; missing rates use USD.

### `pera_currency_get_supported(): array`

Returns public metadata keyed by code (`code`, `symbol`, `label`, `available`). This keeps selector implementation out of the theme without exposing provider details.

Optional advanced functions for integrations: `pera_currency_to_usd( $display_amount, $currency, $rounding )` for visible filter adapters and `pera_currency_render_selector( $context )`. The inverse converter must be explicit—never overload `convert()` direction ambiguously.

### Formatting/rounding decision

Use the exact rate and round each displayed property amount to the **nearest whole currency unit**, not 100 or 1,000. Whole-unit rounding meets no-cents requirements, keeps range endpoints close to their canonical economic value, and avoids materially misstating prices (especially TRY). `number_format_i18n()` is site-locale-aware but can produce language-dependent separators; V1 examples require comma grouping, so the formatter should use an explicit, filterable display locale policy (default `en-US`) and zero fraction digits. PHP output and `Intl.NumberFormat` JS output must be fixture-tested to match symbols and separators. Do not round converted values before calculations or store them.

## 12. JS architecture

`currency.js` should be dependency-free and receive a small `wp_add_inline_script(..., 'before')` config:

```js
window.peraCurrencyConfig = {
  selected: 'GBP',
  base: 'USD',
  rates: { USD: 1, GBP: 0.8, EUR: 0.9, TRY: 41.1 },
  symbols: { USD: '$', GBP: '£', EUR: '€', TRY: '₺' },
  cookie: { name: 'pera_currency', path: '/', maxAge: 31536000, secure: true },
  restUrl: '/wp-json/pera-currency/v1/rates'
};
```

Responsibilities:

* validate currency codes against config;
* write `SameSite=Lax` cookie and mirror `localStorage.pera_currency`;
* format canonical values with `Intl.NumberFormat('en-US', {style:'currency', currency, maximumFractionDigits:0})`, with symbol metadata fallback;
* enhance `[data-pera-price]` elements from raw USD attributes and semantic attributes (`data-price-mode="single|range"`, `data-price-prefix="From "`);
* adapt archive/map display controls while retaining canonical hidden USD;
* update every selector instance and announce the change once through an `aria-live` status;
* dispatch `pera:currency-change` with `{currency, rate}` and accept `pera:content-inserted` with a root node;
* if localStorage differs from server config (common on cached HTML), validate it, sync cookie, fetch rates if necessary, and rerender immediately; never reload merely to switch.

Rate JSON must not be accepted from arbitrary DOM markup. Config is public, but validate it anyway. Failed REST refresh leaves the current effective USD/stale configuration in place.

## 13. Preference persistence strategy

Use **both cookie and localStorage**, with a simple precedence model:

1. On PHP request, valid `pera_currency` cookie; otherwise USD.
2. On JS boot, valid localStorage value wins for the browser and is mirrored into the cookie; if absent, mirror the server selection into localStorage.
3. On selector change, write both, rerender immediately, and send the currency as an untrusted presentation hint on subsequent AJAX calls.

Cookie: one year, `Path=/`, `SameSite=Lax`, `Secure` on SSL; it need not be HttpOnly because JS intentionally writes it. Do not use PHP sessions. Do not require a query parameter. Do not geolocate in V1.

This hybrid is necessary because server HTML needs a preference and full-page caches may ignore cookies. Raw USD data plus JS reconciliation makes cached pages correct without requiring cache variation by currency. CDN/page-cache policy should deliberately **not vary** the whole page by cookie in V1; serve/cache canonical USD-compatible markup and enhance client-side. Uncached responses may render selected currency for no-flash UX.

## 14. FX provider/cache strategy

### Primary: Frankfurter

Recommend the Frankfurter API adapter (`api.frankfurter.app`) for V1: it offers a simple HTTPS latest-rates endpoint, supports a requested base/symbol list, needs no browser exposure, and is appropriate for indicative display currencies. Verify GBP/EUR/TRY availability in staging during implementation and contract-test the exact response. Its published data is reference/daily rather than trading-grade; that is acceptable for indicative property displays if the timestamp/disclaimer is retained internally.

Example server request shape (not browser code): `GET /latest?from=USD&to=GBP,EUR,TRY` with a short timeout. The provider adapter must be replaceable because rate coverage/terms can change.

### Alternative: Open Exchange Rates

Open Exchange Rates is a reasonable keyed alternative with USD-based latest rates. Store its App ID in an environment constant or non-autoloaded protected option and never localize it to JS. Confirm plan terms, update frequency and required attribution before selection. A paid/provider-neutral alternative can be substituted behind the same interface without theme changes.

### Storage and refresh

Maintain two distinct records:

* non-autoloaded option `pera_currency_last_good_rates`: validated complete snapshot, provider timestamp, fetched-at UTC, checksum/schema version;
* transient `pera_currency_fresh_rates`: same snapshot, TTL **6 hours**;
* transient/atomic option lock `pera_currency_refresh_lock`: about 60 seconds to prevent stampedes.

Schedule WP-Cron every six hours, with ± jitter if supported. Admin manual refresh is nonce/capability protected. Frontend requests never synchronously fetch merely because the transient expired: serve last-good, schedule one `wp_schedule_single_event()` refresh, and continue. A low-traffic site still refreshes through cron when traffic arrives; a real system cron invoking WP-Cron is recommended.

Validate HTTP 200, JSON shape, base USD, all required codes, finite positive rates, plausible configurable bounds, and timestamp. Only then atomically replace last-good. Never erase last-good on a bad response.

## 15. Failure/fallback behaviour

| State | Behaviour |
|---|---|
| Fresh complete cache | Use it; no HTTP. |
| Fresh transient absent, valid last-good exists | Serve stale snapshot immediately; acquire lock and schedule refresh. UI remains functional. |
| Refresh fails | Keep last-good, record sanitized error/time for administrators, retry on next scheduled interval with capped backoff; no frontend warning/fatal. |
| Partial/corrupt response | Reject entire snapshot; do not mix timestamps from currencies. |
| No valid snapshot has ever existed | Effective currency is USD only; disable/hide non-USD choices with an accessible “temporarily unavailable” explanation. `$` formatting and all queries continue. |
| One selected code becomes unsupported | Fall back to USD for output, correct preference to USD client-side, announce once. |
| Plugin inactive/missing | Theme’s current USD formatter/controls execute. No plugin assets, cookie interpretation or rate fetch. |

Stale rates can safely format property prices because they are explicitly indicative display values. Set an operational alert threshold (recommended 72 hours) in Site Health/admin; do not take the site down. Never expose verbose provider errors to visitors.

## 16. Currency selector UX

The real header is `header.php`: `.header-icons` contains the desktop language disclosure (admin-only today), admin/CRM/search controls and menu toggle; the off-canvas menu renders the mobile language navigation immediately after `.offcanvas-top`.

Recommended placements without redesign:

* **Desktop:** render `pera_currency_render_selector('desktop')` in `.header-icons` immediately after the language switcher and before admin/CRM/search icons. Use compact current code (`USD`) plus chevron, matching `.header-language-switcher` disclosure dimensions and focus treatment rather than adding a new icon.
* **Mobile:** render `pera_currency_render_selector('mobile')` immediately after the mobile language switcher and before `.offcanvas-main`, with a “Currency” title and four large radio/list choices. It remains available even when the admin-only language selector is absent.

Plugin markup classes: `.pera-currency-selector`, `--desktop`, `--mobile`, `__toggle`, `__list`, `__option`, `__code`, `__status`. Reuse theme CSS variables and generic typography/button tokens; plugin CSS owns only layout/disclosure states and must not target unrelated header elements. Provide keyboard Arrow/Escape behaviour, `aria-expanded`, `aria-haspopup="listbox"` (or native select), visible focus, `aria-current`/checked state, and a non-intrusive live announcement. A native `<select>` is the most robust V1 mobile control.

Because the theme must remain functional without the plugin, header integrations are only guarded calls; no empty placeholder is required.

## 17. Archive filter currency UX

1. Load: PHP parses canonical USD URL and outputs canonical hidden/range values plus raw attributes. Visible heading changes from `Price range (USD)` to `Price range (GBP)` and summary formats selected currency when plugin exists.
2. Switch USD→GBP: do not change canonical values or refetch results. Immediately rerender global/current summary from USD; range thumbs remain at identical positions because their internal values remain USD.
3. Drag: slider emits USD canonical values, summary displays `USD × current rate`. AJAX/URL receive USD only.
4. Existing active range: preserve the economic range, never literal prior display numbers. This avoids result changes on a presentation-only action.
5. Reset: clear canonical hidden inputs/URL price parameters, restore USD global bounds internally, rerender them in selected currency.
6. AJAX bounds: interpret response `price_bounds` as USD and rerender; never replace rate state from the response.
7. History: retain current `replaceState` behaviour and canonical USD values. Currency switching does not edit the URL. Refresh restores result semantics; cookie/localStorage restores presentation. If future UX moves to `pushState`, add a `popstate` handler that restores canonical controls before formatting.

Display examples are approximate and rate-dependent. Inputs should show grouping/symbol in adjacent labels rather than inside `type=number`. Use `aria-valuetext` for converted slider values.

## 18. Exact theme integration points

| File/function | Minimal future change |
|---|---|
| `inc/v2-units-index.php::pera_v2_units_format_price_text()` | Preserve project/range decisions; call guarded plugin `format`/`format_range`, otherwise current `$` formatter. Add explicit canonical-currency option/helper for SEO. |
| `inc/v2-units-index.php::pera_v2_render_units_price_table()` | Dynamic selected-currency column label for visible body; add raw USD attributes; guarded USD fallback. |
| `parts/property-card-v2.php` | Remove local `$fmt_usd`; use the shared theme helper and output raw USD semantic attributes. This is the key centralization. |
| `single-property.php` hero | Replace direct `$` output with shared theme amount helper; retain theme-owned `From`; add raw attributes. |
| `parts/home-featured-property.php` | It already consumes shared display data; add raw attributes for cache/JS reconciliation. |
| `parts/_archive/property-card.php` | If retained, guarded plugin formatting around legacy numeric `price_usd`; USD fallback. |
| `archive-property.php` | Keep request/query values USD; add currency-aware labels/raw config and JS adapter; dispatch insertion event; do not alter query builder contract. |
| `home-page.php`, `home-page-test.php`, `js/home-hero-search.js` | Keep preset `data-budget` USD; rerender preset text and annotate canonical values. Test file integration depends on whether route is deployed. |
| `page-property-map.php` | Mark JSON amounts/canonical hidden values as USD and add converted display controls/summaries. |
| `js/property-map.js` | Keep comparisons USD; listen for currency changes, manage display-to-hidden adapter, pass presentation hint for card HTML, dispatch insertion event. |
| `inc/ajax-property-archive.php` | Query request stays USD; allow-list optional presentation hint only for renderer; card output carries raw data. |
| `inc/favourites.php` / favourites insertion JS | Dispatch common insertion event if current injection does not; shared card does the formatting. |
| `header.php` | Two guarded calls to plugin selector renderer at audited desktop/mobile positions. |
| `inc/seo-property.php` | Force canonical USD price text for metadata, independent of preference. |
| `inc/seo-property-offer.php` | No behavioural change; add regression tests asserting USD if desired. |
| asset enqueue/bootstrap files | Declare integration JS dependencies/order only where needed; plugin owns its global assets. |

Guard example (design only):

```php
if ( function_exists( 'pera_currency_format' ) ) {
    $amount_text = pera_currency_format( $price_usd );
} else {
    $amount_text = '$' . number_format_i18n( $price_usd );
}
```

Do not cache `function_exists()` at file-load time or call plugin services while theme files are included. Evaluate at render time, after `plugins_loaded`, so boot order is unimportant.

## 19. Files that will require modification

Minimum confirmed set for complete V2 coverage:

* `wp-content/themes/hello-elementor-child/header.php`
* `wp-content/themes/hello-elementor-child/inc/v2-units-index.php`
* `wp-content/themes/hello-elementor-child/parts/property-card-v2.php`
* `wp-content/themes/hello-elementor-child/parts/home-featured-property.php`
* `wp-content/themes/hello-elementor-child/single-property.php`
* `wp-content/themes/hello-elementor-child/archive-property.php`
* `wp-content/themes/hello-elementor-child/page-property-map.php`
* `wp-content/themes/hello-elementor-child/js/property-map.js`
* `wp-content/themes/hello-elementor-child/home-page.php`
* `wp-content/themes/hello-elementor-child/js/home-hero-search.js`
* `wp-content/themes/hello-elementor-child/inc/ajax-property-archive.php`
* `wp-content/themes/hello-elementor-child/inc/seo-property.php`
* the theme’s relevant enqueue/header CSS integration file(s), only for hooks/events/layout.

Conditional/legacy: `home-page-test.php`, `parts/_archive/property-card.php`, and the favourites insertion script/handler. Latest-offer and CRM/token price renderers require a separately reviewed phase.

## 20. Files that should deliberately remain untouched

* ACF values and field definitions: no migration and no converted subfields.
* `inc/property-archive-query.php`: filtering/sorting is already correctly canonical USD.
* derived index save logic and post metadata writes in `inc/v2-units-index.php` (apart from display helpers in that same file).
* `inc/seo-property-offer.php`: explicit USD Product/Offer is correct.
* `inc/seo-property-archive.php`: canonical/noindex query behaviour remains USD.
* CRM database schema/repositories and admin price inputs in `wp-content/plugins/peracrm/`.
* editorial/legal price copy and unrelated templates/styles.
* property taxonomy/business logic, unit selection and aggregation algorithms.

## 21. Deactivation/fallback strategy

The theme must ship fallback code in every integration, never a required include from the plugin:

* PHP: guard only public wrappers with `function_exists()`. USD branch reproduces present formatting and labels. Do not type-hint plugin classes or reference plugin constants from theme code.
* Header: selector call is conditional; absence produces existing markup exactly.
* JS: theme adapters first test `window.PeraCurrency`/config. Without it, current USD labels, range values, map comparisons and AJAX proceed.
* HTML: canonical USD attributes/hidden fields are useful but inert without plugin JS.
* AJAX: currency hint is optional; handler query parsing never calls plugin conversion.
* SEO: canonical formatter is theme-owned and always has a USD branch.
* Data: plugin creates no property metadata and deactivation schedules no migration/reindex. Existing preference cookies are ignored.

Deactivation acceptance is a mandatory test run, not just a code review: deactivate plugin, clear caches, exercise SSR archive/taxonomy/single/map/home/favourites, AJAX filter and map-card endpoints, sorting/pagination, and inspect JSON-LD.

## 22. Security considerations

* Allow-list exactly `USD`, `GBP`, `EUR`, `TRY`; sanitize cookie, REST/AJAX and settings values. Never use a currency value as HTML, an option key, URL host or query meta key without mapping.
* Escape selector markup and all formatted output at the final context. Raw numeric data attributes use numeric casting plus `esc_attr()`.
* Provider endpoint is hard-coded/allow-listed HTTPS to avoid SSRF; use `wp_safe_remote_get()`, short connect/total timeouts, response-size limits and no redirects to unknown hosts.
* API credentials are server-side constants or protected non-autoloaded options, never localized/REST-returned/logged.
* Admin settings/manual refresh require `manage_options`, nonce and rate limiting. Public rates REST is read-only and reveals no secret.
* Validate response completeness, positivity, finiteness, timestamp and plausible bounds before atomic persistence. Reject NaN/infinity/negative/zero.
* Use a refresh lock to prevent denial-of-service/stampedes. Never make provider calls from arbitrary public AJAX requests.
* Canonical filter values are sanitized/clamped server-side as today; display currency and client conversion are never trusted for SQL.
* Avoid PHP sessions and user identifiers. Preference is low-risk functional storage; document cookie usage in the privacy/cookie policy as appropriate.

## 23. Performance considerations

* One cached snapshot serves all requests; zero provider requests per page view.
* Store last-good option with `autoload=no`; transient/object cache handles fresh reads. Cache service state per PHP request.
* Enqueue the small JS/CSS only on public pages containing the selector/prices (the selector is site-wide, so likely all frontend pages), never admin except settings.
* Do not duplicate converted values in post meta or fragment caches. Conversion is constant-time arithmetic.
* Preserve existing USD SQL indexes/query shapes and six-hour bounds cache.
* Batch DOM updates in one animation frame on switch. Query only annotated price nodes; avoid document-wide MutationObserver/text parsing.
* Page cache remains currency-agnostic. Canonical raw attributes allow a single cache object, preventing fourfold fragmentation.
* REST responses use a short public cache header/ETag keyed to snapshot timestamp. JS normally receives rates inline and need not fetch.

## 24. Testing matrix

| Area | Cases / expected result |
|---|---|
| Converter unit tests | Each USD→USD/GBP/EUR/TRY; inverse boundaries; zero/negative/string/NaN; floor min and ceil max; no cumulative conversion drift. |
| Formatter unit tests | Exact examples, no decimals, symbol/order/grouping, equal/absent/reversed ranges, `From`, invalid empty, stale and USD fallback. PHP/JS fixtures match. |
| Rate service | Fresh hit makes no HTTP; expiry serves stale and schedules once; lock prevents concurrency; 4xx/5xx/timeout/malformed/partial/outlier preserves last-good; first-run failure exposes USD only. |
| Preference | Missing/invalid cookie defaults USD; all four values; Secure/SameSite/path/max-age; localStorage reconciliation under cached USD HTML. |
| Archive SSR | Direct canonical USD URL in every selected currency yields identical post IDs/count/order/pagination; display changes only. Taxonomy and keyword contexts included. |
| Archive AJAX | Price overlap boundaries, untouched bounds, reset, sort/pagination, append/replace cards, URL remains USD, inserted cards selected currency. |
| Archive history/share | Refresh and copied URL return identical results for different preferences; currency change does not mutate URL; current replaceState behaviour verified. |
| Map | Input conversion/inverse, raw marker comparisons identical across currencies, reset, marker card AJAX, change after initialization/after selected card, mobile list view. |
| Single | Project From, resale single/range, missing max, selected/invalid/missing `unit_key`, hero and every unit row, price-on-request. |
| Other renderers | Home featured/grid/presets, favourites SSR/AJAX, related cards, editorial related cards, luxury, portfolio-token V2 cards, legacy partial if reachable. |
| SEO | JSON-LD price and `priceCurrency=USD` identical for four cookies; meta/OG descriptions identical USD; filtered archive robots/canonical unchanged. |
| Accessibility | Keyboard selector, focus return/Escape, screen-reader labels/live status, mobile touch targets, contrast, no-JS USD baseline. |
| Caching | Full-page cache hit created under another preference corrects from raw USD; AJAX behind cache/auth; no cache key explosion. |
| Deactivation | No fatal/console error; USD cards/single/table/archive/map/AJAX/favourites; filter query and SEO remain valid. Reactivation restores prior preference if rates valid. |
| Multilingual/RTL | Selector alongside existing language UI, translated labels, symbol/bidi isolation, no route change. |
| Performance/security | Provider call count, refresh lock, response limits, capability/nonce, XSS payload cookie, unsupported AJAX currency, REST contains no secret. |

Manual QA should use a fixed test snapshot so expected converted amounts are deterministic. Provider integration tests should be separate and mock HTTP in normal CI.

## 25. Recommended implementation sequence

### Phase 0 — fixtures and contract (review 1)

1. Confirm production ACF field group and whether any published property lacks V2 rows.
2. Confirm whether latest-offer/CRM values are always USD and decide phase-2 scope.
3. Record fixed rates, representative project/resale/missing-price fixtures, current USD screenshots and archive query result IDs.
4. Approve deterministic URL and rounding contracts.

### Phase 1 — isolated plugin core (review 2)

1. Scaffold plugin/bootstrap and pure currencies, converter and formatter services.
2. Add public guarded API wrappers and unit tests.
3. No theme integration; activation/deactivation is already harmless.

### Phase 2 — resilient rates/preferences/admin (review 3)

1. Add provider interface and Frankfurter adapter, validation, last-good/fresh cache, cron/lock/manual refresh and Site Health status.
2. Add cookie preference, read-only rates REST endpoint and failure tests.
3. Verify first-install/provider-down USD-only mode.

### Phase 3 — shared theme display seam (review 4)

1. Refactor `pera_v2_units_format_price_text()` with explicit selected vs canonical formatting.
2. Remove `$fmt_usd` duplication from `parts/property-card-v2.php` and annotate raw USD.
3. Integrate single hero/table and homepage featured output.
4. Lock SEO to canonical USD and add snapshot tests.

### Phase 4 — selector and client enhancement (review 5)

1. Add accessible desktop/mobile selectors at the audited header seams.
2. Add `currency.js/css`, raw-node enhancement and cache reconciliation.
3. Integrate home preset labels and favourites/dynamic insertion events.
4. Capture desktop/mobile screenshots and perform keyboard/no-JS checks.

### Phase 5 — archive filters (review 6)

1. Retain canonical USD sliders/hidden fields/URLs and add converted labels/ARIA.
2. Integrate AJAX inserted HTML and verify identical result IDs across currencies.
3. Test reset, sort, pagination, taxonomy, refresh/share/history and deactivation.

### Phase 6 — Property Map (review 7)

1. Preserve marker USD values/comparisons; add visible adapters and currency events.
2. Enhance selected AJAX cards and any marker price UI from raw USD.
3. Test switching before/after initialization and mobile map/list modes.

### Phase 7 — optional separate price domains and release (review 8)

1. Only if approved, integrate public latest-offer and client quote list/cash renderers while leaving storage/admin USD.
2. Run the full matrix, provider outage drill, cache test, performance/security review and plugin-off regression suite.
3. Deploy plugin inactive, deploy guarded theme integration, activate, fetch/verify rates, then monitor freshness and frontend errors. Rollback is plugin deactivation.

Each phase is independently reviewable. The plugin should not be activated in production until the guarded theme changes and a valid last-good snapshot exist; nevertheless, activation with no rates must still produce the current USD experience.
