# CRM header icon size match audit

## Header icon markup locations

- Header icon container: `wp-content/themes/hello-elementor-child/header.php`
  - CRM icon toggle: `.header-crm-toggle` using `#icon-users-group`
  - Search icon toggle: `.header-search-toggle` using `#icon-search`
  - Menu icon toggle: `.header-menu-toggle` using `#icon-bars`

## Measured icon sizing rules (desktop + mobile)

### Shared header icon base sizing

- Selector: `#site-header .header-icons .icon`
  - Desktop size: `30px × 30px`
  - Mobile override at `@media (max-width: 640px)`: `22px × 22px`

### Menu icon (`#icon-bars`)

- Markup selector: `.header-menu-toggle .icon`
- Size source:
  - Inherits from `#site-header .header-icons .icon`
  - No per-icon width/height override found in `main.css`, `property.css`, `slider.css`, or `crm.css`
- Effective size:
  - Desktop: `30px × 30px`
  - Mobile (`max-width: 640px`): `22px × 22px`

### Search icon (`#icon-search`)

- Markup selector: `.header-search-toggle .icon`
- Size source:
  - Inherits from `#site-header .header-icons .icon`
  - No per-icon width/height override found in `main.css`, `property.css`, `slider.css`, or `crm.css`
- Effective size:
  - Desktop: `30px × 30px`
  - Mobile (`max-width: 640px`): `22px × 22px`

## CRM header icon change

- Added targeted override for header CRM icon only:
  - `#site-header .header-icons .header-crm-toggle .icon { width: 16px; height: 16px; }`
  - Mobile mirror at same breakpoint (`max-width: 640px`):
    - `#site-header .header-icons .header-crm-toggle .icon { width: 12px; height: 12px; }`

This keeps the same wrapper element (`.header-crm-toggle`) layout/click area while matching the rendered visual scale of the menu/search icons.

## Floating CRM icon verification

- Floating CRM CTA remains controlled by:
  - `.crm-floating-toggle .icon { width: 32px; height: 32px; }` in `css/crm.css`
- The new header override is scoped under `#site-header .header-icons .header-crm-toggle .icon`, so the floating CRM icon is unaffected.

## Files changed

- `wp-content/themes/hello-elementor-child/css/main.css`
- `wp-content/themes/hello-elementor-child/docs/crm-header-icon-size-match.md`

## Mobile CRM header icon size update

Mobile CRM header icon size updated from 16px to 24px to match visual weight of other header icons. Selector scoped to #site-header .header-icons .header-crm-toggle .icon to avoid affecting floating CRM icon.
