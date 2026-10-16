<?php
/** php tests/property-sticky-summary-test.php [--fixture] */
define( 'ABSPATH', __DIR__ );
function add_action() {}
function is_user_logged_in() { return $GLOBALS['logged_in']; }
function current_user_can( $cap ) { return $GLOBALS['admin']; }
function is_singular( $type ) { return $GLOBALS['property']; }
function sanitize_text_field( $value ) { return $value; }
function wp_unslash( $value ) { return $value; }
function pera_ml_ui( $text, $key ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( $text, ENT_QUOTES ); }
function esc_url( $text ) { return esc_html( $text ); }
function esc_attr( $text ) { return esc_html( $text ); }
function number_format_i18n( $number ) { return number_format( $number ); }
function get_stylesheet_directory_uri() { return '/theme'; }
function pera_get_asset_version( $path ) { return 'test'; }
function wp_enqueue_script() { $GLOBALS['enqueues']++; }
function check_summary( $condition, $label ) { if ( ! $condition ) { throw new Exception( $label ); } }
$theme = dirname( __DIR__ );
require $theme . '/../../mu-plugins/pera-property-layout-preview.php';
require $theme . '/inc/v2-units-index.php';
$template = file_get_contents( $theme . '/single-property.php' );
$start = strpos( $template, '<?php if ( $layout_preview ) : ?>', strpos( $template, '</section>' ) );
$end = strpos( $template, '<!-- =====================================' . "\n  OVERVIEW", $start );
$section = substr( $template, $start, $end - $start );
function render_summary( $section, $mode = 'single', $price = 299000, $size = '69 m²', $beds = '1+1', $type_name = 'Apartment' ) {
    $layout_preview = pera_property_layout_preview_enabled();
    $hero_price_min = $price;
    $hero_display_price = pera_property_display_price( $price, $price + ( 'from' === $mode ? 100000 : 0 ), $mode );
    $hero_show_from = 'from' === $mode;
    $selected_size_text = $size;
    $hero_bed_label_v2 = $beds;
    $title = 'Large Furnished Studio · Bomonti';
    $GLOBALS['enqueues'] = 0;
    ob_start(); eval( '?>' . $section ); return ob_get_clean();
}
foreach ( array( false, true ) as $logged_in ) {
foreach ( array( false, true ) as $admin ) {
foreach ( array( false, true ) as $property ) {
foreach ( array( null, '1', '0', array( '1' ) ) as $query ) {
    $GLOBALS['logged_in'] = $logged_in; $GLOBALS['admin'] = $admin; $GLOBALS['property'] = $property;
    $_GET = null === $query ? array() : array( 'layout_preview' => $query );
    $expected = $logged_in && $admin && $property && '1' === $query;
    $html = render_summary( $section );
    check_summary( $expected === ( false !== strpos( $html, 'data-property-sticky-preview' ) ), 'preview isolation' );
    check_summary( ( $expected ? 1 : 0 ) === $GLOBALS['enqueues'], 'script isolation' );
}}}}
$GLOBALS['logged_in'] = $GLOBALS['admin'] = $GLOBALS['property'] = true;
$_GET = array( 'layout_preview' => '1' );
foreach ( array( 'single', 'from' ) as $mode ) {
    $html = render_summary( $section, $mode );
    check_summary( false !== strpos( $html, 'data-price-mode="' . $mode . '"' ), 'authoritative price mode' );
    check_summary( false !== strpos( $html, 'data-usd-min="299000"' ), 'currency metadata' );
    check_summary( false !== strpos( $html, '69 m² · 1+1' ), 'selected hero facts' );
    check_summary( false !== strpos( $html, 'hidden inert' ), 'initially unfocusable' );
    check_summary( false !== strpos( $html, 'href="#contact-form"' ), 'existing anchor' );
}
$html = render_summary( $section, 'single', 0, '', '', '' );
check_summary( false !== strpos( $html, 'Contact us for pricing' ), 'missing price fallback' );
check_summary( false === strpos( $html, 'property-sticky-preview__facts' ), 'missing facts omitted' );
$type_html = render_summary( $section, 'single', 299000, '69 m²', '', 'Apartment' );
check_summary( false !== strpos( $type_html, ' · Apartment' ), 'existing translated property type used when beds unavailable' );
check_summary( false === strpos( $type_html, 'Large Furnished Studio' ), 'no sticky title' );
check_summary( false !== strpos( $type_html, 'card-shell property-sticky-preview' ), 'existing card design system reused' );
if ( in_array( '--type-fixture', $argv, true ) ) { echo $type_html; exit; }
if ( in_array( '--normal-fixture', $argv, true ) ) {
    $_GET = array(); echo render_summary( $section ); exit;
}
// Render the actual header shell; only logo and selector helpers are stubbed.
if ( in_array( '--header-fixture', $argv, true ) ) {
    function home_url( $path ) { return $path; }
    function admin_url() { return '/wp-admin/'; }
    function get_post_type_archive_link( $type ) { return '/property/'; }
    function pera_get_site_logo_markup( $args ) { return '<a href="/" class="site-logo logo-pera">Pera Property</a>'; }
    function pera_render_header_language_switcher( $context ) { echo '<button aria-expanded="false" id="language-toggle">EN</button>'; }
    function pera_render_currency_selector( $context ) { echo '<button aria-expanded="false" id="currency-toggle">USD</button>'; }
    $show_crm_header_button = $show_new_leads_pill = false;
    $source = file_get_contents( $theme . '/header.php' );
    $offset = strpos( $source, '<header id="site-header"' );
    eval( '?>' . substr( $source, $offset, strpos( $source, '</header>', $offset ) + 9 - $offset ) );
    exit;
}
if ( in_array( '--fixture', $argv, true ) ) echo render_summary( $section, 'from' );
else echo "Sticky summary PHP tests passed (32 gate combinations, project/resale and missing data)\n";
