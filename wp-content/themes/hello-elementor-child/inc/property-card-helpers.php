<?php
/**
 * Property card helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_render_property_card' ) ) {
	/**
	 * Render a property card with provided args.
	 *
	 * @param array $args Card arguments.
	 */
	function pera_render_property_card( array $args = array() ): void {
		$prev = get_query_var( 'pera_property_card_args', null );

		set_query_var( 'pera_property_card_args', $args );

		get_template_part( 'parts/property-card-v2' );

		set_query_var( 'pera_property_card_args', $prev );
	}
}
