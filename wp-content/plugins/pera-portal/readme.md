# Pera Portal (MU-plugin scaffold)

This scaffold adds a staff-only portal shell under a dedicated MU-plugin, following the existing CRM security architecture.

## Structure
- `pera-portal.php` bootstraps constants and includes.
- `includes/` contains capability wrapper, ACF JSON wiring, CPT/REST placeholders, shortcode, enqueue logic, services, and helpers.
- `assets/src/` holds future source assets, while `assets/dist/` holds currently enqueued viewer assets.
- `templates/portal-shell.php` renders a simple two-column shell for SVG + unit details.
- `acf-json/` stores ACF Local JSON field groups for building/floor/unit.

## Security model
Portal access checks stay **pure capability checks** and never include theme CRM routing/bootstrap files.

`pera_portal_user_can_access()` supports two modes via constants in `includes/config.php`:
- `PERA_PORTAL_ACCESS_MODE = 'reuse_crm'` (default): delegates to `peracrm_user_can_access_crm()` when available, with `manage_options` fallback.
- `PERA_PORTAL_ACCESS_MODE = 'dedicated_cap'` (optional future): uses `PERA_PORTAL_ACCESS_CAP` (default `access_pera_portal`) plus `manage_options` override.

This keeps default behavior unchanged while allowing future decoupling from CRM access.

## 2D MVP setup
1. Create a **Building** (`pera_building`) post and fill optional building fields.
2. Create a **Floor** (`pera_floor`) post:
   - Fill `floor_number`.
   - Select related `building` (optional for MVP).
   - Upload `floor_svg` (SVG file). If omitted, the plugin uses `data/fixtures/demo-floor.svg`.
3. Create **Unit** (`pera_unit`) posts:
   - Set `floor` to the related floor.
   - Fill `unit_code`, `unit_type`, `net_size`, `gross_size`, `price`, `currency`, `status`.
   - `unit_code` **must exactly match** an SVG element `id` (for example: `UNIT_A0101`).

## Shortcode usage
- Floor-specific view:
  - `[pera_portal floor="123"]`
- Legacy building attribute remains available:
  - `[pera_portal building="10" floor="123"]`

The front-end fetches:
- `GET /wp-json/pera-portal/v1/floor?floor_id=123`
- `GET /wp-json/pera-portal/v1/units?floor_id=123`

## Asset cache-busting (deploy-proof)
Portal JS/CSS assets in `assets/dist/` are enqueued with a version string derived from numeric mtimes when available.

- Primary version source: the asset file mtime (`filemtime`) for `portal-viewer.js`, `portal-viewer.css`, and `portal-compat.css`.
- Deploy/build guard: `assets/dist/.build` mtime is used as a numeric minimum build floor so URLs still change on deploys where mtimes may be preserved.
- Last-resort fallback: plugin version constant (`PERA_PORTAL_VERSION`, then `1.0.0`) only when neither asset nor build mtimes are available.

To stamp a new deploy/build version, run:

```bash
wp-content/plugins/pera-portal/tools/stamp-dist-build.sh
```

> If portal assets still appear stale after deploy, the issue is usually HTML/page cache (plugin cache, server cache, or CDN) serving old markup with old `?ver=` values.
> Purge the HTML cache layer (or exclude portal routes) so the new asset URLs are emitted.

### Deploy integration

After deploying the plugin files run:

`wp-content/plugins/pera-portal/tools/stamp-dist-build.sh`

or

`wp-content/plugins/pera-portal/tools/deploy-stamp-build.sh`

This ensures the `.build` mtime changes so asset URLs refresh even if deploy tools preserve mtimes.

