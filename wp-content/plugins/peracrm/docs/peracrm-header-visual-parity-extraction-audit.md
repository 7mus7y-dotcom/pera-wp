# Peracrm Header Visual Parity Extraction Audit

## Executive summary

The active theme header/off-canvas is a **single-theme-template implementation** in `wp-content/themes/hello-elementor-child/header.php`, styled almost entirely from `wp-content/themes/hello-elementor-child/css/main.css`, with behavior in `wp-content/themes/hello-elementor-child/js/main.js`. It is **not Elementor-driven** and **not split into reusable template parts**. The visible pattern is exactly what you described: logo left, action icons right, right-anchored off-canvas drawer, full-screen overlay, two-column drawer at larger widths, and a one-column collapse at smaller widths.

For Peracrm, the safe conclusion is:

- **Replicate the visual shell** of the top header bar, logo presentation, right-side icon rhythm, drawer surface, overlay look, column layout, menu typography, and spacing.
- **Do not port the theme runtime implementation literally**, because the theme relies on global selectors like `#site-header`, `.header-icons`, `.offcanvas-nav`, and `body.is-nav-open`, plus theme-only helpers/assets such as `pera_get_site_logo_markup()`, the theme SVG sprite injector, and theme-local font/assets.
- **Keep CRM behavior plugin-owned**. The plugin already dequeues theme header assets on CRM routes and renders its own shell/header plus its own nav drawer, so the eventual implementation must hook into those existing plugin touchpoints instead of reintroducing theme dependencies.

---

## Source file inventory

### Theme render files
- `wp-content/themes/hello-elementor-child/header.php`  
  Renders the header bar, action cluster, off-canvas drawer, user/favourites area, director message, contact/social block, and backdrop.

### Theme CSS files
- `wp-content/themes/hello-elementor-child/css/main.css`  
  Contains the header shell styles, scroll-state glass treatment, icon sizing, off-canvas positioning, overlay, internal grid, typography, and contact/social styling.
- Also defines root tokens used by the header: `--brand`, `--container`, radius tokens, glass tokens, pill red, and local Montserrat font faces.

### Theme JS files
- `wp-content/themes/hello-elementor-child/js/main.js`  
  Controls open/close state via `body.is-nav-open`, overlay click, close click, `Escape`, accordion submenu toggling with `.is-open`, and the header scroll class `#site-header.is-scrolled`.
- `wp-content/themes/hello-elementor-child/js/favourites.js`  
  Hydrates guest “latest favourites” content inside the drawer via `[data-guest-*]` hooks. That secondary content is not structural to the drawer shell, but it is a runtime dependency for that specific section.

### Theme asset/helper sources
- Logo helper: `wp-content/themes/hello-elementor-child/inc/theme-helpers.php` → `pera_get_site_logo_markup()`.  
  Order of logo sourcing:
  1. `custom_logo`
  2. `logos-icons/pera-logo.svg`
  3. `logos-icons/logo-white.svg` fallback image.
- SVG icon sprite file: `wp-content/themes/hello-elementor-child/logos-icons/icons.svg`. It is used for header icons and social icons via `<use href="...#icon-*">`. The sprite is injected in the footer by `inc/modules/svg-sprite.php`, and `main.js` rewrites external sprite URLs to inline fragment refs after injection.
- Available header-logo-related files found:
  - `wp-content/themes/hello-elementor-child/logos-icons/pera-logo.svg`
  - `wp-content/themes/hello-elementor-child/logos-icons/logo-white.svg`
  - `wp-content/themes/hello-elementor-child/logos-icons/logo-navy.svg`
  - `wp-content/themes/hello-elementor-child/logos-icons/pera-small.svg`
  - `wp-content/themes/hello-elementor-child/logos-icons/icons.svg`

### Theme asset loading
- Theme loads `main.css` and `main.js` on non-CRM routes only.

### Plugin render/CSS/JS touchpoints
- Plugin shell header: `wp-content/plugins/peracrm/inc/views/shell/header.php`.
- Plugin nav drawer: `wp-content/plugins/peracrm/inc/views/partials/crm-side-nav.php`.
- Plugin CSS: `wp-content/plugins/peracrm/assets/frontend/crm.css`.
- Plugin JS: `wp-content/plugins/peracrm/assets/frontend/crm.js`.
- Plugin asset policy: dequeues theme `pera-main-css` / `pera-main-js` on CRM routes and enqueues plugin-owned CSS/JS instead.

