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
	$sprite = preg_replace( '/<\?xml[^>]*\?>/i', '', $sprite );
	if ( ! is_string( $sprite ) ) {
		return;
	}

	if ( preg_match( '/<svg\b[^>]*>/i', $sprite, $match ) ) {
		$svg_tag = $match[0];
		$new_tag = $svg_tag;
		if ( stripos( $new_tag, 'style=' ) !== false ) {
			$new_tag = preg_replace(
				'/style=(["\'])(.*?)\1/i',
				'style=$1$2;position:absolute;width:0;height:0;overflow:hidden$1',
				$new_tag,
				1
			);
		} else {
			$new_tag = rtrim( substr( $new_tag, 0, -1 ) ) . ' style="position:absolute;width:0;height:0;overflow:hidden">';
		}
		if ( stripos( $new_tag, 'aria-hidden=' ) === false ) {
			$new_tag = rtrim( substr( $new_tag, 0, -1 ) ) . ' aria-hidden="true">';
		}
		if ( stripos( $new_tag, 'focusable=' ) === false ) {
			$new_tag = rtrim( substr( $new_tag, 0, -1 ) ) . ' focusable="false">';
		}
		$sprite = preg_replace( '/<svg\b[^>]*>/i', $new_tag, $sprite, 1 );
	}

	echo $sprite; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted plugin-owned SVG sprite.
}
add_action( 'wp_footer', 'peracrm_render_frontend_svg_sprite', 20 );
