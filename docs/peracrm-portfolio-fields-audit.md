# PeraCRM Audit (Pre-Implementation)
## Portfolio custom fields on `crm_client_property` (Option A)

Scope: audit-only for adding nullable per-portfolio relation columns on `{$wpdb->prefix}crm_client_property`.

Target fields:
- `floor_number`
- `net_size`
- `gross_size`
- `list_price`
- `cash_price`

---

## 1) Current DB schema creation code path

### Where `crm_client_property` schema is defined
- Schema SQL is defined in `peracrm_upgrade_schema_to()` in:
  - `wp-content/mu-plugins/peracrm/inc/schema.php`
- Table name is derived via `peracrm_table('crm_client_property')` and injected into this `CREATE TABLE` statement:
  - `id`, `client_id`, `property_id`, `relation_type`, `created_at`
  - unique key: `(client_id, property_id, relation_type)`
  - indexes on `(client_id, relation_type, created_at)` and `(property_id, relation_type, created_at)`.

### How migrations run
- Upgrade gate is `peracrm_maybe_upgrade_schema()`:
  - reads `get_option('peracrm_schema_version', 0)`
  - runs `peracrm_upgrade_schema_to(PERACRM_SCHEMA_VERSION, $installed)` when needed
  - updates the option to `PERACRM_SCHEMA_VERSION`.
- `dbDelta()` is called for `crm_client_property` inside `peracrm_upgrade_schema_to()`.
- Execution hooks:
  - `admin_init` for `manage_options`
  - `init` (priority 5) for logged-in users with either `manage_options` or `edit_crm_clients`.

### Versioning
- Schema version constant is `PERACRM_SCHEMA_VERSION` (currently `5`) in `wp-content/mu-plugins/peracrm/peracrm.php`.
- Schema version option key is `peracrm_schema_version`.

---

## 2) Current read/write helpers used by Linked Properties + Portfolio panel

Primary repository file:
- `wp-content/mu-plugins/peracrm/inc/repositories/client_property.php`

### `peracrm_client_property_link($client_id, $property_id, $relation_type)`
**Current behavior**
- Guards on table existence (`peracrm_client_property_table_exists()`).
- Uses prepared raw SQL `INSERT ... ON DUPLICATE KEY UPDATE created_at = created_at`.
- Inserts only `(client_id, property_id, relation_type, created_at)`.
- Sanitizes relation type via `sanitize_key()`.
- Returns boolean success.

**Impact of new columns**
- Existing calls keep working (new columns nullable).
- No way to set new field values at insert time yet; function signature currently does not accept attribute payload.

### `peracrm_client_property_unlink($client_id, $property_id, $relation_type)`
**Current behavior**
- Guards on table existence.
- Uses `$wpdb->delete()` with formats `%d, %d, %s`.
- Deletes by `(client_id, property_id, relation_type)`.
- Returns boolean success.

**Impact of new columns**
- No behavior change required.

### `peracrm_client_property_list($client_id, $relation_type, $limit = 200)`
**Current behavior**
- Guards on table existence.
- Uses prepared query with `SELECT *`.
- Returns `ARRAY_A` full rows ordered by `created_at DESC`.
- Current row shape already includes relation metadata (`id`, `client_id`, `property_id`, `relation_type`, `created_at`).

**Impact of new columns**
- New columns will automatically appear in returned rows because of `SELECT *`.
- Template/controller code reading only `property_id` remains backward compatible.

### Additional table consumers worth noting
- `wp-content/mu-plugins/peracrm/inc/favourites.php`:
  - add/remove/is_favourited use specific SQL statements.
  - `peracrm_favourite_list()` uses explicit `SELECT property_id, created_at`.
  - This is unaffected by adding nullable columns, but it will not expose them unless query is expanded.
- `wp-content/mu-plugins/peracrm/inc/admin/actions.php`:
  - `peracrm_admin_get_client_property_count()` does `COUNT(*)` by `client_id` + `relation_type`.

---

## 3) Exact data flow for rendering portfolio-linked properties on `/crm/client/{id}`

### Route and template entry
1. Route resolves to CRM client view via router in theme (`/crm/client/{id}`).
2. Template `wp-content/themes/hello-elementor-child/page-crm-client.php` reads:
   - `$client_id = pera_crm_client_view_get_client_id()`
   - `$access = pera_crm_client_view_access_state($client_id)`
   - `$data = pera_crm_client_view_load_data($client_id)`.

