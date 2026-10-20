# Phase 5A — delivery-provider decision and integration specification

Decision date / official documentation checked: **2026-10-20** (Europe/Istanbul).
Status: **Phase 5A documentation complete; Phase 5 implementation not started**.
Baseline: GitHub `main` **d58b018191cc34af24d0d222ce63ba9d5b4c7975**;
branch `docs/peracrm-phase-5a-provider-spec`. No applicable `AGENTS.md` was found
in the repository, workspace or searched ancestors. Read with the authoritative
[living plan](email-campaigns-implementation-plan.md) and
[operations runbook](email-campaigns-operations.md).

This specification changes no runtime behavior. Production dispatch, real intake
and public collection remain disabled. No account purchase, credential request,
DNS change, real message, production cron activation, merge or deployment belongs
to this task. Recommendations below are proposed contracts, not existing controls.

## Decision and its limits

Recommend **Amazon SES API v2 `SendEmail`, one recipient per request**, with
CRM-owned content, preferences and global marketing suppression; configuration-set
events delivered through authenticated SNS HTTPS notifications. Use one fixed
account/region/configuration set, no global endpoint or automatic region failover.
Select a region and commercial plan only after the account/privacy/budget review.
SES explicitly supports marketing/newsletters through this API; production access
must accurately describe the use as **MARKETING** [S1, S4].

This recommendation favors the existing CRM-owned per-recipient ledger, final
eligibility lock and pause/cancel boundary. SES requires more operations work:
IAM, DNS, quotas, SNS certificate/signature validation, event loss recovery and
CRM-managed RFC 8058 unsubscribe. It has **no documented SendEmail idempotency
key/window** [S2]. Unknown outcomes can remain held indefinitely. If automatic
recovery of every timed-out request, or exactly-once delivery, becomes mandatory,
SES fails that requirement; do not conceal it with local unique keys or tags.

**Brevo remains suitable as a marketing platform, but is not the recommended
adapter for this architecture.** The documented marketing route is
`POST /v3/emailCampaigns` followed by
`POST /v3/emailCampaigns/{campaignId}/sendNow`, using provider contact lists or
segments [B1, B2]. Its `/v3/smtp/email` guide explicitly describes non-promotional
transactional messages [B3]; custom HTML capability is not permission to use that
route for these newsletters. Treat marketing use of that exact route as
unverified unless Brevo provides explicit applicable product/contract evidence.
Do not implement it on the strength of the previous provisional preference.

Brevo campaign delivery transfers recipient expansion/submission to Brevo. It
cannot preserve a CRM authorization check immediately before *each* provider
submission once a whole campaign is handed over. Its marketing API also lacks a
documented field for submitting our approved plain-text bytes alongside HTML,
and a documented campaign-send idempotency window. These are concrete fit gaps,
not a claim that Brevo prohibits marketing or that its UI lacks text editing.
One campaign/list per recipient would multiply objects/API calls, introduce new
unknown boundaries and still not prove the required text/correlation contract.
Do not adopt that workaround. Reconsider Brevo only with a separately reviewed
provider-owned dispatch design or written evidence resolving the exact API gaps.
SES meets the marketing path/control requirement with the stated unknown-hold
limitation, so no third provider is evaluated.

## Evidence conventions and current acceptance

**D** = explicitly documented capability/behavior in the linked official source;
**I** = engineering inference or proposed PeraCRM policy; **U** = not established
by reviewed docs or account-specific evidence. A dated retrieval is not a claim
that an undated page was published that day. Recheck sources before coding and
before activation: current pricing and API behavior can change.

**User-reported live evidence, received 2026-10-20:** preparation from a frozen
campaign, one synthetic recipient, schedule intent, pause/resume and terminal
cancellation passed in the live queue UI; **no emails were sent**. This is not
an automated check, independently reproduced host test, CLI health check or
provider/inbox acceptance. Existing automated Phase 4D evidence remains in the
living plan. CLI health, real-host scheduler/restore verification, the property-
block issue and recorded usability problems (sender-label confusion, obscured
configuration errors, import-error navigation) remain outstanding. No phase is
completed by this live report and no public collection/intake is authorized.

## Requirements comparison

