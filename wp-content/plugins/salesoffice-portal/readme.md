# Salesoffice Portal

Standalone WordPress plugin for floor/unit portal rendering.

## Runtime design

- Plugin-local constants live in `includes/config.php` and use `SO_*` names.
- REST namespace is `salesoffice-portal/v1`.
- Frontend runtime config is exposed as `window.SoPortalConfig`.
- Access checks are plugin-local (`so_portal_current_user_can_access`).
- Templates/assets are loaded from this plugin directory only.

## REST endpoints

- `GET /wp-json/salesoffice-portal/v1/floor?floor_id=123`
- `GET /wp-json/salesoffice-portal/v1/floors?building_id=123`
- `GET /wp-json/salesoffice-portal/v1/units?floor_id=123`

## Shortcode

- `[so_portal]`
- Also configurable via `SO_PORTAL_SHORTCODE_TAG`.
