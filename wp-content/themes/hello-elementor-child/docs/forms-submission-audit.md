# Enquiry Form Submission Audit (Full Site)

Date: 2026-02-15  
Scope: Theme forms, `/crm/` front-end forms, MU-plugin ingestion/integrations, and form-related JS/nonce usage.

## Commands used for discovery

```bash
rg -n "form" wp-content/themes/hello-elementor-child
rg -n "admin_post_|wp_ajax_|admin-ajax.php|admin-post.php|wp_rest|register_rest_route" wp-content/themes/hello-elementor-child wp-content/mu-plugins/peracrm
rg -n "nonce|wp_create_nonce|wp_verify_nonce|check_ajax_referer|wp_nonce_field|wp_rest" wp-content/themes/hello-elementor-child wp-content/mu-plugins/peracrm
rg -n "<form" wp-content/themes/hello-elementor-child wp-content/mu-plugins/peracrm/inc
rg -n "add_action\(\s*'admin_post|add_action\(\s*\"admin_post|add_action\(\s*'wp_ajax|add_action\(\s*\"wp_ajax|register_rest_route" wp-content/themes/hello-elementor-child/inc wp-content/mu-plugins/peracrm/inc
```

---

## A) Inventory + routing map

## 1) Theme/custom enquiry forms

| Form key | Render locations | Submit mechanism | Handler(s) | Security controls | Response/UX | Logging/observability |
|---|---|---|---|---|---|---|
| SR enquiry (`sr_action`) | Shared partial `parts/enquiry-form.php`; rendered in property templates and sell/rent CTA partials including `parts/form-sell-rent.php`, `single-property.php`, `single-bodrum-property.php`, plus dedicated form in `page-book-a-consultancy.php`. | Native POST to same URL (`action=""`), processed on `init`. | `pera_maybe_handle_citizenship_enquiry()` -> `pera_handle_citizenship_enquiry()` branch `sr_action` in `inc/enquiry.php`; ingestion mirror in `peracrm_ingest_theme_enquiries()` with nonce/honeypot checks in MU plugin. | Nonce field `sr_nonce` + `wp_verify_nonce`; honeypot `sr_company`; field sanitization (`sanitize_text_field`, `sanitize_email`, etc.). | `wp_safe_redirect` back to referer with `sr_status=sent|failed` and anchors (`#contact` or `#contact-form`). | Added optional debug logs behind `PERA_CRM_DEBUG_FORMS`; MU ingest debug already behind `PERACRM_DEBUG_INGEST`. |
| Favourites enquiry (`fav_enquiry_action`) | `page-favourites.php` | Native POST to same URL, processed on `init`. | Same theme handler function (favourites branch) + MU ingestion (`theme_favourites_form`). | Nonce `fav_nonce`; honeypot `fav_company`; required fields validated; post IDs constrained to valid published properties. | Redirect with `enquiry=sent/failed#favourites-enquiry`; user-facing success block. | Added `PERA_CRM_DEBUG_FORMS` route/nonce/mail logs + existing MU ingest logs. |
| Citizenship enquiry (`pera_citizenship_action`) | `page-citizenship.php` | Native POST to page permalink, processed on `init`. | Same theme handler function (citizenship branch) + MU ingestion (`theme_citizenship_form`). | Nonce `pera_citizenship_nonce`; field sanitization; preferred-contact array sanitized. | Redirect to `/citizenship-by-investment/?enquiry=ok|mail-failed#citizenship-form`; success/failure alert rendered. | Added `PERA_CRM_DEBUG_FORMS` route/nonce/mail logs + existing MU ingest logs. |
| Client portal profile update (`pera_client_portal_update_profile`) | `page-client-portal.php` | `admin-post.php` POST | `admin_post_pera_client_portal_update_profile` -> `pera_handle_client_portal_profile_update()` in `inc/client-portal.php`. | `check_admin_referer`; logged-in guard; strict allowed values for preferred contact; sanitized phone + budgets. | Redirect back with `updated=1`. | No dedicated debug logger; normal WP errors/redirect flow. |

Notes:
- `wp-content/themes/hello-elementor-child/js/enquiry-nonce.js` is now present and enqueued on enquiry-bearing templates/single pages. It refreshes public enquiry nonces on submit when page age is stale.

## 2) Front-end CRM forms (`/crm/`)

