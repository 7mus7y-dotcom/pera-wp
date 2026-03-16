# PeraCRM Auth / Capability / Scope Audit (plugin-owned runtime)

## 1) Executive summary
**Status: PARTIAL PASS**

Most privileged surfaces are gated by login + nonce + capability checks, and advisor/client scope controls are present in key frontend/admin flows. However, there are important pre-go-live gaps:

1. REST collection endpoints (`/peracrm/v1/leads`, `/clients`, `/deals`) enforce CRM capability but **do not enforce advisor/client scope**, so employee-level users with CRM access can enumerate global CRM data.
2. `admin-post` reassign action (`peracrm_reassign_client_advisor`) authorizes by reassignment capability but does not additionally verify `edit_post` on the target client.
3. Several templates rely on central router gating rather than local guard calls; this is safe while router ownership remains plugin-owned, but fragile if template entrypoints are reused outside that path.

---

## 2) Route access map

### CRM frontend routes (`/crm/*`)
- Route ownership and template loading are plugin-owned through rewrite + `template_include` hook in routing.
- Global gate is `pera_crm_gate_or_redirect()`:
  - anonymous users redirected to login;
  - logged-in users must pass `pera_crm_user_can_access()` (which delegates to `peracrm_user_can_access_crm()`).
- Effective CRM access capability set is: `manage_options` OR `edit_crm_clients` OR `edit_crm_leads` OR `edit_crm_deals`.

### Route-by-route
- `/crm/`, `/crm/leads`, `/crm/clients`, `/crm/tasks`, `/crm/pipeline`, `/crm/client/{id}`, `/crm/new`
  - gated centrally before template selection via `pera_crm_maybe_load_template()`.
- `/crm/new` POST (lead creation)
  - requires login;
  - requires CRM access + explicit `current_user_can('edit_crm_clients')`;
  - requires nonce `pera_crm_create_lead_nonce` for action `pera_crm_create_lead`.

---

## 3) Admin-post access map

Registered CRM admin-post actions are all authenticated (`admin_post_*`; no `admin_post_nopriv_*` in audited CRM admin registrations).

### Strongly gated (login + nonce + capability/scope)
- `peracrm_add_note`: login + nonce + assigned advisor or override cap.
- `peracrm_add_reminder`: login + nonce + assigned advisor or override cap.
- `peracrm_mark_reminder_done`: login + nonce + authorization delegated to reminders service.
- `peracrm_update_reminder_status`: login + nonce + authorization delegated to reminders service (supports frontend scope enforcement flag).
- `peracrm_save_client_profile`: login + nonce + `edit_post(client)` (+ dormant transition guard).
- `peracrm_save_party_status`: login + nonce + `edit_post(client)`.
- `peracrm_convert_to_client`: login + CRM-manage gate + nonce + `edit_post(client)`.
- `peracrm_create_deal` / `update_deal` / `delete_deal`: CRM-manage gate + deal nonce validator + `edit_post(client)` (plus extra delete restriction).
- `peracrm_delete_client`: login + POST + nonce + delete_post + reassignment safety gate.
- `peracrm_pipeline_save_view` / `delete_view`: login + `edit_crm_clients` + nonce.
- `peracrm_pipeline_move_stage` / `bulk_action`: login + nonce + per-client `edit_post` + assignment scope checks.
- `peracrm_pipeline_export_csv`: login + `edit_crm_clients` + nonce, with employee scoping.

### Acceptable but weaker/fragile patterns
- `peracrm_link_user` / `peracrm_unlink_user`
  - check `edit_post(client)` + nonce,
  - but no explicit `is_user_logged_in()` (implicitly denied by caps/nonce when anonymous).
- `peracrm_unlink_user` reads nonce from `$_REQUEST` (permits GET/POST); nonce still required, but POST-only enforcement would be stronger.

### Notable gap
- `peracrm_reassign_client_advisor`
  - enforces login + nonce + `peracrm_admin_user_can_reassign()`,
  - **does not also require `edit_post(client)`** unlike most other client-mutating handlers.

---

## 4) AJAX access map

