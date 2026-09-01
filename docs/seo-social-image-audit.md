# SEO Social Image Audit

## Executive summary

The repository has three conditionally routed custom SEO implementations:

1. Singular `property` posts load `inc/seo-property.php`.
2. Property archives and property taxonomies load `inc/seo-property-archive.php`.
3. All other frontend contexts load `inc/seo-all.php`.

The router runs on `wp` at priority 1 and returns after selecting one implementation, so these modules should not compete with each other on a normal request.

The most important finding is that singular properties do **not** currently read `seo_social_image`. Their social-image chain is:

```text
formatted ACF main_image array
-> WordPress featured image
-> no image
```

Posts, pages, the front page, and the posts page use:

```text
seo_social_image
-> featured image
-> PERA_SEO_DEFAULT_OG_IMAGE_ID
-> no image
```

`PERA_SEO_DEFAULT_OG_IMAGE_ID` defaults to `0` in tracked code, so the effective last fallback is no image unless production defines the constant earlier.

Principal risks:

- A property change that omits the current featured-image fallback would be a regression.
- No repository-owned registration, admin field definition, save handler, or sanitizer for `seo_social_image` was found.
- Arbitrary nonnumeric strings are treated as URLs and only passed through `esc_url()`; reachability, MIME type, dimensions, and attachment existence are not validated.
- Singular properties advertise `1200 x 630` when a URL exists but dimensions cannot be resolved, even if those are not the real dimensions.
- Third-party SEO plugins can duplicate theme output. Only property archive OG/Twitter output has a partial Yoast/Rank Math guard.
- No code emits `og:image:secure_url` or image MIME/type metadata.
- Property archives emit image URLs without image alt or dimension tags.

## File and function map

| File | Function/method | Hook/filter | Responsibility | Affected content |
|---|---|---|---|---|
| `wp-content/themes/hello-elementor-child/inc/modules/seo-loader.php` | Anonymous callback | `wp`, priority 1 | Routes a request to one SEO module. | All frontend requests |
| `wp-content/themes/hello-elementor-child/inc/seo-property.php` | `pera_property_get_social_image()` | Direct call | Resolves `main_image`, then featured image, including alt and attachment dimensions. It does not inspect `seo_social_image`. | Singular properties and property schema |
| `wp-content/themes/hello-elementor-child/inc/seo-property.php` | `pera_property_get_schema_images()` | Direct call | Starts the property schema image list with the social image and then adds gallery images. | Singular properties |
| `wp-content/themes/hello-elementor-child/inc/seo-property.php` | Anonymous callback | `wp_head`, priority 12 | Emits description, canonical, OG, Twitter, and JSON-LD. | Singular properties |
| `wp-content/themes/hello-elementor-child/inc/seo-all.php` | `pera_seo_all_get_image()` | Direct call | Resolves featured-image URL, dimensions, ID, and alt. | Non-property contexts with a post ID |
| `wp-content/themes/hello-elementor-child/inc/seo-all.php` | `pera_seo_all_resolve_social_image_from_value()` | Direct call | Normalizes ACF arrays, attachment IDs, numeric strings, or URL-like strings. | Posts, pages, posts page, front page, categories, and tags |
| `wp-content/themes/hello-elementor-child/inc/seo-all.php` | `pera_seo_all_get_post_manual_social_image()` | Direct call | Reads formatted ACF `seo_social_image`, then raw post meta. | Front page, pages, posts page, and singular posts |
| `wp-content/themes/hello-elementor-child/inc/seo-all.php` | `pera_seo_all_get_term_manual_social_image()` | Direct call | Tries several ACF term references and raw term meta. | Blog categories and tags |
| `wp-content/themes/hello-elementor-child/inc/seo-all.php` | `pera_seo_default_image()` | Direct call | Resolves the attachment configured by `PERA_SEO_DEFAULT_OG_IMAGE_ID`. | General contexts and property archives |
| `wp-content/themes/hello-elementor-child/inc/seo-all.php` | `pera_seo_all_get_context_key()` | Direct call | Classifies homepage, post, page, posts page, category, tag, date, author, and fallback contexts. | Non-property requests |
| `wp-content/themes/hello-elementor-child/inc/seo-all.php` | Anonymous callback | `wp_head`, priority 10 | Emits general description, canonical, OG, Twitter, and context schema; skips property contexts and 404s. | Non-property, non-404 requests |
| `wp-content/themes/hello-elementor-child/inc/seo-property-archive.php` | `pera_property_archive_resolve_image_from_acf_value()` | Direct call | Normalizes an archive image array, ID, numeric value, or string. | Property archives/taxonomies |
| `wp-content/themes/hello-elementor-child/inc/seo-property-archive.php` | `pera_property_archive_taxonomy_manual_social_image()` | Direct call | Tries `seo_social_image`, `social_image`, `seo_og_image`, `og_image`, and `twitter_image`. | Property taxonomies |
| `wp-content/themes/hello-elementor-child/inc/seo-property-archive.php` | `pera_property_archive_social_image()` | Direct call | Resolves taxonomy/archive images and the site default. | Property archives/taxonomies |
| `wp-content/themes/hello-elementor-child/inc/seo-property-archive.php` | Anonymous callback | `pre_get_document_title`, priority 20 | Owns archive/taxonomy title formulas. | Property archives/taxonomies |
| `wp-content/themes/hello-elementor-child/inc/seo-property-archive.php` | Anonymous callback | `wp_head`, priority 10 | Emits description/canonical and conditionally OG/Twitter. | Property archives/taxonomies |
| `wp-content/themes/hello-elementor-child/inc/modules/performance.php` | Anonymous callback | `init`, priority 20 | Removes WordPress core `rel_canonical`. | Frontend globally |
| `wp-content/themes/hello-elementor-child/inc/seo-all.php` | Anonymous callback | `after_setup_theme`, priority 20 | Removes Hello Elementor's description callback when present. | Theme-wide |
| `wp-content/themes/hello-elementor-child/inc/modules/sitemaps.php` | Anonymous filters | Core sitemap filters | Limits sitemap types and honors common noindex metadata; does not add image entries. | Posts, pages, properties, selected terms |
| `wp-content/plugins/pera-multilingual/includes/class-seo.php` | `hooks()`, `alternates()`, `canonical()`, `document_title()` | `wp_head` priority 2, Rank Math canonical, document-title filter | Outputs hreflang and translates titles; does not emit or translate social images. | Translated routes |

