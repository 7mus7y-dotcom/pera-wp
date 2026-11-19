# Theme

Scope: `wp-content/themes/hello-elementor-child/`, the Hello Elementor child theme that owns the public site shell and most public templates.

## Bootstrap and templates

`functions.php` loads `inc/bootstrap.php`, helpers, and `inc/theme-modules.php`. `inc/bootstrap-modules.php` gates request-specific code; `inc/bootstrap/always.php` provides shared access, taxonomy, SEO, favourites, card, enquiry, and integration helpers. Modules under `inc/modules/` own setup, SEO loading, routing, assets, performance, sitemaps, and related hooks.

Key public boundaries are:

- `header.php` and `footer.php`: shared public shell.
- `home-page.php`, `home.php`, `archive.php`, `single-post.php`, and `page-*.php`: public page/archive templates.
- `archive-property.php`: server-rendered property archive controls and results.
- `single-property.php` (plus the specialized `single-bodrum-property.php`): property detail rendering.
- `parts/` and `partials/`: reusable view fragments; `parts/property-card-v2.php` is the canonical property card.

CRM uses its plugin-owned shell and assets; do not assume the public theme header or global bundle is present there. Portal ownership is described in [portal.md](portal.md).

## CSS and JavaScript

`inc/modules/enqueue-assets.php` is the source of truth for route-based loading. It versions local files through `pera_get_asset_version()` and keeps the global `css/main.css` and `js/main.js` off CRM and standalone-auth surfaces. Page/component CSS lives in `css/` (`property.css`, `property-card.css`, `blog.css`, `slider.css`, etc.); behaviour lives in `js/`. Preserve declared handles and dependencies when changing loading.

Use variables, utilities, primitives, and component classes already defined in `css/main.css` and focused component files. Add a rule to the owning file and scope it to the component; avoid one-off inline/global overrides.

## Header and off-canvas

`header.php` owns the public logo, language and currency controls, staff/CRM links, menu toggle, and off-canvas markup. `css/main.css` and `js/main.js` own its presentation/interaction. The off-canvas state depends on the existing `#nav-toggle` checkbox, matching labels, IDs, classes, and menu locations—preserve these hooks.

The header contains login-, capability-, favourite-, reminder-, language-, and currency-sensitive output. Cached HTML therefore needs special scrutiny: monetary content is hydrated client-side, but authenticated header state must never be assumed safe in a shared page cache. Verify logged-out and relevant logged-in states after header changes.

## Property frontend boundary

The theme reads Property CPT/ACF data and taxonomies; no Property CPT or property-taxonomy registration exists in this repository. `single-property.php` owns the standard detail page and gallery markup. `archive-property.php`, `inc/property-archive-query.php`, `inc/ajax-property-archive.php`, and `parts/property-card-v2.php` jointly own archive SSR, AJAX refreshes, and cards. See [property-cpt.md](property-cpt.md) before changing any of these and [currency.md](currency.md) for monetary nodes.

## Focused checks

Tests are executable PHP/Node scripts in `wp-content/themes/hello-elementor-child/tests/`. Run only relevant files, for example:

```sh
php wp-content/themes/hello-elementor-child/tests/property-card-resale-pricing-test.php
php wp-content/themes/hello-elementor-child/tests/single-property-hero-price-test.php
node wp-content/themes/hello-elementor-child/tests/dynamic-price-rehydration-test.js
php wp-content/themes/hello-elementor-child/tests/visitor-link-routing-test.php
```

Also run `php -l` on changed PHP. For perceptible work, smoke-test the affected desktop/mobile page, navigation focus/close behaviour, and both light/dark system modes where applicable.
