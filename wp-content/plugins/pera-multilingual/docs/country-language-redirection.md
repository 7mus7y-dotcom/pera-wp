# Country-based automatic language redirection

## Follow-up design decision (PR comment 6543444810)

The direct Apache/cPanel host has no trusted GeoIP module/environment or audience service. The theme's `send_headers` priority-20 handler sets public TTLs (home/archive 300s, property 600s, district 900s, posts 1800s). WordPress fires `send_headers` after resolving the main query, before `template_redirect`; the existing preparation alone does not explicitly coordinate with that theme policy. The follow-up will add a final `send_headers` guard and repeat it before template output, including Expires/Pragma. Tests will inspect real HTTP response headers with the actual theme module loaded. With flags off, no new hooks will change existing TTLs. Apache mod_expires does not add Expires when the application already supplies it; any later Apache `Header set` or upstream cache still requires host verification.

GeoIP will use the maintained `maxmind-db/reader` Composer package and a private, operator-updated GeoLite2 Country MMDB, retaining explicitly configured trusted server/Cloudflare sources. Missing reader/database, corruption, stale data and unrecognized records fail closed to no country redirect. Only the connection peer (or an explicitly trusted non-HTTP server client-IP parameter) is used; forwarded headers never establish client identity.

For this host, synchronous PHP DNS cannot promise a per-call timeout. An optional local crawler mode will therefore queue bounded reverse/forward DNS checks in a background WordPress cron worker, with bounded cached results and no DNS calls on frontend requests. Known search-provider suffixes require forward-confirmed IP ownership. A crawler-like User-Agent is only an additional conservative bypass, never verification. New IPs, absent/failed DNS, unconfirmed names and stale cache get no automatic country redirect. Successfully forward-confirmed non-search-provider addresses may negotiate only on browser navigation requests; this is a bounded browser-traffic policy, not proof of human identity or coverage of every possible crawler. The existing trusted audience integration remains available. Explicit manual selections work without DNS classification. The operator must accept delayed negotiation for first/new addresses and no negotiation for addresses without reliable DNS, or supply a trusted audience service instead. No feature flag or live hosting configuration is changed by this work.

Status: **disabled by default**. No deployment or production verification is performed by this change. PHP 7.4+ is required (cookie options API). No database migration, new translation system, paid API, DNS lookup, or per-request external HTTP call is added.

## Architecture audit

The plugin's entrypoint boots the language registry, storage, router, content, ACF fields, vocabulary, UI, menu, SEO, AJAX and administrative translation services. Providers, queue/health tools and CLI scripts generate stored translations only during explicit administrative/offline work. They are not part of request negotiation.

- `class-router.php` removes an enabled first language segment at `plugins_loaded` priority 1, before theme path-based loading, and restores the public URI at `parse_request` priority 0. WordPress resolves the same original post, page, property, archive or search. Static front-page roots have special main-query handling. No duplicate translated posts or translated slugs exist.
- `url_for_language()` is the sole URL resolver. It replaces existing enabled prefixes, supports subdirectory installs, pagination, raw query strings and fragments, rejects external/out-of-home/system paths and respects enabled languages. The new service uses it unchanged.
- `class-content.php`, `class-fields.php`, vocabulary and UI readers return usable current/stale translations; missing, blank or invalid translations fall back per field to English. Availability is not a separate page-existence condition. A missing translation therefore still resolves the **same content** under the selected language, with existing English field fallback. No redirect to a homepage or unrelated object is introduced.
- The plugin switcher and the shared desktop/mobile header renderer use `publicly_available()`, including Russian/Turkish launch gates. Neither previously persisted a language preference. Both now pass their existing resolved link through `pera_ml_switcher_url`. This is the only theme change.
- Plugin SEO emits reciprocal public-language hreflang plus English `x-default`, and has a Rank Math canonical compatibility filter. Theme SEO owns most canonical/OG/schema output. These remain unchanged. Core sitemap post/term filtering lives in `inc/modules/sitemaps.php`; this feature does not add, remove or rewrite sitemap entries.
- Core `redirect_canonical` runs at `template_redirect` priority 10; the router keeps translated prefixes on canonical redirects. The theme's `property-archive-safety.php` normalizes array taxonomy filters at priority 1. The service prepares before both, combines same-path normalization destinations, and redirects at priority 11 if neither already redirected.
- Storage/object caches are keyed by translation identity and language, not visitor IP. The repository does not contain a production CDN/reverse-proxy cache configuration, active `advanced-cache.php` drop-in, trusted country source or verified-crawler integration. Existing `DONOTCACHEPAGE`/no-store patterns protect selected theme routes, but cannot control caches before WordPress. Hosting behavior must be verified separately.