No repository-defined Yoast, Rank Math, AIOSEO, Open Graph image, or Twitter image filters were found. The multilingual Rank Math compatibility filter affects only canonical URLs.

## Resolution flow by content type

| Context | Exact current chain | Notes |
|---|---|---|
| Singular property | `main_image` array URL/ID -> featured image -> no image | `seo_social_image` and the global default are not read. `twitter:card` remains `summary_large_image` even with no image tag. |
| Singular standard post | `seo_social_image` -> featured image -> global default -> no image | An attachment-backed selected image can also populate Article/BlogPosting JSON-LD. |
| Singular static page | `seo_social_image` -> featured image -> global default -> no image | Custom page templates follow the same `static_page` branch. |
| Static front page | Front-page `seo_social_image` -> front-page featured image -> global default -> no image | The front page is classified as `homepage`. |
| Posts page | Posts-page `seo_social_image` -> posts-page featured image -> global default -> no image | The callback replaces the queried ID with `page_for_posts`. |
| Blog category/tag | Term `seo_social_image` -> term featured-image helper -> possible generic post-thumbnail lookup using the term ID -> global default -> no image | The generic thumbnail step can accidentally collide with a post ID. |
| Blog date/author archive | Possible generic post-thumbnail lookup using the queried numeric ID -> global default -> no image | No manual image branch exists. Author IDs can collide with post IDs. |
| Main property archive | Private settings-page `seo_social_image` on a clean archive -> global default -> no image | Filtered/paginated variants can bypass the clean-archive setting. |
| Property taxonomy | First valid manual field among five supported names -> term featured image -> global default -> no image | Applies to the configured property taxonomies. |
| Other taxonomy | Usually generic thumbnail lookup by queried numeric ID -> global default -> no image | Only category and tag have a dedicated term-image branch. |
| Other singular CPT | Featured image -> global default -> no image | `seo_social_image` is not checked for fallback custom post types. |
| Other post-type archive | Usually global default -> no image | No dedicated archive image source exists. |
| Search | Global default -> no image | Search is noindexed. |
| 404 | No custom social metadata | The general head callback returns immediately. |
| Translated variants | Same image chain as canonical route | Image fields are not translated; canonical media is reused. |

## Storage findings

### `seo_social_image`

The generic runtime resolver accepts:

- an ACF-style array with `ID` or lowercase `id`;
- optional array `url` and `alt`;
- an integer attachment ID;
- a numeric-string attachment ID;
- an arbitrary nonempty string treated as a URL candidate;
- empty or unsupported values, which resolve to no image.

Posts/pages first call formatted `get_field('seo_social_image', $post_id)`. If that value is PHP-empty, they call `get_post_meta(..., true)`. WordPress metadata APIs normally unserialize stored values, so serialized arrays written through those APIs can arrive as arrays. The resolver does not itself call `maybe_unserialize()`.

No tracked field registration, ACF JSON definition, meta box, save handler, or sanitizer for `seo_social_image` was found. The repository therefore cannot establish its production ACF field type, return format, admin location rules, or the formats of actual stored rows.