| Requirement | Brevo marketing campaign API | SES v2 SendEmail |
| --- | --- | --- |
| Permitted marketing path | **D:** email campaign API is explicitly a newsletter/campaign path [B1, B2]. **U:** promotional use of `/smtp/email` is not established; its guide says non-promotional [B3]. | **D:** SES API sends marketing; production-access flow distinguishes MARKETING [S1, S4]. Permission still requires opted-in recipients, bounce/complaint handling and account approval. |
| Stored HTML / text / personalization | **D:** campaign `htmlContent`, subject, sender, reply-to, params/contact attributes; HTML >10 characters and <1 MB [B1]. **D:** UI supports editing plain text [B4]. **U:** exact plain-text upload through campaign create/send API. **I:** mutable contact attributes are not a frozen recipient envelope. | **D:** Simple HTML and Text bodies with charset, subject, headers, reply-to and EmailTags [S2]. **I:** submit stored UTF-8 delivery bodies with only declared recipient slots filled locally; no remote template lookup. |
| Sender / domain / alignment | **D:** authenticate domain with Brevo ownership code, DKIM and DMARC. Generic return-path SPF differs from branded SPF alignment; branding/setup availability needs checking [B5]. **U:** actual sender verification, DNS and branded-domain entitlement. | **D:** sender/domain verification and DKIM are regional [S5]; aligned DKIM or custom MAIL FROM SPF can satisfy DMARC [S6]. **I:** verify SPF, DKIM, DMARC and actual alignment in received mail, not just a green admin setting. |
| Visible opt-out / one-click | **D:** campaign footer unsubscribe is required/auto-added; Brevo documents automatic RFC 8058 one-click support [B6]. **I:** RFC 8058 implies `List-Unsubscribe-Post` and no-confirmation POST; capture emitted headers, DKIM coverage and actual POST behavior for selected account at acceptance. Consent-group one-click can be narrower than global opt-out [B7]. | **D:** AWS documents customer-managed RFC 8058 and SES-managed alternatives [S7, S8]. **I:** use CRM-owned HTTPS token URL and `List-Unsubscribe-Post: List-Unsubscribe=One-Click`; omit ListManagementOptions so SES does not override those headers. Verify DKIM coverage and no-login POST at acceptance. |
| Webhook authentication / replay / account | **D:** Basic, bearer and custom header authentication [B8]. No reviewed body-signature/timestamp replay guarantee. `id` is described as internal webhook ID, not proven globally unique event ID [B9]. **I:** dedicated account credential, composite event fingerprint and replay ledger needed; timestamps alone do not authenticate. | **D:** SNS signed notification envelope, certificate verification and stable MessageId across retries [S9, S10]. **I:** pin topic ARN/account/region and SES sendingAccountId/configuration set plus delivery/attempt tags; signature alone is not replay protection. Persist dedupe keys and check timestamp policy. |
| Retries / durable intake | **D:** Brevo documents four retries at incremental 10 minutes, 1 hour, 2 hours, 8 hours; 4xx except 429 **and any 5xx** discard [B10]. **I:** return 429 on authenticated storage outage, not a generic 500; verify behavior with account before use. | **D:** SNS HTTPS defaults to three retries at 20 seconds; custom HTTP policy is bounded to 3,600 seconds, 5xx/429 retry, other errors permanent [S11]. The 23-day managed-endpoint policy is **not** HTTPS. **I:** explicit policy, subscription DLQ and monitored redrive required. |
| Feedback events | **D:** delivered, hardBounce, softBounce, spam, unsubscribed, campaign/mailbox/time correlation [B9]. **U:** event ID uniqueness across retries/accounts. | **D:** configuration-set Send, Delivery, Bounce (Permanent/Transient/Undetermined), Complaint, Reject, DeliveryDelay, Rendering Failure and Subscription data [S12]. **I:** CRM-managed opt-out is a local event, not an expected SES Subscription event. Provider complaint coverage is incomplete; Gmail does not supply SES complaint data [S13]. |
| Suppression / import / export | **D:** contact emailBlacklisted plus separate transactional sender blocking; imports/exports exist [B11, B12]. Consent groups may narrow scope [B7]. **I:** import deny knowledge before audience synchronization; never clear blocks or update a blocked contact's EMAIL (documented to resubscribe it). | **D:** account+region suppression supports BOUNCE/COMPLAINT, list/get/put and bulk import; config-set/tenant overrides can change scope [S13]. **I:** CRM also retains global unsubscribe/admin reasons; these cannot be faithfully encoded as BOUNCE/COMPLAINT. Do not fabricate reasons. |
| Idempotency / retention window | **D:** batch transactional idempotency UUID currently has **30-minute** TTL and duplicate_parameter error [B13]. It is not documented for marketing campaign create/send. **U:** campaign-send dedupe/window. | **U:** no SendEmail idempotency parameter or documented dedupe window [S2]. **I:** every HTTP retry can submit another email; disable SDK submission retries. Local delivery_key is correlation, not provider dedupe. |
| Lookup / ambiguous send | **D:** campaign status/reports exist [B2]; transactional lookup is another product. **U:** proof of recipient non-acceptance by CRM delivery/attempt key. Do not treat campaign creation/status or missing events as proof. | **D:** GetMessageInsights takes provider MessageId; VDM dashboard/export searches recent messages (30 days) [S14]. **U:** exhaustive lookup/non-acceptance proof by our attempt key after lost response. **I:** signed Send/Delivery/Bounce/Complaint event with matching tags can prove prior acceptance; absence cannot prove non-acceptance. |
| Rate / quota / access | **D:** general API limits: contacts 10 RPS/36,000 RPH; other endpoints 100 RPH (campaigns unless explicitly excepted); 429 on excess [B14]. Free daily/paid tier caps are additional [B15]. Transactional sandbox is not marketing acceptance. **U:** account activation, credits and actual limits. | **D:** sandbox is regional, verified recipients/simulator only, 200 recipients/24h and 1/sec; production approval and quotas are regional [S3–S5]. **U:** approved production rate, daily quota and reputation limits. **I:** scheduler bounds are not a provider rate limiter. |
| Timeout behavior | **U:** neither reviewed contract guarantees that client timeout means rejection. **I:** campaign create/send timeouts must be held unknown; a 20-second call limit does not limit provider campaign delivery duration. | **U:** no promise that a 20-second client timeout cancels acceptance. **I:** one bounded request, total transport <=20 seconds including connection/TLS; timeout/reset/ambiguous 5xx unknown. SDK timeout/retry settings require explicit configuration [S15]. |
| Region / retention / DPA | **D:** EU database hosting; DPA in Terms [B16]. Email-event policy retains events without a time limit below 10M; above 10M, events older than 24 months are deleted after a 7-day buffer [B17]. **U:** account/product subprocessors, transfers, body retention and deletion obligations; transactional log settings do not establish marketing retention. | **D:** regional resources/quotas/events [S5]; shared-responsibility/deletion docs and DPA framework [S16]. **I:** choose regional SES/SNS/DLQ, no global routing, no optional tracking/archive. **U:** approved transfer/legal terms, provider internal retention and exact logging/DLQ retention. EU endpoint does not promise every recipient/service processes only in EU. |

