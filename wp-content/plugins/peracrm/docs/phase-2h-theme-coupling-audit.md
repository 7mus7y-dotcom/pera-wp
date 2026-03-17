# Phase 2H — Remaining Theme Coupling Audit (Standalone PeraCRM Readiness)

## Scope
Audited the remaining non-runtime theme coupling across:

- `wp-content/mu-plugins/peracrm/`
- Related theme surfaces for comparison:
  - `wp-content/themes/hello-elementor-child/functions.php`
  - `wp-content/themes/hello-elementor-child/inc/`
  - `wp-content/themes/hello-elementor-child/inc/bootstrap*`
  - `wp-content/themes/hello-elementor-child/inc/modules/*`
  - `wp-content/themes/hello-elementor-child/parts/*`
  - `wp-content/themes/hello-elementor-child/css/*`
  - `wp-content/themes/hello-elementor-child/js/*`

This pass is intentionally **targeted** for coupling reduction and portability, not a full plugin conversion.

---

## Audit findings by category

### 1) Theme function coupling

**Observed**
- Frontend routing/views still use `function_exists(...)` checks for optional helpers.
- These checks are mostly guarding plugin-provided helpers or optional feature modules, not theme-only APIs.

**Classification**
- **SAFE TO DEFER** for now.

**Reasoning**
- Current guards are defensive and avoid hard fatals.
- No immediate blocker found that requires theme bootstrap order for core `/crm/*` rendering.

---

### 2) Theme path / template / partial coupling

**Observed**
- No active `get_stylesheet_directory()`, `get_stylesheet_directory_uri()`, `get_template_part()`, or `locate_template()` usage in plugin runtime routing/views for CRM templates.
- A residual coupling remained in CSS selectors that targeted `body.page-template-page-crm`.

**Classification**
- `body.page-template-page-crm` selector dependency: **BLOCKER** (fixed in this pass).
- Historical mentions inside docs: **COSMETIC ONLY**.

**Change applied**
- Added `body.crm-route` equivalents to the affected CRM table/layout selector block, preserving existing behavior while decoupling from theme template body class assumptions.

---

### 3) Theme textdomain coupling (`hello-elementor-child`)

**Observed**
- The plugin frontend and frontend-data surfaces still contain broad usage of `'hello-elementor-child'` for translatable strings.

**Classification**
- **SAFE TO DEFER**.

**Reasoning**
- This is primarily translation-domain ownership coupling, not a hard runtime blocker for moving from MU plugin to regular plugin.
- Should be migrated in a dedicated, complete i18n pass to avoid partial/inconsistent textdomain replacement.

---

### 4) Theme CSS/design-system coupling

**Observed**
- CRM views still use shared class names like `.container`, `.btn`, etc., but plugin-owned CRM CSS now defines and scopes critical CRM presentation behavior.
- Legacy table rules previously depended on theme template class naming.

**Classification**
- Legacy table scoping dependency: **BLOCKER** (fixed in this pass).
- Remaining class vocabulary overlap: **SAFE TO DEFER**.

---

### 5) Theme JS coupling

**Observed**
- Plugin enqueues CRM JS (`crm.js`, `crm-push.js`) from plugin assets on CRM routes.
- Theme global bundles are explicitly dequeued on CRM routes.

**Classification**
- **SAFE TO DEFER** (interop tuning only).

**Reasoning**
- Current behavior is plugin-owned for CRM route runtime; remaining coupling is mostly optional coexistence with theme handles.

---

### 6) Bootstrap / load-order coupling

**Observed**
- No new hard load-order blocker found in frontend routing/view loading path.
- Header nav item injection was hardcoded to a theme menu location slug (`main_menu_v1`).

**Classification**
- Hardcoded menu location slug: **SHOULD FIX NOW** (fixed in this pass).

**Change applied**
- Introduced `pera_crm_nav_theme_location()` and made location filterable via:
  - `apply_filters('peracrm_frontend_nav_theme_location', 'main_menu_v1')`

This preserves default behavior while removing hard coupling to one theme registration.

---

### 7) Standalone plugin blockers (current state)

## Fixed in this Phase 2H pass
- **BLOCKER fixed**: CSS dependence on `body.page-template-page-crm` for CRM table behavior.
- **SHOULD FIX NOW fixed**: hardcoded theme menu location slug in nav injection.

## Remaining portability items
- `'hello-elementor-child'` textdomain usage across plugin frontend strings (**SAFE TO DEFER**).
- Optional dequeue/hook interop assumptions tied to known theme handles (**SAFE TO DEFER**).
- Historical theme references in docs (**COSMETIC ONLY**).

---

## Conclusion
After this pass, PeraCRM is **standalone-ready except for deferred i18n-domain migration and optional theme-interop cleanup**.

Core `/crm/*` runtime rendering/asset ownership remains plugin-led, and no additional hard blocker was identified in audited frontend coupling surfaces.
