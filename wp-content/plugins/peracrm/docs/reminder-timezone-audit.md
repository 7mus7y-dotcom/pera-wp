# Reminder Timezone Audit (Front-end CRM)

## Front-end create path
- **Front-end form**: `page-crm-client.php` posts to `/wp-admin/admin-post.php` with `action=peracrm_add_reminder` and `peracrm_due_at` from an `<input type="datetime-local">` field.
- **Handler registration**: `add_action('admin_post_peracrm_add_reminder', 'peracrm_handle_add_reminder')` in `inc/admin/admin.php`.
- **Handler callback**: `peracrm_handle_add_reminder()` in `inc/admin/actions.php`.

## Input format and payload keys
- **Input field format**: `datetime-local` (`YYYY-MM-DDTHH:MM` wall-time, no timezone marker).
- **Payload keys**:
  - `peracrm_due_at`
  - `peracrm_client_id`
  - `peracrm_reminder_note`
  - `peracrm_redirect`
  - `action=peracrm_add_reminder`

## REST route check
- No reminder-specific REST route was found under `wp-content/mu-plugins/peracrm` for creation.
- Creation path is **admin-post**, not REST.

## Parsing and conversion chain (before fix)
1. Front-end sends `peracrm_due_at` from `datetime-local`.
2. `peracrm_handle_add_reminder()` reads it and called `peracrm_admin_parse_datetime()`.
3. `peracrm_admin_parse_datetime()` parsed only `Y-m-d\TH:i` / `Y-m-d H:i` in `wp_timezone()`.
4. `peracrm_reminder_add()` then called `peracrm_reminders_sanitize_due_at()` which only sanitized text, without canonical datetime normalization.
5. Insert happened in `peracrm_reminders_insert_table()` into `wp_crm_reminders.due_at` (or fallback meta storage).

## DB insert/update location
- Main insert: `inc/repositories/reminders.php` -> `peracrm_reminders_insert_table()`.
- Fallback insert: `inc/repositories/reminders.php` -> `peracrm_reminders_insert_fallback()`.

## Likely drift risk points found
- Multiple parsing entry points with inconsistent format support.
- No single canonical parser for ISO8601-with-offset input.
- Timeline/task rendering included `strtotime()` in theme fallback paths, which can use server timezone assumptions.
