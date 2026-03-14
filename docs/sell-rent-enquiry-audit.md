# Sell / Rent With Pera Enquiry Audit

## A) Audit summary

- `sell-with-pera` and `rent-with-pera` are rendered by dedicated PHP page templates (`page-sell-with-pera.php`, `page-rent-with-pera.php`) and both inject the same shared partial `parts/enquiry-form.php` with different `context`/`form_context` args. They are **not** using separate back-end handlers.
- Both pages post to themselves (`action=""`) with hidden `sr_action=1`, nonce `sr_nonce`, and `form_context`, then route into the same `sr_action` branch in `inc/enquiry.php` via an `init` hook.
- Most likely issues are in **shared logic**, especially nonce-expiry/refresh behavior, global rate limiting, and mail-failure UX mapping to generic `sr_status=failed`. These would affect sell/rent similarly.

---

## B) Rendering path for `sell-with-pera`

1. WordPress page template: `page-sell-with-pera.php` (`Template Name: Sell with Pera`).
2. Template includes standard header/footer and static section markup; no `the_content()` call in this template region, so no direct Elementor content rendering in this file path.
3. Shared enquiry UI partial loaded via:
   - `get_template_part('parts/enquiry-form', ..., [context => sell, form_context => sell-page])`.
4. Also includes shared non-form partial `parts/about-pera`.

---

## C) Rendering path for `rent-with-pera`

1. WordPress page template: `page-rent-with-pera.php` (`Template Name: Rent with Pera`).
2. Static template rendering with header/footer; no direct `the_content()` in this template path either.
3. Same shared enquiry partial loaded via:
   - `get_template_part('parts/enquiry-form', ..., [context => rent, form_context => rent-page])`.
4. Also includes shared non-form partial `parts/about-pera`.

---

## D) Submission/handler path for both

1. **Front-end form markup** (shared):
   - `sr_action=1`
   - `form_context` hidden field
   - nonce: `wp_nonce_field('pera_seller_landlord_enquiry', 'sr_nonce')`
   - honeypot `sr_company`
   - sell sets hidden `sr_intent=sell`; rent renders radio `sr_intent` options.
2. **Enquiry handler loading**:
   - `inc/modules/enquiry-loader.php` loads `inc/enquiry.php`:
     - always for relevant POSTs,
     - otherwise by template/slug checks.
3. **Handler entry**:
   - `pera_maybe_handle_citizenship_enquiry()` on `init`,
   - dispatches to `pera_handle_citizenship_enquiry()` when `sr_action` exists.
4. **SR branch (`sell/rent/property`)**:
   - nonce verification against `pera_seller_landlord_enquiry`,
   - honeypot check,
   - context whitelist,
   - sanitize fields,
   - build subject/body by context and intent,
   - send email via `pera_enquiry_send_logged_mail()`,
   - redirect back with `sr_status=sent|failed` and `#contact` (or `#contact-form` for property).
5. **Nonce refresh JS path**:
   - `enquiry-nonce.js` intercepts stale-page submit (>900s), fetches fresh nonces from `admin-ajax.php?action=pera_get_enquiry_nonces`, then resubmits.
   - Script enqueued on these templates via `$is_enquiry_page`.
   - AJAX nonce endpoint in `inc/enquiry.php`.

---

## E) Ranked list of most likely causes

1. **Likely bug / likely top contributor: stale nonce refresh dependency can silently fail, causing intermittent `failed` on older open tabs.**
   If JS refresh fails (blocked AJAX/cache/adblock/network), submit proceeds with expired nonce and server returns `sr_status=failed&reason=nonce`. JS intentionally swallows errors and falls back to normal submit.

2. **Likely bug: global rate-limit is very low (more than 5/day per IP+UA across all enquiry forms).**
   Could unexpectedly block legitimate submissions, especially office/shared IP traffic, and presents as generic failure redirect.

3. **Likely bug: honeypot failure redirects to homepage instead of referer page.**
   In SR branch, honeypot redirect hardcodes `home_url('/')#contact`, unlike other failure paths that respect referer. This can look like broken submission flow specifically from sell/rent pages.

4. **Possible contributor: mail failure is surfaced as generic form failure (`sr_status=failed`), conflating delivery issues with validation issues.**
   If `wp_mail` fails, user sees same “could not be submitted” state, even though handler path executed correctly.

5. **Possible contributor: loader/template slug assumptions for GET context are brittle if live page slug/template changed.**
   POST safety is good (loads on POST regardless), but non-POST behavior (e.g., nonce refresh on page views) depends on template/slug checks for script enqueue and handler loading conditions. Mismatch can break stale nonce refresh.

---

## F) Evidence with file paths and code references

- **Shared component + shared handler (not page-specific branches):**
  `sell` and `rent` both call `parts/enquiry-form.php` with only context differences; both route into `sr_action` branch in same function.

- **Form fields/action/nonce/context chain:**
  Hidden fields and nonce names in form match handler expectations (`sr_action`, `sr_nonce`, `form_context`). No obvious mismatch here.

- **Sell vs rent implementation differences:**
  Sell hard-sets `sr_intent=sell`; rent provides radio for `rent` / `short-term`. Handler subject logic supports both. No direct copy/paste mismatch found in this pair.

- **Redirect and status behavior:**
  Generic SR success/failure redirect with `#contact`; nonce fail and honeypot differ in behavior.

- **Nonce-refresh coupling risk:**
  Refresh script only runs if page flagged by template checks; refresh endpoint rate-limited per IP every 30s and failures are silent client-side.

- **Email logging/auto-reply coupling:**
  Mail wrapper logs status and can include last WP mail error; auto-reply toggle only affects client auto-reply, not primary admin email send. No direct branch-specific break introduced for sell/rent identified in this audit.

---

## G) Minimal recommended fixes (audit only, no code changes)

1. **Harden nonce refresh UX path**
   - Add explicit user-facing fallback when nonce refresh AJAX fails, instead of silent fallback submit with stale nonce.
   - Consider refreshing nonce proactively before expiry on idle pages.

2. **Adjust SR global rate-limit policy**
   - Raise threshold or scope by form/context and include better failure messaging (`reason=rate_limit`) to avoid false “broken form” reports.

3. **Normalize honeypot failure redirect behavior**
   - Use referer-based redirect (same page + anchor) consistently, not homepage hard redirect.

4. **Differentiate mail failures from validation/nonce failures in UI/query params**
   - e.g., `sr_status=mail_failed` to reduce confusion when transport is the root issue.

5. **Verify live page template/slug assignment in WP admin**
   - Ensure affected pages still use expected templates/slugs so enqueue/loader conditions stay valid.