## Activation and hosting prerequisites

Set these in `wp-config.php` **before** WordPress loads, initially keeping the first two false:

```php
define( 'PERA_ML_GEO_REDIRECT_ENABLED', false );
define( 'PERA_ML_GEO_CACHE_READY', false );
define( 'PERA_ML_GEO_COUNTRY_SOURCE', 'server' );
define( 'PERA_ML_GEO_COUNTRY_SERVER_VAR', 'GEOIP_COUNTRY_CODE' );
define( 'PERA_ML_GEO_AUDIENCE_SERVER_VAR', 'PERA_ML_VERIFIED_AUDIENCE' );
```

The host must provide non-client-controlled server parameters:

1. `GEOIP_COUNTRY_CODE`: ISO 3166-1 alpha-2 result from a local GeoIP module/database or a verified upstream integration. Alternate non-`HTTP_` parameter names can be configured above. Naming a parameter does **not** establish trust: the web server must set/overwrite it itself. Do not pass arbitrary incoming headers through to it.
2. `PERA_ML_VERIFIED_AUDIENCE`: exactly `human`, `bot`, or absent/unknown. Set `human` only for traffic positively admitted by the host's trusted browser/bot classification. A verified bot must get `bot`; ambiguous/unclassified traffic must remain unknown. A rule such as “all non-Googlebot User-Agents are human” is unsafe. Cloudflare's verified-bot verdict can exempt known bots, but its **false** value alone does not prove a visitor is human. Use a trusted browser classification/challenge or an equivalent host facility for the human verdict.

**Safe crawler alternative:** this repository cannot reliably verify crawler identity itself. There is no hostname/IP list maintained here, no reverse-DNS guessing and no User-Agent-only exception. Country/stored-preference redirects require a positive trusted `human` verdict. Verified bots and all unclassified traffic keep the exact requested language URL. Explicit switcher selections still work for unclassified browsers; verified bots bypass them. If reliable classification is unavailable, leave it unset: automatic redirects safely remain inactive. This hosting dependency is intentional and must not be replaced with an “assume human” default.

Use HTTPS and ensure WordPress `is_ssl()` sees the trusted TLS termination state. Cookies are never issued without HTTPS.

### Cloudflare alternative

```php
define( 'PERA_ML_GEO_COUNTRY_SOURCE', 'cloudflare' );
define( 'PERA_ML_GEO_TRUSTED_PROXY_CIDRS', array(
    // Operator-maintained CURRENT Cloudflare origin-facing IPv4/IPv6 ranges.
    // No permissive example ranges are shipped. Never use 0.0.0.0/0 or ::/0.
) );
```

The service accepts `HTTP_CF_IPCOUNTRY` only if the **actual connection peer** in `REMOTE_ADDR` matches an explicitly configured trusted proxy CIDR. IPv4 and IPv6 are supported. Restrict direct origin access and maintain Cloudflare's current ranges as part of hosting configuration. Do not allow clients to route through a trusted relay that preserves forged country headers. `X-Forwarded-For`, `CF-Connecting-IP` and arbitrary country headers do not establish trust.

If `mod_remoteip`, another reverse proxy, or a load balancer has replaced `REMOTE_ADDR` with the visitor IP, this mode deliberately fails closed. Instead validate the upstream at the web server and map its verified country result to a non-HTTP server parameter, using `server` mode. Audience classification remains a separate requirement in both modes. Missing/invalid results (including Cloudflare `XX`/`T1`) cause no automatic navigation. English is the conceptual default; an incoming translated URL is left intact when detection is unavailable.

After verifying trust, crawler handling and cache bypass in staging, set **both** `PERA_ML_GEO_CACHE_READY` and `PERA_ML_GEO_REDIRECT_ENABLED` to `true` for controlled activation. If either is missing or false, the service registers no hooks: no redirects, cookies, link markers or cache headers are introduced.

## Country mapping

Defaults are in [`../config/country-languages.php`](../config/country-languages.php).

