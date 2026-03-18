# Theme Header + Off-Canvas Menu Audit for Peracrm Visual Parity

## Executive summary

The live site header is **theme-native and rendered directly by the child theme’s `header.php`**, not by Elementor Theme Builder, not by a shortcode, and not by a plugin shell. The active header markup is emitted by `wp-content/themes/hello-elementor-child/header.php`, which outputs both the visible site header and the off-canvas menu markup in one file. The primary menu content comes from the registered nav location `main_menu_v1`.

The theme’s header pattern is **not a “desktop inline nav + mobile drawer” system**. Instead, the visible header is a compact bar with **logo on the left** and **icon actions on the right**; the main navigation always lives inside the off-canvas panel. On desktop, the panel opens at `75%` width up to `900px`; on smaller screens it becomes full-width. The off-canvas internal content is two-column above `900px` and single-column below that. There is no breakpoint where an inline desktop primary nav appears, because no such markup exists in the header template.

The header/off-canvas behavior is controlled by `wp-content/themes/hello-elementor-child/js/main.js`, which toggles `body.is-nav-open`, closes on overlay click and `Escape`, toggles submenu accordions by adding `.is-open`, and toggles `#site-header.is-scrolled` after scrolling past 12px. However, it does **not** implement focus trapping, explicit focus return, or body scroll lock for the theme nav.

For CRM routes, the standalone plugin already **dequeues the theme header assets** (`pera-main-css`, `pera-main-js`) and renders its own shell header. That means any visual parity work must **re-create** the theme header/off-canvas behavior inside plugin-owned markup/CSS/JS rather than importing the theme runtime.

---

## Audit method / commands used

- `find .. -name AGENTS.md -print`
- `find wp-content/themes/hello-elementor-child -maxdepth 3 -type f | sort`
- `find wp-content/plugins/peracrm -maxdepth 4 -type f | sort`
- `rg -n "elementor|header|get_header|wp_nav_menu|register_nav_menus|theme_location|nav_menu|off-canvas|offcanvas|hamburger|burger|menu-toggle|drawer|overlay|mobile-menu|sticky|scroll-lock|focus trap|Escape|keydown|aria-expanded|aria-controls|custom_logo|the_custom_logo|site-logo|site-header|primary-menu" wp-content/themes/hello-elementor-child wp-content/plugins/peracrm`
- `rg -n "elementor_theme_do_location|elementor_location_exits|Theme Builder|header-footer|Hello Elementor|display_header_footer|register_nav_menu|register_nav_menus|main_menu_v1|footer_menu|guidance|add_theme_support( 'custom-logo'|custom-logo|wp_body_open|body_class|add_filter( 'body_class'|body_class(" wp-content/themes/hello-elementor-child/inc wp-content/themes/hello-elementor-child/functions.php wp-content/themes/hello-elementor-child/header.php wp-content/plugins/peracrm/inc`
- `rg -n "nav-toggle|is-nav-open|offcanvas-backdrop|offcanvas-nav" wp-content/themes/hello-elementor-child/css/main.css wp-content/themes/hello-elementor-child/js/main.js wp-content/themes/hello-elementor-child/header.php`

---

## File inventory

### Active theme header implementation
- `wp-content/themes/hello-elementor-child/header.php`
  Renders:
  - skip link
  - hidden `#nav-toggle` checkbox
  - `#site-header.site-header`
  - icon actions (`.header-crm-toggle`, `.header-search-toggle`, `.header-menu-toggle`)
  - full off-canvas menu markup
  - overlay/backdrop

### Theme helpers and registration
- `wp-content/themes/hello-elementor-child/inc/modules/theme-setup.php`
  Registers `main_menu_v1` and enables `custom-logo`
- `wp-content/themes/hello-elementor-child/inc/theme-helpers.php`
  Provides `pera_get_site_logo_markup()` and font preload helper

### Theme CSS / JS
- `wp-content/themes/hello-elementor-child/inc/modules/enqueue-assets.php`
  Enqueues `css/main.css` and `js/main.js` on non-CRM routes
- `wp-content/themes/hello-elementor-child/css/main.css`
  Defines header tokens, sticky header styles, off-canvas panel layout, overlay, contact block, typography, breakpoints
- `wp-content/themes/hello-elementor-child/js/main.js`
  Controls open/close, accordion submenus, and scroll-state styling

