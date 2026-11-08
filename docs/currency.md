# Currency and monetary display

Scope: `wp-content/plugins/pera-currency/` plus explicit theme integrations. All stored/query monetary values are canonical USD.

## Canonical contract

For properties, `v2_units[*].v2_price_usd_min` and optional `v2_price_usd_max` are authoritative; derived post meta with the same min/max names supports archive queries. Legacy `price` and `price_usd` are defunct. See [property-cpt.md](property-cpt.md) for indexing and project/resale semantics.

Pera Currency supports USD, EUR, and GBP. `includes/class-rates.php` owns validated USD-base snapshots, durable option/transient cache, refresh locking/cron, freshness, and safe USD fallback. `class-formatter.php` and `includes/functions.php` own validation, conversion, half-up display rounding, symbols, and range formatting. Do not perform HTTP during rendering, write converted values to property data, use display-rounded values for filtering, or label unconverted USD with a foreign symbol.

## Rendering and hydration

Server output stays deterministic USD. Theme helper `pera_property_display_price()` chooses `from`, `single`, or `range`; `pera_property_display_price_html()` emits an isolated `<bdi data-pera-money data-usd-min ...>` monetary node. Semantic prose such as “From” remains outside the hydrated node. `Pera_Currency_Assets::enqueue()` publishes cache-neutral rate/config data and loads `assets/js/currency.js`; the child theme header adds `js/currency-selector.js`.

The browser reads the validated preference from `localStorage` (with a first-party cookie mirror), converts only `[data-pera-money]`, keeps direction LTR, and emits `pera:currency-change`. Cached HTML must not contain a visitor-specific converted SSR value. New/dynamic cards must either use the canonical data attributes before insertion or call `PeraCurrency.render(container)` afterward.

Archive budget controls may display the selected currency, but request/query boundaries must convert min/max back to canonical USD with floor/ceil-safe bounds. Sort and database comparisons always use derived USD meta. Map, cards, singles, favourites, portfolio, and newly injected archive results must obey the same contract.

## Exclusions and ownership

The currency engine does not own property data, taxonomy, semantic “From” policy, SEO/schema amounts, translation routing, CRM values, or quote currencies. Do not infer currency from language/location, add unsupported codes ad hoc, or translate currency codes/symbols. Pera Multilingual may translate selector/toast prose only; see [multilingual.md](multilingual.md).

## Tests

```sh
php wp-content/plugins/pera-currency/tests/php-test.php
node wp-content/plugins/pera-currency/tests/js-test.js
php wp-content/themes/hello-elementor-child/tests/property-display-price-test.php
php wp-content/themes/hello-elementor-child/tests/property-display-price-plugin-active-test.php
node wp-content/themes/hello-elementor-child/tests/archive-price-filter-currency-test.js
node wp-content/themes/hello-elementor-child/tests/dynamic-price-rehydration-test.js
node wp-content/themes/hello-elementor-child/tests/property-map-currency-test.js
```

Manually switch currencies on an SSR page and an AJAX-refreshed archive; verify range/from prose, selector persistence, expired/unavailable-rate USD fallback, RTL isolation, filters/sorting, and no hydration flash that changes surrounding text.
