<?php

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 404 );
	exit;
}

function crm_mobile_view_panel_expect( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}

	echo "PASS: {$message}\n";
}

$root   = dirname( __DIR__ );
$header = file_get_contents( $root . '/inc/views/partials/crm-header.php' );
$css    = file_get_contents( $root . '/assets/frontend/crm.css' );

crm_mobile_view_panel_expect(
	false !== strpos( $header, 'pera-crm-viewing-as-banner__label-default' )
		&& false !== strpos( $header, 'pera-crm-viewing-as-banner__label-mobile' )
		&& false !== strpos( $header, "esc_html_e( 'Current:', 'peracrm' )" ),
	'default CRM view exposes a compact translated mobile label'
);

$mobile_start = strpos( $css, '@media (max-width: 767px)' );
crm_mobile_view_panel_expect( false !== $mobile_start, 'mobile CRM breakpoint remains present' );
$mobile_css = substr( $css, $mobile_start );

$scope = '.crm-page--overview .pera-crm-viewing-as-banner:not(.pera-crm-viewing-as-banner--active)';
crm_mobile_view_panel_expect(
	false !== strpos( $mobile_css, $scope ),
	'compact rules are scoped to the overview default state'
);
crm_mobile_view_panel_expect(
	false !== strpos( $mobile_css, $scope . ' .pera-crm-viewing-as-banner__meta' )
		&& false !== strpos( $mobile_css, 'flex-direction: row;' ),
	'default view heading and current identity share the mobile row'
);
crm_mobile_view_panel_expect(
	false !== strpos( $mobile_css, $scope . ' .peracrm-impersonation-switcher__select' )
		&& false !== strpos( $mobile_css, $scope . ' .peracrm-impersonation-switcher .btn' ),
	'view selector and Apply view button receive compact mobile overrides'
);
crm_mobile_view_panel_expect(
	false !== strpos( $css, 'background: linear-gradient(135deg, rgba(7, 16, 33, 0.96), rgba(10, 33, 74, 0.92));' ),
	'existing dark CRM view background remains unchanged'
);
crm_mobile_view_panel_expect(
	false === strpos( substr( $css, 0, $mobile_start ), $scope ),
	'overview compact overrides do not affect larger viewports'
);

echo "PeraCRM mobile default-view panel checks passed\n";
