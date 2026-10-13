# Nearby Amenities: implementation audit and operations

This is a targeted follow-up on draft PR #1419 and its existing `feature/nearby-amenities` branch. No deployment, merge, property-data edits, taxonomy changes or alteration of existing map/listing functionality is included.

## Audit findings and corrections

| Area | Finding in the initial PR | Targeted correction |
| --- | --- | --- |
| Placement | Already immediately after the location grid and before pricing; shares the existing content rail. | Preserve the exact template placement and map markup. |
| Hierarchy | Component `h3` and subgroup `h4` incorrectly implied subordination to “About this project”; that heading is optional. | Component `h2` peers with other property sections; subgroup `h3`. |
| Presentation | Plain lists lacked group boundaries and inline distances were hard to scan. | Existing compact cards and responsive two-column grid; reusable POI lists with distance at the opposite edge. |
| Airports | Ordinary 3.5 km POI filtering usually excluded Istanbul's airports. | Independently cached, versioned local IST/SAW reference data with a 150 km relevance range. Remove airports from the ordinary query; ignore old cached aerodromes. |
| RTL | Initial browser checks used document direction alone. Existing Pera RTL CSS resets body layout to LTR. | Explicit reusable component opt-in in the existing RTL stylesheet; test the actual stylesheet and body class. |
| Cron | Scheduling works with `DISABLE_WP_CRON=1`, but visitor-triggered execution does not. | Document system cron and add bounded, resumable WP-CLI warming using the existing provider gate. |
| Readiness | Initial documentation overstated the need for manual UI registration. | Real Pera UI automatically registers encountered labels and supplies English fallback. Bulk discovery/translation is maintenance, not a requirement for basic display. |
| Provider handling | Some malformed element/tag/member shapes could generate warnings. | Guard shapes, string tags, positive numeric OSM IDs and valid route-member references before normalization. |

## Exact template placement and rendering conditions

`wp-content/themes/hello-elementor-child/single-property.php:1334` calls `pera_render_nearby_amenities($map)` directly inside `section#property-location`, after the closing tag of the existing `div.grid-2--tight` containing `.property-location__main` and `.property-location__aside`, and before the section closes. The next section is `#property-price-range`. No markup in the map, description, facilities, address, map actions or distance description changes.

The outer `main.content-rail` and existing `.content-rail > section:not(.hero) > *` rule constrain both the original grid and the component to `--container`. Existing section padding and `mt-md` separate it from the map. The component is not placed inside the map column and spans the same rail as the location content. Desktop/tablet group cards use the existing `grid-2`; at the existing 768 px breakpoint they stack into one column.

The renderer reads only the existing ACF `map` field's `lat` and `lng`. Coordinates must be numeric, finite, and within world bounds; zero is valid. Invalid/missing coordinates render nothing and create no cache or job. Valid coordinates read shared ordinary-POI caches and the separate airport cache. If neither contains usable relevant results, nothing renders. Istanbul properties can render verified airports immediately even with a cold/empty/unavailable ordinary cache; other categories show translated empty states until usable data exists. Transport is selected when it has data; otherwise the first nonempty category is selected. No browser-side insertion, external POI fetch or fabricated empty-state places.

## Frontend appearance and CSS reuse

Each category contains clearly separated subgroup cards with an `h3` and up to three nearest rows. Each row links the original/localised source name to its OSM location and aligns the distance to the opposite edge. Names wrap without pushing distance outside the card. RTL reverses the row geometry and tab arrow navigation; `bdi` protects original names and formatted distances.

Existing classes reused:

- `card-shell`, `p-sm`: card surface, border, radius, shadow, stacked gap and compact padding.
- `grid-2`: existing desktop/tablet two-column layout and mobile single column, with a smaller existing gap than `grid-2--tight`.
- `mt-md`, `text-soft`, `text-nowrap`: component spacing, secondary typography and stable distance labels.
- `section-cta`, `justify-start`: responsive wrapping tabs aligned to the start.
- `pill`, `pill--lg`, `pill--active`: tab appearance and selected state.
- `inline-row`, `icon`, `#icon-map`: existing heading/icon infrastructure.

All selectors added by this feature, including the initial PR:

