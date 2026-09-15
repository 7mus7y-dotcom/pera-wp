# PeraCRM WhatsApp Meta App Review vertical slice

## Purpose and safety boundary

This is the first production-compatible text-message slice: Meta Cloud API → the existing PeraCRM WhatsApp webhook/table → an existing `crm_client`, plus authorised client-scoped read/send REST APIs and the client conversation panel. It is deliberately restricted to **Meta TEST assets**.

> **Do not migrate, disconnect, deregister, verify, onboard, or otherwise change the production WhatsApp Business App number during this phase.** Do not enter production credentials or identifiers in these settings. The Embedded Signup / Coexistence diagnostic launcher remains observational and separate; cancel any Meta screen that offers migration or disconnection.

The slice fails closed unless integration and TEST mode are enabled and the Phone Number ID, access token, verify token, and Meta App Secret are configured. No code path falls back to a website WhatsApp number.

## Required Meta test assets

Obtain from the Meta App's WhatsApp **API Setup** and App Settings screens:

* Meta test WABA ID (recommended inbound allow-list);
* Meta test Phone Number ID;
* test access token (or an appropriately scoped non-production system-user token);
* an operator-generated webhook verify token;
* that Meta App's App Secret;
* a recipient added to Meta's permitted test-recipient list;
* a deployment-approved Graph API version.

Never commit these values. Prefer environment/deployment constants in `wp-config.php`: `PERACRM_WHATSAPP_ENABLED`, `PERACRM_WHATSAPP_TEST_MODE`, `PERACRM_WHATSAPP_PHONE_NUMBER_ID`, `PERACRM_WHATSAPP_WABA_ID`, `PERACRM_WHATSAPP_ACCESS_TOKEN`, `PERACRM_WHATSAPP_VERIFY_TOKEN`, `PERACRM_WHATSAPP_APP_SECRET`, and `PERACRM_WHATSAPP_GRAPH_API_VERSION`. Constants override the non-autoloaded WordPress option. Secret fields are write-only in the UI and diagnostics show only Configured/Missing.

## Setup

1. Deploy and visit an authorised CRM/admin request so schema version 17 creates/evolves the existing `peracrm_whatsapp_messages` table.
2. Open **CRM Clients → CRM WhatsApp**. Confirm the prominent **META TEST MODE** warning.
3. Enter only the test Phone Number ID/WABA and secrets, select **Enabled** and **Require Meta TEST assets**, then save. Prefer constants above on shared environments.
4. In Meta, configure callback `https://YOUR-SITE/wp-json/peracrm/v1/whatsapp/webhook`, enter the same verify token, verify, and subscribe the test WABA to `messages`.
5. Ensure HTTPS/proxies preserve the raw request body and `X-Hub-Signature-256` header. POST HMAC is computed over the exact raw bytes before JSON parsing.
6. Create/select a test `crm_client` and store the permitted recipient as its Mobile/WhatsApp phone in full international form. Outbound sends derive the recipient exclusively from that client.

### Associating inbound test messages

Inbound senders are linked only by an exact existing canonical phone match; unknown numbers never create clients. Normally, set the test client's phone to the permitted recipient before the demo. If a message already arrived unlinked, an administrator may call `PUT /wp-json/peracrm/v1/whatsapp/associate` with a WordPress REST nonce and JSON `{ "client_id": 123, "wa_id": "15551112222" }`. This associates existing unlinked rows only and does not create or merge clients.

## App Review recording procedure

1. Log into PeraCRM as an administrator/manager and open CRM WhatsApp settings.
2. Show **META TEST MODE**, configured-state diagnostics (never reveal secrets), and the existing Embedded Signup launcher.
3. Launch Embedded Signup for the separate Coexistence demonstration; do not complete any production-number migration/disconnection path. Return to PeraCRM.
4. Open the prepared test CRM client. Show its WhatsApp conversation panel and test recipient phone.
5. Enter plain text and click **Send WhatsApp text**. The server validates user/client scope and phone, calls `/{TEST_PHONE_NUMBER_ID}/messages`, and persists only a successful response containing a WAMID.
6. Show delivery on the permitted test device and reply with plain text.
7. Meta delivers the signed event to the configured webhook. Click **Refresh** or wait up to ten seconds for polling; show the inbound reply in chronological order and its received timestamp/status.
8. Optionally show the CRM activity timeline entries and admin recent-message diagnostics.

A free-form text message can only be sent inside Meta's current customer-service-window rules. If the test environment requires initiating outside that window, first send/reply from the permitted device to open the window. This slice intentionally does not add templates.

## Security controls

* POST requires `X-Hub-Signature-256`, HMAC SHA-256 over raw bytes, and constant-time `hash_equals`; missing/invalid signatures receive 401.
* GET verification separately requires enabled TEST mode and constant-time verify-token comparison.
* One MiB request-body limit; authenticated JSON parsing; expected top-level object, `messages` field, test WABA (when configured), and exact Phone Number ID allow-lists.
* Text-only parser; WAMID required; existing unique index supplies durable duplicate suppression.
* Unknown senders remain unlinked; no webhook-driven client creation, merging, notifications, or media processing.
* Client-scoped REST permission checks enforce administrator/global manager access or assigned-adviser access. Cookie requests use WordPress REST nonces.
* Outbound phone comes from the selected CRM client; no arbitrary recipient endpoint. Credentials remain server-side.
* Provider failures do not create successful message rows; errors returned to browsers are generic and credentials/message bodies are not logged.

## Schema version 17

The existing message table is evolved non-destructively with `sender_wa_id`, `recipient_phone_number_id`, `message_status`, and `meta_timestamp`; an index is added for `sender_wa_id`. Existing `client_id`, `phone_e164`, direction/type/body, WAMID, created time, and unique WAMID index remain. Raw payload storage for this slice is `{}` to minimise unnecessary webhook/customer data retention.

## Intentionally out of scope

No production Coexistence onboarding, templates, media, global/adviser inbox, new-client creation, identity/merge service, attribution, search, unread counters, broadcasts, campaigns, assignment UI, history import, or production rollout. Status webhooks update the basic state when straightforward, but there is no full status/error history. The synchronous webhook/table architecture is a minimal slice, not the eventual durable event queue. International phone handling is limited; use explicit E.164 for test clients. Retention/privacy policy and operational retry/dead-letter tooling remain future work.
