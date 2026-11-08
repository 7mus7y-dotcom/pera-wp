# Pera Portal Comprehensive Audit

**Audit date:** 2026-09-14  
**Scope:** `wp-content/plugins/pera-portal/`, its WordPress integration, the active Hello Elementor Child theme integration points, PeraCRM access control, ACF data, routes, assets, and deployment requirements.  
**Method:** Comprehensive static repository review plus non-mutating PHP, JavaScript, JSON, Git, and repository checks. No live WordPress runtime, database, staging hostname, or authenticated browser session was available.

## Executive summary

**Overall health: functional foundation, but not production-ready without remediation and staging verification.**

The plugin is physically structured as a standard WordPress plugin. It has a valid plugin header, derives its own filesystem and URL roots with WordPress APIs, and bootstraps code from its own directory (`wp-content/plugins/pera-portal/pera-portal.php`, lines 1-23). The compiled assets, templates, ACF Local JSON definitions, fixture, quote services, and administration tools required by the repository implementation are tracked.

The main blockers are:

1. **No activation/deactivation rewrite lifecycle.** Routes are added on `init`, but there is no activation/deactivation hook and no automatic `flush_rewrite_rules()`. A clean activation can leave all virtual portal routes returning 404 until an administrator manually saves Permalinks (`includes/routing/portal-pages.php`, lines 7-25 and 56-82).
2. **Portal CPT authorization uses generic post capabilities.** Buildings, floors, units, and quotes (`pera_quote`) use `capability_type => post` and `show_ui => true`; buildings, floors, and units also set `show_in_rest => true`. The menu-hiding code covers buildings, floors, and units but not `pera_quote`, so—depending on installed role capabilities—ordinary Editors may be able to administer quotes directly (`includes/cpt/building.php`, `includes/cpt/floor.php`, `includes/cpt/unit.php`, and `includes/cpt/quote.php`, lines 21-29; `includes/admin/menu.php`, lines 30-45).
3. **Published floor metadata and SVG plans are public through REST.** Anonymous callers can enumerate published buildings/floors and retrieve floor-plan SVGs even though the portal HTML routes are staff-only (`includes/rest/routes.php`, lines 69-127, 379-447, and 595-660).
4. **SVG delivery needs hardening.** The floor endpoint serves stored SVG bytes as same-origin `image/svg+xml` and may fetch an ACF-supplied URL server-side. Viewer-side sanitation does not protect direct requests to the raw endpoint (`includes/rest/routes.php`, lines 17-66 and 175-246; `assets/dist/portal-viewer.js`, lines 104-138).
5. **`/portal-test/` is not a standalone route and is probably non-operational.** It requires an existing WordPress Page with slug `portal-test`, invokes the legacy shortcode without building/floor attributes, and renders a shell with no building selector (`includes/frontend/template-routing.php`, lines 7-25; `templates/page-portal-test.php`, lines 7-13).
6. **Quote permissions are not meaningfully separated.** All quote capability helpers resolve to general portal access, and revocation uses the create helper (`includes/capabilities.php`, lines 91-121; `includes/rest/quote-routes.php`, lines 77-92).
7. **Production assets cannot be reproduced from repository source.** The committed `dist` assets are deployable, but the source JavaScript modules and CSS are empty scaffolds and there is no plugin-local build manifest.

No PHP syntax, JSON parsing, or compiled JavaScript syntax failures were found. No Critical vulnerability was confirmed statically.

### Verification boundary

This checkout does not contain a runnable WordPress core/configuration/database, and WP-CLI was unavailable. The following could not be verified and must be checked on staging or production:

- Whether the plugin is present in `active_plugins` or network-active.
- Whether rewrite rules have already been flushed.
- Whether a published `portal-test` Page exists.
- Actual buildings, floors, units, quotes, ACF values, and uploaded media.
- HTTP status codes, canonical redirects, cache/CDN behavior, and authentication cookies.
- Pixel-level desktop/mobile/browser behavior.
- Installed WordPress, PHP, ACF, Elementor, and PeraCRM versions.

## Current status

