# Public Tokenized Quote Page UI/Presentation Audit

Scope reviewed:
- `wp-content/plugins/pera-portal/templates/portal-quote.php`
- `wp-content/plugins/pera-portal/assets/dist/portal-viewer.css` (reference patterns)
- `wp-content/plugins/pera-portal/templates/portal-shell.php` (reference patterns)

## 1. Executive summary

The quote page currently reads as **functionally complete but visually utilitarian**. It communicates all core data and plan visuals, but it still looks closer to a template/debug deliverable than a premium client-facing quotation sheet.

The largest polish gaps are:
- flat, repetitive card styling with limited hierarchy,
- weak typographic scale (especially metadata and legal text),
- floor-plan section lacking framing/contextual cues,
- limited print-specific layout intent beyond simple page-break avoidance.

With a handful of styling adjustments (without architecture changes), this page can feel significantly more commercial and presentation-ready.

## 2. Floor plan section critique

### What is good
- Clear section labeling (“Frozen Floor Plan” and “Frozen Apartment Plan”).
- Unit highlighting logic supports selected-vs-other visual state and is robustly applied to common SVG shapes.
- Non-selected unit neutralization prevents visual noise.

### What feels unfinished
- The floor-plan block is visually almost identical to generic content cards, so it lacks “document importance.”
- There is no legend/caption in quote context explaining the blue highlight and grey outlines.
- Current highlight uses a relatively assertive stroke (`2.5`) and medium-strong blue fill; for print it may feel heavy.
- Spacing between heading and graphic is minimal/implicit; no intentional frame or caption rhythm.
- Floor plan and apartment plan appear as two consecutive similar blocks with insufficient visual differentiation.

### What should change
- Add a compact legend/caption directly under floor-plan heading (e.g., “Selected unit in blue; other units outlined”).
- Introduce a subtle inner frame/background for plan canvas to distinguish the media zone from card chrome.
- Soften selected-unit style slightly for elegance: slightly reduced stroke or opacity, with better harmony against neutral palette.
- Add modest vertical spacing rhythm: heading → caption → graphic.
- Differentiate floor vs apartment plan via small section descriptors (e.g., “Building context” vs “Unit snapshot”).

## 3. Layout and hierarchy critique

### Strengths
- Logical top-level order exists: title/reference/status → price/validity → unit facts → plans → notes/client text.
- Price is currently isolated in its own block and prominent enough to be noticed.
- Grid-based facts section is concise and easy to scan.

### Weaknesses
- All sections use nearly identical border/padding/radius, creating a monotonous visual rhythm.
- Header block does not strongly establish a “document masthead” feel (no stronger spacing/typographic contrast).
- Price block prominence is limited by plain context around it; it could feel more “commercial quote amount.”
- Facts grid is semantically fine but visually raw (plain `<p>` rows with strong labels and no subtle separators).
- Final client/disclaimer block is under-structured, causing important legal text to blend with contact data.

## 4. Typography and styling critique

### Strengths
- Readable base typography and straightforward contrast.
- Status color variants communicate state clearly (active/expired/revoked).
- Heading usage is consistent enough for current simple document.

### Weaknesses
- Single-font, minimal type scale causes weak hierarchy depth (headline, section titles, metadata, legal text too close in tone).
- Default-ish spacing around headings and paragraph stacks reduces editorial polish.
- Borders (`#ddd`) and radius values feel generic; lacks a refined tokenized visual system.
- Status banner is clear but visually dense; could be made more “premium alert” with softer surface and left accent.
- Card surfaces/shadows/dividers are minimal; page feels flat compared with portal viewer styling patterns.

## 5. Print/PDF readiness critique

### Current state
- Good start: print mode removes page padding and avoids breaking sections (`page-break-inside: avoid`).
- Core content is linear and therefore printable.

### Gaps
- No print-specific type sizing or spacing optimization for long-form A4/Letter output.
- No explicit handling for large SVG/image scaling (risk of awkward clipping or oversized media blocks).
- No page-break intent between major visual sections (floor plan and apartment plan may land awkwardly).
- Status banner/background colors may not print elegantly on grayscale or low-ink settings.

## 6. Prioritized improvements

### P0 (quick visual wins)
1. Increase hierarchy contrast in header and price blocks (larger heading spacing, smaller metadata text, stronger price context label).
2. Add floor-plan caption/legend explaining highlight semantics.
3. Refine selected-unit highlight for balance (reduce visual harshness while maintaining clarity).
4. Improve spacing rhythm between section headings and media/content.
5. Split client identity and disclaimer into clearly separated sub-blocks.

### P1 (layout refinements)
1. Introduce differentiated card treatments by role:
   - masthead card,
   - financial summary card,
   - media cards,
   - legal/footer card.
2. Upgrade facts grid presentation with subtle separators or key/value styling.
3. Add quiet surface contrast and/or subtle shadow hierarchy to avoid flat, repetitive blocks.
4. Align quote-page styling tokens with portal viewer palette conventions for visual consistency.

### P2 (optional premium polish)
1. Add a branded quote masthead strip (building/project + reference/status metadata cluster).
2. Add a print-tailored stylesheet:
   - media max-heights,
   - controlled page breaks before major sections,
   - grayscale-friendly banner styling.
3. Add microcopy refinements for a more formal sales-document tone (without increasing verbosity).
4. Add a compact “document facts” row (issue date, expiry, consultant) with refined typography.
