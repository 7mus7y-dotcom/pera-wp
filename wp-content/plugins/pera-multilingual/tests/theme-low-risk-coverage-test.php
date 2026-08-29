<?php
/** Focused regressions for the first low-risk theme coverage fixes. */

function expect_theme_coverage( $condition, $label ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL {$label}\n" );
		exit( 1 );
	}
}

$theme = dirname( dirname( dirname( __DIR__ ) ) ) . '/themes/hello-elementor-child';

$citizenship = file_get_contents( $theme . '/page-citizenship-properties.php' );
expect_theme_coverage( false !== strpos( $citizenship, "pera_ml_ui( 'Price ↑', 'theme.template.page_citizenship_properties.price_ascending' )" ), 'ascending price has its own semantic identity' );
expect_theme_coverage( false !== strpos( $citizenship, "pera_ml_ui( 'Price ↓', 'theme.template.page_citizenship_properties.price_descending' )" ), 'descending price has its own semantic identity' );
expect_theme_coverage( false === strpos( $citizenship, "'theme.template.page_citizenship_properties.price'" ), 'one semantic identity cannot represent both price labels' );

$auth_expectations = array(
	'page-client-login.php'           => array( 'Sign in to access your reserved project documents and reports.', 'theme.template.page_client_login.sign_in_intro' ),
	'page-client-forgot-password.php' => array( 'Reset your password', 'theme.template.page_client_forgot_password.heading' ),
	'page-client-portal.php'          => array( 'My Account', 'theme.template.page_client_portal.my_account' ),
	'page-register.php'               => array( 'Create your account', 'theme.template.page_register.create_your_account' ),
);
foreach ( $auth_expectations as $file => $expected ) {
	$source = file_get_contents( $theme . '/' . $file );
	expect_theme_coverage( false !== strpos( $source, "pera_ml_ui( '" . $expected[0] . "', '" . $expected[1] . "' )" ), "{$file} visitor copy is statically discoverable" );
}

$term_expectations = array(
	'home-page.php'                 => 'pera_ml_term( $term ) : $term->name',
	'archive.php'                   => 'pera_ml_term( $cat, \'description\' ) : $cat->description',
	'parts/post-card.php'           => 'pera_ml_term( $primary_cat ) : $primary_cat->name',
	'single-post.php'               => 'pera_ml_term( $tag_term ) : $tag_term->name',
	'parts/home-featured-property.php' => 'pera_ml_term( $region ) : $region->name',
	'single-property.php'           => 'pera_ml_term( $bed_terms[0] ) : $bed_terms[0]->name',
	'inc/latest-offers-card.php'    => 'pera_ml_term( $term ) : $term->name',
	'inc/ajax-property-archive.php' => 'pera_ml_term( $t ) : $t->name',
	'inc/theme-helpers.php'         => 'pera_ml_term( $district_term ) : $district_term->name',
);
foreach ( $term_expectations as $file => $expected ) {
	$source = file_get_contents( $theme . '/' . $file );
	expect_theme_coverage( false !== strpos( $source, "function_exists( 'pera_ml_term' )" ), "{$file} checks helper availability" );
	expect_theme_coverage( false !== strpos( $source, $expected ), "{$file} translates display terms with canonical fallback" );
}

echo "Pera ML low-risk theme coverage tests passed\n";