| Check | Result |
|---|---|
| Standard plugin entry point | Pass: valid `Plugin Name` header in `pera-portal.php`, lines 1-5. |
| Self-contained path and URL constants | Pass: `plugin_dir_path()` and `plugin_dir_url()` in `pera-portal.php`, lines 11-23. |
| Active in WordPress | Not verifiable without database/runtime access. |
| Activation/deactivation hooks | Fail: none found. |
| Rewrite flush | Fail: none found; only a Permalinks-screen warning exists. |
| Compiled deploy assets | Pass: all referenced `assets/dist` files and `.build` are tracked. |
| Reproducible asset build | Fail: source files are scaffolds and no build manifest exists. |
| Automated tests | Absent. `page-portal-test.php` is a route template, not a test suite. |
| MU-plugin remnants | Present in documentation/comments and shortcode compatibility logic, not as an executable MU loader in the tracked repository. |

### Legacy, obsolete, and dead code

- `readme.md` still calls the project an “MU-plugin scaffold” and describes an MU-plugin deployment (`readme.md`, lines 1-9).
- The ACF JSON comment still refers to registering filters “in MU context” (`includes/acf/fields.php`, lines 26-28).
- The legacy `[pera_portal]` shortcode remains registered. Within this repository it is used by `/portal-test/` (`includes/shortcodes/portal-shortcode.php`, lines 7-115).
- Shortcode probing and late asset enqueue compatibility remain in `includes/assets/enqueue.php`, lines 103-118, 221-262, and 316-325.
- `PeraPortalUnitLookupService::getUnitById()` returns an ID and permanently empty label (`includes/services/UnitLookupService.php`, lines 7-15).
- `PeraPortalSvgPlanService::getFloorPlanPath()` always returns an empty string (`includes/services/SvgPlanService.php`, lines 7-18).
- Bootstrap silently skips missing files rather than failing on an incomplete deployment (`includes/bootstrap.php`, lines 39-42).
- Documentation says a missing floor SVG falls back to the fixture, while executable code permits this by default only outside production (`readme.md`, lines 21-30; `includes/rest/routes.php`, lines 35-51).

## Route/status table

| Route | Static trace | Access and edge behavior | Assessment |
|---|---|---|---|
| `/portal/` | Top rewrite maps to `pera_portal_page=landing`; template filter priority 98 loads `templates/portal-landing.php`. The template checks portal access and queries all published buildings. | Unauthorized users receive HTTP 403 via `wp_die()`. Empty inventory shows “No buildings found.” Canonical behavior is not explicitly controlled. | Conditionally functional after activation and rewrite flush. |
| `/portal/building/{id}/` | Digits-only rewrite maps to the building template. It checks access, loads the building, queries related published floors, chooses the first sorted floor, configures REST, and includes `portal-shell.php`. | Unauthorized: 403. Unknown numeric ID/wrong post type: theme 404. Draft/private/trashed building posts pass the type-only check. Missing or non-numeric IDs do not match and fall through to WordPress. | Mostly functional; lifecycle, status validation, query override, and canonical behavior need fixes/tests. |
| `/portal-test/` | Not a rewrite. A normal WordPress Page must satisfy `is_page('portal-test')`; priority-99 template override calls `[pera_portal]` without attributes. | Requires a database Page. Unauthorized users get an inline denial rather than an HTTP 403. Building/floor are both zero and the shell has no building selector. | Not reliably deployable and likely functionally empty. |

## Functional trace and edge cases

### `/portal/`

1. `pera_portal_register_page_rewrites()` maps the route (`includes/routing/portal-pages.php`, lines 7-14).
2. `pera_portal_maybe_override_page_template()` selects `portal-landing.php` (`includes/routing/portal-pages.php`, lines 27-54).
3. The template enforces portal access before rendering (`templates/portal-landing.php`, lines 7-13).
4. It performs one unbounded query for published buildings (`templates/portal-landing.php`, lines 15-21).
5. It renders escaped links to `/portal/building/{ID}/` and a clear empty state (`templates/portal-landing.php`, lines 33-49).
6. It uses the active theme header/footer and therefore participates in normal `wp_head()`, `body_class()`, `wp_body_open()`, navigation, and footer behavior (`themes/hello-elementor-child/header.php`, lines 18-38).

### `/portal/building/{id}/`

