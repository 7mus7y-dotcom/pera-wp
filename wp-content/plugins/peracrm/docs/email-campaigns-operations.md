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
Operator recovery preserves an active cooldown from a newer throttle signal.

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

### Pending submission latch (v39 prerequisite; integration disabled)

`beginSubmission` is a trusted internal accounting API, not worker authorization.
After an original durable reservation it rechecks readiness/quota/clock/breaker and
commits one pending attempt/owner for the shared account/region. Network calls
must follow outside all SQL transactions. Every participating worker must use
this protocol; existing fixture-only worker paths are not yet wired. Do not
interpret a successful reserve, a health read or elapsed cooldown as a send permit.

`health().submission_pending` blocks reserves, new begins, generic finishes and
breaker recovery across processes. It never expires. Quota refresh, reconnect,
lease expiry, process restart and recipient rollback cannot clear it. Stop on any
begin/settlement error; never retry transport merely because COMMIT returned an
error. A failed begin may have committed its latch. A failed settlement may have
committed its outcome: replay only exact evidence, never the external request.

Settlement must precede recipient-result persistence. `settleSubmission` stores a
trusted classified outcome and opaque evidence digest immutably in the reservation,
a durable signal/audit where required, completion charge and latch clear in one
transaction. Failed signal/audit/result settlement leaves durable exclusion.
Independent recipient writes cannot roll back an already committed account signal.
Throttle settlement extends the cooldown; rejection and unknown settlement require
operator recovery. There are no refunds, even for preflight denial after reservation
or proven non-acceptance. A reserved-but-not-begun crash remains pending usage.

If outcome evidence is unavailable after a crash/outage, retain the latch until
storage is healthy, then durably settle the original attempt/owner with
`submission_unknown` and a recovery-evidence digest. This asserts uncertainty,
not non-acceptance. It creates an operator hold before clearing the latch. Existing
version/actor/quota/readiness recovery is a separate audited step and cannot clear
an unsettled latch. It never modifies recipient/acceptance evidence or grants retry
authority. Worker integration must fence that recipient as unknown independently;
this prerequisite does not implement that orchestration. Do not fabricate accepted
or rejection evidence to clear a latch. Conflicting stored outcome/digest rejects;
exact replay leaves any newer pending submission untouched. There is no CLI/UI
activation or recovery shortcut, and production factories remain null.

Durable rows, rather than an in-memory stop flag, provide exclusion for other
adopting workers. Databases restored without these rows and independent/external
senders are outside this protection. Restore/ownership/clock and externally
verified account allocation remain activation blockers. No production readiness
or transport/suppression-sync integration is established by this prerequisite.


### Integrated prepared-worker boundary (v40; production disabled)

The internal prepared worker now requires an injected preflight/budget service.
All production factories remain null, dispatch remains unconditionally false and
existing CLI activation/recovery shortcuts remain unavailable. `budget-health`
reports pending submissions; `budget-recover` still refuses a pending latch and
requires the existing actor, version, quota and readiness checks. Neither CLI
command changes recipient results or grants send authority.

Final authorization requires the configured account/region/deployment/blog sync
checkpoint to be complete, younger than 24 hours, internally consistent and free
of running/failed progress, cursor or error. Health is diagnostic, never a permit;
a previous successful sync cannot authorize a running or failed successor. Worker
preflight performs SQL reads only, never sync activation or an AWS request.

Reservation and latch commits precede final worker authorization. The final
transaction rechecks budget/readiness/breaker/pending owner, fresh sync and all
worker/consent/identity/pause/cancel fences, then commits inflight. Transport runs
outside every database transaction. A failure after reservation may strand usage;
never delete/refund it or retry transport following an exception.

Immutable `recordSubmission` evidence and account signals commit before recipient
acceptance/result persistence. `outcome_staged=1` with zero completion means known
evidence remains pending, not a finished reservation. Failed recipient results or
final settlement leave the latch blocking every adopting worker. Do not edit that
flag or overwrite outcome/digest; partial legacy evidence without the staging
marker remains inconsistent and fails closed.

