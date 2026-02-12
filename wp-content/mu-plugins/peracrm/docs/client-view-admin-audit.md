# PeraCRM WP-Admin Client View Audit (MU plugin + theme integration)

## 1) How it works (end-to-end)

### What “Client View” actually is in current code
There are **two related admin experiences** for a CRM client:

1. **Submenu page** under CRM post type:
   - `edit.php?post_type=crm_client&page=peracrm-client-view&client_id=<id>`
   - Rendered by `peracrm_render_client_view_page()`.
   - This is a consolidated read-heavy view with limited write actions (currently: add note).

2. **Primary edit screen** for a `crm_client` post:
   - `post.php?post=<id>&action=edit`
   - Renders the full metabox stack (CRM Status, Linked Properties, Username link, Client Health, Assigned Advisor, Client Profile, Notes, Reminders, Timeline, Activity Timeline, Deals).
   - Most write actions are wired from this screen via metabox action buttons (admin-post handlers).

So in practical terms, the “working Client View under CRM” is a split architecture:
- Submenu page = summary + timeline + submissions + note add form.
- Edit screen metaboxes = full operational controls.

---

### Routing/menu -> controller
- Admin menu is registered in `peracrm_register_admin_menu()`.
- Parent slug: `edit.php?post_type=crm_client`.
- Client View submenu slug: `peracrm-client-view`.
- Callback: `peracrm_render_client_view_page()`.

Admin-post action hooks are registered centrally in `inc/admin/admin.php` and dispatch to handlers in `inc/admin/actions.php`.

---

### Permissions and access checks
#### Top-level CRM access
`peracrm_admin_user_can_manage()` delegates to CRM capability checks (`manage_options` OR `edit_crm_clients` OR `edit_crm_leads` OR `edit_crm_deals` fallback).

#### Menu capability selection
`peracrm_admin_required_capability()` picks one capability depending on current user (admin > leads > deals > clients).

#### Submenu Client View runtime checks
`peracrm_render_client_view_page()` enforces:
1. `peracrm_admin_user_can_manage()`
2. valid `client_id` and post type `crm_client`
3. `current_user_can('edit_post', $client_id)`
4. advisor scoping: non-admin/non-`peracrm_manage_all_clients` users must be assigned advisor for that client.

#### Metabox-level permissions
- Some metaboxes require only `edit_post`.
- Timeline metabox additionally requires `manage_options`.
- Assigned advisor reassign requires `manage_options` OR `peracrm_manage_assignments`.
- Reminder status updates are authorized via `peracrm_reminders_update_status_authorized()` (admin/global reminder cap or assigned advisor).

---

### Data sources and persistence model
#### Core identity
- Client entity = CPT `crm_client` (post + post meta).

#### Meta-driven profile/assignment
- Profile (`status`, `client_type`, `preferred_contact`, budget, phone, email) is stored in post meta (`_peracrm_*`) via helper APIs.
- Assigned advisor comes from post meta keys (`assigned_advisor_user_id` or `crm_assigned_advisor`).

#### Custom tables
- Notes: `{prefix}crm_notes` with fallback post meta `_peracrm_notes_fallback`.
- Reminders: `{prefix}crm_reminders` with fallback post meta `_peracrm_reminders_fallback`.
- Activity: `{prefix}crm_activity`.
- Client-property links: `{prefix}crm_client_property`.
- Party/CRM status: `{prefix}peracrm_party`.
- Deals: `{prefix}peracrm_deals`.

#### User linkage
- Linked WP user is primarily resolved via user meta `crm_client_id` (plus legacy support for a `linked_user_id` column in custom `crm_client` table if present).

---

### Rendering and UI
- Submenu page and metaboxes are rendered with **custom PHP/HTML** (not `WP_List_Table`).
- Styling: shared admin stylesheet `peracrm/assets/admin.css` enqueued on CRM admin screens, reminders, pipeline, and client-view submenu.
- Metabox action buttons use a JS helper (`peracrm_render_metabox_action_helper_script`) that builds hidden forms and submits to `admin-post.php`.

---

### Nonces / requests / sanitization patterns
- Most writes use `admin-post.php` actions with explicit nonce checks:
  - `check_admin_referer()` or `wp_verify_nonce()`.
- Inputs are sanitized via `sanitize_key`, `sanitize_text_field`, `sanitize_textarea_field`, `sanitize_email`, `absint`/casts, plus domain sanitizers in repository/helpers.
- Redirects use safe/admin URLs and notice query args (`peracrm_notice=...`).

---

