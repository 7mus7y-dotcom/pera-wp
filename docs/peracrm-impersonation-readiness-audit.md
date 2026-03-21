# PeraCRM impersonation readiness audit

## Executive summary

### Overall readiness assessment

PeraCRM is **feasible for an admin-only “view as advisor” mode, but only with a deliberate helper-layer refactor** rather than a UI-only dropdown.

The codebase already separates some concerns in a few places:

- Access control is usually tied to the real logged-in WordPress user via `current_user_can()`, `is_user_logged_in()`, and `peracrm_admin_user_can_manage()`.
- Some admin screens already support an explicit advisor scope parameter (`advisor` / `advisor_id`) for reads.
- Some write paths already record `actor_user_id` explicitly in activity payloads.

However, identity handling is **inconsistent across modules**:

- Many dashboard/list/task queries scope data directly from `get_current_user_id()`.
- Some admin pages support explicit advisor scoping, while equivalent front-end pages do not.
- Several write paths use the current logged-in user both for authorization and attribution, which is correct for the actor but unsafe if later reused as an effective-view identity.
- A few analytics/count helpers fall back to global/unscoped SQL for admins, so counts could disagree with advisor-scoped lists if impersonation is introduced incompletely.

### Main risk areas

1. **Dashboard/task/count helpers** in `hello-elementor-child/inc/crm-data.php` mix employee-only per-user scoping with admin global fallback, so impersonation could produce mismatched counts/widgets/lists.
2. **Front-end client detail AJAX/admin-post mutations** use `get_current_user_id()` for both assignment checks and write attribution, so impersonation could accidentally attribute admin actions to the viewed advisor if changed naively.
3. **REST collection endpoints** in `peracrm/inc/rest.php` currently authorize based on the real user, but their collection queries are not advisor-scoped at all.
4. **Saved pipeline views** are stored per real user, but may contain advisor filters; this is a dual-context area that needs explicit design.
5. **Client-view access checks** correctly stay real-user based today, but reads/writes inside that surface do not have a formal real/effective/actor split.

### Refactor size assessment

- **Moderate refactor** if scope is limited to read-only impersonation first: header state, dashboard, leads, tasks, pipeline/work-queue, count widgets, and read endpoints.
- **Heavy refactor** if writes must also work while impersonating on day one: note creation, reminders, status moves, bulk actions, deal creation/update, reassignments, and activity/audit logging all need explicit actor/effective separation.

### Top 5 highest-risk code paths

1. `pera_crm_get_overdue_reminders_count_for_current_user()` and related task/dashboard helpers: admin fallback returns global data instead of advisor-scoped data. (`wp-content/themes/hello-elementor-child/inc/crm-data.php`)
2. `pera_crm_get_tasks_view_data()`: employee views scope to `get_current_user_id()`, but admin fallback queries all reminders. (`wp-content/themes/hello-elementor-child/inc/crm-data.php`)
3. `peracrm_handle_add_note()` / `peracrm_handle_add_reminder()` / `pera_crm_client_action_ajax()` note/reminder branches: authorization and attribution are both anchored to `get_current_user_id()`. (`wp-content/plugins/peracrm/inc/admin/actions.php`, `wp-content/plugins/peracrm/inc/frontend-data/crm-client-view.php`)
4. `peracrm_rest_get_client_ids_by_type()` and REST collections: collections are capability-gated but not advisor-scoped. (`wp-content/plugins/peracrm/inc/rest.php`)
5. `peracrm_handle_convert_to_client()` and bulk/stage activity logging: `owner_user_id` / `actor_user_id` are currently the real current user, which is probably correct for audit history but will be tempting to switch incorrectly during impersonation. (`wp-content/plugins/peracrm/inc/admin/actions.php`)

## Identity model to use

Future implementation should explicitly distinguish:

1. **Real logged-in user**
   - The actual WordPress session identity.
   - Must continue to drive authentication, capability checks, nonce context, and admin-only access.

2. **Effective CRM user**
   - The advisor/employee whose CRM data is being rendered.
   - Should drive read scoping for lists, counts, pipeline boards, reminders, and activity feeds when impersonation is active.

