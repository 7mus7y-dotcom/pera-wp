# Pera Portal Phase 2 Spec — Units Manager, CSV Workflow, and Mapping Diagnostics

## 1. Executive summary

Phase 2 should **retain the existing CPT + postmeta/ACF architecture** and layer a focused admin tool on top, rather than replacing storage. The current plugin already models building/floor/unit as CPTs (`pera_building`, `pera_floor`, `pera_unit`), and units are queried through `floor` meta and exposed via stable REST payload keys used by the portal frontend. Changing the persistence model now would create unnecessary risk for the viewer and quote flow.

A **custom Units Manager screen** is the right next layer because current editing is post-by-post while operational usage needs floor-level tabular management (bulk status/price, plan visibility, fast correction). This can be implemented as an admin UI that still writes to `pera_unit` and existing meta fields, preserving runtime contracts.

Validation/diagnostics should ship **before or with CSV import** because frontend interactivity depends on `unit_code` mapping to SVG element IDs (`#unit_code` query in viewer). CSV without diagnostics risks silent mapping breakage and degraded sales workflows.

---

## 2. Canonical data model for Phase 2

### 2.1 Canonical storage decision
- Canonical source remains: **`pera_unit` CPT + existing meta/ACF fields**.
- No custom tables in Phase 2.
- Units Manager must read/write the same fields consumed by REST and quote snapshot logic.

### 2.2 Canonical unit schema (Phase 2)

#### Required fields (hard-required at save/import preflight)
1. `unit_code` (string)
   - non-empty, trimmed
   - allowed chars: `A-Z a-z 0-9 _ -` (recommend normalization to preserve SVG id safety)
2. `floor` (integer post ID -> `pera_floor`)
3. `status` (enum): `available | reserved | sold`
4. `currency` (enum): `GBP | EUR | USD | TRY` (Phase 2 uses current choices)

#### Optional business fields
- `unit_type` (string)
- `net_size` (number >= 0)
- `gross_size` (number >= 0)
- `price` (number >= 0; allow blank when unknown)
- `unit_detail_plan` (attachment; canonical as attachment id internally, URL exposed downstream)
- `title` (post title; optional display/helper)

#### Recommended Phase 2 additions (safe/admin-only helpers)
- `sort_order` (integer, default `0`) — admin grid ordering helper only.
- `last_validated_at` (datetime string) — diagnostics UX aid.
- `validation_state` (enum `ok|warning|error`) — cached indicator for quick list filtering.
> These helpers are additive and must not alter frontend response field names.

### 2.3 Defaults
- `status`: `available` (matches current fallback behavior)
- `currency`: `GBP` when blank/invalid (matches current REST fallback)
- `sort_order`: `0`
- sizes/price: null when blank

### 2.4 Uniqueness expectations
- Enforce uniqueness at application level by **`(floor_id, unit_code)`**.
- Same `unit_code` can exist on different floors.
- Duplicate `(floor, unit_code)`:
  - block CSV commit
  - block normal save unless explicit admin override mode enabled (override should be disabled by default)

### 2.5 Backward-compatibility fields that must keep names
Must preserve existing REST keys and semantics:
- `unit_code`, `unit_type`, `net_size`, `gross_size`, `price`, `currency`, `status`
- `detail_plan_url`, `detail_plan_filename`, `detail_plan_mime` (derived from `unit_detail_plan`)

---

## 3. Units Manager admin UX specification

### 3.1 Location and relationship to CPT screens
- New admin page: **Pera Portal → Units Manager**
- URL: `admin.php?page=pera-portal-units-manager`
- Existing `pera_unit` edit/add screens remain available as fallback and for edge-case debugging.

### 3.2 Page structure

#### Top toolbar (left to right)
1. Building filter (required for meaningful floor scoping)
2. Floor filter (dependent on building; required before grid loads)
3. Search box (`unit_code` contains)
4. Status filter (`all|available|reserved|sold`)
5. Buttons:
   - `Add row`
   - `Save changes`
   - `Bulk status update`
   - `Bulk price update`
   - `Import CSV`
   - `Export CSV`
   - `Run validation / diagnostics`

