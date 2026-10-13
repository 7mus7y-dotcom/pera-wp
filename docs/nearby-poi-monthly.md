# Monthly Nearby Amenities updates: setup, acceptance and recovery

Continue draft PR #1420 (`feature/local-nearby-poi`). No merge, workflow dispatch, server installation, credential creation or production deployment was performed by Codex. Existing Nearby Amenities markup/CSS/JS, Google Maps, multilingual/RTL behavior and independent airports remain unchanged. No property/ACF records are written.

## Real-source investigation: precise results and blockers

On 2026-10-13, development attempts to access both `https://download.bbbike.org/osm/bbbike/Istanbul/Istanbul.osm.pbf` and `https://download.geofabrik.de/europe/turkey-latest.osm.pbf` failed with **curl exit 7: Failed to connect to proxy port 8080 / Could not connect to server**. A package-download probe to `https://pypi.org/simple/osmium/` failed identically. The current environment has PHP but neither Osmium/Pyosmium nor a running MariaDB/WordPress installation. We preserved the configured proxy and did not bypass network policy. Therefore **actual POI counts, transport accuracy, final real artifact size, real PBF processing and populated MariaDB performance are not measured here**.

The connected web reader could inspect published metadata:

- [BBBike's Istanbul polygon](https://download.bbbike.org/osm/bbbike/Istanbul/Istanbul.poly) is a rectangle from **28.66–29.27° E, 40.90–41.27° N**. All eleven requested district reference points below, including their conservative 3.5 km neighbourhood bounds, lie inside this rectangle. This establishes the publisher's declared geometric envelope, not the availability/completeness of facilities in it.
- [BBBike source listing](https://download.bbbike.org/osm/bbbike/Istanbul/) advertises approximately 45 MB PBF and a [CHECKSUM.txt](https://download.bbbike.org/osm/bbbike/Istanbul/CHECKSUM.txt) containing an MD5 entry. Its city rectangle does **not** span the original broader metropolitan policy envelope of 27.8–30.1° E / 40.7–41.7° N. The production validator therefore rejects it under the current policy. [Custom BBBike](https://extract.bbbike.org/) is possible, but no stable custom source/refresh arrangement has been established; it is not part of automation.
- [Geofabrik Türkiye](https://download.geofabrik.de/europe/turkey.html) advertises approximately 620 MB PBF and no regional subdivisions. The automated default downloads this legitimate source **only to the GitHub build runner**, verifies its published MD5 and snapshot header, then crops a padded Istanbul rectangle using Osmium's smart strategy. cPanel never receives or processes the national PBF.

Reference points, chosen for repeatable acceptance probes rather than complete district polygons:

| District | Latitude | Longitude |
| --- | --- | --- |
| Şişli | 41.060 | 28.987 |
| Beşiktaş | 41.043 | 29.009 |
| Kadıköy | 40.990 | 29.025 |
| Üsküdar | 41.025 | 29.015 |
| Zeytinburnu | 40.994 | 28.903 |
| Bakırköy | 40.980 | 28.873 |
| Sarıyer | 41.167 | 29.056 |
| Maslak | 41.111 | 29.021 |
| Beykoz | 41.134 | 29.096 |
| Ataşehir | 40.984 | 29.125 |
| Ümraniye | 41.026 | 29.096 |

The actual 127 property coordinates are unavailable in this environment. Before first enablement, inspect those read-only coordinates against the policy envelope and check real examples on both continents. A named district probe is not proof of every property's neighbourhood coverage.

## Implemented pipeline and automatic acceptance

`.github/workflows/nearby-poi-monthly.yml` runs on the **first day of each month at 04:00 UTC (07:00 Europe/Istanbul)**, with manual dispatch for bootstrap/recovery. GitHub may delay scheduled starts. Schedules run from the default branch after merge; this draft has not installed or executed a schedule. Concurrency uses one queue group and does not cancel an ongoing run.

1. Retrieve the most recent immutable `poi-accepted-*` release, **not** the last merely built artifact. Download its exact artifact/report/manifest/license and verify the manifest. Initial absence of a baseline fails unless an operator explicitly dispatches with `bootstrap=true`.
2. If the same calendar month is already accepted, skip acquisition and delivery. Manual `force=true` permits reviewed recovery/rebuilds. There is no automatic count-guard bypass.
3. Download Geofabrik input and published checksum over HTTPS, with retries, temporary files and bounded transfer timeout. Verify MD5; record SHA-256 identity. Published MD5 plus TLS detects transfer mismatch/rotation; it is **not a cryptographic publisher signature**. Full Osmium parsing must show sorted non-history objects, valid geographic extent, a snapshot timestamp and plausible node count. Do not substitute the latest object edit for the source snapshot date. Reject source age above 14 days or a future timestamp.
4. Crop the national PBF on the runner, then remove it before city POI processing. Build with the production PHP normalizer and the disk-backed location index. Existing tags/direct memberships classify Metro/Marmaray/Metrobüs/ferry; unknown rail stays generic. Source-derived way/member centres remain approximations, not entrance/routing coordinates. Nested unresolved relations and route-master inheritance remain limitations.
5. Validate the compact artifact: names/coordinates/stable unique sorted IDs/schema/classification/license, full body SHA-256/count, geographic envelope, translations and transport samples. Source metadata and retained alias IDs accompany the output.
6. Enforce the canonical acceptance policy in `inc/data/nearby-poi-policy.json`: 500–500,000 POIs, at most 100 MiB, required subgroup minima, at least ten named POIs across three categories within 3.5 km of **each** reference point, and at least five each of Metro/Marmaray/Metrobüs/ferry. These are conservative initial gates awaiting real-data review; they are not asserted measured counts. Changes to this policy require reviewed code changes and matching deployed policy. The PHP preflight refuses a report whose policy differs from the deployed file.
7. Compare total, subgroup and district counts to the last accepted report. Counts with a baseline of at least five must remain within 80–150%; reject suspicious reductions **or** expansions. Reject source-date regression. The report lists exact counts, source/build dates, artifact size, translation counts and source-linked transport samples. First-time human review must verify the samples against OSM; automated category presence does not independently survey classification accuracy.
8. Upload only the compact artifact, report, licence/source notice, checksum manifest and summary. Never commit generated datasets or upload source PBFs into GitHub build artifacts.
9. On a disposable WordPress/MariaDB 10.11 service, import the exact previous baseline and new artifact, compare provider output with an independent full geographic-candidate/PHP nearest-three reference, inspect EXPLAIN plans, test rendering without HTTP and collect ten cold/warm readings for all eleven districts. Fail unless the geographic index is used, warm reads make zero **POI-table** queries, cold p95 is below 0.5 seconds and the CLI process peak remains below 128 MiB. These initial staging budgets can be adjusted only after reviewed measurements. This is an executable acceptance job, **not a completed benchmark result**.
10. Only after build and database acceptance pass, and only when delivery is explicitly enabled, send a fixed five-file tar.gz to a protected GitHub environment's SSH target. Manual delivery also requires `deliver=true` and `bootstrap=false`; **bootstrap runs always skip delivery**, even if the repository variable is enabled. Scheduled delivery still requires `POI_DELIVERY_ENABLED=true`. Record an immutable accepted release only after the receiver succeeds.

The runner checks at least 8 GiB free disk before downloading. Build job timeout is two hours, database acceptance twenty minutes and delivery forty-five minutes. [GitHub's documented limits](https://docs.github.com/en/actions/reference/limits) include a six-hour hosted-job ceiling and plan-dependent minutes/storage. A ~620 MB input and city-only disk-backed processing are a plausible fit for a standard runner; **actual runtime, peak RAM/disk and private-repository quota usage must be measured in the first run**. If the job fails a budget, retain the live dataset and investigate; do not move heavy processing onto cPanel or silently loosen acceptance.

## Exact first build in GitHub Actions (build only)

The workflow name is **Monthly Istanbul POI refresh**, file `.github/workflows/nearby-poi-monthly.yml`. This file was absent from `main` when checked on 2026-10-13. GitHub requires a `workflow_dispatch` workflow to exist on the default branch before it can be manually dispatched; merely pushing this draft branch does not make the Run workflow button available. Do not bypass that prerequisite with another trigger or a new PR.

1. Under normal repository approval, make this workflow available on default branch **`main`**, with its referenced `tools/nearby-poi/` scripts, canonical policy and theme modules. The normal path is an approved merge of existing PR **#1420**. **Codex has not merged it.** GitHub workflow availability and production WordPress deployment are separate operations; this first build needs no server deployment. Keep repository variable `POI_DELIVERY_ENABLED` unset or exactly `false`, including before the workflow reaches `main`. Ensure repository Actions and artifact uploads are allowed, and sufficient minutes/storage are available.
2. Open [repository Actions](https://github.com/7mus7y-dotcom/pera-wp/actions), then select **Monthly Istanbul POI refresh** in the left sidebar, or open [the workflow page](https://github.com/7mus7y-dotcom/pera-wp/actions/workflows/nearby-poi-monthly.yml). If it is missing or Run workflow is absent, first check the default-branch prerequisite and repository Actions settings.
3. Click **Run workflow**, then select branch **`feature/local-nearby-poi`** in **Use workflow from**. Retain this branch through the first build if branch deletion is normally automatic. Confirm it includes this final workflow revision before running.
4. Set the inputs exactly as follows, then click the green **Run workflow** button:

   | Input | Value | Purpose |
   | --- | --- | --- |
   | `bootstrap` | **`true`** (checked) | Allows the first build with no accepted release; all source, coverage, category and DB gates still apply. |
   | `force` | **`false`** (unchecked) | Normal first build; does not override count or source checks. |
   | `deliver` | **`false`** (unchecked) | Build only; leave production opt-in disabled. |

5. Open the new run. In job **`build`**, monitor tool setup, baseline discovery (no release is valid on the first run), **Build, compare and accept** (Geofabrik Türkiye download/checksum/full parse, padded metropolitan crop, filtered build and validation), and artifact upload. There is no separate download job. Only the GitHub runner downloads OSM; Codex must not retry it.
6. After `build` succeeds, monitor **`database-acceptance`**: disposable MariaDB **10.11** health, disposable WordPress setup, exact-artifact import, nearest-three correctness, geographic EXPLAIN/index checks, cold/warm timings, PHP peak memory and rendering HTTP count. Job **`deliver` must be skipped**. `notify-failure` is skipped on success; on failure it creates a repository issue with all job outcomes and the run URL.
7. From the run's **Summary → Artifacts**, download and unzip:

   | Artifact | Contents | Retention |
   | --- | --- | --- |
   | `nearby-poi-<version>` | `dataset.poi.ndjson`, `report.json`, `LICENSE.txt`, `SHA256SUMS`, `summary.txt` | 90 days |
   | `nearby-poi-db-<version>` | `database-report.json`, `database-acceptance.log` when the acceptance step ran | 90 days, also uploaded on failure |
   | `nearby-poi-failed-<run_id>` | Available `source-info.json`, `source-checksum.txt`, `failure.json`, `build.log`, `report.json` | 30 days, failed builds only |

   `<version>` is the UTC build timestamp plus GitHub run ID, e.g. `20261013T120000Z-123456789`; use the actual artifact name, not this example. No source PBF is retained. A valid build bundle can exist when DB acceptance fails: **its existence is not overall acceptance**. Early setup failures may have no reports; use the job logs. Abrupt termination may leave only partial evidence.
8. In the unzipped bundle directory, run `sha256sum --check --strict SHA256SUMS` (or verify each SHA-256 with your platform's tool). Inspect the first NDJSON line for schema/license/version/source/bounds/count/body digest, then inspect actual POI records and aliases/translations. In `report.json`, require `passed=true`, the expected policy, fresh source timestamp, download/cropped-source/artifact identities, all required subgroup counts and any optional rail count, eleven district/category checks and OSM-linked transport samples. Verify `LICENSE.txt` names OpenStreetMap contributors and ODbL 1.0. In `database-report.json`, require `passed=true` and the same version; inspect all eleven district plans/timings/memory and zero HTTP/warm POI queries. Human transport and property-boundary review remains required.
9. If the run fails, open the first failed step and its diagnostics. Source/checksum errors require checking Geofabrik availability or latest-file rotation and rerunning after resolution. Stale snapshots or inadequate source bounds require source investigation; never substitute build time for source time. Missing subgroup/district counts require inspecting OSM tags, geometry statistics and coverage; fix a demonstrated classifier/source defect through reviewed code. Suspicious baseline changes require comparison with the accepted release, not relaxing ratios. DB failures require inspecting `database-report.json`/acceptance logs for nearest-three, index, cache, import or budget failures; fix the specific cause before rerunning. Baseline/API permission failures require restoring release read access or valid manifests. Missing reports require job-log inspection. Bootstrap never bypasses these gates. Keep `bootstrap=true`, `force=false`, `deliver=false` for a corrected first-run retry when no accepted baseline exists.

### Verified production isolation

For this bootstrap, only GitHub-hosted `build` and `database-acceptance` runners process the dataset. They reference **no production secrets or production environment**; their token has `contents: read`. MariaDB is a disposable CI service at `127.0.0.1:3306`, with a disposable WordPress directory under `work/wordpress`. The delivery condition rejects every manual run with `bootstrap=true`, including one with `deliver=true` or `POI_DELIVERY_ENABLED=true`. No SSH step or accepted-release write is reachable. The failure notifier alone has `issues: write` for reporting.

No production server credentials, SSH connection, production file transfer, live WordPress change, live dataset activation, production DB writes, existing server-cron change, or ACF/property-record writes are needed or performed. Disposable acceptance activation changes only the CI database. Leave `POI_DELIVERY_ENABLED` unset/`false`; **do not enable monthly production delivery as part of this first run**.

## Later production acceptance (separate operator work, not performed here)

1. After successful build-only acceptance and human review, approve production deployment separately. `sync.sh` copies changed `wp-content` files, including the canonical policy JSON and new update module. Root workflow/tools/docs are not copied into the public theme. Workflow availability alone is not production acceptance.
2. Review the real reports and all district counts/OSM-linked Metro/Marmaray/Metrobüs/ferry samples; inspect all available property coordinates and real sources at boundary properties. Check classification/dedup limitations, source timestamp, report size and measured build resources. There is no bootstrap waiver of coverage, count or database gates.
3. On the cPanel filesystem owner's account, back up the WordPress DB and confirm CREATE/REPLACE/INSERT/SELECT/DROP rights. Verify Bash, GNU tar, awk, flock, curl, PHP CLI and WP-CLI paths. Confirm at least 1 GiB free account filesystem disk, enough **database tablespace and cPanel quota** for active + previous + staging, and adequate PHP headroom at 128 MiB. The receiver's `df` check cannot prove DB-server free space or account quota; review these initially and monitor hosting quotas. No credentials go into Git.
4. Create private `/home/d21882/nearby-poi/{bin,incoming,logs}`. Install the reviewed `tools/nearby-poi/activate.sh` as `/home/d21882/nearby-poi/bin/activate.sh`, owned by the filesystem owner, mode 700. Configure its fixed WP/CLI paths if the hosting installation differs. Install a reviewed executable `/home/d21882/nearby-poi/bin/purge-property-cache` using the site's actual cache plugin/CDN mechanism. It is optional in code because the cache product is not known here, but should be configured for prompt visible updates.
5. Use a **dedicated SSH key** with a forced command and forwarding/PTY restrictions, e.g. the public-key line in the account's `authorized_keys`:

   ```text
   restrict,command="/home/d21882/nearby-poi/bin/activate.sh" ssh-ed25519 PUBLIC_KEY nearby-poi-updater
   ```

   Confirm the host's OpenSSH supports `restrict`; otherwise use explicit `no-agent-forwarding,no-port-forwarding,no-X11-forwarding,no-pty` flags. The script accepts only `receive <safe version>` through `SSH_ORIGINAL_COMMAND`; no arbitrary shell/SCP command, repository code, scripts or credentials are accepted in the upload bundle. Add IP restrictions if hosting supports a stable build origin; standard GitHub runners do not promise one.
6. Create GitHub environment **`nearby-poi-production`**, restrict it to the trusted default branch, and add secrets `POI_SSH_KEY`, `POI_KNOWN_HOSTS`, `POI_SSH_HOST`, `POI_SSH_USER`, `POI_SSH_PORT`. Pin the actual host key obtained through a trusted hosting channel; never disable strict host-key checks or trust an unchecked runtime `ssh-keyscan`. Set repository variable `POI_DELIVERY_ENABLED=true` only after the initial real-data acceptance and server setup. Keep required environment reviewers for first rollout if desired; to run monthly without human approval, remove that recurring review gate after approved setup. This requires operator configuration, not developer downloads each month.
7. Add `define('PERA_NEARBY_UPDATES_ENABLED', true);` to `wp-config.php` when monthly maintenance is enabled. Check `admin_email` and real WordPress mail delivery; ensure the existing system cron runs due WP events. Subscribe the responsible operator to GitHub Actions failure and repository issue notifications. No SMTP password is added by this change.
8. Link the static **`/poi-data/index.html`** database offer from the site's existing legal/copyright page, and verify anonymous access. This legal-link edit is an explicit operator setup step; the Nearby Amenities UI remains unchanged.
9. The first production import requires a separate approved rollout of the reviewed, fresh bootstrap bundle through the restricted receiver, followed by recording its matching `poi-accepted-<version>` release with dataset/report/license/manifest and DB report. Bootstrap dispatch cannot perform that rollout. Establish this actual production baseline before a later non-bootstrap run (`bootstrap=false`, manual `deliver=true` if authorized). Enablement, secrets, SSH key, legal link, initial rollout and accepted-release creation remain **not performed** in this task. A merely built bundle must never be labeled production-accepted.

If a legacy local dataset is already active without an acceptance report, automatic bootstrap refuses to replace it: production's active body SHA must match the supplied accepted baseline and carry a valid validation report. Audit/validate that existing artifact and establish its baseline under operator review, or keep it serving while planning a deliberate migration; do not erase live data merely to bypass the gate.

## Production receive/import safety

The forced-command receiver holds filesystem `flock` through transfer/import/cleanup. It caps compressed upload at 110 MiB, requires exactly five regular archive entries, rejects oversized expanded entries/path substitutions/symlinks/hardlinks and verifies the fixed checksum manifest. Downloads and private incoming directories are outside the web root. Interrupted incoming transfers never overwrite an existing version's files; retries must match its manifest exactly.

Before publication it runs production artifact preflight again: full-file SHA-256, streamed body/count/schema/name/coordinate checks, sorted unique IDs, canonical policy, source freshness, subgroup/district checks and comparison against **the actual currently active production generation**. It refuses an outdated baseline after an independent rollback or concurrent operator change. A report saying `passed=true` alone is insufficient.

Public ODbL copies of the exact artifact and source/license notice are written before dataset activation. Public directories/files are readable, and an HTTPS HEAD check must succeed before activation. Only database text and licence notices enter `/public_html/poi-data/`; no executable upload payloads. The static index lists available versioned databases. A failed import may leave a validated candidate's public copy available, but it does not change the active dataset. Public/private archive cleanup is intentionally separate from table cleanup.

Batches default to 1,000 rows (allowed 1–2,000). A complete preflight occurs once per new session; each resumed invocation verifies immutable artifact/report hashes, seeks a persisted byte offset and writes bounded records. Checkpoints are in one non-autoloaded option. Staging rows use REPLACE on prevalidated unique IDs, so a killed batch whose rows were written but checkpoint was not saved can replay safely. No giant transaction, OSM parser or full dataset PHP array runs on production. The receiver enforces a 128 MiB WP-CLI limit; actual plugin overhead must pass cPanel acceptance.

The active option changes only after every record is staged and the table count matches. The coherent option contains active/previous descriptors, validation report and import timestamp. Until that atomic update, pages read the old complete table. A crash after activation but before checkpoint removal retries without overwriting the previous rollback descriptor. Normal concurrent batch/abort/rollback/cleanup operations share the import lock; the receiver's filesystem lock spans all batches. Batch lock leases are five minutes; loss/expiry prevents checkpoint advancement/publication. Legacy manual import and rollback refuse a pending session.

Import failure retains the old active dataset and pending checkpoint for retry; it does not automatically destroy recoverable staging. Explicit abort removes only the staged table, never an active table, including a post-activation crash. A crash before a staging descriptor is persisted can leave an orphan table; aged cleanup handles that later. Fatal process termination cannot synchronously record a PHP exception; the private receiver log, persisted session and daily watchdog cover this case.

After success: inspect status/count, clean safe obsolete tables, purge property caches if the installed adapter exists, then record the accepted GitHub release. A cleanup/cache-purge/release failure **after** activation may report overall job failure even though the new valid dataset is already active: inspect status before recovery; do not presume activation was reverted. Identical accepted version delivery is idempotent.

## Status, notifications and normal monthly operations

```sh
wp --path=/home/d21882/public_html pera nearby status
wp --path=/home/d21882/public_html pera nearby dataset
```

Status returns active version/body SHA, OSM source timestamp, last successful import, POI count, planned next first-of-month refresh and timezone, previous version, last attempt/error, pending row count and enabled flag. The schedule field describes the configured timetable, not proof that GitHub is running. No public admin notice or frontend CSS is introduced.

Actions failures/cancellations create an actionable repository issue with run URL and build/delivery outcomes. The independent daily WordPress monitor emails `admin_email` on recorded errors, imports pending more than six hours or source data older than 45 days. Email is rate-limited to one/day, and failed mail attempts do not set the throttle. Verify mail delivery and notification subscriptions initially. A completely disabled GitHub schedule cannot create its own failure issue; the stale-data watchdog is the independent signal.

After approved setup, monthly acquisition, checks, comparison, real DB acceptance, secure transfer, resumable import, activation, table cleanup, archive creation and failure issue creation are automatic. Operators intervene for suspicious drift, failed source/SSH/DB checks, stale data or quota exhaustion; no routine manual file download is required. Secrets and host keys require normal credential lifecycle maintenance. No production operation was executed by Codex.

## Recovery, rollback and retention

Resume by rerunning delivery of the **identical bundle/version**, or on the server:

```sh
wp --path=/home/d21882/public_html pera nearby status
wp --path=/home/d21882/public_html pera nearby import /home/d21882/nearby-poi/incoming/VERSION/dataset.poi.ndjson \
  --report=/home/d21882/nearby-poi/incoming/VERSION/report.json --batch-size=1000
# Repeat bounded invocations until JSON complete=true. Do not change files during the session.
```

To abandon failed/interrupted staging, then roll back:

```sh
wp --path=/home/d21882/public_html pera nearby abort-import
wp --path=/home/d21882/public_html pera nearby rollback
wp --path=/home/d21882/public_html pera nearby status
```

Purge the site's page cache after rollback. Repeated rollback toggles the last two complete generations. Generation-aware exact-coordinate caches select the correct old results automatically. A missing previous table is rejected. After rollback, reconcile the accepted release baseline with the restored dataset before the next monthly run; the production comparison intentionally rejects a build based on the wrong generation. Publish/recover the original matching accepted report/artifact, rather than weakening the drift guard.

`pera nearby cleanup` automatically removes only matching generation tables **older than seven days** that are neither active, previous nor pending staging. Table creation dates must be available; unknown dates are retained. It excludes other prefixes/tables and recent/in-flight generations. Both active and previous tables survive routine cleanup. Old generation transients expire after seven days.

Validated run bundles/database reports are GitHub Actions artifacts retained for 90 days; failure diagnostics for 30 days. Artifacts may expire or consume plan quota, so they are **not** the sole rollback store. Accepted releases contain exact dataset/report/manifest/licence plus the real DB acceptance report and have no automatic expiry; retain at least twelve monthly accepted releases plus every currently active/previous/pending recovery bundle. Private receiver copies and public ODbL versioned copies are also retained without automatic deletion. Review their disk usage quarterly and remove only versions no longer needed for active/previous/recovery/licensing availability. This intentional operator retention review avoids accidental deletion of required recovery/licensing copies; it is not a monthly developer download task.

Recover a lost server artifact from its accepted release, verify SHA256SUMS, then deliver through the same restricted receiver. If an accepted release write failed after production activation, recover the identical bundle from the run artifact/private incoming directory, inspect status and create the missing matching accepted archive under operator review. Private GitHub release storage does not itself satisfy public ODbL access; the public database offer is separate.

## Query performance and accuracy changes

Cold queries retain the subgroup/geographic index and 256-row batches. At most three nearest distinct facilities per subgroup are retained. Cache key contains the **active generation table, classification version 1 and exact serialized property coordinates**, never a rounded cell centre. Payloads above 128 KiB are not cached. Cache TTL is seven days; old/new/rollback generations cannot share entries. Two properties in the same legacy 0.01-degree cell can therefore have different correct nearest facilities. Every rendering still calculates distances from the property's exact coordinates using the existing PHP Haversine function.

Numeric SQL parameters and inserted DOUBLE coordinates now use round-trip JSON representations instead of `%f` six-decimal formatting. This preserves sub-microdegree coordinate/order accuracy rather than silently rounding before choosing the third nearest facility. Airport caching remains separate.

Cold-provider SQL remains up to fifteen subgroup queries, plus additional batches for heavy duplicates and WordPress cache-option overhead. Warm exact-coordinate results make zero POI-table queries, although WordPress may query its options/transient store across separate requests without persistent object caching. Synthetic harness timings are not MariaDB measurements. Run `pera nearby benchmark` on a populated staging installation for immediate per-district cold/warm timings; the workflow's `staging-check.php` adds p50/p95, EXPLAIN and independent nearest-three reference checks. cPanel traffic/plugin/page-cache latency must still be measured separately.

## Licensing

[ODbL section 4.4](https://opendatacommons.org/licenses/odbl/1-0/) covers share-alike for publicly used derivative databases; section 4.6 requires offering the entire machine-readable derivative database **or** sufficient alterations/derivation material, free for internet distribution. We use the straightforward entire-database offer: exact accepted `dataset.poi.ndjson` plus source/licence notice in `/poi-data/VERSION/`, indexed at `/poi-data/index.html`. Maintain the existing visible OSM contributor/ODbL attribution and an accessible legal-page link to that offer. Do not claim that private GitHub backups or a mutable latest-source URL alone supply this offer. The airport source remains independently attributed OurAirports public-domain data.

## Local tests and outstanding acceptance

Final bootstrap review on 2026-10-13 corrected confirmed defects: unconditional repository-variable delivery during bootstrap; WP-CLI `eval-file` path/global scope in the DB acceptance script; loss of DB failure reports; omission of the DB outcome from failure issues; and receiver preflight rejecting an identical already-activated bundle on retry. Source/build errors now retain `failure.json` and logs; build summaries distinguish artifact validation from database/human acceptance. Query design, monthly timetable, canonical coverage policy and OSM/ODbL attribution remain as reviewed.

Final offline verification passed on **PHP 8.4.24**: all 16 targeted Python tests (including 48 delivery-condition combinations, explicit-root eval scope and failed-preflight report retention), existing PHP provider/render/airport/local contracts and resumable-update contracts including already-active receiver preflight, PHP lint of relevant PR files, Python compilation, Bash syntax, workflow YAML/dependency/output/input validation and Bash parsing of its run steps. PyYAML was already installed for local workflow validation; no dependencies were installed. `actionlint`, Osmium/Pyosmium, MariaDB and a populated disposable WordPress were unavailable locally; no download or external shell operation was attempted. Workflow validation here is static and contractual, not GitHub's execution-time validation. The real runner's PHP/WordPress/MariaDB combination and the production PHP/plugin combination remain acceptance gates.

Passed in this environment: existing external/local regression suite; seven monthly Python contracts (successful synthetic comparison/orchestration, unavailable download, checksum/truncated source, incomplete/stale source metadata, missing district envelope/counts, suspicious drift, missing baseline and duplicate-month skip); resumable PHP contracts (partial write failure/checkpoint replay, duplicate lock, activation failure, crash after activation, prior-generation preservation/rollback, abort, protected cleanup, exact-coordinate and generation-aware cache behavior, status, rate-limited admin email); three temporary-directory receiver contracts (success/retry, failed import recording, command/archive-path rejection); PHP/Python/shell syntax, workflow YAML/shell parsing and Git whitespace checks. Fixtures contain **770 synthetic POIs**, never real coverage evidence.

Reproduce the contract fixtures/tests:

```sh
PHP=php python3 tools/nearby-poi/test_build.py
python3 tools/nearby-poi/test_refresh.py
PHP=php python3 tools/nearby-poi/test_bootstrap.py # Uses already-available PyYAML for workflow parsing.
PATH="/path/to/php-bin:$PATH" python3 tools/nearby-poi/test_receiver.py
mkdir -p work/update-fixtures
python3 tools/nearby-poi/create-test-fixtures.py work/update-fixtures
NEARBY_UPDATE_FIXTURES="$PWD/work/update-fixtures" php wp-content/themes/hello-elementor-child/tests/nearby-updates-test.php
```

Executable real acceptance without production delivery:

```sh
# On an approved offline machine with tools installed; fails safely if data/policy cannot pass.
python3 tools/nearby-poi/refresh.py --directory=work/real-acceptance --version=REVIEWED_VERSION --bootstrap
# For subsequent builds add --previous=/path/to/last-accepted-report.json instead of bootstrap.
```

Or run the manual GitHub bootstrap with delivery disabled, which includes the disposable MariaDB acceptance job. The real report must replace all count/size/runtime estimates. First acceptance still needs: actual PBF/transport review, every property coordinate inside the chosen coverage, real DB job success, cPanel DB/quotas/memory/import/concurrency tests, verified mail and GitHub subscriptions, SSH host pinning/restricted key, accessible ODbL offer/legal link, rollback drill and page-cache adapter. None of these production checks or the real-data workflow has been executed here.
