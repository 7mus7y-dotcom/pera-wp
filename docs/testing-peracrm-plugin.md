# PeraCRM Plugin Migration Smoke Test Checklist

## Preconditions
- Standard plugin exists at `wp-content/plugins/peracrm/peracrm.php`.
- MU shim files are present at:
  - `wp-content/mu-plugins/peracrm.php`
  - `wp-content/mu-plugins/peracrm-loader.php`
- Permalinks are enabled (pretty URLs).

## Route checks
1. Open `/crm/` while logged in with CRM-capable user.
2. Open `/crm/new/` and confirm create-lead form renders.
3. Open `/crm/client/{id}/` for a known `crm_client` post.
4. Open `/crm/tasks/` and `/crm/pipeline/`.
5. Confirm unauthorized users are redirected/blocked.

## Create/update flow checks
1. Submit a new lead from `/crm/new/`.
2. Confirm redirect to `/crm/client/{new_id}/`.
3. Add note/reminder actions from client view and confirm persisted updates.
4. Verify portfolio token/property AJAX actions still function.

## Admin checks
1. Open `CRM Clients` CPT list and edit screen.
2. Confirm CRM submenu pages still load:
   - My Reminders
   - Work Queue
   - Pipeline
   - Client View
3. Verify `admin_post_peracrm_*` actions return expected redirects/notices.

## REST checks
1. `GET /wp-json/peracrm/v1/leads` with authenticated REST nonce.
2. `GET /wp-json/peracrm/v1/clients`.
3. `GET /wp-json/peracrm/v1/deals`.
4. Push endpoints:
   - `GET /wp-json/peracrm/v1/push/config`
   - `POST /wp-json/peracrm/v1/push/subscribe`
   - `POST /wp-json/peracrm/v1/push/unsubscribe`

## Service worker/push checks
1. Request `/peracrm-sw.js` and confirm JavaScript response.
2. Open CRM overview push card and enable notifications.
3. Run digest/test push and confirm notice/result state.

## Data/schema checks
1. Confirm options still exist and unchanged:
   - `peracrm_schema_version`
   - `peracrm_migration_v4_done`
2. Confirm existing tables are reused (no renamed tables):
   - `crm_notes`
   - `crm_reminders`
   - `crm_activity`
   - `crm_client_property`
   - `peracrm_party`
   - `peracrm_deals`
   - `crm_push_log`

## Cross-plugin compatibility
1. Confirm `pera-portal` access still delegates through `peracrm_user_can_access_crm()`.
2. Confirm no fatal errors when child theme CRM files are not loaded (plugin should own route/controller stack).
