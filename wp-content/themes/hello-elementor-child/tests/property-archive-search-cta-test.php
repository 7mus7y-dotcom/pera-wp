<?php
if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

/** Focused regression coverage for the property archive search CTA. */

define( 'ABSPATH', __DIR__ );

function expect_archive_cta( $condition, $label ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL {$label}\n" );
		exit( 1 );
	}
}

function get_query_var( $key, $default = null ) {
	return $default;
}
function set_query_var() {}
function get_template_part( $slug ) {
	if ( 'parts/property-search-cta-card' === $slug ) {
		echo '<aside class="property-search-cta">CTA</aside>';
	}
}
function esc_html( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function pera_ml_ui( $source ) {
	return $source;
}
function pera_render_property_card( array $args = array() ): void {
	static $card_number = 0;
	$card_number++;
	echo '<article class="property-card">PROPERTY-' . $card_number . '</article>';
}

final class Pera_Cta_Fake_Query {
	public $post_count;
	private $position = 0;

	public function __construct( int $post_count ) {
		$this->post_count = $post_count;
	}

	public function have_posts(): bool {
		return $this->position < $this->post_count;
	}

	public function the_post(): void {
		$this->position++;
	}
}

$theme = dirname( __DIR__ );
require_once $theme . '/inc/property-card-helpers.php';

$render = static function ( int $count, int $page ): string {
	ob_start();
	pera_render_property_archive_results( new Pera_Cta_Fake_Query( $count ), $page, array( 'variant' => 'archive' ) );
	return (string) ob_get_clean();
};

$twelve = $render( 12, 1 );
expect_archive_cta( 12 === substr_count( $twelve, 'class="property-card"' ), 'twelve results retain twelve property cards' );
expect_archive_cta( 1 === substr_count( $twelve, 'class="property-search-cta"' ), 'page one includes exactly one CTA' );
expect_archive_cta( preg_match( '/PROPERTY-6<\/article><aside class="property-search-cta">/', $twelve ) === 1, 'CTA follows property six' );

$five = $render( 5, 1 );
expect_archive_cta( preg_match( '/PROPERTY-17<\/article><aside class="property-search-cta">/', $five ) === 1, 'five-result CTA follows the final property' );

$one = $render( 1, 1 );
expect_archive_cta( preg_match( '/PROPERTY-18<\/article><aside class="property-search-cta">/', $one ) === 1, 'one-result CTA follows the property' );

$zero = $render( 0, 1 );
expect_archive_cta( strpos( $zero, 'no-results' ) < strpos( $zero, 'property-search-cta' ), 'empty state is retained before the CTA' );
expect_archive_cta( 0 === substr_count( $render( 12, 2 ), 'property-search-cta' ), 'page two never includes the CTA' );
expect_archive_cta( 0 === substr_count( $render( 0, 3 ), 'property-search-cta' ), 'later empty pages never include the CTA' );

$archive = file_get_contents( $theme . '/archive-property.php' );
$ajax    = file_get_contents( $theme . '/inc/ajax-property-archive.php' );
$helper  = file_get_contents( $theme . '/inc/property-card-helpers.php' );
$partial = file_get_contents( $theme . '/parts/property-search-cta-card.php' );
$query   = file_get_contents( $theme . '/inc/property-archive-query.php' );

expect_archive_cta( false !== strpos( $archive, 'pera_render_property_archive_results(' ), 'SSR uses shared archive renderer' );
expect_archive_cta( false !== strpos( $ajax, 'pera_render_property_archive_results(' ), 'AJAX uses shared archive renderer' );
expect_archive_cta( false !== strpos( $helper, 'min( 6, (int) $query->post_count )' ), 'insertion is capped at property six' );
expect_archive_cta( false !== strpos( $helper, 'pera_render_property_card( $card_args )' ), 'existing property cards retain their renderer' );
expect_archive_cta( false !== strpos( $partial, "home_url( '/book-a-consultancy/' )" ), 'consultancy uses the required internal path' );
expect_archive_cta( false !== strpos( $partial, 'pera_ml_url( $consultancy_url )' ), 'consultancy URL uses multilingual routing' );
expect_archive_cta( false === strpos( $partial, '/contact/' ) && false === strpos( $partial, '/contact-us/' ), 'CTA introduces no contact path' );
expect_archive_cta( substr_count( $partial, 'pera_ml_ui(' ) >= 6, 'all visitor-facing CTA strings use multilingual UI calls' );
expect_archive_cta( false !== strpos( $partial, 'pera_get_whatsapp_url(' ), 'WhatsApp uses the central URL helper' );
expect_archive_cta( false !== strpos( $partial, 'target="_blank" rel="noopener noreferrer"' ), 'WhatsApp preserves external-link conventions' );
expect_archive_cta( false !== strpos( $partial, 'data-whatsapp="1"' ) && false !== strpos( $partial, 'data-track-context="property_archive_search_cta"' ), 'CTA uses established analytics attributes' );
expect_archive_cta( false !== strpos( $partial, 'esc_url( $consultancy_url )' ) && false !== strpos( $partial, 'esc_url( $whatsapp_url )' ), 'CTA URLs are escaped' );
expect_archive_cta( false !== strpos( $query, "'posts_per_page'         => 12" ), 'query remains twelve properties per page' );
expect_archive_cta( false === strpos( $helper, 'found_posts =' ) && false === strpos( $helper, 'max_num_pages =' ), 'renderer does not alter totals or pagination' );

echo "Property archive search CTA tests passed\n";
