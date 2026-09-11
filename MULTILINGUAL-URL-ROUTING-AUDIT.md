# Multilingual URL-routing audit

## Executive summary

Audit date: 2026-09-11. Scope was recursively inventoried across `wp-content/themes/hello-elementor-child/`, `wp-content/plugins/peracrm/`, and `wp-content/plugins/pera-multilingual/`. The inventory contains **771 files** (**591 PHP/JS/TS/HTML source/render candidates**). A combined search for URL constructors, literal internal links, redirects, permalink APIs, and JavaScript navigation produced **844 unique candidate lines**. Expanding the one source line that contains four distinct destinations yields **847 URL candidate occurrences**, all of which were triaged.

| Classification | Candidate occurrences |
|---|---:|
| Confirmed unsafe — maintained code (fixed) | 43 |
| Confirmed unsafe — legacy/pending deletion (not fixed) | 4 |
| Safe / no action | 688 |
| Intentional exclusions | 109 |
| Uncertain / manual review | 3 |
| **Total reviewed** | **847** |

The audit confirmed 47 unsafe visitor destinations: 43 in active maintained code across 16 theme rendering files were fixed through `pera_ml_url( home_url( ... ) )`, while four belong to the legacy standalone Chinese template and are intentionally left unchanged pending deletion. No second routing system was added. No confirmed language-dropping visitor link remains in the maintained translated architecture. Three content-driven cases remain a production-content review concern, not a confirmed source-code defect.

## Method

The recursive review covered PHP templates/includes/partials, frontend and AJAX renderers, PHP-localized data, and JavaScript. Search families included `home_url`/`site_url`/`network_home_url`, literal root-relative and same-site absolute destinations, permalink/term/archive functions, redirects, forms, `location`/`.href`, and structured SEO URLs. Each hit was traced to its consumer rather than classified mechanically. Vendor code was inventoried but technical library URLs were excluded.

## P0 Critical

None.

## Count reconciliation

The original summary incorrectly used **44**, the number of production **source lines initially changed**, as the unsafe URL count. The audit contains **47 distinct visitor destinations**: `page-sell-with-pera.php` line 576 holds four separate links on one source line. URL-006 accounts for four genuine unsafe occurrences but, after review, they are not production fixes: that standalone legacy template will be deleted. The maintained-code patch therefore fixes 43 destinations on 40 source lines. No unsafe group is duplicated, and no single construction fans out into multiple counted destinations. The candidate denominator remains 847 URL occurrences rather than 844 unique matching lines.

| ID | File | Previous count | Verified exact count | Reconciliation |
|---|---|---:|---:|---|
| URL-001 | `404.php` | 7 | 7 | Seven distinct rendered links. |
| URL-002 | `page-contact.php` | 12 | 12 | Six district links and six CTA-card links. |
| URL-003 | `page-sell-with-pera.php` | 4 | 4 | Four distinct anchors share one physical source line; this is the three-occurrence line-count discrepancy. |
| URL-004 | `single-post.php` | 4 | 4 | One archive fallback plus three rendered CTA/home destinations. |
| URL-005 | `page-luxury-property.php` | 4 | 4 | Four independent URL assignments/rendered destinations. |
| URL-006 | `page-zh-citizenship.php` | 4 | 4 | Four genuinely unsafe Chinese-context links, but this legacy template predates multilingual routing and is pending deletion; no fix is retained. The two explicit English links remain unchanged. |
| URL-007 | `inc/ajax-property-archive.php` | 1 | 1 | One computed AJAX pagination base; it can render many page numbers but is counted once syntactically. |
| URL-008 | `inc/property-pagination.php` | 1 | 1 | One path-to-absolute pagination fallback; counted once syntactically. |
| URL-009 | `page-citizenship.php` | 2 | 2 | Two distinct in-copy links. |
| URL-010 | `page-book-a-consultancy.php` | 1 | 1 | One privacy-policy link. |
| URL-011 | `page-property-map.php` | 1 | 1 | One consultancy CTA. |
| URL-012 | `parts/contact-cta.php` | 1 | 1 | One shared-part CTA; inclusion at multiple pages is not double-counted. |
| URL-013 | `partials/citizenship-latest-offers.php` | 1 | 1 | One URL array value, counted once even if reused while rendering. |
| URL-014 | `partials/portfolio-citizenship-cta.php` | 1 | 1 | One guide URL assignment. |
| URL-015 | `single-bodrum-property.php` | 1 | 1 | One primary CTA URL assignment. |
| URL-016 | `attachment.php` | 1 | 1 | One Home link. |
| URL-017 | `archive.php` | 1 | 1 | One empty-state Home link. |
| **Total** |  | **47** | **47** | Verified audit total: 43 maintained-code fixes plus 4 legacy findings pending deletion. |

## P1 High — confirmed unsafe and fixed

