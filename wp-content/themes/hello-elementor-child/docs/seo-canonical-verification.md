# Property archive canonical verification

## Findings
- `pera_property_archive_base_url()` only returns a term link (when `is_tax()`) or the `property` post type archive link; it does not append pagination or query strings. This means its output is a clean base URL. (Defined in `inc/seo-all.php`.)
- `pera_property_archive_canonical_url()` builds a canonical URL by:
  - verifying the request is a property archive/taxonomy context,
  - using `pera_property_archive_base_url()` if available (or term link/archive link otherwise),
  - adding `page/{N}/` only when the resolved `paged` value is > 1.
- Both `inc/seo-all.php` and `inc/seo-property-archive.php` now prefer `pera_property_archive_canonical_url()` for property archive/taxonomy contexts, with fallbacks only when the helper returns an empty string.
- Filtered archive detection (`pera_is_filtered_property_archive()`) is a thin wrapper around `pera_property_archive_is_filtered_request()`; both code paths now share the same filtered detection logic.

## Risks reviewed
- **Double `/page/N/` risk:** Not observed. The base URL helper returns only the term link or post type archive link without pagination, and the canonical helper appends pagination once when needed.
- **Filtered policy mismatch:** Cleared. The canonical helper always preserves the current page number, so filtered requests canonicalize to the clean URL **for the same page number**, matching the documented rule in `inc/seo-property-archive.php`.
- **Duplicate canonical tags:** Both `inc/seo-all.php` and `inc/seo-property-archive.php` still output `<link rel="canonical">` on property archives. This duplication pre-existed and was not modified in the consolidation.

## Recommended patch
- No code change required. Current behavior matches the stated rule: “Canonical always points to the clean URL for the current page number (taxonomy-aware).”

## Smoke test URLs
- `/property/` → canonical `/property/`
- `/property/page/2/` → canonical `/property/page/2/`
- `/district/<term>/page/3/` → canonical `/district/<term>/page/3/`
- Filtered: `/property/page/2/?min_price=...` → canonical `/property/page/2/`
- Filtered taxonomy: `/district/<term>/page/3/?property_tags[]=...` → canonical `/district/<term>/page/3/`

