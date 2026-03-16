# PeraCRM Audit: CRM Lead/Client Determination vs WordPress `lead` Role

## 1) Executive summary

- **CRM lead vs CRM client is determined from CRM deal state** (specifically whether a party has at least one deal in stage `completed`), not from the WordPress role `lead`.
- The system exposes this via helpers such as `peracrm_party_is_client()`, `peracrm_party_batch_get_closed_won_client_ids()`, and `peracrm_party_get_derived_type()`.
- The WordPress role `lead` is used as a **website membership/account role** on the target blog (multisite membership flows), and linked users with that role can be attached to CRM records.
- **Important mismatch:** wp-admin blocking is currently tied to WP role `lead` (`peracrm_block_wp_admin_for_leads`), while CRM authorization is based on CRM capabilities (`edit_crm_*`, `manage_options`). This means “CRM lead/client business state” and “WP role lead” are separate concepts, but admin blocking currently conflates policy at the role layer.

---

## 2) WordPress role model

- Plugin role bootstrap (`inc/roles.php`) manages CRM staff roles `manager` and `employee` plus caps; it does **not** define the WP role `lead` there.
- WP role `lead` is created/ensured in membership service (`inc/services/user_membership_service.php`) for target-site users in multisite, with `read` capability.
- User onboarding/membership flows assign users to WP role `lead` on target blog.

Implication:
- `lead` is an account/membership role concept, not a CRM business classification source.

---

## 3) CRM lead/client determination model

### Primary source of truth

- **Deals table stage = `completed`** determines CRM “client” classification.

### Determination functions

- `peracrm_party_is_client($party_id)` => true when `peracrm_party_completed_deal_count($party_id) > 0`.
- `peracrm_party_batch_get_closed_won_client_ids(array $party_ids)` => selects distinct `party_id` where deal stage is `completed`.
- `peracrm_party_get_derived_type($party_id)` => returns `client` if party id appears in above completed-deal set; otherwise `lead`.

### Related state models

- Party table (`peracrm_party`) stores pipeline/engagement/disposition (`lead_pipeline_stage`, etc.), but business “lead vs client” rendering/filtering frequently derives from completed deal presence.
- Manual conversion action (`peracrm_handle_convert_to_client`) creates a deal with stage `completed`, which flips derived type to `client`.

### Not used as source of truth

- WP role `lead` is not used to compute CRM lead/client type.

---

## 4) Relationship between WP role `lead` and CRM lead/client

- They are **orthogonal** dimensions:
  - WP role `lead` = website membership role.
  - CRM lead/client = business lifecycle derived from deals.
- A WP user with role `lead` can be linked to a CRM record (`crm_client_id`/`linked_user_id`) via autolink and manual linking flows.
- The linked CRM record may be business `lead` or `client` depending on completed-deal state.
- CRM conversion to client does **not** automatically promote/demote WP role from `lead`.

---

## 5) Exact functions/files that determine CRM lead/client state

### Core determination

- `wp-content/plugins/peracrm/inc/repositories/deals.php`
  - `peracrm_party_completed_deal_count()`
  - `peracrm_party_is_client()`
- `wp-content/plugins/peracrm/inc/repositories/party.php`
  - `peracrm_party_batch_get_closed_won_client_ids()` (stage `completed`)
- `wp-content/plugins/peracrm/inc/helpers.php`
  - `peracrm_party_get_derived_type()`

### Conversion logic

- `wp-content/plugins/peracrm/inc/admin/actions.php`
  - `peracrm_handle_convert_to_client()` creates deal with `stage => 'completed'`.

### Surface usage (UI/data)

- `wp-content/plugins/peracrm/includes/frontend/data.php`
  - Builds `client_lookup` from `peracrm_party_batch_get_closed_won_client_ids()`.
  - Emits per-row `derived_type` as `client` or `lead`.
- `wp-content/plugins/peracrm/includes/frontend/client-view.php`
  - Loads `derived_type` via `peracrm_party_get_derived_type()`.
- `wp-content/plugins/peracrm/templates/page-crm-client.php`
  - Shows “Convert to client” action only when `derived_type === 'lead'`.
- `wp-content/plugins/peracrm/inc/admin/actions.php`
  - Admin list filtering and type column logic use completed-deal join and derived type filter.

---

## 6) Exact places where WP role `lead` is used in security/access logic

### Direct security/access usage

- `wp-content/plugins/peracrm/inc/admin-block-leads.php`
  - `peracrm_user_is_lead()` checks if current user has role `lead`.
  - `peracrm_block_wp_admin_for_leads()` blocks wp-admin for role `lead` users (except ajax/cron/rest/json contexts).
  - `show_admin_bar` filter hides admin bar for role `lead` users.

### Membership/account lifecycle usage (not CRM classification)

- `wp-content/plugins/peracrm/inc/services/user_membership_service.php`
  - Ensures role exists and assigns role `lead` to target-blog users.
- `wp-content/plugins/peracrm/inc/services/client_service.php`
  - During ingest membership-ensure fallback, adds/sets user role `lead` on target blog.

### CRM authorization/capability checks

- CRM authorization checks generally use capabilities (`manage_options`, `edit_crm_clients`, etc.), not role `lead`.

---

## 7) Is current wp-admin blocking based on role `lead` correct?

**Assessment: incomplete / conceptually mismatched if interpreted as CRM-state security.**

- If product intent is “block portal membership users from wp-admin,” role-based block for WP role `lead` is conceptually fine.
- If intent is “block CRM leads (business state) from wp-admin,” then current implementation is incorrect, because CRM state is not tied to role `lead`; CRM state is deal-derived.
- Practically, current guard only blocks users who *have WP role `lead`*, regardless of whether linked CRM record is business lead or client.

---

## 8) Recommended security model for next patch

Use a **two-axis model**:

1. **Identity/access axis (WP roles/caps)**
   - Role/capabilities determine system access (wp-admin vs CRM staff UI).
   - Keep membership role (`lead`) separate from CRM staff roles (`employee`, `manager`, `administrator`).

2. **Business state axis (CRM data state)**
   - Lead/client business classification derived from CRM data (completed deal existence), not WP role.

Policy guidance:
- Do not use WP role `lead` as proxy for CRM business lead/client classification.
- Enforce CRM data scope through assignment and explicit CRM capabilities.
- Keep wp-admin block policy explicit about *account class* (membership vs staff), not business status.

---

## 9) Minimal follow-up patch plan

1. **Codify terminology in code/comments/docs**
   - “WP lead role” vs “CRM business lead/client (deal-derived)”.

2. **Centralize business classification helper usage**
   - Use one consistent helper path (`peracrm_party_is_client` / `peracrm_party_get_derived_type`) across frontend/admin/REST to avoid drift.

3. **Separate admin-access gates from CRM state**
   - Keep wp-admin block tied to intended account roles/caps, not inferred CRM lifecycle labels.

4. **Audit and align all authorization checks**
   - Ensure CRM actions use capability + scope checks; never WP role `lead` for CRM data access decisions.

5. **Add regression tests/checklist**
   - Verify: a WP role `lead` user linked to a CRM client remains membership-role user;
   - Verify: CRM lead->client conversion changes derived business type but does not mutate WP role unexpectedly.
