# Campaign scheduler and queue operations — Phase 4C

Production dispatch is unconditionally disabled. This release has no production
worker/provider factory, enable switch, send/resend/reset command or installed
campaign dispatch event. Unsubscribe, suppression, preference/event infrastructure
and existing CRM cron tasks remain independent of this dispatch gate.

The hosting configuration has `DISABLE_WP_CRON=1`. Traffic does **not** run cron.
Nothing in this task installs server cron, changes this constant, deploys code or
activates sending. The `peracrm_campaign_scheduler` action is the sole production
boundary: direct and scheduled invocation both return `disabled` before any
production delivery read, claim, submission, recovery or completion mutation.
Only bounded invocation metadata changes in the configured target blog.
Repeated init/activation never schedules a dispatch event, on any blog.

## Read-only CLI

Use the host's normal PHP/WP-CLI binary, WordPress path and an authorized real
user on the configured PeraCRM target blog. For multisite use the target URL;
services also switch to the configured target internally and restore the caller.
Report access requires `peracrm_view_campaign_reports` and rejects impersonation,
even if schema readiness fails. Root/shell access alone is not report authority.

```sh
wp --path=/path/to/wordpress --url=https://target.example --user=REPORT_USER peracrm campaigns status --format=json
wp --path=/path/to/wordpress --url=https://target.example --user=REPORT_USER peracrm campaigns health --format=json
wp --path=/path/to/wordpress --url=https://target.example --user=REPORT_USER peracrm campaigns due --limit=10 --format=json
```

`status` and `health` expose the same precise snapshot. `due` returns at most
1–25 job IDs/states/UTC schedules with claimable work, not recipient rows.
Only `json` (default) and `text` are accepted. Output excludes addresses, content,
tokens, credentials, worker owner/attempt/delivery keys and provider payloads.
Diagnostics are fixed codes; exception/SQL text is not returned. Counts are
indexed aggregate facts, not a transactionally frozen cross-query snapshot.

Exit codes: **0** read inspection or completed/bounded fixture run; **2** production
status/health is disabled; **3** rejected input/authority/fixture environment;
**4** blocked health/readiness, unknown/expired work needing attention, execution
failure or overlap. A disabled status is expected, not healthy processing.
`due` is an inspection and returns 0 even when dispatch is disabled. A JSON error
is still printed on handled failures. Unknown CLI subcommands fail in WP-CLI.

Facts are separately labelled: dispatch state, physical schema readiness, module
enabled state, combined queue readiness, queued/scheduled/
preparing/paused/cancelled jobs, jobs with currently claimable due work, active and
expired leases, unknown outcomes, all retries and currently due retries. Counts
include cancelled jobs' unresolved delivery history; lifecycle intent never
rewrites outcomes. `last_invocation` records result and bounded counts.
`last_successful_processing` advances only on a persisted known terminal result
(accepted/excluded/failed), never merely invocation, recovery, retry or unknown.
`last_provider_acceptance` reads ledger acceptance, distinct from scheduler
metadata. Neither implies inbox delivery (`delivery_verified=false`). A cron event
is never evidence of healthy processing. Missing metadata means not observed;
a process killed before recording can leave the previous invocation visible.

## Disposable fixtures only

These commands require all of: explicit `--confirm-disposable=campaign_fixture`,
`PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE===true`, exact database `campaign_fixture`,
local WP environment, WP-CLI and the previously established 256-bit fixture
worker authority. Only after these checks are test-owned fake dependencies loaded.
They require prepared synthetic jobs; they do not create or reset production work.

```sh
wp peracrm campaigns fixture-run --confirm-disposable=campaign_fixture --format=json
wp peracrm campaigns fixture-recover --confirm-disposable=campaign_fixture --format=json
```

All writes and fake outcomes stay in target-blog `peracrm_fixture_worker_*`
ledgers and `peracrm_fixture_scheduler_*` metadata. There is no production table
selector, arbitrary provider/fixture file path, force reset or dispatch enablement.
The normal plugin never loads a fake provider. Use the disposable Compose suites,
not a copied production database, to exercise these commands.