Each row is a confirmed occurrence group; the count identifies every call in that section.

| ID | Severity | File / section | Count | URL source and example | Frontend context / prior behavior | Classification and remediation | Existing handling |
|---|---|---|---:|---|---|---|---|
| URL-001 | P1 | `404.php` buttons and recovery list | 7 | `home_url( '/property/' )` | Error-page navigation from `/de/`, `/ar/`, or `/zh/` fell back to English. | UNSAFE; wrap each destination with `pera_ml_url()`. Production change: yes. | None before fix. |
| URL-002 | P1 | `page-contact.php` district links and CTA cards | 12 | `home_url( '/district/istanbul/besiktas/' )` | Contact-page related navigation silently left the active language. | UNSAFE; route at render time. Production change: yes. | None before fix. |
| URL-003 | P1 | `page-sell-with-pera.php` closing related links | 4 | `home_url( '/about-us/' )` | Inline visitor links returned translated visitors to English. | UNSAFE; route at render time. Production change: yes. | None before fix. |
| URL-004 | P1 | `single-post.php` archive fallback, owner CTAs, empty-state home | 4 | `home_url( '/sell-your-istanbul-real-estate/' )` | Blog navigation/CTAs lost the prefix when using fallbacks or fixed paths. | UNSAFE; route fixed/fallback destinations. Production change: yes. | `get_permalink()` branch remains router-filtered; only raw fallback changed. |
| URL-005 | P1 | `page-luxury-property.php` taxonomy/guide/blog/consultancy destinations | 4 | `home_url( '/tag/luxury-istanbul/' )` | Luxury landing-page CTAs and fallback links lost language. | UNSAFE; route raw fixed/fallback destinations. Production change: yes. | Dynamic `get_permalink()` branch remains unchanged and filtered. |
| URL-007 | P1 | `inc/ajax-property-archive.php` AJAX pagination base | 1 | `$pagination_base = home_url( ... )` | AJAX pagination could emit English `/page/N/` links. Query arguments could survive while the language prefix did not. | UNSAFE; localize the completed base once. Production change: yes. | Shared pagination renderer did not localize an absolute base. |
| URL-008 | P1 | `inc/property-pagination.php` path-to-URL fallback | 1 | `$base = home_url( ... )` | A caller supplying a path could produce unprefixed pagination. | UNSAFE; localize after converting the path. Production change: yes. | Existing absolute URLs and `get_pagenum_link()` remain untouched. |

## Legacy / pending deletion — confirmed unsafe, intentionally not fixed

| ID | Severity | File / section | Count | URL source and example | Current behavior / architecture status | Classification and disposition | Existing handling |
|---|---|---|---:|---|---|---|---|
| URL-006 | P1 legacy | `page-zh-citizenship.php` Chinese visitor destinations | 4 | `home_url( '/privacy-policy/' )` | The links can lose `/zh/` under the current router contract. The standalone template predates Pera Multilingual and sits outside the maintained translated architecture. | **LEGACY / PENDING DELETION.** Confirmed unsafe, but the template will be deleted rather than remediated; no production fix is required or retained. | The two explicit “View this page in English” links also remain unchanged and intentionally unprefixed. |

## P2 Medium — confirmed unsafe and fixed

| ID | Severity | File / section | Count | URL source and example | Frontend context / prior behavior | Classification and remediation | Existing handling |
|---|---|---|---:|---|---|---|---|
| URL-009 | P2 | `page-citizenship.php` related-property/contact links | 2 | `home_url( '/property/' )` | In-copy navigation dropped the active prefix. | UNSAFE; route with existing helper. Production change: yes. | None before fix. |
| URL-010 | P2 | `page-book-a-consultancy.php` privacy link | 1 | `home_url( '/privacy-policy/' )` | The form’s visitor-facing policy link opened English. | UNSAFE; route at render time. Production change: yes. | The form endpoint itself is unchanged. |
| URL-011 | P2 | `page-property-map.php` consultancy CTA | 1 | `home_url( '/book-a-consultancy/' )` | Map CTA fell back to English. | UNSAFE; route with existing helper. Production change: yes. | None before fix. |
| URL-012 | P2 | `parts/contact-cta.php` consultancy CTA | 1 | `home_url( '/book-a-consultancy/' )` | Shared CTA could drop language wherever included. | UNSAFE; route with existing helper. Production change: yes. | None before fix. |
| URL-013 | P2 | `partials/citizenship-latest-offers.php` card-list CTA | 1 | array `url => home_url( ...?view=cards )` | Rendered CTA lost prefix; query string survived. | UNSAFE; route value at construction. Production change: yes. | `pera_ml_url()` preserves query parameters. |
| URL-014 | P2 | `partials/portfolio-citizenship-cta.php` guide CTA | 1 | `$guide_url = home_url( ... )` | Portfolio CTA dropped language. | UNSAFE; route render value. Production change: yes. | None before fix. |
| URL-015 | P2 | `single-bodrum-property.php` primary CTA | 1 | `site_url( '/book-a-consultancy/' )` | Primary property CTA bypassed the home/router contract. | UNSAFE; use `pera_ml_url( home_url(...) )`. Production change: yes. | None before fix. |