| Countries | Language |
| --- | --- |
| CN, HK, TW, MO | zh |
| DE, AT | de |
| RU, BY | ru |
| SA, AE, QA, KW, BH, OM | ar |
| Other valid ISO countries | en |
| Missing/invalid country | No automatic redirect |

Extend in a site-owned MU plugin to survive plugin updates:

```php
add_filter( 'pera_ml_country_languages', function ( $mapping ) {
    $mapping['CH'] = 'de';
    return $mapping;
} );
```

Only enabled, publicly available registry languages can be selected. A disabled/hidden mapping target safely falls back to English. No admin UI is added.

## Request flow and manual preference

1. Existing router resolves the requested content and restores the public URL.
2. On eligible GET requests, prepare at `template_redirect` -100 and mark the response private/no-store.
3. Verified bots bypass negotiation. A valid `pera_ml_select=<code>` matching the URL's current language records an explicit selection. It overrides a previous cookie and country.
4. For positively classified human traffic, a valid stored `pera_ml_language` preference wins; otherwise use a trusted country result. Country detection **never** writes or overwrites a cookie.
5. Resolve the destination through `url_for_language()`. If language is already correct, emit no redirect. Preserve the exact requested path, pagination and raw query string. English fallback is still rendered per field if translation is absent.
6. Existing same-path canonical/slash/filter normalization redirects are combined with the language destination and become temporary **302** responses. Authentication/external/different-content redirects are left alone. Otherwise the priority-11 callback issues one safe 302. A valid selection parameter is removed from the destination.

Switcher links alone receive the explicit selection parameter; canonical and hreflang URLs stay clean. This works without JavaScript and in new tabs. The server sets a host-only, Secure, HttpOnly, SameSite=Lax first-party cookie scoped to the WordPress home path, expiring in 365 days (12 months). Users can freely select English or another public language; every valid new selection replaces the previous one. Unsupported/array/mismatched selections are ignored. Directly typing a language-prefixed URL is not a persistent manual selection; use the switcher to establish preference. Browsers must accept first-party cookies for persistence. Unknown audience traffic is not automatically redirected even with a preference cookie, preserving crawler access.

## SEO and excluded requests

Only temporary 302 language redirects are emitted. There are no canonical/hreflang/indexability changes. Bots and unknown audience traffic can independently fetch each language URL. No redirects apply to POST/HEAD, admin, AJAX, cron, XML-RPC, REST (including `rest_route`), robots, XML/text/sitemap routes, feeds, previews, embeds, trackbacks, 404s or unresolved/custom endpoints outside supported public main-query contexts. Search and archive pages remain eligible. Country is not inferred from Accept-Language or User-Agent.

Same-path normalizations are composed, rather than creating an extra geo hop. Existing redirects to a different content path (for example an old slug), authentication or domain/HTTPS normalization outside WordPress remain host/core behavior; validate those separately. This feature cannot collapse upstream redirects it never sees.

## Cache requirements

Before setting `PERA_ML_GEO_CACHE_READY=true`, **bypass full-page caching for all eligible frontend HTML requests, in every language**, at CDN, reverse proxy, hosting cache, and any WordPress early page-cache drop-in. Also bypass requests containing `pera_ml_select` and the preference cookie. Purge existing HTML/redirect caches when enabling or disabling. This initial implementation chooses bypass over IP cache fragmentation. Object caches and static assets can remain cached; translation storage keys stay unchanged. Capacity-test the additional origin traffic before activation.

The service sets `DONOTCACHEPAGE`, WordPress no-cache headers and explicit `Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0` on eligible responses, including matching-language, bot and no-country 200 responses. This protects downstream caches that honor response headers; it **cannot** bypass a previously cached response or caches serving before plugins execute. `Vary: Cookie`/country headers alone are insufficient and no IP-based cache keys are added. Cache readiness is an operator assertion, not automatic detection.

## Testing

GitHub Actions workflow `Pera Multilingual regression tests` runs PHP syntax checks, all existing plugin test scripts and the new real-router negotiation harness on PHP 7.4 and 8.3:

```sh
for mode in server cloudflare disabled undefined cache-not-ready; do
    php wp-content/plugins/pera-multilingual/tests/geo-redirect-test.php "$mode"
done
for test in wp-content/plugins/pera-multilingual/tests/*-test.php; do php "$test"; done
```

