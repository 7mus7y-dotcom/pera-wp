# Nearby Amenities transport: classification and dataset v2

This change prepares a replacement for the active **28,880-POI schema-v1** dataset. It does not alter production tables in place, property/ACF data, CSS, JavaScript, the page template, delivery settings or server cron. The existing Nearby Amenities renderer gains translated subgroup labels, verified line suffixes and accurate radius copy using its current UI classes and layout.

## Classification and display

| Subgroup | Evidence | Property radius |
| --- | --- | --- |
| Metro | Explicit subway station tags or direct operational `type=route`, `route=subway` stop/platform membership | 3.5 km |
| Tramway | `railway=tram_stop`, explicit tram platform/station tags or qualifying direct `route=tram` membership | 3.5 km |
| Funicular | Explicit funicular station/infrastructure tags or direct `route=funicular` membership | 3.5 km |
| Metrobüs | Bus/station facility with explicit Metrobüs system identity/flag, or qualifying operational bus route with explicit Metrobüs network/operator/brand identity | **500 m** |
| Marmaray | Railway facility with explicit Marmaray system identity, directly or through an operational train route | **500 m** |
| Conventional rail / ordinary Bus / Ferry | Original facility types, after operational and specific-mode checks | 3.5 km |

Distances are straight-line Haversine measurements from exact property coordinates. 500 m is inclusive, with a one-micrometre numerical tolerance for floating-point calculations. Other POIs remain at 3.5 km and airports independently remain at 150 km.

M/T/F identifiers come only from a typed station's `ref` or the `ref` of its directly linked operational route. Semicolon/comma-separated refs are verified individually against their mode prefix (e.g. M1A/M1B/M2/M4/M7/M11, T1/T4/T5, F2). Descriptive route names, destinations, route-master inheritance and network names alone never generate line identifiers. Multiple verified lines are deduplicated and displayed together. Missing/unreliable refs yield a station name with no added identifier. Source facility names/translations are preserved.

Ordinary İETT routes mentioning “Metrobüs” in prose stay under Bus. İETT operator identity alone is not Metrobüs evidence. Conservative system matching may leave real facilities without adequate tags under Bus/Rail or without a line suffix; first real-data review must inspect those cases rather than weakening evidence rules. Each record retains one facility subgroup, as in v1; line union operates within that mode, not by guessing transfers between nearby facilities.

Lifecycle prefixes/flags (`proposed`, `planned`, `construction`, `disused`, `abandoned`, etc.), nonoperational status values, `operational=no` and future ISO opening dates exclude facilities. Equivalent lifecycle evidence on a route prevents that route from establishing a functioning mode/line. Unsupported/absent OSM lifecycle tags cannot independently prove real-world operation; human source review remains necessary.

## Artifact, importer and compatibility

New builds use **artifact schema 2 / classification version 2**. Acceptance reports retain report-envelope schema 1 and explicitly report `dataset_schema=2`. Each transport record carries `transport.sources` (source facility IDs/tags and direct route IDs/roles/tags) and recomputed verified `transport.lines`. Both Python validation and production PHP recompute the group/lines from retained evidence and reject mismatches. Dedup merges aliases, translations and compatible same-mode evidence and recomputes the complete line union.

Evidence bounds: 16 facility sources, 32 direct memberships per source, 16 displayed line refs, 40 admitted tag fields per evidence object, 512 UTF-8 bytes per evidence value and 32 KiB encoded transport metadata. Excess data fails explicitly; no silent truncation of the artifact or line list. Existing name-value/UTF-8/TEXT/record/artifact limits, ODbL notices and body/source integrity checks remain. Reports contain compact evidence summaries; the artifact retains the exact proof fields.

Every new v2 InnoDB generation adds one `transport TEXT` column to the existing immutable table design and retains the geographic index. V1 tables are never ALTERed. Reads branch on the active descriptor's schema; old tables are queried without new columns. V2 reads include aliases so merged source proof survives retrieval. Active/previous pointers, batch checkpoints, REPLACE replay, locks, abort, protected cleanup and atomic publication are unchanged. Rollback can switch between v1 and v2 generations with the new code. Roll back the dataset **before** reverting to old code that only understands schema 1.

Local exact-coordinate cache prefix is now `pera_poi_local_c2_`; external-provider cache prefix also advances. Both include the generation identity through their existing key construction. CLI/staging cold-cache probes use the new prefix. Reads retain 256-row batches, at most three facilities per subgroup (51 candidate results across 17 subgroups) and the existing 128 KiB cache-payload cap. Typical cold queries increase from 15 to 17 indexed subgroup queries; heavy duplicates may need additional bounded batches, as before. Metrobüs/Marmaray queries use the tighter geographic bounds. Real MariaDB timing/memory budgets still gate acceptance.

