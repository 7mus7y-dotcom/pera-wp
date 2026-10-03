<?php
if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

/** Isolated regression coverage for the global floating WhatsApp context resolver. */
define( 'ABSPATH', __DIR__ );

class WP_Post {
	public $ID;
	public $post_type;
	public function __construct( $id, $post_type ) { $this->ID = $id; $this->post_type = $post_type; }
}
class WP_Term {
	public $name;
	public $taxonomy;
	public function __construct( $name, $taxonomy ) { $this->name = $name; $this->taxonomy = $taxonomy; }
}

$GLOBALS['wa_state'] = array();
$GLOBALS['wa_options'] = array();
function wa_expect( $condition, $label ) { if ( ! $condition ) { fwrite( STDERR, "FAIL {$label}\n" ); exit( 1 ); } }
function wa_set( array $state ) {
	$GLOBALS['wa_state'] = array_merge( array( 'kind' => 'fallback', 'id' => 0, 'title' => '', 'template' => '', 'slug' => '', 'taxonomy' => '', 'post_type' => '' ), $state );
	$_SERVER['REQUEST_URI'] = $state['request'] ?? '/';
}
function wa_kind( $kind ) { return $GLOBALS['wa_state']['kind'] === $kind; }
function is_singular( $type = '' ) { $singular = in_array( $GLOBALS['wa_state']['kind'], array( 'property', 'post', 'page', 'singular' ), true ); return $type ? $singular && $GLOBALS['wa_state']['post_type'] === $type : $singular; }
function is_page( $slug = '' ) { return wa_kind( 'page' ) && ( is_array( $slug ) ? in_array( $GLOBALS['wa_state']['slug'], $slug, true ) : $GLOBALS['wa_state']['slug'] === $slug ); }
function is_page_template( $template ) { return wa_kind( 'page' ) && $GLOBALS['wa_state']['template'] === $template; }
function is_post_type_archive( $type ) { return wa_kind( 'property_archive' ) && 'property' === $type; }
function is_search() { return wa_kind( 'search' ); }
function is_tax( $tax = '' ) { $yes = wa_kind( 'taxonomy' ); return ! $tax ? $yes : $yes && in_array( $GLOBALS['wa_state']['taxonomy'], (array) $tax, true ); }
function is_category() { return wa_kind( 'category' ); }
function is_tag() { return wa_kind( 'tag' ); }
function is_home() { return wa_kind( 'home' ); }
function is_date() { return wa_kind( 'date' ); }
function is_author() { return wa_kind( 'author' ); }
function is_archive() { return in_array( $GLOBALS['wa_state']['kind'], array( 'archive', 'date', 'author', 'category', 'tag', 'taxonomy' ), true ); }
function get_query_var( $key ) { return 'post_type' === $key ? $GLOBALS['wa_state']['post_type'] : ''; }
function get_queried_object_id() { return $GLOBALS['wa_state']['id']; }
function get_queried_object() {
	if ( in_array( $GLOBALS['wa_state']['kind'], array( 'taxonomy', 'category', 'tag' ), true ) ) return new WP_Term( $GLOBALS['wa_state']['title'], $GLOBALS['wa_state']['taxonomy'] );
	if ( is_singular() ) return new WP_Post( $GLOBALS['wa_state']['id'], $GLOBALS['wa_state']['post_type'] );
	return null;
}
function get_post( $id ) { return new WP_Post( $id, $GLOBALS['wa_state']['post_type'] ); }
function get_the_title() { return $GLOBALS['wa_state']['title']; }
function get_permalink( $id ) { return 'https://example.test/canonical/' . $id . '/'; }
function wp_get_document_title() { return $GLOBALS['wa_state']['title']; }
function get_bloginfo() { return 'UTF-8'; }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function wp_unslash( $value ) { return $value; }
function wp_parse_url( $value ) { return parse_url( $value ); }
function home_url( $path = '/' ) { return 'https://example.test' . $path; }
function esc_url_raw( $value ) { return filter_var( $value, FILTER_SANITIZE_URL ); }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function apply_filters( $tag, $value ) { return $value; }
function pera_ml_ui( $source, $key = '' ) { $GLOBALS['wa_translation_keys'][] = $key; return $source; }
function get_option( $key, $default = '' ) { return $GLOBALS['wa_options'][ $key ] ?? $default; }
function esc_url( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function get_stylesheet_directory_uri() { return 'https://example.test/theme'; }
function is_user_logged_in() { return false; }
function add_action() {}

$theme = dirname( __DIR__ );
require_once $theme . '/inc/whatsapp-helpers.php';
require_once $theme . '/inc/whatsapp.php';

$cases = array(
	array( array( 'kind' => 'property', 'post_type' => 'property', 'id' => 12345, 'title' => 'Bosphorus &amp; Home', 'request' => '/tr/property/ignored/?utm_source=x' ), 'single-property', array( 'Bosphorus & Home', '12345', 'https://example.test/canonical/12345/' ) ),
	array( array( 'kind' => 'post', 'post_type' => 'post', 'id' => 88, 'title' => '<b>Buying Guide</b>', 'request' => '/de/blog/kaufen/' ), 'blog-article', array( 'Buying Guide', 'https://example.test/canonical/88/' ) ),
	array( array( 'kind' => 'property_archive', 'request' => '/fr/property/?district%5B%5D=besiktas&min_price=100000&_wpnonce=secret&debug=1' ), 'property-search', array( 'district%5B0%5D=besiktas', 'min_price=100000' ) ),
	array( array( 'kind' => 'page', 'post_type' => 'page', 'id' => 10, 'title' => 'Eligible homes', 'template' => 'page-citizenship-properties.php', 'request' => '/tr/turkish-citizenship-properties/?view=cards' ), 'citizenship-properties', array( 'citizenship-eligible', '/tr/turkish-citizenship-properties/?view=cards' ) ),
	array( array( 'kind' => 'taxonomy', 'taxonomy' => 'region', 'title' => 'European Side', 'request' => '/region/european-side/' ), 'property-taxonomy', array( 'European Side' ) ),
	array( array( 'kind' => 'taxonomy', 'taxonomy' => 'district', 'title' => 'Beşiktaş', 'request' => '/district/besiktas/' ), 'property-taxonomy', array( 'Beşiktaş' ) ),
	array( array( 'kind' => 'taxonomy', 'taxonomy' => 'property_tags', 'title' => 'Sea View', 'request' => '/property_tags/sea-view/' ), 'property-taxonomy', array( 'Sea View' ) ),
	array( array( 'kind' => 'category', 'taxonomy' => 'category', 'title' => 'Area Guides', 'request' => '/category/areas/' ), 'blog-category', array( 'Area Guides' ) ),
	array( array( 'kind' => 'tag', 'taxonomy' => 'post_tag', 'title' => 'Investing', 'request' => '/tag/investing/' ), 'blog-tag', array( 'Investing' ) ),
	array( array( 'kind' => 'home', 'title' => 'Blog', 'request' => '/blog/' ), 'blog-archive', array( '/blog/' ) ),
	array( array( 'kind' => 'page', 'post_type' => 'page', 'template' => 'page-citizenship.php', 'id' => 11, 'title' => 'Citizenship', 'request' => '/citizenship-by-investment/' ), 'citizenship-by-investment', array( '/citizenship-by-investment/' ) ),
	array( array( 'kind' => 'page', 'post_type' => 'page', 'template' => 'page-sell-with-pera.php', 'id' => 12, 'title' => 'Sell', 'request' => '/sell-with-pera/' ), 'sell-with-pera', array( 'selling my property', '/sell-with-pera/' ) ),
	array( array( 'kind' => 'page', 'post_type' => 'page', 'template' => 'page-rent-with-pera.php', 'id' => 13, 'title' => 'Rent', 'request' => '/rent-with-pera/' ), 'rent-with-pera', array( 'property management', '/rent-with-pera/' ) ),
	array( array( 'kind' => 'page', 'post_type' => 'page', 'template' => 'page-developer-sales-office.php', 'id' => 14, 'title' => 'Developer', 'request' => '/developer-sales-office/' ), 'developer-sales-office', array( 'developer sales and marketing' ) ),
	array( array( 'kind' => 'page', 'post_type' => 'page', 'template' => 'page-book-a-consultancy.php', 'id' => 15, 'title' => 'Book', 'request' => '/book-a-consultancy/' ), 'book-consultancy', array( 'book a property consultancy' ) ),
	array( array( 'kind' => 'page', 'post_type' => 'page', 'template' => 'page-property-map.php', 'id' => 16, 'title' => 'Map', 'request' => '/es/property-map/' ), 'property-map', array( '/es/property-map/' ) ),
	array( array( 'kind' => 'page', 'post_type' => 'page', 'template' => 'page-luxury-property.php', 'id' => 17, 'title' => 'Luxury', 'request' => '/istanbul-luxury-property/' ), 'luxury-property', array( 'personalised shortlist' ) ),
	array( array( 'kind' => 'page', 'post_type' => 'page', 'template' => 'page-contact.php', 'id' => 18, 'title' => 'Contact', 'request' => '/contact-us/' ), 'contact', array( '/contact-us/' ) ),
	array( array( 'kind' => 'page', 'post_type' => 'page', 'id' => 19, 'title' => 'About “Pera”', 'request' => '/about/' ), 'content-page', array( 'About “Pera”', 'https://example.test/canonical/19/' ) ),
	array( array( 'kind' => 'fallback', 'title' => 'Search results', 'request' => '/tr/unusual/?q=home&token=private' ), 'generic', array( 'Search results', '/tr/unusual/?q=home' ) ),
);

foreach ( $cases as $index => $case ) {
	wa_set( $case[0] );
	$context = pera_get_whatsapp_context();
	wa_expect( $case[1] === $context['page_type'], "case {$index} page type" );
	foreach ( $case[2] as $needle ) wa_expect( false !== strpos( $context['message_text'], $needle ), "case {$index} contains {$needle}" );
	wa_expect( 0 === strpos( $context['whatsapp_url'], 'https://wa.me/905452054356?text=' ), "case {$index} uses configured builder/default number" );
	wa_expect( $context['message_text'] === rawurldecode( substr( $context['whatsapp_url'], strpos( $context['whatsapp_url'], '?text=' ) + 6 ) ), "case {$index} message encoding round trips" );
}

wa_set( array( 'kind' => 'post', 'post_type' => 'post', 'id' => 20, 'title' => 'Article', 'request' => '/article/' ) );
$article = pera_get_whatsapp_context();
wa_expect( false === strpos( $article['message_text'], 'property in Istanbul' ), 'blog article never receives old generic property message' );

wa_set( array( 'kind' => 'page', 'post_type' => 'page', 'id' => 21, 'title' => '', 'request' => '/untitled/' ) );
$untitled = pera_get_whatsapp_context();
wa_expect( false === strpos( $untitled['message_text'], '“”' ) && false !== strpos( $untitled['message_text'], 'https://example.test/canonical/21/' ), 'missing singular title has clean punctuation and URL' );
wa_set( array( 'kind' => 'fallback', 'title' => '', 'request' => '/nothing/' ) );
$fallback = pera_get_whatsapp_context();
wa_expect( false === strpos( $fallback['message_text'], '“”' ) && false !== strpos( $fallback['message_text'], '/nothing/' ), 'untitled fallback has clean punctuation and current URL' );

$GLOBALS['wa_options']['pera_whatsapp_number'] = '+90 (555) 123-4567';
wa_expect( 'https://wa.me/905551234567?text=Hello%20%26%20welcome' === pera_get_whatsapp_url( 'Hello & welcome' ), 'configured WhatsApp number and encoding remain unchanged' );

wa_set( array( 'kind' => 'post', 'post_type' => 'post', 'id' => 88, 'title' => 'Translated tooltip', 'request' => '/blog/article/' ) );
ob_start();
pera_floating_whatsapp_button();
$button = ob_get_clean();
wa_expect( false !== strpos( $button, 'data-page-type="blog-article"' ) && false !== strpos( $button, 'data-post-title="Translated tooltip"' ), 'context metadata is rendered and escaped' );
wa_expect( in_array( 'theme.whatsapp.floating_button_label', $GLOBALS['wa_translation_keys'], true ), 'tooltip uses translation key' );
wa_expect( false !== strpos( file_get_contents( $theme . '/inc/whatsapp.php' ), 'floating-whatsapp__tooltip"><?php echo esc_html( $floating_label ); ?>' ), 'tooltip renders the translated accessible label' );

require_once $theme . '/inc/whatsapp-click-log.php';
foreach ( array( 'blog-article', 'property-search', 'citizenship-properties', 'property-taxonomy', 'blog-archive', 'blog-category', 'blog-tag', 'developer-sales-office', 'book-consultancy', 'property-map', 'luxury-property', 'contact', 'content-page' ) as $type ) {
	wa_expect( in_array( $type, pera_whatsapp_log_allowed_page_types(), true ), "click log allows {$type}" );
	$normalized = pera_whatsapp_normalize_payload( array( 'page_type' => $type, 'page_url' => 'https://example.test/source/' ) );
	wa_expect( $type === $normalized['page_type'], "click log normalization preserves {$type}" );
}
$main_js = file_get_contents( $theme . '/js/main.js' );
wa_expect( false !== strpos( $main_js, "target.closest('a[data-whatsapp=\"1\"][href]')" ), 'delegated inline WhatsApp tracking remains intact' );
wa_expect( false !== strpos( $main_js, "window.gtag('event'" ) && false !== strpos( $main_js, "'WhatsAppLead'" ) && false !== strpos( $main_js, 'navigator.sendBeacon' ), 'GA4 Meta and beacon tracking remain intact' );

echo "WhatsApp context regression tests passed.\n";
