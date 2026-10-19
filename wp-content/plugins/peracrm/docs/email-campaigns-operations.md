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

Facts are separately labelled: dispatch state, schema readiness, queued/scheduled/
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
