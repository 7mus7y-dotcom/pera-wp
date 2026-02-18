# archive-property SSR Query Prelude Extract (Audit Only)

This report is an audit-only extraction of the original SSR prelude + args-builder logic from `archive-property.php` (pre-helper extraction revision), captured verbatim.

## Source snapshot used

- File: `wp-content/themes/hello-elementor-child/archive-property.php`
- Revision: `HEAD^` (the parent of the latest helper-extraction commit)
- Reason: the latest commit replaced inline SSR args-building with helper call, so verbatim block is taken from the immediate prior revision.

## 1) COMPLETE prelude section (variables feeding SSR args builder) — verbatim

```php
// ------------------------------------------------------------
// 1) PAGED RESOLUTION (robust for /page/N/ and ?paged=N)
// ------------------------------------------------------------
$paged = max(
  1,
  (int) get_query_var( 'paged' ),
  (int) get_query_var( 'page' ),
  isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 0
);


/* ------------------------------------------------------------
   2) READ FILTERS FROM QUERY STRING (GET)
   (These variables drive:
   - SSR query for SEO pagination
   - initial UI state for controls
------------------------------------------------------------ */

// Property type (single-select taxonomy slug)
$current_type = isset( $_GET['property_type'] )
  ? sanitize_title( wp_unslash( (string) $_GET['property_type'] ) )
  : '';

// District (multi-select)
$current_district = array();
if ( isset( $_GET['district'] ) ) {
  $raw = $_GET['district'];
  if ( is_array( $raw ) ) {
    $current_district = array_map( 'sanitize_title', wp_unslash( $raw ) );
  } elseif ( $raw !== '' ) {
    $current_district = array( sanitize_title( wp_unslash( $raw ) ) );
  }
}

// Tags (multi-select)
$current_tag = array();
if ( isset( $_GET['property_tags'] ) ) {
  $raw = $_GET['property_tags'];
  if ( is_array( $raw ) ) {
    $current_tag = array_map( 'sanitize_title', wp_unslash( $raw ) );
  } elseif ( $raw !== '' ) {
    $current_tag = array( sanitize_title( wp_unslash( $raw ) ) );
  }
}

// Bedrooms (V2 single radio)
$selected_beds = isset($_GET['v2_beds'])
  ? sanitize_text_field( wp_unslash( (string) $_GET['v2_beds'] ) )
  : '';


// Keyword
$current_keyword = isset( $_GET['s'] )
  ? sanitize_text_field( wp_unslash( (string) $_GET['s'] ) )
  : '';
$current_keyword = trim( $current_keyword );

$current_keyword_is_post_id = ( $current_keyword !== '' ) && preg_match( '/^\d+$/', $current_keyword );
$current_keyword_post_id    = $current_keyword_is_post_id ? absint( $current_keyword ) : 0;

// Taxonomy archive context (property tax term archives).
$taxonomy_context = function_exists( 'pera_get_property_tax_archive_context' )
  ? pera_get_property_tax_archive_context()
  : array();
  
  // Sort (match V1: date_desc/date_asc/price_asc/price_desc)
$sort = isset( $_GET['sort'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['sort'] ) ) : 'date_desc';
$allowed_sorts = array( 'date_desc', 'date_asc', 'price_asc', 'price_desc' );
if ( ! in_array( $sort, $allowed_sorts, true ) ) {
  $sort = 'date_desc';
}


/* ------------------------------------------------------------
   3) GLOBAL PRICE BOUNDS (V2 meta range)
------------------------------------------------------------ */
if ( function_exists( 'pera_v2_get_price_bounds' ) ) {
  $bounds = pera_v2_get_price_bounds();
  $global_min_price = isset( $bounds['min'] ) ? (int) $bounds['min'] : 100000;
  $global_max_price = isset( $bounds['max'] ) ? (int) $bounds['max'] : 5000000;
} else {
  $global_min_price = 100000;
  $global_max_price = 5000000;
}

/* ------------------------------------------------------------
   4) PRICE QS + SLIDER POSITIONS
   IMPORTANT:
   - slider UI can show bounds even when no price filter is active
   - hidden inputs should be empty unless HAS_PRICE_QS or user touches slider (JS)
------------------------------------------------------------ */
$qs_min = ( isset( $_GET['min_price'] ) && $_GET['min_price'] !== '' ) ? absint( $_GET['min_price'] ) : 0;
$qs_max = ( isset( $_GET['max_price'] ) && $_GET['max_price'] !== '' ) ? absint( $_GET['max_price'] ) : 0;
$has_price_qs = ( $qs_min > 0 || $qs_max > 0 );

// Default to global bounds when not specified
$slider_min = ( $qs_min > 0 ) ? $qs_min : $global_min_price;
$slider_max = ( $qs_max > 0 ) ? $qs_max : $global_max_price;

// Defensive swap/clamp
if ( $slider_min > $slider_max ) {
  $tmp = $slider_min;
  $slider_min = $slider_max;
  $slider_max = $tmp;
}

$slider_min = max( $global_min_price, (int) $slider_min );
$slider_max = min( $global_max_price, (int) $slider_max );

```

