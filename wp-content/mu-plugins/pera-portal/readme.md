# Pera Portal (MU-plugin scaffold)

This scaffold adds a staff-only portal shell under a dedicated MU-plugin, following the existing CRM security architecture.

## Structure
- `pera-portal.php` bootstraps constants and includes.
- `includes/` contains capability wrapper, ACF JSON wiring, CPT/REST placeholders, shortcode, enqueue logic, services, and helpers.
- `assets/src/` holds future source assets, while `assets/dist/` holds currently enqueued placeholders.
- `templates/portal-shell.php` renders a simple two-column shell for SVG + unit details.
- `acf-json/` is reserved for ACF Local JSON field groups for portal entities.

## Security model
Portal access checks stay **pure capability checks** and never include theme CRM routing/bootstrap files.

`pera_portal_user_can_access()` supports two modes via constants in `includes/config.php`:
- `PERA_PORTAL_ACCESS_MODE = 'reuse_crm'` (default): delegates to `peracrm_user_can_access_crm()` when available, with `manage_options` fallback.
- `PERA_PORTAL_ACCESS_MODE = 'dedicated_cap'` (optional future): uses `PERA_PORTAL_ACCESS_CAP` (default `access_pera_portal`) plus `manage_options` override.

This keeps default behavior unchanged while allowing future decoupling from CRM access.

## Next steps
1. Define ACF field groups for building/floor/unit and save into `acf-json/`.
2. Implement CPT registration details and REST routes.
3. Replace placeholder JS/CSS with interactive viewer bundle.
