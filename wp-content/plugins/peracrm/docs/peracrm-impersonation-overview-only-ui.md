# PeraCRM impersonation switcher: overview-only UI

## Previous behavior
- The CRM impersonation switcher UI rendered from the shared CRM header partial, so it could appear across CRM subpages that reused that header.

## New behavior
- The impersonation switcher UI now renders only on the CRM overview/dashboard route at `/crm/`.
- The gated UI block includes the viewing-state banner, advisor dropdown, apply button, and reset button.
- Other shared CRM header content still renders normally on CRM subpages.

## Overview-route detection
- Route detection uses the canonical CRM query vars rather than raw URL string matching.
- `peracrm_is_crm_overview_route()` returns true only when the request is on a CRM route, has no CRM action, and the CRM view is empty or explicitly `overview`.
- This matches the `/crm/` dashboard route while excluding subpages such as `/crm/clients/`, `/crm/tasks/`, `/crm/pipeline/`, `/crm/client/{id}/`, and `/crm/new/`.

## Impersonation state remains unchanged
- This change only controls where the switcher UI renders.
- Active impersonation state is not cleared or disabled when navigating to CRM subpages.
- CRM reads/writes continue to use the existing impersonation behavior on subpages when an impersonation session was started from `/crm/`.

## Manual QA checklist

### Overview page
- [ ] `/crm/` shows the impersonation switcher
- [ ] switching view still works
- [ ] reset still works

### Subpages
- [ ] `/crm/clients/` does not show the switcher
- [ ] `/crm/client/{id}/` does not show the switcher
- [ ] `/crm/tasks/` does not show the switcher
- [ ] other CRM subpages do not show the switcher

### State behavior
- [ ] if impersonation was activated on `/crm/`, CRM scoping still remains active on subpages even though the switcher UI is hidden there
- [ ] returning to `/crm/` shows the switcher again with the current active state reflected correctly
