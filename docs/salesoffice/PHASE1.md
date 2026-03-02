# Salesoffice Phase 1 (Core + CRM + Portal Isolation)

## What was created

- New MU core plugin: `wp-content/mu-plugins/salesoffice-core/`
  - Owns prefixed routes under `/so/*`.
  - Registers `salesoffice_*` query vars.
  - Handles access gating and login redirects.
  - Routes through `templates/app-shell.php`.
  - Enqueues shared primitives CSS (`assets/css/salesoffice-ui.css`).
- New CRM plugin: `wp-content/plugins/salesoffice-crm/`
  - Owns copied CRM templates, partials, data helpers, CSS/JS.
  - Renders into the salesoffice shell via `salesoffice_render_app`.
  - Removes direct theme header/footer/template part usage.
- New Portal plugin copy: `wp-content/plugins/salesoffice-portal/`
  - Copied from `wp-content/mu-plugins/pera-portal/`.
  - Adds plugin entrypoint `salesoffice-portal.php`.
  - Supports salesoffice route rendering while preserving legacy fallback behavior.

## Phase-1 routes

- CRM overview: `/so/crm/`
- CRM new lead: `/so/crm/new/`
- CRM client: `/so/crm/client/{id}/`
- CRM pipeline: `/so/crm/pipeline/`
- Portal: `/so/portal/`

## What remains untouched

- Theme CRM router and templates under `hello-elementor-child`.
- `wp-content/mu-plugins/peracrm/`.
- Existing MU portal (`wp-content/mu-plugins/pera-portal/`) remains for safety in Phase 1.

## Known limitations

- Rewrite flush is required after deploys that add/change routes (`wp rewrite flush --hard`).
- CRM copied templates are legacy markup and may need follow-up styling passes.
