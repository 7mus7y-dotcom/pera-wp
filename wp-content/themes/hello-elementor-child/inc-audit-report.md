# Inc Audit Report

## Step 1 — AJAX: property archive filter action
JS action name and backend handlers (repo-wide `rg`):
```
./archive-property.php:1193:    fd.set('action', 'pera_filter_properties_v2');
./inc/ajax-property-archive.php:4: * Endpoint action: pera_filter_properties_v2
./inc/ajax-property-archive.php:612:add_action( 'wp_ajax_pera_filter_properties_v2', 'pera_ajax_filter_properties_v2' );
./inc/ajax-property-archive.php:613:add_action( 'wp_ajax_nopriv_pera_filter_properties_v2', 'pera_ajax_filter_properties_v2' );
```

## Step 2 — SEO helpers used by templates (moved to always-loaded file)
Archive heading helpers referenced in the property archive template:
```
./archive-property.php:378:  if ( $qo->taxonomy === 'district' && function_exists( 'pera_get_district_archive_heading' ) ) {
./archive-property.php:379:    $hero_title = pera_get_district_archive_heading( $qo );
./archive-property.php:380:  } elseif ( $qo->taxonomy === 'region' && function_exists( 'pera_get_region_archive_heading' ) ) {
./archive-property.php:381:    $hero_title = pera_get_region_archive_heading( $qo );
./archive-property.php:382:  } elseif ( $qo->taxonomy === 'property_tags' && function_exists( 'pera_get_property_tags_archive_heading' ) ) {
./archive-property.php:383:    $hero_title = pera_get_property_tags_archive_heading( $qo );
```

Helper definitions now live in `inc/seo-helpers.php` (repo-wide `rg`):
```
./inc/seo-helpers.php:7:if ( ! function_exists( 'pera_get_district_archive_location_name' ) ) {
./inc/seo-helpers.php:8:  function pera_get_district_archive_location_name( WP_Term $term ): string {
./inc/seo-helpers.php:27:if ( ! function_exists( 'pera_get_district_archive_heading' ) ) {
./inc/seo-helpers.php:28:  function pera_get_district_archive_heading( WP_Term $term ): string {
./inc/seo-helpers.php:29:    $location = pera_get_district_archive_location_name( $term );
./inc/seo-helpers.php:34:if ( ! function_exists( 'pera_get_district_archive_title' ) ) {
./inc/seo-helpers.php:35:  function pera_get_district_archive_title( WP_Term $term ): string {
./inc/seo-helpers.php:36:    $location = pera_get_district_archive_location_name( $term );
./inc/seo-helpers.php:41:if ( ! function_exists( 'pera_get_region_archive_location_name' ) ) {
./inc/seo-helpers.php:42:  function pera_get_region_archive_location_name( WP_Term $term ): string {
./inc/seo-helpers.php:61:if ( ! function_exists( 'pera_get_region_archive_heading' ) ) {
./inc/seo-helpers.php:62:  function pera_get_region_archive_heading( WP_Term $term ): string {
./inc/seo-helpers.php:63:    $location = pera_get_region_archive_location_name( $term );
./inc/seo-helpers.php:68:if ( ! function_exists( 'pera_get_region_archive_title' ) ) {
./inc/seo-helpers.php:69:  function pera_get_region_archive_title( WP_Term $term ): string {
./inc/seo-helpers.php:70:    $location = pera_get_region_archive_location_name( $term );
./inc/seo-helpers.php:75:if ( ! function_exists( 'pera_get_property_tags_archive_heading' ) ) {
./inc/seo-helpers.php:76:  function pera_get_property_tags_archive_heading( WP_Term $term ): string {
./inc/seo-helpers.php:87:if ( ! function_exists( 'pera_get_property_tags_archive_title' ) ) {
./inc/seo-helpers.php:88:  function pera_get_property_tags_archive_title( WP_Term $term ): string {
```

Helper usage inside the SEO archive module (repo-wide `rg`):
```
./inc/seo-property-archive.php:61:  return pera_get_district_archive_title( $term );
./inc/seo-property-archive.php:74:  return pera_get_region_archive_title( $term );
./inc/seo-property-archive.php:87:  return pera_get_property_tags_archive_title( $term );
```

