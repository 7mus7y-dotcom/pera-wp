# PeraCRM Independence Audit (MU-plugin ➜ Standard Plugin)

## 1) Executive summary (what blocks independence)

PeraCRM core business/data logic is mostly inside `wp-content/mu-plugins/peracrm`, but **critical front-end CRM runtime lives in the child theme**, not the plugin. The largest blockers are:

1. **Routing and template ownership is in theme code** (`inc/crm-router.php` + `page-crm*.php`). `/crm/*` rewrites, query vars, template resolution, and front-end gating are all theme-bound.
2. **Front-end CRM data/controller layer is in theme code** (`inc/crm-data.php`, `inc/crm-client-view.php`, theme AJAX handlers).
3. **CRM front-end assets are enqueued from the theme and depend on theme asset handles/helpers** (`pera-main-css`, `pera_get_asset_version`, `get_stylesheet_directory*`).
4. **Plugin still references theme helper functions and text domain in a few places** (redirect helpers + `hello-elementor-child` textdomain in admin action).
5. **Schema/caps lifecycle is MU style** (runtime `admin_init`/`init` upgrade checks), not activation-hook-first lifecycle.

Result: PeraCRM is **not yet theme-independent** and cannot be moved to `wp-content/plugins/` without moving route/template/front-end concerns into plugin-owned code.

---

## 2) Current load/boot sequence (MU-plugin mechanics)

### Current MU entrypoints
- `wp-content/mu-plugins/peracrm.php` defines `PERACRM_MAIN_FILE` and loads nested entrypoint `wp-content/mu-plugins/peracrm/peracrm.php`.
- `wp-content/mu-plugins/peracrm-loader.php` also requires the same nested entrypoint.

Evidence:
- `peracrm.php` (MU root) sets `PERACRM_MAIN_FILE` and requires `__DIR__ . '/peracrm/peracrm.php'`.
- `peracrm-loader.php` also requires `__DIR__ . '/peracrm/peracrm.php'`.

### Nested plugin bootstrap
- `wp-content/mu-plugins/peracrm/peracrm.php` defines:
  - `PERACRM_VERSION`
  - `PERACRM_SCHEMA_VERSION`
  - `PERACRM_MAIN_FILE` (if not already defined)
  - `PERACRM_PATH`
  - `PERACRM_INC`
- Then includes `inc/bootstrap.php`.

### Bootstrap behavior (`inc/bootstrap.php`)
- Unconditionally loads helpers, schema, roles, CPT, repositories/services, REST modules.
- Conditionally loads admin files under `is_admin()`.
- Performs schema/cap bootstrapping at runtime:
  - `admin_init`: `peracrm_maybe_upgrade_schema()` for `manage_options` users.
  - `init` (priority 5): schema upgrade for logged-in CRM-capable users.
  - `admin_init`: `peracrm_ensure_roles_and_caps()` for admins.
- Registers CPT on `init`.

### Implications for plugin conversion
- In standard plugin mode, install/upgrade should pivot to plugin activation/version checks; current runtime checks can remain as fallback but should not be sole mechanism.
- `PERACRM_MAIN_FILE` currently points to MU loader in some executions; plugin-mode should ensure this points to standard plugin main file for `plugins_url()` correctness.

---

## 3) Theme coupling inventory (parent + child) with evidence

> Note: In this repository scan, only `hello-elementor-child` theme is present under `wp-content/themes`.

### Theme bootstrap always loads CRM router
- `hello-elementor-child/inc/bootstrap/always.php` unconditionally requires `inc/crm-router.php`.
- This makes CRM route registration and template handling theme-owned.

### Theme loads CRM integration modules conditionally
- `hello-elementor-child/inc/bootstrap-modules.php` computes CRM route/AJAX/admin contexts and loads `inc/bootstrap/crm-gated.php`.
- `crm-gated.php` includes `inc/crm-data.php` and `inc/crm-client-view.php`.

