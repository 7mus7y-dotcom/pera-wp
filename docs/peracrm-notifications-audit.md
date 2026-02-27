# PeraCRM Reminder Notifications Audit (End-to-End)

## Scope and method
This audit traced the reminder notification path across:

- `wp-content/mu-plugins/peracrm/` (reminder data, push digest, cron, REST)
- `wp-content/themes/hello-elementor-child/inc/` and `.../js/` (CRM UI, push client)

Search focus was on actual trigger/delivery mechanisms (`cron`, scheduled hooks, REST, admin-post, push service worker), then on gating and timezone logic.

---

## 1) System overview

### What exists today
The implemented reminder notification path is **Web Push digest notifications** (not per-reminder in-app bell notifications):

1. Reminder rows are created/updated in `crm_reminders`.
2. A 15-minute WP-Cron event (`peracrm_push_digest`) runs `peracrm_push_run_digest()`.
3. Digest aggregates pending/overdue reminder counts **per advisor**.
4. For advisors with valid subscriptions in usermeta (`peracrm_push_subscriptions`), the system sends Web Push payloads.
5. Browser service worker (`/peracrm-sw.js`) displays notification.

There is no dedicated `crm_notifications` table and no unread counter/bell rendering path found for reminders in this code path.

### ASCII architecture / data flow

```text
[CRM UI (front-end or wp-admin)]
   |  admin-post: peracrm_add_reminder / peracrm_update_reminder_status
   v
[peracrm_handle_add_reminder / update handlers]
   |
   v
[peracrm_reminder_add / peracrm_reminder_update_status]
   |
   v
[DB: wp_crm_reminders]
  fields: id, client_id, advisor_user_id, due_at, status, note, created_at, updated_at
   |
   | (every 15 min if WP-Cron runs)
   v
[WP-Cron hook: peracrm_push_digest]
   -> peracrm_push_run_digest()
   -> GROUP BY advisor_user_id over status in ('pending','open',...filtered)
   -> build digest payload per advisor
   |
   +--> read usermeta[peracrm_push_subscriptions]
   |      (device endpoints)
   |
   +--> send via WebPush library
   |
   +--> optional DB log: wp_crm_push_log
   v
[Service worker: /peracrm-sw.js]
   -> showNotification(title/body/click_url)
   -> open/focus CRM URL on click
```

---

## 2) Source of truth for reminders

## Data model and storage
- Primary reminders table is `{$wpdb->prefix}crm_reminders`, created in schema upgrade with fields:
  - `client_id`
  - `advisor_user_id`
  - `due_at` (`DATETIME`)
  - `status` (default `'pending'`)
  - `note`, `created_at`, `updated_at`
- Fallback exists via post meta key `_peracrm_reminders_fallback` when table is missing.

## CRUD entry points
- Create:
  - `peracrm_handle_add_reminder()` (admin-post)
  - calls `peracrm_admin_parse_datetime()` → `peracrm_parse_due_at_input()`
  - calls `peracrm_reminder_add()`
- Update status:
  - `peracrm_handle_update_reminder_status()` and `peracrm_handle_mark_reminder_done()`
  - call `peracrm_reminders_update_status_authorized()` → `peracrm_reminder_update_status()`

## Reminder fields relevant to notifications
- `due_at`: stored as site-timezone-normalized MySQL datetime string (`Y-m-d H:i:s`).
- `status`: allowed statuses are `pending`, `done`, `dismissed`.
- `advisor_user_id`: notification routing key (digest groups reminders by advisor).
- `client_id`: reminder ownership/context.

---

## 3) Trigger mechanism (due detection)

### Actual trigger found
- Custom schedule: `peracrm_fifteen_minutes` (15 min).
- Event hook: `peracrm_push_digest`.
- Event scheduling occurs on `init` via `peracrm_push_schedule_digest()`.
- Handler `peracrm_push_digest_handler()` calls `peracrm_push_run_digest()`.

### Due scanning behavior
`peracrm_push_run_digest()` does **not** enqueue one notification per reminder row. It computes advisor-level digest counts:
- `pending_count = COUNT(*)`
- `overdue_count = SUM(CASE WHEN due_at < now THEN 1 END)`
- filtered by statuses from `peracrm_push_digest_status_filters()` (defaults include `pending`, `open`).

### Other trigger paths
- Manual manager/admin trigger via REST:
  - `POST /peracrm/v1/push/digest/run`
