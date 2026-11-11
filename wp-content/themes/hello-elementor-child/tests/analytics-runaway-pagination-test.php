<?php
/**
 * Regression tests for malformed pagination URLs in the analytics tracker.
 * Run: php tests/analytics-runaway-pagination-test.php
 */
define( 'ABSPATH', __DIR__ . '/' );
function add_action( ...$args ) {}
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
require_once dirname( __DIR__ ) . '/inc/modules/analytics/tracker.php';

function expect_pagination( bool $actual, bool $expected, string $label ): void {
	if ( $actual !== $expected ) {
		fwrite( STDERR, "FAIL: {$label}\n" );
		exit( 1 );
	}
}

$bad = 'https://www.peraproperty.com/zh/property/?_specials=project%2Fpage%2F2%2F%2Fpage%2F9%2F%2Fpage%2F10%2F';
expect_pagination( pera_analytics_has_runaway_pagination_url( $bad ), true, 'repeated encoded pagination inside _specials' );
expect_pagination( pera_analytics_has_runaway_pagination_url( '/zh/property/?_specials=project/page/2//page/11/' ), true, 'repeated decoded pagination' );
expect_pagination( pera_analytics_has_runaway_pagination_url( 'https://www.peraproperty.com/zh/property/?_specials=project' ), false, 'valid filter' );
expect_pagination( pera_analytics_has_runaway_pagination_url( 'https://www.peraproperty.com/zh/property/page/2/?_specials=project' ), false, 'valid paged archive' );
expect_pagination( pera_analytics_has_runaway_pagination_url( 'https://www.peraproperty.com/de/property/?_specials=project' ), false, 'valid German archive' );
expect_pagination( pera_analytics_has_runaway_pagination_url( 'https://www.peraproperty.com/ar/property/?_specials=project' ), false, 'valid Arabic archive' );
expect_pagination( pera_analytics_has_runaway_pagination_url( 'https://www.peraproperty.com/ru/property/?_specials=project' ), false, 'valid Russian archive' );
expect_pagination( pera_analytics_has_runaway_pagination_url( '' ), false, 'empty referrer is legitimate' );
echo "Analytics malformed pagination tests passed\n";