### Data loading
3. `pera_crm_client_view_load_data()` (theme helper) builds `property_groups`:
   - loops relation types `['favourite', 'enquiry', 'portfolio']`
   - calls `peracrm_client_property_list($client_id, $relation, 20)` for each
   - stores full rows under `$property_groups[$relation]`.

### Portfolio rendering
4. In `page-crm-client.php`:
   - `$portfolio_items = $property_groups['portfolio'] ?? []`
   - In the “Linked Properties” section, loops each relation, including `portfolio`.
   - For each row (`$item`), reads `property_id`, resolves project label + permalink, and renders a list item card with unlink form.

### Existing interaction model
5. Link/unlink is currently form-post (not AJAX):
   - hidden field `pera_crm_property_action` = `link` or `unlink`
   - nonce field `pera_crm_property_nonce` for action `pera_crm_property_action`
   - handled on `template_redirect` by `pera_crm_client_view_handle_property_actions()`.
6. Portfolio token generation is AJAX (separate concern):
   - JS in `js/crm.js` posts to `action=peracrm_create_portfolio_token`.

### Safest insertion point in UI
- Add the 5 field inputs inside each `portfolio` list item in the existing linked-properties loop in `page-crm-client.php` (around the `<li class="peracrm-linked-properties-grid__item">` block for relation `portfolio`).
- Keep non-portfolio relations (`favourite`, `enquiry`) unchanged.

---

## 4) Best migration strategy (safe for live site)

### Recommended column definitions
Add nullable columns on `crm_client_property`:
- `floor_number VARCHAR(20) NULL`
- `net_size DECIMAL(10,2) NULL`
- `gross_size DECIMAL(10,2) NULL`
- `list_price DECIMAL(14,2) NULL`
- `cash_price DECIMAL(14,2) NULL`

Optional:
- `currency CHAR(3) NULL DEFAULT 'USD'`

Audit note on currency:
- Deals already use currency columns with USD defaults (`peracrm_deals`), but `crm_client_property` has no currency field today.
- If list/cash prices are expected to be multi-currency per row, add `currency`; otherwise omit and treat USD as implied business default.

### Where to place schema change
- Update `$sql_client_property` `CREATE TABLE` block in `peracrm_upgrade_schema_to()` so `dbDelta()` can add columns safely.
- Bump `PERACRM_SCHEMA_VERSION` to trigger upgrade path on active sites.

### Why this is low-risk
- Existing rows remain valid with `NULL` values (no backfill required).
- Existing inserts that omit new columns continue to work.
- Existing readers using `SELECT *` can receive new columns without breakage.

### Query compatibility review
- `peracrm_client_property_list()` already uses `SELECT *` (good).
- Some table consumers enumerate columns (e.g., favourites list), but those do not need updating unless they must expose new fields.

### Indexing
- No new index required initially (fields are for display/edit, not filter/sort heavy queries).

---

## 5) Existing AJAX/security patterns to reuse

### Relevant CRM client-view AJAX endpoints (theme)
- `wp_ajax_peracrm_create_portfolio_token` → `pera_crm_create_portfolio_token_ajax()`.
- `wp_ajax_pera_crm_property_search` → `pera_crm_property_search_ajax()`.

### Nonce generation + transport
- Nonces are localized into `window.peraCrmData` in `pera_crm_enqueue_assets()`:
  - `propertySearchNonce`
  - `createPortfolioNonce`
- Source: `wp_localize_script('pera-crm-js', 'peraCrmData', ...)`.

### Access enforcement
- Client-scoped permission checks use `pera_crm_client_view_access_state($client_id)`.
- Manage-level checks use `pera_crm_client_view_can_manage()` and `is_user_logged_in()`.

### Response pattern
- JSON responses use `wp_send_json_success()` / `wp_send_json_error()` with HTTP status codes.

### MU plugin AJAX inventory
- No `wp_ajax_*` handlers found in `wp-content/mu-plugins/peracrm/inc`; client-view AJAX is implemented in the theme layer for this route.

---

## 6) Gotchas checklist

1. **Assumptions about table columns**
   - No strict row-column-count assumptions found in helper code.
   - Write helpers only insert base columns; this is fine with nullable additions.