#### Grid behavior
- Default sort: `sort_order ASC`, then `unit_code ASC`.
- Pagination: 50 rows/page (options 25/50/100).
- Sticky header and dirty-row indicators.
- Row selection via checkbox for bulk actions.

### 3.3 Editable columns
Inline editable:
- `unit_code` (text)
- `unit_type` (text)
- `net_size` (number)
- `gross_size` (number)
- `price` (number)
- `currency` (select)
- `status` (select)
- `sort_order` (number, admin helper)

Read-only display columns:
- `unit_id`
- `floor`
- `building`
- validation badge
- plan preview state

Plan column interactions:
- `Upload/Replace` opens WP media modal
- `Preview` opens media in new tab/modal
- `Remove` clears attachment

### 3.4 Row actions
- `Open CPT` (edit post in classic screen)
- `Duplicate row` (new unsaved row with copied fields, blank `unit_code`)
- `Delete unit` (soft confirmation)

### 3.5 Bulk actions
- Bulk status set
- Bulk price adjust:
  - set absolute value
  - add/subtract fixed amount
  - percentage adjust
- Bulk currency set
- Bulk clear plan (optional, confirm-required)

### 3.6 Save behavior
- Two-phase save:
  1. Client validation + server preflight
  2. Commit only valid rows; invalid rows remain unsaved and highlighted
- Save summary:
  - updated count
  - created count
  - failed count with downloadable error CSV/JSON

### 3.7 Warning/error display
- Per-row badge:
  - red = blocking error
  - amber = warning
  - blue = info
- Inline cell messages for exact field issue.
- Page-level diagnostics panel with grouped counts and “jump to row”.

---

## 4. Validation and diagnostics specification

### 4.1 Diagnostic rules

1. **DUPLICATE_UNIT_CODE_ON_FLOOR**  
   - Severity: **error**  
   - Check: duplicate `unit_code` among units sharing same `floor`  
   - Action: rename code or merge duplicate units

2. **UNIT_CODE_MISSING_IN_SVG**  
   - Severity: **error**  
   - Check: unit exists but no matching SVG element `id="{unit_code}"` in selected floor SVG  
   - Action: fix `unit_code` or update SVG IDs  
   - Basis: viewer uses `svg.querySelector('#' + CSS.escape(unit.unit_code))`.

3. **SVG_ID_WITHOUT_UNIT_RECORD**  
   - Severity: **warning**  
   - Check: interactive SVG IDs detected with no corresponding unit record  
   - Action: create missing unit or remove stray SVG ID

4. **REQUIRED_FIELDS_MISSING**  
   - Severity: **error**  
   - Check: blank `unit_code`, missing `floor`, invalid/missing `status`, invalid/missing `currency`  
   - Action: fill required fields

5. **INVALID_STATUS_VALUE**  
   - Severity: **error**  
   - Check: status outside `available|reserved|sold`  
   - Action: normalize to valid enum (same as runtime expectations)

6. **INVALID_OR_MISSING_CURRENCY**  
   - Severity: **warning** (error during CSV commit if policy set strict)  
   - Check: currency not in allowed list or blank  
   - Action: set GBP/EUR/USD/TRY

7. **MISSING_UNIT_PLAN**  
   - Severity: **warning**  
   - Check: `unit_detail_plan` absent  
   - Action: upload plan (important for quote completeness warnings)

8. **FLOOR_MISSING_SVG_UPLOAD**  
   - Severity: **error** for sales readiness, **warning** in non-prod if fixture fallback allowed  
   - Check: no uploaded floor SVG  
   - Action: upload SVG to floor  
   - Basis: runtime fallback exists but should not be relied on operationally.

### 4.2 Validation run points
- Manual: `Run diagnostics` button
- Auto preflight before:
  - Save changes
  - CSV import commit