## Step 3 — SEO hooks (conditional modules)
Hook locations for `pre_get_document_title`, `wp_head`, and `wp_robots` within SEO modules:
```
inc/seo-property-archive.php:51:add_filter( 'pre_get_document_title', function( $title ) {
inc/seo-property-archive.php:64:add_filter( 'pre_get_document_title', function( $title ) {
inc/seo-property-archive.php:77:add_filter( 'pre_get_document_title', function( $title ) {
inc/seo-property-archive.php:90:add_filter( 'pre_get_document_title', function( $title ) {
inc/seo-property-archive.php:111:add_action( 'wp_head', function () {
inc/seo-property-archive.php:203:add_filter( 'wp_robots', function ( array $robots ): array {
inc/seo-all.php:9: * - Adds "noindex,follow" via wp_robots for:
inc/seo-all.php:162:   ROBOTS RULES (wp_robots)
inc/seo-all.php:165:add_filter( 'wp_robots', function ( array $robots ): array {
inc/seo-all.php:191:add_action( 'wp_head', function () {
inc/seo-property.php:179:add_filter( 'pre_get_document_title', function ( string $title ): string {
inc/seo-property.php:210:add_action('wp_head', function () {
inc/seo-property.php:286:add_filter( 'wp_robots', function ( array $robots ): array {
```

## Step 4 — Access control helper usage
`pera_is_frontend_admin_equivalent()` references (repo-wide `rg`):
```
./archive/single-property-v2.php:375:      <?php if ( pera_is_frontend_admin_equivalent() && $project_name ) : ?>
./functions.php:102:    if ( $flag === '1' && pera_is_frontend_admin_equivalent() ) {
./inc/access-control.php:29:  function pera_is_frontend_admin_equivalent( int $user_id = 0 ): bool {
./inc/ajax-property-archive.php:247:        $debug_enabled = pera_is_frontend_admin_equivalent() && isset( $_POST['pera_debug'] ) && (string) $_POST['pera_debug'] === '1';
./archive-property.php:311:$debug_enabled = pera_is_frontend_admin_equivalent() && isset( $_GET['pera_debug'] ) && (string) $_GET['pera_debug'] === '1';
./single-property.php:479:      <?php if ( pera_is_frontend_admin_equivalent() && $project_name ) : ?>
./parts/_archive/property-card.php:124:      <?php if ( $show_admin && pera_is_frontend_admin_equivalent() ) : ?>
./parts/property-card-v2.php:286:        <?php if ( $show_admin && pera_is_frontend_admin_equivalent() ) : ?>
```

## Step 5 — Inc usage proof (from /tmp/inc_function_usages.txt + inline evidence)
Note: `/tmp/inc_function_usages.txt` was generated earlier. Key evidence is inlined here.

### inc/published-term-counts.php — unused, not loaded
Repo-wide `rg` result for `/inc/published-term-counts.php|published_term_counts|archive_published_property_term_counts` (definition only, no call sites):
```
./inc/_archive/published-term-counts.php:9:function pera_archive_published_property_term_counts( string $taxonomy ): array {
```

### inc/v2-units-index.php — debug inspector (`?debug_price_max`)
Dormant-by-design (manual trigger). Only internal guard reference found:
```
./inc/v2-units-index.php:133:  if ( ! isset($_GET['debug_price_max']) ) return;
```

### inc/v2-units-index.php — compatibility alias `pera_v2_get_units()`
Probably unused; no call sites beyond the alias and its internal use:
```
./inc/v2-units-index.php:489:  function pera_v2_get_units( int $post_id ): array {
./inc/v2-units-index.php:710:      ? pera_v2_get_units( $post_id )
```

## Step 6 — Notes / wording corrections
- published-term-counts.php → **unused, not loaded** (archived under `inc/_archive/`).
- debug inspector → **Dormant-by-design (manual trigger), remove only if not needed**.
- compatibility alias → **Probably unused; remove if confirmed**.
- home-page-test-assets → requires removing its **unconditional `require_once`** after template retirement:
  ```
  functions.php:1043:require_once get_stylesheet_directory() . '/inc/home-page-test-assets.php';
  ```

## Removal protocol
**Suggested branch name:** `chore/inc-prune`

**Exact removal steps:**
1) Remove `require_once get_stylesheet_directory() . '/inc/home-page-test-assets.php';` once the template(s) that need it are retired.
2) If confirmed unused, delete or archive:
   - `inc/_archive/published-term-counts.php`
   - the `pera_v2_get_units()` compatibility alias in `inc/v2-units-index.php`
3) If the debug inspector is no longer needed, remove the `debug_price_max` guard block in `inc/v2-units-index.php`.

**Commands to re-run (evidence checks):**
```
rg -n "/inc/published-term-counts.php|published_term_counts|archive_published_property_term_counts" .
rg -n "pera_v2_get_units\\(" .
rg -n "debug_price_max" .
rg -n "pera_get_.*archive_heading|archive_heading|pre_get_document_title|wp_head|wp_robots" inc/seo-*.php archive-property.php single-property.php parts -S
rg -n "function\\s+pera_|pera_" inc archive-property.php single-property.php parts -S
```

**Smoke test checklist:**
- AJAX archive filter (search + pagination)
- Single property pricing table (unit selector + price range)
- Favourites (add/remove + list)
- Enquiry forms (citizenship / seller / favourites)
- Admin property list table (custom columns + quick edit)