3. **Actor user**
   - The person who actually performed a write action.
   - Should be preserved for notes, reminder changes, status changes, deal mutations, import/audit logging, and any security-sensitive event trail.

**Recommendation:** introduce helper functions early, such as:

- `peracrm_get_real_user_id()`
- `peracrm_get_effective_crm_user_id()`
- `peracrm_get_actor_user_id()`
- `peracrm_is_impersonating_crm_user()`
- `peracrm_current_user_can_view_as_advisor()`

Do **not** blanket-replace `get_current_user_id()`. Access control and actor logging should usually stay real-user based.

## Findings table

| Area | File | Function / Endpoint | Current identity source | Classification | Recommendation | Risk | Notes |
|---|---|---|---|---|---|---|---|
| CRM shell/header | `wp-content/themes/hello-elementor-child/header.php` | CRM header button + overdue badge | `is_user_logged_in()`, `current_user_can()`, `pera_crm_get_overdue_reminders_count_for_current_user()` | Mixed / dual-context | Keep access gate real-user based; switch overdue badge to effective-user helper | High | Header entry may remain visible correctly for admin, but badge/count can disagree with advisor-scoped pages. |
| Access control / routing | `wp-content/plugins/peracrm/inc/frontend/routing.php` | `pera_crm_user_can_access()`, route guards, lead-create handler | `wp_get_current_user()`, `current_user_can()`, `get_current_user_id()` | Access control | Keep real-user based | Medium | Front-end CRM entry should still depend on the real admin session, not the advisor being viewed. |
| Access control / routing | `wp-content/themes/hello-elementor-child/inc/access-control.php` | wp-admin/admin-bar helpers | `wp_get_current_user()`, `get_current_user_id()` | Access control / Presentation | Keep real-user based | Low | Admin-bar and wp-admin access must never follow effective advisor context. |
| Access control / routing | `wp-content/themes/hello-elementor-child/inc/filter-for-admin-panel.php` | employee wp-admin block / allowed `admin-post` actions | `is_user_logged_in()`, `wp_get_current_user()`, `current_user_can()` | Access control | Keep real-user based | Medium | Impersonation must not weaken admin-post or wp-admin blocking logic. |
| Dashboard | `wp-content/themes/hello-elementor-child/inc/crm-data.php` | `pera_crm_get_overdue_reminders_count_for_current_user()` | `get_current_user_id()` | Data scoping | Convert to effective-user helper | High | Employee path scopes to advisor; admin path falls back to global count, causing likely widget mismatch. |
| Dashboard | `wp-content/themes/hello-elementor-child/inc/crm-data.php` | `pera_crm_fetch_recent_activity()` | `get_current_user_id()` + allowed client IDs | Data scoping | Convert to effective-user helper | High | Employee filtering is assignment-based; admin fallback returns global activity. |
| Dashboard | `wp-content/themes/hello-elementor-child/inc/crm-data.php` | `pera_crm_get_recent_leads()` | `get_current_user_id()` + allowed client IDs | Data scoping | Convert to effective-user helper | High | Without change, impersonating admin will still see admin/global recent leads logic. |
| Tasks/reminders | `wp-content/themes/hello-elementor-child/inc/crm-data.php` | `pera_crm_get_task_rows()` / `pera_crm_get_tasks_view_data()` | `get_current_user_id()` | Data scoping | Convert to effective-user helper | Critical | Tasks and overdue widgets are among the most visible scoping surfaces; admin fallback is unscoped SQL. |
| Clients list | `wp-content/themes/hello-elementor-child/inc/crm-data.php` | `pera_crm_get_allowed_client_ids_for_user()` | explicit `$user_id` argument | Data scoping | Reuse via effective-user helper | Medium | This is a strong candidate to become the canonical advisor-scope resolver. |
| Clients list | `wp-content/themes/hello-elementor-child/inc/crm-data.php` | `pera_crm_get_leads_view_data()` | `get_current_user_id()`, optional `advisor` query param | Mixed / dual-context | Needs dual real/effective handling | High | Current employee scoping is current-user based; admin advisor filter exists, but impersonation should not rely on raw GET alone. |
| Pipeline/deals | `wp-content/themes/hello-elementor-child/inc/crm-data.php` | `pera_crm_get_pipeline_view_data()` | `get_current_user_id()`, `current_user_can()`, `advisor` query param | Mixed / dual-context | Needs dual real/effective handling | High | Good starting point because reads already distinguish “manage all” vs scoped advisor, but helper layer is missing. |
| Admin pipeline page | `wp-content/plugins/peracrm/inc/admin/pages/pipeline.php` | `peracrm_render_pipeline_page()` | `current_user_can()`, `get_current_user_id()`, `advisor_id` view filters | Mixed / dual-context | Needs dual real/effective handling | High | Saved views belong to real user, but board data can be advisor-scoped. This is a product/UX decision area. |
| Work queue | `wp-content/plugins/peracrm/inc/admin/pages/work-queue.php` | `peracrm_render_work_queue_page()` | `current_user_can()`, `get_current_user_id()`, `advisor` query param | Mixed / dual-context | Needs dual real/effective handling | High | Similar to pipeline: admin can already choose advisor, suggesting a reusable pattern for impersonated reads. |
| Client detail | `wp-content/plugins/peracrm/inc/admin/pages/client-view.php` | `peracrm_render_client_view_page()` | `current_user_can()`, `get_current_user_id()` | Access control + Data scoping | Keep access real-user; add effective-user-aware read helpers later | High | Assignment check uses current real user for non-managers; read model inside page is not formally separated yet. |
| Client detail AJAX | `wp-content/plugins/peracrm/inc/frontend-data/crm-client-view.php` | `pera_crm_client_view_access_state()` | `current_user_can()`, `get_current_user_id()` | Access control | Keep real-user based | High | This is a critical guardrail; do not switch to effective advisor for authorization. |
| Client detail AJAX | `wp-content/plugins/peracrm/inc/frontend-data/crm-client-view.php` | `pera_crm_client_action_ajax()` | `get_current_user_id()` for note/reminder/status actions | Mixed / dual-context | Needs dual real/effective handling | Critical | Reads/writes happen in one endpoint family; actor must remain real user while list refreshes may need effective scope. |
| Admin-post writes | `wp-content/plugins/peracrm/inc/admin/actions.php` | `peracrm_handle_add_note()` | `get_current_user_id()` | Attribution / actor logging | Keep actor real-user based; authorization may need effective-view awareness only if product requires it | Critical | Today note author is the real user, which is likely correct. Dangerous if later replaced with effective advisor. |
| Admin-post writes | `wp-content/plugins/peracrm/inc/admin/actions.php` | `peracrm_handle_add_reminder()` | `get_current_user_id()` + assigned advisor meta | Mixed / dual-context | Needs dual handling | Critical | Actor is real user; reminder assignee may be assigned advisor. In impersonation, admin may expect reminder creation “for advisor X” while still logged as admin. |
| Pipeline writes | `wp-content/plugins/peracrm/inc/admin/actions.php` | `peracrm_handle_pipeline_move_stage()` / bulk action | `get_current_user_id()`, `actor_user_id` | Attribution / mixed | Keep actor real-user based; add explicit effective scope validation if needed | Critical | Current activity payloads already use `actor_user_id`; this is the correct audit direction to preserve. |
| Reassignment | `wp-content/plugins/peracrm/inc/admin/actions.php` | `peracrm_handle_reassign_client_advisor()` | real current user for access; no explicit actor in log payload | Mixed / dual-context | Needs product decision before implementation | Medium | Reassignment during impersonation could be confusing: is admin acting as viewer or as admin manager? |
| Deals | `wp-content/plugins/peracrm/inc/admin/actions.php` | `peracrm_handle_convert_to_client()`, create/update deal | `get_current_user_id()` or submitted `owner_user_id` | Attribution / mixed | Needs dual handling | High | `owner_user_id` is business ownership, not necessarily actor. Must not conflate with admin actor. |
| Activity/logging | `wp-content/plugins/peracrm/inc/activity.php` | activity log/throttle helpers | `get_current_user_id()` in fallback throttle only | Attribution / presentation | Mostly keep real-user based | Low | This file is more about client-portal/user activity than advisor impersonation, but avoid changing fallback current-user logic casually. |
| REST endpoints | `wp-content/plugins/peracrm/inc/rest.php` | `/peracrm/v1/leads`, `/clients`, `/deals` | capability checks via real user; collection queries unscoped | Access control + Data scoping | Keep permission callback real-user; add effective-user scope to collection queries | Critical | Otherwise impersonated UI using REST will leak global/admin data. |
| Push REST | `wp-content/plugins/peracrm/inc/rest/push.php` | push config/debug/subscribe routes | `get_current_user_id()`, `requested_user_id` | Mixed / dual-context | Keep separate from CRM impersonation for now | Medium | Push debug already has its own acting/target model; do not conflate with CRM advisor impersonation unless intentionally unified. |
| Theme / MU dependencies | N/A (`wp-content/mu-plugins` absent in this repo snapshot) | N/A | N/A | Theme or mu-plugin dependencies | Re-verify in production/server checkout | Medium | Search reported no `wp-content/mu-plugins` directory locally, so deployment may contain additional scope filters not present here. |