1. Only `[0-9]+` matches the route (`includes/routing/portal-pages.php`, lines 9-11).
2. Access is checked before building validity, limiting anonymous HTML-route enumeration (`templates/portal-building.php`, lines 7-15).
3. `absint()` normalizes the query var and `get_post()` resolves it.
4. Missing/wrong post types become an explicit 404 using the active theme’s 404 template (`templates/portal-building.php`, lines 15-30).
5. Post status is not checked.
6. Published floors related through the `building` meta key are loaded and sorted numerically where possible (`templates/portal-building.php`, lines 32-83).
7. The first floor is localized along with REST URL, nonce, building ID, and external mode (`templates/portal-building.php`, lines 85-99).
8. `portal-shell.php` supplies the viewer controls, placeholders, comparison table, share actions, and quote tools.
9. JavaScript loads units first and SVG second. It distinguishes access failures, missing SVG, SVG parsing failures, and general request errors (`assets/dist/portal-viewer.js`, lines 962-1024 and 1153-1176).
10. A `?floor_id=` query value overrides the localized floor. If it belongs to another building, the protected units endpoint correctly rejects the mismatch (`assets/dist/portal-viewer.js`, lines 153-160; `includes/rest/routes.php`, lines 287-297), but the UI errors instead of falling back.

### `/portal-test/`

1. WordPress must first resolve an actual Page whose slug is `portal-test`.
2. The plugin replaces the theme/Elementor template at priority 99 (`includes/frontend/template-routing.php`, lines 7-25).
3. The replacement calls `[pera_portal]` with no attributes (`templates/page-portal-test.php`, lines 7-13).
4. The shortcode defaults building and floor to zero (`includes/shortcodes/portal-shortcode.php`, lines 18-31).
5. The shared shell contains a floor selector but no building selector (`templates/portal-shell.php`, lines 33-43).
6. JavaScript cannot list floors without a building and leaves the empty-state messaging in place (`assets/dist/portal-viewer.js`, lines 1278-1310).

### Empty and malformed records

- No buildings: explicit landing empty state.
- Building without floors: viewer reports no floors for that building (`assets/dist/portal-viewer.js`, lines 969-972).
- Missing production SVG: REST 404; non-production may use the fixture (`includes/rest/routes.php`, lines 35-51).
- Invalid numeric sizes/prices: returned as `null`; price-per-square-metre avoids division by zero (`includes/rest/routes.php`, lines 338-346).
- Unknown statuses are normalized to `available`, which is robust technically but can misrepresent malformed inventory (`includes/rest/routes.php`, lines 348-357).
- Empty unit codes and units without matching SVG IDs are ignored by the interactive mapping (`assets/dist/portal-viewer.js`, lines 1026-1057).
- If no units match the SVG, a clear unavailable message is shown (`assets/dist/portal-viewer.js`, lines 1081-1085).
- Missing unit plan URLs are handled through safe URL validation and placeholders.

## Findings

No Critical issue was confirmed statically.

