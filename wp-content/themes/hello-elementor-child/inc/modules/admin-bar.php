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
			'crm',
			'peracrm',
			'peracrm-dashboard',
			'edit-crm-clients',
			'peracrm_clients',
		);

		foreach ( $nodes_to_remove as $node_id ) {
			$wp_admin_bar->remove_node( $node_id );
		}

		$wp_admin_bar->remove_node( 'peracrm_clients' );

		$icon_label = static function ( string $icon_class, string $label ): string {
			return sprintf(
				'<span class="ab-icon dashicons %1$s" aria-hidden="true"></span><span class="screen-reader-text">%2$s</span>',
			esc_attr( $icon_class ),
			esc_html( $label )
			);
		};

		if ( pera_admin_bar_can_access_crm() ) {
			$wp_admin_bar->add_node(
				array(
					'id'    => 'pera-crm',
					'title' => $icon_label( 'dashicons-groups', __( 'CRM', 'hello-elementor-child' ) ),
					'href'  => esc_url( home_url( '/crm/' ) ),
				)
			);
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'pera-home',
				'title' => $icon_label( 'dashicons-admin-home', __( 'Home', 'hello-elementor-child' ) ),
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


/**
 * Temporary admin bar node debug dump (HTML comments only).
 */
function pera_debug_admin_bar_nodes_dump(): void {
	if ( ! is_admin() ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! isset( $_GET['pera_admin_bar_debug'] ) || '1' !== (string) $_GET['pera_admin_bar_debug'] ) {
		return;
	}

	global $wp_admin_bar;

	if ( ! ( $wp_admin_bar instanceof WP_Admin_Bar ) ) {
		return;
	}

	$nodes = $wp_admin_bar->get_nodes();

	foreach ( $nodes as $node ) {
		$id     = isset( $node->id ) ? str_replace( '--', '—', (string) $node->id ) : '';
		$title  = isset( $node->title ) ? str_replace( '--', '—', wp_strip_all_tags( (string) $node->title ) ) : '';
		$href   = isset( $node->href ) ? str_replace( '--', '—', (string) $node->href ) : '';
		$parent = isset( $node->parent ) ? str_replace( '--', '—', (string) $node->parent ) : '';

		echo "
<!-- PERA ADMIN BAR NODE: id={$id} title={$title} href={$href} parent={$parent} -->
";
	}
}


/**
 * Keep custom admin bar icon nodes visible on narrow admin screens.
 */
function pera_admin_bar_mobile_visibility_css(): void {
	echo '<style id="pera-admin-bar-mobile-visibility">@media screen and (max-width: 600px) {#wpadminbar #wp-admin-bar-pera-admin-menu,#wpadminbar #wp-admin-bar-pera-crm,#wpadminbar #wp-admin-bar-pera-home {display: block !important;}#wpadminbar #wp-admin-bar-pera-admin-menu > .ab-item,#wpadminbar #wp-admin-bar-pera-crm > .ab-item,#wpadminbar #wp-admin-bar-pera-home > .ab-item {display: flex !important;align-items: center;justify-content: center;width: 40px;}}</style>';
}

if ( is_admin() ) {
	add_action( 'admin_bar_menu', 'pera_customize_admin_bar', 999 );
	add_action( 'wp_before_admin_bar_render', 'pera_debug_admin_bar_nodes_dump', 9999 );
	add_action( 'admin_head', 'pera_admin_bar_mobile_visibility_css' );
}