After storage recovery, an authorized internal `recoverPrepared` operation requires
current quota/readiness, fresh exact-scope sync, the original pending attempt/owner
and a durable recipient fence. Expired claimed/inflight work becomes unknown;
existing accepted/unknown/failed/excluded/retry results are preserved. Stored
classification/digest is retained; absent evidence settles as uncertainty. An
operator hold commits before latch release. Separate breaker-only recovery is
still mandatory. Recovery never regenerates output, erases outcomes, resets a
recipient to held or authorizes retry of accepted/unknown work. If a newer owner
or attempt has replaced the supplied binding, stop and investigate; do not force
an old owner through the fence. Reserved-but-not-begun capacity has no refund or
automatic aging recovery.

These guarantees cover adopting workers sharing one target-blog account ledger.
Independent databases/external senders and backups omitting recent rows remain
outside local coordination. Keep dispatch disabled during restore and establish
external readiness separately. No real credentials/messages/AWS requests, cron
changes, merge or deployment are part of this integration.


Final authorization repeats quota/sync clock checks after lock contention; locking
a fresh row alone cannot prevent expiry. A pause after latch commit keeps its
charged attempt number, so resume uses a new attempt; a final charged attempt
becomes terminal `attempt_limit`. Accounting recovery rejects missing/mismatched
latches or incoherent evidence before changing recipient state. Never use it to
consume a never-begun claim: ordinary lease recovery remains the appropriate path.

### Consolidated readiness diagnostics (service/CLI; production disabled)

Run with a real target-blog user holding `peracrm_view_campaign_reports`:

```sh
wp --path=/path/to/wordpress --url=https://target.example --user=REPORT_USER peracrm campaigns readiness --format=json
```

The command accepts only `--format=json|text`, denies anonymous/impersonated
actors and switches to the CRM target blog with caller context restoration.
Exit 4 means blocked (always, while unconditional dispatch blocking remains);
exit 3 means rejected arguments/authority/configuration. Shell access is not
authority. The command cannot send, activate, sync, redrive, reserve or record
evidence. No production dependency or secret is discovered by diagnostics.

Inspect fixed `checks` and `blockers`: schema unavailable, scope unconfigured,
configuration unavailable/approval missing, sync scope/storage/stale cursor,
budget quota/clock/pending/breaker/exhausted/storage/readiness, event processing
counts and unverified/stale/insufficient external evidence. Configuration details
require existing administrative campaign authority; report-only users may see
configuration unavailable. Missing production identity/signing/encryption factories
remain unavailable, even if some unrelated credential is configured. Supported
envelope versions/renderer describe repository contracts, not live deliverability.

No configuration presence proves DNS/DKIM/DMARC alignment, IAM permissions/quota
headroom, SNS/DLQ monitoring, host-log protections, retention, old-mailbox withdrawal
policy or restore correctness. Those checks start explicitly unverified. A future
administrator form will call the internal evidence API with a nonce bound to the
displayed current configuration fingerprint. The API requires `manage_options`,
campaign management and report capabilities and denies impersonation. Evidence
and its audit are one append-only, non-autoloaded target-blog option; concurrent
updates use compare-and-swap and conflicts require a fresh read. At 64 entries per
binding recording fails closed; there is no automatic audit pruning/reset.

Record only a SHA-256 of an independently retained redacted artifact, the
prerequisite and provenance. Never pass credentials, bearer URLs, mailboxes or raw
message bodies. Actor and verification date come from the authenticated session
and trusted server clock. Repository/mock validation remains distinguishable from
operator attestation and actual provider/host observation. The API records an
operator's claim about evidence, not independent remote verification. DNS/IAM/
monitoring require provider observations; host logging/restore require host
observations; retention/old-mailbox policy require operator attestations.

Evidence binds deployment/blog/provider/account/region/configuration-set/version,
the CRM configuration revision/document and event pins. Any changed binding
invalidates its applicability, while preserving history; dates at least 30 days old
are stale and future dates are unverified. **Advance configuration_version after
any relevant deployment, host, secret/key, DNS/IAM, monitoring, retention, mailbox
policy or restore change**; such external changes cannot be discovered from local
configuration. Do not reuse a previous version after rollback or restore. Recheck
the affected evidence; this conservative binding invalidates all prerequisites.

