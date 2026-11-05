<?php
if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

/** Focused regression coverage for the single-property owner CTA. */

function expect_owner_cta( $condition, $label ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL {$label}\n" );
		exit( 1 );
	}
}

$theme    = dirname( __DIR__ );
$template = file_get_contents( $theme . '/single-property.php' );

expect_owner_cta(
	false !== strpos( $template, '$owner_cta_project_name = trim( wp_strip_all_tags( $project_name ) );' ),
	'project name is normalized before output'
);
expect_owner_cta(
	false !== strpos( $template, "pera_ml_ui( 'Own a property in %s?', 'theme.template.single_property.owner_cta_project_heading' )" ),
	'project listings use the translatable project-aware heading'
);
expect_owner_cta(
	false !== strpos( $template, "pera_ml_ui( 'Own a property in Istanbul?', 'theme.template.single_property.owner_cta_fallback_heading' )" ),
	'listings without a project name retain a useful fallback heading'
);
expect_owner_cta(
	false !== strpos( $template, "pera_ml_ui( 'Sell or rent it with Pera Property.', 'theme.template.single_property.owner_cta_copy' )" ),
	'owner CTA uses the requested supporting copy'
);
expect_owner_cta(
	false !== strpos( $template, 'esc_html( $owner_cta_heading )' ),
	'dynamic owner heading is escaped'
);
expect_owner_cta(
	false !== strpos( $template, "home_url( '/sell-your-istanbul-real-estate/' )" )
		&& false !== strpos( $template, "home_url( '/rent-your-istanbul-real-estate/' )" ),
	'existing sell and rental destinations remain available'
);

echo "single-property owner CTA regression checks passed\n";
