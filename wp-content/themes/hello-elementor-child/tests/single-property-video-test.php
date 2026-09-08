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
