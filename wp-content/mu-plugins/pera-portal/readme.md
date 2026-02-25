# Pera Portal (MU-plugin scaffold)

This scaffold adds a staff-only portal shell under a dedicated MU-plugin, following the existing CRM security architecture.

## Structure
- `pera-portal.php` bootstraps constants and includes.
- `includes/` contains capability wrapper, ACF JSON wiring, CPT/REST placeholders, shortcode, enqueue logic, services, and helpers.
- `assets/src/` holds future source assets, while `assets/dist/` holds currently enqueued placeholders.
- `templates/portal-shell.php` renders a simple two-column shell for SVG + unit details.
- `acf-json/` is reserved for ACF Local JSON field groups for portal entities.

## Security model
Portal access delegates to the existing CRM access helper (`peracrm_user_can_access_crm`) via `pera_portal_user_can_access()`.

## Next steps
1. Define ACF field groups for building/floor/unit and save into `acf-json/`.
2. Implement CPT registration details and REST routes.
3. Replace placeholder JS/CSS with interactive viewer bundle.
