# Permanent property-page layout

`single-property.php` renders the approved layout for all visitors. There is no
role or query-string rendering gate. The reusable MU plugin protects requests
containing `layout_preview` from caching but activates no experimental layout;
see [property layout preview infrastructure](property-layout-preview.md).

## Rendering

The property description and facilities span the section. Nearby Amenities uses
one `card-shell`, one translated H2 with the existing decorative map icon, and
one call to the existing POI renderer with `property-location-heading` as its
shared accessible heading. A coordinate-backed map and nonempty POI output form
the desktop 40/60 grid. A single available component occupies the full width;
missing content produces no empty map or panel. The separate Map heading and ACF
distances output are removed. ACF definitions and stored values are untouched.

Editorial cards use the existing tight grid, two columns on desktop and one on
mobile. Optional cards retain their data conditions and natural heights. Only
the Property Questions FAQ spans both desktop columns.

## Styles and sticky summary

All component styles remain in `css/main.css` and `css/property.css`. Permanent
`property-location__*`, `property-editorial-grid` and
`property-sticky-summary` names replace the experimental selectors. The old
location wrapper, map placeholder/distance markup, introductory editorial wide
variant, preview badge, baseline fixtures and gate tests are removed. The unused
`property-location__distances` selector is removed after a repository-wide usage
audit. Generic map-page `.property-map-card` styles remain because other templates
use them. Existing card, button, spacing, typography and colour tokens are reused.
Desktop maps retain 32/25 geometry and the 16px action gap; mobile maps retain
16/10 geometry and POI distances remain on the first line beside wrapping names.

`js/property-sticky-summary.js` retains the approved observers and interactions.
The 300px desktop card aligns to the container's right edge, 10px below the
measured header. An observer sentinel shows the summary after the visitor scrolls
past one measured header height, while the hero is still available. Mobile reserves bottom space from initial render and uses a
fixed bar with safe-area padding. The original global WhatsApp node moves into
its action slot only while the mobile bar is active and returns to its original
position when hidden or on desktop. No clone, URL or tracking attributes change.
Visible enquiry forms, navigation, cookie overlays and dialogs suppress the
summary. Hidden/inert state and enquiry focus are preserved.
Prices reuse the hero's authoritative v2 display-price data and currency runtime.

## Validation

Run from the repository root (PHP with mbstring; Playwright and Chromium):

```sh
php wp-content/themes/hello-elementor-child/tests/property-layout-test.php
php wp-content/themes/hello-elementor-child/tests/property-editorial-test.php
php wp-content/themes/hello-elementor-child/tests/property-sticky-summary-test.php
node wp-content/themes/hello-elementor-child/tests/property-layout-responsive-test.js
node wp-content/themes/hello-elementor-child/tests/property-sticky-summary-browser-test.js
```

`PHP_BIN` and `CHROMIUM_PATH` select local executables. PHP regressions cover 32
location content combinations, missing coordinates, 128 optional editorial
combinations, visitor/query independence, authoritative pricing and missing facts.
Chromium uses actual PHP template sections, the header shell, production CSS and
currency runtime. It covers 320/375/430/1024/1280/1440px, LTR/RTL, light/dark,
map geometry, POI wrapping, grids, overflow, summary placement, scroll/focus,
overlays, header resize, currency switching, WhatsApp identity and restoration.

These are local fixtures with WordPress helpers stubbed, not a full WordPress
boot or a production traffic test. Full staging pages, locale-specific controls,
real consent/CRM/GA4 delivery and iOS safe-area/keyboard behavior still merit
release verification. The approved fixed desktop card can cover scrolling
content. No deployment or merge is part of this change.