### Multisite / blog switching impact
- `PERACRM_TARGET_BLOG_ID` is respected through `peracrm_with_target_blog()`.
- **Party + Deals repositories** are target-blog aware.
- Notes/reminders/activity/client-property repositories are **not consistently wrapped** with target-blog switching.
- This can produce cross-blog inconsistency in multisite setups (status/deals on target blog while other CRM artifacts are on current blog tables).


## 2) Client View Blueprint (structured outline)

## URL/page slug
- Submenu page URL:
  - `/wp-admin/edit.php?post_type=crm_client&page=peracrm-client-view&client_id=<id>`
- Slug: `peracrm-client-view`
- Parent: `edit.php?post_type=crm_client`

## Query vars
- `client_id` (required to render a specific client)
- `peracrm_timeline` (`all|activity|notes|reminders`)
- notices via `peracrm_notice`

## Controller functions
- Menu registration: `peracrm_register_admin_menu()`
- Capability routing: `peracrm_admin_required_capability()`
- Page callback: `peracrm_render_client_view_page()`
- Selector support:
  - `peracrm_client_view_selectable_clients()`
  - `peracrm_client_view_render_selector()`

## Data loaders per section

### Submenu page sections (actual current output)
1. **Health summary**
   - loader: `peracrm_client_health_get()`
2. **Client profile summary**
   - loader: `peracrm_client_get_profile()`
3. **Reminders counts**
   - loaders:
     - `peracrm_reminders_count_open_by_client()`
     - `peracrm_reminders_count_overdue_by_client()`
4. **Notes list**
   - loader: `peracrm_notes_list($client_id, 10, 0)`
5. **Form submissions**
   - loader: `peracrm_activity_list(..., 'enquiry')`
   - payload normalization via `peracrm_client_view_*` helper functions
6. **Timeline**
   - filter: `peracrm_timeline_get_filter()`
   - data: `peracrm_timeline_get_items($client_id, 50, $filter)`
   - diagnostics: `peracrm_timeline_missing_sources()`

### Full “client view” sections mapped to edit-screen metaboxes
1. **CRM status**
   - metabox: `peracrm_render_crm_status_metabox()`
   - source: `peracrm_party_get()` (`peracrm_party` table)
2. **Linked properties**
   - metabox: `peracrm_render_properties_metabox()`
   - source: `peracrm_client_property_list()` + counts from `crm_client_property`
3. **Username link**
   - metabox: `peracrm_render_account_metabox()`
   - source: user meta `crm_client_id` + optional legacy `crm_client.linked_user_id`
4. **Client health**
   - metabox: `peracrm_render_client_health_metabox()`
   - source: `peracrm_client_health_get()` (activity + reminder derived)
5. **Assigned advisor**
   - metabox: `peracrm_render_assigned_advisor_metabox()`
   - source: client post meta assignment keys
6. **Client profile**
   - metabox: `peracrm_render_client_profile_metabox()`
   - source: `_peracrm_*` post meta via helper APIs
7. **Advisor notes**
   - metabox: `peracrm_render_notes_metabox()`
   - source: `crm_notes` table with fallback post meta
8. **Reminders**
   - metabox: `peracrm_render_reminders_metabox()`
   - source: `crm_reminders` table with fallback post meta
9. **Timeline**
   - metabox: `peracrm_render_timeline_metabox()`
   - source merger: notes + reminders + activity
10. **Activity timeline**
   - metabox: `peracrm_render_activity_timeline_metabox()`
   - source: `crm_activity` table
11. **Deals**
   - metabox: `peracrm_render_deals_metabox()`
   - source: `peracrm_deals` table

## Actions per section

### Submenu page actions
- **Add note**
  - Request: POST `admin-post.php`
  - `action=peracrm_add_note`
  - nonce field: `peracrm_add_note_nonce` (`peracrm_add_note`)
  - params: `peracrm_client_id`, `peracrm_note_body`
  - handler: `peracrm_handle_add_note()`

### Edit-screen metabox actions (core operational actions)
- **Save profile** -> `peracrm_save_client_profile`
- **Reassign advisor** -> `peracrm_reassign_client_advisor`
- **Add note** -> `peracrm_add_note`
- **Add reminder** -> `peracrm_add_reminder`
- **Mark reminder done / dismiss** -> `peracrm_update_reminder_status` (or mark-done action)
- **Link user / unlink user** -> `peracrm_link_user` / `peracrm_unlink_user`
- **Save CRM status**
  - via normal post update (`save_post_crm_client`) with status nonce
  - optional dedicated handler `peracrm_save_party_status`
- **Create deal / update deal** -> `peracrm_create_deal` / `peracrm_update_deal`

### Explicitly absent on submenu Client View page
- No direct forms for: reminders create/complete, advisor reassignment, status update, property link/unlink, deal create/update (those live on edit-screen metaboxes).


