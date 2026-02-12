# CRM Routing Audit: `/crm/new/` 404 and implementation plan

## Scope
Audited files:
- `inc/crm-router.php`
- `page-crm.php`
- `page-crm-new.php`
- `page-crm-client.php`

## Why `/crm/new/` can 404
WordPress only resolves pretty URLs that have matching rewrite rules + query vars. The CRM route is virtual (no WP Page post), so it depends entirely on custom rules and template routing. If `/crm/new/` is missing from rewrite rules in the active runtime ruleset, request falls through to 404.

Most common causes:
1. Rule not registered.
2. Rule registered in PHP but rewrite rules cache not flushed after deployment.
3. Query var not public, so rule cannot drive template conditions.
4. Template include callback not handling the resolved view.

## CRM rewrite rule inventory (code-level)
Current CRM router registers these routes on `init`:
1. `^crm/?$` -> `index.php?pera_crm=1`
2. `^crm/new/?$` -> `index.php?pera_crm=1&pera_crm_view=new`
3. `^crm/client/([0-9]+)/?$` -> `index.php?pera_crm=1&pera_crm_view=client&pera_crm_client_id=$matches[1]`
4. `^crm/leads/?$` -> `index.php?pera_crm=1&pera_crm_view=leads&paged=1`
5. `^crm/leads/page/([0-9]+)/?$` -> `index.php?pera_crm=1&pera_crm_view=leads&paged=$matches[1]`

## Query vars registered
CRM router currently registers these public query vars:
- `pera_crm`
- `pera_crm_view`
- `pera_crm_client_id`
- `crm_error`
- `crm_notice`

## Path match behavior
- `/crm/` => sets `pera_crm=1`, defaults template view to `overview`.
- `/crm/new/` => sets `pera_crm=1`, `pera_crm_view=new`.
- `/crm/client/<id>/` => sets `pera_crm=1`, `pera_crm_view=client`, `pera_crm_client_id=<id>`.

## Template include behavior
`template_include` checks CRM virtual route (`pera_crm=1`):
- if `pera_crm_view === new` => load `page-crm-new.php`
- else if `pera_crm_view === client` => load `page-crm-client.php`
- else => load `page-crm.php`

## Implementation summary completed
1. Route registration normalized to:
   - `^crm/?$` => `index.php?pera_crm=1`
   - `^crm/new/?$` => `index.php?pera_crm=1&pera_crm_view=new`
2. Added `pera_is_crm_route()` helper.
3. Added front-end create-lead POST handler via `admin_post_peracrm_front_create_lead`.
4. Updated `/crm/new/` form to post to `admin-post.php` with nonce + action.
5. Added validation/sanitization, owner assignment to logged-in user, and redirect to `/crm/client/<ID>/?crm_notice=created`.
6. Added success notice on client page.
7. Rewrite flush remains handled on `after_switch_theme`; manual flush instructions included below.

## Rewrite flush instructions
- WP Admin: **Settings → Permalinks → Save Changes**.
- WP-CLI: `wp rewrite flush --hard`

## Test invocation examples
1. Route check:
   - `GET /crm/new/` expected `200` for authorized logged-in staff user.
   - `GET /crm/new/` expected redirect to login (`302`) for guest.
2. Nonce presence:
   - View source on `/crm/new/` and confirm `pera_crm_create_lead_nonce` hidden input exists.
3. Form submit:
   - `POST /wp-admin/admin-post.php` with `action=peracrm_front_create_lead` + valid nonce + lead data.
   - expected redirect `302` to `/crm/client/<ID>/?crm_notice=created`.
4. Rewrite persistence:
   - flush permalinks, retest `/crm/new/` -> expected `200` (authorized user).

## URL access flow graph
```text
User GET /crm/new/
  -> rewrite ^crm/new/?$
  -> index.php?pera_crm=1&pera_crm_view=new
  -> template_include => page-crm-new.php
  -> form POST /wp-admin/admin-post.php?action=peracrm_front_create_lead
  -> admin_post_peracrm_front_create_lead
     -> auth/cap/nonce/validation
     -> create crm_client + assign owner=current user
     -> redirect /crm/client/<ID>/?crm_notice=created
  -> rewrite ^crm/client/([0-9]+)/?$
  -> template_include => page-crm-client.php
```

## Host/cookie note for front-end `admin-post.php` submissions
When `WP_HOME` and `WP_SITEURL` differ by host (for example `www.example.com` vs `example.com`), front-end CRM forms must post to `home_url('/wp-admin/admin-post.php')` so browser auth cookies are sent on submit. Using `admin_url('admin-post.php')` on front-end templates can target the `WP_SITEURL` host and cause `is_user_logged_in()` to fail during `admin_post_*` handling.
