# CRM Icon Swap Audit

## Sprite asset and symbol
- Sprite file: `wp-content/themes/hello-elementor-child/logos-icons/icons.svg`
- Updated symbol id: `icon-users-group`
- Symbol viewBox is now `0 0 16 16` to match the Bootstrap `bi-person-workspace` source icon.

## CRM UI locations using the symbol
- Logged-in header CRM icon in `wp-content/themes/hello-elementor-child/header.php`
  - Uses `<use ...#icon-users-group>` inside `.header-crm-toggle`.
- Floating CRM icon on CRM routes in `wp-content/themes/hello-elementor-child/functions.php`
  - Uses `<use ...#icon-users-group>` inside `.crm-floating-toggle` output by `pera_floating_whatsapp_button()`.

## Template/partial updates
- No template wrapper/structure/class updates were needed.
- No CSS changes were required.
- Icon swap was completed by replacing the symbol path data only, preserving existing `<use>` references in both locations.
