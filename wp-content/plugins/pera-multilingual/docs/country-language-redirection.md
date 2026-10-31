# Country-based language redirection

**Disabled by default.** No live configuration, installation, hosting change or deployment is performed by this PR. Enable only after the checklist below. Requirements: PHP 7.4+ (tested also on production-target PHP 8.2), a 64-bit runtime, and the optional locked Composer reader for MMDB mode.

## Confirmed hosting and current browser policy

The completed hosting checks confirm PHP 8.2, Composer at `/usr/local/bin/composer`, working WP-CLI cron every minute, DNS resolution with `gethostbyaddr()` and `dns_get_record()`, a writable private home directory, and direct Apache hosting without an identified CDN. Further completed checks confirm geoipupdate v8.0.0 at `/home/d21882/bin/geoipupdate`, private configuration at `/home/d21882/private/pera-geo/GeoIP.conf`, MaxMind reader v1.14.0, and a staged Country database that passed the CLI validator metadata/IPv4/IPv6 checks. These are user-confirmed capabilities, not live checks performed by this PR. No hosting settings are edited here.

Ordinary browser document navigation is eligible for an **immediate first-request** country redirect once the feature flags and reliable country database/source are configured. Neither a missing PTR record, an empty DNS cache, failed DNS nor an expired non-bot result blocks the browser. Crawler-like requests bypass geographic negotiation immediately; forward-confirmed cached crawler identity is an additional exemption. Unknown traffic without browser-navigation evidence stays on its requested URL. All existing preference, mapping, route, flag and header safeguards remain in place.

## Architecture audit and follow-up design

English remains the sole WordPress object. The language registry controls enabled languages and Russian/Turkish public launch gates. `class-router.php` strips enabled prefixes at `plugins_loaded` priority 1, lets WordPress/theme routing resolve the canonical object and restores the browser URI at `parse_request` priority 0. `url_for_language()` preserves deep paths, query strings, archive pagination and search while replacing existing prefixes. No second translation system or translated slug table is added.

Content, ACF fields, menu, UI and vocabulary readers use current/stale stored translations, falling back per field to English. Translation availability does not define a separate page's existence. Selecting a language with missing translations still renders the same object with existing English fallback. No provider is called while serving a visitor. Canonical/OG/schema output is principally theme-owned; plugin hreflang and Rank Math compatibility remain unchanged. Existing core sitemap filtering remains untouched. Desktop/mobile and plugin switchers pass resolved links through the single `pera_ml_switcher_url` filter.

