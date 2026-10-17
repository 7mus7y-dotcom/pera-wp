<?php
/** Standalone regression coverage: php tests/property-archive-seo-preview-test.php */
if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}
define( 'ABSPATH', __DIR__ );
$state = array();
$cache_calls = 0;
$hooks = array();
function is_admin() { return $GLOBALS['state']['admin_screen']; }
function wp_doing_ajax() { return $GLOBALS['state']['ajax']; }
function is_post_type_archive( $type ) { return 'property' === $type && $GLOBALS['state']['archive']; }
function is_tax() { return $GLOBALS['state']['tax']; }
function is_search() { return $GLOBALS['state']['search']; }
function is_paged() { return $GLOBALS['state']['paged']; }
function is_user_logged_in() { return $GLOBALS['state']['logged_in']; }
function current_user_can( $capability ) { return 'manage_options' === $capability && $GLOBALS['state']['manage_options']; }
function nocache_headers() { $GLOBALS['cache_calls']++; }
function add_action( $hook, $callback, $priority = 10 ) { $GLOBALS['hooks'][ $hook ] = array( $callback, $priority ); }
function preview_expect( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}
require dirname( __DIR__ ) . '/inc/modules/property-archive-settings.php';
$defaults = array( 'admin_screen' => false, 'ajax' => false, 'archive' => true, 'tax' => false, 'search' => false, 'paged' => false, 'logged_in' => true, 'manage_options' => true );
// Exercise the existing clean-archive helper used by the public template and schema.
$seo_source = file_get_contents( dirname( __DIR__ ) . '/inc/seo-property-archive.php' );
$start = strpos( $seo_source, "if ( ! function_exists( 'pera_property_archive_is_clean_main_archive' ) )" );
$end = strpos( $seo_source, "if ( ! function_exists( 'pera_property_archive_settings_field' ) )", $start );
eval( substr( $seo_source, $start, $end - $start ) );
foreach ( array(
    array( array(), array( 'logged_in' => false, 'manage_options' => false ), true ),
    array( array(), array(), true ),
    array( array(), array( 'tax' => true ), false ),
    array( array(), array( 'search' => true ), false ),
    array( array(), array( 'paged' => true ), false ),
    array( array(), array( 'archive' => false ), false ),
    array( array( 'district' => 'sisli' ), array(), false ),
    array( array( 'paged' => '2' ), array(), false ),
    array( array( 'seo_archive_preview' => '1' ), array(), false ),
) as $case ) {
    list( $_GET, $overrides, $expected ) = $case;
    $state = array_merge( $defaults, $overrides );
    $original = $_GET;
    preview_expect( $expected === pera_property_archive_is_clean_main_archive(), 'public archive context' );
    preview_expect( $_GET === $original, 'request remains intact' );
}
preview_expect( empty( $hooks ) && ! defined( 'DONOTCACHEPAGE' ), 'settings no longer register preview cache handling' );

