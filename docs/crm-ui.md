# CRM UI

Presentation source of truth is `wp-content/plugins/peracrm/assets/frontend/crm.css`, with behaviour in `assets/frontend/crm.js` and markup in `inc/views/`. The CRM has a plugin-owned shell and does not rely on the public theme's main bundle.

## Visual system

The `.crm-page` scope defines the current tokens: Montserrat font stack; `--bg`, `--surface*`, `--text*`, `--brand`, `--accent`, border and status colors; spacing/radius/shadow tokens; and shared pill, chip, button, and compact-control dimensions. Use these variables rather than literal near-duplicates. System dark mode is implemented with `@media (prefers-color-scheme: dark)` overrides; every new surface, border, text, hover, and focus state must remain legible in both modes.

Established primitives include:

- `.card-shell` for contained surfaces;
- `.btn` with existing solid/ghost/color modifiers and `.crm-icon-btn` for icon actions;
- `.crm-chip` and the supported legacy `.pill` aliases;
- `.crm-table-wrap.crm-table-wrap--primitive` and `.crm-table` for tabular data;
- existing workspace, toolbar, field, notice, modal, empty-state, and responsive classes near the owning component rules.

Search existing markup and CSS before adding a class. Extend the closest primitive/component under `.crm-page`; do not introduce a second token palette, generic unscoped selectors, or a theme dependency.

## Responsive behaviour

Tables normally retain semantic table markup inside an `overflow-x: auto` wrapper. Keep compact identifiers/actions on one line and explicitly allow long names, notes, email, or descriptive content to wrap. Some client/task workspaces provide purpose-built mobile/card views; preserve their `data-*` view controls and do not force every table into cards globally. Check 320–390px widths, long localized/user content, keyboard focus, and large desktop widths.

## Behaviour-preserving restyles

Treat IDs, form names, nonce fields, URLs, ARIA relationships, `data-*` attributes, classes queried by `crm.js`, and PHP conditionals as functional API. Preserve them unless behaviour is intentionally changed and validated. Do not replace buttons with inert elements or conceal authorization/status information only visually. Keep disabled, loading, error, empty, hover, focus-visible, and print states intact.

For a small fix: identify the owning view and JS hooks; find the nearest existing component/token; add the narrowest scoped rule; inspect light/dark and mobile/desktop; exercise the affected interaction and table overflow. Read [crm.md](crm.md) before changing markup or workflows.