---

## 1. Re-audit confirmation

### Render file(s)
- Theme header render is in `wp-content/themes/hello-elementor-child/header.php`. There is no separate theme template-part for the off-canvas shell; it is inline in this file.

### CSS file(s)
- All primary header/off-canvas visuals are in `wp-content/themes/hello-elementor-child/css/main.css`.

### JS file(s)
- Open/close mechanics, submenu accordions, and scroll-state header class are in `wp-content/themes/hello-elementor-child/js/main.js`.

### Logo/icon asset sources
- Logo helper uses custom logo first, then `logos-icons/pera-logo.svg`, then `logos-icons/logo-white.svg`.
- Header icons come from `logos-icons/icons.svg` via `<use>` in `header.php`.
- Social icons in the drawer also come from `logos-icons/icons.svg`.

### Breakpoint rules
- Header icon/logo small-screen tweak at `max-width: 640px`.
- Off-canvas width goes full width at `max-width: 768px`.
- Drawer content grid collapses to one column at `max-width: 900px`.
- Contact/footer area becomes one column at `max-width: 800px`.
- Narrow-typography tweak at `max-width: 480px`.

### Overlay/panel mechanics
- Drawer panel is fixed right, starts at `transform: translateX(100%)`, and moves in when `body.is-nav-open` is set.
- Overlay is `.offcanvas-backdrop`, full-viewport fixed, `rgba(0,0,0,0.5)`, hidden via `opacity: 0; pointer-events: none`, shown via `body.is-nav-open`.
- JS toggles `body.is-nav-open`, closes on backdrop click, close control click, and `Escape`.

---

## 2. Replication zones

## Zone A — Top shell / outer header bar

### Exact selectors
- `#site-header`
- `#site-header .header-inner`
- `#site-header .site-branding`
- `#site-header .header-icons`
- `#site-header.is-scrolled .site-branding`
- `#site-header.is-scrolled .header-icons`

### Exact source files
- Render: `wp-content/themes/hello-elementor-child/header.php`.
- CSS: `wp-content/themes/hello-elementor-child/css/main.css`.
- JS scroll-state toggling: `wp-content/themes/hello-elementor-child/js/main.js`.

### Visual behavior
- The shell is `position: sticky; top: 0; width: 100%; z-index: 1000; background: transparent;`.
- Inner layout is a max-width container with `padding: 18px 20px`, flex row, space-between. It uses `max-width: var(--container)` where `--container: 1200px`.
- At rest, both the logo block and the icon cluster are white and transparent; no glass surface is applied.
- On scroll (`.is-scrolled`), the logo block and icon cluster switch to `color: var(--brand)` and receive a glass treatment using `--glass-bg`, `blur(12px)`, `saturate(120%)`, an inset border, and a soft shadow.
- Hover treatment in scrolled state is only `opacity: 0.85` on the icon controls; there is no fixed header height rule, so perceived height comes from logo/icon sizes plus `padding: 18px 20px` and the cluster/block paddings.
- Layering: header `z-index: 1000`, drawer `9998`, backdrop `9997`. So the drawer and overlay sit above the header when open.

### Recommendation
- **RE-CREATE SAFELY**
- Reason: the visual shell is desirable, but the selectors are global and too collision-prone to port directly. The sticky/scroll/glass behavior should be recreated under plugin-specific selectors, not reused as `#site-header` + `.header-inner` theme rules.

## Zone B — Logo block

### Exact selectors
- `.site-branding`
- `.site-logo.logo-pera`
- `.site-logo.logo-pera svg`
- `#site-header .site-logo.logo-pera img.custom-logo`
- `#site-header .site-logo.logo-pera .pera-site-logo-image`
- `.offcanvas-top .site-logo.logo-pera ...`

### Exact asset path(s)
- Theme helper fallback paths:
  - `wp-content/themes/hello-elementor-child/logos-icons/pera-logo.svg`
  - `wp-content/themes/hello-elementor-child/logos-icons/logo-white.svg`
- Possible primary source: `custom_logo` from WP theme mod.

### Exact sizing/styling rules
- Header logo width: `200px`, mobile width `150px` below `640px`.
- Off-canvas logo width: `140px`.
- `.site-branding` has `padding: 5px 8px`, `border-radius: var(--radius-md)`, transparent at rest, glass on scroll. This padding creates the logo’s spacing off the left edge in combination with `.header-inner` padding.
- No separate breakpoint-specific alternate logo asset is used in the header code; only size changes are defined. No logo hover style is defined beyond inheriting shell color behavior.