Brevo API authentication is documented as `api-key` (and OAuth where supported)
[B1, B11]. Any reconsidered adapter must use deployment-managed credentials and
a separate webhook bearer, fixed API host/TLS, redaction and account isolation;
least-privilege scope availability remains account/product-specific, not assumed.

Retention is not a single lookup window: SES suppression entries ordinarily remain
until removed, but a sending pause can cause deletion after 90 days [S13]. CRM
tombstones therefore remain authoritative. VDM search is 30 days [S14]; external
event stores have their own lifecycles [S16]. Brevo's optional extended event
retention is Enterprise-only (10 years, 20% of net spend) [B17], not a needed plan
assumption. Neither provider's reviewed pages establish an approved Pera-wide
retention/deletion contract. Agree minimal diagnostic, body/token, consent,
suppression, replay, backup and DLQ lifetimes before activation; don't equate
indefinite provider storage with approved retention.

### Pricing and unresolved operating scale

Official prices checked 2026-10-20, USD starting/list prices, not a quote or
purchase decision. Brevo's pricing page is client-rendered in the browsing tool;
its official pricing help provides the verifiable tier details [B15].

- **Brevo:** Free up to 300/day; Starter from **$9/month**, Standard from
  **$18/month**, starting at 5,000/month and up to 500 stored contacts in those
  entry tiers. Higher volume/contact tiers change pricing. Starter branding
  removal is an add-on; Standard adds features not needed by this CRM. Outbound
  webhooks appear in Free features; dedicated IP/branding/advanced limits and
  account activation still need selected-plan confirmation. Do not assume
  5,000 sends means 5,000 contacts.
- **SES:** current pricing includes Essentials **$0.16/1,000** (first 10M/month,
  no listed fixed monthly fee); Pro **$0.22/1,000 + $105/account/region/month**;
  Enterprise **$0.23/1,000 + $500/account/region/month**. À la carte outbound
  remains **$0.10/1,000**, with optional capabilities separately charged [S17].
  New accounts/account-region combinations without metered activity since
  2025-06-01 default to Essentials beginning 2026-07-21; do not budget every
  new account at the old à la carte rate. Additional data, SNS, SQS/DLQ,
  monitoring, optional VDM/dedicated IP and taxes are excluded here.

Illustrative **scenarios, not Pera's forecast**: at 5k / 50k / 250k recipients
per month, SES Essentials base outbound is $0.80 / $8 / $40; à la carte is
$0.50 / $5 / $25 before extras. Brevo's starting $9 tier only covers the first
scenario if contact capacity also fits; obtain an account quote for the others.
Engineering/operations cost may exceed transport cost. No free credit, dedicated
IP or paid diagnostics assumption is needed to recommend the API.

Open decisions: monthly recipients, active stored contacts, campaign frequency,
peak burst and required completion time; budget including operations; account
owner/access/approval, SES plan/region and available quotas; verified From,
Reply-To, footer/legal address and DNS owner; historical consent/suppression
inventory, legal basis, DPA/transfer and retention approval; topic/global opt-out
policy and translations; target-blog topology, scheduler/restore owner; and an
operator willing to hold unresolved unknowns. If that final limitation is
unacceptable, stop before adapter activation and revisit the delivery design.

## Mapping to the current code and smallest necessary changes

Paths are relative to this plugin. Links point to actual source; proposed file
names below do not assert that an implementation exists.