## Bounds, overlap and recovery

One invocation visits at most **3 jobs**, processes at most **10 recipients total**,
recovers at most **10 rows total**, and has a **25-second work-start deadline**.
A new submission requires the full **20-second worker transport budget** remaining.
There is no preclaiming of waiting recipients. The accepted worker retains its
60-second owner/version lease, four-attempt limit, fresh eligibility checks,
submission authorization boundary and unknown-outcome rules. Smaller internal
fixture bounds are permitted; CLI cannot increase bounds.

A target-table-specific MySQL `GET_LOCK(...,0)` excludes overlapping scheduler
invocations immediately; the losing call returns `overlap` and does not overwrite
active-run metadata. Production and fixture lock namespaces differ. The same
connection holds the advisory lock until `finally` releases it or process death
closes the connection. Direct worker concurrency remains protected by the worker's
job/delivery locks and owner/version fencing. Advisory lock ownership alone never
authorizes a submission or stale result write.

Elapsed time bounds whether new work may begin, not forcible interruption of a
synchronous dependency or database lock wait. The neutral worker passes a
20-second total transport timeout; a future adapter must enforce it. Host process
watchdogs and database lock timeouts must be validated before production activation.
A dependency ignoring its timeout can overrun; the 60-second lease fence rejects
late persistence and recovery holds inflight outcomes as unknown. Do not describe
a watchdog kill or expired lease as proven non-acceptance.

Maintenance only recovers bounded expired claims/inflight rows, including paused
or cancelled jobs. Expired unsubmitted claims refund the reserved attempt;
expired inflight work becomes unknown. Accepted and unknown work cannot be
reclaimed. There is no history/recipient/tombstone cleanup in this scheduler.
Cancelled revision/delivery fences, immutable published history, consent,
suppressions, existing subscriber cleanup and staff evidence requirements remain.
A crashed scheduler connection releases its advisory lock, but recovery must still
wait for/fence expired worker ownership. An accepted request interrupted before
result persistence requires independently verified reconciliation, never resend.

## Backup restore and later server-cron activation

After any restore, keep dispatch disabled, quiesce future scheduler processes and
compare restore point/ledger keys/attempts with independently verified provider
history. Restored held/retry work may already have been accepted after the backup.
Do not run claim/recovery/reset to infer otherwise. Preserve evidence and cancelled
revision fences; staff reconciliation retains real-actor authority, nonce, expected
version and provider-verified delivery/attempt-bound evidence. This phase supplies
no provider lookup, restore reset or production reconciliation CLI.

A later reviewed activation must first supply a concrete adapter with enforced
timeouts/idempotency/lookup semantics, production gate/readiness policy, restore
reconciliation, monitoring and a scheduler installation decision. There is no
command in this release that enables production dispatch. Do not install the
following example now:

```cron
# FUTURE ONLY: installed by hosting operations after separate activation approval.
# Run as the existing WordPress owner, with host-specific paths and protected logs.
* * * * * flock -n /run/lock/peracrm-campaigns.lock /usr/local/bin/wp --path=/path/to/wordpress --url=https://target.example cron event run peracrm_campaign_scheduler >> /protected/log/peracrm-campaigns.log 2>&1
```

That future command requires a separately reviewed target-blog event installation;
no such event exists here. With `DISABLE_WP_CRON=1`, explicitly invoke the specific
hook through server cron/WP-CLI after that future change. Do not substitute an
all-sites/all-due-events job: existing CRM maintenance has separate ownership.
`flock` is host overlap protection; the database scheduler lock remains necessary
across hosts. Alert on missed invocations, blocked readiness, expired leases and
unknown outcomes separately from successful processing and provider delivery.
No production cron, provider, credentials, UI or public collection is activated by
this runbook.

## Phase 5: local SES event inspection and bounded redrive