// Exercise the isolated partial with the real FAQ parser and renderer.
function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
function wp_kses_post( $text ) { return $text; }
function wpautop( $text ) { return '<p>' . $text . '</p>'; }
function esc_html( $text ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $text ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function pera_ml_ui( $text, $key ) { return $text; }
function home_url( $path ) { return 'https://example.test' . $path; }
function pera_ml_url( $url ) { return str_replace( 'example.test/', 'example.test/de/', $url ); }
function pera_get_whatsapp_url( $message ) { return 'https://wa.me/123?text=' . rawurlencode( $message ); }
require dirname( __DIR__ ) . '/inc/modules/faqs.php';
function get_queried_object() { return null; }
function pera_property_archive_settings_field( $field ) { return $GLOBALS['editor_faq'] ?? ''; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function pera_schema_should_emit_type( $type, $context ) { return empty( $GLOBALS['block_schema'] ); }

// Separate processes cover the provider's per-request memoization and editor priority.
$_GET = array();
$state = $defaults;
$editor_faq = $argv[1] ?? '';
$items = pera_get_property_archive_faq_items();
preview_expect( ( $editor_faq ? 1 : 5 ) === count( $items ), 'editor FAQs replace defaults' );
ob_start();
pera_render_faq_html( $items, 'FAQ' );
$html = ob_get_clean();
preview_expect( count( $items ) === substr_count( $html, '<details ' ), 'visible questions use provider rows' );
$block_schema = true;
ob_start(); pera_render_faq_schema( $items ); $blocked = ob_get_clean();
preview_expect( '' === $blocked, 'schema ownership suppression retained' );
$block_schema = false;
ob_start(); pera_render_faq_schema( $items ); pera_render_faq_schema( $items ); $schema = ob_get_clean();
preview_expect( 1 === substr_count( $schema, 'application/ld+json' ), 'duplicate schema suppressed' );
preg_match( '~<script[^>]*>(.*?)</script>~s', $schema, $matches );
$decoded = json_decode( $matches[1], true );
foreach ( $items as $index => $item ) {
    preview_expect( $item['question'] === $decoded['mainEntity'][$index]['name'], 'schema and HTML share question' );
    preview_expect( false !== strpos( $html, esc_html( $item['question'] ) ), 'provider question visible' );
}

function render_public_content( $fields ) {
    $property_archive_get_field = static function ( $key ) use ( $fields ) { return $fields[ $key ] ?? ''; };
    $is_clean_main_property_archive = true;
    $source = file_get_contents( dirname( __DIR__ ) . '/archive-property.php' );
    $start = strpos( $source, '<?php if ( $is_clean_main_property_archive ) : ?>', strpos( $source, 'related_taxonomy_total > 0' ) );
    // Locate the bottom content by its first field read, avoiding unrelated conditionals.
    $field = strpos( $source, '    $archive_bottom_content' );
    $start = strrpos( substr( $source, 0, $field ), '<?php if' );
    $end = strpos( $source, '<?php if ( ! empty( $property_archive_faq_items )', $field );
    ob_start(); eval( '?>' . substr( $source, $start, $end - $start ) ); return ob_get_clean();
}
$html = render_public_content( array() );
foreach ( array( 'Choosing where to buy', 'Get your Istanbul property shortlist', '/de/book-a-consultancy/', 'Request my shortlist' ) as $copy ) {
    preview_expect( false !== strpos( $html, $copy ), 'public default: ' . $copy );
}
$html = render_public_content( array( 'archive_bottom_content' => 'Editor bottom', 'archive_cta_heading' => 'Editor heading', 'archive_cta_text' => 'Editor text', 'archive_whatsapp_message' => 'Editor message' ) );
foreach ( array( 'Editor bottom', 'Editor heading', 'Editor text', 'Editor%20message' ) as $copy ) {
    preview_expect( false !== strpos( $html, $copy ), 'editor override: ' . $copy );
}
preview_expect( false === strpos( $html, 'Choosing where to buy' ), 'editor bottom replaces fallback' );
echo "Public archive content and shared FAQ tests passed\n";

$template = file_get_contents( dirname( __DIR__ ) . '/archive-property.php' );
preview_expect( false === strpos( $template, 'seo_archive_preview' ), 'template has no obsolete preview gate or indicator' );
$start = strpos( $template, '                <?php if ( $is_clean_main_property_archive ) : ?>' );
$end = strpos( $template, '<div class="property-filters-toolbar">', $start );
$is_clean_main_property_archive = true;
ob_start(); eval( '?>' . substr( $template, $start, $end - $start ) ); $districts = ob_get_clean();
preview_expect( 6 === substr_count( $districts, '<a href=' ), 'six public district links' );
foreach ( array( 'besiktas', 'sisli', 'kadikoy', 'zeytinburnu', 'uskudar', 'sariyer' ) as $slug ) {
    preview_expect( false !== strpos( $districts, '/de/district/istanbul/' . $slug . '/' ), 'district uses language URL helper' );
}
$is_clean_main_property_archive = false;
ob_start(); eval( '?>' . substr( $template, $start, $end - $start ) ); $districts = ob_get_clean();
preview_expect( '' === trim( $districts ), 'district navigation excluded on other contexts' );
