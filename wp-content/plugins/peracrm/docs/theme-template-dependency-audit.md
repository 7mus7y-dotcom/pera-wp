# PeraCRM Theme Template Dependency Audit (Phase 1: Inventory Only)

## 1) Executive summary

PeraCRM front-end rendering is still theme-owned today. The `/crm/*` route resolution, template selection, and CRM asset enqueueing all happen in the theme (`inc/crm-router.php`), not in the MU plugin. This means CRM front-end pages are not independently shippable with the plugin yet.

### What this audit found

- **Route-to-template coupling is in theme code** via `template_include` and `get_stylesheet_directory()` path checks.
- **All CRM front-end page templates are theme files** (`page-crm*.php`) and share a theme partial (`parts/crm-header.php`).
- **Theme bootstrap controls CRM helper loading** (`inc/bootstrap/crm-gated.php`), so view helpers (`inc/crm-data.php`, `inc/crm-client-view.php`) are also theme-owned.
- **CRM styling and JS hooks are theme assets** (`css/crm.css`, `js/crm.js`) and expect theme design-system classes (`hero`, `btn`, `pill`, `card-shell`, `content-panel`, etc.).
- **Plugin already owns data/services/actions**, and templates call many `peracrm_*` APIs; therefore migration path should move view/router layers into MU plugin while keeping existing peracrm service API usage.

---

## 2) Template dependency inventory table

| Current theme file | Type | Purpose | Route/page using it | Direct caller | Plugin caller(s) |
|---|---|---|---|---|---|
| `wp-content/themes/hello-elementor-child/page-crm.php` | Full page template | CRM overview + leads + tasks (switches on query vars) | `/crm/`, `/crm/leads/`, `/crm/clients/`, `/crm/tasks/` | `inc/crm-router.php` (`template_include`) | None (no direct template load from MU plugin) |
| `wp-content/themes/hello-elementor-child/page-crm-new.php` | Full page template | Create-new-lead form | `/crm/new/` | `inc/crm-router.php` (`template_include`) | None |
| `wp-content/themes/hello-elementor-child/page-crm-client.php` | Full page template | Client detail/workspace view | `/crm/client/{id}/` | `inc/crm-router.php` (`template_include`) | None |
| `wp-content/themes/hello-elementor-child/page-crm-pipeline.php` | Full page template | Pipeline board view | `/crm/pipeline/` | `inc/crm-router.php` (`template_include`) | None |
| `wp-content/themes/hello-elementor-child/parts/crm-header.php` | Shared partial/hero | CRM section header, nav, and lead/client filters | Included by all CRM page templates | `get_template_part()` from `page-crm*.php` | None |

### Supporting CRM theme-owned non-template files tightly coupled to templates

| Current file | Role today |
|---|---|
| `wp-content/themes/hello-elementor-child/inc/crm-router.php` | Rewrites, query vars, gate checks, template selection, CRM asset enqueue, body class |
| `wp-content/themes/hello-elementor-child/inc/crm-data.php` | Data shaping/read helpers used by CRM templates |
| `wp-content/themes/hello-elementor-child/inc/crm-client-view.php` | Client-view-specific access/data/handlers/render helpers |
| `wp-content/themes/hello-elementor-child/css/crm.css` | CRM front-end style layer |
| `wp-content/themes/hello-elementor-child/js/crm.js` | CRM front-end JS behavior and DOM hooks |
| `wp-content/themes/hello-elementor-child/inc/bootstrap/crm-gated.php` | Conditional loading wrapper for CRM helper includes |

---

## 3) Dependency findings by template

## `page-crm.php` (overview/leads/tasks)

- **Template composition**
  - Uses theme wrapper `get_header()/get_footer()`.
  - Includes `parts/crm-header` via `get_template_part()`.