| Selector | File | Why an existing rule was insufficient |
| --- | --- | --- |
| `.component-tabs [role="tab"]` | Theme `css/main.css` | Generic pills do not guarantee a token-based 48 px touch target or pointer cursor. |
| `[data-component-tabs] [role="tabpanel"][hidden]` | Theme `css/main.css` | Keeps inactive panels hidden if other layout styles set display. |
| `.component-tabs [role="tab"]:focus-visible` | Theme `css/main.css` | Brand-token outline and spacing-token offset are clearer than the existing pale pill focus shadow. |
| `.poi-list` | Theme `css/main.css` — new in follow-up | Existing reset lists belong to navigation/timelines; checklists add tick icons. None supplies a clean semantic POI list with token-based row gaps. |
| `.poi-list > li` | Theme `css/main.css` — new in follow-up | A `minmax(0,1fr) max-content` grid keeps long names and opposite-edge distances in the same row. Existing `card-meta-row` wraps and adds margins; flex utilities alone do not provide this column constraint, gap or long-name wrapping. |
| `body.pera-ml-rtl [data-component-tabs]` | Plugin `assets/css/rtl.css` — new in follow-up | Opts the reusable component back into RTL after the existing body LTR reset, following the plugin's convention. Document `dir` alone was insufficient. |

No new property-specific CSS, copied card/grid/pill declarations, hardcoded new colour/spacing values, inline styles, framework or JS dependencies. Native `ul`/`li` remain semantic; `role="list"` preserves list announcement with marker-free styling. Existing unrelated card/pill/grid styles remain unchanged in computed-style tests.

## Verified, separately cached airports

