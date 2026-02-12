# Front-end CRM Client View

## Route
- Pretty URL: `/crm/client/<id>/`
- Legacy client view route is removed (no redirect).
- Rewrite resolves to query vars:
  - `pera_crm=1`
  - `pera_crm_view=client`
  - `pera_crm_client_id=<id>`
  - `client_id=<id>`

## Access
The page enforces the same runtime checks as WP-Admin client view logic:
1. `peracrm_admin_user_can_manage()`
2. Valid `client_id` and `crm_client` post type
3. `current_user_can( 'edit_post', client_id )`
4. Advisor scope (unless `manage_options` or `peracrm_manage_all_clients`)

## Usage
- Open `/crm/client/<id>/` as an allowed CRM user.
- Forms post to `admin-post.php` actions used by existing MU plugin handlers.
- On deploys with rewrite changes, flush permalinks once (Settings → Permalinks → Save, or `wp rewrite flush --hard`).
- Do not flush rewrites per request (`flush_rewrite_rules()` is expensive).
