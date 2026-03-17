# CRM Layout/Nav/AJAX Audit Pass

## Scope audited
- `/crm/` (overview)
- `/crm/clients/`
- `/crm/tasks/`
- `/crm/pipeline/`
- `/crm/client/{id}`

## Affected files inspected
- `wp-content/plugins/peracrm/assets/frontend/crm.css`
- `wp-content/plugins/peracrm/assets/frontend/crm.js`
- `wp-content/plugins/peracrm/inc/frontend/routing.php`
- `wp-content/plugins/peracrm/inc/frontend/assets.php`
- `wp-content/plugins/peracrm/inc/views/partials/crm-header.php`
- `wp-content/plugins/peracrm/inc/views/pages/crm-overview.php`
- `wp-content/plugins/peracrm/inc/views/pages/crm-client.php`
- `wp-content/plugins/peracrm/inc/views/pages/crm-pipeline.php`
- `wp-content/plugins/peracrm/inc/frontend-data/crm-client-view.php`
- `wp-content/plugins/peracrm/inc/admin/actions.php`
- `wp-content/plugins/peracrm/inc/helpers.php`
- `wp-content/themes/hello-elementor-child/inc/whatsapp-click-log.php`
- `wp-content/themes/hello-elementor-child/inc/enquiry-email-log.php`
- `wp-content/themes/hello-elementor-child/logos-icons/icons.svg`

## Confirmed issues

### 1) Shared overflow/layout fragility on client view panels
- `input/select/textarea` in `.crm-form-stack` are `width: 100%` without a shared `box-sizing: border-box`, so padded controls can overflow in tight panel/flex contexts.
- Several panel rows use flex/grid without consistent shrink guards (`min-width: 0`) on children that carry long content.
- `crm-client-panels-grid` uses CSS columns masonry; mixed-width internal form controls and inline action rows can trigger awkward width pressure and clipping behavior.
- The reminders and deals sections rely on nested row wrappers but do not consistently force wrapping for action rows and long text.

### 2) Client profile/reminders/deals overflow causes
- **Client profile form**: control box model + nested rows (`.crm-phone-row`, `.crm-form-row-2`) can exceed card width when content is long or viewport narrows.
- **Tasks/reminders panel**: reminder list items include inline forms and long notes without a uniform wrap strategy on all descendants.
- **Deals panel**: inline deal meta/actions and value/currency row can push width in narrow states.

### 3) `/crm/clients/` search currently tied to hero/admin-ish utility styling
- Search uses `cta-control` in shared hero filter layout. It works but is not explicitly CRM-form pattern and is visually inconsistent with CRM card/form controls.

### 4) CRM nav rendering and insertion point
- Current nav is horizontal and embedded inside `crm-header` (`.crm-subnav`) for each page.
- Best insertion point for a right-side CRM nav is the shared shell around page content (the content panel wrapper), rendered once and reused by overview/client/pipeline templates.

### 5) Floating round tick button
- Rendered in `crm-client.php` as `.crm-floating-add-task` with `#icon-check`.
- It is a floating anchor to `#crm-add-reminder`, not a save action.
- Current visual/positioning makes it read as an unexplained floating FAB and it duplicates visible reminder CTA.
- Decision: remove as obsolete/duplicative control and clean CSS.

### 6) Client-page actions still redirect
Current forms on `/crm/client/{id}` submit to `admin-post.php` and redirect:
- Save profile (`peracrm_save_client_profile`)
- Save status (`peracrm_save_party_status`)
- Add note (`peracrm_add_note`)
- Add reminder (`peracrm_add_reminder`)
- Link property (`pera_crm_property_action=link` via template_redirect handler)
- Create/update deal (`peracrm_create_deal` / `peracrm_update_deal`)
- Reassign advisor (`peracrm_reassign_client_advisor`)
- Convert to client (`peracrm_convert_to_client`) → keep redirect per scope

### 7) WhatsApp/Email logs source implementation
- Existing log implementations live in theme admin pages:
  - `admin.php?page=pera-whatsapp-logs`
  - `admin.php?page=pera-enquiry-email-log`
- Best CRM strategy: add dedicated CRM front-end routes/pages that query the same underlying tables/options and reuse the same helper functions when available; render in CRM card/table style (no wp-admin table classes).

## Implementation plan (surgical)
1. **Routing/views**
   - Add CRM routes for `/crm/whatsapp-logs/` and `/crm/email-logs/` in frontend routing and template resolver.
   - Add a new CRM logs page template that conditionally renders email/whatsapp logs.

2. **Right-side CRM nav**
   - Add a shared CRM side-nav partial and include it in overview/client/pipeline/log pages through a shared content wrapper layout.
   - Use shared icon sprite (`icon-bars`) from existing icons SVG.
   - Show logs items only for admin/manager-level users.

3. **Overview ordering**
   - Reorder overview sections so New Leads appears first, then Today’s Tasks, then remaining sections.

4. **Overflow hardening**
   - Add shared containment fixes in `crm.css`:
     - `box-sizing: border-box` for CRM form controls.
     - `min-width: 0` on shrinkable flex/grid children and key panel wrappers.
     - `overflow-wrap`/word-break rules for long text in cards/lists.
     - Wrap button groups and inline action rows consistently.

5. **Clients search style**
   - Replace ad-hoc hero-search classes with reusable CRM search/form classes and align with CRM tokens/buttons.

6. **AJAX conversion on client page**
   - Add `wp_ajax` endpoints (same capability + nonce checks) for:
     - profile save, status save, add note, add reminder, link property, create deal, reassign advisor.
   - Keep convert-to-client redirect.
   - Return structured JSON (`ok`, `message`, optional html/items/state).
   - Add JS progressive enhancement for targeted forms:
     - loading/disabled states
     - inline feedback
     - panel-level refresh via returned HTML snippets where safer than deep DOM patching.
   - Add confirmation dialog for advisor reassign with required copy.

7. **Linked properties “Create portfolio” button spacing**
   - Normalize button sizing/padding by reusing shared `.btn` dimensions and removing over-specific round icon treatment from this CTA.

8. **Floating tick cleanup**
   - Remove markup and associated CSS rules for `.crm-floating-add-task` from client view.

## Risks / regression points
- Permission mismatches between existing admin-post and new AJAX handlers.
- Theme helper functions for logs may not exist in some environments; CRM pages need graceful fallback when tables/functions are unavailable.
- Partial panel refreshes must preserve nonce freshness and form state where possible.
- Removing floating anchor must not reduce reminder discoverability on mobile.

## Test checklist
- Role matrix: administrator, manager, employee/advisor.
- Route coverage: `/crm/`, `/crm/clients/`, `/crm/tasks/`, `/crm/pipeline/`, `/crm/client/{id}`, `/crm/whatsapp-logs/`, `/crm/email-logs/`.
- Verify right nav active state and permission-gated log entries.
- Verify no panel overflow in client profile, reminders, deals, linked properties.
- Verify clients search field styling is CRM-consistent.
- Verify AJAX actions update in place (profile, status, note, reminder, link property, create deal, reassign).
- Verify convert-to-client remains redirect flow.
- Verify advisor reassign confirmation text/buttons exactly match spec.
- Verify floating tick button is removed.
- Verify no console errors and no PHP warnings/fatals during actions.
