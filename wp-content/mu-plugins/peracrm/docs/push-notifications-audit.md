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
