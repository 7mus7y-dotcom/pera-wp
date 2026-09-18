# PeraCRM frontend authorization audit

**Audit date:** 2026-09-16

**Scope:** `wp-content/plugins/peracrm`

**Reviewer scenario:** WordPress user 97 (`metaappreviewer`), role `advisor`, assigned to CRM client 59939 through both `assigned_advisor_user_id` and `crm_assigned_advisor`.

## Executive conclusion

Do not provide user 97's credentials to Meta App Review yet.

The current code has two incompatible authorization models:

1. CRM entry is capability-based: `edit_crm_clients` permits entry to `/crm/`.
2. Most collection scoping is role-based: only a user whose literal role is `employee` is treated as a scoped adviser.

User 97 therefore passes the CRM entry gate but is not scoped by the main client list or header search. Separately, the canonical assignment resolver does not recognize `advisor` as a valid adviser role, so the assignment metadata for client 59939 resolves to no adviser. This explains both the full-dataset exposure and why the current assignment cannot safely provide the intended reviewer experience.

There is a second critical exposure: the custom REST collections `/peracrm/v1/leads`, `/peracrm/v1/clients`, and `/peracrm/v1/deals` accept any user with `edit_crm_clients` and return unscoped results. Their REST nonce is CSRF protection, not record-level authorization.

The recent WhatsApp message GET and POST routes are better structured: both use a client-specific permission callback. URL substitution should be denied. However, user 97 is not recognized by the existing adviser validator, so the same bug that affects direct client access should make client 59939 fail closed rather than make it available to the reviewer.

## 1. Current authorization architecture

### CRM route entry

`inc/frontend/routing.php` registers `/crm/`, `/crm/client/{id}/`, list, task, pipeline, performance, and log routes in `pera_crm_register_route()`.

All recognized frontend CRM routes pass through `pera_crm_gate_or_redirect()`. It requires authentication and calls `pera_crm_user_can_access()`, which delegates to `peracrm_user_can_access_crm()` in `inc/helpers.php`.

`peracrm_user_can_access_crm()` grants entry for any of:

- `manage_options`;
- `edit_crm_clients`;
- `edit_crm_leads`;
- `edit_crm_deals`.

This is only an application-entry permission. It does not establish a set of clients the user may access. User 97 can enter because the `advisor` role has `edit_crm_clients`.

### CRM client CPT capabilities

`peracrm_register_cpt_crm_client()` in `inc/cpt.php` registers `crm_client` with:

- `capability_type => ['crm_client', 'crm_clients']`;
- `map_meta_cap => true`;
- `show_in_rest => true`.

The employee-level CPT capabilities in `inc/roles.php` include `read_crm_client`, `edit_crm_client`, `edit_crm_clients`, and `read_private_crm_clients`, but not `edit_others_crm_clients`.

Consequences:

- `current_user_can('edit_post', $client_id)` is mapped by WordPress using post authorship and status.
- WordPress does not know that PeraCRM adviser assignment metadata is an authorization relationship.
- `read_private_crm_clients` is broad CPT authority, not "read private clients assigned to me."
- `show_in_rest` creates a parallel built-in `wp/v2` access surface governed by CPT capabilities rather than PeraCRM assignment.

Object/meta capabilities remain useful secondary checks, but they cannot be the canonical assignment boundary.

### Assignment fields and resolver

The code recognizes both assignment fields:

- `assigned_advisor_user_id`;
- legacy `crm_assigned_advisor`.

`peracrm_pipeline_assigned_meta_keys()` returns both. `peracrm_resolve_allowed_client_ids_for_user()` in `inc/services/client_scope_service.php` builds an OR meta query across both fields.

The single-client resolver, `peracrm_client_get_assigned_advisor_id()` in `inc/helpers.php`, reads both values but returns one only if `peracrm_user_is_valid_advisor()` accepts the referenced user. That validator ultimately calls `peracrm_user_is_staff()`, which recognizes only these literal roles:

- `employee`;
- `manager`;
- `administrator`.

It does not recognize `advisor`. Consequently, with the code in this repository, setting both assignment fields to 97 does not make user 97 the resolved adviser: `peracrm_client_get_assigned_advisor_id(59939)` should return `0`.

### Client collections and lists

`pera_crm_get_leads_view_data()` in `inc/frontend-data/crm-data.php` resolves allowed IDs, but adds `post__in` only when `pera_crm_user_is_employee()` is true. That helper recognizes only an `employee` who is not also a manager or administrator.

