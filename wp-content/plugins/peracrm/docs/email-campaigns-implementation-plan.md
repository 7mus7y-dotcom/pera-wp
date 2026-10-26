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

**Current task:** bounded Phase 5 durable sending budget and circuit service
prerequisite. Worker integration remains dependent work; see the final section.

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

## Phase 5 — durable SES normalization prerequisite (2026-10-24)

**Current task:** twelfth bounded Phase 5 PR, deliberately split to finish the
normalization/quarantine prerequisite. Phase 5 remains **in progress**. This
section supersedes earlier current-task and #1477 pending-review statements.
The full event-application request is **not complete** in this prerequisite.

### Verified baseline and externally supplied AWS verification

GitHub connector verified #1477 merged into latest main
`0e894ea87b3a1f88529dbb17dafb0aa93530beae`. Final head `d44bd8e0aab0c893c48fdd07da43c58835ab7c19`
passed [CI 40090978273](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/40090978273)
and received completed clean [Codex review](https://github.com/7mus7y-dotcom/pera-wp/pull/1477#issuecomment-6386190114)
on that exact head. Its description's usage-limit statement is stale. No
applicable AGENTS.md found in repository/workspace or searched ancestors.
Read living/history plan, provider specification, receipt, immutable attempt,
prepared worker, retry/reconciliation and cumulative mailbox suppression services.
Original clean `/workspace/pera-wp` at `4a4204a7` is preserved. Isolated
`/workspace/pera-events` reconstructs #1477 via connector; baseline tree
`072b04a8e5649c30df5e49844785c38301122dd9` matches its published tree exactly.
The local reconstruction commit is scaffolding, excluded from publication;
the published change must parent directly to verified main.

Shell fetch failed once on unreachable configured proxy port 8080; no repeat
attempt. Direct documentation fetch also failed. **Externally supplied source
verification:** the parent session retrieved the following official AWS pages
on 2026-10-24 and the user supplied the findings. This workspace did not retrieve
these pages directly:

- [SES event-publishing SNS contents](https://docs.aws.amazon.com/ses/latest/dg/event-publishing-retrieving-sns-contents.html)
- [SES notification contents](https://docs.aws.amazon.com/ses/latest/dg/notification-contents.html)
- [SES suppression list](https://docs.aws.amazon.com/ses/latest/dg/sending-email-suppression-list.html)

Those findings distinguish eventType from legacy notificationType; mail
messageId/sendingAccountId/destination and event-publishing tags; bounceType,
bounceSubType, bouncedRecipients and feedbackId; terminal transient bounce
delivery failures; redacted complaint recipients as candidates and not-spam
feedback; Delivery as receiving-server acceptance; Send's empty object. AWS
provides no notification ordering/batching guarantee, supports multiple
recipients and introduces additional fields. Strict authority-field checks and
tolerance for harmless additive fields follow these supplied findings. No live
AWS verification, sending or provider acceptance is claimed.

### Coherent boundary and storage contract

Normalization and durable quarantine are split from matching/application because
the latter must coordinate immutable message associations with synchronous
worker results/reconciliation/retry fences and mailbox-first suppression writes.
Publishing partial matching without those fences could let an early Send conflict
with a later non-acceptance result. This PR therefore provides no matching,
acceptance, delivery-state, suppression, consent, subscriber or retry authority.
Normalized facts are observations pending matching, never verified-provider
application evidence merely because tags exist.

Internal/unbootstrapped normalizer supports Send, Delivery, Bounce, Complaint
and DeliveryDelay, with legacy type alias only when required correlation data
exists. It bounds bytes/depth/recipients/string fields, validates fixed account,
configuration set and singleton hash tags, canonicalizes local mailboxes and
timestamps, verifies recipient membership, and ignores harmless unknown fields.
Reject, Rendering Failure, tracking/subscription events and malformed/missing
correlation data remain quarantined. Complaint candidate identity and feedback
subtype including not-spam are preserved; no subtype changes suppression.
Transient/Undetermined/Permanent remain distinct observations without retry or
permanent-denial decisions. No transient threshold policy is activated.

Schema 32 adds immutable normalized event facts and per-receipt normalization
links. Logical identity uses scope/message/type/recipient/feedbackId when present,
otherwise the complete stable canonical projection. Conflicting content under
the same feedback identity quarantines the entire batch without replacing facts.
Duplicate SNS receipts and logical events retain original bytes/times and durable
receipt references. Only existing authenticated #1477 receipts can enter the
durable service: callers pass receipt ID, never JSON or a verified boolean.
Canonical payload hash/version/topic suffix are checked; no certificate network
call is needed to consume the authenticated durable record. Old/future receipts
keep their original quarantine; no automatic redrive. Provider policy hash pins
repeat processing to the same configuration/version. The original receipt remains
immutable and quarantined, even after normalization. No application state exists.

Fact inserts and normalization link commit atomically. Nested callers are
rejected without ending their transaction. SQL connection checks before/after
COMMIT retain #1477's fail-closed acknowledgment protection; an owned savepoint
also detects loss before connection capture. Each write is SQL-session-fenced,
so wpdb reconnect-and-replay on an autocommit replacement session writes nothing.
Storage failures return fixed sns_storage and preserve the receipt for retry.

### Validation checkpoint and dependent work

Local PHP 8.4: all 29 standalone suites and 213 plugin PHP syntax checks excluding
vendor-webpush passed; shell syntax and whitespace checks passed. Focused tests
cover supported types, legacy aliases, recipient/order/additive-field stability,
wrong scope/recipient/tags, candidate complaints/not-spam, bounce distinctions,
timestamp and size/depth/list bounds, unsupported types and logical conflicts.
New real SQL fixtures cover v31 additive migration, authenticated-only access,
receipt/logical duplicates, immutable conflicts, early/out-of-order facts,
scope/policy/target isolation, stale/future quarantine, caller transactions,
insert/SELECT/COMMIT failures and real SQL reconnection before either write,
COMMIT and initial connection capture. Four independent-process races exercise
same receipt, duplicate logical event, conflicting feedback and concurrent
withdrawal, retaining evidence and cumulative withdrawal. State snapshots cover
subscriber/consent/suppression, all worker/job states, attempts and output bytes.
Hosted single/multisite SQL/race acceptance is pending initial publication.
Local Docker socket failed with operation not permitted; daemon is running and
UID is 1000. No restart or repeated socket attempts; no local SQL success claimed.

**Dependent PR:** match deployment/blog and all persisted immutable attempt,
account/region/configuration-set, delivery/attempt/snapshot/envelope, recipient and
message-ID bindings. Persist immutable response/event message associations,
handle early Send and conflicting provider IDs, and coordinate worker outcome,
claim and reconciliation fences so no late/unknown/accepted/cancelled observation
can produce resend authority. Add separate acceptance and receiving-server
delivery facts. Transactionally apply Permanent bounce and complaint suppression
with mailbox-first locking, preserved evidence and cumulative flags, including
post-cancellation/completion events. Define conservative complaint subtype and
candidate policy under exact single-recipient attempt binding; not-spam must
never clear denial. Commit application facts, suppression and receipt application
state together. Exercise actual matching/application, both withdrawal lock orders,
message conflicts and all rollback/reconnect/protected-attempt races in both SQL
topologies. This PR's normalization tests do not claim that dependent acceptance.

All artifacts remain PeraCRM-owned. No public HTTP endpoint, bootstrap/factory,
AWS provisioning, real transport/credentials/messages, cron activation or UI.
Dispatch, intake, public collection and recipient factories stay disabled. No
merge or deploy. Retention/privacy/restore/redrive/readiness activation requirements
remain open; preserve receipts and normalized tombstones on rollback.

### Initial publication and local review follow-up

Non-draft [PR #1478](https://github.com/7mus7y-dotcom/pera-wp/pull/1478)
published initial head `f6ac868ca5e2cdab67f09c2a0d078827eaad9f47`, tree
`323ebabbed904b2116496b088afef94e68c42542`, directly parented to verified main.
Local review of the actual runtime/schema/fixture diff found that normalized
recipient facts should retain the complete sorted mail destination set for
future single-recipient attempt matching; that projection is now retained.
Also reject explicit null eventType/complaintFeedbackType rather than treating
them as absent optional data. Focused regressions pass for all three checks.
Hosted CI and review must cover the final runtime follow-up, not just initial
publication. No matching/application or activation is added by this correction.

Further transaction review tightened the top-level probe: only MySQL's expected
missing-savepoint error can establish autocommit; arbitrary RELEASE failure
cannot authorize START and implicitly commit a caller transaction. Added a real
SQL injected RELEASE-failure fixture that verifies the caller still owns its
transaction. A server returning a different/localized diagnostic fails closed.

### Hosted acceptance and final runtime review

Final runtime/test commit `67e7ed44c06107121c4decf6fba177faecf74993`, tree
`87998398f563c475d34ec3a7abef6abefd9a5242`, matches the isolated local worktree.
[CI 40113981001](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/40113981001)
passed both single-site job `120931474071` and multisite job `120931473797`.
Completed logs from both jobs were inspected. Both passed the new schema-31
migration, authenticated-receipt-only path, immutable SNS/logical duplicates and
receipt references, feedback conflicts, scope/recipient/tag/unsupported quarantine,
out-of-order and freshness cases, insert/SELECT/COMMIT rollback, real replacement
SQL sessions before event/link writes/COMMIT/connection capture, caller transaction
and failed-probe preservation, four independent-process races including withdrawal,
and complete subscriber/suppression/attempt/output/worker/job state invariance.
Existing worker/lease/recovery/reconciliation, scheduler, withdrawal/preferences
and browser suites also passed. No new UI or screenshot acceptance is claimed.

The exact final runtime received completed clean
[Codex review](https://github.com/7mus7y-dotcom/pera-wp/pull/1478#issuecomment-6387006371),
with no findings or unresolved review threads. Local review covered all actual
runtime/schema/fixture changes and addressed destination-set preservation,
explicit-null authority validation and the unexpected transaction-probe failure.
All 29 standalone suites, 213 PHP syntax checks, shell syntax and whitespace
checks passed locally on that final runtime. Local Docker remained unavailable;
real SQL/race acceptance comes from the two hosted jobs above.

Evidence artifacts: single `12567578858`, multi `12566849884`, retained through
2026-11-07. Superseded runs `40113116041` and `40113594024` were cancelled after
runtime fixes and are not acceptance. This evidence follow-up changes only this
plan; every runtime and fixture byte remains exactly the tested/reviewed version.
Documentation-head CI is tracked in PR #1478's checks. The dependent matching,
message association, worker fences and transactional suppression work above is
still outstanding. All disabled gates remain intact; no merge or deployment.

## Phase 5 — bounded SES attempt/message correlation (2026-10-24)

**Current task:** correlate authenticated normalized events only. Phase 5 remains
**in progress**. Verified #1478 merged at `a9cd8d3b46e53fb99bbbaf5317cb279d87da34e7`;
final-head CI [40114612366](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/40114612366)
passed and completed clean [review](https://github.com/7mus7y-dotcom/pera-wp/pull/1478#issuecomment-6387006371)
covers final runtime `67e7ed44`, unchanged by the evidence-only final commit.
Read current/history plan, provider specification, immutable attempts/output,
normalized-event/receipt and worker services. No applicable AGENTS.md found.
Original `/workspace/pera-wp` is clean and preserved. Isolated worktree
`/workspace/pera-correlation` reconstructs #1478's twelve changed files through
the connector after one failed fetch (configured proxy unreachable); baseline
tree `0972375210668932ff800976b610d9e9bbee0e60` exactly matches verified main.
Reconstruction scaffolding will not be included in published ancestry.

Schema 33 adds immutable provider-message associations with exact binary unique
provider namespace/message ID and deployment/blog/attempt indexes, plus append-only
matching decisions keyed by authenticated event scope, logical event and pass.
Both uniqueness constraints are checked at schema readiness. Provider namespace
includes deployment/blog and account/region, across topic, configuration-set and
configuration-version changes; immutable attempts independently pin the full
provider scope including configuration version. Decisions retain receipt, fact
hash, immutable binding hash and fixed quarantine reason. Association retains
first authenticated event/receipt provenance. Existing receipt/normalization/fact
bytes remain untouched. A matched decision is correlation, never event application.

The unbootstrapped matcher accepts only IDs of already authenticated, durably
normalized matching-pending receipts. It verifies receipt/normalization policy,
provenance and stored hashes, locks the logical facts and exact immutable attempt,
and compares deployment/blog, account/region/configuration set/version, all four
tags, snapshot/envelope hashes, recipient and the entire single-recipient destination
set. It never searches by mailbox, delivery alone or mutable current subscriber
identity. Revocation or withdrawal does not erase historical correlation. Exact
attempt uniqueness removes ambiguity rather than choosing a best candidate.
Missing attempts remain quarantined; mismatches and conflicting associations
are retained as decisions without replacing any binding. Identical replay and
logical duplicates retain first decision and association provenance.

Events can create the association before any submission response message ID
exists; no response or current worker status is required. Submission-response
integration and worker conflict/reconciliation fences remain dependent work;
this matcher is not wired into the submission path and grants it no authority.
Explicit internal `reprocess(receipt_id)` revisits only missing bindings, with
at most five passes total per logical event and at most fifty facts per receipt.
Ordinary replay cannot consume that budget. Exhaustion remains quarantined;
there is no scan, cron, endpoint, automatic resend or hold release.

Association creation and decisions share one transaction. Logical-event and
attempt locks plus database uniqueness serialize concurrent identical/conflicting
creators. Owned-savepoint checks preserve caller transactions, including failed
probe cases. SQL-session guards make replayed writes on reconnected sessions
no-ops; connection checks fence capture, each write and COMMIT. Fixed unchained
errors expose no addresses, tokens or SQL. No subscriber, suppression, delivery,
retry, job, output or attempt updates occur. All production gates stay disabled.

Local standalone suites, PHP lint, shell syntax and whitespace checks pass.
New disposable SQL fixtures cover v32 additive/idempotent migration, unique-index
readiness, early events, SNS/logical duplicates, both association conflicts,
missing bindings/later arrival/bounded redrive, wrong tags/recipient/full destination
set, deployment/blog/policy denial, insert/SELECT/COMMIT rollback, real SQL-session
replacement before both writes/capture/COMMIT and caller transaction preservation.
Four independent-process races cover duplicate receipts, logical duplicate events,
different messages for one attempt and one message for different attempts.
Snapshots assert consent/evidence/suppression/worker/attempt/output invariance.
Local Docker socket access is denied; daemon exists and UID 1000 cannot recover it.
Real single/multisite hosted execution and final runtime review remain pending.
No local SQL/race pass or provider acceptance is claimed.

No additional AWS fact is needed; use the findings already recorded above and in
the provider specification. No browsing retries, real transport, AWS operations,
credentials, messages, activation, merge or deployment. Remaining bounded work:
submission-response association integration, event outcome application, worker
reconciliation/retry fences, transactional suppression application and only then
reviewed HTTP endpoint adaptation/activation prerequisites. Correlation does not
clear suppression, change consent, release unknowns or prove non-acceptance.

Local follow-up review tightened message uniqueness across configuration sets
within the same deployment/blog/account/region, preventing a second configuration
set from aliasing an already associated provider message. Each immutable attempt
still independently pins its exact configuration set/version. Provider configuration
is defensively copied without caller-held PHP references, and persisted pass numbers
outside the bounded range fail closed. New hosted CI and review must cover this
follow-up runtime. No activation or worker integration is added.

Initial [CI 40116281087](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/40116281087)
passed migration and existing suites but both topologies stopped at fixture setup:
the synthetic expansion origin used a reserved `.test` TLD, correctly denied by
the existing origin validator. Fixture now uses the established `example.org`
synthetic origin; no networking occurs. Runtime is unchanged by this fixture fix.
The failed run is not correlation SQL acceptance.

### Final runtime/fixture acceptance and clean review

Non-draft [PR #1479](https://github.com/7mus7y-dotcom/pera-wp/pull/1479)
published directly on verified main. Final runtime is
`5dced147fe185a0be2612ab8eb70ae58e2757dbb`; the subsequent synthetic-origin
fixture correction produces code/test head
`5bb1f70f11bf9d90ec9ab629407cb1f5f0503fda`, tree
`2b7c7a0271b22110260449a8a05abf3a87ff9ada`, exactly matching local.
[CI 40116728761](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/40116728761)
**passed** single-site job `120939494606` and multisite job `120939494914`.
Completed logs from both jobs were inspected, including migration and missing
unique-index readiness rejection, authenticated-normalized-only input, early
association without response, immutable/logical replay, both message conflicts,
cross-configuration alias protection, defensive configuration references,
missing/later bindings, five-pass exhaustion, tag and complete-recipient denial,
scope/blog/policy isolation, both write failures/SELECT/COMMIT rollback, real
replacement sessions before both writes/capture/COMMIT, caller/failed-probe
transaction preservation and all four independent-process association races.
Subscriber permissions, consent evidence, cumulative suppression, immutable
attempts/output and worker/job state snapshots remain unchanged in those tests.
Existing worker/lease/reconciliation/scheduler/withdrawal/preferences/browser
acceptance also passed. No new UI or visual acceptance is claimed.

Completed clean [Codex review](https://github.com/7mus7y-dotcom/pera-wp/pull/1479#issuecomment-6387378312)
covers exact final runtime `5dced147fe`, with no findings or unresolved threads.
Later changes touch only synthetic fixture origin and this plan, with runtime
unchanged. Local review covered actual runtime/schema/test changes; all 30
standalone suites, 218 PHP syntax checks, shell/JavaScript syntax and whitespace
checks passed on the published code/test tree. The actual SQL seed/normalization
projection also passed locally after the fixture correction. Local Docker is
unavailable; real SQL/race evidence comes from hosted execution above.

Artifacts retained through 2026-11-07: single-site `12569445999`, multisite
`12569611356`. Failed initial run `40116281087` and superseded `40116687565`
are not acceptance. This final evidence commit changes only the living plan;
all runtime and fixture bytes remain those tested above. Documentation-head CI
is tracked in #1479 checks. Original checkout stays clean; no reconstruction
scaffolding is in published ancestry. All eleven changed files are plugin-owned.
Submission-response integration, outcome application, worker reconciliation/retry
fences, suppression application and endpoint adaptation remain dependent work.
All disabled gates remain intact. No merge, deployment or real provider traffic.

## Phase 5 — submission responses, acceptance evidence and retry fencing (2026-10-24)

**Current task:** bounded response integration and acceptance protections. Verified
#1479 merged at `0bd75f353a1e16b5e34eaf63ba7ed6bc74c71e7f`; final-head
CI 40117397794 passed both topologies and completed clean final review
[6387660168](https://github.com/7mus7y-dotcom/pera-wp/pull/1479#issuecomment-6387660168)
followed the explicit final-head request, covering the fixture follow-up as well.
No applicable AGENTS.md was found. Read current/history plan, provider specification,
immutable attempt/output, SES mapper, worker/reconciliation and matching contracts.
Original clean `/workspace/pera-wp` at `0e894ea8` remains preserved. Work is in
`/workspace/pera-acceptance`; connector reconstruction tree
`46d4b3ed4ff981bf3809c1e3c672f632f347d26e` exactly matches latest main.
Reconstruction commit is local scaffolding only; publication parents to main.
The original network command was aborted while awaiting permissions, with no
result. No repeated shell networking or duplicate test runs were launched.

Schema 34 adds immutable acceptance evidence keyed by deployment/blog/attempt,
source and source fingerprint. Successful injected SES mapper responses and
matched authenticated events share one immutable insert-or-compare association
contract. Positive response capture checks the exact persisted envelope/scope/
recipient/hash projection independently of lease ownership. Conflicting message
IDs/bindings retain evidence without replacing the association. Event facts retain
their separate type and provenance: Send, receiving-server Delivery and subsequent
Bounce/Complaint/DeliveryDelay are not submission outcomes or resend permission.
No suppression is applied. Identical and logical replay preserve first provenance.
Pre-v34 event associations already fence retries; explicit replay adds evidence
without rewriting original matching decisions.

Workers pin deployment through an explicit abstract dependency. Claims, final
submission authorization and non-acceptance outcomes check all historical attempts
of the exact scoped delivery under attempt locks. Positive evidence and conflicts
both fence retry. Late positive responses persist before owner/version checks;
no expired owner gains authority. Unknown state is not automatically resolved;
the existing staff capability, nonce, version and independent verification boundary
also requires durable exact positive evidence for prepared attempts. No durable
proof-of-non-acceptance adapter exists here, so prepared unknowns cannot be released
by negative or missing lookup/event results. Cancellation, accepted state and
post-acceptance failures never permit resubmission. Worker mutations now guard
SQL connection/session identity to prevent replayed updates on reconnection.

Added disposable single/multisite response/event arrival-order, duplicate/conflict,
rollback/reconnection/caller-transaction and independent-process race fixtures.
Worker fixtures use injected mocked SES transport for early event vs rejection,
late response/lease expiry, unknown/late event, retry/late evidence, cancellation,
and separate delivery/transient-bounce/delay facts. No provider traffic occurs.
Local standalone suites, PHP lint and shell/JavaScript/whitespace checks passed
at the initial implementation checkpoint; final checks and hosted SQL/races/review
remain pending. Docker's explicit local socket check failed once with operation
not permitted; dockerd runs but UID 1000 cannot recover it. Hosted CI supplies
real SQL acceptance; no local SQL success is claimed.

Suppression application, public HTTP adaptation, real transport, operational UI,
retention/redrive/restore and external readiness remain dependent work. All production
factories, dispatch, real intake/collection and cron gates remain disabled. No AWS
provisioning, credentials, messages, cron activation, merge or deployment.

Initial automated review identified fenced retry rows remaining first in bounded
claim scans. Local review found the same issue: fenced claimable rows now become
an audited unknown hold, removing them from subsequent scans without granting
resend or resolving acceptance. Added a later-recipient starvation regression
and an independent response-vs-retry SQL race. Added deterministic injected-clock
regressions for positive responses after the mapper timeout, v33 additive migration,
response binding/message-alias conflicts and worker reconnect-before-write/COMMIT
checks. Final hosted execution and rereview must cover these follow-ups.

Initial hosted CI 40128631024 passed standalone/syntax and existing scheduler
cases but stopped in both topologies at an inherited worker race barrier. Its
interposer recognizes backtick-quoted state columns; the new guarded UPDATE had
changed that formatting. Restored conventional quoted identifiers in guarded
updates while preserving session/CAS predicates. This run did not reach the new
SQL acceptance fixtures and is not acceptance evidence. Expected worker transaction
probe errors are also suppressed, matching other owned-transaction services.

### Continued review (2026-10-25)

Recovered the interrupted publication by checking remote head before repeating
the expected-head update; no duplicate PR or check was created. Follow-up
`f52c35fe260d15e3b3ddde797e5eb207b2da4056` matches local tree
`b1c3f6c414e107a2c248d401921b0b93f84a5e32`; clean completed
[review 6391852269](https://github.com/7mus7y-dotcom/pera-wp/pull/1480#issuecomment-6391852269)
covers that commit. The original starvation thread is fixed and resolved.
Hosted CI 40149930466 is pending at this checkpoint.

Further local review tightened fail-closed handling of contradictory authenticated
binding/recipient/scope decisions for known immutable attempts: these also fence
retry, separately from positive acceptance evidence. An indexed locking read
avoids treating an earlier SQL snapshot as absence of a newly committed conflict.
Storage contention/deadlock errors roll back before transport authorization.
Added a signed binding-conflict vs retry regression and schema readiness check
for its lookup index. This follow-up needs new hosted validation and rereview;
the earlier clean review is not claimed to cover later changes.

[CI 40149930466](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/40149930466)
completed successfully on `f52c35fe26`: single-site job 121039121753 and
multisite job 121039121904 passed. Completed logs from both were inspected:
response/event arrival orders, immutable duplicates/conflicts/aliases, binding
conflicts, additive migration, every response rollback/reconnect/caller case,
worker reconnect guards, final authorization zero-call fence, late acceptance,
unknown-only staff reconciliation, cancellation, post-acceptance delivery/bounce/
delay invariance, starvation correction and all five new independent-process
acceptance races passed. Existing worker/scheduler/recipient/browser suites also
passed. No new UI/visual acceptance or real provider acceptance is claimed.
Artifacts: single 12582198879, multi 12581909209, retained through 2026-11-08.
The subsequent contradictory-binding hold/index/regression changes above still
require their own hosted run and clean review before final acceptance.

The final conflict-hold implementation uses the existing fully scoped evidence
ledger rather than searching matching decisions by binding hash alone: identical
binding bytes must not hold another deployment. Authenticated known-attempt
mismatches append a conflict decision to evidence in the matching transaction,
without creating a provider-message association or asserting acceptance. Replay
backfills prior known-attempt conflicts under the existing authenticated matcher
boundary. This supersedes the preceding indexed-hash lookup checkpoint; no new
matching index is retained. Added an identical-binding cross-deployment regression.
Workspace became unavailable after local commit/tree preservation; subsequent
bounded edits use connector-fetched exact source and hosted validation. Local
work and all remote ancestry remain preserved. Final code/test CI and review
must cover this refinement before acceptance.

### Final runtime acceptance (2026-10-25)

[CI 40165732479](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/40165732479)
completed **successfully** on final runtime/tests
`532016a072ed16bcc439d1f3ae8f96fdbdd557ab`, tree
`6ab834cd6823d19598997f3ce2662a7d64a84618`.
Single-site job `121086323558` and multisite job `121086323556` both passed.
Completed logs from both jobs were inspected. Both passed 30 standalone suites,
223 PHP syntax checks, shell/JavaScript checks, additive migration, both response/
event arrival orders, exact/logical duplicates, response binding/message/alias
conflicts, scoped authenticated contradiction holds, identical-binding bytes in
different deployments, response insert/SELECT/COMMIT rollback and SQL replacement
before each write/capture/COMMIT, caller transaction preservation, worker reconnect
guards, zero-call final authorization fencing, late acceptance after lease expiry
and unknown hold, authorized durable-positive reconciliation, cancellation/stale
owner protections, separate receiving-server delivery and transient bounce/delay
facts, starvation correction, and all five independent-process acceptance races.
Existing atomic preparation, worker, scheduler, withdrawal/preferences and browser
fixtures passed as well. No new UI or visual acceptance is claimed.

Completed clean [review 6393938444](https://github.com/7mus7y-dotcom/pera-wp/pull/1480#issuecomment-6393938444)
covers this exact final runtime/test commit with no findings or unresolved threads.
The original starvation finding was fixed, regressed and resolved. Local review
covered the actual changes and corrected starvation and conflict-scope issues;
hosted validation covers the final connector refinement after workspace loss.
The original checkout and local commits remain preserved; remote ancestry parents
directly to verified main and includes no reconstruction scaffolding.

Runtime artifacts, retained through 2026-11-08: single-site `12587833295`,
multisite `12588521749`. Initial failed `40128631024` and superseded/cancelled
`40165558989` are not final acceptance. Prior successful `40149930466` is
superseded by the final run above. This evidence follow-up changes only this plan;
all runtime and fixture bytes remain exactly those tested and reviewed. Final
documentation-head CI and review are tracked in PR #1480.

All 18 changed files are plugin-owned. Suppression application, public HTTP
adaptation, real transport, operational UI and external activation readiness
remain dependent work. Missing events/lookups never establish non-acceptance;
post-acceptance failures never permit resend. Production factories, dispatch,
intake/collection and cron gates remain disabled. No AWS provisioning, credentials,
real messages, cron activation, merge or deployment was performed.


## Phase 5 — transactional SES suppression application (2026-10-25)

Verified #1480 merged at latest main `69094d4a34383f168dbaaf8c1f1e49100fc5cc7e`.
Exact final head `a5d43ecaaf01c810a83035e7bbc9c12cd048a2d1` passed
[CI 40166340092](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/40166340092)
and completed clean [review 6394018232](https://github.com/7mus7y-dotcom/pera-wp/pull/1480#issuecomment-6394018232).
The sole earlier review finding is resolved. No applicable AGENTS.md found in
workspace/repository/ancestors. Read current/history plan, provider specification,
receipt/normalization/matching/association/acceptance, suppression repository,
recipient identity/withdrawal and worker services. Use recorded AWS findings;
no additional provider fact or browsing is needed.

Original clean `/workspace/pera-wp` remains preserved. Shell fetch failed once
because configured proxy port 8080 was unreachable; connector verified main and
supplied all eighteen changed files to isolated `/workspace/pera-suppression`.
Local reconstruction is scaffolding only; publication must parent to verified
main and exclude that commit. Local Docker socket access is denied, dockerd is
running and UID is 1000; no local SQL pass is claimed. Hosted single/multisite CI
must supply actual SQL/race acceptance before this PR is complete.

Schema 35 adds immutable logical application evidence and per-receipt application
references. The new internal/unbootstrapped service accepts durable receipt IDs
only. Authentication/normalization policy and fact hashes are rechecked, latest
matched decisions and exact single-recipient immutable bindings are required,
and the unique message association plus acceptance/conflict ledger are reread
under attempt locks. Contradictions remain quarantined; existing acceptance and
retry fences are unchanged. No staff identity is fabricated.

All applicable mailboxes are locked in sorted order through the authoritative
repository mutex before event/attempt locks. Its optional internal insert writer
preserves SQL-session fencing on the mutex's inserting path. Flags, actor-0
suppression audit, server-controlled scope/event provenance, immutable application
and receipt reference commit atomically. Logical event locks and unique indexes
serialize replay; duplicates retain first evidence and do not repeat audit or
version changes. Distinct valid events retain their own evidence and cumulative
reasons, even when a reason was already present. No flag can be cleared.

Permanent bounce adds hard_bounce. Complaint candidates may add complaint only
under an exact single-recipient historical binding. Recognized feedback meanings
(abuse, auth-failure, fraud, other, virus or absent/unspecified) retain Complaint
meaning; not-spam and unknown subtypes remain quarantined without adding or
clearing denial. Transient/Undetermined bounce, Send, Delivery and DeliveryDelay
remain observations, with no permanent denial or resend authority. The proposed
transient threshold remains inactive.

Denial binds to the historical mailbox, independently of current subscriber ID,
identity epoch or existence. Deleted identities cannot cause subscriber creation;
replacement addresses cannot inherit an old address's event. Late valid feedback
remains applicable after job submission completion/cancellation without changing
consent, job/delivery state, unknown history or submission retry authority.

Focused standalone policy and real SQL fixtures cover identical/logical replay,
cumulative bounce/complaint, feedback semantics, changed/deleted/reused IDs,
wrong scope, unnormalized/unmatched/missing/conflicting associations, rollback at
every write/read/COMMIT, actual SQL-session replacement and caller transactions.
Prepared-worker fixtures cover late completed/cancelled jobs. Thirteen independent-
process race scenarios include duplicate receipts, logical duplicates, both orders
of complaint/bounce/withdrawal, deletion/mailbox replacement and three concurrent owners. Protected snapshots
prove no consent grant, clearing, subscriber creation or retry-state regression.
Validation and final-head review are pending initial hosted publication.

All changes remain plugin-owned. No public HTTP adaptation/activation, real
transport, AWS provisioning, credentials, messages, cron activation or operational
UI. Production factories, dispatch, intake/collection and cron gates stay disabled.
Retention/redrive/restore/readiness and later endpoint adaptation remain dependent
work. No merge or deployment.


### Initial publication and review follow-up

Non-draft [PR #1481](https://github.com/7mus7y-dotcom/pera-wp/pull/1481)
opened at `410dcd7e93ad99607f38f3da8f583d2a6408e9c3`, tree
`14bc923ca4eef526d9a44e55d3cee96c22c7d5eb`, directly parented to verified main.
Reconstructed baseline tree `08086b815ed5e4d1b916901a4f9f8c8ce7727421`
exactly matches main. All 31 standalone suites and 228 PHP syntax checks,
shell/JavaScript/whitespace checks pass locally. Initial automated review completed
cleanly on this head; hosted CI 40174499763 remains in progress.
Local diff review added both orders of deletion/mailbox replacement races,
server-controlled audit provenance checks, preservation of existing admin/withdrawal
flags and absent-mutex/deleted-subscriber coverage. These follow-ups require their
own hosted CI and final-head review; the initial clean review does not cover them.


Initial hosted CI 40174499763 failed in both topologies after the new application,
identity, quarantine and all rollback/reconnection cases passed. The late-event
fixture then tried to prepare the shared worker mailbox again after correctly
suppressing it; no second immutable attempt existed. This is a fixture isolation
failure, not a reason to clear suppression. Late-event acceptance now runs last,
prepares all exact attempts before feedback, uses unique mocked provider message
IDs and checks completion then cancellation on the retained historical attempt.
Permanent deny knowledge remains intact. The failed run did not reach races and
is not final acceptance. The additional late fixture raises PHP syntax coverage
to 229 files; final hosted execution and clean review remain required.


CI 40175092302 again passed both topologies through application rollback/session
replacement and duplicate/logical/bounce-complaint/complaint-bounce/bounce-withdraw
races, then exposed a premature fixture barrier in withdraw-bounce. Token withdrawal
first commits a read-only locator transaction; the race barrier was waking its
competitor at that earlier COMMIT, before the mailbox mutex was held. The interposer
now arms only on the actual mailbox insertion and pauses its first subsequent
COMMIT. This keeps the independent blocking assertion meaningful in both lock
orders; no runtime relaxation or timing-threshold reduction is made. The same
head completed clean review 6395191719 before this fixture-only correction.
A new full run and exact-head review are required.

### Final runtime/fixture acceptance (2026-10-25)

Final runtime/test head `8fce3751e29c490260dbcd62ec7f4c7c1901918b`, tree
`b883a04d498f482c1339bc31a5c7a068a2f454e6`, exactly matches the local tree.
[CI 40175457519](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/40175457519)
**passed** single-site job `121115027716` and multisite job `121115027534`.
Both completed logs were inspected. Both passed 31 standalone suites, all 229 PHP
syntax checks, shell/JavaScript checks, additive v34-to-v35/idempotent migration
and required application uniqueness, server-controlled system actor/provenance,
identical/logical replay with first-evidence retention, cumulative existing
admin/withdrawal/bounce/complaint flags, not-spam/unknown/transient/undetermined/
delivery/delay/Send non-application, changed/deleted/reused subscriber IDs and
absent-mutex retention without subscriber recreation or replacement suppression.
Wrong deployment/configuration/blog, unnormalized/unmatched/missing bindings,
contradictory tags/full destination sets/provider messages and later acceptance
conflicts stayed quarantined. Each mailbox insert, flag update, audit insert,
application insert, receipt-reference insert, locked attempt read and COMMIT
failure rolled back; real SQL-session replacement at every boundary and initial
connection capture failed closed. Caller and failed-probe transactions survived.

All thirteen independent-process application races passed with distinct SQL
connections and actual blocking: duplicate receipt, duplicate logical event,
bounce/complaint in both orders, bounce/withdrawal in both orders, complaint/
withdrawal in both orders, three concurrent bounce/complaint/withdrawal owners,
and deletion/mailbox replacement in both orders. No valid deny reason was lost.
Exact subscriber/consent, identity/token, immutable attempt/output and worker/job
snapshots proved no consent grant, clearing, subscriber creation or retry-state
regression. Actual prepared-job completion followed by cancellation accepted late
bounce and complaint feedback, retained both reasons and made zero new transport
calls. Existing acceptance/worker/scheduler/recipient and desktop/mobile browser
fixtures also passed. No new UI/visual or real provider acceptance is claimed.

Completed clean [review 6395336697](https://github.com/7mus7y-dotcom/pera-wp/pull/1481#issuecomment-6395336697)
covers this exact runtime/test head, with no findings or unresolved threads.
Local review covered all actual schema/service/repository/fixture changes and
addressed test isolation and authoritative-lock synchronization; hosted acceptance
covers those corrections. Earlier failed runs 40174499763 and 40175092302 are
not final acceptance. Evidence artifacts are retained through 2026-11-08:
single-site `12591589672`, multisite `12591519951`.

This final evidence follow-up changes only the living plan. Every runtime and
fixture byte remains exactly the tested/reviewed version above. Exact final-head
documentation CI and review are tracked in PR #1481. All fourteen changed files
are plugin-owned, publication parents to verified main, and no reconstruction
scaffolding is published. Original checkout and local commits remain preserved.

This completes the bounded internal suppression-application implementation;
Phase 5 remains in progress. Public HTTP adaptation, real transport, retention/
redrive/restore, operational readiness and any activation remain dependent work.
Missing provider facts/events never prove non-acceptance. No suppression clearing,
consent grant, resend authorization or subscriber creation was added. Production
factories, dispatch, intake/collection and cron gates remain disabled. No AWS
provisioning, credentials, real messages, operational UI, merge or deployment.

## Phase 5 — guarded SNS HTTP connection (2026-10-25)

Current bounded task: plugin-owned, default-disabled SNS POST adapter connecting
existing authentication, durable receipts, normalization, matching and suppression.
#1481 merged at latest main `e65b22e626081ebc47149cffb9ff50740cdd1f02`.
Verified exact final `071a7a70d5547f1e77dda2ecc8eecfb796df9942` passed
[CI 40176310060](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/40176310060)
and completed [clean review](https://github.com/7mus7y-dotcom/pera-wp/pull/1481#issuecomment-6395357423),
with no unresolved findings. No applicable AGENTS.md found. Read the living plan,
provider specification and all existing event services and transaction contracts.

Preserved clean `/workspace/pera-wp` at `69094d4a`. Shell networking was blocked;
no further shell network attempts were made after recovery. Connector supplied
all fourteen changed main files to isolated `/workspace/pera-sns-http`; resulting
tree `d18807c2be549a765e1850af3344721e0efcfe8d` exactly matches main. Local
reconstruction commit is scaffolding only; publication parents directly to main.

Explicit deployment-only configuration pins blog/deployment/account/region/topic
and SES configuration set/version. Disabled/misconfigured endpoint returns 404.
No request or database option enables production. Bounded stream reads and existing
JSON depth limits, documented SNS content types, early method rejection, strict
certificate/signature verification and no subscription confirmation are preserved.
Existing receipt/normalization/matching/application commits remain separate. A
replayed receipt always resumes downstream work; storage/certificate/downstream
failure returns retryable 503, never receipt-only completion. 202 denotes durable
acceptance/quarantine only; eligible suppression must commit before it returns.
No event clears denial, grants consent or authorizes sending. Generic responses
carry no-store and privacy headers and bypass themes/analytics; no sensitive
request data or exception details are logged.

Added standalone configuration/preflight/authentication tests, actual HTTP and
SQL fixtures in both topologies, staged receipt/normalization/matching/application/
application-COMMIT faults and retry recovery, replay/no-op and signed ID collision,
wrong scope/forgery, bounded malformed/oversize input, documented media types,
durable unsupported/unmatched/stale quarantine and independent-process concurrent
pipeline acceptance with one committed suppression audit. Test-only certificate
retrieval injection retains the external-network blocker. Required standalone,
PHP/shell/JavaScript syntax and whitespace checks pass locally. Hosted SQL/HTTP/
races and final-change review are pending publication; no local SQL pass claimed.

Dependent operational work is recorded in the provider specification: subscription
provisioning/confirmation and delivery policy, bounded audited redrive, DLQ,
monitoring, retention/restore/privacy, production TLS/host/CDN/WAF limits and
log/APM/trace protections. No AWS resources, live configuration, cron, transport,
messages, credentials, merge or deployment. Sending blocks and all other disabled
factories/intake/collection remain intact. Phase 5 remains in progress.

### Guarded HTTP runtime acceptance

Non-draft [PR #1482](https://github.com/7mus7y-dotcom/pera-wp/pull/1482)
is published directly on verified main with runtime/test head
`80bc4f6ad7c4752092efe79e694b6a6005735b5e`, tree
`7ef5859850366d55436be8fbef1cb2e48563c6c1`, exactly matching local.
[CI 40203953097](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/40203953097)
passed single-site job `121200798572` and multisite job `121200798266`.
Both completed logs were inspected. Both passed 32 standalone suites, 233 PHP
syntax checks, shell/JavaScript checks, actual HTTP disabled/misconfigured scope,
all unsupported methods/media types, oversize/depth/malformed/forged/wrong-topic
rejections, no subscription action and generic no-store responses. Injected
certificate dependency failure returned 503 with Retry-After and no receipt.
Receipt, normalization, matching, application insert and application-COMMIT
failures each returned retryable 503; identical replay resumed downstream work,
committed suppression before 202, and further replay was a complete durable
no-op. Signed same-ID conflict returned 409. Unsupported/unmatched/stale events
were durably quarantined before 202. Independent SQL connections serialized
concurrent full HTTP pipelines, retained one logical suppression audit and both
returned durable acceptance. Existing late completed/cancelled feedback,
cumulative reasons, acceptance/conflict/retry fences, worker, scheduler,
recipient and desktop/mobile browser fixtures also passed.

Completed clean [review](https://github.com/7mus7y-dotcom/pera-wp/pull/1482#issuecomment-6399089333)
covers this exact runtime/test head with no findings or unresolved threads.
Local review inspected the actual bootstrap/adapter/fixture/documentation diff;
no runtime findings remain. This acceptance update changes only the living plan;
all runtime/test bytes remain exactly those tested and reviewed above. Final
exact-head documentation CI and review are tracked in #1482 before completion.
Artifacts retained through 2026-11-08: single-site `12603680649`, multisite
`12603391125`. No new UI/visual or real AWS/production acceptance is claimed.
All nine changed files are plugin-owned. Original checkout/local history remain
preserved; no reconstruction scaffolding is published. Production endpoint stays
disabled and all sending/intake/collection/cron gates remain intact. Operational
dependencies remain open. No provisioning, live activation, real messages,
credentials, merge or deployment was performed.


## Phase 5 — bounded SES redrive and local event health (2026-10-25)

#1482 merged as `101445e9f90a9944e6bb50f91f05aaefe9326b58`. Its final head
`56dc2a64fc03666b915d933e4d747336afed6671` passed CI 40204897994 and completed
clean review 6399279483. Latest main was obtained through the GitHub connector;
the complete non-truncated manifest and every blob were verified, and isolated
baseline tree `2a0653e3b5668240284340a7fa400e3b1babc824` matches GitHub exactly.
Original checkout/history remain untouched. No applicable AGENTS.md exists.

Adds additive schema v36 durable receipt progress and scope-specific processing
lock. HTTP records progress through the same pipeline; explicit operator redrive
resumes interrupted stages and only reprocesses missing bindings under the existing
five-pass logical-event fence. Original authentication, payload/certificate hashes,
freshness quarantine, normalization policy, permanent conflicts and manual-review
holds remain immutable. Session-fenced progress cannot claim success after reconnect.
Batches are 1–25 receipts, at most 50 facts each, with a 25-second work-start deadline.
No automatic scheduling or quarantine release exists.

Guarded `event-health` and `event-redrive` use existing real-actor report authority;
redrive also requires campaign management, explicit confirmation and bounds.
Separate absent-by-default deployment pins allow local operations with HTTP disabled.
Read-only health reports local pending/failed/unmatched/conflicting/quarantined
work, actionable age and last successful processing/redrive. It explicitly declines
provider health or delivery completeness. Output is payload/mailbox/token/secret free.
Operations runbook documents exact commands, state meanings, stop/recovery steps,
limits and external dependencies.

Focused standalone CLI/config regressions and real SQL fixtures cover batch limits,
interrupted resume, independent duplicate workers, missing-binding recovery,
permanent conflicts, retry exhaustion, rollback/session replacement and read-only
inspection. Existing sending, consent, acceptance, unknown-outcome, cancellation,
recipient withdrawal and suppression fixtures remain required and unchanged.
Local Docker socket access is unavailable (`connect: operation not permitted`);
real single/multisite SQL/concurrency execution is delegated to repository CI.
Hosted acceptance and exact-head review are tracked in [PR #1483](https://github.com/7mus7y-dotcom/pera-wp/pull/1483).
Completion requires passing final-head CI and a clean review covering all final
changes; the PR description records the final commit and acceptance links.

All changes remain plugin-owned. No endpoint activation, AWS provisioning,
credentials, transport/messages, cron activation, merge or deployment. External
SNS/DLQ provisioning, host monitoring/log protection and retention/restore decisions
remain explicit dependencies. Phase 5 remains in progress; all disabled gates hold.

Local review follow-up separates synthetic race mailboxes and makes general health
emit actionable fixed diagnostics for local processing failures, manual-review
conflicts and mismatched provider policy pins. Read-only policy conflict reporting
has a real SQL regression. Updated hosted CI and exact-head review are required.

Further local review rejects unrecognized stored progress states on HTTP-style
replay rather than overwriting them as pending. Read-only health counts them as
manual-review holds; a real SQL regression proves replay/redrive preserve the hold.


### Publication and acceptance tracking

Non-draft [PR #1483](https://github.com/7mus7y-dotcom/pera-wp/pull/1483) contains
15 plugin-owned changed files. Local acceptance is 33 standalone suites, 237 PHP
syntax checks and shell/JavaScript/whitespace checks. Published trees match the
isolated local trees; published history parents directly to verified main without
publishing reconstruction scaffolding. Original work remains preserved.

Initial CI 40213392945 passed the new pipeline, rollback/reconnection, bounds,
missing-binding recovery and all prior protection fixtures on both topologies,
then exposed shared synthetic mailboxes between race scenarios. The fixtures now
use distinct mailboxes. Subsequent local review added actionable health/policy
conflict reporting and fail-closed handling of unknown/manual progress states.
Earlier clean reviews apply only to their reviewed heads. Final-head CI and the
exact-head review request/response are linked in the PR; no earlier passing check
or review substitutes for acceptance of later changes. No merge or deployment is
authorized by these checks. External provisioning/monitoring/retention/restore
and all activation decisions remain open dependencies.

## Phase 5 — immutable unsubscribe envelope prerequisite (2026-10-25)

The requested concrete SES v2 transport is split at a necessary protocol boundary.
This PR completes the coherent prerequisite: explicit V2 recipient output and
submission envelopes carrying the exact expansion-issued unsubscribe URL through
atomic encrypted preparation, exact-byte replay and SES mapper headers. Existing
body-only V1 artifacts cannot acquire headers without changing their immutable
hashes. No such migration or token regeneration is performed. The concrete SDK
transport and its mocked HTTP/signature acceptance remain **not implemented**.
Phase 5 remains **in progress**; mocks do not establish production readiness.

Baseline verified through GitHub connector: #1483 merged to latest main
`8d1f53540d8562f24ff37e1c528fc115a2b29061`. Final head
`989402dbdffe02901c1ec98282ee2f96ae96a472` passed CI 40214563795 and clean
final-head review receipt 6400582589. No applicable AGENTS.md found. Read the
living plan/history, provider specification, recipient token/expansion/persistence,
worker preparation, SES mapper and response-correlation contracts. Original clean
checkout `/workspace/pera-wp` at `101445e9` is preserved. Isolated worktree
`/workspace/pera-ses` baseline tree `f95786f3bce0712d4581ee52a85664a2acb966f3`
matches GitHub main. All 15 baseline blobs were verified against GitHub SHA-1s.
Shell fetch failed once (unreachable proxy); connector publication parents directly
to main and excludes local reconstruction scaffolding.

V2 URL bytes are part of the envelope/output hashes and encrypted payload, excluded
from attempt SQL metadata and redacted debug/exception output. Atomic preparation
compares both body and header authority against the same expansion result. Replay
requires the exact supported envelope version and immutable metadata and uses
stored ciphertext without live rendering, origin, signing keys or new issuance.
Historical V1 shape/hash/recovery remains supported; unsupported versions reject.
The mapper emits both RFC 8058 headers, with no managed list option or body edits.
New worker preparations request V2; historical V1 conflict fails closed.

Dependency inspection: existing Guzzle/PSR HTTP dependencies live inside webpush;
no AWS SDK/signer exists. No dependency is added here. The next PR must select,
pin/lock and package an established SDK/signer under plugin ownership, validate
actual signing through HTTP mocks and document platform/autoload/distribution.
It must implement complete provider decoding and total-budget/no-retry/TLS/fixed
scope/redaction behavior and test rejection/throttling pairs, ambiguity and
response/event races. Preserve immutable response correlation, late-return fences
and durable acceptance/conflicts; absent events never establish non-acceptance.
Concrete transport construction must remain inaccessible to production dispatch.

Validation before publication: all 34 standalone suites and 238 plugin PHP syntax
checks passed with PHP 8.4; shell/JavaScript syntax and whitespace passed. New
pure regressions cover exact prepared URL/header bytes, token purpose/version,
URL injection rejection, hash and recipient binding, redaction, defensive copies,
legacy shape, unknown versions and one mock mapper call. New real-SQL fixtures
in both topology paths cover V2 atomic preparation, encrypted exact-byte replay
without issuance inputs, bearer-free metadata and conflicting version/header
rejection. Existing worker races now prepare V2; existing late-response/event and
acceptance/conflict/unknown protections remain required. Local Docker socket check
failed with operation not permitted; UID 1000 cannot recover the running daemon.
No local SQL success claimed. Hosted both-topology CI and final review are pending
at initial publication and must be verified on the final head.

Readiness remains blocked on concrete transport acceptance; suppression
synchronization; account-wide rate limits/circuit breakers; deployment identity
and IAM/account/region pins; endpoint/DLQ monitoring; key management, retention
and restore decisions; and separately authorized controlled inbox acceptance
including signed unsubscribe headers, TLS, DKIM/SPF/DMARC and real one-click POST.
The unconditional sending block, disabled intake/public collection/cron and
plugin ownership remain unchanged. No AWS requests, account provisioning, DNS,
real credentials, messages, merge or deployment are authorized or performed.

Initial non-draft publication: [#1484](https://github.com/7mus7y-dotcom/pera-wp/pull/1484),
initial head `1899431d2d257da0ceeaa5a680269d00e193d462`, tree
`e0236e6a5b5e522e16213faa578b89687ce9c934` matching local. Final diff review found
and closed constructor reentry in the existing submission value class: neither
V1 nor V2 fields/correlation hashes may be replaced after construction. A focused
regression verifies rejection and unchanged hash. New final-head CI and clean
review remain required for this correction; initial-head results cannot cover it.

Automated review of the initial head found the event matcher still required V1
attempt metadata. Corrected it to accept strictly consistent integer V1/V2 pairs
and reject unknown/mixed versions. Pure regressions cover V2 matching, mismatched
versions and V1-hash/V2-envelope conflict. Shared real-SQL matching fixtures now
prepare V2, so matching, suppression, response/event conflict and concurrency
paths exercise the new protocol; an explicit SQL V1 case preserves compatibility.
The finding requires new final-head CI and rereview; prior heads do not cover it.

### Runtime acceptance and final evidence follow-up

Runtime/test head `69e499222d8a055df6945a96858499ebd7e2f3d5`, tree
`3080b4d06108235fea96268e3f28f4b2aebcb4f4`, passed
[CI 40221253927](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/40221253927):
single-site job `121253177386` and multisite job `121253177648` both succeeded.
Both completed logs inspected: V2 exact body/header URL, encrypted replay without
issuance, bearer-free metadata, mock handoff, V2 durable event matching, explicit
historical V1 matching, matching/suppression/response-event races and existing
worker/late-return/unknown/cancellation fences passed. No PHP warning, fatal error
or failed assertion appeared. All 34 standalone suites and 238 PHP lint checks
also passed locally. Local Docker remains unavailable; hosted SQL results supply
the actual single/multisite acceptance evidence.

[Clean runtime review 6401487661](https://github.com/7mus7y-dotcom/pera-wp/pull/1484#issuecomment-6401487661)
explicitly covers `69e499222d`; completed summary reports no findings/unresolved
findings. The earlier matcher finding is fixed with regressions and its thread
resolved. This evidence-only follow-up changes no runtime/test bytes. Final-head
CI and review of this documentation follow-up remain required at publication;
PR acceptance records the actual final result. The SDK/HTTP transport remains
unimplemented dependent work; no production readiness, merge or deployment claim.

## Phase 5 — concrete SES v2 signed transport (2026-10-25)

Phase 5 remains **in progress**. Envelope prerequisite #1484 is merged as
`cc11b821463720c81f4940a841475a7603c5b6c9`. Connector verified its final head
`8912b36514684714c2b332e1c25ffcb1cb675abd` passed workflow `40222735280` and
[clean final-head review](https://github.com/7mus7y-dotcom/pera-wp/pull/1484#issuecomment-6401690713)
with no findings/unresolved findings. Latest main was that merge. No applicable
AGENTS.md exists in the repository or searched workspace/ancestors. The original
clean `/workspace/pera-wp` remains preserved; isolated worktree is
`/workspace/pera-ses-transport`. Shell Git failed once at the unavailable proxy;
connector-fetched main files produced exact tree
`e6d5d1678222657573654b75b317ae142872ec90`. Reconstruction scaffolding must not
appear in published history, which parents directly to verified main.

Implemented the existing transport boundary with a pinned AWS SDK SigV4 signer
subset and one native cURL execution. Plugin-local namespace-isolated dependencies,
licenses, source/packaged digest lock and offline reproduction script are shipped.
The dependency choice and PHP >=8.2/ext-curl/async-DNS platform are documented in
[the package README](../vendor-ses/README.md) and provider specification. No full
SDK credential/discovery/retry stack is loaded. Explicit account-attested identity,
fixed account/region/endpoint, TLS verification, no redirects/proxy credentials,
zero retries, 3-second connect cap and preparation-inclusive 20-second budget
are enforced. Exact V2 stored body/subject/sender/tags/unsubscribe authority are
mapped without token issuance. Complete decoding preserves unknowns for malformed,
truncated, timeout, connection-loss and ambiguous/error outcomes. Documented SES
status/code pairs flow through the unchanged mapper outcome contract.

Local acceptance: **35 standalone suites**, **273 PHP syntax checks**, shell and
JavaScript syntax and whitespace checks pass. New HTTP mocks verify actual SDK
signature scope/payload bytes, session/static identities, exact headers/content,
security/timeouts/no-retry policy, documented error variants, ambiguity, late
outcomes, redaction and historical V1 denial. Existing worker and event correlation
suites are reused unchanged. The lock verifier and offline source regeneration
both pass (37 selected upstream files including licenses). No real HTTP executed.
Docker is installed/running but local socket access is sandbox-blocked; hosted
single/multisite CI is required for real SQL/race acceptance. Final-head CI and
review remain required; the PR acceptance record will identify the reviewed SHA.

No scope split is needed. Production construction remains absent from bootstrap,
provider factory and dispatch. All production/intake/public collection/cron gates
remain disabled. No AWS requests, real credentials, emails, account/DNS changes,
merge or deployment. Deployment identity attestation/refresh, host timing, IAM,
received DKIM/MIME/inbox acceptance, suppression sync, rate/circuit breakers,
event/DLQ monitoring, keys/retention/restore and controlled activation remain open.

### Packaging review follow-up (2026-10-26)

Review of runtime head `5a3f6f304423a3d385f49a3257cb6a9c6a2d3e44`
found that two upstream root `namespace Aws;` declarations escaped prefixing.
Corrected the shared packaging transformation to handle root declarations as
well as qualified namespace references; regenerated the monitoring trait/interface
and their packaged hash locks. New PHP regressions check every packaged namespace
and load the checksum exception's private monitoring dependencies, asserting that
no public AWS trait/interface is published. The packager now self-checks root and
qualified transformations, and the existing CI regression step runs its hash/
isolation verifier. This extends verification without changing production wiring.

The execution environment became unavailable after initial publication. This
follow-up is committed through the connector; new hosted CI supplies execution
validation of the changed packager/exception regressions. Earlier local test and
offline-regeneration evidence applies to the initial runtime head only. Final-head
both-topology CI and clean review are required before reporting acceptance.

### Verified runtime evidence and final documentation follow-up (2026-10-26)

Initial transport head `5a3f6f304423a3d385f49a3257cb6a9c6a2d3e44` passed
[CI 40271545277](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/40271545277):
single-site job `121405237789` and multisite job `121405238000` succeeded.
Both completed logs were inspected: SDK signing/HTTP mocks, V2 immutable replay/
matching, suppression and response/event races, worker late/unknown/cancellation
fences and desktop/mobile browser acceptance passed. No PHP warning/fatal/parse
error, failed assertion or Actions error was found. This initial CI did not by
itself close the subsequently identified namespace-isolation finding.

Fix head `3f064e1ad81e321d10ec03d9d8c62fb850782a95` adds the corrected
root-namespace transformation, regenerated private monitoring dependencies/hash
locks, all-package namespace checks and actual checksum-exception dependency
loading. Both jobs in CI `40273089126` passed the regression/syntax step,
including the newly required Python packaging hash/isolation self-checks; full
SQL/browser completion was still pending when this documentation was written.
[Clean rereview 6408804949](https://github.com/7mus7y-dotcom/pera-wp/pull/1485#issuecomment-6408804949)
explicitly covers `3f064e1ad8`; the completed review summary has no findings or
unresolved findings, and the earlier thread is resolved.

This evidence-only follow-up changes no runtime/test/packaging/workflow bytes.
Final-head single/multisite CI and clean review of this documentation are still
required at publication; the PR acceptance record must identify the final SHA
and completed results. All production construction/dispatch/intake/collection/
cron gates remain disabled. Mocks and disposable SQL tests do not establish
real-host, deployment identity/IAM, AWS account, DKIM or inbox readiness.

## Phase 5 — bounded SES suppression sync (2026-10-26)

Baseline #1485 is merged (`7c1d13c9fad2442126183bbbb2a5b7efa2db1a30`). Its final
head `664e7614f2c8ef9bc9e26fde5c32d101d94dee45` passed CI 40273525201 and clean
review receipt 6408836268; the completed review summary reports no findings or
unresolved findings at that SHA. Latest main is `a65e071ef26e14de805df87b60c210d983d45311`.
No applicable AGENTS.md found. Read the plan, provider specification, transport,
suppression, persistence, schema and operations services. Original checkout is
preserved; isolated worktree `/workspace/pera-ses-sync` reconstructs all 49 changed
baseline files through the connector, with every Git blob hash verified. Shell
Git remains blocked by the proxy. Publication parents directly to verified main;
local reconstruction scaffolding must not be published.

Adds a trusted injected SDK-signed account-level read adapter, additive schema
v37, bounded explicit sync/health commands and durable page transactions. Scope
pins bind account, region, deployment and target blog. Provider spelling is
retained separately from local normalization. Import only adds BOUNCE/COMPLAINT
knowledge with immutable provenance, without subscriber/CRM/profile/consent
creation. Duplicate pages/facts are idempotent. Interrupted runs resume committed
cursors; expired/stale/cyclic tokens need explicit restart. Unsupported reasons
and scope mismatch fail closed. Terminal complete time only advances with the
whole terminal page transaction. SQL guards and session-scoped overlap locks
preserve reconnection, rollback and concurrent-run protections.

Official current AWS list/get/account-suppression documentation was accessible
through the web connector and verified; exact links/limitations and separate
bounded read retry policy are in the provider specification/runbook. SendEmail's
zero-retry policy and isolated dependency packaging are unchanged. Production sync
factory unconditionally returns null. The reader requires an injected executor;
there are no native requests, cron hooks, credentials, provider writes, sending,
endpoint/intake/public collection activation, provisioning, merge or deployment.

Added focused policy/signature/retry/CLI tests and real single-site/multisite SQL
fixtures for pagination, duplicate pages, interruption/resume, failure/false-complete
prevention, wrong scope, historical denial retention, rollback/reconnection at each
write boundary and independent-process overlap/death races. Local PHP and Docker
executables are absent, so no local PHP/SQL execution is claimed. Local dependency
hash verification, shell syntax and whitespace pass. Hosted CI and clean review
covering the final changes remain required before acceptance; evidence is recorded
in the PR. Phase 5 remains in progress and external identity/IAM, production HTTP,
rate/circuit, monitoring, key/retention/restore and inbox acceptance remain open.

### Runtime validation and checkpoint review correction

Non-draft [PR #1487](https://github.com/7mus7y-dotcom/pera-wp/pull/1487) is published.
Runtime/test head `f661a9cdec85c6c3c3c82d1b583c9468bcc6e180` passed both-topology
CI 40288157569 (multi job 121456746561, single job 121456746727), including all
36 standalone suites, PHP syntax, real SQL/races and browser fixtures. The initial
schema-version and CLI-count assertions were updated. Initial review's capture
reconnect fixture finding was corrected to inject after lock ownership.

Review of that passing runtime found that post-terminal diagnostic errors or a
rolled-back successor initialization could downgrade the prior completed run.
Failure persistence now requires the invocation's durably initialized run ID and
a nonterminal stored state. Cursor-age/manual holds are checked after durable
initialization. New real SQL regressions inject post-terminal session/health errors
and successor UPDATE/COMMIT failures, preserve completion and verify the next scan
starts cleanly. Historical suppression flags and provenance are retained verbatim;
duplicates reassert deny flags without replacing evidence. This correction needs
new hosted CI and clean review covering the final changes. Earlier passing CI is
not acceptance of the later correction. Final evidence is tracked in the PR.

## Phase 5 — durable sending budget prerequisite (2026-10-26)

This bounded PR completes the durable ledger/circuit service prerequisite only.
The complete requested worker/transport integration is explicitly NOT complete.
Phase 5 remains in progress. Final worker suppression-sync preflight, reservation
and signal ordering, durable outage handling and integrated transport races are
recorded dependent work, not production readiness. See provider specification and
operations runbook for exact ownership, accounting, clock and restore contracts.

Verified #1487 merged at latest main `15221709f9368b832db2415d8e947ab92b785133`.
Its final head `745990b05cf3f9162360ba5e83e5259465855019` passed CI 40289263054;
clean final review receipt 6411120613 and completed summary cover that head with
no findings/unresolved findings. Earlier two finding threads are resolved. No
applicable AGENTS.md found under workspace/ancestors. Read living plan/history,
provider specification, worker/preparation, transport, suppression-sync and health/
CLI services. Original clean checkout is preserved. Isolated `/workspace/pera-budget`
reconstructs latest main through 24 connector-fetched/hash-verified blobs. Shell
fetch failed once at unreachable proxy and was not retried. Publication parents
directly to verified main; local reconstruction scaffolding is excluded.

Schema v38 adds account/region ownership, quota/clock/breaker state, immutable
reservation identity and actor-coded events. Atomic reservations share both rolling
limits across campaigns/processes on one designated target-blog ledger. Missing/
invalid/stale quotas, wrong owners/targets, clock rollback and breakers deny.
Pending reservations never age free; completed usage is conservatively retained
from completion. No refund, reset, recipient change or automatic resend exists.
Operator holds and timed throttling are distinct; recovery is version-fenced,
authorized, audited and requires quota/readiness. Signal transactions are independent
of recipient persistence. Production service factory is unconditionally null.

Focused policy/CLI regressions and real single-site/multisite SQL fixtures cover
rolling boundaries, indefinitely pending/crashed usage, duplicates/stale owners,
readiness and quota denials, ownership, durable breaker persistence, safe recovery,
rollback/reconnect mutations and independent-process overlap/death. Local PHP is
absent; Docker daemon is running but socket access is sandbox-blocked under UID
1000. No local PHP/SQL success is claimed. Hosted final-head CI and clean review
are required; final evidence is recorded in the PR acceptance record.

No real AWS requests, credentials, messages, endpoint/sync/intake/collection
activation, cron installation, UI, merge or deployment. All code is plugin-owned.
Unconditional production dispatch blocking and all disabled factories/gates stay.
Account quotas, shared host ownership, external senders, production clock/restore
recovery and production acceptance remain explicitly unverified. Local accounting
alone cannot provide account-wide protection across independent installations.

### Budget acceptance corrections

Initial CI 40294787657 passed both-topology regressions/PHP syntax and prior SQL
protections, then failed the new budget's final savepoint check. Release the
guard-initialization savepoint before creating the final validation savepoint.
Quota input now rejects PHP coercion; health exposes only allowlisted usage and
active cooldown. New SQL tests add daily-cap concurrency, real-actor audit and
silent rollback injection at actual mutations. Operator recovery also preserves
a newer throttle cooldown. Budget tests run after management setup for early
feedback without skipping existing protection/browser suites. These changes need
new final-head CI and clean review; no earlier result substitutes for acceptance.

The split follows transaction ownership: durable budget reservations/signals own
their commits, while worker final authorization owns separate recipient/job locks.
A later orchestration PR must compose those boundaries with final suppression
freshness and acceptance/conflict fences, never nest the transactions or treat
a reservation as send/retry authority. Durable failed-signal recovery/outage
coordination remains required before this service can protect real dispatch.

## Phase 5 — durable submission-latch prerequisite (2026-10-26)

Phase 5 remains **in progress**. This bounded PR completes a prerequisite for
worker/transport integration: durable account-wide exclusion while the outcome
of a submission has not been durably settled. It does **not** wire the worker,
transport or suppression-sync preflight. The split is necessary because a signal
write can fail after an external request: a process-local stop cannot prevent
other workers/campaigns from sending. The pre-existing budget and worker own
separate transactions; nesting them would break their commit/lease guarantees.

Connector verified #1490 merged to latest main
`6ea1dd4e70f9f6dfb4096657b263626b85ed19fb`, tree
`081f40aa847f4cacb70ab04c925988c780a3b432`. Its final head
`924302925ceac792725013f52e7d30df081c80b1` passed both-topology
CI 40319230596 and clean final-head review receipt 6415518868; completed review
summary has no findings/unresolved findings and no review threads remain.
No applicable AGENTS.md exists in the workspace/repository or searched ancestors.
Read this plan, provider specification and budget/worker/transport/sync services.
Original clean checkout is preserved. Isolated `/workspace/pera-preflight` has
16 connector-fetched baseline blobs verified against Git hashes; reconstructed
tree exactly matches main. Shell fetch failed once at the unavailable proxy and
was not retried. Published history must parent directly to main, excluding local
reconstruction scaffolding.

Schema v39 adds account pending attempt/owner and reservation outcome/evidence
fields. `beginSubmission` rechecks quota, clock, readiness, original reservation
owner and breaker under the account lock, then commits a single-use account latch.
All subsequent reserves/begins and operator recovery deny while it is present.
`settleSubmission` atomically persists an immutable outcome/digest, signal/audit,
completion charge and latch release. Failed mutation/audit/COMMIT, rollback,
reconnection or death retains the pre-existing latch. No transaction spans a
network call. Accepted, denied, rejected and ambiguous attempts retain charges;
there is no refund API. Generic finish cannot age usage while a latch is pending.
Exact evidence replay cannot clear a newer latch; conflicting evidence denies.

Lost outcome evidence may only be conservatively recorded as `submission_unknown`,
which commits an operator hold before releasing the latch. This is durable
recovery evidence of uncertainty, **not** proof of non-acceptance or permission
to resend. The caller must already have independently classified/authenticated
any positive/provider outcome; this accounting API authenticates no provider
response. It never changes recipient state, exact output bytes, response/event
correlation, acceptance/conflict, unknown or cancellation fences. All participating
workers must adopt the protocol before it can protect the transport path; current
internal fixture-only legacy paths remain unwired. No claim covers independent
databases or external account senders.

Validation added: synthetic transport/no-transaction/zero-call denials, reservation
breaker race, quota exhaustion/staleness, owner/evidence mismatch and replay,
no-refund outcomes, 75 mutation/commit fault cases, independent-process concurrent
campaigns, process death before/after synthetic transport, signal outage and durable
unknown recovery. Existing real single/multisite SQL/race/browser suites remain
required. Local PHP is absent; local Docker socket check returned operation not
permitted (daemon running, UID 1000). No local PHP/SQL success is claimed. Local
packaging hash verifier, shell syntax and whitespace checks pass. Hosted CI and
clean review of the final changes must be recorded in the PR acceptance report.

Remaining next bounded work: integrate reservation/begin/settlement immediately
around final authorized prepared worker/mapper submission; exact configured
account/region/deployment/blog complete/fresh suppression checkpoint validation
(including running/failed/inconsistent states); recheck lease/owner/version,
due-time/pause/cancel/eligibility after independent preflight transactions; persist
signals before recipient results; preserve late/unknown/conflict and exact-byte
recovery; classify and durably recover lost outcomes without resending; test
breaker/sync/worker races and failed result writes end-to-end. The latch protocol
is deliberately conservative (one unsettled submission per account and stranded
capacity on crashes); it is not production throughput acceptance.

Production budget/sync factories remain unconditionally null, dispatch false,
intake/public collection/cron disabled. No AWS requests, credentials, messages,
endpoint/sync activation, new UI, merge or deployment. External quota/headroom,
host clock/ownership/restore, identity/IAM, monitoring, keys/retention and controlled
inbox acceptance remain unverified activation dependencies.

Local final-change review also rejects partially populated reservation evidence
rather than overwriting it during settlement. Real SQL regressions retain those
inconsistent rows unchanged for investigation. Final-head CI/review must cover
this correction; initial-head checks do not substitute for it.
