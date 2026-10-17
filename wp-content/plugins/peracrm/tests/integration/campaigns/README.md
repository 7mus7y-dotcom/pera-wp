# Disposable campaign foundation integration fixtures

This setup runs the **real plugin**, WordPress 6.8.3 / PHP 8.3, WP-CLI 2.12.0 and MySQL 8.0.43. It runs once as single-site and once as subdirectory multisite (origin blog 1, CRM target blog 2). Only PeraCRM is mounted; WordPress uses its bundled default theme. No repository theme code or assets are present.

Prerequisites: Docker Engine with working local socket access, Docker Compose v2 (`up --wait` support), network/registry permission to pull the three pinned image tags, and enough disk space for disposable volumes. No host WordPress, PHP, MySQL, provider credentials or production database is used. Tags pin versions; record resolved image digests alongside results if reproducing across architectures or future registry changes.

From the repository root, run:

```sh
docker compose -f wp-content/plugins/peracrm/tests/integration/campaigns/compose.yaml config --quiet
sh wp-content/plugins/peracrm/tests/integration/campaigns/run.sh
```

To run one topology, pass `single` or `multi` to the same runner. The path-scoped `.github/workflows/peracrm-campaign-foundations.yml` invokes these modes on GitHub-hosted Ubuntu 24.04 runners for pull requests and manual runs. It grants only repository read permission, uses no secrets or production environment, and retains synthetic integration logs for 14 days. A workflow being configured is not evidence of a successful run; record its actual result below and in the living plan.

The runner creates a unique Compose project, runs both fixtures, exits nonzero on any failed assertion/worker, and removes only its own containers/volumes on exit. It publishes no host ports, mounts the plugin read-only, uses an internal Docker network and synthetic fixture-only passwords. The fixture-only MU plugin blocks `wp_mail` and outgoing WordPress HTTP; `DISABLE_WP_CRON` prevents background execution. The fixture guard constant is required before any test can write/drop data. These tests deliberately drop campaign tables **only in disposable fixtures**; PeraCRM has no down migration.

Coverage:

- Fresh activation, schema-20 upgrade through registered `init`, repeated real `dbDelta`/init, schema assertions and record preservation.
- Full unique mailbox keys in both subscriber and suppression tables reject raw SQL duplicate inserts. Services normalize case/whitespace and preserve plus-tags/dots.
- A separate migration race pauses a losing request before native lock acquisition, lets one owner enter real DDL, and requires the competing ordinary request to return before that owner is released. Exactly one connection must run all four campaign installers. A genuine stale attempt then loses its native lock and resumes only after a newer administrator retry publishes readiness; all late status/marker writes and successor-lock release must be rejected.
- Four independent WP-CLI processes concurrently create/update one mailbox and add all four suppression reasons. A fifth process deliberately holds the mailbox mutex for eight seconds. Assertions require actual contention, one subscriber, four audited reasons, no lost version increments and continued ineligibility.
- Real target-blog administrator/manager/employee roles and manager/employee/anonymous service checks, originating-blog isolation, and blog restoration. The manager is a target-only user, not a super administrator.
- A real missing-key assertion failure through `init` leaves later hooks running, denies campaign services and recovers after explicit key repair and administrator retry. Standalone tests additionally inject installation errors, test the failure latch, impersonation, diagnostic redaction and retry authorization.

## Current execution evidence (2026-10-17)

Compose configuration validation and shell syntax checks pass. Local runtime execution is blocked: this managed executor's local Docker socket returns `connect: operation not permitted`; UID 1000 cannot restart the running daemon. No local WordPress/MySQL containers were started. The first actual GitHub-hosted run [39049115966](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39049115966) passed all multisite assertions. Single-site reached the schema-20 upgrade, then exposed a fixture call to multisite-only `ms_is_switched()`. That assertion is now topology-guarded. The full matrix rerun [39049422823](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39049422823) on commit `7df6669dc6b63a07e4fd487bb8ed136ea271c318` passed both single-site and multisite, including real migrations, unique keys, concurrent mutations/lock contention, zero-valued evidence and target-blog capabilities/context. Retained logs: [single](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39049422823/artifacts/12107470637), [multi](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39049422823/artifacts/12106738432), expiring 2026-10-31. Required isolated integration checks have passed; Phase 1 remains in progress for acceptance review. This setup does not establish historical consent/retention policy, production topology or production compatibility.

## Administrator migration recovery

On a real installation, a failed migration leaves campaigns unavailable, retains the previous schema marker and stores only `schema_verification_failed` plus a UTC timestamp. Ordinary requests stop retrying. A real administrator can use the WordPress admin notice's **Retry campaign schema verification** POST form (nonce protected; impersonation denied). Repair database permissions/table definitions first. A stuck `checking` attempt is manually retryable after 15 minutes only when its native MySQL migration slot is free; it is never retried automatically by public traffic and a live owner cannot be taken over. Publication/release require both the current attempt token and native connection ownership.

For an authenticated CLI alternative, use a real target-blog administrator ID (not a manager or an impersonated session):

```sh
wp --user=ADMIN_ID eval '$result = peracrm_campaigns_retry_schema(); if (is_wp_error($result) || $result !== true) { WP_CLI::error("Campaign schema is still unavailable."); } WP_CLI::success("Campaign schema verified.");'
```

When the stored schema marker is already 21, retry is read-only verification; a missing key/table must be repaired explicitly before verification can succeed. When the marker is below 21, retry runs the additive upgrade again. Neither path clears consent history or suppressions. No campaign sending feature exists in this phase.

Atomic migration-slot follow-up: [run 39050763912](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39050763912) on `8ea4f9dc337e2231cc000660fb79190f7edc587d` passed both single-site (`117448694512`) and multisite (`117448694858`), including the real pre-acquisition race, sole DDL connection, competing-request availability, stale status/marker/release fencing and interrupted administrator recovery. All earlier fixture checks also passed. Logs: [single](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39050763912/artifacts/12107677276), [multi](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39050763912/artifacts/12107906044), retained until 2026-10-31. Phase 1 remains in progress for acceptance review.
