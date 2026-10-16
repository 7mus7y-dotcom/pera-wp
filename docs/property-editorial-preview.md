# Property editorial grid preview

The existing administrator gate controls the new editorial grid: a logged-in user with `manage_options`, on a single property, with `?layout_preview=1`. Anonymous and non-admin preview requests, and ordinary property URLs, retain the previous editorial layout. The MU plugin and its cache protections are unchanged; see [property layout preview](property-layout-preview.md).

The preview uses the existing `grid-2--tight` gaps and mobile stack. The intro card no longer spans both desktop columns, producing this order:

1. Why this property?
2. Location and district analysis
3. Investment and rental potential
4. Who this property is suitable for

Optional panels are omitted by the existing content conditions and remaining cards reflow in source order. Developer credibility follows when present. Property Questions / FAQ always spans the grid below the other cards. Card heights remain content driven. ACF reads, sanitization, translations, headings, pills and checklists are unchanged.

The desktop preview map uses `32 / 25`, making its height at a given width 25% greater than the effective `16 / 10` ratio in `property.css`. The existing preview spacing, 40/60 location columns, Google Maps markup and mobile map dimensions are preserved.

At mobile widths up to 768px, all Nearby Amenities rows use a flexible name column and a non-wrapping distance column. Long names can wrap; RTL uses the inherited direction with bidi isolation from the existing renderer. This improvement applies to ordinary mobile pages as well as previews. Desktop amenity styles are unchanged.

## Regression checks

From the repository root:

```sh
php wp-content/themes/hello-elementor-child/tests/property-editorial-preview-test.php
php wp-content/themes/hello-elementor-child/tests/property-layout-preview-test.php
php wp-content/themes/hello-elementor-child/tests/nearby-amenities-test.php
node wp-content/themes/hello-elementor-child/tests/property-layout-responsive-test.js
```

The browser check requires Playwright and Chromium. Set `PHP_BIN` and `CHROMIUM_PATH` if needed (defaults: `php`, `/usr/bin/chromium`). It renders the actual editorial template section with both production stylesheets, checks every nonempty optional-content combination on desktop, and tests normal/preview mobile layouts at 320px, 375px and 430px in LTR and RTL. Amenity row fixtures mirror the unchanged renderer and cover every category, long names, M/T/F identifiers, links and distances. Map checks use local iframe fixtures; they do not contact Google Maps.

The PHP check compares normal editorial output byte for byte with the pre-change section fixture from main at `5260554eba11b198a5d3d2ddac7ba3213009b42a` across all 128 content combinations and exercises the existing admin gate. The location regression test continues to cover its 32 content combinations and normal baseline. These are local render checks, not a production WordPress or upstream-cache certification.
