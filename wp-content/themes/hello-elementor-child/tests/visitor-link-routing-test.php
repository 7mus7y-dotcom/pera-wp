<?php
/** Focused regressions for editor HTML and service ACF URL routing. */
define( 'ABSPATH', __DIR__ );
$GLOBALS['visitor_admin'] = false;
$GLOBALS['visitor_post_type'] = 'service';
function add_filter() {}
function is_admin() { return $GLOBALS['visitor_admin']; }
function get_post_type() { return $GLOBALS['visitor_post_type']; }
function pera_ml_url( $url ) {
	if ( ! is_string( $url ) || '' === $url || '#' === $url[0] || preg_match( '/^(?:mailto|tel):/i', $url ) ) return $url;
	if ( preg_match( '#^https://www\.peraproperty\.com(/.*)$#', $url, $matches ) ) $url = $matches[1];
	if ( 0 !== strpos( $url, '/' ) || 0 === strpos( $url, '/wp-content/' ) ) return $url;
	if ( preg_match( '#^/(?:de|ar|zh)(?:/|$)#', $url ) ) return $url;
	return '/ar' . $url;
}
function visitor_expect( $expected, $actual, $label ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAIL {$label}\n" ); exit( 1 ); } }
require dirname( __DIR__ ) . '/inc/theme-helpers.php';

$html = '<a href="/property/">Property</a><a href="https://www.peraproperty.com/contact-us/">Contact</a><a href="/property/?beds=2">Query</a><a href="/about-us/#team">Fragment</a><a href="https://external.test/">External</a><a href="mailto:a@b.test">Mail</a><a href="tel:+90">Tel</a><a href="/de/property/">Localized</a><a href="/wp-content/a.pdf">Media</a>';
$expected = '<a href="/ar/property/">Property</a><a href="/ar/contact-us/">Contact</a><a href="/ar/property/?beds=2">Query</a><a href="/ar/about-us/#team">Fragment</a><a href="https://external.test/">External</a><a href="mailto:a@b.test">Mail</a><a href="tel:+90">Tel</a><a href="/de/property/">Localized</a><a href="/wp-content/a.pdf">Media</a>';
visitor_expect( $expected, pera_localize_visitor_links( $html ), 'content links use the multilingual URL contract' );

$stored = 'https://www.peraproperty.com/sell-your-istanbul-real-estate/';
visitor_expect( '/ar/sell-your-istanbul-real-estate/', pera_localize_service_button_link( $stored, 50893 ), 'formatted service button URL localized' );
visitor_expect( 'https://www.peraproperty.com/sell-your-istanbul-real-estate/', $stored, 'raw service field value unchanged' );
$GLOBALS['visitor_admin'] = true;
visitor_expect( $stored, pera_localize_service_button_link( $stored, 50893 ), 'admin ACF value unchanged' );
echo "Visitor link routing tests passed\n";
