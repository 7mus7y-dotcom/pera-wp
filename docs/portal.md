# Portal, quotes, and public portfolios

## Distinct surfaces

### Authenticated Pera Portal plugin

`wp-content/plugins/pera-portal/pera-portal.php` loads `includes/bootstrap.php`. The plugin registers `pera_building`, `pera_floor`, `pera_unit`, and `pera_quote`, their ACF fields, services, admin tools, REST routes, assets, and templates. `/portal/` and `/portal/building/{id}/` are staff viewers guarded by `pera_portal_current_user_can_access()`. Default access delegates to PeraCRM access (or administrator); an optional dedicated-capability mode is defined by plugin configuration. Do not weaken template, REST, admin, or data-status checks independently.

`templates/portal-shell.php` and `assets/dist/portal-viewer.{css,js}` own the interactive viewer. Source-controlled `assets/dist/` is runtime output here; update it only through the repository's portal build/stamp workflow, not as an unrelated global-theme fix.

### Public token surfaces

- `/portal/quote/{token}/` belongs to Pera Portal. Authorized staff create immutable quote snapshots through `peracrm/v1`-independent portal REST routes (namespace in `includes/config.php`); `templates/portal-quote.php` validates token status/expiry and renders the snapshot. Quote pages are intentionally public-by-token and output `noindex,nofollow`; tokens are bearer secrets and must not be logged or exposed beyond the recipient.
- `/portfolio/{token}/` and `/portfolio-theme/{token}/` are separate public-by-token portfolio surfaces owned by the child theme (`inc/portfolio-token.php` and `page-portfolio-*.php`). CRM initiates/manages portfolio data and AJAX actions, but the theme registers the storage CPT, rewrite, lookup, templates, and public rendering.

Thus Portal owns building/floor/unit inventory and quotes; CRM owns staff/client workflow and authorization context; the theme owns public portfolio presentation and shared property cards. Do not move data or hooks across these boundaries casually.

## Routing, caching, and access

Portal rewrites/query vars/template routing live in `includes/routing/portal-pages.php`; permalink rules may need flushing after route changes. `includes/cache/nocache.php` sets `DONOTCACHE*`/`DONOTMINIFY` and private no-store headers for HTML under `/portal`, while excluding REST/admin/static plugin assets. Floor SVG REST responses also send no-cache headers. Preserve early hook timing and subdirectory-site path handling.

Public floor/building/unit REST modes expose only published objects; internal mode requires portal access and may include non-public status. Mutation/quote endpoints require access, capability, nonce/authentication as applicable, strict ownership relationships, validation, and sanitation. Never treat knowledge of a numeric ID as authorization. Quote tokens are randomly generated, looked up exactly, can expire/revoke, and render stored snapshots rather than mutable live unit data.

## Print behaviour

Viewer print is scoped to `#portal-print-scope`. JavaScript creates a temporary image fallback for canvas plans before `window.print()` and removes it on `afterprint`; CSS hides non-print controls and constrains plans/sections. Preserve IDs/classes and test both native SVG/image and canvas paths. Quote print CSS avoids splitting the header/sections.

## Verification

No dedicated automated portal test suite exists. Run `php -l` on changed PHP and syntax-check changed JS. Safely smoke-test: unauthorized `/portal/`; authorized landing/building; published versus draft/private REST data; quote creation, active/expired/revoked token pages; noindex and no-store headers; a hard refresh; and print preview. If public portfolio code changes, also run the theme's portfolio/multilingual coverage and inspect logged-out token URLs without placing real tokens in reports.
