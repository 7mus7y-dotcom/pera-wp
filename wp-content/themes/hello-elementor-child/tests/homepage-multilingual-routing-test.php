<?php
if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

/** Focused homepage rendering contracts layered on the router behavior suite. */

require dirname( __DIR__, 3 ) . '/plugins/pera-multilingual/tests/router-test.php';

function expect_homepage_routing( $condition, $label ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL {$label}\n" );
		exit( 1 );
	}
}

$theme     = dirname( __DIR__ );
$homepage  = file_get_contents( $theme . '/home-page.php' );
$editorial = file_get_contents( $theme . '/parts/home-editorial-posts.php' );
$card      = file_get_contents( $theme . '/parts/property-card-v2.php' );
$router    = file_get_contents( dirname( $theme, 2 ) . '/plugins/pera-multilingual/includes/class-router.php' );
$helpers   = file_get_contents( $theme . '/inc/theme-helpers.php' );

// Static buyer routes and district/region gateways must use the public helper.
foreach ( array(
	'/citizenship-by-investment/',
	'/turkish-citizenship-properties/',
	'/category/investment-advice/',
	'/property_tags/istanbul-investment-property-for-sale/',
	'/istanbul-luxury-property/',
	'/contact-us/',
	'/category/buyer-guides/',
	'/book-a-consultancy/',
	'/district/istanbul/besiktas/#results',
	'/district/istanbul/sisli/#results',
	'/district/istanbul/kadikoy/#results',
	'/district/istanbul/sariyer/#results',
	'/besiktas-from-bronze-age-to-ottoman-palaces_51249/',
	'/sisli-the-heart-of-modern-istanbul_51392/',
	'/kadikoy-regional-guide-a-vibrant-hub-on-istanbuls-asian-side_51561/',
	'/sell-your-istanbul-real-estate/',
	'/rent-your-istanbul-real-estate/',
) as $path ) {
	expect_homepage_routing(
		false !== strpos( $homepage, "pera_ml_url( home_url( '{$path}' ) )" ) || false !== strpos( $homepage, "pera_ml_url( home_url('{$path}') )" ),
		"homepage static URL {$path} uses Pera ML routing"
	);
}

expect_homepage_routing( 0 === preg_match( '/href=["\']\/(?!\/)/', $homepage ), 'homepage has no raw root-relative href' );
expect_homepage_routing( 0 === preg_match( '/href=["\']https?:\/\/(?:www\.)?peraproperty\.com/i', $homepage ), 'homepage has no raw same-site absolute href' );
expect_homepage_routing( 0 === preg_match( '/(?<!pera_ml_url\( )home_url\s*\(/', $homepage ), 'homepage has no unwrapped visitor-facing home_url call' );

// ACF WYSIWYG fields are localized only at render time; storage and copy remain untouched.
expect_homepage_routing( 2 === substr_count( $homepage, 'wp_kses_post( pera_localize_visitor_links(' ), 'both homepage ACF WYSIWYG fields localize links at render time' );
expect_homepage_routing( false !== strpos( $helpers, 'pera_ml_url( html_entity_decode(' ), 'editor-authored links delegate classification to Pera ML' );

// Editorial fallbacks are static links; real page/category/post links remain router-filtered.
foreach ( array( '/blog/', '/category/investment-advice/', '/category/regional-guides/' ) as $path ) {
	expect_homepage_routing( false !== strpos( $editorial, "pera_ml_url( home_url( '{$path}' ) )" ), "editorial fallback {$path} uses Pera ML routing" );
}
foreach ( array( "add_filter( 'post_link'", "add_filter( 'page_link'", "add_filter( 'post_type_link'", "add_filter( 'post_type_archive_link'", "add_filter( 'term_link'" ) as $filter ) {
	expect_homepage_routing( false !== strpos( $router, $filter ), "router owns {$filter}" );
}
expect_homepage_routing( false !== strpos( $card, 'get_permalink( $post_id )' ), 'working dynamic property permalinks remain WordPress-generated' );
expect_homepage_routing( false !== strpos( $card, 'get_term_link( $district_term )' ), 'working dynamic taxonomy permalinks remain WordPress-generated' );

echo "Homepage multilingual routing tests passed\n";