### Safe recommendation for plugin parity
- **COPY LOOK ONLY** for size, placement, and spacing.
- **RE-CREATE SAFELY** for sourcing.  
  The plugin should keep using plugin-owned markup and ideally plugin-owned fallback asset(s), while optionally using `custom_logo` if available. It should **not depend on** `pera_get_site_logo_markup()` because that helper is theme-owned.

## Zone C — Right icon/action cluster

### Selector map for each action
- Cluster wrapper: `.header-icons`.
- CRM action: `.header-crm-toggle` with `.header-icon-dot` when overdue reminders exist.
- Search action: `.header-search-toggle`.
- Menu trigger: `.header-menu-toggle`.

### Source file locations
- Markup: `wp-content/themes/hello-elementor-child/header.php`.
- CSS: `wp-content/themes/hello-elementor-child/css/main.css`.
- Related behavior: no separate JS handlers for CRM/search; only menu toggle behavior in `main.js`.

### Audit details
- Order is CRM → Search → Menu in markup.
- Cluster uses `gap: 8px`, `padding: 5px 12px`, `border-radius: 999px`.
- Icon size is `30px`, reduced to `22px` on mobile; the CRM glyph is specifically set to `30px` desktop / `24px` mobile to visually align with the other icons.
- Button hit areas are visually only the SVG/control itself because the anchor/label wrappers have `padding: 0`; there is no dedicated minimum tap target sizing here.
- No text labels are shown in the theme header action cluster; only `aria-label`s are present.
- The CRM button is theme-level/site-level, but functionally it routes to `/crm` and adds an overdue dot based on CRM-specific permissions/counts. That is not a generic visual element; it is coupled to CRM logic.
- Hover state is subtle `opacity: 0.85` only when scrolled; no explicit focus style is defined here in the header rule block.
- Visibility does not differ by breakpoint in the theme header cluster itself; only sizes change below `640px`.

### Notes on visual vs functional matching
- CRM/search/menu **spacing and icon rhythm should match visually**.
- CRM plugin should **keep its own click handlers/routes**:
  - CRM action should stay CRM-specific.
  - Search action in Peracrm should remain whatever CRM needs, not the theme’s property archive route.
  - Menu trigger must continue to control the plugin drawer, not the theme drawer state.

### Recommendation
- CRM action: **KEEP CRM-SPECIFIC**
- Search action: **KEEP CRM-SPECIFIC** functionally, but **COPY LOOK ONLY** if a search action remains in the future.
- Menu action: **COPY LOOK ONLY** visually, **RE-CREATE SAFELY** behaviorally.

## Zone D — Menu trigger / hamburger / close icon

### Selectors/classes
- Trigger: `.header-menu-toggle`
- Close control: `.offcanvas-close`
- Checkbox input: `#nav-toggle.nav-toggle`
- JS hooks: `.header-menu-toggle`, `.offcanvas-close`

### Source CSS/JS
- CSS:
  - Trigger base styling comes from `#site-header .header-icons a, ... .header-menu-toggle`.
  - Close icon styling is `.offcanvas-close`.
- JS:
  - Open/close logic in `main.js`.

### Audit
- Visual design of trigger: bare icon-only control using `#icon-bars`, no pill/background of its own except the surrounding cluster shell when scrolled.
- Close icon is plain text `&times;`, styled at `34px`, weight `200`, white, padded `0 10px`. It is visually simple, not an animated icon swap from the trigger glyph.
- Transition behavior applies to the panel transform, not to a morphing hamburger/close icon. No icon transition is defined between open and closed states.
- Implementation is a **hybrid**:
  - markup includes a hidden checkbox + labels targeting it,
  - but actual open/close state is controlled in JS by toggling `body.is-nav-open`.
- That hybrid pattern is not clean to port because the checkbox is effectively redundant for the actual state machine.

### Visual parity yes / implementation parity no
- **Visual parity yes**: hamburger glyph size/weight and simple white close mark are worth matching.
- **Implementation parity no**: do **not** reproduce the checkbox/label hybrid. Peracrm already has a cleaner button-driven drawer toggle with `aria-expanded` and data attributes.

## Zone E — Overlay

### Exact selectors
- `.offcanvas-backdrop`
- `body.is-nav-open .offcanvas-backdrop`

