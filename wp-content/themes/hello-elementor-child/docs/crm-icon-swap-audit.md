# CRM Icon Swap Audit

## Sprite asset and symbol
- Sprite file modified: `wp-content/themes/hello-elementor-child/logos-icons/icons.svg`
- Symbol id used: `icon-users-group`
- Symbol `viewBox` retained: `0 0 16 16`
- Symbol path replaced with Bootstrap `bi-person-lines-fill` path data using `fill="currentColor"`.

## CRM UI locations
- Logged-in header CRM icon: `wp-content/themes/hello-elementor-child/header.php`
  - Existing `<use ...#icon-users-group>` remains unchanged.
- Floating CRM icon on CRM pages: `wp-content/themes/hello-elementor-child/functions.php` (`pera_floating_whatsapp_button()`)
  - Existing `<use ...#icon-users-group>` remains unchanged.

## Template/partial update status
- No markup changes were required in either location.
- No CSS changes were required.
- Swap completed by symbol path update only.