- Optional WP-CLI trigger exists (`inc/cli/push_cli.php`).

No client-side polling timer that creates reminder notifications was found; JS is for browser push subscription/diagnostics only.

---

## 4) Delivery channels and unread state

### Delivery channels found
- **Web Push** only for reminder notifications in this path:
  - subscription endpoints: `/peracrm/v1/push/subscribe`, `/unsubscribe`
  - payload delivery via Minishlink WebPush
  - browser display via dynamic service worker at `/peracrm-sw.js`

### Not found
- No in-app notification bell/unread reminders store.
- No reminder notification email pipeline in audited files.
- No dashboard unread notification table/transient for reminder pushes.

### State storage related to push
- Subscriptions: usermeta key `peracrm_push_subscriptions`.
- Digest dedupe: usermeta key pattern `peracrm_push_last_digest_<window_key>`.
- Push send logs (optional/diagnostic): table `crm_push_log`.

---

## 5) Routing / gating analysis (why execution may be blocked)

## Critical gating observations
1. **WP-Cron dependency**
   - Digest scheduling is on `init`; execution depends on WP-Cron being triggered by traffic or server cron.
   - If `DISABLE_WP_CRON` is true and no real cron calls `wp-cron.php`, digest will never run.

2. **Push config hard gate**
   - `peracrm_push_run_digest()` returns early with `skipped.not_configured` when VAPID constants or push library are missing.
   - In this case reminders exist, but no notifications are sent.

3. **Subscription hard gate**
   - Even with due reminders, advisor needs at least one valid subscription in `peracrm_push_subscriptions`.
   - No subscriptions => summary skip reason `no_subs`.

4. **Employee `wp-admin` lockdown likely impacts frontend `admin-post` reminder actions**
   - Theme blocks employee access in `admin_init` (`pera_block_employee_admin_access`) and redirects to home unless request is AJAX/cron/JSON.
   - Front-end reminder create/update forms post to `/wp-admin/admin-post.php`.
   - `admin-post.php` is not exempted in this lock; this can prevent reminder CRUD from completing for employee users in front-end flows.

5. **Digest is aggregate, not immediate**
   - Product expectation might be “notify when a reminder is due” (near-real-time), but implementation is 15-minute digest + dedupe. Per-row immediate trigger is not present.

---

## 6) Timezone / parsing audit

### Storage and parsing
- Reminder input uses `datetime-local` in UI.
- Parsing accepts:
  - `Y-m-d\TH:i` (datetime-local)
  - `Y-m-d H:i[:s]`
  - ISO8601 with timezone
- Parsed datetimes are converted to `wp_timezone()` and stored as local MySQL string.

### Comparison behavior
- Overdue checks compare against `current_time('mysql')` or `current_time('timestamp')` and format with WP timezone.
- Digest overdue SQL uses `due_at < $now` where `$now` is local `current_time('mysql')`.

### Risk note
Timezone handling appears internally consistent (site-local storage + local comparisons). Main risk is UX mismatch if operators assume UTC, but code itself is not obviously using mixed UTC/local in due comparisons.

---

## 7) Code map (file/function map)

## A) Reminder CRUD
- `wp-content/mu-plugins/peracrm/inc/admin/actions.php`
  - `peracrm_handle_add_reminder()`
  - `peracrm_handle_mark_reminder_done()`
  - `peracrm_handle_update_reminder_status()`
  - `peracrm_admin_parse_datetime()`
- `wp-content/mu-plugins/peracrm/inc/admin/admin.php`
  - `add_action('admin_post_peracrm_add_reminder', ...)`
  - `add_action('admin_post_peracrm_update_reminder_status', ...)`
- `wp-content/mu-plugins/peracrm/inc/repositories/reminders.php`
  - `peracrm_reminder_add()`
  - `peracrm_reminder_update_status()`
  - `peracrm_reminders_get()`, list/count helpers
  - fallback meta helpers (`_peracrm_reminders_fallback`)

## B) Due scanning / trigger
- `wp-content/mu-plugins/peracrm/inc/cron/push_cron.php`
  - `peracrm_push_register_cron_schedule()`
  - `peracrm_push_schedule_digest()`
  - `peracrm_push_digest_handler()`
- `wp-content/mu-plugins/peracrm/inc/services/push_service.php`
  - `peracrm_push_run_digest()`
  - `peracrm_push_digest_status_filters()`
  - `peracrm_push_get_cron_health()`

