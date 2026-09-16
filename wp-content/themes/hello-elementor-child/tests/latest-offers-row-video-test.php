<?php
if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ );

function latest_offer_video_expect( $condition, $label ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL {$label}\n" );
		exit( 1 );
	}
}

$GLOBALS['latest_offer_meta'] = array();
function get_post_meta() { return $GLOBALS['latest_offer_meta']; }
function get_post_type( $id ) { return in_array( (int) $id, array( 11, 12 ), true ) ? 'attachment' : 'property'; }
function get_post_mime_type( $id ) { return 11 === (int) $id ? 'video/mp4' : ( 12 === (int) $id ? 'image/jpeg' : '' ); }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_text_field( $value ) { return trim( strip_tags( preg_replace( '/[\r\n\t]+/', ' ', (string) $value ) ) ); }
function add_action() {}

require dirname( __DIR__ ) . '/inc/admin/property-latest-offers.php';

$GLOBALS['latest_offer_meta'] = array( array( 'type' => '2+1', 'floor_plan_id' => 12 ) );
$legacy = pera_property_latest_offers_get_rows( 1 )[0];
latest_offer_video_expect( 0 === $legacy['video_id'] && '' === $legacy['video_text'], 'legacy rows receive safe video defaults' );
latest_offer_video_expect( '2+1' === $legacy['type'] && 12 === $legacy['floor_plan_id'], 'existing row and floor-plan values remain intact' );

$GLOBALS['latest_offer_meta'] = array(
	array( 'video_id' => 11, 'video_text' => " Tour <b>caption</b>\n" ),
	array( 'video_id' => 99 ),
	array( 'video_id' => 12 ),
);
$rows = pera_property_latest_offers_get_rows( 1 );
latest_offer_video_expect( 11 === $rows[0]['video_id'], 'valid MP4 attachment is retained' );
latest_offer_video_expect( 0 === $rows[1]['video_id'], 'non-attachment ID is rejected' );
latest_offer_video_expect( 0 === $rows[2]['video_id'], 'non-MP4 attachment is rejected' );
latest_offer_video_expect( 'Tour caption' === $rows[0]['video_text'], 'video text is sanitized' );

$partial = file_get_contents( dirname( __DIR__ ) . '/partials/latest-offers-card.php' );
$script  = file_get_contents( dirname( __DIR__ ) . '/js/latest-offers-video.js' );
$model   = file_get_contents( dirname( __DIR__ ) . '/inc/latest-offers-card.php' );
latest_offer_video_expect( false !== strpos( $partial, 'data-pera-offer-video-open' ), 'valid-video card branch renders the Watch video control' );
latest_offer_video_expect( false !== strpos( $partial, "if ( '' !== \$video_url )" ), 'card without a valid video suppresses the control' );
latest_offer_video_expect( false !== strpos( $partial, "data-video-text=\"<?php echo esc_attr( \$video_text ); ?>\"" ), 'card carries safely escaped row caption data' );
latest_offer_video_expect( false !== strpos( $model, 'data-pera-offer-video-caption hidden' ), 'empty caption is hidden in reusable modal markup' );
latest_offer_video_expect( false !== strpos( $model, 'controls playsinline preload="metadata"' ), 'modal uses required native video attributes' );
latest_offer_video_expect( false !== strpos( $script, "document.addEventListener('click'" ), 'delegated click handling supports dynamically inserted cards' );
latest_offer_video_expect( false === strpos( $script, 'querySelectorAll(\'[data-pera-offer-video-open]\')' ), 'video controls are not bound only at initial load' );
latest_offer_video_expect( false !== strpos( $script, 'player.pause();' ) && false !== strpos( $script, 'player.currentTime = 0;' ), 'close stops and resets playback' );
latest_offer_video_expect( false !== strpos( $script, "event.key === 'Escape'" ), 'Escape closes the modal' );
latest_offer_video_expect( false !== strpos( $script, 'returnFocus.focus();' ), 'focus returns to the opening card button' );

echo "Latest Offers row video tests passed\n";
