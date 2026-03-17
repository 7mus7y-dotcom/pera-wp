# Phase 2G — CRM runtime asset findings (follow-up)

## What was adjusted after review

1. **Preserved CRM push script handle compatibility**
   - The MU-plugin enqueue/localize path now uses the legacy handle `pera-crm-push` (instead of `peracrm-crm-push`).
   - This avoids potential regressions where other code may rely on the historical handle name.

2. **Removed misleading/dead theme CRM push logic**
   - `inc/modules/crm-push.php` in the theme is now an explicit no-op compatibility shim.
   - Reason: CRM push runtime ownership for `/crm/*` now lives in the MU plugin.

## Current ownership model on `/crm/*`

- **Plugin-owned runtime assets:**
  - `pera-slider-css`
  - `pera-crm-css`
  - `pera-crm-js`
  - `pera-crm-push`

- **Theme runtime assets excluded from `/crm/*`:**
  - `pera-main-css`
  - `pera-main-js`
  - `pera-whatsapp-click-log`

## Notes

- Theme enqueue code for non-CRM pages remains unchanged in behavior.
- CRM push localization payload shape (`window.peraCrmPush`) and nonce behavior are preserved in plugin-owned runtime loading.
