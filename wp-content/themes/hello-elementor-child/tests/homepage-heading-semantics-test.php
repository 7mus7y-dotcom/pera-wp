<?php
/**
 * php tests/homepage-heading-semantics-test.php
 */

$theme    = dirname( __DIR__ );
$homepage = file_get_contents( $theme . '/home-page.php' );
$css      = file_get_contents( $theme . '/css/main.css' );

function expect_homepage_heading_semantics( $condition, $label ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$label}\n" );
		exit( 1 );
	}
}

expect_homepage_heading_semantics(
	0 === preg_match_all( '/<h5\\b/i', $homepage ),
	'homepage does not use level-five headings for signoff names'
);
expect_homepage_heading_semantics(
	2 === substr_count( $homepage, 'class="signoff-name"' ),
	'both homepage signoff names use the semantic text class'
);
expect_homepage_heading_semantics(
	false !== strpos( $css, '.signoff-text .signoff-name' ),
	'signoff name appearance is preserved explicitly'
);

echo "Homepage heading semantics tests passed.\n";
