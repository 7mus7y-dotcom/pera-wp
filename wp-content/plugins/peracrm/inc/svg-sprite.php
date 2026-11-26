<?php
/**
 * Provide the PeraCRM frontend with its own inline SVG sprite.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function peracrm_render_frontend_svg_sprite(): void {
	if ( ! function_exists( 'pera_is_crm_route' ) || ! pera_is_crm_route() ) {
		return;
	}
	$sprite_path = PERACRM_PATH . '/logos-icons/icons.svg';
	if ( ! is_readable( $sprite_path ) ) {
		return;
	}
	$sprite = file_get_contents( $sprite_path );
	if ( ! is_string( $sprite ) || '' === trim( $sprite ) ) {
		return;
	}
	$sprite = preg_replace( '/^\s*<\?xml[^>]*>\s*/i', '', $sprite );
	if ( ! is_string( $sprite ) ) {
		return;
	}
	echo '<div class="peracrm-svg-sprite" aria-hidden="true" hidden>' . $sprite . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted plugin-owned SVG sprite.
}
add_action( 'wp_footer', 'peracrm_render_frontend_svg_sprite', 1 );
