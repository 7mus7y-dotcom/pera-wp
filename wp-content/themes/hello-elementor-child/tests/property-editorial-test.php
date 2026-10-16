<?php
/** Run with php; --fixtures emits real template sections for the browser test. */
function editorial_expect( $ok, $message ) {
    if ( ! $ok ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); }
}
function pera_ml_ui( $text, $key ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( $text, ENT_QUOTES ); }
function wpautop( $text ) { return '<p>' . $text . '</p>'; }
function wp_kses_post( $text ) { return strip_tags( $text, '<p><strong><em><a>' ); }
$theme = dirname( __DIR__ );
$template = file_get_contents( $theme . '/single-property.php' );
$start = strpos( $template, '<?php if ( $has_property_editorial_content ) : ?>' );
$end = strpos( $template, '<?php if ( $has_floor_plans ) : ?>', $start );
$section = substr( $template, $start, $end - $start );
function render_editorial( $section, $visitor, $mask ) {
    $has_property_editorial_intro_card = (bool) ( $mask & 1 );
    $has_property_editorial_intro = $has_property_editorial_intro_card;
    $has_property_district_analysis = (bool) ( $mask & 2 );
    $has_property_investment_potential = (bool) ( $mask & 4 );
    $has_estimated_rental_yield = (bool) ( $mask & 8 );
    $has_property_buyer_suitability = (bool) ( $mask & 16 );
    $has_property_developer_profile = (bool) ( $mask & 32 );
    $property_faq_items = $mask & 64 ? array( array( 'question' => 'Question?', 'answer' => 'Answer' ) ) : array();
    $has_property_editorial_content = (bool) $mask;
    $property_editorial_intro = 'Intro with enough content to wrap over several lines. ' . str_repeat( 'Editorial content. ', 20 );
    $property_district_analysis = 'District analysis';
    $property_investment_potential = 'Investment potential';
    $estimated_rental_yield = '5%';
    $property_buyer_suitability = 'Buyer suitability';
    $property_developer_profile = 'Developer profile';
    $property_highlights_items = array( 'Highlight' );
    $property_key_advantages_items = array( 'Advantage' );
    $target_buyer_type_items = array( 'Investor' );
    ob_start(); eval( '?>' . $section ); return ob_get_clean();
}
$fixtures = array();
for ( $mask = 0; $mask < 128; $mask++ ) {
    $html = render_editorial( $section, true, $mask );
    $normal = render_editorial( $section, false, $mask );
    editorial_expect( $normal === $html, "all visitors receive same layout: $mask" );
    preg_match_all( '/<h2>(.*?)<\/h2>/', $html, $headings );
    $expected = array();
    if ( $mask & 1 ) $expected[] = 'Why this property?';
    if ( $mask & 2 ) $expected[] = 'Location and district analysis';
    if ( $mask & 12 ) $expected[] = 'Investment and rental potential';
    if ( $mask & 16 ) $expected[] = 'Who this property is suitable for';
    if ( $mask & 32 ) $expected[] = 'Developer and construction credibility';
    if ( $mask & 64 ) $expected[] = 'Property questions';
    editorial_expect( $expected === $headings[1], "optional cards and heading order: $mask" );
    editorial_expect( substr_count( $html, 'card-shell property-editorial-card' ) === count( $expected ), "no placeholders: $mask" );
    editorial_expect( substr_count( $html, 'property-editorial-card--wide' ) === ( $mask & 64 ? 1 : 0 ), "FAQ alone spans: $mask" );
    $fixtures[$mask] = $html;
}
editorial_expect( false === strpos( $template, '$layout_preview' ), 'no preview branch' );
if ( in_array( '--fixtures', $argv, true ) ) echo json_encode( $fixtures, JSON_THROW_ON_ERROR );
else echo "Permanent editorial passed: 128 optional-field combinations, visitor parity, headings and FAQ\n";
