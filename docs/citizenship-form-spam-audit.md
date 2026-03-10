# Citizenship Enquiry Form Spam Audit

## Short audit summary
- The citizenship form is a native WordPress POST form on `page-citizenship.php` posting back to the same page URL.
- Submission handling is done in the theme on `init` in `pera_handle_citizenship_enquiry()`, which sends email via `wp_mail()` and redirects with status query params.
- CRM ingestion is separately performed by the MU plugin (`peracrm`) on `init` / `template_redirect` when the same POST markers and nonce are present.
- Current anti-spam for citizenship is effectively **nonce-only**; there is no captcha, no honeypot for this specific form, no minimum-time trap, and no submission throttling for the form endpoint.
- This leaves the form vulnerable to high-frequency automated submissions containing links/promotional payloads or nonsense text that still pass sanitization.

## Exact files and functions involved

### 1) Form rendering (frontend)
- File: `wp-content/themes/hello-elementor-child/page-citizenship.php`
- Section: `#citizenship-form`
- Form characteristics:
  - `method="post"`
  - `action="<?php echo esc_url( get_permalink() ); ?>"` (same-page POST)
  - Hidden action flag: `pera_citizenship_action=1`
  - Nonce field: `pera_citizenship_nonce`
  - Fields: `name`, `phone`, `email`, `contact_method[]`, `enquiry_type`, `family`, `message`, `policy`

### 2) JS affecting submission
- File: `wp-content/themes/hello-elementor-child/js/enquiry-nonce.js`
- Purpose: if page age exceeds threshold (`max_age_seconds`, default 900), intercept submit, call AJAX action `pera_get_enquiry_nonces`, replace nonce fields, then submit.
- Important: this is **nonce-refresh UX**, not anti-spam. It does not add challenge/honeypot/throttle/content checks.

### 3) Backend handler (mail sender + redirect)
- File: `wp-content/themes/hello-elementor-child/inc/enquiry.php`
- Entrypoint:
  - `pera_maybe_handle_citizenship_enquiry()` hooked on `init`
  - Calls `pera_handle_citizenship_enquiry()` for `POST` requests carrying one of: `sr_action`, `pera_citizenship_action`, `fav_enquiry_action`
- Citizenship branch:
  - Verifies nonce `pera_citizenship_enquiry`
  - Sanitizes incoming fields
  - Builds email body and sends via `wp_mail()` to `info@peraproperty.com`
  - Sends auto-reply if user email validates
  - Redirects to `/citizenship-by-investment/?enquiry=ok|mail-failed#citizenship-form`

### 4) Backend ingestion into CRM flow
- File: `wp-content/mu-plugins/peracrm/inc/integrations/enquiries.php`
- Entry points:
  - `peracrm_ingest_theme_enquiries('init')` via `init`
  - `peracrm_ingest_theme_enquiries('template_redirect')` via `template_redirect`
- Citizenship detector:
  - `peracrm_ingest_should_capture_citizenship()` checks for `pera_citizenship_action` + valid nonce
- Ingestion action:
  - `peracrm_ingest_enquiry([...], peracrm_ingest_request_context(...))`
  - Captures email, name split, phone, message, preferred_contact, form context, raw fields.

---

## Current protection stack (citizenship form)

### Present
1. **Nonce validation**
   - Frontend emits `pera_citizenship_nonce`.
   - Theme handler verifies nonce before processing.
   - CRM ingestion verifies nonce before capture.

2. **Sanitization**
   - `sanitize_text_field`, `sanitize_email`, `sanitize_textarea_field`/`wp_kses_post` applied to fields.

3. **Duplicate processing guard (same request only)**
   - Theme handler uses static `$handled` to avoid double-run in one request.

### Not present for citizenship endpoint
1. **Captcha / challenge**: none found (Turnstile/Recaptcha/hCaptcha absent).
2. **Honeypot field**: none in citizenship form (exists for other forms like `sr_company`, `fav_company`, not this one).
3. **Time trap (minimum fill duration)**: none.
4. **Rate limiting / throttling on submission endpoint**: none.
5. **Submission-level IP-based spam rejection**: none.
6. **Duplicate-content detection**: none.
7. **Server-side semantic spam heuristics**:
   - no URL/promo-text blocking in `name`/`message`
   - no minimum quality/length checks for `message`
   - no numeric-pattern checks for fields like `family`.
8. **Server-side policy validation**: checkbox required in HTML, but no explicit backend rejection if missing.

> Note: there *is* rate limiting for nonce-refresh AJAX and auto-replies, but those do not protect core submission processing.

---

## Gap analysis against observed bot patterns

1. **URL or promo text inside Name**
   - Name is only sanitized as plain text; not validated against URL/promo patterns.
   - Result: `name="Best offer http://..."` reaches mail + CRM.

2. **Random numeric phone/family-member fields**
   - No strict format/quality rules beyond sanitization.
   - Result: junk numerics pass and are stored/sent.

