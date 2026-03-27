# CRM Push Panel Bootstrap Failure Audit

## 1) Executive summary

### Confirmed facts
- The `/crm/` push panel skeleton is rendered server-side with default placeholder text values (`Checking push notification status…`, `Service worker status: checking…`, `Digest cron health: checking…`). These are only replaced by JavaScript after `refreshState()` runs.  
- The panel JavaScript (`assets/frontend/crm-push.js`) exits immediately if `window.peraCrmPush` is missing, and also exits if the panel root selector is not found. Either condition leaves placeholders unchanged forever.  
- The push JS is enqueued/localized only on CRM routes for logged-in users, with handle `pera-crm-push` and localized object name `peraCrmPush`. 
- A later hook in the same file (`peracrm_frontend_dequeue_theme_assets`, priority 41) dequeues `pera-crm-push` after enqueue happens at priority 40, which can remove the just-enqueued push bootstrap script.  
- `refreshState()` starts by awaiting service worker registration/subscription lookup before updating all three status fields; if that await chain stalls, all three fields remain in “checking…” state.  
- The diagnostics and digest actions both rely on REST calls requiring a valid `X-WP-Nonce` (`wp_rest`) and CRM access permissions; digest additionally requires manager/admin capability via `peracrm_push_user_can_run_digest()`.

### Inference (most likely earliest failing layer)
- The earliest single-point failure that explains **all three status lines never updating** is bootstrap precondition failure in JS (`peraCrmPush` missing, script not executing, or early-return path), or an unresolved hang in `getCurrentSubscription()` before any status mutation occurs.

---

## 2) Push panel boot sequence

### Step A — Template markup rendered
- **File**: `wp-content/plugins/peracrm/inc/views/pages/crm-overview.php`
- **Markers**:
  - Panel root: `data-crm-push-card`
  - Placeholders: `data-crm-push-status`, `data-crm-push-sw-status`, `data-crm-push-cron-health`
  - Action buttons: `data-crm-push-run-digest`, `data-crm-push-refresh-diagnostics`
  - Test push form posts to `admin-post.php` action `peracrm_send_test_push`.
- **Dependency inputs**: user logged in; page is CRM overview render path.
- **Stall conditions**:
  - Not stalled here; this layer only prints static placeholders that require JS to change.

### Step B — JS file binding and guard clauses
- **File**: `wp-content/plugins/peracrm/assets/frontend/crm-push.js`
- **Entry behavior**:
  1. IIFE executes.
  2. Immediate guard: `if (!window.peraCrmPush) return;`
  3. DOM guard: `const card = document.querySelector('[data-crm-push-card]'); if (!card) return;`
- **Dependency inputs**:
  - Global localized object `window.peraCrmPush`
  - Presence of card element in DOM
- **Stall conditions**:
  - Missing/misnamed localization object
  - Script not loaded/executed
  - Selector mismatch

### Step C — Localized config injection
- **File**: `wp-content/plugins/peracrm/inc/frontend/assets.php`
- **Function**: `pera_crm_enqueue_assets()`
- **Behavior**:
  - Enqueues `assets/frontend/crm-push.js` as `pera-crm-push` on CRM routes and logged-in sessions.
  - Localizes object `peraCrmPush` with `swUrl`, `subscribeUrl`, `unsubscribeUrl`, `digestRunUrl`, `debugUrl`, `canRunDigest`, `isConfigured`, `missingReasons`, `debug`, `clickUrl`, `restNonce`.
- **Dependency inputs**:
  - `pera_is_crm_route()` true
  - `is_user_logged_in()` true
  - asset file exists
- **Stall conditions**:
  - CRM route detection false on `/crm/`
  - not logged in
  - localization not emitted (handle mismatch/order break)
  - malformed/missing `restNonce` or URL properties

### Step D — Initial function calls on load
- **File**: `wp-content/plugins/peracrm/assets/frontend/crm-push.js`
- **Initial calls**:
  - `showDigestButton();`
  - `refreshState();`
- **Dependencies**:
  - button/query selectors resolved
  - browser push APIs available for full path
