<?php
if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

/** Focused regression coverage for uploaded property videos in the gallery. */
function expect_property_video( $condition, $label ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL {$label}\n" );
		exit( 1 );
	}
}

/** Mirror the template's media insertion rule with simple values for ordering checks. */
function property_video_test_media_order( $photos, $has_video ) {
	$media = $photos;
	if ( $has_video ) {
		array_splice( $media, empty( $photos ) ? 0 : 1, 0, array( 'video' ) );
	}
	return $media;
}

$theme_dir = dirname( __DIR__ );
$template  = file_get_contents( $theme_dir . '/single-property.php' );
$script    = file_get_contents( $theme_dir . '/js/main.js' );

expect_property_video( false === strpos( $template, 'wp_get_attachment_mime_type(' ), 'nonexistent MIME function is absent' );
expect_property_video( false !== strpos( $template, 'get_post_mime_type( $custom_video_attachment_id )' ), 'WordPress post MIME API validates the attachment' );
expect_property_video( false !== strpos( $template, "0 === strpos( \$validated_video_mime, 'video/' )" ), 'attachment must have a video MIME type' );
expect_property_video( false !== strpos( $template, 'wp_get_attachment_url( $custom_video_attachment_id )' ), 'canonical attachment URL is used' );
expect_property_video( false !== strpos( $template, 'wp_http_validate_url( $validated_video_url )' ), 'canonical attachment URL must pass validation' );
expect_property_video( false !== strpos( $template, 'attachment_url_to_postid( $custom_video_candidate_url )' ), 'ACF array URL fallback resolves an attachment ID' );
expect_property_video( false !== strpos( $template, 'attachment_url_to_postid( $custom_video_file )' ), 'ACF URL return value resolves an attachment ID' );

expect_property_video( false !== strpos( $template, 'if ( $custom_video_url ) :' ), 'a valid uploaded video enables the gallery tile' );
expect_property_video( false !== strpos( $template, 'property-gallery__video-trigger' ), 'video is rendered as a gallery tile' );
expect_property_video( false !== strpos( $template, '$gallery_media_items = $gallery_items;' ), 'media ordering starts with the validated photos' );
expect_property_video( false !== strpos( $template, 'empty( $gallery_items ) ? 0 : 1' ), 'video insertion point is first without photos and second with photos' );
expect_property_video( false !== strpos( $template, "array( array( 'type' => 'video' ) )" ), 'video is inserted into the shared media order' );
expect_property_video( 1 === substr_count( $template, 'property-gallery__video-trigger' ), 'video tile is rendered exactly once' );
expect_property_video( 1 === substr_count( $template, 'data-gallery-type="video"' ), 'video has exactly one lightbox trigger' );
expect_property_video(
	array( 'photo-1', 'video', 'photo-2', 'photo-3' ) === property_video_test_media_order( array( 'photo-1', 'photo-2', 'photo-3' ), true ),
	'video follows the first photo instead of being appended and trigger order matches tile order'
);
expect_property_video(
	array( 'video' ) === property_video_test_media_order( array(), true ),
	'video remains the sole gallery item when there are no photos'
);
expect_property_video(
	array( 'photo-1', 'photo-2', 'photo-3' ) === property_video_test_media_order( array( 'photo-1', 'photo-2', 'photo-3' ), false ),
	'photo ordering is unchanged without a valid video'
);
expect_property_video( false !== strpos( $template, '$custom_video_poster_url' ), 'main image is normalized as the video poster' );
expect_property_video( false !== strpos( $template, 'poster="' ), 'main image poster is applied to the native player' );
expect_property_video( false !== strpos( $template, 'property-gallery__item--video-fallback' ), 'missing poster has a styled fallback tile' );
expect_property_video( false === strpos( $template, 'class="section section-soft property-video-tour"' ), 'standalone MP4 section is removed' );
expect_property_video( false === strpos( $template, "get_field( 'custom_video_text'" ), 'standalone custom video text block is removed' );
expect_property_video( false !== strpos( $template, '<video class="lightbox__video" controls playsinline preload="metadata"' ), 'native modal video has required playback attributes' );
expect_property_video( false !== strpos( $template, 'esc_attr( $custom_video_mime_type )' ), 'validated MIME type is escaped' );
expect_property_video( false !== strpos( $template, '$custom_video_heading ?: pera_ml_ui(' ), 'custom heading has a translated Apartment tour fallback' );

expect_property_video( false !== strpos( $script, 'lightboxVideo.pause();' ), 'closing or changing media pauses video' );
expect_property_video( false !== strpos( $script, 'lightboxVideo.currentTime = 0;' ), 'closing or changing media resets video' );
expect_property_video( false !== strpos( $script, "event.key === 'Escape'" ), 'Escape closes the modal' );
expect_property_video( false !== strpos( $script, "querySelectorAll('[data-gallery-close]')" ), 'close button and backdrop share close handling' );
expect_property_video( false !== strpos( $script, 'galleryReturnFocus.focus();' ), 'focus returns to the gallery trigger' );

expect_property_video( false !== strpos( $template, '$photo_count = count( $gallery_ids );' ), 'photo count remains based only on image IDs' );
expect_property_video( false !== strpos( $template, 'data-gallery-type="image"' ), 'existing images remain in the shared lightbox' );
expect_property_video( false !== strpos( $template, "get_field( 'yt_video', \$property_id )" ), 'YouTube field remains supported' );
expect_property_video( false !== strpos( $template, 'wp_oembed_get( esc_url_raw( $yt_video_raw ) )' ), 'YouTube oEmbed rendering remains intact' );
expect_property_video( false !== strpos( $template, 'if ( ! empty( $yt_embed_html ) )' ), 'YouTube section retains its display condition' );

echo "Single-property video tests passed\n";