For user 97:

- `pera_crm_user_is_employee(97)` is false;
- no `post__in` restriction is added;
- the query retrieves every CRM client in the configured post statuses.

This is the direct cause of the observed full client list.

The pipeline uses a different condition: users lacking `manage_options` and `peracrm_manage_all_clients` are scoped. But its allowed-ID helper rejects `advisor`, so user 97 should see no pipeline clients rather than the one assigned client. Dashboard providers similarly mix employee checks, allowed-ID filters, and manage-all checks, producing inconsistent results rather than a reliable boundary.

### Header search/autocomplete

`peracrm_header_search_ajax()` in `inc/services/header_search_service.php` requires login, general CRM access, and a nonce. It then calls `peracrm_header_search_results()`.

`peracrm_header_search_scope_client_ids()` returns `null`, meaning full scope, when the effective user is neither impersonating nor a literal `employee`. User 97 meets those conditions. The resulting SQL omits its `p.ID IN (...)` clause and can return client IDs, names, email addresses, phone numbers, stages, types, and direct URLs for the production dataset.

The nonce prevents cross-site request forgery. It does not authorize the returned records.

### Direct client URL

`inc/views/pages/crm-client.php` calls `pera_crm_client_view_access_state($client_id)` before loading the record.

`pera_crm_client_view_access_state()` in `inc/frontend-data/crm-client-view.php` checks:

1. general CRM management access;
2. that the post is a `crm_client`;
3. `current_user_can('edit_post', $client_id)`;
4. unless the user has `manage_options` or `peracrm_manage_all_clients`, that the resolved assigned adviser equals the current user.

This is a real server-side check, not merely UI hiding. An unrelated client should be denied. But because the assignment resolver rejects the `advisor` role, client 59939 should also be denied to user 97. If production behaves differently, deployed code or runtime policy differs from this checkout and must be investigated before credentials are shared.

### Timeline, notes, reminders, and frontend AJAX

The direct client template loads profile, timeline, notes, reminders, deals, and property data only after the page access check passes.

The consolidated `pera_crm_client_action_ajax()` handler and the portfolio-related AJAX handlers call `pera_crm_client_view_access_state()` before client-specific reads or writes. These are server-side checks in addition to nonce checks.

The notes and reminder admin-post handlers also compare the current user with the resolved assigned adviser, with reminder-specific manager overrides. However, several other legacy handlers, including profile, status, and deal writes, primarily rely on `current_user_can('edit_post', $client_id)`. Because post authorship and adviser assignment are distinct, those handlers are only partially assignment-safe.

### Custom PeraCRM REST collections

`inc/rest.php` registers:

- `GET /peracrm/v1/leads`;
- `GET /peracrm/v1/clients`;
- `GET /peracrm/v1/deals`.

All use `peracrm_rest_can_access()`, which accepts a valid REST nonce plus any of the broad CRM capabilities. User 97 has `edit_crm_clients`, so the permission callback succeeds.

The lead/client SQL queries all published `crm_client` posts without assignment conditions. The deals endpoint queries every deal row without filtering `party_id` by accessible clients. Counts and pagination totals are likewise global. These are server-side adviser enumeration vulnerabilities.

### WhatsApp conversation routes

`inc/rest/whatsapp.php` registers both methods on:

`/peracrm/v1/whatsapp/clients/{client_id}/messages`

- GET calls `peracrm_rest_whatsapp_client_messages()`.
- POST calls `peracrm_rest_whatsapp_send_message()`.
- Both use `peracrm_rest_whatsapp_client_permission()`.

That permission callback delegates to `peracrm_whatsapp_user_can_access_client()`. Its current policy:

1. validates a real `crm_client`;
2. grants global access for `manage_options` or `peracrm_manage_all_clients`;
3. otherwise requires `edit_crm_clients`;
4. compares the resolved assigned adviser to the requesting user.

Therefore changing the client ID should not retrieve or send messages for another client. GET and POST share the same server-side authorization. The POST recipient is derived from the authorized client, not accepted from the browser.

The defect is compatibility with the reviewer: `peracrm_client_get_assigned_advisor_id()` does not recognize user 97's `advisor` role, so 59939 should not pass this permission check until adviser eligibility is corrected.

### WhatsApp association and assignment controls