### Theme asset dependencies
- `wp-content/themes/hello-elementor-child/logos-icons/icons.svg`
- `wp-content/themes/hello-elementor-child/logos-icons/pera-logo.svg`
- `wp-content/themes/hello-elementor-child/logos-icons/logo-white.svg`
- `wp-content/themes/hello-elementor-child/fonts/Montserrat-*.woff2`
  Used by `main.css` and preloaded in `theme-helpers.php`

### Theme wrapper / runtime notes
- `wp-content/themes/hello-elementor-child/inc/disable-hello-parent-loads.php`
  Disables Hello Elementor parent front-end CSS, including `hello-elementor-header-footer`. This strongly indicates the child theme’s own header is the intended active header layer.

### Current plugin-owned CRM shell
- `wp-content/plugins/peracrm/inc/views/shell/header.php`
  Minimal plugin shell header with logo and CRM menu button
- `wp-content/plugins/peracrm/inc/views/partials/crm-side-nav.php`
  Plugin’s own CRM overlay + drawer nav
- `wp-content/plugins/peracrm/assets/frontend/crm.css`
  Plugin header/drawer styles
- `wp-content/plugins/peracrm/assets/frontend/crm.js`
  Plugin nav behavior

---

## Render path summary

### Theme render entry point

1. Normal theme templates call `get_header()`.
2. WordPress resolves the child theme’s `header.php`.
3. That `header.php` directly emits the **site header** and **off-canvas nav** markup.
4. The menu list itself is populated via `wp_nav_menu( [ 'theme_location' => 'main_menu_v1' ] )`.

### Partials/includes used by the theme header

The theme header does **not** split the header into template parts. It is a single-file implementation in `header.php`. It does call a helper for logo markup, `pera_get_site_logo_markup()`, from `inc/theme-helpers.php`.

### Active-vs-possible header sources

**Active source:** child theme `header.php`.

**Why:**
- The child theme defines a full `header.php` with concrete header markup.
- The child theme disables the Hello parent header/footer CSS handles.
- Menu registration and helper functions are theme-managed.

**Not supported by evidence in this repo audit:** Elementor Theme Builder header, shortcode header, or hook-generated header.

I specifically searched for Elementor header location hooks (`elementor_theme_do_location`, similar builder hooks) and did not find them in the child theme/plugin code with the commands listed above.

---

## CSS / JS dependency summary

### CSS dependency chain

For non-CRM routes:
1. `functions.php` loads bootstrap/module files.
2. `inc/theme-modules.php` includes `inc/modules/enqueue-assets.php`.
3. `enqueue-assets.php` enqueues `pera-main-css` from `css/main.css` on all non-CRM routes.
4. `main.css` contains all header and off-canvas styles.

### JS dependency chain

For non-CRM routes:
1. `enqueue-assets.php` enqueues `pera-main-js` from `js/main.js` on non-CRM routes.
2. `main.js`:
   - adds/removes `body.is-nav-open`
   - toggles off-canvas visibility indirectly through CSS
   - handles submenu accordion `.is-open`
   - toggles `#site-header.is-scrolled`

### Asset dependency chain

#### Logo
- Source helper: `pera_get_site_logo_markup()`
- Order of preference:
  1. `get_theme_mod('custom_logo')`
  2. fallback file `logos-icons/pera-logo.svg`
  3. final fallback image `logos-icons/logo-white.svg`

#### Icons
- Header and off-canvas icons use `<use href=".../logos-icons/icons.svg#icon-*">`
- `inc/modules/svg-sprite.php` inlines the `icons.svg` sprite into the footer
- `main.js` rewrites external `icons.svg#...` references to inline fragment references after the sprite is injected

#### Fonts
- `main.css` declares local `Montserrat` faces at weights 400/700/800
- `pera_preload_fonts()` preloads those same `.woff2` files into `<head>`

### Important WordPress hooks / filters affecting output

- `after_setup_theme` → enables custom logo, registers nav menus
- `wp_enqueue_scripts` → enqueues theme CSS/JS
- `wp_footer` → injects inline SVG sprite
- `wp_head` → preloads fonts
- `wp_nav_menu_items` in the plugin can inject a CRM menu item into `main_menu_v1` for authorized users, so the live theme off-canvas menu content can differ by auth state

---

## Architecture summary

### Render entry point
- **Theme live header:** `wp-content/themes/hello-elementor-child/header.php`