## 2) COMPLETE SSR args builder block (from "SSR QUERY..." to before `new WP_Query($args)`) — verbatim

```php
/* ------------------------------------------------------------
   SSR QUERY (SEO pagination baseline)
   - Mirrors the V2 AJAX filtering logic closely
   - Supports:
     district[] (IN)
     property_type (single)
     property_tags[] (IN)
     v2_beds (radio, via v2_index_flat token)
     min_price/max_price (via v2_price_usd_min/max overlap logic)
     keyword (s)
   - IMPORTANT: define meta_query as an array before pushing to it
------------------------------------------------------------ */

// Ensure these are defined (defensive)
if ( ! isset( $paged ) ) {
  $paged = max( 1, (int) get_query_var( 'paged' ) );
  if ( get_query_var( 'page' ) ) {
    $paged = max( $paged, (int) get_query_var( 'page' ) );
  }
}

$current_district = isset($current_district) && is_array($current_district) ? $current_district : array();
$current_tag      = isset($current_tag) && is_array($current_tag) ? $current_tag : array();
$current_type     = isset($current_type) ? (string) $current_type : '';
$selected_beds    = isset($selected_beds) ? (string) $selected_beds : '';
$current_keyword  = isset($current_keyword) ? (string) $current_keyword : '';
$taxonomy_context = isset( $taxonomy_context ) && is_array( $taxonomy_context ) ? $taxonomy_context : array();

$has_price_qs = isset($has_price_qs) ? (bool) $has_price_qs : false;
$qs_min       = isset($qs_min) ? (int) $qs_min : 0;
$qs_max       = isset($qs_max) ? (int) $qs_max : 0;

// Base args
$args = array(
  'post_type'              => 'property',
  'post_status'            => 'publish',
  'posts_per_page'         => 12,
  'paged'                  => $paged,
  'orderby'                => 'date',
  'order'                  => 'DESC',
  'update_post_meta_cache' => false,
  'update_post_term_cache' => false,
);

// ------------------------------------------------------------
// TAX QUERY
// ------------------------------------------------------------
$tax_query = array();

// Taxonomy context (property term archives)
if ( ! empty( $taxonomy_context['taxonomy'] ) && ! empty( $taxonomy_context['term_id'] ) ) {
  $tax_query[] = array(
    'taxonomy' => $taxonomy_context['taxonomy'],
    'field'    => 'term_id',
    'terms'    => array( (int) $taxonomy_context['term_id'] ),
  );
}

// District (multi)
if ( ! empty( $current_district ) ) {
  $tax_query[] = array(
    'taxonomy' => 'district',
    'field'    => 'slug',
    'terms'    => $current_district,
    'operator' => 'IN',
  );
}

// Property type (single)
if ( $current_type !== '' ) {
  $tax_query[] = array(
    'taxonomy' => 'property_type',
    'field'    => 'slug',
    'terms'    => $current_type,
  );
}

// Tags (multi)
if ( ! empty( $current_tag ) ) {
  $tax_query[] = array(
    'taxonomy' => 'property_tags',
    'field'    => 'slug',
    'terms'    => $current_tag,
    'operator' => 'IN',
  );
}

if ( ! empty( $tax_query ) ) {
  $args['tax_query'] = array_merge( array( 'relation' => 'AND' ), $tax_query );
}

// ------------------------------------------------------------
// META QUERY (V2)
// ------------------------------------------------------------
$meta_query = array();

// V2 Beds filter (radio) -> v2_index_flat LIKE "|2|"
if ( $selected_beds !== '' && preg_match( '/^\d+$/', $selected_beds ) ) {
  $b = (int) $selected_beds;

  $meta_query[] = array(
    'key'     => 'v2_index_flat',
    'value'   => '|' . $b . '|',
    'compare' => 'LIKE',
  );
}

// Price filter: overlap logic using v2_price_usd_min/max
// A property matches if:
// - v2_price_usd_max >= min (when min provided)
// - v2_price_usd_min <= max (when max provided)
if ( $has_price_qs ) {

  $min = ( $qs_min > 0 ) ? $qs_min : null;
  $max = ( $qs_max > 0 ) ? $qs_max : null;

  if ( $min !== null ) {
    $meta_query[] = array(
      'key'     => 'v2_price_usd_max',
      'value'   => $min,
      'type'    => 'NUMERIC',
      'compare' => '>=',
    );
  }

  if ( $max !== null ) {
    $meta_query[] = array(
      'key'     => 'v2_price_usd_min',
      'value'   => $max,
      'type'    => 'NUMERIC',
      'compare' => '<=',
    );
  }
}

if ( ! empty( $meta_query ) ) {
  $args['meta_query'] = array_merge( array( 'relation' => 'AND' ), $meta_query );
}

// ------------------------------------------------------------
// KEYWORD
// ------------------------------------------------------------
if ( $current_keyword !== '' ) {
  if ( $current_keyword_is_post_id ) {
    $args['p'] = $current_keyword_post_id;
  } else {
    $args['s'] = $current_keyword;
    if ( function_exists( 'pera_is_frontend_admin_equivalent' ) && pera_is_frontend_admin_equivalent() ) {
      $args['pera_kw_project'] = 1;
    }
  }
}


// ------------------------------------------------------------
// SORTING (V2)
// - price sorts use v2_price_usd_min
// ------------------------------------------------------------
switch ( $sort ) {
  case 'price_asc':
    $args['meta_key'] = 'v2_price_usd_min';
    $args['orderby']  = 'meta_value_num';
    $args['order']    = 'ASC';
    break;

  case 'price_desc':
    $args['meta_key'] = 'v2_price_usd_min';
    $args['orderby']  = 'meta_value_num';
    $args['order']    = 'DESC';
    break;

  case 'date_asc':
    $args['orderby'] = 'date';
    $args['order']   = 'ASC';
    break;

  case 'date_desc':
  default:
    $args['orderby'] = 'date';
    $args['order']   = 'DESC';
    break;
}


```

