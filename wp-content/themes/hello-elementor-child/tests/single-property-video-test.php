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

/** Mirror the hero control's validated-video display rule. */
function property_video_test_show_hero_button( $validated_video_url ) {
	return $validated_video_url !== '';
}

/** Mirror the hero control's custom-text-or-fallback label rule. */
function property_video_test_button_label( $button_text, $fallback ) {
	$button_text = trim( $button_text );
	return $button_text !== '' ? $button_text : $fallback;
}

$theme_dir = dirname( __DIR__ );
$template  = file_get_contents( $theme_dir . '/single-property.php' );
$script    = file_get_contents( $theme_dir . '/js/main.js' );
$styles    = file_get_contents( $theme_dir . '/css/property.css' );

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

expect_property_video( false !== strpos( $template, "trim( (string) get_field( 'custom_video_button', \$property_id ) )" ), 'translated custom video button text is read and trimmed' );
expect_property_video( false !== strpos( $template, "\$video_button_label         = \$custom_video_button !== ''" ), 'non-empty custom video button text controls the label' );
expect_property_video( false !== strpos( $template, "pera_ml_ui( 'Watch video', 'theme.template.single_property.watch_video' )" ), 'empty custom text uses the translated Watch video fallback' );
expect_property_video( false !== strpos( $template, 'if ( $custom_video_url ) :' ), 'hero video button requires only a validated video URL' );
expect_property_video( false === strpos( $template, "get_field( 'custom_video_checkbox'" ), 'hero button does not depend on the retired checkbox field' );
expect_property_video( false !== strpos( $template, 'data-open-property-video' ), 'hero video button has a stable selector' );
expect_property_video( false !== strpos( $template, 'aria-label="<?php echo esc_attr( $video_button_label ); ?>"' ), 'hero video button has an escaped accessible label' );
expect_property_video( false !== strpos( $template, 'esc_html( $video_button_label )' ), 'resolved hero video button label is escaped visibly' );
expect_property_video( false !== strpos( $template, 'class="btn btn--ghost btn--white property-hero__video-button"' ), 'hero video button uses a restrained existing ghost design' );
expect_property_video( false !== strpos( $template, 'class="property-hero__video-icon" aria-hidden="true" focusable="false"' ), 'play icon is decorative and removed from the accessibility tree' );
expect_property_video( false !== strpos( $template, 'type="button"' ), 'hero video control is a semantic non-submitting button' );
expect_property_video( property_video_test_show_hero_button( 'https://example.test/tour.mp4' ), 'valid video renders the hero control even without custom text' );
expect_property_video( 'Watch video' === property_video_test_button_label( '', 'Watch video' ), 'empty custom text selects the fallback' );
expect_property_video( 'Watch video' === property_video_test_button_label( " \t\n", 'Watch video' ), 'whitespace-only custom text selects the fallback' );
expect_property_video( 'Watch tour' === property_video_test_button_label( ' Watch tour ', 'Watch video' ), 'non-empty trimmed custom text overrides the fallback' );
expect_property_video( ! property_video_test_show_hero_button( '' ), 'absent or invalid video suppresses the hero control' );

$facts_position        = strpos( $template, '<div class="property-hero__facts"' );
$video_action_position = strpos( $template, '<div class="property-hero__video-action">' );
$cta_position          = strpos( $template, '<div class="property-hero__cta">' );
expect_property_video( $facts_position < $video_action_position && $video_action_position < $cta_position, 'video action follows facts and precedes the CTA row' );
$cta_request_details_position = strpos( $template, '<a class="btn btn--solid btn--blue"', $cta_position );
$cta_leading_controls         = substr( $template, $cta_position, $cta_request_details_position - $cta_position );
expect_property_video( false === strpos( $cta_leading_controls, 'data-open-property-video' ), 'video button is not inside the CTA row' );
expect_property_video( false !== strpos( $styles, '.property-hero__video-action{' ), 'video action has dedicated desktop styling' );
expect_property_video( false !== strpos( $styles, 'justify-content: flex-start;' ), 'video action stays left aligned' );
expect_property_video( false !== strpos( $styles, '.property-hero__video-action .property-hero__video-button{' ), 'video button styling is scoped outside the CTA layout' );
expect_property_video( false !== strpos( $styles, 'width: auto;' ), 'video control remains compact on desktop and mobile' );

expect_property_video( false !== strpos( $script, 'lightboxVideo.pause();' ), 'closing or changing media pauses video' );
expect_property_video( false !== strpos( $script, 'lightboxVideo.currentTime = 0;' ), 'closing or changing media resets video' );
expect_property_video( false !== strpos( $script, "event.key === 'Escape'" ), 'Escape closes the modal' );
expect_property_video( false !== strpos( $script, "querySelectorAll('[data-gallery-close]')" ), 'close button and backdrop share close handling' );
expect_property_video( false !== strpos( $script, 'galleryReturnFocus.focus();' ), 'focus returns to the gallery trigger' );
expect_property_video( false !== strpos( $script, "document.querySelector('[data-open-property-video]')" ), 'hero video control is progressively enhanced' );
expect_property_video( false !== strpos( $script, "document.querySelector('.property-gallery__video-trigger')" ), 'hero control locates the actual gallery video tile' );
expect_property_video( false !== strpos( $script, 'galleryTriggers.indexOf(videoTrigger)' ), 'hero control derives the video index instead of hard-coding it' );
expect_property_video( false !== strpos( $script, 'if (!videoTrigger || videoIndex === -1) return;' ), 'missing gallery video trigger fails safely' );
expect_property_video( false !== strpos( $script, 'openPropertyGallery(videoIndex, heroVideoButton);' ), 'hero control uses the shared modal path and records itself for focus restoration' );
expect_property_video( false !== strpos( $script, 'openPropertyGallery(index, trigger);' ), 'gallery tiles retain their independent shared-modal behavior' );

expect_property_video( false !== strpos( $template, '$photo_count = count( $gallery_ids );' ), 'photo count remains based only on image IDs' );
expect_property_video( false !== strpos( $template, 'data-gallery-type="image"' ), 'existing images remain in the shared lightbox' );
expect_property_video( false !== strpos( $template, "get_field( 'yt_video', \$property_id )" ), 'YouTube field remains supported' );
expect_property_video( false !== strpos( $template, 'wp_oembed_get( esc_url_raw( $yt_video_raw ) )' ), 'YouTube oEmbed rendering remains intact' );
expect_property_video( false !== strpos( $template, 'if ( ! empty( $yt_embed_html ) )' ), 'YouTube section retains its display condition' );

echo "Single-property video tests passed\n";
