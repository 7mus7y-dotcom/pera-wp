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
function pera_render_nearby_amenities( $map ) {
    $GLOBALS['nearby_calls']++;
    echo $GLOBALS['nearby_fixture'];
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
    expect_preview( ( $map_present && '' !== $nearby ) === ( false !== strpos( $preview, 'grid--paired' ) ), 'only map and amenities together produce paired columns' );
    expect_preview( ( $about_present || ! empty( $facilities ) ) === ( false !== strpos( $preview, 'class="property-location__main"' ) ), 'no empty description block' );
    if ( $map_present ) {
        expect_preview( false !== strpos( $preview, '&amp;output=embed' ) && false !== strpos( $preview, 'Open in Google Maps' ), 'Google Maps embed and link preserved' );
    }
    if ( '' !== $nearby ) { expect_preview( false !== strpos( $preview, $nearby ), 'POI markup preserved verbatim' ); }
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
expect_preview( 1 === preg_match( '/@media \(min-width: 769px\)\s*\{\s*\.property-location--preview .*?\.property-location--preview \.property-location-preview__grid--paired\s*\{\s*grid-template-columns: minmax\(0, 9fr\) minmax\(0, 11fr\);/s', $css ), 'desktop preview has 45/55 columns at the existing breakpoint' );
expect_preview( 1 === preg_match( '/\.property-location--preview \.property-location-preview__grid\s*\{[^}]*grid-template-columns: minmax\(0, 1fr\);[^}]*margin-top: var\(--space-md\);/s', $css ), 'mobile stacks and row uses spacing token' );
foreach ( array( '[data-component-tabs] > h2', '.component-tabs', '[role="tabpanel"] .grid-2', '[role="tabpanel"] .card-shell', '[role="tabpanel"] h3' ) as $selector ) {
    expect_preview( false !== strpos( $css, '.property-location--preview .property-location-preview__nearby ' . $selector . ' {' ), 'amenity spacing scoped to preview: ' . $selector );
}
echo "Property layout preview tests passed (32 content combinations plus normal baseline comparisons)\n";