### Styling summary
- Full viewport: `position: fixed; inset: 0`
- Color: `rgba(0,0,0,0.5)`
- Hidden state: `opacity: 0; pointer-events: none`
- Transition: `opacity .35s ease`
- Z-index: `9997`
- Click-to-close handled in JS.

### Recommendation
- **RE-CREATE SAFELY**
- Styling can be copied closely, but it must be re-scoped under plugin selectors and plugin z-index conventions. The plugin already has `.crm-side-nav__overlay`; that is the correct hook to evolve rather than introducing `.offcanvas-backdrop` into CRM routes.

## Zone F — Off-canvas panel container

### Exact selectors
- `.offcanvas-nav`
- `.offcanvas-inner`
- `body.is-nav-open .offcanvas-nav`

### Exact source rules
- Width desktop: `75%`
- Max width: `900px`
- Mobile/tablet width at `max-width: 768px`: `100%`
- Positioning: fixed, top `0`, right `0`, full viewport height
- Background: `var(--brand)`
- Text color: white
- Closed transform: `translateX(100%)`
- Open transform: `translateX(0)`
- Transition: `transform .45s cubic-bezier(.25,.1,.25,1)`
- Z-index: `9998`
- Inner padding: `45px 40px`
- Inner scrolling: `overflow-y: auto`

### Portable values to replicate
- Right-anchored panel
- Full-height viewport
- 75% / 900px desktop cap
- 100% width under 768px
- `translateX(100%)` → `0`
- ~450ms ease-like slide
- Strong brand background with white content
- Generous internal padding.

### Theme-coupled values to avoid
- Raw `.offcanvas-nav` selector
- Theme token dependency on `var(--brand)` without plugin-local fallback
- Theme z-index stack if CRM shell already has its own layering model
- Theme’s full-width-on-768 rule if CRM needs a slightly different device threshold.

### Recommendation
- **COPY LOOK ONLY** for surface, width profile, and motion.
- **RE-CREATE SAFELY** under plugin-owned drawer container.

## Zone G — Off-canvas internal layout

### ASCII wireframe based on actual structure

```text
+------------------------------------------------------------+
| offcanvas-top                                              |
| [logo]                                         [ × close ] |
+------------------------------------------------------------+
| offcanvas-main                                             |
|                                                            |
|  offcanvas-main-left          | offcanvas-main-right       |
|  ---------------------------  | -------------------------  |
|  [main menu items]            | [user panel or client]     |
|  [submenu items/accordion]    | [latest favourites]        |
|                               | [director message]         |
|                                                            |
+------------------------------------------------------------+
| offcanvas-contact                                          |
| [contact text] | [social icons] | [optional login area]    |
+------------------------------------------------------------+
```

This is a real CSS grid split (`grid-template-columns: 2fr 2fr`) rather than a flex split. It collapses to one column below `900px`.

### Selector map for each subsection
- Top row: `.offcanvas-top`
- Main grid: `.offcanvas-main`
- Left column: `.offcanvas-main-left` containing `.offcanvas-menu`
- Right column: `.offcanvas-main-right` containing:
  - `#offcanvas-user-panel.offcanvas-user-panel`
  - `.offcanvas-latest-favs`
  - `.offcanvas-director-title`
  - `.offcanvas-director-text`
  - `.offcanvas-director-name`
- Bottom utility block: `.offcanvas-contact`
  - `.offcanvas-contact-text`
  - `.offcanvas-contact-social.footer-social`

### Portable layout rules
- `display: grid`
- two equal-ish columns (`2fr 2fr`)
- `gap: 40px`
- `margin-bottom: 30px`
- collapse to one column at `900px`
- contact block anchored to bottom via `margin-top: auto` on `.offcanvas-contact` inside a column flex wrapper.

### CRM-safe reproduction recommendation
- **RE-CREATE SAFELY**
- Keep the same visual composition:
  - top logo/close row,
  - main content split,
  - bottom utility strip.
- But the right column should become CRM-specific utility content, not site marketing/personalisation content.

## Zone H — Typography and menu item styling

### Selector map
- `.offcanvas-menu > li > a`
- `.offcanvas-menu > li > a:hover`
- `.offcanvas-menu li.menu-item-has-children > a::after`
- `.offcanvas-menu .sub-menu`
- `.offcanvas-menu li.is-open > .sub-menu`
- `.offcanvas-menu .sub-menu a`
- `.offcanvas-director-title`
- `.offcanvas-director-text`
- `.offcanvas-director-name`

