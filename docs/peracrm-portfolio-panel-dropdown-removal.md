# CRM client portfolio panel: relation-type dropdown removal

## Summary
- Removed the relation-type `<select>` from the **Linked Properties** form on `/crm/client/{id}`.
- Kept the existing property search UI (query input, hidden `property_id`, results list, feedback text) and submit button unchanged.
- Added a hidden `relation_type` input with a fixed value of `portfolio` for compatibility with existing form payload expectations.

## Server-side behavior hardening
- Updated the property action handler (`pera_crm_client_view_handle_property_actions()`) so `link` actions always force `relation_type = 'portfolio'` server-side.
- This means linking no longer depends on any client-sent relation type value and cannot be switched via tampering.
- `unlink` behavior is unchanged and still uses the row-posted relation type from the rendered list item form.

## JavaScript impact
- No relation-type-dependent code is currently used by the CRM property search/autocomplete flow.
- Property search/autocomplete behavior remains unchanged.

## Why
Portfolio is now the only manual link type in this panel, so the dropdown is redundant and was removed to simplify the UI while preserving link behavior.
