# Markdown documentation audit

> **Phase 1 only (2026-09-14).** This is an inventory and consolidation proposal. No
> existing document has been moved, rewritten, or removed. Statuses below are proposed
> Phase 2 actions and are not declarations that an old audit is current guidance.

## Method and scope

- Inventoried every repository file whose extension is `.md` or `.markdown`, including
  hidden paths, then excluded Git internals, dependency trees, generated output, and
  caches. The relevant inventory contains **95 files before this audit** and **96 files
  including this audit**. There were no `.markdown` files.
- Looked for `AGENTS.md` and `CONTRIBUTING.md` before assessing the documents. The
  repository has no project-owned copy of either. Five `CONTRIBUTING.md` files under
  `wp-content/plugins/peracrm/vendor-webpush/vendor/web-token/*/.github/` belong to
  vendored JWT packages and are deliberately excluded.
- Compared claims with the present directory layout and source entry points, and used
  Markdown Git history where age or intent mattered. In particular, the 2026-03 CRM
  migration series is implementation history, while the 2026-08/09 multilingual and
  currency documents describe newer active subsystems.
- `keep` means the file is already a useful canonical or bounded reference. `merge`
  means extract durable knowledge into the named canonical document and then remove
  the source unless its path still has discovery value. `archive` means retain it only
  as explicitly historical context. `replace with redirect` means replace its body
  with a short pointer after consolidation. `delete` means Git history is sufficient.
- Accuracy here means suitability as **current guidance**, not whether an audit was a
  faithful snapshot on the day it was written. Phase 2 should validate every extracted
  field, route, capability, hook, and command against code before calling it canonical.

## Findings

1. **The main problem is snapshot volume, not missing knowledge.** Most files are
   task-specific audits or implementation reports. Their filenames give future agents
   no reliable starting point, and related CRM guidance is split across root `docs/`,
   plugin docs, and theme docs.
2. **CRM ownership changed.** Theme-era CRM routing/templates and the later regular-plugin
   extraction coexist. Theme-era notes must be labelled historical; the active plugin
   should be the authority for routing, capabilities, identity/impersonation, writes,
   assets, UI, reminders, and push notifications.
3. **Property guidance is fragmented by rendering concern.** Query-building, V2 units,
   cards, gallery, taxonomy selection, currency, multilingual fields, and SEO are
   documented separately. Canonical docs should explain boundaries and link to the
   currency and multilingual subsystem docs rather than duplicate their contracts.
4. **Several audits record unresolved or environment-dependent conclusions.** Form mail,
   random logout, push bootstrap, live CSS, UI critiques, and security audits should not
   silently become instructions. Preserve only verified architecture, diagnostic steps,
   and known regression risks; record open questions explicitly.
5. **Some high-value operational detail is worth preserving.** Examples include rewrite
   flushing for token routes, cache-busting/no-cache behavior, CRM capability and
   effective-user rules, reminder timezone conversion, archive query parity, translation
   field classes, currency API contracts, and focused smoke-test commands.
6. **There is no dependable deployment runbook.** Existing deploy references are narrow
   notes inside portal/currency documents. A `docs/deployment.md` should be created only
   if Phase 2 can verify the actual pull/rsync/rewrite/cache procedure; this audit does
   not propose inventing one.

## File-by-file disposition

The destination column names the proposed canonical home. `Git history` means that the
file should be removed after any unique durable facts have been captured elsewhere.