### Source CSS rules
- Base family comes from local Montserrat `@font-face` in `main.css`.
- Top-level menu links:
  - `font-size: 0.90rem`
  - `font-weight: 500`
  - white text
  - bottom border with low opacity
  - padding `4px 0 5px`
  - flex alignment with `gap: 8px`.
- Hover: lightened text + slightly stronger divider.
- Submenu indicator: `"▾"` pseudo-element, `font-size: 0.7rem`, rotates 180° when open.
- Submenus:
  - hidden via `max-height: 0`, `opacity: 0`
  - indented with `padding-left: 12px`
  - left border `1px solid rgba(255,255,255,0.1)`
  - shown by `.is-open`.
- Submenu links:
  - `font-size: 0.85rem`
  - `padding: 5px 0`
  - lighter white tint
  - bottom border subtle.
- Side headings:
  - `.offcanvas-director-title`: `1rem`, `600`, uppercase, `letter-spacing: 0.06em`
  - `.offcanvas-director-text`: soft white
  - `.offcanvas-director-name`: `0.85rem`, italic.

### Typography tokens worth reusing in plugin namespace
- Font family: Montserrat stack
- Brand navy background + white foreground contrast
- Uppercase section labels with `letter-spacing: 0.06em`
- Top-level menu around `0.90rem` / 500
- Submenu around `0.85rem`
- Divider opacity pattern
- Light hover tint rather than heavy hover decoration.

### Recommendation
- **COPY LOOK ONLY**
- But fonts must be plugin-owned; the plugin currently loads Montserrat from Google Fonts, not the theme-local `woff2` files.

## Zone I — Secondary content inside drawer

### Itemized list of visible sections
1. User/client area:
   - `#offcanvas-user-panel.offcanvas-user-panel`
   - shows either “Welcome back” with logout/favourites and latest favourites, or “Client area” login panel for guests.
2. Latest favourites:
   - `.offcanvas-favourites-summary`
   - `.offcanvas-favourites-link`
   - guest hooks: `[data-guest-fav-link]`, `[data-guest-latest-favs]`, `[data-guest-latest-favs-list]`.
3. Director message:
   - `.offcanvas-director-title`
   - `.offcanvas-director-text`
   - `.offcanvas-director-name`.
4. Bottom contact block:
   - `.offcanvas-contact`
   - `.offcanvas-contact-text`
   - `.offcanvas-contact-social.footer-social`
   - `.footer-social-link`.
5. CTA buttons/links:
   - `.btn.btn--solid.btn--green`
   - `.btn.btn--solid.btn--black`
   - logout/login/favourites actions in user panel.

### Recommendation by section
- User/account area: **REPLACE WITH CRM-SPECIFIC CONTENT**
- Latest favourites: **OMIT**
- Director message: **OMIT**
- Contact/social block: **REPLICATE PARTIALLY** only if CRM needs a secondary utility/footer area inside the drawer; otherwise omit.
- CTA buttons/links: **REPLACE WITH CRM-SPECIFIC CONTENT**

### Why
These sections are not part of the core header/drawer visual shell; they are theme/site marketing and client-area content. They also bring in non-CRM behaviors and external site/social goals that are unrelated to the CRM workspace. The plugin drawer should instead use that right-hand column for CRM-specific status/actions/navigation helpers while keeping the same spacing and typography shell.

---

## 3. Actual-state ASCII wireframes

### Desktop header closed

```text
┌──────────────────────────────────────────────────────────────────────────────┐
│ sticky transparent header                                                   │
│                                                                              │
│  [logo block, white]                           [CRM] [Search] [≡]           │
│                                                                              │
└──────────────────────────────────────────────────────────────────────────────┘
```

At scroll, both the logo block and the icon cluster gain separate glass backgrounds and switch to brand-colored content in light mode.

### Desktop header open

```text
┌──────────────────────────────────── page ────────────────────────────────────┐
│ [header remains underneath z-stack]                                          │
│                                                                              │
│                    [dark overlay over viewport]                              │
│                                              ┌────────────────────────────┐  │
│                                              │ [logo]               [×]   │  │
│                                              │                            │  │
│                                              │ [main nav]  [user/client]  │  │
│                                              │ [submenu]   [favourites]   │  │
│                                              │            [director msg]  │  │
│                                              │                            │  │
│                                              │ [contact text][socials]    │  │
│                                              └────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────────────┘
```

Drawer overlays the header because drawer/backdrop z-indices exceed the header’s z-index.

