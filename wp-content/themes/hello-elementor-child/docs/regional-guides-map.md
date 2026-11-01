# Regional Guides Istanbul map

## Architecture and audit

`archive.php` calls `pera_render_regional_guides_map()` immediately after the introduction and before featured cards. The renderer and enqueue callback both require `is_category( 'regional-guides' )`. Other archives return without querying or rendering. `inc/theme-modules.php` loads the dedicated `inc/modules/regional-guides-map.php` module.

The existing archive owns cards, search, date sorting, pagination, FAQ/schema, category content and CTA. AJAX replaces only `[data-blog-results]`; the map is outside that element. No main query filters, SEO hooks, canonicals, breadcrumbs, search handlers or existing cards were changed.

The module makes one unpaginated published-post query, restricted to Regional Guides (including descendants), enabled metadata and no password. WordPress primes post metadata; invalid or missing coordinate pairs are excluded in PHP. No per-marker taxonomy queries, persistent language-sensitive data cache, REST routes, geocoding API or new framework is introduced. WordPress query/object caching applies normally; page caches must already vary by language as required by Pera ML.

Pera ML stores translations separately from canonical posts. Coordinates and the optional location label belong to the canonical article, not a category or translated duplicate. `get_the_title( $id )` uses the existing Pera ML title filter; `pera_ml_url( get_permalink( $id ) )` uses the current router language, including its enabled-language rules. Public strings use literal `pera_ml_ui()` calls with `theme.regional_map.*` keys, discoverable through the existing theme UI discovery workflow. Run that workflow and translate the new strings for enabled languages; absent translations use the existing English fallback. The optional map label is a shared proper place name used for marker accessibility; the popup always displays the translated article title.

## Assign an article location

1. Open a standard post and assign it to Regional Guides.
2. In **Map location — Regional Guides**, enter latitude and longitude as decimal degrees, or expand **Choose location on map** and click a location. The picker is optional and initializes only when expanded. It does not enable the article automatically.
3. Optionally provide a short place label. Check **Show on Regional Guides map**.
4. Publish or update the article. Verify the marker and popup on the archive in each enabled language.

Latitude must be between -90 and 90; longitude between -180 and 180. Zero is valid. Invalid values are removed on save and disable display. Unchecking disables display while preserving valid coordinates. Existing articles remain unchanged until the metabox is submitted. Quick edit, autosaves, revisions and requests without the nonce do not overwrite data. Save checks include post edit capability and nonce validation. Metadata keys are `_pera_guide_map_lat`, `_pera_guide_map_lng`, `_pera_guide_map_label`, and `_pera_guide_map_enabled`.

The metabox appears on all standard posts so editors can assign a category during the same edit. Only published Regional Guides articles appear publicly; drafts, password-protected articles and other categories do not.

## Landmarks

Maintain the three-item WGS84 configuration in `pera_regional_map_landmarks()` in the module. Coordinates identify the buildings, not entire districts:

