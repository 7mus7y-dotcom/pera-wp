# Appointments and shared calendar

## Repository audit (main 71781b4d, before implementation)

The plugin bootstrap loads procedural repositories/services and owns the CRM shell, routing and assets. Clients are `crm_client` posts; lead state is in `peracrm_party`, distinct from deal stages. `peracrm_party_upsert_status()` is shared by frontend status AJAX, admin status/post saves, individual pipeline moves and bulk moves. Pipeline boards currently link to client workspaces; administrative mutations use admin-post handlers. The canonical viewing stage is `viewing_arranged` in `inc/stages.php`.

Client ownership resolves `assigned_advisor_user_id` and `crm_assigned_advisor` through the canonical helper; conflicting metadata fails closed. `peracrm_user_can_access_client()` is the authorization boundary. Advisor scope is a presentation filter, never the acting identity. Staff lists and permissions must be evaluated on the target blog. `peracrm_with_target_blog()` restores context in finally; `peracrm_table()` uses its current prefix.

`inc/client-panels.php` owns optional visibility, employee/client/blog preferences and protected panels. The new panel will use this registry and preserve an independent always-visible booking action. Activity goes into `crm_activity`, rendered by the existing client timeline. Advisor notes remain separate.

Schema version 41 is upgraded under the campaign schema lock/circuit breaker using additive dbDelta; fresh installs also run historic migrations. New tables must be added to that versioned path, with post-DDL verification before publishing the version. Existing tests are standalone PHP/JS plus disposable WordPress/MySQL single/multisite fixtures and Playwright in the campaign-foundations workflow. UI references: `docs/peracrm-ui-system-definition.md`, plugin `docs/client-panels.md`; reuse CRM form, section and semantic color tokens.

Rebased onto main **21cdc675** after PR #1580 removed the optional flush modifier; the appointment panel follows that updated section spacing.

## Implementation

Schema version **42** adds four InnoDB tables on the CRM target blog:

- `{prefix}peracrm_appointments`: independent records; UTC start/end, original IANA zone, six stable types, five statuses, location details, notes, actor timestamps and monotonically increasing version.
- `{prefix}peracrm_appointment_participants`: unique appointment/user pair; primary flag plus user/schedule index.
- `{prefix}peracrm_appointment_properties`: existing published `property` / `bodrum-property` IDs, unique associations and ordered tour positions. No property duplication.
- `{prefix}peracrm_appointment_requests`: actor/key receipt with payload hash and appointment reference, protecting creation, editing and stage-only reuse from duplicate submissions.

Lifecycle evidence uses existing `crm_activity`, with appointment references, time/status changes, assignment references and intentional-overlap evidence. Private/outcome note bodies remain on the appointment, rather than being copied into Advisor Notes or audit payloads. There is no second audit table.

Appointments join the existing additive schema definitions, installer, lock, circuit breaker and read-only verification, rather than introducing a second migrator. Required columns, primary/unique keys, operational indexes and InnoDB are verified before schema-version publication. No appointment backfill, client-stage conversion or destructive data rewrite is performed. A tightly scoped internal migration context permits historic stage migrations without imposing an interactive booking workflow. Existing client, deal, note and property records remain unchanged by v42 installation.

## Operations, transactions and permissions

The reusable service is `inc/services/appointment_service.php`; SQL lives in `inc/repositories/appointments.php`. The shared native dialog serves client records, calendar and pipeline flows. Confirmed is the default; tentative/confirmed are active for scheduling. Completed, cancelled and no-show stay in history. A manager/authorized editor may correct any status using the versioned edit form; cancellation never deletes a record and lifecycle changes never advance or reverse client/deal stages.

All operations switch to the configured CRM blog and restore context in `finally`. The real logged-in WordPress actor authorizes writes; presentation scope never supplies permissions. Managers use `peracrm_manage_all_clients` / administrators; employees require canonical client access plus appointment participation for edits. New employee bookings inherit the client's primary advisor; managers may reassign across staff. Every selected employee must be authorized CRM staff on that blog. Property references must be published, recognized property types without password protection.

All authenticated reads are nonce protected. Calendar visibility is broader than client visibility: inaccessible records expose only appointment ID, time, generic type, status and advisor. They expose no client ID/name/link, custom title, location, notes or property associations. Direct event reads use the same projection. Client-scoped history/search and mutation endpoints independently authorize the client.

