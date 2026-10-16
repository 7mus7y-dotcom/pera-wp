# Temporary property layout preview

The URL is `https://www.peraproperty.com/property/large-furnished-studio-for-sale-at-extensa-bomonti/?layout_preview=1`.
Only a logged-in user with `manage_options` viewing a single property with `?layout_preview=1` can receive the new markup. Normal URLs, anonymous requests (including preview URLs), and non-admin preview requests retain the original layout. The lower editorial section is unchanged.

## Cache protections

Production has no WordPress caching plugin or `advanced-cache.php` drop-in. No environment configuration constant is required to enable the authenticated preview.

The MU plugin sets `DONOTCACHEPAGE` before normal plugins/themes and sends no-cache response headers on `init` for all requests containing `layout_preview`, including anonymous and malformed ones: `Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0`, `CDN-Cache-Control: no-store`, and `Surrogate-Control: no-store`.

**Upstream caches must never expose authenticated preview responses to anonymous or non-admin visitors.** CDN, reverse-proxy and host caches must respect these headers and bypass lookup and storage for authenticated WordPress cookies and requests containing `layout_preview`. MU plugins cannot prevent a cache lookup that happens before WordPress executes. Verify isolation on the actual infrastructure; local gate tests cannot certify upstream caches.

## Layout and acceptance

The preview reuses the existing description, facilities, map and POI renderer. Description and facilities form the full-width first block. Map and amenities follow in a 40/60 grid above the existing 768px mobile breakpoint. Single available cards span the row; missing description/facilities produce no empty text block; missing coordinates suppress the map unless distances remain useful. Amenities output is buffered once to detect an empty renderer without duplicating POI work or tab IDs. All new CSS is scoped to preview markup, uses the existing grid spacing, and stacks in source order on mobile, including RTL.

When reviewing the preview, check a real property in desktop/mobile and RTL with working amenity tabs, iframe, external map link and facility pills. Check all missing-content combinations and anonymous and non-admin preview URLs, admin normal URLs, admin preview URLs, and admin preview URLs outside a single property. Verify the cache protections above against the actual target infrastructure.