- **Data/helper dependencies**
  - Expects `pera_crm_get_dashboard_data()`, `pera_crm_get_leads_view_data()`, `pera_crm_get_tasks_view_data()`, `pera_crm_get_pipeline_stages()`, `pera_crm_get_pipeline_advisor_options()`.
  - Calls numerous `peracrm_*` action endpoints via forms/URLs (status updates, etc.) through `admin-post.php` and route links.
- **Globals/query vars expected**
  - `pera_crm_view`, `paged`, `type`, `peracrm_push_notice`, raw `REQUEST_URI` for redirects.
- **Security/auth assumptions**
  - Relies on upstream route gate in router; template itself assumes authenticated/authorized state already enforced.
  - Embeds nonces for form actions (e.g. reminder status updates).
- **Theme CSS/JS coupling**
  - Heavy usage of theme utility/layout classes: `hero`, `content-panel`, `card-shell`, `pill`, `btn`, `grid-*`.
  - JS hooks: `[data-crm-view-toggle]`, `[data-crm-sort-table]`, `data-row-url`, `.crm-table-sort`, `.peracrm-sort-indicator`.

## `page-crm-new.php` (new lead)

- **Template composition**
  - Uses `get_header()/get_footer()`, includes `parts/crm-header`.
- **Data/helper dependencies**
  - Expects router-side POST handler (`pera_crm_handle_new_lead`) and allowed source list parity.
- **Globals/query vars expected**
  - `crm_error`, prefill query string fields (`first_name`, `last_name`, `email`, `phone`, `source`, `notes`).
- **Security/auth assumptions**
  - Relies on router gate + POST handler for auth/cap checks.
  - Form includes `pera_crm_create_lead_nonce` and posts to `/crm/new/`.
- **Theme CSS/JS coupling**
  - Uses theme form/button/panel classes and shared header partial.

## `page-crm-client.php` (client detail)

- **Template composition**
  - Uses `get_header()/get_footer()`, includes `parts/crm-header`.
- **Data/helper dependencies**
  - Strong dependency on `inc/crm-client-view.php` helpers (`*_get_client_id`, access checks, data loading, notice helpers, bucket helpers, etc.).
  - Uses plugin-render helper `peracrm_render_assigned_advisor_box()` from MU plugin.
  - Uses option providers from MU plugin (`peracrm_party_stage_options()`, `peracrm_deal_stage_options()`, etc.).
- **Globals/query vars expected**
  - `pera_crm_client_id`, notice keys and filter/task parameters.
- **Security/auth assumptions**
  - Access state computed in helper layer and shown in template.
  - Multiple forms with nonce checks for admin-post actions (delete, save profile, reminders, deals, assignment updates).
- **Theme CSS/JS coupling**
  - High coupling to CRM CSS and dialog JS hooks (`[data-crm-danger-open]`, `[data-crm-danger-close]`, floating action button patterns).

## `page-crm-pipeline.php` (pipeline board)

- **Template composition**
  - Uses `get_header()/get_footer()`, includes `parts/crm-header`.
- **Data/helper dependencies**
  - Expects `pera_crm_get_pipeline_view_data()`.
- **Routing/security assumptions**
  - Calls gate helper if available; assumes routed from CRM virtual route.
- **Theme CSS/JS coupling**
  - Uses board/card classes styled in `css/crm.css` and shared button/pill classes.

## `parts/crm-header.php` (shared partial)

- **Purpose/type**
  - Shared hero/subnav/filter partial for CRM pages.
- **Input dependencies**
  - Expects `$args` keys (`title`, `description`, `active_view`, `show_client_filters`, `stages`, `advisors`, `clients_type_view`).
  - Reads GET filters (`q`, `stage`, `advisor`).
- **Routing assumptions**
  - Hardcodes CRM section URLs under `/crm/*`.
- **Theme CSS/JS coupling**
  - Uses theme hero/nav/button/form-control classes (`hero`, `container`, `btn`, `cta-control`).

---

## 4) Proposed plugin view structure