Local sync/capacity/event observations are snapshots under separate SELECTs and
can change immediately. They are never a worker permit. The worker still repeats
its own transaction/lease/consent/identity/scope/quota/sync/breaker/latch fences.
Output exposes only fixed codes/counts, scope/configuration fingerprints and actor/
UTC dates for recorded evidence. Protect host/PHP/SQL/APM logs independently.
Passing tests, recorded attestations and local observations never set
`production_ready` or enable dispatch. The administrator UI, artifact-retention
workflow and controlled internal test remain dependent work. No desktop/mobile
view has been added or verified in this service/CLI PR.


### Administrator campaign readiness and evidence recording

Open **WordPress administration → Campaign readiness** on the CRM target site.
Only a real administrator with campaign management and report permissions can
view the page or record evidence. Impersonated sessions, managers and employees
are denied. The plugin loads its own crm.css/crm.js; no theme assets are required.
The page uses the same readiness service as `peracrm campaigns readiness`; checks
and blockers must agree for the same actor, configuration and observation time.
Separate reads are snapshots and may change immediately.

Review the site/deployment/provider/account/region/configuration-set/version pins
and configuration fingerprint first. Internal results are automated local checks;
they are not observations of AWS, DNS or the host. External results remain
unverified without applicable evidence. “Required evidence recorded” means an
operator supplied the required type, not independent live authentication. The
source column distinguishes repository mocks, policy attestations and
operator-reported provider/host observations. Mocks and attestations cannot meet
checks requiring observations. No recorded result changes dispatch authority.

To record evidence, independently inspect and redact the artifact, retain it in
your controlled evidence store, and compute its lowercase SHA-256. Verify it again
on the recording date. Select the prerequisite and accurate evidence type, enter
only that 64-character digest, and confirm today's exact-scope verification.
The server supplies your real actor ID and current UTC date. There is no free-text
notes/upload/URL field: keep explanatory notes with the separately retained,
redacted artifact. Never submit credentials, bearer URLs, mailboxes or message
bodies. The page neither fetches nor executes evidence. DNS/IAM/monitoring require
provider observations; logging/restore require host observations; retention and
old-mailbox policy require operator attestations.

A successful response says “Evidence saved to the audit history” and the service
results refresh. Failed validation, nonce, stale configuration, scope, permission,
storage conflict or audit-capacity checks show an error with no saved evidence.
Rejected input is cleared, so secret-like content cannot be echoed. Reload, inspect
the new pins/fingerprint, reverify the artifact and submit again. Never blindly
rebind an old artifact to a changed configuration. The form works without
JavaScript; keyboard users receive visible focus and focused success/error status
when JavaScript is available. Reloading a POST can append another audit record;
use the menu URL for a fresh read rather than confirming browser resubmission.

Audit history preserves every accepted record with prerequisite, provenance,
artifact digest, real actor, UTC date and configuration binding. Up to ten binding
histories appear per page; “Older configuration histories” uses bounded keyset
pagination. Each binding permits 64 records. At capacity or on conflict, recording
fails closed; do not delete/reset options to work around the bound. There is no
pruning or overwrite control. Historical bindings are explicitly not applicable;
current-binding records can still be stale or insufficient, as the checks show.

Changing CRM configuration revision or any deployment/provider pins invalidates
all previous evidence while retaining history. Advance `configuration_version`
for external host/key/DNS/IAM/monitoring/retention/mailbox/restore changes that local
code cannot detect, and never reuse an earlier version after rollback or restore.
Evidence expires after 30 days; future dates are unverified. Missing evidence
never becomes verified by configuration presence or a successful form save.

Production identities and keys, genuine external verification, artifact retention/
export operations and controlled internal testing remain dependent work. Dispatch
is always disabled, endpoint/sync/intake/public collection stay disabled, and no
send or activation controls exist. Runtime checks remain mandatory. This page
does not authorize AWS requests, credentials, DNS changes, messages, cron,
merge or deployment.

## Controlled internal-test orchestration — synthetic only

