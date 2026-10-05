<?php
if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

/** Focused static regression coverage for the Rent With Pera process slider. */
function expect_rent_process_slider( $condition, $label ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL {$label}\n" );
		exit( 1 );
	}
}

$theme_dir = dirname( __DIR__ );
$template  = file_get_contents( $theme_dir . '/page-rent-with-pera.php' );
$enqueue   = file_get_contents( $theme_dir . '/inc/modules/enqueue-assets.php' );
$styles    = file_get_contents( $theme_dir . '/css/slider.css' );

$process_start = strpos( $template, '<section class="section" id="rental-management-process">' );
$process_end   = strpos( $template, '</section>', $process_start );
expect_rent_process_slider( false !== $process_start && false !== $process_end, 'management-process section remains server-rendered' );

$before_process = substr( $template, 0, $process_start );
$process        = substr( $template, $process_start, $process_end - $process_start );
$after_process  = substr( $template, $process_end );

expect_rent_process_slider( 1 === substr_count( $template, 'class="cards-slider cards-slider--snap cards-slider--grid-2"' ), 'only one card collection receives the slider classes' );
expect_rent_process_slider( false === strpos( $before_process, 'cards-slider' ) && false === strpos( $after_process, 'cards-slider' ), 'slider classes are limited to the management-process section' );
expect_rent_process_slider( false !== strpos( $before_process, '<div class="feature-grid">' ), 'earlier service and pricing grid remains unchanged' );
expect_rent_process_slider( 6 === substr_count( $process, '<article class="feature-card slider-card">' ), 'all six process cards receive slider-card' );
expect_rent_process_slider( false !== strpos( $process, 'cards-slider--snap' ), 'process slider enables scroll snapping' );
expect_rent_process_slider( false !== strpos( $process, 'cards-slider--grid-2' ), 'process slider restores the two-column grid' );
expect_rent_process_slider( false !== strpos( $process, '<h2 id="rental-management-process-heading">' ), 'process heading exposes a stable label ID' );
expect_rent_process_slider( false !== strpos( $process, 'role="region" aria-labelledby="rental-management-process-heading" tabindex="0"' ), 'focusable slider region is labelled by its heading' );

for ( $step = 1; $step <= 6; $step++ ) {
	expect_rent_process_slider( false !== strpos( $process, "page_rent_with_pera_v2.process_{$step}_heading" ), "step {$step} translated heading remains server-rendered" );
	expect_rent_process_slider( false !== strpos( $process, "page_rent_with_pera_v2.process_{$step}_body" ), "step {$step} translated body remains server-rendered" );
}

expect_rent_process_slider( false !== strpos( $enqueue, "is_page_template( 'page-rent-with-pera.php' ) ||" ), 'slider stylesheet is requested for the Rent With Pera template' );
foreach ( array( '$is_home', '$is_single_property', '$is_single_bodrum_property', '$is_single_post', '$is_featured_guides_archive', '$is_contact_page', '$is_about_new', '$is_citizenship_page', '$is_citizenship_properties_page' ) as $existing_condition ) {
	expect_rent_process_slider( false !== strpos( $enqueue, $existing_condition ), "existing slider condition {$existing_condition} remains present" );
}
expect_rent_process_slider( false === strpos( $process, '<script' ), 'process slider introduces no JavaScript' );
expect_rent_process_slider( false === strpos( $process, 'carousel' ), 'process slider introduces no carousel dependency or semantics' );
expect_rent_process_slider( false !== strpos( $styles, '@media (max-width: 767px)' ), 'near-full-width process cards are limited to mobile' );
expect_rent_process_slider( false !== strpos( $styles, '#rental-management-process .slider-card' ), 'process card sizing is section-scoped' );
expect_rent_process_slider( false !== strpos( $styles, '@media (min-width: 768px)' ), 'shared grid breakpoint remains 768px' );
expect_rent_process_slider( false !== strpos( $styles, '.cards-slider--grid-2' ), 'shared two-column slider utility remains available' );

echo "Rent management process slider tests passed\n";
