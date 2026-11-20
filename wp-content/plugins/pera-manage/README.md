# Pera Manage — Phase 1 (navigation-only)
Private WordPress front-end management hub at `/manage/`.

## Purpose
This is a **read-only navigation layer**. Clicking "Edit", "Add property", "New article", or "Translation tools" opens the **existing WordPress editor/metabox interfaces**. CRM remains at `/crm/`. No ACF/V2/property-save code is introduced.

## Activate
Activate **Pera Manage** in WP Admin > Plugins on staging. Unlike a rewrite-based implementation, this implementation intercepts only the exact /manage path at template_redirect, so no permalink flush is needed. Also configure your origin/CDN cache to bypass `/manage` and `/manage/`, including query-string variants, independently of PHP headers.

## Access
This initial phase is **administrator-only** (manage_options). The site currently sends employees away from wp-admin. Opening the dashboard does not grant editing permissions; native WordPress editors apply their usual per-record capability checks. Staff access must be designed and tested separately rather than implying that an existing CRM role can use wp-admin.

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
2. As subscriber/employee/CRM-only user, expect 403; direct /wp-admin/post.php and post-new.php must retain existing restrictions.
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
