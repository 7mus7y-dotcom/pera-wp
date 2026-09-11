<?php
/**
 * Static regression checks for the focused multilingual URL-routing audit.
 *
 * Run: php tests/multilingual-url-routing-audit-test.php
 */

$theme = dirname( __DIR__ );
$files = array(
	'404.php',
	'archive.php',
	'attachment.php',
	'inc/ajax-property-archive.php',
	'inc/property-pagination.php',
	'page-book-a-consultancy.php',
	'page-citizenship.php',
	'page-contact.php',
	'page-luxury-property.php',
	'page-property-map.php',
	'page-sell-with-pera.php',
	'page-zh-citizenship.php',
	'partials/citizenship-latest-offers.php',
	'partials/portfolio-citizenship-cta.php',
	'parts/contact-cta.php',
	'single-bodrum-property.php',
	'single-post.php',
);

$failures = array();
$routed_calls = 0;
foreach ( $files as $relative ) {
	$source = file_get_contents( $theme . '/' . $relative );
	$routed_calls += substr_count( $source, 'pera_ml_url( home_url(' );

	foreach ( preg_split( '/\R/', $source ) as $index => $line ) {
		if ( false === strpos( $line, 'home_url(' ) && false === strpos( $line, 'home_url (' ) && false === strpos( $line, 'site_url(' ) ) {
			continue;
		}
		if ( false !== strpos( $line, 'View this page in English' ) || false !== strpos( $line, 'PHP_URL_PATH' ) ) {
			continue;
		}
		if ( false === strpos( $line, 'pera_ml_url( home_url(' ) ) {
			$failures[] = sprintf( '%s:%d has an un-routed visitor URL', $relative, $index + 1 );
		}
	}
}

if ( $routed_calls < 44 ) {
	$failures[] = sprintf( 'Expected at least 44 audited routed calls; found %d', $routed_calls );
}

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "Multilingual URL routing audit checks passed ({$routed_calls} routed calls).\n";