| File | Domain / purpose | Status | Destination | Reasoning, conflicts, or uncertainty |
|---|---|---:|---|---|
| `MULTILINGUAL-PROPERTY-TEMPLATES-AUDIT.md` | Multilingual property templates; cleanup decision record | merge | `docs/multilingual.md` | Recent and useful for template identity/coverage, but it is a milestone snapshot and overlaps the plugin README and delta audit. Validate “current main” assertions before extraction. |
| `MULTILINGUAL-URL-ROUTING-AUDIT.md` | Multilingual visitor URL routing and remediation | merge | `docs/multilingual.md` | Detailed, recent routing evidence is valuable; issue counts and fixed/pending labels are historical and should not remain the primary entry point. |
| `docs/archive-property-ssr-query-prelude-extract.md` | Property archive SSR query snapshot | delete | `docs/property-cpt.md` (only invariants) | A long verbatim source extract becomes stale immediately. Preserve only query-builder inputs, SSR/AJAX parity constraints, and pitfalls. |
| `docs/audits/crm-client-bugs-and-nav-offcanvas-pass.md` | CRM bug-fix pass | merge | `docs/crm.md` | Keep regression risks around AJAX refresh, preferred contact, notes, reminders, portfolio, and navigation; discard per-pass narrative. |
| `docs/audits/crm-layout-nav-ajax-cleanup-pass.md` | CRM layout/navigation/AJAX follow-up | delete | Git history | Follow-up checklist is superseded by the completed pass and broader CRM/UI docs; confirm no still-open item before deletion. |
| `docs/audits/crm-layout-nav-ajax-pass.md` | CRM layout/navigation/AJAX audit | merge | `docs/crm.md` | Extract active ownership and AJAX update patterns; issue inventory is historical and overlaps the cleanup/client-bugs passes. |
| `docs/audits/crm-nav-toggle-and-logo-fallback-pass.md` | CRM navigation and header logo fix | merge | `docs/crm.md` | Preserve shell ownership and logo fallback constraint; implementation chronology is redundant. |
| `docs/audits/crm-push-subscription-bootstrap-audit-2026-03-27.md` | CRM push subscription bootstrap trace | merge | `docs/crm.md` | Useful flow and diagnostics, but overlaps two other push audits and contains date-specific observed state. Reconcile all three against current code. |
| `docs/audits/css-phase-1-normalization-report.md` | Theme CSS normalization implementation report | merge | `docs/theme.md` | Extract active load order, source-of-truth files, and safe CSS conventions; phase statistics are historical. |
| `docs/audits/css-phase-2-normalization-report.md` | Theme CSS normalization follow-up | merge | `docs/theme.md` | Same destination as Phase 1; retain selector/load-order guardrails, not the change log. |
| `docs/audits/homepage-logged-in-header-state-audit-2026-03-27.md` | Theme header/auth/cache behavior | merge | `docs/theme.md` | Valuable server-versus-JS ownership and cache warning; dated incident conclusions must be rechecked. |
| `docs/audits/off-canvas-filters-audit.md` | Property archive filter UI ownership and breakpoints | merge | `docs/property-cpt.md` | Preserve PHP/CSS/JS ownership, body-lock contract, and responsive test matrix; avoid duplicating general theme rules. |
| `docs/audits/push-panel-bootstrap-failure-audit.md` | CRM push failure investigation | merge | `docs/crm.md` | Keep the boot sequence and diagnostic ladder. Ranked likely causes are not established facts and conflict in emphasis with later push findings. |
| `docs/audits/whatsapp-integration-phase-2a-diff-report.md` | WhatsApp implementation diff | delete | `docs/integrations.md` (only unique facts) | Short change report duplicates the implementation summary; Git history is a better diff. |
| `docs/audits/whatsapp-integration-phase-2a-summary.md` | WhatsApp schema/routes/settings summary | merge | `docs/integrations.md` | Preserve architecture, schema versioning, webhook boundaries, and verification; validate route and option names. |
| `docs/citizenship-form-spam-audit.md` | Citizenship form spam and submission flow | merge | `docs/forms-and-email.md` | High-value handler/protection map and gaps, but recommendations/observations may be unresolved. Do not turn speculative protections into claims. |
| `docs/client-autoreply-email-audit.md` | Form auto-reply/mail delivery investigation | merge | `docs/forms-and-email.md` | Preserve sender ownership and diagnostic sequence. Post-SMTP-specific hypotheses are incident context, not canonical truth. |
| `docs/crm-theme-template-cleanup.md` | CRM extraction cleanup decision record | archive | `docs/archive/crm-plugin-migration.md` | Useful boundary history explaining why theme files disappeared. Keep as a concise historical section with current plugin ownership, not current work instructions. |
| `docs/crm-typography-audit.md` | CRM font loading and typography fix | merge | `docs/crm.md` | Retain asset/load-order ownership and typography tokens that remain active; CSS inventory overlaps UI system and live CSS audit. |
| `docs/crm-ui-audit-appearance-vs-layout.md` | CRM design critique and recommendations | delete | Git history | Large subjective pre-refactor critique was followed by the UI plan and implementation phases. Any still-valid principles already belong in the UI system. |
| `docs/header-logo-audit.md` | Theme header/off-canvas logo sources | merge | `docs/theme.md` | Preserve source/fallback hierarchy and cache implications; overlaps CRM header parity material. |
| `docs/live-css-baseline-consistency-audit.md` | Theme/CRM CSS loading and duplicate baseline audit | merge | `docs/theme.md`; `docs/crm.md` | Valuable ownership/load order, but exact file state is snapshot-sensitive and overlaps phase reports. Split by owner after code validation. |
| `docs/pera-currency-plugin-audit.md` | Currency architecture and implementation specification | merge | `docs/currency.md` | Extremely useful field/rendering inventory, but partly a pre-implementation spec and duplicates the later plugin README. Verify what shipped; exclude obsolete proposal text. |
| `docs/peracrm-button-pill-phase1-implementation-notes.md` | CRM controls/UI migration log | merge | `docs/crm.md` | Preserve final control semantics, selector caveats, and visual regression tests; collapse multi-phase chronology. |
| `docs/peracrm-dark-mode-audit.md` | Route-by-route CRM dark-mode audit | merge | `docs/crm.md` | Retain active dark-mode contract, tokens, and test routes. Issue counts and screenshots/observations are dated; verify whether findings were fixed. |
| `docs/peracrm-final-polish-implementation-notes.md` | CRM UI cleanup log | delete | Git history | Narrow final-pass chronology is superseded by the UI system and current CSS; extract any unique regression warning during Phase 2 review. |
| `docs/peracrm-impersonation-cleanup-pass.md` | CRM theme impersonation cleanup | merge | `docs/crm.md` | Concise evidence of single plugin ownership; combine with the authoritative identity model. |
| `docs/peracrm-impersonation-hardening-pass-2.md` | CRM impersonation read-scope hardening | merge | `docs/crm.md` | Effective-versus-acting-user rules and test checklist are important; “next pass” language is obsolete. |
| `docs/peracrm-impersonation-phase1.md` | Initial CRM impersonation implementation | delete | `docs/crm.md` (only unique rule) | Superseded by hardening, target policy, Phase 3 write-path, and verification docs. |
| `docs/peracrm-impersonation-readiness-audit.md` | Pre-implementation identity/write-path risk audit | delete | `docs/crm.md` (risk checklist only) | Large readiness plan predates implementation. Preserve the canonical identity vocabulary and any still-relevant mutation checklist, then rely on Git history. |
| `docs/peracrm-linked-properties-viewed-removal.md` | CRM relation-type removal note | merge | `docs/crm.md` | Preserve the current supported relation semantics if code confirms them; re-enable instructions are historical. |
| `docs/peracrm-next-steps-implemented.md` | CRM hardening/diagnostics implementation note | merge | `docs/crm.md` | Diagnostics commands/toggle and unauthenticated-AJAX conclusions could prevent regressions; validate names and environment safeguards. |
| `docs/peracrm-notifications-audit.md` | CRM reminders/notifications architecture | merge | `docs/crm.md` | Strong end-to-end source-of-truth map. Reconcile with plugin push docs and remove dated findings/duplicated code inventory. |
| `docs/peracrm-phase1-header-implementation-notes.md` | CRM UI refactor Phase 1 | delete | Git history | Superseded by later phases and final UI system; no need to preserve a phase-by-phase report. |
| `docs/peracrm-phase2-primitives-implementation-notes.md` | CRM UI primitives Phase 2 | merge | `docs/crm.md` | Final primitive contracts may still guide markup. Verify actual selectors; remove deferred-work chronology. |
| `docs/peracrm-phase3-client-detail-implementation-notes.md` | CRM client-detail UI Phase 3 | delete | Git history | Page order and rationale should be evident in current UI system/code; keep only non-obvious workflow constraints if still active. |
| `docs/peracrm-phase4-overview-implementation-notes.md` | CRM overview UI Phase 4 | delete | Git history | Historical implementation log with substantial overlap in the UI definition. |
| `docs/peracrm-phase5-lists-and-tasks-implementation-notes.md` | CRM list/task UI Phase 5 | merge | `docs/crm.md` | Desktop-table and responsive fallback conventions are durable if still implemented; remove phase narrative. |
| `docs/peracrm-phase6-pipeline-implementation-notes.md` | CRM pipeline UI Phase 6 | merge | `docs/crm.md` | Preserve card anatomy, stage/write behavior, and responsive test risks if current; otherwise Git history. |
| `docs/peracrm-phase7-create-edit-implementation-notes.md` | CRM form UI Phase 7 | merge | `docs/crm.md` | Form/action hierarchy and JS-sensitive markup can prevent regressions; validate against current templates. |
| `docs/peracrm-portfolio-fields-audit.md` | CRM portfolio schema/read-write pre-audit | merge | `docs/crm.md` | Important table/field/helper contract, but explicitly pre-implementation. Reconcile with implementation doc and migrations. |
| `docs/peracrm-portfolio-fields-desktop-ui-audit.md` | CRM portfolio desktop layout issue | delete | Git history | Narrow presentation investigation; retain only a current overflow regression test if still needed. |
| `docs/peracrm-portfolio-fields-implementation.md` | CRM portfolio field implementation/test notes | merge | `docs/crm.md` | Use as shipped-state counterpart to the pre-audit; verify schema and field names. |
| `docs/peracrm-portfolio-fields-mobile-ui-audit.md` | CRM portfolio mobile layout issue | delete | Git history | Narrow issue snapshot now superseded by implementation/CSS; no independent operational role. |
| `docs/peracrm-portfolio-frontend-audit.md` | Public portfolio route and asset ownership | merge | `docs/portal.md` | Despite the CRM prefix, this documents a public token route. Merge with token-page operations and distinguish it from authenticated portal routes. |
| `docs/peracrm-portfolio-panel-dropdown-removal.md` | CRM portfolio UI relation change | merge | `docs/crm.md` | Combine with relation-type and portfolio field rules; standalone note is too narrow. |
| `docs/peracrm-ui-refactor-plan.md` | CRM UI redesign plan and phased checklist | archive | `docs/archive/crm-ui-refactor.md` | Valuable design decision history, but a completed plan must not compete with current conventions. Condense rather than retain 992 lines. |
| `docs/peracrm-ui-system-definition.md` | CRM design system and page patterns | keep | `docs/crm-ui.md` (rename/move proposed) | The strongest canonical UI reference. Phase 2 should trim restated CSS/source detail, verify tokens/components, and link it from `docs/crm.md`. |
| `docs/portal-print-audit.md` | Authenticated portal print failure investigation | merge | `docs/portal.md` | Preserve print DOM/CSS constraints and a smoke test, not ranked historical root-cause guesses. |
| `docs/portfolio-token-page.md` | Public CRM portfolio token operations | merge | `docs/portal.md` | Useful data lifecycle, CLI, noindex, expiry, and rewrite-flush guidance. Clarify that public portfolios and the Pera Portal plugin are separate surfaces if code confirms. |
| `docs/property-card-v2-district-pill-audit.md` | Property card taxonomy display rule | merge | `docs/property-cpt.md` | Canonical deepest-district choice and competing helpers are valuable; standalone incident audit is too narrow. |
| `docs/quote-page-ui-audit.md` | Public tokenized quote UI critique | merge | `docs/portal.md` | Preserve route/asset ownership and functional/test constraints. Subjective recommendations and unresolved proposals should be discarded or labelled backlog. |
| `docs/random-user-logout-audit.md` | WordPress authentication incident audit | merge | `docs/operations.md` | Preserve auth lifecycle, cache/session hazards, and diagnostic checklist. Root cause was not proven, so canonical docs must retain uncertainty. |
| `docs/salesoffice/PHASE1.md` | Salesoffice plugin initial architecture/routes | replace with redirect | `docs/portal.md` | A small active feature boundary worth documenting, but `PHASE1` implies temporary state. Confirm the current plugin location/routes because inventory layout does not expose a matching top-level plugin directory. |
| `docs/single-post-seo-audit-theme.md` | Theme single-post metadata/schema audit | merge | `docs/content-seo.md` | Strong render/head ownership and validation checklist; issue findings need current-code verification. |
| `docs/step1-property-archive-query-refactor-audit.md` | Property archive query refactor inventory | merge | `docs/property-cpt.md` | Preserve canonical query helper boundaries and taxonomy/archive exceptions; phase framing is obsolete. |
| `docs/step2b-ajax-archive-shared-builder-redo-audit.md` | Property SSR/AJAX query parity fix | merge | `docs/property-cpt.md` | Valuable payload normalization and taxonomy-shape pitfall; combine with Step 1 and filter audit. |
| `docs/whatsapp-crm-integration-audit.md` | WhatsApp/CRM architecture and implementation plan | merge | `docs/integrations.md` | Detailed baseline and event-flow knowledge is useful; immediate blockers and proposals predate Phase 2A and must be reconciled. |
| `docs/whatsapp-crm-phase-0-readiness.md` | WhatsApp production-readiness/configuration assessment | merge | `docs/integrations.md` | Preserve safe prerequisites and diagnostics only. Do **not** carry forward production numbers, tokens, personal data, private URLs, or environment values. |
| `wp-content/plugins/pera-currency/README.md` | Active currency plugin contract/API/tests | replace with redirect | `docs/currency.md` | Recent, concise, and likely accurate. Make root canonical doc authoritative while leaving a short plugin-local discovery pointer. |
| `wp-content/plugins/pera-multilingual/README.md` | Active multilingual architecture/storage/routing | replace with redirect | `docs/multilingual.md` | Strong current overview; consolidate into one repository-level entry point and keep a plugin-local pointer. |
| `wp-content/plugins/pera-multilingual/docs/admin-translation-workflow.md` | Admin translation operations | merge | `docs/multilingual.md` | Durable workflow is useful and small; standalone nesting fragments discovery. |
| `wp-content/plugins/pera-multilingual/docs/internal-link-audit.md` | Multilingual property internal-link coverage | merge | `docs/multilingual.md` | Preserve current localization boundary and known gaps after verifying whether recommendations shipped. |
| `wp-content/plugins/pera-multilingual/docs/ml-prop-010-remediation.md` | One multilingual field remediation | delete | `docs/multilingual.md` (field rule only) | Narrow completed fix; keep only advisor-position classification/validation if still current. |
| `wp-content/plugins/pera-multilingual/docs/property-field-inventory.md` | Property translation field classes | merge | `docs/multilingual.md`; link from `docs/property-cpt.md` | High-value canonical field classification, but it belongs in the single multilingual guide. Verify all fields and controlled vocabularies. |
| `wp-content/plugins/pera-multilingual/docs/quick-delta-audit-2026-08-30.md` | Multilingual gap/status audit | archive | `docs/archive/multilingual-rollout.md` | Recent useful rollout context, but finding statuses were already followed by September fixes and should not be current guidance. Condense unresolved items only. |
| `wp-content/plugins/pera-portal/docs/audit-security-architecture.md` | Portal/CRM capability and bootstrap audit | merge | `docs/portal.md`; cross-link `docs/crm.md` | Security boundary and loader patterns are critical. It is an audit/proposal, so validate implemented access helpers and avoid duplicating CRM identity rules. |
| `wp-content/plugins/pera-portal/readme.md` | Active authenticated portal scaffold/setup | replace with redirect | `docs/portal.md` | Useful structure, security, shortcode, caching, and deploy integration. Consolidate centrally and retain a plugin-local pointer. The “MU-plugin scaffold” title may no longer match its regular-plugin path. |
| `wp-content/plugins/peracrm/docs/client-view-admin-audit.md` | WP-admin CRM client view architecture/security | merge | `docs/crm.md` | Detailed admin/frontend boundary and capability map is valuable; audit framing and duplicative call graph should be reduced. |
| `wp-content/plugins/peracrm/docs/frontend-timeline-details-parity.md` | CRM timeline admin/frontend parity | merge | `docs/crm.md` | Preserve shared dataset/escaping expectations and regression check; too narrow alone. |
| `wp-content/plugins/peracrm/docs/peracrm-header-visual-parity-extraction-audit.md` | CRM plugin header extraction audit | merge | `docs/crm.md` | Useful final ownership/assets/dependency detail, but overlaps the theme header audit and is mostly migration evidence. |
| `wp-content/plugins/peracrm/docs/peracrm-impersonation-overview-only-ui.md` | CRM impersonation switcher visibility | merge | `docs/crm.md` | Preserve overview-only UI rule while clarifying that impersonation state still affects allowed subroutes. |
| `wp-content/plugins/peracrm/docs/peracrm-impersonation-phase3-write-paths.md` | CRM effective-user mutation rules | merge | `docs/crm.md` | Critical current safety knowledge for reminders, notes, activity, clients, deals, and tasks. Reconcile with verification and current helpers. |
| `wp-content/plugins/peracrm/docs/peracrm-impersonation-phase3b-verification.md` | CRM deal/activity write verification | merge | `docs/crm.md` | Preserve verified owner/actor invariants and regression matrix; standalone phase label is confusing. |
| `wp-content/plugins/peracrm/docs/peracrm-impersonation-target-discovery-fix.md` | Multisite impersonation target discovery fix | merge | `docs/crm.md` | Keep multisite discovery constraint and negative tests if still current; merge with target policy. Do not preserve personal/example user details. |
| `wp-content/plugins/peracrm/docs/peracrm-impersonation-target-policy-update.md` | CRM target eligibility policy | merge | `docs/crm.md` | Canonical target rule is valuable; verify capability/role conditions and combine with discovery/write rules. |
| `wp-content/plugins/peracrm/docs/peracrm-impersonation-ui-polish.md` | CRM impersonation UI responsive fix | delete | Git history | Presentation fix is narrow; retain only overview visibility and functional advisor-filter tests in CRM guide. |
| `wp-content/plugins/peracrm/docs/phase-2g-runtime-asset-findings.md` | CRM runtime asset ownership follow-up | merge | `docs/crm.md` | Concise active ownership/load notes help prevent theme coupling; phase label should disappear. |
| `wp-content/plugins/peracrm/docs/phase-2h-theme-coupling-audit.md` | CRM standalone-plugin coupling inventory | archive | `docs/archive/crm-plugin-migration.md` | Useful extraction history and possible remaining-risk checklist. Validate which couplings remain before placing any in current CRM guidance. |
| `wp-content/plugins/peracrm/docs/push-notifications-audit.md` | CRM push architecture/failure modes | merge | `docs/crm.md` | Likely best later push reference; combine with bootstrap and notification audits into one concise subsystem section. |
| `wp-content/plugins/peracrm/docs/reminder-timezone-audit.md` | CRM reminder time conversion | merge | `docs/crm.md` | Exact parsing/UTC/display contract is high regression value; verify post-fix chain. |
| `wp-content/plugins/peracrm/docs/reminders-admin-post.md` | CRM reminder status endpoint contract | merge | `docs/crm.md` | Preserve nonce, authorization/filter, POST contract, and test path if endpoint remains active. |
| `wp-content/plugins/peracrm/docs/roles-and-caps-audit.md` | CRM capabilities follow-up | merge | `docs/crm.md` | Small but security-relevant decision; combine with access model after current-code validation. |
| `wp-content/plugins/peracrm/docs/theme-header-offcanvas-audit.md` | Theme header/off-canvas inventory for CRM parity | archive | `docs/archive/crm-plugin-migration.md` | Large source extraction/migration artifact. Preserve only decisions explaining current CRM shell; theme details belong in `docs/theme.md`. |
| `wp-content/plugins/peracrm/docs/theme-template-dependency-audit.md` | CRM theme dependency inventory before extraction | archive | `docs/archive/crm-plugin-migration.md` | Historically useful for extraction rationale, but paths and missing/present conclusions are not current architecture. |
| `wp-content/plugins/peracrm/docs/v4-hardening-notes.md` | CRM schema/stage/REST hardening | merge | `docs/crm.md` | Preserve schema migration fallback, pipeline stage behavior, REST auth example, and regression tests after verifying version relevance. |
| `wp-content/themes/hello-elementor-child/docs/crm-client-view-frontend.md` | Theme-era front-end CRM route/access quick guide | replace with redirect | `docs/crm.md` | Path has discovery value for theme workers, but ownership is now plugin-side. Redirect should explicitly warn against reintroducing theme CRM templates. |
| `wp-content/themes/hello-elementor-child/docs/crm-header-icon-size-match.md` | CRM/theme header icon visual parity | merge | `docs/crm.md` | Preserve sprite/icon sizing convention only if active; otherwise UI-level historical detail can be dropped. |
| `wp-content/themes/hello-elementor-child/docs/crm-icon-swap-audit.md` | CRM icon sprite swap | delete | Git history | Completed, tiny visual migration with no independent operational value. |
| `wp-content/themes/hello-elementor-child/docs/crm-routing-audit.md` | Historical theme-era CRM routing/404 notes | replace with redirect | `docs/crm.md` | Already labels itself historical. Replace with a concise warning/pointer so old path searches do not encourage edits to retired routing. |
| `wp-content/themes/hello-elementor-child/docs/forms-submission-audit.md` | Site-wide theme/CRM form routing and security audit | merge | `docs/forms-and-email.md`; cross-link `docs/crm.md` | Broad, valuable inventory. Split public enquiry/mail rules from CRM form endpoints and validate nonce/handler claims. |
| `wp-content/themes/hello-elementor-child/docs/property-gallery-audit-2026-04-04.md` | Property single gallery behavior/responsive issue | merge | `docs/theme.md` | Preserve gallery asset ownership, data expectations, and responsive test points; temporary test flags and critique are historical. |
| `wp-content/themes/hello-elementor-child/docs/seo-canonical-verification.md` | Property archive canonical checks | merge | `docs/content-seo.md` | Useful canonical/noindex contract and smoke URLs, but “recommended patch” must be reconciled with current implementation. |
| `wp-content/themes/hello-elementor-child/helper-duplication-audit.md` | Theme helper duplication inventory | merge | `docs/theme.md`; cross-link `docs/property-cpt.md` | Preserve source-of-truth helper boundaries and duplication warning. Evidence snapshots are stale and should not remain standalone. |
| `wp-content/themes/hello-elementor-child/inc-audit-report.md` | Theme include/load/unused-module audit | merge | `docs/theme.md` | Useful bootstrap/conditional-load conventions and debug-only caveats; exact usage proof should be re-run rather than copied. |
| `docs/markdown-audit.md` | Repository-wide Markdown inventory and Phase 2 proposal | keep | `docs/markdown-audit.md` | This Phase 1 deliverable remains the decision record until the owner approves and Phase 2 records the final before/after outcome. |