| Form area | Render file(s) | Submit mechanism | Handler(s) | Security controls | Response/UX |
|---|---|---|---|---|---|
| Create lead | `page-crm-new.php` | POST to `/crm/new/` route (not `admin-post`) | `pera_crm_handle_new_lead()` in `inc/crm-router.php` (hooked on `init`) | login + role/cap checks (`edit_crm_clients`), nonce `pera_crm_create_lead_nonce`, required field and source whitelist validation | Redirects to `/crm/new/` with error code or new `/crm/client/{id}/` |
| Client profile/status/forms and reminders | `page-crm.php`, `page-crm-client.php` | `admin-post.php` | MU plugin actions in `inc/admin/admin.php` routed to handlers in `inc/admin/actions.php` | Per-action nonce checks (`check_admin_referer`/`wp_verify_nonce`), capability and advisor-scope checks | Redirect back with `peracrm_notice`/`crm_notice` flags |
| Deal create/update/delete | `page-crm-client.php` | `admin-post.php` | `peracrm_handle_create_deal`, `peracrm_handle_update_deal`, `peracrm_handle_delete_deal` | Shared validator `peracrm_is_valid_deal_submission_request` enforces matching action + nonce; caps enforced | Safe redirects with success/failure notice |
| Property search (front-end CRM helper) | `page-crm-client.php` + `js/crm.js` | `admin-ajax.php?action=pera_crm_property_search` | `wp_ajax_pera_crm_property_search` in `inc/crm-client-view.php` | nonce from localized script (`propertySearchNonce`) verified server-side; capability checks on client scope | `wp_send_json_success/error` |

## 3) MU-plugin ingestion + integrations

- `wp-content/mu-plugins/peracrm/inc/integrations/enquiries.php`
  - Captures theme enquiry posts (`sr_action`, `fav_enquiry_action`, `pera_citizenship_action`) with nonce + honeypot checks before ingest.
  - Runs on both `init` and `template_redirect`; dedup guard `peracrm_ingest_mark_flow_handled()` prevents duplicate ingest in same request.
  - Deduplicates activity by fingerprint window via prepared SQL query.
- Additional endpoints:
  - `admin-post` action handlers are registered in `inc/admin/admin.php`.
  - REST routes are registered in `inc/rest.php` and `inc/rest/push.php` with explicit permission callbacks and `wp_rest` nonce checks in protected flows.

## 4) Nonce + JS submission touchpoints

- Enquiry forms use server-side `wp_nonce_field` hidden inputs and still verify server-side in PHP.
- Added optional public AJAX nonce refresh endpoint: `wp_ajax_nopriv_pera_get_enquiry_nonces` / `wp_ajax_pera_get_enquiry_nonces` in `inc/enquiry.php`.
- Added `js/enquiry-nonce.js` helper: if page age exceeds threshold, it refreshes `sr_nonce`, `fav_nonce`, and `pera_citizenship_nonce` just before submit.
- `js/crm.js` uses localized nonce for property-search AJAX.
- `js/favourites.js` uses localized nonce (`pera_favourites`) for favourites AJAX actions.

---

## B) Hard audit checks + findings

## 1) Duplicate/conflicting handlers

Findings:
- No duplicate `add_action` registrations for the same `admin_post_*` or `wp_ajax_*` hook across scanned theme + MU plugin files.
- Theme enquiry flow and MU ingest are intentionally parallel but non-conflicting: one sends email/UX redirect; one ingests CRM event. Ingest code includes explicit same-request dedup guard and 5-minute fingerprint dedup for event writes.
- No duplicate JavaScript submit listeners attached to SR/favourites/citizenship forms.

Risk noted:
- Inconsistency in front-end CRM form action host: one delete-client form used `admin_url('admin-post.php')` while others used `home_url('/wp-admin/admin-post.php')`. This can cause auth-cookie host mismatch in split `WP_HOME`/`WP_SITEURL` setups.

## 2) Nonce lifecycle + caching conflicts

Findings:
- All audited write forms use server-rendered nonces and server-side verification.
- Enquiry forms now support optional JS nonce refresh before submit on stale pages (`js/enquiry-nonce.js`) while preserving strict server-side nonce verification.
- Current UX on nonce failure is now safe redirect with generic failure flags for public enquiry forms; CRM flows continue to use their existing redirect/error handling.

