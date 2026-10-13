# Nearby amenities on property pages

The single-property location section calls `pera_render_nearby_amenities($map)` with the existing ACF map. No location fields, property queries, taxonomy assignments, Google Maps behavior, or stored property data change. The reusable module is loaded by `inc/theme-modules.php` and includes a read-only `wp pera nearby audit` command.

## Data and refresh architecture

- Validate finite numeric latitude/longitude within world bounds; zero is valid. Invalid/missing locations produce no markup, scheduled job, or request.
- Share raw, language-independent OSM results between coordinates rounded to a 0.01-degree grid (approximately 1 km in Istanbul). Query a 4.3 km circle around the cell center to cover a 3.5 km property radius plus the maximum rounding offset. Compute and sort actual property distances with Haversine; deduplicate nearby same-name objects and render at most three per subgroup.
- Fetch only on a demand-triggered WP-Cron event, never during page rendering or via frontend requests. Cache results, including empty results, for seven days of freshness and up to 30 days of stale retention. Expired/missing results schedule one event; unavailable/empty data hides the component. Later navigations can show refreshed data without client-side insertion or layout shifts.
- One atomic, site-wide option gate allows one request per minute, including concurrent workers; compare-and-delete handles expired locks. Busy workers reschedule with jitter. A one-hour per-cell failure backoff prevents repeated requests after HTTP errors, timeouts, bad JSON or Overpass partial-result remarks. Retain stale successes on failures.
- The public Overpass interpreter defaults to `https://overpass-api.de/api/interpreter`. A `pera_nearby_overpass_endpoint` filter supports an operator-managed endpoint. HTTP timeout is 25 seconds; query timeout 20 seconds; Overpass memory cap 16 MiB; response limit 8 MiB; redirects disabled. Identify requests with a site URL in User-Agent.
- Overpass is a shared service without an uptime guarantee. Do not rotate public endpoints to bypass quotas. The per-site gate caps demand to approximately 1,440 requests/day; persistent cache reuse normally makes demand much lower. Monitor capacity and use a managed/self-hosted provider if multiple sites or traffic require more. No bulk city dataset fetch, route geometry, or recursive membership expansion.
- Return named nodes/ways/relations only, preserving source names and reliable `name:<language>` variants. Way/area centers and straight-line distances are approximate. Coverage depends on OSM completeness and tags; lack of a result is not evidence a facility does not exist. Airports use the same 3.5 km radius and will often have no results.
- Explicit `station=subway`, `subway=yes`, or subway route membership distinguishes Metro from generic rail. Station and bus-stop names, networks/operators, and containing train/bus/subway routes identify Marmaray and Metrobüs. Unknown rail remains “Rail stations”; never infer a service solely from proximity. Route enrichment covers direct node members; not every mapped station area belongs directly to a route. Generic bus classification remains the fallback for unclassified bus stops.
- Map data links to OpenStreetMap copyright/licensing and names contributors with ODbL attribution. No routing claims or structured data.

## UI, accessibility and CSS reuse report

Five categories include translatable subgroup headings and empty states. The first nonempty category is selected, preferring Transport by order. Semantic tabs connect unique tab/panel IDs; roving tabindex, Home/End and RTL-aware Left/Right keys activate without navigation. Enter/Space use native button click behavior. Focus remains visible, and each panel is focusable. Only the closest three POIs per group enter the HTML, rather than the full cache.

Existing classes reused:

- `card-shell`: surface, border, radius, shadow, padding and stacked spacing.
- `mt-md`, `text-soft`: spacing and secondary typography.
- `section-cta`: wrapping responsive tab row and gaps.
- `pill`, `pill--lg`, `pill--active`: tab appearance, sizing and active selection.
- `grid-2--tight`: responsive subgroup columns; native lists preserve POI semantics.
- `inline-row`, `icon`, and existing `#icon-map` sprite: heading icon.

Every new selector is in global `main.css`, scoped to reusable tabs (none is property-specific):

1. `.component-tabs [role="tab"]`: token-based 48 px minimum touch target and pointer cursor, absent from generic pills.
2. `[data-component-tabs] [role="tabpanel"][hidden]`: guarantees inactive panels stay hidden even when other component styles set display.
3. `.component-tabs [role="tab"]:focus-visible`: high-contrast brand outline and token-based offset; the existing pale pill shadow alone is insufficient for this keyboard interface.

No duplicated card/grid/pill styles, inline styles, framework or external frontend dependencies. Native logical CSS and bidi isolation support RTL. New selectors do not match unrelated components.

## Production prerequisites and read-only audit

Before enabling on production:

1. Ensure outbound HTTPS POST to the chosen Overpass endpoint and working WP-Cron (prefer a regular system cron where visitor-triggered cron is disabled). Ordinary page requests only schedule refresh; the first visit to a cold cell intentionally has no component.
2. Register `theme.nearby.*` strings through the existing theme UI discovery workflow, then translate them in Pera ML. Original source names remain intact when a language-specific OSM name is unavailable. Registration dry run:

   ```sh
   wp eval-file wp-content/plugins/pera-multilingual/tools/register-theme-ui-strings.php -- --dry-run
   ```

3. Count published properties with valid coordinates, without writing property data or calling the POI provider:

   ```sh
   wp pera nearby audit
   ```

   Output: JSON totals for `published`, `valid`, `missing_or_invalid`. This environment contains no WordPress installation/database, so no production count was claimed or live records changed.

4. Account for full-page caching: invalidate or expire property page HTML after cache warmup through existing cache operations if prompt visibility is needed. This module does not globally purge page caches. Object/transient eviction can discard stale data early; the component then stays hidden until a successful background fetch.
5. Stage one representative location and check OSM tag coverage (especially transport), cron refreshes, approved translations and the complete existing property page. Do not interpret test fixtures as actual OSM data.

## Validation

Standalone provider/cache contracts:

```sh
php wp-content/themes/hello-elementor-child/tests/nearby-amenities-test.php
```

Optional rendered fixtures for browser tests (synthetic test POIs; mocked translations):

```sh
mkdir -p work/fixtures
NEARBY_FIXTURE_DIR="$PWD/work/fixtures" php wp-content/themes/hello-elementor-child/tests/nearby-amenities-test.php
NEARBY_FIXTURE_DIR="$PWD/work/fixtures" node wp-content/themes/hello-elementor-child/tests/component-tabs-test.js
# Set CHROMIUM_PATH=/usr/bin/chromium if using system Chromium.
```

Passed: PHP syntax; coordinate validation (including zero), shared cells, known distance calculation, transport route enrichment, unnamed exclusion, cache misses/hits/empty results, deduplicated scheduling, timeout/error/429/partial-response/bad-JSON backoff and stale retention, site-wide gate/deferred refresh, nearest-three/radius filtering, output escaping, localised names, all five categories, first-nonempty default, hidden empty/missing states and read-only audit counts without requests or cache mutations. Real Chromium checks passed for mobile touch switching, Home/End/Left/Right, RTL direction, focus, one visible panel, ARIA relationships, no mobile horizontal overflow and independent instances. Computed styles for unrelated existing cards, pills and grids were unchanged by new rules. Existing multilingual property-template and theme UI discovery suites passed.

Limits: no live provider call, production database audit, actual translated catalog check, complete WordPress-page visual regression, or Core Web Vitals measurement was possible here. Use the staging checks above before promoting the draft PR. No deployment or merge is part of this change.