These commands process **already durably authenticated local receipts**. They do
not fetch SNS messages, verify a new signature, confirm subscriptions, submit mail,
create resend permission, grant consent, clear suppression, install cron or enable
HTTP. All production sending gates remain disabled. Existing receipt authentication,
certificate/payload hashes, received time, freshness quarantine and normalization
policy remain immutable. Redrive uses the existing five-pass missing-binding matcher
and cumulative hard-bounce/complaint application. Acceptance, cancellation and
unknown-outcome fences remain in their owning services.

A deployment owner must separately supply `PERACRM_SES_EVENT_OPERATIONS` as a PHP
array containing exactly `deployment` (1–64 ASCII letters/digits/underscore/hyphen),
`blog` (positive integer target blog), `account` (12-digit AWS account), `region`,
`topic` (exact SNS ARN matching account/region), `configuration_set` and
`configuration_version` (positive integer). Copy the **original** receipt deployment,
SNS and SES policy pins from the approved deployment inventory. Never change pins
to work around a conflict. There is no option, request parameter or filter to supply
production configuration. This constant is absent by default and independent of
`PERACRM_SES_SNS_HTTP`; configuring operations never enables the HTTP endpoint.
Configuration is a later deployment decision, not an action taken by this PR.

Use an authorized target-blog real user; root/shell access is insufficient.
`event-health` requires `peracrm_view_campaign_reports`. `event-redrive` additionally
requires `peracrm_manage_campaigns`, explicit confirmation and an explicit limit.
Both reject impersonation. On multisite the operation switches to the configured
PeraCRM target and restores the caller; configured blog must match that target.

```sh
wp --path=/path/to/wordpress --url=https://target.example --user=REPORT_USER peracrm campaigns event-health --format=json
wp --path=/path/to/wordpress --url=https://target.example --user=OPERATOR_USER peracrm campaigns event-redrive --limit=10 --confirm=authenticated-events --format=json
wp --path=/path/to/wordpress --url=https://target.example --user=REPORT_USER peracrm campaigns health --format=json
```

Only JSON (default) and text are supported. Output contains bounded result counts,
UTC times, ages and fixed diagnostic codes; it contains no receipt/event/attempt
identifiers, raw payloads, mailboxes, bearer URLs, bodies, SQL, secrets or exception
text. Do not add verbose payload logging around these commands. Host/WP-CLI/PHP,
proxy, database general-query, error and APM logging protections still require
separate host verification; sanitized command output does not sanitize host logs.

A redrive invocation selects at most **1–25 receipts** (at most 50 facts each),
with a **25-second work-start deadline**. Bounds never forcefully interrupt SQL;
operations must validate database lock timeouts and process watchdogs. A per-scope
MySQL advisory lock excludes overlapping redrives immediately. HTTP processing
waits at most five seconds for that lock before returning retryable 503; existing
receipt authentication and downstream transaction/session fences still apply.
No worker claims a waiting batch in advance. Oldest/unattempted work is selected
first, with receipt IDs used only internally as tie breakers.

Progress states are durable, scoped to the original deployment/blog/topic and
pinned normalization policy:

| State | Meaning and operator action |
| --- | --- |
| No progress / `pending` | Full pipeline completion has not been observed, possibly because a process died. Eligible for bounded resume. Absence of progress on older receipts is not proof of a failure. |
| `failed` | A local dependency/storage/processing failure was observed. Stop and investigate fixed diagnostics and infrastructure before another bounded invocation. |
| `waiting` | Authenticated normalized facts lack an immutable attempt binding. Investigate original binding persistence; an explicit invocation may retry. |
| `completed` | Eligible cumulative suppression processing committed locally. Terminal; no resend or inbox-delivery meaning. |
| `quarantined` | Preserved freshness/normalization quarantine or unsupported feedback. Terminal and excluded from automatic redrive. Not an infrastructure failure. |
| `conflict` | Permanent event/binding/recipient/scope/message conflict. Terminal manual-review hold; never automatically released. |
| `exhausted` | A missing binding reached the existing five-total-pass limit per logical event, across duplicate receipts. Terminal manual-review hold. |