### Mobile header closed

```text
┌──────────────────────────────────────────┐
│ [logo 150px]             [CRM][Search][≡]│
└──────────────────────────────────────────┘
```

Icons shrink to ~22–24px and logo to 150px below 640px.

### Mobile drawer open

```text
┌──────────────────────────────────────────┐
│ [logo]                             [×]   │
│------------------------------------------│
│ [main nav item]                          │
│ [main nav item ▼]                        │
│   [submenu item]                         │
│   [submenu item]                         │
│------------------------------------------│
│ [client/user area]                       │
│ [latest favourites if present]           │
│ [director message]                       │
│------------------------------------------│
│ [contact text]                           │
│ [social icons]                           │
└──────────────────────────────────────────┘
```

Below 900px the main grid is single-column; below 768px the panel itself becomes full width.

---

## 4. Proposed Peracrm Visual Parity Scope

## Proposed Peracrm Visual Parity Scope — ASCII wireframes

### Desktop closed

```text
┌──────────────────────────────────────────────────────────────────────────────┐
│ plugin-owned sticky header                                                   │
│                                                                              │
│  [logo block with same spacing/look]     [CRM action(s)] [search?] [≡]      │
│                                                                              │
└──────────────────────────────────────────────────────────────────────────────┘
```

### Desktop open

```text
┌──────────────────────────────────── page ────────────────────────────────────┐
│                                                                              │
│                    [plugin-owned dark overlay]                               │
│                                              ┌────────────────────────────┐  │
│                                              │ [logo]               [×]   │  │
│                                              │                            │  │
│                                              │ [CRM nav]   [CRM utility]  │  │
│                                              │ [sections]  [status/help]  │  │
│                                              │                            │  │
│                                              │ [optional lower utility]   │  │
│                                              └────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────────────┘
```

### Mobile closed

```text
┌──────────────────────────────────────────┐
│ [logo]                [CRM action(s)] [≡]│
└──────────────────────────────────────────┘
```

### Mobile open

```text
┌──────────────────────────────────────────┐
│ [logo]                             [×]   │
│------------------------------------------│
│ [CRM nav item]                           │
│ [CRM nav item]                           │
│ [CRM nav item]                           │
│------------------------------------------│
│ [CRM-specific utility block]             │
│ [optional account/status/help]           │
└──────────────────────────────────────────┘
```

---

## 5. Replicate Matrix

| Part | Theme source | Recommendation | Reason |
|---|---|---|---|
| Sticky transparent header shell | `header.php` + `main.css` `#site-header`, `.header-inner` | RE-CREATE SAFELY | Visual pattern is good, but selectors are global and theme-coupled. |
| Scroll glass treatment on logo and action cluster | `main.css` `.is-scrolled` rules | COPY LOOK ONLY | Worth matching visually, but must be plugin-scoped and plugin-triggered. |
| Logo sizing/placement | `header.php`, `main.css`, `theme-helpers.php` | COPY LOOK ONLY | Same visual size and spacing are desirable. Asset sourcing should remain plugin-owned/flexible. |
| Theme logo helper `pera_get_site_logo_markup()` | `inc/theme-helpers.php` | OMIT | Theme helper introduces runtime dependency on child theme code. |
| Right action cluster spacing/rhythm | `.header-icons` rules | COPY LOOK ONLY | Visual parity target; safe if recreated under plugin namespace. |
| Theme CRM icon button logic | `header.php` CRM checks + overdue dot | KEEP CRM-SPECIFIC | In CRM shell, action semantics should remain owned by plugin routes/data, not copied from site header logic. |
| Theme property-search button route | `.header-search-toggle` → property archive | KEEP CRM-SPECIFIC | That route is site-search-specific, not CRM-shell-specific. |
| Hamburger icon visual | `.header-menu-toggle` + `#icon-bars` | COPY LOOK ONLY | Good visual reference; behavior should use plugin JS hooks. |
| Checkbox/label nav-toggle mechanism | `#nav-toggle` + label wiring | OMIT | Hybrid pattern is unnecessary and inferior to plugin’s button/data-attribute model. |
| Close “×” visual in drawer | `.offcanvas-close` | COPY LOOK ONLY | Visual simplicity is portable; implement as accessible button in plugin markup. |
| Overlay look | `.offcanvas-backdrop` | RE-CREATE SAFELY | Same look is fine; existing plugin overlay hook should be reused instead of importing theme class names. |
| Drawer width/motion/surface | `.offcanvas-nav`, `.offcanvas-inner` | COPY LOOK ONLY | Strong candidate for parity; implement inside plugin drawer container. |
| Two-column large-screen internal layout | `.offcanvas-main` | COPY LOOK ONLY | Valuable visual structure for CRM drawer content too. |
| 900px collapse to one column | `.offcanvas-main` media rule | COPY LOOK ONLY | Good portable behavior for drawer responsiveness. |
| Menu typography/dividers/submenu styling | `.offcanvas-menu*` | COPY LOOK ONLY | Good visual language; should be plugin-scoped and fed by CRM nav data. |
| Theme main navigation content | `wp_nav_menu( main_menu_v1 )` | OMIT | CRM drawer should not depend on site menu location or site nav content. |
| User/client area inside drawer | `#offcanvas-user-panel` | OMIT | Site account/favourites content is not CRM-shell parity content. |
| Guest/latest favourites runtime | `favourites.js` + `[data-guest-*]` | OMIT | Theme-specific personalization and AJAX behavior unrelated to CRM shell. |
| Director message | `.offcanvas-director-*` | OMIT | Marketing/editorial content, not CRM-functional shell content. |
| Contact/social footer strip | `.offcanvas-contact*` | RE-CREATE SAFELY | Layout treatment may be useful, but content should be CRM-specific or omitted if unnecessary. |
| Theme SVG sprite runtime | `svg-sprite.php` + `main.js` rewrite | OMIT | Plugin should not rely on theme footer sprite injection or rewrite behavior. |
| Theme-local font files | `main.css @font-face` | RE-CREATE SAFELY | Typography can match, but asset ownership must remain plugin-controlled. |

