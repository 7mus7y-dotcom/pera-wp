# CRM Client Bugs and Nav Off-Canvas Pass

## Scope
- `/crm/client/{id}`
- CRM shared hero and side navigation

## Root causes by issue

### 1. Preferred contact handling
- The CRM profile UI rendered `preferred_contact` as a plain text field even though website enquiry ingestion already stores checkbox-style contact preferences from forms as a delimited string.
- `peracrm_client_update_profile()` only accepted a single sanitized token (`phone|whatsapp|email`), so multi-select source data was collapsed or discarded.
- Legacy values could contain `phone`, while the CRM requirement now uses `call`, so read/write normalization was missing.

### 2. CRM Status client type save
- The status panel rendered `peracrm_client_type`, but the front-end AJAX status handler only persisted party status fields (`lead_pipeline_stage`, `engagement_state`, `disposition`).
- The non-AJAX admin-post handler already saved `_peracrm_client_type`, which is why the bug was isolated to the AJAX path.

### 3. Advisor Notes delete
- Notes listing had no delete control.
- There was no shared repository helper to delete notes from either the database-backed notes table or the fallback post-meta storage.

### 4. Reminder Done action refreshes page
- Existing reminder forms still posted to `admin-post.php` without using the front-end panel-refresh AJAX flow.
- The client view already had a generic `pera_crm_client_action` AJAX endpoint, but reminder status transitions were not wired into it.

### 5. Create portfolio action broken
- Portfolio generation depends on `pera_portfolio_token_create_portfolio()`, which still lives in the theme portfolio-token helper.
- After CRM front-end ownership shifted into the plugin, the client-view AJAX handler had no compatibility bridge when that helper was not already loaded, so creation failed hard instead of loading the dependency safely.
- The UI also did not reveal the Update button after a newly generated portfolio link was returned.

### 6. Side nav layout below 1024px
- The current responsive rule simply collapsed the sticky right rail into normal document flow, which caused layout breakage.
- There was no dedicated toggle, overlay, or drawer state for tablet/mobile CRM navigation.

### 7. Create lead in nav
- Create lead existed in the hero, not in the shared navigation, so once the hero controls were removed the action needed a permanent nav home.

### 8. Hero extra nav buttons
- The shared CRM header still rendered a hero CTA and section button row even though the side nav is now the primary navigation surface.

## Affected files
- `wp-content/plugins/peracrm/inc/helpers.php`
- `wp-content/plugins/peracrm/inc/repositories/notes.php`
- `wp-content/plugins/peracrm/inc/frontend-data/crm-client-view.php`
- `wp-content/plugins/peracrm/inc/views/pages/crm-client.php`
- `wp-content/plugins/peracrm/inc/views/partials/crm-header.php`
- `wp-content/plugins/peracrm/inc/views/partials/crm-side-nav.php`
- `wp-content/plugins/peracrm/assets/frontend/crm.js`
- `wp-content/plugins/peracrm/assets/frontend/crm.css`
- `wp-content/plugins/peracrm/inc/admin/actions.php`

## Implementation decisions
- Preferred contact is now normalized to the existing meta key as a comma-delimited token list (`call,whatsapp,email`) to preserve website-form checkbox data without introducing a schema migration.
- Normalization maps legacy `phone` values to `call` for CRM display compatibility.
- Client profile now renders checkbox options and posts `peracrm_preferred_contact[]` through the existing AJAX profile save flow.
- Status save keeps party status persistence unchanged and additionally updates client type via `peracrm_client_update_profile()` inside the AJAX status branch.
- Notes deletion was added through the existing client-action AJAX endpoint plus a new repository delete helper that supports both SQL-table and fallback-meta storage.
- Reminder completion now uses the same client-action AJAX endpoint and panel HTML refresh pattern already used by other client-panel actions.
- Portfolio generation remains theme-compatible through a small plugin-side compatibility loader instead of duplicating the full theme portfolio subsystem in this pass.
- The mobile nav is implemented as an off-canvas drawer under 1024px while preserving the desktop sticky right rail at larger widths.
- Create lead was moved into the shared nav model so it appears in both desktop and off-canvas variants.
- Hero nav/CTA controls were removed entirely; the hero now only carries title/description plus existing client filters where relevant.

## Regression risks
- Any other UI that assumes `preferred_contact` is always a single token may display blank for multi-select records unless it is later updated to parse normalized CSV.
- Notes fallback deletion relies on synthetic per-record IDs derived from current ordering; this is safe for in-page deletion but should be kept in mind if other fallback note APIs later assume immutable IDs.
- Portfolio generation still depends on the theme helper file existing; this pass adds a safe compatibility bridge, but full plugin ownership would be a better long-term hardening step.
- The new mobile nav drawer uses front-end JS state. If CRM JS fails to load, the desktop nav still works on large screens, but mobile menu access will be degraded.

## Test checklist

### Client page
- [ ] Save profile with Preferred contact checkboxes selected.
- [ ] Save profile with no Preferred contact selected.
- [ ] Confirm legacy `phone` values render as `Call` checked.
- [ ] Save CRM Status and verify client type persists immediately.
- [ ] Add note still works.
- [ ] Delete note refreshes the notes panel in place.
- [ ] Add reminder still works.
- [ ] Mark reminder Done refreshes reminders in place without full reload.
- [ ] Link property still works.
- [ ] Create portfolio generates a link again.
- [ ] Portfolio Update remains available after generation.
- [ ] Deal create/update still works.
- [ ] Advisor reassign still uses confirmation dialog.

### Navigation / layout
- [ ] Desktop (`>=1024px`): sticky right nav remains visible.
- [ ] Desktop (`>=1024px`): Create lead is present in nav.
- [ ] Mobile/tablet (`<1024px`): toggle opens drawer.
- [ ] Mobile/tablet (`<1024px`): overlay and close button dismiss drawer.
- [ ] Mobile/tablet (`<1024px`): active nav state remains visible.
- [ ] Logs links remain role-gated.
- [ ] Hero no longer shows redundant nav buttons/CTA.

### General
- [ ] No JS console errors on CRM pages.
- [ ] No PHP warnings/fatals in the updated flows.
- [ ] Nonces and capability checks still gate profile, status, notes, reminders, portfolio, and advisor actions.
- [ ] No panel refresh regressions on existing AJAX-backed forms.