`/peracrm/v1/whatsapp/associate` uses `peracrm_rest_whatsapp_admin_permission()`, not ordinary client access, and validates the target as a `crm_client`. It should remain manager/admin-only.

Assignment UI and the reassignment handler require `manage_options` or `peracrm_manage_assignments`. Reassignment updates both assignment fields. User 97 lacks that capability and should not be able to reassign clients.

## 2. Root cause

### Primary cause: role-name coupling

There is no universal definition of a scoped CRM adviser. CRM entry checks capabilities, while list and assignment code repeatedly asks whether the user has a specific role name.

Because the reviewer has role `advisor`:

- entry succeeds via `edit_crm_clients`;
- main list filtering does not run;
- header search is treated as full scope;
- assignment allowed-ID helpers either return nothing or are bypassed;
- the single-client assignment resolver refuses to accept 97 as a valid adviser.

This produces the paradox observed in practice: enumeration succeeds, but assignment-based access is not reliably granted.

### Capability mapping is not assignment mapping

`map_meta_cap` makes `edit_post` object-aware, but it maps using post author/status and primitive CPT capabilities. It does not consult `assigned_advisor_user_id` or `crm_assigned_advisor`. The reviewer lacking `edit_others_crm_clients` reduces some editing authority but does not filter application SQL, `get_posts()` calls, custom REST routes, or header-search queries.

### Nonces are not authorization

Several routes correctly require nonces. A nonce proves request origin/freshness for CSRF purposes; it does not prove that user 97 may read or mutate the supplied client ID. Every boundary must separately enforce client scope.

### `peracrm_manage_all_clients` is only partially enforced

The capability is used by direct client access, pipeline scope, parts of header search, WhatsApp access, dashboard metrics, and impersonation. It is not the universal switch between full and assigned-only scope. In particular, the main list uses literal `employee` role detection, and custom REST collections do not use it to scope results.

## 3. Exposure matrix

| Surface | Current adviser status | Finding |
| --- | --- | --- |
| `/crm/` entry | Unrestricted at entry level | `edit_crm_clients` grants entry; no client scope is established. |
| Dashboard/overview | Partially restricted | Providers use inconsistent employee/manage-all/allowed-ID rules. |
| Main client list | Unrestricted | Scoping runs only for literal `employee`, not `advisor`. |
| Pipeline | Partially restricted | It attempts to scope non-managers, but the resolver gives an `advisor` no allowed clients. |
| Header client search | Unrestricted | Non-employee/non-impersonated users receive full scope. |
| Custom REST `/leads` | Unrestricted | Broad capability and nonce; global published-client query. |
| Custom REST `/clients` | Unrestricted | Same issue. |
| Custom REST `/deals` | Unrestricted | All deal rows and party IDs are returned. |
| Built-in `wp/v2` CRM routes | Partially restricted / runtime test required | Enabled by `show_in_rest`; governed by CPT capabilities, not assignment. |
| Direct client URL | Server-side restricted, but broken for reviewer | Assignment comparison exists; role `advisor` is not accepted by the assignment resolver. |
| Client timeline/activity | Restricted behind detail boundary | Loaded only after detail access succeeds. |
| Frontend client AJAX | Properly restricted structurally | Calls the client access-state function before mutations. |
| Notes | Partially/properly restricted | Assignment check exists, but policy is duplicated. |
| Reminders | Partially/properly restricted | Assignment checks exist; override/context behavior varies. |
| Legacy profile/status/deal writes | Partially restricted | Often rely on `edit_post`, not assignment. |
| Portfolio client AJAX | Properly restricted structurally | Nonce plus server-side client access check. |
| WhatsApp message GET | Properly scoped structurally; reviewer broken | Client-ID substitution should fail, but 59939 also fails until adviser eligibility is fixed. |
| WhatsApp message POST/send | Properly scoped structurally; reviewer broken | Same permission callback as GET; recipient derives from client. |
| WhatsApp association | Properly privileged | Uses target-site administrative permission. |
| Assignment controls | Properly privileged | Require assignment-management capability or admin. |
| WordPress admin client list | Unrestricted/uncertain | Existing list hooks add filters but no assignment scope; runtime verification is required. |

## 4. Smallest safe Meta reviewer solution

### Establish one canonical client boundary

Add a reusable policy function conceptually equivalent to:

```php
peracrm_user_can_access_client( int $user_id, int $client_id ): bool
```

The exact name is not important. Its policy should:

1. reject unauthenticated/invalid users and non-`crm_client` IDs;
2. allow `manage_options` or `peracrm_manage_all_clients`;
3. require baseline CRM access such as `edit_crm_clients` for scoped users;
4. resolve assignment from both current and legacy fields;
5. allow a scoped user only when the assignment matches;
6. fail closed for missing or conflicting assignments;
7. avoid hard-coding user 97 or client 59939;
8. avoid depending on the literal role name `employee`.

For collections, add or normalize a companion resolver conceptually equivalent to:

```php
peracrm_get_accessible_client_ids( int $user_id )
```

Its result must distinguish full scope from empty scope. An empty allowed-ID list must generate a false query (`post__in => [0]` or equivalent), never mean "omit the filter."

### Dual-field policy

For the immediate compatibility period:

- if both fields are populated and equal, accept the assignment;
- if only one is populated, accept it;
- if both are populated and differ, fail closed and require repair.

The current "first valid staff user wins" behavior can conceal inconsistent assignment data.

### Apply the boundary everywhere

Before credentials are shared, use the canonical policy for:

- direct client pages;
- all client list/dashboard/pipeline queries;
- header search;
- custom REST lead/client/deal collections;
- WhatsApp GET and POST;
- every client-specific AJAX/admin-post mutation;
- the WordPress admin list/direct editor;
- built-in CPT REST routes, or disable that REST surface if it is unnecessary.

This is the smallest safe approach because the repository already has both assignment fields, allowed-ID queries, detail-page checks, a WhatsApp permission callback, dual-field writes, and a manage-all capability. The required patch is primarily consolidation plus complete boundary coverage.

## 5. Long-term adviser isolation

The same minimal fix is the correct basis for the production model:

```text
WhatsApp conversation
    -> CRM client
    -> canonical assignment
    -> assigned adviser or manage-all user
```

Recommended policy:

- Adviser: baseline CRM capability plus assignment match.
- Manager/admin: `peracrm_manage_all_clients` or `manage_options`.
- Assignment manager: additionally `peracrm_manage_assignments`.
- Reminder manager: `peracrm_manage_all_reminders` applies only to reminder operations and never grants client or WhatsApp-wide access.
- Nonce: CSRF protection only.

Work that can wait until after App Review:

- migrate the legacy assignment key to one canonical field;
- add assignment history/timestamps;
- replace role-name helpers with formally registered CRM capabilities;
- refactor impersonation and duplicated scope resolvers;
- separate reporting scopes from operational client access;
- add centralized denial/audit telemetry;
- normalize repository/service APIs around an authorization context.

## 6. Proposed implementation plan

### A. Required before providing reviewer credentials

#### `inc/helpers.php`

- Add the canonical client-access policy.
- Replace role-coupled adviser validation with capability/policy-based eligibility.
- Add deterministic dual-field resolution and fail closed on conflicts.
- Retain global overrides only for `manage_options` and `peracrm_manage_all_clients`.

#### `inc/services/client_scope_service.php`

- Make this the canonical collection resolver.
- Return assigned IDs for every scoped CRM user, including role `advisor`.
- Define unambiguous full-scope and empty-scope results.

#### `inc/frontend-data/crm-data.php`

- Remove literal `employee` role checks from authorization decisions.
- Apply canonical scope to main lists, overview metrics, recent activity/leads, tasks, pipeline, and performance data.

#### `inc/services/header_search_service.php`

- Scope every non-manage-all user.
- Never return full scope because the role is not `employee`.
- Retain the nonce, but enforce allowed IDs in SQL.

#### `inc/frontend-data/crm-client-view.php`

- Make `pera_crm_client_view_access_state()` delegate to the canonical policy.
- Separate read authorization from optional edit capability checks.
- Continue using it for all frontend client AJAX actions.

#### `inc/rest.php`

- Scope lead/client item queries and totals to allowed IDs.
- Scope deals through `party_id`.
- Keep REST nonces as CSRF controls, not record authorization.

#### `inc/rest/whatsapp.php` and `inc/whatsapp.php`

- Reuse the canonical helper in the WhatsApp permission callback.
- Preserve identical authorization for GET and POST.
- Preserve server-side recipient derivation.

#### `inc/cpt.php`

Either:

1. disable `show_in_rest` if the built-in CRM REST API is unused; or
2. add collection and object authorization hooks enforcing canonical client scope.

#### `inc/admin/actions.php`

