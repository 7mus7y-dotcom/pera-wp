# Pera Portal Print Blank Page Audit

## 1. Executive summary
The blank print output is most likely caused by a deterministic CSS/DOM mismatch: in print mode, the stylesheet hides every direct child of `#portal-print-scope` (`#portal-print-scope > * { display:none !important; }`), but the three whitelisted printable blocks (`.portal-print-section--selector`, `--details`, `--plan`) are nested *inside* those hidden direct children rather than being direct children themselves. Once a parent is `display:none`, descendants cannot be rendered even if explicitly set to `display:block`, which yields an effectively blank printed page.

## 2. DOM findings
- `#portal-print-scope` is present in the portal shell template as expected. (`portal-shell.php`)  
- Its direct children are:
  1. `<section class="pera-portal-panel pera-portal-panel--svg">...`
  2. `<aside class="pera-portal-panel pera-portal-panel--details">...`
- Printable class placement:
  - `.portal-print-section--selector` is on `.pera-portal-floorbar`, nested inside the first direct child section.
  - `.portal-print-section--details` and `.portal-print-section--plan` are nested inside the second direct child aside.
- Therefore, printable nodes are not at the same level as the direct-child hide rule; they are descendants of elements that get `display:none` in print.

## 3. CSS findings
- Print strategy does the following in order:
  1. `body * { visibility:hidden !important; }`
  2. Makes `#portal-print-scope` and all descendants visible (`visibility:visible !important`).
  3. Hides all direct children of print scope (`#portal-print-scope > * { display:none !important; }`).
  4. Re-shows only whitelisted descendants (`.portal-print-section--selector|--details|--plan { display:block !important; }`).
- Critical issue: step 3 hides the direct parent wrappers (`section` and `aside`) that contain the whitelisted blocks. CSS cannot render children of `display:none` parents, so step 4 cannot recover those nodes.
- `display: contents` on `.portal-print-scope` outside print and `display:block` inside print is not by itself the blank-page trigger; the direct-child `display:none` rule is sufficient to fully explain the symptom.
- No additional print rules in this block suggest forced off-page positioning/zero-height on printable sections.

## 4. JS findings
- Print button is wired via delegated click in `shareContainer` and explicitly runs:
  1. `preparePrintPlanFallback()`
  2. `window.print()`
- That flow is synchronous and present.
- `preparePrintPlanFallback()` is opportunistic only; it runs only when a canvas exists under `.portal-print-section--plan` and does nothing otherwise.
- Unit details/plan content are only populated when `renderDetails(unit)` is called after a unit click; otherwise details may show placeholder/message and plan may be empty. This can lead to sparse output, but not complete blankness when selector/details wrappers are renderable.
- No clear JS race is required to explain a fully blank page given the CSS parent-hiding behavior.

## 5. Ranked root causes
1. **High confidence (primary):** `#portal-print-scope > * { display:none !important; }` hides parent wrappers containing all whitelisted sections, making whitelist ineffective.
2. **Medium confidence (secondary):** Whitelist selectors are applied to nested blocks rather than structural wrappers, increasing fragility when parent-level hide rules are used.
3. **Low confidence (situational):** If no unit selected, `.portal-print-section--plan` may be empty and details may only contain default message; this reduces useful content but does not independently explain fully blank output.
4. **Low confidence:** JS timing/fallback path issues are possible for plan image quality, but not needed to explain all-content blanking.

## 6. Fix options
### Option A (safest)
- **Files:** `assets/dist/portal-viewer.css` only.
- **Change direction:** Remove or narrow `#portal-print-scope > * { display:none !important; }`; instead hide known non-print blocks directly and keep parent wrappers renderable.
- **Why it solves blank page:** Prevents hiding the ancestor elements that contain whitelisted printable blocks.
- **Risk:** Low. Minimal selector change, mostly print-only behavior.

### Option B (medium)
- **Files:** `templates/portal-shell.php`, `assets/dist/portal-viewer.css`.
- **Change direction:** Move printable modifier classes to top-level print-scope children (or introduce dedicated direct-child print wrappers), then keep whitelist aligned to direct-child structure.
- **Why it solves blank page:** Aligns whitelist strategy with DOM hierarchy so parent/child visibility rules cannot conflict.
- **Risk:** Medium. Template class/layout adjustments may impact JS selectors if not carefully preserved.

### Option C (structural cleaner)
- **Files:** `templates/portal-shell.php`, `assets/dist/portal-viewer.css`, potentially `assets/dist/portal-viewer.js`.
- **Change direction:** Create a dedicated print subtree (cloned/assembled content) rendered only for print; decouple from live interactive layout.
- **Why it solves blank page:** Eliminates brittle global hide/show logic and makes print output explicit and deterministic.
- **Risk:** Medium-high. More moving parts and possible duplication drift between live and print views.

## 7. Recommended next step
Implement **Option A** first: patch print CSS to stop hiding all direct children of `#portal-print-scope`, then validate print with and without a selected unit. This directly addresses the deterministic blank-page mechanism with the smallest regression surface.
