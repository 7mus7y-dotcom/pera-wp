# Pera Property internal-link audit

This audit covers the active `hello-elementor-child` PHP templates. It intentionally does not recommend global HTML rewriting.

## Already localized by router filters

The router filters `post_link`, `page_link`, `post_type_link`, `post_type_archive_link`, `term_link`, `author_link`, and `get_pagenum_link`. Consequently, links generated through `get_permalink()` / `the_permalink()`, `get_term_link()`, and `get_post_type_archive_link()` localize without template edits. These APIs are used throughout property cards, property detail breadcrumbs/taxonomy pills, related posts, archive cards, favourites, the property map, and schema helpers.

Representative locations:

- `parts/property-card-v2.php:38,371,377`
- `single-property.php:52,70,77,85,95,1037`
- `archive-property.php:330,1087`
- `page-property-map.php:140,176,196`
- `single-post.php:26,295,302`
- `parts/post-card.php:19` and `parts/related-taxonomy-card.php:15`

The audit found 34 theme PHP files using permalink APIs, 13 using term links, and 11 using post-type archive links. AJAX-rendered property cards also use `get_permalink()` and therefore receive localized result links when the AJAX request itself carries language context. At present the AJAX endpoint is deliberately never language-prefixed, so callers must pass an explicit language value in a future integration if localized AJAX fragments are required.

## Not currently localized

The theme contains 185 direct `home_url()` calls across 49 PHP files. `home_url()` has no suitable narrow WordPress filter in this plugin, so these remain English unless each call is changed to a future `pera_ml_url()` helper or an equivalent targeted integration is added. Important clusters include:

- header fallbacks and account/favourites links in `header.php:72,92,160-164`;
- homepage CTA, district, guide, category, and service links throughout `home-page.php`;
- property archive CTA and editorial links in `archive-property.php:16,1086-1115,1206-1232`;
- property detail service/guide links in `single-property.php:87,1019,1688-1692`;
- blog service links in `single-post.php:363,377,452`;
- contact, privacy, consultation, citizenship, and account links across custom page templates and partials.

There are also 54 hard-coded root-relative `href`/`action` attributes. Examples include `page-posts.php:38-39` and service links in `parts/our-services-card.php:27,80,96,113,141,157`. These bypass every WordPress URL filter and need explicit template-level conversion later.

## Navigation, filters, and AJAX

- Dynamic post, term, property archive, pagination, and standard WordPress menu item URLs are covered by existing router filters. Custom menu values that are literal URLs are not covered.
- Property archive filter state is query-string based and survives prefix strip/restore. The archive base uses `get_post_type_archive_link()` where available, but its `home_url('/property/')` fallback remains English.
- `admin_url('admin-ajax.php')` calls in the property archive, citizenship page, asset localization, analytics, favourites, and other modules must remain unprefixed. The hardened router explicitly excludes admin/AJAX routes.
- AJAX response links created with `get_permalink()` are filter-compatible, but because admin-AJAX is excluded from language detection they need an explicit, validated language parameter before they can localize reliably.
- Forms targeting `admin-post.php`, authentication/logout URLs, CRM/portal routes, and account redirects are system or application actions and should not be automatically prefixed without separate workflow review.

## Recommended later integration

Add a small public `pera_ml_url( $url, $language = null )` wrapper around the router and replace only audited visitor-facing `home_url()` and hard-coded root-relative links. Keep system endpoints and form handlers canonical. Pass a nonce-protected language code to the property AJAX actions when translated fragments are introduced. Do not rewrite complete HTML output.