Recommendation:
- Exclude high-traffic enquiry form pages from aggressive full-page caching, or vary cache by login/cookies where applicable.

## 3) Endpoint correctness

Findings:
- `admin-post` actions in front-end CRM forms include `action=` and corresponding `admin_post_{action}` registrations.
- Front-end public forms (SR/favourites/citizenship) intentionally use native same-page POST + `init` handler, not `admin-post`.
- AJAX handlers using front-end context return `wp_send_json_*` and verify nonce.
- REST routes are registered with permission callbacks; protected CRM REST uses `wp_rest` nonce expectations for cookie-auth contexts.

Fix applied:
- Standardized remaining front-end CRM delete form action to `home_url('/wp-admin/admin-post.php')` for cookie-host consistency.

## 4) Input validation + sanitization

Findings:
- Theme enquiry handler sanitizes all expected fields before use.
- MU admin handlers enforce nonces/caps and generally sanitize via helper APIs.
- Ingestion SQL uses `$wpdb->prepare` in dedup query.

Fix applied:
- Enquiry nonce values are now unslashed + sanitized before `wp_verify_nonce` (safer and consistent with WP patterns).

## 5) Mail delivery reliability

Findings:
- Enquiry email delivery uses `wp_mail()` in theme handler; UI reflects send status through query arg redirects.
- Potential mismatch risk remains where mail transport fails silently beyond boolean status; no structured logger existed for enquiry mail failures.

Fix applied:
- Added debug logging (off by default) behind `PERA_CRM_DEBUG_FORMS`, including handler hits, nonce pass/fail reasons, spam trap, and `wp_mail` failure points.

---

## C) Implemented minimal fixes

1. Enhanced `PERA_CRM_DEBUG_FORMS` logging with request correlation ID (`rid`), writable-target checks, and uploads log rotation (`uploads/pera-forms.log` -> `.1` when over 5MB) with `error_log` fallback.
2. Added request-scoped guard in `pera_handle_citizenship_enquiry()` to prevent duplicate processing in one request.
3. Replaced nonce-failure hard dies with safe redirects carrying generic failure flags (`sr_status=failed`, `enquiry=failed`) and `reason=nonce` while keeping detailed cause in logs only; unified SR success to `sr_status=sent` (removed `sr_success`).
4. Added optional nonce refresh AJAX endpoint and front-end helper (`js/enquiry-nonce.js`) for stale-page submit resilience, without weakening server-side nonce checks.
5. Standardized front-end `admin-post.php` action host usage to `home_url('/wp-admin/admin-post.php')` in remaining theme template (`page-client-portal.php`).

---

## D) Verification/test plan

## Automated checks run in this audit session

```bash
php -l wp-content/themes/hello-elementor-child/inc/enquiry.php
php -l wp-content/themes/hello-elementor-child/functions.php
php -l wp-content/themes/hello-elementor-child/page-crm-client.php
php -l wp-content/themes/hello-elementor-child/page-client-portal.php
php -l wp-content/themes/hello-elementor-child/parts/enquiry-form.php
php -l wp-content/themes/hello-elementor-child/page-favourites.php
```

## Manual test matrix (recommended)

1. Logged-out submissions
   - SR form on sell/rent/property pages (expected: accepted, redirected with `sr_status=sent` on valid payload).
   - Citizenship form (expected: `enquiry=ok`).
   - Favourites form (with/without login, depending required fields).

2. Logged-in CRM submissions
   - `/crm/new/` lead create with valid nonce, then invalid nonce.
   - `/crm/client/{id}/` reminder add/update, profile save, deal create/update/delete.

3. Failure-mode checks
   - Remove nonce field -> expect safe redirect with generic failure flag (`reason=nonce`) for public enquiry forms, or route-specific invalid handling for CRM actions.
   - Invalid email in `/crm/new/` -> expect `crm_error=invalid_email`.
   - Trigger honeypot fields (`sr_company`, `fav_company`) -> expect spam rejection and no processing.

4. Caching scenario check
   - Visit an enquiry page, cache HTML, wait nonce expiry window, resubmit.
   - Confirm failure behavior is user-safe and no partial processing occurs.

## Optional WP-CLI smoke checklist

```bash
wp rewrite flush --hard
wp option get home
wp option get siteurl
wp cron event list --fields=hook,next_run_relative --format=table
```