### Partials/includes used
- No theme partial for header/off-canvas; direct markup in `header.php`
- Helper dependency: `pera_get_site_logo_markup()` in `inc/theme-helpers.php`

### CSS dependency chain
- `functions.php` → `inc/theme-modules.php` → `inc/modules/enqueue-assets.php` → `css/main.css`

### JS dependency chain
- `functions.php` → `inc/theme-modules.php` → `inc/modules/enqueue-assets.php` → `js/main.js`

### Asset dependency chain
- Logo: `custom_logo` → `logos-icons/pera-logo.svg` → `logos-icons/logo-white.svg`
- Icons: `logos-icons/icons.svg` + footer inline sprite injection + JS rewrite
- Fonts: `fonts/Montserrat-*.woff2` via `main.css` + preload hook

---

## ASCII wireframes

### A. Desktop header

Actual live structure:

```text
[sticky transparent header shell]
+----------------------------------------------------------------------------------+
| [Logo / custom logo or pera-logo.svg]                           [CRM?][Search][≡] |
+----------------------------------------------------------------------------------+

When page scrollY > 12:
- logo cluster gets glass background + brand color
- icon cluster gets glass pill background + brand color

Off-canvas is NOT inline on desktop.
Desktop nav is still accessed via hamburger.

Desktop open panel:
                                  [Overlay on page]
                    +------------------------------------------------------+
                    | [Logo]                                      [×]      |
                    |                                                      |
                    | [Main menu column]      [User panel / Director msg]  |
                    | - top-level item        - login/logout/favourites    |
                    | - accordion submenu     - latest favourites          |
                    |                           - director copy             |
                    |                                                      |
                    | [Contact text] [Social icons] [login/favourites CTA] |
                    +------------------------------------------------------+
```

### B. Mobile header closed state

```text
[sticky transparent header shell]
+--------------------------------------------------+
| [Logo]                            [CRM?][🔍][≡]  |
+--------------------------------------------------+

- same basic header markup as desktop
- smaller logo and icon sizes at <= 640px
- off-canvas remains hidden off-screen to the right
```

### C. Mobile header off-canvas open state

```text
[full-screen dark overlay]
+--------------------------------------------------+
| [Logo]                                      [×]  |
|                                                  |
| [Main menu list]                                 |
| - Item                                           |
| - Item ▾                                         |
|   - Sub item                                     |
|   - Sub item                                     |
| - Item                                           |
|                                                  |
| [Client area / favourites or logout buttons]     |
| [Latest favourites block if available]           |
| [Director message]                               |
|                                                  |
| [Reach our Istanbul team...]                     |
| [WhatsApp][Instagram][YouTube][Facebook][... ]   |
+--------------------------------------------------+
```

---

## Major visible elements audit

### 1) `#site-header`
- **Selector:** `#site-header.site-header`
- **Purpose:** sticky shell for site logo and right-side action icons
- **Layout:** full-width, sticky at top, transparent until scrolled
- **Visibility:** always visible
- **Scroll behavior:** gains `.is-scrolled` after `window.scrollY > 12`

### 2) `.header-inner`
- **Selector:** `#site-header .header-inner`
- **Purpose:** inner max-width container
- **Layout:** `max-width: var(--container)`; horizontal flex; `justify-content: space-between`
- **Visibility:** always visible
- **Scroll behavior:** none directly

### 3) `.site-branding`
- **Selector:** `#site-header .site-branding`
- **Purpose:** left logo cluster
- **Layout:** flexible left-aligned block with `padding: 5px 8px`, radius, transparent by default, glass on scroll
- **Visibility:** always visible
- **Scroll behavior:** transparent initially; glass shell in scrolled state

### 4) `.site-logo.logo-pera`
- **Selector:** `.site-logo.logo-pera`
- **Purpose:** home link with theme custom logo or fallback logo asset
- **Layout:** logo image/svg width `200px`; reduced to `150px` at `<= 640px`
- **Visibility:** always visible
- **Scroll behavior:** inherits color from parent cluster

### 5) `.header-icons`
- **Selector:** `#site-header .header-icons`
- **Purpose:** right-side action group
- **Layout:** inline flex row, `gap: 8px`, pill radius `999px`, transparent by default, glass on scroll
- **Visibility:** always visible
- **Scroll behavior:** color changes from white to brand in scrolled light mode