A target-blog MySQL named lock serializes appointment writers, including the server-side overlap recheck. Transactions encompass record/association changes, activity, submission receipt, and an optional Viewing arranged transition. The party row is locked against legacy stage writers. Every edit/reuse checks its expected version; pipeline commits check the original stage. Required tables must be InnoDB; incompatible legacy storage fails closed with an administrator-facing error, rather than offering false rollback guarantees. Intentional overlaps require an explicit confirmation in the dialog and produce audit evidence. Conflicts check both primary and additional participants using strict interval overlap; adjacent bookings are valid. Completed/cancelled/no-show schedules do not block new active bookings.

One stable submission key lives for the entire dialog, including network retries and subsequent field corrections. Replaying an identical committed command returns its original appointment; reusing a committed key with changed values is rejected. Editing an existing record opens a new command key. No automatic request-key expiry risks recreating an old booking.

## Pipeline and panels

The repository guard blocks new `viewing_arranged` transitions outside the booking transaction, including raw AJAX/admin/pipeline/bulk routes. Already-viewing clients may save without another booking. The client status selector opens booking immediately, retaining the original form and values; dismissal restores the old selection. Booking failure leaves both appointment and stage unchanged. Upcoming active appointments can be explicitly reused or opened/edited; new legitimate appointments remain available. Engagement/disposition and client-type selections from the status form are validated and committed with the transition. The existing timeline/status fragments and workspace reconciliation refresh after saves without replacing unrelated editors.

Legacy authorized admin-post / post-editor stage transitions redirect to the same frontend booking interface before saving; bulk moves become a queue of explicit per-client bookings. Dismissing the queue stops remaining transitions. Other stages use existing handlers unchanged. The normal admin client editor's unrelated unsaved fields are not submitted by that redirect; save unrelated edits separately before entering the booking workflow.

The optional Appointments panel uses the established registry and employee/client/blog preferences. Hiding it never hides the independent summary booking action. History is paged, upcoming active appointments first, then past records newest first. Details offer edit/reschedule/cancel/complete; all use the same versioned save service. Dashboard upcoming appointments follow urgent work and new leads, use the effective advisor as a filter only, and link to the shared calendar.

## Calendar, timezones and assets

`/crm/appointments/` uses the native shell/sidebar with a calendar icon. Default view is Week, all advisors. Day/week use actual 24-hour time positions and separate overlap columns; month and chronological list views are available. Previous/next/today, date picker, IANA timezone, advisor/type/status/client filters are server-backed. Mobile presents the selected period as an agenda. Native dialogs provide focus trapping/Escape/return focus; visible labels, focus indicators and live error/success regions accompany controls.

Queries use strict interval boundaries, at most **93 days**, with **200 events per page**. The UI retrieves all pages of the selected period; it never loads the entire appointment archive. Associations load in two indexed batch queries per page, and calendar client scope is resolved once per page. Client history pages contain 20 records. Dashboard is capped to five active appointments in the next 90 days. The current release uses offset pagination within a bounded range; writes are serialized per CRM blog, a deliberate small-team consistency tradeoff.

UTC is stored and used for conflicts. PHP `DateTimeImmutable` converts entered wall time using the selected IANA zone; browser display uses `Intl.DateTimeFormat` with that explicit zone. Browser-local time is never authoritative. Nonexistent spring-forward times and ambiguous fall-back times are rejected with an accessible explanation; this version does not offer a fold/UTC-offset selector for duplicated wall times. No hard-coded UTC+3 arithmetic is used.

No calendar dependency or build step was added: the plugin currently ships direct JavaScript/CSS. A bounded native scheduler avoids a new library's bundle/licence/update surface; native date controls and dialogs cover the required interactions. Appointment assets load only on relevant authenticated CRM routes, not public website pages. Existing CRM spacing, controls, buttons, borders and light/dark variables are reused. No external calendar, messaging, booking portal or notification integration is included.

## Authenticated AJAX contract

POST `wp-admin/admin-ajax.php` with `action=peracrm_appointments`, a nonce for `peracrm_appointments_{target_blog_id}`, and a JSON `payload`:

- `save`: create, update/reschedule, lifecycle change or explicit upcoming reuse. Requires stable `request_key`; existing records require `id`, `version`; optional `pipeline`, `expected_stage` atomically enter Viewing arranged.
- `get`: privacy-projected event detail by appointment ID.
- `list`: `start`/`end` local datetimes, explicit `timezone`, optional advisor/type/status/client filters and offset.
- `history`: authorized client history with offset.
- `client`: authorized booking defaults and upcoming active appointments.
- `search`: bounded authorized client/property title search.
- `conflicts`: authorized advisory check; `save` always independently rechecks.
- `refresh`: authorized existing client status/timeline/workspace fragments after a committed operation.