## Grouped findings by module

## CRM shell/header

### Current identity resolution

- Header CRM visibility is controlled by `is_user_logged_in()` plus real-user capabilities in `header.php`.
- The overdue badge uses `pera_crm_get_overdue_reminders_count_for_current_user()`, which derives scope from `get_current_user_id()`.

### Impersonation safety

- **Partially safe**.
- The visibility gate should remain tied to the real admin.
- The badge/count is **not impersonation-safe** because it follows current-user/global logic rather than a formal effective advisor identity.

### Required future changes

- Keep header access control real-user based.
- Add a dedicated effective advisor state source in the shell/header.
- Update the badge/count to use effective-user scoping when impersonation is active.
- Add a visible “Viewing as Advisor X” indicator and reset control.

### Inconsistencies

- Header badge can diverge from tasks/dashboard page contents if only page queries are updated later.

## Dashboard

### Current identity resolution

Dashboard data in `inc/crm-data.php` heavily uses `get_current_user_id()` and `pera_crm_user_is_employee()`:

- employees get advisor-scoped results;
- admins/managers often fall back to global queries.

This affects:

- overdue reminder count;
- recent activity;
- recent leads;
- dashboard task widgets.

### Impersonation safety

- **Not safe**.
- The employee-vs-admin branching assumes the real user identity is also the desired data scope.

