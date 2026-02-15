# PeraCRM Push Notifications Audit

## Summary
- Click URL recursion was removed by making `peracrm_push_default_click_url()` pure/non-recursive (filterable static default).
- Server-side payload click URLs are aligned to the same helper for digest, admin-post test, and WP-CLI test sends.
- Service worker now defines `DEFAULT_CLICK_URL` once and uses it as fallback in both `push` and `notificationclick` handlers.

## Current service worker URL, scope, and CRM registration path
- Service worker script is served dynamically at `/peracrm-sw.js` via `peracrm_push_render_service_worker()` on `init` in `inc/services/push_service.php`.
- The CRM front-end script registers the SW with `navigator.serviceWorker.register(config.swUrl || '/peracrm-sw.js')` in `wp-content/themes/hello-elementor-child/js/crm-push.js`.
- `/crm/` pages enqueue and localize `crm-push.js` from `functions.php`, passing `swUrl` and REST endpoints into `window.peraCrmPush`.
- Diagnostics now report:
  - whether a matching registration exists (by scanning `getRegistrations()` and matching `scriptURL`),
  - whether the current page is controlled (`navigator.serviceWorker.controller`),
  - hint when registered but not controlled: reload/navigate under SW scope.

## Subscription storage and subscribe/unsubscribe flow
- Subscriptions are stored in user meta key `peracrm_push_subscriptions` (`peracrm_push_meta_key()`), not in a dedicated subscription DB table.
- `POST /peracrm/v1/push/subscribe`
  - validates CRM + nonce,
  - normalizes endpoint + keys,
  - upserts subscription into user meta.
- `POST /peracrm/v1/push/unsubscribe`
  - validates CRM + nonce,
  - removes endpoint from user meta.
- Sending uses current user subscriptions via `peracrm_push_list_user_subscriptions()`.

## VAPID/public config source and configured/not-configured logic
- VAPID values are read from constants:
  - `PERACRM_VAPID_PUBLIC_KEY`
  - `PERACRM_VAPID_PRIVATE_KEY`
  - `PERACRM_VAPID_SUBJECT`
- `peracrm_push_missing_config_reasons()` now returns explicit reasons for missing constants and missing WebPush library classes.
- `peracrm_push_is_configured()` is true only when there are no missing reasons.
- Public config now includes SW + REST URLs and diagnostics flags for UI:
  - `swUrl`, `subscribeUrl`, `unsubscribeUrl`, `digestRunUrl`, `debugUrl`, `canRunDigest`, `isConfigured`, `missingReasons`.

## Digest trigger paths, schedule, and WP-Cron caveats
- Cron schedule key: `peracrm_fifteen_minutes` (15-minute interval).
- Digest event hook: `peracrm_push_digest`.
- Event scheduler runs on `init` and schedules the event if missing.
- Cron health API includes:
  - next scheduled unix timestamp,
  - local formatted time,
  - `DISABLE_WP_CRON` flag.
- Deterministic triggers:
  - REST: `POST /peracrm/v1/push/digest/run` (manager/admin + CRM + nonce)
  - WP-CLI: `wp peracrm push digest`

## Top 3 failure modes from inspection and instrumentation added
1. **No active subscriptions / stale dedupe state**
   - Digest summary now reports `rows_considered`, `pushes_attempted`, `pushes_sent`, and skipped reason counters (`no_subs`, `deduped`, etc.).
   - Debug endpoint returns `subs_count`, current `window_key`, dedupe meta key, and last dedupe value.

2. **Service worker registered but page not controlled (common Android/Chrome issue)**
   - CRM diagnostics now explicitly display registration + active + controlled + scope.
   - Added UI hint: if registered but uncontrolled, reload/navigate once under scope.

3. **Push logging/schema mismatch causing hidden failures**
   - Logging now introspects available `wp_crm_push_log` columns via `SHOW COLUMNS` and inserts only fields that exist.
   - Log insert failures never block delivery; they only emit `error_log` in debug mode.
   - Debug endpoint returns latest 10 push log rows (status-focused summary).


## Digest dedupe trap root cause and fix
- **Root cause:** digest dedupe meta (`peracrm_push_last_digest_<window_key>`) was written whenever there were any send results, even when every send failed. This included local failures (`status_code=0`) such as missing WebPush library/config, which caused the next run in the same window to be marked `deduped` even though nothing was delivered.
- **Fix implemented:** dedupe meta is now written only when at least one digest send succeeds (`ok=true`).
  - If push is not configured, digest exits early and writes no dedupe state.
  - If all sends fail (including all-`status_code=0` failures), dedupe is not written.
  - Existing behavior for a real hash match in-window still applies (`decision=deduped`).

## New digest instrumentation and debug signals
- Digest summary now includes `advisor_decisions` (first 5 advisors):
  - `advisor_user_id`, `pending_count`, `overdue_count`, `subs_count`
  - `dedupe_meta_key`, `last_hash`, `new_hash`
  - `decision` and `reason` (`sent`, `deduped`, `no_subs`, `send_error`, etc.)
- `/peracrm/v1/push/debug` now also includes:
  - `digest_window_key`
  - computed `digest_hash` for target user
  - `last_digest_meta`
  - `digest_dedupe` object with `would_dedupe`, `decision`, and `reason`

## Force-run options (manager/admin)
- REST force run: `POST /peracrm/v1/push/digest/run?force=1`
- WP-CLI force run: `wp peracrm push digest --force`
- Force bypasses dedupe checks for that run, but still does **not** write dedupe unless at least one send succeeds.

## Dead-subscription self-healing
- When send results return HTTP `404` or `410`, the endpoint is automatically removed from `peracrm_push_subscriptions` using `peracrm_push_remove_subscription()` in target-blog context.

## Verification / reproduction steps
1. Ensure at least one advisor has pending reminders and a stored push subscription (`peracrm_push_subscriptions`).
2. Simulate local send failure (missing library/config so send returns `status_code=0`).
3. Run digest: `wp peracrm push digest`
   - Confirm `pushes_attempted > 0`, `pushes_sent = 0`, and no dedupe lockout on next run.
4. Restore library/config and run digest again (or force):
   - `wp peracrm push digest` or `wp peracrm push digest --force`
   - Confirm sends succeed and dedupe meta writes only after success.
5. Inspect dedupe meta/debug:
   - Meta key pattern: `peracrm_push_last_digest_<window_key>`
   - Check `/peracrm/v1/push/debug` fields: `digest_window_key`, `digest_hash`, `last_digest_meta`, and `digest_dedupe` to verify dedupe reasoning.
