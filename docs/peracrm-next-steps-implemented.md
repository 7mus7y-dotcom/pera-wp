# PeraCRM next steps implemented

## What was added

- Added an explicit admin CRM load allowlist helper in `inc/bootstrap-modules.php`:
  - `pera_crm_is_allowed_admin_screen()`
  - `pera_crm_should_load_integration()`
- Updated CRM bootstrap gating so CRM integration loads only on `/crm/*` routes, CRM AJAX requests, and allowlisted `wp-admin` pages (`peracrm_*` and `tools.php?page=peracrm_diagnostics`).
- Added router hook visibility helper `pera_crm_router_hooks_registered()` in `inc/crm-router.php` for smoke diagnostics.
- CRM-capable users on non-CRM public pages do **not** trigger CRM integration loading.
- Added admin-only diagnostics page under **Tools → PeraCRM Diagnostics** with an AJAX-run smoke check endpoint:
  - route-gate smoke expectations (`non_crm_route_loads_crm`, `crm_route_loads_crm`)
  - router hook registration state for the current request
  - AJAX access notes and current-user capability check
- Added constant default:
  - `PERA_CRM_DEBUG_AJAX` defaults to `false` in theme `functions.php`.
- Added optional staging diagnostics behavior in CRM AJAX helper:
  - extra JSON `context` only when `WP_DEBUG && PERA_CRM_DEBUG_AJAX`
  - optional rejection-path `error_log` under same guard.
- Standardized CRM AJAX handler response schema in `inc/crm-client-view.php` for:
  - `peracrm_create_portfolio_token`
  - `pera_crm_upload_portfolio_floor_plan`
  - `pera_crm_save_portfolio_property_fields`
  - `pera_crm_property_search`

Error format:

```json
{ "ok": false, "code": "...", "message": "...", "context": { } }
```

Success format now includes `ok: true` and a `code`.

- Ensured strict AJAX action gating at handler start via `pera_crm_ajax_is_expected_action()`.
- Ensured handlers terminate via centralized `pera_crm_ajax_error()` / `pera_crm_ajax_success()` helpers.

## How to run diagnostics

1. Log in as an administrator.
2. Go to **Tools → PeraCRM Diagnostics**.
3. Click **Run diagnostics**.
4. Confirm output includes:
   - `routing_gate.non_crm_route_loads_crm = false`
   - `routing_gate.crm_route_loads_crm = true`

For unauthenticated AJAX verification, run in an unauthenticated browser session or curl:

```bash
curl -i "https://<site>/wp-admin/admin-ajax.php?action=pera_crm_property_search"
```

Expected: `403` with standardized JSON error.

## Staging-only AJAX diagnostics toggle

In `wp-config.php` (staging only):

```php
define( 'WP_DEBUG', true );
define( 'PERA_CRM_DEBUG_AJAX', true );
```

This adds non-sensitive rejection context (for example: current user id, nonce presence) and logs rejection summaries.

## `wp_ajax_nopriv` audit outcome

- Audited CRM modules and CRM AJAX handlers.
- No `wp_ajax_nopriv_*` hooks were found for CRM-prefixed handlers (`peracrm_*` / `pera_crm_*`).
- Existing `wp_ajax_nopriv_*` hooks in the theme are for non-CRM flows (property archive/favourites/enquiry nonce refresh) and were not changed.

## Regression test checklist

- Public pages unaffected (home, property archive).
- Logged-in CRM-capable user on non-CRM public pages does not load CRM integration or CRM router hooks.
- `/crm/*` routes still work.
- CRM AJAX endpoints still function normally.
- Diagnostics are off unless explicitly enabled via `WP_DEBUG && PERA_CRM_DEBUG_AJAX`.