Freshness and normalization quarantines are also excluded directly from batch
selection, including receipts with no progress row. Mixed receipts with a permanent
conflict remain held even if another fact has a missing binding. Unrecognized stored progress states also fail closed on replay and are counted as
manual-review holds. No command resets
progress, deletes evidence, overrides policy, releases quarantine or increases the
matcher pass budget. Each stage owns its existing transaction. Progress is marked
pending before downstream work; successful completion follows the stage COMMITs.
If interrupted, already committed stages are idempotently replayed and one logical
suppression audit is retained. SQL-session replacement cannot publish progress
under the old lock. A crashed process releases its native lock when its connection
closes; it may leave pending progress. Never manually clear a lock held by a live
process or erase progress to force another attempt.

Stop on exit **3** (invalid arguments/authority), **4** (overlap, blocked storage,
failed processing or manual-review health counts), unexpected failures, rising
conflicts or unexpected scope/readiness. Exit **2** means operations configuration
is absent; it does not mean provider health is good. Exit **0** means a read or a
bounded run completed; inspect waiting/quarantine/conflict/exhausted counts even
on 0. A run stops at its first processing failure. Repeat only after the cause is
understood, with an explicit small limit. After a crash, inspect `event-health`,
confirm the prior process/connection has ended, rerun the bounded command and
inspect again. After a restore, keep sending disabled and follow the restore
reconciliation steps above before processing: local history cannot establish what
SNS/SES delivered outside the restored database.

`health`/`status` include the same `events` summary when configured. Counts report
pending/failed processing, waiting receipts, latest unmatched/conflicting logical
events, manual-review receipts, policy-pin conflicts and ordinary quarantines separately.
Policy conflicts require restoring the original approved pins; no command migrates
or overrides stored normalization policy. General health emits fixed actionable
diagnostics for event failures, manual-review work and policy mismatches. Oldest actionable
age is measured from original receipt arrival for eligible no-progress/pending/
failed/waiting work; null means no locally actionable receipts observed.
`last_successful_processing` and `last_successful_redrive` inside `events` are the
latest durable non-failing pipeline results, including waiting/quarantine; they
are not timestamps of message delivery or necessarily suppression application.
Legacy completed work without a progress record remains unobserved until bounded
processing. The top-level scheduler timestamps retain their existing meanings.
The aggregate snapshot uses separate SELECTs and may change during concurrent
intake; it is not a frozen audit export. Read-only inspection writes no progress,
metadata or locks and performs no authentication or network calls.

Local counts **cannot establish provider health, complete event delivery, upstream
loss, SNS subscription correctness or DLQ emptiness**. External SNS subscription/
DLQ provisioning and retry policy, real-host heartbeat/alerting, privacy-safe log
configuration, retention/deletion, backup/restore and reconciliation decisions
remain explicit operational dependencies. No automatic schedule is installed;
operator invocation and future monitoring/activation require their own decisions.

## Explicit SES suppression synchronization (disabled in production)

`wp peracrm campaigns suppression-health --format=json` reports the disabled
production factory. `suppression-sync` requires a real non-impersonated actor with
report, campaign-management and subscriber-management capabilities, explicit
`--pages=1` (1–10) and `--confirm=account-deny-only`. It returns exit 2 while the
production factory is disabled; no option, credential or filter activates it.
There is no schedule. Do not provision credentials or invoke AWS for this PR.
Disposable injected fixtures exercise the internal service in both topologies.

A future separately approved production adapter must attest the configured AWS
account, pin region/deployment/target blog, and authorize importing account-wide
denials. SES cannot filter those entries by WordPress blog/configuration set.
It must obey the documented complete-response/TLS/size/timeout executor contract.
No production adapter or credential acquisition is supplied here.