### Required future changes

- Centralize scope resolution around effective CRM user.
- Keep capability checks real-user based.
- Ensure widget counts and list previews call the same scope helper.

### Inconsistencies

- Admin global fallback can produce more data than advisor-scoped list views.
- Some helpers use allowed client IDs; others use raw reminders SQL fallback.

## Clients list

### Current identity resolution

- `pera_crm_get_allowed_client_ids_for_user( $user_id )` is the main assignment-based scope helper.
- `pera_crm_get_leads_view_data()` uses `get_current_user_id()` for employee scoping, but also accepts `advisor` filter input.

### Impersonation safety

- **Partially safe**.
- The explicit helper that accepts a user ID is promising.
- The higher-level view builder still assumes current user equals effective scope.

### Required future changes

- Reuse `pera_crm_get_allowed_client_ids_for_user()` behind an effective-user wrapper.
- Stop reading advisor scope directly from `$_GET` as the primary source once impersonation state exists.
- Decide whether admin dropdown state should override, replace, or coexist with current `advisor` URL filters.

### Inconsistencies

- Current URL-based advisor filter works for admins, but employee scoping is hardcoded to current user.
- Search/filter/pagination are all computed after initial scope resolution, so any missed scope helper will leak records.

## Client detail

### Current identity resolution

Both admin-page and front-end/AJAX client view flows:

- gate access with real-user capabilities (`current_user_can( 'edit_post' )`, `manage_options`, `peracrm_manage_all_clients`);
- for non-managers, require assigned advisor to equal `get_current_user_id()`.

### Impersonation safety

- **Access control is mostly safe today** because it is real-user based.
- **Read/write behavior is not fully impersonation-ready** because there is no formal effective-user abstraction inside the client-view surface.

### Required future changes

- Preserve real-user access checks.
- Decide whether admin viewing advisor X should be allowed to open any client visible to advisor X, even if not directly assigned to admin. Likely yes, but that is a deliberate dual-context rule.
- Separate detail-page rendering scope from mutation actor identity.

### Inconsistencies

- Similar rules exist in both plugin admin-page and front-end AJAX/client-view code, increasing drift risk.

## Tasks/reminders

### Current identity resolution

Tasks/reminders are one of the strongest current-user-coupled areas:

- dashboard/task rows use `get_current_user_id()`;
- note/reminder creation checks assignment against `get_current_user_id()`;
- reminder status changes pass `get_current_user_id()` into authorization/update helpers.

### Impersonation safety

- **High risk / not safe** if changed naively.

### Required future changes

- Reads: switch to effective-user scope.
- Writes: keep actor as real user.
- Add explicit reminder assignee semantics where needed.
- Review whether “view as advisor” should allow the admin to create reminders assigned to the viewed advisor by default or require explicit selection.

### Inconsistencies

- Some flows derive reminder assignee from the client’s assigned advisor.
- Some bulk actions let admins override reminder advisor.
- Some note/reminder flows are admin-post, others are AJAX, so both paths must be updated consistently.

## Pipeline/deals

### Current identity resolution

- Admin pipeline/work-queue pages already accept advisor filters.
- Pipeline saved views are stored under the real current user.
- Stage move and bulk actions use real current user for authorization and `actor_user_id` logging.
- Deal ownership uses `owner_user_id`, which is a business ownership field rather than an actor field.

### Impersonation safety

- **Read side: moderately adaptable** because advisor filtering already exists.
- **Write side: high risk** because actor vs owner vs viewed advisor must be kept distinct.

### Required future changes

- Reuse advisor filter mechanics as the first implementation path for effective-user reads.
- Keep saved views associated with the real user unless product decides otherwise.
- Preserve `actor_user_id` for stage/bulk activity logs.
- Introduce explicit actor metadata for reassign/deal mutations where missing.

### Inconsistencies

- Pipeline board/work queue already support admin advisor selection, but dashboard/leads/tasks do not use the same abstraction.

## Activity/logging

### Current identity resolution

- Activity writes generally accept payloads from callers.
- Several mutation handlers already send `actor_user_id => get_current_user_id()`.
- Some reassignment logs only store `from`/`to` and omit explicit actor info.

### Impersonation safety

- **Mixed**.
- The presence of `actor_user_id` in some handlers is good.
- Missing actor metadata in other mutations will become more problematic once viewing-as is introduced.

### Required future changes

- Standardize actor metadata on all write logs.
- If useful, also record effective-view advisor context separately for audit/debugging, but do not overwrite actor.

### Inconsistencies

- Some write paths log actor explicitly; others rely on business fields or omit actor entirely.

## Analytics/widgets

### Current identity resolution

- Counts and widgets are spread across dashboard helpers, task helpers, activity health helpers, and pipeline/work queue rollups.
- Several count paths use different sources and fallbacks depending on whether the current user is treated as employee/admin.

### Impersonation safety

- **Not safe until unified**.

### Required future changes

- Put all widget/count queries behind a shared effective-scope resolver.
- Regression-test counts against the corresponding list/detail surfaces.

### Inconsistencies

- Overdue counts, task tables, and pipeline/work queue may currently answer slightly different scope questions even before impersonation.

## REST/AJAX endpoints

### Current identity resolution

- REST permission callbacks use the real logged-in user.
- Core REST collection queries in `inc/rest.php` are not advisor-scoped.
- Client detail AJAX endpoints use real-user access checks and current-user attribution.

### Impersonation safety

- **Access control mostly safe, data scope not safe**.

### Required future changes

