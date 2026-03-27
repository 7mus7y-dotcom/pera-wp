# CRM Push Subscription/Bootstrap Audit (2026-03-27)

## 1. Executive summary

- The push panel can remain on "checking…" because `refreshState()` previously awaited `getCurrentSubscription()` before any fallback UI write for common failure paths.
- `getCurrentSubscription()` always called `navigator.serviceWorker.register(SW_URL)`, so a stalled registration promise blocked all status rendering.
- `/push/debug` reporting `subs_count: 0` means no valid subscription rows are persisted for the **debug target user** in user meta key `peracrm_push_subscriptions`.
- A browser can still have a local Push subscription while server-side `subs_count` is zero, because `refreshState()` reads browser state only and does not backfill server persistence.
- Smallest safe stabilization: make `refreshState()` fail fast and keep rendering diagnostics even if service worker registration stalls.

## 2. `refreshState()` execution path

1. `crm-push.js` IIFE exits early unless `window.peraCrmPush` exists and `[data-crm-push-card]` is present.
2. On load it calls `refreshState()`.
3. `refreshState()` now:
   - checks feature support first;
   - resolves current subscription via `getCurrentSubscription()`;
   - reads `Notification.permission`;
   - renders status pill/button state;
   - renders cron health, service worker status, and diagnostics.
4. If any awaited step throws, catch path renders a red fallback status and still attempts service worker + diagnostics rendering so the UI does not remain frozen on placeholders.

## 3. Service worker + subscription flow

### Bootstrap/read path
- `getCurrentSubscription()`:
  - uses `navigator.serviceWorker.register(SW_URL)`;
  - then reads `registration.pushManager.getSubscription()`.
- This is a read-only check and does **not** call `/push/subscribe`.

### Enable path
- Clicking enable runs:
  1. `Notification.requestPermission()`
  2. service worker registration + `navigator.serviceWorker.ready`
  3. `registration.pushManager.getSubscription()`; if absent, `pushManager.subscribe(...)`
  4. `POST /peracrm/v1/push/subscribe` with `subscription.toJSON()` and WP REST nonce
  5. server persists payload in user meta (`peracrm_push_subscriptions`)

### Persistence/readback
- `/push/subscribe` calls `peracrm_push_save_subscription($user_id, $payload, $user_agent)`.
- Payload must include endpoint + keys (`p256dh`, `auth`) or it is rejected as invalid.
- `/push/debug` computes `subs_count` from `peracrm_push_list_user_subscriptions($target_user_id)`.

## 4. Reasons `subs_count` can stay 0

- Enable flow never reached `POST /push/subscribe` (permission dismissed/error/uncaught failure).
- Existing browser subscription already present, so no re-subscribe was triggered and no server backfill happened.
- `/push/subscribe` returned 401/403 (nonce, auth, CRM access) so persistence never occurred.
- Payload normalization rejected malformed data (missing endpoint or keys).
- Multisite/target-blog context mismatch (saved in different blog context than where debug reads).
- Debug endpoint is inspecting a different target user than expected.

## 5. Most likely breakpoint ranked

1. Service worker registration promise chain stalls before status rendering.
2. `POST /push/subscribe` not occurring for this browser/user session.
3. REST auth/nonce denial on subscribe request.
4. Debug user/blog context mismatch (subscription saved elsewhere).
5. Invalid subscription payload normalization rejection.

## 6. Smallest safe next fix

- Keep the current flow but harden bootstrap:
  - short timeout around service worker registration in `getCurrentSubscription()`;
  - support check before async subscription lookup;
  - in `refreshState()` catch path, still render service-worker summary + diagnostics.
- This is low-risk because it does not alter push permission semantics or subscription payload shape; it only prevents UI deadlock and improves observability.
