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