The internal `PeraCRM_Campaign_Internal_Test` service is unbootstrapped and has no
UI, CLI, factory, route, scheduler or delivery switch. Do not load it to operate on
production ledgers. Disposable fixtures inject the existing authority-bound worker,
preparation, readiness and preflight services plus a final mock transport that
cannot perform HTTP/SDK calls. No real credentials, provider requests or messages
are part of this procedure. Production dispatch remains unconditionally disabled.

A real non-impersonated administrator also needs campaign management, subscriber
management and report capabilities on the target blog. A read-only proposal binds
the exact job/recipient manifest and current configuration. Authorization requires
a nonce for that binding and literal `synthetic-only` confirmation, with the real
actor and trusted time recorded server-side. It expires at five minutes, rejects
clock rollback and does not survive configuration/manifest changes. Same actor
executes it. Unknown, alternate-case or plus-tag addresses are rejected: the only
fixed mailboxes are `internal-test-1@example.test` and `internal-test-2@example.test`.
These reserved test addresses are not real internal-mailbox approvals.

Hard bounds: one exact prepared job, at most two distinct recipients, three
lifetime authorizations per deployment/target blog, one outstanding run at a time.
Configuration changes and recovery do not replenish the journal. Never delete or
edit journal options to regain capacity. Execution consumes authority before any
claim and journals each original claim before preparation. A second execution,
including after a known rejection, is denied; there is no resume/send retry API.
A connection-owned lock rejects concurrent execution/recovery without waiting.

Synthetic readiness requires schema/renderer, approved CRM configuration, exact
scope, fresh complete suppression sync and available budget/breaker/latch capacity.
The worker still enforces immutable output, consent, suppression, identity, due-time,
pause/cancel, lease/version, quota/clock and acceptance fences. Production identity
and keys, DNS/IAM, host logs, retention/restore policy, real event intake/monitoring
and inbox acceptance remain unavailable/unverified production requirements; they
are not asserted by a synthetic test. No synthetic result changes readiness
records or clears external blockers. Mock acceptance is not provider acceptance
or inbox verification. The result always reports inbox_verified=false and
production_ready=false.

On an exception or process death, stop execution. Inspect the administrator-only
sanitized audit: authorized/running/interrupted does not imply a completed run.
Recovery requires its own run-bound nonce and current authority/scope/sync/quota.
It never reuses execution authority or calls transport. Wait for live leases to
expire; never force-clear a native lock or owner. Original pending claims use the
existing prepared recovery protocol: preserve staged evidence, fence expired work
unknown and commit an operator hold before releasing the account latch. Unknown
remains unknown without independently verified outcome evidence. Never-begun
claims use ordinary lease recovery; stranded reservations remain charged. Missing
or conflicting bindings, stale readiness, live leases and unidentified pending
latches fail closed. After recovery a separate breaker-only procedure still
cannot authorize resend or renew the consumed run.

The journal stores original claim bindings privately for recovery. Its audit
projection exposes only job/result IDs, states, actor IDs, fixed events and times;
protect the database and logs independently. Anonymous/unauthorized requests are
rejected before journal access, without retaining attacker-supplied data. No
retention deletion/reset/export or production restore protocol is introduced.

Required acceptance uses injected mocks in both disposable SQL topologies,
including unauthorized recipients, stale readiness/configuration, duplicates,
ambiguous outcomes, storage faults, live withdrawal, overlap and actual process
death/recovery. Existing browser tests retain readiness screenshots. #1494 manual
screenshot inspection remains incomplete: artifact retrieval and local disposable
Docker were attempted here but blocked by proxy/socket access. Do not substitute
passing automated layout assertions for manual visual inspection.

Real internal delivery remains separate work: reviewed real allowlist and authority,
production adapters, genuine provider/host/monitoring/restore readiness and explicit
acceptance authorization. Only an actual separately authorized test can establish
inbox/header/HTML/text/link/Reply-To/opt-out observations. No intake, public
collection, endpoint/sync activation or cron gate changes are authorized here.

## Deployment adapters — local validation, dispatch still blocked

