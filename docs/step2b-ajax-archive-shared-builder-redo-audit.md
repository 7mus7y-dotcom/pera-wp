# Step 2b Audit — AJAX archive shared builder redo (safe)

## What caused `taxonomy => array` risk in prior attempt

The fatal (`urlencode(): string expected, array given` inside `WP_Query->parse_tax_query()`) is consistent with a tax clause where `taxonomy` was an array instead of a string.

In this AJAX flow, taxonomy context comes from hidden POST fields:

- `$_POST['archive_taxonomy']` (from hidden `name="archive_taxonomy"`)
- `$_POST['archive_term_id']` (from hidden `name="archive_term_id"`)

If either payload is duplicated in the DOM, serialized with repeated keys, or posted with bracket notation (`archive_taxonomy[]`), PHP can present these as arrays. If code then forwards that value directly (or partially normalized) into `taxonomy_context['taxonomy']`, shared query assembly can emit a tax clause with `taxonomy => array(...)`.

## Input audit for taxonomy-related payloads

### `archive_taxonomy` / `archive_term_id`
- Expected as scalar hidden fields on archive page.
- Can still arrive as arrays in real-world requests due to duplicate fields, JS serialization behavior, or crafted requests.
- Must be forced to a scalar before `sanitize_key`/`absint`.

### `district` / `property_tags` / `property_type`
- SSR form uses `district[]` and `property_tags[]` checkboxes (array by design).
- AJAX can still submit a scalar for these when only one value is emitted by client code or custom callers.
- For SSR parity and robustness, handler should accept scalar **or** array and normalize to sanitized slug arrays (for district/tags) and a single sanitized slug (for type).

## Shared builder contract check

`pera_property_archive_build_args_from_context( $ctx, $overrides )` currently builds all core query pieces (tax/meta/keyword/sort) from a context payload, including `taxonomy_context`.

Critical contract points for safety:

- `taxonomy_context['taxonomy']` must end as a string (`''` when invalid).
- `taxonomy_context['term_id']` must end as int (`0` when invalid).
- Taxonomy-context clause is added only when taxonomy is non-empty string and term_id > 0.

This prevents invalid type leakage into WP tax query args even if caller context is malformed.

## Minimal fix strategy

- Normalize AJAX inputs aggressively:
  - force scalar for `archive_taxonomy` and `archive_term_id` before sanitize/cast,
  - build `taxonomy_context` only when taxonomy exists, belongs to `property`, and term_id > 0,
  - accept scalar-or-array for `district` and `property_tags` and sanitize as arrays.
- Move AJAX query assembly to shared builder:
  - build SSR-aligned `$ctx`,
  - call `pera_property_archive_build_args_from_context( $ctx, $overrides )`,
  - keep facet/grid/pagination/JSON key shape unchanged.
- Add belt-and-braces in shared builder:
  - if `taxonomy_context['taxonomy']` is array, reduce to first scalar,
  - finalize taxonomy as string and term_id as int,
  - only emit taxonomy-context tax clause when taxonomy string is non-empty and term_id > 0.