- Post-save: background re-check on changed rows

### 4.3 Result presentation
- Page summary card: counts by severity + rule.
- Per-row flags with expandable detail.
- Exportable report (CSV + JSON) including:
  - timestamp
  - building/floor context
  - row key (`floor_id + unit_code`)
  - rule code
  - severity
  - recommendation

### 4.4 Blocking policy
- **Block commit** for errors (default).
- Warnings do not block but require explicit acknowledgment on import/save dialog.
- Override mode (future/admin capability gated) can bypass selected errors, but not duplicates by default.

---

## 5. CSV import/export specification

### 5.1 Scope and UX
- Day-one CSV flow is **contextual to selected floor** in Units Manager.
- Building/floor IDs are not required in each row for initial UX simplicity.
- Context banner: “Importing into Building X / Floor Y”.

### 5.2 Matching key and upsert behavior
- Stable key: **`floor_id (from page context) + unit_code (from row)`**
- Behavior:
  - existing key -> update row
  - missing key -> create new unit in selected floor
- Duplicate `unit_code` rows in file:
  - preflight error, import blocked

### 5.3 CSV columns

#### Required columns
- `unit_code`
- `status`
- `currency`

#### Optional columns
- `unit_type`
- `net_size`
- `gross_size`
- `price`
- `plan_file` (optional placeholder; no auto-match commit in Phase 2)
- `sort_order`

#### Context columns (optional for export compatibility, ignored on scoped import)
- `building_id`
- `floor_id`

### 5.4 Preflight and dry-run
- Import flow:
  1. Upload CSV
  2. Parse + normalize
  3. Diagnostics preflight
  4. Dry-run summary
  5. Confirm commit
- Dry-run output:
  - create/update/skip counts
  - normalized values
  - row-level errors/warnings

### 5.5 Parsing/normalization rules
- Blank values:
  - required fields blank => error
  - optional numeric blanks => null
- Status normalization:
  - case-insensitive map (`AVAILABLE` → `available`, etc.)
  - unknown => error
- Currency normalization:
  - uppercase trim
  - aliases (e.g., `TL` -> `TRY`) optional mapping table
  - unknown => warning/error per strictness
- Numeric parsing:
  - strip commas/spaces
  - decimal point only
  - invalid numeric => error for edited field
- Unsupported columns:
  - ignored with warning list in preflight

### 5.6 Export schema
- Export only selected floor by default.
- Columns exported in stable order:
  `unit_code,unit_type,net_size,gross_size,price,currency,status,sort_order,detail_plan_url`
- Optional “include ids” toggle adds `unit_id,floor_id,building_id`.

### 5.7 Sample CSV
```csv
unit_code,unit_type,net_size,gross_size,price,currency,status,sort_order
A-101,1+1,62.5,78.0,185000,GBP,available,10
A-102,2+1,81,98,235000,GBP,reserved,20
A-103,Studio,45,57,,GBP,sold,30
```

---

## 6. Apartment plan media model

- Canonical storage remains **attachment-id based** at unit field level (`unit_detail_plan`), as already used in quote snapshot copy flow.
- REST continues exposing URL/filename/mime (`detail_plan_*`) for frontend compatibility.
- Row-level upload:
  - WP media modal
  - immediate thumbnail/file-chip update in row
  - unsaved indicator until commit
- Replace behavior:
  - replacing plan updates attachment reference only (no historic versioning in Phase 2)
- Preview:
  - image preview inline modal for jpg/png
  - new-tab for pdf
- Accepted MIME/types:
  - jpg/jpeg/png/pdf (aligned with current field config)
- Bulk filename auto-match:
  - defer to future phase (too error-prone for day one)
- Missing-file diagnostics:
  - warning rule `MISSING_UNIT_PLAN`
  - actionable “Upload” link from diagnostics panel

---

## 7. Compatibility and rollout strategy

### Recommended staged rollout

1. **Stage A — Diagnostics engine first**
   - Build rule engine + report UI with no edit surface changes.
   - Objective: expose data quality issues safely before mass edits/import.