| Severity | Confidence | Area | Evidence | Impact | Recommended fix |
|---|---|---|---|---|---|
| **High** | Confirmed | Plugin lifecycle/rewrite rules | Routes are added on `init`; no activation/deactivation hook or rewrite flush exists. The plugin only warns on the Permalinks screen (`includes/routing/portal-pages.php`, lines 7-25 and 56-82). | Clean activation can leave every virtual route at 404. | Add activation and deactivation callbacks. Activation should register rules and flush once; deactivation should flush once. Never flush per request. |
| **High** | Confirmed behavior; exploitability depends on installed roles | CPT authorization | Buildings, floors, units, and quotes use generic `post` capabilities and show UI (`includes/cpt/building.php`, `includes/cpt/floor.php`, `includes/cpt/unit.php`, and `includes/cpt/quote.php`, lines 21-29). Buildings, floors, and units expose core REST; `pera_quote` does not, but it is also absent from the disallowed-menu removals (`includes/admin/menu.php`, lines 30-45). | Depending on installed role capabilities, ordinary post editors may reach direct edit URLs without portal access, alter inventory, or edit/trash live quotes; core REST may additionally expose CRUD for the three REST-enabled CPTs. | Define explicit capabilities with `map_meta_cap` for all four CPTs, migrate only approved roles, test direct-admin access for each CPT, and set an explicit REST policy for each CPT including `pera_quote`. |
| **High** | Confirmed exposure; sensitivity needs business confirmation | Floor/SVG REST disclosure | `/floor` and `/floors` allow anonymous access to published records and return IDs, labels, direct SVG URLs, and SVG content (`includes/rest/routes.php`, lines 69-127, 379-447, and 595-660). | Anonymous building/floor enumeration and floor-plan disclosure conflict with the staff-only HTML portal. Unit prices remain protected. | Decide whether plans are public. If private, require portal access and use authenticated file delivery rather than public Media Library URLs. |
| **High** | Confirmed risk; exploitability requires SVG/content control | SVG serving and remote retrieval | The endpoint reads and serves raw SVG and may use `wp_remote_get()` on the field URL (`includes/rest/routes.php`, lines 17-66). Client sanitation happens only inside the viewer (`assets/dist/portal-viewer.js`, lines 104-138). | Direct same-origin SVG requests do not benefit from client sanitation; externally controlled URLs could induce server-side requests. | Sanitize at upload/save and response, restrict delivery context, eliminate arbitrary remote fetches or enforce a host allowlist and safe HTTP API. |
| **Medium** | Confirmed | `/portal-test/` | Requires a database Page and invokes the shortcode with building/floor zero; shared shell has no building selector. | Route can be 404 or render a non-operational viewer; unauthorized response is not HTTP 403. | Register and seed a deterministic QA route or remove it and its compatibility path. |
| **Medium** | Confirmed | Quote permissions/revocation | Every quote helper ends with general portal access, making dedicated caps ineffective (`includes/capabilities.php`, lines 91-121); revoke calls the create helper (`includes/rest/quote-routes.php`, lines 77-92). | Every portal user can create and revoke any quote. | Enforce distinct create/manage/revoke permissions and optionally ownership. |
| **Medium** | Confirmed behavior; policy decision needed | Public quote privacy | Token pages show price, plans, client contact data, consultant note, and issuer. Revoked/expired quotes still show the full payload (`templates/portal-quote.php`, lines 38-57 and 117-168). Tokens are strong (`includes/quotes/token-service.php`, lines 7-18). | Forwarded/logged/leaked URLs retain PII and commercial information after revocation. | Decide whether expiry/revocation hides content, returns 410, or requires secondary verification; define retention. |
| **Medium** | Confirmed | Quote source validation | Quote service checks types/relations but not source post statuses or unit availability and accepts arbitrary sanitized currency (`includes/quotes/snapshot-service.php`, lines 75-111). | Crafted requests can quote draft/private/sold units or unsupported currencies. | Enforce post status, availability policy, currency allowlist, and expiry constraints server-side. |
| **Medium** | Confirmed | CSV export | Text fields are written directly with `fputcsv()` (`includes/admin/units-manager-page.php`, lines 998-1016). | Formula-like values may execute when opened in spreadsheet software. | Neutralize cells beginning with `=`, `+`, `-`, or `@`. |
| **Medium** | Confirmed | Asset maintainability | Source JS modules export empty objects; source CSS is a one-line scaffold; production uses large committed `dist` files. | Deployment artifacts cannot be reproduced or reviewed from source. | Restore real source/build tooling or formally maintain `dist` as authoritative source. |
| **Low** | Confirmed | Building status validation | Building route checks type but not `post_status` (`templates/portal-building.php`, lines 15-30). | Authorized users can open known draft/private/trashed buildings. | Require `publish` for external mode or explicitly support internal previews. |
| **Low** | Confirmed | Query-string floor override | URL `floor_id` overrides server config; mismatch is rejected rather than recovered. | Stale/malformed shared links produce avoidable errors. | Validate against returned floors and fall back to the first valid floor. |
| **Low** | Confirmed | Quote references | Reference is daily matching-count plus one (`includes/quotes/token-service.php`, lines 21-36). | Concurrent creations can receive duplicate human references. | Use a collision-safe sequence/suffix or check-and-retry. |
| **Low** | Confirmed | Performance | Multiple queries use `posts_per_page => -1`; unit serialization performs several field reads per item. Portal paths disable page, DB, object cache, and minification (`includes/cache/nocache.php`, lines 125-161). | Cost grows with inventory; disabling object/DB cache may increase load without improving HTML privacy. | Measure real inventory/query counts, keep private HTML no-store, and remove unnecessary cache-disabling constants if safe. |
| **Low** | Confirmed, not currently exploitable from known responses | Quote-result DOM construction | `safeText()` only stringifies, but quote success values are interpolated into `innerHTML` (`assets/dist/portal-viewer.js`, lines 82-84 and 619-625). | Future filters/API changes could turn response fields into DOM XSS. | Build DOM with `textContent` and validate the public URL. |
| **Low** | Confirmed | Bootstrap/docs | Required modules are silently skipped; documentation still describes MU-plugin behavior. | Partial deployments fail ambiguously and instructions mislead maintainers. | Require mandatory files and update standard-plugin documentation. |