`inc/data/nearby-airports.php` contains only these two large airports with scheduled service, checked against `airports.csv` in [OurAirports revision 132fc8c3471c96cfffe3ece8a89f9f252f670243](https://github.com/davidmegginson/ourairports-data/blob/132fc8c3471c96cfffe3ece8a89f9f252f670243/airports.csv) on 2026-10-13:

| Airport | ICAO / IATA | Latitude | Longitude |
| --- | --- | --- | --- |
| İstanbul Airport | LTFM / IST | 41.274874 | 28.732136 |
| Istanbul Sabiha Gökçen International Airport | LTFJ / SAW | 40.898602 | 29.3092 |

These are dataset reference coordinates, not terminal entrances or walking/driving endpoints. Names remain source names, with IATA identifiers appended. Source verification means matching the pinned published dataset, not independently surveying the coordinates. [OurAirports data terms](https://ourairports.com/data/) release the data to the public domain.

`pera_nearby_airports()` sorts the two real records by Haversine distance from the exact property coordinates, excludes those beyond 150 km and caches the computed results (including empty results) for 30 days. Its versioned cache namespace `pera_airports_20261013_*` is independent of ordinary OSM cell caches; no runtime third-party airport requests. Both airports remain in Transport. Airport records in older ordinary caches are ignored to prevent duplicates. The visible distance notice differentiates the ordinary 3.5 km range from the airport range and explicitly says straight-line, not walking/driving distance. OSM attribution and separate OurAirports attribution are visible.

Maintenance: review these exact two source records when updating the dataset; update coordinates/names and source revision/date, bump the dataset `version` to invalidate airport caches, then rerun distance tests. No expansion of the general query radius or automatic worldwide-airport import.

## Ordinary POIs and provider limits

Ordinary OSM data remains shared between properties rounded to a 0.01-degree geographic cell. A 4.3 km Overpass circle covers the 3.5 km property range plus maximum cell-rounding offset. Distances are computed from each property's actual coordinates. Only three nearest distinct same-name/nearby places per group enter HTML; no full hidden provider dataset.

The endpoint defaults to `https://overpass-api.de/api/interpreter` and supports `pera_nearby_overpass_endpoint` for an operator-managed service. Query timeout: 20 seconds; HTTP timeout: 25 seconds; query memory: 16 MiB; response cap: 8 MiB; no redirects. Site URL identifies the User-Agent. No routing geometry, recursive route expansion, city-wide fetch or frontend HTTP.

Seven-day freshness; up to 30-day stale retention; successful empty responses cached. The atomic option gate allows one provider request per minute across cron and CLI. Compare-and-delete prevents expired lock holders removing a newer worker's lock. Provider errors, timeout remarks/partial responses, 429s and bad JSON preserve stale successful data and set a one-hour minimum failure backoff, extended for a longer provider `Retry-After` (seconds or HTTP date). Cron deferrals reschedule with jitter; CLI deferrals preserve their checkpoint without creating new jobs. Public Overpass is shared and has no SLA; do not rotate endpoints to evade limits. At most approximately 1,440 requests/day per site, typically much less after shared-cell reuse. Operators must still monitor shared-service capacity and choose managed/self-hosted service if needed.

Explicit service tags and direct route memberships distinguish Metro, Marmaray and Metrobüs. Unknown rail stays “Rail stations”; unknown bus stops stay Bus. OSM completeness and direct-route tagging remain limitations requiring representative Istanbul staging checks.

## Bounded, resumable WP-CLI warming

The CLI-only module `inc/modules/nearby-amenities-cli.php` is loaded by the nearby module only when `WP_CLI` is active. It retains read-only `wp pera nearby audit` and adds `wp pera nearby warm`.

The warmer reads published `property` IDs in ascending ID order using read-only keyset pagination. It validates ACF map coordinates, deduplicates shared cells within a batch and skips fresh/backoff cells across batches. It neither edits posts/meta/taxonomies nor bypasses provider limits. One provider attempt per invocation by default; it never sleeps. Deferred cells are not checkpointed as handled. A five-minute maintenance lock prevents concurrent warmers from corrupting the resume cursor.

Parameters:

- `--limit=100`: maximum IDs examined, 1–500.
- `--max-cells=1`: maximum provider attempts, 1–10; increasing it does not lift the global one-per-minute gate.
- `--after-id=0`: explicit checkpoint; use returned `next_after_id` on the next invocation.
- `--resume`: persist the checkpoint in non-autoloaded `pera_nearby_warm_cursor`.
- `--restart`: start a new `--resume` pass from zero; use once when a fresh pass is needed, not on every minute invocation.
- `--dry-run`: no HTTP, locks, cache writes or cursor changes.

JSON reports `successful`, `failed`, `skipped` (fresh/backoff cells), `deferred` (rate/batch limits), `planned` (dry-run), `properties_scanned`, `invalid_properties`, `duplicate_properties`, `next_after_id`, `complete`, `reason` and bounded per-cell outcomes. Scanned IDs can include the deferred lookahead; the checkpoint remains the last handled ID. A batch exactly equal to `--limit` needs another invocation to confirm completion. Failures progress the cursor after applying backoff: start another pass after backoff expires to retry them. An exit code of zero means the command ran; inspect JSON failure counts for provider success.

Deployment/operator commands, run as the WordPress filesystem owner with the active theme, ACF and Pera ML loaded:

```sh
# Read-only counts and warmup preview.
wp --path=/path/to/wordpress pera nearby audit
wp --path=/path/to/wordpress pera nearby warm --dry-run --limit=100 --max-cells=1

# First bounded pass; creates only caches, rate gate and maintenance cursor.
wp --path=/path/to/wordpress pera nearby warm --resume --restart --limit=100 --max-cells=1

# Repeat at most once a minute; stop initial warmup when complete=true.
wp --path=/path/to/wordpress pera nearby warm --resume --limit=100 --max-cells=1

# Alternatively, manage the checkpoint externally; substitute next_after_id.
wp --path=/path/to/wordpress pera nearby warm --after-id=123 --limit=100 --max-cells=1
```

Run a new `--resume --restart` pass after freshness expires or after a failed-cell backoff; ordinary demand-driven cron refresh also continues. Do not automatically reset the cursor on every execution. Cache eviction can discard ordinary stale data early; independently computed airports remain available for relevant properties.

## System cron with DISABLE_WP_CRON=1

The constant disables automatic browser-triggered cron spawning; it does not prevent scheduling or WP-CLI running due events. Without an external runner, ordinary POIs will not warm themselves. The warmer can populate ordinary caches directly without cron, but ongoing demand refresh needs a runner.

Example entries to adapt to the deployment paths/user; these were documented, not installed:

```cron
# Persistent WordPress due-event runner (all normal WordPress jobs, including Nearby).
* * * * * /usr/bin/flock -n /tmp/pera-wp-cron.lock /usr/local/bin/wp --path=/path/to/wordpress cron event run --due-now >> /path/to/logs/wp-cron.log 2>&1
# Temporary initial warming; remove when complete=true. Uses the SAME provider gate.
* * * * * /usr/bin/flock -n /tmp/pera-nearby-warm.lock /usr/local/bin/wp --path=/path/to/wordpress pera nearby warm --resume --limit=100 --max-cells=1 >> /path/to/logs/nearby-warm.log 2>&1
```

Verify as the cron user:

```sh
wp --path=/path/to/wordpress config get DISABLE_WP_CRON --type=constant
crontab -l
wp --path=/path/to/wordpress cron event list --fields=hook,next_run_gmt,next_run_relative --format=table
# Controlled execution check; this runs due WordPress background jobs.
wp --path=/path/to/wordpress cron event run --due-now
# Inspect a cache key returned by the warmer (replace placeholder).
wp --path=/path/to/wordpress transient get <cell-key-from-json>
```

Check the actual system cron/service logs over successive minute ticks, not merely that a crontab entry exists. Scheduled `pera_nearby_refresh` events should run or move to deferred timestamps; a successful cache's `updated` timestamp advances. Failed requests instead leave stale data and `<cell-key>_retry` backoff. `wp cron test` can report disabled spawning with this constant; that alone does not demonstrate failure of the system runner. Ensure outbound HTTPS POST to the chosen endpoint. Full-page caching must expire/purge existing property HTML after warming via the site's established cache operations; this feature does not purge unrelated pages.

## Frontend readiness: code audit versus production verification

Source audit confirms:

- `functions.php` loads `inc/theme-modules.php`, which unconditionally loads the nearby module. The airport data file is local; CLI code is conditional.
- Existing enqueue code loads `pera-main-css` and property styles on normal property pages. No additional style dependency is required.
- The renderer enqueues `pera-component-tabs` with no dependencies, a versioned URL and footer loading; the existing footer calls `wp_footer()`. Browser tests execute that same shipped JS file.
- Existing Pera ML `Pera_ML_UI::get()` registers encountered literal strings and returns stored current translations or English fallback. The module is already inside approved theme UI discovery scope. It never calls a translation provider during rendering.
- Names, labels, IDs, URLs and distances are escaped; malformed provider record shapes are excluded. List/panel IDs and ARIA relationships are unique. OSM/ODbL attribution and airport source attribution are separate.

Therefore, with the existing required theme/ACF/Pera ML infrastructure active, basic display requires no new API key, field, JS configuration or manual string registration: relevant Istanbul properties can show airports immediately. Ordinary local results require a successful cached provider fetch, and fully translated copy requires current translation rows. This is code-level readiness, not a claim that the production database, cron or outbound access has been verified.

Optional registration and translation maintenance:

```sh
wp eval-file wp-content/plugins/pera-multilingual/tools/register-theme-ui-strings.php -- --dry-run
wp eval-file wp-content/plugins/pera-multilingual/tools/register-theme-ui-strings.php
# Preview only; generate/import reviewed translations through the existing workflow.
wp eval-file wp-content/plugins/pera-multilingual/tools/pera-translate-latest.php dry-run status=all object_type=ui field_prefix=key:theme_nearby language=ar limit=100
```

The revised distance notice invalidates translations of the old source; Pera ML falls back safely until refreshed. Airport proper names remain unchanged unless the dataset gains a reliable localised name.

## Test results and remaining production checks

Standalone suite:

```sh
php wp-content/themes/hello-elementor-child/tests/nearby-amenities-test.php
mkdir -p work/fixtures
NEARBY_FIXTURE_DIR="$PWD/work/fixtures" php wp-content/themes/hello-elementor-child/tests/nearby-amenities-test.php
CHROMIUM_PATH=/usr/bin/chromium NEARBY_FIXTURE_DIR="$PWD/work/fixtures" node wp-content/themes/hello-elementor-child/tests/component-tabs-test.js
```

Requires PHP with tokenizer for the real UI discovery assertions, and Playwright with Chromium. If Playwright's own Chromium is installed, omit `CHROMIUM_PATH`.

Passed PHP contracts: coordinate validation/zero, shared cells, Haversine calculation, transport classification/enrichment, malformed provider shapes and output escaping, nearest-three/radius filtering, old airport-cache exclusion, hits/misses/empty/refresh/error/429/partial-response/bad-JSON/backoff/stale behavior, one global provider gate and longer provider Retry-After, footer JS enqueue contract, independent airport caches/reference coordinates/no HTTP/range/sort, cold airport rendering and empty states outside coverage, read-only audit, bounded warming/dedup/resume/checkpoints/failure counts/dry-run/concurrency/CLI option validation under `DISABLE_WP_CRON=1`, and real Pera UI discovery/current/stale/fallback logic in memory.

Passed real Chromium checks across nine English/Turkish/Arabic × 390/834/1440 px cases using the actual PHP renderer, production component JS, existing main/property/RTL CSS and sprite: group columns, max-three rows, opposite-edge distances, long names, hierarchy, preserved map/location geometry, touch/click/Enter/Space, Home/End/Left/Right and RTL arrows, roving tabindex/panel focus, unique ARIA relationships, no horizontal overflow, no console/page errors. Unrelated card/pill/grid computed styles unchanged; independent tab instances remain isolated. Screenshots visually reviewed for desktop and mobile RTL. Existing multilingual property-template, UI discovery and RTL asset suites passed; PHP/JS syntax and whitespace checks passed.

Simulation boundary: ordinary POI fixtures use realistic Istanbul names with deliberately synthetic positions and sample facilities; they are not fetched OSM results, not production listing records and never enter shipped provider data. UI translations/registry/storage and the WordPress database/HTTP/cron functions are simulated; airport source records alone were checked against the real pinned dataset. Browser map iframe is a placeholder, external map requests are not exercised, and local fonts are excluded from the test harness. There is no `wp-config.php`, `wp-load.php` or connected production database in this workspace.

Remaining production checks: actual published-property counts, active ACF/Pera ML and translated catalogs, real Overpass tag coverage, successful system-cron ticks/outbound access, page-cache warmup visibility, complete live property-page visuals and Core Web Vitals. No live WordPress record, deployment configuration or system crontab was changed.