## Proposed canonical documentation map

The map intentionally separates task entry points from deep subsystem contracts. It is
a proposal only; Phase 2 should create a document only after verifying that its source
material has enough durable value.

| Canonical path | Role and intended first reader | Principal material to consolidate |
|---|---|---|
| `AGENTS.md` | Short repository-wide entry point for every Codex task | Scope boundaries; architecture map; security/no-secret rule; “do not edit vendor”; verification baseline; links below. No long subsystem detail. |
| `docs/theme.md` | First read for Hello Elementor child-theme/frontend work | Bootstrap/includes, templates and partials, CSS/JS ownership/load order, header/off-canvas, gallery, responsive conventions, shared-helper boundaries, focused checks. |
| `docs/property-cpt.md` | First read for Property CPT, archive, cards, taxonomy, and V2 unit work | Field/data invariants, query helper and SSR/AJAX parity, V2 pricing boundary, deepest-district rule, archive filters, display/content constraints, validation matrix; link to currency/multilingual rather than duplicate them. |
| `docs/portal.md` | First read for authenticated portal and public tokenized customer surfaces | Plugin/bootstrap/security boundary, routes and rewrites, assets/cache busting, portfolio/quote token lifecycle, print constraints, Salesoffice boundary, CLI/manual tests. Explicitly distinguish portal, portfolio, and CRM ownership. |
| `docs/crm.md` | First read for PeraCRM architecture or behavior | Plugin structure, routes/views/assets, capabilities, acting/effective identity, mutation ownership, schema/migrations, client/deal/task/portfolio flows, reminders/timezones/push, AJAX/REST conventions, regression checklist. Link to `docs/crm-ui.md`. |
| `docs/crm-ui.md` | Detailed UI/design-system companion, only for CRM presentation tasks | Verified final page models, primitives, tokens, dark mode, responsive and accessibility rules; condensed from the existing UI system rather than phase reports. |
| `docs/multilingual.md` | First read for translation, locale routing, or translated Property fields | Storage/routing contract, field classes, template/string registration, admin workflow, internal links, known limitations and tests. |
| `docs/currency.md` | First read for pricing conversion/display work | Canonical monetary data, V2-unit interaction, PHP/browser APIs, hydration/caching, formatting, exclusions, test commands. |
| `docs/forms-and-email.md` | First read for public forms, spam, mail, or auto-replies | Form inventory/owners, request flow, nonces and validation, CRM ingestion boundary, mail diagnostics, spam controls, safe test matrix. |
| `docs/integrations.md` | First read for WhatsApp and future external integrations | Event/webhook flow, schema/options ownership, idempotency/security boundaries, safe diagnostics; never environment values or secrets. Create only if WhatsApp remains active. |
| `docs/content-seo.md` | First read for editorial metadata, schema, canonicals, and indexing | Template/head ownership, title/description/Open Graph/schema contracts, archive canonical/noindex rules, validation URLs/tools. |
| `docs/operations.md` | First read for runtime incidents and verified operational checks | Authentication/cache diagnostics and cross-cutting runtime checks. Do not call it a deployment runbook. |
| `docs/deployment.md` | Optional verified deployment runbook | Create only after the owner/codebase supplies a current non-secret pull/rsync/cache/rewrite procedure; current Markdown is insufficient. |
| `docs/archive/crm-plugin-migration.md` | Explicit historical context, not instructions | Condensed theme-to-plugin extraction decisions and remaining verified coupling only. |
| `docs/archive/crm-ui-refactor.md` | Explicit historical design decision record | Short rationale and completed phase map; current UI rules live in `docs/crm-ui.md`. |
| `docs/archive/multilingual-rollout.md` | Explicit historical rollout context | Condensed pre-September gap baseline and only genuinely unresolved follow-ups. |