Without new deployment constants, the existing unconfigured page/CLI behavior
is unchanged. This PR installs no values on a host and requests no credentials.
The following contract is for a separately approved deployment configuration;
never enter secret material in CRM settings, command arguments, source control,
chat, evidence forms or logs. Provisioning/rotation/refresh belongs to the host's
approved secret mechanism outside campaign requests. The adapter itself reads
only explicit PHP constants and performs no discovery, file reads or network.

`PERACRM_CAMPAIGN_DEPLOYMENT` is an exact nonsecret array:

| Field | Contract |
| --- | --- |
| provider, account_id, region, configuration_set, configuration_version | Existing SES v2 scope contract; fixed account/region, positive integer version |
| deployment, blog_id | Existing deployment ID and designated target-blog integer |
| origin | Canonical HTTPS DNS origin accepted by recipient expansion, no port/path/userinfo/query/fragment or reserved test host |
| instance_id | Independently supplied host-instance identifier, 16–64 ASCII letters/digits/underscore/hyphen |
| identity_version | Positive integer principal/identity revision |
| signing_key_id, encryption_key_id | Permanent active IDs using existing kN and eN syntax |

`PERACRM_CAMPAIGN_INSTANCE_ID` must independently match instance_id on the running
host. Supply it outside cloned databases and copied application configuration.
The adapter also compares the configured WordPress home origin (target subsite
paths are allowed) and current/target blog. HTTP, ports, userinfo and alternate
origins fail. Request Host/forwarded headers are never authoritative. These local
comparisons do not independently authenticate the host or establish real TLS;
copying all host bindings together defeats a local clone distinction and remains
outside this guarantee. Independently verify host ownership before activation.

If `PERACRM_SES_EVENT_OPERATIONS` is present, its account/blog/deployment/region/
configuration-set/version pins must agree. The new constants neither configure
nor activate that event service or the SNS HTTP/suppression-sync endpoints.
Malformed explicit deployment configuration fails unavailable without legacy-pin
fallback. Missing secrets can coexist with valid diagnostic scope pins, but
provider/key checks remain unavailable.

`PERACRM_CAMPAIGN_MATERIAL` has only these optional components:

| Component | Exact fields |
| --- | --- |
| identity | account_id, version, access_key, secret_key, session_token (string or null), not_before, expires_at |
| signing | active, keys (kN => raw signing bytes), not_before, expires_at |
| encryption | active, keys (eN => raw 32-byte encryption bytes), not_before, expires_at |

All dates are trusted integer UTC epoch seconds. not_before must be positive and
not future; expires_at must follow activation and retain more than a full
20-second request budget for new preparation. Identity account/revision and active
key IDs must match the manifest. Existing SES credential validation additionally
checks actual server-clock expiry. Signing keys retain the existing 32–128-byte,
maximum-eight contract; encryption retains 32-byte, maximum-32 and sodium checks.
No WP salts, defaults or fallback cipher are supplied. Duplicate bytes under
multiple IDs, shared signing/encryption bytes or reuse of the provider secret as
a key fail closed. Bundles are copied defensively with size/depth/count bounds;
objects, executable locators and arbitrary secret-file paths are unsupported.

A `configured` readiness result means syntactic/local availability only. It does
not prove the principal's real AWS account, IAM privileges, quota, DNS alignment,
provider response, host protection or inbox delivery. Those external evidence
checks remain separate and unverified until genuine evidence exists. The adapter
reuses current complete/fresh suppression and budget/quota/breaker/latch validators
for read-only diagnostics; it does not run a scan, set quotas, recover a breaker,
clear a pending latch or acquire send permission. Production sync/budget mutation
factories and scheduler/worker/provider dispatch remain disabled.

Advance configuration_version after relevant host, secret/key, DNS/IAM,
monitoring, policy or restore changes; advance identity_version for principal
changes and use a new permanent key ID for every changed key. Never replace bytes
under an existing ID or reuse a previous version after rollback. The local evidence
fingerprint includes the public deployment manifest, not credential/key values.
Credential refresh under the same verified principal can preserve identity_version;
the host must supply correct current expiry. Local code cannot detect an operator
silently replacing secret bytes under old IDs/versions. It does not claim a key
registry, rotation service or independent AWS account attestation.