### 6) `.header-crm-toggle`
- **Selector:** `.header-crm-toggle`
- **Purpose:** optional CRM icon link
- **Layout:** inline icon button with notification dot
- **Visibility:** only when user is logged in and authorized
- **Scroll behavior:** inherits cluster styling
- **Dependency note:** visibility depends on CRM capability functions and reminder count helper

### 7) `.header-search-toggle`
- **Selector:** `.header-search-toggle`
- **Purpose:** link to property archive
- **Layout:** inline icon button
- **Visibility:** always visible
- **Scroll behavior:** inherits cluster styling

### 8) `.header-menu-toggle`
- **Selector:** `.header-menu-toggle`
- **Purpose:** hamburger trigger
- **Layout:** `<label for="nav-toggle">`, not a `<button>`
- **Visibility:** always visible on all breakpoints
- **Scroll behavior:** inherits cluster styling

### 9) `.offcanvas-nav`
- **Selector:** `.offcanvas-nav`
- **Purpose:** slide-in main menu panel
- **Layout:** fixed right panel, `width: 75%`, `max-width: 900px`, full height, transforms from `translateX(100%)`
- **Visibility:** hidden by transform until `body.is-nav-open`
- **Breakpoint behavior:** becomes `width: 100%` at `<= 768px`

### 10) `.offcanvas-top`
- **Selector:** `.offcanvas-top`
- **Purpose:** top row inside panel with logo and close control
- **Layout:** horizontal flex, spaced apart
- **Visibility:** only inside open off-canvas

### 11) `.offcanvas-menu`
- **Selector:** `.offcanvas-menu`
- **Purpose:** main nav list from `main_menu_v1`
- **Layout:** vertical list with bordered top-level links and accordion submenus
- **Visibility:** only inside open off-canvas
- **Breakpoint behavior:** typography slightly adjusted at `<= 480px`

### 12) `.offcanvas-main-right`
- **Selector:** `.offcanvas-main-right`
- **Purpose:** user panel + “Message from our Director”
- **Layout:** second column beside menu on wider screens; stacked below menu under `900px`
- **Visibility:** always present in panel, content varies by auth state

### 13) `.offcanvas-contact`
- **Selector:** `.offcanvas-contact`
- **Purpose:** bottom contact/social/action block
- **Layout:** three-column grid on large widths; one column under `800px`
- **Visibility:** always present in panel
- **Positioning:** `margin-top: auto`, so it sits at the panel bottom after main content

### 14) `.offcanvas-backdrop`
- **Selector:** `.offcanvas-backdrop`
- **Purpose:** overlay behind drawer
- **Layout:** full-screen fixed layer, initially `opacity: 0` and `pointer-events: none`
- **Visibility:** activated when `body.is-nav-open`

---

## Behaviour audit

### Header scroll behaviour
- **Selector/class:** `#site-header.is-scrolled`
- **JS source:** `js/main.js`
- **Trigger:** `window.scrollY > 12`
- **Effect:** toggles `.is-scrolled`; CSS switches logo/icon clusters from transparent white to glass + brand color in light mode

### Hamburger trigger selector
- **Trigger selector:** `.header-menu-toggle`
- **Markup type:** `<label for="nav-toggle">`
- **Behavioral note:** JS binds click listener to the label and toggles `body.is-nav-open`

### Open/close classes added
- **Theme nav open class:** `body.is-nav-open`
- **Submenu class:** `.is-open` on `li.menu-item-has-children`
- **Scrolled header class:** `#site-header.is-scrolled`

### Overlay selector
- **Selector:** `.offcanvas-backdrop`
- **Open state styling:** `body.is-nav-open .offcanvas-backdrop`
- **Close interaction:** clicking overlay removes `body.is-nav-open`

### Focus management
- **Evidence found:** skip link styling exists
- **Evidence not found:** no focus trap, no initial focus sent into drawer, no explicit focus return to trigger after close in `main.js`
- **Conclusion:** focus management is minimal and should be re-created more robustly in plugin code rather than copied exactly

### Escape key support
- **Implemented:** yes
- **JS:** global `keydown` listener closes nav on `Escape`

### Body scroll lock
- **Implemented:** no evidence for the theme off-canvas
- `main.js` only toggles `body.is-nav-open`, and the visible CSS rules for that class only move the drawer and overlay; no `overflow: hidden` rule was found for `body.is-nav-open`