Plugin/theme-local README paths should contain only short discovery redirects when they
are likely entry points for a developer working inside that component. Canonical docs
must link back to relevant source directories so navigation works in both directions.

## Proposed removals after consolidation

The following files can be removed in Phase 2 after checking that the named canonical
documents contain their still-valid unique facts:

- `docs/archive-property-ssr-query-prelude-extract.md`
- `docs/audits/crm-layout-nav-ajax-cleanup-pass.md`
- `docs/audits/whatsapp-integration-phase-2a-diff-report.md`
- `docs/crm-ui-audit-appearance-vs-layout.md`
- `docs/peracrm-final-polish-implementation-notes.md`
- `docs/peracrm-impersonation-phase1.md`
- `docs/peracrm-impersonation-readiness-audit.md`
- `docs/peracrm-phase1-header-implementation-notes.md`
- `docs/peracrm-phase3-client-detail-implementation-notes.md`
- `docs/peracrm-phase4-overview-implementation-notes.md`
- `docs/peracrm-portfolio-fields-desktop-ui-audit.md`
- `docs/peracrm-portfolio-fields-mobile-ui-audit.md`
- `wp-content/plugins/pera-multilingual/docs/ml-prop-010-remediation.md`
- `wp-content/plugins/peracrm/docs/peracrm-impersonation-ui-polish.md`
- `wp-content/themes/hello-elementor-child/docs/crm-icon-swap-audit.md`

