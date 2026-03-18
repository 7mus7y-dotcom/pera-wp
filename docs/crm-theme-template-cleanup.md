# CRM theme template cleanup

## Summary

This cleanup concerns only the five legacy theme presentation files that previously backed front-end CRM pages:

- `wp-content/themes/hello-elementor-child/page-crm.php`
- `wp-content/themes/hello-elementor-child/page-crm-new.php`
- `wp-content/themes/hello-elementor-child/page-crm-client.php`
- `wp-content/themes/hello-elementor-child/page-crm-pipeline.php`
- `wp-content/themes/hello-elementor-child/parts/crm-header.php`

On the current branch, those five files are already absent from the checked-out tree. The current `/crm/*` runtime is plugin-owned, and no active runtime references to those five files remain.

## What this cleanup actually covers

This is presentation/template cleanup only.

It documents that the old theme CRM page templates and shared theme CRM header partial are no longer part of the active runtime path for `/crm/*` requests.

It does **not** mean that all theme↔plugin CRM integration is gone, and it should not be read as proof of full CRM decoupling from the theme.

## Why the five presentation files are safe to remove / are already absent

Current `/crm/*` runtime ownership is plugin-side:

- `wp-content/plugins/peracrm/inc/frontend/routing.php` owns the front-end CRM rewrites, query vars, template resolution, and `template_include` routing.
- Current CRM page views live under `wp-content/plugins/peracrm/inc/views/pages/`.
- The current shared CRM header is plugin-owned at `wp-content/plugins/peracrm/inc/views/partials/crm-header.php` and is rendered through the plugin view/partial loader, not theme `get_template_part('parts/crm-header')`.

Because of that:

- the five old theme presentation files are not active runtime owners;
- no active `/crm/*` page rendering depends on them;
- their removal is runtime-safe;
- on this branch, they are already no longer present in the working tree.

## What is still theme↔plugin integration and therefore out of scope

Broader theme↔plugin integration still exists elsewhere and remains out of scope for this cleanup.

Examples include:

- theme helper / integration files that still participate in current CRM-related runtime behavior;
- theme-owned header CRM button / overdue reminder badge behavior;
- theme-owned public client portal pages and portfolio token flows;
- other theme assets or integration points that plugin-side CRM views still rely on.

Those broader integrations should be audited and migrated separately. This cleanup should not be described as removing all theme CRM coupling.

## Post-cleanup grep / audit note

Post-cleanup searches should show:

- no runtime file exists at the five legacy theme presentation paths listed above;
- remaining references to those filenames are documentation-only or historical;
- active runtime ownership for `/crm/*` points to plugin routing and plugin view files instead.

If future audits are run, they should verify current runtime code paths rather than relying on older migration notes.

## Merge recommendation

Safe to merge for the five presentation files.

However, this should be described narrowly as presentation cleanup only. It is **not** proof that all theme↔plugin CRM integration has been removed or that the CRM front end is fully theme-independent.