| Current boundary | Verified behavior / gap | Proposed change and limitation |
| --- | --- | --- |
| [worker.php](../inc/campaigns/worker.php), `PeraCRM_Campaign_Provider`, `submit()` | Provider receives only mailbox, delivery_key, attempt_key, timeout_seconds. `lookup()`/`verify()` are abstract; fake evidence proves fixture behavior only. Result normalization keeps kind/message_id/retry_after, discards provider/error metadata. | Add a versioned immutable submission envelope plus narrow content resolver under worker authority. Keep outcomes, leases and retry rules; extend sanitized diagnostics separately. Adapter cannot query current draft/config or silently invent content. |
| [snapshots.php](../inc/campaigns/snapshots.php), `snapshot_candidate()` / `snapshot_decode()` | payload.content stores sender, model.subject/preheader, output.html/text/destinations, template/config/rules and hash. `snapshot_get()` requires staff authority, so is not a background-worker resolver. Decoder verifies original hash/target. | Add internal authority-bound resolver using job/revision/snapshot/recipient binding checks. Obtain subject/from/reply-to from captured fields, not parsed display strings or current settings. Never loosen staff APIs for background use. |
| [renderer.php](../inc/campaigns/renderer.php), `peracrm_campaign_render()` | Captured v1 output is review material: review/no-send notices, inert HTML CTA span, escaped textual preference placeholders and no real footer anchors. Text contains destinations but HTML does not link them. | Before send readiness, introduce a versioned deliverable output with reviewed real public anchors and typed recipient URL slots in HTML/text. Keep review notices in review UI. New capture/approval/freeze required; reject existing v1 snapshots for real delivery, never repair them at send time or re-render from live posts. |
| [queue-schema.php](../inc/campaigns/queue-schema.php), [queue.php](../inc/campaigns/queue.php) | Lifetime revision/mailbox delivery key, unique ledger, current attempt key/count, owner/version/lease, one message_id; worker_events are state/version audit, not provider-event dedupe. No immutable per-attempt provider/account/request binding. | Add append-only submission-attempt binding (provider/account/region/config version, revision/hash, delivery/attempt keys, envelope hash and provider ID when known), unique scoped message map, durable intake receipt/event ledger and sync cursor. Do not repurpose worker_events as incoming-event IDs. Additive schema only, version chosen against implementation main. |
| [preferences.php](../inc/campaigns/preferences.php), [routes.php](../inc/campaigns/routes.php) | Staff-only random 256-bit token issuance, SHA-256 digest storage, purpose/version/expiry/revocation, target-domain URL, idempotent withdrawal/topic writes. GET/HEAD don't withdraw. POST expects preference_action, not RFC 8058 form. | Separate worker-authorized issuance from staff path; dedicated unsubscribe purpose (cannot edit topics), one-click POST mapper and genuinely clickable visible footer. Keep public collection off. Tokens remain usable for opt-out after campaign cancellation/completion. |
| [service.php](../inc/campaigns/service.php), [repository.php](../inc/campaigns/repository.php), worker `eligibility()` | Staff suppression service requires staff; repository mailbox mutex protects cumulative global marketing_email suppression. Worker rechecks frozen binding, consent/evidence, current language/topics, linked CRM exclusions and suppression at final authorization. | Add narrowly verified-provider authority and transactional event->suppression service, not a fabricated staff session. Keep cumulative hard_bounce/complaint/unsubscribe/admin flags and local normalization. Fresh sync/readiness facts must also gate starts and each submission. No event grants consent or changes frozen mailbox. |
| [scheduler.php](../inc/campaigns/scheduler.php), [operations.php](../inc/campaigns/operations.php), [readiness.php](../inc/campaigns/readiness.php) | Dispatch false unconditionally; no factory/concrete production worker/event installation. 3 jobs/10 recipients/10 recovery, 25-second work-start deadline with full 20-second transport budget, 60-second lease, four attempts. Schema readiness isn't provider readiness. | Leave disabled through adapter/event PRs. Later internal-only authority adds explicit allowlist and provider readiness, cross-job account rate budget and fail-closed sync/event health. No automatic cron installation. Preserve accepted vs delivered vs unknown reporting. |

### Stored-content and personalization contract

The delivery envelope is versioned and constructed only after published revision,
completed freeze/preparation, target-blog, hash and mailbox binding validation.
Minimum fields: provider identity/config version; snapshot/revision/hash;
subject, UTF-8 HTML and text; approved captured sender name/address and Reply-To;
exact frozen mailbox (one To, no CC/BCC); opaque deployment-scoped delivery and
attempt tags; body/header personalization version; transport deadline.

Store **deliverable** HTML/text in a newly approved snapshot. At submit time fill
only explicitly typed preference/unsubscribe URL slots. Validate slot count and
context; URL-encode token, HTML-attribute-escape HTML URLs, use unescaped URL in
text. Reject unknown/unfilled slots, CR/LF header values, invalid hosts, wrong
blog or unsupported template version. Do not global-string-replace arbitrary
user-authored text, substitute names/live property prices or mutate shared output.
Recipient links and resulting envelope hash belong to an immutable attempt;
shared snapshot hashes/rows must remain identical. Rendering changes require
another reviewed capture and published revision.

Propose deployment-keyed deterministic per-delivery opaque tokens with purpose
separation and key version, recording only digest/address/purpose metadata via a
worker-only issuer. This permits stable retry reconstruction without storing raw
bearers; it needs a new versioned resolver (existing v1 only accepts preferences).
Derive URLs only on configured authoritative HTTPS domain, never request Host.
Keep old verification keys through the approved opt-out lifetime; missing key,
revocation/expiry or unsafe lifetime blocks new mail. Alternative encrypted token
storage requires an explicit key-management decision; never quietly reuse staff
issuance in CLI or generate tokens in shared preview/freeze.

Visible HTML anchors/text URLs lead to the preferences/confirmation page; GET
and scanner HEAD remain read-only. A separate purpose-bound one-click URL accepts
`application/x-www-form-urlencoded` POST `List-Unsubscribe=One-Click`, no cookies,
login, CSRF nonce, CAPTCHA or confirmation. Success only after durable idempotent
global marketing withdrawal; invalid bearer cannot select arbitrary mailbox or
edit topics. Preserve no-store/no-referrer behavior and redact access logs.
Set `List-Unsubscribe: <https://AUTHORITATIVE/...opaque-token...>` and
`List-Unsubscribe-Post: List-Unsubscribe=One-Click`. Verify RFC 8058 DKIM coverage
of both headers on actual SES output. Body link and machine POST converge on the
same mailbox lock and suppression operation. Do not enable SES list management
in parallel: it overrides headers and introduces a second preference authority
[S8]. SES Subscription events only apply if that alternative is later adopted.