### Submenu expand/collapse handling
- **Trigger selectors:** `.offcanvas-menu .menu-item-has-children > a`
- **Behavior:** click is prevented; parent `li` toggles `.is-open`
- **Implication:** parent items with children act as accordion toggles, not navigable links

### Breakpoint where desktop switches to mobile/off-canvas
There is **no desktop inline-nav breakpoint** in this implementation. The hamburger/off-canvas pattern is always used. Only the panel dimensions/layout change:
- panel width to `100%` at `<= 768px`
- internal content stacks at `<= 900px`
- contact block stacks at `<= 800px`
- icon/logo size reduction at `<= 640px`

### Accessibility observations
- Good:
  - skip link present
  - `aria-label` on some controls
  - `aria-hidden="true"` on backdrop
  - `Escape` close support
- Weak:
  - hamburger is a `<label>`, not a semantic `<button>`
  - no `aria-expanded` state updates on theme nav trigger
  - no focus trap / focus return
  - submenu parent links become non-navigable via `preventDefault()`

### Important oddity: legacy `#nav-toggle`
The markup includes a hidden checkbox `#nav-toggle`, and both open/close controls are labels targeting it, but the actual drawer visibility is driven by `body.is-nav-open`, not by `#nav-toggle:checked`. I did not find any CSS or JS consuming the checkbox state beyond the labels. That makes the checkbox effectively legacy/redundant in the current implementation.

---

## CSS strategy audit

### Design tokens / CSS variables used by header layer
Defined in `:root`:
- colors: `--text`, `--text-soft`, `--inverse`, `--bg`, `--bg-soft`, `--brand`, `--accent`
- radii: `--radius-xs` … `--radius-2xl`
- spacing: `--space-2xs` … `--space-xl`
- glass: `--glass-bg`, `--glass-bg-soft`, `--glass-bg-strong`, `--glass-blur-sm/md/lg`, `--glass-saturate`, `--glass-border-soft/strong`, `--glass-shadow-soft/strong`
- container: `--container`
- breakpoints: `--bp-xs` … `--bp-xxl`
- header heights: `--header-height`, `--header-height-mobile`

### Font families and weights
- `font-family: 'Montserrat'`
- weights declared: `400`, `700`, `800`

### Spacing scale
- `4px`, `8px`, `12px`, `20px`, `32px`, `48px` in root variables

### Border radius
- Global radius scale exists, but header uses:
  - `var(--radius-md)` for `.site-branding`
  - `999px` for `.header-icons`
  - `var(--radius-lg)` for favourites summary

### Border colours
- top-level off-canvas items: `rgba(255,255,255,0.2)` border bottom
- submenu rail: `rgba(255,255,255,0.1)`
- favourites summary: `1px solid #ffffff`
- glass borders: `--glass-border-soft`, `--glass-border-strong`

### Box shadows
- glass shadow tokens:
  - `0 4px 10px rgba(15, 23, 42, 0.18)`
  - `0 10px 30px rgba(15, 23, 42, 0.35)`
Used by scrolled logo/icons cluster glass effect

### Z-index layers
- `#site-header`: `1000`
- `.offcanvas-backdrop`: `9997`
- `.offcanvas-nav`: `9998`

### Transition timing
- header cluster transitions: `0.35s ease`
- drawer transform: `.45s cubic-bezier(.25,.1,.25,1)`
- overlay opacity: `.35s ease`
- submenu transitions: `.3s ease`
- submenu chevron rotation: `.25s ease`

### Responsive breakpoints actually used in header/off-canvas
- `<= 640px`: icon/logo sizing
- `<= 768px`: drawer becomes full-width
- `<= 900px`: main off-canvas content stacks
- `<= 800px`: bottom contact area stacks
- `<= 480px`: menu typography slightly increases

### Rules tightly coupled to theme wrappers
These are tightly coupled and should **not** be copied blindly:
- `#site-header ...` global selectors
- `.footer-social-link` reuse inside off-canvas
- `.btn`, `.btn--solid`, `.btn--green`, `.btn--black`
- global `.icon`
- body class open-state `body.is-nav-open`
- helper-provided logo output and `main_menu_v1`
- runtime expectation that `icons.svg` is inlined in footer and rewritten by JS

### Rules that could be ported safely with namespacing
- visual proportions of logo cluster and icon cluster
- scrolled glass treatment
- off-canvas panel width, padding, color palette
- two-column-to-one-column panel layout
- menu typography and accordion visuals
- backdrop opacity / transform transitions

---

