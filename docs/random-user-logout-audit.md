# Random User Logout Audit (WordPress)

Date: 2026-03-27  
Repository: `/workspace/pera-wp`

## Executive summary

The most likely causes are **cookie host/scheme mismatches** rather than explicit logout code. In this codebase, there is no custom call to core session-destroying APIs (`wp_clear_auth_cookie`, `wp_destroy_current_session`, etc.), so unexpected logout is more likely due to requests landing on a host or protocol where the auth cookie is not valid. The strongest evidence is a remaining front-end registration form posting to `admin_url('admin-post.php')` (WP_SITEURL host) while other front-end flows intentionally post to `home_url('/wp-admin/admin-post.php')` (WP_HOME host) to preserve auth cookies in split-host setups.  

Secondary likely causes are reverse-proxy HTTPS detection inconsistencies (custom redirects build URLs from `is_ssl()` + `HTTP_HOST`) and cache/proxy behavior outside this repository (no full-site cache plugin config is present in code; must be verified in runtime/CDN).

## Findings table

| Issue | Likelihood | Evidence | Safest fix |
|---|---|---|---|
| Front-end host mismatch for `admin-post.php` on registration flow can drop auth state (or appear as logged-out during follow-up actions) when `WP_HOME` and `WP_SITEURL` differ (e.g., `example.com` vs `www.example.com`) | **High** | `page-register.php` still uses `admin_url('admin-post.php')` for a front-end form. `admin_url()` tracks site/admin host, not necessarily public host. Existing in-repo docs explicitly call this out as a cookie-host risk and recommend `home_url('/wp-admin/admin-post.php')`. | Change registration form action to `home_url('/wp-admin/admin-post.php')`; deploy and validate with split-host test. |
| Upstream HTTPS termination not consistently reflected to WordPress can cause login redirect URL/scheme drift and cookie scope confusion | **High** | CRM gate builds login return URL with `is_ssl()` + `HTTP_HOST` + `REQUEST_URI`. If proxy headers are inconsistent, users can bounce between http/https or hosts. | Ensure proxy sets `X-Forwarded-Proto=https`; in `wp-config.php` set `$_SERVER['HTTPS']='on'` when forwarded proto is https; enforce one canonical HTTPS host. |
| Canonical host switching (`www`/non-`www`) or mixed `home`/`siteurl` values | **High** | Multiple code paths rely on `home_url()` for front-end and `admin_url()` for back-office; docs already warn split host setups can break auth checks on admin-post round-trips. | Verify and align `home` + `siteurl` host policy. Keep front-end form posts on `home_url('/wp-admin/admin-post.php')`. |
| Cache/CDN/proxy caching authenticated responses or login/account endpoints | **Medium** | Only portal routes set strict no-cache headers in code; broader site/auth cache policy is not visible in this repo. | Exclude `/wp-login.php`, `/wp-admin/*`, `/client-*`, `/crm/*`, and authenticated-cookie traffic from cache; vary by auth cookies; verify CDN rules. |
| Admin access blocking redirects misinterpreted as logout | **Medium** | Non-admin users are forcibly redirected out of wp-admin to `/crm/` or `/`; this can look like “kicked out” even when session is intact. | Add explicit UI messaging (“access restricted”) and verify login cookie remains present after redirect. |
| Explicit custom logout/session-destroy code | **Low** | No matches for `wp_set_auth_cookie`, `wp_clear_auth_cookie`, `wp_destroy_current_session`, `wp_destroy_all_sessions`, `auth_cookie_expiration`, `secure_auth_cookie`, `logged_in_cookie`, `send_auth_cookies`, or cookie path/domain constants in audited theme/plugins. | No direct fix needed here; focus on host/scheme/cache first. |

## Auth lifecycle trace for this site

### 1) Login entrypoints
- Core login page (`wp-login.php`) is styled and login redirect behavior is filtered (`login_redirect`) in theme login module.
- Custom client login page renders core `wp_login_form()` and passes through `redirect_to`.
- CRM route gate redirects unauthenticated users to `wp_login_url($requested_url)`.

### 2) Auth cookie set
- Cookie set is handled by WordPress core (no custom `wp_set_auth_cookie` in repo).
- Therefore, cookie domain/path/scheme behavior is controlled primarily by runtime config (`wp-config.php`, `home/siteurl`, proxy SSL detection).

### 3) Auth cookie validation
- Most gated routes use `is_user_logged_in()`.
- Front-end `admin-post.php` handlers depend on browser sending valid auth cookies to the request host.
- If form action host differs from cookie host, handlers can see users as logged out.