## C) Notification creation/storage
- `wp-content/mu-plugins/peracrm/inc/services/push_service.php`
  - `peracrm_push_send_to_user()` / `peracrm_push_send_to_subscription()`
  - `peracrm_push_get_digest_decision()` (dedupe decision)
  - `peracrm_push_meta_key()` (`peracrm_push_subscriptions`)
- `wp-content/mu-plugins/peracrm/inc/db/push_log_table.php`
  - `peracrm_push_log_create_table()` (`crm_push_log`)

## D) Delivery/UI rendering
- `wp-content/mu-plugins/peracrm/inc/services/push_service.php`
  - `peracrm_push_render_service_worker()` serves `/peracrm-sw.js`
- `wp-content/themes/hello-elementor-child/js/crm-push.js`
  - registers service worker
  - subscribe/unsubscribe calls
  - digest run/debug calls
- `wp-content/themes/hello-elementor-child/inc/modules/crm-push.php`
  - enqueues/localizes `crm-push.js` with REST nonce and endpoints
- `wp-content/themes/hello-elementor-child/page-crm.php`
  - push diagnostics UI card and actions

## E) REST/AJAX endpoints
- `wp-content/mu-plugins/peracrm/inc/rest/push.php`
  - `/push/config`
  - `/push/subscribe`
  - `/push/unsubscribe`
  - `/push/debug`
  - `/push/digest/run`

## F) Security/gating relevant to CRM front-end posting
- `wp-content/themes/hello-elementor-child/inc/filter-for-admin-panel.php`
  - `pera_block_employee_admin_access()` redirect on `admin_init`
- `wp-content/themes/hello-elementor-child/page-crm-client.php`
  - reminder forms posting to `/wp-admin/admin-post.php`

---

## 8) Most likely failure points (ranked)

1. **Employee wp-admin lockdown intercepts frontend reminder admin-post calls** (Highest likelihood)
- Evidence:
  - Employee redirect on `admin_init` in `pera_block_employee_admin_access()` without explicit `admin-post.php` exemption.
  - Front-end reminder create/update forms submit to `/wp-admin/admin-post.php`.
- Effect:
  - Reminder CRUD may fail/redirect before handlers run, resulting in no new due reminders to notify.

2. **Push not configured (`not_configured`)**
- Evidence:
  - Digest returns early when VAPID constants/webpush classes missing (`peracrm_push_is_configured()` gate).
- Effect:
  - Reminders exist; push notifications never sent.

3. **No device subscriptions for target advisor(s)**
- Evidence:
  - Digest decision path requires non-empty `peracrm_push_subscriptions` for advisor.
  - Skip reason explicitly `no_subs`.
- Effect:
  - Digest runs but sends nothing.

4. **WP-Cron not firing in environment**
- Evidence:
  - Digest event relies on schedule + WP-Cron traffic trigger.
  - Health reports include `disable_wp_cron` and next run metadata.
- Effect:
  - Scheduler exists but callback never executes.

5. **Status mismatch between data and digest filter expectations**
- Evidence:
  - Digest default status filters include `pending`/`open`; skipped reason `status_mismatch` is tracked.
  - Reminder CRUD currently writes `pending|done|dismissed`; older data with different status values can be excluded.
- Effect:
  - Rows present but ignored by digest query.

6. **Expectation mismatch: implementation is digest, not immediate due-event notify**
- Evidence:
  - No per-reminder “on due_at reached” action hook found; only periodic aggregate digest.
- Effect:
  - Team may expect immediate notification at exact due time, but system can notify up to next 15-min window (or not at all if deduped/no change).

---

## 9) Minimal reproducible test plan (staging)

Preconditions: one employee/advisor user, one CRM client assigned to that advisor, push keys configured, browser supporting Push API.

1. **Create reminder due in 2 minutes**
   - In CRM client page submit reminder form.
   - Confirm request reaches `admin-post` handler (watch redirect notice / optional debug log).

2. **Verify reminder row exists**
   - SQL:
     ```sql
     SELECT id, client_id, advisor_user_id, due_at, status, created_at
     FROM wp_crm_reminders
     WHERE client_id = <CLIENT_ID>
     ORDER BY id DESC
     LIMIT 5;
     ```
   - Expect status `pending`, correct advisor user id, due_at in site timezone.