---

## 6. High-level audit of current Peracrm header files

### Current plugin shell/header file(s)
- Shell header renderer: `wp-content/plugins/peracrm/inc/frontend/view-loader.php` → `peracrm_frontend_render_shell_header()`.
- Actual shell header template: `wp-content/plugins/peracrm/inc/views/shell/header.php`.
- CRM side nav partial: `wp-content/plugins/peracrm/inc/views/partials/crm-side-nav.php`.
- CRM pages render both the shell header and the side nav partial. Example: `crm-overview.php`.

### Current plugin CSS/JS controlling header/menu
- Header shell and nav styles in `wp-content/plugins/peracrm/assets/frontend/crm.css`.  
  Existing relevant selectors:
  - `body.crm-route #site-header.peracrm-shell-header`
  - `.peracrm-header-actions`
  - `.crm-side-nav__toggle`
  - `.crm-side-nav__overlay`
  - `.crm-side-nav--drawer`
  - `.crm-side-nav__drawer-header`
  - `.crm-side-nav__close`
- Menu open/close in `wp-content/plugins/peracrm/assets/frontend/crm.js`.  
  Uses:
  - `[data-crm-nav]`
  - `[data-crm-nav-toggle]`
  - `[data-crm-nav-drawer]`
  - `[data-crm-nav-overlay]`
  - `[data-crm-nav-close]`
  - toggles `.is-open` and `body.crm-nav-open`.

### Existing functional items that must remain intact
- The shell header is already plugin-owned and route-aware.
- CRM drawer links are route-driven:
  - Overview
  - Clients
  - Tasks
  - Pipeline
  - Create lead
  - optional WhatsApp logs / Email logs depending on permissions.
- Menu toggle state already keeps `aria-expanded` in sync and closes on overlay/Escape/desktop resize.
- Plugin already disables theme global presentation bundle on CRM routes, which must continue to preserve independence.

### Current selectors/hooks we should preserve
- `#site-header.peracrm-shell-header`
- `.peracrm-header-actions`
- `.crm-nav-shell`
- `.crm-side-nav__toggle`
- `.crm-side-nav__overlay`
- `.crm-side-nav--drawer`
- `.crm-side-nav__drawer-header`
- `.crm-side-nav__close`
- `[data-crm-nav-toggle]`
- `[data-crm-nav-drawer]`
- `[data-crm-nav-overlay]`
- `[data-crm-nav-close]`

These are the safest integration points. The next implementation pass should restyle and possibly enrich these hooks, not replace them blindly.

---

## 7. Important risk analysis

### Theme selectors that are too global to port
- `#site-header`
- `.header-inner`
- `.site-branding`
- `.header-icons`
- `.offcanvas-nav`
- `.offcanvas-inner`
- `.offcanvas-menu`
- `.offcanvas-backdrop`
- `body.is-nav-open`