Use existing MU plugin naming (`peracrm_*`) and keep route/view concerns under `inc/`.

```text
wp-content/mu-plugins/peracrm/
  inc/
    frontend/
      routing.php                  # rewrite/query vars/template selection/gate
      assets.php                   # enqueue frontend CRM CSS/JS + localization
      view-helpers.php             # lightweight URL/build helpers
    views/
      pages/
        crm-overview.php           # from page-crm.php (overview/leads/tasks)
        crm-new.php                # from page-crm-new.php
        crm-client.php             # from page-crm-client.php
        crm-pipeline.php           # from page-crm-pipeline.php
      partials/
        crm-header.php             # from parts/crm-header.php
    frontend-data/
      crm-data.php                 # migrate from theme/inc/crm-data.php
      crm-client-view.php          # migrate from theme/inc/crm-client-view.php
  assets/
    frontend/
      crm.css                      # migrate from theme/css/crm.css
      crm.js                       # migrate from theme/js/crm.js (split non-CRM block)
```

### Notes on structure choice

- Keeps migration incremental: move wrappers/helpers first, then templates, then assets.
- Avoids introducing a brand-new architecture; mirrors existing file responsibilities.
- Supports future `load_template()` from plugin paths while preserving route behavior.

---

## 5) Migration map (current → target + refactors + risk)

| Current path | Proposed plugin path | Required refactors before move | Risk notes |
|---|---|---|---|
| `themes/.../inc/crm-router.php` | `peracrm/inc/frontend/routing.php` | Replace `get_stylesheet_directory()` template resolution with plugin path resolver; keep same query vars/rewrites and hook priorities. | High: route regressions and auth gate regressions can break all CRM pages. |
| `themes/.../page-crm.php` | `peracrm/inc/views/pages/crm-overview.php` | Remove implicit theme assumptions; ensure partial include uses plugin resolver; keep all nonce/action names unchanged. | Medium: CSS class compatibility and table JS hooks must remain stable. |
| `themes/.../page-crm-new.php` | `peracrm/inc/views/pages/crm-new.php` | Keep error/prefill contract and nonce names exactly; ensure POST target still `/crm/new/`. | Medium: lead creation UX and validation parity risks. |
| `themes/.../page-crm-client.php` | `peracrm/inc/views/pages/crm-client.php` | Move/bridge all `pera_crm_client_view_*` helpers first; keep admin-post action contracts. | High: largest template with many form actions and permission states. |
| `themes/.../page-crm-pipeline.php` | `peracrm/inc/views/pages/crm-pipeline.php` | Ensure `pera_crm_get_pipeline_view_data()` availability in plugin scope. | Low/Medium: mostly read-only rendering. |
| `themes/.../parts/crm-header.php` | `peracrm/inc/views/partials/crm-header.php` | Update `get_template_part()` calls to plugin include helper (e.g. `peracrm_render_view('partials/crm-header', $args)`). | Medium: used by all CRM pages. |
| `themes/.../inc/crm-data.php` | `peracrm/inc/frontend-data/crm-data.php` | Namespace/guard collisions; keep public function names initially for compatibility shim. | Medium: broad helper surface used by several templates. |
| `themes/.../inc/crm-client-view.php` | `peracrm/inc/frontend-data/crm-client-view.php` | Move action handlers with same hooks; separate data logic from output helpers if possible. | High: mixed data+handler logic currently central to client page. |
| `themes/.../css/crm.css` | `peracrm/assets/frontend/crm.css` | Update enqueue handle/path in plugin asset loader. | Medium: depends on theme design tokens and base class definitions. |
| `themes/.../js/crm.js` | `peracrm/assets/frontend/crm.js` | Split out non-CRM archive block or guard it strictly; keep CRM selectors unchanged. | Medium: mixed-purpose JS currently bundled together. |

---

## 6) Blockers / risks