## Security and access-control matrix

| Surface | Current access |
|---|---|
| `/portal/` | PeraCRM-access user, administrator fallback, or multisite network administrator/super-admin |
| `/portal/building/{id}/` | Same as landing |
| `/portal-test/` | Normal Page route; shortcode displays inline denial to unauthorized visitors |
| `/wp-json/pera-portal/v1/floors` | Anonymous for a published building |
| `/wp-json/pera-portal/v1/floor` | Anonymous for a published floor |
| `/wp-json/pera-portal/v1/units` | Portal/CRM access required |
| `/wp-json/pera-portal/v1/diag` | Portal/CRM access required |
| Quote creation | Any portal/CRM user |
| Quote revocation | Any portal/CRM user |
| Public quote | Anyone holding the high-entropy token |
| CPT admin/core REST | Buildings, floors, units, and `pera_quote` use generic WordPress post capabilities in admin; core REST is enabled for the first three and disabled for `pera_quote` |
| Units Manager writes/import/delete | Any portal/CRM user plus a valid nonce |

The Units Manager custom handlers do consistently check portal access and nonces. Destructive unit deletion additionally verifies post type and floor membership (`includes/admin/units-manager-page.php`, lines 895-923). CSV preview verifies access, nonce, selected context, filename/type, and `is_uploaded_file()` (`includes/admin/units-manager-page.php`, lines 1023-1059).

## Theme, Elementor, conditionals, and canonical behavior

- `/portal/` and `/portal/building/...` are virtual rewrite templates, not Elementor Pages.
- `/portal-test/` is a normal Page intercepted at template priority 99; its Elementor/page content is bypassed.
- Plugin templates use `get_header()` and `get_footer()`, so they integrate with the active theme lifecycle.
- The child theme header calls `wp_head()`, `body_class()`, and `wp_body_open()` (`themes/hello-elementor-child/header.php`, lines 18-38).
- The plugin does not directly call theme-only helpers, so changing themes should not fatal these templates. Visual output nevertheless depends on generic theme class names such as `hero`, `container`, `section`, and `btn` used by `portal-shell.php`.
- Plugin template filters run at priorities 98/99, after the child theme’s property template filters at priorities 20/30, reducing direct conflict.
- No `redirect_canonical` exception exists for portal rewrites. Trailing-slash, query-selection, SEO-plugin, and canonical behavior require runtime checks.
- Main-query pagination is not used; all portal data queries are intentionally unpaged.

## Responsive/UI assessment

Static CSS provides a two-column viewer that collapses to one column at 900px, several 640px mobile adaptations, horizontal overflow for comparison tables, print rules, and dark-mode variants. No definite CSS blocker was identified statically.

Browser testing remains necessary for:

- wide or malformed SVG dimensions;
- long development/floor/unit names;
- comparison tables with many units;
- 320px quote forms and toolbar wrapping;
- theme header overlap;
- focus visibility and keyboard-only operation;
- print/canvas fallback;
- touch shortlist interaction, because the UI instructs users to Shift+click (`templates/portal-shell.php`, lines 116-135), which has no clear mobile equivalent.

## Performance and maintainability

### Worthwhile improvements

1. Fix rewrite lifecycle rather than adding runtime flushes.
2. Introduce explicit portal data capabilities.
3. Decide whether shortcode embedding remains supported; if not, remove probing and late-enqueue compatibility.
4. Restore reproducible assets or make `dist` explicitly authoritative.
5. Replace daily count-based quote references regardless of scale because concurrency is the primary defect.
6. Profile inventory queries before introducing pagination/caching abstractions.
7. Retain private HTML no-cache headers, but reconsider `DONOTCACHEDB` and `DONOTCACHEOBJECT`.