These would collide with plugin header markup immediately, especially because the plugin already uses `#site-header.peracrm-shell-header`.

### Dependencies on theme variables/wrappers
- `--container`, `--brand`, `--radius-md`, `--radius-lg`, `--pill-red`, and all glass tokens come from theme `:root`.
- Theme layout expects `.container` plus theme token values. The plugin has its own container token definitions under `.crm-page`.

### Dependencies on theme fonts/assets
- Theme header typography depends on theme-local Montserrat `woff2` files declared in `main.css`.
- Theme icons depend on `logos-icons/icons.svg` plus footer sprite injection and JS rewrite logic.
- Theme logo helper and fallback asset chain are theme-owned. Plugin currently only has `peracrm/logos-icons/pera-logo.svg` as its local fallback asset in this directory listing.

### Accessibility issues in the theme implementation that should not be reproduced literally
- Menu trigger is a `label` wired to a hidden checkbox, but the actual open state is JS-managed via `body.is-nav-open`. That is a confusing hybrid and should not be copied.
- There is no focus trap or explicit focus return in the theme drawer JS. The plugin does at least manage `aria-expanded`, but future parity work should improve focus behavior further rather than matching the theme limitation.
- Header icon targets do not define minimum tap target dimensions; wrappers use `padding: 0`. For CRM mobile usability, this should be improved rather than copied exactly.

### Behaviors that would conflict with CRM routing or shell layout
- Theme search button routes to the property archive, not CRM search.
- Theme drawer content uses `wp_nav_menu( theme_location => 'main_menu_v1' )`, which is site navigation, not CRM navigation.
- Theme drawer contains favourites/account/marketing content that is irrelevant to CRM task flow and would distract from CRM routing/actions.
- Theme global assets are explicitly removed on CRM routes, so any implementation plan that assumes reuse of theme `main.css` / `main.js` is incompatible with the current plugin architecture.

---

## Plugin integration touchpoints

The eventual parity implementation should hook into these plugin-owned touchpoints:

- Shell header template: `wp-content/plugins/peracrm/inc/views/shell/header.php`.
- Existing plugin header wrapper: `#site-header.peracrm-shell-header`.
- Current header action wrapper: `.peracrm-header-actions`.
- Existing nav toggle button and state attributes: `[data-crm-nav-toggle]`, `aria-expanded`, `aria-controls="crm-side-nav-drawer"`.
- Existing nav shell and overlay/drawer nodes: `.crm-nav-shell`, `.crm-side-nav__overlay`, `.crm-side-nav--drawer`.
- Existing JS open/close controller: `crm.js` `setOpen()`, `.is-open`, `body.crm-nav-open`.
- Existing responsive policy: current drawer becomes mobile/tablet-only at `max-width: 1024px`; desktop still uses the inline side nav. That is a larger architectural difference from the theme, and the next pass must decide whether to preserve that behavior or evolve it while keeping CRM navigation intact.

---

## Implementation brief for next pass

- Keep the implementation entirely inside the plugin:
  - `inc/views/shell/header.php`
  - `inc/views/partials/crm-side-nav.php`
  - `assets/frontend/crm.css`
  - `assets/frontend/crm.js`
- Recreate the theme’s **visual shell only**:
  - sticky transparent top bar,
  - left logo block,
  - right icon cluster rhythm,
  - scroll-state glass styling,
  - right-side navy drawer,
  - dark overlay,
  - two-column large-screen drawer / one-column smaller-screen drawer,
  - menu typography and divider language.
- Do **not** import or depend on:
  - theme `main.css`,
  - theme `main.js`,
  - `pera_get_site_logo_markup()`,
  - theme `body.is-nav-open` state,
  - theme `.offcanvas-*` class names,
  - theme SVG sprite injection pipeline.
- Preserve existing CRM functionality:
  - current CRM routes,
  - existing drawer toggle handlers,
  - existing `aria-expanded` sync,
  - existing overlay close and `Escape` close,
  - desktop/mobile CRM navigation usability.
- Replace the theme drawer’s right-column marketing/account content with CRM-owned content only if useful; otherwise keep it minimal.
- Improve, do not copy, theme accessibility gaps:
  - use real buttons,
  - keep current plugin JS state model,
  - add focus management if implementation expands drawer scope,
  - ensure touch targets are large enough on mobile.
