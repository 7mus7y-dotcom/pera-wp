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
function get_post_type( $id ) { return in_array( (int) $id, array( 11, 12, 13, 14, 15 ), true ) ? 'attachment' : 'property'; }
function get_post_mime_type( $id ) { return in_array( (int) $id, array( 11, 13, 14, 15 ), true ) ? 'video/mp4' : ( 12 === (int) $id ? 'image/jpeg' : '' ); }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_text_field( $value ) { return trim( strip_tags( preg_replace( '/[\r\n\t]+/', ' ', (string) $value ) ) ); }
function add_action() {}
function get_post_status( $id ) { return 14 === $id ? 'trash' : 'inherit'; }
function wp_get_attachment_url( $id ) { return 15 === $id ? false : "https://example.test/{$id}.mp4"; }
function wp_http_validate_url( $url ) { return filter_var( $url, FILTER_VALIDATE_URL ); }
function get_post_field( $field, $id, $context ) { return "Attachment {$id}"; }
class WP_Post {
	public $post_type = 'property';
}
function wp_is_post_autosave() { return false; }
function wp_is_post_revision() { return false; }
function wp_unslash( $value ) { return $value; }
function wp_verify_nonce() { return true; }
function current_user_can() { return true; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['latest_offer_meta'] = $value; }

require dirname( __DIR__ ) . '/inc/admin/property-latest-offers.php';
require dirname( __DIR__ ) . '/inc/latest-offers-card.php';

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

latest_offer_video_expect( false === $legacy['show_in_gallery'], 'legacy rows default to off' );

// Save, read, and order the same records that the property template uses.
$_POST = array(
	'pera_property_latest_offers_nonce' => 'test',
	'pera_latest_offers' => array(
		array( 'type' => '2+1', 'floor_plan_id' => 12, 'video_id' => '11', 'video_text' => ' Tour caption ', 'show_in_gallery' => '1' ),
		array( 'video_id' => '13', 'video_text' => ' ' ),
	),
);
pera_property_latest_offers_save( 1, new WP_Post() );
$saved = pera_property_latest_offers_get_rows( 1 );
latest_offer_video_expect( true === $saved[0]['show_in_gallery'] && false === $saved[1]['show_in_gallery'], 'checkbox persists independently for each row' );
latest_offer_video_expect( '2+1' === $saved[0]['type'] && 12 === $saved[0]['floor_plan_id'] && 13 === $saved[1]['video_id'], 'unrelated row data survives saving' );

$images = array( array( 'id' => 31 ), array( 'id' => 32 ), array( 'id' => 33 ) );
$main = array( 'id' => 32 );
$primary = array( 'id' => 11, 'type' => 'video', 'label' => 'Primary caption' );
$rows = pera_latest_offers_get_rows( 1 );
$media = pera_property_gallery_media_items( $images, $main, array(), $rows );
latest_offer_video_expect( array( 32, 11, 31, 33 ) === array_column( $media, 'id' ), 'enabled offer is second without a primary and unchecked offer is omitted' );
latest_offer_video_expect( 'Tour caption' === $media[1]['label'] && 'https://example.test/11.mp4' === $media[1]['url'], 'offer uses the existing attachment and Video text' );

unset( $_POST['pera_latest_offers'][0]['show_in_gallery'] );
pera_property_latest_offers_save( 1, new WP_Post() );
latest_offer_video_expect( $images === pera_property_gallery_media_items( $images, $main, array(), pera_latest_offers_get_rows( 1 ) ), 'unchecking restores the original image-only gallery' );

$GLOBALS['latest_offer_meta'] = array(
	array( 'video_id' => 11, 'show_in_gallery' => true, 'video_text' => 'First caption' ),
	array( 'video_id' => 11, 'show_in_gallery' => true, 'video_text' => 'Duplicate caption' ),
	array( 'video_id' => 13, 'show_in_gallery' => true, 'video_text' => ' ' ),
	array( 'show_in_gallery' => true ),
	array( 'video_id' => 12, 'show_in_gallery' => true ),
	array( 'video_id' => 14, 'show_in_gallery' => true ),
	array( 'video_id' => 15, 'show_in_gallery' => true ),
	array( 'video_id' => 99, 'show_in_gallery' => true ),
	array( 'video_id' => '11broken', 'show_in_gallery' => true ),
);
$rows = pera_latest_offers_get_rows( 1 );
$media = pera_property_gallery_media_items( $images, $main, $primary, $rows );
latest_offer_video_expect( array( 32, 11, 13, 31, 33 ) === array_column( $media, 'id' ), 'primary wins deduplication and precedes the enabled offer' );
latest_offer_video_expect( 'Primary caption' === $media[1]['label'] && 'Attachment 13' === $media[2]['label'], 'primary caption is retained and missing offer caption falls back to the attachment title' );
$media = pera_property_gallery_media_items( $images, $main, array(), $rows );
latest_offer_video_expect( array( 32, 11, 13, 31, 33 ) === array_column( $media, 'id' ) && 'First caption' === $media[1]['label'], 'unique offer videos retain row order and the first caption' );
$media = pera_property_gallery_media_items( array(), $main, array(), $rows );
latest_offer_video_expect( array( 32, 11, 13 ) === array_column( $media, 'id' ), 'main image leads opted-in videos without an ACF gallery' );
latest_offer_video_expect( $images === pera_property_gallery_media_items( $images, $main, array(), array_slice( $rows, 3 ) ), 'missing, deleted, malformed, and non-MP4 attachments are ignored' );

$partial = file_get_contents( dirname( __DIR__ ) . '/partials/latest-offers-card.php' );
$script  = file_get_contents( dirname( __DIR__ ) . '/js/latest-offers-video.js' );
$model   = file_get_contents( dirname( __DIR__ ) . '/inc/latest-offers-card.php' );
latest_offer_video_expect( false !== strpos( $partial, 'data-pera-offer-video-open' ), 'valid-video card branch renders the Watch video control' );
latest_offer_video_expect( false !== strpos( $partial, "if ( '' !== \$video_url )" ), 'card without a valid video suppresses the control' );
latest_offer_video_expect( false !== strpos( $partial, "data-video-text=\"<?php echo esc_attr( \$video_text ); ?>\"" ), 'card carries safely escaped row caption data' );
latest_offer_video_expect( false !== strpos( $model, 'data-pera-offer-video-caption hidden' ), 'empty caption is hidden in reusable modal markup' );
latest_offer_video_expect( false !== strpos( $model, 'controls playsinline preload="metadata"' ), 'modal uses required native video attributes' );
latest_offer_video_expect( false !== strpos( $model, "add_action( 'wp_footer', 'pera_latest_offers_render_video_modal', 19 )" ), 'modal renders before WordPress footer scripts at priority 20' );
latest_offer_video_expect( false !== strpos( $script, "document.addEventListener('click'" ), 'delegated click handling supports dynamically inserted cards' );
latest_offer_video_expect( false === strpos( $script, 'querySelectorAll(\'[data-pera-offer-video-open]\')' ), 'video controls are not bound only at initial load' );
latest_offer_video_expect( false !== strpos( $script, 'player.pause();' ) && false !== strpos( $script, 'player.currentTime = 0;' ), 'close stops and resets playback' );
latest_offer_video_expect( false !== strpos( $script, "event.key === 'Escape'" ), 'Escape closes the modal' );
latest_offer_video_expect( false !== strpos( $script, 'returnFocus.focus();' ), 'focus returns to the opening card button' );

echo "Latest Offers row video tests passed\n";
