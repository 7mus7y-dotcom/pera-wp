<?php
// Offline adapter deliberately reuses the production classifier and normalizer.
define( 'ABSPATH', __DIR__ );
function add_action( ...$args ) {}
require dirname( __DIR__, 2 ) . '/wp-content/themes/hello-elementor-child/inc/modules/nearby-amenities.php';
while ( false !== ( $line = fgets( STDIN ) ) ) {
    $element = json_decode( $line, true, 512, JSON_THROW_ON_ERROR );
    $places = pera_nearby_normalize( array( $element ) );
    foreach ( $places as $place ) {
        $error = pera_nearby_place_text_error( $place ) ?? pera_nearby_transport_error( $place );
        if ( $error !== null ) { fwrite( STDERR, $error . "\n" ); exit( 1 ); }
    }
    echo json_encode( $places, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR ) . "\n";
    flush();
}