3. **Short nonsense comments**
   - Message is optional and only sanitized.
   - No min meaningful length, token entropy, repetition heuristics, or keyword filtering.

4. **High-frequency repeated submissions**
   - Nonce is not a bot-prevention control; bots can fetch pages and reuse valid nonce windows.
   - No endpoint throttle by IP/session/fingerprint.
   - Therefore spam bursts (e.g., ~10/minute) are feasible.

---

## Risk notes
- **Operational risk now**: mailbox pollution, CRM contamination, noisy timelines, and consultant time waste.
- **Data-quality risk**: junk leads can degrade automation/analytics and obscure real enquiries.
- **Implementation risk constraints**:
  - This form already has working UX and downstream CRM ingestion hooks; safest approach is additive controls with early rejection and unchanged success path.
  - Avoid changing field names/payload shape used by current mail + CRM flows.

---

## Recommended patch order (minimal-risk layered hardening)

### 1) Add Cloudflare Turnstile (or equivalent) — highest impact, low UX friction
- Add widget to citizenship form template.
- Validate token server-side in theme handler **before** mail send and before CRM ingest capture gate.
- On failure: redirect with `enquiry=failed` and retain current anchor behavior.
- Keep fail-safe messaging consistent with existing alert pattern.

### 2) Add hidden honeypot field (citizenship-specific)
- Add hidden text field (e.g., `citizenship_company`) with clear label-hidden technique.
- Reject if non-empty in theme handler and ingestion detector.
- No user-facing change.

### 3) Add server-side validation rules (strict but conservative)
- Reject if name contains URL-like patterns (`http`, `www.`, domain TLD pattern) or high-risk promo tokens.
- Reject if form completed too quickly (hidden timestamp + minimum elapsed, e.g., 4–6 seconds).
- Reject missing/invalid captcha token.
- Add basic spam heuristics for `name`/`message`:
  - repeated characters/tokens,
  - very short low-information message when other fields indicate bot-like payload,
  - obvious unrelated promo terms.
- Keep thresholds conservative to avoid false positives.

### 4) Add submission throttling by IP/session
- Use transient-based key (`ip + form` and optionally `ip + ua`) with short rolling window.
- Suggested baseline: allow 2 submissions/5 minutes, then temporary block (e.g., 15 minutes).
- Return same generic failure UX to avoid attacker feedback.

### 5) Optional spam decision logging (recommended)
- Log blocked attempts with reason code, hashed IP or IP (based on policy), UA, timestamp, and request fingerprint.
- Store in dedicated option/transient table or plugin logger, behind debug/feature flag.
- Do not log full sensitive message bodies unless required.

---

## Compatibility notes (mail + CRM)
- Preserve existing submit endpoint, redirect contract, and field names to avoid breaking templates and analytics.
- Add anti-spam checks as early gates:
  1. in theme handler (authoritative mail send gate),
  2. mirrored in `peracrm_ingest_should_capture_citizenship()` so blocked spam never enters CRM.
- Maintain existing success path unchanged when checks pass.

---

## Codex-ready implementation prompt

```text
Implement minimal-risk spam hardening for the citizenship enquiry flow in this repo.

Scope:
- Theme form template: wp-content/themes/hello-elementor-child/page-citizenship.php
- Theme handler: wp-content/themes/hello-elementor-child/inc/enquiry.php
- CRM ingestion gate: wp-content/mu-plugins/peracrm/inc/integrations/enquiries.php

Requirements (in this order):
1) Add Cloudflare Turnstile to citizenship form
   - Render widget in the form.
   - Add server-side verification helper in theme handler.
   - Reject submit if token missing/invalid.

2) Add citizenship honeypot
   - Hidden field in form (e.g., citizenship_company).
   - Reject if populated in theme handler and peracrm_ingest_should_capture_citizenship().

3) Add server-side anti-spam validation
   - Reject URLs in name.
   - Add hidden form-start timestamp and reject too-fast submissions (<5s).
   - Reject obvious spam patterns in name/message (conservative regex/heuristics).
   - Keep existing sanitization and UX redirects.

4) Add transient-based throttling
   - Keyed by IP (+ optional UA/session), for citizenship form only.
   - Example: max 2 requests / 5 min, then block 15 min.
   - Use generic failure response.

5) Optional logging
   - Add lightweight log helper for blocked submissions with reason, IP (or hash), UA, and timestamp.
   - Feature flag via constant (default off).

Safety constraints:
- Do not change existing field names used by successful mail/CRM flows.
- Keep current redirect anchors/status behavior.
- Keep changes isolated to citizenship flow only.
- Follow existing code style and sanitization patterns.
- Add concise inline comments for each new anti-spam gate.

Validation:
- Confirm normal valid submissions still send mail and ingest CRM.
- Confirm blocked cases: invalid captcha, honeypot filled, too-fast submit, URL in name, throttle exceeded.
- Provide a short test checklist and rollback notes.
```
