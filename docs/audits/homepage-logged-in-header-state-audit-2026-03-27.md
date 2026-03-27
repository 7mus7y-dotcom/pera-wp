# Homepage logged-in header state audit (2026-03-27)

## Scope covered
- Homepage template/render path
- Shared header render path
- Admin panel / CRM visibility conditionals
- Header/menu caching checks (PHP + transients + output buffering)
- JS hydration/toggling behavior for header auth UI
- Route-specific no-cache behavior vs general homepage behavior

## 1) Render path trace

1. Theme bootstrap loads from `functions.php` -> `inc/bootstrap.php` -> `inc/bootstrap-modules.php` and always loads front-end bootstrap for non-admin requests.  
2. Homepage template (`home-page.php`) is server-rendered and calls `get_header()`.  
3. Shared header is emitted by `header.php` (not client-side templated), including:
   - CRM icon button in header right actions
   - Off-canvas menu from `wp_nav_menu( theme_location = main_menu_v1 )`
   - Logged-in vs logged-out off-canvas user panel
4. CRM menu item inside off-canvas is injected by Peracrm filter `pera_crm_add_header_nav_item()` on `wp_nav_menu_items` for `main_menu_v1`.

## 2) Exact conditionals controlling staff UI

### Header CRM icon (`.header-crm-toggle`)
Server-side PHP in `header.php`:
- Resolves CRM access via `peracrm_user_can_access_crm()` (preferred) / `pera_crm_user_can_access()` fallback.
- Final show condition:
  - `is_user_logged_in()`
  - CRM access allowed
  - `current_user_can('edit_crm_clients')`

### Off-canvas CRM menu item (`<li class="menu-item-crm">`)
Server-side PHP in `peracrm/inc/frontend/routing.php` filter:
- Applies only for `main_menu_v1` location.
- Requires:
  - `is_user_logged_in()`
  - `pera_crm_user_can_access()`

### “Admin panel UI” (WordPress admin bar)
Server-side PHP in `inc/access-control.php`:
- `show_admin_bar` filter returns `pera_should_show_admin_bar()` on front-end.
- Requires:
  - not CRM route
  - logged in
  - `pera_is_frontend_admin_equivalent()` true
- `pera_is_frontend_admin_equivalent()` currently checks roles `administrator` or `employee` (not `manager`).

## 3) Server-side vs JS ownership

- **CRM icon visibility:** PHP/server-render only.
- **CRM menu item visibility:** PHP/server-render only.
- **Admin bar visibility:** PHP/server-render only (WordPress core + `show_admin_bar` filter).
- `js/main.js` handles nav open/close, accordion, SVG use rewriting, cookie banner, sticky-scrolled class; it does not hydrate or replace logged-in header fragments, and does not re-evaluate auth state.

## 4) Caching / transient / output buffering findings

### Header/menu-specific caching
- No transient/object-cache wrapper around header rendering or menu HTML for auth UI.
- No output buffering around header template rendering.

### Existing transients found are unrelated to header auth state
- CRM flash message transient (`pera_crm_flash_*`) in routing.
- Various rate-limit transients in enquiry/client flows.
- None used to cache whether header should show CRM/admin controls.

### No-cache behavior present only in narrow routes
- `nocache_headers()` exists for:
  - `/peracrm-sw.js` service worker response
  - CSV export/admin actions
  - portfolio token invalid status handling
- No global `send_headers`/`DONOTCACHEPAGE` behavior for homepage/header routes was found.

## 5) JS checks for first-load toggling/cached state

- No `pageshow` or bfcache handling for header auth UI.
- No JS that fetches/replaces header fragments.
- No JS that toggles CRM/admin visibility based on auth after initial HTML render.

## 6) Likely root cause class

Most likely issue class: **cache-layer serving stale anonymous homepage HTML for the first Home navigation after login**.

Why this best fits observed behavior:
- Staff UI in question is server-rendered only (not JS-hydrated).
- A stale anonymous cached Home response would omit:
  - admin bar (no `admin-bar` body/admin markup)
  - CRM icon/menu item (fails logged-in condition during cached generation)
- A hard refresh then restoring UI is consistent with bypass/revalidation producing fresh logged-in HTML.

## 7) Ranked root causes

1. **Full-page cache mismatch on Home for authenticated traffic** (highest confidence).  
   - Home route likely cacheable at edge/plugin layer without varying/bypass on WP auth cookies.
2. **Browser memory/bfcache reuse of pre-login Home document** (medium).  
   - Especially if user previously had anonymous Home in session and returns via history-like navigation.
3. **Role mismatch for manager in admin bar helper** (lower relevance to the reported sequence, but real inconsistency).  
   - `manager` is included in CRM access fallback roles, but not in `pera_is_frontend_admin_equivalent()`.

## 8) Classification

- **Primary:** cache-layer / response caching issue.
- **Not primary:** JS hydration bug (no header auth hydration logic exists).
- **Not primary:** server conditional logic bug (conditionals themselves are deterministic and server-side).

## 9) Safest fix order

1. **Cache safety first (infrastructure/config):**
   - Ensure Home (`/`) bypasses full-page cache when any WP auth cookie is present (`wordpress_logged_in_*`, auth/secure auth cookies).
   - Ensure cache varies correctly by auth state at CDN/reverse-proxy/plugin layer.
2. **Theme/plugin hardening (defensive):**
   - Add targeted no-cache headers for front-end requests where `is_user_logged_in()` and header auth UI is critical, if infra-level fix is not guaranteed.
3. **Consistency fix:**
   - Decide if `manager` should also be treated as admin-equivalent for admin bar visibility and align `pera_is_frontend_admin_equivalent()` accordingly.
4. **Optional UX hardening:**
   - Add a lightweight `pageshow` handler to force refresh when a stale authenticated mismatch is detected (fallback only; not a substitute for cache correctness).

## 10) Validation plan

1. **HTML truth test (no JS):**
   - After login with Remember me, request `/` with browser devtools “Disable cache” OFF then ON.
   - Compare response HTML for presence/absence of:
     - `menu-item-crm`
     - `.header-crm-toggle`
     - `body.admin-bar` / `#wpadminbar`
2. **Header-level cache diagnostics:**
   - Capture response headers for `/` before and after login (`curl -I` with and without auth cookies).
   - Confirm cache status and vary behavior at edge/plugin.
3. **Navigation-path test:**
   - `/my-favourites` -> click Home link/logo -> observe first render.
   - hard refresh -> observe corrected render.
4. **Role matrix:**
   - employee/admin/manager accounts verify CRM icon/menu/admin bar expectations.
5. **Post-fix regression:**
   - Repeat with warm cache, private browsing, and multi-tab sessions.

## Conclusion
The observed “missing on first Home render, restored on refresh” pattern is most consistent with **cached anonymous homepage HTML being served to an authenticated user**, while header staff controls are fully server-rendered and not JS-hydrated.