### SES submission, authentication and outcome contract

Use SES v2 **Simple** content over HTTPS with stored HTML/Text/Subject, captured
From/ReplyToAddresses, required ConfigurationSetName and EmailTags. No attachments,
remote provider templates, tracking rewrite, open/click events, CC/BCC, provider
schedule or EndpointId. The provider can add transport headers/DKIM; stored body
approval is not a promise that every MIME byte is identical. If header signing
acceptance forces Raw MIME later, make that an explicit reviewed contract change.

Prefer deployment-managed short-lived IAM role credentials if hosting supports
it; otherwise a restricted deployment secret binding (access key, secret,
optional session token). Use a maintained SigV4-capable client, fixed regional
host allowlist, TLS verification and no redirect. No credential collection in
CRM/browser/options/logs. Runtime send principal gets only required send rights
on approved identity/configuration set with From restrictions; separate restricted
read/suppression-sync principal, no account-wide admin or suppression-delete
rights. Credential refresh, DNS, certificate fetch and dependency loading must
not create hidden unbounded work during submit. Host credential mechanism is an
open deployment decision, not a request for keys in this task [S15, S18].

Disable SDK/HTTP automatic **send retries**. Bound total synchronous transport to
20 seconds (connect cap proposed 3 seconds, within total); set SDK HTTP timeout
and retries explicitly, inject transport for tests, account for credential and
TLS overhead. Check elapsed monotonic time and remaining worker lease before
starting. Watchdog/DB lock bounds require real-host verification; a timeout or
kill cannot undo an accepted request. Maintain the scheduler's full-budget-start
rule; do not increase limits to hide slow calls.

| Observation | Worker result and additional action |
| --- | --- |
| Valid 200 and bounded nonempty MessageId | accepted; persist provider/account/region/message correlation. This is provider acceptance only, possibly later suppressed/rejected, not inbox delivery. |
| Complete authenticated SES TooManyRequestsException / documented quota rejection | transient known non-acceptance; sanitized code, bounded retry_after/backoff, account rate gate. Never apply this classification to a proxy-generated or truncated 429. |
| Complete documented BadRequest/MessageRejected/unverified identity/access rejection | permanent known non-acceptance for this request; fixed diagnostic. Identity/auth/account pause failures also trip readiness so later recipients aren't burned through. Do not hard-bounce-suppress a mailbox merely for configuration failure. |
| Timeout, reset after possible write, unrecognized/ambiguous 5xx, malformed 200/missing ID, thrown exception, expired inflight lease | unknown; hold, preserve attempt binding, no automatic resend or provider failover. A proven local validation failure before transport can be failed/excluded without submission. |

Keep the current accepted/transient/permanent/unknown result kinds. Add only fixed
failure codes and provider scope/request binding needed for audit; never retain
raw provider error bodies. Sanitized account-level circuit-breaker metadata must
survive a failed per-recipient result write. Late returns remain fenced; an
incoming event can independently supply acceptance evidence later.

### Correlation, event authentication and suppressions

Before transport, durably bind opaque deployment/environment + delivery_key +
attempt_key + account + region + configuration set + snapshot/envelope hash.
Send keys as non-PII EmailTags; keys are correlation, not authorization or external
idempotency. A signed SES Send event can arrive before the response write, so
intake can resolve the pre-existing attempt without message_id being present.
Never correlate by email, subject, tag or caller-selected CRM ID alone.

Propose a plugin-owned REST endpoint under `/peracrm/v1/campaign-events/ses`:

1. Limit raw body (proposed 256 KiB) and parser depth; accept SNS text/plain JSON
   as well as configured application/json. Disable SNS raw delivery. No payload
   logging. Parse only enough bounded envelope to verify it; no inner event may
   affect state before authentication.
2. Use AWS-supported signature validation/canonical fields, require configured
   SignatureVersion 2, validate certificate chain/signature and the exact allowed
   regional SNS HTTPS certificate host/path. Reject alternate schemes, ports,
   userinfo, redirects/private hosts; bounded certificate fetch/cache. Validate
   signed Type, TopicArn, Timestamp, MessageId and Message [S9, S10]. Never fetch
   an arbitrary SigningCertURL/SubscribeURL from unauthenticated input. Accept
   subscription confirmation only for a separately provisioned expected topic
   and pending subscription; do not auto-subscribe an arbitrary topic.
3. Pin topic ARN and account/region; validate SES sendingAccountId, config-set
   tags, frozen recipient and both attempt keys against persisted scope. Wrong
   account/environment fails closed, including valid AWS signatures from another
   customer. Unknown matches are durably quarantined and block readiness until
   reviewed; no unsolicited contact creation.
4. Receipt unique key: `(provider, account, region, topic_arn, SNS MessageId)`.
   Store canonical payload hash and authentication/key version. Same ID with
   changed content is a security conflict; don't overwrite it. Also dedupe
   normalized logical events across different SNS receipt IDs by scope/message/
   recipient/type/feedbackId (where supplied), otherwise stable canonical event
   fingerprint. Event timestamps are not unique IDs. Preserve receipt references.
