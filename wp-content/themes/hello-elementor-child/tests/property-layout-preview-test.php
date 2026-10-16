<?php
/** Run with php: exercises the actual location section with WordPress/ACF stubs. */
function expect_preview( $condition, $message ) {
    if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); }
}
function get_field( $key, $id ) { return $GLOBALS['fields'][$key] ?? ''; }
function pera_ml_ui( $text, $key ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( $text, ENT_QUOTES ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_url( $text ) { return esc_html( $text ); }
function apply_filters( $hook, $text ) { return $text; }
function pera_render_nearby_amenities( $map, $shared_heading_id = '' ) {
    $GLOBALS['nearby_calls']++;
    $html = $GLOBALS['nearby_fixture'];
    if ( $shared_heading_id ) {
        $html = str_replace( 'class="card-shell"', 'aria-labelledby="' . $shared_heading_id . '"', $html );
        $html = str_replace( '<h2>Nearby amenities</h2>', '', $html );
    }
    echo $html;
}
function location_section( $template ) {
    $start = strpos( $template, '<section class="section section-soft property-location' );
    $end = strpos( $template, '</section>', $start );
    expect_preview( false !== $start && false !== $end, 'location section exists' );
    return substr( $template, $start, $end + strlen( '</section>' ) - $start );
}
function render_location( $section, $layout_preview, $fields, $nearby, $facilities ) {
    $GLOBALS['fields'] = $fields;
    $GLOBALS['nearby_fixture'] = $nearby;
    $GLOBALS['nearby_calls'] = 0;
    $property_id = 42;
    $has_facilities = ! empty( $facilities );
    ob_start();
    eval( '?>' . $section );
    $html = ob_get_clean();
    expect_preview( 1 === $GLOBALS['nearby_calls'], 'POI renderer runs exactly once' );
    expect_preview( substr_count( $html, '<div' ) === substr_count( $html, '</div>' ), 'balanced layout wrappers' );
    return $html;
}
$theme = dirname( __DIR__ );
$template = file_get_contents( $theme . '/single-property.php' );
$section = location_section( $template );
// Baseline from main at 005eecdaf6c5a1621a436072c9cc1b2f01e770b3; compare normal output ignoring whitespace.
$baseline = file_get_contents( __DIR__ . '/fixtures/property-location-before-refinements.html' );
$nearby_content = '<button role="tab">Transport</button><div role="tabpanel">POI fixture</div>';
$nearby_markup = '<section class="card-shell" data-component-tabs><h2>Nearby amenities</h2><button role="tab">Transport</button><div role="tabpanel">POI fixture</div></section>';
foreach ( array( false, true ) as $map_present ) {
foreach ( array( '', '<p>Saved distance fixture</p>' ) as $distances ) {
foreach ( array( '', $nearby_markup ) as $nearby ) {
foreach ( array( false, true ) as $about_present ) {
foreach ( array( array(), array( 'Pool' ) ) as $facilities ) {
    $fields = array( 'map' => $map_present ? array( 'lat' => 41.01, 'lng' => 28.97, 'address' => 'Istanbul' ) : null,
        'distances' => $distances, 'about_this_project' => $about_present ? '<p>Description fixture</p>' : '' );
    $preview = render_location( $section, true, $fields, $nearby, $facilities );
    expect_preview( false === strpos( $preview, 'property-map-card__distances' ) && false === strpos( $preview, 'Saved distance fixture' ) && false === strpos( $preview, 'Location &amp; distances' ), 'preview suppresses distances heading and content' );
    expect_preview( $map_present === ( false !== strpos( $preview, '<aside' ) ), 'only coordinates produce a preview map column' );
    expect_preview( ( $map_present || '' !== $nearby ) === ( false !== strpos( $preview, 'class="property-location-preview__grid' ) ), 'no empty preview row' );
    expect_preview( ( $map_present && '' !== $nearby ) === ( false !== strpos( $preview, 'property-location-preview__grid--paired' ) ), 'paired columns only when both contents exist' );
    $has_panel = $map_present || '' !== $nearby;
    expect_preview( substr_count( $preview, 'card-shell' ) === ( $has_panel ? 1 : 0 ), 'exactly one outer card and no nested outer cards' );
    expect_preview( preg_match_all( '/<h2[^>]*>.*?Nearby amenities<\/h2>/s', $preview ) === ( $has_panel ? 1 : 0 ), 'exactly one shared Nearby amenities H2 when content exists' );
    expect_preview( false === strpos( $preview, 'property-map-card__title' ), 'no Map heading in preview' );
    if ( $has_panel ) {
        expect_preview( false !== strpos( $preview, 'aria-hidden="true" focusable="false"><use href="#icon-map"' ), 'decorative existing map-pin icon' );
        if ( $about_present || $facilities ) expect_preview( strpos( $preview, 'property-location__main' ) < strpos( $preview, 'card-shell' ), 'description and facilities precede panel' );
    }
    if ( $map_present && '' !== $nearby ) {
        expect_preview( strpos( $preview, '<aside' ) < strpos( $preview, $nearby_content ), 'map appears before nearby amenities' );
    }
    expect_preview( ( $about_present || ! empty( $facilities ) ) === ( false !== strpos( $preview, 'class="property-location__main"' ) ), 'no empty description block' );
    if ( $map_present ) {
        expect_preview( false !== strpos( $preview, '&amp;output=embed' ) && false !== strpos( $preview, 'Open in Google Maps' ), 'Google Maps embed and link preserved' );
    }
    if ( '' !== $nearby ) { expect_preview( false !== strpos( $preview, $nearby_content ), 'POI tabs and content preserved' ); }
    $normal = render_location( $section, false, $fields, $nearby, $facilities );
    $original = render_location( $baseline, false, $fields, $nearby, $facilities );
    expect_preview( preg_replace( '/\s+/', ' ', $normal ) === preg_replace( '/\s+/', ' ', $original ), 'normal rendering unchanged' );
    expect_preview( ( $map_present && '' !== $distances ) === ( false !== strpos( $normal, 'Saved distance fixture' ) ), 'normal distances retain original visibility' );
}
}
}
}
}
foreach ( array( array(), array( 'lat' => 41.01 ), array( 'lng' => 28.97 ), 'invalid' ) as $missing_map ) {
    $fields = array( 'map' => $missing_map, 'distances' => '<p>Saved distance fixture</p>' );
    $html = render_location( $section, true, $fields, $nearby_markup, array() );
    expect_preview( false === strpos( $html, '<aside' ) && false === strpos( $html, 'grid--paired' ), 'incomplete coordinates cannot retain a map column via distances' );
}
$css = file_get_contents( $theme . '/css/main.css' );
expect_preview( 1 === preg_match( '/@media \(min-width: 769px\)\s*\{\s*\.property-location--preview \.property-location-preview__grid--paired\s*\{\s*grid-template-columns: minmax\(0, 2fr\) minmax\(0, 3fr\);/s', $css ), 'desktop uses 40/60 split only for paired columns' );
expect_preview( 1 === preg_match( '/\.property-location--preview \.property-location-preview__grid\s*\{[^}]*grid-template-columns: minmax\(0, 1fr\);/s', $css ), 'mobile and single-content layouts stack' );
expect_preview( 1 === preg_match( '/\.property-location--preview \.property-map-card,\s*\.property-location--preview \.property-location-preview__nearby > \[data-component-tabs\]\s*\{\s*border: 0;\s*border-radius: 0;\s*background: transparent;\s*box-shadow: none;\s*padding: 0;\s*margin: 0;/s', $css ), 'both outer column wrappers have no nested panel styling' );
expect_preview( 1 === preg_match( '/\.property-location--preview \.property-location-preview__heading \.icon\s*\{\s*width: 1em;\s*height: 1em;\s*flex-shrink: 0;/s', $css ), 'shared icon scales with heading' );
expect_preview( false !== strpos( $css, 'align-items: center;' ), 'icon vertically centered' );
expect_preview( false !== strpos( $css, 'min-width: 0;' ) && false !== strpos( $css, 'overflow-wrap: anywhere;' ), 'columns can shrink and long text wraps' );
expect_preview( false === strpos( $css, '.property-location--preview .property-location-preview__nearby [role="tabpanel"] .card-shell {' ), 'subgroup cards retain existing styling' );
echo "Property layout preview tests passed (32 content combinations plus normal baseline comparisons)\n";