### 4) Logout / invalidation
- No direct session invalidation calls found.
- Visible logout is via standard `wp_logout_url(...)` links in templates.

## Code references (exact)

### Host/cookie-sensitive form actions and notes
- Front-end registration form currently posts to `admin_url('admin-post.php')`.  
  `wp-content/themes/hello-elementor-child/page-register.php`.
- Client portal profile form posts to `home_url('/wp-admin/admin-post.php')` (preferred for front-end host consistency).  
  `wp-content/themes/hello-elementor-child/page-client-portal.php`.
- In-repo audits explicitly document the host mismatch risk and recommended `home_url('/wp-admin/admin-post.php')` pattern.  
  `wp-content/themes/hello-elementor-child/docs/crm-routing-audit.md`; `wp-content/themes/hello-elementor-child/docs/forms-submission-audit.md`; `wp-content/plugins/peracrm/docs/reminders-admin-post.md`.

### Reverse proxy / SSL-sensitive logic
- CRM gate composes requested URL from `is_ssl()` + `HTTP_HOST` + `REQUEST_URI` before redirecting to login.  
  `wp-content/plugins/peracrm/inc/frontend/routing.php`.

### Redirects that can be mistaken for logout
- Leads are blocked from wp-admin and redirected to `/crm/`.  
  `wp-content/plugins/peracrm/inc/admin-block-leads.php`.
- Non-admin users in theme admin gate can be redirected to `/crm/` or `/`.  
  `wp-content/themes/hello-elementor-child/inc/filter-for-admin-panel.php`.

### Cache/no-cache behavior in scope
- Portal plugin sets no-cache constants/headers for `/portal/*` routes and `Vary: Cookie`.  
  `wp-content/plugins/pera-portal/includes/cache/nocache.php`.

## Config references and gaps

- `wp-config.php` is **not present in this repository snapshot**, so these critical values could not be directly validated:
  - `COOKIE_DOMAIN`, `ADMIN_COOKIE_PATH`, `COOKIEPATH`, `SITECOOKIEPATH`
  - `FORCE_SSL_ADMIN`
  - reverse-proxy `HTTPS` / `HTTP_X_FORWARDED_PROTO` handling
  - `WP_HOME`, `WP_SITEURL` overrides
- `wp-content/mu-plugins/` is not present here; no MU-level auth/session middleware could be audited from code.

## Recommended safe fix order (low risk → higher risk)

1. **Unify front-end `admin-post.php` actions to `home_url('/wp-admin/admin-post.php')`** (start with `page-register.php`).
2. **Instrument and observe** before broad changes:
   - Temporarily enable admin-post debug context logging in CRM (`PERACRM_DEBUG_ADMIN_POST`) to capture host/uri/logged_in state.
3. **Validate canonical host/scheme at runtime**:
   - Confirm `home` and `siteurl` and all redirects resolve to one HTTPS host.
4. **Fix reverse-proxy SSL detection in `wp-config.php`** if needed:
   - Ensure forwarded HTTPS is mapped to `$_SERVER['HTTPS']='on'`.
5. **Harden cache/CDN rules** for authenticated traffic and login/account/admin endpoints.
6. **Only if still unresolved**: audit runtime plugin stack and database for security/session plugins enforcing forced logout or UA/IP pinning.

## Validation checklist

1. **Cookie host consistency**
   - Log in on canonical front-end host.
   - Submit front-end `admin-post.php` forms.
   - Confirm request host equals cookie host and `is_user_logged_in()` remains true.
2. **Proxy SSL consistency**
   - Verify no http↔https flip during login/redirect chain.
3. **Canonical domain consistency**
   - Verify no `www`↔non-`www` flips on login/account/CRM routes.
4. **Cache bypass**
   - Confirm CDN/page cache bypass for logged-in cookies and account/admin endpoints.
5. **No forced invalidation events**
   - Verify no custom session destroy hooks and inspect security plugin runtime settings in production.

## Suggested terminal/browser tests

- `wp option get home`
- `wp option get siteurl`
- `wp eval 'echo is_ssl() ? "ssl\n" : "not_ssl\n";'`
- `curl -I https://<canonical-host>/client-login/`
- `curl -I https://<canonical-host>/crm/`
- Browser DevTools: inspect `Set-Cookie` and request cookies for login, `/client-portal/`, `/crm/`, and front-end `admin-post.php` submissions.

