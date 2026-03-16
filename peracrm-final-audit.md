# Final PeraCRM Plugin Audit: Independence + Role/Scope/Security Enforcement

## 1) Executive summary

**Result: PARTIAL (with blockers).**

- Plugin runtime independence is mostly sound.
- Role/scope/security enforcement does **not** fully match required behavior for Admin/Manager/Employee.
- Critical blockers exist around wp-admin access controls and manager all-client visibility consistency.

---

## 2) Independence audit result

### Plugin-owned runtime (pass)

- Main plugin bootstraps its own runtime (`peracrm.php` -> `inc/bootstrap.php`) and loads plugin-owned routing/frontend/admin/services from `wp-content/plugins/peracrm/`.
- CRM templates are loaded from plugin templates by default.

### Fallback-only behavior (pass)

- MU shim (`wp-content/mu-plugins/peracrm.php`) exits when the standard plugin is active.
- Theme CRM files are loaded only behind `function_exists` guards (fallback/compat behavior).

### Hidden dependencies (none critical found)

- No hard dependency requiring old MU/theme CRM runtime for normal plugin operation was found.
- Compatibility guards are present but not hard-coupled for primary runtime.

---

## 3) Role/capability map (actual behavior)

### Admin

- Has full CRM access, reports, and wp-admin access (expected).

### Manager

- Has `edit_crm_*` and report access.
- Does **not** receive explicit `peracrm_manage_all_clients`, `peracrm_manage_assignments`, `peracrm_manage_all_reminders` in role bootstrap.
- This causes mismatch with intended “all-clients but no wp-admin” model.

### Employee

- Has `edit_crm_*` plus limited CPT capabilities.
- Report cap removed.

---

## 4) wp-admin access findings

### Intended

- Admin: allowed
- Manager: denied
- Employee: denied

### Actual

- Only role `lead` is blocked from wp-admin by plugin guard.
- Manager and Employee are **not** blocked by equivalent role-based admin deny logic.

**Finding:** Manager/Employee wp-admin denial requirement is not enforced.

---

## 5) Client visibility/scope findings

### Intended

- Admin: all clients
- Manager: all clients
- Employee: assigned clients only

### Actual

- Employee is scoped to assigned clients in frontend list/data paths.
- Manager visibility is inconsistent:
  - Some list views effectively show all.
  - Client-detail and REST/export scoping rely on “manage-all” caps managers are not granted, causing incorrect restrictions (including possible empty REST scope).

**Finding:** Manager all-client visibility is not consistently enforced.

---

## 6) Endpoint/action security findings

### Frontend routes

- Core CRM routes are gated by authentication + CRM capability checks.

### Data providers

- Employee scoping is implemented via allowed-client-ID resolution.
- A global filter path can force non-employee users (including managers) into empty/non-global scopes when manage-all caps are missing.

### AJAX

- Client-view AJAX handlers generally validate login, nonce, and access state before acting.

### REST

- REST endpoints enforce auth + REST nonce + CRM capability.
- Scope handling for non-admin relies on manage-all caps + allowed ID filters.
- Managers lacking manage-all caps can be improperly scoped.

### admin-post handlers

- Many handlers use nonce + capability checks.
- Enforcement is not perfectly uniform: some handlers rely on `edit_post` semantics without explicit assigned-advisor ownership checks.

### Exports

- Pipeline export forces non-manage-all users into advisor-scoped export.
- Managers lacking manage-all cap cannot reliably export all clients as required.

---

## 7) Places where employee can see/act on non-owned clients

- No single universal bypass was confirmed in this pass.
- However, there is **risk ambiguity** where handlers use only `edit_post` checks rather than explicit assignment ownership checks, which can diverge from strict assigned-client policy in edge cases.

---

## 8) Places where manager is incorrectly blocked from all-client visibility

- Frontend client detail access path (manage-all cap dependent).
- REST scoped collections (manage-all cap dependent + allowed-ID filter interactions).
- Pipeline export scoping (advisor scope forced without manage-all cap).

---

## 9) Places where manager/employee can still access wp-admin

- Manager and Employee are not covered by the plugin’s wp-admin block guard (which currently targets `lead` role only).

---

## 10) Final blocker list before live deployment

1. No manager/employee wp-admin deny enforcement.
2. Manager all-client behavior is inconsistent (detail/REST/export).
3. Capability naming vs assignment mismatch (`peracrm_manage_all_clients`, etc.)
4. Non-uniform action-level ownership checks across admin-post handlers.

---

## 11) Minimal patch plan (recommended order)

1. Align role caps in `inc/roles.php` to explicit policy (admin full, manager all-clients/no-admin, employee assigned-only).
2. Add explicit wp-admin deny gate for manager/employee (with explicit technical allowlist only where necessary).
3. Unify scope resolution so manager always resolves to all-client scope and employee to assigned scope across frontend/REST/export.
4. Standardize action authorization through one ownership policy helper (replace mixed `edit_post`-only assumptions).
5. Re-test tampering vectors: URL params, IDs, REST paging/filters, export payloads, admin-post actions.
