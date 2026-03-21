# PeraCRM impersonation hardening pass 2

## What was fixed in this pass

- Replaced broad target validation with an explicit `peracrm_user_is_impersonatable_target()` rule.
- Added `peracrm_get_impersonation_targets()` for reusable advisor selector data.
- Added `peracrm_is_request_on_crm_route()` so CRM-route detection no longer depends only on `pera_is_crm_route()` load timing.
- Added a real admin-only impersonation switcher UI to the shared CRM page header partial.
- Kept a visible active-state indicator showing the effective advisor and the signed-in admin.
- Removed inline impersonation banner styles from the theme header and moved shared styling into CSS files.
- Corrected read-side helper scoping so impersonation centers on the effective user instead of the real user's employee status.
- Continued to keep write-path actor attribution unchanged; this pass stays read-side focused.

## Explicit impersonation target rule now used

A valid impersonation target must:

1. exist,
2. not be the real logged-in user,
3. have CRM access, and
4. qualify as an explicit advisor/employee assignee.

Implementation detail:

- Prefer `pera_crm_user_is_employee()` when available.
- Fall back to `peracrm_user_is_employee_advisor()` if needed.
- Fall back again to an explicit `employee`-role-only check that rejects `manager` and `administrator` users.

This prevents manager/admin-only users from appearing as valid view targets unless they also explicitly qualify under the employee/advisor rule.

## Route detection approach

`peracrm_is_request_on_crm_route()` now:

- returns true immediately when `pera_is_crm_route()` is available and true,
- otherwise checks the CRM query var (`pera_crm`), and
- finally falls back to normalized request-path inspection for `/crm` and `/crm/*`.

It ignores wp-admin, AJAX, and REST requests.

## Where the switch UI lives

- Primary switch UI: `wp-content/plugins/peracrm/inc/views/partials/crm-header.php`
- Legacy/fallback active-state banner cleanup: `wp-content/themes/hello-elementor-child/header.php`

The shared CRM header partial now renders:

- a dropdown with `My view` plus valid advisor targets,
- a submit button to switch views, and
- a reset button when impersonation is active.

## Read helpers corrected to use effective-user-centered scoping

Updated helpers:

- `pera_crm_get_overdue_reminders_count_for_current_user()`
- `pera_crm_fetch_recent_activity()`
- `pera_crm_get_recent_leads()`
- `pera_crm_get_task_rows()`
- `pera_crm_get_tasks_view_data()`

These now resolve the effective CRM user first and base scoped reads on the effective user when impersonation is active.

## What remains intentionally for the next pass

- Write-path actor vs assignee handling beyond the already-preserved actor helper boundary.
- Broader audit of create/update/delete handlers for explicit `peracrm_get_actor_user_id()` use where needed.
- Any richer JS selector UX (searchable advisor picker, async switching, etc.).

## Manual QA checklist

### Admin

- [ ] On a CRM route such as `/crm/`, the admin sees the impersonation selector.
- [ ] Outside CRM routes, the admin does not see the selector.
- [ ] The selector lists only valid advisor/employee targets.
- [ ] Selecting an advisor and submitting switches the CRM read scope successfully.
- [ ] Choosing **Return to my view** clears impersonation successfully.
- [ ] While impersonating, the active indicator shows `Viewing as: <advisor>` and `Signed in as: <admin>`.
- [ ] Dashboard counts reflect the selected advisor scope.
- [ ] Recent task lists reflect the selected advisor scope.
- [ ] Recent activity reflects the selected advisor scope.
- [ ] Recent leads reflect the selected advisor scope.
- [ ] If a stored target becomes invalid/stale, it is cleared safely and the UI falls back to `My view`.

### Employee

- [ ] Employees do not see the impersonation selector.
- [ ] Employee own-view behavior remains unchanged.
- [ ] Employee dashboard/task/activity/lead views still scope to their own allowed clients.
