# PeraCRM Plugin-Mode Runtime Audit

Date: 2026-03-12
Scope: lifecycle, activation/deactivation hooks, schema install/upgrade behavior after MU->plugin runtime migration.

## Executive summary

**Status: FAIL**

Primary blockers before live plugin-mode testing:
1. Plugin bootstrap requires `inc/activity.php`, but plugin runtime tree does not contain that file.
2. Constant-guarding in plugin entrypoint can bind activation hooks to MU main file when MU loader runs first on the activation request.
3. Several table-existence checks are not wrapped in `peracrm_with_target_blog()`, creating multisite target-blog mismatch risk.

## Activation/deactivation hook map

- Plugin registers:
  - `register_activation_hook(PERACRM_MAIN_FILE, 'peracrm_activate')`
  - `register_deactivation_hook(PERACRM_MAIN_FILE, 'peracrm_deactivate')`
- Callback implementations are in `includes/install.php`:
  - `peracrm_activate()` -> roles/caps ensure, schema ensure, route registration, rewrite flush.
  - `peracrm_deactivate()` -> rewrite flush only.

Risk:
- `PERACRM_MAIN_FILE` is only defined if not already defined.
- MU runtime defines `PERACRM_MAIN_FILE` to the MU plugin file when MU loader executes.
- On first standard-plugin activation request, MU loader can still run first (plugin not yet active in option state), potentially causing activation/deactivation hook registration to target the MU file basename instead of `peracrm/peracrm.php`.

## Install/upgrade execution path

### Intended path (plugin mode)
- `peracrm.php` loads `includes/install.php`, registers hooks, then loads `inc/bootstrap.php`.
- Activation callback performs one-time install tasks.
- Ongoing requests run version-gated `peracrm_maybe_upgrade_schema()` on admin and privileged init hooks.

### Observed blockers
- `inc/bootstrap.php` requires `PERACRM_INC . '/activity.php'`.
- Plugin-owned runtime tree lacks `wp-content/plugins/peracrm/inc/activity.php`.
- This is a hard runtime fatal in true plugin-owned mode.

## DB tables expected vs created

### Created by current schema installer
- `{$prefix}crm_notes`
- `{$prefix}crm_reminders`
- `{$prefix}crm_activity`
- `{$prefix}crm_client_property`
- `{$prefix}peracrm_party`
- `{$prefix}peracrm_deals`
- plus push log via `peracrm_push_log_create_table()` => `{$prefix}crm_push_log`

### Consumed by repositories/services in scope
- Repositories/services use:
  - `crm_notes`, `crm_reminders`, `crm_activity`, `crm_client_property`, `peracrm_party`, `peracrm_deals`, `crm_push_log` (all covered)
  - `crm_client` via `user_link_service` (legacy table, not created by schema installer)

Finding:
- Required migrated repository/service tables are present in install code.
- `crm_client` is intentionally legacy/optional, but probes use `SHOW COLUMNS` directly and can emit DB errors if table absent.

## Roles/caps lifecycle findings

- Roles/caps are applied on activation (when callback runs) and on every `admin_init` for `manage_options` users.
- Role/cap operations are additive/idempotent (`add_role` if missing, `add_cap` repeatedly safe).
- Multisite context uses `peracrm_with_target_blog()` inside role setup.

Risk:
- If activation callback is missed due to main-file constant collision, roles still eventually apply for admins on `admin_init`, but timing is deferred.

## Rewrite lifecycle findings

- Activation: explicitly registers CRM routes (if function available) then flushes rewrite rules.
- Deactivation: flushes rewrite rules.
- Runtime routing registers rewrite rules on `init` every request (normal WP pattern), but flushing is activation/deactivation only.

Assessment:
- Flush behavior is correct and not repeatedly executed per request.

## Version gating / idempotence

- Schema gate option: `peracrm_schema_version`, compared against `PERACRM_SCHEMA_VERSION`.
- Global migration marker: `peracrm_migration_v4_done` to avoid repeated v4 taxonomy migration.
- Main schema creation uses `dbDelta` and thus is generally idempotent.
- Additional `closed_reason` column check/ALTER is guarded by `SHOW COLUMNS` check.

Risk:
- `update_option('peracrm_schema_version', PERACRM_SCHEMA_VERSION)` occurs after `peracrm_upgrade_schema_to(...)` without checking internal failures, so partial failures can still advance version marker if no fatal occurred.

## Pre-go-live risk register

1. **Missing plugin runtime file**: `inc/activity.php` missing in plugin tree but required by bootstrap. (blocking)
2. **Activation hook target collision**: `PERACRM_MAIN_FILE` may stay bound to MU file during first plugin activation request. (high)
3. **Target-blog inconsistency**: not all table-existence checks use target-blog wrapper in multisite. (medium)
4. **Version marker optimistic update**: schema version bumped regardless of granular DB success checks. (medium)
5. **Legacy table probe noise**: `crm_client` probes can produce SQL warnings when absent. (low-medium)

## Exact files needing follow-up

1. `wp-content/plugins/peracrm/peracrm.php`
2. `wp-content/plugins/peracrm/inc/bootstrap.php`
3. `wp-content/plugins/peracrm/inc/schema.php`
4. `wp-content/plugins/peracrm/includes/install.php`
5. `wp-content/plugins/peracrm/inc/services/user_link_service.php`
6. `wp-content/plugins/peracrm/inc/repositories/notes.php`
7. `wp-content/plugins/peracrm/inc/repositories/reminders.php`
8. `wp-content/plugins/peracrm/inc/repositories/client_property.php`
9. `wp-content/plugins/peracrm/inc/favourites.php`
10. `wp-content/plugins/peracrm/inc/repositories/deals.php`
11. `wp-content/plugins/peracrm/inc/repositories/party.php`
12. `wp-content/mu-plugins/peracrm.php`
13. `wp-content/mu-plugins/peracrm/peracrm.php`

## Minimal patch plan (recommended order)

1. **Fix runtime bootstrap blocker**
   - Add/restore plugin-owned `inc/activity.php` or remove stale require if superseded.
2. **Harden activation hook ownership**
   - In plugin entrypoint, use literal `__FILE__` for activation/deactivation registration (not overrideable constant), and isolate plugin constants from MU-defined ones where needed.
3. **Normalize target-blog table checks**
   - Wrap table-existence and column-existence probes in `peracrm_with_target_blog()` consistently for multisite correctness.
4. **Guard optimistic schema version bump**
   - Introduce success/error bookkeeping before advancing `peracrm_schema_version`.
5. **Silence legacy-table probe errors**
   - Add explicit table-exists checks before `SHOW COLUMNS` against `crm_client`.
6. **Re-verify rewrite and role lifecycle**
   - Confirm activation/deactivation hooks fire under plugin-owned mode with MU loader present.
