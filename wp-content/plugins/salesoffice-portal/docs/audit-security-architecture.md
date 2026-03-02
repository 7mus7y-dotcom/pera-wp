# Salesoffice Portal Security & Architecture

## Standalone boundary

This plugin is intentionally standalone:

- no MU-plugin includes
- no theme includes
- no CRM delegation calls

## Access control

`so_portal_current_user_can_access()` is the only runtime access gate.

Default behavior:

- multisite super-admins allowed
- `manage_options` allowed
- optional `SO_PORTAL_ACCESS_CAP` allowed when `SO_PORTAL_ACCESS_MODE` is `dedicated_cap`

## REST and frontend

- REST namespace: `salesoffice-portal/v1`
- Frontend global: `window.SoPortalConfig`
- REST permission callback: `so_portal_current_user_can_access`

## Template and assets

Templates and assets are loaded only from `wp-content/plugins/salesoffice-portal`.