### Serving the current v1 dataset

V1 records remain readable while v2 stages and after rollback. They have no retained route/lifecycle proof, so **no line refs are invented**. Unverifiable legacy Metrobüs records are presented conservatively as ordinary Bus; known exact Tünel names formerly in Rail move to Funicular without an invented F-line. The Marmaray radius already tightens to 500 m. General categories, airports and the UI remain functional.

Other v1 classification/lifecycle errors cannot be reconstructed from names and coordinates alone. Full operational transport correction therefore requires the successful v2 replacement; deployment of reader code alone does not establish new source evidence. Inspect the exact Beyoğlu/Tünel record in the v2 artifact before rollout, ensuring Funicular/F2 when directly evidenced and no entry under Rail.

## Concise migration plan

1. Keep `POI_DELIVERY_ENABLED` unset/false. Establish the immutable **accepted v1 artifact/report/license/manifest** matching the actual active body SHA. If production was imported outside the release workflow, reconcile that baseline under operator review first; never use bootstrap to erase or bypass an active generation.
2. Review this draft PR and test the new code with that v1 baseline in disposable WordPress/MariaDB. Code deployment remains a separate approval. For future deployment, install the reader/modules and both canonical policy files before importing v2; v1 continues serving without a table migration.
3. Start a **new manual** run of **Monthly Istanbul POI refresh**, selecting **`feature/nearby-transport-v2`**. Set `bootstrap=false`, `force=false`, `deliver=false`, **`classification_migration=true`**. This explicit migration skips a same-month duplicate suppression and allows the reviewed v1→v2 comparison. Missing/mismatched accepted baseline fails safely. No run was dispatched by Codex.
4. Migration comparisons retain source-date, total, district and every non-transport subgroup count guard; compare **aggregate transport** counts within 80–150% because correcting/reclassifying transport changes individual subgroup counts. The report exposes before/after subgroup counts for human review. Ordinary later v2 builds resume individual transport subgroup guards. New tram/funicular minima and all existing coverage/category gates still apply. The migration flag cannot bypass other version transitions or bootstrap without a baseline. The production PHP preflight repeats these transition guards against the real active descriptor.
5. Download `nearby-poi-<version>` and `nearby-poi-db-<version>` and require matching passed source/artifact/DB reports and SHA256SUMS. Inspect actual M1A/M1B/M2/M4/M7/M11, T1/T4/T5, Beyoğlu/Tünel/F2 and Metrobüs/Marmaray samples on both continents, future-line exclusions, subgroup drift, all property-boundary cases and host resource budgets. **Migration dispatch always skips delivery**, even if its deliver input/repository variable is set. A missing tram/funicular minimum or failed proof is investigated, not waived.
6. After separate production approval, preserve backups/v1 generation and import the exact fresh accepted candidate through the existing restricted receiver. Its preflight checks the v1 baseline and v2 policy; batches stage while v1 serves, and only complete validated data activates. Purge the normal property page cache, inspect the frontend/status, and record the matching accepted v2 release. Resume an interruption only with identical files; rollback to v1 if acceptance fails. Keep monthly delivery disabled until separately authorized.

The schedule, GitHub job architecture, receiver, source acquisition and licensing offer are preserved. The sole new dispatch input is the explicit classification-migration gate; no production credentials are needed for build/DB acceptance.

## Focused tests and limits

```sh
PHP=php python3 -m unittest discover -s tools/nearby-poi -p 'test_*.py'
python3 tools/nearby-poi/create-test-fixtures.py work/update-fixtures
NEARBY_UPDATE_FIXTURES="$PWD/work/update-fixtures" php wp-content/themes/hello-elementor-child/tests/nearby-transport-test.php
```

Passing local coverage includes **29 Python tests**, the PHP transport suite plus existing provider/render/airport/import/cache/replay/rollback contracts, direct real-builder orchestration against explicitly synthetic Osmium objects, multiple metro refs, route-master/free-text rejection, tram/funicular/rail distinction, false İETT Metrobüs matches, lifecycle/future-line rejection, 499/500/501 m boundaries, evidence/dedup/serialization validation, merged alias proof through DB reads, v1 serving during interrupted v2 import, cross-schema rollback and warm-cache zero POI queries. Migration fixtures contain 770 legacy and 880 new synthetic records; the 100,002-candidate memory test excludes real SQL cost. PHP lint, Python compilation and workflow YAML/Bash/input/delivery-gate checks are also run.

No full OSM/PBF download, real dataset rebuild, production DB operation, dependency installation, merge, deployment or monthly enablement occurred in Codex. Pyosmium/MariaDB were not installed locally. Real source counts, stop classification, operating-line coverage, EXPLAIN/timing/memory and production-plugin acceptance remain gates for the first GitHub runner build and subsequent reviewed rollout.
