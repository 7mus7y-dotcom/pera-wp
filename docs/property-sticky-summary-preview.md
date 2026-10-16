# Property sticky summary preview

The existing `pera_property_layout_preview_enabled()` gate emits the summary and
queues its footer script only for logged-in administrators with `manage_options`
on a singular property URL with `?layout_preview=1`. The MU plugin, cache bypasses
and normal property layout are unchanged. All new styles target preview markup.
The approved location panel, editorial grid, amenity rows and map sizing remain intact.

The summary renders the hero's `$hero_display_price` through
`pera_property_display_price_html()`. That object comes from
`pera_units_get_display_data()` and the existing v2 unit price bounds, using the
hero's single/range/from mode. Project “From” copy remains outside the monetary
node. The existing Pera Currency runtime updates its canonical `data-pera-money`
node along with the hero. No legacy fields, extra ACF lookups or calculations are
introduced. Size and bedroom text reuse `$selected_size_text` and
`$hero_bed_label_v2`; missing facts are omitted and missing prices use the hero's
translated contact-for-pricing fallback.

On desktop the fixed 300px summary card sits 20px below the measured sticky
header, at a lower stacking level, aligned to the content container’s right edge
with a 16px minimum viewport gutter. It contains price, gross size and bedrooms
(or the already-loaded translated property type when bedrooms are unavailable),
and a full-width Enquire button. The sticky title has been removed. ResizeObserver accounts for header changes. At the existing
768px breakpoint it becomes a bottom bar with 16px horizontal padding, centered
price/facts and equal-height actions, plus safe-area padding. The property-type
fallback is desktop-only, preserving the mobile facts. Preview pages
reserve bottom space from initial render, including while the bar is hidden.
IntersectionObserver watches the hero, overview/quick facts and existing enquiry
form. The bar appears only after both introductory sections have passed above
the header and hides whenever the actual enquiry form intersects the usable
viewport. Passing the form restores it. Header menus, navigation, lightbox,
offer video modal, native dialogs and the visible cookie notice suppress it.
There are no scroll listeners or external runtime dependencies. Without either
observer API the feature stays hidden and the global WhatsApp link stays intact.

The existing `inc/whatsapp.php` component renders `#floating-whatsapp` using
`pera_get_whatsapp_context()`. On visible mobile summaries, the script moves that
**original node** into the action slot. It restores the node beside a comment at
its original location on hide or desktop resize. This preserves the contextual
URL, prefilled message, translated accessible label, all tracking attributes and
attached click handlers; there is no clone, second URL builder or second ID.
Desktop and normal pages retain the original floating presentation.

Enquire retains `#contact-form` and the existing form. It focuses a real form
control (excluding hidden and honeypot inputs), accounts for measured header
height, and uses instant scrolling for reduced motion. Hidden summaries use
`hidden` and `inert`, with a focus fallback if scrolling removes a focused bar.
Logical positioning supports RTL and surface/text colors use existing tokens.

## Regression checks

From `wp-content/themes/hello-elementor-child`:

```sh
php tests/property-sticky-summary-test.php
node tests/property-sticky-summary-browser-test.js
php tests/property-layout-preview-test.php
php tests/property-editorial-preview-test.php
php tests/single-property-hero-price-test.php
php tests/property-display-price-test.php
php tests/whatsapp-context-test.php
node tests/property-layout-responsive-test.js
```

Browser tests require Playwright and Chromium. `PHP_BIN` and `CHROMIUM_PATH` can
select local executables; PHP's mbstring extension is required by the existing
WhatsApp test. New Chromium coverage uses actual PHP-rendered summary markup,
both production stylesheets, the actual header template shell (logo/selector
helpers stubbed), the production currency runtime, and the global
WhatsApp opening tag from its real template. It verifies 320/375/430/1024/1280/1440px,
LTR/RTL, dark mode, reduced motion, menu/cookie/dialog suppression, scrolling
past the form and back, form focus, header resize, viewport transitions, currency
switching, missing-price copy, long translated action text, unique WhatsApp ID,
original-node identity, preserved attributes and an attached click listener.
PHP tests cover all 32 auth/query/property gate combinations and project/resale
formatting, missing facts and initial hidden/inert state.

Remaining staging checks: a full WordPress page with real resale and multi-unit
project records and every supported locale; live selector menus and consent
states; actual CRM/GA tracking delivery; iOS safe-area and virtual-keyboard
behavior; production cache/CDN preview exclusion. The local harness does not
boot WordPress or contact tracking services. No deployment or activation for
normal visitors is part of this change.

## CSS refinement after PR #1435

Before: a full-width 66px desktop band, duplicated surface/border/shadow and
focus declarations, truncated title, and horizontal price/facts/actions.
After: a compact 300px card with stacked price, facts and full-width Enquire,
20px below the measured header. No page-wide band or separator remains. The
original floating WhatsApp button stays separate on desktop.

Reused design-system classes: `card-shell` (light/dark surfaces, neutral border,
radius, shadow and flex shell), `text-base`, `text-bold`, `text-xs`, `text-soft`,
and the existing `btn btn--solid btn--blue` conventions, including focus states.
Spacing, size and color rules use existing `--space-*`, `--fs-ui-*`, `--container`,
`--brand-green` and `--radius` tokens. There are no new color values or stylesheet.

Custom rules remain for fixed placement/header offset, 300px desktop sizing and
16px padding, stacked/full-width actions, the 768px mobile layout and compact
text/control sizing, `hidden`, reserved bottom space and safe-area padding, and
the original floating link’s in-bar geometry/tooltip. The mobile shell resets
card borders/radius for an edge-to-edge bottom bar while retaining its shared
surface and shadow. Pricing, observers, navigation/focus and WhatsApp JavaScript
are unchanged.

Chromium covers compact card dimensions, container alignment and header clearance
at 1024/1280/1440px in both directions, plus header dropdown suppression, separate
desktop WhatsApp, full-width Enquire, mobile padding/action heights, type fallback,
and no preview markup or extra bottom spacing for normal pages. As with any fixed
card, it can visually cover part of the content while scrolling; full-page staging
review remains necessary for property-specific controls outside this fixture.