5. Proposed freshness policy: reject/quarantine future timestamps >5 minutes;
   unseen notifications older than 24h use a controlled verified replay path.
   Already stored identical receipts can return success. These are local policies,
   not AWS replay guarantees. Retain replay tombstones through the approved event/
   redrive/restore horizon; establish that horizon before enabling delivery.
6. Transactionally persist receipt, normalized event and any suppression before
   2xx. Return 503/429 for storage/temporary validation-dependency failure so SNS
   can retry; auth/schema rejection gets fixed 4xx. Record unmatched authenticated
   events durably before ack, with alert/quarantine. Use explicit HTTPS retry
   policy, SQS subscription DLQ, monitored redrive and regular gap reconciliation;
   provisioning is a later operations task [S11].

Send proves acceptance; Delivery records receiving-server acceptance, not inbox
placement. Permanent bounce -> `hard_bounce`; Complaint -> `complaint` even when
it arrives after completion/cancellation. Transient bounce/DeliveryDelay is a
post-acceptance delivery fact, **never** a retryable submission. Undetermined
bounce is review/temporary hold, not invented permanent failure. Proposed initial
policy: one transient records diagnostics only; three terminal transient bounces
for the mailbox in 30 days trigger an audited administrative hold pending review.
Finalize threshold with operator before activation; no automatic clearing. Reject/
Rendering Failure records post-acceptance failure, not permission to resend.
Local unsubscribe updates subscription state and cumulative suppression together.
No delivered/open/subscribed/contact-update event can clear a suppression or
authorize resubscription. Keep submission state separate from delivery facts;
late events must not regress accepted to retry or erase unknown history.

Add a verified-provider service using the existing mailbox mutex/repository
primitives, not the staff-only `add_suppression()` entrypoint. Evidence source,
account/event reference, occurrence/recorded times and system actor are server-
controlled. Unknown delivery correlation cannot update arbitrary subscribers;
a separately authenticated suppression-sync record can add mailbox deny knowledge
under its own recorded authority even when no local send exists. This distinction
prevents dropping prior-provider suppressions during initial synchronization.

SES sync: enable both account BOUNCE and COMPLAINT and ensure the chosen config
set doesn't override them away; no tenant mode initially. Page ListSuppressed-
Destinations/GetSuppressedDestination, preserve provider address spelling for API
operations and normalize locally. Import via put/bulk import only with actual
BOUNCE/COMPLAINT evidence. Never remove local deny flags because a remote export
is empty or an account list expires/is cleared. Keep unsubscribe/admin local;
provider lack of an equivalent reason does not make them sendable. Baseline all
historical sources before first delivery; periodically page full provider state
with durable checkpoints (no unsupported delta assumption), union denies, expose
last complete sync. Proposed stale cutoff 24h plus immediate block on intake/DLQ
or sync failure, checked at each submission; tune only after volume/latency review.
No worker may bypass a blocked suppression checkpoint. A concurrent withdrawal
after committed inflight authorization cannot recall an already-starting request.

For Brevo, equivalent work would additionally reconcile emailBlacklisted, sender-
specific transactional blocks and consent-group scopes; use import/export and
2026-10-14 modifiedSince filtering with overlap and per-row state checks [B11,
B12]. Do not assume campaign/list unsubscribe is global; conservatively map a
verified campaign opt-out to CRM global marketing withdrawal under the stated MVP
policy. Provider lists never become consent truth.

### Unknown-outcome reconciliation and restore

`lookup(delivery_key, attempt_key)` for SES must consult authenticated locally
retained provider events/message mapping and optionally GetMessageInsights when
MessageId is known. It may return **null** forever. Do not implement a fabricated
lookup by subject/mailbox or return transient because nothing was found in VDM.
VDM's recent search is useful positive evidence, not an exhaustive negative oracle.
No documented SES idempotency retention window exists to make a later resend safe.

`verify()` must use durable authenticated receipt/evidence lookup, scope/key/hash
binding and a server-created evidence record; it must not trust a browser
`verified=true`, arbitrary JSON/hash or possession of tags. Preserve staff
capabilities, impersonation denial, nonce, expected version and real-actor audit
in worker `reconcile()`. Minimal later UI can select verified evidence IDs, never
manually mark acceptance/non-acceptance. Accepted evidence can resolve unknown;
transient/permanent resolution requires provider proof of **non-acceptance for
that exact attempt**. Missing events, expired lookup history, cancellation, elapsed
lease or an empty search are insufficient. Hold unresolved rows, alert, and keep
submission_complete false. Do not add reset/resend/retry buttons for unknowns.

Restore procedure remains [the runbook](email-campaigns-operations.md): quiesce
workers/cron, keep dispatch off, reconcile post-backup provider events and attempt
bindings, restore unioned opt-outs/suppressions and cancellation fences before
any future resume. Retain independent event/DLQ/backup evidence for the approved
horizon; a restore earlier than correlation history requires holding affected
work, not assuming non-acceptance. No exactly-once guarantee is offered.

## Subsequent PRs, validation and rollback

These are implementation proposals, not authorization to activate production.
Keep each PR small; split prerequisite content/persistence changes when needed.
All campaign code/assets/routes remain plugin-owned.

