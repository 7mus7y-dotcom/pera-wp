# Audit: CRM security/capability architecture for Pera Portal

Date: 2026-02-25
Scope: repository audit only; no runtime behavior changes.

## A) MU-plugin load/boot patterns

### What loads in `wp-content/mu-plugins/`
- Root MU-plugin entrypoints (directly auto-loaded by WordPress):
  - `wp-content/mu-plugins/pera-portal.php`
  - `wp-content/mu-plugins/peracrm-loader.php`
- Each root loader only `require_once`s a nested plugin entrypoint:
  - `pera-portal.php` -> `wp-content/mu-plugins/pera-portal/pera-portal.php`
  - `peracrm-loader.php` -> `wp-content/mu-plugins/peracrm/peracrm.php`

### Nested bootstrap patterns
- `pera-portal/pera-portal.php` defines plugin constants and requires `includes/bootstrap.php`, which then loops a fixed file list and conditionally includes each file if it exists.
- `peracrm/peracrm.php` defines constants and requires `inc/bootstrap.php`, which explicitly `require_once`s many CRM modules unconditionally, then conditionally loads admin-only files inside `if (is_admin())`.

### Ordering/constraints to reuse
- At MU root, load order depends on root entrypoint filenames; current files suggest `pera-portal.php` can load before `peracrm-loader.php`.
- Therefore cross-plugin calls should keep defensive `function_exists(...)` checks (already used by `pera_portal_user_can_access`).

## B) CRM access + capability functions

### Canonical access helper
- **Function:** `peracrm_user_can_access_crm($user_id = 0)`
- **Location:** `wp-content/mu-plugins/peracrm/inc/helpers.php`
- **Checks:**
  - For explicit `$user_id`: resolves `WP_User`, then `user_can()` for: `manage_options`, `edit_crm_clients`, `edit_crm_leads`, `edit_crm_deals`.
  - For current user: same capability set via `current_user_can()`.
- **Safety:** pure capability check; no routing, no hook registration, safe to call on any page.

### Wrapper already present in portal
- **Function:** `pera_portal_user_can_access($user_id = 0)`
- **Location:** `wp-content/mu-plugins/pera-portal/includes/capabilities.php`
- **Behavior:** delegates to `peracrm_user_can_access_crm` if available; fallback is admin-only (`manage_options`).
- **Safety:** pure function, safe on any page.

### Other related checks (not canonical for portal gating)
- `peracrm_admin_user_can_manage()` in `peracrm/inc/admin/actions.php` delegates to `peracrm_user_can_access_crm` but lives in admin actions context; not needed for portal-level gating.
- Theme-level `pera_crm_user_can_access()` in `themes/hello-elementor-child/inc/crm-router.php` delegates to CRM helper when available, otherwise role-based fallback; this is for CRM route gating, not MU-plugin canonical check.

## C) Capability/role registration

### Where roles/caps are registered
- **Primary registration:** `wp-content/mu-plugins/peracrm/inc/roles.php` via `peracrm_ensure_roles_and_caps()`.
- Hooked from `peracrm/inc/bootstrap.php` on `admin_init` (admin only) for administrators.

### Roles ensured
- `manager`
- `employee`
- (Also `lead` in multisite membership helper flow, but this is not a CRM staff capability role.)

### CRM capabilities explicitly added in plugin
- Common CRM caps:
  - `edit_crm_leads`
  - `edit_crm_clients`
  - `edit_crm_deals`
- Reports cap:
  - `view_crm_reports`
- Custom post-type primitive/meta caps:
  - `edit_crm_client`
  - `read_crm_client`
  - `delete_crm_client`
  - `edit_others_crm_clients`
  - `publish_crm_clients`
  - `read_private_crm_clients`
  - `delete_crm_clients`
  - `delete_private_crm_clients`
  - `delete_published_crm_clients`
  - `delete_others_crm_clients`
  - `edit_private_crm_clients`
  - `edit_published_crm_clients`

### Role-to-cap mapping (explicit)
- `administrator`: gets common + reports + full CPT admin cap set.
- `manager`: gets common + reports + limited CPT employee set.
- `employee`: gets common + limited CPT employee set; explicitly has `view_crm_reports` removed.

