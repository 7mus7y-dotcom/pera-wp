<?php
// Offline adapter deliberately reuses the production classifier and normalizer.
define( 'ABSPATH', __DIR__ );
function add_action( ...$args ) {}
require dirname( __DIR__, 2 ) . '/wp-content/themes/hello-elementor-child/inc/modules/nearby-amenities.php';
while ( false !== ( $line = fgets( STDIN ) ) ) {
    $element = json_decode( $line, true, 512, JSON_THROW_ON_ERROR );
    echo json_encode( pera_nearby_normalize( array( $element ) ), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR ) . "\n";
    flush();
}
