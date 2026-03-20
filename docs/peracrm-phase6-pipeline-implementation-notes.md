# PeraCRM Phase 6 Pipeline Implementation Notes

## Summary of what changed
Phase 6 refactors only the existing CRM pipeline route so it reads as a denser operational board instead of a stack of oversized mini-profile cards.

The work specifically:
- keeps the shared Phase 1 shell and header intact while tightening the pipeline board itself;
- compacts each stage lane into a calmer working column with a lighter header, explicit count, and subtle lane descriptor;
- restructures each pipeline card around identity, two concise context lines, restrained metadata rows, and one next-step line;
- reduces pill/chip use so cards only show a single state chip when it is genuinely useful (`Needs owner` or `No activity logged`);
- improves desktop scan density without turning the board into a list page;
- keeps tablet/mobile as horizontally scrollable board lanes, but with narrower columns and shorter card heights so the fallback stays practical.

## Files changed
- `wp-content/plugins/peracrm/inc/views/pages/crm-pipeline.php`
- `wp-content/plugins/peracrm/assets/frontend/crm.css`
- `docs/peracrm-phase6-pipeline-implementation-notes.md`

## Final card anatomy
Each pipeline card now follows this tighter structure:
1. **Identity row**
   - client name / title as the main link;
   - optional single state chip only when the card lacks an advisor or lacks logged activity.
2. **Key context block**
   - up to two compact text lines;
   - currently prioritized from existing data as source and budget.
3. **Metadata block**
   - text-first `crm-meta-line` rows for advisor and last activity;
   - no generic pill wall.
4. **Next-step line**
   - a compact concluding line that makes the likely next operational move obvious from current data;
   - examples include assigning an advisor, logging the next touchpoint, or quickly restating advisor + last activity.

## Final column / header treatment
Each stage lane now uses a compact board-lane treatment instead of a large floating card shell:
- stage name remains the primary label;
- count chip remains in the header;
- a subtle descriptor line shows record count in plain language (`1 record in lane`, `N records in lane`);
- the lane header uses a calm divider instead of oversized framing;
- empty states stay inside the lane with a dashed low-emphasis treatment.

## Desktop vs tablet/mobile behavior
### Desktop
- The page remains a board, not a list.
- Lanes are narrower and more consistent, so more stage content fits in the same viewport.
- Cards use less padding and less vertical chrome, making it easier to scan names, ownership, and freshness quickly.

### Tablet
- The board continues to scroll horizontally, but lane widths step down so scanning across stages stays practical.
- Card structure remains intact without oversized heights or overflowing metadata.

### Mobile
- The page still uses horizontal board scanning rather than redesigning into a different screen type.
- Lanes become narrower again and cards stay compact.
- Context and next-step lines wrap cleanly, avoiding giant metadata stacks or action overflow.

## Did `crm.js` change?
No. `wp-content/plugins/peracrm/assets/frontend/crm.js` was intentionally left unchanged in Phase 6.

## What was intentionally deferred
Still intentionally deferred after Phase 6:
- overview / dashboard changes beyond the existing Phase 4 work;
- client detail changes beyond the existing Phase 3 work;
- leads / clients list and tasks changes beyond the existing Phase 5 work;
- create / edit screen refactors (Phase 7);
- drag/drop or new board interactions;
- backend/business-logic rewrites or broader pipeline data model changes;
- unrelated CRM route refactors.

## Manual QA checklist
### Desktop
- Confirm the pipeline still renders as a board with stage lanes.
- Confirm each lane header shows stage name, count chip, and a compact descriptor.
- Confirm more cards fit vertically and horizontally in the same viewport than before.
- Confirm cards no longer render a wall of pills.
- Confirm client links still open the correct client page.

### Tablet
- Confirm the board scrolls horizontally without broken column sizing.
- Confirm cards remain readable without excessive height.
- Confirm long names and context lines wrap without overflow.

### Mobile
- Confirm the board remains usable with horizontal scrolling.
- Confirm cards stay compact and metadata does not overflow awkwardly.
- Confirm the optional state chip does not force broken layouts.

### Regression / scope checks
- Confirm the Phase 1 compact shell/header remains intact.
- Confirm Phase 2 primitives remain intact and are reused lightly (`crm-chip`, `crm-meta-line`).
- Confirm no list-page, overview, client-detail, tasks, or create/edit refactors were introduced here.
- Confirm no Phase 7+ work was started.
