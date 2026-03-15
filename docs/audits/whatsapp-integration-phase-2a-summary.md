# WhatsApp Integration (Schema v9) — Implementation Summary

## Scope
This summary captures the WhatsApp inbound integration currently present in the latest branch commit.

## What was added

### 1) Core inbound WhatsApp logic
- Added `wp-content/mu-plugins/peracrm/inc/whatsapp.php`.
- Introduces:
  - settings read/save helpers (enabled, phone number id, access token, verify token, test mode)
  - diagnostics persistence (`last_received_at`, `last_status`, `last_error`)
  - phone normalization helper for matching (including Turkish-local assumptions)
  - client match-by-phone helper (`_peracrm_phone`, `crm_phone`)
  - fallback lead/client creation for unmatched inbound numbers
  - durable WhatsApp message storage helper
  - inbound payload processor that:
    - iterates webhook entries/changes/messages
    - extracts contact name and sender phone
    - stores inbound message rows
    - links to matched/new client where possible
    - logs concise CRM activities

### 2) Webhook endpoint
- Added `wp-content/mu-plugins/peracrm/inc/rest/whatsapp.php`.
- Exposes `peracrm/v1/whatsapp/webhook` with:
  - `GET` verification challenge handling
  - `POST` inbound ingestion handling
- Defensive behavior includes payload checks and non-fatal failure responses with diagnostics updates.

### 3) Database table for durable WhatsApp messages
- Added `wp-content/mu-plugins/peracrm/inc/db/whatsapp_messages_table.php`.
- Added table creation wiring in `wp-content/mu-plugins/peracrm/inc/schema.php` for:
  - `peracrm_whatsapp_messages`
  - indexes on `client_id`, `phone_e164`, `whatsapp_message_id`, `created_at`
- Bumped schema constant in `wp-content/mu-plugins/peracrm/peracrm.php` from `8` to `9`.

### 4) Admin settings/diagnostics UI
- Added `wp-content/mu-plugins/peracrm/inc/admin/pages/whatsapp.php`.
- Added submenu registration in `wp-content/mu-plugins/peracrm/inc/admin/pages.php`.
- Added admin-post save handler hook in `wp-content/mu-plugins/peracrm/inc/admin/admin.php`.
- UI shows endpoint URL, setting state, masked token visibility, last webhook diagnostic, and message count.

### 5) Bootstrap/registration wiring
- Updated `wp-content/mu-plugins/peracrm/inc/bootstrap.php` to load new WhatsApp modules.
- Updated `wp-content/mu-plugins/peracrm/inc/rest.php` to register WhatsApp REST routes.

## Behavior impact classification
- **Additive**: New inbound-only WhatsApp integration path, new table, new REST endpoint, new admin page.
- **Behavior-changing**: Inbound webhook requests can now create/link CRM leads/clients and write activity rows.
- **No explicit UI redesign**: Existing CRM screens remain intact; a focused submenu page is added.

## Operational notes
- Access token is intentionally masked in admin output.
- Message raw payload is persisted in dedicated durable storage for traceability.
- Inbound handling focuses on text-first and unknown-type-safe behavior.

## Manual verification checklist
1. Open CRM admin WhatsApp page and save valid settings.
2. Run webhook verification (`GET`) and confirm challenge response.
3. Send inbound test payload (`POST`) for an existing phone and verify link + activity.
4. Send inbound test payload from unknown phone and verify lead creation + activity + message row.
5. Confirm diagnostics update after success and failure paths.
6. Confirm existing front-end WhatsApp click logging remains unaffected.
