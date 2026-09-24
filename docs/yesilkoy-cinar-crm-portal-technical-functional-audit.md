# Yeşilköy Çınar — Pera CRM and Sales Portal Technical & Functional Audit

**Audit date:** 14 September 2026  
**Scope:** `wp-content/plugins/peracrm`, `wp-content/plugins/pera-portal`  
**Target:** approximately 120 residential and retail independent units across multiple blocks  
**Method:** static, file-by-file source audit. A feature is credited only where an executable implementation was found; directory names, stub classes, and UI labels are not treated as evidence.

## 1. Bottom line

The two plugins are a **credible foundation, but not a launch-ready development-sales platform**. They already cover a useful CRM core, a working multi-building/floor SVG inventory viewer, basic centralized unit records, and frozen shareable quotations. At this scale, 120 units is not intrinsically difficult for WordPress. The problem is commercial integrity, not record count: there is no project/inventory domain, saleable-area pricing engine, basket history, payment-plan engine, atomic reservation ledger, durable broker attribution, or developer-grade sales reporting.

The recommended decision is **proceed with the existing codebase as the UI/workflow foundation, conditional on an inventory-and-commercial-domain rebuild before launch**. Do not represent today's product as having Şinpaş-style pricing or reservation control.

Key architectural conclusion:

* Portal inventory is currently the closest thing to a source of truth: `pera_unit` posts contain one mutable price and one mutable status (`available`, `reserved`, `sold`) ([unit CPT](../wp-content/plugins/pera-portal/includes/cpt/unit.php#L7), [ACF unit schema](../wp-content/plugins/pera-portal/acf-json/group_pera_unit_fields.json#L1)).
* CRM and Portal share a WordPress runtime and Portal reuses CRM access, but their business models are merely **adjacent**. Quotes accept optional CRM IDs as client-supplied strings; no service verifies those IDs, writes a CRM activity, creates a deal, or reserves a unit ([quote route](../wp-content/plugins/pera-portal/includes/rest/quote-routes.php#L12), [snapshot builder](../wp-content/plugins/pera-portal/includes/quotes/snapshot-service.php#L75)).
* A quote is genuinely frozen and is a valuable base: it stores copied plan media, sanitized SVG, source IDs, price, customer fields, issue/expiry timestamps, and a versioned JSON payload ([snapshot](../wp-content/plugins/pera-portal/includes/quotes/snapshot-service.php#L113-L160), [repository](../wp-content/plugins/pera-portal/includes/quotes/repository.php#L59-L106)). However, the advisor types the price; it is not calculated or checked against inventory ([REST arguments](../wp-content/plugins/pera-portal/includes/rest/quote-routes.php#L95-L123), [viewer form](../wp-content/plugins/pera-portal/assets/dist/portal-viewer.js#L540-L639)).

## 2. Current architecture map

### 2.1 `peracrm`

`peracrm.php` establishes constants and loads `inc/bootstrap.php`; the bootstrap is a procedural composition root for schema, roles, CPT, repositories, services, ingestion, notifications, REST, admin, and front-end routing ([plugin entry](../wp-content/plugins/peracrm/peracrm.php#L1-L40), [bootstrap](../wp-content/plugins/peracrm/inc/bootstrap.php#L7-L69)).

| Layer | Current modules | What is actually implemented |
|---|---|---|
| Party record | `inc/cpt.php`, `services/client_service.php` | Private `crm_client` CPT; identity matching primarily by email, then phone; profile stored in post meta. |
| Lifecycle | `inc/stages.php`, `repositories/party.php` | Lead stages plus engagement/disposition in `peracrm_party`; separate deal stages in `peracrm_deals`. |
| Ownership/scope | `helpers.php`, `services/client_scope_service.php`, `roles.php` | Dual legacy advisor meta keys, advisor-scoped queries, manager/admin override, roles/capabilities. |
| Work records | `repositories/notes.php`, `reminders.php`, `activity.php` | Notes, reminders, and append-only activity rows, with post-meta fallbacks for notes/reminders. |
| Property interest | `repositories/client_property.php` | Client-to-WordPress-property links for shortlist/offered relations, including copied commercial presentation fields. This is not Portal inventory linkage. |
| Deals | `repositories/deals.php` | Deal stage/value/owner/commission details tied to a party and a generic `primary_property_id`. |
| Ingestion | `integrations/enquiries.php`, `integrations/facebook-leads/*` | Theme-form capture, identity dedupe, raw context, source URL/property, default assignment, Facebook webhook mapping and idempotency claims. |
| Communications | `whatsapp.php`, notification providers, push services/log tables | WhatsApp, email and browser-push support; operational logging exists for these subsystems. |
| UI | front-end routing/data/views; WP admin pages | Overview, leads, work queue, client detail/timeline, pipeline, performance, import and logs. |

#### CRM physical data model

The schema installer creates the following custom tables ([schema](../wp-content/plugins/peracrm/inc/schema.php#L24-L153)):

* `crm_notes`: client, advisor, body, visibility, created time.
* `crm_reminders`: client, advisor, due time, status, note and notification timestamp.
* `crm_activity`: client, event type, JSON-like payload and creation time. It does **not** have a first-class actor, immutable entity version, IP, request ID, or before/after columns.
* `crm_client_property`: relation between a client and a generic property post, with snapshot-like unit type, floor, net/gross size, list/cash price, notes and plan attachment.
* `peracrm_party`: one row per CRM post with lead stage, engagement and disposition.
* `peracrm_deals`: party, title, generic property, stage, deal value/currency, owner, close dates/reason, and percent/fixed commission accounting.
* Import-batch and Facebook-lead claim tables; notification, push and WhatsApp tables are installed by subsystem installers.

Clients are `crm_client` posts rather than rows. Creation stores `crm_email`, `crm_phone`, `crm_source` and `crm_assigned_advisor`; profile/detail fields remain post meta ([client creation](../wp-content/plugins/peracrm/inc/services/client_service.php#L334-L470)). Duplicate assignment keys (`assigned_advisor_user_id` and `crm_assigned_advisor`) remain in active use ([enquiry assignment](../wp-content/plugins/peracrm/inc/integrations/enquiries.php#L219-L231)). This compatibility layer is a consistency risk.

Lead lifecycle is deliberately split from deal lifecycle. Current lead stages are new enquiry, contacted, qualified, viewing arranged, offer made, closed, and lost; deal stages are reservation taken, contract signed, payment in progress, completed, and lost ([stages](../wp-content/plugins/peracrm/inc/stages.php#L7-L33)). “Appointment”, “presentation”, and “quotation” are not first-class stage records.

### 2.2 `pera-portal`

The Portal is also procedural WordPress PHP with four CPTs, ACF/local field registration, admin tools, REST endpoints, a browser viewer, and quote snapshot services ([bootstrap](../wp-content/plugins/pera-portal/includes/bootstrap.php#L7-L39)). `PeraPortalSvgPlanService` and `PeraPortalUnitLookupService` are stubs and are not meaningful domain services ([SVG stub](../wp-content/plugins/pera-portal/includes/services/SvgPlanService.php#L7-L19), [unit stub](../wp-content/plugins/pera-portal/includes/services/UnitLookupService.php#L7-L17)).

| Object | Storage and relations | Current fields |
|---|---|---|
| Building/block | `pera_building` CPT | code and display name. There is no parent project entity. |
| Floor | `pera_floor` CPT | floor number, building post-object, SVG attachment. |
| Unit | `pera_unit` CPT | floor, unit code/type, net/gross area, one price, currency, status and unit-detail plan. |
| Quote | `pera_quote` CPT plus protected post meta | token/reference/status, issuer, source building/floor/unit, optional CRM strings, client data, frozen JSON/SVG/copied media. |

The CPTs use generic WordPress `post` capabilities rather than object-specific inventory capabilities ([building](../wp-content/plugins/pera-portal/includes/cpt/building.php#L7-L35), [floor](../wp-content/plugins/pera-portal/includes/cpt/floor.php#L7-L35), [unit](../wp-content/plugins/pera-portal/includes/cpt/unit.php#L7-L35), [quote](../wp-content/plugins/pera-portal/includes/cpt/quote.php#L7-L35)).

The Units Manager supports per-floor create/edit/delete, filters, CSV preview/commit/export, field validation, duplicate unit codes per floor, and nonces ([CSV contract](../wp-content/plugins/pera-portal/includes/admin/units-manager-page.php#L372-L584), [persistence](../wp-content/plugins/pera-portal/includes/admin/units-manager-page.php#L653-L789), [handlers](../wp-content/plugins/pera-portal/includes/admin/units-manager-page.php#L799-L1098)). This is a strong loading/maintenance base for 120 units, but lacks optimistic locking, transactions, change history and bulk project-wide controls.

## 3. Data-flow trace and source-of-truth finding

### 3.1 Enquiry to CRM

1. Theme or Facebook handlers normalize identity and source context.
2. `peracrm_ingest_enquiry()` fingerprints near-duplicate submissions, resolves/creates a client by identity, assigns the default administrator, links a generic website property when present, and logs the enquiry ([ingest orchestration](../wp-content/plugins/peracrm/inc/integrations/enquiries.php#L193-L449)).
3. Facebook adds a durable external lead-ID claim and maps form/ad context ([Facebook mapper](../wp-content/plugins/peracrm/inc/integrations/facebook-leads/mapper.php#L7-L82), [Facebook ingest](../wp-content/plugins/peracrm/inc/integrations/facebook-leads/ingest.php#L335-L482)).
4. Later notes, reminders, status changes, logins, account visits and property views populate the timeline ([activity capture](../wp-content/plugins/peracrm/inc/activity-capture.php#L7-L115), [activity repository](../wp-content/plugins/peracrm/inc/repositories/activity.php#L7-L105)).

### 3.2 Portal viewer

1. A building/floor shortcode/template emits configuration.
2. Browser code requests `/floors`, `/units` and `/floor` REST resources.
3. `/floors` traverses `pera_floor -> building`; `/units` traverses `pera_unit -> floor` and calculates display price/m² using gross size, falling back to net ([REST units](../wp-content/plugins/pera-portal/includes/rest/routes.php#L277-L377), [REST floors](../wp-content/plugins/pera-portal/includes/rest/routes.php#L379-L448)).
4. JavaScript sanitizes the returned SVG again, matches each unit by `unit_code` to the SVG element ID, adds status classes/data attributes and click handlers ([viewer](../wp-content/plugins/pera-portal/assets/dist/portal-viewer.js#L104-L155), [matching](../wp-content/plugins/pera-portal/assets/dist/portal-viewer.js#L975-L1106)).

### 3.3 Quote

1. An authenticated Portal user selects a unit but manually enters quoted price, currency, expiry and optional client/CRM context.
2. Server validates only that price is positive and expiry is future; it resolves unit/floor/building, snapshots unit metadata and plans, and stores a published `pera_quote` with an opaque token ([builder](../wp-content/plugins/pera-portal/includes/quotes/snapshot-service.php#L75-L161), [route](../wp-content/plugins/pera-portal/includes/rest/quote-routes.php#L12-L75)).
3. Public token routing renders active, expired or revoked snapshots; revocation only changes two quote meta fields ([repository status](../wp-content/plugins/pera-portal/includes/quotes/repository.php#L35-L56), [revoke](../wp-content/plugins/pera-portal/includes/rest/quote-routes.php#L77-L93)).

### 3.4 Finding

They share authentication and a database installation, **not a common sales aggregate**. There is no foreign-key-like repository joining `pera_unit`, `pera_quote`, `crm_client`, and `peracrm_deals`; no CRM reference to a quote; no quote-created activity; no reservation write; no status lock; and no synchronization event. `crm_client_property.property_id` and `peracrm_deals.primary_property_id` are generic IDs without validation that they refer to `pera_unit` ([client-property repository](../wp-content/plugins/peracrm/inc/repositories/client_property.php#L7-L173), [deals repository](../wp-content/plugins/peracrm/inc/repositories/deals.php#L236-L463)).

Therefore the answer is: **adjacent systems with some deliberate bridge fields, not a genuine end-to-end source of truth**.

## 4. Functional findings

### 4.1 CRM ingestion, ownership, activities and attribution

**Strengths.** Identity matching, submission context, raw enquiry details, source URL/property, Facebook metadata, duplicate suppression, notifications and advisor assignment are real. Advisor data uses WordPress users and scope services. Notes/reminders are advisor-stamped. Timeline capture includes operational and customer digital events. Deals already support owner and commission amount/rate/status/dates ([deal commission model](../wp-content/plugins/peracrm/inc/repositories/deals.php#L20-L158), [deal storage](../wp-content/plugins/peracrm/inc/schema.php#L100-L131)).

**Limits.** `crm_source` is a mutable string, not an attribution ledger. There is no normalized Channel, Agency, Broker/Introducer, protected registration, protection expiry, attribution precedence, ownership dispute, or attribution-change history. Source reporting derives buckets from whichever source-like value is available ([source resolver](../wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L2216-L2250)). Default web ingestion assigns the default administrator, not a routing rule/team queue ([assignment](../wp-content/plugins/peracrm/inc/integrations/enquiries.php#L198-L231), [ingestion](../wp-content/plugins/peracrm/inc/integrations/enquiries.php#L265-L449)).

Appointments and presentations can be represented only as notes/reminders or coarse stages. There is no scheduled appointment entity, attendees, outcome, no-show state, presentation event, or quotation relation. The activity table is append-oriented, but its general writer does not make a complete compliance audit trail of every CRUD mutation ([activity writer](../wp-content/plugins/peracrm/inc/repositories/activity.php#L7-L80)).

### 4.2 SVG/floor/unit selection

The working production logic is in the compiled `assets/dist/portal-viewer.js`; the source-module files are empty exports, so the build is not reproducible from checked-in source ([source entry/stubs](../wp-content/plugins/pera-portal/assets/src/js/viewer/index.js#L1-L4), [API stub](../wp-content/plugins/pera-portal/assets/src/js/viewer/api.js#L1)). This is an **architectural and maintainability risk**.

The design supports many buildings and floors. SVG element IDs must exactly equal unique unit codes. It highlights/filter-counts available/reserved/sold units, shows detail cards and derives price/m². Diagnostics validate SVG/unit consistency and sanitize active content. Missing SVGs may use a demo fixture, which is useful operationally but must never be mistaken for a sale plan ([floor SVG endpoint](../wp-content/plugins/pera-portal/includes/rest/routes.php#L129-L247), [diagnostics](../wp-content/plugins/pera-portal/includes/services/DiagnosticsService.php#L7-L390)).

Missing unit facts are balcony area, saleable pricing area, view/orientation, residential/retail class, price band, inventory hold/block reason, release phase, and canonical project. “Gross” is currently used as price/m² denominator; that must not silently stand in for contractually defined saleable area.

### 4.3 Quotation system

**What is good:** immutable-at-issuance JSON payload, issue/expiry/revocation lifecycle, opaque public link, noindex output, issuer/client/source IDs, frozen floor SVG and copied apartment plan. This can demonstrate professional quote sharing today ([quote template](../wp-content/plugins/pera-portal/templates/portal-quote.php#L1-L260), [token service](../wp-content/plugins/pera-portal/includes/quotes/token-service.php#L7-L38)).

**What is unsafe for launch:** quoted price and currency are trusted from the browser. There is no payment plan, deposit/installment schedule, discount authority, basket/version ID, list-to-achieved bridge, tax/fees, approval, acceptance, PDF artifact/hash, quote revision chain, quote number uniqueness constraint, or automatic CRM activity. A quote can be issued for a sold unit because status is only copied, not enforced. Any Portal user effectively passes quote permissions because capability helpers fall back to access itself ([capability helpers](../wp-content/plugins/pera-portal/includes/capabilities.php#L64-L101)). Revocation uses the create predicate rather than the dedicated revoke predicate ([quote route](../wp-content/plugins/pera-portal/includes/rest/quote-routes.php#L77-L93)).

### 4.4 Reporting/dashboard

CRM has a useful first-version operational dashboard: leads, qualified, junk, viewings, deals created, attention queues, cohort conversion, source distribution, stage distribution, response-speed statistics and period comparison ([performance data](../wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L2216-L2899), [view](../wp-content/plugins/peracrm/inc/views/pages/crm-performance.php#L9-L240)). Overview, work queue and pipeline lanes provide advisor operations ([overview](../wp-content/plugins/peracrm/inc/views/pages/crm-overview.php#L1-L220), [pipeline](../wp-content/plugins/peracrm/inc/views/pages/crm-pipeline.php#L1-L180)). Deal repositories expose some commission totals ([deal totals](../wp-content/plugins/peracrm/inc/repositories/deals.php#L490-L681)).

There is no Portal sales dashboard and insufficient facts to compute authoritative sold m², remaining m², achieved $/m², basket, velocity, reservations/contracts, revenue, block/type/view/floor/price-band segmentation or channel-attributed revenue. Existing CRM “viewing” and “deal” rates are cohort indicators, not the requested development funnel.

### 4.5 Security, permissions, concurrency and auditability

**Present:** CRM uses custom capabilities (`edit_crm_*`, `view_crm_reports`) and employee/manager/admin roles ([roles](../wp-content/plugins/peracrm/inc/roles.php#L7-L75)); write handlers generally enforce scope and nonce checks ([CRM actions](../wp-content/plugins/peracrm/inc/admin/actions.php#L691-L990)). Portal REST permissions and admin-post nonces exist. SVG markup removes scripts, `foreignObject`, event handlers and unsafe URL attributes ([quote SVG sanitizer](../wp-content/plugins/pera-portal/includes/quotes/media-service.php#L7-L48), [viewer sanitizer](../wp-content/plugins/pera-portal/assets/dist/portal-viewer.js#L104-L155)). Quote public access uses a high-entropy token.

**Risks:**

1. Portal inventory CPTs retain generic post capabilities and the Units Manager gates all mutations on broad “Portal access”; sales advisors can therefore potentially edit prices/statuses if they can reach the admin handler.
2. Quote create/manage/revoke helpers collapse to broad access, defeating their named granular capabilities.
3. Public external-mode floor/unit behavior needs an explicit disclosure decision. The `/units` route is registered with a broad access callback even though lower-level logic contains mode checks, creating confusing policy ([route registration](../wp-content/plugins/pera-portal/includes/rest/routes.php#L595-L660)).
4. No atomic compare-and-set reservation operation exists. Two advisors can quote or verbally reserve the same available unit; plain post-meta updates provide no conflict detection.
5. No immutable audit journal covers price, basket, unit status, reservation, attribution, deal value, commission, or overrides. WordPress post revisions are not enabled for these CPTs.
6. Custom-table relations have indexes but no foreign keys, and mixed post/meta/custom-table data makes referential cleanup and reporting harder.

## 5. Requirement-by-requirement gap analysis

Classification means: **READY NOW** can be used materially as-is; **EXISTS BUT NEEDS EXTENSION** has working implementation but lacks required depth; **MISSING** has no executable domain implementation; **ARCHITECTURAL RISK** can produce inconsistency, overclaiming, or loss.

| Requirement | Classification | Evidence and gap |
|---|---|---|
| Lead ingestion: website and Facebook | **READY NOW** | Central ingestion and Facebook claim/mapping exist ([enquiries](../wp-content/plugins/peracrm/inc/integrations/enquiries.php#L265-L449), [Facebook ingest](../wp-content/plugins/peracrm/inc/integrations/facebook-leads/ingest.php#L335-L482)). Additional connectors still need mapping. |
| Lead source/campaign attribution | **EXISTS BUT NEEDS EXTENSION** | `crm_source` and raw form/Facebook context exist, with source-bucket reporting; no normalized immutable touch/attribution model ([client service](../wp-content/plugins/peracrm/inc/services/client_service.php#L437-L470), [source report](../wp-content/plugins/peracrm/inc/frontend-data/crm-data.php#L2678-L2808)). |
| Advisor ownership and scoped working | **EXISTS BUT NEEDS EXTENSION** | Ownership, roles and scope exist; dual meta keys/default-admin routing need consolidation and assignment history ([roles](../wp-content/plugins/peracrm/inc/roles.php#L7-L75), [assignment](../wp-content/plugins/peracrm/inc/integrations/enquiries.php#L219-L231)). |
| Domestic/international direct/B2B/referral/walk-in taxonomy | **MISSING** | No controlled channel taxonomy or required dimensions; current source is free-form/meta-derived. |
| Agency, individual broker, protection period, attribution persistence | **MISSING** | No agency/broker/registration/protection entities or immutable attribution link. Generic deal commission is not broker attribution ([deal fields](../wp-content/plugins/peracrm/inc/schema.php#L100-L131)). |
| Follow-ups/reminders | **READY NOW** | Advisor-scoped due/status reminders and work queues are implemented ([reminders](../wp-content/plugins/peracrm/inc/repositories/reminders.php#L23-L149)). |
| Appointments and presentations | **EXISTS BUT NEEDS EXTENSION** | Coarse “viewing arranged” stage and generic activity/notes exist; no event/outcome entities ([stages](../wp-content/plugins/peracrm/inc/stages.php#L7-L18)). |
| Complete activity timeline | **EXISTS BUT NEEDS EXTENSION** | Timeline combines activity/notes/reminders, but not every commercial mutation is journaled and activity lacks explicit actor columns ([activity schema](../wp-content/plugins/peracrm/inc/schema.php#L68-L78), [activity capture](../wp-content/plugins/peracrm/inc/activity-capture.php#L7-L115)). |
| Contracts/sales progression | **EXISTS BUT NEEDS EXTENSION** | Deal stages include reservation, contract, payment, completed/lost; no documents, unit exclusivity, deposits, conditions or status synchronization ([deal stages](../wp-content/plugins/peracrm/inc/stages.php#L20-L33), [deals](../wp-content/plugins/peracrm/inc/repositories/deals.php#L236-L463)). |
| Multiple blocks/floors | **READY NOW** | Building and floor CPT relations and floor switching work ([floor fields](../wp-content/plugins/pera-portal/acf-json/group_pera_floor_fields.json#L1), [floors endpoint](../wp-content/plugins/pera-portal/includes/rest/routes.php#L379-L448)). Treat each block as a building; add a project parent. |
| Clickable SVG units | **READY NOW** | Unit-code-to-SVG-ID matching, sanitization, status classes and selection exist ([viewer](../wp-content/plugins/pera-portal/assets/dist/portal-viewer.js#L975-L1138)). Source-build gap remains a delivery risk. |
| Live availability | **EXISTS BUT NEEDS EXTENSION** | Viewer reads current post meta on request and no-cache behavior exists, but there is no reservation transaction/expiry/lock or event-driven invalidation ([REST units](../wp-content/plugins/pera-portal/includes/rest/routes.php#L277-L377)). |
| Available/reserved/sold/blocked | **EXISTS BUT NEEDS EXTENSION** | First three are controlled values; blocked/hold state and reason/expiry/authority are absent ([unit fields](../wp-content/plugins/pera-portal/acf-json/group_pera_unit_fields.json#L1)). |
| Plans, type, net size | **READY NOW** | Floor SVG, apartment plan, type and net size are persisted and displayed/snapshotted ([unit fields](../wp-content/plugins/pera-portal/acf-json/group_pera_unit_fields.json#L1), [snapshot](../wp-content/plugins/pera-portal/includes/quotes/snapshot-service.php#L120-L136)). |
| Balcony, saleable pricing area, view/orientation | **MISSING** | No fields or API payload members. |
| Current USD price and current price/m² | **EXISTS BUT NEEDS EXTENSION** | One price/currency and derived price/m² exist; denominator is gross/net, not saleable area, and currency is not project-enforced ([REST calculation](../wp-content/plugins/pera-portal/includes/rest/routes.php#L319-L370)). |
| Project base price/unit multipliers/current basket | **MISSING** | No project CPT, price rule, multiplier or basket object. |
| m²-sold basket transitions and scarcity stock | **MISSING** | No saleable-area ledger, thresholds, transition engine, approvals or individual scarcity policy. |
| Historical price snapshots/management override audit | **ARCHITECTURAL RISK** | Quotes freeze manually supplied price, but mutable unit price has no history and no authority/override trail ([quote snapshot](../wp-content/plugins/pera-portal/includes/quotes/snapshot-service.php#L103-L150)). |
| Centrally calculated cash/12m/24m plans | **MISSING** | No payment-plan schema/calculator/version; quote accepts a single browser-entered number. |
| Original quote/reservation price | **EXISTS BUT NEEDS EXTENSION** | Quote payload freezes price; no reservation price snapshot, price-rule inputs or revision/acceptance relation ([repository](../wp-content/plugins/pera-portal/includes/quotes/repository.php#L73-L104)). |
| Quote generation/share/expiry | **EXISTS BUT NEEDS EXTENSION** | Strong snapshot/token base, but no authoritative server calculation, approval, CRM event or reservation guard. |
| One inventory/pricing/payment/reservation source | **ARCHITECTURAL RISK** | Inventory is one set of Portal posts, but price/status can be edited broadly and CRM deals/quotes do not enforce it. Pricing, plan and reservation sources do not exist. |
| Sales developer KPIs | **MISSING** | No authoritative inventory-sales fact model. Deal value alone cannot produce m²/basket/achieved-segment measures. |
| Pipeline/conversion KPIs | **EXISTS BUT NEEDS EXTENSION** | First-version lead/source/stage/cohort dashboard exists, but required appointment/presentation/quote/reservation/contract events are incomplete ([performance view](../wp-content/plugins/peracrm/inc/views/pages/crm-performance.php#L9-L240)). |
| Channel/advisor performance | **EXISTS BUT NEEDS EXTENSION** | Source and scoped cohorts exist; normalized channel, attribution persistence and revenue join are absent. |
| Inventory segment/velocity reporting | **MISSING** | Required attributes and sale/reservation event facts are absent. |
| Multi-advisor permissions | **ARCHITECTURAL RISK** | CRM is reasonably scoped, Portal is broad and its named quote capabilities are effectively bypassed by generic access ([Portal capabilities](../wp-content/plugins/pera-portal/includes/capabilities.php#L7-L101)). |
| Commercial audit trail | **MISSING** | Operational activity/log tables do not constitute a price/status/attribution/reservation override journal. |

## A. Executive assessment

**Yes, conditionally:** the software is fundamentally suitable as a foundation because it has working CRM operations, WordPress users/roles, normalized-enough custom CRM tables, an inventory viewer, bulk unit maintenance, and snapshot quotes. A 120-unit scheme fits the technology comfortably.

**No, as currently implemented:** it is not safe to operate the complete Yeşilköy Çınar sales process. Before any launch promise, build the authoritative project/unit commercial model, server-side pricing/payment engine, reservation transaction, durable attribution, integration events and reporting facts. Those are central domain capabilities, not cosmetic extensions.

Suggested proposal wording: “Pera already has an operational CRM and interactive sales-office foundation; Yeşilköy Çınar requires a project-specific commercial-control layer and developer reporting phase before go-live.”

## B. Existing strengths demonstrable today

1. Website/Facebook lead capture with duplicate controls, source context and notifications.
2. Client profiles, advisor ownership, notes, reminders, work queues, pipeline and activity timeline.
3. Manager/employee CRM separation and advisor-scoped client access.
4. Deal stages, values, owners and basic expected/received commission administration.
5. Multiple buildings and floors, sortable floor navigation, safe clickable SVGs and live reads of unit status.
6. Unit type/net/gross size, plan, mutable price/currency and three-state availability.
7. CSV preview/validation/import/export for unit loading.
8. Shareable, expiring, revocable, frozen quote pages with plans and identifiable issuer/client.
9. Basic operational CRM dashboards: response speed, lead stages, source cohorts, conversion and attention queues.

Demonstrations must explicitly label the quote price as manual and availability as non-locking.

## C. Critical gaps before launch

1. **Canonical project/inventory model:** project, block, floor, unit, saleable area, balcony, orientation/view, category, release state and globally unique unit identity.
2. **Commercial pricing engine:** versioned base price/rate, unit factors/adjustments, basket schedule, sold-m² basis, controlled transition and scarcity override.
3. **Server-authoritative quote calculator:** select plan; never accept final price from the browser; store every formula input/version and output.
4. **Reservation ledger:** atomic state transition, client/unit/quote/deal links, deposit/expiry/cancellation, automatic expiry and concurrency conflict handling.
5. **Durable attribution:** normalized channel, agency, broker/introducer, registration/protection rules, assignment history and commission basis tied through sale.
6. **CRM/Portal application service:** quote/reservation/contract/sale must generate linked CRM activities/deals and inventory events in one controlled workflow.
7. **Granular permissions:** view inventory, quote, discount within band, approve override, reserve, release, mark contracted/sold, edit master price and report access.
8. **Immutable audit journal:** actor, timestamp, entity, action, before/after, reason, approval, request/correlation ID.
9. **Developer data mart/dashboard:** authoritative sale/reservation facts joined to unit dimensions and attribution.
10. **Build/release repair:** restore real viewer source modules and reproduce `dist`; add automated domain/integration/concurrency tests.

## D. Nice-to-have improvements

* E-signature/document packs, automated contract generation and payment reminders.
* Broker self-service registration and availability access with watermarked/expiring price lists.
* Multilingual quote templates and configurable branding.
* PDF archival with cryptographic hash, quote compare/revision UI and electronic acceptance.
* Forecasting, lead scoring, anomaly alerts, velocity projections and basket-transition simulations.
* BI export/API, scheduled developer email packs and warehouse replication.
* Rich appointment calendar integration and presentation attendance/outcomes.

## E. Recommended architecture

### E.1 Ownership boundaries

Keep one WordPress installation initially, but establish explicit modules:

* **CRM owns:** Party, contact identity, advisor assignment, activities, appointments, attribution/protection, agency/broker and pipeline.
* **Inventory owns:** Project/block/floor/unit master data and the current state machine.
* **Pricing owns:** immutable price-book versions, unit factors, baskets, plan versions and evaluated price snapshots.
* **Sales owns:** quote, reservation, contract and sale aggregates linked by integer IDs.
* **Reporting reads:** append-only commercial events or a reporting fact table; it must not reverse-engineer truth from arbitrary current post meta.

### E.2 Price evaluation

Use integer minor currency units or documented `DECIMAL`, never binary floats for persisted money. A server-side calculator should receive `unit_id`, `payment_plan_version_id`, effective time and actor. It loads:

`saleable_area × project_base_usd_per_m² × basket_multiplier × unit_multiplier + unit_adjustments`, then applies the selected plan adjustment (illustratively cash 0%, 12m +5%, 24m +8%). The exact compounding/rounding order is a business decision.

Return a signed/versioned breakdown. Quotes store the unit master snapshot, price-book/basket/plan IDs, every input factor, pre/post-adjustment totals, $/m² and rounding. Manual overrides are a separate delta requiring reason and authority; never overwrite the calculated price.

Basket progression should derive from completed/contracted saleable m² according to the approved policy—not mutable status alone—and propose or execute a transition idempotently. Transitions produce immutable snapshots and cannot retrospectively alter issued quotes/reservations.

### E.3 Inventory state machine

Recommended states: `unreleased`, `available`, `held/blocked`, `reserved`, `contracted`, `sold`, `withdrawn`. Each transition has allowed roles, required reason/data and expected prior version. A dedicated reservation table should have a unique active-unit constraint (or a transactional locking equivalent). Status is a projection of authoritative state/events, not an independently edited label.

### E.4 Integration flow

`Lead -> qualified party -> selected unit -> server price preview -> issued quote -> atomic reservation -> contract -> completed sale`.

Each command writes its aggregate plus an outbox/audit event within the same database transaction. Event consumers update CRM timeline, pipeline projections, notifications and reporting. Quotes/reservations store actual integer foreign IDs, validated types and denormalized snapshots for history.

### E.5 Attribution

Create controlled `channel`, `agency`, `broker` and `client_attribution` records. The attribution row includes introduced/registered time, protection start/end, status, evidence, source/campaign, advisor, priority rule and changes. A sale copies the winning attribution ID and commission rule snapshot. Later contact updates must not overwrite first/winning attribution; conflicts require an audited decision.

## F. Implementation phases

### Phase 0 — audit / cleanup

* Confirm production schema/data volumes, multisite blog ownership, ACF behavior and duplicate advisor keys.
* Back up, inventory orphan relations/duplicate unit codes, and reconcile current status/price spreadsheets.
* Recover Portal JS sources from `dist`; add build manifest and CI.
* Threat-model public endpoints/tokens; split capabilities; establish test fixtures and acceptance definitions.
* Freeze canonical terminology and IDs with the developer.

**Exit:** reconciled data dictionary, reproducible build, permission matrix, migration/rollback plan.

### Phase 1 — inventory and unit data

* Add project parent and required dimensions/areas.
* Introduce inventory repositories/API DTOs and a state-transition service.
* Extend CSV import with project-wide preview, external IDs, area validation and audit events.
* Update viewer/detail/diagnostics for block, retail, balcony, saleable area, view and blocked/unreleased states.

**Exit:** all ~120 units reconcile to signed developer schedule and SVGs; one read source.

### Phase 2 — pricing / baskets / payment plans

* Versioned price books, basket thresholds, unit factors, plan rules and calculator.
* Server-only calculation and override approval/audit.
* Quote v2 snapshots/revisions using calculated results.
* Simulation and transition approval screen; historical tests for boundary percentages and rounding.

**Exit:** sample units reproduce approved commercial examples exactly; issued quotes remain reproducible after basket changes.

### Phase 3 — attribution / B2B / reservation workflow

* Agency/broker/channel/protection schemas and UI.
* Appointment/presentation/quote activities and durable CRM links.
* Atomic holds/reservations, expiry jobs, deposits, cancellation/release and contract/sale transitions.
* Commission-rule snapshot and attribution dispute/override workflow.

**Exit:** simultaneous reservation tests prevent double sale; broker attribution survives the full lifecycle.

### Phase 4 — developer dashboards and reporting

* Sales, pipeline, channel/advisor and inventory facts/dimensions.
* Date/effective-time definitions, currency treatment and reconciliation report.
* Dashboard/export filters by project/block/type/view/floor/price/channel/advisor.

**Exit:** dashboard totals tie to inventory, reservations, contracts and finance-approved revenue.

### Phase 5 — polish / automation

* PDFs/e-sign, scheduled reports, alerts, broker access, calendar/automation and UX/performance/accessibility work.
* Load, security, disaster-recovery and operational runbook exercises.

## G. File-level implementation map

### Extend in `pera-portal`

| Existing location | Recommended change |
|---|---|
| `includes/cpt/building.php`, `floor.php`, `unit.php` | Add project relation/dimensions and dedicated capabilities; retain CPTs as editorial master records if desired. |
| `acf-json/group_pera_*` and `includes/acf/fields.php` | Add project/block/unit fields, but do not put transactional history or price-book rows in unversioned post meta. |
| `includes/admin/units-manager-page.php` | Extend importer/manager; route mutations through inventory service, version checks and audit journal. |
| `includes/rest/routes.php` | Return stable DTOs from repositories; separate internal/public disclosure; add ETag/version; never expose unapproved prices. |
| `assets/src/js/viewer/*` | Replace stubs with maintainable source matching `dist`; add states/details and generated API client. |
| `includes/quotes/snapshot-service.php`, `repository.php`, `rest/quote-routes.php` | Quote v2 via pricing application service, validated CRM relation, revision/approval and timeline event. |
| `includes/capabilities.php` and CPT registration | Make named granular capabilities effective and use `edit_*`/`read_*`/transition checks. |

### Add under `pera-portal`

* `includes/domain/inventory/` — state machine, repository, transitions, concurrency/versioning.
* `includes/domain/pricing/` — price books, baskets, unit adjustments, calculator, rounding policy.
* `includes/domain/payment-plans/` — plan versions and installment schedule evaluator.
* `includes/domain/reservations/` — commands/repository/expiry and unit lock.
* `includes/db/` — normalized commercial, reservation, audit and outbox tables/migrations.
* `includes/admin/pricing/`, `admin/reservations/`, `admin/audit/` — controlled management screens.
* `includes/reporting/` — inventory/sales facts and reconciliation queries.

### Extend in `peracrm`

| Existing location | Recommended change |
|---|---|
| `inc/schema.php` | Add agency, broker, attribution/protection, appointment and integration/outbox schema migrations. |
| `inc/services/client_service.php`, `integrations/enquiries.php` | Preserve first/winning attribution, implement routing rules and stop writing divergent ownership keys. |
| `inc/repositories/activity.php`, `services/activity_service.php` | Add commercial event adapters and explicit actor/correlation metadata. |
| `inc/repositories/deals.php` | Replace generic property ambiguity with validated unit/reservation/contract links; snapshot attribution/commission rule. |
| `inc/repositories/client_property.php` | Either formally bridge Portal units with typed relations or keep it clearly limited to marketing-property portfolios. |
| `inc/stages.php` | Align projections with appointment/presentation/quote/reservation/contract events; avoid using a stage as the event record. |
| `inc/frontend-data/crm-data.php` and performance views | Add project/channel/advisor funnel measures from authoritative facts, not mutable source strings. |
| `inc/roles.php`, `client_scope_service.php` | Implement approved project/team and commercial authority matrix. |

Prefer a small shared application/API layer over either plugin calling the other's procedural internals directly. If deployment remains monolithic, it can be a shared `pera-sales-core` plugin loaded before both; if channels later become external, expose authenticated APIs backed by the same services—not duplicated storage.

## H. Questions requiring business decisions

1. What precisely is “saleable pricing area”: gross, net plus weighted balcony, or a developer-issued contractual area? What weighting/rounding applies?
2. What event counts as “sold m²” for basket movement: reservation, deposit received, contract, a payment threshold, or completed sale? What happens on cancellation?
3. Are basket transitions automatic, proposed for approval, or manual? Who can approve, backdate or pause them?
4. Exact basket bands/multipliers, treatment at threshold equality, launch inventory, and scarcity-stock rules?
5. Is unit multiplier applied before or after basket multiplier and adjustments? Are there fixed premiums as well as percentages?
6. Are 5%/8% plan uplifts applied to full unit price or financed balance? What are installment dates, rounding/residual rules, taxes/fees and currencies?
7. Quote validity, grace rules after basket transitions, revision policy and whether a valid quote protects price or inventory?
8. Hold/reservation duration, required deposit, approval, extension limits, cancellation/refund rules and blocked-unit reasons?
9. Which contract/payment milestone changes a unit to contracted/sold and recognizes revenue?
10. Can a buyer reserve multiple units; can joint buyers, companies or multiple brokers participate?
11. Attribution precedence among first touch, broker registration, campaign, walk-in and later direct interaction? Can attribution be split?
12. Agency/broker protection duration, renewals, duplicate registrations, evidence/dispute process and geographic/project scope?
13. Commission basis: achieved price before/after VAT/discount/fees, collected cash, fixed/percent/tier, currency and clawbacks?
14. Who may see price, client identity and commission; who may quote, discount, override, reserve, release, contract and mark sold?
15. Which inventory/pricing facts may be public or broker-visible, and should feeds/watermarked price lists expire?
16. Required source/campaign hierarchy and integrations beyond website/Facebook (phone, WhatsApp, walk-in, portals, broker uploads)?
17. Developer reporting timezone, currency conversion policy, revenue definition, cancellation restatement and daily cut-off?
18. Required developer export/API cadence, signed-off dashboard layouts and record-retention/privacy obligations?
19. Is Yeşilköy Çınar one legal/commercial project with several blocks, and will the platform need multiple simultaneous developments?
20. Who is the business owner authorized to sign off inventory reconciliation, formulas, permissions and go-live readiness?

## 6. Acceptance gates before making a launch promise

* Every unit and every SVG region reconciles against the signed developer inventory schedule.
* The same server calculation drives viewer, quote, reservation and contract; browser-supplied totals are rejected.
* Historical quote reproduction remains exact after price book/basket/plan changes.
* Concurrent reservation tests prove one active reservation per unit.
* Every price/status/attribution/override transition identifies actor, reason, time and before/after state.
* CRM timeline and developer reports reconcile to the same quote/reservation/contract/sale IDs.
* Role tests demonstrate advisors cannot edit master price/status or approve their own out-of-band overrides.
* Dashboard sold unit/m²/revenue totals reconcile to an exportable transaction register.

Until these gates pass, spreadsheets may be used only as controlled import/reconciliation artifacts, never as operational sources of truth.