- Keep REST/AJAX permission callbacks real-user based.
- Pass effective advisor context into read endpoints explicitly from the server-side impersonation state.
- Keep actor identity real-user based for writes.

### Inconsistencies

- Some front-end surfaces are rendered server-side from theme helpers; others hit plugin AJAX or REST handlers.
- Any partial migration will cause the UI to disagree with itself.

## Access control / routing

### Current identity resolution

- Routing and wp-admin access are built around the real WordPress session.
- Employee/admin barriers in theme routing/admin filters are real-user checks.

### Impersonation safety

- **Should remain unchanged in principle**.

### Required future changes

- Introduce impersonation state only after access control passes.
- Never evaluate `current_user_can()` against the effective advisor identity.

### Inconsistencies

- None critical here; main risk is accidental future misuse, not current behavior.

## Theme or mu-plugin dependencies

### Current identity resolution

- This repo snapshot contains theme-based CRM data/access helpers.
- The local checkout does **not** contain `wp-content/mu-plugins`, though some code references MU-style helpers such as `peracrm_with_target_blog()` and `peracrm_user_can_access_crm()`.

### Impersonation safety

- **Unknown until server parity is confirmed**.

### Required future changes

- Re-run this audit in the server/production-like checkout if MU plugins exist there.
- Confirm whether any production-only filters already override allowed client IDs, capability checks, or target blog behavior.

### Inconsistencies

- Local absence of MU plugins means deployment could still hide important scope logic outside this repository snapshot.

## Highest-risk issues

1. **Reads still showing admin/global data instead of advisor data**
   - Dashboard overdue counts.
   - Dashboard recent activity.
   - Dashboard recent leads.
   - Tasks view and SQL reminder fallbacks.
   - REST collection endpoints.

2. **Writes incorrectly attributed to the impersonated advisor**
   - Notes currently store the current real user as author; this is probably correct and must be preserved.
   - Stage moves already log `actor_user_id`; keep that pattern.
   - Deal ownership (`owner_user_id`) must not be confused with actor identity.

3. **Access control accidentally following impersonated permissions**
   - Client-detail assignment checks and page access must remain based on the real logged-in admin’s capabilities.
   - wp-admin blocking and admin-bar access must stay real-user based.

4. **Counts/widgets disagreeing with lists**
   - Header overdue badge vs tasks page.
   - Dashboard widgets vs advisor-filtered pipeline/work queue.
   - REST-driven surfaces vs server-rendered theme helpers.

5. **Endpoints ignoring impersonation state**
   - REST `/leads`, `/clients`, `/deals` currently have no advisor scoping.
   - AJAX/admin-post note/reminder handlers currently assume real current user for both auth context and action identity.

## Recommended implementation order

1. **Introduce helper layer for real/effective identity separation**
   - Build canonical helpers for real user, effective CRM user, actor user, and impersonation state.

2. **Update read-only scoping paths first**
   - Dashboard, leads, tasks, pipeline board, work queue, client detail read models.

3. **Update dashboard/widgets/counts**
   - Ensure every widget/badge/count uses the same effective-user scoping as its corresponding list.

4. **Update REST/AJAX endpoints**
   - Keep permission callbacks real-user based.
   - Move read queries to effective-user scoping.

5. **Review write attribution and activity logging**
   - Preserve actor = real user.
   - Add missing actor metadata where absent.
   - Decide where business owner/assignee should follow viewed advisor vs explicit form input.

6. **Add UI switch in shell header**
   - Admin-only advisor dropdown in CRM shell.

7. **Add visible “Viewing as X” indicator + reset action**
   - Make the dual context explicit on every CRM screen.

8. **Regression test all major CRM screens**
   - Dashboard, leads, client detail, tasks, pipeline, work queue, notes, reminders, deals, CSV export, REST/AJAX flows.

## Audit notes / limitations

- This pass was **audit-only**; no feature implementation was started.
- No code was changed outside creation of this report.
- Local audit found **no `wp-content/mu-plugins/` directory** in this repository snapshot, so any production-only MU behavior still needs a parity check.