## 3) Exact input variables to the args-builder block

- `$paged` (int, default >= 1).
  - Initialized from `get_query_var("paged")`, `get_query_var("page")`, and `$_GET["paged"]` via `max(...)`.
  - Defensive re-init inside SSR block if not set.
- `$current_district` (array<string>, default `[]`).
  - Parsed from `$_GET["district"]`, handling both array and scalar string forms.
- `$current_tag` (array<string>, default `[]`).
  - Parsed from `$_GET["property_tags"]`, handling both array and scalar string forms.
- `$current_type` (string, default `""`).
  - Parsed from `$_GET["property_type"]`.
- `$selected_beds` (string, default `""`).
  - Parsed from `$_GET["v2_beds"]`; later validated with `preg_match("/^\\d+$/", ...)` before use.
- `$current_keyword` (string, default `""`).
  - Parsed from `$_GET["s"]`, trimmed; used for `p` when numeric keyword, else `s`.
- `$current_keyword_is_post_id` (truthy/falsey regex result; effectively bool-ish at call sites).
- `$current_keyword_post_id` (int, default `0`).
- `$taxonomy_context` (array, default `[]`).
  - Derived via `pera_get_property_tax_archive_context()` helper when available.
- `$sort` (string, default `"date_desc"`).
  - Allowed values: `date_desc`, `date_asc`, `price_asc`, `price_desc`; invalid values reset to `date_desc`.
- `$has_price_qs` (bool, default `false`).
  - Derived from `$qs_min > 0 || $qs_max > 0`.
- `$qs_min` (int, default `0`).
  - Parsed from `$_GET["min_price"]` when non-empty.
- `$qs_max` (int, default `0`).
  - Parsed from `$_GET["max_price"]` when non-empty.

## 4) Dependencies/subtleties affecting SSR behavior

- **Array-vs-scalar GET handling is intentional** for `district` and `property_tags`:
  - Array input uses `array_map( "sanitize_title", wp_unslash($raw) )`.
  - Scalar non-empty input is wrapped into single-element array.
- **Keyword special casing is intentional**:
  - Numeric-only `s` is treated as post ID (`$args["p"]`).
  - Non-numeric `s` sets `$args["s"]`; also sets `pera_kw_project=1` only for `pera_is_frontend_admin_equivalent()`.
- **Price input semantics**:
  - `$qs_min/$qs_max` are read directly from querystring using `absint` and non-empty checks.
  - Price filtering is active only when `$has_price_qs` is true.
  - Overlap logic uses `v2_price_usd_max >= min` and `v2_price_usd_min <= max`.
- **Slider/global bounds are prelude-only UI support in this section**:
  - `$global_min_price/$global_max_price`, `$slider_min/$slider_max`, and clamp/swap are computed before SSR block.
  - SSR args-builder itself only consumes `$has_price_qs/$qs_min/$qs_max` (not slider values directly).
- **Defensive retyping inside SSR block is behavior-significant**:
  - It re-normalizes `$current_*`, `$taxonomy_context`, and price flags before building `$args`.
- **Sort fallback order is behavior-significant**:
  - Default base args are `orderby=date`, `order=DESC`, then sort switch may override.
