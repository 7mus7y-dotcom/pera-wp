# MU-plugin CRM → Independent plugin layout parity audit

## Source of truth used

The MU plugin (`wp-content/mu-plugins/peracrm/`) does not contain front-end CRM page templates or CRM route CSS/JS. In the old working setup, `/crm/*` rendering came from the theme-level CRM router/templates.

For this parity task, the visual/structural source of truth was therefore:

- Routing/template source: `wp-content/themes/hello-elementor-child/inc/crm-router.php`
- Templates/partials source:
  - `wp-content/themes/hello-elementor-child/page-crm.php`
  - `wp-content/themes/hello-elementor-child/page-crm-pipeline.php`
  - `wp-content/themes/hello-elementor-child/page-crm-client.php`
  - `wp-content/themes/hello-elementor-child/page-crm-new.php`
  - `wp-content/themes/hello-elementor-child/parts/crm-header.php`
- CRM assets source:
  - `wp-content/themes/hello-elementor-child/css/crm.css`
  - `wp-content/themes/hello-elementor-child/js/crm.js`

Target plugin-owned files:

- Router: `wp-content/plugins/peracrm/includes/routing.php`
- Templates/partials:
  - `wp-content/plugins/peracrm/templates/page-crm.php`
  - `wp-content/plugins/peracrm/templates/page-crm-pipeline.php`
  - `wp-content/plugins/peracrm/templates/page-crm-client.php`
  - `wp-content/plugins/peracrm/templates/page-crm-new.php`
  - `wp-content/plugins/peracrm/templates/parts/crm-header.php`
- CRM assets:
  - `wp-content/plugins/peracrm/assets/css/crm.css`
  - `wp-content/plugins/peracrm/assets/js/crm.js`

## Route-by-route mapping and parity findings

### 1) `/crm/` (overview)

- Source template: `wp-content/themes/hello-elementor-child/page-crm.php`
- Plugin template: `wp-content/plugins/peracrm/templates/page-crm.php`
- Structural parity notes:
  - Wrapper hierarchy, section structure, cards/tables/forms, subnav/filter bars, and class names are aligned.
  - Plugin template uses `peracrm_render_template_part('parts/crm-header', ...)` instead of theme `get_template_part(...)` to keep rendering plugin-owned and independent from theme template loading.
- Fix applied in this task:
  - Removed extra template-local gate call (`pera_crm_gate_or_redirect`) so template structure follows old route behavior more closely (gate stays centralized in router).

### 2) `/crm/clients/`

- Source template: `wp-content/themes/hello-elementor-child/page-crm.php` (leads/clients view state)
- Plugin template: `wp-content/plugins/peracrm/templates/page-crm.php`
- Structural parity notes:
  - Same leads table/cards container, same CRM header partial usage, same filter/search/action controls, same class names.

### 3) `/crm/tasks/`

- Source template: `wp-content/themes/hello-elementor-child/page-crm.php` (tasks view state)
- Plugin template: `wp-content/plugins/peracrm/templates/page-crm.php`
- Structural parity notes:
  - Same tasks wrapper layout, same cards/list toggle hooks, same task action form blocks, same class names and task section hierarchy.

### 4) `/crm/client/{ID}/`

- Source template: `wp-content/themes/hello-elementor-child/page-crm-client.php`
- Plugin template: `wp-content/plugins/peracrm/templates/page-crm-client.php`
- Structural parity notes:
  - Same page wrapper, summary strips, timeline blocks, profile/status/forms, data attributes, and class hierarchy.
  - Header partial switched to plugin-local render helper for independence while preserving markup/classes.

### 5) `/crm/new/`

- Source template: `wp-content/themes/hello-elementor-child/page-crm-new.php`
- Plugin template: `wp-content/plugins/peracrm/templates/page-crm-new.php`
- Structural parity notes:
  - Same lead creation form layout, field groups, button rows, wrapper/classes, and error/notice block placement.

## CSS/JS parity findings

- CSS comparison (`theme/css/crm.css` vs `plugin/assets/css/crm.css`): selectors and declarations are effectively aligned for CRM layout; diffs were formatting-only (blank lines/media-query formatting), not visual-structure changes.
- JS comparison (`theme/js/crm.js` vs `plugin/assets/js/crm.js`): behavior is aligned; diff was formatting-only (blank line), no hook/name/behavior divergence.

## Independence requirements status

- Plugin CRM route templates are loaded from plugin-owned paths via `peracrm_locate_template()` in `wp-content/plugins/peracrm/includes/routing.php`.
- Plugin CRM CSS/JS are enqueued from plugin-owned assets (`/assets/css/crm.css`, `/assets/js/crm.js`).
- Theme fallback is disabled by default (`peracrm_allow_theme_template_fallback` defaults to false), so no default dependency on theme CRM templates.

## Manual test checklist

Use a CRM-authorized user and verify each route:

1. `/crm/`
   - Header/subnav and action button layout match old CRM.
   - Overview cards/sections spacing and classes match.
2. `/crm/clients/`
   - Filter/search/action bar structure matches old CRM.
   - Lead rows/cards/table classes and spacing match.
3. `/crm/tasks/`
   - Task view sections and card/list containers match old CRM.
   - Mark-done/task action controls align visually/structurally.
4. `/crm/client/{ID}/`
   - Client header strip, profile/status panels, timeline, and form sections match old CRM.
   - Data-attribute-driven sections render with same structure/classes.
5. `/crm/new/`
   - New lead form wrappers, field grid, submit area, and notice/error blocks match old CRM.
