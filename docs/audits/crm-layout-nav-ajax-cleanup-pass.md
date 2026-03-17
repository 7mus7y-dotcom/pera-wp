# CRM Layout/Nav/AJAX Cleanup Pass Audit

## Confirmed remaining issues
1. AJAX action handler used independent `if` blocks, which is brittle and not explicitly exclusive.
2. Panel extraction used `esc_attr()` in XPath query construction, which is not the correct escaping context.
3. Client view HTML render flow restored only a subset of modified query vars (`pera_crm_client_id` was not restored).
4. AJAX panel refresh path could silently return empty panel HTML without explicit response context for frontend handling.
5. Logs visibility rule was duplicated in multiple places (side nav + logs page) without a shared helper.
6. Logs page still used direct SQL fallback; theme files expose table name helpers but do not expose reusable frontend list query helpers, so duplication must remain minimal and isolated.
7. Frontend AJAX assumed `response.json()` always succeeds, risking malformed-response failures.
8. Inline status and confirmation dialog styles existed but needed minor hardening for consistent visibility/semantics.

## Files affected
- `wp-content/plugins/peracrm/inc/frontend-data/crm-client-view.php`
- `wp-content/plugins/peracrm/assets/frontend/crm.js`
- `wp-content/plugins/peracrm/assets/frontend/crm.css`
- `wp-content/plugins/peracrm/inc/views/partials/crm-side-nav.php`
- `wp-content/plugins/peracrm/inc/views/pages/crm-logs.php`
- `wp-content/plugins/peracrm/inc/helpers.php`

## Exact implementation decisions
- Kept current AJAX UX direction and in-place panel replacement; hardened failure/parse handling instead of rewriting transport.
- Converted `pera_crm_client_action_ajax()` to an explicit `if / elseif / else` chain so only one branch can execute.
- Added XPath-safe literal builder for panel selector and restricted extraction to known panel keys.
- Restored all touched query vars after full client render (`client_id`, `pera_crm_client_id`, `pera_crm_view`).
- Kept graceful JSON payloads even when panel render/extraction fails by returning `panel`, `panel_html` (possibly empty), and `render_failed` flag.
- Confirmed panel/form hook wiring in `crm-client.php` already matches required values exactly; preserved nonce/id/redirect hidden fields.
- Confirmed floating tick button markup is absent in plugin client view; retained CSS cleanup direction.
- Confirmed Create portfolio button uses `.crm-portfolio-create-btn`; kept spacing styles wired to real markup selector.
- Introduced shared capability helper `peracrm_can_view_operational_logs()` and reused it in both side nav and logs page.
- For logs data access, reused existing theme table-name helpers (`pera_whatsapp_clicks_table_name`, `pera_enquiry_email_log_table_name`) and kept SQL retrieval as isolated fallback due to lack of reusable list-query helpers.

## Regression risks
- Any external code expecting multiple action `if` checks to run (unlikely) would no longer do so.
- Empty `panel_html` now explicitly reported; frontend still shows inline message and keeps page usable.
- Shared log capability helper relies on `peracrm_manage_assignments` parity with prior checks (same effective rule).

## Test checklist
- Verify right-side nav appears on `/crm/`, `/crm/clients/`, `/crm/tasks/`, `/crm/pipeline/`, `/crm/client/{id}`, `/crm/whatsapp-logs/`, `/crm/email-logs/`.
- Verify logs nav links appear only for `manage_options` or reassignment-capable users.
- Verify `/crm/client/{id}` AJAX updates still work in place for `profile`, `status`, `note`, `reminder`, `property-link`, `deal`, `advisor`.
- Verify advisor dialog copy remains exactly:
  - `Yes, I’m sure`
  - `No, I made a mistake`
- Verify malformed/non-JSON AJAX responses surface stable inline error and do not break submit button state restoration.
- Verify convert-to-client flow remains redirect (non-AJAX).
- Verify no floating tick markup and portfolio button spacing selector remains `.crm-portfolio-create-btn`.
