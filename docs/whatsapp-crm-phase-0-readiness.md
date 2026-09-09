# WhatsApp CRM Phase 0 Readiness Assessment

**Assessment date:** 2026-08-29
**Scope:** analysis and documentation only. No webhook, Meta account, WordPress setting, credential, schema, or runtime behaviour was changed.
**Baseline:** this assessment follows and should be read with `docs/whatsapp-crm-integration-audit.md` (PR #1303).

## Evidence labels

The labels below have a strict meaning throughout this document:

> **CURRENT — confirmed in repository**: a statement verified in checked-in code. It does **not** prove that the code is deployed or that a live WordPress option has a particular value.

> **EXTERNAL — verification required**: a fact that only the live WordPress/server or the Meta account owner can establish. The repository is not evidence for it.

> **RECOMMENDATION — future design**: proposed work or an operating control. It is not implemented in this assessment.

No secret value is reproduced below. Meta UI names and navigation can change; the account owner should record the labels actually shown and the date observed. Meta product eligibility and event behaviour must be confirmed in the account and against the current official Meta documentation before activation.

## 1. Executive Summary

**CURRENT — confirmed in repository**

* PeraCRM registers public `GET` and `POST` handlers at `/wp-json/peracrm/v1/whatsapp/webhook` in `wp-content/plugins/peracrm/inc/rest/whatsapp.php`. Both routes use `permission_callback => __return_true`.
* GET performs the Meta challenge/token handshake, but POST does **not** authenticate `X-Hub-Signature-256`, does not read an App Secret, and does not validate the WABA, entry ID, or payload `metadata.phone_number_id` against an allow-list.
* POST synchronously parses only `entry[].changes[].value.messages[]`, finds or directly creates a `crm_client`, inserts `{CRM prefix}peracrm_whatsapp_messages`, writes CRM activity, and may send first-enquiry notifications before responding.
* The single option `peracrm_whatsapp_settings` contains `enabled`, `phone_number_id`, `access_token`, `verify_token`, `graph_api_version`, and `test_mode`. It is explicitly written with autoload disabled. There is no WhatsApp App Secret, WABA ID, Business ID, App ID, environment, or stored callback URL.
* `test_mode` is saved and displayed but is not consulted by the webhook processor or outbound Meta provider. It provides no isolation.
* The customer-facing number **+90 545 205 4356** originates in more than one place. Website CTAs use option `pera_whatsapp_number` with digits-only fallback `905452054356`; the contact page displays a hard-coded telephone link; enquiry alert delivery has a separate hard-coded recipient.
* A repository credential-pattern review found **no committed WhatsApp access token, App Secret, or verify-token value**. This is a source-tree finding only, not a Git-history or live-database attestation.

**Conclusion:** **No—the existing POST endpoint cannot safely be exposed to Meta today.** The decisive blocker is missing request signature verification. Additional blockers are missing account/phone allow-listing, synchronous side effects before durable acceptance, incomplete event semantics, unsafe phone identity/deduplication, incomplete retry/idempotency handling, and unknown live Meta/server state.

**EXTERNAL — verification required**

The repository cannot establish who owns the Meta Business Portfolio, whether a WABA/App already exists, whether the candidate number is registered anywhere, whether Business App + Cloud API Coexistence is offered, what webhook is subscribed, or whether the live endpoint is publicly reachable.

**RECOMMENDATION — future design**

Phase 1 should be limited to authenticated, allow-listed, durable, idempotent and quickly acknowledged webhook transport, with diagnostics and fixture tests. It must not connect or alter the production number during development.

## 2. Known Production Number

| Representation | Value | Treatment |
|---|---|---|
| Customer-facing | **+90 545 205 4356** | Candidate production number for future CRM integration |
| Digits only | **905452054356** | Canonical digits supplied for this assessment |

**CURRENT — confirmed in repository:** the digits occur as a theme fallback, a settings example, a contact-page telephone link, and an enquiry-notification destination. None of those occurrences proves a Meta Phone Number ID, WABA registration, Cloud API registration, or live WordPress option value.

**EXTERNAL — verification required:** Pera must affirm that this remains the customer-facing production number and identify its current WhatsApp product/account state.

**RECOMMENDATION — future design:** do not register, migrate, disconnect, verify, replace, or otherwise alter this number during Phase 0 or Phase 1 development. Use a Meta test number or a separately approved non-production asset.

## 3. Current Repository Configuration

### 3.1 PeraCRM WhatsApp settings

`peracrm_whatsapp_default_settings()`, `peracrm_whatsapp_get_settings()`, and `peracrm_whatsapp_save_settings()` are in `wp-content/plugins/peracrm/inc/whatsapp.php`. The admin form is `peracrm_render_whatsapp_page()` in `inc/admin/pages/whatsapp.php`; `admin_post_peracrm_save_whatsapp_settings` calls `peracrm_handle_whatsapp_settings_save()` in `inc/admin/actions.php`. Save access is `peracrm_admin_user_can_manage()` plus nonce `peracrm_whatsapp_settings`.

All six keys are members of one serialized WordPress option row, `peracrm_whatsapp_settings`. `update_option(..., false)` requests **non-autoload** on creation. WordPress may retain an existing row's prior autoload state when merely updating it, so the live row must still be inspected.

| Key / value | Read in | Written in | Expected format | Sensitive? | UI exposure | Webhook requirement now | Phase 1 disposition |
|---|---|---|---|---|---|---|---|
| `peracrm_whatsapp_settings.enabled` | `inc/whatsapp.php`; `inc/rest/whatsapp.php`; admin page | `peracrm_whatsapp_save_settings()` | checkbox-like `0`/`1` | No | Editable and diagnostic status | **Yes** for GET and POST; false returns 403 | **Retain**, but default off and add health gates |
| `.phone_number_id` | settings/admin; `PeraCRM_Whatsapp_Meta_Provider::send()` | same save function | Meta numeric/string Phone Number ID | Identifier, not normally secret | Editable and printed in full | **No** for inbound; outbound alert provider requires it | **Retain**, validate format; use as inbound allow-list too |
| `.access_token` | settings/admin; outbound provider | save function preserves current token when input blank | opaque bearer token | **Yes—high** | Password input blank; masked diagnostic | No inbound requirement; outbound requires it | **Replace DB-first handling** with environment constant/secret injection; retain migration fallback temporarily |
| `.verify_token` | settings/admin; GET handler | save function preserves current token when input blank | operator-generated opaque string | **Yes—credential** | Blank text input with masked placeholder; masked diagnostic | **Yes** for GET challenge | **Retain concept**, move to environment secret; never reveal after save |
| `.graph_api_version` | settings/admin; outbound provider | save function | string such as `v22.0`; default is `v22.0` | No | Editable and displayed | No inbound requirement | **Retain**, validate against a supported deployment-approved value; do not assume default remains current |
| `.test_mode` | settings/admin only | save function | `0`/`1` | No | Editable | **No effect** | **Deprecate/replace** with explicit environment and hard isolation; never imply safety |

The same file stores operational diagnostic option `peracrm_whatsapp_last_diag` (`last_received_at`, `last_status`, `last_error`) through `peracrm_whatsapp_set_diagnostic()` with autoload false requested. It is displayed on the admin WhatsApp page. Error text may contain exception details and needs redaction in Phase 1.

Per-message best-effort locks use dynamically named options `peracrm_wa_msg_lock_{md5(WAMID)}`, created with autoload false and a 120-second TTL. They are not credentials or configuration. They do not replace durable event idempotency.

### 3.2 Values specifically requested in this assessment

| Concept | Existing equivalent | Repository status |
|---|---|---|
| Enabled | `peracrm_whatsapp_settings.enabled` | Present |
| Phone Number ID | `.phone_number_id` | Present; outbound-only use today |
| Access token | `.access_token` | Present; plaintext in the WP option; outbound-only |
| Verify token | `.verify_token` | Present; GET verification |
| Graph API version | `.graph_api_version` | Present; outbound-only; hard default `v22.0` |
| Test mode | `.test_mode` | Present but behaviourally inert |
| App Secret | None for WhatsApp | Missing; consequently no POST HMAC check |
| WABA ID | None | Missing |
| Meta Business ID | None | Missing |
| Meta App ID | None | Missing |
| Environment | None | Missing; `test_mode` is not an environment boundary |
| Webhook URL | Computed with `rest_url('peracrm/v1/whatsapp/webhook')` | Display-only, not stored |

### 3.3 Related but separate configuration

* **CURRENT:** theme option `pera_whatsapp_number` is registered in `wp-content/themes/hello-elementor-child/inc/admin/site-settings.php` and read in `inc/whatsapp-helpers.php`. WordPress `register_setting()` does not explicitly set an autoload policy here; confirm the live option row. It controls website `wa.me` destinations, not Cloud API webhook identity.
* **CURRENT:** `PERACRM_TARGET_BLOG_ID` controls PeraCRM target-blog operations; `PERACRM_WHATSAPP_LOGS_BLOG_ID` can separately select the website click-log blog. Neither identifies a Meta account.
* **CURRENT:** Facebook Leads has an established environment-override pattern in `inc/integrations/facebook-leads/settings.php`: `PERACRM_FACEBOOK_LEADS_ENABLED`, `...VERIFY_TOKEN`, `...APP_SECRET`, and `...PAGE_ACCESS_TOKEN` override option values. Push also requires `PERACRM_VAPID_*` constants. WhatsApp does not yet follow either pattern.
* **RECOMMENDATION:** Phase 1 should use dedicated `PERACRM_WHATSAPP_*` constants (or a deployment secret manager injected through `wp-config.php`/environment) with constants taking precedence. Do not reuse Facebook Leads secrets.

### 3.4 Credential scan result

**CURRENT:** targeted searches for WhatsApp/Meta secret names and common token patterns found configuration field names and placeholder/default code, but no actual WhatsApp credential value in tracked source. No credential is included in this document.

**LIMIT:** this does not inspect the live database, hosting environment, GitHub secret store, Meta UI, deleted branches, or every historical object. A dedicated history scan by an authorized security owner remains advisable before activation. If a real credential is later found committed, record only its file/path and pattern, classify it **critical**, revoke/rotate it outside Git, and purge history under an incident plan—never paste it into an issue or PR.

## 4. Current Webhook State

### 4.1 Registration and GET verification

**CURRENT — confirmed flow**

1. `inc/bootstrap.php` loads `inc/rest/whatsapp.php` and `inc/rest.php`.
2. `peracrm_rest_register_whatsapp_routes()` is attached to `rest_api_init` (and is also called through `peracrm_rest_register_routes()` with a static duplicate guard).
3. Namespace/route: `peracrm/v1` + `/whatsapp/webhook`; readable and creatable methods both have public permission callbacks.
4. GET callback `peracrm_rest_whatsapp_verify_webhook()` first requires `enabled`; otherwise JSON 403.
5. It accepts WordPress-normalized `hub_mode`, `hub_verify_token`, `hub_challenge`, with dotted-name fallbacks.
6. It requires mode `subscribe`, non-empty token/challenge, then calls `hash_equals(saved verify token, supplied token)`.
7. Missing parameters produce 400; mismatch produces 403; both update diagnostics. Success updates diagnostics and returns a response containing the challenge.
8. `peracrm_rest_whatsapp_serve_verify_challenge()` on `rest_pre_serve_request` converts a successful response for this exact GET route into plain-text challenge output, as Meta expects.

The GET logic does not explicitly reject an empty saved verify token before `hash_equals`; a non-empty request token cannot equal an empty saved token, but Phase 1 health checks should explicitly require configured secrets.

### 4.2 POST delivery and side effects

**CURRENT — confirmed flow**

1. `peracrm_rest_whatsapp_receive_webhook()` returns 403 if disabled.
2. It calls `$request->get_json_params()`; non-array input is 400.
3. An object other than `whatsapp_business_account` is acknowledged as ignored with 202.
4. There is **no** body-size limit, raw-body capture, `X-Hub-Signature-256` lookup/HMAC comparison, App Secret, timestamp/replay window, entry/WABA allow-list, or `metadata.phone_number_id` allow-list.
5. The entire synchronous processor runs inside `peracrm_with_target_blog()`.
6. `peracrm_whatsapp_process_inbound_payload()` iterates entries/changes/messages. It ignores statuses and does not require change field `messages`.
7. It takes the first contact profile name for all messages in that change; normalizes `message.from` with Turkish defaults; supports text body, and stores `[type]` for every non-text type. It does not retrieve media or normalize referral, context/reply, errors, timestamps, pricing, status, or display-phone metadata.
8. It pre-checks WAMID, acquires an option lock when WAMID exists, checks again, searches `_peracrm_phone` and `crm_phone`, and directly creates a published `crm_client` if unmatched.
9. Client creation writes phone/source/status/advisor/name postmeta and a `client_created` activity. This occurs **before** message insertion and bypasses `peracrm_find_or_create_client_by_identity()` / `peracrm_ingest_enquiry()`.
10. It inserts `{CRM prefix}peracrm_whatsapp_messages`; `whatsapp_message_id` has a unique index but is nullable. It stores a raw subset containing the entry, change, and message.
11. For an inserted row linked to a client, it writes `whatsapp_inbound`. For a newly created client it also writes `lead_created` and `enquiry`, then calls `peracrm_enquiry_notifications_dispatch()`.
12. Success updates `peracrm_whatsapp_last_diag`, writes an `error_log` summary, and returns 200 with a processed count. A thrown `Throwable` updates/logs the exception and returns 500. Many individual write failures return zero/continue rather than throw, so a 200 can follow partial side effects.

### 4.3 Response, retries, idempotency and logging

| Concern | CURRENT behaviour | Readiness consequence |
|---|---|---|
| Acknowledgement | Only after synchronous client/message/activity/notification work | Meta timeout/retry exposure; slow downstream notification can delay response |
| Meta retries | No retry scheduler or delivery-attempt record; 500 invites Meta retry, 200 ends delivery | Cannot reliably distinguish accepted, processed, partial, or dead-lettered events |
| Idempotency | Unique nullable WAMID plus pre-check and transient option lock | Helps message duplicates only; no event/status idempotency and client can be created before insert failure |
| Logging | `error_log` summary; context drops keys exactly named `access_token`/`verify_token`; diagnostic option stores latest status/error | No structured event trace/retention; redaction list is incomplete for future payload/secrets |
| Database writes | `crm_client` post/postmeta, `{prefix}peracrm_whatsapp_messages`, `{prefix}crm_activity`, notification log/provider effects | Public unauthenticated forged POST can create durable CRM data when enabled |
| Account validation | Only top-level object string | Payload can name any WABA/phone and still process |

### 4.4 Exposure decision

**NO-GO.** The endpoint is not safe to expose to Meta or any untrusted sender today.

Exact blockers:

1. **Critical:** no Meta `X-Hub-Signature-256` verification with App Secret.
2. **Critical:** enabling it exposes unauthenticated CRM client creation and message/activity writes.
3. **High:** no WABA/entry and Phone Number ID allow-list.
4. **High:** no immutable durable event acceptance before side effects; synchronous response path.
5. **High:** direct, race-prone phone identity/client creation is not internationally safe.
6. **High:** partial writes can be acknowledged; retry and processing state are not modeled.
7. **High:** parser ignores important event classes and semantics.
8. **Medium:** no explicit payload size/complexity limits or event retention policy.
9. **External blocker:** live ownership, subscription, endpoint/network, and number state are unknown.

## 5. Current Phone Number Configuration

Repository-wide exact searches covered `905452054356`, `+905452054356`, `05452054356`, and `5452054356`.

| File / origin | Occurrence | Classification | Effect |
|---|---|---|---|
| `wp-content/themes/hello-elementor-child/inc/whatsapp-helpers.php` | `pera_get_default_whatsapp_number()` returns `905452054356` | **Fallback; production-facing; CRM lead CTA related** | Used by `pera_get_whatsapp_number()` when option is blank; feeds `pera_get_whatsapp_url()` |
| `wp-content/themes/hello-elementor-child/inc/admin/site-settings.php` | example `905452054356` | **Test/example; display only** | Admin instructions; same page manages option `pera_whatsapp_number` |
| `wp-content/themes/hello-elementor-child/page-contact.php` | `tel:+905452054356`, displayed `+90 545 205 43 56` | **Production configuration; display/outbound telephone destination** | Direct telephone link; independent of WhatsApp option |
| `wp-content/plugins/peracrm/inc/notifications/enquiry-notification-service.php` | production `$whatsapp_recipient = '+905452054356'` | **Production configuration; notification-related; outbound destination** | Every enquiry notification attempts a Meta alert to this recipient |
| same notification service | sample-test send to `+905452054356` | **Test path; notification-related; outbound destination** | Admin enquiry test targets the production-facing number |
| `docs/whatsapp-crm-integration-audit.md` | documents fallback | **Documentation** | No runtime effect |

No `05452054356`-only or `5452054356`-only runtime occurrence was found beyond substrings of the values above.

**CURRENT:** website WhatsApp CTA destination is a **single settings change** (`pera_whatsapp_number`) only when the theme option is populated. The hard-coded fallback means a blank/deleted option reverts to this number. The contact telephone link and PeraCRM notification recipient remain independent hard-coded values.

**Answer for a hypothetical future number change:** today it would require **multiple settings/code changes**, not a database migration: update the theme option, code fallback/contact display, and notification destinations. Existing click/message/client records should remain historical; changing their stored numbers would be an inappropriate migration. This assessment changes none of them.

## 6. Meta Identifier Map

| Meta concept | Meaning / avoid confusing with | Stored now? | Phase 1 required? | Secret? | Recommended storage | Safe in CRM UI? |
|---|---|---|---|---|---|---|
| Business Portfolio / Business Manager ID | Owner/container for assets; not WABA ID | No | Required for ownership confirmation, not webhook cryptography | No | Non-autoload option/deployment inventory | Yes, full to admins |
| WABA ID | WhatsApp Business Account containing numbers/templates | No | **Yes**, inbound allow-list/diagnostics | No | Validated non-autoload option or deploy config | Yes, full to admins |
| WhatsApp business phone number | Human/E.164 number customers use | Theme option/fallback and other hard-coded uses; not an authoritative Meta mapping | **Yes**, acceptance checks | PII/business contact, not credential | Normalized non-secret config; display masked/full by role | Yes for authorized CRM staff |
| WhatsApp Phone Number ID | Meta object ID used in Graph `/PHONE_NUMBER_ID/messages` and webhook metadata | Yes, `.phone_number_id` | **Yes** | No | Validated non-autoload config; environment-specific | Yes, admins |
| Meta App ID | App container subscribing to webhooks | No | **Yes** for provenance/operations | No | Deploy config/non-autoload option | Yes, admins |
| Meta App Secret | HMAC key for webhook signature validation | No | **Yes—security blocker** | **Yes, critical** | Hosting secret manager/environment-defined constant; never Git/normal UI | Only “configured/not configured” and rotation metadata |
| System user / access token | Principal credential authorizing Graph calls | Access token only, in `.access_token`; system user identity absent | Not needed merely to receive POST; required for Graph calls/media/outbound/management diagnostics | **Yes, critical** | Least-privilege system-user token via secret manager/environment constant | Never value; configuration status only |
| Verify token | Operator-created shared value used only for GET subscription handshake | Yes, `.verify_token` | **Yes** for callback verification | Treat as secret | Secret manager/environment constant | Never value; configured status only |
| Graph API version | Version segment for Graph requests | Yes, `.graph_api_version` | Required for any Phase 1 diagnostic Graph call; not for incoming POST parsing | No | Validated deployment setting | Yes |
| Webhook callback URL | Public HTTPS WordPress REST URL | Computed, not stored | **Yes** before staging subscription | No, but avoid exposing internal staging hostname broadly | Derived from canonical environment URL; optionally expected-host config | Yes to admins |

**EXTERNAL:** no identifier value other than the candidate human phone number is known from repository evidence. In particular, do not infer a Phone Number ID from the phone digits.

## 7. WhatsApp Business App / Coexistence Assessment

### Repository fact versus external fact

**CURRENT:** the code implements an early Cloud API webhook/provider. It contains no onboarding flow, Embedded Signup, Coexistence API, account eligibility query, history synchronization, linked-device inventory, or Business App state. Therefore it cannot determine eligibility or operational effects for **+90 545 205 4356**.

**EXTERNAL:** Meta controls Coexistence availability and may vary availability, onboarding, history synchronization, supported features, webhook event coverage, country/account constraints, and UI terminology. Confirm in the actual account and current official Meta materials, including the [Cloud API overview](https://developers.facebook.com/docs/whatsapp/cloud-api/overview), [webhooks documentation](https://developers.facebook.com/docs/whatsapp/cloud-api/webhooks), and current Business App onboarding/Coexistence guidance shown for the account. Do not treat third-party BSP marketing pages as proof of eligibility.

### Read-only eligibility and impact checklist

The account owner should answer, with screenshots/IDs but no secrets:

- [ ] Is **+90 545 205 4356** using ordinary WhatsApp Messenger, WhatsApp Business App, a BSP/on-premises API, Cloud API, or an unknown combination?
- [ ] What Business App version and mobile OS are in use? Is the app current enough for any offered Coexistence flow?
- [ ] Is the number already linked to a Meta Business Portfolio? Record owner name and ID.
- [ ] Does a WABA already contain the number? Record WABA name/ID and phone status.
- [ ] Does an existing Meta App own or subscribe to the WABA? Record App name/ID and owner.
- [ ] Is the phone already registered in Cloud API or controlled by a BSP? Record evidence only; do not release/migrate it.
- [ ] Does the **actual account UI** explicitly show Business App + Cloud API Coexistence as available for this number?
- [ ] Is Business Portfolio verification complete? Are display-name review, payment, quality, messaging-limit, or policy states blocking?
- [ ] Who is an administrator of each Business Portfolio, WABA, App, and system user? Are they the expected legal Pera owner?
- [ ] Which mobile phones, WhatsApp Web/Desktop sessions, companion devices, and staff workflows are currently linked?
- [ ] Does Meta's presented onboarding warning say any linked devices will be logged out, limited, or need relinking?
- [ ] Would onboarding preserve Business App use and the exact existing number? Obtain an explicit account-specific answer before any action.
- [ ] What happens to existing device-only message history and contacts? Is backup required? What history window, directions, types, and limits synchronize?
- [ ] Which new inbound messages/events reach Cloud API webhooks under Coexistence?
- [ ] Are messages sent in the Business App echoed to Cloud API/webhooks, and with which status/identity fields? Validate with a non-production pilot, not assumption.
- [ ] Are Cloud API outbound messages visible in the Business App? What attribution/advisor identity is retained?
- [ ] Are message edits/deletes/reactions, calls, groups, catalog/order events, disappearing messages, media, and status transitions supported?
- [ ] Are Turkey, business category, account age, existing BSP relationship, two-step verification, policy/quality status, or current number setup restrictions shown?
- [ ] Does the flow require a number migration, OTP, PIN, business verification, payment setup, BSP release, downtime, or terms acceptance? **Stop and escalate; do not perform it during discovery.**

**RECOMMENDATION:** eligibility evidence must be captured read-only. Any onboarding button, OTP, registration, migration, disconnect, BSP release, or webhook subscription is a later separately approved change window.

## 8. Meta Account Discovery Checklist

This is a non-developer, **read-only** walkthrough. Menu labels may appear as “Business Portfolio” or “Business Manager,” and WhatsApp assets may appear under Business Settings, Accounts, WhatsApp Accounts, WhatsApp Manager, or Meta for Developers.

### Business Suite / Business Settings

- [ ] Sign into the known Pera administrator account; note which login/account was used (do not share its password).
- [ ] Open **Settings / More business settings / Business info**. Record Business Portfolio **name**, **ID**, legal owner, primary Page, and business **verification status**.
- [ ] Under **Users → People/Partners/System users**, record names/IDs, roles, owning organization, and assigned WhatsApp/App assets. Do not generate tokens or change access.
- [ ] Under **Accounts → WhatsApp accounts**, record every WABA **name**, **ID**, owner Portfolio, and assigned people/partners.
- [ ] Flag duplicate or unfamiliar portfolios/WABAs; do not choose one by name alone.

### WhatsApp Manager

- [ ] Open the relevant WABA and record WABA name/ID, timezone/currency if shown, and policy/account status.
- [ ] Under **Phone numbers**, locate **+90 545 205 4356** exactly. Record displayed number, **Phone Number ID**, display name and display-name review status.
- [ ] Record phone registration/API status, connection status, messaging limit/tier, **quality rating** where shown, and two-step-verification status (not its PIN).
- [ ] Record whether the UI labels the number as Business App, Cloud API, Coexistence, BSP-managed, migrated, pending, disconnected, or another state.
- [ ] Under **Message templates**, export a names-only inventory: template name, language, category, status, last update. Do not edit or submit templates.
- [ ] Capture the account-specific Coexistence availability/status and any informational prerequisites/warnings without starting onboarding.

### Meta for Developers

- [ ] In **My Apps**, list relevant App **name**, **App ID**, owning Business Portfolio, app mode/type, and the presence of the WhatsApp product. Do not expose App Secret.
- [ ] In **WhatsApp → API Setup**, record associated WABA, test/production Phone Number IDs, API setup status and any configuration warnings. Do not send a test to the Pera number.
- [ ] In **WhatsApp → Configuration / Webhooks**, record callback hostname/path, verification/subscription status, subscribed object and fields, and last delivery/error summary if shown. Do not click verify/resubscribe.
- [ ] Record every callback/subscriber/BSP already consuming the WABA, if visible. Unknown integrations are a stop condition.
- [ ] Under App roles/settings, record administrators/developers and App ID; report only whether App Secret exists, never reveal it.
- [ ] In system-user/token views, record system user name/ID, owner, assigned assets, token existence/expiry and permissions. Do not create, extend, copy, or paste a token.

Return screenshots with secret fields hidden and a dated table mapping `Business ID → WABA ID → Phone Number ID → +90 545 205 4356 → App ID`.

## 9. Safe Identifiers vs Secrets

### Safe identifiers to share in the implementation ticket/Codex session

Share only after confirming the correct owner/environment:

* Business Portfolio name and ID
* WABA name and ID
* Phone Number ID and the already-known business phone number
* Meta App name and App ID
* Graph API version selected for the environment
* Public callback URL
* Non-secret status labels: verification, display-name, connection, quality, template names/statuses, subscribed webhook field names, Coexistence availability result

These identifiers are not authentication credentials, but they still reveal account topology. Keep them in an access-controlled engineering ticket where practical.

### Secrets — do not paste into Codex, GitHub, PRs, chat, screenshots, logs, or this document

* Meta App Secret
* temporary, long-lived, or permanent access tokens
* system-user tokens and token-debug output containing token material
* verify token (treat it as a credential)
* two-step-verification PINs, OTPs, backup/recovery codes
* private keys, passwords, session cookies, signed request material

**RECOMMENDATION:** production operations should inject WhatsApp secrets through the hosting secret manager or environment-specific `wp-config.php` constants outside Git. Mirror the repository's Facebook Leads constant-override approach and push `PERACRM_VAPID_PRIVATE_KEY` precedent, but add strict precedence and “configured/source/rotation due” diagnostics. If a DB fallback is temporarily necessary, use a non-autoloaded option, restrict capability, never render the value, and plan removal/rotation. WordPress salts are not by themselves a managed encryption/rotation strategy.

## 10. Server / WordPress Readiness

### Repository findings

* **CURRENT:** route registration is standard WordPress REST. The callback URL is derived by `rest_url()`, so WordPress home/site URL, proxy HTTPS detection and permalink/REST routing must be correct.
* **CURRENT:** the repository contains no Cloudflare-specific config, WAF rule, web-server config, Basic Auth config, or production `wp-config.php`; their live state is unknown.
* **CURRENT:** the plugin header declares no minimum PHP. Code uses `Throwable`, scalar/return type hints elsewhere, and bundled push Composer dependencies require PHP `>=8.0.2`; the live PHP/WordPress matrix must be tested rather than inferred.
* **CURRENT:** schema version is `PERACRM_SCHEMA_VERSION = 16`. Activation invokes `peracrm_maybe_upgrade_schema()`, while normal upgrades run only on authenticated requests with `manage_options` or `edit_crm_clients`. A first anonymous webhook does not deploy missing schema.
* **CURRENT:** PeraCRM storage uses `peracrm_with_target_blog()` / `peracrm_table()`. `PERACRM_TARGET_BLOG_ID` may redirect CRM writes in multisite; website click logs have a separate blog-resolution path.
* **CURRENT:** `PeraCRM_Whatsapp_Meta_Provider` calls `https://graph.facebook.com/{version}/{phone_number_id}/messages` through `wp_remote_post()` with a 12-second timeout. Inbound processing can invoke it via enquiry notifications. Media GET is not implemented.
* **CURRENT:** WP-Cron exists for push digests, with diagnostics for `DISABLE_WP_CRON`; there is no WhatsApp worker/queue today.

### Live operator checklist

- [ ] From an external network, confirm canonical callback URL resolves via public DNS and valid HTTPS chain; no redirect loop, mixed hostname, expired certificate, or TLS interception.
- [ ] Confirm anonymous GET/POST can reach only the intended REST route without Basic Auth, maintenance mode, IP login gate, cookie challenge, CAPTCHA, or HTML redirect. Do not enable the integration to test this.
- [ ] Confirm `/wp-json/` and the route are not disabled by a security plugin or `rest_authentication_errors` policy.
- [ ] Inventory CDN/cache/WAF/reverse proxy (including Cloudflare if used). Bypass caching for webhook requests; preserve request body and `X-Hub-Signature-256`; allow legitimate Meta traffic without trusting spoofable IP headers.
- [ ] Set strict but compatible body/time/rate limits. Confirm WAF does not rewrite JSON, strip signature headers, cache GET challenges, or block Meta retries.
- [ ] Confirm production WordPress/PHP/database versions, JSON/mbstring/hash support, timezone, memory/execution limits, and supported security updates.
- [ ] Confirm the PeraCRM plugin is active on the correct site/network and the REST route resolves on the expected blog.
- [ ] Record `is_multisite()`, current blog ID, `PERACRM_TARGET_BLOG_ID`, table prefix, and expected CRM blog. Validate switch/restore behaviour in staging.
- [ ] Run the authorized schema deployment path before delivery; verify schema version/table existence and DB user `CREATE/ALTER/INSERT/UPDATE` permissions for deployment.
- [ ] Confirm a reliable real system cron or approved queue runner for Phase 1/2; do not depend on low visitor traffic. Monitor stuck jobs and dead letters.
- [ ] Confirm outbound TCP 443/DNS/CA validation to `graph.facebook.com`; no firewall/proxy blocks. Least-privilege allow-list changes belong to server operations, not this PR.
- [ ] Establish protected structured application logging, retention, alerting and correlation IDs. Verify logs are not public and redact tokens, signatures, message bodies and phone numbers.
- [ ] Confirm backups, restore drill, DB capacity, raw-event retention/deletion, privacy access controls, incident owner and rollback procedure.
- [ ] Verify staging has a distinct hostname/database/secrets/test Meta asset and cannot accidentally address the production number.

## 11. Staging Strategy

### Existing test/diagnostic capability

**CURRENT:** `test_mode` is only stored/displayed and changes no code path. The WhatsApp admin page displays endpoint, enabled state, masked token presence, Graph version, latest diagnostic, table/message count, recent inbound messages, notification logs, and a **Send test WhatsApp + email alert** form. That alert uses the configured production-capable provider and hard-coded Pera recipient; it is not a webhook fixture test and must not be used for Phase 1 development. There is no signed payload generator, parser fixture suite, webhook event replay tool, Meta test-number isolation, or WhatsApp health gate.

### Safe development sequence

1. **Local/unit:** capture sanitized representative Meta payload fixtures; create deterministic valid/invalid `sha256=` signatures from test-only App Secrets; test raw-byte HMAC, malformed/oversized bodies, missing headers, object/account/phone mismatch, replay, DB failure and redaction. No network or live secrets.
2. **Integration:** run WordPress/database tests for route response, target-blog table, immutable event uniqueness, state transitions and fast acknowledgement. Assert no `crm_client`, message, activity, or notification side effect occurs in the transport request.
3. **Staging endpoint:** use public HTTPS on a separate database with environment hard lock (`staging`) and a distinct test secret. Confirm proxy/WAF preserves raw bytes/header and schema deploys before traffic.
4. **Meta test asset:** where Meta currently provides a test number/test WABA, subscribe only that asset to staging. Alternatively use a separately owned non-production number approved by Pera. Never register or migrate **+90 545 205 4356** for testing.
5. **Acceptance:** validate legitimate/replayed/invalid deliveries, Meta retry behaviour, event ordering, latency, logs, alerts, retention, worker pause/replay and rollback. Reconfirm zero production recipients.
6. **Production later:** connect the production App/WABA/Phone Number ID only after Phase 1 acceptance, account-owner approval, Coexistence decision, backups/change window and activation GO criteria.

**RECOMMENDATION:** replace `test_mode` with immutable environment identity plus allow-lists. A UI checkbox must never be capable of turning staging credentials into production traffic.

## 12. Phase 1 Entry Criteria

### Required before coding can begin

**GO only when all are true:**

- [ ] Scope is approved as transport/security only; existing production webhook remains disabled/unsubscribed.
- [ ] Candidate production number is confirmed as **+90 545 205 4356**, with an explicit promise not to onboard/change it during development.
- [ ] Correct Business Portfolio owner and accountable Pera/Meta administrators are identified.
- [ ] Read-only mapping of Business ID, WABA ID, Phone Number ID, App ID and number is obtained, with unknown/conflict flags.
- [ ] Coexistence is recorded as **eligible, unavailable, or still unknown**; coding may proceed while unknown because no production connection is needed, but no operating-model assumptions may enter code.
- [ ] Secret naming/injection and rotation ownership are approved; developers receive test secrets through a non-Git channel only.
- [ ] Sanitized fixture set, retention/redaction requirements, target-blog rules, schema migration pattern and queue approach are agreed.
- [ ] A staging/test asset strategy exists that cannot touch the production number.

### Required before connecting a staging webhook

- [ ] Separate HTTPS staging WordPress/database and explicit environment hard lock are verified.
- [ ] Test App/WABA/Phone Number ID and App Secret/verify token are separate from production and injected securely.
- [ ] Signature, missing/invalid signature, account/phone mismatch, payload limits, idempotency, DB outage and redaction tests pass.
- [ ] Event table schema is deployed through an authorized path and health check passes.
- [ ] WAF/CDN/REST/Basic Auth exception is narrowly configured and raw body/signature preserved.
- [ ] Fast acknowledgement and asynchronous state/retry/dead-letter monitoring meet an agreed SLO.
- [ ] No automatic client creation or outbound notification is reachable from the Phase 1 receiver.

### Required before production activation

- [ ] Account owner has confirmed exact Portfolio/WABA/App/Phone mapping and no unknown BSP/webhook conflict.
- [ ] Coexistence/account eligibility and effects on Business App, linked devices and history have been verified in the live account; staff accept the operating model.
- [ ] Business verification, number/display-name/API status, quality/policy and permissions are acceptable.
- [ ] Production App Secret/verify token and least-privilege token (only if needed) are securely provisioned and rotation/revocation tested.
- [ ] Production endpoint/network/schema/cron/logging/alerts/backups/privacy/retention/incident response are verified.
- [ ] Staging acceptance, load/retry/replay tests, security review, rollback rehearsal and change-window approval are complete.
- [ ] Production allow-list contains only approved WABA and Phone Number ID; activation is initially transport-only with processing safely controlled.

Any unchecked item in its stage is **NO-GO** for that stage. Unknown Coexistence does not block isolated code development; it absolutely blocks production onboarding/activation.

## 13. Production Activation Risks

| Risk | Severity | Trigger | Consequence | Mitigation | Can Phase 0 resolve? |
|---|---|---|---|---|---|
| Lose Business App access | Critical | Unsupported migration/onboarding assumption | Staff lose primary customer channel | Account-specific Coexistence proof, backup/change window, no Phase 0 changes | **No**; identify only |
| Linked devices disrupted | Critical | Registration logs out/restricts companions | Advisors cannot respond | Device inventory, Meta warning review, staff test/rollback | No |
| Customer messaging interruption | Critical | Wrong migration, webhook, quality/policy or token action | Lost/delayed sales enquiries | Non-production pilot, phased activation, incident owner | No |
| Forged webhook creates CRM data | Critical | Current endpoint enabled without HMAC | Fraudulent clients/messages/notifications | App Secret HMAC before any connection | **Yes: blocker identified; fix is Phase 1** |
| Wrong Meta business/WABA/App | Critical | Similar names or partner-owned asset selected | Data/control routed to wrong owner | Dated ID mapping and dual approval | Partly |
| Wrong phone number/Phone Number ID | Critical | Human number confused with object ID/test asset | Wrong customers/data affected | Exact mapping plus allow-list and production lock | Partly |
| Unsupported Coexistence assumption | Critical | Product eligibility/event echo inferred | Missing history/messages or workflow failure | Live account verification and test matrix | No |
| Secrets exposed | Critical | Tokens/App Secret in Git/UI/logs/chat | Account compromise/forged delivery | Secret manager/constants, least privilege, redaction, rotation | Pattern chosen in Phase 0; implementation later |
| Duplicate CRM clients | High | Concurrent first messages or variant phones | Fragmented history/advisor work | No auto-create in Phase 1; canonical identity service later | No |
| Incorrect international match | High | Turkish-centric normalization applied globally | Wrong person linked or missed match | E.164 identity migration/conflict queue | No |
| Message loss | High | Synchronous failure followed by misleading 200; unsupported event | Unrecoverable conversation gap | Immutable event-first acceptance, worker state/dead letter | Phase 1 design only |
| Webhook retries overload/duplicate effects | High | Slow/500 response | Repeated side effects and load | Fast ACK, event uniqueness, idempotent worker | Phase 1 |
| Duplicate messages/events | High | Meta replay, nullable/missing WAMID, status reorder | Repeated timeline/alerts | Event identity/hash plus semantic idempotency | Phase 1 foundation |
| Production used while testing | Critical | Inert `test_mode` trusted or shared credentials | Real customer/staff impact | Separate assets/secrets/DB; environment hard lock | Strategy resolved; enforcement Phase 1 |
| Partial client without message | High | Client insert succeeds, message insert fails | Orphan lead and false alerts | Event transaction/state; defer client creation | Phase 1/3 |
| WAF/cache alters/blocks webhook | High | Proxy strips header/rewrites body/caches response | Signature failures or delivery loss | Staging ingress test and narrow bypass | External verification |
| Missing schema/wrong multisite blog | High | Anonymous delivery precedes authorized migration | 500 or split data | Deployment health gate and target-blog test | Partly |
| Raw payload/PII retained insecurely | High | Indefinite event/message/log storage | Privacy/security exposure | Approved retention, access, encryption/backups, redaction | Policy in Phase 0; code later |
| Outbound alert targets Pera number during test | High | Admin test/provider invoked | Unwanted real WhatsApp messages | Do not use current test action; isolate provider | Yes operationally |

## 14. Recommended Phase 1 Scope

Phase 1 is **transport/security foundation only**, not a conversation inbox, outbound customer messaging, number onboarding, click attribution, identity merge, media pipeline, or production activation.

### In scope

1. Raw request-body capture with constant-time `X-Hub-Signature-256` HMAC-SHA256 verification using an environment-injected App Secret; reject missing/malformed/invalid signatures before parsing or writes.
2. Strict environment-specific allow-list for WABA/entry ID and `metadata.phone_number_id`; validate expected top-level object/change shape and apply body/collection limits.
3. Additive immutable `{prefix}peracrm_whatsapp_webhook_events` table: event ID/hash, WABA/phone identifiers, payload, received time, signature result/version, processing state/attempts/error/timestamps; unique event/hash keys.
4. Authenticate, durably insert once, and acknowledge quickly. Duplicate valid delivery should receive safe 2xx without duplicate downstream effects.
5. Separate worker/processing state with atomic claim, retry/backoff and dead-letter diagnostics. Phase 1 may stop after safe envelope classification; no automatic client creation is required.
6. Redacted structured logging and admin health: secret source/presence, environment, expected IDs, callback URL, table/schema, queue/cron, last accepted/rejected/processed event and age—never body/token/signature/phone in ordinary logs.
7. Additive schema migration/version bump and explicit deployment/rollback instructions.
8. Fixture tests for GET challenge, valid/invalid/missing signatures, byte changes, wrong WABA/phone/object, malformed/oversized JSON, replay/concurrency, DB failure, fast acknowledgement, target blog, redaction and state transitions.

### Explicitly out of scope

* Enabling/subscribing the current endpoint or modifying Meta/WhatsApp settings
* Registering/migrating/disconnecting/verifying **+90 545 205 4356**
* Rewriting client identity, automatic client creation, inbox/composer, outbound customer messaging, templates, media retrieval, website-click correlation, Coexistence onboarding, or historical import

### Repository files likely to change in Phase 1 (forecast only)

* `wp-content/plugins/peracrm/peracrm.php` — schema/version bump
* `wp-content/plugins/peracrm/inc/bootstrap.php` — load transport/event repository/worker
* `wp-content/plugins/peracrm/inc/rest/whatsapp.php` — raw-body authentication, allow-list, fast response
* `wp-content/plugins/peracrm/inc/whatsapp.php` — split configuration/diagnostic compatibility; prevent transport from invoking legacy synchronous processor
* `wp-content/plugins/peracrm/inc/schema.php` — additive deployment path
* new `wp-content/plugins/peracrm/inc/db/whatsapp_webhook_events_table.php` — immutable event schema/repository
* new service/worker files under `inc/whatsapp/` and possibly `inc/cron/` — validation and processing state
* `wp-content/plugins/peracrm/inc/admin/pages/whatsapp.php` and `inc/admin/actions.php` — safe configuration-status/health UX (not secret display)
* a dedicated WhatsApp health module or carefully scoped additions near `inc/health.php`
* new tests/fixtures in the repository's approved test location

`inc/db/whatsapp_messages_table.php`, notification provider/service, client services and theme files should not need behavioural changes for the narrow transport PR unless isolation requires an explicit guard; any expansion requires separate review.

## 15. Exact Information Required From Pera

### Safe information to return to engineering

- [ ] Confirmation that **+90 545 205 4356** remains the production customer number and must remain unchanged
- [ ] Current product: Messenger / Business App / BSP / on-prem API / Cloud API / unknown
- [ ] Business Portfolio name, ID, legal owner and verification status
- [ ] WABA name, ID and owner
- [ ] Number display name, Phone Number ID, connection/registration status, quality rating, messaging limit and display-name status
- [ ] Meta App name, App ID, owner, mode/type and WhatsApp product/API setup status
- [ ] Current callback URL/status, subscribed object/fields and any existing BSP/integration subscriber
- [ ] Account UI's Coexistence availability/status, prerequisites and warnings for this exact number
- [ ] Linked device inventory and required staff mobile/desktop workflow
- [ ] Account-specific history synchronization and inbound/outbound echo/event answers
- [ ] System user names/IDs/owners, assigned assets, token existence/expiry/permissions (**not token values**)
- [ ] Approved template inventory: names, languages, categories, statuses
- [ ] Live server topology: public hostname, CDN/WAF/security plugins/Basic Auth, multisite/blog IDs, PHP/WP/DB versions, cron, logs, outbound firewall
- [ ] Staging hostname/database owner and approved Meta test WABA/number strategy
- [ ] Privacy owner and raw event/message retention, deletion, access, backup and incident requirements
- [ ] Named Meta account owner, server operator, security/secret owner, CRM product approver and go-live incident contact

### Secrets to provision only through the approved secret channel

- [ ] Staging App Secret and independently generated verify token
- [ ] Production App Secret and verify token only near approved production connection
- [ ] Least-privilege system-user access token only if Phase 1 diagnostics truly require Graph access

Engineering needs to know that each secret is configured, its environment/owner/expiry/rotation date, and its allowed permissions—not its value in a ticket or PR.

## 16. Recommended Next Actions

1. Pera administrator completes the read-only Meta Account Discovery Checklist and returns the safe ID mapping plus redacted screenshots.
2. Operations completes the server checklist and establishes an isolated staging endpoint/database/cron/logging path.
3. Product/operations records whether continued Business App use is mandatory and obtains account-specific Coexistence eligibility/effect evidence; no onboarding action is taken.
4. Security approves `PERACRM_WHATSAPP_*` secret injection, rotation/revocation, logging/redaction and retention policy following existing repository constant patterns.
5. Engineering assembles sanitized Meta fixtures and a test asset that cannot address the production number.
6. Hold a GO/NO-GO review against Section 12. Safe code development may start while production Coexistence remains unknown, but staging and production gates remain separate.
7. Open a narrowly scoped Phase 1 PR implementing only Section 14. Keep the legacy webhook disabled and production Meta configuration untouched until a separately approved activation.

### Phase 0 disposition

* **Repository readiness:** **NO-GO for exposure; conditionally GO for isolated Phase 1 coding after the “before coding” checklist is complete.**
* **Meta account readiness:** **UNKNOWN—external discovery required.**
* **Server readiness:** **UNKNOWN—live verification required.**
* **Credential finding:** no actual WhatsApp credential was found in the checked-in source scan; live stores and full history remain outside this assessment.