All audited AJAX endpoints are `wp_ajax_*` only (no `wp_ajax_nopriv_*`), so anonymous requests are blocked by WordPress auth cookie requirement.

### Endpoints and checks
- `peracrm_create_portfolio_token`
- `peracrm_update_portfolio_token`
- `pera_crm_upload_portfolio_floor_plan`
- `pera_crm_save_portfolio_property_fields`
  - each validates expected action,
  - checks `is_user_logged_in()`,
  - checks endpoint-specific nonce,
  - checks `pera_crm_client_view_access_state(client_id)` (includes `edit_post` + assigned-advisor scope unless manage-all).
- `pera_crm_property_search`
  - validates action + login + `pera_crm_client_view_can_manage()` + nonce;
  - this endpoint is global search (not tied to single client scope).

---

## 5) REST access map (`peracrm/v1`)

### Core collections
- `GET /peracrm/v1/leads`
- `GET /peracrm/v1/clients`
- `GET /peracrm/v1/deals`
  - use `peracrm_rest_can_access()`:
    - must be logged in,
    - must present valid REST nonce (`X-WP-Nonce` / `_wpnonce`, action `wp_rest`),
    - must have one CRM capability (`manage_options` / `edit_crm_clients` / `edit_crm_leads` / `edit_crm_deals`).

### Push routes
- `/push/config`, `/push/subscribe`, `/push/unsubscribe`, `/push/debug`
  - use `peracrm_rest_can_access_push()` (login + CRM access + REST nonce).
- `/push/digest/run`
  - uses `peracrm_rest_can_run_digest()` = push access + digest manager check.

### Notable gap
- Core collection handlers do not apply advisor/client scope filters for employee users.

---

## 6) Scope enforcement findings

### What is correctly scoped
- Frontend client view access (`pera_crm_client_view_access_state`):
  - requires `edit_post(client)`;
  - non-manage-all users must be assigned advisor.
- Frontend list/pipeline data (`pera_crm_get_allowed_client_ids_for_user` + query restrictions in data helpers):
  - employee users are scoped to assigned client IDs.
- Reminder status updates can enforce frontend scope path via `enforce_client_scope` in delegated service authorization.
- Pipeline admin-post flows (`move_stage`, `bulk_action`, export) apply per-client and/or advisor scoping.

### Scope weakness
- REST `/leads|clients|deals` responses are not advisor-scoped for employee users.

---

## 7) Missing nonce/capability checks

### Confirmed missing / insufficient
1. `peracrm_handle_reassign_client_advisor` missing `current_user_can('edit_post', $client_id)`.
2. REST collection endpoints missing advisor/client scope checks for non-manage-all users.

### Hardening recommendations (non-blocking but advisable)
1. Add explicit `is_user_logged_in()`/method checks to `link_user` and `unlink_user` for consistency.
2. Restrict `unlink_user` nonce source to `POST` only (avoid `$_REQUEST`).

---

## 8) Risks before go-live

### High
- **Data overexposure via REST collections**: employee-cap users can retrieve global CRM records rather than assigned scope.

### Medium
- **Potential authorization drift on advisor reassignment**: role with reassignment cap but lacking per-post edit could still mutate assignment metadata.

### Low / operational
- Template-level guards are inconsistent (some templates rely entirely on router-level gate). Safe in current architecture, but sensitive to future route/template entrypoint changes.

---

## 9) Exact files needing follow-up

1. `wp-content/plugins/peracrm/inc/rest.php`
   - apply advisor/client scope in `/leads`, `/clients`, `/deals` handlers.
2. `wp-content/plugins/peracrm/inc/admin/actions.php`
   - add `edit_post(client)` check in `peracrm_handle_reassign_client_advisor`.
   - optionally harden `peracrm_handle_unlink_user` to POST-only nonce source.
3. (Consistency hardening) `wp-content/plugins/peracrm/templates/page-crm.php` and `wp-content/plugins/peracrm/templates/page-crm-new.php`
   - optional explicit local gate call for defense-in-depth parity with `page-crm-pipeline.php`.