- **Stall conditions**:
  - `refreshState()` awaits `getCurrentSubscription()` first (service worker register/getSubscription).
  - If this await chain hangs or never resolves, no downstream calls (`renderCronHealth`, `renderServiceWorkerStatus`, `refreshDiagnostics`) run, leaving all placeholders unchanged.

### Step E — First network requests made
- **Primary first network** (browser-level): `navigator.serviceWorker.register(SW_URL)` inside `getRegistration()`.
- **First app REST request**: `GET config.debugUrl` in `refreshDiagnostics()` via `requestJson()` with `X-WP-Nonce` header.
- **Dependency inputs**:
  - service worker URL reachable (`/peracrm-sw.js` default)
  - valid REST nonce + auth cookies
- **Stall conditions**:
  - SW register unresolved/rejected before UI updates
  - debug endpoint rejects (403/401/500/non-JSON)

### Step F — DOM update points for the three “checking…” fields
- `data-crm-push-status`: updated by `setStatus()` from `refreshState()` (or enable/disable flows).
- `data-crm-push-sw-status`: updated by `renderServiceWorkerStatus()` invoked from `refreshState()`.
- `data-crm-push-cron-health`: updated by `renderCronHealth()` invoked from `refreshState()` and diagnostics/digest paths.
- **Permanent checking root cause**:
  - If `refreshState()` never reaches these calls, all three default placeholders persist.

---

## 3) Action handler map

## A) Refresh diagnostics
- **Button selector**: `[data-crm-push-refresh-diagnostics]`
- **JS handler**: click -> `refreshDiagnostics()`
- **Endpoint**: `config.debugUrl` (default REST `/peracrm/v1/push/debug`)
- **Method**: `GET`
- **Auth/nonce dependency**: `X-WP-Nonce: config.restNonce`, logged-in cookie session, CRM access capability.
- **Expected response shape**:
  - JSON with `debug` object; code reads `response.debug` and `debug.cron`.
- **Current error handling**:
  - `catch` sets diagnostics text: `Unable to load push diagnostics right now.`
- **Permanent checking risk**:
  - Yes for the three headline fields if `refreshState()` never got to first render; this action only updates diagnostics and cron summary (if successful), not guaranteed to set push status/service worker status.

## B) Run digest
- **Button selector**: `[data-crm-push-run-digest]`
- **JS handler**: click -> `runDigestNow()`
- **Endpoint**: `config.digestRunUrl` (default REST `/peracrm/v1/push/digest/run`)
- **Method**: `POST` JSON `{}`
- **Auth/nonce dependency**:
  - `X-WP-Nonce: config.restNonce`
  - logged-in CRM access + manager/admin via `peracrm_push_user_can_run_digest()`
- **Expected response shape**:
  - JSON containing `summary` object and optional `cron` object.
- **Current error handling**:
  - Internal `catch` sets digest line: `Unable to run digest right now.` (+missing config reasons if present).
- **Permanent checking risk**:
  - Yes (indirect). Even on failure, this does not force `status` or `sw` fields out of checking state.

## C) Test push notifications (UI label currently “Send test notification”)
- **Control**: form submit button (not JS in `crm-push.js`)
- **Form target**: `/wp-admin/admin-post.php`
- **Action**: `peracrm_send_test_push`
- **Method**: `POST`
- **Nonce/auth dependency**:
  - `check_admin_referer('peracrm_send_test_push', 'peracrm_send_test_push_nonce')`
  - user must be logged in and have CRM access (or `manage_options`)
- **Expected response shape**:
  - Redirect back to `/crm/` with query arg `peracrm_push_notice=test_push_sent|test_push_failed`
- **Current error handling**:
  - Hard fail via `wp_die(..., 403)` for access denial
  - Nonce failure triggers WP nonce error flow
- **Permanent checking risk**:
  - This action is independent of the “checking…” bootstrap state; failure here does not explain status placeholders by itself.

---

## 4) Config/localization audit

### Confirmed name/shape alignment
- JS expects `window.peraCrmPush` and properties:
  - `swUrl`, `publicKey`, `subscribeUrl`, `unsubscribeUrl`, `digestRunUrl`, `debugUrl`, `canRunDigest`, `isConfigured`, `missingReasons`, `debug`, `restNonce`.
