<?php
if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

/** Focused regression coverage for the single-property video sections. */

function expect_property_video( $condition, $label ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL {$label}\n" );
		exit( 1 );
	}
}

$template = file_get_contents( dirname( __DIR__ ) . '/single-property.php' );

expect_property_video( false === strpos( $template, 'DISABLE_APARTMENT_TOUR_VIDEO' ), 'obsolete hard-coded disable flag is absent' );
expect_property_video( false === strpos( $template, 'custom_video_checkbox' ), 'removed checkbox field is not referenced' );
expect_property_video( false !== strpos( $template, '<?php if ( $custom_video_url ) : ?>' ), 'a valid uploaded video URL alone enables the MP4 section' );
expect_property_video( false !== strpos( $template, "0 === strpos( \$validated_video_mime, 'video/' )" ), 'attachment must have a video MIME type' );
expect_property_video( substr_count( $template, "\$custom_video_url = '';" ) >= 2, 'empty and invalid attachments clear the MP4 URL' );
expect_property_video( false !== strpos( $template, 'wp_get_attachment_url( $custom_video_attachment_id )' ), 'video URL is validated against its attachment' );
expect_property_video( false !== strpos( $template, 'wp_http_validate_url( $validated_video_url )' ), 'attachment URL must be valid before rendering' );
expect_property_video( false !== strpos( $template, 'wp_get_attachment_mime_type( $custom_video_attachment_id )' ), 'source uses the validated attachment MIME type' );
expect_property_video( false !== strpos( $template, 'esc_attr( $custom_video_mime_type )' ), 'source MIME type is escaped' );

expect_property_video( false !== strpos( $template, "get_field( 'custom_video_heading', \$property_id )" ), 'custom MP4 heading remains supported' );
expect_property_video( false !== strpos( $template, "get_field( 'custom_video_text', \$property_id )" ), 'custom MP4 text remains supported' );
expect_property_video( false !== strpos( $template, "get_field( 'yt_video', \$property_id )" ), 'YouTube field remains supported' );
expect_property_video( false !== strpos( $template, 'wp_oembed_get( esc_url_raw( $yt_video_raw ) )' ), 'YouTube oEmbed rendering remains intact' );
expect_property_video( false !== strpos( $template, 'if ( ! empty( $yt_embed_html ) )' ), 'YouTube section retains its display condition' );

echo "Single-property video tests passed\n";