If `home` and `siteurl` hosts differ, re-check all front-end CRM `admin-post.php` form actions are `home_url('/wp-admin/admin-post.php')`.


## Follow-up hardening verification (this update)

- Verified front-end admin-post form actions in theme templates resolve to `home_url('/wp-admin/admin-post.php')`.
- Verified `enquiry-nonce.js` enqueue + localized settings and AJAX route registration (`pera_get_enquiry_nonces`).
- Verified nonce failure UX now redirects with generic failure state and form-level error message rendering.

- Verified nonce-refresh AJAX response includes `sr_nonce`, `fav_nonce`, `pera_citizenship_nonce`, and `generated_at`.


## Endpoint response shape (final)

`POST /wp-admin/admin-ajax.php?action=pera_get_enquiry_nonces` returns:

```json
{
  "success": true,
  "data": {
    "sr_nonce": "<nonce>",
    "fav_nonce": "<nonce>",
    "pera_citizenship_nonce": "<nonce>",
    "generated_at": 1730000000
  }
}
```

## Manual nonce-refresh test snippet

```bash
curl -sS -X POST "https://peraproperty.com/wp-admin/admin-ajax.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data "action=pera_get_enquiry_nonces"
```

Expected: JSON `success: true` with `sr_nonce`, `fav_nonce`, `pera_citizenship_nonce`, and `generated_at`.

## Behavior changes (final)

- SR form status parameter is now unified as `sr_status=sent|failed` (legacy `sr_success` removed).
- Public nonce failures redirect safely with generic flags (`sr_status=failed` or `enquiry=failed`) and `reason=nonce`.

## Snippet verification payload

### 1) `inc/enquiry.php` header (top through debug/log helpers)

```php
<?php
/**
 * Enquiry Handlers (Sell / Rent / Property / Citizenship)
 * Location: /inc/enquiry.php
 *
 * Loads only when required (see functions.php loader snippet below).
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! defined( 'PERA_CRM_DEBUG_FORMS' ) ) {
  define( 'PERA_CRM_DEBUG_FORMS', false );
}

function pera_forms_get_request_id() {
  static $request_id = null;

  if ( null !== $request_id ) {
    return $request_id;
  }

  if ( function_exists( 'wp_generate_uuid4' ) ) {
    $request_id = sanitize_key( wp_generate_uuid4() );
  }

  if ( empty( $request_id ) ) {
    $request_id = sanitize_key( uniqid( 'pera_forms_', true ) );
  }

  return $request_id;
}

function pera_forms_debug_log( $message, array $context = array() ) {
  if ( ! ( defined( 'PERA_CRM_DEBUG_FORMS' ) && PERA_CRM_DEBUG_FORMS ) ) {
    return;
  }

  $context['rid'] = pera_forms_get_request_id();

  $safe_context = array();
  foreach ( $context as $key => $value ) {
    $safe_key = sanitize_key( (string) $key );
    if ( is_scalar( $value ) || null === $value ) {
      $safe_context[ $safe_key ] = $value;
    }
  }

  $line = '[Pera forms] ' . sanitize_text_field( (string) $message ) . ' ' . wp_json_encode( $safe_context );

  $upload_dir = wp_get_upload_dir();
  $log_dir    = isset( $upload_dir['basedir'] ) ? trailingslashit( $upload_dir['basedir'] ) : '';
  $log_file   = $log_dir ? $log_dir . 'pera-forms.log' : '';

  if ( $log_dir && ( is_dir( $log_dir ) || wp_mkdir_p( $log_dir ) ) && is_writable( $log_dir ) ) {
    $can_write_target = ( ! file_exists( $log_file ) && is_writable( $log_dir ) ) || ( file_exists( $log_file ) && is_writable( $log_file ) );

    if ( $can_write_target ) {
      clearstatcache( true, $log_file );
      if ( file_exists( $log_file ) && filesize( $log_file ) > 5 * 1024 * 1024 ) {
        $rotated = $log_dir . 'pera-forms.log.1';
        if ( file_exists( $rotated ) && is_writable( $rotated ) ) {
          @unlink( $rotated );
        }
        @rename( $log_file, $rotated );
      }

      @file_put_contents( $log_file, gmdate( 'c' ) . ' ' . $line . PHP_EOL, FILE_APPEND );
      return;
    }
  }

  error_log( $line );
}

function pera_forms_nonce_failure_redirect( $form_key, $fallback_url, $query_arg, $anchor = '' ) {
  $redirect = ! empty( $_POST['_wp_http_referer'] )
    ? esc_url_raw( wp_unslash( $_POST['_wp_http_referer'] ) )
    : home_url( $fallback_url );

  $redirect = preg_replace( '/#.*$/', '', $redirect );
  $redirect = add_query_arg(
    array(
      $query_arg => 'failed',
      'reason'   => 'nonce',
    ),
    $redirect
  );

  if ( $anchor ) {
    $redirect .= $anchor;
  }

  pera_forms_debug_log(
    'nonce_redirect',
    array(
      'form_key'     => $form_key,
      'handler'      => __FUNCTION__,
      'nonce_status' => 'fail',
      'redirect'     => $redirect,
    )
  );

  wp_safe_redirect( $redirect );
  exit;
}

/**
```