### Positive implementation details

- PHP direct-access guards are consistently present.
- Route IDs are normalized with `absint()`.
- REST argument definitions include types, sanitizers, and validators (`includes/rest/routes.php`, lines 597-660).
- Unit response output normalizes numeric/status data and sanitizes URL, file, and MIME fields (`includes/rest/routes.php`, lines 338-373).
- Templates generally escape output appropriately.
- Most data-driven viewer UI uses DOM construction and `textContent` (`assets/dist/portal-viewer.js`, lines 211-259).
- Viewer loading and error states are explicit.
- Quote tokens use `random_bytes(32)`.
- Diagnostics and CSV preview-before-commit provide useful operational controls.
- Asset cache-busting uses per-file mtimes with a deploy build-stamp floor (`includes/assets/enqueue.php`, lines 32-72).

## Deployment readiness

### Included in the repository

- Standard plugin loader and PHP modules.
- Landing, building, shared viewer, test, and public quote templates.
- Compiled CSS and JavaScript.
- ACF Local JSON for buildings, floors, and units.
- Development fixture SVG.
- Asset build-stamp scripts.
- CPT/post-meta quote storage implementation.

### Not included or not reproducible

- WordPress core/configuration/database.
- Production buildings, floors, units, quotes, and the `portal-test` Page.
- Uploaded production SVGs and unit plan media; uploads are gitignored (`.gitignore`, lines 6-15).
- Real JavaScript/CSS source and build tooling.
- Automated tests.
- Portal role/capability installation/migration.
- Rewrite activation lifecycle.
- Translation files.
- Explicit minimum WordPress/PHP plugin headers.

### Server-side dependencies and settings

1. **WordPress with pretty permalinks** for virtual routes.
2. **Plugin activation**, either site or network scope as intended.
3. **PeraCRM or an explicit access policy.** Default `reuse_crm` delegates to `peracrm_user_can_access_crm()` and otherwise falls back to administrators (`includes/config.php`, lines 15-21; `includes/capabilities.php`, lines 65-78).
4. **Advanced Custom Fields.** Scalar meta fallbacks exist, but floor SVG and plan resolution depend materially on ACF-shaped values. `pera_portal_rest_get_floor_svg_asset()` returns no asset when `get_field()` is unavailable (`includes/rest/routes.php`, lines 175-191).
5. **Trusted SVG upload support and sanitization.** The ACF field accepts SVG (`acf-json/group_pera_floor_fields.json`, lines 53-70), while WordPress does not universally permit SVG by default.
6. **Writable uploads.** Quote creation copies unit-plan attachments into uploads (`includes/quotes/media-service.php`, lines 50-85).
7. **Correct `WP_ENVIRONMENT_TYPE`.** It controls missing-SVG fixture behavior.
8. **CDN/cache exclusions** that honor authenticated/private portal responses.
9. **No custom table or cron dependency** was found; data uses posts/post meta.
10. **No mandatory external service** exists for local attachments, though remote SVG retrieval is possible and should be constrained.
11. **Quote retention/cleanup.** Copied quote attachments can accumulate; no cron cleanup exists.

### Post-deployment verification

1. Confirm the standard plugin is active and no previous MU loader remains on the server.
2. Confirm activation creates working rewrites without manually saving Permalinks after the lifecycle fix.
3. Confirm `/portal/` gives the chosen unauthorized behavior and HTTP 200 to an authorized user.
4. Confirm no authenticated markup is served to another session by CDN/full-page cache.
5. Confirm landing records match production buildings and only intended statuses appear.
6. Confirm each building exposes only its intended floors.
7. Confirm every floor SVG exists and expected SVG IDs match unit codes.
8. Run Portal Diagnostics for every production floor.
9. Confirm `/portal-test/` works according to its approved purpose or is removed.
10. Verify anonymous REST exposure matches the approved floor-plan policy.
11. Verify `/units` denies anonymous access and core REST does not bypass portal policy.
12. Verify Subscriber/Author/Editor/CRM/administrator roles against direct list/create/edit/trash URLs for `pera_building`, `pera_floor`, `pera_unit`, and `pera_quote`.
13. Create, open, expire, revoke, and remove a quote; verify PII and attachment retention behavior.
14. Verify malicious SVG constructs cannot execute through direct upload or REST URLs.
15. Confirm CSS/JS URLs return HTTP 200 with updated `?ver=` values after deployment.
16. Inspect cache, Vary, and no-store headers at the CDN edge, not only PHP origin.

