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
mailbox/client identity or consent. HEAD emits no body. Human POST requires an
exact same-origin Origin (or `Origin: null` with browser-enforced
`Sec-Fetch-Site: same-origin` for no-referrer form navigation), URL-encoded
content and a short-lived signed form proof tied to the bearer digest and
deployment with a separate MAC domain. No WordPress login, cookie or guest
nonce is required. Missing/foreign Origin, null Origin without same-origin Fetch Metadata, and
missing/expired/tampered or
other-token proof fail generically. Proofs authorize no recipient access and
never extend preference expiry; the recipient token is separately rechecked.

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

An optional `withdrawal_token` supplied independently alongside a preference
link is authenticated strictly as unsubscribe authority by #1473's service.
When valid it provides a direct link to the existing visible confirmation route,
including when preference access expires. That GET remains read-only and the
existing withdrawal POST handles persistence. An expired preference token alone
cannot produce, reconstruct, mint or substitute for withdrawal authority. Without
an independent valid bearer the page directs recipients to the separate original
email unsubscribe link. This PR does not change expansion/issuance bytes, add a
provider/worker, or claim future messages already carry the optional bearer.
Future authorized issuance must supply the separate link/bearer; existing emails
retain their independently issued unsubscribe link.

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