[Follow-up comment 6543444810](https://github.com/7mus7y-dotcom/pera-wp/pull/1533#issuecomment-6543444810) reports direct Apache/cPanel hosting with no GeoIP extension/module, configured country environment, CDN or active page-cache plugin. The architecture/design was recorded here before broad follow-up code changes. The decisions are:

- Coordinate explicitly with the actual theme cache module using a final `send_headers` hook, repeat before template output and enforce at PHP's final header boundary.
- Retain trusted server/Cloudflare GeoIP and add a maintained, locked pure-PHP local MMDB reader. No visitor-IP API or per-page external request.
- Retain a trusted audience service when available. Offer an explicitly opted-in browser policy with background DNS verification for this host, without making DNS confirmation a prerequisite for ordinary visitors. Document its limits instead of labelling unknown visitors verified humans.
- Keep both feature/cache-readiness flags off, preserve manual preference priority, and use the enabled registry for route eligibility (no assumption that Chinese is enabled).

## Execution and cache-header integration

When both flags are true:

1. After WordPress resolves the main query, the theme's `send_headers` priority-20 callback applies its existing policy. The plugin's `send_headers` hook at `PHP_INT_MAX` then protects every eligible response and registers a final PHP header callback. This includes English, translated, matching-language, unknown-country and crawler 200 responses.
2. `template_redirect` priority -100 plans preference/country negotiation and repeats cache protection. Existing core canonical normalization (priority 10) and theme property-filter normalization (priority 1) can compose their same-path destination with the selected language in a single 302. Different-content, authentication and external redirects retain existing behavior.
3. At priority 11, if there is a different destination, issue a safe temporary **302**. Otherwise continue rendering the original resolved object.
4. A final template hook and the PHP header callback enforce the policy even if later PHP code writes public headers. The callback only registers for eligible requests while enabled. No body buffering is introduced.

Final eligible response headers are:

```text
Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0
Expires: Wed, 11 Jan 1984 05:00:00 GMT
Pragma: no-cache
```

`DONOTCACHEPAGE` is set and ETag/Last-Modified are removed. The actual theme's disabled-state public TTLs remain home/property archive 300s, property single 600s, district taxonomy 900s, blog post 1800s; filtered and logged-in no-store behavior also stays unchanged. No new hooks register when either flag is absent/false. PHP has one final header callback slot: another component replacing that callback after the plugin requires integration testing; the two hook-based safeguards still apply.

Apache `mod_expires` does not add/replace Expires when the application already supplies it. The reported `ExpiresByType text/html "access plus 10 minutes"` therefore must not replace these application headers under standard mod_expires behavior. This PR does not edit live `.htaccess`. Apache `Header set/always set`, cPanel accelerators, reverse proxies or a future CDN can still override/serve before PHP. Inspect the **actual final production response**, and remove conflicting HTML cache rules before activation. HTTP integration tests exercise the actual theme PHP module; they do not claim to exercise the live Apache configuration.

Full-page caches at every layer must bypass all eligible HTML, including English/no-redirect responses, explicit selection parameters and preference cookies. Purge existing HTML/redirect caches when toggling. Object caches and static assets can remain enabled; no IP cache fragmentation is added. Capacity-test the origin impact. `PERA_ML_GEO_CACHE_READY` is the operator's assertion that those checks passed, not automatic cache detection.

## Flags and configuration

Set constants in `wp-config.php` before WordPress loads. Examples below are instructions only; no live changes are included.

```php
define( 'PERA_ML_GEO_REDIRECT_ENABLED', false );
define( 'PERA_ML_GEO_CACHE_READY', false );

// Direct Apache/cPanel deployment:
define( 'PERA_ML_GEO_COUNTRY_SOURCE', 'mmdb' );
define( 'PERA_ML_GEO_MMDB_PATH', '/home/CPANEL_USER/private/pera-geo/GeoLite2-Country.mmdb' );
define( 'PERA_ML_GEO_CRAWLER_MODE', 'dns' );
```

Only after host/staging checks pass, explicitly set both flags to true. Removing/setting either false disables all new hooks, cache changes, redirects and switcher selection markers. There is no admin UI or database migration.

## Local GeoLite2 Country deployment

The plugin pins `maxmind-db/reader` **1.14.0** (MaxMind's maintained Apache-2.0 PHP reader) in `composer.json`/`composer.lock`. It supports PHP 8.2 and does not require a compiled GeoIP extension. Install dependencies in a build/staging workspace and include `vendor/` in the deployment artifact:

```sh
cd wp-content/plugins/pera-multilingual
/usr/local/bin/composer validate --strict
/usr/local/bin/composer install --no-dev --prefer-dist --no-interaction --no-scripts
```

Do not run Composer in a visitor request. Missing dependencies produce no country result, not a fatal error. Update the pinned dependency and lock through reviewed maintenance, run this suite and Composer audit in the build process. The optional MaxMind C extension is unnecessary; an incompatible pre-existing extension must be removed/upgraded to the locked reader's compatible range.

Create a MaxMind account, accept the [GeoLite2 terms/license](https://www.maxmind.com/en/geolite2/eula), obtain a license key and install/configure the official [geoipupdate](https://github.com/maxmind/geoipupdate) updater as a hosting maintenance tool. The reader license and GeoLite data license are separate. GeoLite downloads are authenticated; no account/key/data is included in Git. Preserve required MaxMind attribution and comply with current update, deletion and redistribution terms. Attribution: This product includes GeoLite2 data created by MaxMind, available from https://www.maxmind.com/.

Use a private directory **outside every domain's webroot**, not uploads, the plugin, a public temp directory or `public_html`. Runtime checks resolve symlinks and reject databases within `ABSPATH` or the active `DOCUMENT_ROOT`; the operator must check other domain roots and aliases. Recommended directory permissions: 0700 (or tightly scoped 0750), database 0600/0640, readable by the site's PHP identity. Restrict GeoIP.conf/license-key access to the maintenance identity. Never expose a public diagnostics endpoint.

Example private updater configuration (replace values privately):

```text
AccountID YOUR_ACCOUNT_ID
LicenseKey YOUR_SECRET_LICENSE_KEY
EditionIDs GeoLite2-Country
DatabaseDirectory /home/CPANEL_USER/private/pera-geo/staging
```

### Production maintenance script and exact cron

The reviewed script is [`../tools/update-geolite2-country.sh`](../tools/update-geolite2-country.sh). It runs independently of WordPress, does not change wp-config/options or redirect flags, and refuses HTTP/CGI invocation. It requires only the cPanel filesystem owner and standard Bash/GNU coreutils/util-linux tools (`flock`, `timeout`, `stat`, `mktemp`, `cp`, `chmod`, `mv`, `rm`, `date`), plus the confirmed updater, CLI PHP and reader. Root access is not required.

Default server paths (no placeholders):

| Purpose | Path |
| --- | --- |
| Updater | `/home/d21882/bin/geoipupdate` (v8.0.0) |
| Private configuration | `/home/d21882/private/pera-geo/GeoIP.conf` |
| Staged database | `/home/d21882/private/pera-geo/staging/GeoLite2-Country.mmdb` |
| Active database | `/home/d21882/private/pera-geo/GeoLite2-Country.mmdb` |
| Lock | `/home/d21882/private/pera-geo/maintenance.lock` |
| Private log | `/home/d21882/private/pera-geo/maintenance.log` |
| Installed maintenance script | `/home/d21882/public_html/wp-content/plugins/pera-multilingual/tools/update-geolite2-country.sh` |
| Adjacent validator | `/home/d21882/public_html/wp-content/plugins/pera-multilingual/tools/validate-geoip-db.php` |

The live WordPress root is recorded by the repository's `sync.sh`. The script locates the validator relative to itself, so the deployment must include both tools and the installed Composer reader. Its fixed PATH is `/usr/local/bin:/usr/bin:/bin`; `php` must resolve to the confirmed PHP 8.2 CLI. If that account selects PHP differently, set `PERA_GEO_PHP` to the verified absolute CLI executable when invoking it. No additional Composer installation is performed by maintenance.

After separately reviewing/deploying the tools (not done by this task), add this **exact daily crontab entry as user d21882**:

```cron
17 3 * * * /bin/bash /home/d21882/public_html/wp-content/plugins/pera-multilingual/tools/update-geolite2-country.sh >/dev/null
```

This runs at **03:17 in the server/crontab timezone**, independently of the existing one-minute WP-CLI crawler cron. Bash invocation avoids reliance on an executable bit from the sync/deployment process. Sanitized errors remain on stderr for cPanel cron email; configure cPanel's cron notification address if email alerts are wanted. The script also appends UTC-stamped, sanitized messages to the private log. It never prints the private configuration, account/license key or raw updater/PHP diagnostics. Do not enable shell tracing or replace this with a verbose geoipupdate command in cron.

Maintenance flow:

1. Check that private/staging directories are user-owned, writable, not symlinks and not group/world writable; reject the WordPress webroot. Check that configuration is a user-owned readable regular file with **0600/0400** permissions, and reject unsafe active/staging/lock/log symlinks. The operator must still ensure the private directory is outside every domain's webroot/aliases.
2. Acquire nonblocking `flock` on `maintenance.lock` for the full run. An overlapping run exits 75 without touching the database. The lock file is deliberately retained; kernel locks release when descriptors/processes close. Do not delete the lock file to force concurrent execution.
3. Run `/home/d21882/bin/geoipupdate -f /home/d21882/private/pera-geo/GeoIP.conf -d /home/d21882/private/pera-geo/staging`. The explicit `-d` overrides any wrong DatabaseDirectory in the configuration and protects the active location. Updater output is discarded because third-party diagnostics might contain secrets; failures log only a fixed phase and numeric subprocess status.
4. Run the existing validator on the staged file. Only a successful download and validation permit further work.
5. Use `mktemp` **inside the active database's directory**, copy the staged bytes into that private 0600 candidate, then validate the copied snapshot again. This catches staging changes between the first validation and copy; the exact file promoted has passed validation. Staging itself can be on another filesystem because it is copied, never directly renamed over active.
6. Replace the active filename with `mv -fT` from that same-directory candidate. This is a same-filesystem atomic rename: requests see the old valid file or the new validated file, without a missing/partial active-file window. The active database is never truncated or deleted first. No root service restart is needed.
7. Clean up unfinished candidates and log success. Every pre-promotion failure leaves the active database and its inode intact. Candidate cleanup failures (for example permissions changed during a run) are logged for operator cleanup; any leftover candidate is private and never becomes active. A hard kill/power loss can leave a candidate too. Atomic visibility is not a claim of crash-proof storage durability or a permanent historical backup.

Download timeout defaults to 300 seconds and each validator invocation to 60 seconds; GNU timeout terminates subprocess groups and escalates after 15 seconds. INT/TERM trigger cleanup. The bounded worker lock remains held while a timed-out/terminating child is being stopped. Optional operator overrides: `PERA_GEO_DIRECTORY` (private root), `PERA_GEO_UPDATER`, `PERA_GEO_PHP`, `PERA_GEO_UPDATE_TIMEOUT` (1–3600 seconds), `PERA_GEO_VALIDATE_TIMEOUT` (1–300 seconds). The configuration, staging, active, log and lock paths derive from the private root. Overrides are for trusted operator/test use, never HTTP input.

| Exit | Meaning |
| --- | --- |
| 0 | Validated database promoted successfully |
| 10 | Invalid/missing prerequisite, unsafe path/permissions or HTTP invocation |
| 20 | Updater failed or timed out |
| 30 | Staged database missing/unsafe, or staged/copied snapshot validation failed/timed out |
| 40 | Candidate creation/copy/permissions or atomic promotion failed |
| 70 | Unexpected script failure |
| 75 | Another run holds the maintenance lock; run skipped |
| 130 / 143 | Interrupted / terminated; unfinished candidate not promoted |

Failures before the atomic commit retain the last known-good active database. After a successful commit, the new validated file is active; this script does not retain an unlimited archive of old copies. Existing runtime stale-database protection still applies if updates continue to fail. Monitor the private log and cron stderr, alert on repeated failures/no recent success, and rotate/archive the small log privately under the same user. Keep current MaxMind license/update/deletion obligations; no database or credentials are committed to Git.

Runtime also validates Country database type and build timestamp. Default maximum age is **30 days**; stale data fails closed to no country redirect. Optional `PERA_ML_GEO_MMDB_MAX_AGE` is in seconds and must be positive; do not extend it contrary to data-license/update obligations. Missing files, absent reader, unreadable/corrupt MMDB, future/old builds, unknown IP records or missing/invalid `country.iso_code` return no country. `registered_country` is deliberately not substituted. IPv4, IPv6 and mapped IPv4 are supported. No IP-based object-cache entries are created for country lookups.

## Client-IP and trusted GeoIP options

On direct Apache, use the actual connection peer `REMOTE_ADDR`. Invalid/private/reserved addresses fail closed. Arbitrary `X-Forwarded-For`, CF-Connecting-IP and other HTTP headers are ignored.

If a future proxy is installed, establish client identity at the web server with a maintained trusted-peer list and validated overwrite of a **non-HTTP server parameter**, then explicitly set `PERA_ML_GEO_CLIENT_IP_SERVER_VAR` to that parameter. Never simply copy an incoming forwarded header. Apache mod_remoteip is acceptable only when properly restricted to trusted proxies; verify what PHP actually sees. DNS/MMDB cannot safely identify visitors from an un-restored shared proxy IP.

Existing country modes remain:

```php
// Host-owned module/environment result; not an unchecked client header.
define( 'PERA_ML_GEO_COUNTRY_SOURCE', 'server' );
define( 'PERA_ML_GEO_COUNTRY_SERVER_VAR', 'GEOIP_COUNTRY_CODE' );
```

`auto` uses a valid value from that explicitly trusted server parameter, then falls back to local MMDB. `mmdb` uses only the database. Neither silently trusts HTTP country headers.

`cloudflare` mode accepts `HTTP_CF_IPCOUNTRY` only when the actual peer in `REMOTE_ADDR` belongs to operator-maintained `PERA_ML_GEO_TRUSTED_PROXY_CIDRS` (IPv4/IPv6). No trust-all/default proxy ranges ship. Restrict origin access and verify the proxy overwrites the header. If mod_remoteip replaces the peer, map validated country data to a server variable instead of weakening this check. Local DNS mode refuses Cloudflare requests without an explicitly restored client-IP parameter; otherwise it would verify the proxy rather than the visitor.

Missing/invalid countries (including Cloudflare XX/T1) cause no automatic navigation. English is the conceptual fallback; an already-prefixed URL remains accessible when detection is unavailable.

## Crawler policy: trusted service or bounded local alternative

The existing non-HTTP `PERA_ML_VERIFIED_AUDIENCE` server parameter remains authoritative when exactly `human` or `bot`. Its configurable name is `PERA_ML_GEO_AUDIENCE_SERVER_VAR`. The host must set/overwrite it through a reliable service; an incoming HTTP header or a false verified-bot flag alone does not establish a human. If no local mode or reliable service is configured, country/stored-preference redirects remain inactive; explicit selections still work.

For direct cPanel without a trusted audience service, opt into `PERA_ML_GEO_CRAWLER_MODE='dns'`. This existing mode name is retained for compatibility; DNS is now an additional crawler safeguard, **not an admission test for visitors**.

1. Read any fresh cached bot result for the actual public client IP. A verified bot bypasses negotiation regardless of its User-Agent. The client-IP trust rules above still apply.
2. Conservatively bypass crawler-like User-Agents (including Googlebot) immediately, whether or not DNS has run. This is an exclusion hint, **not verified identity**.
3. Allow an ordinary browser document navigation immediately, including first-time visitors and addresses without PTR records. Browser evidence means a `Mozilla/` User-Agent plus `Sec-Fetch-Mode: navigate` and `Sec-Fetch-Dest: document`. `Sec-Fetch-User` is not required. For older browsers with neither Fetch Metadata field, a `text/html` Accept header is sufficient. Explicit non-navigation metadata (such as CORS/resource fetches) cannot fall back to Accept.
4. Leave other/unknown non-browser traffic on the requested language URL. A valid explicit manual language selection still works without browser/DNS evidence. Manual preferences continue to override country whenever automatic negotiation applies.
5. Queue uncached/expired addresses for bounded background verification, including crawler-hinted traffic. No frontend reverse DNS, forward DNS or external IP API call occurs. A full queue, failed/absent PTR, DNS errors or an expired non-bot result never delays an otherwise eligible browser redirect.

The shared cron worker processes **at most five addresses**. It reverse-resolves an IP and forward-resolves the returned hostname with A/AAAA records. A byte-equivalent address must match before provider identity is accepted. Boundary-matched suffixes for Google, Bing, Yahoo, Yandex, Baidu, DuckDuckGo, Apple, Petal, Sogou and Naver are verified bot exemptions. The provider inventory is explicit in `class-crawler-detection.php` and requires maintenance. Forward-confirmed other names and lookup failures are retained briefly only to avoid repeated DNS work; neither is required to permit a browser.

**Identification limits:** User-Agent, Accept and all browser-navigation headers are spoofable. They are policy hints, not proof of human identity. An unrecognized crawler that mimics ordinary browser navigation may receive a country redirect on its first request, or later if DNS cannot identify it. A spoofed crawler User-Agent can avoid negotiation. Expired/evicted verified-bot entries no longer provide an IP exemption until refreshed, although crawler hints still bypass immediately. Shared/NAT addresses can also conservatively exempt ordinary users when a bot result is cached. Perfect crawler identification is impossible without trusted infrastructure. Use the trusted audience-service alternative or leave the feature disabled if these SEO tradeoffs are unacceptable; this policy does not claim universal bot detection.

Queue/cache limits remain 64 pending addresses, five checks per event and 256 hashed IP entries. Bot results expire after 24h, non-provider results after 6h, errors after 5m; queued work older than 10m is discarded. Capacity/concurrent-update losses can delay verification, but not ordinary browser redirects. The worker lock expires after five minutes. Options do not autoload. Raw IPs occur only in the bounded pending queue; cached SHA-256 keys are still personal/pseudonymous data. Reflect this processing in privacy/retention notices. Expired data is removed on subsequent activity; delete the options to remove inactive residual state.

**Confirmed infrastructure and remaining setup:** the existing one-minute WP-CLI cron and DNS functions can support the worker. Confirm that the scheduled `pera_ml_verify_crawler_queue` event is actually being processed after activation; do not add a duplicate cron unnecessarily. Alternatively the site's normal WP-CLI can invoke `wp eval '(new Pera_ML_Crawler_Detection())->work();'` under the site identity. PHP resolver calls have no per-call timeout controls: retain a process timeout/non-overlap lock and appropriate local resolver timeouts for background work. A cron/DNS outage reduces verified IP exemptions but does not block browser negotiation or the immediate crawler-UA bypass. Country detection still fails closed when the trusted GeoIP source/database is missing, stale or invalid.

## Mapping and manual preference

PHP defaults: [`../config/country-languages.php`](../config/country-languages.php).

| ISO countries | Language |
| --- | --- |
| CN, HK, TW, MO | zh |
| DE, AT | de |
| RU, BY | ru |
| SA, AE, QA, KW, BH, OM | ar |
| Other valid ISO countries | en |
| Missing/invalid country | No country redirect |

Extend through the site-owned `pera_ml_country_languages` filter, e.g. `$mapping['CH']='de'`. Only enabled/public registry languages are selected; unavailable mapping targets fall back to English. Route eligibility probes an actually enabled alternate, so disabling Chinese does not disable German/Arabic negotiation.

Priority: valid explicit switcher selection, valid stored preference, reliable country, English conceptual fallback. Explicit `pera_ml_select` must match the URL's current enabled/public language. It overrides stored preference/country and sets a host-only Secure/HttpOnly/SameSite=Lax first-party cookie, `pera_ml_language`, for 365 days (12 months), scoped to the home path. The selection parameter is cleaned with a 302. Users can choose English in China and change again freely. Country detection never writes a cookie. Verified bots bypass negotiation; explicit selections work without a DNS/human result, while automatic preference/country redirects obey the crawler policy. Directly typing a prefix is not a persisted manual selection. Persistent preferences require HTTPS, trusted TLS detection and accepted first-party cookies.

## SEO safeguards and exclusions

Language redirects are temporary 302 only. Resolve the same requested content with the unchanged router; preserve raw query strings, filters, pagination and searches. Matching destinations cause no redirect/loop. Missing translations retain English per-field fallback. Canonical, hreflang, x-default and sitemap content stay unchanged and clean. No rewrite flush is needed. Same-path existing normalization is composed; existing old-slug, domain, HTTPS and other upstream/different-content redirects cannot always be collapsed by this plugin.

No country redirects apply to admin, AJAX, cron, XML-RPC, REST including rest_route, robots.txt, sitemaps/XML/text, feeds, previews, embeds, trackbacks, 404s, non-GET methods or unsupported/unresolved main-query contexts. All language versions remain directly accessible to recognized/verified crawlers and unknown traffic without browser-navigation evidence. Browser-looking unrecognized crawlers are subject to the documented identification limits.

## Tests and limits

CI `Pera Multilingual regression tests` runs PHP 7.4, **8.2** and 8.3, validates/installs the pinned reader, syntax-checks plugin PHP, runs existing regressions, actual-reader synthetic MMDB tests and real HTTP final-header tests. The MaxMind test fixture is synthetic, licensed upstream test data pinned to a commit; it is not a distributed GeoLite database.

```sh
for mode in server cloudflare disabled undefined cache-not-ready dns; do
    php wp-content/plugins/pera-multilingual/tests/geo-redirect-test.php "$mode"
done
python3 wp-content/plugins/pera-multilingual/tests/geo-http-test.py
bash -n wp-content/plugins/pera-multilingual/tools/update-geolite2-country.sh
python3 wp-content/plugins/pera-multilingual/tests/geolite-maintenance-test.py
# PERA_ML_TEST_MMDB must point to the private synthetic fixture, as in the workflow.
for mode in normal missing corrupt stale webroot autoload-missing; do
    php wp-content/plugins/pera-multilingual/tests/local-geoip-test.php "$mode"
done
php wp-content/plugins/pera-multilingual/tests/crawler-detection-test.php
python3 wp-content/plugins/pera-multilingual/tests/run-regressions.py
```

The HTTP harness loads the actual theme `performance.php` and request service, runs hooks in order through PHP's HTTP SAPI, and inspects final response headers. It covers the five reported public TTL route types, pages/search, English, no-redirect bot/unknown cases, late public writes, filtered/logged-in requests, disabled/cache-not-ready behavior and 302/matching-language paths. It stubs WordPress query functions and is not a full WordPress/Apache deployment test. DNS tests use deterministic resolver callbacks; no successful live crawler/production test is claimed.

Maintenance tests use temporary private directories and synthetic updater/PHP executables, never production files or a MaxMind download. They verify last-good bytes/inode preservation on failure, non-overlap locking, sanitized logs with a fake credential, timeout/signal handling, symlink/permission checks, copied-snapshot validation, cleanup and same-filesystem candidate promotion. CI also promotes MaxMind’s synthetic fixture through the **actual** PHP reader/validator. The negotiation suite verifies that each prepare call reads audience classification once and reuses it.

The regression runner compares any existing failure with unchanged PR base, requiring identical exit status and normalized diagnostics before reporting a visible baseline warning. New/changed failures fail CI. Known baseline issues: PHP-7.4 private ReflectionMethod access in `post-content-chunking-test.php`; unrelated theme UI registration coverage in `theme-ui-registration-test.php`. These are not hidden or fixed by changing unrelated theme functionality.

## Production verification checklist

- [ ] With flags off, compare actual theme TTL/Expires/Pragma on home, property archive, property single, district, blog, filters and logged-in routes. Confirm switchers/canonical/hreflang/sitemaps match baseline.
- [ ] The supplied checks already confirm reader v1.14.0 and validator metadata/IPv4/IPv6 success. After reviewed deployment, verify the maintenance script’s PHP resolution and private permissions, run it as d21882, confirm the sanitized success log, then install the documented daily cron with monitoring. Confirm no database/config/key is web-accessible. This PR does not run the live maintenance script or install cron.
- [ ] Verify PHP's real peer/TLS state. Attempt forged country, audience and forwarded headers through the actual connection; they must not override identity or local lookups.
- [ ] Exercise first-time German, Chinese and Arabic browser arrivals with an empty DNS cache and missing/failed PTR. Confirm immediate country redirects, immediate Googlebot bypass, cached verified-bot bypass despite a browser UA, unknown non-browser bypass, and manual preference overrides. Verify the existing cron handles the bounded queue and confirm provider DNS/timeout/cache expiry behavior.
- [ ] On staging, enable both flags and inspect final Apache response headers (including mod_expires, Header directives and cPanel caches), not PHP helper output. All eligible 200/302 responses must have the exact private/no-store policy and past Expires/Pragma. Purge old caches and alternate countries/preferences at one URL to rule out contamination.
- [ ] Verify real property/article/page, paginated/filtered archive and search destinations are the same content, one temporary hop, no loops. Verify missing translations use English fallback and missing content stays 404.
- [ ] Manually select English from China, verify secure cookie/expiry/scope, return, and switch again using desktop/mobile/plugin switchers. Confirm country never overwrites preference.
- [ ] Verify robots, sitemaps, REST, feed, AJAX and admin bypass, and compare canonical/hreflang output. Capacity-test cache bypass. Record results before controlled production activation.

## Troubleshooting and rollback

No negotiation: check both flags, eligibility, public languages, manual cookie, GeoIP source/DB age and audience policy. For browser/DNS mode check actual IP and navigation hints; first-time browsers must not wait for DNS. Missing browser evidence deliberately bypasses. Inspect queue/cron/resolver only for background crawler verification, not browser admission. Wrong country: inspect configured local DB/source and trusted peer model, never add a trust-all proxy rule. Missing translated copy is the existing field fallback.

Wrong headers: disable immediately, purge caches, inspect actual final Apache/CDN headers and any late header-callback replacement. A response served before WordPress cannot be fixed by PHP headers. Manual preference failure: check HTTPS detection, home-path cookie scope and browser acceptance.

Rollback: set `PERA_ML_GEO_REDIRECT_ENABLED=false`/remove it and purge HTML/redirect caches. Keep the multilingual plugin active for existing translations. Cookies are ignored while disabled. Stop optional crawler/update maintenance tasks if no longer needed; optionally remove `pera_ml_crawler_queue`, `pera_ml_crawler_cache` and `pera_ml_crawler_worker_lock` options and clear the `pera_ml_verify_crawler_queue` scheduled event using normal WordPress tools. Remove private DB/license data in accordance with MaxMind terms if discontinuing service. No translation-data rollback or rewrite flush is needed. No merge or deployment is part of this task.
