# Seller and landlord property instructions

## Architecture

The existing `crm_client_property` relationship describes public WordPress property posts (enquiries, favourites and portfolios). It cannot safely represent an unpublished owner instruction without creating or overloading public listing records. This implementation leaves that relationship unchanged.

Schema **41** adds two private, target-blog-prefixed InnoDB tables through the existing locked, versioned `dbDelta` upgrade path:

- `crm_instruction_properties`: a client-owned physical asset, identifier, shared structured physical details, timestamps and revision.
- `crm_property_instructions`: a client/asset-linked sale or rental instruction, lifecycle status, advisor, structured commercial/operational details, internal notes, optional public property ID, optional Sahibinden/Pera URLs, timestamps and revision.

Physical details live once per asset; sale/rental prices, tenancy terms and workflow details belong to the instruction. Use **Reuse a property belonging to this client** when adding a second instruction for the same asset. Assets cannot be reused across owners/clients: this prevents leaking another client's private information. There is no automatic address matching, public post creation, publication, REST exposure, client pipeline advancement, payment processing or rent ledger.

The shared service lives in `inc/property-instructions.php`; the page uses `inc/views/partials/property-instructions.php` inside its existing authorized layout. Native disclosure controls expose basic creation, additional details, edit and reminder forms without requiring JavaScript. Existing CRM section, badge, form and button styles provide responsive and dark-mode behavior; the few added scoped selectors control spacing and wrapping only. The seller/landlord dropdown and public property workspace are unchanged.

Both types remain visible and editable in the instruction panel. If client type changes away from seller/landlord, the panel becomes **Property instruction history**. No instruction or advisor assignment is removed by a client-type change. Archive by editing the instruction status; reopening is a status change with a reason. There is deliberately no destructive delete endpoint.

## Security and concurrency

Reads and writes run through `peracrm_with_target_blog()` and canonical `peracrm_user_can_access_client()` checks. Mutations additionally require the actor's client-edit capability, POST and a client-bound WordPress nonce. Editing verifies the instruction/client and asset/client relationships. Advisor selection uses existing staff helpers, and the chosen advisor must be eligible and have client access. Public property links must reference an existing published `property` post. Listing URLs are optional, independently editable, validated as HTTP(S), sanitized server-side and escaped at rendering; external actions use `noopener noreferrer`.

Asset/instruction writes are transactional. Row locks and independent asset/instruction revisions reject stale forms, including when a different instruction updated the shared property. Backward moves, reopening, withdrawal, cancellation and archival require a reason. Every successful save emits `property_instruction_saved` activity including old/new statuses, reason, IDs and the real actor. Like existing CRM activity logging, activity emission follows persistence and uses the existing logger.

Reminders use the existing `peracrm_add_reminder` admin-post form, nonce, authorization, timezone parsing and storage. Client ID is prepopulated and the editable note includes the property reference, instruction ID and next operational action. Reminder ownership follows the existing client/effective-advisor rules; it is not a new instruction-specific accounting or notification system.

## Migration and rollout verification

No backfill is required and no existing property/client data is rewritten. The normal `init` schema upgrade creates both tables and verifies required columns and InnoDB before the schema marker advances to 41. The existing schema failure circuit breaker and administrator retry process apply. Verify the target blog's schema marker and the existing CRM schema status after upgrade. If an existing campaign-schema failure has opened the circuit breaker, resolve it through the existing administrator retry workflow before using instructions. Rolling back code leaves the new tables intact; do not drop them if instructions have been entered.

Manual staging checks (not run in this workspace, which has no WordPress server/database/browser fixture):

1. As administrator, open a seller client, save a name-only sale instruction, add a second, edit both optional listing URLs and verify links open safely. Repeat as the client's assigned employee and as a manager with scope.
2. Add a landlord rental, expand additional details, enter tenancy/deposit/review information, and move through the rental lifecycle. Check reason enforcement for backward moves, cancellation, archival and reopening, and inspect client activity.
3. Add a sale/rental instruction reusing the same asset. Confirm physical edits appear on both, while commercial details remain independent. Open two forms and verify stale revisions are rejected.
4. Change seller → landlord → investor and confirm all instructions, advisor assignments, history and edit forms survive. Confirm the client lead pipeline is unchanged.
5. Create a reminder from a card and confirm the client, property reference, due time and normal reminder actions. Test forged client/instruction IDs, missing/invalid nonce, invalid URLs, another employee's client and an unrelated public post ID.
6. Verify 1440px desktop and 390px mobile in light/dark mode. Check card wrapping, fields, keyboard Tab/Enter/Space on disclosure summaries, focus visibility, labels and secure links. Confirm the existing public listing count does not change.
7. On multisite, verify all reads, tables, writes and reminders use the configured CRM blog and restore the previous blog context.

## Automated checks

Run standalone PHP scripts under `tests/` using PHP 8.4 (the repository's existing test convention). New suites cover behavioral persistence/security/lifecycles, additive schema verification/failure and rendered panels/forms/links/reminder prefilling. These use WordPress/database doubles; actual `dbDelta`, InnoDB locking, browser styling and a live multisite session require the staging checks above. No PHPStan/PHPCS project configuration or WordPress database integration harness is supplied for this plugin.

Validation on 2026-10-28: all 40 standalone PeraCRM regression scripts passed (including 67 instruction service checks plus schema/render/timeline checks), PHP lint passed for all 269 non-vendor plugin PHP files, `node --check assets/frontend/crm.js` passed, and `git diff --check` passed. Live WordPress/database and browser verification remain manual as described above.
