# Nearby Amenities: local OSM provider and operations

The monthly follow-up adds exact-coordinate/versioned caching, guarded resumable imports, status/monitoring, safe cleanup and GitHub Actions build + real MariaDB acceptance + disabled-by-default delivery. See [monthly setup, source blockers and recovery](nearby-poi-monthly.md) for the current operations workflow. No real OSM or MariaDB validation was possible locally.

## Status and audit

This branch replaces ordinary POI retrieval from merged PR #1419. Implemented: local MySQL provider, streamed and checksummed imports, independent generations, rollback, offline builder and tests. **No production deployment, property/ACF edits or frontend redesign. No real Istanbul artifact has been built or imported in this environment.** Source download/PBF processing, real MySQL integration, production query plans and coverage acceptance are release gates, not completed validation. The monthly Actions workflow includes executable real MariaDB acceptance; it has not been dispatched here.

The audit inspected `inc/modules/nearby-amenities.php`, `nearby-amenities-cli.php`, `inc/data/nearby-airports.php`, `single-property.php`, `js/component-tabs.js` and the prior guide.

| Existing function/path | Live Overpass dependency before replacement | Behavior now |
| --- | --- | --- |
| `pera_nearby_query()` | Builds the 4.3 km query and direct route-membership requests. | Retained for explicit external mode only. |
| `pera_nearby_refresh()` | Sole HTTP caller: `wp_remote_post()`, 25-second timeout, 8 MiB cap, rate gate, failure backoff. Called by `pera_nearby_refresh` cron hook. | Checks selected provider before reading legacy caches or making requests. Local mode never calls HTTP. Old queued jobs therefore cannot continue Overpass requests. |
| `pera_nearby_cached()` | Reads seven-day cell caches, schedules background Overpass refreshes on cold/stale data. No synchronous HTTP. | Defaults to indexed local queries; no scheduling, external call, legacy cell cache or failure backoff in local mode. |
| `pera_nearby_warm()` / `pera nearby warm` | Bounded property/cell scan calls the same refresh function directly. | Existing external warmer retained; unnecessary for local data. Use `pera nearby dataset` to inspect local readiness. |
| `pera_nearby_normalize()` / `pera_nearby_group()` | Interprets provider data; neither makes requests. | Same production code is reused offline, including conservative transport classification. |
| `pera_render_nearby_amenities()` | Calls cache read, nearest grouping, separate airports. | Markup unchanged. Calls now resolve ordinary POIs locally. |
| Airport file / `pera_nearby_airports()` | No Overpass dependency. | Unchanged IST/SAW points, exact-coordinate cache and 150 km relevance radius. |
| Template / tabs JS | Existing ACF map, PHP render placement, keyboard/ARIA tab behavior. | Unchanged, as are CSS, RTL, map functionality and JS dependencies. |

Production diagnostics supplied by the requester (406, timeout, valid coordinates and cron) are evidence of external-provider failure, not reproduced measurements here. Cycling public endpoints would not remove this dependency.

## Alternatives and sources

Sources reviewed on 2026-10-13:

- [Geofabrik Türkiye](https://download.geofabrik.de/europe/turkey.html): approximately 620 MB current PBF as displayed on the source page; no Türkiye subregions listed. Reliable legitimate regional source and update files. Download/crop only on a separate build machine. Never download/process this national file on cPanel.
- [BBBike Istanbul](https://download.bbbike.org/osm/bbbike/Istanbul/): city PBF advertised at approximately 45 MB. Its canned city bounds must be checked; a city label does not establish full metropolitan coverage. [Custom BBBike extracts](https://extract.bbbike.org/) are an alternative for a reviewed rectangle spanning both continents.
- [Osmium tooling](https://osmcode.org/osmium-tool/manual.html) and [Pyosmium handlers](https://docs.osmcode.org/pyosmium/latest/reference/Handler-Processing/) support source geometry and disk-backed location indexing. The builder uses the original PBF, not simplified shapefiles that could omit translations or source identities.

| Approach | Reliability/resource/maintenance assessment |
| --- | --- |
| Filtered local OSM | No page-time external dependency or API cost; shared storage; deliberate refreshes. OSM completeness and transport tags vary. Requires offline build and ODbL compliance. Recommended. |
| Managed commercial POI service (e.g. Google Places or Foursquare) | Could replace volunteer endpoint availability with managed service and vendor data. Requires credentials, billing, background fetch/error handling, a separate adapter and review of retention, display and attribution terms. Exact prices/contracts/coverage have not been evaluated; do not assume indefinite caching is permitted. Proposal only. |
| Managed/self-hosted Overpass | Preserves query semantics but still adds service administration or vendor cost. City-only instance is possible; hosting a broad OSM database on this 3.6 GB cPanel host is not recommended. Proposal only. |
| Public live Overpass | Existing production failure mode; external mode is retained only for intentional use. No endpoint cycling. |

Source sizes above are advertised compressed inputs, **not measured artifact sizes or POI counts**. A planning envelope of tens of thousands of relevant named facilities and a few to tens of MB of NDJSON is a capacity estimate only. Import prints exact count; source/build stats and artifact byte size must replace these estimates before release. Allow disk space for active, previous and staging indexed tables plus backups; reserve at least three times measured table size. Historical generations are deliberately retained, so storage grows until reviewed cleanup.

## Storage, queries and provider selection

One purpose-built InnoDB table per generation, with primary OSM ID and `(poi_group,lat,lng)` geographic index, stores only ID, subgroup, representative point, names/translations and duplicate aliases. One non-autoloaded WordPress option `pera_nearby_dataset` contains the coherent `active` and `previous` descriptors. Existing WordPress DB access avoids requiring SQLite/PDO extensions on cPanel. No per-property files, property edits, national import, full-city PHP loads or new browser dependency.

Default `pera_nearby_provider` is `local`. A deliberately small provider selection boundary dispatches local reads or the existing cached external implementation; unknown providers return no ordinary amenities. No automatic external fallback. To opt into the existing background external provider, install a reviewed server-side configuration:

```php
add_filter( 'pera_nearby_provider', function () { return 'overpass'; } );
add_filter( 'pera_nearby_overpass_endpoint', function () { return 'https://your-approved-provider.example/api/interpreter'; } );
```

The endpoint filter alone does not enable external mode. Any future managed provider must supply the same normalized place shape, use explicit selection and cache/background retrieval; it must never add page-render HTTP. Current implementation supports local and explicit Overpass, not a commercial adapter.

Local retrieval captures one generation pointer per call. For each of 15 ordinary subgroups, use a conservative spherical bounding box for 3.5 km and SQL ordering by the Haversine intermediate value, then compute exact distances using the existing PHP Haversine function. Fetch at most 256 rows per batch; discard duplicates and keep three nearest. The existing grouping/renderer receives at most 45 places. SQL ordering ensures facility dedup happens nearest-first. Polar and antimeridian bounds are supported. Exact-coordinate results are cached for seven days under the active generation and classification version; there is no cell-centre reuse. Numeric SQL/DOUBLE values preserve round-trip coordinates. No result truncation silently drops all later facilities: if duplicates fill a batch, continue until three distinct places or exhausted results.

Memory is bounded by a batch plus selected results, not the dataset size. SQL may sort all candidates inside a subgroup bounding box; the index bounds the candidate set. Pathological duplicates increase batched queries; offline dedup mitigates this. Production EXPLAIN/latency remain mandatory. Missing pointer, missing table or invalid row names yields no ordinary data; airports stay available. A missing local dataset never consults old Overpass caches. Installed tables are immutable; filesystem/page caches may still show older pages until purged.

## Offline build workflow

Use a dedicated machine with Python 3.11+, PHP CLI, Osmium Tool and disk space for the PBF, disk-backed node-location index and intermediate SQLite. No Docker requirement. Pin the Python dependency with `tools/nearby-poi/requirements.txt` (`osmium==4.3.1`); record the exact PHP/Osmium versions in the build log. Retain the downloaded input and SHA-256 to reproduce a source snapshot: `latest` URLs change over time.

```sh
python3 -m venv work/poi-venv
work/poi-venv/bin/pip install -r tools/nearby-poi/requirements.txt
bash tools/nearby-poi/download.sh
osmium fileinfo -e work/nearby-poi/Istanbul.osm.pbf
```

Default required rectangle: **27.8–30.1° E, 40.7–41.7° N**, spanning metropolitan European and Asian Istanbul plus ordinary-radius margin. Review this rectangle against the 127 actual property coordinates using the existing read-only audit; it is a proposed geographic envelope, not proof of coverage. The builder checks actual source-node bounds and refuses a source that does not span the chosen rectangle. Extents alone cannot detect holes or clipping; review the extract polygon and spot checks too.

If BBBike's canned file is too small, request a custom rectangular extract with padding, or download Geofabrik Türkiye **on the separate build machine** and crop it with complete ways/relation references, e.g.:

```sh
osmium extract --bbox=27.75,40.65,30.15,41.75 --strategy=smart \
  turkey-latest.osm.pbf -o work/nearby-poi/istanbul-reviewed.osm.pbf
```

`smart` extraction helps preserve relation members; nested/incomplete relations still need review. Supply reviewed source URL, actual source checksum and extract/source timestamp (from source metadata, not build time):

```sh
work/poi-venv/bin/python tools/nearby-poi/build.py \
  work/nearby-poi/istanbul-reviewed.osm.pbf work/nearby-poi/20261013.poi.ndjson \
  --source=https://download.geofabrik.de/europe/turkey-latest.osm.pbf \
  --source-sha256=ACTUAL_SHA256_OF_INPUT_EXTRACT \
  --source-timestamp=ACTUAL_SOURCE_TIMESTAMP --version=20261013-reviewed
```

The command intentionally uses placeholders requiring real measurements. Download script is only for BBBike; alternative acquisition must retain source and crop logs. No synthetic fixture is a deployment dataset. Generated artifacts/scratch are ignored by Git.

Builder steps:

1. Verify input SHA-256 with streaming reads and check source extents.
2. First relation pass records direct subway/train/bus/ferry memberships and references of tagged facilities in temporary SQLite.
3. Stream nodes and ways with a disk-backed location index. Retain only relevant tags/names for the production classifier. Preserve stable node/way/relation identities.
4. Read tagged facility relations, including multipolygons, using available direct-member points. Incomplete or nested unresolved relations are skipped and counted; no partial relation centre is published.
5. Call the shipped PHP normalizer through a streaming adapter. Explicit route tags/memberships distinguish Metro/Marmaray/Metrobüs; unidentified rail stays generic. Ferry terminals retain their existing classification. No name guesses fabricate transport types.
6. Dedup same normalized name and subgroup within 100 m. Merge missing translations and retain alias OSM IDs. This conservative heuristic can merge similarly named nearby branches or miss differently named representations; audit spot samples. It is not a universal facility-resolution algorithm.
7. Sort records by stable ID and write versioned compact NDJSON with schema, source, source SHA/timestamp, build date, license, bounds, counts, geometry convention and statistics. Header includes exact record count and body SHA-256. Artifact replacement is a same-directory rename after body completion.

Way points are source bounding-box centres, consistent with previous Overpass centre approximation; relation points derive from direct-member centres. They are approximate source-derived reference positions, not entrances, routing endpoints or surveyed POIs. Arbitrary nested relation geometry and route-master inheritance are outstanding improvements; present transport classification uses explicit tags/direct memberships. No source names or POIs are invented. PHP importer validates the normalized coordinate/name/group/ID contract again and rejects duplicate canonical IDs or checksum/count mismatch.

## cPanel deployment, rollback and updates

Do not merge or deploy this branch as part of this task. When approved after release gates, the existing `sync.sh` copies changed `wp-content` files from `/home/d21882/_work/pera-wp` to `/home/d21882/public_html`. Tools/docs at repository root remain offline/operational resources and are not copied into the theme. Generated dataset files must be transferred separately to a directory outside the public web root, e.g. `/home/d21882/nearby-poi/`.

Check WordPress DB credentials/privileges allow CREATE/INSERT/DROP and the filesystem owner can read the artifact. Back up the WordPress DB. First deploy code, then explicitly import:

```sh
wp --path=/home/d21882/public_html pera nearby audit
wp --path=/home/d21882/public_html pera nearby import /home/d21882/nearby-poi/20261013.poi.ndjson
wp --path=/home/d21882/public_html pera nearby dataset
```

Import creates a random, unpublished table and inserts one validated row at a time (bounded PHP memory). Record count and checksum are checked before one option update publishes it. Readers use the old complete table until publication. No DDL renaming of active tables, no partially populated table visibility. The shared import/rollback option lock excludes normal concurrent jobs; imports have a one-hour lease and refuse publication if their lease expires or ownership changes. Run these commands with one supervised operator/system job; do not run competing jobs around lease expiry. Failed imports drop their staging table and preserve the active pointer. A killed process can leave an orphan table, but the active dataset remains available.

Rollback swaps the coherent active/previous descriptors:

```sh
wp --path=/home/d21882/public_html pera nearby rollback
wp --path=/home/d21882/public_html pera nearby dataset
```

It checks the previous table still exists; repeating rollback toggles the last two generations. Purge property page caches after import/rollback using the site's normal cache tooling. Retain old tables while in-flight requests may still reference them. For automated updates, `pera nearby cleanup` safely removes only unreferenced generation tables older than seven days. Manual inspection remains available: list only tables matching this site's `PREFIXpera_poi_<24 hex>` pattern, compare them with both descriptors, back up and drop reviewed orphan/historical tables after a safe retention interval. No automatic cleanup may remove the active/previous table. Multi-site prefixes produce separate datasets.

Schedule weekly or monthly **offline rebuilds** depending on accepted data freshness. Each job: acquire snapshot, retain source/build logs, validate bounds/counts/subgroup drift and district spot checks, produce checksum manifest, transfer privately, import once, run status and page checks, purge caches. Automate deployment only after a real successful acceptance pass. Monitoring should alert on failed jobs, unexpectedly empty or sharply changed subgroup counts, missing table, stale source timestamp and slow geographic queries. Log CLI exit code/output and metadata. `pera nearby dataset` validates presence/count; it reports metadata for monitoring and does not implement an alert service or per-row corruption scan. OSM public-source schedules vary; site refresh schedule is independently chosen.

Existing cron remains useful for other WordPress features and explicit external mode. Ordinary local retrieval needs neither cache warming nor WP cron. No production crontab was changed. `pera nearby warm` is retained for backwards compatibility, but its legacy cache statuses are not authoritative local readiness.

## Attribution and licensing

[OSM copyright guidance](https://www.openstreetmap.org/copyright) and [ODbL 1.0](https://opendatacommons.org/licenses/odbl/1-0/) apply. Visible OSM contributor attribution and ODbL notice remain in the renderer. Dataset metadata preserves source and license; source aliases/translations remain in the table/artifact. Keep license/source information with distributed artifacts.

Treat the filtered dataset as an OSM-derived database: when publicly using it, provide the applicable ODbL database offer/share-alike access and attribution. Operationally publish the exact filtered artifact and a source/build/license notice at a stable data-download URL (or a compliant written offer) alongside the site's licensing page; keep database availability separate from public executable/import paths. The monthly receiver now publishes the entire filtered database and source/licence notice at `/poi-data/VERSION/` before activation. First-time setup must link the offer from the existing legal page and verify public access; no production artifact has been published here. Commercial-provider terms need a separate review if that proposal is chosen. Independently sourced OurAirports coordinates retain their existing public-domain attribution and pinned reference revision.

## Tests, evidence and release gates

```sh
php wp-content/themes/hello-elementor-child/tests/nearby-local-test.php
PHP=php python3 tools/nearby-poi/test_build.py
```

Passed here with PHP 8.4.24:

- Existing external-provider cache/error/backoff/gate, categories, airports, renderer and CLI warmer regression suite, explicitly selecting external mode only in that test harness.
- Local missing dataset/table, valid/empty imports, invalid coordinates/names/IDs/schema, duplicate canonical IDs, count/checksum mismatch, simulated insert failure, staging cleanup and rollback contracts.
- Synthetic fixtures centred on Şişli, Beşiktaş, Kadıköy and Zeytinburnu: bounding boxes, radius filtering, nearest-three sorting, multilingual output and existing five-tab output. These verify geography logic, **not real district POI coverage**.
- Local rendering and old queued refresh jobs make no HTTP calls or new cron jobs; airports survive missing ordinary store.
- Offline dedup merges translations/alias IDs; deterministic body ordering, checksum/count/empty-artifact writing; adapter uses real PHP Metro/Marmaray/Metrobüs/ferry/generic rail classification.
- Generated 100,002-candidate stream: 256-row maximum batch, three output places, 405 simulated queries, approximately 0.21 seconds; allocator peak did not rise above the prior regression-suite peak. This proves bounded retention in the harness, **not production SQL timing or absolute PHP worker memory**.
- PHP/Python syntax and Git whitespace checks. No UI assets changed; browser redesign verification is unnecessary for this backend-only diff.

This workspace has no running WordPress/MySQL database, Osmium Tool or Pyosmium package. Shell network proxy is unreachable; GitHub connector recovered merged PR #1419 for the correct baseline and is used to publish the draft. Thus PBF end-to-end execution, real dataset sizes/counts/coverage, actual MySQL SQL/index/collation behavior, cPanel import duration/resource usage, crash/concurrency behavior, live page caches and production coordinates were **not tested**.

Before production approval: run the builder on a real reviewed extract; preserve artifact/license offer; import on staging WordPress/MySQL; EXPLAIN the indexed subgroup box queries for all four districts; measure p50/p95 latency and PHP peak memory under the real table (target candidate reads under 256 per normal subgroup and an agreed page-query budget); verify 3.5 km boundary cases, real route classifications and names against OSM; exercise failure during import and simultaneous read/rollback; inspect all 127 property coordinates against chosen source coverage; validate count changes; confirm old-generation retention and page-cache purge. No benchmark/coverage or production-readiness claim is made from synthetic fixtures.