1. **Theme-only template loader and routing**
   - CRM templates are selected in theme `template_include`; plugin has no independent view loader yet.

2. **Theme bootstrap gating controls helper availability**
   - `inc/bootstrap/crm-gated.php` loads `crm-data.php` and `crm-client-view.php`; moving only templates without moving this gate will cause fatal/missing helper calls.

3. **Theme design-system lock-in**
   - Templates depend on non-CRM theme classes (`hero`, `container`, `btn`, `pill`, `card-shell`, etc.). If plugin is used with another theme, visuals and spacing degrade unless CSS is duplicated or rewritten.

4. **Security/routing mixed with view lifecycle**
   - `inc/crm-router.php` combines rewrite rules, auth gating, POST processing (`/crm/new`), template include, and asset enqueue. This should be split before/while migrating for lower risk.

5. **Client view complexity**
   - `page-crm-client.php` has many forms/actions/nonces and depends on helper stack and plugin service functions. It is not a “copy-only” migration.

6. **Mixed-purpose CRM JS**
   - `js/crm.js` includes non-CRM archive behavior plus CRM behavior; moving as-is can create unintended frontend side effects if loaded differently.

---

## 7) Migration order recommendation

1. **Move router + loader infrastructure first**
   - Introduce plugin routing hooks and plugin template resolver with compatibility fallback to theme paths.

2. **Move shared partial + smallest page next**
   - `parts/crm-header.php` then `page-crm-pipeline.php`.

3. **Move `page-crm-new.php`**
   - Keep new-lead handler contract and nonce semantics unchanged.

4. **Move `page-crm.php` overview/leads/tasks**
   - Preserve JS hook attributes and sort/view toggle behavior.

5. **Move helper layers**
   - `inc/crm-data.php` and then `inc/crm-client-view.php` into plugin, with shims if needed.

6. **Move `page-crm-client.php` last**
   - Highest complexity and highest regression risk.

7. **Move CSS/JS after template parity is stable**
   - Keep same handles at first; then safely decouple from theme token dependencies.

---

## 8) Concise search findings (paths)

- Template routing to theme files:
  - `wp-content/themes/hello-elementor-child/inc/crm-router.php`
- CRM full page templates in theme:
  - `wp-content/themes/hello-elementor-child/page-crm.php`
  - `wp-content/themes/hello-elementor-child/page-crm-new.php`
  - `wp-content/themes/hello-elementor-child/page-crm-client.php`
  - `wp-content/themes/hello-elementor-child/page-crm-pipeline.php`
- CRM shared partial in theme:
  - `wp-content/themes/hello-elementor-child/parts/crm-header.php`
- CRM helper wrappers in theme:
  - `wp-content/themes/hello-elementor-child/inc/bootstrap/crm-gated.php`
  - `wp-content/themes/hello-elementor-child/inc/crm-data.php`
  - `wp-content/themes/hello-elementor-child/inc/crm-client-view.php`
- CRM styles/scripts in theme:
  - `wp-content/themes/hello-elementor-child/css/crm.css`
  - `wp-content/themes/hello-elementor-child/js/crm.js`
- MU plugin scan result:
  - No direct `locate_template()/get_template_part()/template_include/get_stylesheet_directory()` usage in `wp-content/mu-plugins/peracrm` for front-end CRM templates.

---

## 9) Exact files to move first in implementation phase

1. `wp-content/themes/hello-elementor-child/inc/crm-router.php` → plugin `inc/frontend/routing.php`
2. `wp-content/themes/hello-elementor-child/parts/crm-header.php` → plugin `inc/views/partials/crm-header.php`
3. `wp-content/themes/hello-elementor-child/page-crm-pipeline.php` → plugin `inc/views/pages/crm-pipeline.php`
4. `wp-content/themes/hello-elementor-child/page-crm-new.php` → plugin `inc/views/pages/crm-new.php`

(Then `page-crm.php`, helper files, and finally `page-crm-client.php`.)
