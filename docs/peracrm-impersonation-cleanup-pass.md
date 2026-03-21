# PeraCRM impersonation cleanup pass

## Theme layer cleanup
- Removed the duplicate impersonation banner rendering from `wp-content/themes/hello-elementor-child/header.php`.
- Removed the theme-only helper variables that were introduced solely to support that banner fallback.
- Removed impersonation banner and switcher CSS from `wp-content/themes/hello-elementor-child/css/main.css`.

## CRM single source of truth
- Confirmed the impersonation UI remains in `wp-content/plugins/peracrm/inc/views/partials/crm-header.php`.
- The CRM shared header partial remains the single source of truth for the current view indicator, advisor dropdown, switch action, and reset action.

## Styling ownership
- Confirmed impersonation UI styles remain owned by `wp-content/plugins/peracrm/assets/frontend/crm.css` only.
- No impersonation styles were moved back into the theme layer.

## Legacy theme CRM data
- Left `wp-content/themes/hello-elementor-child/inc/crm-data.php` untouched in this pass.
- That file remains legacy and out of scope for this cleanup.