## 3) Front-end replication checklist

## Reproduce 1:1 first
1. Access model parity:
   - CRM-level access + per-client assignment scoping logic.
2. Section ordering/content parity for current admin experience.
3. Data loaders and derived formatting:
   - health badge rules
   - timeline merge/sort/filter
   - notes/reminders paging strategy
4. Nonce and capability protections on every write path.
5. `peracrm_notice` semantics for UX parity on redirects.

## Can be simplified initially
1. Keep only one canonical “client page” (front-end) and omit duplicate submenu-vs-metabox split.
2. Start with notes/reminders/timeline/profile/assigned advisor; defer deal commission UX complexity.
3. Replace metabox submit-helper JS with straightforward `<form>` POSTs first.
4. Skip legacy linked-user custom table column path if user-meta path is sufficient.

## AJAX vs normal POST recommendation
### Keep normal POST initially
- save profile
- reassign advisor
- create/update deal (complex validation + redirects)
- save CRM status

### Good AJAX candidates
- add note
- add reminder
- reminder status transitions (done/dismiss)
- link/unlink user lookup UX
- timeline/filter pagination

Reason: these are interaction-heavy and benefit from in-place updates with lower navigation churn.


## 4) File map (key paths + what they own)

- `wp-content/mu-plugins/peracrm/inc/admin/admin.php`
  - Hook registrations for admin menu, metaboxes, admin-post actions.
- `wp-content/mu-plugins/peracrm/inc/admin/pages.php`
  - CRM submenu registration including `peracrm-client-view`.
- `wp-content/mu-plugins/peracrm/inc/admin/pages/client-view.php`
  - Submenu Client View controller + rendering.
- `wp-content/mu-plugins/peracrm/inc/admin/metaboxes.php`
  - Edit-screen section renderers and action-button form payloads.
- `wp-content/mu-plugins/peracrm/inc/admin/metaboxes/timeline.php`
  - Timeline assembly/filtering/normalization.
- `wp-content/mu-plugins/peracrm/inc/admin/actions.php`
  - POST handlers (note/reminder/profile/advisor/status/deals/user link).
- `wp-content/mu-plugins/peracrm/inc/repositories/notes.php`
  - Notes storage (table + fallback meta).
- `wp-content/mu-plugins/peracrm/inc/repositories/reminders.php`
  - Reminders storage (table + fallback meta).
- `wp-content/mu-plugins/peracrm/inc/repositories/activity.php`
  - Activity event reads/writes.
- `wp-content/mu-plugins/peracrm/inc/repositories/client_property.php`
  - Client-property linking.
- `wp-content/mu-plugins/peracrm/inc/repositories/party.php`
  - CRM status persistence (`peracrm_party`).
- `wp-content/mu-plugins/peracrm/inc/repositories/deals.php`
  - Deals persistence (`peracrm_deals`).
- `wp-content/mu-plugins/peracrm/inc/schema.php`
  - Custom table schema definitions.
- `wp-content/mu-plugins/peracrm/inc/helpers.php`
  - profile/advisor helpers + multisite target-blog switching helpers.
- `wp-content/mu-plugins/peracrm/inc/admin/assets.php`
  - Admin CSS enqueueing.
- `wp-content/themes/hello-elementor-child/page-crm-client.php`
  - Existing front-end CRM client page stub (currently minimal summary, links to wp-admin).


## 5) Obvious bugs / fragility risks

1. **Feature split confusion (submenu vs edit metaboxes)**
   - Requested “Client View” sections are mostly in edit metaboxes, not submenu page.
   - Risk of implementing FE from submenu only and missing operational actions.

2. **Add-note redirect from submenu returns to edit screen**
   - `peracrm_handle_add_note()` redirects to `get_edit_post_link()` not back to submenu URL.
   - UX inconsistency for users entering via `peracrm-client-view`.

3. **Multisite data consistency risk**
   - Party/deals use target-blog wrapper; notes/reminders/activity/client-property do not.
   - In multisite with `PERACRM_TARGET_BLOG_ID`, sections may read/write different blogs.

4. **Timeline metabox visibility restricted to admins**
   - Timeline metabox requires `manage_options`; non-admin CRM staff only get activity timeline/metabox subset.
   - Potential expectation mismatch for advisors.

5. **Legacy linked-user storage divergence**
   - Code supports both user meta and optional custom `crm_client.linked_user_id` column.
   - Divergence can create sync edge cases if one write path fails.

6. **Table-absence degraded behavior varies by section**
   - Some sections have fallback meta; others hard-fail to “Unavailable (missing table)”.
   - Operational parity differs across environments.