### `main_image`

Singular property SEO expects formatted `get_field('main_image')` to be an ACF image array with `ID`, `url`, and `alt`. The single-property template has the same explicit expectation. Other display helpers defensively understand IDs and, in one case, URL strings.

It is likely that the raw ACF database value is an attachment ID while the formatted value is an image array, but that is an inference: the field definition and production database are not present in this checkout.

## Image validity and supporting metadata

- Missing/blank general manual values fall through to featured image and default.
- Missing/non-array property `main_image` falls through to featured image.
- Invalid/deleted attachment IDs normally produce no attachment URL and allow the outer fallback chain to continue.
- A stale URL already present in an ACF array is trusted even if the attachment ID is invalid.
- Nonnumeric strings are accepted as URL candidates; the code does not require an absolute HTTPS URL, check reachability, or verify image content.
- No minimum size or aspect ratio is enforced.
- General attachment-backed images can supply width, height, and attachment alt.
- General URL-only images omit dimensions.
- Property images with unknown dimensions are advertised as `1200 x 630`.
- Property archive images do not emit alt or dimensions.
- No output derives or emits MIME type, `og:image:type`, or `og:image:secure_url`.
- No HTTP-to-HTTPS normalization occurs.
- If the global default is absent or invalid, the result is no image.

## JSON-LD, sitemaps, and multilingual behavior

Singular property schema calls the same property social-image helper, then appends unique `main_gallery` URLs. A future helper change will therefore affect both social tags and property JSON-LD unless those concerns are separated.

Singular post JSON-LD adds an `ImageObject` only when the chosen image has both a nonzero attachment ID and a URL. A URL-only explicit image can appear in OG/Twitter but not in Article/BlogPosting JSON-LD.

Specialized page schema can use the selected `$img_url`; citizenship schema also has a hard-coded attachment fallback that is schema-specific and is not part of the social-image chain.

The sitemap module limits post and taxonomy providers and filters noindex entries. It does not inspect either image field or create image-sitemap extensions.

Pera Multilingual translates approved text such as `seo_title` and `seo_meta_description`, but neither `main_image` nor `seo_social_image` is in its approved field contracts. Translated routes therefore share canonical image media.

## Duplicate-output assessment

The three theme SEO modules are mutually routed, so they should not overlap with each other in normal execution. WordPress core canonical output is removed, and the known Hello Elementor description callback is removed when present. The theme filters the core document title rather than printing an additional `<title>`.

Third-party duplication remains possible:

| Context | Guard | Remaining risk |
|---|---|---|
| Property archive/taxonomy | Suppresses custom OG/Twitter when Yoast or Rank Math is detected | Custom canonical/description can still duplicate plugin output; AIOSEO is absent from this guard. |
| Singular property | No SEO-plugin guard | Theme and plugin can both emit OG/Twitter, canonical, description, title behavior, and schema. |
| General contexts | No SEO-plugin guard | The same duplication is possible. |
| Multilingual plugin | Rank Math canonical filter only | It changes a plugin canonical but suppresses neither system. |

The tracked plugin directories do not contain Yoast, Rank Math, AIOSEO, or a generic Open Graph plugin. This does not prove that none is active in production.

## Caching implications

The social-image helpers have no custom transient or static-HTML cache. They rely on normal WordPress post/meta/object caching.

The theme sends public cache headers for property archives (5 minutes), property singles (10 minutes), district taxonomies (15 minutes), blog posts (30 minutes), and the front/posts page (5 minutes). Filtered requests and logged-in users are marked non-cacheable.

The multilingual storage layer uses the `pera_ml` object-cache group for translated text, but social-image fields are not translated and do not directly use this cache.

No repository-owned SEO page cache, CDN purge hook, generated static HTML, or translated-page HTML cache was found. After a future change, clear host/plugin full-page caches and CDN/reverse-proxy HTML caches for canonical and translated property URLs, account for browser/proxy TTLs, and re-scrape URLs in social-platform debuggers. A code-only runtime fallback should not require metadata writes or bulk copying.

## Recommended future implementation

The proposed property-only scope is the safest direction, but the literal chain needs clarification. The current property fallback after `main_image` is the featured image, not the site default.

If “existing default fallback” means existing property behavior, the accurate chain is:

```text
valid explicit seo_social_image
-> valid main_image
-> valid featured image
-> no image
```

If adding the configured site default is separately approved, use:

```text
valid explicit seo_social_image
-> valid main_image
-> valid featured image
-> valid PERA_SEO_DEFAULT_OG_IMAGE_ID
-> no image
```

The narrowest implementation should:

1. Change only the singular-property resolver.
2. Normalize explicit `seo_social_image` through a shared version of the existing flexible resolver.
3. Return it only when it has a usable, validated URL.
4. Otherwise execute existing `main_image` and featured-image behavior unchanged.
5. Preserve no-image behavior unless the site default is explicitly approved.
6. Confirm that property JSON-LD should inherit the same preference.
7. Never copy `main_image` into `seo_social_image` or write metadata.

“Valid” should ideally mean a live image attachment or an absolute HTTP(S) URL accepted by `wp_http_validate_url()`. Attachment MIME should begin with `image/`; real dimensions should be used when available; unknown URL-only dimensions should not be fabricated. Minimum social dimensions should be treated as a separate product decision rather than silently rejecting current images.

All non-property behavior should remain unchanged.

## Proposed verification plan

### Repository checks

```bash
rg -n "seo_social_image|main_image|og:image|twitter:image|twitter:card" \
  wp-content/themes/hello-elementor-child \
  wp-content/plugins/pera-multilingual

rg -n "add_action.*wp_head|add_filter.*(opengraph|twitter)|rel_canonical" \
  wp-content -g '*.php'

php -l wp-content/themes/hello-elementor-child/inc/seo-property.php
php -l wp-content/themes/hello-elementor-child/inc/seo-all.php
php -l wp-content/themes/hello-elementor-child/inc/seo-property-archive.php
```

### Read-only production storage inspection

```bash
wp post meta get <PROPERTY_ID> seo_social_image
wp post meta get <PROPERTY_ID> main_image
wp post meta get <PROPERTY_ID> _thumbnail_id
```

```bash
wp eval '
$id = <PROPERTY_ID>;
var_export([
  "seo_formatted"  => function_exists("get_field") ? get_field("seo_social_image", $id) : null,
  "seo_raw_acf"    => function_exists("get_field") ? get_field("seo_social_image", $id, false) : null,
  "seo_raw_meta"   => get_post_meta($id, "seo_social_image", true),
  "main_formatted" => function_exists("get_field") ? get_field("main_image", $id) : null,
  "main_raw_acf"   => function_exists("get_field") ? get_field("main_image", $id, false) : null,
  "main_raw_meta"  => get_post_meta($id, "main_image", true),
  "featured_id"    => get_post_thumbnail_id($id),
]);
'
```

### Duplicate callback/plugin checks

```bash
wp plugin list --status=active

wp eval '
global $wp_filter;
foreach (["wp_head", "pre_get_document_title", "get_canonical_url"] as $hook) {
    echo "\n== $hook ==\n";
    if (!empty($wp_filter[$hook])) {
        print_r($wp_filter[$hook]->callbacks);
    }
}
'
```

### Frontend checks

```bash
curl -fsSL 'https://example.com/property/example/' |
  rg -o '<meta[^>]+(?:og:image|twitter:image|twitter:card)[^>]*>|<link[^>]+canonical[^>]*>|<meta[^>]+description[^>]*>'

curl -fsSL 'https://example.com/property/example/' |
  rg -c 'property="og:image"'

curl -fsSI 'https://example.com/property/example/' |
  rg -i 'cache-control|age|expires|vary|cf-cache-status|x-cache'
```

Test representative properties with an explicit image, only `main_image`, only featured image, an invalid/deleted explicit attachment, and no candidates. Repeat for a standard post, page, front page, posts page, property archive, filtered archive, property taxonomy, ordinary archives, search, 404, and translated variants.

Assert exactly one `og:image` and `twitter:image` where appropriate; explicit property image wins; invalid explicit data falls through; `main_image` still wins over featured image; non-property tags remain unchanged; property schema ordering is intentional; and cache headers/CDN state are understood.

## Risks and open questions

### Verified

- Singular properties ignore `seo_social_image`.
- Their current chain is `main_image -> featured image -> no image`.
- General post/page contexts use `seo_social_image -> featured image -> configured default -> no image`.
- The tracked default ID is `0`.
- No secure-image URL or MIME tag is emitted.
- No image sitemap integration exists.
- Image fields are not translated.
- The property social image affects property JSON-LD.
- Core canonical output is removed.
- Custom SEO modules are mutually routed.
- External SEO plugins can still duplicate output.

### Requires production confirmation

- The raw/formatted ACF configuration and real stored formats for both fields.
- Whether the field group for `seo_social_image` is database-defined.
- Which SEO or social plugins are active.
- Whether production overrides `PERA_SEO_DEFAULT_OG_IMAGE_ID`.
- Which host, CDN, and full-page caches are active.
- Whether URL-only images should enter article JSON-LD.
- Whether properties should gain a site default after their current featured-image fallback.

## Audit limitations

The checkout does not contain a working WordPress installation or WP-CLI, so production database rows, ACF configuration, active plugins, rendered frontend HTML, and external caches could not be inspected. Findings above distinguish source-verified behavior from production assumptions.