Git history remains the archive for these one-off reports. Phase 2 must not delete a
file merely because it appears here if current-code validation reveals an uncaptured
operational constraint.

## Files proposed for material rewrite, move, or replacement

- Create the canonical map above (except optional `docs/deployment.md`) by carefully
  consolidating the sources listed in the table.
- Condense/move `docs/peracrm-ui-system-definition.md` to `docs/crm-ui.md`.
- Condense the CRM migration, CRM refactor, and multilingual rollout history into the
  three explicitly historical archive documents.
- Replace component-local entry points with short redirects only for:
  `wp-content/plugins/pera-currency/README.md`,
  `wp-content/plugins/pera-multilingual/README.md`,
  `wp-content/plugins/pera-portal/readme.md`,
  `wp-content/themes/hello-elementor-child/docs/crm-client-view-frontend.md`, and
  `wp-content/themes/hello-elementor-child/docs/crm-routing-audit.md`.
- Replace `docs/salesoffice/PHASE1.md` with a current feature pointer only after locating
  and verifying its implementation; otherwise archive it as an unverified historical
  note rather than presenting its routes as current.
- Every other `merge` source is materially rewritten through consolidation and then
  removed, unless Phase 2 discovers a genuine external/path-stability reason for a
  redirect.

## Phase 2 verification gates

If this plan is approved, consolidation should not be considered complete until it:

1. Re-runs the case-insensitive Markdown inventory with dependency/generated exclusions
   and reports the before/after paths and counts.
2. Validates each documented source path, hook, route, capability, field, option, table,
   query variable, asset handle, shortcode, REST/AJAX/admin-post action, and WP-CLI
   command against current code.
3. Searches both code and Git history for links/references to files proposed for removal;
   retains a redirect only when that path has real navigation value.
4. Scans canonical documentation for credential-like data, production contact details,
   private URLs, tokens, and personal examples before commit.
5. Checks every relative Markdown link and heading fragment from the repository root,
   and confirms that `AGENTS.md` has exactly one obvious first-read link per domain.
6. Runs the repository's existing domain-specific automated checks documented by source
   or test configuration; where browser behavior matters, records focused manual routes
   without claiming unperformed production validation.