- PHP provides `wp_localize_script('pera-crm-push', 'peraCrmPush', [...])` with exactly those keys.

### Handle/order dependencies
- Enqueue/localize happen in `pera_crm_enqueue_assets()` on `wp_enqueue_scripts` priority 40.
- `peracrm_frontend_dequeue_theme_assets()` runs at priority 41 and dequeues `pera-crm-push`.
- `pera_crm_enqueue_assets()` runs at priority 40 and enqueues/localizes `pera-crm-push`.
- Because 41 runs after 40, the script can be removed from the final queue on CRM routes. This is a confirmed handle collision/order bug and a prime earliest-layer bootstrap failure.

### Config injection failure modes
- If `pera_is_crm_route()` returns false on `/crm/`, push script/localization will not load.
- If localized object is absent/malformed, JS hard-returns before binding handlers.
- If `restNonce` invalid/empty, REST actions fail 403.

---

## 5) REST endpoint audit

### Registered push routes
- `GET /peracrm/v1/push/config` (access callback: CRM + nonce)
- `POST /peracrm/v1/push/subscribe` (access callback: CRM + nonce)
- `POST /peracrm/v1/push/unsubscribe` (access callback: CRM + nonce)
- `GET /peracrm/v1/push/debug` (access callback: CRM + nonce)
- `POST /peracrm/v1/push/digest/run` (access callback: CRM + nonce + manager/admin)

### Permission callbacks
- `peracrm_rest_can_access_push()` requires:
  - logged in
  - `peracrm_user_can_access_crm(user)` true
  - valid nonce from `X-WP-Nonce` or `_wpnonce`
- `peracrm_rest_can_run_digest()` adds `peracrm_push_user_can_run_digest()` requirement.

### Response format vs frontend expectation
- `/push/debug` returns JSON object containing `debug` (frontend expects this)
- `/push/digest/run` returns JSON object containing `summary` and `cron` (frontend expects these)
- If server emits non-JSON (fatal/HTML/auth redirect), frontend `response.json()` throws and falls into catch paths for diagnostics/digest outputs.

### Mismatch callouts
- No direct schema mismatch found between JS reads and REST payload keys in current code.
- Main mismatch risk is auth/nonce/capability rejection, not key naming.

---

## 6) Most likely breakpoints ranked

1. **Enqueue/dequeue order bug removes `pera-crm-push` on CRM routes.**  
   - `pera_crm_enqueue_assets()` enqueues at priority 40, then `peracrm_frontend_dequeue_theme_assets()` dequeues same handle at 41.
   - This directly explains `window.peraCrmPush` missing, placeholders stuck on “checking…”, and JS action handlers failing to bind.
2. **JS bootstrap guard short-circuit (`window.peraCrmPush` missing or script not executing).**  
   - Mechanistic failure state reached by #1 or by any separate script/load break.
3. **`refreshState()` blocked at service worker registration/subscription await chain.**  
   - Explains all three status placeholders remaining unchanged despite script load.
4. **REST nonce/auth failure (403) across debug and digest calls.**  
   - Explains action failures for refresh/digest; does not alone explain unchanged headline status unless combined with #2.
5. **Digest capability mismatch (`canRunDigest` true in UI but `/push/digest/run` denied).**  
   - Explains run digest failure specifically.
6. **Admin-post test push failure (nonce/access) independent of REST pipeline.**  
   - Explains test push failure; separate path from JS REST actions.

---

## 7) Smallest safe next diagnostic step

1. In browser devtools on `/crm/`, verify earliest bootstrap preconditions in this strict order:
   - `typeof window.peraCrmPush` and required keys (`debugUrl`, `digestRunUrl`, `restNonce`, `swUrl`).
   - Confirm `crm-push.js` loaded (Network + no console syntax/runtime error before listener bind).
   - Manually run `window.peraCrmPush.debugUrl` fetch with nonce header to inspect real HTTP/JSON response.
2. Add temporary one-line timestamped console markers (or equivalent minimal server logging) at:
   - top of IIFE,
   - before/after `getCurrentSubscription()`,
   - before/after `refreshDiagnostics()` fetch.  

This isolates whether failure is at **bootstrap object injection**, **service worker promise chain**, or **REST auth/response** without refactoring behavior.