## P3 Low / cleanup — confirmed unsafe and fixed

| ID | Severity | File / section | Count | URL source and example | Frontend context / prior behavior | Classification and remediation | Existing handling |
|---|---|---|---:|---|---|---|---|
| URL-016 | P3 | `attachment.php` Home pill | 1 | `home_url( '/' )` | Attachment navigation returned translated visitors to English home. | UNSAFE; route home. Production change: yes. | None before fix. |
| URL-017 | P3 | `archive.php` empty-state home CTA | 1 | `home_url( '/' )` | Empty archive navigation returned to English home. | UNSAFE; route home. Production change: yes. | None before fix. |

## Safe / no action (688)

| ID | Representative locations | Why safe |
|---|---|---|
| SAFE-001 | `home-page.php`, `footer.php`, `archive-property.php`, `single-property.php`, `parts/enquiry-form.php` | Fixed internal visitor destinations already call `pera_ml_url()`/`pera_ml_home_url()`; fallback branches apply only when the multilingual plugin contract is unavailable. |
| SAFE-002 | Theme cards, archives and related-post renderers using `get_permalink()`, `get_term_link()`, `get_post_type_archive_link()`, `get_pagenum_link()` | WordPress-generated URLs are filtered by the authoritative router. Extra wrapping would be redundant and risks double handling. |
| SAFE-003 | `inc/theme-helpers.php` and homepage rich-text renderers | `pera_localize_visitor_links()` rewrites editor-authored anchor `href` values at render time through `pera_ml_url()` without mutating ACF storage. |
| SAFE-004 | Property archive form/current-page bases already passed through `pera_ml_url()` | Query strings/fragments are handled by the router; already-localized paths are idempotent. |
| SAFE-005 | `peracrm` `/crm/` workspace routes and frontend-data client URLs | Authenticated operational CRM navigation is an application namespace, not translated public-site navigation. |
| SAFE-006 | JavaScript navigation hits | Reviewed `location` and `.href` assignments resolve server-provided/permalink destinations, external destinations, same-document fragments, or CRM application routes; no additional public language-loss defect was confirmed. |

## Intentional exclusions (109)

| ID | Representative locations | Exclusion |
|---|---|---|
| EX-001 | `pera-multilingual/includes/class-seo.php`, theme SEO/schema modules | Canonical, hreflang, organization/schema, sitemap/feed and current-origin construction intentionally model canonical/system URLs. |
| EX-002 | Forms using `/wp-admin/admin-post.php`, AJAX/REST/nonces, service worker and asset/media URLs | Technical action or asset endpoints must not receive a language prefix. |
| EX-003 | CRM/admin/login/logout/impersonation redirects and admin-bar URLs | Auth/system and wp-admin-only destinations are outside visitor translation routing. |
| EX-004 | `page-zh-citizenship.php` “View this page in English” destinations | Explicit cross-language English navigation is intentionally unprefixed. |
| EX-005 | `wa.me`, `tel:`, `mailto:`, map/social hosts and third-party integration/webhook URLs | External destinations remain untouched. |

## Uncertain / manual review (3)

| ID | File / source | Concern | Recommendation | Production change |
|---|---|---|---|---|
| REVIEW-001 | Generic post/page `the_content` output | Stored block/editor HTML can contain legacy same-site absolute anchors; core/router filters do not necessarily rewrite arbitrary anchor markup. | Sample production translated content for legacy anchors before adding any broad content filter. Avoid stored-value mutation. | No. |
| REVIEW-002 | Repeater/link-type ACF values consumed by generic CTA components | WordPress ACF link fields may hold an editor-entered absolute internal URL. Most explicit WYSIWYG homepage fields are covered, but production field values cannot be proven from source. | Inventory live field values; if defects exist, apply the existing render-time helper only at the uncovered renderer. | No. |
| REVIEW-003 | User-authored menu/custom-link records | Dynamic menu custom links are database values and absent from this repository. Router filters appear authoritative for generated items, but custom absolute links require production-data validation. | Audit translated menus in production; do not hard-code or mutate menu records in this PR. | No. |

## Production-data verification procedures

The repository includes `wp-content/plugins/pera-multilingual/tools/multilingual-production-data-audit.php`, a read-only WP-CLI report covering REVIEW-001 through REVIEW-003. It calls only WordPress read APIs (`WP_Query`, raw ACF reads, and menu reads), performs no update/delete operation, generates no translations, and requires no cache flush. From the production WordPress root, run:

```bash
cd /home/peraukco/public_html
wp eval-file wp-content/plugins/pera-multilingual/tools/multilingual-production-data-audit.php | tee /tmp/pera-ml-production-url-audit.tsv
```

`tee` writes only the report file under `/tmp`; it does not modify WordPress or its database. Remove `| tee ...` if no report file is desired. Review findings rather than feeding this output into an update command.

### REVIEW-001 — published `post_content` anchors

The script queries all published public post types, extracts anchor `href` values directly from stored `post_content`, and reports same-site absolute (`peraproperty.com`/`www.peraproperty.com`) or root-relative visitor URLs. It excludes fragments, `mailto:`, `tel:`, external domains, protocol-relative external URLs, `wp-admin`, `wp-json`, uploads, and common media/document extensions. It does **not** render shortcodes or change content.

Expected tab-separated output shape:

```text
SECTION  POST_ID  POST_TYPE  TITLE_OR_FIELD  URL
CONTENT  12345    page       About us       /contact-us/
CONTENT  23456    post       Buyer guide    https://www.peraproperty.com/property/
```

A clean result has no `CONTENT` rows. Any `CONTENT` row means the stored link needs manual context review: confirm whether the actual frontend renderer localizes it. If not, remediation is needed at render time using the existing router/helper; this audit command must not be changed into a database rewrite.

### REVIEW-002 — ACF URL/link/repeater values

When ACF is active, the same command calls `get_field_objects( $post_id, false, true )` (`format_value = false`, `load_value = true`) for published public objects and recursively inspects raw values. Array paths retain repeater row indexes and link-array keys, so URL fields, Link fields, groups, flexible content, and nested repeater values can be traced.

Expected output shape:

```text
ACF  34567  page  hero_ctas[0].link.url  /book-a-consultancy/
ACF  45678  property  sidebar_cta.url    https://www.peraproperty.com/contact-us/
```

A clean result has no `ACF` rows and no `NOTICE` row. An `ACF` row means manual renderer inspection is required; it is not automatically a defect because that renderer may already call `pera_localize_visitor_links()` or `pera_ml_url()`. `NOTICE ACF is not active` means the check was incomplete and must be rerun in the production context where ACF is loaded. Limitation: generic ACF discovery can only see fields registered in the active production field configuration and attached to published public posts; orphaned meta, options-page fields, draft content, and unregistered historical field keys are intentionally not inferred from arbitrary `postmeta`. If production uses visitor-facing ACF options, audit those known option field names separately with a reviewed read-only script rather than scanning and guessing at all metadata.

### REVIEW-003 — custom nav-menu links

The menu portion reads every menu and reports only items whose WordPress menu-item type is exactly `custom` and whose stored URL is same-site absolute or root-relative. Object-backed post, taxonomy, archive, and other generated menu items are not reported.

Expected output shape:

```text
SECTION  MENU          ITEM_ID  LABEL         URL
MENU     Primary menu  9876     Properties    /property/
MENU     Footer        9877     Contact us    https://www.peraproperty.com/contact-us/
```

A clean result has no `MENU` rows. A `MENU` row means that custom link needs manual language-context review and remediation is needed if it bypasses the router on translated pages. Prefer existing render-time menu/router handling; do not edit menu records as part of this audit.

## ACF/editor HTML conclusion

The audited homepage WYSIWYG fields already call `pera_localize_visitor_links()` at output, and that helper delegates URL classification to `pera_ml_url()`. It preserves external/technical destinations according to the router and avoids storage mutation. No second pass was added. Generic database-authored content is documented above because repository-only evidence cannot establish its live values.

## Regression coverage

`tests/multilingual-url-routing-audit-test.php` protects all 16 maintained render files from reintroducing a direct `home_url()`/`site_url()` visitor destination. It also requires at least 43 routed constructions to remain present. The router suite provides behavior coverage for `/de/`, `/ar/`, `/zh/`, query strings, fragments, already-prefixed URLs, external and technical URLs, and prefix idempotency.

## PR accounting

- Files recursively inventoried: **771**.
- Source/render files scanned: **591**.
- Candidate URL occurrences reviewed: **847** (from 844 unique candidate source lines).
- Confirmed unsafe occurrences: **47** total — **43 fixed in maintained code**, **4 legacy/pending deletion and intentionally not fixed**.
- Safe occurrences: **688**.
- Intentional exclusions: **109**.
- Uncertain/manual-review items: **3**.
- Production routing files changed: **16** maintained theme files; the legacy Chinese template is unchanged from its pre-audit behavior.
- Tests added: **1** focused static regression test.
- Remaining status: **no confirmed instance remains in maintained repository visitor-facing code**. Four confirmed unsafe links remain only in the legacy template pending deletion; the three production-data questions above still require live content review.