### Theme contains CRM-specific controllers/helpers
- `inc/crm-router.php`: rewrites/query vars, access gate, template switch, asset enqueue, nav item injection.
- `inc/crm-data.php`: dashboard/pipeline aggregation helpers and read model shaping.
- `inc/crm-client-view.php`: client view helper layer and CRM AJAX handlers (`wp_ajax_peracrm_create_portfolio_token`, `wp_ajax_pera_crm_property_search`, etc.).

### Theme templates host CRM UI
- `page-crm.php`
- `page-crm-client.php`
- `page-crm-new.php`
- `page-crm-pipeline.php`
- plus partials like `parts/crm-header`.

### Theme textdomain leakage in MU plugin
- `wp-content/mu-plugins/peracrm/inc/admin/actions.php` uses `__('Converted to client', 'hello-elementor-child')`.

---

## 4) Routing/templating coupling inventory

### `/crm/*` route ownership is in theme
`inc/crm-router.php` registers:
- Rewrite rules: `/crm/`, `/crm/new/`, `/crm/client/{id}/`, `/crm/leads/`, `/crm/tasks/`, `/crm/pipeline/`.
- Query vars: `pera_crm`, `pera_crm_action`, `pera_crm_view`, `pera_crm_client_id`, `client_id`, etc.
- Template resolver (`template_include`) that points to theme template files.

### Theme lifecycle manages rewrite flush
- `after_switch_theme` hook flushes CRM rewrites.
- Admin notice on permalink screen reminds manual flush.

### Front-end form submit handling in theme
- `template_redirect` in `inc/crm-router.php` handles `/crm/new` lead creation.
- `inc/crm-client-view.php` handles property/client actions and CRM-specific AJAX endpoints.

### Coupling impact
To be theme-independent, **all CRM routing hooks, virtual query vars, template selection, and front-end action handlers must move to plugin-owned code** while preserving same URL contracts.

---

## 5) Assets/CSS coupling inventory

### Current enqueue source
- `inc/crm-router.php` enqueues `css/crm.css` and `js/crm.js` from child theme directories.
- Theme module `inc/modules/crm-push.php` enqueues `js/crm-push.js` (route-gated via `pera_is_crm_route`).

### Hard dependencies on theme helpers/handles
- Uses `get_stylesheet_directory()` / `get_stylesheet_directory_uri()`.
- Uses `pera_get_asset_version()` helper from theme.
- CRM stylesheet declares dependencies on theme handle `pera-main-css` and optionally `pera-slider-css`.

### CSS utility-class coupling
- Theme CRM CSS targets theme utility classes (`.btn`, `.pill`, panel/card conventions).
- Theme templates use shared shell classes (`content-panel`, `card-shell`, button variants).

### Service worker / push integration
- `crm-push.js` defaults SW URL to `/peracrm-sw.js`.
- MU plugin serves `/peracrm-sw.js` from `peracrm_push_render_service_worker()` on `init`.

### Coupling impact
For plugin independence, CRM asset registration/versioning should be plugin-owned (`PERACRM_URL`, `PERACRM_PATH`) with optional compatibility deps if theme styles are present.

---

## 6) Auth/roles/caps coupling inventory

### Plugin-defined roles/caps
- `peracrm_ensure_roles_and_caps()` adds roles `manager`, `employee`.
- Adds CRM caps such as `edit_crm_clients`, `edit_crm_leads`, `edit_crm_deals`, CPT caps, report cap.

### Canonical access helper
- `peracrm_user_can_access_crm()` checks `manage_options` or CRM edit caps.

### Theme-level access helper wraps plugin helper
- `pera_crm_user_can_access()` calls `peracrm_user_can_access_crm()` if available, otherwise falls back to role checks.

### Cap usage mismatch risk
- Plugin code checks custom caps like `peracrm_manage_all_clients`, `peracrm_manage_assignments`, `peracrm_manage_all_reminders` in multiple paths, but those are not added in `roles.php` (at least in this file).

### Coupling impact
Role/cap registration must be plugin-owned and complete. Theme wrapper access helpers should become optional shims or be removed after plugin owns front-end routing.

---

## 7) Data/schema lifecycle + what changes for activation hooks

