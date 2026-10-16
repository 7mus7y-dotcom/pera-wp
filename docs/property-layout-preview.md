# Temporary property layout preview

The URL is `https://www.peraproperty.com/property/large-furnished-studio-for-sale-at-extensa-bomonti/?layout_preview=1`.
Only a logged-in user with `manage_options` viewing a single property with `?layout_preview=1` can receive the new markup. Normal URLs, anonymous requests (including preview URLs), and non-admin preview requests retain the original layout. The lower editorial section is unchanged.

## Cache protections

Production has no WordPress caching plugin or `advanced-cache.php` drop-in. No environment configuration constant is required to enable the authenticated preview.

The MU plugin sets `DONOTCACHEPAGE` before normal plugins/themes and sends no-cache response headers on `init` for all requests containing `layout_preview`, including anonymous and malformed ones: `Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0`, `CDN-Cache-Control: no-store`, and `Surrogate-Control: no-store`.

**Upstream caches must never expose authenticated preview responses to anonymous or non-admin visitors.** CDN, reverse-proxy and host caches must respect these headers and bypass lookup and storage for authenticated WordPress cookies and requests containing `layout_preview`. MU plugins cannot prevent a cache lookup that happens before WordPress executes. Verify isolation on the actual infrastructure; local gate tests cannot certify upstream caches.

## Layout and acceptance

The preview reuses the existing description, facilities, map and POI renderer. Description and facilities form the full-width first block. Map and amenities follow in a single column at every viewport, with the map above amenities. Amenity groups retain their compact padding and gaps; the heading icon scales to match the heading. Single available cards span the row; missing description/facilities produce no empty text block; missing coordinates suppress the map regardless of saved distances. The preview omits the Location & distances section; normal rendering and saved ACF data are unchanged. Amenities output is buffered once to detect an empty renderer without duplicating POI work or tab IDs. All new CSS is scoped to preview markup, uses the existing grid spacing, and stacks in source order on mobile, including RTL.

When reviewing the preview, check a real property in desktop/mobile and RTL with working amenity tabs, iframe, external map link and facility pills. Check all missing-content combinations and anonymous and non-admin preview URLs, admin normal URLs, admin preview URLs, and admin preview URLs outside a single property. Verify the cache protections above against the actual target infrastructure.

## Regression checks

Run `php wp-content/themes/hello-elementor-child/tests/property-layout-preview-test.php`. It exercises 32 combinations of map, distances, amenities, description and facilities, plus incomplete coordinates; compares normal output against the pre-refinement location section fixture; and checks scoped stacked desktop/mobile CSS and heading icon sizing. The fixture is test-only markup from commit `005eecdaf6c5a1621a436072c9cc1b2f01e770b3`, with no saved property data.
