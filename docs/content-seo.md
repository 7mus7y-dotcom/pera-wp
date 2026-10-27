# Content and SEO

The child theme owns public head output. `inc/modules/seo-loader.php` loads `inc/seo-all.php` for general pages/posts/taxonomies, `inc/seo-property.php` for single Property CPT pages, `inc/seo-property-archive.php` for property/taxonomy archives, and offer/schema helpers. `inc/schema.php` coordinates reusable schema rules and duplicate suppression. Pera Multilingual supplies translated approved fields and language SEO; do not bypass its readers on translated pages.

## Content metadata and images

Manual SEO fields include `seo_title`, `seo_meta_description`, `seo_faq_v2`, and `seo_social_image` where the relevant code/ACF context supports them. Normalize and escape at output; schema FAQ must reflect visible, valid content rather than hidden/generated extras.

For posts/pages, general SEO resolves an explicit `seo_social_image` before context fallbacks such as featured image. For Property CPT social output, the property `main_image` is the fallback for `seo_social_image` (then featured image if no usable main image). Keep `main_image` populated and public; do not expose internal `project_name` through title, description, alt, or schema generation.

Taxonomy archive copy and social images are term metadata owned by `inc/taxonomy-meta.php` and the archive SEO helpers. Property archive-wide copy comes from the deliberately private page with slug `property-archive-seo-settings`; do not treat arbitrary pages as settings.

## Canonical, robots, and schema ownership

- `seo-all.php` owns canonical/description/Open Graph/Twitter/schema outside single properties and delegates property-archive canonical logic where applicable.
- `seo-property.php` exclusively owns single-property head output and public property/FAQ/breadcrumb schema.
- `seo-property-archive.php` owns clean property and supported taxonomy archive titles, descriptions, social data, canonicals, and eligible FAQ/archive schema.
- Search, attachment, query-driven archive/filter, and other explicitly detected faceted URLs are `noindex,follow`; filtered archive canonicals discard filter parameters. Clean indexable archives and their paginated canonical paths must remain distinct.
- `/portal/quote/{token}/` noindex belongs to Pera Portal; public portfolio token protections belong to theme token routing. See [portal.md](portal.md).

Only one module should emit a given canonical or schema entity. Preserve the existing context guards and `$GLOBALS['pera_schema_*_emitted']` duplicate controls. Monetary structured data remains canonical USD; see [currency.md](currency.md). Translate only the fields registered in [multilingual.md](multilingual.md), leaving media and identifiers unchanged.

## Validation checklist

1. View source, not only the DOM inspector: exactly one title, description, canonical, robots directive, OG/Twitter set, and intended JSON-LD entity.
2. Check a normal page/post, single property, clean main archive, supported taxonomy archive, paginated archive, filtered/query archive, and translated equivalent relevant to the change.
3. Validate canonical paths and language prefixes; ensure query filters are noindex and do not leak into canonical URLs.
4. Verify explicit social image, property `main_image` fallback, featured fallback, URL/alt/dimensions, and a missing-image case.
5. Parse JSON-LD and confirm FAQ/schema content is visible, deduplicated, escaped, and contains no internal fields.
6. Run `php -l` on changed PHP plus the focused multilingual/property tests; explain any browser, crawler, or rich-results checks not performed.
