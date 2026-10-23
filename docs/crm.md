# PeraCRM architecture and safety

Scope: `wp-content/plugins/peracrm/`. `peracrm.php` defines plugin constants and loads `inc/bootstrap.php`, which wires schema/lifecycle, roles, the `crm_client` CPT, repositories/services, integrations, routes, notifications, admin screens, and frontend assets.

## Routes, views, and assets

`inc/frontend/routing.php` registers `/crm/`, `/crm/new/`, `/crm/client/{id}/`, `/crm/clients/` (with paged forms), `/crm/leads/`, `/crm/tasks/`, `/crm/pipeline/`, `/crm/performance/`, and log routes. It owns route POST handling, query vars, access checks, and template selection. `inc/frontend/view-loader.php` loads plugin views from `inc/views/`:

- `shell/` and `partials/crm-header.php` / `crm-side-nav.php` own the independent shell/navigation;
- `pages/` owns overview, create, client, pipeline, performance, and logs screens;
- `inc/frontend-data/` prepares view models.

`inc/frontend/assets.php` loads `assets/frontend/crm.css`, `crm.js`, fonts, slider, phone picker, and push runtime. CRM deliberately dequeues the theme's main CSS/JS, so CRM fixes belong to this plugin. See [crm-ui.md](crm-ui.md).

## Access and identity

`inc/roles.php` maintains `manager` and `employee` roles plus explicit CRM capabilities. Access is capability-based through `peracrm_user_can_access_crm()` and feature-specific capabilities; never replace these with a logged-in check or a role-name-only shortcut. Repositories/services must also enforce client/advisor scope, because hiding UI is not authorization.

Impersonation distinguishes:

- **actor/real user**: `peracrm_get_actor_user_id()` / `peracrm_get_real_user_id()`, used for authorization and audit attribution;
- **effective CRM user**: `peracrm_get_effective_crm_user_id()`, used for read-only advisor scoping and valid default assignment.

Only privileged CRM users may select valid employee/manager targets; administrators are not targets. Never change the WordPress current user to impersonate. A write while viewing as another advisor must still authorize and audit the real actor; only an explicitly validated/default assignee may use the effective user. Re-check every write path for this distinction.

## Write ownership

- Clients: `inc/services/client_service.php`, `inc/cpt.php`, and explicit frontend/admin handlers.
- Deals: `inc/repositories/deals.php` plus guarded handlers in frontend/admin actions.
- Activity/timeline: `inc/repositories/activity.php`, `inc/services/activity_service.php`, and `inc/activity*.php`.
- Notes: `inc/repositories/notes.php`.
- Tasks/reminders: `inc/repositories/reminders.php` and `inc/services/reminder_service.php`.
- Client-property links and favourites: their named repositories/modules.

Route/view files should collect and sanitize intent, then call the owning service/repository rather than adding direct SQL or duplicate metadata updates. Keep activity/audit emission paired with the authoritative write.

## Time, reminders, and push

Reminder `due_at` values are stored as WordPress-local MySQL datetimes and parsed/formatted with `wp_timezone()`; do not silently reinterpret them as UTC. Push is a separate boundary: subscription/digest/debug REST routes are in `inc/rest/push.php`, orchestration in `inc/services/push_service.php`, cron in `inc/cron/push_cron.php`, and browser/service-worker integration in `assets/frontend/crm-push.js`. Configuration belongs outside documentation and source-controlled values; a missing configuration must degrade safely rather than bypass access.

## Request security

- AJAX handlers: authenticated action where appropriate, nonce verification, capability and row/scope authorization, input sanitation, escaped/structured response.
- REST: a restrictive `permission_callback`; authenticated browser requests also verify the `wp_rest` nonce where the endpoint requires it. Public webhook endpoints are exceptional and must validate their provider signature/token in the callback.
- `admin-post`/form POSTs: method, login, feature capability, client scope, and action-specific nonce before mutation; redirect safely afterward.
- Parameterized repository queries only. Escape at output, not before storage.

## Regression checks

There is no isolated CRM test suite in the repository. Run `php -l` over changed PHP and use focused code/static searches for the action, nonce, capability, actor/effective-user, and repository path. Manually smoke-test as administrator/manager/employee and, when relevant, while impersonating. Confirm denied/out-of-scope access, successful write and timeline entry, redirects/AJAX errors, and push/reminder timezone behaviour.

Tables are a recurring risk: preserve `.crm-table-wrap` horizontal overflow, intentional nowrap action/identifier cells, wrapping narrative cells, and mobile workspace/card layouts. Test narrow and wide viewports plus light/dark system modes.