New preparation checks availability before/after durable output preparation and
before/after final identity-lock waits. Expiry or a failed guard after COMMIT leaves
prepared bytes durable but unusable as a send/retry permit. Follow existing lease/
accounting recovery; never erase output, reset accepted/unknown work or retry an
external request because a guard/COMMIT returned an error.

Retain retired signing and encryption keys for the independently approved token/
output retention horizon. Historical token verification and exact encrypted-output
recovery can use retained bytes after the active ring's preparation window expires;
normal purpose, registry, identity/epoch, bearer expiry, scope and envelope checks
remain mandatory. These methods do not issue a token, modify worker outcomes,
renew a lease or call transport. Missing/corrupt keys or ciphertext fail closed
without regeneration. Public token-service factories remain disabled. Key loss,
compromise, host clock discipline and restore reconciliation still require their
own approved procedures; no automatic key deletion or retention policy is added.

Administrator page/CLI output exposes fixed states/provenance and opaque public
manifest/configuration fingerprints, never secret bytes, credential identifiers,
session tokens, recipient bodies or keyring projections. Debug and serialization
are redacted/denied. Host/PHP/APM argument capture and database/access logs require
independent protection; local sanitization does not prove host logging safety.
All code remains PeraCRM-owned. No theme sync, CSS/JS changes, messages, AWS
resources, live configuration changes, activation, merge or deployment are included.

Historical output recovery accepts prior configuration versions (including their
original configuration sets) after a version advance, with the current independent
host binding and the same deployment, target blog, provider/account and region.
It rejects future versions and a changed set under an unchanged version. The
immutable persistence/envelope authentication still requires the exact original
metadata and ciphertext; an operator cannot invent historical pins or overwrite
output. This grants historical access only and never makes an old configuration
eligible for a new submission. Moving provider accounts/regions requires a separate
reviewed historical-access/restore decision rather than relaxing scope isolation.

## SNS subscription provisioning and confirmation (control plane only)

This is a deployment/operator procedure for a separately approved future window.
Do not execute it as part of this change. No AWS resource, live credential,
subscription, endpoint activation, message, merge or deployment is authorized by
this PR. Dispatch, event intake, public collection and cron remain disabled.
Provisioning does not satisfy provider/host readiness or grant delivery authority.

The existing `/peracrm-ses-sns/` endpoint normally rejects control messages.
A separate deployment constant, `PERACRM_SES_SNS_PROVISIONING`, temporarily makes
that same URL a **confirmation-only** receiver. This permits provisioning before
SES event intake is activated. Notifications and UnsubscribeConfirmation remain
rejected; no SubscribeURL is ever fetched. The receiver authenticates SNS v2 and
exact topic/account/region, then requires an administrator-armed pending request.
It cannot call Subscribe or ConfirmSubscription, and never loads AWS credentials.
Only the operator CLI can perform these explicit, authenticated API mutations.

Prerequisites for the separately approved window:

1. Independently approve the deployment ID, CRM target blog, AWS account/region,
   exact topic ARN and canonical public HTTPS URL. Keep the topic dedicated to
   this deployment. Verify TLS and outbound certificate/SNS regional API access.
   Configure topic SignatureVersion **2** before requesting a subscription. The
   system deliberately rejects v1, including AWS defaults; do not weaken it.
2. Protect request bodies and POST data at host/proxy/CDN/WAF/PHP/APM/HTTP-debug/
   trace layers. Exclude this endpoint's bodies and all SNS API bodies/headers
   from logs. Do not enable WP HTTP debug logging, CLI `--debug`, shell tracing or
   provider wire logs. Never capture/log SubscribeURL, Token, canonical signed
   bytes, session credentials or Authorization. Block access before configuring
   the receiver until these protections and body/time/rate limits are verified.
3. Restrict database, backups and WordPress authentication salts. Pending tokens
   use sodium secretbox with random nonces and a domain/scope-derived key from
   `wp_salt('auth')`. Require sodium and SimpleXML. No raw envelope or URL is
   stored; only ciphertext, signed-payload digest/message ID, scope binding and
   operator/state timestamps persist in a non-autoloaded target-blog option.
   Salt/configuration rotation deliberately prevents decryption. Never restore
   a provisioning journal to a different deployment or silently reset it.
