# Temporary property layout preview

The URL is `https://www.peraproperty.com/property/large-furnished-studio-for-sale-at-extensa-bomonti/?layout_preview=1`.
Only a logged-in user with `manage_options` can receive the new markup. Normal URLs, anonymous requests (including preview URLs), and non-admin preview requests retain the original layout. The lower editorial section is unchanged.

## Cache prerequisite — disabled by default

Production cache/CDN configuration is not tracked here and cannot be verified from this checkout. **Do not enable on production until every cache layer has been verified.** The server-side gate also requires `define( 'PERA_PROPERTY_LAYOUT_PREVIEW_CACHE_BYPASS_VERIFIED', true );` in environment configuration. Absent that assertion, even administrators see the original layout.

Before setting it:

1. Configure the CDN, reverse proxy, host page cache and WordPress page-cache drop-in to bypass both cache lookup and storage whenever the query contains `layout_preview` (including query reordering/encoding). Also bypass authenticated WordPress cookies. Do not merely add the parameter to a shared cache key: all administrators would then share a key with anonymous visitors.
2. Purge any pre-existing preview variants. Confirm that neither a warmed ordinary URL nor a warmed preview URL can serve cached markup to an administrator preview request.
3. Verify preview responses have `Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0`, `CDN-Cache-Control: no-store`, `Surrogate-Control: no-store`, and cache-layer BYPASS/MISS diagnostics with no subsequent HIT. Recheck as anonymous and non-admin users immediately afterward, with and without the parameter and in both request orders. Experimental markup must never appear for these users.

The MU plugin sets `DONOTCACHEPAGE` before normal plugins/themes and sends no-store headers on `init` for all query-bearing requests, including anonymous and malformed ones. This prevents compliant caches storing origin responses, but cannot prevent an upstream cache lookup or `advanced-cache.php` short circuit before MU plugins execute. Thus code alone does not establish cache isolation. If any layer cannot reliably bypass lookup and storage, leave the assertion unset and review on an access-controlled, fully uncached staging host instead. Set the assertion only on that verified host.

## Layout and acceptance

The preview reuses the existing description, facilities, map and POI renderer. Description and facilities form the full-width first block. Map and amenities follow in a 40/60 grid above the existing 768px mobile breakpoint. Single available cards span the row; missing description/facilities produce no empty text block; missing coordinates suppress the map unless distances remain useful. Amenities output is buffered once to detect an empty renderer without duplicating POI work or tab IDs. All new CSS is scoped to preview markup, uses the existing grid spacing, and stacks in source order on mobile, including RTL.

Before enabling, check a real property in desktop/mobile and RTL with working amenity tabs, iframe, external map link and facility pills. Check all missing-content combinations and the five visitor/URL states. Cache-layer acceptance above must be run against the actual target infrastructure; local rendering tests cannot certify a CDN.
