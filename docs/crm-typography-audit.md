# CRM Typography Audit + Consistency Fix

## Scope
CRM routes reviewed:
- `/crm/`
- `/crm/clients/`
- `/crm/tasks/`
- `/crm/pipeline/`

Constraints applied in this implementation:
- No edits to `css/main.css`
- No inline style usage
- Fixes implemented in `css/crm.css` only

---

## Phase 1 — CSS enqueues on CRM routes

## Route resolution / CRM condition
`/crm/*` routes are virtual rewrites that set `pera_crm=1`; `pera_is_crm_route()` checks this query var and gates CRM assets/templates.

## Theme CSS load order on CRM pages (first → last)
| Order | Handle | File path | CRM-only conditional? | Inline CSS via `wp_add_inline_style`? |
|---|---|---|---|---|
| 1 | `pera-main-css` | `wp-content/themes/hello-elementor-child/css/main.css` | No (global enqueue) | No |
| 2 | `pera-crm-css` | `wp-content/themes/hello-elementor-child/css/crm.css` | Yes (`pera_is_crm_route()`) | No |

Notes:
- `pera-crm-css` depends on `pera-main-css`, so it reliably loads after global styles.
- Hello Elementor parent CSS handles are dequeued/deregistered in the child theme.
- Frontend block CSS (`wp-block-library`, etc.) is dequeued.
- Existing `wp_add_inline_style` calls are only for admin taxonomy UI and login UI, not CRM frontend.

## Files not conditionally loaded for CRM routes
Not enqueued by CRM route conditions:
- `slider.css`
- `property.css`
- `property-card.css`
- `blog.css`
- `posts.css`

---

## Phase 2 — Detailed CRM typography audit

## CRM wrappers/components found in templates
Key wrappers/classes used by CRM templates:
- `.crm-page` (all CRM pages)
- `.crm-subnav`
- `.crm-leads-table`, `.crm-table-sort`
- `.crm-lead-cards`, `.card-shell`
- `.crm-form-stack`
- `.crm-list`
- `.crm-pipeline-*`

## Component typography map (effective/winning)

> Values below reflect the **current effective state after this change**, with winning selector and source.

| Component | Font size | Font weight | Line height | Winning selector | Source |
|---|---:|---:|---:|---|---|
| A) CRM header title (`h1`) | `3rem` (responsive down at breakpoints) | `800` | `1.2` | `h1` (+ `h1,h2,h3...`) | `css/main.css` |
| B) CRM subnav buttons | `0.95rem` | `600` | `1` | `.btn` | `css/main.css` |
| C1) Section headings `h2` | `2.4rem` | `700` | `1.2` | `h2` | `css/main.css` |
| C2) Section headings `h3` | `1.4rem` | `600` | `1.2` | `h3` | `css/main.css` |
| C3) Section headings `h4` | inherited heading size + `line-height:1.2` | inherited heading weight | `1.2` | `h1,h2,h3,h4,h5,h6` | `css/main.css` |
| D) Card titles (client names, `h3`) | `1.4rem` | `600` | `1.2` | `h3` | `css/main.css` |
| E1) Card body paragraphs | `1rem` | `400` | `1.6` | `.crm-page p` | `css/crm.css` |
| E2) Meta labels/dates (plain text) | `1rem` | `400` | `1.6` | `.crm-page li, .crm-page td` | `css/crm.css` |
| F) Pills/badges in CRM | `0.8rem` | inherited (regular in most contexts) | `1.3` (from global `.pill`) | `.crm-page .pill` (+ global `.pill`) | `css/crm.css` + `css/main.css` |
| G1) Tables (`table`) | `1rem` | inherited | `1.4` | `.crm-page .crm-leads-table` | `css/crm.css` |
| G2) Table headers (`th`) | `1rem` | `600` | `1.4` | `.crm-page .crm-leads-table th` | `css/crm.css` |
| G3) Sort button text | inherits `th` | inherits `th` | inherits `th` | `.crm-page .crm-table-sort` | `css/crm.css` |
| H1) Form labels | `0.8rem` | `600` | `1.6` | `.crm-form-stack label` | `css/crm.css` |
| H2) Inputs/selects/textareas | `0.95rem` | `400` | `1.6` | `.crm-form-stack input/select/textarea` | `css/crm.css` |
| H3) Form helper/small text | `0.9rem` | inherited | `1.45` | `.crm-page small, .crm-page .text-sm` | `css/crm.css` |
| H4) Button text in forms | `0.95rem` | `600` | `1` | `.btn` | `css/main.css` |
| I) Small/muted text (`crm-pipeline-empty`, small hints) | `0.9rem` | inherited | `1.45` | `.crm-page .crm-pipeline-empty` | `css/crm.css` |

---

## Phase 3 — Existing enquiry form styles and reuse

## Where enquiry field styling lives
The enquiry form field styling is in:
- `wp-content/themes/hello-elementor-child/css/main.css`

Primary selectors there:
- `.cta-label`
- `.cta-control`
- `.cta-control:focus`
- `.cta-control::placeholder`

These provide the “nice field” UX:
- 10px radius
- 0.7rem/0.9rem padding
- 1px light border
- white background
- blue focus border + subtle focus ring
- light placeholder color

## Reuse strategy chosen
Used **Option B**:
- Copied only the minimal enquiry field styling characteristics into `css/crm.css`
- Scoped to `.crm-form-stack` and `.crm-page` so public site styles are unaffected

Reason:
- Enqueuing all of `main.css` is already happening globally, but enquiry-specific selectors are class-based (`.cta-control`) and not used by CRM markup.
- A tiny scoped CRM rule set is the smallest reliable change without template edits.

---

## Inconsistencies found and target scale

## Inconsistencies found (before fix)
- CRM tables inherited `0.95rem` from global table rule while card/body copy was `1rem`.
- `th` weight was not explicit in CRM table scope.
- CRM pills were `0.75rem` and visually too small against body/table copy.
- CRM form controls had browser-default appearance/typography in many places.

## Target CRM typography scale (close to existing system)
- Base text: `1rem / 400 / 1.6`
- Small/meta text: `0.9rem / 400 / 1.45`
- Pills/meta chips in CRM: `0.8rem`
- Buttons: unchanged global `.btn` (`0.95rem / 600 / 1`)
- Table text: `1rem`
- Table headers: `600`

---

## Changes applied

## `css/crm.css` updates
1. Added scoped CRM typography tokens under `.crm-page`:
   - `--crm-text-base`, `--crm-text-small`, `--crm-meta-size`, etc.
2. Normalized base text for CRM content elements (`p`, `li`, `td`, labels, fields).
3. Normalized tables:
   - table size to `1rem`
   - explicit `th` weight `600`
   - sort button inherits table header typography
4. Normalized CRM-only pills:
   - set CRM pill size to `0.8rem`
   - reduced letter spacing for better legibility
5. Applied enquiry-like field UX to CRM forms:
   - label style (`0.8rem`, uppercase, `600`)
   - inputs/selects/textarea padding, border, radius, background
   - placeholder and focus styles matching enquiry-field behavior
6. Added small-text normalization for helper/muted UI text.

No other stylesheet was edited or enqueued.
