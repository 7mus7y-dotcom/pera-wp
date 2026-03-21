# PeraCRM impersonation phase 1

## What was added

- Added a plugin-level impersonation helper layer in `wp-content/plugins/peracrm/inc/impersonation.php`.
- Added canonical helpers for:
  - real logged-in user
  - actor user
  - impersonation permission checks
  - stored impersonation target lookup
  - effective CRM user resolution
  - safe set/clear behavior
- Persisted impersonation state in current user meta via `_peracrm_view_as_user_id`.
- Added authenticated `admin-post` actions for:
  - `peracrm_set_view_as_advisor`
  - `peracrm_clear_view_as_advisor`
- Added a passive CRM-only banner in the theme header that appears only when impersonation is active and provides a `Return to my view` action.

## Read-only helpers refactored in this phase

The following helpers now use `peracrm_get_effective_crm_user_id()` when impersonation is active, while preserving existing non-impersonated admin behavior as closely as possible:

- `pera_crm_get_overdue_reminders_count_for_current_user()`
- `pera_crm_fetch_recent_activity()`
- `pera_crm_get_recent_leads()`
- `pera_crm_get_task_rows()`
- `pera_crm_get_tasks_view_data()`

## Intentionally out of scope

- full advisor dropdown UI
- broad write-path refactors
- REST collection refactors
- client detail mutation changes
- pipeline write redesign
- saved-view redesign

## Deferred product decisions

- Whether managers should always be valid impersonation targets, or only employee/advisor-assigned users.
- Whether future write flows should default new reminders/records to the viewed advisor, require explicit advisor selection, or always keep current assignment behavior.
- Whether the final UI should keep URL-based advisor filters alongside impersonation state or consolidate around a single shell-level selector.
