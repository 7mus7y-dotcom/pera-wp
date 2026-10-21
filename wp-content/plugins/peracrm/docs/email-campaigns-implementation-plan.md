# Integrated email campaigns: repository review and living implementation plan

Review status: **complete** (2026-10-17). Implementation: **Phase 1 complete (technical acceptance)**; **Phase 2 in progress**; **Phase 3 in progress (3A/3B/3C technical/live core checks passed; clean PR review recorded; property review and external readiness remain open)**; **Phase 4 in progress (4A/4B/4C and 4D repository checks passed; final review and external acceptance remain)**; **Phase 5 in progress (5A documentation complete; 5B submission, deliverable capture/approval, recipient token/expansion contracts, bounded durable recipient persistence and subscriber lifecycle integration complete; production integration not started)**; Phases 6–7 not started.

## Purpose, scope and maintenance

Let Pera staff prepare newsletters and targeted campaigns inside the existing CRM, with an external provider delivering mail. This document is the authoritative review and implementation plan. Phase 2 adds plugin-owned subscriber management, isolated preview/import services, suppression intake and public preferences with real intake gated off. Phase 3A adds draft/revision storage, approved configuration and the structured editor. Phase 3B adds public content references, current-draft rendering and advisory language/topic audience previews. No provider, queue, scheduling, delivery, production contacts, merge or deployment is included.

Future implementation PRs must update phase status, validation evidence, decisions and the progress log here. A phase must not be marked complete solely because code exists when required verification remains outstanding. Use only `not started`, `in progress`, `blocked` or `complete` for phase status. Keep repository checks distinct from external verification; record outstanding evidence explicitly.

**Current task:** fifth bounded Phase 5B PR — internal subscriber lifecycle integration for durable recipient identity and revocation. Verified #1468 merged at `9113bdb3b857c3464d0b790adc53cd2228f77a34`, also latest main at start. Its final head `9659d3c87ffe51aa77c5f661440e5f4522f05e96` has a completed clean Codex review and passing single/multisite [run 39487438922](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39487438922); no review threads remain. The original clean `/workspace/pera-wp` checkout remains at `1995ab58`; work is isolated at `/workspace/pera-lifecycle`. Shell fetch failed once (proxy unreachable). Connected GitHub supplied the sixteen merged files; local baseline scaffolding is not included in publication, whose parent is verified GitHub main. No applicable AGENTS.md exists in the workspace or searched ancestors.

**Completed bounded scope:** internal subscriber lifecycle integration now revokes durable identities atomically on deletion/recreation while preserving ordinary profile/consent edits; local and real single/multisite validation passed (evidence below). Previous bounded work: additive schema 29 identity epochs, digest revocation registry and immutable attempt metadata; unbootstrapped internal service, pure projection regressions and disposable SQL/concurrency fixtures. Provider scope, frozen IDs/hashes, recipient and exact expanded-output/full-envelope hashes are bound. No bodies, raw bearers or keys enter these new tables. Existing approval/capture bytes and cancellation/suppression/unknown fences are unchanged. This is persistence evidence, not worker authority or end-to-end unsubscribe acceptance.

**Next dependent work:** public preference/visible withdrawal and RFC 8058 POST handlers; deployment key/retention/restore readiness and exact-byte secure output recovery; concrete worker-authorized resolution and transport integration. The worker must persist and confirm the exact immutable binding before transport, validate all authority/fences/eligibility, register the actual issued token digests/metadata and never regenerate random tokens for an existing attempt. No provider factory, public route, worker activation, scheduler or UI change. Dispatch remains blocked unconditionally; real intake and public collection remain disabled.

## Review baseline and method

Inspected the working repository at `a90a151281e9508d91347cedc25ff1bf388bb820`. GitHub comparison against latest `main`, `41036e8218b1da320608b866210eaaf2bee60d62`, showed six newer commits changing only the property archive, FAQ module and two theme tests; none changes the CRM components cited below. PR branch is to start from that latest main SHA. Shell fetch was unavailable because the configured proxy could not be reached; GitHub connector reads supplied the comparison and base. No live WordPress database, credentials, client records, provider account or inbox was inspected. Repository code establishes available functionality, not whether integrations are enabled or healthy in production.

No `AGENTS.md` was found in the repository or workspace ancestor locations searched. Plugin docs are standalone Markdown audits/plans; no documentation index was found that needs updating. Historical audits can describe older ownership: the current bootstrap and source code take precedence over `docs/theme-template-dependency-audit.md`.

Paths below are repository-relative. In the review table, `P/` means `wp-content/plugins/peracrm/` and `T/` means `wp-content/themes/hello-elementor-child/`. Proposed files later in this document are recommendations, not existing functionality.

## Repository review: verified functionality and gaps

| Area | Verified code and implications | Gap or constraint |
| --- | --- | --- |
| 2026-10-20 | Phase 5 in progress — bounded durable recipient persistence | Additive schema 29, mailbox identity epochs, digest registry and immutable attempt metadata. Local regression/syntax results and hosted single/multisite evidence recorded below; all delivery/intake/collection gates unchanged. |
| Ownership | `P/peracrm.php` loads `inc/bootstrap.php`, declaring schema version 20. Bootstrap owns CRM services, repositories, frontend routing, REST and admin modules. `wp-content/plugins/pera-portal/pera-portal.php` identifies a separate internal portal scaffold; CRM feature ownership is PeraCRM. | Keep campaigns in PeraCRM; no second CRM plugin or new app required. |
| Contacts | `P/inc/cpt.php::peracrm_register_cpt_crm_client()` registers clients as posts. `inc/helpers.php::peracrm_client_get_profile()` reads `_peracrm_email`, phone, client type, preferred contact, bedroom and USD budget metadata. `inc/services/client_service.php::peracrm_sync_client_contact_meta()` maintains legacy email aliases and normalized email fields. | There is no database unique constraint on client email postmeta. An email field is not permission to market. |
| Identity and duplicates | `peracrm_find_all_client_ids_by_email()`, `peracrm_find_existing_client_id_by_email()` and `peracrm_find_or_create_client_by_email()` in `inc/services/client_service.php` search aliases. The latter selects a canonical match, republishes it, marks other matches with `peracrm_dedupe_canonical_id` and trashes them; it can also ensure existing WP-user membership. `peracrm_find_or_create_client_by_identity()` supports phone fallback. | Do not call lead ingestion for subscriber imports or automatic linking: it has sales-record and membership side effects. Do not silently merge ambiguous CRM matches. |
| Relationships and status | `P/inc/repositories/client_property.php` stores property relations; schema has unique client/property/relation keys. `inc/repositories/party.php::peracrm_party_upsert_status()` and `inc/stages.php` own pipeline/engagement/disposition; deals are separate in `inc/repositories/deals.php`. `inc/services/user_link_service.php` handles WP-user links. | CRM client/lead classification and marketing subscription must remain independent. Duplicate/junk disposition is useful exclusion evidence, not a complete suppression system. |
| Existing mail | `P/inc/notifications/providers/class-peracrm-email-provider.php::PeraCRM_Email_Provider::send()` sends a hardcoded plain-text staff enquiry notification through `wp_mail`. `inc/notifications/enquiry-notification-service.php::peracrm_enquiry_notifications_dispatch()` claims the event then sends email and WhatsApp synchronously; `peracrm_enquiry_notifications_log_attempt()` records results. | This is operational notification delivery, not a reusable bulk campaign engine. A true `wp_mail` return means local acceptance, not delivered mail. No durable marketing retries or provider delivery events here. |
| Theme email and templates | `T/inc/enquiry.php::pera_enquiry_send_logged_mail()` wraps `wp_mail`; `pera_send_enquiry_autoreply()` and `pera_enquiry_autoreply_is_rate_limited()` provide enquiry acknowledgements and throttling. Property, favourites and citizenship handlers compose bodies. `T/inc/enquiry-email-log.php::pera_client_autoreply_enabled()` exposes a setting. | Reuse branding/copy conventions where suitable, but do not move campaign sends into enquiry handlers or apply newsletter opt-out to requested correspondence indiscriminately. No general campaign template editor found. |
| Logs | `P/inc/db/notification_log_table.php::peracrm_notification_log_claim_event()` uses unique `(event_key, channel)` claims. Theme `inc/enquiry-email-log.php::pera_enquiry_email_log_install()` creates a separate log containing recipient, subject, status, request ID and metadata; `pera_enquiry_email_log_event()` writes it. `P/inc/views/pages/crm-logs.php` reads that theme table on `/crm/email-logs/`. | Two existing log systems have different purposes. Neither is a recipient ledger. Preserve operational logs and label campaign reporting separately; avoid copying existing full payload logging into marketing. |
| Timeline | `P/inc/services/activity_service.php::peracrm_log_event()` writes CRM activity; `inc/admin/metaboxes/timeline.php::peracrm_timeline_get_items()` combines notes, reminders and activity. `peracrm_timeline_normalize_activity()` renders events. `inc/frontend-data/crm-client-view.php` supplies frontend client presentation. | Add campaign event labels and permission-safe links to both timeline surfaces; existing notification logs are not proof of campaign timeline integration. |
| Consent | `P/inc/enquiry-ingest.php::peracrm_ingest_theme_enquiry_forms()` captures `sr_consent` in raw enquiry fields. `inc/integrations/enquiries.php` requires policy consent on the applicable form; `T/inc/client-portal.php` validates registration privacy/terms consent. | No dedicated marketing subscription, email unsubscribe or suppression registry found in first-party CRM code. These consent fields do not establish newsletter consent. `/push/unsubscribe` in `P/inc/rest/push.php` removes browser push subscriptions, not email subscriptions. |
| Audience data | `P/inc/helpers.php::peracrm_client_get_profile()` exposes USD min/max budgets, bedrooms and preferred contact; `peracrm_client_get_assigned_advisor_id()` resolves assignment aliases. `inc/frontend-data/crm-data.php::pera_crm_get_leads_view_data()` supports text, stage, advisor, derived lead/client/agent and unassigned/stale/new72/open-scope filters. Party state provides engagement/disposition. Property relations and enquiry payloads provide evidence of interests. | No canonical subscriber language or structured marketing interests field found. Preferred contact is a correspondence channel preference. Budget display exists but is not already a full campaign segmentation UI. Do not infer language from a name or turn arbitrary raw enquiry text into a trusted audience taxonomy. Missing data must be explicit. |
| Import | `P/inc/admin/importer.php::peracrm_import_parse_csv()`, `peracrm_import_validate_rows()`, `peracrm_import_row_identity_keys()`, `peracrm_import_match_existing_record()` and `peracrm_import_log_batch()` provide mapping, validation, duplicate detection and batch summaries. `inc/admin/pages/import-data.php` and `inc/admin/actions.php` provide admin flows. | `peracrm_import_apply_row()` writes sales records. Reuse pure parsing/preview conventions, not the write path. Existing imports do not preserve a marketing suppression ledger because none exists. |
| Routing and UI | `P/inc/frontend/routing.php::pera_crm_register_route()` registers `/crm/*`, including email logs; `inc/frontend/view-loader.php` resolves plugin views/shell. `inc/frontend/helper-bridge.php` loads plugin frontend data. `inc/views/partials/crm-side-nav.php`, `crm-header.php`, `assets/frontend/crm.css` and `crm.js` are current UI building blocks. | Add plugin routes/views/nav; preserve existing responsive forms, tables, notices and shell. Theme compatibility files are not the primary place for new screens. |
| Permissions | `P/inc/roles.php::peracrm_ensure_roles_and_caps()` gives administrator/manager management caps and employees basic CRM editing. `inc/services/client_scope_service.php::peracrm_get_client_access_scope()` distinguishes full/assigned/empty scope; `peracrm_user_can_access_client()` checks object access. `inc/impersonation.php` tracks actor/effective context. | Existing CRM access is too broad for bulk marketing. New dedicated campaign capabilities and endpoint checks are required; hiding navigation is insufficient. Preserve real actor audit and reject campaign mutations during impersonation. |
| Background jobs | `P/inc/cron/push_cron.php::peracrm_push_schedule_digest()` schedules a 15-minute WP-Cron digest; `inc/services/push_service.php::peracrm_push_run_digest()` has window decisions, sent markers and health reporting. `inc/cli/push_cli.php` provides CLI precedent. `inc/whatsapp.php::peracrm_whatsapp_acquire_message_lock()` provides atomic option-lock precedent. | These are channel-specific mechanisms, not a durable campaign queue. No general campaign worker, retries or Action Scheduler integration found in inspected CRM bootstrap. WP-Cron timing and a real scheduler require live verification. |
| Settings and webhooks | `P/inc/whatsapp.php::peracrm_whatsapp_get_settings()` supports saved settings and constant overrides; management is target-blog scoped. `inc/integrations/facebook-leads/settings.php::peracrm_facebook_leads_get_settings()` also supports constants. `inc/rest/whatsapp.php::peracrm_whatsapp_verify_meta_signature()` validates raw-body HMAC; Facebook `inc/integrations/facebook-leads/rest.php::peracrm_facebook_leads_verify_signature()` is another precedent. `inc/rest.php` and `inc/rest/whatsapp.php` demonstrate permission callbacks. | No email delivery-provider connection verified. Reuse boundary patterns, not Meta's signature algorithm for another provider. Do not reproduce diagnostic logging of tokens or payloads. |
| Content selection | `T/inc/blog/ajax-blog-search.php::pera_ajax_blog_search()` and `pera_blog_search_render_grid()` query articles and render `parts/post-card.php`. `inc/property-card-helpers.php::pera_render_property_card()` wraps `parts/property-card-v2.php`, which uses unit totals and display-price helpers. `inc/portfolio-token.php::pera_theme_portfolio_token_build_offer_groups()` is a property grouping precedent. `pera-multilingual` and `pera-currency` provide separate content/price concerns. | Website cards depend on theme CSS, query globals, translations and pricing logic. Use WordPress/plugin APIs through plugin-owned content adapters; never invoke theme card or pricing helpers. Snapshot language, price/currency and image URLs; do not expose private portfolio tokens in campaigns. |
| Schema, tests and docs | `P/inc/schema.php::peracrm_maybe_upgrade_schema()` and `peracrm_upgrade_schema_to()` use a version option and `dbDelta`; bootstrap runs upgrades on `init`. `inc/lifecycle.php` owns lifecycle hooks. `tests/roles-upgrade-target-blog-test.php`, `rest-authorization-behavior-test.php` and WhatsApp tests use standalone PHP harnesses/stubs. Plugin `docs/` contains audits and verification checklists; root `.github/workflows/visual-qa.yml` is UI infrastructure. | Stub checks alone cannot verify SQL contention, real WP permissions, delivery or inbox rendering. Use isolated integration fixtures for those; no production migration during this review. |
| Multisite | `P/inc/helpers.php::peracrm_table()` uses current DB prefix; `peracrm_get_target_blog_id()` resolves target membership/configuration and `peracrm_with_target_blog()` restores context in `finally`. Schema and role updates wrap target context. | All campaign repositories, settings, public links, cron/CLI and webhooks must resolve the same authoritative target explicitly. Never use an untrusted blog ID or create independent queues on every frontend blog. Live topology remains unknown. |

## Recommended architecture

### Placement and reuse

Add a bounded `inc/campaigns/` module loaded by `inc/bootstrap.php`, using existing repository/service separation, `peracrm_table()`, target-blog wrappers, capability upgrades, CRM shell, notices and activity service. Keep provider code behind a small interface: send one recipient, verify/normalize events, query/reconcile accepted sends if supported. Provider contact lists must not become the CRM's source of consent truth. Start with one adapter and one branded template; do not introduce a visual builder, separate application or provider-hosted campaign editor.

Use dedicated relational tables for identity, unique keys and queue operations. A custom worker driven by existing WP-Cron/CLI conventions is the smallest dependency change, but it must implement durable claims, leases and retry state. Reconsider Action Scheduler only if Phase 4 proves that maintaining this small worker is more expensive than adding the dependency. Never use transients as the durable recipient ledger.

### Required ownership boundary for every phase

Campaigns belong completely to PeraCRM. All feature functionality, templates, routes, CSS and JavaScript must live in the plugin; no theme files may be added/modified for this feature and no campaign code may require theme helpers or assets. Add future screen styles to `wp-content/plugins/peracrm/assets/frontend/crm.css` and behavior to `wp-content/plugins/peracrm/assets/frontend/crm.js`, using the plugin's existing registration/enqueue mechanism in `inc/frontend/assets.php`. Public preference templates/routes must also be plugin-owned. The email renderer must generate its own email-safe inline styles, independent of both the active theme and the CRM stylesheet. Article/property selectors must use WordPress/plugin APIs and plugin-owned adapters, never theme card-rendering, portfolio or pricing helpers. Content availability/metadata gaps must be reported explicitly, not repaired by loading the theme.

PR #1447 review: foundation services use plugin/core functions only; no newly introduced theme dependency or theme-file change was found. A source ownership guard now checks campaign code and plugin asset loading. Phase 1 has no campaign frontend screen and needs no new CSS/JavaScript; the administrator retry notice/form uses core WordPress admin presentation. Future feature assets must follow the paths above. The disposable integration fixture mounts only PeraCRM, with a bundled WordPress theme, to test the feature without the repository theme (runtime evidence remains outstanding).

Existing CRM dependencies are outside this PR: `inc/frontend-data/crm-client-view.php::pera_crm_client_view_ensure_portfolio_support()` conditionally loads the active theme's `inc/portfolio-token.php`; the legacy email-log view reads a theme-owned log. Neither is called by these foundation services. Those paths would obstruct theme-independent campaign content/rendering if reused; later phases must use their own plugin adapters, renderer and campaign reporting. This PR does not refactor existing portfolio/log behavior. Canonical property registration/pricing availability must be checked through plugin/core APIs in Phase 3; do not silently introduce a theme requirement to fill a gap.

### Proposed data model (names illustrative; exact DDL decided in Phase 1/3)

All tables are target-blog prefixed; timestamps are UTC, IDs unsigned, status values server-validated. Index status/due time and relevant foreign IDs; application services enforce references consistently with the existing schema style.

| Entity | Fields and constraints |
| --- | --- |
| `peracrm_subscribers` | ID, original deliverable address, normalized email with unique key, nullable canonical `client_id`, subscription state (`unknown`, `pending`, `subscribed`, `unsubscribed`), source, source reference, recorded/confirmed/withdrawn timestamps, evidence reference and policy version, explicit preferred language, validated topic preferences, created/updated timestamps and version. No WP user or sales lead creation is required. |
| `peracrm_subscription_events` | Append-only subscriber/address consent changes, source/import batch, policy/evidence reference, real actor or verified public/provider origin, timestamp. Avoid storing full enquiry bodies or unnecessary IP addresses. |
| `peracrm_email_suppressions` | Normalized email unique key, reason flags (unsubscribe, hard bounce, complaint, administrative), scope, source/provider reference and timestamps. Preserve history separately from current state. Minimal keyed address tombstones can survive subscriber deletion under an approved retention policy. |
| `peracrm_campaigns` | ID, name, subject, preheader, language, sender identity reference, template/schema version, structured draft JSON, audience rules/version, lifecycle state, UTC schedule, real author/approver, revision, frozen content and audience snapshot reference/hash, counters derived from ledger. |
| `peracrm_campaign_snapshots` | Immutable approved subject/from/reply-to, HTML and plain text, ordered block data, source content IDs, resolved titles/excerpts/URLs/images/alt text, price/currency/language labels, template version and hash. Can include a campaign-local template definition initially; a template-library table is unnecessary for MVP. |
| `peracrm_campaign_recipients` | Campaign/snapshot/subscriber IDs, normalized address at freeze, optional CRM ID at freeze, selected personalization, state, exclusion reason, attempt count, next attempt time, lease owner/expiry, deterministic send key, provider message ID, accepted/delivered/terminal timestamps and sanitized failure code. Unique `(campaign_id, normalized_email)` and send key. |
| `peracrm_campaign_events` | Provider/account identity, unique external event ID (or documented stable payload-derived key), message/recipient/campaign references, type, event/receipt time and minimal normalized metadata. Unique dedupe key; retain unmapped valid events for reconciliation without guessing a client. |

Normalize by trimming and consistent case handling compatible with existing CRM normalization; validate syntax and address length before writes. Do not strip plus-tags or Gmail dots. A normalized mailbox represents one delivery per campaign even if many CRM clients use it. Ambiguous client links require staff resolution and do not merge/trash posts. Re-linking or deleting a client must not delete suppression or rewrite historical recipients. An email change is a new address identity requiring its own consent and suppression evaluation.

Eligibility is an explicit service: valid address + documented active marketing permission + applicable topic/language rules + no suppression + no configured safety exclusion. Unknown consent is ineligible. Default exclusions include junk/duplicate linked sales records pending explicit review; sales inactivity alone does not revoke valid newsletter consent. Newsletter-only subscribers remain eligible without fabricated advisor, budget or lead status. Missing segmentation data is excluded from positive filters, not silently treated as a match. No inferred marketing consent from contact preference, enquiry, account registration or existing provider membership.

### Imports and preferences

Provide a subscriber-specific CSV preview/apply path with row caps, file validation, permission checks and batch audit. Preview creates no clients and sends nothing. Import historical unsubscribe, bounce and complaint lists **before** opted-in addresses; require source/date/evidence mapping. Missing evidence becomes unknown, not subscribed. Existing suppressions always win over imported opt-in, including repeat or differently cased imports. A fresh resubscribe requires an explicit, audited verification flow; complaints/hard bounces cannot be cleared by a generic import. Do not replay old consent timestamps as new actions.

Offer a signed opaque preference/unsubscribe token independent of login and free of email/client IDs in URLs. A normal GET displays a confirmation/preferences page to avoid scanner-triggered changes; authenticated one-click unsubscribe POST must work without login using the selected provider/header standard. Process opt-out immediately, idempotently and across all pending campaigns. Separate optional topic preferences from global marketing unsubscribe. Double opt-in for new public subscriptions is recommended; establish the applicable permission policy before enabling collection.

### Screens, content and workflow

Add `/crm/campaigns/` (status list), `/crm/campaigns/new/`, campaign detail/editor, audience preview, recipient/report detail, and `/crm/subscribers/` with import and suppression views. Admin-only provider settings should show readiness and masked status, never a retrievable secret. Managers manage campaigns/subscribers/reporting; employees have no campaign, export or subscriber-list access. An employee may see a read-only marketing status and relevant campaign timeline entries for an assigned client if policy permits. Enforce permissions at routes, REST/admin-post actions and object reads; use dedicated caps such as `peracrm_manage_campaigns`, `peracrm_view_campaign_reports`, `peracrm_manage_subscribers`, with provider configuration restricted to target-blog administrators. Reject writes while impersonating; audit the real actor.

Editor: subject, preheader, language, approved sender/reply-to, intro text, ordered article/property blocks, CTA and mandatory identity/address/preferences footer. Use bounded text/link/image inputs and sanitization, not arbitrary HTML or scripts. Content search only returns published public `post` and `property` records through paginated permission-checked selectors. Resolve multilingual fields deliberately through an adapter; do not depend on request locale. Build simple email-safe HTML with inline styles, accessible images/links and plain-text alternative. Resolve metadata/pricing through WordPress or plugin APIs in plugin-owned adapters. Never require active-theme helpers, templates or assets; missing canonical data must be explicit. The renderer owns its email-safe inline styles and is independent of both theme stylesheets and CRM CSS. Protect or copy snapshot assets if image immutability is required; a frozen URL alone does not freeze the file at that URL.

Workflow: draft → preview → allowlisted internal test → approved freeze → scheduled → queued/sending → completed. Also support paused, cancelled and failed states. Tests are separate audited sends with test branding and never consume production recipient state or bypass the internal allowlist. Scheduling requires an exact preview count, exclusion summary, sender readiness, successful review and explicit timezone conversion. Freeze audience membership and personalized content in bounded batches, lock the revision, then atomically approve the complete snapshot. Failed/partial materialization cannot become sendable. Subsequent source edits, template edits, client email changes or newly matching subscribers do not alter that campaign. Editing a scheduled campaign requires unscheduling and a new revision/approval; it must not overwrite an active ledger.

Pause prevents new claims and rechecks before provider submission, but cannot recall already accepted mail. Resume reuses the ledger and revalidates eligibility. Cancellation skips remaining unsent recipients. Completion means all ledger rows are terminal (accepted, excluded, failed or cancelled); it does not mean all mail reached inboxes. Delivery events continue after campaign completion. Expose unresolved ambiguous sends and do not silently complete them.

### Queue correctness and event processing

Claim small batches with atomic database compare-and-set and expiring worker leases; cap concurrency, provider rate and maximum attempts. Before **each** external submission, recheck current campaign pause/cancel state, subscription version, consent and all suppression sources against the frozen address; excluded recipients become terminal with reasons. Coordinate preference writes and final send authorization through per-address locking/version checks; document the unavoidable boundary once a request has left WordPress. Stop new submissions promptly when a suppression arrives. Never substitute a client's new email into an approved recipient.

Persist deterministic recipient send keys before the request; use provider idempotency where its documented semantics permit. Separate queued, sending, accepted, delivered, retryable failure, permanent failure, suppressed and unknown outcome. A timeout after submission is **unknown**, not safe to retry automatically. Reconcile with provider message lookup/events or its confirmed idempotency window; without a safe way to prove non-acceptance, hold for manual review. Worker restart/lease expiry must not resend accepted or ambiguous rows. Retry only known transient non-acceptance (including documented rate-limit responses), with bounded exponential backoff/jitter and retry-after support. Unique rows alone cannot guarantee exactly-once external delivery.

Use a dedicated HTTPS REST webhook endpoint. Verify the raw request using the provider's documented signature/authentication, key rotation and replay rules before parsing; reject oversized/invalid requests. Persist a deduplicated event durably before acknowledging success; apply suppression synchronously or through a guaranteed priority path before ordinary queue work. Duplicate and out-of-order events must not regress state: complaint/unsubscribe suppression dominates later delivery/open events. Validate account/message correlation; do not link by an arbitrary supplied CRM ID. Provider retries are safe because event keys are unique. Monitor unmatched events and reconcile gaps through provider APIs where available.

Hard bounce suppresses the mailbox; complaint suppresses marketing immediately; unsubscribe updates both subscription and suppression ledger. Classify soft bounces using documented provider codes and a bounded threshold, not every transient failure. Manual clearing of administrative/soft-bounce blocks requires audit; permanent address failures need verified remediation. Synchronize provider suppression state before imports/pilots and periodically thereafter; outage or uncertain suppression synchronization should block new campaign starts. Provider feedback must never authorize resubscription.

### Reporting, attribution and privacy

Show eligible/excluded, queued, accepted, delivered, failed, bounced, complained and unsubscribed counts with clear denominators and timestamps. Opens/clicks are optional, imperfect signals due to image proxies, privacy protections and scanners. Never automatically qualify leads or change pipeline stages from them. Defer tracking until the permission/retention decision is made.

Add campaign/snapshot IDs and controlled UTM parameters to public article/property destinations; avoid email, CRM IDs and private tokens in analytics URLs. Preserve existing query strings. Record explicit enquiry campaign attribution separately from passive engagement; later deal reporting should describe association rather than claim causality. For linked CRM clients, write idempotent concise accepted/delivered/failure/unsubscribe timeline events with campaign links; do not flood timelines with opens or raw payloads. Respect client scope and campaign-report permissions independently.

Keep operational enquiries, acknowledgements and client correspondence on their existing paths. Marketing unsubscribe must not suppress a requested service response; delivery failures can still warn staff about address health. Never use the operational path as a marketing suppression bypass.

Prefer deployment-managed secrets/constant overrides, modeled on existing integration settings, with least-privilege send keys and a separate webhook secret. If DB-stored credentials are necessary, settle encryption/key-management first; WordPress options are not automatically encrypted. Only admins can rotate keys; never return saved values to the browser, logs, exports or this plan. Store provider identity/configuration separately from credentials and validate outbound hosts. Separate test/live settings and disable sending on staging clones by default.

Agree retention with Pera before collection: proposed starting policy is short-lived raw import files (delete after apply/failure expiry), no routine raw webhook retention, 90 days detailed delivery diagnostics and 12 months aggregate campaign reporting, subject to operational/legal review. Keep the minimal consent/suppression evidence needed to honor opt-outs for the approved period; deletion must not enable reimport/resend. Add permission-controlled export/erasure handling, encrypted backups, redacted logs, bounded retention jobs and an audit of administrative changes. Minimize provider data to address and required content/identifiers; document region, subprocessors and deletion obligations.

## Delivery-provider recommendation and decisions

**Phase 5A decision (2026-10-20): recommend Amazon SES API v2 `SendEmail`, one recipient per request**, subject to account/region/privacy/operational acceptance. SES explicitly supports marketing through its API and fits CRM-owned frozen content, final per-recipient eligibility checks and pause/cancel semantics. Use CRM preferences/global marketing suppression and authenticated SNS configuration-set events. There is no documented SES send-idempotency window: ambiguous sends must remain held until independently verified evidence resolves them; no exactly-once promise.

**Brevo remains suitable as a marketing platform, but the earlier provisional preference is superseded.** Its documented marketing path is the email campaign API, not assumed promotional use of `/smtp/email`. Campaign handoff moves recipient dispatch into Brevo, cannot retain our final per-recipient submission check, and lacks a documented approved plain-text upload/campaign-send dedupe contract. Current transactional batch idempotency (30 minutes) cannot be transferred to marketing campaigns. No third provider is needed while SES meets the stated requirements with explicit unknown-outcome holds.