1. **Provider adapter and mocked contract tests. Exact first implementation task:**
   introduce a versioned `CampaignSubmissionV1` envelope/content resolver contract
   and SES request/result mapper with injected transport in proposed
   `inc/campaigns/providers/ses.php`; keep the unconditional production gate and
   no production factory/network wiring. First tests prove stored subject/sender/
   HTML/text mapping, one frozen recipient, fixed scope/tags, all four outcome
   kinds, no automatic retries, timeout options, bad headers/IDs and redaction.
   **Reject v1 review-only snapshots**. Add a separately reviewable deliverable
   renderer/template version and capture/approval tests before accepting any real
   delivery; don't change historical hashes. Follow with immutable attempt
   binding/token issuance contracts as narrowly scoped dependent changes. Fake
   tests cannot establish real timeout or AWS acceptance semantics.
2. **Authenticated event intake, suppression and unsubscribe integration.** Add
   additive attempt/message/event/dedupe schema, authority boundaries, SNS
   verification and purpose-separated tokens/POST route; baseline/periodic deny
   sync and unknown evidence lookup. Mock signature/certificate/SSRF/wrong-topic/
   account/replay/conflict and invalid-body tests; concurrent SQL events, duplicate/
   out-of-order delivery, opt-out vs final authorization, rollback-before-ack,
   early-event/lost-response and restored-state fixtures in single/multisite.
   Test machine POST without cookies/nonces and scanner GET read-only, token
   secrecy, suppressed reimport, no clearing and no retry after accepted soft
   bounce. All automated tests block real mail/HTTP except injected mocks.
3. **Readiness/settings and allowlisted internal delivery.** Admin-only masked
   status, deployment secret presence (never value), identity/config/account/
   region/plan/quota/sync/event/unknown/restore readiness; separate read principal,
   account-wide rate budget, deployment/target-blog binding and clone denial.
   Separate internal-only authority with exact mailbox allowlist and hard count
   caps, no public dispatch switch or automatic cron install. Tests deny unknown
   recipients, employee/anonymous/impersonation, stale readiness and wrong sites;
   check current eligibility immediately before submission. Real internal mail
   requires the next explicit acceptance activity, not merely merging this PR.
4. **Real inbox/DNS/webhook acceptance before any public pilot.** With separately
   authorized account/DNS/inbox operations, record exact plan/region/access/quota,
   current docs, approved list evidence/footer, SPF/DKIM/DMARC alignment and signed
   unsubscribe headers; actual Gmail/Outlook/mobile HTML/text/links/Reply-To;
   no-login one-click and visible opt-out; provider bounce simulator, replay/
   storage-outage/retry/DLQ recovery, sync and timeout/watchdog/late-event behavior.
   Do not generate complaints. Verify real-host CLI health, scheduler ownership/
   locks/frequency and restore while sending remains disabled except controlled
   allowlisted acceptance. Review property-block/usability/accessibility issues
   and consent/retention requirements before public pilot. Public delivery,
   real intake and collection require a later separately reviewed rollout.

Readiness fails closed on unsupported review-only snapshot, unapproved content,
missing/alignment-unverified identity, missing credentials, wrong scope, stale
suppression sync, broken event/DLQ monitoring, quota/rate uncertainty or unresolved
restore/unknown evidence. Acceptance records must distinguish mocks, disposable
DB/race tests, user-reported UI results and actual provider/host/inbox observations.

Rollback: disable new submissions/internal authority, quiesce future processes,
rotate compromised send credentials if necessary; leave public withdrawal and
authenticated event/suppression intake available. Keep additive tables, attempt
bindings, immutable snapshots, dedupe tombstones, cancelled revision fences and
all deny knowledge. Never truncate/reset ledger or replay accepted/unknown mail
on rollback. Already accepted delivery cannot be recalled. Application rollback
must remain compatible with event persistence or stop sends until repaired.

## Official source register

All links checked **2026-10-20** through official documentation/search. Pages
without publication dates use that access date only. Dynamic/limited pages and
account-specific gaps are identified above; no provider account was inspected.

### Brevo