4. Use a real, non-impersonated WordPress administrator with `manage_options`
   and `peracrm_manage_campaigns` on the CRM target blog. OS/CLI access alone and
   report-only roles do not grant authority. IAM session permissions must be
   constrained to the exact topic/account/region and HTTPS endpoint:
   `sns:Subscribe`, `sns:ConfirmSubscription`, `sns:GetSubscriptionAttributes`.
   Use resource/region/endpoint/protocol conditions where supported; independently
   verify the actual session identity/account. The credentials wrapper's account
   label is a deployment pin, not STS identity verification. No wildcard admin
   credentials, discovery chain or automatic refresh is used.
5. Plan HTTPS retry policy, regional subscription DLQ/redrive/monitoring and
   retention separately. RawMessageDelivery must remain false for event intake.
   Prefer a maintenance window with no topic publishers: confirmation-only mode
   rejects notifications and does not spool SES events. Do not publish test mail.

In deployment-owned configuration, temporarily define only this explicit array
(with reviewed real values substituted); **leave PERACRM_SES_SNS_HTTP undefined**:

```php
define('PERACRM_SES_SNS_PROVISIONING', [
    'enabled' => true,
    'blog' => 2, // exact CRM target blog, positive integer
    'deployment' => 'reviewed-immutable-deployment',
    'account' => '123456789012', // string, exactly 12 digits
    'region' => 'eu-west-1',
    'topic' => 'arn:aws:sns:eu-west-1:123456789012:reviewed-topic',
    'endpoint' => 'https://crm.example.test/peracrm-ses-sns/',
]);
```

The endpoint must exactly equal that target blog's `home_url('/peracrm-ses-sns/')`,
including multisite subdirectory and trailing slash. Switch to that blog with
`--url`; no automatic target switching or cross-blog authority is supplied.
Do not use the example values in production. There is no option/UI/public enabling
switch, rewrite flush or cron integration.

After deployment approval and log protection, run the following explicit steps
using the approved `--url` and real administrator `--user`. The literal confirmation
phrase is required for every action, including status; it contains no secret.

```sh
wp --url=https://crm.example.test --user=approved-admin peracrm campaigns sns-subscription arm --confirm=provision-exact-sns-subscription
wp --url=https://crm.example.test --user=approved-admin peracrm campaigns sns-subscription status --confirm=provision-exact-sns-subscription
```

Arming durably records the real actor and a one-hour window before any AWS call.
Rearming an active request is idempotent. To perform Subscribe/ConfirmSubscription,
a trusted secret injector must provide **process environment**, never CLI values
or shell history: `PERACRM_SNS_ACCESS_KEY_ID`, `PERACRM_SNS_SECRET_ACCESS_KEY`,
`PERACRM_SNS_SESSION_TOKEN`, `PERACRM_SNS_CREDENTIAL_EXPIRY` (integer Unix seconds).
Use a short-lived session with more than 20 seconds remaining. Do not print or
source an unreviewed credential dump. Then invoke:

```sh
wp --url=https://crm.example.test --user=approved-admin peracrm campaigns sns-subscription subscribe --confirm=provision-exact-sns-subscription
wp --url=https://crm.example.test --user=approved-admin peracrm campaigns sns-subscription status --confirm=provision-exact-sns-subscription
```

Subscribe uses a fixed regional HTTPS SNS API, SigV4, POST, bounded TLS verification,
no redirects and no automatic retries. The subscription endpoint is taken only
from deployment configuration. A durable subscribe-attempt actor/time precedes
this call. SNS may deliver concurrently; the pending window is already durable.
Wait for `received`; status contains no token, raw body or signed digest. Before
confirming, independently review the expected subscription/topic/endpoint in AWS
and its exact approved scope. Signed SNS envelopes do not themselves attest the
destination URL: do not import another endpoint's envelope or forward controls.
This receiver accepts envelopes only at the configured endpoint, and final AWS
attributes must independently match that endpoint/owner/topic/protocol.