The [provider specification](email-campaigns-provider-specification.md) contains dated official sources, documented facts versus inferences/account unknowns, current pricing/plan restrictions, code references and the smallest proposed changes. It identifies the worker's missing content envelope, review-only frozen HTML/text, recipient-link/token authority, secrets, timeout enforcement, attempt/account correlation, authenticated durable event dedupe, suppression synchronization and restricted reconciliation. No fake-provider guarantee is assumed for a real provider.

Subsequent review sequence: (1) adapter/contracts with mocks and dependent deliverable-content/attempt binding work; (2) authenticated events, suppression and unsubscribe; (3) masked readiness/settings and exact internal allowlist; (4) actual DNS/inbox/webhook/host acceptance before any public pilot. All delivery stays disabled until required controls are verified. Account access, volume/contact count/peak throughput, budget, selected plan/region, approved identity/footer, historical consent and suppression inventory, DPA/transfers/retention, target topology and scheduler/restore ownership remain open. No paid commitment or account configuration is authorized here.

## Numbered implementation phases

Each phase should be a focused PR, split further if review size grows. Phase 1 technical acceptance is **complete** based on merged #1447 and its passed integration evidence. Phase 2 is **in progress** for external acceptance; Phase 3 is **in progress** (3A/3B/3C technical checks and recorded clean review passed; property/external prerequisites remain open); Phase 4 is **in progress** (4A–4D repository checks passed; external acceptance and latest-head review remain open); Phase 5 is **in progress** with 5A documentation and the first bounded 5B contracts complete; production integration is **not started**; Phases 6–7 are **not started**. Repository checks use synthetic addresses/fixtures; external checks require controlled environments.

### 1. Subscriber, consent, suppression and permission foundations

- **Status:** complete (technical acceptance recorded in Phase 2 below). Code, standalone checks and disposable real WordPress/MySQL single-site/multisite migration, unique-key, concurrency and target-blog capability checks have passed (CI evidence below). Historical evidence/suppression-source inventory and retention approval remain outstanding before production intake.
- **Goal/outcome:** staff data has an independent marketing identity; opt-outs cannot be lost by lead edits. Sending remains disabled.
- **Prerequisites:** agree normalization, consent states, permission policy and target-blog scope; inventory historical suppression sources without importing them yet.
- **Work/components:** new subscriber/eligibility repositories and services under `inc/campaigns/`; bootstrap wiring, `inc/schema.php`, schema constant, `inc/roles.php`; dedicated caps and impersonation denial; synthetic fixtures in `tests/`.
- **Database:** subscribers, subscription events and suppression tables with unique mailbox keys and explicit schema assertions; version bump chosen against then-current main. No automatic client conversion, mass backfill or eligibility promotion.
- **Repository acceptance:** idempotent upgrades from prior/current schema, rollback of blog context on errors, duplicate/case/alias tests, unknown consent denied, suppression survives deletion/recreation, newsletter-only identity creates no client/user, employee/anonymous/impersonated writes denied.
- **External checks:** isolated real WordPress/MySQL migration and multisite capability checks; approve evidence/retention policy before production data intake.
- **Risks/rollback:** unique-key collisions and historical ambiguity; fail closed and report conflicts. Disable module on rollback, retain additive tables and suppression evidence; no destructive down migration.

#### Phase 1 concrete implementation decisions (2026-10-17)

Baseline: current GitHub `main` at `d79b49539ef909b3a5c394944a80742ba046d58e`, schema 20. No `AGENTS.md` exists in the recursive repository tree or searched workspace ancestors. Shell Git fetch fails because the configured proxy is unreachable; the connected GitHub API supplied the current main files and authoritative plan. The PR commit is parented directly to current main; local baseline materialization is not included in the PR.

Changed components: `inc/campaigns/schema.php`, `repository.php`, `service.php`; bootstrap wiring; schema version **21**, schema/role upgrade integration; campaign behavior/DB fixture tests and updated authorization schema-version assertion. No campaign screen, import, endpoint, provider, cron, queue, scheduler or sending function is added. Existing operational email code is unchanged.

- **Identity:** independent subscriber rows, nullable CRM client link, no WP-user dependency. Lowercase plus outer trim matches the current CRM fallback and importer rules. Reject invalid/non-ASCII or over-254-byte addresses before persistence; do not repair invalid input with `sanitize_email`, remove plus-tags or fold Gmail dots. Preserve the trimmed deliverable address separately. Address identity is immutable: saving another address creates an unknown identity with independent consent/suppression evaluation. Profile updates allow only client link, explicit BCP47-shaped language tag and a bounded list of topic keys; unsupported fields fail. Language/topic vocabulary and copy ownership remain future product decisions; no inferred preferences or public collection.
- **Client links:** never automatic. Explicit staff linking requires existing client authorization and exactly one matching CRM client, including the legacy `_peracrm_email` alias. Reject ambiguous/absent/trashed matches; leave them unresolved. No lead-ingestion or user-membership helper is called. Unlinking and client deletion/recreation cannot remove mailbox suppressions or rewrite events. Missing/deleted/trashed linked clients and missing/unknown/junk/duplicate party disposition fail eligibility; explicit unlink allows evaluation as an independent identity.
- **Schema:** four target-blog-prefixed InnoDB tables: `peracrm_subscribers`, `peracrm_subscription_events`, `peracrm_email_suppressions`, `peracrm_email_suppression_events`. Subscriber mailbox has a full unique key; suppression mailbox is a primary key. Mailbox columns use ASCII binary comparison with the full 254-byte key, avoiding truncated utf8mb4 keys. Other text follows the site's charset. IDs/version counters are unsigned; timestamps are explicitly UTC via `gmdate`, independent of legacy `peracrm_now_mysql()` local-time semantics. Subscribers retain source/source reference, evidence/policy version, permission timestamps, explicit language/preferences and a monotonic version. Audit events retain mailbox, historical subscriber ID, real actor, source/evidence, occurrence and recording timestamps. No cascading foreign keys or destructive down migrations.
- **Consent:** only `unknown`, `pending`, `subscribed`, `unsubscribed`; explicit staff consent service separate from profile save. Subscribed requires source, source reference, evidence reference, policy version and a valid explicit nonfuture UTC occurrence time. Reject older permission evidence instead of replaying it as a new opt-in. Enquiry consent, CRM membership and contact preferences never grant permission. Public verification/resubscription is deferred. Withdrawal records an unsubscribe tombstone even without a subscriber. Deleting/recreating a subscriber starts unknown and preserves the mailbox history/suppression.
- **Suppression:** global `marketing_email` scope; independent cumulative `unsubscribe`, `hard_bounce`, `complaint`, `administrative` flags in the current mailbox row. Every addition appends a distinct reason/source/time/actor history event, including repeated additions. No Phase 1 clearing operation exists. Generic updates, consent recording, relinking and deletion cannot clear reasons or resubscribe a suppressed address. Subscriber consent state and suppression are separate facts; any active reason wins.
- **Atomicity/failure:** the durable suppression mailbox row also provides a `FOR UPDATE` mutex for mutations, including subscriber creation/deletion, so absent-subscriber races use the same identity lock. Transactionally persist current state plus append-only audit; roll back on errors. Eligibility reads current permission and suppression in one SQL statement, denies missing evidence/unknown permission/storage errors, and requires explicit matching language/topic data for supported positive filters; unsupported filters deny. No internal read requires a staff session. SQL constraints/locking subsequently passed the real disposable database checks recorded below.
- **Permissions:** version 21 follows `peracrm_ensure_roles_and_caps()` and target-blog schema upgrade conventions. Administrators/managers receive exactly the plan's `peracrm_manage_campaigns`, `peracrm_manage_subscribers`, `peracrm_view_campaign_reports`; employees receive none (role re-ensure removes those grants). Subscriber mutation services enforce the dedicated capability on the authoritative blog and reject impersonation; audit uses `peracrm_get_actor_user_id()`, never effective read identity or caller-supplied actor. Repository primitives are internal PHP persistence APIs, not request endpoints. Future provider settings remain administrator-only and are not implemented here.
- **Migrations/rollback:** fresh/repeated/schema-20 upgrades call additive `dbDelta` definitions and assert columns, full unique mailbox keys and InnoDB before advancing the version option. Failures restore target context through the existing `finally` wrapper and leave the previous version marker for retry. No imports, backfills, opt-ins, client conversions or campaign jobs are attached. Define `PERACRM_CAMPAIGNS_ENABLED` as `false` to disable foundation service reads/writes while retaining tables/evidence/suppressions. Removing that rollback flag never clears suppression; no dispatch feature exists to enable in Phase 1.

Initial standalone validation evidence (superseded by the follow-up evidence below): `tests/campaign-foundations-test.php` executes the real foundation/schema/role services and actual target-blog helper against synthetic WordPress/DB fixtures. It covers fresh/repeated/previous-schema wiring, failed DDL/missing-key marker preservation and blog restoration; case/trim/duplicate/plus/dot identity; invalid addresses; pending/unknown/missing evidence denial; preference missing-data rules; independent identity without client/user creation; ambiguous legacy/current aliases; linked duplicate exclusion; all four cumulative reasons; update/resubscribe bypass rejection; deletion/recreation/unlink and new-address evaluation; absent-subscriber withdrawal; audit failure transaction rollback; admin/manager grants and employee/anonymous/impersonated mutation denial; target storage isolation and real-actor audit. No foundation operation calls mail; the unchanged operational enquiry email provider retains valid/invalid-recipient behavior through fake `wp_mail`. All 10 PeraCRM standalone test scripts pass, including client/REST/WhatsApp authorization regressions. PHP syntax checks pass across all 370 plugin PHP files; `git diff --check` passes.

**Initial local validation limitation (superseded by hosted CI below):** no isolated WordPress/MySQL installation or MySQL CLI/server was available; Docker socket access is denied. Stub DB/WP checks do **not** prove real `dbDelta`, mailbox uniqueness under actual SQL collation, concurrent transactions, fresh/schema-20/repeated migration execution, WordPress multisite role caching or real blog restoration. These checks were subsequently run successfully on disposable GitHub-hosted WordPress/MySQL single-site and multisite fixtures; see the dated CI evidence below. Historical suppression-source inventory, approved historical marketing legal basis/evidence mapping and consent/suppression retention periods remain explicitly outstanding; no production data intake is performed or approved. Production topology remains unknown. Phases 2–7 remain unstarted.

#### Phase 1 migration containment and reproducible integration follow-up (2026-10-17)

The registered production `init` callback is now `peracrm_maybe_upgrade_schema()` at priority 5. It contains migration/assertion exceptions, retains the previous schema marker on failure, restores the originating blog and SQL error-display setting, and lets later website/CRM hooks run. Only fixed `schema_verification_failed`, version/state and UTC timing metadata are retained; exception text, SQL and credentials are never stored or displayed. The installer still throws for failed installation/assertions; the request boundary owns containment.

Target-blog `peracrm_campaigns_schema_status` is a persistent circuit breaker: `checking`/`failed` denies campaign capabilities, staff mutations and internal eligibility reads, including when an earlier revision already stored schema marker 21. After an upgrade or read-only assertion succeeds it becomes `ready`; an existing version marker alone cannot enable campaigns. Ordinary requests perform cheap option checks and never automatically repeat a failed attempt. An interrupted `checking` attempt stays disabled until an administrator retries after 15 minutes. There is no background retry or campaign cron.

A core WordPress administrator notice displays a fixed diagnostic and POST retry form. `admin_post_peracrm_campaigns_retry_schema` checks POST, a dedicated nonce and real target-blog `manage_options` authority; impersonation is rejected. The retry service rechecks the same authority and records the real retry actor. It reruns additive upgrades for a prior marker, or read-only verification for an already-v21 marker after explicit database repair. Managers/employees/anonymous users cannot retry or see the diagnostic. `PERACRM_CAMPAIGNS_ENABLED=false` remains an independent rollback gate; retry does not change that flag or enable sending.

Changed follow-up components: `inc/schema.php`, bootstrap hook registration, `inc/campaigns/schema.php`, new `inc/campaigns/readiness.php`, service readiness gate, schema/behavior fixtures, source ownership guard, and `tests/integration/campaigns/` Compose/shell/PHP fixtures plus README. Existing client authorization, operational notification and theme files are unchanged.

Standalone regression tests dispatch the production callback through `init`, inject a campaign DDL failure and missing unique key, verify the old marker/blog/error settings, later hook execution and fake operational email provider availability during failure, denial of campaign writes/eligibility, no repeated DDL on later public requests, sanitized administrator-only diagnostics, POST/nonce enforcement, staff/anonymous/impersonated retry denial, successful administrator recovery and verification of earlier-v21 installations. All **11** PeraCRM standalone tests pass; PHP lint passes for all **375** plugin PHP files; shell syntax, Compose configuration and whitespace checks pass.

Reproducible real integration setup and exact commands: [`tests/integration/campaigns/README.md`](../tests/integration/campaigns/README.md). It uses disposable WordPress 6.8.3/PHP 8.3, WP-CLI 2.12.0 and MySQL 8.0.43 single-site/multisite volumes, blocks email/outgoing WP HTTP, and exposes no host ports. It covers fresh/schema-20/repeated migrations, real SQL mailbox uniqueness, separate-process concurrent mutations/lock contention, target-blog capabilities/isolation/context, and contained assertion failure/recovery. Executed `sh tests/integration/campaigns/run.sh` from the plugin root: runtime stopped before container creation because the local Docker socket returned `connect: operation not permitted` (UID 1000; daemon running). No real integration assertion passed. Compose configuration and shell syntax validation alone do not establish SQL/multisite correctness.

**Historical Phase 1 status (superseded by technical acceptance below): in progress for acceptance review.** Both disposable fixtures have now passed on a GitHub-hosted Docker runner, as recorded below; the earlier local Docker limitation remains unchanged. Historical evidence/retention policy and production topology remain outstanding. Sending remains disabled; Phases 2–7 are unstarted. No merge, deployment or production-data change.

#### Consent-evidence review follow-up (2026-10-17)

The four reference fields (`source`, `source_ref`, `evidence_ref`, `policy_version`) now share explicit string/type/length/sanitization/nonempty validation between recording and eligibility. The string `"0"` is accepted evidence in both paths; missing/null/empty/whitespace/non-string values fail closed. Each field has standalone write/read regressions, including malformed stored rows, plus real-database fixture assertions for accepted zero values and rejected missing/null/empty consent inputs.

Local standalone behavior tests pass. Docker remains unavailable on the current executor (`connect: operation not permitted` on the local socket). Added a narrowly scoped pull-request/manual GitHub Actions workflow using the existing disposable runner, with independent single-site/multisite jobs, read-only token permissions, no production secrets/environment, synthetic data, blocked mail/WordPress HTTP and retained logs. First actual CI run [`39049115966`](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39049115966) on `de6d3eb85fd8001aa53722b975d3b12be7f26f99`: multisite passed all migration, uniqueness, evidence, role/context, recovery and concurrent-mutation assertions. Single-site stopped after the schema-20 upgrade because the fixture context assertion called multisite-only `ms_is_switched()`. Fixed the fixture to call it only on multisite; both topologies subsequently passed in run `39049422823`. This was a fixture failure, not a consent or migration service failure. Phase 1 remains in progress for acceptance review; the required isolated integration evidence is now recorded below. Plugin-only ownership and disabled sending are unchanged; Phases 2–7 remain unstarted.

#### Actual hosted integration evidence (2026-10-17)

GitHub Actions run [`39049422823`](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39049422823), tested commit `7df6669dc6b63a07e4fd487bb8ed136ea271c318`: **single-site passed** (job `117444791176`) and **multisite passed** (job `117444791153`). Both jobs executed the existing disposable runner against real WordPress 6.8.3/PHP 8.3 and MySQL 8.0.43, mounting PeraCRM with a bundled default theme and no repository theme. Synthetic data only; mail/outgoing WordPress HTTP blocked; no production secrets or environment; containers/volumes removed afterward.

Verified in both jobs: fresh activation/no imports; actual init upgrade from schema 20; repeated `dbDelta` preserves records; full subscriber and suppression unique keys reject raw duplicate inserts; case/trim normalization preserves plus-tags/dots; each of the four reference fields accepts string `"0"` and remains eligible while missing/null/empty consent inputs are rejected; target administrator/manager capabilities and employee/anonymous denial; context restoration; contained real missing-key assertion failure and administrator recovery after repair. Four independent WP-CLI mutation processes plus a lock holder verified actual row-lock contention, one normalized subscriber, four distinct suppression history rows, monotonic profile version and no lost active reasons. Multisite additionally verified target-only manager authority, no originating-blog campaign tables/capability grant and restoration to blog 1.

Artifacts (14-day retention, expires 2026-10-31): [single-site log `12107470637`](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39049422823/artifacts/12107470637) and [multisite log `12106738432`](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39049422823/artifacts/12106738432). The preceding run `39049115966` passed multisite but failed a single-site fixture-only `ms_is_switched()` call; that defect was fixed and the full matrix rerun successfully. No production service failure was hidden or waived. Local checks also pass: 11 PeraCRM standalone tests, all 375 plugin PHP syntax checks, shell syntax, Compose configuration and whitespace.

The consent-evidence review finding was replied to and resolved after the explicit validator/per-field regressions were committed. Final documentation records these results without changing tested runtime code. Phase 1 remains **in progress for acceptance review**; historical consent/suppression-source inventory, approved evidence/legal basis, retention decisions and actual production topology remain outstanding before data intake. Isolated SQL/multisite checks are no longer listed as unrun. No Phase 2 work, sending, merge or deployment.

#### Atomic migration-slot review follow-up (2026-10-17)

Replaced the advisory `checking` option write with a nonblocking MySQL `GET_LOCK` slot named by a hash of database and target-blog options table. The lock survives DDL commits and is released automatically on connection termination. Same-connection re-entry is denied rather than recursively acquiring a named lock. A unique attempt token in the private, non-autoloaded `peracrm_campaigns_schema_owner` option fences every status/schema-marker publication and release in SQL, together with `IS_USED_LOCK(...) = CONNECTION_ID()`. A stale token or different connection cannot overwrite readiness/failure, downgrade the marker, or release a newer live owner. Only the acquired owner publishes `checking`/`ready`/`failed`; metadata is freshly read from indexed SQL after acquisition, bypassing stale option caches. Status/marker writes invalidate WordPress option caches.

Administrator retry keeps the existing POST/nonce/real-actor/impersonation checks but no longer deletes status or stamps audit metadata outside the slot. The owner records retry actor metadata. An interrupted `checking` attempt is manually recoverable after 15 minutes only when the native lock is free; elapsed time cannot take over a still-live connection. Public competing requests return without waiting, running DDL, or publishing failure. Failure containment and SQL-error/blog restoration remain in place. No new production test hook, theme dependency, schema version bump or sending path is introduced.

Standalone regressions cover non-reentrant acquisition, stale-token writes/releases, busy live connections, recent interrupted denial and old interrupted recovery. New fixture-only SQL barriers create a real initial-read/acquisition race between separate WP-CLI connections, hold the sole installer in campaign DDL, and verify the loser returns through normal WordPress init before the owner is released. They then resume a genuine old owner after its native lock is lost and a newer authorized retry has published success, verifying rejected late failure/marker writes and rejected release of the successor's live lock. Actual hosted execution passed in both topologies in run `39050763912`, as recorded below; earlier passing CI alone was not treated as proof of this change. Phase 1 remains in progress, plugin-only and sending disabled.

Verified hosted evidence: [run `39050763912`](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39050763912) on runtime commit `8ea4f9dc337e2231cc000660fb79190f7edc587d` passed **single-site** (job `117448694512`) and **multisite** (job `117448694858`). Both logs confirm exactly one connection ran all four campaign installers; the pre-acquisition competitor returned through ordinary WordPress init while the DDL owner was still blocked; borrowed owner tokens could not publish/release from another connection; late loser failure/schema-marker writes were rejected after success; a genuine stale owner could not overwrite readiness or release a live administrator successor; the final slot was free and blog context was restored. Existing fresh/schema-20/repeated, uniqueness, mailbox contention, consent/evidence, target capability and recovery assertions also passed. Artifacts: [single `12107677276`](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39050763912/artifacts/12107677276), [multi `12107906044`](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39050763912/artifacts/12107906044), expiring 2026-10-31. Local checks: all 11 standalone scripts, all 377 PHP syntax checks, shell syntax, Compose configuration and whitespace passed. Phase 1 remains in progress for acceptance review and the previously listed historical evidence/retention decisions; no sending or production action.

### 2. Subscriber management, safe import and public preferences

- **Status:** in progress.
- **Goal/outcome:** managers can preview/import subscribers and staff or recipients can withdraw permission safely.
- **Prerequisites:** Phase 1; approved source/evidence mapping, import limits and token policy.
- **Work/components:** subscriber views/nav, campaign REST/admin-post handlers, public preference endpoint, importer parsing reuse from `inc/admin/importer.php`; independent subscriber apply service, expiry cleanup and audit.
- **Database:** import batch metadata and token digest/version/expiry fields where needed; no plaintext reusable tokens. Reuse batch summary concepts without conflating sales imports.
- **Repository acceptance:** preview has no writes/sends; import suppressions first; reimport cannot resubscribe; missing evidence remains unknown; collisions/ambiguous client links reported; CSV caps and unauthorized access tested; GET cannot mutate opt-out; signed POST is idempotent; invalid tokens cannot enumerate addresses; import file cleanup verified.
- **External checks:** preference URLs on authoritative domain, HTTPS/cache behavior, real multisite redirects and approved consent copy/accessibility. New public collection stays disabled until its confirmation flow is validated.
- **Risks/rollback:** accidental opt-in or token leakage; stop imports/collection, preserve opt-outs and revoke tokens without erasing suppression.

### 3. Draft campaigns, audience preview and frozen content

