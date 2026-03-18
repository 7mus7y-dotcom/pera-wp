# CRM theme template cleanup

## Summary

Removed the remaining legacy theme CRM presentation files that are no longer selected by the active plugin CRM router:

- `wp-content/themes/hello-elementor-child/page-crm.php`
- `wp-content/themes/hello-elementor-child/page-crm-new.php`
- `wp-content/themes/hello-elementor-child/page-crm-client.php`
- `wp-content/themes/hello-elementor-child/page-crm-pipeline.php`
- `wp-content/themes/hello-elementor-child/parts/crm-header.php`

## Why this cleanup is safe

The active `/crm/*` route ownership is plugin-side:

- `wp-content/plugins/peracrm/inc/frontend/routing.php` registers the `/crm/`, `/crm/new/`, `/crm/client/{id}/`, `/crm/clients/`, `/crm/leads/`, `/crm/tasks/`, `/crm/pipeline/`, `/crm/whatsapp-logs/`, and `/crm/email-logs/` rewrite rules.
- `wp-content/themes/hello-elementor-child/inc/crm-router.php` is now only a compatibility shim and explicitly states that the plugin owns CRM router hooks.
- Current plugin views live under `wp-content/plugins/peracrm/inc/views/` and include plugin-owned page templates and CRM header partials.

Result: no active theme route ownership remains for `/crm/*` in the current tree.

## Intentionally kept

These files remain because they are still valid theme↔plugin CRM integration points rather than dead presentation templates:

- `wp-content/themes/hello-elementor-child/inc/filter-for-admin-panel.php`
  - Keeps employee admin blocking exceptions for allowed CRM `admin-post.php` actions, including `peracrm_front_create_lead`.
- `wp-content/themes/hello-elementor-child/inc/crm-client-view.php`
  - Provides active helper, AJAX, and client-view support code still consumed by plugin CRM views.
- `wp-content/themes/hello-elementor-child/inc/enquiry-email-log.php`
  - Remains valid for CRM-aware email log integrations.
- `wp-content/themes/hello-elementor-child/inc/whatsapp-click-log.php`
  - Remains valid for CRM-aware WhatsApp log integrations.
- `wp-content/themes/hello-elementor-child/inc/whatsapp.php`
  - Remains valid for CRM-aware header/floating WhatsApp behavior.
- `wp-content/themes/hello-elementor-child/inc/portfolio-token.php`
  - Remains valid for business-flow portfolio token usage.

No plugin routing, plugin views, `inc/filter-for-admin-panel.php`, or `peracrm_front_create_lead` behavior was changed by this cleanup.

## Post-cleanup grep notes

Searches were re-run against the current tree after deleting the files.

- `page-crm.php`
  - No runtime file remains at `wp-content/themes/hello-elementor-child/page-crm.php`.
  - Remaining hits are documentation references only.
- `page-crm-new.php`
  - No runtime file remains at `wp-content/themes/hello-elementor-child/page-crm-new.php`.
  - Remaining hits are documentation references only.
- `page-crm-client.php`
  - No runtime file remains at `wp-content/themes/hello-elementor-child/page-crm-client.php`.
  - Remaining hits are documentation references only.
- `page-crm-pipeline.php`
  - No runtime file remains at `wp-content/themes/hello-elementor-child/page-crm-pipeline.php`.
  - Remaining hits are documentation references only.
- `parts/crm-header.php`
  - No runtime file remains at `wp-content/themes/hello-elementor-child/parts/crm-header.php`.
  - Remaining hits are documentation references and plugin-owned partial references only.

## Manual regression checklist

Verify these routes still render from plugin-owned CRM routing/views and preserve existing integrations:

- `/crm/`
- `/crm/new/`
- `/crm/client/{id}/`
- `/crm/clients/`
- `/crm/leads/`
- `/crm/tasks/`
- `/crm/pipeline/`
- `/crm/whatsapp-logs/`
- `/crm/email-logs/`

For regression testing, confirm:

- each route resolves successfully through the plugin router;
- the CRM shell/header/nav still renders from plugin-owned views;
- create-lead submission still works;
- client detail actions and timeline panels still work;
- portfolio token flows still work;
- WhatsApp and email log pages still load;
- CRM-aware WhatsApp/header behavior remains intact where expected.