### Custom tables (current)
From `inc/schema.php` (+ push table helper):
- `{$wpdb->prefix}crm_notes`
- `{$wpdb->prefix}crm_reminders`
- `{$wpdb->prefix}crm_activity`
- `{$wpdb->prefix}crm_client_property`
- `{$wpdb->prefix}peracrm_party`
- `{$wpdb->prefix}peracrm_deals`
- `{$wpdb->prefix}crm_push_log` (via `peracrm_push_log_create_table()`)

### Current schema trigger
- Runtime checks in bootstrap hooks (`admin_init` + `init`) call `peracrm_maybe_upgrade_schema()`.
- Schema version stored in option `peracrm_schema_version`.
- Migration state option `peracrm_migration_v4_done`.

### Plugin-mode required lifecycle updates
- Add `register_activation_hook()` to run:
  - role/cap registration
  - schema creation/migrations via `dbDelta`
  - rewrite registration + `flush_rewrite_rules()` once
- Keep defensive runtime `maybe_upgrade_schema()` for safe drift correction.

### Deactivation/uninstall considerations
- Deactivation: do **not** drop data/tables by default.
- Uninstall: optional explicit cleanup path (tables/options/caps) only if product policy allows; currently no uninstall flow exists.

---

## 8) Cross-plugin dependencies + hard/soft classification

| Dependency | Location | Why used | Classification | Replacement/guard plan |
|---|---|---|---|---|
| Theme CRM helpers (`pera_crm_client_view_url`, `pera_crm_get_client_view_url`) | `mu-plugins/peracrm/inc/admin/actions.php` | Redirect to front-end client views | Soft (fallback exists to `home_url('/crm/client/...')`) | Move canonical URL builder into plugin; keep `function_exists` shim for backward compatibility. |
| Theme route helper (`pera_is_crm_route`) | `themes/hello-elementor-child/inc/modules/crm-push.php` | Gate push script enqueue to CRM route | Hard for current theme push UX, soft for core CRM | Make plugin expose canonical route predicate and enqueue push script directly in plugin. |
| Pera Portal capability bridge (`peracrm_user_can_access_crm`) | `plugins/pera-portal/includes/capabilities.php` | Portal access delegation to CRM access helper | Soft from CRM perspective, hard from portal perspective | Preserve `peracrm_user_can_access_crm` API and load order in plugin mode. |
| Theme assets/handles (`pera-main-css`, `pera-slider-css`, `pera_get_asset_version`) | `themes/.../inc/crm-router.php` | CRM asset loading/versioning | Hard for current front-end style parity | Plugin should register/enqueue its own assets; optionally depend on theme handles if present. |

---

## 9) Proposed target architecture (folder layout + bootstrap flow)

> Preserve slug and main file name where feasible: keep `peracrm/peracrm.php` as plugin main entrypoint under `wp-content/plugins`.

### Suggested plugin structure

```text
wp-content/plugins/peracrm/
  peracrm.php                  # plugin header + constants + bootstrap
  includes/
    bootstrap.php
    install.php                # activation/deactivation/uninstall callbacks
    routing.php                # /crm rewrites + query vars + template loader
    capabilities.php           # roles/caps registration/upgrade
    schema.php                 # dbDelta + migrations (existing logic)
    rest/...
    admin/...
    frontend/
      data.php                 # migrate theme crm-data
      client-view.php          # migrate theme crm-client-view handlers
  templates/
    page-crm.php
    page-crm-client.php
    page-crm-new.php
    page-crm-pipeline.php
    parts/...
  assets/
    css/crm.css
    js/crm.js
    js/crm-push.js
```

### Bootstrap flow (target)
1. Define constants (`PERACRM_PATH`, `PERACRM_URL`, `PERACRM_MAIN_FILE`, versions).
2. Load includes for helpers/services/repos/rest/admin/frontend.
3. Register runtime hooks:
   - `init`: register CPT, rewrites/query vars.
   - `template_include`: CRM template routing.
   - `wp_enqueue_scripts`: plugin-owned CRM assets.
   - REST/admin-post/wp_ajax hooks as today.