- **Status:** in progress (technical checks passed for draft/editor, selectors, rendering, audience preview, immutable capture, recipient freeze/publication and review/history UI; completion pending clean review of #1455; external checks below remain open).
- **Goal/outcome:** managers build and review a newsletter with public articles/properties and an accurate exclusion preview; no external delivery yet.
- **Prerequisites:** Phases 1–2; template/footer approval and explicit language/topic rules.
- **Work/components:** campaign repositories/editor, paginated selectors/content adapter, email HTML/text renderer, plugin routing/views/nav/assets and capability checks. Reuse plugin profile/party/assignment accessors; retrieve content/price metadata through WordPress/plugin APIs and plugin-owned adapters. Do not call or include theme card/pricing helpers or templates.
- **Database:** campaigns, snapshots and recipient materialization tables; versioned JSON and unique campaign/mailbox keys.
- **Repository acceptance:** stable snapshot after article/price/template/client edits; private/deleted content excluded at freeze; explicit language/topic filters and missing data obey documented rules; unsupported CRM segmentation is rejected; multiple CRM matches yield one recipient; partial freeze is unsendable; scheduled revision cannot mutate; sanitization, permission and timezone/DST checks; preview exactly reconciles eligible plus excluded counts.
- **External checks:** approve brand/accessibility and mobile previews; verify multilingual/property price semantics against staging content; no inbox claim from browser preview.
- **Risks/rollback:** stale source content and broken images; explicit snapshot preview/approval and draft migration versioning. Disable editor while retaining immutable revisions; no queue is active yet.

### 4. Durable scheduler and provider-neutral worker

- **Status:** in progress — Phase 4A storage/lifecycle, Phase 4B bounded worker, Phase 4C scheduler/CLI health and Phase 4D operational UI repository checks passed; latest-head review and original external staging acceptance remain outstanding.
- **Goal/outcome:** schedule, pause, resume and cancel work reliably against a fake provider; production send feature flag remains off.
- **Prerequisites:** Phase 3; decision on worker dependency, batch/concurrency limits and hosting scheduler.
- **Work/components:** campaign worker, state machine, lease/reconciliation service, dedicated cron hook/CLI and health view; lifecycle scheduling/cleanup consistent with `inc/lifecycle.php`, `inc/cron/push_cron.php` and `inc/cli/push_cli.php`.
- **Database:** attempts, lease ownership/expiry, deterministic send keys, due/retry indexes and unknown outcomes in recipient ledger; transition audit where not already present.
- **Repository acceptance:** real isolated DB concurrency tests with two workers; crash before/after fake acceptance, duplicate cron, lease expiry, timeout/unknown, rate limit, bounded retries, pause/cancel and unsubscribe between freeze/claim/submit. Accepted/ambiguous rows never blindly retry; suppressions win; timezones stored UTC; completion waits on unresolved work; test campaigns cannot consume live ledger.
- **External checks:** staging cron/CLI ownership, task frequency, lock behavior and restore-from-backup procedure with sends disabled. WP-Cron alone cannot promise exact-minute dispatch.
- **Risks/rollback:** duplicate delivery at ambiguous external boundary; hold for reconciliation. Kill switch stops new submissions, preserve ledger/leases and do not reset accepted markers on rollback.

### 5. One provider adapter, authenticated events and internal tests

- **Status:** in progress — Phase 5A evaluation/specification and bounded Phase 5B submission, deliverable capture/approval, recipient token/expansion contracts and bounded durable recipient persistence complete (2026-10-20); concrete production adapter/transport, events, readiness and real delivery implementation not started. See [provider specification](email-campaigns-provider-specification.md).
- **Goal/outcome:** allowlisted internal test mail and delivery feedback work through one approved provider; public campaigns remain disabled.
- **Prerequisites:** Phase 4; provider documentation gate completed, account access granted separately, domain/DNS readiness and retention agreements recorded. Split adapter and webhook review if needed, but enable neither public sends nor imports from provider until both pass.
- **Work/components:** one adapter, masked admin settings/readiness, authenticated event endpoint, durable dedupe/reconciliation, suppression sync, one-click unsubscribe headers/handler and safe diagnostic reporting. Provider-specific fixtures under `tests/`.
- **Database:** event ledger unique provider/account/event key, provider message correlation and diagnostic retention indexes.
- **Repository acceptance:** invalid/missing provider authentication rejected before processing, wrong account rejected, duplicate/out-of-order events safe, persistence failure not acknowledged as success, hard bounce/complaint/unsubscribe blocks pending recipients, no event clears suppression, retry/lookup semantics match verified contract; secret/redaction and test allowlist checks; no real network calls in automated tests.
- **External checks:** dated provider docs and plan limits; verify account/region, SPF/DKIM/DMARC, authenticated webhooks, provider retry behavior, suppression import/sync, genuine internal Gmail/Outlook/mobile inbox rendering, text version, reply-to, unsubscribe and bounce simulations where provider supports them. Do not generate real complaints to test.
- **Risks/rollback:** provider outage or compromised key; pause all new claims, rotate key and keep preference/webhook processing available. Do not roll back suppression knowledge or assume accepted mail can be recalled.

### 6. Useful MVP: reporting, timeline and controlled campaign pilot

- **Status:** not started.
- **Goal/outcome:** admins/managers can run a reviewed small newsletter or targeted campaign end-to-end and explain every recipient outcome.
- **Prerequisites:** Phases 1–5 verification complete; approved audience evidence, sender identity, pilot size/volume cap, operator and stop thresholds. Optional opens/clicks remain off.
- **Work/components:** recipient/report views, concise idempotent activity entries and labels in frontend/admin timeline, campaign UTM attribution capture in enquiry integration, operational readiness/runbook and feature gates.
- **Database:** indexed reporting/correlation fields only if needed; unique timeline emission marker to prevent replay duplicates. No automatic pipeline updates.
- **Repository acceptance:** totals reconcile against ledger/events; authorization prevents report/client leakage; attribution preserves URL query strings and excludes personal data; webhooks do not duplicate timeline entries; deletion/retention checks preserve minimal suppression; existing enquiry email/logging and client authorization regression tests pass.
- **External checks:** first seed-only campaign, then explicitly approved small opted-in pilot; monitor accepted versus delivered, unsubscribe latency, bounces, backlog and rate limits; verify pause/kill switch and document evidence. Increase caps only after review of predefined provider/account reputation thresholds. Production delivery is a future implementation rollout action, not authorized by this review task.
- **Risks/rollback:** audience mistakes or reputation damage; stop new sends immediately, keep opt-outs/events processing, reconcile in-flight results. Roll back UI/worker via gates while preserving consent and delivery history.

### 7. Optional improvements after stable MVP

- **Status:** not started.
- **Goal/outcome:** justified enhancements such as reusable template library, saved audiences, explicit topic self-service, optional engagement reporting and richer attribution.
- **Prerequisites:** stable pilot evidence and a separate scoped decision for each addition; privacy review before tracking.
- **Work/components:** template/audience repositories and screens; optional normalized engagement aggregates; explicit human-led follow-up workflows. Advanced automation and complex visual builders require separate proposals and are outside MVP.
- **Database:** versioned templates/saved-rule definitions or aggregate tables only for approved scope; migrate snapshots without rewriting sent content.
- **Repository acceptance:** template edits never change frozen campaigns; tracking can be disabled; scanner/proxy effects are labelled; no lead qualification/stage change from opens/clicks; permissions and retention cover new data.
- **External checks:** verify tracking-domain DNS, current provider event semantics and permission requirements; assess real inbox behavior and reporting limitations.
- **Risks/rollback:** unnecessary complexity and misleading signals; gate optional modules independently, retain core delivery/suppression services.

## Validation and rollout ledger

Repository-verifiable during this documentation review: confirmed plugin path/bootstrap and cited functions, source-only consent/email/queue inventory, existing docs/tests conventions, clean initial worktree, GitHub main comparison. Documentation checks: validate cited paths/symbols, Markdown diff whitespace, phase statuses, single authoritative file and documentation-only PR diff. No runtime test is needed for a Markdown-only change.

Still outstanding for production implementation: production WP/plugin activation/configuration, live target-blog topology, data quality and historical consent/suppression completeness, production database compatibility/restore verification, hosting cron health, provider account/product access, current documentation contract, DNS authentication/alignment, signed webhook delivery/replay, real inbox and opt-out testing, privacy/retention approval and supervised pilot evidence. Repository source cannot establish these facts.

Rollout order: migrations/permissions with sending off → subscriber preview and suppression intake → draft-only editor → fake-provider queue → internal allowlist → small approved pilot → bounded expansion. Maintain separate flags for collection, imports, worker dispatch and optional tracking. Disabling dispatch must leave unsubscribe and event intake working. Backup restore must replay/reconcile suppression and accepted-send evidence before workers resume, to prevent restored old state causing duplicate sends or resubscription.

## Progress log

| Date | Status | Evidence / decision |
| --- | --- | --- |
| 2026-10-20 | Phase 5 in progress — bounded recipient services | Purpose/deployment-separated tokens and exact typed URL expansion into immutable recipient copies. All 22 local plugin suites and final hosted single/multisite SQL/race/browser checks passed; exact evidence below. Public handlers, registry, durable attempt binding, key/retention readiness and worker/transport integration remain dependent work; gates unchanged. |
| 2026-10-20 | Phase 5 in progress — bounded deliverable rendering/capture complete | Versioned HTML/text links and typed URL slots; exact approval binding; immutable historical compatibility; inert staff display. All 11 local campaign suites and final hosted single-site/multisite plus desktop/mobile checks passed on `3302460e`. Token issuance/expansion, attempt binding and concrete worker/transport integration remain dependent work; production gates unchanged. |
| 2026-10-20 | Phase 5 in progress — first bounded 5B contracts complete | Immutable envelope, default-deny abstract resolver and injected SES mapper; ten standalone campaign suites and PHP syntax/whitespace checks passed. Review-only snapshots rejected; no real transport, factory or production wiring. Deliverable renderer/capture, recipient links/tokens and durable attempt binding remain dependent work. Dispatch/intake/public collection disabled. |
| 2026-10-20 | Phase 5 in progress — 5A documentation complete | [Provider specification](email-campaigns-provider-specification.md): recommend SES v2 with unknown-outcome holds; Brevo marketing campaign API has worker-fit gaps. Runtime implementation not started. User-reported live queue UI evidence recorded separately below; no emails sent. CLI/host/restore/property/usability readiness remains open; dispatch/intake/public collection disabled. |
| 2026-10-17 | Review complete | Inspected PeraCRM plus relevant theme integrations; verified main comparison at `41036e8218b1da320608b866210eaaf2bee60d62`. No runtime or production data access. |
| 2026-10-17 | Plan documented | Prefer plugin-owned campaigns, separate subscriber/consent/suppression ledger, frozen recipient/content snapshots and one provider adapter. Brevo is provisional pending documented evaluation. |
| 2026-10-17 | Implementation not started | Phases 1–7 unstarted. First implementation task is Phase 1; all live/provider/DNS/inbox checks remain outstanding. This review authorizes no rollout. |


| 2026-10-17 | Phase 1 in progress | Implemented schema 21 independent identities, consent/events, cumulative suppressions and dedicated role/service authority; standalone tests, plugin PHP lint and whitespace checks pass. Real isolated WordPress/MySQL migrations, unique keys/concurrency and multisite permissions remain unverified; historical evidence/retention decisions remain open. Phases 2–7 unstarted; no sends, production writes, merge or deployment. |
| 2026-10-17 | Phase 1 in progress — PR #1447 follow-up | Contained init migration failures; added persistent readiness/failure gating, sanitized administrator diagnostics and controlled retry; added default-theme disposable WordPress/MySQL single/multisite fixtures and plugin ownership requirement. 11 standalone tests / 375 PHP lint files plus shell/Compose/whitespace checks pass. Integration runtime blocked by Docker socket permissions; SQL/multisite acceptance remains outstanding. |
| 2026-10-17 | Phase 1 in progress — consent-evidence review | Shared explicit evidence validation recognizes string zero in all four reference fields; standalone per-field regressions pass. Added scoped disposable single/multisite GitHub Actions jobs because local Docker socket is blocked. Actual CI execution evidence pending. |
| 2026-10-17 | Integration CI partial result | Run 39049115966: multisite passed; single-site exposed a fixture-only call to multisite-only ms_is_switched(). Guarded that call with is_multisite(); the succeeding run is recorded below. Phase 1 remains in progress. |
| 2026-10-17 | Phase 1 in progress — hosted integrations passed | Run 39049422823 on 7df6669dc6b63a07e4fd487bb8ed136ea271c318 passed both real WordPress/MySQL single-site and multisite suites, including migration/unique-key/concurrency/target-capability/evidence checks. Fixed the initial single-site fixture defect. Evidence finding resolved; acceptance review and historical evidence/retention decisions remain open. |
| 2026-10-17 | Phase 1 in progress — atomic migration-slot review | Implemented target-blog native SQL lock, attempt-token/connection-fenced status and marker publication/release, owner-only retry metadata and fresh SQL reads. Added standalone and separate-connection migration race/stale-owner regressions; hosted results pending. |
| 2026-10-17 | Phase 1 in progress — migration serialization verified | Run 39050763912 on 8ea4f9dc337e2231cc000660fb79190f7edc587d passed single-site/multisite, including real competing migration, sole DDL owner, available loser, stale-result fencing and administrator interrupted-attempt recovery. 11 standalone tests / 377 PHP files plus shell/Compose/whitespace checks passed. |


## Phase 2 implementation record (2026-10-17)

PR #1447 merged at 2026-10-17 19:05 UTC; latest main checked through GitHub is `afc16f1ae4347a7e3f6484caaf268fd66863ce04`. Phase 2 starts on that content and targets main. Shell network permissions blocked fetch; the connector supplied the merged patch and main metadata. No AGENTS.md was found in workspace or repository ancestors.

### Phase 1 technical acceptance

Phase 1's technical foundation acceptance passed: all 11 standalone scripts, 377 PHP syntax checks, and real single-site/multisite WordPress/MySQL suites on final Phase 1 runtime commit `271c3c21d5214a3ae6caaa12fd119115355b79e1`, [run 39050980823](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39050980823), jobs `117449332828` and `117449333103`. Earlier contention/migration acceptance evidence remains in the Phase 1 ledger. This accepts technical dependencies for Phase 2; it does **not** approve historical marketing permission, suppression-source completeness, retention, production topology or real-data intake. Those prerequisites remain open. Phases 3–7 remain not started.

### Architecture and operating policy

- Schema 22 additively adds target-blog InnoDB batches, import rows and token digests. The existing fenced schema owner performs migration/assertions; Phase 1 mailbox transactions serialize consent/suppression and import completion. Staff listing/detail/suppression reads require `peracrm_manage_subscribers`; writes independently reject impersonation. Employees have no list/import authority. All routing, rendering, CSS/JS, navigation and assets belong to PeraCRM.
- Routes: `/crm/subscribers/`, numeric detail, `import/`, `suppression/`; public `/email-preferences/` on the configured target-blog home domain. Profile edits only change explicit language, topics and an authorized unambiguous link. They cannot change identity, consent or suppression. Staff withdrawal is separate. No public collection, confirmation delivery, campaign editor, provider, delivery worker, scheduling or sending is introduced. The hourly task only expires temporary previews.
- CSV format: UTF-8-compatible comma-separated CSV with heading row; explicit zero-based column mapping, 1 MiB, 1000 data rows, 64 columns, 25 apply rows/request, 50 preview rows/page, at most 10 active batches/owner. Required mappings: mailbox, state, source/reference, evidence reference, policy version and UTC occurrence time; optional explicit language and pipe-separated topic keys. No names, phones or raw enquiry payloads are retained. Missing source or occurrence time excludes a row; missing consent reference/policy remains unknown. States: unknown, subscribed, unsubscribe, complaint, hard_bounce. Preview reports duplicates, conflicting states, existing identities, missing evidence, existing/batch suppression and unresolved client matches.
- Preview changes **temporary batch bookkeeping only**, never subscriber/consent/suppression tables. Uploaded PHP temporary files are read directly, checked for real upload ownership and bounds, and unlinked after parse or failure; no file is moved to uploads, webroot, or a predictable shared directory. Mapped rows are stored in target-blog tables scoped to their authenticated owner. Even administrators cannot view another owner's batch. No filename, bearer URL or filesystem path is accepted as a batch identity.
- Apply rechecks authority, readiness and live suppression. Suppression rows commit before contact rows, including conflicts in the same file. Opted-in contact rows require an explicit batch confirmation that all historical suppression sources were imported. Unknown/previously existing/withdrawn identities are never promoted by reimport. Suppressions always win; all client matches remain unlinked until an explicit authorized profile edit. No client/user creation, republishing, merging, trashing or automatic linking occurs. Historical occurrence timestamps are retained while audit receipt times remain current.
- Each row's mailbox mutation and completion marker share a transaction; connection loss rolls both back. Native batch locks serialize apply/cancel/cleanup. Earlier committed rows survive a later failure; retries process pending rows only, at most three storage failures/row. Permanent suppression failures block all remaining contacts. Terminal summaries retain counts/results without mailbox payloads; completion, cancellation, permanent partial failure and 24-hour expiry delete mapped rows. Interrupted batches retain pending work until retry or expiry. Cleanup runs hourly (bounded to 50 batches/pass), requiring a functioning WordPress scheduler; reads hide expired payloads immediately. No rollback removes suppression. Downloaded CSV cells neutralize formula prefixes, including leading whitespace/control characters.
- Real-data intake defaults **off**. A target-blog administrator, outside impersonation, must record historical evidence mapping, suppression-source inventory/intake and approved retention decision references and explicitly enable the gate. Managers cannot enable it. Each real batch apply rechecks fresh SQL gate state. Previews work while closed. Synthetic apply is restricted by the server to example.test mailboxes, regardless of checkbox claims. Manual historical suppression intake uses the same gate; immediate withdrawal remains available.
- Public tokens contain 256 random bits encoded as opaque hex; database stores SHA-256 digests, fixed `preferences` purpose, version 1, mailbox binding, creation time, optional expiry and revocation. No addresses/CRM IDs occur in links. Internal issuance/revocation is staff-authorized only; no public mint endpoint exists. Default expiry is **none**, to honor opt-out links in old emails; tokens remain valid only for that mailbox and purpose until staff revocation/version retirement. Deployments may set explicit future expiry at issuance. Tokens never grant consent, and deletion/recreation cannot remove mailbox suppression. Production token retention/revocation policy still requires approval before messages exist.
- GET/HEAD only validate and display; no state mutation or consent audit occurs. Deliberate POST separates global withdrawal from topic replacement. Public service fixes identity/reason/actor/evidence server-side and reuses Phase 1 atomic persistence/audit, without staff-authorized methods or permission weakening. Repeated opt-outs return success with no duplicate consent/suppression audit. Topics cannot grant consent or clear global suppression. Invalid/revoked/expired/purpose/version errors use the same generic response. Public renderer omits theme hooks, analytics and third-party assets; responses set no-store, no-referrer, noindex and restrictive CSP. Bounded hourly hashed counters constrain topic updates; valid repeated withdrawals remain available. No raw IPs/bearers are retained by abuse counters. Reverse proxies must honor no-store and redact token query strings in access logs before future delivery.
- The public POST service is deliberately shaped for later one-click integration, but no provider/header/one-click compatibility is claimed or enabled. It does not accept a generic caller-selected mailbox, reason, evidence or audit actor.

### Phase 2 validation ledger

Local: all existing 11 standalone scripts pass after schema-version updates; new bounded parser/validation/export-safety regression passes. All 381 plugin PHP syntax checks, shell syntax, Compose config, JavaScript syntax and whitespace checks passed. Local Docker socket access is denied (`operation not permitted`), UID 1000; no daemon restart or production connection attempted.

Real fixture additions cover migrations from Phase 1; preview isolation; permissions and cross-user access; suppression-first, duplicate/repeated/partial application; missing evidence and historical timestamps; readiness; expiry/cancel/permanent failure cleanup; opaque token binding/purpose/version/revocation/expiry; idempotent public POST; independent topic/global state; client/user side effects and ambiguous matches; originating-blog restoration; separate MySQL connections racing withdrawal/import. Disposable browser checks are wired for desktop/mobile plugin screens and public GET/POST on the bundled default theme. Actual run results and any unavailable browser checks will be recorded before acceptance. Phase 2 remains **in progress** until required results are available.

Outstanding production decisions: historical list/legal-basis inventory, evidence mapping, suppression sources/intake completion, approved retention periods (including summary/token/audit lifetimes), language/topic taxonomy and translated/approved preference copy, actual target-blog topology, HTTPS/cache/log-redaction behavior and scheduler ownership. No production contacts were imported and no mail was sent. Phases 3–7 remain unstarted.

| Date | Status | Evidence / decision |
| --- | --- | --- |
| 2026-10-17 | Phase 1 technical acceptance recorded | Merged #1447 and passed final single/multisite run; historical consent/retention/readiness prerequisites retained. |
| 2026-10-17 | Phase 2 in progress | Plugin-owned subscriber management, isolated CSV previews/application, suppression intake, readiness gate and public token-authorized preferences; sending and new collection stay disabled. |

### Passed Phase 2 technical validation

Both real topologies and Chromium browser flows passed in [run 39070911377](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39070911377), runtime commit `857d8ffc52e0535939284c9fa402c0d4bdc0acec`:

- Multisite job `117508193531`: success; [logs/screenshots](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39070911377/artifacts/12115197526).
- Single-site job `117508193669`: success; [logs/screenshots](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39070911377/artifacts/12115131536).

The jobs include all existing foundation migration/contention tests; actual Phase 1→22 migration; new unique-key assertions; preview changing no subscriber/consent/suppression tables; duplicates, conflicts, repeated/partial apply; missing evidence/historical times; suppression ordering and both import/withdrawal transaction orders; real-data readiness and suppression confirmation; employee/anonymous/impersonation/owner restrictions; injected storage failures and transactional cleanup retry; cancellation/expiry; ambiguous CRM links with no client/user side effects; token binding/purpose/version/expiry/revocation; topic/global separation; target URLs/host isolation and context restoration.

Browser flows passed on a bundled default WordPress theme at 1440×1000 and 390×844: list/detail/import/suppression rendering, horizontal overflow assertion, actual CSV upload/explicit mapping/preview/apply, preview subscriber-count invariant, formula-safe downloaded CSV, employee GET/POST denial, missing nonce rejection, public GET/reload leaving consent subscribed, valid/repeated POST immediately unsubscribing, invalid tokens, anonymous staff read denial and no-store/no-referrer response headers. Screenshot artifacts expire 2026-10-31. A mobile content-box overflow was found and fixed in plugin `crm.css`; a single-site target-ID sentinel bug was found and fixed in plugin public routing. Earlier invalid synthetic/owner-switch fixture errors were corrected without relaxing guardrails. No failed service check was waived.

Subsequent checks also cover partial summaries after a later eligibility guard throws, the CRM sidebar/mobile drawer, plugin-resolved public assets and checkbox topic POSTs. Final runtime evidence below supersedes the earlier intermediate run. Small synthetic review images in CI logs enable visual inspection through API-only tooling. Local browser execution remains unavailable because the Docker socket is blocked. Hosted Chromium desktop/mobile checks ran successfully. Safari/Firefox, manual assistive-technology/copy approval and production HTTPS/cache/log-redaction checks were not available and are explicitly outstanding.

**Phase 2 remains in progress for external acceptance:** historical legal-basis/evidence mapping, suppression inventory/completion, approved retention, translated/canonical topic copy, actual topology and production HTTPS/cache/log/scheduler verification remain required before real intake/future delivery. Technical acceptance is passed; this PR does not approve those operational prerequisites. Phases 3–7 remain not started.

| Date | Status | Evidence / decision |
| --- | --- | --- |
| 2026-10-17 | Phase 2 technical checks passed | Run 39070911377: real single-site/multisite plus default-theme Chromium desktop/mobile and actual upload/public POST flows passed. Gate remains closed by default; external acceptance outstanding. |

### Final runtime acceptance evidence

All required technical checks passed on final runtime commit `1861cb58a4613b0cf8043097f21b2d9933e372f9` in [run 39072151825](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39072151825):

| Topology | Actual result | Retained evidence (until 2026-10-31) |
| --- | --- | --- |
| Single-site | Job `117511809241`: success, including Chromium flows | [Logs and screenshots](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39072151825/artifacts/12115835403) |
| Multisite | Job `117511809415`: success, including Chromium flows | [Logs and screenshots](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39072151825/artifacts/12115597512) |

The final run additionally verifies durable summaries when a later confirmation guard rejects a partially applied batch; subscriber-page mobile drawer open/close; public stylesheet application and mobile overflow; and real checkbox topic POST preserving global permission before global/repeated unsubscribe. Visual review of the emitted desktop/mobile list and mobile detail/import/suppression/public screenshots confirmed plugin rendering and responsive forms/table scroll regions on the bundled default theme. It caught and corrected a missing public asset URL separator, inherited public font specificity and the shared drawer CSS overriding its hidden attribute; fixes remain plugin-owned and scoped. The public form uses provisional human-readable News / Property updates choices, with final taxonomy/translation approval still outstanding. No failed behavior check was waived.

All 12 local standalone scripts, 381 PHP syntax checks, JavaScript/shell syntax, Compose configuration and whitespace checks pass. Both connection race orders pass against actual MySQL. Batches missing suppression confirmation must be cancelled and re-previewed with confirmation after source intake; the confirmation is immutable on an existing batch. Earlier committed unknown rows remain unknown and retry summaries remain accurate. Failure redirects retain the batch review location.

Phase 2 technical implementation and validation are complete. The phase's overall status remains **in progress** for the explicitly outstanding external acceptance / production readiness decisions above; public collection and real-data application default off, and no delivery exists. Safari/Firefox, manual assistive-technology/copy approval, actual topology and production HTTPS/cache/referrer/log/scheduler verification are unavailable in these disposable fixtures. Historical evidence/legal basis, suppression-source completeness and retention approval are not inferred from technical tests. Phases 3–7 remain not started.

| Date | Status | Evidence / decision |
| --- | --- | --- |
| 2026-10-17 | Final Phase 2 technical acceptance passed | Run 39072151825 passed single-site/multisite database and default-theme browser checks on final runtime 1861cb58; visual screenshots reviewed; external readiness gates retained. Non-draft PR #1448 targets main; no merge/deploy. |

### PR #1448 review follow-up: hidden client links

Profile POSTs now update only language/topics; empty or masked legacy client fields preserve stored links. Linking is a separate explicit form/action, and unlinking uses a dedicated staff service. Both replacement and unlink authorize the actual stored client inside the mailbox transaction, independently of submitted flags or masked reads. Permission/impersonation checks, audit, target-blog context, consent and suppression remain unchanged. No schema change; all changes remain plugin-owned. Real intake/public collection remain disabled; Phases 3–7 remain unstarted.

New standalone and real WordPress/browser regressions cover masked profile edits, crafted unlink/replacement requests, explicit authorized linking/unlinking, idempotence and suppression/eligibility preservation. Follow-up validation passed on runtime `cf2c1601ee59b7461a673810af245173a9b85e55`: [run 39101954999](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39101954999), single-site job `117599870238` and multisite job `117599870177`, both including default-theme Chromium desktop/mobile and forged-request checks. All 12 standalone scripts, 387 PHP syntax checks, browser JavaScript syntax and whitespace checks passed locally. The mobile detail screenshot was visually reviewed. The first multisite browser attempt exposed synthetic registration assigning the lead role before staff capabilities existed; fixture setup now explicitly initializes its scoped staff role, and both complete suites passed afterward. Production permissions were not relaxed. Local Docker remains socket-blocked; Safari/Firefox/manual assistive-technology checks remain unavailable. No readiness prerequisite was waived.

| Date | Status | Evidence / decision |
| --- | --- | --- |
| 2026-10-18 | Hidden-client-link review fix verified | Run 39101954999 passed both database/browser suites; explicit stored-link authorization replaces masked-field unlink semantics. Real intake/public collection remain disabled; Phase 2 external acceptance pending. |


## Phase 3 recovery and dependent delivery plan (2026-10-18)

Recovery found a clean `work` branch at `afc16f1a`, no Phase 3 implementation,
no new commits and no existing email Phase 3 PR. The first shell fetch failed
because the proxy was unreachable; the permission-enabled retry was aborted.
No test/fetch remained running. GitHub confirmed #1448 merged at 05:59:40 UTC as
`413fcbfd17ef49a062820d3380e0d1ce1cf988bd`; comparison to main was identical.
Its latest review (06:01:03 UTC, reviewed `dd9692f986`) left two unresolved
findings: deleted client reference repair and unbounded per-row CSV lookups.
No applicable AGENTS.md was found in repository/workspace ancestors or tree.
The GitHub connector supplied all 34 merged files at that exact commit for local
validation; publication uses a branch based on the actual remote main, avoiding
a synthetic local synchronization commit in the PR.

### First PR: prerequisite review fixes

- Explicit unlink still requires subscriber capability, a real non-impersonated
  actor and the mailbox transaction. Missing post references may be cleared only
  by `manage_options` / `peracrm_manage_all_clients` actors. Live links retain the
  existing client-scope check. Missing references stay masked; a separate derived
  `can_unlink_client` flag exposes the existing explicit unlink form. Consent,
  suppression and identity do not change; repair is audited and idempotent.
- CSV preview validates first, then looks up distinct normalized mailboxes in
  chunks of 100. Each chunk uses one identity/suppression union and one grouped
  distinct-client count, covering all five canonical/legacy email aliases. A
  1,000-distinct-mailbox preview performs 20 lookup queries (not 20 total writes
  or total request queries). No client IDs are loaded or selected, duplicate
  aliases on one client count once, ambiguity stays unresolved, trash and other
  statuses excluded by WordPress `post_status=any` semantics do not match.
  Alias values explicitly use lower-case/trim normalization. Suppressions without
  subscribers are preserved. Query errors abort before preview bookkeeping.
  Postmeta aggregation may still scan a large table; this removes per-row scans,
  not a claim of a canonical indexed CRM email directory or a production timing SLA.
- No schema, campaign UI, content adapter, renderer, snapshot, recipient ledger,
  token issuance, delivery, queue, worker or scheduling change is included. All
  code/views remain PeraCRM-owned; no theme files/assets are changed. Production
  intake/public collection remain disabled by existing gates.

Validation: all 12 standalone PHP scripts and 381 plugin PHP syntax checks pass
using the bundled PHP 8.4 runtime. Browser JavaScript syntax, both runner shell
syntax checks, Compose configuration and `git diff --check` pass.
New checks cover deleted-link full/scoped authority, restored newsletter-only
eligibility without granting consent, audit/idempotence, 1,000-mailbox lookup
bounds, suppression union, missing data, ambiguity, normalization/deduplication,
error/context restoration. Real single-site/multisite assertions were added for
SQL counts/aliases/trash/absence and actual deleted-post repair. Browser fixtures
now exercise scoped denial, actual administrator repair/save/reload and desktop /
mobile repair screenshots with overflow assertions; those flows have not yet run. Local Docker
socket health check fails `connect: operation not permitted` (UID 1000); no local
SQL/browser suite ran and no new screenshots exist. Hosted CI must establish
those results before technical acceptance; prior #1448 evidence does not validate
these changes. Browser previews remain distinct from future inbox rendering.

### Original dependent Phase 3 sequence recorded by #1449 (historical)

1. Draft/revision storage and structured editor: additive target-blog owned
   migrations/readiness/assertions; dedicated capabilities on routes/services;
   immutable revision history and stale-save conflicts; list/create/edit/duplicate
   using the CRM shell, `assets/frontend/crm.css` and `crm.js`; approved sender,
   reply-to, identity/address/footer configuration and bounded text/block inputs.
2. Content selectors, renderer and audience preview, depending on draft storage:
   paginated public article/property adapters; canonical v2 pricing; explicit
   language/translation availability; unavailable states for theme-owned data;
   inline email styles; sandboxed desktop/mobile and text previews; bounded
   exactly reconciled audience evaluation. Initial campaign filters are **language
   and topic only**, using explicit subscriber data. **No new campaign audience
   filter is implemented by this prerequisite PR.** Existing subscriber-list
   search/state/suppression/language/topic filters are unchanged. CRM segmentation
   remains unsupported until its data/semantics are verified; missing positive
   data cannot match and ambiguous relationships cannot be resolved implicitly.
3. Explicit review and atomic publication of frozen revisions, depending on both:
   fresh source/eligibility validation; immutable HTML/text/structured metadata
   and personalization; bounded resumable/cancellable membership materialization
   with database campaign/mailbox uniqueness; no concurrent edits; publish only
   after success. Preserve frozen history, never substitute a changed client
   mailbox, never mint public tokens in shared HTML; controlled preference-link
   placeholders only. Frozen URLs do not make image files immutable. Historical
   membership never authorizes later delivery without live eligibility checks.

Each dependent PR must add the relevant single-site/multisite regressions and
exercise actual desktop/mobile controls on a bundled default theme, inspect
screenshots and record evidence. Full Phase 3 remains incomplete. Historical
consent/evidence, suppression-source completeness, retention, approved sender /
footer / translations, domain readiness and production topology/readiness gates
remain outstanding. Phases 4–7 stay **not started**. No email delivery/test sends,
provider integration, attempts/leases/retries, queue, worker, scheduling,
production subscribers, merge or deployment is authorized by this record.


## Phase 3A: campaign draft storage and structured editor (2026-10-18)

Baseline: #1449 merged at `04a7d93aa0c6b584dbb875c94cd9d51849cc17f4`.
Connected GitHub confirmed this was latest main at task start. The initial branch
was clean `work` at `413fcbfd`. Shell fetch failed once because the configured
proxy was unreachable. The local synchronization tree exactly matches main tree
`a37c485b83c3b94cf0ef609b7aa37fbba405cb8e`; the publication commit is parented
on actual merged main, excluding the local synchronization commit. Work branch:
`feat/campaign-draft-editor`. No AGENTS.md was found in repository/workspace
ancestors or tree. #1449 is no longer an unmerged dependency.

### Prerequisite acceptance evidence

[#1449 CI run 39108549138](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39108549138)
passed on `54049e6fa1dc5575ecde2fa9800d7260b02362aa`: multisite job
`117619543524`, single-site job `117619543740`, including disposable MySQL and
bundled-default-theme desktop/mobile browser controls. The [Codex review summary](https://github.com/7mus7y-dotcom/pera-wp/pull/1449#issuecomment-6247238443)
completed at 07:42:10 UTC with no findings/unresolved findings on that same head.
There were no formal review submissions. This records prerequisite evidence;
it does not establish Phase 3A runtime acceptance or production readiness.

### Implementation decisions

- Schema 23 adds only target-blog InnoDB campaign drafts, immutable draft
  revisions and a singleton approved configuration table. Existing nonblocking
  connection-owned migration slot, fenced publication, readiness assertions,
  circuit breaker, sanitized failure notices and administrator retry are reused.
  Assertions require draft/config primary keys and the full unique
  `(campaign_id, revision)` revision key. No recipients, snapshots, delivery
  tables, campaign public tokens or machinery are created.
- Dedicated `peracrm_manage_campaigns` checks protect routes, reads and writes.
  Real actors are audited; all impersonated writes fail. Configuration editing
  and administrative reads additionally require target-blog `manage_options`.
  Editor reads expose only approved identities/footer; employees cannot access
  campaigns. All URLs and storage use the authoritative target-blog wrapper.
- Creation and explicit saves transact current document plus append-only history.
  Locked current revision and conditional updates reject stale saves with HTTP
  409, without adding history or changing newer content. Revision-insert failures
  roll back current content. Configuration also uses revision checks. No history
  update/delete or restore endpoint exists. Duplication creates a new draft at
  revision one with copied authored fields only, a new real author and no source
  history, recipients or tokens.
- Structured document schema version 1 permits plain text/CTA blocks only; later
  block types require explicit versioned validation. Bounds (bytes): name 160,
  subject 200, preheader 300, explicit language 35, sender key 64, introduction
  4,000, main text 16,000, at most 20 blocks (text heading/CTA label 160,
  block text 4,000, HTTP(S) URL 2,048), total encoded document 128 KiB. Unknown
  fields/types fail; HTML is sanitized to plain text; credential-bearing and
  non-HTTP(S) CTA URLs fail. No article/property selectors are present.
- Configuration supports up to ten stable sender keys, From/reply-to approval,
  mandatory plain-text footer approval, real approving actor and revision.
  Approval records an editorial decision, never DNS/domain/provider verification.
  Drafts reference sender keys and display current approved footer; revisions
  preserve authored content, not an immutable configuration/email snapshot.
  Changed/revoked/missing approvals appear as missing requirements and never
  prevent a draft save or erase content. Language is explicit, with no inference.
- `/crm/campaigns/`, `/new/`, numeric detail/editor and `/configuration/` use the
  existing plugin CRM shell/navigation/enqueue conventions. All CSS/JavaScript
  additions are in `assets/frontend/crm.css` and `crm.js`. Explicit Save shows
  unsaved/saving/saved/error status; before-unload warns on edited content.
  Validation errors (422) and conflicts retain submitted content and its original
  revision in the same response; a separate saved-version link supports manual
  reconciliation. No autosave. JavaScript adds/moves/removes blocks and identities;
  existing content remains editable without it. List and history are paginated.

### Phase 3A verification record

All 13 standalone PHP regression scripts, all 394 plugin PHP syntax checks,
browser/CRM JavaScript syntax, both runner shell syntax checks, Compose
configuration and whitespace checks pass.
Real fixture coverage adds fresh/22-upgrade/repeated migrations and persisted
activation/v3-upgrade rewrite rules, unique-revision
assertion failure containment and recovery; authorization/impersonation/target
isolation; create/save/reload/order/duplicate; history/real actor; two independent
connections contending on one draft; revision-insert rollback; sanitization and
bounds; administrator configuration; missing/revoked approvals preserving drafts.
Browser fixtures exercise actual controls, invalid-input retention and two-tab
stale-save conflicts at desktop/mobile sizes, and save screenshots on the bundled
default WordPress theme with no repository theme mounted.

Local Docker socket check failed `connect: operation not permitted` (UID 1000;
daemon running). No repeated Docker retry was made. Hosted acceptance passed on
`e8fde98dbba99ce1051b155a618f0018f00a6ca6`: [run 39112405507](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39112405507),
single-site job `117631126743` and multisite job `117631127042`, both successful.
These ran real WordPress 6.8.3/PHP 8.3/MySQL 8.0.43, with Twenty Twenty-Five
and only PeraCRM mounted. All earlier subscriber, suppression, import, migration
ownership and public-preference regressions also passed. No failed assertion
was waived. Earlier failed/cancelled runs are not acceptance evidence.

Screenshots/artifacts: [single-site bundle](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39112405507/artifacts/12130689128)
and [multisite bundle](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39112405507/artifacts/12129979723),
retained until 2026-11-01. Each contains full PNG captures and logs. Emitted JPEG
review copies of actual desktop/mobile campaign list/editor/configuration PNGs,
mobile conflict/approved states and multisite mobile editor/configuration were
visually inspected. Forms, block controls, approval controls, mandatory footer,
retained conflict content and save/error statuses were readable within the page;
mobile tables intentionally use the existing labelled horizontal scroll region.
Automated overflow checks passed. These are browser/editor screenshots, not
email or inbox rendering evidence. Final user-facing copy removes development
sequence details and explains that administrator configuration can change sender
and footer values without changing authored text; this does not change behavior.

Limitations: no autosave; conflicts require manual reconciliation in a second tab;
JavaScript is needed for adding/reordering/removing blocks and identities; no
manual assistive-technology, Safari/Firefox or production/topology verification
is claimed. Administrator approvals are editorial, not domain/provider checks.
Phase 3 remains in progress for the two dependent portions below.

Review/verification corrections: stable sender identifiers reject sanitization
that would silently change the key; the rewrite version advances to v4 and the
shared registrar includes campaign rules during activation; template resolution
preserves campaign 409/422 responses; retained failed submissions start dirty
and warn before navigation. The browser suite checks these actual responses and
warnings, and sizes both tabs explicitly for mobile conflicts. The real fixture
initializes pretty permalinks before activation and refreshes the in-memory
rewrite object before simulating an upgrade. A missing eval-file `$wpdb` global
was fixed in the multisite fixture. Populated textarea/select controls use their
actual form fields in browser locators, rather than exact wrapper-label text
that includes control contents. Failed checks were corrected, not waived.

### Remaining dependent portions — Phase 3 remains incomplete

1. Content selectors, email renderer and bounded language/topic audience preview:
   paginated public article/property adapters, explicit language/availability,
   plugin-owned email-safe HTML/text renderer and reconciled bounded audience
   evaluation. No CRM segmentation without separately verified semantics.
2. Review and immutable content/recipient freeze: fresh source/eligibility checks,
   immutable resolved content/configuration snapshots and bounded atomic
   membership publication, concurrency protection and failure/cancellation rules.

Historical consent, suppression-source completeness, retention, approved copy /
sender/footer/translations and production topology/domain readiness stay open.
Real intake/public collection remain disabled by existing gates. Phases 4–7 stay
not started. No sending, test email, scheduling, provider, queue, worker, merge
or deployment is included or authorized by this record.


| Date | Status | Evidence / decision |
| --- | --- | --- |
| 2026-10-18 | Phase 3A technical acceptance passed | Non-draft [PR #1450](https://github.com/7mus7y-dotcom/pera-wp/pull/1450); run 39112405507 passed both database/browser suites on e8fde98; actual screenshot review recorded above. Phase 3 remains incomplete; external approvals and readiness remain open; no merge/deploy. |


## PR #1450 acceptance review closure (2026-10-18)

Verified the implementation head `87d89ca8a539aa4ddf869fc7583378d860da40ef`
against the three original findings and the final hosted [run 39112678562](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39112678562).
The run belongs to that exact SHA and concluded **success**. Both full jobs passed:
[single-site 117631934436](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39112678562/job/117631934436)
and [multisite 117631934683](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39112678562/job/117631934683).
The suites use real WordPress/MySQL and desktop/mobile Chromium (1440×1000 /
390×844) on bundled Twenty Twenty-Five with only PeraCRM mounted.

| Original finding | Confirmed fix at 87d89ca | Passing acceptance coverage / thread reply |
| --- | --- | --- |
| Campaign rewrite rules must persist automatically on upgrade | Rewrite version advances from v3 to v4; the shared CRM registrar includes campaign routes for activation and the init-time versioned flush. | Real fixtures set the old v3 marker, run init and inspect the persisted marker and `rewrite_rules`; fresh activation is checked separately. Multisite covers origin blog 1 and target blog 2. [Fix/evidence reply](https://github.com/7mus7y-dotcom/pera-wp/pull/1450#discussion_r4306828547); thread resolved. |
| Preserve HTTP 422/409 through template loading | The campaign POST handler records its controlled `http_status`; the CRM template loader preserves campaign 422/409 instead of replacing them with 200. | Actual browser POSTs require 422 for an invalid CTA and 409 for a stale second-tab save after template loading; submitted content remains visible and the winning saved subject is unchanged. Both sizes/topologies pass. [Fix/evidence reply](https://github.com/7mus7y-dotcom/pera-wp/pull/1450#discussion_r4306828825); thread resolved. |
| Retained failed submissions must start dirty and warn before navigation | Editor initialization derives dirty state from the rendered campaign error marker used by both validation and conflict responses. | Immediately after a 422, without another input event, the browser clicks Campaigns and requires a real `beforeunload` dialog. The same suite verifies the retained subject/error marker after 409. Desktop/mobile and single-site/multisite pass. [Fix/evidence reply](https://github.com/7mus7y-dotcom/pera-wp/pull/1450#discussion_r4306828949); thread resolved. |

All three findings received individual fix-and-validation replies before their
threads were resolved. [Re-review was requested](https://github.com/7mus7y-dotcom/pera-wp/pull/1450#issuecomment-6249343480)
with `@codex review` while `87d89ca` was still the PR head, explicitly naming the
full implementation SHA. GitHub's review summary confirmed the manual review
running on that exact SHA. This follow-up changes only this living plan; runtime,
routes, views, assets, tests and configuration gates remain exactly as at that
validated implementation head. A review request is not a claim of reviewer
approval; any subsequent review outcome must be recorded separately.

Final-head screenshot/log bundles: [single-site](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39112678562/artifacts/12131600157)
and [multisite](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39112678562/artifacts/12130966927),
retained until 2026-11-01. The refreshed captures were inspected during implementation
acceptance: desktop/mobile list/editor/configuration, mobile conflict state and
multisite mobile editor/configuration review copies. No new screenshot inspection
is claimed by this documentation-only closure; the existing recorded inspection
and actual browser assertions are the evidence.

**Phase 3 remains in progress and incomplete.** At Phase 3A closure, Phase 3B was not begun. The next
portions remain (1) content selectors, email renderer and bounded language/topic
audience preview; (2) review and immutable content/recipient freeze. All ownership
remains in PeraCRM. Sending, real intake and public collection remain disabled;
no provider, scheduling, queue/worker, merge or deployment is introduced or performed.

## Phase 3B — content, draft rendering and audience preview (2026-10-18)

Status: **complete** (Phase 3B technical acceptance). Phase 3 remains **in progress**; Phase 3C remains **not started**.
PR #1450 merged at 11:35:13 UTC as `5b009f562b5a76811ff7e007dd99472097b2f953`;
Phase 3B starts at that latest main, branch `feat/campaign-phase3b`. No dependent
unmerged PR is required. No applicable AGENTS.md exists in the searched repository
or workspace ancestors. The clean initial branch was `work` at `04a7d93a`.
Shell fetch could not connect to its proxy. Connected GitHub reads supplied the
26 changed blobs and exact signed merge commit; local blob/tree/commit SHAs were
verified before switching. No existing work was replaced.

### Phase 3A acceptance

Merged #1450 records passing real single-site/multisite and bundled Twenty
Twenty-Five Chromium checks at 1440×1000 and 390×844, including actual create,
save/reload, duplication, block ordering, input retention and 409 conflicts.
[Run 39112678562](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39112678562)
validated runtime `87d89ca8`; documentation-only head `c972cd7a` retained identical
runtime files. [Single-site evidence](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39112678562/artifacts/12131600157)
and [multisite evidence](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39112678562/artifacts/12130966927)
are retained until November 1. This is Phase 3A evidence, not validation of 3B.

### Decisions and actual supported data

- Existing schema version 1 draft documents gain optional version 1 `audience`
  rules; old revisions remain byte-for-byte unchanged. Current save/duplicate
  paths normalize defaults. Only exact explicit `language` and one `topic` key
  are supported, combined with AND. Empty means no filter; positive missing data
  cannot match. No budget, advisor, pipeline or inferred interests.
- Article/property blocks store only kind and positive ID. Published, publicly
  viewable, password-free title search uses stable ascending ID pagination,
  21 candidates / 20 results and bounded metadata priming. Preview rechecks IDs;
  inaccessible titles, private portfolio URLs and credentials are never returned.
  Selector labels explicitly use canonical source titles, with requested-language
  availability alongside them. Content IDs remain references, not snapshots.
- Actual Pera Multilingual APIs define English as canonical source. With that
  plugin active, current non-stale stored title and excerpt (or content, when no
  excerpt) translations must exist in a publicly available requested language.
  A new storage reader is constructed inside target context (the singleton caches
  its construction prefix), preserving multisite translation isolation. No fallback,
  language inference, provider request or translation generation.
  Without the plugin, explicit content language is unavailable. Russian/Turkish
  public discovery gates are honored. Image alt translations use current stored
  attachment metadata translations; missing alt text stays empty. Authored draft
  text/footer is not automatically translated.
- Property registration and helper functions currently belong to the theme.
  PeraCRM neither registers the production CPT nor loads those helpers. The
  bundled default-theme fixture first asserts an explicit unavailable state,
  then uses a synthetic registered property. The adapter reads raw canonical
  ACF repeater metadata `v2_units` (maximum 100 rows), `v2_units_N_v2_beds`,
  `v2_gross_size_min/max` and `v2_price_usd_min/max` subkeys. It displays individual
  unit rows and USD values, with explicit missing size/price data. It never reads
  deprecated `price`/`price_usd`, uses no derived theme index, and does not infer
  inventory availability. Unknown units or metadata remain unavailable.
- One normalized Pera-branded model generates inline-styled HTML and plain text
  in the same order, with approved current sender/footer, introduction, content,
  CTA and mandatory preference placeholders. It escapes text/attributes and
  rejects non-HTTP(S), credentials, local/reserved hosts and token/portfolio URLs.
  Email images come only from selected public content thumbnails; no arbitrary
  HTML, scripts, forms, embeds, authored tracking pixels or subscriber data.
  Shared HTML contains inert CTA labels; separately validated destinations open
  outside the sandbox for staff verification. Later freeze consumes the model;
  a later delivery implementation must deliberately resolve recipient links.
- Preview AJAX routes require campaign authority, real actor (no impersonation),
  POST and nonce; replies use private/no-store/no-referrer and restrictive CSP.
  Visual HTML is injected into an empty-sandbox iframe with its own CSP,
  no-referrer policy and no active navigation. Desktop/mobile and plain-text
  controls render current form input without saving. Preview refresh is explicit.
  Draft status, preference placeholders, live image URLs and unverified
  domain/provider readiness remain visible. Images/URLs are not frozen files.
- Audience uses the same pure eligibility-reason policy as existing live
  single-address eligibility. A suppression registry mailbox must exist; active
  suppression takes precedence, then consent/evidence, invalid linked-client
  state, language, topic. Newsletter-only identities need no CRM link. Existing
  unresolved/deleted/disposition policy is unchanged; no automatic link inference.
  Unique subscriber mailbox keys count shared mailboxes once.
- Per request: at most 200 evaluated rows plus one lookahead, joined suppression,
  linked post and party disposition in one query. No subscriber query loops or
  whole-audience reads in implementation. Signed aggregate-only cursor progress
  binds actor, target blog, draft ID/revision, normalized rules hash, fixed maximum
  candidate ID and 30-minute expiry. No server progress/recipient ledger or public
  token is created. Draft changes invalidate; errors fail closed. Counts reconcile
  considered = eligible + mutually exclusive exclusions. Partial counts are
  explicitly partial. As-of/check timing is advisory; concurrent edits/deletions
  affect traversal and no transactional snapshot is claimed.

### Validation and outstanding evidence

All 13 pre-existing standalone regressions passed after the shared policy change;
new pure Phase 3B contracts also pass. PHP/JavaScript/shell syntax, Compose
configuration and whitespace checks are being run on final changes. Real suites
extend existing single-site/multisite regressions with actual ML stored
translations, synthetic property v2 metadata, selector exclusions/pagination,
HTML/text parity, draft compatibility/duplication/stale writes, eligibility
parity/count reconciliation, constant query bounds, signed progress invalidation
and no subscriber mutations. Browser fixtures exercise actual selection,
reordering, save/reload, current unsaved preview, CSP/sandbox, zero eligible
recipients and missing translations/images/prices at both desktop/mobile sizes.

Local Docker socket health check is blocked (`operation not permitted`, UID 1000,
daemon running). No local database/browser success or screenshot review is
claimed. Hosted CI must establish the new runtime results; the final evidence
entry below will record actual results and inspected screenshot artifacts.
Gmail/Outlook inbox rendering, Safari/Firefox, manual assistive technology,
approved translations/copy and production domain/provider readiness remain later
verification. Real intake/public collection and all sending stay disabled.

**Phase 3C remaining:** explicit review, immutable content and recipient snapshots,
bounded resumable/cancellable freeze and atomic publication. No review/freeze,
recipient materialization, test emails, delivery, scheduling, providers, queues,
workers, merge or deployment is implemented or authorized here. Phase 3 stays
incomplete; Phase 4–7 remain not started.


### Phase 3B final acceptance evidence

Non-draft [PR #1451](https://github.com/7mus7y-dotcom/pera-wp/pull/1451) targets main.
Final runtime and test commit `d4b1cafc02e6e59125b762492830707674a223a6` passed
[run 39128396228](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39128396228):

| Topology | Result | Screenshots and logs (retained until November 1) |
| --- | --- | --- |
| Single-site | Job 117678291498: success | [Artifact 12137490605](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39128396228/artifacts/12137490605) |
| Multisite | Job 117678291323: success | [Artifact 12137276655](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39128396228/artifacts/12137276655) |

Both jobs include the existing regressions and real WordPress/MySQL suites, then
Chromium controls at 1440×1000 and 390×844 on a bundled default theme. Actual
selectors paginate and exclude private/draft/pending/future/trash/password posts;
selected blocks reorder, save/reload and remove; searching preserves unsaved text.
HTML and plain-text controls render unsaved current text. Tests assert no-store,
empty iframe sandbox, restrictive CSP, no active links/scripts/forms, separate
validated destinations and no horizontal overflow in page or frame. Audience
controls require saved rules, handle multiple bounded requests and show zero
eligible recipients. No subscriber or consent/suppression change, public token
issuance, recipient ledger or sending is introduced. New candidates after the
initial maximum-ID boundary are excluded; signed foreign actor/blog, changed
rules/revision, tampering and expiry invalidate progress.

Visually inspected emitted synthetic JPEG review copies of
`campaign-preview-desktop/mobile.png`, `campaign-content-desktop/mobile.png`,
`campaign-missing-mobile.png` and `campaign-audience-mobile.png`, including
multisite desktop/mobile controls. Full PNGs are retained in both artifacts.
Long unbroken text wraps; v2 USD ranges, missing images/prices and unavailable
German translations remain readable inside the mobile frame. The zero-audience
example reconciles 441 considered = 0 eligible + 62 suppression + 60 consent +
58 linked-client + 261 topic exclusions (language/rules exclusions both zero).
Only component screenshot capture temporarily hides the existing sticky shell
header/admin bar to avoid overlaying the captured region; controls and overflow
assertions run with the ordinary shell. Missing-data captures explicitly scroll
inside the frame; this changes no product styles or behavior.

Initial hosted checks caught a fixture `future` post without a future timestamp,
which WordPress published immediately. The fixture now assigns tomorrow's date;
pagination assertions remain intact. A subsequent actual browser check caught
ambiguous content-kind labeling; the selector now has an explicit accessible
name. Both complete suites pass after those corrections; no failed assertion
was waived. Earlier passing run 39128140889 validated the same production code;
final run adds fixed-boundary/token assertions and clearer screenshot coverage.
All 14 standalone scripts, 394 plugin PHP syntax checks, CRM/browser JavaScript,
both shell runners, Compose configuration and whitespace checks pass locally.
The final follow-up updates documentation only; runtime/test trees stay identical
to the validated commit above.

Remaining verification: Gmail/Outlook inbox rendering; Safari/Firefox; manual
assistive-technology and copy/translation approval; actual production topology,
HTTPS/cache/log behavior, approved identities/address, domain/provider readiness,
suppression-source completeness and retention/legal-basis decisions. These are
not established by browser previews. Real intake/public collection stay gated
and sending stays absent. Phase 3C explicit review, content/recipient snapshots,
bounded resumable freeze and atomic publication remain unimplemented. Phase 3
is incomplete; Phases 4–7 remain not started. No merge or deployment occurred.

Changed files, all relative to `wp-content/plugins/peracrm/`:

```text
assets/frontend/crm.css
assets/frontend/crm.js
docs/email-campaigns-implementation-plan.md
inc/bootstrap.php
inc/campaigns/audience.php
inc/campaigns/content.php
inc/campaigns/drafts.php
inc/campaigns/preview-routes.php
inc/campaigns/renderer.php
inc/campaigns/service.php
inc/views/pages/crm-campaigns.php
tests/campaign-drafts-test.php
tests/campaign-previews-test.php
tests/integration/campaigns/browser.cjs
tests/integration/campaigns/change-tracking-browser.cjs
tests/integration/campaigns/compose.yaml
tests/integration/campaigns/fixture-guard.php
tests/integration/campaigns/preview-browser.cjs
tests/integration/campaigns/previews.php
tests/integration/campaigns/run-inside.sh
```

### PR #1451 acceptance follow-up: selector change tracking

Acceptance starts from head `29ea3b722fc76acf03bedf59aeffc3cfd2d6f9b2`.
The existing finding is [selector-only controls must not dirty the draft](https://github.com/7mus7y-dotcom/pera-wp/pull/1451#discussion_r4306975597).
That head already limits input/change tracking to named `document[...]` fields,
so search text and content-kind controls do not dirty the draft or clear its
signed aggregate cursor. Explicit article/property selection remains a document
mutation. Follow-up inspection found that Add text/Add CTA marked dirty but did
not invalidate audience progress; the structural invalidation selector now also
includes `data-block-add`.

New actual browser regressions run at 1440×1000 and 390×844. Starting with a
saved draft and a 200-row partial audience result, typing search text, changing
kind, searching both kinds and paginating must preserve the exact saved-status
text, partial counts and visible continuation. Continuing must post the original
signed cursor and reach 400 considered, proving that progress was neither
cleared nor silently restarted. Independent reload/prime/mutate cases cover
article/property selection, text/CTA additions, removal, both reorder directions,
subject/block-text edits and audience-rule edits. Every mutation must mark dirty,
clear continuation, replace stale counts, and refuse an audience HTTP request
until the document is saved.

Local JavaScript syntax and whitespace checks pass. Both complete hosted suites
and every new browser regression passed; evidence is recorded below.
Phase 3 remains **in progress and incomplete**; Phase 3C is **not started**.
Existing disabled real intake/public collection gates and absent sending remain
unchanged. No merge or deployment is included.


Acceptance verified on runtime/test head
`d9026aa99e77f6d81c82349a8051fc3a750a3d3f` in
[run 39129444264](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39129444264):

| Topology | Result | Retained logs and screenshots |
| --- | --- | --- |
| Single-site | Job 117681407797: success | [Artifact 12137103987](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39129444264/artifacts/12137103987) |
| Multisite | Job 117681407880: success | [Artifact 12137985104](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39129444264/artifacts/12137985104) |

Each topology logs two selector-preservation/continuation passes and twenty
independent mutation passes, covering both desktop and mobile. The posted signed
cursor is checked against the one issued before selector interactions; considered
counts advance from 200 to 400. Mutation checks assert dirty status, replaced
counts, hidden continuation and zero audience HTTP requests for unsaved input.
The first attempt passed selector/cursor and structural cases, then stopped at a
prefilled-textarea exact-label lookup in the new test. The test now locates the
actual textarea through its persisted `document[blocks][...][text]` name; its
state assertions were preserved. Both full suites pass after this correction;
no failed assertion was waived.

The selector-only guards were already in requested head `29ea3b7`. Follow-up
`08edfc04` also invalidates progress for Add text/Add CTA; `d9026aa` corrects only
the regression locator. The final acceptance record is documentation-only, so
runtime/test files remain identical to the passing head above. Finding closure
is supported by this evidence; review of the acceptance follow-up is requested
separately and does not imply reviewer approval.

Follow-up files: `assets/frontend/crm.js`, the browser harness, new
`tests/integration/campaigns/change-tracking-browser.cjs`, and this plan. Phase 3
remains **in progress and incomplete**, Phase 3C remains **not started**, and
all existing disabled collection/intake gates and absent sending stay unchanged.
No merge, deployment, provider, queue, scheduling, delivery or freeze work occurred.

## Phase 3C checkpoint: unpublished content-capture foundation (2026-10-18)

Status: **in progress**, not technically complete. The user narrowed the task
before any Phase 3C implementation to a limited, reviewable checkpoint; full
review/freeze UI and browser acceptance belong in a dependent PR. This section
supersedes the historical “3C not started” entries above. No frozen campaign or
recipient membership can be produced by this checkpoint.

Baseline and preservation: #1451 is merged (13:39:32 UTC), merge commit
`e526a2605d11acc0bbad6c7c9be8a0c0a9da7941`; connected GitHub comparison confirmed
it was latest main at task start. Initial clean branch `work` was at `5b009f56`.
No AGENTS.md was found under /workspace or in repository ancestors. Shell fetch
failed because its configured proxy could not be reached. A subsequent fetch
with additional network permission stayed blocked and was aborted by the user;
no further shell fetch/push retries are planned. Connected GitHub supplied the
20 merged files and signed merge object: the local index tree matches main's
`960cac5cf9662c8394be90cede78fd2c8138f1db`, and reconstructed commit bytes match
GitHub's merge SHA. No pre-existing edits were discarded. Implementation branch:
`feat/campaign-snapshot-foundation`, based on that actual main, with no unmerged
prerequisite. Publication uses the GitHub connector because shell networking is
unavailable.

### Phase 3B prerequisite evidence

Merged #1451 records full real single-site/multisite and default-theme Chromium
acceptance, including the final selector/cursor change-tracking corrections on
runtime/test head `d9026aa` in [run 39129444264](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39129444264).
Its final head `348a823` changed only documentation. The two topology job and
artifact links are recorded immediately above. This evidence validates Phase 3B,
not this checkpoint or Phase 3C acceptance.

### Implemented boundary and validation contract

- Schema 24 additively introduces only `peracrm_campaign_content_snapshots` with
  an InnoDB primary key and full unique `(campaign_id, draft_revision,
  binding_hash)` constraint. Readiness assertions require that unique key;
  existing target-blog migration ownership, failure containment and administrator
  recovery apply. No recipient, freeze-attempt or dispatch table is added.
- Internal review/capture/read services require target-blog campaign capability,
  schema readiness, real actors and denial of impersonation. No HTTP route,
  CLI command, UI control or public read is registered. Capture additionally
  requires the campaign/revision-specific WordPress nonce. Future UI integration
  must submit its actual current document; server normalization/equality rejects
  unsaved content and stale saved revisions. No server can detect browser edits
  that a future caller omits from its request; client integration remains required.
- A signed proof expires after 15 minutes and binds a purpose/version, actor,
  target blog, campaign, saved revision and exact content/configuration/output
  hash. Capture locks the draft and singleton configuration in that order, then
  freshly resolves the bounded selected content using Phase 3B adapters/renderer.
  A changed source, renderer, configuration, rules or document invalidates an
  uncaptured review. Insert/readback/commit use the existing rollback wrapper;
  errors remain sanitized. There is no partial artifact returned on failure.
- Required authored name, subject, language, body, complete text/CTA blocks and
  approved sender/reply-to/footer follow existing draft rules. Selected public
  content additionally requires nonempty resolved title and summary, canonical
  public URL and exact requested language/current translation. No fallback or
  translation generation. Every selected property unit requires canonical v2
  beds (zero allowed), positive minimum gross size and USD price; optional maxima
  may be absent but cannot be below minima. Missing canonical unit data blocks
  capture. Optional image/translated-alt absence is a warning, not a blocker.
  Incomplete drafts remain saveable; these stricter rules apply only to capture.
- Stored payload includes schema/template/validation versions, normalized
  document hash, language/topic rules and hash, configuration revision and approved
  configuration/approver, structured sender, ordered resolved model with source
  IDs/public URLs/image URLs/alt text/canonical property values and labels, exact
  renderer HTML/text/destinations, warnings, real reviewing actor, target blog,
  UTC capture timestamp and resolution start/end timestamps. The content hash
  covers the stored content bundle. Reads verify it and return stored bytes;
  they do not invoke the current renderer, configuration or content adapters.
- Duplicate requests for the same actor/blog/content and saved revision return
  the original artifact after permission, nonce, proof and current draft checks.
  That replay does not reapprove current sources or publish anything. Later
  source/configuration changes cannot rewrite the record. No update/delete or
  restoration API exists. Later draft saves and duplicates retain earlier
  captures. Database administrators remain outside application immutability.
- Artifacts are explicitly `unpublished_content`, not editorial approval of a
  frozen revision, a recipient freeze, or sending authorization. Output retains
  the Phase 3B inert draft-preview presentation. Editorial sender/footer approval
  does not verify DNS/provider readiness. Shared HTML contains only controlled
  recipient-link placeholders, no subscriber details or public bearer tokens.
  **Storing an image URL does not make the underlying image file immutable.**

### Temporal semantics and dependent work

Source resolution is a bounded evaluation interval, not a single-time database
snapshot of WordPress objects, caches, translations and configuration. Draft and
configuration writes serialize with capture; source changes are not globally
locked. Content is bound to exactly the model/output bytes reviewed and freshly
resolved at capture. Concurrent changes that invalidate that binding fail closed.
Once stored, output never silently follows live posts/prices/templates/config.

The dependent freeze work must add versioned attempt state, exact draft/content/
configuration/rule binding, initiating actor, fixed maximum candidate subscriber
ID, stable keyset batches, transactional recipient writes and progress, unique
revision/mailbox membership, mutually exclusive eligibility outcomes and exact
count reconciliation. Membership will be evaluated **during materialization**
against that fixed boundary, not automatically at one database instant; persist
policy versions and evaluation timing. Freeze must never substitute client email
for the frozen normalized subscriber mailbox. Historical membership remains
unchanged by later unsubscribe; any future delivery must recheck live permission
and suppression. No such materialization or later delivery is implemented here.

Before frozen publication exists, dependent work must implement draft editing
locks, serialized start/continue/resume/cancel/publish, stale-owner fencing,
failed/cancelled recovery, zero-included rejection, complete-counter/binding
rechecks and one atomic publication only after all content/recipient rows exist.
Published history/new-draft behavior and desktop/mobile review, actual controls,
read-only HTML/text previews and screenshots also remain outstanding. Independent
connection concurrency/failure tests and both real topology suites are mandatory
for that work. Browser previews never establish Gmail/Outlook inbox rendering.

Retention is deliberately not automated in this checkpoint: unpublished content
captures are retained, with no cleanup endpoint/worker. Before production use,
approve a retention period and implement bounded manual cleanup (proposed: at
most 100 records per administrator request, abandoned unpublished artifacts older
than 30 days, locked and rechecked to exclude any published/referenced artifact).
Freeze-attempt cleanup must cancel/fence active attempts first. Published history,
consent and suppression evidence must never be deleted by such cleanup. This is
an outstanding implementation/policy requirement, not a claim that cleanup runs.

### Checkpoint verification and limitations

All 15 standalone PHP regressions pass, including the new synthetic snapshot
service tests for authority, impersonation, nonce/proof expiry and binding,
unsaved/stale review rejection, failed insertion/rollback/retry, duplicate capture,
stored-byte stability after configuration changes, corruption rejection and no
subscriber/public-token/frozen-status mutation. Existing migration regression
fixtures now expect schema 24. Stub DB tests do not prove real SQL or concurrency.

New disposable WordPress/MySQL fixtures are wired into both existing topology
suites: v23 upgrade/repeated readiness, raw unique-key rejection, unavailable
translation/public content, real insert failure/retry/idempotency, stored output
after source/config changes, employee/impersonation denial, campaign read binding,
later draft/history preservation and no subscriber/evidence/token changes.
These new real fixtures have **not run locally**: Docker's explicit local socket
check failed `connect: operation not permitted`; UID is 1000 and dockerd is running.
No daemon restart or repeated socket retry was attempted. Real single-site and
multisite checks, separate-connection capture/save concurrency, source/price/
template variation coverage and Phase 3C browser acceptance remain outstanding.
No new screenshots/artifacts are claimed. Existing Phase 3B artifacts above are
prerequisite evidence only.

Phase 3 must remain **in progress**. Editorial copy/translations/identity approvals,
historical consent/evidence and retention policy, real intake, suppression-source
completeness and production topology/domain/provider readiness remain outstanding.
All changes belong to PeraCRM. No theme, CSS/JS, existing route or collection gate
is changed. Real intake/public collection and campaign sending stay disabled;
Phases 4–7 remain not started. No test email, scheduling, provider, worker, merge
or deployment is included.

Additional bounded checks passed: all 398 plugin PHP files parse under the bundled
PHP 8.4 runtime; both integration runners pass shell syntax; Compose configuration
validation and staged whitespace checks pass. These are repository checks, not
real database/browser acceptance. Existing CSS/JavaScript are unchanged.

Checkpoint file inventory (all relative to `wp-content/plugins/peracrm/`):

| Purpose | Changed files |
| --- | --- |
| Additive storage and loading | `peracrm.php`, `inc/bootstrap.php`, `inc/campaigns/schema.php`, new `inc/campaigns/snapshot-schema.php` |
| Internal review/capture/stored-read service | new `inc/campaigns/snapshots.php` |
| Standalone contracts and schema expectations | `tests/authorization-surfaces-test.php`, `tests/campaign-foundations-test.php`, `tests/campaign-theme-independence-test.php`, new `tests/campaign-snapshots-test.php` |
| Real fixtures, wired but not locally run | `tests/integration/campaigns/assertions.php`, `tests/integration/campaigns/run-inside.sh`, new `tests/integration/campaigns/snapshots.php` |
| Decisions/evidence/remaining work | this living plan |

### Configuration-consistency review fix (2026-10-18)

Based on merged #1452, current main `5fcbd6c651df6cf16776f132ef2a9b99bf53356d`.
Connected GitHub supplied the changed baseline files; each blob, the complete
main tree and signed merge commit were SHA-verified locally. The initial `work`
checkout was clean at outdated `5b009f56`; no existing work was discarded. No
applicable AGENTS.md was found in the repository/workspace tree or ancestors.
Fix branch: `fix/campaign-review-configuration`, targeting main.

Snapshot candidate construction now reads approved configuration once and passes
that exact document to model rendering. Configuration revision/document/approver,
structured sender, sender/footer output and the signed content hash therefore
describe one coherent configuration revision. Ordinary editable previews retain
their current-configuration read and existing authorization checks. Capture still
locks draft/configuration, freshly constructs a candidate, compares its complete
hash to the review proof and checks the locked configuration revision. A change
after review still rejects an uncaptured stale review; stored replay semantics
and immutable stored bytes are unchanged. Evaluation remains an interval, not a
claim that all content sources share a single-time database snapshot.

The standalone regression replaces configuration immediately after its first
read, verifies one read and coherent revision/approver/sender/footer/HTML/text/
proof, and requires stale capture to fail without writing. It fails against the
original implementation and passes with this fix. The real WordPress fixture
changes the configuration row between the first SELECT and the next query,
checks the same coherence and stale-capture contract, and restores configuration
through the authorized save service. It runs in both existing hosted topology
jobs. Focused snapshot regressions (including inherited draft/preview/foundation
contracts), ownership/authorization guards, syntax checks for all four changed
PHP files and whitespace checks pass locally. Hosted [run 39144134902](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39144134902)
passed on implementation head `ee3a9229dcd6d81a3f0d85346de93c20357def85`:
[single-site job 117724410553](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39144134902/job/117724410553)
and [multisite job 117724410813](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39144134902/job/117724410813).
Both complete WordPress/MySQL/browser jobs succeeded. Their fetched logs explicitly
confirm the configuration change after the first read, exactly one review read,
coherent output/proof, stale-capture rejection without an artifact and authorized
configuration restoration. This is actual executed evidence, not fixture
availability. This evidence update changes documentation only; implementation and
tests remain identical to that validated head. No local Docker integration run or
new Phase 3C UI/browser acceptance is claimed.

Changed files are `inc/campaigns/renderer.php`, `inc/campaigns/snapshots.php`,
`tests/campaign-snapshots-test.php`, `tests/integration/campaigns/snapshots.php`
and this plan. **Phase 3 remains in progress and incomplete.** Recipient freezing,
publication, UI/browser acceptance and previously recorded external readiness
remain outstanding. No theme dependency, UI, route, migration, recipient,
delivery, scheduling or worker is added. Sending, real intake and public
collection gates remain disabled/unchanged. No merge or deployment is performed.

## Phase 3C recipient-freeze service (2026-10-18)

Status: **in progress; Phase 3 remains incomplete**. No new UI or browser
acceptance. #1453 merged at 16:31:28 UTC; latest main at task start was
`625f48fa64436b45359b091cca829a6b8117aefe`. Branch
`feat/campaign-recipient-freeze` targets main. No applicable AGENTS.md was found.
Shell Git fetch failed once because proxy:8080 was unreachable; no shell network
retry was made. Connected GitHub supplied the five changed baseline files and
signed commit. Their complete local tree and reconstructed commit SHA match
GitHub main exactly. The original checkout was clean; no work was discarded.

### Storage, operation and temporal contract

Schema 25 additively adds attempt, candidate-evaluation, frozen-recipient and
published-revision tables using the existing target-blog migration readiness,
InnoDB assertions and migration locking. An attempt ID identifies one membership
revision; a unique campaign/draft revision prevents competing attempts for the
same saved revision. Unique attempt/subscriber evaluation and attempt/mailbox
membership constraints protect replay and deduplication. Only complete,
reconciled, nonempty membership may receive a published-revision row.

Internal services require campaign management capability, readiness and a real
actor, deny impersonation and enter authoritative target storage. Mutations also
require the campaign-specific `peracrm_campaign_freeze_{campaign_id}` nonce.
Start takes the saved document/revision and immutable capture ID/hash, checks
capture actor/blog/revision/document/rules/configuration binding and fixes the
maximum subscriber ID. It never rerenders stored content. Duplicate starts return
the existing materializing attempt only for the same actor and binding. A
cancelled attempt requires a new saved draft revision before starting again.

All start/continue/resume/cancel/publish operations lock the draft first, then
the attempt, then configuration when needed. Existing draft-save locks that same
draft and rejects any materializing attempt inside the service. An initiating
actor and monotonically increasing attempt version fence stale operations.
Duplicate continuation/publish requests with an old version return a conflict;
they cannot advance twice. There is no expiring worker lease or implicit takeover.
Each continuation handles 1–200 candidates using keyset order, one shared batched
eligibility read and one bounded existing-mailbox read. No audience-sized load or
per-candidate client/profile SELECT occurs. Inserts, counters and cursor commit
together. A failed batch rolls back, then attempts a separate version-fenced
transition to failed; storage loss may also prevent that transition, in which
case an authorized actor reads current state and retries or cancels. Process loss
before commit rolls back; a lost successful response is recovered by reading the
new version. No request can assume ownership from a timeout.

States are materializing, failed, cancelled and frozen. Failed/cancelled attempts
release draft editing; resume is allowed only for failed attempts with the exact
original draft/content/configuration binding. Any later saved revision prevents
resumption. Cancelled/frozen attempts are terminal. Abandoned materializing
attempts can be explicitly cancelled; administrators can fence them through the
retention service after 30 days. There are no workers or automatic transitions.

Eligibility is evaluated **during materialization against a fixed candidate ID
boundary**, not at one database instant. Shared policy uses suppression, consent,
linked-client, language, topic and invalid-rule precedence; an otherwise eligible
mailbox already materialized receives the mutually exclusive duplicate exclusion.
Deleted candidates before evaluation are absent from considered counts. Frozen
mailbox, subscriber/client IDs, explicit language personalization, outcome,
subscriber/evaluation version and evaluation time persist in the ledger. Current
templates require no personal name. Client email is never substituted. Later
sending MUST recheck live consent and suppression; frozen inclusion is historical
evidence, not permission to send. No subscriber/consent/suppression record or
preference token is created or modified by freeze services, and personal data is
never inserted into shared captured HTML.

Publication rechecks bindings under locks, exhaustion, persisted reason counts,
considered = included + mutually exclusive exclusions, and included membership
count. It inserts the unique publication and marks the attempt/draft frozen in
one transaction. Partial, failed, cancelled and zero-included attempts cannot
publish. Publication has no application update/delete path. Subsequent saves
create a distinct draft revision; published captures and recipient records remain
unchanged. Reads authorize and return stored capture bytes plus bounded keyset
recipient/evaluation pages. No current-source rendering occurs.

### Manual retention policy and validation

The administrator-only cleanup service accepts one campaign/attempt and nonce.
Only unpublished attempts untouched for more than 30 days qualify. Under the
same draft/attempt locks it rechecks publication, cancels/fences any abandoned
owner, and deletes at most 100 child ledger rows per call. The cancelled attempt
row remains as a durable unique campaign/revision tombstone after all child rows
are gone; repeated empty calls are idempotent. Published history,
content captures and all subscriber/consent/suppression evidence are retained.
There is no scheduled deletion. This is an explicit service policy for synthetic
validation; production retention/legal-basis approval remains outstanding.

Repository and hosted technical validation pass. Focused standalone freeze regressions pass, including
inherited content/draft/foundation checks, authority, impersonation, bounded
input and the draft-save guard. New real WordPress/MySQL fixtures cover v24
upgrade/repeat, bindings, fixed boundaries, shared eligibility/counts, withdrawal,
changed client/subscriber email, deduplication, rollback/resume, zero/partial/
failed/cancelled publication denial, safe later revisions and manual retention.
Separate-process fixtures cover competing start/start, start/save,
continue/cancel, cancel/continue, continue/continue, publish/cancel and
cancel/publish with actual blocking and stale-version rejection.

Local Docker socket access failed `connect: operation not permitted`; UID 1000,
dockerd running. No restart or repeated socket access was attempted. Hosted
single-site/multisite execution passed; the evidence below records actual results. No new browser acceptance
is claimed. Review/history UI, desktop/mobile review controls and Phase 3C browser
acceptance remain outstanding, as do prior external readiness requirements.
Sending, real intake and public collection remain disabled. No Phase 4, test
emails, providers, scheduling, background workers, merge or deployment.


### Executed recipient-freeze evidence

Implementation/test head `b17b63c011619381f3c2440a985a0758f07310d6` passed
[hosted run 39148641964](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39148641964):

| Topology | Complete job result | Retained synthetic evidence |
| --- | --- | --- |
| Single-site | [117737679897: success](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39148641964/job/117737679897) | [Artifact 12144347390](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39148641964/artifacts/12144347390) |
| Multisite | [117737679692: success](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39148641964/job/117737679692) | [Artifact 12144312434](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39148641964/artifacts/12144312434) |

Fetched logs from both jobs explicitly confirm rollback of recipient rows and
cursor/counters together, failed-state fencing, exact resume, duplicate/stale
request rejection, complete count reconciliation, fixed boundaries, subscriber/
client email changes and withdrawal, database mailbox uniqueness, atomic
publication, partial/failed/cancelled/zero-included denial, immutable later
history, no subscriber/evidence/token mutations, authorized reads, manual cleanup,
eligibility parity and stale configuration rejection. Multisite additionally
confirms no origin-blog freeze tables and restored target context. All seven
separate-process scenarios log distinct connections, actual blocking, winner
commit and stale-loser rejection. Existing migration-contention and authority
suites also pass in both complete jobs.

The first run exposed a fixture-isolation problem: service setup left approved
configuration for existing browser tests that expect an unapproved footer.
The fixture now saves and restores the pre-suite configuration through authorized
services after all service races. Production behavior and browser assertions were
not relaxed. Earlier runs were superseded/cancelled by follow-up commits; they
are not claimed as complete passing runs. The complete run linked above passes
all existing browser regressions after restoration. These are regression checks
for existing screens, **not new Phase 3C UI or browser acceptance**.

All 16 standalone regression scripts and all 403 plugin PHP syntax checks pass
locally, along with integration shell syntax, Compose configuration and whitespace
checks. Docker could not run locally; no local SQL acceptance is claimed. This
final evidence change only updates documentation; runtime/tests are identical to
the passing implementation head. No production data, provider/inbox checks or
production deployment was used.

Changed files (relative to `wp-content/plugins/peracrm/`):

- Storage/runtime: `peracrm.php`, `inc/bootstrap.php`, `inc/campaigns/schema.php`,
  new `inc/campaigns/freeze-schema.php`, new `inc/campaigns/freeze.php`.
- Shared batching/edit guard: `inc/campaigns/audience.php`, `inc/campaigns/drafts.php`.
- Focused guards/schema expectations: `tests/authorization-surfaces-test.php`,
  `tests/campaign-foundations-test.php`, new `tests/campaign-freeze-test.php`,
  `tests/campaign-theme-independence-test.php`.
- Real fixtures: `tests/integration/campaigns/assertions.php`, new
  `tests/integration/campaigns/freeze.php`, new
  `tests/integration/campaigns/freeze-worker.php`,
  `tests/integration/campaigns/run-inside.sh`.
- Living plan: `docs/email-campaigns-implementation-plan.md`.

Phase 3 remains **in progress and incomplete**. Review/history UI and Phase 3C
browser acceptance remain outstanding, together with prior external readiness
and production retention approval. Sending, real intake and public collection
remain disabled. No Phase 4, merge or deployment.


### PR #1454 review follow-up: retain cleaned revision tombstones

The review finding “Preserve a tombstone for cleaned-up cancelled revisions”
identified that deleting an empty attempt also removed its unique campaign/draft
revision fence. Cleanup now retains the cancelled attempt metadata permanently,
including its original capture binding and actor/version audit. Only unpublished
recipient/evaluation payloads are deleted, at most 100 rows per transaction.
Empty cleanup calls return complete with zero deletions and never remove or
rewrite the tombstone. Existing cancelled attempts need no migration; no schema,
UI, gate or published-history change is introduced.

Start now rejects a terminal revision before consulting any capture, so neither
the retained capture nor another capture of that saved revision can bypass the
fence. Resume/publication continue to reject cancelled attempts. A newly saved
draft revision and its own valid capture can start a distinct attempt normally.
Cleanup shares the draft/attempt locks with start/resume and fences abandoned
failed/materializing owners before deleting payloads.

Regressions cover cancel → age → complete multi-batch cleanup → rejected restart,
retained/recaptured old content, repeated empty cleanup, resume/publication denial,
new-revision success and unchanged published content/membership. Two additional
separate-connection races pause cleanup under its locks while start/resume wait,
then verify the retained cancelled row and rejection of both stale and current
versions. Focused checks and both real hosted topology suites passed for
this follow-up; actual evidence is recorded below. Phase 3 remains
incomplete; review/history UI and Phase 3C browser acceptance stay outstanding.
Sending, real intake and public collection remain disabled. No merge/deployment.


Follow-up validation on implementation/test head
`896c52993242dec61181b23260762842b4cd77e0`:

- The new focused regression fails against the pre-fix cleanup and passes with
  the fix. All 16 standalone regression scripts, changed PHP syntax, integration
  runner shell syntax and whitespace checks pass locally.
- [Hosted run 39149899957](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39149899957)
  passed both complete real WordPress/MySQL suites:
  [single-site job 117741206517](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39149899957/job/117741206517)
  and [multisite job 117741206727](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39149899957/job/117741206727).
  Retained [single-site artifact 12145230236](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39149899957/artifacts/12145230236)
  and [multisite artifact 12144588521](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39149899957/artifacts/12144588521)
  include the synthetic logs and existing browser regression evidence.
- Fetched logs from both jobs explicitly confirm complete multi-batch payload
  deletion with a retained cancelled campaign/revision row, repeated zero-delete
  cleanup with identical tombstone metadata, retained/recaptured content restart
  denial, resume/publication denial, old capture rejection for the next revision,
  successful new-revision freezing and unchanged published content/membership.
  Both new cleanup/start and cleanup/resume races pass with distinct connections,
  actual blocking and rejection after cleanup commits, including current-version
  resume rejection. All seven previous race scenarios and existing regression
  suites also pass. No new browser acceptance is claimed.

This follow-up changes only `inc/campaigns/freeze.php`, the focused freeze test,
the two real freeze fixture files, their shell runner and this plan. The final
validation-record commit is documentation-only; runtime/tests match the passing
head above. Local Docker and shell Git networking remain unavailable as recorded
earlier; connected GitHub tools supplied publication and hosted execution.
Phase 3 remains incomplete, all disabled gates unchanged, and no UI, merge or
deployment is included.


## Phase 3C UI and browser acceptance (2026-10-18)

Status: **in progress (Phase 3C technical checks passed; Phase 3 completion pending clean review)**.
#1454 is merged; latest main at task start is `7258e143504953d96a2630e360ebe0d3f62b9554`.
Branch `feat/campaign-phase3c-ui` targets main. No AGENTS.md found. The clean
checkout was advanced through connected GitHub file reads after one failed shell
fetch (proxy unreachable). Its complete tree SHA `9ce959891e3dec1ddb24b856409c89a1420ff00b`
exactly matches GitHub main. Existing work was preserved. Implementation and
browser acceptance were separately checkpointed before hosted checks.

Plugin-owned review/freeze/history controls live in the CRM shell and existing
crm.css/crm.js. The adapter uses saved-document review proofs, immutable capture,
version-fenced start/continue/resume/cancel/publish and normal safe draft saves.
One click handles at most 200 candidates; there is no automatic loop or worker.
Publication requires a separate confirmation and service validation of complete,
nonempty reconciled membership. Errors stop actions until explicit state refresh;
reload reads server progress. BroadcastChannel invalidates competing tab controls,
and SQL versions fence stale callers even without browser channel support.
Unsaved editor fields are preserved; review/start/new-revision require saving.
Stored history renders captured bytes in empty-sandbox/CSP frames and plain text,
with controlled preference placeholders, recorded counts, real actor and UTC
metadata displayed in Europe/Istanbul. Image file immutability is not promised.
Recipient pages require subscriber plus campaign capability and expose only
mailbox/evaluation time, at most 200 per page. Attempt metadata history is at most
20 per page. Cancelled revision tombstones remain intact; no cleanup UI exists.

Narrow service gap: a bounded authorized target-blog attempt metadata read was
needed for reload/history discovery. Recipient ledger reads now independently
require subscriber capability inside the existing service as well as the UI;
this closes a narrow authority gap. No freeze engine or schema rewrite.

Validation passed: both complete hosted real single-site/multisite suites,
all 16 existing standalone regressions, 137 first-party plugin PHP syntax checks
(excluding unchanged bundled vendor code), and actual desktop/mobile screenshot
inspection. Browser flows cover saved/unsaved/stale review, repeated and competing
requests, bounded continuation/reload, injected real SQL rollback/failure/resume,
cancellation, zero-included denial, deliberate publication, stored HTML/text,
new editable revisions preserving history, authority, independent recipient
permission and unchanged protected subscriber/evidence/token bytes.
Local JavaScript/shell syntax, Compose configuration and whitespace checks pass.
Local PHP is absent. Local Docker socket denies access (`operation not permitted`,
UID 1000, daemon running); it was not retried. Hosted evidence will be recorded
below; configured tests alone were not treated as acceptance.

Historical consent/retention/legal-basis approval, editorial/brand/accessibility
approval and production readiness requirements remain open. No Chromium result
establishes Gmail/Outlook inbox rendering or manual assistive-technology review.
Sending, real intake and public collection remain disabled. No Phase 4, provider,
scheduling, background worker, merge or deployment.


### Executed Phase 3C UI acceptance

Runtime/test head `21648fbc1b06563c44868af19e29044828fd35d9` passed
[complete hosted run 39154763550](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39154763550):

| Topology | Complete job | Retained logs and screenshots (until 2026-11-01) |
| --- | --- | --- |
| Single-site | [117755587851: success](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39154763550/job/117755587851) | [Artifact 12146323660](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39154763550/artifacts/12146323660) |
| Multisite | [117755587787: success](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39154763550/job/117755587787) | [Artifact 12146547584](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39154763550/artifacts/12146547584) |

Fetched logs explicitly pass both desktop (1440 × 1000) and mobile (390 × 844)
Phase 3C flows: saved/unsaved/stale review and stale start proofs, keyboard review
activation and feedback focus, a single continuation in flight despite repeated
clicks, competing-tab stale-version denial, multiple bounded batches, reload,
real SQL rollback/failure/resume, terminal cancellation, zero-included publication
denial, dismissed then deliberately accepted publication, isolated stored HTML and
plain text, bounded nonoverlapping recipient pages, new editable revision with
exactly one increment, subsequent editing without changed frozen text/membership,
and explicit historical revision selection. Employee/anonymous/missing-nonce and
impersonation denials pass; impersonated screens expose no freeze controls.
Campaign-only access retains frozen-content access but cannot view recipient
metadata, in both HTTP/browser and service checks. Protected subscriber, consent,
suppression and preference-token bytes are identical before/after UI operations.
The separate service fixture deliberately withdraws consent after publication and
verifies historical content/membership remain byte-stable; historical inclusion
is not current sending permission. Existing source/configuration immutability
checks, all prior regressions, target-blog isolation and all nine independent
connection races pass unchanged. No PHP warnings/fatal errors appear in either
fetched complete-job log.

Both artifacts were downloaded and actual screenshots inspected. Reviewed
single-site desktop/mobile review/progress/error/failed/cancelled/frozen/history
views and separate visible stored-content captures; also checked multisite mobile
progress/history and desktop stored content. Long content and sender/footer text
wrap; controls and revision timestamps stay within mobile width. Fixes found by
acceptance include explicit accessible names for persisted textarea controls,
feedback scrolling clear of the fixed header, and a selector strong enough to
override the shell button nowrap rule while accounting for button margins.

Chromium's long element screenshots can leave an offscreen nested frame blank.
History overview captures therefore collapse the HTML detail, with separate
in-view `freeze-content-desktop.png` / `freeze-content-mobile.png` captures proving
the rendered stored HTML. Long review overview frames may show that capture-only
limitation; the same captured output renders in the inspected in-view images.
Fixed shell/admin headers are hidden only during component screenshot capture;
runtime styles, empty sandbox, CSP, referrer policy and keyboard flow remain intact.
No screenshot or Chromium check establishes inbox rendering or manual assistive-
technology verification.

Earlier unsuccessful/superseded runs are not accepted evidence. Acceptance fixed
the test fault injector to fail materializing progress only (allowing the separate
failed-state fence), nested-frame screenshot scrolling/paint timing without
relaxing isolation, duplicate shared dialog handlers, navigation racing AJAX body
collection, and comparing exact DOM text rather than visibility-dependent
innerText. Assertions for denial, immutable content/membership, rollback,
permissions and no overflow were preserved. New captures identify reviewed
immutable content rather than claiming to be a current editable preview; existing
stored artifacts are never rewritten.

Changed files (17):

- Existing CRM shell/UI: `inc/bootstrap.php`, `inc/views/pages/crm-campaigns.php`,
  `assets/frontend/crm.css`, `assets/frontend/crm.js`; new
  `inc/campaigns/freeze-ui.php`.
- Narrow service/render contracts: `inc/campaigns/freeze.php`,
  `inc/campaigns/snapshots.php`, `inc/campaigns/renderer.php`.
- Focused regressions: `tests/campaign-snapshots-test.php`,
  `tests/campaign-theme-independence-test.php`.
- Real acceptance: `tests/integration/campaigns/browser.cjs`, `fixture-guard.php`,
  new `freeze-browser.cjs`, `freeze.php`, `preview-browser.cjs` in that directory;
  repository `.github/workflows/peracrm-campaign-foundations.yml`.
- This living plan. All other paths above are relative to PeraCRM.

Non-draft [PR #1455](https://github.com/7mus7y-dotcom/pera-wp/pull/1455) targets main.
The final evidence commit changes documentation only; runtime/tests match the
passing head above. Local original and incremental work remain preserved on
checkpoint branches, and the connected GitHub commit objects/base were restored
with matching SHAs. Phase 3 technical checks have passed; **completion remains
subject to a clean review**. Historical
consent/legal-basis and production retention approval, final editorial/brand/copy
and accessibility approval, staging content semantics, production topology/HTTPS/
cache behavior and provider/domain readiness remain outstanding as previously
recorded. Phase 2 external acceptance remains open. Sending, real intake and public
collection remain disabled. No Phase 4, provider integration, scheduling,
background worker, cleanup UI, merge or deployment.


### PR #1455 acceptance and review follow-up (2026-10-19)

Status: **in progress (technical checks passed; clean review pending)**.
No runtime, fixture or gate changes are needed for this follow-up.

The finding “Let the failed-state recovery update bypass the injected fault” is
fixed by [7094731aa093329fd1bdcb7431ea8b9c6776f887](https://github.com/7mus7y-dotcom/pera-wp/commit/7094731aa093329fd1bdcb7431ea8b9c6776f887).
Verified the actual patch and current predicate: the disposable cookie-enabled
query filter requires UPDATE, the freeze-attempt table, `last_id`, and the SQL
value `'materializing'`. The materializing progress UPDATE fails and rolls back;
the separate recovery UPDATE sets state to `'failed'` and therefore does not
match the injector, even though it also contains `last_id` and runs while the
cookie is still present. Recovery can persist the failed state and new version.
The browser clears the fault cookie only after the failed continuation response,
then explicitly reloads current server state, waits for the failed-only Resume
materialization control, resumes and finishes the bounded batches before separate
publication. This is verification of the existing fix, not a new engine change.

Latest requested implementation/evidence head
`812e83b6c4a6832d3635e94432dd293d18b8b416` passed the complete
[run 39155235145](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39155235145):

| Topology | Complete job | Retained evidence (until 2026-11-01) |
| --- | --- | --- |
| Single-site | [117756984553: success](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39155235145/job/117756984553) | [Artifact 12146782684](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39155235145/artifacts/12146782684) |
| Multisite | [117756984609: success](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39155235145/job/117756984609) | [Artifact 12147101403](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39155235145/artifacts/12147101403) |

Fetched logs from both jobs confirm batch rollback with a persisted failed fence,
exact-binding resume, and actual desktop/mobile browser failure → reload → resume
→ finish → deliberate publication flows. Downloaded and inspected the failed
screenshots at both sizes in both topologies, plus subsequent frozen-state
captures. Failed state retains considered/included/excluded 200/122/78 with
resume/cancel controls; the completed frozen revision records 446/263/183.
All existing regressions and nine separate-connection freeze races also pass.
Protected subscriber/evidence/preference-token bytes remain unchanged by UI flows.
No inbox rendering or manual assistive-technology verification is inferred.

Posted the [fix and passing-evidence reply](https://github.com/7mus7y-dotcom/pera-wp/pull/1455#discussion_r4308451192)
to the existing finding and verified thread `PRRT_kwDORM3KJ86ts_cC` is resolved.
[Requested review of head 812e83b](https://github.com/7mus7y-dotcom/pera-wp/pull/1455#issuecomment-6259987739).
Resolution of this finding and passing acceptance checks do not establish a clean
latest-head review. Phase 3 completion remains conditional on that review; current
phase status is in progress. This follow-up commit changes the living plan only.

Sending, real intake and public collection remain disabled. Historical consent,
legal-basis/retention, editorial/accessibility and production-readiness prerequisites
remain open. No Phase 4, provider work, scheduling, background worker, merge or
deployment is authorized or performed.


## Phase 4A — durable queue storage and lifecycle services (in progress)

2026-10-19: verified latest main through connected GitHub after one failed shell fetch; clean local checkout matches `18f81534446587ee4386885390a5a7a309c184e3`. Prerequisites #1454/#1455 are merged. #1455 final head `bf3718b00d` received a clean automated review; its failed-state browser-fixture finding is resolved. No AGENTS.md found in repository/workspace ancestors. Existing work preserved.

User-reported live Phase 3 checks passed: synthetic import, consent/suppression exclusions, drafts, previews, freeze/publication, and frozen history preservation. These are user-reported live checks, separate from automated evidence previously recorded. **The property-block review failure remains unresolved.** Retain open usability issues: confusing **Add identity** label, obscured configuration errors, and failed imports returning to the subscriber list. Phase 4A does not fix or expand into these issues. Historical consent/legal basis/retention, editorial/accessibility and production-readiness prerequisites remain open.

### Interfaces and invariants

- Schema v26 adds target-blog InnoDB jobs, deliveries and append-only service audit through existing atomic migration ownership/readiness/failure containment. No theme files, new UI, assets, routes or hook registrations.
- Scope is configured target blog + published frozen revision + normalized frozen mailbox. One job per frozen revision; unique revision/mailbox, revision/recipient and deterministic delivery-key constraints prevent another delivery on retries/concurrency. No resend feature. Cancelled jobs/rows/audits are retained permanently as fences; no queue cleanup service is exposed. Existing freeze cleanup rejects published revisions.
- `peracrm_campaign_queue_prepare(campaign_id, frozen_revision_id, nonce)` returns the existing job including terminal cancellation; only a complete, published, nonempty frozen attempt with valid stored content binding can prepare. Current editable draft/configuration/source changes do not invalidate historical publication. No mutation of frozen content or membership.
- `peracrm_campaign_queue_materialize(campaign_id, job_id, version, nonce, limit=200)` uses keyset batches of 1–200, verifies each frozen recipient against its eligible evaluation, and commits inserts/cursor/counts/audit together. SQL/integrity failure rolls back and version-fences a recoverable failed job; resume uses existing rows. Partial preparation cannot schedule or dispatch. Completion reconciles exact persisted delivery counts.
- `peracrm_campaign_queue_transition(..., operation, schedule=null)` supports schedule/pause/resume/cancel. Preparing → queued only after complete materialization; queued/scheduled → scheduled stores intent; preparing/queued/scheduled → paused → prior state; failed → preparing; all active states → cancelled, terminal. Cancellation retries with the current version are no-ops. Expected versions fence stale changes. Staff requires target campaign capability, readiness and no impersonation; audit uses real actor. Every mutation requires nonce `peracrm_campaign_queue_{campaign_id}`.
- Scheduling accepts only RFC3339 seconds with explicit Z or numeric offset (maximum ±14:00); rejects naive times, unknown -00:00 offsets, invalid calendar/time values and past instants. Explicit offsets disambiguate repeated local hours. Stored times are UTC. No server timezone interpretation or process to execute the intent.
- Delivery rows remain `held`; job state is the job-wide pause/cancel/partial fence. Read APIs `peracrm_campaign_queue_get` and `peracrm_campaign_queue_page` authorize and target-wrap; pages are at most 200, recipient detail additionally requires subscriber capability. Dispatch status is always false. `peracrm_campaign_queue_dispatch_allowed()` unconditionally returns false and `peracrm_campaign_queue_require_dispatch()` returns an error. No option, constant, copied database or environment can enable dispatch in this phase. The job `complete` field means preparation is complete, never campaign delivery completion.
- No preference-token issuance, subscriber writes or personalized shared HTML. No cron, automatic dispatch, worker, provider/network/mail/test email or production-enablement controls.

### Later worker contract — documentation only

The future worker must atomically claim eligible complete jobs and recipient rows with bounded leases, monotonic owner/version fencing and compare-and-swap lease renewal/result writes. Expired owners must never submit or overwrite a successor. Claim transactions must observe job pause/cancel state and use a consistent lock order. A job marked scheduled/queued alone is never authorization to send: future dispatch requires a separate explicit deployment/target/environment authorization, disabled on clones by default.

Immediately before each external submission recheck live subscriber consent, mailbox suppression and campaign/job state, immutable content/recipient bindings, and ownership/lease validity. Do not substitute a changed subscriber/client address for the frozen mailbox. Accepted and ambiguous provider outcomes require durable reconciliation and must never be blindly retried. Completion requires every recipient resolved; expired leases, uncertain acceptance, failed reconciliation or other unresolved work prevent false completion. Worker claims/leases, provider submissions/reconciliation, retries, scheduler/cron/CLI registration and all sending UI remain unimplemented. Phase 4 is not complete; Phase 5 is untouched.

### Phase 4A validation record

Executed on implementation/test head `8e28ae251f714e03a5bd53a94fd00515e59cf5f0` (local checkpoint `b99e507b`, identical full tree `dc364121d05c4f82dc5ba623854afcbe44cd847c`): all 17 standalone regressions and all 142 first-party plugin PHP syntax checks pass locally using the existing PHP 8.4.24 wrapper. JS/shell syntax, Compose configuration and whitespace checks pass. Local Docker socket access is denied; no local SQL execution is claimed.

Hosted [run 39211751255](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39211751255) passed both complete jobs: [single-site 117926587952](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39211751255/job/117926587952) and [multisite 117926588483](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39211751255/job/117926588483). Fetched both logs and verified actual additive v25→v26 migration, missing delivery-key uniqueness denial, contained schema failure/administrator retry, staff/impersonation/target isolation, actual draft/unpublished/incomplete rejection, stored snapshot and recipient/evaluation corruption rejection, exact 205-recipient preparation in bounded batches, rollback/resume, UTC scheduling, stale transitions, pause/resume without new rows, unique database constraints, real-actor versioned audit, terminal cancellation after simulated child cleanup, preserved frozen content/membership after later withdrawal, and unchanged subscriber/evidence/token/cron data with zero scoped mail/network attempts.

All six queue races passed on separate real SQL connections in each topology: prepare/prepare, batch/batch, batch/cancel, cancel/batch, pause/schedule and cancel/resume. Logs confirm actual blocking and no duplicate scope or stale overwrite. All nine existing freeze races and complete existing campaign/subscriber/default-theme desktop/mobile browser regressions also pass. Retained evidence: [single-site artifact 12167174383](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39211751255/artifacts/12167174383), [multisite artifact 12167589660](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39211751255/artifacts/12167589660). No new UI or new visual acceptance is claimed for this service-only change.

PR [#1456](https://github.com/7mus7y-dotcom/pera-wp/pull/1456) targets main and is non-draft. Final evidence update changes this document only; runtime and tests are identical to the passing head. Earlier CI runs were superseded by fixture/evidence checkpoints, not accepted as final validation. Prerequisite #1454's tombstone review is resolved; #1455's final review was clean. Phase 4 remains in progress, with worker/scheduler/UI work and external readiness outstanding. User-reported property-block failure and usability issues above remain unresolved. No provider, worker, scheduler, delivery, merge, deployment, production records or live Phase 4 tests; sending/intake/public collection remain disabled.

## Phase 4B — bounded worker and disposable fake-provider fixtures (in progress)

2026-10-19: #1456 is merged as `199cb7f07414a542a0499f5aad90280d69707317`.
Its final automated review of `e766a7b8b6` completed with no findings and no open
inline threads. No AGENTS.md was found in workspace/repository ancestors or the
repository. Shell Git fetch failed once (proxy unreachable); connected GitHub
reads verified latest main and restored all 14 Phase 4A files. The local restored
baseline tree is exactly `a3bd77c244e85b213226ed12e753b338e7d97a12`, matching
published main. Original checkout remains on `checkpoint/pre-phase4b`.

The worker is an abstract PeraCRM-owned SQL engine with explicit authority, gate
and persistence dependencies. There is no production concrete store or adapter.
`peracrm_campaign_worker_run()` always returns dispatch-disabled; the existing
global dispatch function remains unconditional false. No configuration constant,
request parameter, HTTP route, cron or automatic scheduler activates processing.
The only concrete implementation is test-owned and requires disposable fixture,
local environment, WP-CLI, exact synthetic database and a 256-bit process authority
capability. It maps writes solely to target-blog `peracrm_fixture_worker_*` tables,
cloned from prepared synthetic jobs. Fake accepted/completion outcomes cannot
update ordinary queue jobs or deliveries. Fake provider source is never loaded by
production bootstrap. Schema v27 adds lease/attempt/result fields and a unique
per-delivery/version audit through existing additive readiness/migration checks.

Each invocation handles one explicit job and at most 10 recipients. Claims lock
job then recipient rows, enforce 10 active claims/inflight rows per job and four
attempts, and persist random owner, 60-second lease, deterministic delivery key,
attempt key and version before submission. Even expired leases consume capacity
until bounded recovery fences them. Result updates require matching state, owner,
version and unexpired database-clock lease. There is no database transaction over
the provider boundary. Database uniqueness is not an exactly-once external send
guarantee. A future adapter must define its own idempotency and lookup semantics.

Final authorization rechecks gate, readiness, job state, preparation and due time,
frozen member/address/content binding, supported rules and current subscription,
evidence, cumulative suppression and linked-client eligibility. It uses fresh
locking reads in suppression→subscriber order, coordinating with existing mailbox
mutation services, and holds the job lock against pause/cancel. It never replaces
the frozen mailbox with a newer client address. Exclusions are terminal with a
reason. Once the inflight authorization commits, a concurrently starting provider
request cannot be recalled by a subsequent withdrawal/pause/cancel; such changes
prevent later authorizations. A crash in that boundary is deliberately ambiguous.
Historical membership is not sending permission. No consent, subscriber, CRM
pipeline or preference-token writes occur in the engine.

The provider contract distinguishes acceptance with bounded stable correlation,
known transient non-acceptance, permanent non-acceptance and unknown. Only proven
transient non-acceptance gets deterministic exponential jitter (30 seconds base,
3600-second base ceiling, up to 25% jitter), bounded retry-after (24 hours), and a
four-attempt ceiling. Exceptions/malformed acceptance/timeouts are unknown.
Expired claimed rows can recover without submission; expired inflight rows become
unknown, never ready. Accepted and unknown rows are excluded from claims and
ordinary lifecycle resume never resets them. Staff reconciliation requires campaign and subscriber-management
capabilities, impersonation denial, nonce, expected version and provider-verified
key/attempt-bound evidence. It records real actor and evidence digest atomically,
is idempotent for the identical request and rejects stale/different evidence.
Fake lookup/HMAC evidence and same-key/attempt idempotency are explicit fixture
behavior, not promises about any future provider.

`submission_complete` is separate from preparation `complete`. Exact persisted
counts must match expected/prepared membership, with every row accepted/excluded/
failed and no lease remaining. Preparation, held/claimed/inflight/retry and unknown
rows prevent completion. Submission acceptance/completion does not mean inbox
delivery. Cancellation remains a job-wide fence; it does not rewrite historical
membership or resolve ambiguous acceptance.

### Phase 4B verification checkpoint

Added standalone provider/production-denial/bounds contracts and real SQL synthetic
fixtures for separate-connection claims, concurrency ceiling, stale owners,
pre-submit crash and process exit after durable fake acceptance, timeout/lookup/
verified reconciliation, retry-after/exhaustion/permanent failure, pause/cancel
before claim and authorization, withdrawal/suppression after freeze and claim,
exact completion guards, authority, target isolation, protected history/state and
zero mail/network. Existing race/browser assertions remain unchanged.

Local Docker daemon is running but socket access is denied (`operation not
permitted`, UID 1000); no local SQL/browser execution is claimed. Hosted topology
execution and final local check counts will be recorded after the checkpoint.
Fixtures added are not evidence of executed acceptance.

Phase 4 remains **in progress**. Scheduler/CLI health integration, operational UI,
provider selection/credentials/real adapter/domain/inbox/webhook work remain later.
The property-block review failure and usability issues (Add identity label,
obscured configuration errors, failed import returning to subscriber list) remain
explicitly open. Historical legal/retention/editorial/accessibility and production
readiness remain open. Sending, real intake and public collection remain disabled.
No Phase 5, cron activation, merge or deployment.

### Executed Phase 4B acceptance (2026-10-19)

Implementation/test head `774db63ae69ed88be229a04af8142b4cf263fd5e` (local
checkpoint `fbfa5fdb`, identical full tree
`8f4df174807cefeefe492e88122c824ca0a46026`) passed
[complete hosted run 39231113119](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39231113119):

| Topology | Complete WordPress/MySQL job | Retained evidence through 2026-11-02 |
| --- | --- | --- |
| Single-site | [117989020163: success](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39231113119/job/117989020163) | [Artifact 12177435775](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39231113119/artifacts/12177435775) |
| Multisite | [117989020596: success](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39231113119/job/117989020596) | [Artifact 12178190098](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39231113119/artifacts/12178190098) |

Fetched both complete logs. Actual checks passed: exact 1/3/15-recipient synthetic
published sources prepared through normal services; additive v26→v27 migration
preserving prepared rows; missing worker audit uniqueness rejection; anonymous
worker with independent authority and production invocation denial; persisted
lease/owner/attempt/delivery keys; exact counts and unresolved/missing-membership
completion guards; stable fake correlation; acceptance never reclaimed; expired
pre-submit ownership recovery; ambiguous timeout held until verified lookup;
ordinary pause/resume preserving unknown; anonymous/employee/impersonated and
campaign-only reconciliation denial; rejected unverified/stale evidence;
idempotent real-actor audited reconciliation; retry-after and future retry hold;
four-attempt exhaustion; permanent failure; pause/cancel before claim and final
authorization; unsubscribe, cumulative suppression, missing evidence and changed
subscriber mailbox exclusion without address substitution; unchanged frozen
history, subscribers, evidence, tokens, ordinary queue and cron; zero scoped
mail/network calls; restored target context and no origin-blog fixture ledger.

All eight new scenarios passed per topology. Six concurrent scenarios require
distinct SQL connections and actual blocking: claim/claim with a ten-lease ceiling,
expired-lease recovery/stale owner, pause/claim, cancel/claim, authorization/pause
and authorization/unsubscribe. Two separate-process crash scenarios exit before
submission and after durable fake acceptance respectively; the latter recovers to
unknown, rejects late owner writes, resolves using verified fake evidence and
never resubmits. The authorization races demonstrate the documented committed
request boundary. All six existing queue and nine freeze races pass unchanged.
Complete default-theme desktop/mobile subscriber/import/suppression and Phase
3A/3B/3C browser regressions also pass. These are executed regression checks;
no new UI, inbox rendering, manual visual/accessibility acceptance or real-provider
validation is claimed.

All 18 standalone regressions and 149 first-party PHP syntax checks pass locally
and in hosted checks; shell/JavaScript syntax, Compose configuration and whitespace
checks pass. Local PHP uses the existing PHP 8.4.24 wrapper. Local Docker socket
and shell Git proxy limitations remain as recorded; real SQL/browser execution
comes from hosted disposable synthetic fixtures. Main was rechecked and remains
`199cb7f07414a542a0499f5aad90280d69707317`.

Earlier failed/superseded runs are not acceptance evidence. Fixture fixes preserve
single-site target ID 0 semantics, create exact-sized published memberships rather
than truncating the history binding, retain the existing mailbox race entrypoint,
and place the lease-race barrier after the job lock inside fenced recovery.
Minimum blocking assertions and existing race/browser assertions were not weakened.
The engine also rechecks ownership after mailbox contention before renewing the
inflight lease; pause before submission does not consume an attempt.

Changed files, all relative to `wp-content/plugins/peracrm/` (14 total):
`inc/campaigns/worker.php`, `inc/campaigns/queue-schema.php`,
`inc/campaigns/schema.php`, `inc/bootstrap.php`, `peracrm.php`,
`tests/campaign-worker-test.php`, `tests/campaign-foundations-test.php`,
`tests/authorization-surfaces-test.php`,
`tests/integration/campaigns/assertions.php`,
`tests/integration/campaigns/queue-worker.php`,
`tests/integration/campaigns/worker-fixture.php`,
`tests/integration/campaigns/worker-race.php`,
`tests/integration/campaigns/run-inside.sh`, and this living plan.
No theme files or existing browser assertions changed.

Non-draft [PR #1457](https://github.com/7mus7y-dotcom/pera-wp/pull/1457) targets
main. This final evidence change is documentation-only; engine and fixtures match
the passing head above. Phase 4 remains **in progress** with scheduler/CLI health
integration, operational UI and later real-provider work outstanding. Property-block
review failure and all recorded usability issues remain open. Sending, real intake
and public collection remain disabled. No Phase 5, cron activation, merge or
deployment.

## PR #1457 acceptance follow-up — attempts and sequential leases (technical acceptance passed)

Follow-up starts from PR head `fcdd07cbf30761e071283c8e3c9dc411b40da529`
on `feat/campaign-phase4b-worker`, based on main `199cb7f`. Both review findings
were inspected against current code. The existing no-submission pause/future-schedule
path already returns the reserved attempt; new fourth-claim regressions exercise
three proven transient outcomes, claim four, pause/resume or future scheduling,
zero calls while blocked, restored count three and successful fourth submission.

`run()` now claims one recipient immediately before processing it, up to the same
explicit 1–10 invocation bound. It does not acquire leases or reserve attempts for
recipients waiting behind earlier provider calls. Atomic claims retain the shared
per-job concurrency ceiling and existing owner/version fences and live rechecks.
If processing aborts, later recipients remain held with untouched attempt budgets.
Bounded recovery also refunds expired `claimed` reservations (including claim four)
because submission was never authorized. Expired `inflight` rows still become
unknown and retain their consumed attempt; acceptance/unknown cannot be reclaimed.

The provider boundary explicitly receives `timeout_seconds=20`; a future adapter
must enforce this total transport timeout against the 60-second in-flight lease,
leaving 40 seconds for persistence/lock contention. This synchronous neutral
engine cannot forcibly interrupt a misbehaving dependency. A return after lease
expiry is rejected by ownership fencing; bounded recovery holds the in-flight
outcome as unknown for verified reconciliation. It is never treated as proven
non-acceptance and never blindly retried. No real adapter is introduced here.

Added real SQL fixture regressions for ten seven-second simulated submissions
(cumulative 70 seconds), no waiting leases/attempts, a provider ignoring its timeout
and taking 61 seconds, preserved untouched rows after abort, unknown recovery,
stale-result rejection, no accepted/unknown retry, and refund/reprocessing of
three untouched fourth claims. Elapsed-time tests advance only the disposable
SQL connection's database clock with MySQL `SET timestamp`, restoring it in
`finally`; they exercise the actual SQL lease timestamps/fences without wall-clock
sleeps or real delivery. Existing separate-connection race/browser assertions are
preserved. Focused/full execution and review-thread evidence will be recorded
below after validation; added fixtures alone are not acceptance evidence.

Phase 4 remains in progress. Production invocation/global dispatch remain
unconditionally disabled and fake outcomes stay in isolated fixture ledgers.
Scheduler/CLI health, operational UI and later real-provider work remain later;
property-block and recorded usability issues remain explicitly open. No scheduler,
UI, provider integration, real delivery, merge or deployment.

### Follow-up executed evidence and review disposition

Implementation/test head `2966c2be7cc5bd9eba55eb3f1ad4aec428b1f814` (local
checkpoint `b34bdd25`, identical full tree
`64e622ba6ebfd29905d0a83a70a06a72f38bd4f3`) passed
[complete run 39233082965](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39233082965).
Both complete real WordPress/MySQL suites succeeded:
[single-site job 117995515845](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39233082965/job/117995515845)
and [multisite job 117995516221](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39233082965/job/117995516221).
Retained [single-site artifact 12177953438](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39233082965/artifacts/12177953438)
and [multisite artifact 12178545514](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39233082965/artifacts/12178545514)
expire 2026-11-02.

Fetched both complete logs and verified every new fourth-claim, cumulative slow
submission, over-lease, untouched recovery and stale-owner assertion described
above. The logs explicitly confirm future scheduling remains a gate, ten calls
with 70 simulated cumulative seconds succeed with no waiting claims, five later
rows remain untouched, and a 61-second violating call cannot persist a late result.
Its recovery is unknown, two later rows remain at attempt zero and can proceed
without resending accepted/unknown rows. Three untouched fourth claims recover at
count three and subsequently succeed at count four. Invalid invocation bounds
are rejected before claiming/submitting. These are executed SQL regressions using
simulated elapsed database time, not real slow network/provider acceptance.

All eight existing worker race/crash scenarios, six queue races, nine freeze
races, full desktop/mobile browser regressions, live eligibility and protected
history/subscriber/consent/token/ordinary-queue/cron assertions pass unchanged in
both topologies. Scoped mail/network calls remain zero. All 18 standalone
regressions, 150 first-party PHP syntax checks, focused worker contracts, shell
syntax and whitespace checks pass. Local Docker/shell-network limitations remain
as recorded; actual SQL/browser execution is hosted, using synthetic fixtures.

Posted separate fix-specific evidence replies for the
[fourth-claim finding](https://github.com/7mus7y-dotcom/pera-wp/pull/1457#discussion_r4312465673)
and [sequential-lease finding](https://github.com/7mus7y-dotcom/pera-wp/pull/1457#discussion_r4312465936),
then resolved both verified threads. Latest-head review is requested after this
final documentation checkpoint; thread resolution and passing tests alone do not
establish a clean latest-head review. This evidence checkpoint changes only the
living plan; runtime and fixtures are identical to the passing head above.

Follow-up changes only `inc/campaigns/worker.php`, the fixture provider and queue
worker harness, new `tests/integration/campaigns/worker-lease-acceptance.php`, and
this plan. No schema, scheduler, UI or real-provider integration. Production
dispatch stays unconditionally disabled, fake outcomes stay isolated, Phase 4
remains in progress and the recorded property-block/usability issues remain open.
No sending, real intake, public collection, merge or deployment.


## Phase 4C — scheduler, CLI and operational health (in progress)

2026-10-19: #1457 merged at `2657fcad92e5f7ba305260ee151e89d957ffd41b`.
Final automated review of `a712e31f121b44b68d4ff8b3c4d136320eb32888` completed
without findings. Earlier attempt-budget and sequential-lease findings have
fix-specific SQL/topology evidence and were resolved. This phase preserves those
worker rules and existing races/browser assertions. No AGENTS.md was found in
workspace/repository ancestors or repository. Shell fetch failed once (proxy
unreachable); connected GitHub supplied latest main's files. Restored local base
tree `9db595c2604d5a95b29508a8d31641c85c5e6b8c` exactly matches main's Git tree.
Original checkout is retained at `checkpoint/pre-phase4c`; local reconstructed
base commit is `584c3f3d`. Published commits will parent actual latest main.

Decisions: one target-blog production action with an unconditional gate before
any delivery state work; no scheduled production dispatch event or enable switch.
Fixture scheduler alone supplies concrete fake worker/provider dependencies and
isolated ledger/metadata. Three-job, ten-recipient, ten-recovery-row invocation
bounds and a 25-second work-start deadline reserve the worker's full 20-second
transport budget before each submission. Target-table-specific connection-owned
MySQL lock excludes overlapping schedulers; worker leases/fences remain decisive.
No history cleanup is introduced. Schema v28 adds non-unique health aggregate/
lease/result indexes without changing delivery uniqueness or cancellation fences.

Health requires a real target-blog report user and denies impersonation; readiness
failure remains reportable. Disabled/invoked/processed/blocked, claimable due jobs,
active/expired leases, unknown and retry backlog, last known successful terminal
processing and ledger acceptance are distinct. No event existence or disabled
return is success; acceptance is not verified inbox delivery. Output uses bounded
allowlisted metadata and fixed diagnostics. CLI offers JSON/text and exit codes
0/2/3/4, read-only status/health/due, guarded fixture-run/fixture-recover only.
No production send/force-reset/resend/enable command or reconciliation bypass.

[Operational runbook](email-campaigns-operations.md) records `DISABLE_WP_CRON=1`,
explicit future server-cron/WP-CLI operation, connection/host overlap protection,
interruption recovery and backup-restore reconciliation. It installs nothing.
Synchronous dependency/SQL lock waits cannot be forcibly interrupted by the
neutral engine; the deadline bounds new work starts, future adapter/hosting
watchdog/lock timeouts require separate activation validation. An over-lease
inflight result remains unknown and cannot be blindly retried.

Validation checkpoint: 19 standalone regression suites and 157 first-party PHP
syntax checks passed locally, together with shell/JavaScript syntax and whitespace
checks. Real single-site/multisite SQL/browser suites and new CLI/scheduler/race
acceptance will run on hosted GitHub Actions; local Docker socket access is denied.
Added tests alone are not executed acceptance. Hosted results will be recorded
below. No production/real-provider or new visual acceptance is claimed.

Phase 4 remains **in progress**. Operational UI, provider selection/credentials/
real adapter/domain/inbox/webhook and external readiness remain later. Property-
block review failure, confusing Add identity label, obscured configuration errors
and failed imports returning to subscriber list remain explicitly unresolved.
Sending, real intake and public collection remain disabled. No Phase 5, cron
activation on the actual host, merge or deployment.


### Executed Phase 4C acceptance (2026-10-19)

Final runtime/test head `ad368ff8f6f6bc048a54c155785c83dceaf84734` (local
checkpoint `9a80e867`, identical full tree
`26f79f61ea35c520bae26d2b6e8784bba2412704`) passed
[complete hosted run 39237202283](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39237202283):

| Topology | Complete WordPress/MySQL job | Evidence retained through 2026-11-02 |
| --- | --- | --- |
| Single-site | [118009258227: success](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39237202283/job/118009258227) | [Artifact 12180290017](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39237202283/artifacts/12180290017) |
| Multisite | [118009258015: success](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39237202283/job/118009258015) | [Artifact 12179143039](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39237202283/artifacts/12179143039) |

Fetched both complete logs and checked the explicit scheduler/CLI/race assertions
and absence of failure markers. Actual checks passed in both topologies:
repeated activation/init without production dispatch registration; existing
CRM/subscriber cron preservation; direct/action production denial without claims
or submissions; anonymous/impersonated health denial; disabled/invoked/processed/
blocked distinctions; additive v27→v28 index installation and missing-index
rejection; future/UTC-due discovery; elapsed work-start/transport reservation,
recipient/recovery/job/output limits; saturated-capacity exclusion; duplicate and
same-process recursive entry protection; separate-process immediate overlap
rejection; process death releasing the scheduler lock; expired unsubmitted
reservation recovery; actual scheduler process death after durable fake acceptance
recovering to unknown without resubmission; retry-time/backlog and active/expired
lease distinctions; actual disposable scheduled event against the isolated fake
ledger; bounded allowlisted metadata with no private payload; restored target
context and no origin-blog fixture ledger. Production queue/history/subscriber/
consent/suppression/token bytes remain unchanged; scheduler checks record zero
mail/HTTP calls. No fake outcome reaches ordinary campaign queues.

The real WP-CLI checks execute JSON commands and assert exact exits, privacy-safe
stdout and empty stderr. Invalid flags/limits/anonymous access and fixture guards
return 3; disabled production health/status returns 2; bounded read and explicit
fixture commands return 0. Authorized due/health checks under a forced persistent
schema failure or scoped storage fault return 4. The storage fault includes a
synthetic private SQL comment, which never reaches command output. A disabled
module is separately reported from a physically ready schema and combined queue
readiness. Module configuration cannot enable production dispatch.

PR [#1458](https://github.com/7mus7y-dotcom/pera-wp/pull/1458) is non-draft against
main. Its [exit-code finding](https://github.com/7mus7y-dotcom/pera-wp/pull/1458#discussion_r4312669086)
was fixed with typed operational failures and the explicit readiness/storage CLI
regressions above, verified in both complete suites, and resolved. No clean
latest-head automated review is claimed from resolving that thread alone.
#1457's final review was clean and both prerequisite threads were verified resolved.

All eight existing worker race/crash scenarios, six queue races and nine freeze
races pass unchanged. The only worker-fixture adjustment removes newer health
indexes when reconstructing the older pre-worker schema; worker/freeze/browser
assertions were not weakened. Complete default-theme desktop/mobile subscriber,
import, suppression and Phase 3A/3B/3C browser regressions pass. This is regression
execution, not new visual/accessibility acceptance. All 19 standalone suites and
158 first-party PHP syntax checks pass; shell/JavaScript syntax, hosted Compose
configuration and whitespace checks pass. Local PHP uses the existing PHP 8.4.24
wrapper. Final evidence publication changes this living plan only; runtime and
fixtures exactly match the passing head above. Earlier superseded runs are not
final acceptance evidence. Main was rechecked at `2657fcad92e5f7ba305260ee151e89d957ffd41b`.

Limitations: local Docker socket access is denied and shell Git proxy is
unreachable; connected GitHub supplied hosted execution/publication. No actual
production host execution, production data, provider/inbox guarantee, credentials,
mail/network delivery or new operational UI was introduced or validated.
The 25-second deadline bounds work starts; synchronous dependency/SQL waits cannot
be forcibly interrupted, and future transport/watchdog/lock-timeout validation
remains required before any production activation. Server cron was not installed
or activated; `DISABLE_WP_CRON=1` remains documented, not changed.

Phase 4 remains **in progress** with operational UI and real-provider/external
readiness outstanding. Property-block review failure and all recorded usability
issues remain unresolved. Sending, real intake and public collection remain
disabled. No Phase 5, merge or deployment.

### PR #1458 follow-up — failed materialization health counts

The latest finding omitted the recoverable `failed` queue-job lifecycle state.
Health now includes `failed_jobs` alongside the existing five lifecycle counts;
JSON and text use the same allowlisted health payload. This counts job rows,
independently of recipient delivery rows in the `failed` outcome state.

The existing real SQL queue regression now inspects health before its forced
materialization update fault, after rollback/persisted failure, after explicit
resume, and after successful materialization. It asserts exact lifecycle deltas,
all existing state aggregates (including paused/scheduled/cancelled checkpoints),
and no change to job counts when a recipient delivery is temporarily failed.
Every checkpoint executes the real JSON and text CLI in separate processes,
checks exact counts/disabled exit 2/empty stderr/privacy, and compares complete
queue, delivery, lifecycle/worker event and option snapshots before/after reads.
Existing history/subscriber/token/cron/no-mail/no-HTTP assertions remain intact.

Executed acceptance: implementation/test head `999e5839dd67279e01cd44c427347afa2d81b336`
(full tree `2d44178dfe4e061bc42c611cb25cb2b4c76b03df`; local checkpoint
`d97226b8` has the identical tree) passed
[complete hosted run 39242677006](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39242677006).
Both full WordPress/MySQL/CLI/race/browser suites passed:
[single-site job 118027019101](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39242677006/job/118027019101)
and [multisite job 118027019528](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39242677006/job/118027019528).
Retained [single-site artifact 12182291256](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39242677006/artifacts/12182291256)
and [multisite artifact 12182326228](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39242677006/artifacts/12182326228)
expire 2026-11-02.

Fetched both complete logs and verified every new assertion above, including all
eight lifecycle checkpoints in JSON and text, exact failed/preparing/queued
deltas, independent failed recipient state, disabled exit 2, empty stderr,
private-field exclusion and unchanged ledger/event/option/context snapshots.
Both logs contain no failure markers. Existing queue/worker/freeze races,
scheduler/CLI/crash and desktop/mobile browser regressions pass unchanged.
The hosted standalone regression and PHP syntax steps passed in both jobs;
local whitespace and focused harness shell syntax checks also pass.

Local PHP is absent and the Docker socket is denied; blocked commands were not
retried. Hosted execution uses only disposable synthetic fixtures. This final
acceptance checkpoint changes documentation only; runtime/tests match the passing
head above. Fix-specific evidence is posted on the finding before resolution;
latest-head review is requested after publishing this checkpoint. Passing tests
and thread resolution do not establish a clean latest-head review.

Only the health aggregate, focused queue regression and this plan change.
Dispatch, intake and public collection gates remain unchanged and disabled.
Phase 4 stays in progress; no new UI, merge or deployment.


## Phase 4D — operational queue UI (in progress; repository checks passed)

2026-10-19: #1458 merged at `e15084a2e978f5c735defb1ebc4f0cd2bc4b18f1`.
Its final head `bda31dbd28ca10e6439703d719c4aff3fa5a4853` received a clean
[latest-head review](https://github.com/7mus7y-dotcom/pera-wp/pull/1458#issuecomment-6265975508).
The blocked CLI exit-code and omitted failed-job health findings are fixed. The
failed-job regression passed both complete suites in
[run 39242677006](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39242677006),
with fetched-log acceptance recorded above. This accepts 4C as the prerequisite;
it does not infer 4D or overall Phase 4 completion.

No applicable AGENTS.md found in repository/workspace/ancestors. Shell Git fetch
failed once due to unreachable configured proxy. Connected GitHub supplied exact
latest-main changed file contents, preserved in local checkpoint `6d38bd42` on
`work`, before branching. Remote publication will use the actual main SHA as
parent; local preservation is distinct from remote history. Latest main advanced
to `7b1289456e47c525d8a2f1f4dccc32bc2f744306` during implementation (one
unrelated theme-only sell-page change). This upstream change is incorporated,
with exact remote/local full-tree equality; the PR diff remains PeraCRM-only.

### Bounded implementation

- PeraCRM campaign routes and shell own queue overview, campaign published-revision
  discovery and campaign-linked job detail. Existing `crm.css`/`crm.js` own all
  new styles and behavior. No theme files, helpers or dependencies.
- Preparation delegates to existing published-only idempotent services; 200-row
  explicit continuation, persisted reload state, rollback/failure recovery and
  terminal cancellation preserve lifetime revision/ledger fences. No send,
  dispatch enable, worker, provider, fixture execution or cron activation control.
- Every queue read/handler denies impersonation and requires existing target-blog
  campaign authority. Recipient reads and handlers independently require subscriber
  authority. Global health retains the existing report capability. Responses expose
  only fixed aggregate facts, selected metadata and permitted mailbox/state pages;
  no delivery keys, lease owners, correlation tokens or provider payloads.
- Expected versions, nonces and real-actor audit remain service-owned. One in-flight
  request per screen and cross-tab invalidation require explicit state refresh.
  Schedule input lives outside refreshed server panels and is retained across
  validation errors, stale tabs and reload-state actions. Full navigation warns
  when input is unsaved. Terminal cancellation requires explicit confirmation.
- Istanbul local input is parsed in `Europe/Istanbul`, validated without timezone
  defaults and passed as explicit-offset RFC3339 to the existing UTC contract.
  Display labels explicitly identify Europe/Istanbul and UTC storage. All screens
  prominently state “Dispatch disabled — scheduled work will not send.”
- Detail separates preparation/lifecycle, considered/prepared totals, each delivery
  state, failed job versus failed deliveries, retry backlog, active/expired leases
  and unknown outcomes requiring reconciliation. Health separates invocation from
  successful known processing and provider acceptance. No manual outcome override;
  provider lookup/reconciliation UI remains deferred until a real provider exists.
- Discovery uses keyset pages of 25 jobs / 20 published revisions; recipient pages
  are at most 200 and use the existing authorized service. Sanitized readiness and
  validation feedback has accessible status announcements and keyboard focus.

### Initial validation checkpoint (superseded by executed acceptance below)

All existing standalone regressions pass locally with the preserved PHP 8.4.24
wrapper. New PHP and JavaScript syntax checks pass. Complete single-site/multisite
SQL/race/browser execution is not yet accepted. Docker local socket is denied
(operation not permitted, UID 1000, daemon running); no repeated attempt or local
integration success is claimed. The existing hosted workflow will run both full
suites without removing or weakening service races or earlier browser assertions.

New CLI-only synthetic seeds create published revisions and visual-only unknown,
failed, retry and lease states before browser tests. No provider or worker is
invoked to generate those rows, and browser fixture endpoints do not create
outcomes. Browser checks cover actual desktop/mobile queue navigation, published-
only and duplicate preparation, bounded batches, rollback/reload/recovery,
Istanbul schedule validation/display, competing tabs, pause/resume/cancel,
recipient permissions and employee/anonymous/impersonation denial. Evidence hashes
protect subscriber/evidence/token data, immutable history, worker events,
pre-seeded unknown ledger, cron and scheduler invocation metadata; scoped mail/HTTP
probes must remain zero. Screenshot review and hosted evidence remain pending.

### Phase 4 acceptance assessment

Phase 4 remains **in progress**. Existing 4A/4B/4C technical evidence covers durable
queue/lifecycle, worker leases/fences, independent-connection races, crash/unknown
handling, suppression rechecks, bounded retries, UTC storage, unresolved completion
and isolated fake-provider ledgers. 4D repository checks now pass both complete hosted suites and inspected
desktop/mobile screenshots, as recorded below. Latest-head review remains open. Original
external staging cron/CLI ownership, frequency/lock behavior and restore-from-
backup procedure remain unverified; no production activation is authorized.
Future real-adapter watchdog/timeout/lock validation, provider readiness and
verified-evidence reconciliation UI stay deferred. A UI alone cannot complete
Phase 4 or authorize Phase 5.

The property-block review failure remains open. Sender-label confusion (“Add
identity”), obscured configuration errors and import-error navigation remain open.
Historical consent/legal-basis/retention, editorial/accessibility approval and
production-readiness requirements remain open. Sending, real intake and public
collection remain disabled. No Phase 5, merge, deployment or active production cron.


### Executed Phase 4D repository acceptance (2026-10-19)

Runtime/test head `69af328ba01038700c6b1dfd0de8e64a28612c45` (local checkpoint
`341b59ee`, identical full tree `7d9f2ff2358733833c0126c9a886acad6ccbbdd1`)
passed [complete hosted run 39308295804](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39308295804).
Both complete logs were fetched and checked; no PHP warning/fatal error appeared.

| Topology | Complete WordPress/MySQL/CLI/race/browser job | Retained logs and PNG screenshots (until 2026-11-02) |
| --- | --- | --- |
| Single-site | [118250970031: success](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39308295804/job/118250970031) | [Artifact 12214468295](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39308295804/artifacts/12214468295) |
| Multisite | [118250970574: success](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39308295804/job/118250970574) | [Artifact 12214528178](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39308295804/artifacts/12214528178) |

Both jobs pass all 19 existing standalone regressions, 161 first-party PHP syntax
checks, JS/shell syntax and disposable Compose configuration. Existing six queue,
eight worker, nine freeze and three scheduler race/crash checks pass unchanged,
including actual independent SQL connections, lease/version fences, ambiguous
acceptance, suppression rechecks and isolated fake ledgers. Earlier browser
assertions remain intact and pass. Local whitespace checks pass; local Docker
remains unavailable, so no local SQL/browser execution is claimed.

New actual default-theme browser flows pass at 1440×1000 and 390×844 in both
topologies: frozen-campaign navigation; draft/unpublished rejection; idempotent
preparation and duplicate-click exclusion; exactly 200 then remaining 4 prepared
recipients; injected SQL rollback, persisted failed state, reload, explicit resume
and completion; future Istanbul validation and explicit UTC service conversion;
pause/resume; competing stale versions; unsaved schedule retention and untouched
stale-tab synchronization; dismissed then confirmed terminal cancellation;
bounded nonoverlapping recipient pages; campaign-only recipient control/handler
denial; employee/anonymous/impersonation and missing-nonce denial. Unsupported
send, enable, fixture, manual acceptance and reconciliation operations are rejected.

The fixture verifies a persisted v4 rewrite marker and stale rules upgrade to v5
queue overview/detail routes with exactly one soft flush; a second check does not
flush. This fixes the [PR #1460 rewrite finding](https://github.com/7mus7y-dotcom/pera-wp/pull/1460#discussion_r4317457197)
in `ed6eba71`; both complete suites verify it and the thread is resolved. No clean
latest-head review is inferred from fixing or resolving that finding.

Global health is refreshed with job state: a failed preparation increments only
failed-job health and explicit resume restores its previous count. Failed-delivery
and unknown-outcome visibility is tested separately. Pause/resume on a seeded
unknown job leaves its delivery ledger byte-identical. Subscriber/consent/
suppression/preference-token data, frozen content/membership, worker events,
unknown ledger, cron and scheduler invocation/success metadata hashes remain
unchanged by queue UI flows. New UI-prepared rows stay held with zero worker
attempts/owner/attempt/provider markers; scoped mail and HTTP probes remain zero.
No worker invocation, browser-generated outcomes, token issuance or cron activation.

Each artifact retains 18 queue PNGs: `queue-{overview,error,failed-job,schedule-error,
scheduled,campaign-only,cancelled,unknown,empty}-{desktop,mobile}.png`.
Actual JPEG review captures of those rendered components were inspected through
hosted logs, including desktop/mobile long labels, empty states, validation and
preparation errors, failure recovery, cancellation, permissions and unknown
outcomes, with additional multisite captures. The full PNGs remain in the artifacts.
Fixed shell headers are hidden during component capture only. Tests assert no
page overflow, bounded table scrolling and effective wrapping despite inherited
shell rules. Mobile counts and explicit timezone labels remain readable. This
is Chromium verification, not manual assistive-technology or inbox acceptance.

Acceptance corrected the browser response-body/navigation race without weakening
idempotency assertions; refreshed stale health counts; preserved edited schedule
input while updating untouched stale tabs; overrode inherited table nowrap only
inside queue screens; moved empty messages outside scrolling tables; and made
schedule errors describe the actual Istanbul field. Superseded/failed/cancelled
runs are not the final acceptance evidence above.

**Phase 4 assessment against the original plan:** repository acceptance is met
for durable queue/lifecycle, bounded worker/scheduler, real DB races/crashes,
unknown/retry/lease fences, live eligibility checks, UTC storage, unresolved
completion, isolated fake ledgers and the operational UI. Phase 4 remains
**in progress**: clean latest-head review and original external staging cron/CLI
ownership, frequency/lock behavior and restore-from-backup verification remain
open. Production topology/HTTPS/cache/clone readiness and carried-over historical
consent/legal-basis/retention, editorial/brand/accessibility approval remain open.
Real provider integration and provider lookup/reconciliation UI stay deferred;
no Phase 5 implementation or provider-readiness claim is made.

The property-block failure, sender-label confusion, obscured configuration errors
and import-error navigation remain open. Sending, real intake and public
collection remain disabled. No production cron activation, merge or deployment.
[PR #1460](https://github.com/7mus7y-dotcom/pera-wp/pull/1460) is non-draft against
main. The final acceptance commit changes this plan only; runtime/tests exactly
match the passing head above, and all local checkpoints remain preserved.


## Phase 5A — provider evaluation and integration specification (2026-10-20)

Documentation scope only, on `docs/peracrm-phase-5a-provider-spec`, based on latest
main `d58b018191cc34af24d0d222ce63ba9d5b4c7975`. Official Brevo and SES/SNS docs
were checked on 2026-10-20. The [specification](email-campaigns-provider-specification.md)
recommends SES v2, records why Brevo's marketing campaign path is a poor fit for
this worker, and separates documented behavior from proposed policies and
account unknowns. It defines code gaps, the exact first implementation task,
four subsequent PR stages, validation, unknown-outcome holds and rollback.
Phase 5 remains **in progress** for this documentation; runtime implementation is
**not started**. Phases 2–4 retain their outstanding acceptance requirements;
Phases 6–7 remain **not started**.

### User-reported live evidence — separate from automated checks

The user reports live queue UI tests passed: preparation from a frozen campaign,
one synthetic recipient, schedule intent, pause/resume and terminal cancellation.
**No emails were sent.** This report was received on 2026-10-20; it was not
independently reproduced in this documentation task. It is separate from the
previous automated hosted suites and does not establish CLI health, provider
acceptance, inbox delivery or real-host scheduler/restore behavior.

CLI health and real-host scheduler ownership/frequency/locks and restore
verification remain outstanding. The property-block issue, sender-label confusion
(“Add identity”), obscured configuration errors and import-error navigation remain
open, together with previously recorded editorial/accessibility, consent/legal-
basis/retention and production-readiness requirements. Production dispatch, real
intake and public collection remain disabled. No production cron, account/DNS,
credential request, real message, runtime change, merge or deployment occurred.

### Documentation validation

Checked the specification against actual worker/provider interfaces, captured
renderer output, queue schema/ledger, scheduler gates and public preference/
suppression services. Local relative links, official source references, Markdown
table structure, documentation-only file scope and `git diff --check` are checked
for this PR. Existing hosted results are historical evidence, not rerun or claimed
as new validation. No runtime, database, browser, CLI-health, DNS, provider or
inbox checks were executed for Phase 5A. Shell fetch was blocked by the configured
proxy; connected GitHub confirmed the latest-main baseline already present locally.


## Phase 5B — first bounded submission contracts (2026-10-20)

Completed repository scope (Phase 5 remains **in progress**):

- [submission.php](../inc/campaigns/submission.php) defines immutable `PeraCRM_CampaignSubmissionV1`: exact versioned fields, captured subject/from/reply-to/UTF-8 HTML/text, one frozen mailbox, positive IDs, snapshot hash, opaque delivery/attempt keys, fixed SES scope/configuration version, personalization version and 20-second budget. Unknown fields, header controls, invalid addresses/IDs/scope, invalid UTF-8, oversized bodies and unresolved `{{...}}` slots fail closed. Copies cannot mutate the envelope; hashes use canonical field/scope ordering; debug output is redacted and serialization is disabled. This value is content, not delivery authority.
- The abstract `PeraCRM_Campaign_Frozen_Content_Resolver` authorizes before storage reads and again before returning content, checks exact delivery/job/published revision/completed preparation/frozen recipient/subscriber/snapshot/target bindings and stored content hash. Its source contract uses bounded trusted storage projections with native IDs/booleans. A future implementation must enforce target context, live gate/eligibility, owner/version/lease and exact inflight attempt authority; it must not infer these facts from caller-supplied rows. No concrete storage reader or relaxed staff read API was added. Renderer support defaults to deny. Only a test subclass accepts `fixture-only`; **there is no supported production deliverable renderer**. Existing `unpublished_content`/schema_version=1 review artifacts always fail, even with a valid hash.
- [providers/ses.php](../inc/campaigns/providers/ses.php) maps Simple content, captured sender (including UTF-8 display-name encoding), Reply-To, exactly one To, configuration set and non-PII delivery/attempt/snapshot/envelope tags. Scope is fixed at construction and must exactly match the envelope. Regional endpoint selection is limited to explicit supported SES regions. The injected transport contract requires the configured account's signing identity, one request, TLS verification, redirects off, 3-second connection cap within 20-second total budget and retries explicitly zero. No SDK/dependency, credential lookup, HTTP implementation, bootstrap/factory or existing worker interface change was added.
- Results distinguish accepted provider ID, complete authenticated documented throttling/quota non-acceptance, documented permanent rejection, and unknown. Only strict matching status/code pairs establish non-acceptance. Proxy/truncated responses, malformed success/ID, unexpected status/code, exceptions and late returns are unknown; no automatic resend/failover. Rate-limit hints use a fixed bounded 60 seconds. Rejection/rate-limit readiness-block metadata is returned for future account gating, not persisted or connected to the current worker. Raw errors, bodies, request payloads and chained exceptions are never returned. Provider acceptance is not inbox delivery.

### Validation and limits

Using existing `/workspace/.pera-env/bin/php` (PHP 8.4.24), syntax checks passed for both new runtime files and the new standalone test. All **10** standalone `tests/campaign-*-test.php` suites passed: drafts, foundations, freeze, previews, queue, scheduler, snapshots, submission, theme independence and worker. `git diff --check` passed. The submission suite covers immutable copies/hash, authorization before reads, default renderer denial, frozen recipient/subscriber and all binding mismatches, correctly hashed review-only rejection, tampered content, header/ID/address/scope/version/body validation, UTF-8 mapping, sender/Reply-To, single-recipient request, correlation tags, explicit timeout/no-retry options, all four result kinds, authenticated complete rejection requirements, exception/error redaction and unchanged unconditional production dispatch block. These are synthetic mocked contracts, not new DB/race/browser/host/SDK/DNS/provider/inbox acceptance evidence.

### Remaining work and activation boundary

A **separate deliverable renderer/capture/approval version is required**. This PR does not implement it or promote old captures. Recipient personalization/link slot contexts, authoritative HTTPS URL validation, signed purpose-separated preference/one-click tokens and unsubscribe headers require that reviewed version and token contract. Version 1 here performs no substitution and accepts no unresolved brace slots. Durable immutable attempt-to-envelope/provider/account/region/config binding and deployment-scoped opaque key generation must precede concrete integration; mapper tags are correlation only and do not authorize sends. Envelope hashing alone is not persisted attempt binding or provider idempotency.

Concrete storage/worker integration must independently recheck current authority/eligibility and persist attempt binding before any network request; scheduled-job due policy remains with the existing worker. Concrete transport must prove actual full-budget timeout/no-retry behavior including credential refresh/DNS/TLS, signing account/region, authentic complete response decoding and provider error taxonomy. Mock flags are trusted transport assertions, not cryptographic verification. No provider lookup/reconciliation evidence is implemented. Account-wide circuit breakers/rate budget, authenticated events and durable receipts/dedupe, suppression sync, unsubscribe handling, readiness, restore/host/watchdog and allowlisted real inbox acceptance remain subsequent work. Existing Phase 2–4 acceptance gaps remain open.

The unconditional queue/scheduler production dispatch block is unchanged. No real intake/public collection, SDK installation, credentials, real messages, cron activation, UI, webhooks, merge or deployment. Rollback leaves existing ledgers, immutable snapshots, cancelled fences and suppression knowledge intact; unknown outcomes never authorize retries.

### PR #1464 scheduled-job resolver follow-up (2026-10-20)

Inspected latest PR head `f1d9c244f042eb7e28dc6efa77ce1af262fdefb4` and
preserved the existing work before this focused fix. The resolver now accepts
both worker-supported job states, `queued` and `scheduled`; due-time policy stays
with worker authorization/gating. Regressions prove scheduled content resolves to
the identical envelope, authorization still denies before reads, and unsupported
job states remain rejected. The scheduled regression failed before the fix and
passed afterward. All 10 standalone campaign suites, both changed PHP syntax
checks and `git diff --check` passed using the existing PHP 8.4.24 wrapper.
No production wiring or activation changed. Deliverable renderer/capture and all
other dependent work and acceptance limits listed above remain outstanding.

### PR #1464 SES throttling follow-up (2026-10-20)

Inspected latest PR head `5171a044fc84795058bb27141719ca162916330e` and
preserved the clean local checkpoint before the focused review fix. A complete,
authenticated HTTP 400 `ThrottlingException` now joins the existing documented
transient rate-limit pairs. The fixed 60-second backoff, readiness blocking and
zero automatic submission retries remain unchanged. Regressions cover the new
pair, incomplete/unauthenticated and nonboolean trust flags, wrong or noninteger
status, conflicting message ID and mismatched error code; negative cases remain
unknown with redacted diagnostics and exactly one injected transport call.
The positive regression failed before the fix and passed afterward. All 10
standalone campaign suites, syntax checks for the two changed PHP files and
`git diff --check` passed with existing PHP 8.4.24. This is mocked contract
evidence only; production dispatch/intake/public collection gates and all
remaining delivery prerequisites above remain unchanged and disabled.


## Phase 5B — bounded deliverable renderer and capture/approval (2026-10-20)

Phase 5 remains **in progress**. `peracrm_campaign_render(..., 'deliverable_v2')`
produces template v2 HTML/plain text from the same ordered sections. Article,
property and CTA destinations are validated public URLs and working anchors in
captured HTML, with identical raw URLs in text. Subject/body/labels/attributes
are escaped for their contexts; unsafe or missing required destinations and
incomplete blocks fail closed. Staff-only preview/readiness notices are absent
from deliverable bodies. Editable review-v1 rendering remains inert.

New captures have payload/content schema 2, purpose `deliverable_template`,
renderer `peracrm_email_v2`, template 2 and slot version 1. The exact typed slot
map distinguishes `preferences` from `unsubscribe`, requires HTTPS values and
recipient/attempt binding, and permits only a complete quoted HTML `href` and a
standalone text URL. Captures contain markers, never issued recipient URLs or
subscriber inputs. Validation rejects missing, duplicate, unknown, misplaced or
changed slot definitions and author-controlled marker injection. This PR does
not accept recipient URL values or perform substitution. Dependent code must
validate trusted origin/path/purpose and bind issuance to recipient/attempt,
escape URL attributes independently from text, and reject unresolved markers.
The existing Phase 2 preference bearer is not silently reused for unsubscribe.

`content_review_v2` proofs bind actor/blog/campaign/saved revision/expiry and the
hash of the entire captured candidate: exact HTML/text, renderer/template/slot
versions and definitions, document, resolved content, audience rules, approved
sender/Reply-To/footer configuration, approver and configuration revision.
Capture re-resolves under the existing draft/configuration locks and rejects
stale proofs before inserting. Lost-response replay returns the exact original
artifact. Configuration/source changes cannot rewrite stored bytes. The old
proof purpose cannot approve new captures.

Historical schema-1 snapshots remain readable with their original bytes and
hashes, without re-rendering, migration or silent promotion. The abstract frozen
resolver still defaults to deny; both old review-only captures and new unfilled
v2 templates are ineligible for submission. A concrete authorized decoder and
context-safe expansion are dependent integration work, not inferred from the
new format name. No public dispatch gates or submission contracts were relaxed.

Staff saved/review previews use an inert display copy: anchors become spans,
retaining labels/styles while stored HTML, text, links and hashes remain intact.
The empty iframe sandbox, restrictive CSP and no-referrer isolation remain.
No staff CSS/JS was introduced; existing PeraCRM `crm.css`/`crm.js` own the UI.

### Validation and acceptance limits

Executed locally with existing PHP 8.4.24: all **11** standalone campaign suites
passed, including focused deliverable escaping/URL rejection, HTML/text ordering,
article/property/CTA links, slot contexts/definitions, stale renderer/output/
configuration approval, v1 proof rejection, exact replay, historical bytes/hash
and unresolved-template submission rejection. PHP syntax, browser-script syntax
and whitespace checks passed. Disposable SQL assertions and desktop/mobile
browser regressions were extended for v2 capture, stale slot/renderer approval,
historical rows and isolated inert previews. Local Docker execution is blocked
by socket permissions (`connect: operation not permitted`), observed once;
no local database/browser integration execution is claimed. Hosted [run 39464896834](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39464896834)
on `6c7740a95b46c7a0ba458f258f7c48de3ec47872` passed **single-site and multisite**,
including real snapshot/configuration locks and races, new v2/stale-slot/renderer/
historical assertions and **desktop/mobile Chromium** freeze/preview flows.
Protected subscriber/evidence/token bytes were unchanged; no sending enabled.
Artifacts are retained through 2026-11-03: [single](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39464896834/artifacts/12285246356),
[multi](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39464896834/artifacts/12283579697).
The subsequent numeric-host guard passes all 11 local campaign suites and syntax/
whitespace checks; its hosted rerun passed as recorded below. Saved desktop/mobile review
images are now also emitted as synthetic-only review thumbnails in CI logs.
Actual inbox/provider, real-host and assistive-technology acceptance remain open.

No credentials, real messages, production data, SDK installation, webhook,
cron activation, merge or deployment. The unconditional dispatch block and
disabled real intake/public collection are unchanged. Token issuance, attempt
binding, concrete worker/transport integration and the prior external acceptance
gaps remain dependent work. Rollback preserves immutable snapshots and hashes.


### Disposable fixture readiness follow-up

Initial PR CI [39464500155](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39464500155)
passed repository regressions/syntax on both jobs. Multisite failed during core
installation with a database connection error, before plugin integration
assertions. The existing `mysqladmin ping` health probe could report bootstrap
socket readiness without proving the fixture database/user over TCP. The
fixture now requires an authenticated TCP `SELECT 1` against `campaign_fixture`
before WordPress starts. Compose configuration and runner shell syntax validate
locally. This is disposable test readiness only; no production runtime changed.
The replacement run `39464896834` passed both topologies and desktop/mobile browser acceptance as recorded above.


### Deliverable URL normalization follow-up

Focused synthetic regressions also reject browser-normalized shortened/octal/hex
IPv4 spellings and trailing-dot hosts for both destinations and images. These
must not bypass public-address/host validation. The additional check is limited
to deliverable rendering; historical output remains byte-stable.


### Final code validation (2026-10-20)

[Run 39466069930](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39466069930)
on final code commit `3302460e94422c41110be540fff8cafd55ede3cb` passed both
**single-site** (job `118777691459`) and **multisite** (job `118777691791`). This
includes the additional numeric-host destination/image regressions, all plugin
standalone regressions and PHP/JS syntax, real WordPress/MySQL schema/service/
concurrency checks, and desktop/mobile Chromium UI checks. New assertions prove
working links remain in the capture, old/stale slot/renderer approvals fail
without writes, historical bytes/hash remain unchanged, and the empty-sandbox/
no-referrer preview has no anchors/hrefs/forms/scripts or horizontal overflow.
Clicking its CTA label does not navigate. Protected recipient/evidence/token
bytes remain unchanged; no sending enabled.

Visually inspected the synthetic `freeze-review-desktop` and
`freeze-review-mobile` thumbnails emitted in the multisite logs: retained CTA
styling, legal footer and preference/unsubscribe labels are readable at both
widths. Review screenshots show the frame scrolled to the CTA/footer after the
inert-click check. Full artifacts retained through **2026-11-03**:
[single](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39466069930/artifacts/12285102036),
[multisite](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39466069930/artifacts/12286005901).
A subsequent documentation-only evidence commit changes no tested runtime or
fixture files; no further test rerun is claimed for that documentation commit.

Unverified acceptance remains actual provider/SDK timeout/signing behavior,
real inbox HTML/text/links, deployment/host/DNS and assistive-technology checks.
No public rollout, real token issuance or transport integration is authorized
by these disposable fixtures. Remaining implementation and safety boundaries
above are unchanged. PR #1466 remains non-draft and unmerged.

### PR #1466 image-diagnostics review fix (2026-10-20)

Verified latest PR head `c2290cd8261a279ebecd08e49e987df02900a884` and preserved
the clean worktree before addressing review comment `4327983241`.
`deliverable_v2` now omits internal `image_notice` and `Image unavailable.` text
from both HTML and plain text. Review-v1 retains those diagnostics; the saved
staff review displays the exact block diagnostics separately from the frozen
model, without modifying captured email output. Actual translated alt text
remains escaped in image attributes and present in the text image description.
Article/property links, property summaries, slot contracts and all disabled
production/intake/public-collection gates are unchanged.

The focused regression failed before the fix (`article missing_image excludes
diagnostics from deliverable html`) and passes afterward. Added article and
property cases for absent image, image with unavailable translated alt and
successful translated alt; both output parts, staff diagnostics, escaping,
links/summary preservation and non-mutating staff projection are checked.
A pre-fix v2 capture containing diagnostic bytes still decodes with identical
payload/hash; existing v1 compatibility and stale review tests also pass.
No historical capture is re-rendered or cleaned on read. Changed future bytes
naturally invalidate previously issued uncaptured review proofs.

Executed with PHP 8.4.24: all 11 standalone campaign suites, syntax checks for
all three changed PHP files and `git diff --check` passed. The existing CI
single-site/multisite and desktop/mobile fixture matrix will run on this push;
its result is not yet claimed. Local Docker/proxy restrictions observed earlier
were not retried. No merge, deployment, real message, token issuance or gate
activation. Review resolution will cite the pushed fix and executed evidence;
a fresh latest-head Codex review will be requested.


## Phase 5B — bounded recipient token and URL expansion services (2026-10-20)

Phase 5 remains **in progress**. Only two unwired service files and synthetic
regressions are added. No bootstrap, route, public handler, production factory,
secret lookup, storage migration, provider, worker, header mapping or transport
wiring is introduced. Existing Phase 2 preference tokens and handlers are not
reused or reinterpreted. PeraCRM owns all implementation and fixtures.

### Token protocol, lifetime and revocation decisions

`PeraCRM_CampaignRecipientTokens` requires an explicit deployment identifier,
key ring (1–8 versioned keys, at least 32 bytes each), active key ID and trusted
revocation callback. No default, WordPress salt, environment fallback or generated
production secret exists. Deployment must independently provision high-entropy
keys and a deployment identity that changes on clone/restore into another
environment; supplying copied configuration cannot magically identify a clone.
The constructor's length check is not proof of entropy or deployment readiness.

Protocol `pc1` authenticates canonical external subscriber ID, exact frozen
mailbox identity, target blog, current token epoch, snapshot hash and attempt key
using HMAC-SHA256. Keys are derived separately for each purpose and deployment.
Only protocol/key versions, purpose, issuance/expiry times and a random 192-bit
nonce appear in the bearer, followed by the MAC. No mailbox, subscriber/site ID,
secret or encoded identity payload appears in URLs. Unknown key/purpose, altered
bytes, future issuance and wrong subscriber/mailbox/blog/deployment/attempt/hash/
epoch fail closed. Verification authenticates before invoking the callback;
only literal `true` authorizes, and callback outages deny. A future trusted
registry must locate context by token digest, check individual revocation and
current identity/epoch, and remain independent of campaign completion/cancellation.
The callback and caller-supplied clock are injected contracts, not durable
revocation, target-site authorization or a production clock implementation.

Preference access expires exactly 24 hours after issuance, with no grace or
silent extension. Withdrawal tokens have no time expiry and cannot edit topics.
They remain cryptographically verifiable after preference expiry and any campaign
lifecycle change; public behavior is dependent work. Token epochs enable scoped
identity revocation; digest lookup enables individual revocation. Neither is
persisted here. Key IDs must never be reused for different material. Rotation
issues with the new active ID and retains old keys for verification. Removing a
key deliberately invalidates its tokens, including outstanding withdrawal links;
that is emergency revocation, not routine automatic rotation cleanup.

**Activation blockers / unresolved retention:** approve withdrawal-key and digest
registry retention, mailbox change/deletion and epoch policy, compromise recovery
and an accessible alternative withdrawal path, backup/restore and clone behavior,
access-log/query redaction at every proxy/host, no-referrer/no-store public pages,
and token-bearing body/envelope retention. Do not retire withdrawal keys merely
because preference access expired or jobs completed. No finite opt-out retention
horizon has been approved. The new services produce no logs, redact debug output,
mark sensitive arguments and reject serialization; future HTTP/log infrastructure
must preserve those properties. Ordinary URL encoding is not log protection.

Unlike the specification's earlier deterministic-token proposal, this bounded
service uses independent random nonces. Repeated expansion intentionally makes
new bearer bytes; it is **not** retry reconstruction or idempotency. Before any
integration, persist/recover the exact immutable expanded output (with approved
secure retention) and digest bindings for the attempt, or separately review a
stable derivation design. Never regenerate different content for a possibly
accepted attempt. No durable attempt binding is claimed by this PR.

### Expansion and approval connection

`PeraCRM_CampaignRecipientExpansion` accepts only a configured canonical HTTPS
DNS origin with no port, credentials, path/query/fragment, control bytes, IP or
numeric host alias, trailing dot or local/internal hostname. It never reads Host,
request data, current home URL or arbitrary caller-supplied replacement URLs.
Fixed future `/email-preferences/` and `/email-unsubscribe/` paths distinguish
purposes; neither is registered. Token bytes are URL encoded, the entire HTML
attribute value is independently escaped, and text retains the raw URL.

Expansion requires schema/template 2, renderer `peracrm_email_v2`, slot version 1,
the exact declared typed slot map and the original content hash. Binding must
carry that same hash. Existing validation rejects missing/unknown/duplicate/
malformed/misplaced slots; extra raw/inert HTML contexts fail closed. Replacement
matches the entire declared anchor or standalone text line, never arbitrary
editorial content. Unsupported historical v1 captures remain rejected. There is
no re-render, live-content lookup or shared-row write. The immutable result keeps
personalization version, original approved snapshot hash, recipient-binding hash,
exact HTML/text and its own hash. Defensive copies, redacted debugging and denied
serialization protect the value; callers must handle explicit bearer-bearing
`fields()` access securely. Hash integrity is not approval/worker authority: the
future resolver must load the approved hash and identity from trusted storage.
The result is content, not a submit authorization.

### Validation checkpoint and remaining acceptance

All **22** standalone plugin regression suites (including **12** campaign suites)
passed locally with existing PHP 8.4.24, together with all first-party plugin PHP
syntax and `git diff --check`. Focused tests cover both purposes, tampering and
malformed tokens, wrong identity/site/deployment/epoch/attempt/hash, expiry
boundary, long-lived withdrawal, callback denial/outage, key rotation/removal,
missing/short secrets, unsafe origins, separate HTML/text escaping, all slot
failures, immutable copies and unchanged shared captures. Only explicit synthetic
keys and addresses are used; no credentials requested or production tokens issued.
The same shared fixture runs against actual SQL captures in the existing
single-site/multisite snapshot integration test, preserving the original and
historical rows plus existing subscriber/evidence/token no-mutation assertions.

Local Docker was checked once and denied socket access (`operation not permitted`).
No repeated attempt or local SQL/browser success is claimed. Hosted matrix
execution and final evidence will be recorded below after publication. No
end-to-end preference/one-click, provider acceptance, RFC 8058 headers/DKIM,
actual inbox, real host, deployment or retention acceptance is claimed.

Public preference/withdrawal GET and scanner HEAD must remain read-only; an
independent RFC 8058 no-login POST must transactionally record idempotent global
withdrawal before success, rechecking token/identity under the mailbox lock.
Expired preference access must expose a usable withdrawal path; retaining an
independent token is only the cryptographic prerequisite. Public handlers,
durable attempt binding/registry, worker/provider wiring, suppression sync,
events, readiness and all carried-over Phase 2–4 external acceptance stay open.
Unconditional dispatch blocking and disabled real intake/public collection are
unchanged. No real transport, messages, cron activation, merge or deployment.

### Initial hosted fixture-path correction

[Run 39472195978](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39472195978)
on `6d15045196962d93e251fa6bfdae8bed1af2c00f` passed all standalone regressions
and syntax on both jobs, then failed at the new shared-fixture include before
its SQL-backed service assertions. `wp eval-file` evaluates source and does not
preserve normal `__DIR__` semantics. The integration include now uses the mounted
`WP_PLUGIN_DIR` explicitly. This changes only the disposable fixture path; no
runtime service or prior assertion was weakened. Replacement matrix evidence
remains pending. Published initial tree `34b7d96758bac3568d74f60fb48ee1bf121d7f62`
matched the tested local tree; the original checkout remains clean and preserved.


### Final recipient-service validation and scope (2026-10-20)

[PR #1467](https://github.com/7mus7y-dotcom/pera-wp/pull/1467) is non-draft
against main. Final runtime/test commit
`d94559d7640dc6d3e3879d56056b96cd9b84148f`, full tested tree
`d7a54b6a56b8afcdbb5036bbd058d2e5b233e0e9`, passed
[run 39475440959](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39475440959):
**single-site job 118810059531** and **multisite job 118810058931**. Both complete
logs were checked: no PHP warning/fatal or failed assertion. The published tree
exactly matches the locally tested tree.

Both jobs pass all 22 standalone plugin suites (12 campaign suites), first-party
PHP syntax, JS/shell syntax and disposable Compose checks, actual WordPress/MySQL
services and independent-connection races, and existing desktop/mobile Chromium
flows. The new shared fixture runs against actual SQL captures in both
topologies, including target-blog bindings, expired preferences with still-valid
withdrawal, rotation/revocation, unsafe origins, escaping, malformed slots,
unchanged original/historical captures and protected subscriber/evidence/token
bytes. This is new service-contract evidence; existing browser preference POST
tests exercise the prior Phase 2 handler, not the new token protocol.

Final review added rejection of anchor-shaped slots in raw-text, unclosed-title,
foreign and quoted-attribute HTML contexts. It also guards constructor reentry:
PHP permits explicitly calling a public constructor again, so existing immutable
outputs and service key/deployment/origin scope now reject reinitialization.
Focused regressions pass locally and in both actual WordPress fixtures.

Artifacts retained through **2026-11-03**:
[single-site logs/screenshots](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39475440959/artifacts/12289925661)
and [multisite logs/screenshots](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39475440959/artifacts/12289347272).
No UI changed in this PR and no new manual visual/accessibility acceptance is
claimed. Earlier run 39473939002 was superseded/cancelled after the context
hardening push. The context-hardened code on `36e308c86b275bd2d2e5ce3280b8ba6800196c49`
also passed both complete jobs in run 39474541479 before the constructor guards;
the final evidence is run 39475440959 above. The initial include-path failure
remains recorded rather than presented as acceptance.

This final evidence update changes only the living plan. Runtime and fixture
bytes remain identical to the passing final-code commit; no additional test rerun
is claimed for documentation alone. Original clean checkout and local checkpoints
remain preserved. No production token issuance, public handler or worker/provider
wiring, real transport/messages, cron activation, merge or deployment. Dispatch
remains blocked and real intake/public collection disabled. No end-to-end
unsubscribe, provider or inbox acceptance is claimed. All dependent public
handler, registry, durable attempt-binding and retention decisions above remain
open; Phase 5 is **in progress**.


## Phase 5B — durable recipient revocation and immutable attempt metadata (2026-10-20)

Phase 5 remains **in progress**. All implementation is PeraCRM-owned. Schema 29
adds three InnoDB tables to the existing target-blog installer and read-only
column/engine/full-length unique-index assertions. No destructive migration,
backfill, production issuance, provider factory, transport, event intake, public
handler, dispatch activation, scheduling change or UI is introduced. The service
file is deliberately not loaded by bootstrap. Only schema definitions are loaded
through the existing installer.

### Durable identity and revocation contract

`PeraCRM_CampaignRecipientPersistence` requires an explicit deployment ID and
blog ID. Every operation requires the current context to be that configured
PeraCRM target and schema readiness. It never silently switches sites or discovers
secrets. Scope is binary-exact deployment/blog; target-site tables give an
additional multisite boundary. `ensureIdentity` serializes concurrent initial
creation and rejects a different mailbox or inactive identity. Identity rows
retain exact frozen mailbox bytes and a monotonic epoch for each subscriber.
`changeIdentity` requires the expected epoch, advances it for mailbox changes,
explicit identity revocation or deletion, and can deactivate the identity.
Returning to the former mailbox or re-enabling it never restores an old epoch.
A stale caller cannot overwrite a newer identity. These are trusted internal
operations, not automatic subscriber lifecycle hooks; connecting current subscriber
mutations is required before any issuance/public use. A replacement subscriber ID
must never inherit the prior subscriber's identity state.

The digest registry records immutable protocol `pc1`, purpose, key ID, issued and
expiry times and the canonical subscriber/mailbox/blog/epoch/attempt/snapshot
binding. It accepts SHA-256 digests, never raw bearer bytes. Same digest and exact
metadata is idempotent; any conflict rejects without replacing the original.
Individual revocation is monotonic and cannot be undone by replaying registration;
revoking an unknown digest fails closed. The cryptographic verifier now passes a
fourth, nonsecret protocol/deployment-metadata projection to the injected callback. Existing
three-argument callbacks remain compatible; this durable callback requires exact
protocol/deployment/key/time metadata as well as purpose/identity. Authentication still occurs
before callback access. `locate` is a trusted locator only, never proof of bearer
possession; no public action may follow it without cryptographic verification.
Callback database errors/identity mismatch/unknown digest/revocation deny access.
Public handlers must recheck under the authoritative mailbox lock before mutation;
a completed verification call cannot prevent a subsequent concurrent revocation.

**Routine key rotation:** issue with a new unique key ID, retain every old
withdrawal verification key and registry binding. Rotation never changes identity
epochs or removes digest rows. Do not reuse a key ID for new material, delete old
keys on preference expiry/campaign completion, or call key removal routine
rotation. Emergency key removal revokes affected withdrawal access; compromise
response and an accessible independent withdrawal path remain activation blockers.
No finite withdrawal-key/digest retention horizon has been approved. Keys remain
in injected service configuration, outside SQL and diagnostics.

**Mailbox/deletion policy:** old mailbox tokens immediately fail the current
identity/epoch check after a committed change. This deliberately denies access to
a later subscriber profile through an old mailbox link. Historical attempt rows
and their mailbox/hash bindings are retained unchanged; suppression reasons are
never cleared. Public support for withdrawing the old mailbox after an identity
change/deletion needs a separately approved policy and handler, without granting
profile/topic access to a replacement identity. Retention/deletion of historical
identity metadata remains an unresolved privacy/readiness decision.

**Restore/clone policy:** keep all gates off and provision a fresh external
deployment identity for a clone or rollback restore. Old copied digests/attempts
are inaccessible through the fresh scope. This intentionally invalidates old
bearers until independently retained revocations/epochs are reconciled. A same-
deployment restore can resurrect stale registry state; the library cannot detect
copied configuration or roll back external reality. Same-scope resume is prohibited
until post-backup revocations, cumulative suppressions, cancellation fences and
attempt/provider evidence have been unioned/reconciled under the operations
runbook. A backup alone is insufficient evidence. Unknown outcomes remain held;
new deployment scope does not authorize a resend or infer non-acceptance.

### Immutable attempt contract and dependent worker integration

The attempt table has a unique binary-exact `(deployment, blog_id, attempt_key)`.
Its immutable metadata binds the delivery key; frozen campaign/job/revision/
snapshot/recipient IDs and snapshot hash; subscriber/mailbox/epoch; personalization
version; provider/account/region/configuration set/version; independent expanded
output hash and complete submission envelope hash. The pure projection revalidates
a defensive submission copy and requires exact output HTML/text plus original
snapshot/recipient-binding hashes. The complete envelope hash also binds captured
sender/subject/reply-to/timeout without storing those values. A hash proves
integrity, not staff approval, frozen-row existence, worker lease or eligibility;
a concrete authorized resolver must obtain those facts from trusted storage.

Insert-or-compare uses database uniqueness and row locking, never overwrites
binding JSON on a duplicate. Identical replay returns the same envelope hash;
conflicting output, recipient, IDs, delivery key or provider scope fails closed.
Historical lookup remains available after identity revocation and does not inspect
campaign lifecycle or issue a retry permit. There is no attempt update/delete or
outcome-reset service. All mutations own a top-level transaction and return only
after commit; SQL/commit failure rolls back and returns a fixed unchained error.
An existing transaction or autocommit-disabled connection is rejected without
committing or rolling back the caller's work. No transport callback exists.

**Bounded split / remaining work:** this PR stores binding hashes and identity
metadata only. It deliberately does not store token-bearing bodies, encrypted
bearers or keys. The earlier random-nonce expansion is not retry reconstruction;
re-expansion for an existing attempt produces a conflicting output. Before any
transport integration, separately approve and implement secure exact-byte output
recovery (or separately reviewed stable derivation) plus key/body/token retention.
The concrete worker must persist/confirm all bindings before the first possible
transport invocation, recheck lease/owner/version/target/gates/final eligibility,
and retain unknown-outcome holds. This store is not integrated into the abstract
worker and cannot itself enforce a future caller's invocation order. Public
preferences/visible withdrawal, scanner-safe GET/HEAD and durable RFC 8058 POST
handling remain separate dependent work. No end-to-end unsubscribe is claimed.

### Diagnostics, validation and limitations

New tables contain no raw bearer, secret or message body. Services emit no audit
log or body diagnostic; rejected values are never interpolated into errors,
exception chains are discarded, sensitive arguments are marked, debug output is
redacted and serialization is denied. The existing submission constructor now
also marks its body-bearing argument sensitive. Explicit metadata/locator access
contains mailbox identity and must stay internal. Host/proxy access-log redaction,
no-store/no-referrer and approved retention/access controls remain public-activation
blockers; these internal services do not establish host logging readiness.

Local PHP 8.4.24: all 23 standalone plugin suites (13 campaign suites), first-party
PHP syntax, shell syntax and whitespace checks passed before publication. Focused
pure regressions verify exact identity/output projection, distinct hashes,
canonical order, body exclusion, serialization and redacted debug behavior.
The real WordPress/MySQL single-site/multisite fixtures exercise the actual
installer/unique constraints, identical/conflicting attempts and provider scope,
digest/version conflicts, individual/epoch revocation, stale CAS, return-to-mailbox
behavior, expiry/rotation, failed commit rollback for identity/token/attempt/
revocation, nested-transaction rejection, cross-blog/deployment isolation and
historical row preservation. Independent-process races exercise new identity,
identical/conflicting token/attempt creation and registration versus revocation,
with connection IDs and real lock-contention assertions.

Local Docker socket check failed once (`operation not permitted`); no local SQL
success is claimed and no retry was made. Shell fetch failed once (proxy
unreachable); connected GitHub provides publication and hosted CI. Hosted results
must be recorded below after execution. No provider/inbox, actual host, retention,
restore reconciliation or public-handler acceptance is claimed. Sending, real
intake and public collection remain disabled. No merge or deployment.


### Initial hosted correction and isolation hardening

Initial runtime commit `3f3024e7dfc0cc9c416f18657d42b64df434ea5a`, tree
`5aad4697472091db0187141ed61324b689c60431`, opened non-draft
[PR #1468](https://github.com/7mus7y-dotcom/pera-wp/pull/1468). Initial
[run 39485646789](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39485646789)
passed standalone regressions and syntax on both jobs. Single-site SQL caught
an incorrect literal comparison with `peracrm_get_target_blog_id()`, whose
single-site sentinel is 0. The service now permits the exact current single-site
blog while retaining explicit configured-target equality on multisite. This is
an executed failure, not SQL acceptance. No check was weakened for multisite.
A live multisite log request was not available (404); completed logs will be
read only after job completion.

The authenticated callback metadata also includes the issuer deployment, which
must match the store even when key/digest configuration was copied. Added tests
for that mismatch, registry key-version mismatch, direct identity/token SQL
uniqueness and an actual additive v28-to-v29 migration with repeat readiness.
All prior captures, identity bindings and delivery gates remain unchanged.
Replacement hosted matrix evidence follows after execution.


### Final durable-persistence validation evidence (2026-10-20)

Final runtime/test commit `a8853653956133a52f6451326a781d015745e28a`, exact tested
and published tree `822a0263ac37c01a797388c858f106b198838d21`, passed
[run 39486372802](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39486372802):
**single-site job 118847804585** and **multisite job 118847804879**. Both complete
logs were read and checked. All new persistence assertions, six independent-
process race scenarios, actual v28-to-v29 installation and prior campaign/
worker/scheduler/browser fixtures passed in both topologies. The matrix also
passed all 23 standalone plugin suites (13 campaign suites), PHP syntax, shell/
Compose configuration and existing desktop/mobile browser flows. No PHP warning,
fatal, notice or deprecated diagnostic, failed assertion or new persistence SQL
error was found.

Logs retain five pre-existing management-fixture database diagnostics in each
job: four deliberately injected nonexistent-table operations for cleanup/audit
rollback checks, plus the legacy `crm_client` linked-user-column probe on
fixture client deletion. These occur before the new persistence fixtures and do
not contain bearers/bodies/keys. They are not claimed as a clean production-host
log or changed in this bounded PR. Node/action runtime deprecation notices are
also pre-existing toolchain output. The original first-run multisite job
118845231918 passed; the initial single-site target-sentinel failure is superseded
by the fully passing replacement matrix, not hidden or counted as success.

Evidence retained through **2026-11-03**:
[single-site artifact 12293379200](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39486372802/artifacts/12293379200),
[multisite artifact 12294276430](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39486372802/artifacts/12294276430).
Browser preference POST evidence covers the existing Phase 2 protocol; it does
not establish acceptance of the new token registry or future RFC 8058 handler.
No real provider, mailbox, deployment or manual accessibility acceptance is claimed.

Codex's automatic clean review on PR #1468 covers initial commit `3f3024e7`, not
the subsequent single-site/deployment hardening in `a8853653`; final runtime
rereview remains open. The final evidence commit is documentation-only and
changes no tested runtime or fixture bytes. Publication uses connected GitHub,
with the exact runtime tree checked against the isolated worktree. The original
`/workspace/pera-wp` checkout remains clean and preserved. No merge or deployment.

Remaining retention readiness includes the existing eight-key ring capacity:
never silently discard a still-required withdrawal key to rotate again. Stop new
issuance if the configured ring cannot retain all required verification keys;
approve a key/archive strategy and withdrawal lifetime before activation. No
finite key/digest/body/identity retention horizon is approved. Public preferences/
visible withdrawal and RFC 8058 handling, current-subscriber lifecycle integration,
secure exact-byte output recovery and concrete worker/provider/transport
integration remain dependent work. Historical snapshots/hashes, cancellation
fences, cumulative suppressions and unknown-outcome holds remain unchanged.
Sending, real intake and public collection remain disabled.


## Phase 5B — subscriber lifecycle integration (2026-10-21)

Phase 5 remains **in progress**. This bounded change is confined to PeraCRM
internal lifecycle/persistence services and synthetic tests. No public handlers,
production token issuance, output recovery, worker/transport, UI, schema or
activation change is included. Dispatch remains unconditionally blocked; real
intake and public collection remain disabled.

### Supported mutation-path inventory and contract

- Staff creation/profile management: `peracrm_campaigns_save_subscriber`.
  Mailbox is immutable; only explicit client link, language and topic fields are
  accepted. A different address is a separate subscriber, with separate consent
  and cumulative mailbox suppression evaluation. There is no supported in-place
  mailbox editor, merge, ID replacement or restore endpoint. No new one is added.
- Deletion: `peracrm_campaigns_delete_subscriber` removes only the current
  profile, retaining events, suppressions, frozen recipients/captures and attempts.
  It now advances and deactivates every retained deployment identity for that
  subscriber in the same transaction. A missing subscriber is an idempotent no-op.
- Creation/recreation through staff management and
  `peracrm_subscribers_apply_row`: new IDs do not inherit old identity rows.
  The shared create participant also advances any retained rows for a reused ID,
  setting the current mailbox and active state without resetting an epoch.
  This is defensive allocator/restore handling, not permission to restore a backup.
- Import updates of existing subscribers retain their profile/evidence/identity;
  suppression import may withdraw permission but never replaces a subscriber.
  Profile/topic/language/client link/unlink, consent, staff withdrawal and
  cumulative suppression edits do not rotate durable withdrawal epochs.
- Existing Phase 2 preferences only change topics/withdrawal, never identity.
  Client deletion/link repair and CRM lead/import/identity-deduplication services
  operate on CRM posts or subscriber client links, not subscriber mailbox/ID.
  They remain separate; no sales ingestion or automatic CRM merge is introduced.

The lifecycle participant requires caller-held mailbox/subscriber locks and
executes only SQL within that transaction; it never starts, commits or rolls back
an independent operation. Staff/import retain their existing mailbox transaction
owner and audit/import-row completion semantics. The owner now rejects an
existing transaction or autocommit-disabled session before START TRANSACTION,
without ending the caller's transaction or rolling back its work. A caller may
use the internal lock/lifecycle participants within its own transaction.

Durable identity creation and token registration take the same mailbox mutex,
then verify the live subscriber ID and normalized mailbox before identity/token
locks. Deleted IDs cannot acquire a new deployment identity or register stale
tokens. Registration before deletion is invalid after deletion commits;
registration after deletion blocks then rejects. A concurrent ordinary profile
edit leaves a valid token active. Active explicit identity changes require the
live subscriber/mailbox and retain expected-epoch CAS. They are identity
revocations, not an alternate subscriber mailbox-change service.

Epoch exhaustion or any identity/subscriber/audit/commit error aborts the whole
operation. The lifecycle update covers all retained deployment scopes because a
later configuration change must not reactivate a deleted identity. It does not
alter digest metadata, historical attempts, approved hashes or frozen captures.
There is no token issuance or deployment/key provisioning in lifecycle code.

### Unresolved activation prerequisites

Old-mailbox withdrawal remains unresolved: current durable tokens deny access
after committed deletion/replacement. A separately approved mechanism must permit
any required old-mailbox withdrawal without exposing a replacement subscriber's
profile/topics. No public policy/handler acceptance is inferred from revocation.

Identity/epoch/digest/key/body retention and privacy deletion horizons remain
unapproved. Retain tombstones until an approved policy can preserve revocation;
do not purge them or reset allocators to recover access. The eight-key retention
capacity limitation from #1468 remains open. Clone/rollback restore must keep
gates off, use a fresh external deployment identity and reconcile independently
retained post-backup revocations, cumulative suppressions, cancellation fences
and attempt/provider evidence before any same-scope resume. The ID-reuse guard
does not detect a whole-database rollback or copied deployment configuration.
Restore reconciliation/runbook acceptance remains unresolved.

### Validation checkpoint and limitations

All 24 local standalone suites, first-party PHP syntax (PHP 8.4.24), shell syntax
and git diff whitespace checks passed. Focused regressions cover unchanged
profile/topic/consent epochs, deletion across deployments, audit rollback,
ID reuse and nested transaction rejection.

Real WordPress/MySQL fixtures added to both existing CI topologies cover identity,
subscriber-delete and commit failure rollback; caller-owned transactions and
participant rollback; stale epochs; absent/deleted subscriber rejection;
deletion/recreation and reused IDs; unrelated profile/import/consent/suppression
behavior; exact frozen capture and historical attempt retention. Six independent
process races assert different SQL connections and actual blocking for deletion
versus registration in both orders, profile edits versus registration in both
orders, deletion versus recreation, and new-scope creation versus a caller with an older
snapshot. Prior persistence fixtures now seed
actual subscribers rather than registering identities for nonexistent IDs.

Local Docker socket access failed once with operation not permitted; UID 1000
cannot recover the running daemon's sandbox access. No retry or local SQL success
is claimed. Hosted single/multisite execution and final evidence remain pending.
Shell proxy was blocked once; connected GitHub supplies publication and CI.
No production host, provider/inbox, public-token, old-mailbox policy, retention
or restore acceptance is claimed. No merge or deployment.


### Fixture corrections and caller-snapshot hardening

Initial [run 39552308961](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39552308961)
passed persistence assertions but failed the snapshot-only no-mutation guard:
the new real subscriber seed ran inside that boundary. The fixture now executes
after the unchanged guard. Replacement
[run 39552561017](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39552561017)
passed that boundary and all six prior persistence races in both topologies,
then caught the new lifecycle script's missing explicit import of the
deliberately unbootstrapped persistence class. Added that fixture-only import.
These failed runs are not counted as acceptance.

A further service review identified caller-owned repeatable-read snapshots:
scope discovery must use a current locking read after the mailbox lock, so it
cannot omit a deployment identity committed while the caller was waiting.
The sixth lifecycle race establishes that old snapshot on an independent
connection and verifies the newly committed scope is also deactivated.
The existing primary key begins with deployment; this bounded change adds no
schema/index, so this discovery can lock a wider identity index range.
All identity updates and caller rollback remain atomic. Hosted final evidence
follows after the replacement matrix completes.


### Final subscriber-lifecycle validation evidence (2026-10-21)

Final runtime/test commit `55d030b6eb047e1bb5af60f5f2cee7267151f6b7`,
exact local/published tree `68090bee5ebe635560dcdd3d28eeaf04b4d82999`,
passed [run 39553076167](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39553076167):
**single-site job 119066640009** and **multisite job 119066640253**.
Both completed logs were inspected for all new assertions, all six lifecycle
races, prior persistence/queue/worker/scheduler fixtures and desktop/mobile
browser completion. All passed. Each lifecycle race checks independent connection
IDs and actual blocking. The new caller-snapshot race passes in both topologies.
The matrix also passes all 24 standalone suites, PHP syntax, shell/Compose and
browser checks. No PHP warning/fatal/notice/deprecation or failed assertion was
found in these successful runs.

Each log retains the same five pre-existing management-fixture database
diagnostics documented for #1468: four deliberate nonexistent-table
fault-injection operations and one legacy CRM linked-user-column probe.
New lifecycle SQL fault-injection checks suppress their expected SQL display and
assert rollback. Existing Node/action toolchain notices remain unchanged.
The two earlier failed fixture runs are recorded above; intermediate
run 39552854550 was superseded/cancelled by the caller-snapshot hardening and is
not counted as acceptance.

Evidence retained through **2026-11-04**:
[single-site artifact 12324676856](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39553076167/artifacts/12324676856),
[multisite artifact 12324328665](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/39553076167/artifacts/12324328665).

[PR #1469](https://github.com/7mus7y-dotcom/pera-wp/pull/1469) is non-draft against
main. Initial runtime commit `2b92113` received a completed clean automated
review; subsequent fixture corrections and caller-snapshot hardening are not
claimed as covered by that review. Final rereview remains open. This final
evidence update changes documentation only; tested runtime/fixture bytes remain
exactly those of `55d030b6`. The original checkout and isolated worktree remain
clean after commits. Publication uses connected GitHub because local shell
proxy/Docker access is blocked; no blocked-tool retries were made.

Bounded lifecycle implementation and single/multisite technical validation are
complete; Phase 5 remains **in progress**. No production issuance, public handler,
output recovery, provider/worker/transport activation, new UI, merge or deployment.
Old-mailbox withdrawal policy, retention and restore reconciliation remain
unresolved activation prerequisites. Wider identity-range locking is documented
above; this change is not a production-volume performance acceptance.
