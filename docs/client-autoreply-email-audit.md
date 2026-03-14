# Client Auto-Reply Email Audit (Post SMTP Deactivation)

## Probable root cause (first)

**The client auto-reply logic is in custom theme code, not in Post SMTP.**  
`hello-elementor-child/inc/enquiry.php` defines `pera_send_enquiry_autoreply()` which directly calls `wp_mail()` for client replies, and this runs from the form handler on `init`. Deactivating Post SMTP would remove/alter transport hooks, but **would not disable this trigger logic**.

---

## A) Audit summary

- The only executable email sends found in this repo are `wp_mail()` calls in `wp-content/themes/hello-elementor-child/inc/enquiry.php` (4 calls total).
- Auto-replies are explicitly composed and sent for:
  - property enquiries (`"We received your enquiry"` / Bodrum variant),
  - favourites enquiries (`"We received your favourites enquiry"`),
  - citizenship enquiries (`"We received your citizenship enquiry"`).
- Form handling is custom and triggered by hidden POST flags (`sr_action`, `fav_enquiry_action`, `pera_citizenship_action`) from theme templates/partials, then processed on `init` by `pera_maybe_handle_citizenship_enquiry()`.
- The enquiry handler is conditionally loaded by `inc/modules/enquiry-loader.php`, which force-loads it on relevant POST requests, so mail logic still runs regardless of page-template routing mismatches.
- MU-plugin `peracrm` captures the same form submissions for CRM ingestion (logging/client linkage), but does not call `wp_mail()` in that integration path; it is parallel data ingestion, not autoresponder transport/trigger logic.
- No in-repo evidence of Elementor Pro form-email hooks, Contact Form 7, Fluent Forms, WPForms, Gravity Forms, or PHPMailer direct usage was found in inspected code paths.

---

## B) Ranked list of most likely causes

1. **Custom theme autoresponder path in `inc/enquiry.php` (highest confidence).**
   - Directly builds and sends client auto-replies via `wp_mail()` after successful form submission branches.
2. **Custom favourites/citizenship auto-reply branches in the same handler.**
   - Independent client auto-replies for those flows, also via `wp_mail()`.
3. **Post SMTP expectation mismatch (transport vs trigger).**
   - Trigger logic is custom and still active; transport can fall back to default WordPress/PHP mail path if no SMTP plugin is active.

---

## C) Evidence map (source / trigger / mail type / Post SMTP dependency)

### 1) Theme form handler (primary sender)
- **File:** `wp-content/themes/hello-elementor-child/inc/enquiry.php`
- **Function/hook:** `pera_maybe_handle_citizenship_enquiry()` on `init`; calls `pera_handle_citizenship_enquiry()`.
- **Trigger condition:** POST with `sr_action` or `fav_enquiry_action` or `pera_citizenship_action`.
- **Sends:**
  - admin notifications (`wp_mail($to=info@...)`) for SR/favourites/citizenship.
  - client auto-replies via `pera_send_enquiry_autoreply()` for property/favourites/citizenship branches.
- **Depends on Post SMTP?:** **No (for triggering).** Uses core `wp_mail()` directly; Post SMTP would only have been transport/logging middleware.

### 2) Theme enquiry loader (ensures handler is active)
- **File:** `wp-content/themes/hello-elementor-child/inc/modules/enquiry-loader.php`
- **Function/hook:** anonymous `add_action('init', ..., 1)` that includes `inc/enquiry.php` on relevant POST and specific routes.
- **Trigger condition:** relevant POST keys or matching templates/slugs.
- **Sends:** none itself; enables sender file loading.
- **Depends on Post SMTP?:** No.

### 3) MU-plugin CRM ingestion (parallel, non-mail)
- **File:** `wp-content/mu-plugins/peracrm/inc/integrations/enquiries.php`
- **Function/hook:** `peracrm_ingest_theme_enquiries()` on `init` and `template_redirect`.
- **Trigger condition:** same POST form flags.
- **Sends:** CRM ingestion/event logging; no `wp_mail()` calls found in this file.
- **Depends on Post SMTP?:** No.

### 4) Cron/background checks
- **File:** `wp-content/mu-plugins/peracrm/inc/cron/push_cron.php`
- **Function/hook:** schedules `peracrm_push_digest`; executes push digest handler (web push), not email.
- **Sends:** push notifications (not mail).
- **Depends on Post SMTP?:** No.

---

## D) Safe remediation options (no code changes applied)

1. **Least invasive:** disable only client auto-reply branches while keeping admin notifications.
2. Gate auto-reply by form context/feature flag (e.g., env constant or option).
3. Route all mail through a central helper and add explicit toggles + logging by mail type (admin vs client).
4. Remove/disable theme `init` handler for selected forms if another form system should own notifications.
5. **Most invasive:** refactor all enquiry handling into one service layer (single trigger, explicit notification policy, transport abstraction, audit logs).

---

## E) Should Post SMTP deactivation have stopped these emails?

**No — not by itself.**  
Based on this codebase, Post SMTP was almost certainly acting as a **transport/logging layer**, while **trigger logic lives in custom theme code calling `wp_mail()`**. So deactivating Post SMTP should not be expected to stop autoresponder triggers; it only changes how mail is delivered/logged afterward.

---

## Most likely live path (end-to-end)

1. Front-end form POST includes hidden action key (e.g., `sr_action=1`).
2. On `init`, theme loader ensures `inc/enquiry.php` is loaded for that POST.
3. `pera_maybe_handle_citizenship_enquiry()` dispatches to master handler.
4. Handler validates nonce/spam/rate-limit and composes admin email + optional client auto-reply text.
5. `wp_mail()` is called directly for delivery (through whatever active WP mail transport exists).