## Quick wins

1. Add standard activation/deactivation rewrite hooks.
2. Decide and repair or remove `/portal-test/`.
3. Require a published building in external viewer mode.
4. Validate query-string floor IDs and fall back cleanly.
5. Use the revoke capability helper and remove tautological quote permissions.
6. Replace quote-result `innerHTML` with safe DOM construction.
7. Neutralize spreadsheet formulas in CSV exports.
8. Update MU-plugin terminology and deployment instructions.
9. Remove or implement the two unused service stubs.
10. Fail loudly for missing mandatory bootstrap files.

## Needs decision

1. Are published floor names and SVG plans confidential?
2. Who may administer buildings, floors, and units: administrators, all CRM users, or a manager subset?
3. Who may create, manage, and revoke quotes? Should ownership matter?
4. Should revoked/expired quotes retain full public payload, return 410, hide PII, or require secondary verification?
5. May reserved or sold units be quoted?
6. Is `/portal-test/` a permanent production QA route?
7. Should authorized users preview draft/private buildings through front-end URLs?
8. Should portal access remain coupled to PeraCRM capabilities?
9. How long should quote PII and copied attachments be retained?
10. Should every portal user see price data? `shouldShowPrice()` currently always returns true (`assets/dist/portal-viewer.js`, lines 173-180).

## Proposed implementation plan

No implementation was performed as part of the audit.

### Priority 0 — Policy

1. Decide floor-plan confidentiality.
2. Define portal-data administration roles.
3. Define quote creation/revocation/expiry/retention policy.
4. Decide the fate of `/portal-test/` and arbitrary shortcode embedding.

### Priority 1 — Deployment and access-control blockers

1. Add standard activation/deactivation rewrite flushing.
2. Add explicit CPT capabilities and migrate approved roles.
3. Align core REST exposure with those capabilities.
4. Protect floor REST endpoints and stored files if plans are private.
5. Sanitize SVGs at ingestion and delivery; remove/constrain remote fetching.

### Priority 2 — Route and quote correctness

1. Enforce building status by viewer mode.
2. Validate query floor against building floors and fall back safely.
3. Repair or remove `/portal-test/`.
4. Enforce quote-specific permissions and optional ownership.
5. Validate quote source statuses, availability, currency, and expiry.
6. Implement the approved revoked/expired behavior.
7. Replace count-based references with a collision-safe generator.

### Priority 3 — Defense in depth and operations

1. Prevent CSV formula injection.
2. Replace unsafe result HTML construction.
3. Add quote/media retention cleanup.
4. Require mandatory bootstrap modules.
5. Update plugin metadata and documentation.

### Priority 4 — Maintainability, tests, and measured performance

1. Restore reproducible source/build tooling or designate `dist` as source.
2. Add PHP route/access tests and browser viewer tests.
3. Add malicious SVG, malformed record, invalid ID, and quote authorization tests.
4. Profile with realistic inventory volumes before query abstractions.

## Testing checklist

Use a staging hostname in place of `https://staging.example.com`.

### Activation and rewrites

- Activate from an initially inactive state without visiting Permalinks.
- Request `/portal/` and `/portal/building/{VALID_ID}/`.
- Deactivate/reactivate and repeat.
- Test trailing and non-trailing slash forms.
- Confirm one canonical destination and no redirect loop.
- Confirm no old MU loader or function redeclaration.

### `/portal/`

- Anonymous, Subscriber, each relevant CRM capability role, administrator, and multisite super-admin.
- No buildings, one building, many buildings, and long/special-character titles.
- Draft/private/trash buildings excluded.
- Cross-session/full-page cache isolation.

### `/portal/building/{id}/`

- Published building with multiple numeric and non-numeric floor labels.
- Building with no floors or only unpublished floors.
- Wrong post type, missing numeric ID, `0`, huge ID, and leading zeros.
- Missing ID, alphabetic ID, and mixed numeric/alphabetic ID.
- Draft/private/trash building.
- Valid floor query, other-building floor query, non-numeric/negative/huge floor query.
- Valid/missing/malformed `unit` and `units` query strings.
- Canonical handling for all query strings.