| Landmark | Latitude | Longitude | Reference |
| --- | --- | --- | --- |
| Galata Tower | 41.02566 | 28.97416 | [OpenStreetMap location](https://www.openstreetmap.org/?mlat=41.02566&mlon=28.97416#map=19/41.02566/28.97416), [Wikipedia](https://en.wikipedia.org/wiki/Galata_Tower) |
| Topkapı Palace | 41.01152 | 28.98338 | [OpenStreetMap location](https://www.openstreetmap.org/?mlat=41.01152&mlon=28.98338#map=19/41.01152/28.98338), [Wikipedia](https://en.wikipedia.org/wiki/Topkap%C4%B1_Palace) |
| Sultanahmet Mosque | 41.00546 | 28.97682 | [OpenStreetMap location](https://www.openstreetmap.org/?mlat=41.00546&mlon=28.97682#map=19/41.00546/28.97682), [Wikipedia](https://en.wikipedia.org/wiki/Blue_Mosque,_Istanbul) |

Add/remove entries there, with a unique Pera ML UI key for any new name. Landmarks use small muted markers, translated tooltips and text-only popups. They never manufacture guide links. A guide assigned to a landmark location receives its own prominent blue guide marker.

## Assets, performance and accessibility

`vendor/leaflet/` contains the official pinned Leaflet 1.9.4 JS/CSS, marker/control images and BSD license. The prior files were CDN/document.write proxies; the same `pera-leaflet` handles now serve the complete distribution locally, including on the existing citizenship property map. Sources: Leaflet v1.9.4 `dist/leaflet.css`, `dist/images/`, `LICENSE`, and cdnjs's verbatim 1.9.4 `leaflet.js`. No package installation or build step is required. Leaflet's optional JS sourcemap is not shipped.

`js/regional-guides-map.js` handles archive and lazy editor picker. Leaflet loads only on the Regional Guides archive, standard post editing screens, and the pre-existing citizenship properties map. The controller follows the same scopes; file versions use the existing `pera_get_asset_version()` helper. `assets/css/admin-regional-map.css` is limited to standard post editing. Frontend styles live in `css/main.css`, reuse existing typography, radius and colour tokens, and reserve 480px desktop / 350px mobile height. Popups follow light/dark surface tokens; tiles retain their original colours and attribution.

Only OpenStreetMap standard raster tiles are requested from `https://tile.openstreetmap.org/{z}/{x}/{y}.png`, on demand in the browser. Visible attribution links to OSM copyright. Do not prefetch, bulk-download, proxy/cache with shortened lifetimes, suppress browser caching/referrer, or use offline tile downloads. Browser HTTP caching honors server headers. Review the [OSM tile usage policy](https://operations.osmfoundation.org/policies/tiles/) before launch and when traffic grows; standard tiles have no service guarantee. If externally configured CSP is present, allow that host in `img-src` and permit Leaflet's required style behavior. No sitewide frontend CSP was found in the repository. Hosting headers and tile availability require live verification.

Wheel zoom is always disabled. Coarse-pointer devices initially allow ordinary page scrolling; the **Enable map interaction** toggle (with `aria-pressed`) enables/disables touch dragging and pinch zoom. Standard zoom buttons and keyboard controls remain available. Markers are keyboard-focusable and open popups before navigation. Guide titles/links are DOM nodes using `textContent` and constrained HTTP(S) URLs. PHP escapes JSON attributes, links and displayed text. Guides within 32 screen pixels share one popup with every article link; grouping is recalculated on zoom and identical coordinates remain accessible together. This avoids another clustering dependency or false coordinate offsets.

A crawlable **Browse mapped guides** HTML list remains available independently of JavaScript. Reserved map space and fallback text persist if scripts fail. Tile errors reveal the fallback while marker popups and the HTML list remain usable. Existing archive article cards remain the primary discovery fallback.

## Verification and deployment

No deployment or database migration is part of this PR. Deploy through the normal reviewed theme workflow after merge, invalidate relevant theme/page caches, discover/translate new UI strings, and assign coordinates to selected articles. Do not enable a large set of articles without checking coordinate accuracy.

Automated checks:

```sh
php -l inc/modules/regional-guides-map.php
php tests/regional-guides-map-test.php
node --check js/regional-guides-map.js
node tests/regional-guides-map-test.js
python3 tests/regional-guides-map-browser-test.py
```

Run commands from the theme directory. The browser test requires Chromium and uses real Leaflet with explicitly synthetic offline tiles, never downloading OSM tiles. `.github/workflows/regional-guides-map-tests.yml` runs PHP lint, focused PHP/JS behavior tests, and existing archive/multilingual tests. The browser test runs in CI and can also be run locally in environments supporting Chromium or Google Chrome.

Local validation: JS syntax, controller behavior (overlap popups, keyboard options, localized links, mobile toggle, tile-error fallback), existing archive language-payload tests and whitespace checks ran. This environment has no PHP executable; PHP lint, focused PHP tests and the existing archive/multilingual suites passed in GitHub Actions. Chromium is installed but container socket restrictions prevent starting it, so no rendered screenshots or browser-test pass are claimed.

Live WordPress checks still required: metabox save in Classic and Block editors, optional picker/manual coordinate changes, category assignment on the same save, published/draft/unchecked/password-protected cases, archive page 2 with all markers, AJAX search/sort/pagination, translated archives and popup destinations, RTL, tile host/CSP/referrer policy, desktop and mobile scroll/zoom, keyboard popup traversal, light/dark styling and script/tile failure. Also smoke-test existing citizenship property-map markers after replacing the CDN proxies. The initial Istanbul bounds cover both European and Asian sides; distant article coordinates remain available by panning or the complete HTML link list.