When enabled by future work, inspect only fixed health fields: state, pages,
entries observed (duplicates included), last_complete_at, stale and error_code.
Stale means never completed or last completion over 24 hours old. Progress is
not completeness. `sync_read_failed` preserves the next cursor and committed
pages; repeat the bounded explicit invocation. `sync_overlap` requires waiting
for the active operator. `sync_storage` requires database/session investigation.
`sync_scope` requires correcting pins/target context; never switch targets to
force an import. `sync_invalid_page` / `sync_unsupported_reason` require manual
provider/protocol review. `sync_invalid_cursor`, `sync_stale_cursor` and
`sync_cursor_cycle` require investigation and an explicit
`--restart=abandon-cursor` with the normal confirmation/bounds to restart safely.
Restart never deletes deny facts/audits or prior complete time. Remote absence,
removal, changed case or an empty list cannot grant consent or clear suppression.

Never print or paste cursors, mailboxes, credentials or raw provider responses
into CLI output, logs or incident tickets. Health does not expose those values.
Existing suppression provenance and historical flags must survive restore and
reconnection; schema v37 is additive. Run the repository's complete disposable
single-site/multisite SQL/race suite before accepting sync changes. Mocks cannot
establish IAM access, real AWS behavior, snapshot consistency or inbox delivery.

## Durable sending budget prerequisite (production disabled)

`wp peracrm campaigns budget-health --format=json` exposes only fixed readiness
and breaker fields. `budget-recover --version=1 --confirm=breaker-only` requires
a real, non-impersonated actor with report, campaign and subscriber management
capabilities. Both return disabled (exit 2) with the unconditional null production
factory. No CLI quota setter, budget reset, recipient release, sending switch or
scheduler is installed. Internal configuration/recovery are actor-audited; recovery
also requires fresh quota and passing readiness and rejects stale breaker versions.

The designated CRM target blog owns one account/region ledger for every campaign
and worker using it. Do not configure independent blogs/installations as separate
budgets for the same provider account and call that account-wide protection.
External senders, other databases and uncoordinated installations require a shared
coordinator/exclusive allocation. Account quotas, actual available headroom, host
ownership and production acceptance are explicitly UNVERIFIED.

Before reservation, cancellation or denied preflight costs nothing. After durable
reservation, cancellation, proven rejection and accepted/unknown outcomes are all
charged conservatively. Pending/crashed reservations never expire or refund;
completed charges age out only after a full rolling window from completion. There
is no manual release API. Investigate stranded capacity without deleting rows,
changing owners or inventing non-acceptance. Recovery of an operator breaker cannot
resolve uncertain attempts, grant send authority or bypass any failed readiness.
A throttle cooldown may expire automatically, but it never resets usage or resends.

Persist breaker signals before recipient writes. If signal persistence fails, stop
that worker and investigate storage; the prerequisite does not supply an outbox
or claim outage-wide blocking across other processes. Account/auth/disabled mapped
rejections latch an operator hold. Inspect fixed health fields and version, correct
the underlying issue, refresh independently verified quota through a future
approved adapter, then request authorized recovery with all readiness checks.
No credentials or AWS calls are supplied here. Do not paste account IDs, owner/
attempt hashes, mailboxes, raw provider errors or credentials into incident logs.

Clock rollback fails closed against durable high-water time. Integer-second and
inclusive boundaries are conservative. Forward clock jumps can incorrectly age
usage; host clock discipline and a production clock adapter remain prerequisites.
After restore, keep all dispatch gates disabled: a backup may omit recent usage,
breakers and accepted/unknown evidence. Reconcile the authoritative provider and
all coordinated senders, preserve uncertainty/acceptance fences and re-establish
ownership/clock/readiness. Do not reset the ledger to regain capacity. Wait at
least a full real rolling day after all potentially lost/in-flight submissions
are fenced and stopped, and separately resolve permanent pending holds through a
future reviewed restore protocol. This PR provides no restore/reset automation.

Remaining integration: reserve under final worker job/lease/cancel/identity and
acceptance/conflict fences; recheck complete/fresh suppression synchronization
immediately before transport; persist signals before result writes; handle failed
signal persistence durably; exercise mocked transport across those boundaries.
Production clock/quotas/host ownership, external coordination, monitoring and
controlled acceptance remain separate work. Broader production readiness stays
blocked; all existing disabled gates remain unchanged.
