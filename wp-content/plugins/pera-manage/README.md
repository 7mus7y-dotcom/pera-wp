# Pera Manage — Phase 1 (navigation-only)
Private WordPress front-end management hub at `/manage/`.

## Purpose
This is a **read-only navigation layer**. Clicking "Edit", "Add property", "New article", or "Translation tools" opens the **existing WordPress editor/metabox interfaces**. CRM remains at `/crm/`. No ACF/V2/property-save code is introduced.

## Activate
Activate **Pera Manage** in WP Admin > Plugins on staging. Unlike a rewrite-based implementation, this implementation intercepts only the exact /manage path at template_redirect, so no permalink flush is needed. Also configure your origin/CDN cache to bypass `/manage` and `/manage/`, including query-string variants, independently of PHP headers.

## Access
The dashboard grants the dedicated `pera_access_manage_dashboard` capability to **administrator, manager, and employee** roles. It does not grant `manage_options`, editing, or wp-admin access. Employees/managers get published inventories and existing CRM navigation, not administrative editing links. Subscribers get HTTP 403; logged-out visitors are directed to sign-in. On a normal staff login without an explicit return URL, the default destination is `/manage/`; explicitly requested destinations are preserved.

## What is included
- Overview with counts, quick actions and recently changed properties
- Searchable, paginated property inventory with status/type filters
- Searchable, paginated article/page inventory
- Links to existing translation metaboxes and website tools
- Optional CRM navigation when the CRM access function approves
- Theme `main.css` reuse plus isolated layout CSS
- Cookie-authenticated request, 403 for unauthorized users, private/no-store/noindex response
- Multilingual system-path exclusion for `/manage/`

## Stage validation checklist
1. Activate plugin on staging; visit /manage/ signed out, expect branded WordPress login and safe return.
2. As subscriber expect 403; as visitor expect login; as manager or employee expect dashboard with only published inventory and permitted CRM navigation. Direct /wp-admin/post.php and post-new.php must retain existing restrictions.
3. As administrator, test overview counts, property queries, search, status filters, project/resales taxonomy filters, pagination and content queries.
4. Verify classification against actual `special` term slugs and legacy records; adjust filters if production taxonomy uses different slugs.
5. Click Edit on published/draft properties, and Add Property; confirm the *original* WordPress/ACF editor opens, with unchanged V2 indexing after any subsequent wp-admin save.
6. Visit /de/manage/, /zh/manage/, etc.; verify they do not redirect into a translated internal dashboard, expose data, or become indexed. /manage/ must remain unprefixed.
7. Verify CDN and origin no-store/caching bypass and X-Robots-Tag, including query-string filters and anonymous requests.
8. Check desktop/mobile navigation, table scroll, keyboard focus, native links, OS dark mode and theme modes. Confirm no additional public-site assets load because of the new plugin.
9. Confirm all public pages, /crm/, search/filter AJAX and translations behave unchanged.
10. Check status/access after plugin deactivation: /manage/ should no longer expose dashboard data.

## Non-goals
No custom editor panels, forms, translations regeneration, new REST endpoints, V2 indexing code, schema migration, automated database maintenance or custom CRM interface.

## Staff access validation (v0.2.0)
- Verify `wp role list` and `wp cap list employee`, `wp cap list manager`, `wp cap list administrator` show `pera_access_manage_dashboard` after updating plugin; do **not** grant `manage_options`.
- Test ordinary sign-in for admin, manager and employee redirects to `/manage/` when no `redirect_to` was requested. Deep-link sign-ins to `/crm/tasks/` or a specific allowed page must still return there.
- Already authenticated staff opening login pages should be returned to `/manage/`; subscribers retain the existing client portal behavior.
- Verify employee/manager cannot access draft/private records, Site Performance, Media Library or normal WordPress editing through dashboard shortcuts; original wp-admin access restrictions remain in effect.
- For roles managed by custom role plugins, verify effective capability resolution on production.

## Shared-site styling follow-up (v0.3.0)
- The management shell deliberately uses the existing theme `#site-header`, `.site-branding`, `site-logo logo-pera`, `.header-menu-toggle`, `.offcanvas-nav`, `.offcanvas-menu`, `.offcanvas-backdrop` and the public logo helper. The plugin's small `manage.js` toggles the exact public scroll/menu state classes without loading public tracking or other frontend scripts.
- Global `main.css` supplies logo, sticky/ghost-blur scroll treatment, icon geometry, off-canvas, fonts, buttons, pills and cards. The isolated `manage.css` now focuses on layout, navigation composition and list thumbnails; no public stylesheet was modified.
- Property listing images use ACF `main_image` attachment ID (post meta) with formatted ACF fallback; regular posts/pages use WordPress featured images. A missing image shows a neutral placeholder. Images load lazily at thumbnail size.
- Validate header logo and menu against the home page on desktop and mobile, before/after scrolling; verify off-canvas close, backdrop, Escape, focus trap and body scroll, keyboard focus, dark-mode contrast, and no tracking assets in the dashboard.
- Validate real properties with ACF image arrays and IDs, missing images, posts with/without featured thumbnails, and 20-row list performance. Confirm no edits to WordPress post data or wp-admin access rules.
