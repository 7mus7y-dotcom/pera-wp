# Front-end CRM timeline "View details" parity audit

## Admin timeline source

- CRM client metaboxes are registered in `peracrm_register_metaboxes()`.
- The Timeline metabox (`peracrm_client_timeline`) is added for `crm_client` edit screens and rendered by `peracrm_render_timeline_metabox()`.
- Timeline data comes from `peracrm_timeline_get_items()` which merges notes, reminders, and activity.
- For enquiry activity rows, structured details are built server-side (inline, not AJAX) by `peracrm_timeline_render_enquiry_details()` and emitted as `<details><summary>View details</summary>…`.

## Front-end timeline source

- Front-end CRM client view is rendered in `page-crm-client.php`.
- Timeline data is loaded in `pera_crm_client_view_load_data()` via `pera_crm_client_view_timeline_items()`.
- Front-end now reuses the same activity source (`peracrm_timeline_get_items()`) and prepares display metadata/details via `pera_crm_client_view_prepare_timeline_items()`.

## Dataset comparison and parity

For the same activity record, admin and front-end now share:

- Type label, relative timestamp, title, summary detail, and meta line.
- Enquiry details rows from payload (property/page/context/form/message/UTM/referrer where present).

Difference retained by permission design:

- Managers/admins (full timeline details access): receive the same full enquiry details renderer as admin.
- Employees: receive a safe subset (message/context/page/property/UTM/referrer) and do **not** receive personal contact-style fields (for example email/phone/name-like fields) from enquiry payload details.

## What changed

1. Added `event_payload` into normalized activity timeline items so front-end can safely re-render details according to role.
2. Added front-end timeline preparation helpers to:
   - compute label/time/meta parity with admin helpers,
   - render enquiry details accordion with role-aware field visibility.
3. Updated front-end timeline markup to include:
   - header row with type badge + relative time,
   - detail text,
   - structured "View details" accordion,
   - meta line.
4. Added minimal front-end timeline CSS for accordion/table styling, matching admin visual behaviour.


5. Root cause + fix follow-up:
   - `peracrm_timeline_get_items()` and enquiry detail helpers lived in an admin-only include, so front-end requests often fell back to a reduced timeline dataset with no `event_payload`/`details_html`.
   - Bootstrapping now loads timeline helpers for front-end requests so detail rendering functions are available outside wp-admin.
6. Enquiry detection in `peracrm_timeline_normalize_activity()` now treats activity as enquiry when:
   - `event_type` contains `enquiry`, **or**
   - payload keys indicate enquiry context (`message`, `page_url`, `form`, `utm*`, `referrer*`, `property*`).
7. Added a temporary admin-only front-end debug line per timeline row to report payload/detail preparation counts (`payload_fields`, `details_chars`) without exposing personal data.