- Scope the WordPress admin client list.
- Add canonical client checks to profile, status, conversion, deal, pipeline, note, reminder, deletion, and related client actions.
- Retain action-specific capabilities in addition to client access.
- Keep assignment writes restricted by `peracrm_manage_assignments`.

#### Reviewer configuration and verification

- Confirm both fields for 59939 equal 97.
- Confirm no other client is assigned to 97.
- Confirm 97 lacks all global/manager capabilities.
- Invalidate prior sessions and log in again after deployment.
- Run all positive and negative isolation tests below before sharing credentials.

### B. Hardening that may wait until after App Review

- Migrate to one assignment field and add a conflict-repair report/CLI command.
- Introduce explicit `peracrm_access` and assigned-client capabilities.
- Consider an assignment-aware `map_meta_cap` filter if wp-admin CPT editing remains supported.
- Remove all role-name authorization checks.
- Consolidate duplicate frontend/admin scope logic.
- Audit exports, logs, notifications, and push endpoints against the same policy.
- Consider hiding/removing wp-admin CPT screens for adviser accounts after server-side enforcement is complete.

## 7. Required tests

### Assignment-policy unit tests

- Adviser A can access Client A assigned to Adviser A.
- Adviser A cannot access Client B assigned to Adviser B.
- Adviser A cannot access an unassigned client.
- A single populated current or legacy field works during migration.
- Conflicting fields fail closed.
- Manager with `peracrm_manage_all_clients` retains appropriate global access.
- Administrator retains access.
- `peracrm_manage_all_reminders` alone grants neither client-wide nor WhatsApp-wide access.
- A user without baseline CRM access is denied even if their ID appears in assignment metadata.
- A user whose role is `advisor` passes when their capabilities and assignment satisfy policy; role `employee` is not required.

### Frontend and enumeration tests

- Adviser A loads `/crm/client/{client_a}/` successfully.
- Adviser A gets a non-disclosing 403/denial for `/crm/client/{client_b}/`.
- URL substitution reveals no profile, note, reminder, timeline, deal, phone, or email data.
- `/crm/`, client lists, pipeline, tasks, performance, dashboard rows, and aggregate counts exclude Client B.
- Empty scope returns no records, never all records.

### Search tests

- Searching Client A's name/email/phone returns Client A.
- Searching Client B's name/email/phone/ID returns nothing.
- A valid nonce does not widen scope.
- An invalid nonce is rejected independently as CSRF protection.

### REST tests

- `/peracrm/v1/leads` and `/clients` return only assigned parties and scoped totals.
- `/peracrm/v1/deals` excludes deals whose `party_id` is outside scope.
- Manager/admin results remain appropriate.
- Built-in `wp/v2` collection, individual, search, `context=edit`, and mutation requests cannot enumerate/read/edit out-of-scope clients.

### WhatsApp tests

- Adviser A can GET and POST for Client A.
- Adviser A cannot GET or POST for Client B.
- Changing only `{client_id}` remains denied.
- The browser cannot supply an arbitrary recipient; the recipient is derived from the authorized client.
- Unassigned and conflicting-assignment clients are denied.
- Manager/admin retains appropriate conversation access.
- Reminder-wide capability alone does not grant conversation access.
- Both legacy/current single-field migration cases are covered.

### AJAX/admin-post mutation tests

For every client-specific action, prove that:

- the assigned adviser succeeds when the action-specific permission allows it;
- an unassigned adviser is rejected before mutation;
- a valid nonce does not authorize an out-of-scope client;
- post authorship alone does not authorize an unassigned client;
- assignment alone does not grant manager-only actions.

Cover profile, status, notes, reminders, deal operations, pipeline moves/bulk actions, portfolio operations, deletion, and reassignment.

### Administrative tests

- Adviser cannot see or directly invoke assignment controls.
- A manager with `peracrm_manage_assignments` can reassign safely.
- Reassignment updates both fields consistently or reports failure.
- WhatsApp association remains unavailable to advisers.
- An authorized administrator can associate an unlinked sender only with a valid CRM client.

## Final risk statement

The present deployment model is not safe for Meta reviewer credentials. Filtering only the visible list would be insufficient: the header search and custom REST collections independently enumerate clients, while legacy write paths do not consistently enforce assignment. The minimum acceptable patch is a canonical server-side client-access policy applied to every collection, detail, REST, AJAX, admin-post, and built-in CPT boundary.