3. **Verify cron event exists**
   - WP-CLI:
     ```bash
     wp cron event list --fields=hook,next_run_gmt,next_run_relative | grep peracrm_push_digest
     ```
   - If no WP-CLI, use `/peracrm/v1/push/debug` and inspect `debug.cron`.

4. **Verify digest trigger executes**
   - Force run (manager/admin):
     ```bash
     wp peracrm push digest --force=1
     ```
     or REST `POST /peracrm/v1/push/digest/run?force=1` with REST nonce.
   - Expect summary includes `rows_considered > 0` for advisor with pending reminders.

5. **Verify subscription exists**
   - SQL (or `wp user meta get <uid> peracrm_push_subscriptions`):
     ```sql
     SELECT meta_value FROM wp_usermeta
     WHERE user_id = <ADVISOR_UID> AND meta_key = 'peracrm_push_subscriptions';
     ```
   - Expect at least one endpoint with keys `p256dh` and `auth`.

6. **Verify push attempt/logs**
   - SQL:
     ```sql
     SELECT user_id, payload_type, status_code, ok, created_at
     FROM wp_crm_push_log
     ORDER BY id DESC
     LIMIT 20;
     ```
   - Expect row(s) with `payload_type='digest'`; success if `ok=1`.

7. **Verify end-user delivery/UI**
   - On subscribed browser/device, ensure permission granted and service worker registered for `/peracrm-sw.js`.
   - Confirm notification appears and click opens CRM tasks URL.

8. **If step 1 fails for employee**
   - Hit `/wp-admin/admin-post.php?action=peracrm_add_reminder` while logged as employee and verify whether redirect middleware blocks execution.

---

## 10) Follow-up PR plan (smallest fixes first, not implemented in this audit)

1. **Allowlist `admin-post.php` in employee admin lock middleware**
   - Small, targeted exception in `pera_block_employee_admin_access()`.
2. **Add explicit diagnostics key for admin-post lock reason**
   - Lightweight debug visibility when reminder create/update is blocked.
3. **Add startup self-check notice on CRM push card**
   - Surface `not_configured`, `no_subs`, and cron-disabled states clearly.
4. **Add exact status audit endpoint field**
   - Return status histogram for current advisor from reminder table to catch status mismatch fast.
5. **Optional: schedule hardening**
   - If `DISABLE_WP_CRON` true, document/enforce server cron command in ops runbook.
6. **Optional product fix**
   - If exact due-time behavior required, add near-real-time trigger (single event per reminder) instead of digest-only model.

---

## 11) Single-line safe fix check
No code fix applied in this audit pass. The likely admin-post gating issue is strong, but implementing behavior changes was intentionally deferred to the follow-up PR plan per audit-first constraint.

---

## Fix PR notes

Implemented minimal safe fixes to unblock front-end CRM reminder flows for employee users while preserving wp-admin lockdown:

1. **Narrow admin-post allowlist for employees in admin blocker**
   - Updated `pera_block_employee_admin_access()` in `wp-content/themes/hello-elementor-child/inc/filter-for-admin-panel.php` to allow only:
     - requests to `admin-post.php`
     - `POST` method only
     - CRM action in strict allowlist (`peracrm_add_reminder`, `peracrm_update_reminder_status`, etc.)
   - Non-allowlisted employee wp-admin access remains blocked and redirected as before.

2. **Optional staging-only debug log for blocked requests**
   - Added debug logging guarded by:
     - `WP_DEBUG` true, and
     - `PERA_CRM_DEBUG_ADMIN_BLOCK` true
   - Log includes reason, request URI, and action for blocked employee admin requests.

3. **Digest filter safety hardening**
   - Updated `peracrm_push_digest_status_filters()` in `wp-content/mu-plugins/peracrm/inc/services/push_service.php` to always include `'pending'` even if a filter removes it.
   - Legacy `'open'` remains supported.

4. **Small push health clarity improvement in CRM JS**
   - Updated `wp-content/themes/hello-elementor-child/js/crm-push.js` diagnostics summary to explicitly show `configured yes/no` alongside subscription count and cron next run.
- Follow-up hardening: corrected admin-post path parsing to use `parse_url(..., PHP_URL_PATH)` and relaxed fallback path detection to match `admin-post.php` without hard-coding `/wp-admin/`, reducing fatal risk and install-path brittleness while keeping the same allowlist behavior.
