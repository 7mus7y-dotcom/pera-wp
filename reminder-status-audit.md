# Audit: reminder status updates (admin-post vs front-end CRM)

## Scope checked
- `wp-content/mu-plugins/peracrm/inc/repositories/reminders.php`
- `wp-content/mu-plugins/peracrm/inc/admin/actions.php`
- `wp-content/mu-plugins/peracrm/inc/admin/admin.php`
- `wp-content/mu-plugins/peracrm/inc/helpers.php`
- `wp-content/themes/hello-elementor-child/inc/crm-router.php`
- `wp-content/themes/hello-elementor-child/inc/crm-data.php`

## Findings

1. There is **no** public `peracrm_reminders_update_status()` function.
   - The update entrypoint is `peracrm_reminder_update_status($reminder_id, $status, $actor_user_id)`.
   - It sanitizes/casts `reminder_id`, `actor_user_id`, and `status` (`peracrm_reminders_sanitize_status()`), then writes via table/fallback helpers.

2. Current admin update path
   - Hook: `admin_post_peracrm_update_reminder_status` -> `peracrm_handle_update_reminder_status()`.
   - Nonce: action `peracrm_update_reminder_status`, field `peracrm_update_reminder_status_nonce`.
   - POST shape:
     - `action=peracrm_update_reminder_status`
     - `peracrm_reminder_id`
     - `peracrm_status` (`pending|done|dismissed` after sanitization)
     - `peracrm_redirect` (optional)
   - Capability gate is **not admin-only**. It allows:
     - admin/manage-all-reminders caps, OR
     - reminder owner (`advisor_user_id === current_user_id`).

3. Service-layer suitability
   - Existing repository function is argument-based and sanitizes inputs.
   - But it does **not** enforce capability/ownership beyond `actor_user_id > 0`.
   - Therefore it is not safe to expose directly from front-end without wrapping authorization checks.

4. Ownership security
   - Handler checks `advisor_user_id` match (or admin/manage-all) before update.
   - It validates that reminder's `client_id` resolves to a CRM client post.
   - It does **not** check front-end employee scope list (`pera_crm_get_allowed_client_ids_for_user`) explicitly.

5. Front-end nonce strategy
   - Existing admin nonce/action pair is reusable: `peracrm_update_reminder_status` + `peracrm_update_reminder_status_nonce`.
   - For /crm/, prefer per-row nonce fields (best CSRF granularity); shared page nonce is acceptable as fallback.

## Recommendation

Outcome: **C) Needs small refactor**

- Keep current admin-post flow as baseline.
- Introduce a dedicated service wrapper in MU plugin (e.g. `peracrm_reminders_update_status_authorized($reminder_id, $status, $actor_user_id, $enforce_client_scope = false)`) that:
  1. Loads reminder by ID.
  2. Sanitizes `status` and validates ID/user.
  3. Authorizes (`manage_options`/`peracrm_manage_all_reminders` OR `advisor_user_id === actor`).
  4. Optionally enforces client scope by checking `client_id` against allowed IDs for employee users (`pera_crm_get_allowed_client_ids_for_user`) when called from front-end context.
  5. Calls `peracrm_reminder_update_status()` only after checks.

Then implement front-end “Mark done” as a theme `template_redirect` POST handler on `/crm/` using:
- nonce action `peracrm_update_reminder_status`
- nonce field `peracrm_update_reminder_status_nonce`
- `reminder_id` + fixed status `done`
- safe redirect back to `/crm/...`
- service wrapper for auth/ownership before write.
