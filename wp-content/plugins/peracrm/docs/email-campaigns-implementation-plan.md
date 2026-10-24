# Integrated email campaigns: living implementation plan

This is the authoritative current status and bounded-work addendum. The complete
phase design, prior decisions, acceptance requirements and dated evidence are
preserved verbatim in [the plan history through initial PR #1474](email-campaigns-implementation-plan-through-2026-10-22.md).
That history remains part of this plan; the current addendum supersedes its
current-task and validation checkpoint. This split avoids repeatedly publishing
the full historical document when recording current evidence.

Phase 1: **complete** (technical acceptance); Phase 2: **in progress**;
Phase 3: **in progress**; Phase 4: **in progress**; Phase 5: **in progress**;
Phases 6–7: **not started**. Earlier external readiness requirements remain open
as detailed in the linked history. No phase is complete merely because code exists.
Future PRs must update this active plan and distinguish repository checks from
external verification. Preserve historical evidence and decisions in the history.

**Current task:** eighth bounded Phase 5B PR — new-protocol language/topic preference viewing/editing and expired-link navigation using independently authenticated withdrawal authority. #1473 is merged at `c9f1d5ae6d931efc5f3443ce797f24475096480f`; final head `8e841a2b1c9e5785711e791181ccb2f34ccc8011` passed [CI 39796114469](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39796114469) and completed clean [review](https://github.com/7mus7y-dotcom/pera-wp/pull/1473#issuecomment-6343761645). Its earlier outdated rewrite thread does not negate that final review. See the new dated section below for current scope and validation.

## Phase 5B — bounded recipient preference editor (2026-10-22)

Phase 5 remains **in progress**. Implements pc1 preference viewing and editing
on the existing plugin-owned `/email-preferences/` route. The legacy Phase 2
route is preserved. No new persisted rewrite rule or schema upgrade is needed.

### Baseline and checkpoint

Connected GitHub verified latest main `c9f1d5ae`, including merged #1473,
passing final-head CI and completed clean final review covering its runtime.
No applicable AGENTS.md found in workspace/repository or searched ancestors.
Read this living plan and existing token, revocation, recipient persistence,
withdrawal, preference and subscriber lifecycle services.
Original clean `/workspace/pera-wp` at `76403917` is preserved. Work is isolated
in `/workspace/pera-preferences`. Shell fetch failed once: `Failed to connect to
proxy port 8080 ... Could not connect to server`. The additional-network attempt
was aborted while pending and returned no execution result. Neither attempt was
repeated. Connector supplied all 14 changed main files; each reconstructed file
was checked against its Git blob SHA. The reconstruction commit is local-only
scaffolding; publication parents directly to verified main. Initial checkpoint
was reported before lengthy checks.

### Authority and supported edits

GET/HEAD authenticate the purpose-specific MAC and durable digest binding,
active identity/epoch, deployment/blog and revocation. Read transactions do not
acquire the inserting mailbox mutex and expose only language/topics, never
mailbox/client identity or consent. HEAD emits no body or cookies. An authenticated
GET sets a random anonymous cookie (15 minutes, SameSite=Strict, HttpOnly, host-only;
HTTPS uses Secure and the browser-enforced __Host- prefix). No database state is
written by GET. Human POST requires URL-encoded content and a signed, short-lived
form proof bound to both bearer digest and that cookie, with a separate MAC domain.
Exact same-origin Origin or null Origin (no-referrer navigation) is required;
explicit foreign/same-site/cross-site Fetch Metadata, foreign/missing Origin,
missing/wrong cookie and missing/expired/tampered or other-token proof fail closed.
No WordPress login or guest nonce is required. Proof/cookie renewal never extends
preference expiry; the recipient token is separately rechecked under the lock.

Edits accept only an explicit language tag (existing field syntax, lowercased,
blank removes it) and the existing `news` / `properties` topic choices (empty
list removes the rendered choices). Unrendered staff/import topic keys are
preserved from the authoritative stored list under the lock. Unsupported submitted
fields/topics fail closed. These are profile
choices, never consent. Under mailbox -> subscriber -> identity -> token locks,
the service rechecks subscriber/mailbox, active epoch, digest revocation,
registry bytes/hash, protocol metadata and token authentication/expiry before
updating only language/topics, profile version/timestamp and an actor-0 audit.
Identical submissions create no extra events/version increments. SQL/audit/
COMMIT failures roll back. Withdrawn profiles can be edited without changing
withdrawal, subscription state, suppression flags or consent evidence. Deleted
or replaced identities deny old access; no replacement mailbox is addressed.

A `withdrawal_token` issued independently alongside a preference
link is authenticated strictly as unsubscribe authority by #1473's service.
When valid it provides a direct link to the existing visible confirmation route,
including when preference access expires. That GET remains read-only and the
existing withdrawal POST handles persistence. An expired preference token alone
cannot produce, reconstruct, mint or substitute for withdrawal authority. Without
an independent valid bearer the page directs recipients to the separate original
email unsubscribe link. The internal expansion service places the already independently issued
unsubscribe bearer in new preference URLs and preserves the separate unsubscribe
link. No extra token is issued and no public request can invoke expansion.
Existing persisted output/recovery bytes remain immutable; old links without the
parameter retain their separate original-email unsubscribe link. Production
issuance and delivery remain disabled, with no provider/worker integration.

Rendering uses CRM-owned CSS and bypasses theme hooks, scripts, analytics,
fonts and third-party assets. Responses retain no-store/no-referrer, noindex,
restrictive CSP, clean form actions and generic errors. Bearers/proofs are never
stored in audit or exception text.

### Validation and remaining work

Local PHP 8.4: all 27 standalone suites and all 459 PHP syntax checks passed.
Shell/JavaScript syntax and whitespace checks passed. Added focused profile,
CSRF/proof and disabled-factory regressions. Disposable single/multisite SQL
fixtures cover valid edits, read invariance, expiry, wrong purpose, revocation,
epoch and replacement denial, wrong scope/key, rollback and duplicate submissions,
permission/evidence preservation and withdrawal after edits. Nine independent-
process races cover edits against edits, revocation, epoch changes, deletion and
withdrawal in both orders. Browser/HTTP fixtures cover desktop/mobile editors,
SQL invariance, cross-site/missing-proof rejection, duplicates, expired-link
navigation and independent withdrawal; existing legacy and #1473 flows still run.

Local Docker health failed once with `connect: operation not permitted` at
`unix:///var/run/docker.sock`. Daemon is running; UID 1000 cannot recover it.
No Docker retry or local SQL/browser success is claimed. Hosted execution and
screenshot inspection are pending at publication; record actual results below.

Production preference and withdrawal factories remain unconditionally null.
Production issuance, sending, real intake, public collection, cron and worker/
provider integration remain disabled. Old-mailbox/deletion withdrawal policy is
explicitly **blocked**, not resolved by independent active-identity withdrawal.
Host/proxy/CDN/WAF/APM log protection, verification keys, retention, privacy
lifecycle and restore readiness remain activation prerequisites. No merge or
deployment is authorized or performed.


### Initial publication and review correction

Non-draft [PR #1474](https://github.com/7mus7y-dotcom/pera-wp/pull/1474)
opened on main with runtime `90496bb7`, tree `910e2c79613c056c146a5fc4636e688f4b4f229b`
matching local. Initial hosted run 39844434992 started both topologies.
Automatic review identified that saving rendered choices would discard custom
staff/import topics. Corrected by merging only `news`/`properties` changes into
the authoritative list under the mailbox lock, retaining every other key and
failing closed on invalid/over-capacity lists. Added pure preservation/overflow
regressions plus actual SQL language-only and clear-all preservation assertions.
New hosted execution and final rereview remain pending for this correction.

Initial run 39844434992 completed with both topology jobs failing at the first
browser edit submission after SQL/race and existing unsubscribe checks passed.
The no-referrer form navigation is handled by allowing null Origin only with
same-origin Fetch Metadata and the existing signed proof. Foreign/missing Origin
and null without same-origin metadata remain rejected. Added pure metadata
regressions and sanitized browser origin/metadata failure diagnostics. Both
corrections require a new hosted run; earlier SQL passes are not final acceptance.


### Trustworthy-origin browser fixture correction (2026-10-23)

Correction runtime `9e5d377c`, tree `4701fb4405d3f6de071a820a6faaa623eb56b09b`,
is pushed to #1474 and matches local. Local 27 suites/459 PHP lint passed again.
[CI 39911741853](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39911741853)
passed SQL, custom-topic preservation and all nine preference races in both
single/multisite jobs, but browser edits failed with the sanitized diagnostic
`origin=null; fetch-site=undefined`. Chromium omits Fetch Metadata on the plain
HTTP fixture origin. The disposable browser launch now marks only
`http://fixture.test` trustworthy, with an explicit secure-context assertion, to
exercise the HTTPS metadata behavior without weakening production authority.
Real authoritative-domain HTTPS and host protections remain external activation
requirements; the fixture does not prove deployment TLS. New hosted verification
and final review remain pending. No production gate changes.


### Anonymous strict-cookie proof binding (2026-10-23)

Run 39912488630 passed SQL/races but the disposable browser did not become a
secure context with the test flag, so that test-only approach was removed.
The final design binds the signed proof to an anonymous Strict/HttpOnly cookie
(and Secure/__Host- on HTTPS). This supports no-referrer browsers without Fetch
Metadata while retaining cross-site protection without a WordPress login.
The HTTP fixture checks valid proof without its cookie is denied and the cookie
is anonymous/HttpOnly/Strict; foreign Origin and explicit cross-site metadata
remain rejected. #1473 withdrawal remains cookie-free and unchanged. Earlier
metadata-only/no-cookie descriptions above are historical checkpoints, superseded
by the current contract. New full hosted validation remains pending.


### Prepared-link connection

New internal recipient expansions now carry their already independently issued
unsubscribe bearer as `withdrawal_token` in the preference URL, while preserving
the separate unsubscribe URL. This completes clickable expired-page navigation
for newly prepared links without public issuance or changed stored output bytes.
Focused expansion regressions verify matching independent bearers, expiry
separation and marker-only HTML/text escaping. No worker/provider is integrated;
production issuance remains disabled. Previously prepared links without that
parameter use the original email's separate unsubscribe link.


### Final runtime acceptance evidence (2026-10-23)

[CI 39913876819](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39913876819)
completed **successfully** on runtime/fixtures
`1137f1fd2c117c87587954db9708cb1508f3d8c9`, tree
`9471ce17fcc945719c3f646ce758a8f585424184`, matching local:

- Single-site job `120283177517`: **passed**.
- Multisite job `120283177334`: **passed**.
- Both completed logs inspected: 27 standalone suites, PHP syntax, real SQL
  read invariance, supported edits, topic preservation, expiry/wrong purpose/
  revocation/replacement denial, rollback and duplicate submissions; all nine
  independent-process edit/identity/withdrawal races; legacy public preferences;
  #1473 human and machine withdrawal; new anonymous strict-cookie proof,
  foreign-origin/cross-site/no-cookie rejection, HTTP audit rollback, desktop/
  mobile edits and independent expired-link withdrawal. Prepared-link expiry
  separation and escaping regressions passed in both topologies.
- Downloaded both final artifacts and inspected desktop/mobile preference and
  expired-page captures, plus existing withdrawal captures. Forms/actions are
  readable and usable; browser assertions confirm no horizontal overflow,
  CRM-only preference assets, no third-party analytics and privacy headers.
- [Single-site artifact 12484336957](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39913876819/artifacts/12484336957),
  [multisite artifact 12483689804](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39913876819/artifacts/12483689804),
  retained through 2026-11-06. Disposable synthetic data only; no production
  environment/secrets, mail or provider integration.

Earlier runs 39844434992, 39911741853 and 39912488630 failed browser acceptance
and are superseded, not relabeled as passing. Run 39913556688 was superseded/
cancelled. Final cookie-bound runtime uses the ordinary HTTP fixture without a
trust override. Local 27 suites/459 PHP lint and shell/JavaScript/whitespace checks
passed. The evidence-only follow-up changes no runtime or fixture bytes.

The custom-topic review finding was fixed with preservation/overflow regressions
and its outdated thread resolved. **Final rereview remains pending**; no clean
final runtime review of #1474 is claimed. Activation is still **blocked** on the
old-mailbox/deletion policy, host-log/HTTPS controls, keys, retention/privacy and
restore readiness. Phase 5 remains **in progress**. Both factories stay null;
production issuance, sending, intake, collection and cron stay disabled. No
worker/provider integration, merge or deployment. Original checkout preserved.


## Phase 5B — atomic preparation prerequisite (2026-10-23)

**Current task:** ninth bounded Phase 5B PR, reduced to the smallest coherent
prerequisite for worker integration: atomic durable recipient preparation and
exact-byte replay. Phase 5 remains **in progress**. This section supersedes the
historical current-task and pending-review statements above.

### Baseline and bounded scope

#1474 is merged at latest main `321ffa08b37977bf89a458678769e7a87029133f`.
Final head `4d9dd8f70ba6115674b82b83b601cf21ee91c8f4` passed
[CI 39914817422](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39914817422)
and completed [clean final review](https://github.com/7mus7y-dotcom/pera-wp/pull/1474#issuecomment-6359499969);
the sole earlier finding is resolved. No applicable AGENTS.md found. Read this
plan/history, provider specification and worker, submission, token, expansion,
identity, attempt and encrypted-output contracts. Initial checkpoint preceded
lengthy checks. Original clean `/workspace/pera-wp` at `c9f1d5ae` is preserved;
work is isolated in `/workspace/pera-worker`.

Shell fetch failed once because the configured proxy is unreachable and was not
retried. Connector reads verified latest main and supplied all 17 changed files.
Reconstructed baseline tree `4b2910e0eac82ea9cd40c219fff1fc13b4f1e522`
matches #1474's final tree. The reconstruction commit is local scaffolding only;
publication must parent directly to verified main, excluding that scaffolding.

`prepareOutput` accepts trusted frozen inputs, outside another transaction.
Mailbox/subscriber, identity and attempt locks serialize preparation. An optional
internal issuance registrar receives the actual digest and authenticated protocol
metadata, never the bearer or key. Both issued digests, immutable attempt metadata
and authenticated encrypted output commit in one transaction before returning.
Any expansion, registration, envelope validation, encryption, insert or COMMIT
failure rolls all these writes back. Existing metadata-only methods remain for
compatibility and historical access; new preparation does not compose their
separate transactions.

An existing attempt always enters recovery before issuance. Recovery does not
require current rendering, signing keys, origin or issuance time; it authenticates
stored ciphertext and exact immutable metadata, and compares all supplied envelope
fields. Conflicts and missing/corrupt output fail closed, without replacement
tokens, ciphertext overwrite or partial repair. Historical review-only content
cannot start a new preparation. This API remains internal, unbootstrapped and
explicitly grants no worker, consent or transport authority. Its return is never
a submission/retry permit, including after acceptance, unknown outcome or cancel.

### Validation checkpoint

Local PHP 8.4: all 27 standalone suites and 194 plugin PHP syntax checks excluding
vendor-webpush passed; shell syntax and whitespace passed. Focused issuance
regressions verify actual digest/metadata and failure before returning a bearer.
New disposable SQL fixtures inject failures at first/second token, attempt,
ciphertext and COMMIT, asserting complete rollback and zero mock transport calls.
They cover exact-byte replay without issuance inputs, ciphertext invariance,
conflict rejection, historical capture rejection and missing-output refusal.
A fixture-only SES mapper call checks all four durable records exist and the
transaction has committed before mock transport invocation. Four independent-
process races cover identical/conflicting concurrent preparation and identity
revocation in both lock orders. Both topologies execute these through the existing
workflow. Hosted validation remains pending at initial publication.

Local Docker socket health failed once with `connect: operation not permitted`;
daemon is running and user is UID 1000. No retry, daemon restart or local SQL
success is claimed. Hosted CI is required for actual SQL/race acceptance.

### Dependent work and unchanged gates

The full requested worker integration is deliberately **not complete** in this
prerequisite. Next PR must implement concrete worker-authorized published-capture
and frozen-membership resolution, trusted envelope construction, preparation
orchestration and SES handoff under fresh final job/due-time, lease/owner/version,
pause/cancel and live consent/suppression fences. Exercise withdrawal during
preparation, cancellation, stale leases, denied-authority zero calls and
accepted/unknown no-resubmit behavior through that integrated path in both
SQL topologies. Only independently proven non-acceptance may follow existing
retry rules. Preserve historical recovery access without turning it into send
authority. No worker state machine or retry policy changes in this PR.

Production dispatch stays unconditionally blocked; provider/withdrawal/preference
factories, cron activation, real intake and public collection remain disabled.
No real HTTP transport, credentials, provider activation, sending, merge or
deployment. All runtime and fixtures remain PeraCRM-owned. Earlier external
privacy/key/retention/restore and old-mailbox/deletion policy blockers remain.

### Hosted acceptance and publication

Non-draft [PR #1475](https://github.com/7mus7y-dotcom/pera-wp/pull/1475)
contains runtime/fixtures `b76ff23d40884d2e889d32f88eefe50a831595a0`, tree
`dd5e08e398dcf68beb956f0794753e1355549504` matching the isolated local tree,
parented directly to main. [CI 39929679591](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39929679591)
completed successfully in both single-site job `120334565586` and multisite job
`120334565711`. Completed logs for both were inspected: all five injected failure
boundaries roll back with zero mock calls; actual issued digest/metadata,
historical-capture rejection, exact replay without issuance inputs, ciphertext
invariance, conflict/missing-output denial, four independent-process blocking
races and the SES mapper mock COMMIT boundary all passed. Existing SQL, worker,
withdrawal/preferences and browser suites also passed in both topologies.
No new UI was added or changed; no new visual acceptance is claimed.

[Final runtime review](https://github.com/7mus7y-dotcom/pera-wp/pull/1475#issuecomment-6361467306)
completed on `b76ff23d` with no findings. This evidence-only follow-up changes no
runtime or fixture bytes. Full worker integration and its final authority/race
matrix remain dependent work exactly as listed above. Production gates remain
blocked; no real transport, sending, intake, collection, merge or deployment.

## Phase 5B — worker preparation and mocked SES integration (2026-10-23)

**Current task:** tenth bounded Phase 5B PR. Phase 5 remains **in progress**.
#1474 is merged with passing final-runtime CI 39913876819 and clean review
on final head `4d9dd8f70ba6115674b82b83b601cf21ee91c8f4` (comment
6359499969). Latest main also includes #1475 at
`a0fdebae3b24b5fce6e340fdd283f0d37ad5990e`; its atomic preparation service is
reused, not replaced. No applicable AGENTS.md found in workspace/repository or
ancestors. Read this plan/history, provider specification, capture/publication,
worker, submission, recipient expansion/token/persistence and SES contracts.
Original clean `/workspace/pera-wp` at `321ffa08` is preserved. Isolated worktree
`/workspace/pera-worker` reconstructs the eight newer main files through the
connector, checking every blob SHA and baseline tree
`0c07f436c955cb3b6fcf853f275318f63a0bba60`. Shell fetch failed once on unreachable
proxy port 8080 and was not retried. Reconstruction is local scaffolding only;
publication parents directly to verified latest main.

The concrete worker content projection reads only bound published frozen revision,
complete frozen preparation, exact membership and integrity-checked captured
content. Capture state `unpublished_content` is historical storage terminology;
the separate published frozen revision supplies publication authority. Schema-1
review captures are rejected, never upgraded. Captured structured sender and
subject fields construct the envelope; no live draft/configuration or rendering.
All reads occur inside the worker transaction after owner/version/lease, job,
due-time and eligibility checks, with a second authority/lease check after decode.

`submitPrepared` performs preflight, releases its locks, calls atomic recipient
preparation, then performs fresh final job/due-time, mailbox/consent/suppression,
identity epoch and owner/version/lease checks. Only then does the inflight state
commit and the injected SES mapper execute. No transaction spans transport.
The shared mailbox mutex serializes final checks with withdrawal/import/lifecycle
writes. Preparation can leave durable unused output if cancellation, withdrawal,
lease loss or interruption wins; this is never submission authority. Claimed
recovery refunds the reserved attempt and permits exact-byte recovery under a
new owner. Accepted/unknown rows cannot be submitted or claimed again. Proven
non-acceptance alone follows existing bounded retry rules with a new attempt.
Existing attempt replay uses stored encrypted bytes, even without issuance keys
or a valid issuance origin, and never overwrites immutable conflicting bindings.
Historical recovery remains available through the existing separate store API.

### Validation checkpoint and limits

Local PHP 8.4: 27 standalone suites and 200 plugin PHP syntax checks excluding
vendor-webpush passed before the final focused test extension; that focused suite
also passed. Shell syntax and whitespace checks passed. Added integrated SQL
fixtures cover accepted/unknown no-resubmit, proven transient retry, interrupted
preparation/exact-byte replay, stale owner and refunded recovery, actual withdrawal
in the preparation interval, pause, cancellation, future scheduling, invalid
membership/authority, historical review capture rejection and output insert
rollback. Mock SES checks durable digest/binding/ciphertext presence and COMMIT
before transport. Four independent-process races cover concurrent submission,
withdrawal, cancellation and process exit after preparation COMMIT. Both SQL
topologies run through the existing hosted workflow; hosted results are pending
at initial publication and must be recorded after execution.

Local Docker health failed once: socket `connect: operation not permitted`;
daemon is running, task UID 1000. No local SQL/browser pass is claimed. Hosted
CI remains the required acceptance evidence for SQL/races. No new UI/CSS/JS.

Production dispatch remains unconditionally blocked, with no concrete production
worker or transport factory, HTTP client, credentials, sending, cron activation,
real intake or public collection. All additions are plugin-owned and internal;
fixtures use disposable synthetic data and injected mocks only. Existing external
privacy/log/HTTPS/key/retention/restore and old-mailbox/deletion policy blockers
remain. Provider activation, real transport, authenticated feedback and readiness
work remain future bounded PRs. No merge or deployment.

### Initial hosted correction and review

Non-draft PR #1476 published runtime `25b7861c` with local-matching tree
`528e81c6f7da238c6059c208c6dbf6035c79a5df`. Initial review completed clean on
that runtime (summary comment 6362286799). Run 39935447644 passed the integrated
SQL service scenarios but failed the new process-race setup: an arrow closure
using compact() did not capture variables mentioned only as strings. Replaced
that fixture setup with explicit array values. This failure is not acceptance.
Local review also canonicalized defensive scope copies so replay does not depend
on configuration key order, and strengthened the integrated accepted/unknown/
cancelled cases with historical exact-byte recovery plus unchanged worker-state
assertions. All 27 local suites/200 syntax checks passed again; new final-head
hosted validation and review are required for this runtime correction.

### Final runtime acceptance (2026-10-23)

[CI 39936033892](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39936033892)
completed **successfully** on final runtime/fixtures
`04cad099dbb48cb31e1713abc9904cfb433f0b4e`, tree
`230ef3e168e6a625e49653cd2febabedac899f62`, matching the isolated local tree.
Single-site job `120355671172` and multisite job `120355670877` both passed.
Completed logs from both jobs were inspected. All integrated worker SQL cases,
all four new independent-process races, the inherited atomic rollback/conflict/
concurrency suites, existing worker/scheduler tests and withdrawal/preferences/
browser fixtures passed. In particular both topologies prove exact historical
recovery leaves accepted/unknown/cancelled authority unchanged, only fresh valid
claims can submit, concurrent workers make one mock call, withdrawal/cancellation
make zero calls, and a killed process after preparation COMMIT recovers identical
bytes under fresh authorization. Captured headers and both bodies traverse the
SES mapper with synthetic mock transport only, after durable state and inflight
COMMIT. Earlier failing run 39935447644 is superseded, not relabeled as passing.

[Final clean review](https://github.com/7mus7y-dotcom/pera-wp/pull/1476#issuecomment-6362434356)
covers runtime `04cad099db`; summary 6362286799 reports completed, no findings and
no unresolved findings. All 27 local standalone suites and 200 PHP syntax checks,
plus shell syntax/whitespace passed. Hosted artifacts are retained through
2026-11-06: single-site `12494346363`, multisite `12493628696`. No UI changed;
no new screenshot inspection or real-host/provider acceptance is claimed.

This follow-up changes only acceptance documentation; runtime and fixture bytes
remain exactly those tested and reviewed above. Production dispatch, recipient
factories, intake/collection and cron remain blocked. No real HTTP, credentials,
mail, merge or deployment. Original checkout remains clean and preserved.

## Phase 5 — SNS signature and durable receipt prerequisite (2026-10-23)

**Current task:** eleventh bounded Phase 5 PR. Phase 5 remains **in progress**.
This PR deliberately completes only signature/receipt foundations. Event
application is dependent work, not implemented or enabled by this prerequisite.

Verified #1476 merged at latest main `4a4204a7b371b30cf2c77d14ef331986e63610b5`.
Final-head CI 39936962447 succeeded on `6b69e466`; clean review comment
6362434356 covers final runtime `04cad099db`, unchanged by its evidence-only
follow-up. No applicable AGENTS.md found in workspace/repository or ancestors.
Read current/history plan, provider specification, immutable attempt/output,
worker result/reconciliation and cumulative suppression repository contracts.
Original clean `/workspace/pera-wp` at `a0fdebae` is preserved. Isolated
`/workspace/pera-sns` reconstructs #1476's eight files through GitHub connector;
baseline tree `592b66e113c00d2f45d0b259d523a64775a87825` exactly matches latest main.
Shell fetch failed once on unreachable proxy port 8080 and was not retried.
Local reconstruction is scaffolding only; publication parents directly to main.

Notification-only verifier bounds raw bytes to 256 KiB and JSON depth to 8,
requires flat string fields, exact topic/account/region and signature version 2,
constructs AWS canonical fields including optional Subject and trailing newline,
and verifies RSA/SHA256 with bounded, valid-time, minimum-2048-bit certificate.
Exact regional HTTPS certificate host/path excludes userinfo, explicit ports,
query/fragment, traversal, escaped paths and alternate hosts. Certificate retrieval
is mandatory injection; the separate unbootstrapped WordPress helper uses safe
HTTP, TLS peer/hostname verification, five-second timeout, zero redirects, no
cookies and a 16 KiB certificate limit. Trust in the signing certificate comes
from its authenticated AWS HTTPS origin; local fixtures inject self-signed
synthetic certificates only. There is no cache, subscription confirmation,
SubscribeURL fetch, factory, REST registration or production wiring.

Schema 31 adds one InnoDB receipt table with exact unique scope/message ID,
where scope hashes provider, deployment, blog, account, region and topic.
Receipts retain signed canonical bytes/hash, certificate fingerprint and verifier
version. Insert-or-compare under SQL uniqueness serializes duplicates/conflicts;
conflicts roll back without overwriting the original. Every authenticated receipt
is durably quarantined as application_pending, stale_timestamp (>24h) or
future_timestamp (>5m). Inner SES JSON is deliberately opaque. Identical replays
retain original bytes/reason/time. receive() returns only after COMMIT, rejects
nested transactions without committing caller work, and emits fixed unchained
storage/dependency errors. A future HTTP adapter must map temporary errors to
503 and must never acknowledge before this return. Conflicts need security
monitoring in that adapter. Auth errors must remain fixed 4xx. No HTTP ack is
implemented or claimed here.

Official AWS documentation checked again 2026-10-23:
[SNS signature verification](https://docs.aws.amazon.com/sns/latest/dg/sns-verify-signature-of-message-verify-message-signature.html)
confirms canonical fields/order, final newline and signature-version algorithms;
[SES SNS contents](https://docs.aws.amazon.com/ses/latest/dg/event-publishing-retrieving-sns-contents.html)
confirms sendingAccountId, SES message ID, tags and event taxonomy. These checks
are documentation evidence, not live AWS acceptance.

Validation: 28 local standalone suites, 207 PHP syntax checks and shell syntax/
whitespace pass. Automated signature tests use injected local certificates and
stub HTTP only, including forged payload, wrong topic/account/version, malformed/
oversized envelope, certificate URL attacks, failed dependencies/expiry and
redirect/size policy. New disposable single/multisite SQL fixtures cover v30
additive migration, exact/old duplicate, conflicting receipt, forged/wrong scope,
insert/COMMIT rollback before return, nested transaction preservation, timestamp
quarantine, deployment/blog isolation and invariance of subscribers/suppressions/
attempts/deliveries. Two independent-process races cover duplicates and conflicts.
Compose networking is internal and fixture HTTP/mail remain globally blocked.
Local Docker socket access failed once (operation not permitted); hosted SQL/
race acceptance and final runtime review remain pending at initial publication.

**Dependent bounded PR:** parse normalized SES events only after authentication;
validate sendingAccountId/configuration set, deployment/blog and both opaque keys
against durable attempt bindings, including early Send before message response
and conflicting message mappings. Never correlate solely by mailbox. Add logical
event deduplication across receipt IDs, durable authenticated unmatched quarantine
and reconciliation without subscriber creation. Record separate acceptance and
delivery facts without resend authority or regression of accepted/unknown/cancelled
protections. Apply permanent bounce and complaint through a trusted internal
transaction participant with mailbox-first locks and cumulative suppression;
never grant consent/clear denial or retry submission for post-acceptance transient
failure. Exercise early/out-of-order message facts, cumulative suppression races
and atomic receipt/event/suppression rollback in both SQL topologies. Add guarded
HTTP adaptation only after those application invariants are complete. The current
opaque quarantine is not proof of SES account/configuration/attempt authority.

Endpoint remains absent; dispatch, real intake, public collection, recipient
factories and cron activation remain blocked. No AWS provisioning, credentials,
real HTTP/mail, sending, merge or deployment. Privacy/retention, replay/redrive/
restore horizon, host logging and certificate rotation/readiness remain activation
requirements. Preserve additive receipts/tombstones on rollback; never truncate
or interpret quarantine as resend permission.

### Hosted checkpoint and fixture correction (2026-10-24)

Non-draft PR #1477 published runtime `09e6e191`, tree
`c2c99c7cb4f8baad88e3469f65f22cec86b9cf4a`, matching local. Initial hosted
run 39940513635 passed standalone regressions/syntax but both SQL topologies
stopped at an inherited fresh-migration assertion hardcoded to version 30.
Updated that assertion and repeated-dbDelta target to PERACRM_SCHEMA_VERSION;
this fixture-only correction does not change runtime. New hosted acceptance
is required; the failed initial run is not SQL acceptance.

GitHub automated review failed to complete and reported account code-review
usage exhausted (comment 6363052553). It supplied no actionable inline finding;
the failed summary's findings flags are not a completed review. No clean external
review is claimed. Local review inspected actual runtime and fixture changes.

### Recovery and local review correction (2026-10-24)

Recovered commit `c9d2319b103eadd5def4a57dcc161416dccfe710`: its only parent
exactly matched remote branch head `09e6e191`, its two-file diff contained only
the schema fixture correction/evidence, and tree `cbc85fa541daaca1a3f46f074410977f38a1cc20`
matched preserved local work. Published to the existing branch with force=false
and an expected-head check; no reimplementation or new PR.

Thorough local review found a lost-transaction acknowledgment risk if wpdb
transparently reconnects and replays COMMIT on a replacement SQL session.
Receipt storage now checks its connection ID before and after COMMIT and returns
sns_storage on replacement, permitting provider retry even if the outcome is
uncertain. Added real SQL session replacement immediately before COMMIT plus
SELECT-failure rollback regressions. No event application or activation added.
This local review does not satisfy the blocked external Codex review gate;
no further external review requests are made while usage remains exhausted.

### Final code/test validation and remaining review gate (2026-10-24)

Final code/test commit `59a1cc219cd84840a85976f61e3ef8eecf8a0457`, tree
`224c65ee75e70443f4d59f02440a7c8401488aae`, exactly matches the local worktree.
[Hosted CI 40089961360](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/40089961360)
completed **successfully**. Single-site job `120860497716` and multisite job
`120860497392` both passed; completed logs from both jobs were inspected.

Both topologies passed the independent canonical-field signature oracle,
forged/wrong-scope and certificate URL regressions, v30-to-v31 migration,
immutable old duplicates/conflicts, insert/SELECT/COMMIT failure rollback,
real SQL session replacement immediately before COMMIT (sns_storage, no receipt
acknowledged or retained), caller transaction preservation, early/stale quarantine,
deployment/blog isolation, unchanged subscriber/suppression/attempt/delivery
state, and both independent-process duplicate/conflict races. Existing worker,
atomic preparation, scheduler, withdrawal/preference SQL/races and browser suites
also passed, including disabled-send/protected-state assertions. No new UI changes
or screenshot inspection is claimed. Artifacts: single-site `12558976498`,
multisite `12558926753`; both retained through 2026-11-07.

Local validation: 28 standalone suites, 207 PHP syntax checks, shell syntax and
whitespace checks passed. Added an independent canonical oracle because the
original fixture signer shared the verifier's canonicalization helper. Local
review covered every runtime/schema change, HTTP certificate policy, canonical
signature fields, scope hashing, receipt uniqueness and conflict immutability,
SQL error/reconnect/transaction ownership, test wiring and absent activation.
The reconnect finding was fixed and exercised in both real SQL topologies;
no further local findings remain. This is local review evidence only.

**External review remains BLOCKED** by Codex usage limits recorded in comment
6363052553. There is no completed clean external review, and local review does
not substitute for it. No repeated external review request was sent. The initial
failed run 39940513635 and superseded/cancelled runs 40089833863/40089911073
are not acceptance. This evidence follow-up changes only this plan; all runtime
and fixture bytes remain those validated above. Final-head documentation CI is
tracked in PR #1477 checks and conversation evidence.

Intake remains unregistered/unbootstrapped; all disabled dispatch, collection,
recipient factory and cron gates are preserved. No event application, real AWS
traffic, provisioning, credentials, sending, new PR, merge or deployment. The
original checkout and isolated worktree remain preserved. The dependent event
application work listed above is still outstanding and outside #1477.