2. **`SELECT column_list` that might need updates**
   - `peracrm_client_property_list()` uses `SELECT *` (auto-includes new columns).
   - `peracrm_favourite_list()` explicitly selects `property_id, created_at`; unaffected unless UI wants new fields there.

3. **JSON helpers that could be alternative storage**
   - `peracrm_json_encode()` / `peracrm_json_decode()` exist in `inc/helpers.php`.
   - `event_payload` JSON handling exists for timeline/activity.
   - For this initiative, direct columns are consistent with Option A and simpler to query/update.

4. **Caching/transients**
   - No transient/object-cache layer found for `crm_client_property` relation lookups in audited paths.
   - No cache invalidation changes appear required for this specific enhancement.

---

## Minimal Implementation Plan (no code yet)

### Files likely to change
1. `wp-content/mu-plugins/peracrm/inc/schema.php`
   - Extend `crm_client_property` schema SQL with five nullable columns.
2. `wp-content/mu-plugins/peracrm/peracrm.php`
   - Bump `PERACRM_SCHEMA_VERSION`.
3. `wp-content/mu-plugins/peracrm/inc/repositories/client_property.php`
   - Add update helper(s) for portfolio row attributes (or extend existing link helper signature).
4. `wp-content/themes/hello-elementor-child/inc/crm-client-view.php`
   - Add secure write endpoint/handler for saving portfolio row fields.
   - Reuse existing client access checks + nonce patterns.
5. `wp-content/themes/hello-elementor-child/page-crm-client.php`
   - Add 5 inputs under each portfolio-linked property row.
6. `wp-content/themes/hello-elementor-child/js/crm.js` (if using AJAX save UX)
   - Add inline-save behavior and feedback handling.
7. (Optional) `wp-content/themes/hello-elementor-child/inc/crm-router.php`
   - Localize additional nonce if new AJAX action is introduced.

### Proposed function signatures (minimal diff approach)
- Repository layer:
  - `peracrm_client_property_update_portfolio_fields(int $client_id, int $property_id, array $fields): bool`
  - (optional read helper) `peracrm_client_property_get(int $client_id, int $property_id, string $relation_type = 'portfolio'): array`
- Theme controller/AJAX:
  - `pera_crm_save_portfolio_property_fields_ajax(): void`
  - `add_action('wp_ajax_pera_crm_save_portfolio_property_fields', ...)`

### Why this shape
- Preserves existing link/unlink behavior.
- Adds narrow update pathway only for `relation_type='portfolio'`.
- Avoids broad signature changes at all existing call sites.

---

## Test Plan (for upcoming implementation)

1. **Schema upgrade safety**
   - Confirm `dbDelta` adds new columns on existing install without data loss.
   - Confirm fresh install includes columns.

2. **Backward compatibility**
   - Link/unlink portfolio property still works with unchanged forms.
   - Favourites/enquiries rendering remains unchanged.

3. **Field persistence**
   - Save each of 5 fields on a portfolio-linked row.
   - Reload `/crm/client/{id}` and verify persisted values.
   - Save partial payload (e.g., one field only) and ensure others remain unchanged.

4. **Validation and sanitization**
   - Reject invalid client/property IDs.
   - Ensure decimal fields normalize correctly and accept null/empty.
   - Confirm nonce and access checks block unauthorized writes.

5. **Portfolio token flow regression**
   - Existing “Create portfolio” AJAX flow remains functional.

6. **Data integrity checks**
   - Ensure updates are scoped to `(client_id, property_id, relation_type='portfolio')` only.
   - Ensure unlink removes row and its custom field data together.

---

## Command audit log used
- `rg -n "crm_client_property" wp-content/mu-plugins/peracrm`
- `rg -n "CREATE TABLE|dbDelta|schema_version|peracrm_schema_version" wp-content/mu-plugins/peracrm`
- `rg -n "add_action\(\s*'wp_ajax_|add_action\(\s*\"wp_ajax_" wp-content/mu-plugins/peracrm/inc`
- `rg -n "data-crm-linked-properties|pera_crm_property_search|peracrm_create_portfolio_token|wp_localize_script\(" wp-content/themes/hello-elementor-child/inc wp-content/themes/hello-elementor-child/js -g '*.{php,js}'`

