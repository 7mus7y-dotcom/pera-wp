# Pera Currency — Phase 1

Pera Currency is an isolated, deactivatable currency engine. Phase 1 does **not** alter property templates, queries, metadata, ACF, SEO, or Pera Multilingual. USD remains the canonical source and safe no-JavaScript fallback.

## Contract

The single supported-currency registry contains exactly USD (`$`), EUR (`€`), and GBP (`£`). ECB daily XML is fetched through the WordPress HTTP API by cron, then its EUR-based reference values are normalized to units per USD: `EUR = 1 / ECB_USD` and `GBP = ECB_GBP / ECB_USD`. The complete response, date, finite positive values, and plausible bounds are validated before an atomic write.

A deterministic SHA-256 snapshot ID covers base, rates, provider, and provider date. A non-autoloaded `pera_currency_rates` option is the durable last-known-good record; a 12-hour transient is only a read cache. A 60-second lock prevents overlapping refreshes. WP-Cron refreshes every 12 hours. Rendering never performs HTTP. Call `Pera_Currency_Rates::refresh()` explicitly for tests or a future authorized admin/WP-CLI command, and `Pera_Currency_Rates::debug_status()` for internal state inspection.

Snapshots are fresh for 12 hours and stale-but-usable for seven days. Missing, invalid, or expired rates make foreign requests observably fall back to USD (currency, symbol, and amount together), so a foreign symbol can never label unconverted USD.

## Public PHP API

* `pera_currency_get_supported(): array`
* `pera_currency_get_selected(): string`
* `pera_currency_get_rate(string $currency, string $base = 'USD'): ?float`
* `pera_currency_convert($amount, ?string $currency = null, array $options = array())`
* `pera_currency_format($amount_usd, ?string $currency = null, array $options = array()): string`
* `pera_currency_format_range($min_usd, $max_usd = null, ?string $currency = null, array $options = array()): array`

`convert()` returns the display-rounded number, or structured raw/display/fallback data with `array('details' => true)`. Invalid, negative, NaN, and infinite amounts fail rather than being reinterpreted. Range output is structured monetary data and contains no semantic prose.

Formatting is locale-independent: prefix symbol, Western digits, comma grouping, and no cents. USD rounds half-up to the nearest 1; EUR and GBP round half-up to the nearest 1,000. Raw FX and display-rounded values remain separate.

## Browser runtime

Assets are **registered but not globally enqueued** in Phase 1. A future integration calls `Pera_Currency_Assets::enqueue()`, which publishes cache-neutral configuration with `selected: "USD"`; cookie state is never embedded into cacheable HTML. `window.PeraCurrency` reads and validates `localStorage.pera_currency`, optionally mirrors a one-year `SameSite=Lax` first-party cookie, converts/formats, emits `pera:currency-change`, and only rerenders explicit `[data-pera-money]` nodes.

Future markup may use `data-usd-min`, optional `data-usd-max`, and `data-price-mode`. The runtime modifies only that node, never surrounding prose such as “From”. Monetary nodes receive LTR direction; authors may use `<bdi dir="ltr">` and the supplied CSS uses bidi isolation for RTL-safe symbol/digit ordering.

## Tests

From this directory:

```sh
php tests/php-test.php
node tests/js-test.js
```

Both runners consume shared golden fixtures. They cover exact allowlisting, ECB cross-rate normalization and incomplete rejection, deterministic snapshots, fresh/stale/expired states, durable-option restoration, invalid inputs, unsupported codes, large values, and half-up boundaries.

## Explicit exclusions

Phase 1 does not include Turkish Lira, selectors, language/geolocation inference, user-specific SSR, native-locale formatting, theme/property/card/single/archive/map/AJAX/favourites/homepage integration, ACF or database writes, filter/sort/URL changes, SEO/schema, CRM, latest offers, client portal, editorial/legal conversion, or multilingual integration. Those are later or separately approved phases.