### “Manager sees all / employee sees own” model
- Cap registration alone does **not** encode full data-scope separation.
- Scope is enforced in operational code paths using:
  - `current_user_can('edit_post', $client_id)` checks,
  - assignment checks (`assigned_advisor_user_id` / `crm_assigned_advisor`),
  - override caps such as `peracrm_manage_all_clients`, `peracrm_manage_assignments`, `peracrm_manage_all_reminders` (used, but not registered in `roles.php`).
- Helper filter `peracrm_allowed_client_ids_for_user` (service layer) resolves assigned-client IDs for user-scoped operations.

## D) CRM routing + gating risk analysis

### Where CRM routing is actually wired
- Front-end CRM routing lives in the **theme**, not the MU CRM plugin:
  - `themes/hello-elementor-child/inc/crm-router.php`
  - Registers `init` rewrites, `query_vars`, `template_include`, `template_redirect`, frontend assets/body_class hooks.

### Gating condition that decides whether CRM integration loads
- In `themes/hello-elementor-child/inc/bootstrap-modules.php`:
  - `$is_crm_route`
  - `$is_crm_ajax`
  - `$is_crm_capable_user` (calls `peracrm_user_can_access_crm()`)
  - `$load_crm_integration = $is_crm_route || $is_crm_ajax || $is_crm_capable_user;`
- If true, theme includes `inc/bootstrap/crm-gated.php`, which requires router/data/client-view modules.

### Global-load implications
- Any logged-in CRM-capable user can trigger CRM module loading even on non-CRM URLs because `$is_crm_capable_user` is sufficient.
- This means theme CRM hooks can be attached globally for those users, even when route is not `/crm/*`.
- Calling `peracrm_user_can_access_crm()` itself is not the risky part (it is pure); including `crm-gated.php` is what pulls routing hooks.

## E) What to implement for Pera Portal (actionable)

### 1) ✅ Recommended portal access check
- **Preferred check:** `pera_portal_user_can_access($user_id = 0)` from `pera-portal/includes/capabilities.php`.
  - It already delegates to canonical CRM helper `peracrm_user_can_access_crm`.
- **Fallback if CRM helper unavailable:** keep current fallback (`manage_options` only).

### 2) ✅ Capabilities to reuse vs add
- **Reuse now:** `edit_crm_clients` as baseline CRM staff gate (via canonical helper set).
- **Do not add new portal cap yet** unless portal requirements diverge from CRM access.
- If later needed, add one explicit portal cap (e.g. `access_pera_portal`) in the same role-registration pattern (`peracrm_ensure_roles_and_caps`-style initializer) and apply least privilege.

### 3) ✅ Constraints / do-not-call notes
- Safe to call on non-CRM pages:
  - `peracrm_user_can_access_crm`
  - `pera_portal_user_can_access`
- Avoid using theme CRM router/gate helpers as generic portal dependency:
  - `pera_crm_user_can_access`
  - any bootstrap include that pulls `inc/crm-router.php`
- Avoid introducing conditions that force `$load_crm_integration` true unless CRM routing is truly intended.

### 4) ✅ Implementation notes for portal wrapper
- Keep portal wrapper in: `wp-content/mu-plugins/pera-portal/includes/capabilities.php`.
- Continue delegating to `peracrm_user_can_access_crm` with `function_exists` guard because MU root loader ordering can differ.
- Prefer reusing CRM access/caps now; only define a dedicated portal cap if business rules require non-CRM users to access portal.

## Relevant files audited
- `wp-content/mu-plugins/pera-portal.php`
- `wp-content/mu-plugins/peracrm-loader.php`
- `wp-content/mu-plugins/pera-portal/pera-portal.php`
- `wp-content/mu-plugins/pera-portal/includes/bootstrap.php`
- `wp-content/mu-plugins/pera-portal/includes/capabilities.php`
- `wp-content/mu-plugins/peracrm/peracrm.php`
- `wp-content/mu-plugins/peracrm/inc/bootstrap.php`
- `wp-content/mu-plugins/peracrm/inc/helpers.php`
- `wp-content/mu-plugins/peracrm/inc/roles.php`
- `wp-content/mu-plugins/peracrm/inc/cpt.php`
- `wp-content/mu-plugins/peracrm/inc/services/client_scope_service.php`
- `wp-content/themes/hello-elementor-child/inc/bootstrap-modules.php`
- `wp-content/themes/hello-elementor-child/inc/bootstrap/crm-gated.php`
- `wp-content/themes/hello-elementor-child/inc/crm-router.php`
