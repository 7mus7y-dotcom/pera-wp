<?php
/** Focused regressions for translated values used by parallel SEO paths. */

function seo_paths_expect( $condition, $label ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL {$label}\n" );
		exit( 1 );
	}
}

$root = dirname( dirname( dirname( __DIR__ ) ) );
$theme_seo = file_get_contents( $root . '/themes/hello-elementor-child/inc/seo-all.php' );
$plugin_seo = file_get_contents( __DIR__ . '/../includes/class-seo.php' );
$fields = file_get_contents( __DIR__ . '/../includes/class-fields.php' );

seo_paths_expect( false !== strpos( $theme_seo, "get_field( \$field_name, \$post_id, false )" ), 'SEO reads the canonical unformatted ACF source' );
seo_paths_expect( false !== strpos( $theme_seo, "pera_ml_field( \$post_id, \$field_name, \$value )" ), 'theme SEO fields use the existing multilingual field contract' );
seo_paths_expect( false !== strpos( $plugin_seo, "pera_ml_field( \$id, 'seo_title', \$source )" ), 'late document-title filter uses the validated field reader' );
seo_paths_expect( false !== strpos( $plugin_seo, "empty( \$row['is_stale'] )" ), 'standalone document-title fallback rejects stale rows' );
seo_paths_expect( false !== strpos( $plugin_seo, "'' !== trim( (string) \$row['translated_text'] )" ), 'standalone document-title fallback rejects blank rows' );
seo_paths_expect( false === strpos( $fields, "'_wp_attachment_image_alt'" ), 'attachment alt was not added to post scalar contracts' );
seo_paths_expect( false === strpos( $fields, "'team'" ), 'structured team data was not added to post scalar contracts' );

echo "Pera ML SEO parallel-path tests passed\n";
