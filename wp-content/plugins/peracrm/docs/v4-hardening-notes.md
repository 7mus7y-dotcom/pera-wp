# PeraCRM v4 hardening notes

## What was fixed

1. **Pipeline stage validation + grouping alignment**
   - Added `peracrm_pipeline_stage_options()`/`peracrm_pipeline_allowed_stage_values()` as single source of truth for pipeline stage labels + validation.
   - Pipeline board now groups records by `peracrm_party.lead_pipeline_stage` (via `peracrm_party_get`) instead of `_peracrm_status` meta.
   - Stage move actions now write through `peracrm_party_upsert_status()`.

2. **Roles/capability runtime consistency**
   - Added compatibility wrapper `peracrm_get_advisor_users()` forwarding to `peracrm_get_staff_users()`.
   - Added `peracrm_user_can_access_crm()` and aligned admin menu capability usage.

3. **Deals schema assertion fallback**
   - After `dbDelta($sql_deals)`, schema upgrade checks for `closed_reason` column.
   - If missing, runs idempotent fallback `ALTER TABLE ... ADD COLUMN closed_reason ...`.

4. **Migration v4 safety + closed reason preservation**
   - Migration now runs in target-blog context and exits safely if required tables are missing.
   - Legacy party dispositions `lost_price|lost_finance|lost_competitor` now migrate to latest closed/lost deal `closed_reason` when unset.
   - If no deal exists, fallback stores `_peracrm_migrated_closed_reason` on the CRM client post.

5. **REST route hardening**
   - Added route argument schemas for `page` and `per_page` (min/max/default).

## Schema fallback applied
- `closed_reason` column assertion + fallback is in `inc/schema.php` upgrade routine.

## Behavior change in pipeline grouping
- Board columns remain stage-based, but the data source is now party stage (`peracrm_party`) rather than legacy `_peracrm_status` meta.

## REST call example (cookie-auth + nonce)

```bash
curl 'https://example.com/wp-json/peracrm/v1/leads?page=1&per_page=20' \
  -H 'X-WP-Nonce: <wp_rest_nonce>' \
  -H 'Cookie: wordpress_logged_in_<hash>=<cookie_value>'
```

> Nonce is expected to be the standard `wp_rest` nonce (e.g. `wpApiSettings.nonce` in WP admin).

## Regression checklist updates

### Expected stages in existing installs
- Pipeline stage options are sourced from `peracrm_lead_stage_options()` when available (v4 canonical source).
- If canonical stage helpers are unavailable, pipeline falls back to legacy stages (`enquiry`, `active`, `dormant`, `closed`) and dynamically includes any additional stages already found in party data.
- Unknown/missing client stages are displayed in the default pipeline column (`new_enquiry` when available, otherwise `enquiry`) without rewriting stored values.

### Stage move write-through behavior
- Stage moves always write through `peracrm_party_upsert_status()`.
- If the party write helper is unavailable or write fails, stage move now returns a failure notice (`stage_failed`) and does not report success.
- Bulk stage moves count failed writes in `bulk_failed`; they are no longer silent no-ops.

### Staff validation rules
- Staff users are validated using `peracrm_user_is_staff()`.
- Staff roles are `employee`, `manager`, and `administrator`.
- Admin pipeline filters/exports validate selected staff IDs using the same staff helper.

### No silent no-op guarantee
- Pipeline single stage move only redirects with `stage_moved` after a successful party status write.
- Pipeline bulk stage move only increments success counters after a successful party status write.
- Failed/missing write-path conditions are explicitly counted or surfaced.