CI also uses `tests/run-regressions.py` to run every existing script and compare any failure with the PR's unchanged base commit. A baseline failure is reported as a warning only when both the exit code and normalized diagnostics are identical. New or changed failures fail CI. Without `BASE_SHA`, the runner treats every failure as an error. It does not modify unrelated theme or translation behavior to make old tests pass.

Verified [CI run 41199337408](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/41199337408) passed syntax checks and **162 new assertions per PHP version**, including disabled/unconfigured/cache-not-ready modes. The existing suite reported 45 passing scripts plus two identical baseline failures on PHP 7.4, and 46 passing scripts plus one identical baseline failure on PHP 8.3, with zero regressions. The baseline failures are `post-content-chunking-test.php` on PHP 7.4 (private reflection invocation without `setAccessible`) and `theme-ui-registration-test.php` on both versions (existing unrelated theme UI coverage). These remain visible in CI warnings; the entire legacy suite is not claimed to be clean.

Coverage includes English/other-prefix property arrivals; German articles; Russian pages; correct-language and redirect-loop checks; manual English and changed preferences; returning visitors; actual English field fallback for missing translation; pagination; raw filter/search queries; mappings; invalid/missing GeoIP; crawler and unknown-audience bypass; untrusted headers; IPv4/IPv6 proxy trust; sitemap/REST/admin/feed exclusions; normalization composition; cookie attributes; subdirectory paths; canonical/hreflang preservation; and disabled/unconfigured/cache-not-ready behavior. These standalone scripts stub WordPress request functions and do not replace real WordPress/CDN acceptance testing. Production success must not be inferred from them.

## Production verification checklist

- [ ] With the feature off, compare ordinary routes, desktop/mobile/plugin switchers, canonical, hreflang and sitemap output with baseline. No selection markers or preference cookies should appear.
- [ ] Verify actual server country and audience parameters using restricted host diagnostics, from multiple real geographic origins. Do not expose a public diagnostic endpoint or log full cookies/IPs.
- [ ] Attempt forged `CF-IPCountry`, forwarded IP and audience headers through both the CDN and direct origin; confirm they cannot confer trust. Verify IPv6 and actual peer behavior.
- [ ] Verify genuinely identified crawlers and unclassified traffic get each language version directly without geo redirects; a spoofed crawler UA alone must not establish identity.
- [ ] Verify cache MISS/BYPASS and no stored redirects/HTML across successive CN/DE/RU/other-country visitors to the same URL, including already-correct-language 200s.
- [ ] Enable both flags only after prerequisites pass; verify a real property, article, page, paginated archive, filtered archive and search return one 302 to the same content in the expected language. Follow once and confirm no loop.
- [ ] In a China browser select English, check the Secure/HttpOnly/Lax/host-only scoped cookie and 365-day expiry, revisit a translated URL, then switch back to Chinese. Repeat with desktop, mobile and shortcode switchers.
- [ ] Verify missing translations render existing English fallback on the same object and a nonexistent URL remains a 404.
- [ ] Fetch robots.txt, XML sitemaps, feeds, REST and admin/AJAX endpoints: no language redirects. Compare canonical/hreflang and existing sitemap contents.
- [ ] Record checks and capacity impact in the deployment record. No live verification is claimed by this PR.

## Troubleshooting and rollback

No redirect: check both flags, eligible GET main-query context, current language, manual cookie, public-language gates, audience verdict and country source. Missing GeoIP or unknown audience deliberately leaves the requested URL alone. In Cloudflare mode inspect the connection peer and maintained CIDRs; a restored visitor IP will not match. Never “fix” this with a trust-all CIDR or forwarded-header guess.

Wrong language: check the preference cookie before country mapping. Clear only `pera_ml_language` for a clean country test. Missing translated text is the existing field fallback, not a routing failure. If manual choice does not persist, check HTTPS detection, cookie scope and browser cookie acceptance. If users see another visitor's redirect, immediately disable the flag and inspect every cache layer.

Rollback: set `PERA_ML_GEO_REDIRECT_ENABLED=false` (or remove it), purge frontend HTML and redirect caches and verify baseline routes. Existing cookies are ignored while disabled and can expire naturally or be removed with the same home-path scope. Keep the multilingual plugin active so existing translations and routes remain available. No rewrite flush or translation-data rollback is required. Reverting this PR removes the feature entirely; no database cleanup is needed.