4. Activation hook performs first-run install/migration and rewrite flush.

---

## 10) Migration plan (MU → standard plugin) with steps

1. **Create plugin package from current nested MU folder**
   - Copy `wp-content/mu-plugins/peracrm/` to `wp-content/plugins/peracrm/` preserving folder slug and `peracrm.php` main file name.
2. **Move theme-owned CRM runtime into plugin**
   - Migrate `inc/crm-router.php`, `inc/crm-data.php`, `inc/crm-client-view.php`, and `page-crm*.php` templates/partials into plugin equivalents.
3. **Refactor asset ownership**
   - Move `css/crm.css`, `js/crm.js`, `js/crm-push.js` into plugin assets.
   - Replace `get_stylesheet_directory*` and `pera_get_asset_version()` with plugin constants/filemtime helpers.
4. **Preserve URL contracts**
   - Keep same rewrite patterns/query vars; add plugin activation rewrite flush.
5. **Installation lifecycle**
   - Add activation hook for schema + roles/caps + rewrite flush.
   - Keep versioned migration checks for existing installs.
6. **Compatibility shims**
   - Keep/introduce compatibility wrappers for existing `pera_crm_*` function names consumed by other modules, then gradually deprecate.
7. **Decouple from child theme textdomain/helpers**
   - Replace `hello-elementor-child` domain with plugin domain.
8. **MU cleanup strategy**
   - Replace MU loader with small compatibility stub that conditionally loads plugin copy during transition, then retire after rollout.

---

## 11) Smoke test checklist (URLs, admin screens, CRUD, REST, perms)

### Routing/templates
- [ ] `/crm/` loads overview.
- [ ] `/crm/new/` loads form and creates lead.
- [ ] `/crm/client/{id}/` loads client view.
- [ ] `/crm/leads/`, `/crm/tasks/`, `/crm/pipeline/` render correctly.
- [ ] Non-authorized users are redirected/blocked as before.

### Admin
- [ ] `crm_client` CPT list/edit still works.
- [ ] Submenu pages: My Reminders, Work Queue, Pipeline, Client View.
- [ ] `admin_post_peracrm_*` handlers still succeed with nonce/cap checks.

### Data/schema
- [ ] Existing tables untouched and readable.
- [ ] `peracrm_schema_version` preserved and no duplicate migrations.
- [ ] New activation path runs idempotently.

### REST/security
- [ ] `peracrm/v1` routes return expected payloads.
- [ ] Nonce/cap failures return expected 401/403.
- [ ] Push routes (`/push/config`, `/subscribe`, `/unsubscribe`, `/debug`, `/digest/run`) work.

### Front-end JS/CSS behavior parity
- [ ] CRM UI interactions from `crm.js` (sorting, AJAX actions, portfolio token/property actions) still work.
- [ ] Push UI controls from `crm-push.js` still work.
- [ ] Visual parity against existing theme-based screens.

### Cross-plugin compatibility
- [ ] `pera-portal` access checks via `peracrm_user_can_access_crm()` still resolve.

---

## 12) Appendix: key `rg` hits (verbatim excerpts)

### Query 1
Command:

```bash
rg -n --hidden "(get_stylesheet_directory|get_template_directory|stylesheet_directory_uri|template_directory_uri|pera_|hello-elementor|elementor|acf_|wp_enqueue_style\(|wp_enqueue_script\(|template_include|rewrite|query_var|add_rewrite_rule|rest_route|register_rest_route)" wp-content/mu-plugins/peracrm
```

Important hits:

```text
wp-content/mu-plugins/peracrm/inc/admin/actions.php:1502:    $fallback_redirect = function_exists('pera_crm_client_view_url')
wp-content/mu-plugins/peracrm/inc/admin/actions.php:2486:    $frontend_fallback = function_exists('pera_crm_get_client_view_url')
wp-content/mu-plugins/peracrm/inc/admin/actions.php:2556:        'title' => __('Converted to client', 'hello-elementor-child'),
wp-content/mu-plugins/peracrm/inc/rest.php:30:    register_rest_route('peracrm/v1', '/leads', [
wp-content/mu-plugins/peracrm/inc/rest/push.php:15:    register_rest_route('peracrm/v1', '/push/config', [
```

