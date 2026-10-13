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
10. Only after build and database acceptance pass, and only when delivery is explicitly enabled, send a fixed five-file tar.gz to a protected GitHub environment's SSH target. Record an immutable accepted release only after the receiver succeeds.

The runner checks at least 8 GiB free disk before downloading. Build job timeout is two hours, database acceptance twenty minutes and delivery forty-five minutes. [GitHub's documented limits](https://docs.github.com/en/actions/reference/limits) include a six-hour hosted-job ceiling and plan-dependent minutes/storage. A ~620 MB input and city-only disk-backed processing are a plausible fit for a standard runner; **actual runtime, peak RAM/disk and private-repository quota usage must be measured in the first run**. If the job fails a budget, retain the live dataset and investigate; do not move heavy processing onto cPanel or silently loosen acceptance.

## First-time setup (operator work, not performed here)

1. Review and merge/deploy code separately under normal approval. `sync.sh` copies changed `wp-content` files, including the canonical policy JSON and new update module. Root workflow/tools/docs are not copied into the public theme.
2. Run a manual **build-only bootstrap**, leaving repository variable `POI_DELIVERY_ENABLED` unset/false. Download its compact report and separate real MariaDB acceptance report. Review all district counts and OSM-linked Metro/Marmaray/Metrobüs/ferry samples; inspect real sources at boundary properties. Check classification/dedup limitations, source timestamp, report size and measured build resources. There is no bootstrap waiver of coverage, count or database gates.
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
9. Rerun the approved bootstrap with delivery enabled. The receiver imports the validated compact dataset; only then does Actions save an accepted release. Enablement, secrets, SSH key, legal link and any production execution remain **not performed** in this task.

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

Passed in this environment: existing external/local regression suite; seven monthly Python contracts (successful synthetic comparison/orchestration, unavailable download, checksum/truncated source, incomplete/stale source metadata, missing district envelope/counts, suspicious drift, missing baseline and duplicate-month skip); resumable PHP contracts (partial write failure/checkpoint replay, duplicate lock, activation failure, crash after activation, prior-generation preservation/rollback, abort, protected cleanup, exact-coordinate and generation-aware cache behavior, status, rate-limited admin email); three temporary-directory receiver contracts (success/retry, failed import recording, command/archive-path rejection); PHP/Python/shell syntax, workflow YAML/shell parsing and Git whitespace checks. Fixtures contain **770 synthetic POIs**, never real coverage evidence.

Reproduce the contract fixtures/tests:

```sh
PHP=php python3 tools/nearby-poi/test_build.py
python3 tools/nearby-poi/test_refresh.py
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