2. **Stage B — Units Manager grid editing**
   - Add tabular editing and bulk actions writing to existing meta.
   - Keep CPT edit screens active for fallback.

3. **Stage C — CSV export, then CSV import (dry-run-first)**
   - Export first to establish canonical template.
   - Import after diagnostics and grid workflows are stable.

### Compatibility guarantees
- No changes to public REST payload keys for units/floors.
- No changes to `unit_code` mapping behavior in viewer.
- No schema changes that affect quote snapshot fields (`unit_code`, size, currency, status, floor SVG, apartment plan attachment copy).

---

## 8. Implementation breakdown for the next coding phase

### WP-1: Diagnostics core service (low-medium risk)
- Rule engine + floor SVG parser + unit data loader
- Report DTO for UI/export
- CLI/admin trigger support

### WP-2: Units Manager read-only grid (low risk)
- Admin page scaffolding
- building/floor/status/search filters
- pagination/sort/search

### WP-3: Inline edit + save API (medium risk)
- row edit model
- validation preflight endpoint
- transactional-like save summary response

### WP-4: Plan media interactions (medium risk)
- row upload/replace/remove/preview
- attachment resolution helpers

### WP-5: Bulk operations (medium risk)
- bulk status/currency/price endpoints
- confirmation + rollback messaging on partial failure

### WP-6: CSV export (low risk)
- floor-scoped export
- stable header ordering

### WP-7: CSV import dry-run + commit (high risk)
- parser + normalizer + dedupe
- preflight diagnostics integration
- idempotent upsert by `(floor_id, unit_code)`

### WP-8: Regression and compatibility hardening (high priority)
- REST response snapshot tests
- viewer mapping smoke tests
- quote creation/snapshot regression tests

### Highest-risk areas
- SVG parsing accuracy and ID extraction
- duplicate handling during import
- partial-save consistency under bulk operations

### Test-first priorities
1. Mapping diagnostics rules (`unit_code` vs SVG ids)
2. Upsert key correctness and duplicate blocking
3. REST payload shape unchanged
4. Quote snapshot fields unchanged

---

## 9. Acceptance criteria

1. **Units Manager usability**
   - Staff can filter by building/floor and manage ≥50 units without opening individual posts.
2. **Inline editing**
   - Editable columns save correctly; invalid rows are blocked and highlighted.
3. **Plan upload**
   - Row upload/replace/remove works; preview available for existing files.
4. **Validation results**
   - Diagnostics summary + row-level markers + export report are available.
5. **Duplicate detection**
   - Duplicate `(floor, unit_code)` is detected and blocks commit/import.
6. **SVG mapping diagnostics**
   - System reports both missing unit→SVG matches and orphan SVG IDs.
7. **CSV dry-run**
   - Import provides parsed/normalized dry-run with create/update/error counts.
8. **CSV import/update**
   - Commit performs correct create/update by `(floor_id, unit_code)` with detailed summary.
9. **Frontend compatibility**
   - Existing unit REST payload keys/semantics unchanged; viewer still maps by `unit_code` id.
10. **Quote compatibility**
   - Quote creation and snapshot still capture unit/floor/building and plan behaviors unchanged.

---

## 10. Open questions / deferred enhancements

Deferred beyond Phase 2:
1. Full React spreadsheet engine (virtualized advanced grid)
2. Bulk media auto-match by filename/zip ingestion
3. Versioned REST APIs (`v2`) for expanded unit schema
4. Hard DB-level uniqueness constraints/custom indexing layer
5. Migration to custom tables for units/floors
6. Fine-grained audit trail and change history UI
7. Multi-user edit locking/presence
8. Rule-builder UI for configurable diagnostics
9. Cross-floor/global import mode with in-row building/floor references
10. Async background import queue with resumability

These are intentionally deferred to keep Phase 2 conservative, compatibility-safe, and focused on immediate sales-office workflow gains.