### 2) Full AJAX nonce refresh endpoint + hook registrations

```php
function pera_ajax_get_enquiry_nonces() {
  $ip = '';
  if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
    $parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
    $ip    = trim( $parts[0] );
  } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
    $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
  }

  $rate_key = 'pera_enquiry_nonce_refresh_' . md5( $ip );
  if ( get_transient( $rate_key ) ) {
    pera_forms_debug_log( 'nonce_refresh_rate_limited', array( 'form_key' => 'public_enquiry_nonce_refresh', 'handler' => __FUNCTION__ ) );
    wp_send_json_error( array( 'message' => 'Too many requests.' ), 429 );
  }

  set_transient( $rate_key, 1, 30 );

  $payload = array(
    'sr_nonce'               => wp_create_nonce( 'pera_seller_landlord_enquiry' ),
    'fav_nonce'              => wp_create_nonce( 'pera_favourites_enquiry' ),
    'pera_citizenship_nonce' => wp_create_nonce( 'pera_citizenship_enquiry' ),
    'generated_at'           => time(),
  );

  pera_forms_debug_log( 'nonce_refresh', array( 'form_key' => 'public_enquiry_nonce_refresh', 'handler' => __FUNCTION__, 'nonce_status' => 'refresh' ) );
  wp_send_json_success( $payload );
}
add_action( 'wp_ajax_pera_get_enquiry_nonces', 'pera_ajax_get_enquiry_nonces' );
add_action( 'wp_ajax_nopriv_pera_get_enquiry_nonces', 'pera_ajax_get_enquiry_nonces' );
```

### 3) First ~40 lines of `pera_handle_citizenship_enquiry()`

```php
function pera_handle_citizenship_enquiry() {
  static $handled = false;

  if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    return;
  }

  $has_enquiry_key = isset( $_POST['sr_action'] ) || isset( $_POST['pera_citizenship_action'] ) || isset( $_POST['fav_enquiry_action'] );
  if ( ! $has_enquiry_key ) {
    return;
  }

  if ( $handled ) {
    pera_forms_debug_log( 'double_process_guard', array( 'handler' => __FUNCTION__ ) );
    return;
  }

  $handled = true;

  /* =========================================
   * A) SELL / RENT / PROPERTY ENQUIRY BRANCH
   * Trigger: <input type="hidden" name="sr_action" value="1">
   * ========================================= */
  if ( isset( $_POST['sr_action'] ) ) {
    pera_forms_debug_log(
      'handler_hit',
      array(
        'form_key' => 'sr_action',
        'route'    => 'init',
        'handler'  => __FUNCTION__,
      )
    );

    // Security: SR nonce
    $sr_nonce = isset( $_POST['sr_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['sr_nonce'] ) ) : '';
    if ( '' === $sr_nonce || ! wp_verify_nonce( $sr_nonce, 'pera_seller_landlord_enquiry' ) ) {
      $failed_context = isset( $_POST['form_context'] ) ? sanitize_text_field( wp_unslash( $_POST['form_context'] ) ) : '';
      $failed_anchor  = ( 'property' === $failed_context ) ? '#contact-form' : '#contact';
      pera_forms_debug_log( 'nonce_fail', array( 'form_key' => 'sr_action', 'handler' => __FUNCTION__, 'nonce_status' => '' === $sr_nonce ? 'missing' : 'invalid' ) );
      pera_forms_nonce_failure_redirect( 'sr_action', '/', 'sr_status', $failed_anchor );
```
