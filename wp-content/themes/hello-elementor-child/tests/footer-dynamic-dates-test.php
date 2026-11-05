<?php
if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

function expect_footer_dynamic_dates( $condition, $label ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL {$label}\n" );
		exit( 1 );
	}
}

$footer = file_get_contents( dirname( __DIR__ ) . '/footer.php' );

expect_footer_dynamic_dates( false !== strpos( $footer, "get_lastpostmodified( 'GMT' )" ), 'footer derives the last update from published site content' );
expect_footer_dynamic_dates( false !== strpos( $footer, "wp_date( get_option( 'date_format' ), \$site_last_modified_timestamp )" ), 'last update follows the configured WordPress date format' );
expect_footer_dynamic_dates( false !== strpos( $footer, "pera_ml_ui( 'This website was last updated on'" ), 'last-updated label is multilingual' );
expect_footer_dynamic_dates( false !== strpos( $footer, "wp_date( 'Y' )" ), 'copyright uses the current WordPress year' );
expect_footer_dynamic_dates( false === strpos( $footer, '<strong>©</strong> 2025' ), 'copyright year is not hard-coded' );

echo "Footer dynamic date tests passed\n";
