<?php
if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

/** Focused regression coverage for the property archive search CTA. */

define( 'ABSPATH', __DIR__ );

class WP_Query {
	public $found_posts = 0;
	public $max_num_pages = 0;
	private $query_vars = array();

	public function __construct( array $query_vars = array() ) {
		$this->query_vars = $query_vars;
	}

	public function get( $key ) {
		return $this->query_vars[ $key ] ?? null;
	}
}

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
function get_option() {
	return '';
}
function get_pagenum_link() {
	return 'https://example.test/property/';
}
function add_query_arg( $key, $value, $url ) {
	return $url . '?paged=' . $value;
}
function paginate_links( $args ) {
	$GLOBALS['pera_test_paginate_args'] = $args;
	return '<pagination total="' . (int) $args['total'] . '"></pagination>';
}
function wp_unslash( $value ) {
	return $value;
}
function sanitize_title( $value ) {
	return strtolower( preg_replace( '/[^a-z0-9-]+/i', '-', trim( (string) $value ) ) );
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
require_once $theme . '/inc/property-archive-query.php';
require_once $theme . '/inc/property-pagination.php';

$render = static function ( int $count, int $page ): string {
	ob_start();
	pera_render_property_archive_results( new Pera_Cta_Fake_Query( $count ), $page, array( 'variant' => 'archive' ) );
	return (string) ob_get_clean();
};

$page_one = $render( 11, 1 );
expect_archive_cta( 11 === substr_count( $page_one, 'class="property-card"' ), 'page one renders eleven property cards' );
expect_archive_cta( 1 === substr_count( $page_one, 'class="property-search-cta"' ), 'page one includes exactly one CTA' );
expect_archive_cta( preg_match( '/PROPERTY-6<\/article><aside class="property-search-cta">/', $page_one ) === 1, 'CTA follows property six' );

$five = $render( 5, 1 );
expect_archive_cta( preg_match( '/PROPERTY-16<\/article><aside class="property-search-cta">/', $five ) === 1, 'five-result CTA follows the final property' );

$one = $render( 1, 1 );
expect_archive_cta( preg_match( '/PROPERTY-17<\/article><aside class="property-search-cta">/', $one ) === 1, 'one-result CTA follows the property' );

$zero = $render( 0, 1 );
expect_archive_cta( strpos( $zero, 'no-results' ) < strpos( $zero, 'property-search-cta' ), 'empty state is retained before the CTA' );
$page_two_output = $render( 12, 2 );
expect_archive_cta( 12 === substr_count( $page_two_output, 'class="property-card"' ), 'later pages render twelve property cards' );
expect_archive_cta( 0 === substr_count( $page_two_output, 'property-search-cta' ), 'page two never includes the CTA' );
expect_archive_cta( 0 === substr_count( $render( 0, 3 ), 'property-search-cta' ), 'later empty pages never include the CTA' );

$expected_windows = array(
	1 => array( 'posts_per_page' => 11, 'offset' => 0 ),
	2 => array( 'posts_per_page' => 12, 'offset' => 11 ),
	3 => array( 'posts_per_page' => 12, 'offset' => 23 ),
	4 => array( 'posts_per_page' => 12, 'offset' => 35 ),
);
$previous_ids = array();
foreach ( $expected_windows as $page => $expected ) {
	$window = pera_property_archive_page_window( $page );
	expect_archive_cta( $expected === $window, "page {$page} uses the expected query window" );
	$ids = range( $window['offset'] + 1, $window['offset'] + $window['posts_per_page'] );
	if ( ! empty( $previous_ids ) ) {
		expect_archive_cta( 0 === count( array_intersect( $previous_ids, $ids ) ), "page {$page} duplicates no prior property" );
		expect_archive_cta( max( $previous_ids ) + 1 === min( $ids ), "page {$page} skips no property" );
	}
	$previous_ids = $ids;
}
expect_archive_cta( 12 === pera_property_archive_page_window( 2 )['offset'] + 1, 'load more page two starts with property twelve' );

$filtered_context = array(
	'paged'            => 2,
	'current_district' => array( 'Besiktas' ),
	'current_type'     => 'apartment',
	'current_keyword'  => 'Bosporus',
	'sort'             => 'price_asc',
);
$filtered_args = pera_property_archive_build_args_from_context( $filtered_context );
expect_archive_cta( 12 === $filtered_args['posts_per_page'] && 11 === $filtered_args['offset'], 'search and filter queries retain the page-two window' );
$filtered_context['paged'] = 1;
$filtered_args = pera_property_archive_build_args_from_context( $filtered_context );
expect_archive_cta( 11 === $filtered_args['posts_per_page'] && 0 === $filtered_args['offset'], 'reset search and filter requests use the page-one window' );

foreach ( array( 11 => 1, 12 => 2, 23 => 2, 24 => 3 ) as $found => $pages ) {
	expect_archive_cta( $pages === pera_property_archive_total_pages( $found ), "{$found} properties produce {$pages} page(s)" );
}

$synthetic_query                = new WP_Query();
$synthetic_query->found_posts   = 0;
$synthetic_query->max_num_pages = 5;
$synthetic_html = pera_render_property_pagination( $synthetic_query, 2, array( 'view' => 'map' ), 'https://example.test/citizenship/' );
expect_archive_cta( false !== strpos( $synthetic_html, 'total="5"' ), 'unmarked synthetic queries retain max_num_pages pagination' );
expect_archive_cta( 'Prev' === $GLOBALS['pera_test_paginate_args']['prev_text'] && 'Next' === $GLOBALS['pera_test_paginate_args']['next_text'], 'pagination labels remain unchanged' );
expect_archive_cta( array( 'view' => 'map' ) === $GLOBALS['pera_test_paginate_args']['add_args'], 'pagination query arguments remain unchanged' );
expect_archive_cta( false !== strpos( $GLOBALS['pera_test_paginate_args']['base'], 'https://example.test/citizenship/' ), 'pagination base URL remains unchanged' );

$archive_query                = new WP_Query( array( 'pera_mixed_archive_pagination' => true ) );
$archive_query->found_posts   = 24;
$archive_query->max_num_pages = 2;
$archive_html = pera_render_property_pagination( $archive_query, 1, array(), 'https://example.test/property/' );
expect_archive_cta( false !== strpos( $archive_html, 'total="3"' ), 'marked archive queries use the mixed total-page formula' );

$archive = file_get_contents( $theme . '/archive-property.php' );
$ajax    = file_get_contents( $theme . '/inc/ajax-property-archive.php' );
$helper  = file_get_contents( $theme . '/inc/property-card-helpers.php' );
$partial = file_get_contents( $theme . '/parts/property-search-cta-card.php' );
$query   = file_get_contents( $theme . '/inc/property-archive-query.php' );
$schema  = file_get_contents( $theme . '/inc/seo-property-archive.php' );
$pagination = file_get_contents( $theme . '/inc/property-pagination.php' );
$citizenship = file_get_contents( $theme . '/page-citizenship-properties.php' );
$latest_offers = file_get_contents( $theme . '/inc/latest-offers-card.php' );
$card_css = file_get_contents( $theme . '/css/property-card.css' );

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
expect_archive_cta( false !== strpos( $archive, 'pera_property_archive_build_args_from_context( $ctx )' ), 'SSR uses the shared query builder' );
expect_archive_cta( false !== strpos( $ajax, 'pera_property_archive_build_args_from_context( $ctx, $overrides )' ), 'AJAX uses the shared query builder' );
expect_archive_cta( false !== strpos( $query, "'pera_mixed_archive_pagination' => true" ), 'shared archive queries explicitly opt into mixed pagination' );
expect_archive_cta( false !== strpos( $pagination, "\$query->get( 'pera_mixed_archive_pagination' )" ), 'shared pagination requires the explicit query marker' );
expect_archive_cta( false === strpos( $citizenship, 'pera_mixed_archive_pagination' ), 'citizenship pagination does not opt in' );
expect_archive_cta( false === strpos( $latest_offers, 'pera_mixed_archive_pagination' ), 'Latest Offers pagination does not opt in' );
expect_archive_cta( false !== strpos( $ajax, "unset( \$facet_args['offset'], \$facet_args['pera_mixed_archive_pagination'] )" ), 'facet queries remove offsets and the pagination marker' );
expect_archive_cta( false !== strpos( $schema, 'pera_property_archive_position_offset( $paged )' ), 'schema uses the continuous property position offset' );
expect_archive_cta( 0 === pera_property_archive_position_offset( 1 ), 'page-one schema starts at position one' );
expect_archive_cta( 11 === pera_property_archive_position_offset( 2 ), 'page-two schema starts at position twelve' );
expect_archive_cta( 23 === pera_property_archive_position_offset( 3 ), 'page-three schema starts at position twenty-four' );
expect_archive_cta( 35 === pera_property_archive_position_offset( 4 ), 'page-four schema starts at position thirty-six' );
expect_archive_cta( false === strpos( $helper, 'found_posts =' ) && false === strpos( $helper, 'max_num_pages =' ), 'renderer does not alter totals or pagination' );

$cta_start = strpos( $card_css, '.property-search-cta {' );
$cta_gradient = strpos( $card_css, 'radial-gradient', $cta_start );
$cta_fallback = strpos( $card_css, 'background: var(--bg-soft);', $cta_start );
expect_archive_cta( false !== $cta_fallback && $cta_fallback < $cta_gradient, 'CTA has a solid light background fallback before its gradient' );
$os_dark_start = strpos( $card_css, '@media (prefers-color-scheme: dark)', $cta_start );
$explicit_dark_start = strpos( $card_css, '[data-theme="dark"] .property-search-cta', $os_dark_start );
$os_dark_rule = substr( $card_css, $os_dark_start, $explicit_dark_start - $os_dark_start );
expect_archive_cta( false !== $os_dark_start && false !== strpos( $os_dark_rule, '.property-search-cta' ), 'CTA supports OS-driven dark mode' );
expect_archive_cta( false !== strpos( $os_dark_rule, 'background: var(--pill-bg-dark);' ) && false === strpos( $os_dark_rule, 'var(--inverse)' ), 'OS dark mode uses a solid dark surface fallback' );
expect_archive_cta( false !== $explicit_dark_start && false !== strpos( $card_css, '.dark .property-search-cta', $explicit_dark_start ), 'CTA retains explicit dark-mode selectors' );
expect_archive_cta( false === strpos( $card_css, '--bg-soft:' ) && false === strpos( $card_css, '--pill-bg-dark:' ), 'CTA styling does not redefine global colour tokens' );

echo "Property archive search CTA tests passed\n";