### Query 2
Command:

```bash
rg -n --hidden "(peracrm|crm/|/crm|CRM|pera_crm_|PERACRM_)" wp-content/themes
```

Important hits:

```text
wp-content/themes/hello-elementor-child/inc/crm-router.php:78:            add_rewrite_rule( '^crm/?$', 'index.php?pera_crm=1', 'top' );
wp-content/themes/hello-elementor-child/inc/crm-router.php:472:add_filter( 'template_include', 'pera_crm_maybe_load_template', 30 );
wp-content/themes/hello-elementor-child/inc/crm-router.php:511:        $css_rel_path = '/css/crm.css';
wp-content/themes/hello-elementor-child/inc/crm-router.php:535:        $js_rel_path = '/js/crm.js';
wp-content/themes/hello-elementor-child/inc/crm-router.php:590:add_action( 'after_switch_theme', 'pera_crm_flush_rewrite_on_activation' );
wp-content/themes/hello-elementor-child/inc/crm-client-view.php:824:add_action( 'wp_ajax_peracrm_create_portfolio_token', 'pera_crm_create_portfolio_token_ajax' );
wp-content/themes/hello-elementor-child/inc/modules/crm-push.php:11:    get_stylesheet_directory_uri() . '/js/crm-push.js',
wp-content/themes/hello-elementor-child/page-crm.php:46:$crm_current_url    = home_url( wp_unslash( (string) ( $_SERVER['REQUEST_URI'] ?? '/crm/' ) ) );
```

### Query 3
Command:

```bash
rg -n --hidden "(peracrm|pera_crm_|PERACRM_)" wp-content/mu-plugins wp-content/plugins
```

Important hits:

```text
wp-content/mu-plugins/peracrm.php:18:$peracrm_entrypoint = __DIR__ . '/peracrm/peracrm.php';
wp-content/mu-plugins/peracrm-loader.php:11:require_once __DIR__ . '/peracrm/peracrm.php';
wp-content/plugins/pera-portal/includes/capabilities.php:47:                if (function_exists('peracrm_user_can_access_crm')) {
wp-content/plugins/pera-portal/includes/capabilities.php:48:                    return peracrm_user_can_access_crm($user_id);
wp-content/mu-plugins/peracrm/peracrm.php:18:define('PERACRM_PATH', __DIR__);
wp-content/mu-plugins/peracrm/inc/schema.php:10:        $installed = (int) get_option('peracrm_schema_version', 0);
```

---

## Dependency map (consolidated)

| Dependency | Location | Why used | Replacement plan |
|---|---|---|---|
| Theme routing (`crm-router.php`) | `themes/hello-elementor-child/inc/crm-router.php` | `/crm/*` rewrite/query var/template and route gating | Move routing + gate + template resolver into plugin `includes/routing.php` and plugin `templates/`. |
| Theme CRM templates | `themes/hello-elementor-child/page-crm*.php`, `parts/crm-header*` | UI rendering for CRM views | Move templates/partials to plugin `templates/`; load via plugin `template_include` callback. |
| Theme CRM data/controller helpers | `themes/.../inc/crm-data.php`, `inc/crm-client-view.php` | Dashboard shaping + AJAX/portfolio/client-view handlers | Move to plugin `includes/frontend/*`; retain function shims for backward compatibility. |
| Theme asset helper and handles | `pera_get_asset_version`, `pera-main-css`, `pera-slider-css` | Versioning and stylesheet dependency chain | Replace with plugin-owned version helper + optional `wp_style_is()` conditional deps. |
| Theme textdomain | `mu-plugins/peracrm/inc/admin/actions.php` | Translation domain string | Replace with plugin domain (e.g., `peracrm`). |
| Portal capability bridge | `plugins/pera-portal/includes/capabilities.php` | Delegated access checks | Keep `peracrm_user_can_access_crm` stable and loaded early. |

