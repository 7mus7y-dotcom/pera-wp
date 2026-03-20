# PeraCRM Phase 7 Create / Edit Implementation Notes

## Summary of what changed
Phase 7 refactors only the create / edit style CRM workspaces that are currently part of the operational form flow.

The work specifically:
- keeps the shared Phase 1 shell and page-header system intact;
- keeps the Phase 2 primitive language intact while extending it with a light shared form-workspace pattern layered on top of `crm-section`, `crm-action-group`, and `crm-meta-line`;
- restructures the front-end **Create new lead** screen into a calmer form workspace with a clear intro, grouped sections, and a strong submit hierarchy;
- lightly restructures the embedded **deal create / edit** form inside the client route so it behaves like a scoped edit workspace rather than an undifferentiated block under the deals list;
- preserves all existing form handling, prefilled values, duplicate checks, nonce handling, redirects, and phone country / normalization inputs.

## Files changed
- `wp-content/plugins/peracrm/inc/views/pages/crm-new.php`
- `wp-content/plugins/peracrm/inc/views/pages/crm-client.php`
- `wp-content/plugins/peracrm/assets/frontend/crm.css`
- `docs/peracrm-phase7-create-edit-implementation-notes.md`

## Final form / group structure used
### Create new lead (`crm-new.php`)
1. **Workspace summary / purpose**
   - intro eyebrow;
   - workspace title;
   - short explanation;
   - compact meta line for required vs optional inputs.
2. **Basic identity**
   - first name;
   - last name;
   - email.
3. **Contact details**
   - phone country code;
   - national phone number.
4. **Lead source and context**
   - source;
   - notes.
5. **Action footer**
   - supporting meta line;
   - secondary cancel action;
   - dominant primary create action.

### Embedded deal create / edit section (`crm-client.php`)
1. **Embedded workspace summary**
   - contextual intro for create vs update mode;
   - compact meta line describing current mode and required field.
2. **Deal basics**
   - title;
   - stage;
   - primary property ID.
3. **Commercial details**
   - deal value;
   - currency.
4. **Action footer**
   - contextual helper line;
   - dominant primary create / update action.

## Action hierarchy used
### Create lead page
- **Primary:** `Create lead`
- **Secondary:** `Cancel`
- **Utility/context:** header-level `Back to clients`

The form footer now makes the submit action the strongest control and visually demotes the cancel path.

### Embedded deal form
- **Primary:** `Create deal` or `Update deal`
- **Secondary/adjacent utilities:** existing list-level `Edit` / `Delete` controls remain above the form in the deal list instead of competing with the submit button inside the form itself.

## Desktop vs tablet/mobile behavior
### Desktop
- Form sections stay grouped into clear bordered workspace blocks.
- Two-column form layout is used only where it helps scan speed (`First/Last name`).
- Action groups stay right-aligned where space allows.

### Tablet
- Grouped sections remain intact.
- Controls wrap earlier so action rows do not crowd.
- Embedded deal form keeps grouped sections but remains comfortably stacked.

### Mobile
- Multi-column field groups collapse to a single column.
- Phone rows already collapse safely to stacked controls.
- Form actions expand to full width so there is no cramped side-by-side action overflow.

## Did `crm.js` change?
No. `wp-content/plugins/peracrm/assets/frontend/crm.js` was intentionally left unchanged in Phase 7.

## What was intentionally deferred
Still intentionally deferred after Phase 7:
- overview / dashboard changes beyond existing Phase 4 work;
- client detail structural changes beyond the narrowly scoped embedded deal form workspace treatment;
- leads / clients list and tasks changes beyond existing Phase 5 work;
- pipeline changes beyond existing Phase 6 work;
- broader client profile / status / notes form redesigns;
- new client-side form systems or validation rewrites;
- backend submission logic rewrites.

## Manual QA checklist
### Create lead
- Confirm the page reads as summary -> grouped sections -> action footer.
- Confirm first / last name, email, source, and notes still submit correctly.
- Confirm duplicate email handling still shows the existing error notice and link.
- Confirm prefilled query-string values still populate the same fields.
- Confirm phone country and national phone inputs still preserve existing handling.
- Confirm cancel returns to the clients page without submission.

### Embedded deal form
- Confirm the deals list still renders and existing edit links still load the edit mode.
- Confirm create deal still submits correctly.
- Confirm update deal still submits correctly when a deal is being edited.
- Confirm delete actions still remain available and separate from the form submit action.

### Responsive / regression checks
- Confirm Phase 1 header / shell remains unchanged.
- Confirm Phase 2 primitives remain intact and are reused (`crm-section`, `crm-action-group`, `crm-meta-line`).
- Confirm no overview, list, tasks, or pipeline templates were refactored here.
- Confirm no Phase 8+ work was started.