- **B1:** [Create email campaign](https://developers.brevo.com/reference/create-email-campaign) — marketing HTML, personalization, recipients and fields.
- **B2:** [Send campaign now](https://developers.brevo.com/reference/send-email-campaign-now) and [get campaign](https://developers.brevo.com/reference/get-email-campaign) — provider campaign boundary/reporting.
- **B3:** [Send transactional email](https://developers.brevo.com/docs/send-a-transactional-email) — exact `/smtp/email` product description.
- **B4:** [Edit plain-text version](https://help.brevo.com/hc/en-us/articles/360000486460-How-do-I-create-edit-the-plain-text-version-of-HTML-emails) — campaign UI capability, not API text-upload evidence.
- **B5:** [Domain authentication](https://help.brevo.com/hc/en-us/articles/12163873383186-Authenticate-your-domain-with-Brevo-Brevo-code-DKIM-DMARC) and [domain setup/alignment](https://help.brevo.com/hc/en-us/articles/35852083084178-Domain-setup-for-better-email-deliverability).
- **B6:** [Required unsubscribe link](https://help.brevo.com/hc/en-us/articles/9741388688402-Do-I-need-to-add-an-unsubscribe-link-to-my-emails) and [list-unsubscribe FAQ](https://help.brevo.com/hc/en-us/articles/19100260472850-FAQs-About-list-unsubscribe-and-list-help-headers-in-emails), [automatic RFC 8058 handling](https://help.brevo.com/hc/en-us/articles/14925263522578-Comply-with-Gmail-Yahoo-and-Microsoft-s-requirements-for-email-senders).
- **B7:** [Consent groups](https://help.brevo.com/hc/en-us/articles/33595674951314-Create-and-manage-consent-groups) — one-click scope can differ from global withdrawal.
- **B8:** [Secure webhook calls](https://developers.brevo.com/docs/secured-webhooks) and [create webhook](https://developers.brevo.com/reference/create-webhook).
- **B9:** [Marketing webhook payloads](https://developers.brevo.com/docs/marketing-webhooks).
- **B10:** [Webhook retry mechanism](https://developers.brevo.com/docs/retry-mechanism) — unusual 5xx discard rule requires particular attention.
- **B11:** [Update contact](https://developers.brevo.com/reference/update-contact) and [import contacts](https://developers.brevo.com/reference/import-contacts) — block state, identity-change risk and import options.
- **B12:** [Contact export date filtering, published 2026-10-14](https://developers.brevo.com/changelog/2026/10/14).
- **B13:** [Batch transactional idempotency](https://developers.brevo.com/docs/heterogenous-versions-batch-emails) — current 30-minute window, not a campaign guarantee.
- **B14:** [API rate limits](https://developers.brevo.com/docs/api-limits).
- **B15:** [Pricing-plan details](https://help.brevo.com/hc/en-us/articles/208589409-About-Brevo-s-pricing-plans) and [pricing](https://www.brevo.com/pricing/) (client-rendered).
- **B16:** [Database hosting, official Spanish help](https://help.brevo.com/hc/es/articles/360001005510-D%C3%B3nde-se-almacenan-los-datos), [DPA location](https://help.brevo.com/hc/en-us/articles/15403782599570-Where-can-I-find-the-Data-Processing-Agreement-DPA), [Terms](https://www.brevo.com/legal/termsofuse/) (body not extractable in tool; applicable contract/retention approval remains open).

- **B17:** [Email-event retention policy](https://help.brevo.com/hc/en-us/articles/19317424653586-FAQs-About-the-data-retention-policy-for-email-events) — policy effective 2025-01-01, thresholds and paid extension.

### Amazon SES / SNS

- **S1:** [SES product documentation](https://docs.aws.amazon.com/ses/) — marketing use.
- **S2:** [SES v2 SendEmail](https://docs.aws.amazon.com/ses/latest/APIReference-V2/API_SendEmail.html) — fields, acceptance response and rejection taxonomy; no idempotency key documented.
- **S3:** [Sending quotas](https://docs.aws.amazon.com/ses/latest/dg/manage-sending-quotas.html).
- **S4:** [Production access / MARKETING declaration](https://docs.aws.amazon.com/ses/latest/dg/request-production-access.html).
- **S5:** [Regions and SES](https://docs.aws.amazon.com/ses/latest/dg/regions.html).
- **S6:** [DMARC alignment](https://docs.aws.amazon.com/ses/latest/dg/send-email-authentication-dmarc.html).
- **S7:** [One-click unsubscribe options](https://aws.amazon.com/blogs/messaging-and-targeting/using-one-click-unsubscribe-with-amazon-ses/).
- **S8:** [Managed subscription behavior](https://docs.aws.amazon.com/ses/latest/dg/sending-email-subscription-management.html).
- **S9:** [SNS signature verification](https://docs.aws.amazon.com/sns/latest/dg/sns-verify-signature-of-message-verify-message-signature.html).
- **S10:** [SNS notification envelope and retry MessageId](https://docs.aws.amazon.com/sns/latest/dg/http-notification-json.html).
- **S11:** [SNS delivery retry policies](https://docs.aws.amazon.com/sns/latest/dg/sns-message-delivery-retries.html) and [subscription DLQ](https://docs.aws.amazon.com/sns/latest/dg/sns-dead-letter-queues.html).
- **S12:** [SES event contents / account and tags](https://docs.aws.amazon.com/ses/latest/dg/event-publishing-retrieving-sns-contents.html).
- **S13:** [Account suppression list](https://docs.aws.amazon.com/ses/latest/dg/sending-email-suppression-list.html).
- **S14:** [GetMessageInsights](https://docs.aws.amazon.com/ses/latest/APIReference-V2/API_GetMessageInsights.html) and [VDM dashboard / export search](https://docs.aws.amazon.com/ses/latest/dg/vdm-dashboard.html).
- **S15:** [AWS PHP client HTTP timeouts and retry configuration](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_configuration.html).
- **S16:** [Data protection](https://docs.aws.amazon.com/ses/latest/dg/data-protection.html), [deleting personal data](https://docs.aws.amazon.com/ses/latest/dg/deleting-personal-data.html), [GDPR/DPA framework](https://aws.amazon.com/compliance/gdpr-center/).
- **S17:** [Current pricing](https://aws.amazon.com/ses/pricing/) — plans plus à la carte, including new-account defaults from July 2026.
- **S18:** [IAM for SES](https://docs.aws.amazon.com/ses/latest/dg/control-user-access.html).