Errors return machine code/message (403 forbidden/session, 404 missing, 409 validation/conflict/version). Conflict payloads use the same privacy projection. `allow_overlap: true` is accepted only from a user who can manage that appointment; the UI exposes it only after an explicit warning. URLs are syntax/protocol validated and escaped, and never fetched by this feature.

## Tests and manual QA

Automated tests: standalone `tests/appointments-test.php` (validation, UTC, DST, privacy); `tests/appointments-js-test.js` (periods, timezone boundaries, overlap placement); disposable `tests/integration/campaigns/appointments.php`, `appointments-upgrade.php`, `appointments-race.php` (real persistence, migrations, exact data preservation, single/multisite, permissions, audit failure rollback, concurrent create/edit); `appointments-browser.cjs` (live WordPress AJAX, four views, booking, failed-save retention, pipeline dismiss/reuse, CSRF/privacy, responsive/light/dark screenshots). All join the existing campaign-foundations CI matrix.

Staging verification after a database/files backup:

1. Deploy the feature branch plugin files to staging. Load an authenticated CRM page or invoke `wp --url=<CRM-site> eval 'peracrm_maybe_upgrade_schema();'`. Verify schema version 42 and `peracrm_campaigns_schema_status` ready. Inspect all four appointment tables and indexes on the **target blog**, plus InnoDB for party/activity/postmeta. If the existing migration circuit breaker is failed, use its existing administrator retry flow after inspecting the failure.
2. Confirm rewrite version v7; if a stale route cache remains, run `wp --url=<CRM-site> rewrite flush` or save Permalinks. Visit `/crm/appointments/` and confirm shell navigation plus all four views.
3. Create from the client summary and global calendar; test hotel/online fields and a multi-property tour with reordered stops. Double-submit/retry a network failure and confirm a single record. Open two editors, save one, and confirm the second receives a version error.
4. Enter Viewing arranged through status and administrative individual/bulk flows. Dismiss, provoke validation failure, then save/reuse an upcoming booking. Confirm no premature stage change, placeholders or duplicate appointments. Already-viewing and other-stage saves must remain usable.
5. As administrator/manager/assigned/unassigned employees, verify writes, assignment limits, minimal restricted schedule records and direct endpoint denial. Test advisor presentation scope without granting the selected employee's permissions. Verify invalid nonces and foreign-blog client IDs cannot grant access.
6. Exercise adjacent/overlapping slots involving additional team members; explicitly confirm one overlap and inspect activity evidence. Cancel/complete/no-show and confirm lead/deal stages remain unchanged. Review timeline, notes, reminders, deals and panel hide/restore workflows.
7. Verify Istanbul midnight and non-Türkiye DST boundaries, short/overnight events and date filters. Check 1440px/820px/390px, light/dark, keyboard focus/Escape, long text, no horizontal overflow and preserved invalid form values.

Phase 3 can consume canonical UTC/versioned appointment operations and existing activity references for external synchronization or notifications, with separate consent, credentials and delivery/outbox design. No external side effects are embedded in these transaction paths. Rollback of application files should retain additive tables/history; do not drop appointment tables or reset data in production.

Local verification for this PR: **47 standalone PHP suites pass**, including appointment validation/privacy; appointment date/layout JS and existing panel JS pass; all plugin PHP syntax, both frontend JS syntax, shell syntax, SES package verification and diff whitespace checks pass. Real WordPress **6.8.3 / PHP 8.3 / MariaDB 11.8** persistence and v41→v42 preservation checks pass for single-site and multisite; simultaneous create/edit processes pass; Chromium live single-site browser checks pass, with administrator/employee endpoints, invalid nonce, restricted records, four views, booking/edit, network failure retention, property associations, pipeline dismissal/reuse and mobile/dark screenshots. Screenshots contain synthetic fixture records only: [desktop](peracrm-appointments/screenshots/appointments-desktop.png), [mobile dark](peracrm-appointments/screenshots/appointments-mobile-dark.png), [mobile booking](peracrm-appointments/screenshots/appointments-booking-mobile.png).

Local limitation: official MySQL/Apache Docker fixture images could not unpack within the environment's Docker storage quota. Tests ran against the above real local MariaDB database and PHP server instead. The full pre-existing campaign integration/browser suite and the MySQL 8 single/multisite CI matrix were **not** run locally; their workflow includes the new appointment fixtures and retains screenshots. Confirm those CI jobs before staging approval. No production migration, merge or deployment was performed. Individual appointments are limited to seven days, with at most 50 selected properties/additional employees; calendar queries remain bounded as described above.