```sh
wp --url=https://crm.example.test --user=approved-admin peracrm campaigns sns-subscription confirm --confirm=provision-exact-sns-subscription
wp --url=https://crm.example.test --user=approved-admin peracrm campaigns sns-subscription status --confirm=provision-exact-sns-subscription
```

Confirmation is a second explicit administrator authorization. A durable
`confirming` state precedes the API mutation. The service decrypts internally,
uses `ConfirmSubscription` with `AuthenticateOnUnsubscribe=true`, checks the
returned ARN's exact topic scope, and verifies GetSubscriptionAttributes' owner,
endpoint, HTTPS protocol and ConfirmationWasAuthenticated. Only then does it
persist `confirmed` and erase ciphertext. IAM authentication is required for
unsubscribe; never use console/browser token links as a shortcut. The API token
is sent only in the signed POST body to the fixed SNS endpoint. No command,
response, audit/status, exception or evidence includes it.

Failure/replay procedure:

- Invalid signature: 403; malformed/v1/wrong topic/account/region/control type:
  400. No storage mutation or confirmation. Wrong pending scope/state/time and
  changed signed content for an existing MessageId: 409. Investigate the topic,
  configuration and publisher; do not relax signature or scope rules.
- Valid first receipt: 202 only after ciphertext/digest persistence. Exact signed
  duplicates return 202 without writes or new authority; different signatures
  over identical signed bytes are duplicates. A different identity cannot
  overwrite a received token. Confirmed duplicate CLI actions make no API call.
- Certificate outage, database/lock/reconnect/write failure: 503 with Retry-After
  at ingress; no successful acknowledgment. Existing SNS retry/DLQ procedures
  apply. CLI errors are generic and nonzero. Recover infrastructure, inspect
  sanitized state and repeat only the same explicit action after investigation.
  Never edit options, clear locks, overwrite ciphertext or delete replay records.
- Subscribe network ambiguity may already have created a pending subscription.
  Inspect AWS first; only while still `armed` may the operator explicitly repeat
  Subscribe for the exact endpoint/topic. No automatic retry occurs.
- Confirm/network/attribute/final-storage failure retains `confirming` and the
  encrypted token. AWS may already have confirmed. Inspect the actual approved
  subscription independently before repeating Confirm. Attribute scope mismatch
  is an investigation/stop condition, not permission to confirm again blindly.
  A repeat revalidates remote attributes and cannot mark an unverified ARN ready.
- Expiry rejects confirmation; a new explicit arm after one hour preserves all
  prior replay tombstones and erases old ciphertext. Obtain a new SNS confirmation
  in that new window. An old duplicate cannot populate the new request. Repeated
  Subscribe can return an existing confirmed ARN: stop and reconcile it; it is
  never silently imported. At most 32 requests per deployment/blog journal are
  allowed. Exhaustion or salt/scope mismatch requires reviewed recovery; there is
  no reset/export/token-reveal bypass. Check backup/retention policy separately.

After verified confirmation, remove the provisioning constant and secret process
variables. The endpoint returns 404 while normal intake remains disabled. Record
only redacted state, actor/time, approved scope/subscription ARN and independently
verified retry/DLQ/host evidence. Never put confirmation bodies/tokens/URLs in PRs,
issues, screenshots or logs. A later, separately approved activation can define
PERACRM_SES_SNS_HTTP using the exact existing pins and the verified subscription;
that remains outside this task. Do not enable dispatch/intake/public collection/
cron, send real messages, provision resources, merge or deploy as part of this PR.


### Combined deployment adapters and SNS provisioning

The deployment adapters above do not supply SNS provisioning credentials or
enable the confirmation receiver. Provisioning continues to use its separately
authorized short-lived process credentials and salt-bound journal encryption;
token-signing/output-encryption material is not a substitute. Before a separately
approved provisioning window, independently verify that deployment ID, target
blog, account and region agree across the reviewed configuration, and that the
exact SNS topic is the approved event topic. No automatic cross-configuration
activation or shared credential discovery is provided. Preserve all disabled
production gates during deployment and follow the existing host-log protections
and explicit arm/subscribe/confirm/teardown procedure.