### Data/SVG

- Valid SVG with all units mapped.
- Missing SVG in production and staging.
- Malformed/non-SVG upload.
- SVG with `script`, `foreignObject`, event attributes, `javascript:` URL, external images, CSS imports, and oversized XML.
- Direct anonymous request to raw floor endpoint.
- Empty/duplicate unit codes, missing SVG IDs, and orphan SVG IDs.
- Empty/non-numeric/zero prices and sizes.
- Unknown currency/status.
- Missing/deleted/large unit plan attachment.

### REST

- Anonymous, unauthorized authenticated, and portal users against `/floor`, `/floors`, `/units`, and `/diag`.
- Invalid/expired nonce.
- Wrong building/floor pair.
- Private/draft/trash records.
- Missing, non-numeric, zero, negative, and overflow IDs.
- Core REST CRUD for portal CPTs as Subscriber, Author, Editor, CRM user, and administrator.

### `/portal-test/`

- Page absent, draft, private, and published.
- Page with Elementor content.
- Authorized and unauthorized sessions.
- Confirm it can actually select a building/floor.
- Confirm intended HTTP status and sitemap/indexing behavior.

### Quotes

- Available, reserved, sold, draft, private, and trashed units.
- Broken unit/floor/building relationships.
- Zero, negative, non-numeric, huge, and high-precision price.
- Allowed and unsupported currency.
- Past, current, malformed, DST-boundary, and far-future expiry.
- Missing SVG and missing unit plan.
- Concurrent creations for reference collision.
- Creation and revocation under every role; own and another user’s quote.
- Active, expired, revoked, missing, and random-token public URLs.
- PII exposure and approved status codes in every state.
- Quote deletion and orphaned attachment inspection.

### Admin and CSV

- Direct building, floor, unit, and quote CPT list/create/edit/trash endpoints as Subscriber, Author, Editor, CRM user, and administrator.
- Core REST create/read/update/delete attempts for `pera_building`, `pera_floor`, `pera_unit`, and `pera_quote` as Subscriber, Author, Editor, CRM user, and administrator, including confirmation that the current `pera_quote` REST policy remains disabled unless intentionally changed.
- Units Manager actions with missing, invalid, reused, and valid nonces.
- Cross-building floor/unit tampering.
- CSV wrong extension/MIME, empty/large file, missing/unexpected columns, duplicate codes, bad numeric/status/currency data, commas/newlines, BOM, and high row count.
- Export cells beginning `=`, `+`, `-`, and `@`, opened in the organization’s spreadsheet application.

### Browser/responsive/error states

- Current Chrome, Safari, Firefox, and Edge; iOS Safari and Android Chrome.
- 320, 375, 768, 900, 1024, and 1440px widths.
- Keyboard, screen reader, visible focus, touch shortlist, dark mode, forced colors, and reduced motion.
- Long labels, many shortlist rows, large SVGs, and 320px quote form.
- Print preview in Chrome/Safari, including canvas fallback.
- Clipboard denied, popup blocked, slow network, offline, REST 500/timeout/invalid JSON, malformed SVG.

## Checks performed

- `find wp-content/plugins/pera-portal -type f -printf '%p\n' | sort` — passed.
- `rg -n --hidden -S "pera[-_ ]?portal|portal-shell|portal-test|portal/building|pera_portal|building_id" wp-content` — passed.
- `find wp-content/plugins/pera-portal -name '*.php' -print0 | xargs -0 -n1 php -l` — all plugin PHP files passed.
- `node --check wp-content/plugins/pera-portal/assets/dist/portal-viewer.js` — passed.
- JSON decoding with `JSON_THROW_ON_ERROR` for all `acf-json/*.json` files — passed.
- `rg -n "register_(activation|deactivation)_hook|flush_rewrite_rules" wp-content/plugins/pera-portal` — no lifecycle hooks found.
- Search for plugin-local tests/build manifests — none found.
- `git diff --check` — passed at audit time.
- `git status --short` — clean at audit time.
- `wp plugin status pera-portal` — not available because this checkout has no runnable WordPress/WP-CLI environment.
- Live `curl` and authenticated browser checks — not run because no staging/live runtime was supplied.