## Portability / risk assessment

### What can be copied nearly verbatim
These are mostly presentational and structurally portable:
- off-canvas panel layout proportions
- color system (`--brand`, white-on-brand panel)
- spacing and radius choices
- glass-on-scroll treatment
- menu/submenu visual styling
- bottom contact/social layout

### What should be re-created, not copied literally
These depend on theme-specific wrappers or runtime assumptions:
- raw selectors like `#site-header`, `.header-icons`, `.offcanvas-nav`
- button classes `.btn--solid`, `.btn--green`, `.btn--black`
- logo helper `pera_get_site_logo_markup()`
- SVG sprite footer injection + `main.js` sprite rewrite behavior
- `wp_nav_menu()` output assumptions around theme menu location and plugin-injected CRM item

### What must be isolated under a Peracrm namespace
Strong candidates:
- all header wrappers
- off-canvas open state class
- overlay class
- menu item and submenu selectors
- icon sizing classes
- any “glass scrolled” modifier class

Reason: the plugin already uses `#site-header.peracrm-shell-header`, `.crm-nav-shell`, `.crm-side-nav*`, and route body classes; global theme selectors would collide if copied directly.

### What is dangerous to copy because it relies on theme JS/runtime
- `<use href="...icons.svg#...">` pattern without also porting the sprite strategy
- `<label for="nav-toggle">` hidden-checkbox trigger pattern
- accordion logic that hijacks parent links
- relying on `body.is-nav-open` globally
- relying on theme helpers / auth-specific CRM icon conditions

### Accessibility risk if copied as-is
High:
- trigger is not a semantic button
- no focus trap
- no focus return
- no `aria-expanded` synchronization
- submenu parents lose navigation

---

## High-level comparison with current plugin temporary header

### Where the plugin header differs structurally
The plugin shell header is currently **minimal**:
- left logo
- right “Menu” button on smaller screens
- no search icon
- no CRM icon in the same pattern as the live site
- no theme-style off-canvas content block

The plugin’s current menu system is also different:
- desktop shows a sticky side nav
- mobile shows a right-side drawer
- it is task/CRM-utility focused, not a clone of the main site off-canvas structure

### Functional requirements that must be preserved in CRM
From the current plugin shell/runtime:
- plugin independence from theme CSS/JS on CRM routes
- CRM route body classing
- current CRM route rendering path
- CRM nav open/close JS
- existing CRM routing URLs
- existing accessibility hooks already present in plugin nav
- theme assets are intentionally dequeued on CRM routes

### What should remain plugin-specific even if styling is matched
- CRM route rendering and shell ownership
- CRM navigation items and route logic
- plugin JS open/close controller
- plugin body class/open-state class
- plugin-controlled asset loading
- plugin accessibility behavior

---

## Recommended “copy vs re-create” list

### Copy / mimic visually
- sticky transparent header shell
- left logo sizing and spacing
- right-side icon spacing
- scrolled glass treatment
- dark blue off-canvas panel palette
- panel padding and two-column layout
- submenu accordion visual affordance
- bottom contact/social composition

### Re-create functionally in plugin-owned code
- hamburger button markup using real `<button>`
- plugin-scoped open state class
- overlay and drawer toggle logic
- `aria-expanded`, `aria-controls`, focus return, focus trap
- body scroll locking
- submenu controls that preserve link semantics where needed
- icon strategy that does not require theme footer sprite runtime

### Do not copy verbatim
- `header.php` markup wholesale
- hidden checkbox `#nav-toggle`
- `.btn*` theme utility coupling
- global `#site-header` and `.offcanvas-*` selectors
- reliance on `main.js`
- reliance on `pera_get_site_logo_markup()`
- reliance on `main_menu_v1` being rendered by the theme runtime inside CRM

---

## Bottom-line conclusion

The current live theme header is a **child-theme-owned, non-Elementor, non-inline-nav header**: sticky logo + action icons, with the full primary nav living inside a branded right-side off-canvas drawer. Its appearance is mostly in `css/main.css`, and its interactions are mostly in `js/main.js`. For Peracrm parity, the safe path is to **re-create this look and motion in plugin-owned namespaced markup/CSS/JS**, while avoiding any dependence on:
- theme `header.php`
- theme `main.css`
- theme `main.js`
- theme SVG sprite runtime
- theme global selectors

## Do not implement yet

This is an audit only. No implementation work is included in this document.
