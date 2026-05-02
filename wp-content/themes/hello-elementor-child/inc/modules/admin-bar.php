<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Whether the current user can access CRM.
 */
function pera_admin_bar_can_access_crm(): bool {
	if ( function_exists( 'peracrm_user_can_access_crm' ) ) {
		return (bool) peracrm_user_can_access_crm();
	}

	return current_user_can( 'manage_options' ) || current_user_can( 'edit_crm_clients' );
}

/**
 * Customize WordPress admin bar nodes by context.
 */
function pera_customize_admin_bar( WP_Admin_Bar $wp_admin_bar ): void {
	if ( ! is_user_logged_in() ) {
		return;
	}

	if ( is_admin() ) {
		$nodes_to_remove = array(
			'wp-logo',
			'site-name',
			'comments',
			'customize',
			'search',
			'updates',
			'my-sites',
			'new-content',
		);

		foreach ( $nodes_to_remove as $node_id ) {
			$wp_admin_bar->remove_node( $node_id );
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'pera-admin-menu',
				'title' => esc_html__( 'Menu', 'hello-elementor-child' ),
				'href'  => esc_url( admin_url() ),
			)
		);

		if ( pera_admin_bar_can_access_crm() ) {
			$wp_admin_bar->add_node(
				array(
					'id'    => 'pera-crm',
					'title' => esc_html__( 'CRM', 'hello-elementor-child' ),
					'href'  => esc_url( home_url( '/crm/' ) ),
				)
			);
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'pera-home',
				'title' => esc_html__( 'Home', 'hello-elementor-child' ),
				'href'  => esc_url( home_url( '/' ) ),
			)
		);

		return;
	}

	$wp_admin_bar->remove_node( 'wp-logo' );
	$wp_admin_bar->remove_node( 'site-name' );

	if ( current_user_can( 'read' ) ) {
		$wp_admin_bar->add_node(
			array(
				'id'    => 'pera-wp-admin',
				'title' => esc_html__( 'WP Admin', 'hello-elementor-child' ),
				'href'  => esc_url( admin_url() ),
			)
		);
	}
}

add_action( 'admin_bar_menu', 'pera_customize_admin_bar', 999 );
