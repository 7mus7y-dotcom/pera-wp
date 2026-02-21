# Portfolio Fields Implementation (Option A)

## What changed
- Bumped `PERACRM_SCHEMA_VERSION` from `5` to `6`.
- Added nullable portfolio custom-field columns to `{$wpdb->prefix}crm_client_property` schema:
  - `floor_number VARCHAR(20) NULL`
  - `net_size DECIMAL(10,2) NULL`
  - `gross_size DECIMAL(10,2) NULL`
  - `list_price DECIMAL(14,2) NULL`
  - `cash_price DECIMAL(14,2) NULL`
- Added repository helper `peracrm_client_property_update_portfolio_fields()` to update only portfolio relation rows and only provided keys.
- Added AJAX action `wp_ajax_pera_crm_save_portfolio_property_fields` in CRM client-view layer with:
  - nonce validation,
  - access enforcement,
  - relation-row existence check,
  - strict sanitization for text/decimal fields,
  - JSON success/error responses.
- Added a per-row Portfolio fields editor under each portfolio-linked property in `/crm/client/{id}` Linked Properties section.
- Added JS save wiring in `js/crm.js` for inline save feedback.
- Localized new nonce `portfolioFieldsNonce` into `window.peraCrmData`.

## How to test
1. Open `/crm/client/{id}` for a client with at least one portfolio-linked property.
2. In Linked Properties → Portfolio row, edit one or more fields (Floor, Net, Gross, List, Cash).
3. Click **Save** and confirm inline status shows `Saved`.
4. Refresh page and verify values persist.
5. Try invalid nonce (e.g. alter request nonce) and confirm API returns 403.
6. Unlink a portfolio row, then attempt save on that property/client relation and confirm API returns 404.
7. Verify link/unlink behavior and portfolio token generation still work as before.

## Mobile overflow fix (portfolio fields editor)
- Added a late-file mobile override in `css/crm.css` to resolve a cascade conflict where a later `@media (max-width: 1024px)` rule forced `.peracrm-linked-properties-grid` back to 2 columns after an earlier `@media (max-width: 767px)` rule set 1 column.
- On phones (`<=767px`), linked property cards are now forced to a single-column grid so each portfolio card has full width.
- Added feature-scoped mobile rules under `.crm-route` for `[data-crm-portfolio-row]` / `[data-crm-portfolio-fields]`:
  - `min-width: 0` on nested containers/items,
  - compact 2-column field layout,
  - `input` width constraints (`width/max-width/min-width`) to prevent overflow,
  - last field spanning full width for 5-field balance.
- Added an extra narrow breakpoint (`<=420px`) to collapse the portfolio fields editor to one column.
- Desktop and tablet behavior remain unchanged except intended tablet 2-column linked-property grid behavior is preserved.
