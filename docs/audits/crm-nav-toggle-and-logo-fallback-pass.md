# CRM Nav Toggle and Logo Fallback Pass

## Audit summary
This pass fixes two CRM shell issues in one surgical change set:
1. the mobile nav toggle was rendered inside the nav shell, which made its placement and visibility depend on the aside layout instead of the hero/header action area;
2. the CRM shell logo only checked the theme `custom_logo` attachment and fell straight to text when that attachment was unavailable, even though a branded SVG fallback existed in the theme.

## Root cause: wrong nav toggle placement and visibility
- The menu toggle was rendered directly inside `wp-content/plugins/peracrm/inc/views/partials/crm-side-nav.php` within `.crm-nav-shell`.
- The CSS made `.crm-side-nav__toggle` visible under `1024px`, but because the button lived with the nav shell it appeared in the content/aside context instead of the hero action area.
- The desktop and drawer nav markup were already split correctly, but the trigger ownership was not: the toggle belonged to the hero/header interaction model while the drawer markup belonged to the nav partial.
- The existing JS only queried a single toggle inside `[data-crm-nav]`, so moving the button required a light selector refactor to support a header-owned trigger and a nav-owned drawer.

## Root cause: logo text fallback appearing instead of the intended logo
- `wp-content/plugins/peracrm/inc/views/shell/header.php` only read `get_theme_mod( 'custom_logo' )` and rendered text when no attachment ID was available.
- The plugin did not own a fallback logo asset, so it had no plugin-safe image fallback path.
- The current branded fallback asset existed in the theme at `wp-content/themes/hello-elementor-child/logos-icons/pera-logo.svg`, which meant the plugin remained theme-coupled for any non-text fallback.

## Files changed
- `wp-content/plugins/peracrm/inc/views/partials/crm-header.php`
- `wp-content/plugins/peracrm/inc/views/partials/crm-side-nav.php`
- `wp-content/plugins/peracrm/inc/views/shell/header.php`
- `wp-content/plugins/peracrm/assets/frontend/crm.css`
- `wp-content/plugins/peracrm/assets/frontend/crm.js`
- `wp-content/plugins/peracrm/logos-icons/pera-logo.svg`
- `docs/audits/crm-nav-toggle-and-logo-fallback-pass.md`

## Implementation decisions
- Moved the pill-style mobile toggle into the CRM hero/header partial so it now lives with hero actions instead of inside the aside wrapper.
- Left desktop aside nav rendering and drawer nav rendering in `crm-side-nav.php` so nav items, active states, and role gating continue to come from the existing partial.
- Updated the drawer controller JS to bind to all `[data-crm-nav-toggle]` buttons on the page while still using the existing drawer, overlay, close button, and Escape-key behavior.
- Kept the desktop sticky aside nav in place and relied on responsive CSS to hide the hero toggle above `1024px` and hide the desktop aside below `1024px`.
- Copied `pera-logo.svg` into `wp-content/plugins/peracrm/logos-icons/` so the plugin now owns its fallback asset.
- Updated shell logo resolution so it prefers the theme custom-logo attachment image first, then uses the plugin-owned SVG fallback, and only then falls back to text.
- Kept icon usage and nav item generation intact to avoid regressions in CRM route behavior.

## Regression risks
- The hero layout now contains a mobile-only action button; if future hero variants add additional right-side actions, spacing may need a small follow-up adjustment.
- The nav JS now supports multiple toggles; any future toggle using `[data-crm-nav-toggle]` outside the CRM shell would also control the drawer unless scoped more narrowly.
- If a site expects a theme-provided non-attachment logo fallback before the plugin fallback, this pass intentionally changes that order to prioritize plugin independence.

## Test checklist
- [ ] On desktop widths above `1024px`, verify the sticky aside nav remains visible.
- [ ] On desktop widths above `1024px`, verify no pill-style menu toggle is visible in the hero or aside.
- [ ] On widths below `1024px`, verify the pill-style toggle appears in the CRM hero on the right side of the hero content.
- [ ] On widths below `1024px`, verify clicking the hero toggle opens the existing off-canvas drawer.
- [ ] Verify overlay click closes the drawer.
- [ ] Verify the drawer close button closes the drawer.
- [ ] Verify the Escape key closes the drawer.
- [ ] Verify Overview, Clients, Tasks, Pipeline, Create lead, WhatsApp logs, and Email logs still render according to role gating.
- [ ] Verify active nav states remain correct on each CRM route.
- [ ] Verify the shell uses the theme custom logo image when one exists.
- [ ] Verify the shell uses `wp-content/plugins/peracrm/logos-icons/pera-logo.svg` when no usable theme custom logo image exists.
- [ ] Verify text fallback appears only if neither image source is available.
- [ ] Verify there are no console errors on CRM routes.
- [ ] Verify CRM shell/header/nav spacing still looks correct in dark mode.
