<?php
/**
 * php tests/theme-font-family-tokens-test.php
 */

$theme = dirname( __DIR__ );
$css   = file_get_contents( $theme . '/css/main.css' );

function expect_theme_font_tokens( $condition, $label ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$label}\n" );
		exit( 1 );
	}
}

expect_theme_font_tokens(
	false !== strpos( $css, "--font-body: 'Montserrat', system-ui" ),
	'body font stack has one root token'
);
expect_theme_font_tokens(
	false !== strpos( $css, '--font-heading: var(--font-body);' ),
	'heading stack inherits from the body token by default'
);
expect_theme_font_tokens(
	false !== strpos( $css, 'font-family: var(--font-body);' ),
	'body consumes the body font token'
);
expect_theme_font_tokens(
	false !== strpos( $css, 'font-family: var(--font-heading);' ),
	'headings consume the heading font token'
);

echo "Theme font family token tests passed.\n";
