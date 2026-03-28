<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_admin_rationalize_dashboard_widgets' ) ) {
	/**
	 * Remove low-value default dashboard widgets while preserving custom/plugin widgets.
	 */
	function pera_admin_rationalize_dashboard_widgets(): void {
		remove_action( 'welcome_panel', 'wp_welcome_panel' );

		$core_widgets = array(
			'dashboard_right_now',
			'dashboard_activity',
			'dashboard_quick_press',
			'dashboard_primary',
			// Intentionally hidden: keep dashboard focused on daily operations shortcuts and workflow widgets.
			'dashboard_site_health',
			'dashboard_recent_comments',
			'dashboard_incoming_links',
			'dashboard_plugins',
			'dashboard_secondary',
		);

		foreach ( $core_widgets as $widget_id ) {
			remove_meta_box( $widget_id, 'dashboard', 'normal' );
			remove_meta_box( $widget_id, 'dashboard', 'side' );
		}

		wp_add_dashboard_widget(
			'pera_admin_shortcuts',
			__( 'Admin Shortcuts', 'hello-elementor-child' ),
			'pera_admin_render_shortcuts_dashboard_widget'
		);
	}
}
add_action( 'wp_dashboard_setup', 'pera_admin_rationalize_dashboard_widgets', 99 );

if ( ! function_exists( 'pera_admin_render_shortcuts_dashboard_widget' ) ) {
	/**
	 * Render lightweight grouped shortcuts to common admin workflows.
	 */
	function pera_admin_render_shortcuts_dashboard_widget(): void {
		$groups = array(
			__( 'CRM', 'hello-elementor-child' ) => array(
				__( 'Clients', 'hello-elementor-child' )              => admin_url( 'edit.php?post_type=crm_client' ),
				__( 'My Reminders', 'hello-elementor-child' )         => admin_url( 'admin.php?page=peracrm-my-reminders' ),
				__( 'Work Queue', 'hello-elementor-child' )           => admin_url( 'admin.php?page=peracrm-work-queue' ),
				__( 'Pipeline', 'hello-elementor-child' )             => admin_url( 'admin.php?page=peracrm-pipeline' ),
				__( 'Client View', 'hello-elementor-child' )          => admin_url( 'admin.php?page=peracrm-client-view' ),
				__( 'WhatsApp Integration', 'hello-elementor-child' ) => admin_url( 'admin.php?page=peracrm-whatsapp' ),
			),
			__( 'Properties', 'hello-elementor-child' ) => array(
				__( 'All Properties', 'hello-elementor-child' ) => admin_url( 'edit.php?post_type=property' ),
				__( 'Add Property', 'hello-elementor-child' )   => admin_url( 'post-new.php?post_type=property' ),
			),
			__( 'Portal', 'hello-elementor-child' ) => array(
				__( 'Portal Viewer', 'hello-elementor-child' ) => admin_url( 'admin.php?page=pera-portal' ),
				__( 'Units Manager', 'hello-elementor-child' ) => admin_url( 'admin.php?page=pera-portal-units-manager' ),
				__( 'Diagnostics', 'hello-elementor-child' )   => admin_url( 'admin.php?page=pera-portal-diagnostics' ),
			),
			__( 'Operations', 'hello-elementor-child' ) => array(
				__( 'WhatsApp Logs', 'hello-elementor-child' ) => admin_url( 'admin.php?page=pera-whatsapp-logs' ),
				__( 'Email Logs', 'hello-elementor-child' )    => admin_url( 'admin.php?page=pera-enquiry-email-log' ),
			),
			__( 'System', 'hello-elementor-child' ) => array(
				__( 'Users', 'hello-elementor-child' ) => admin_url( 'users.php' ),
			),
		);

		echo '<div class="pera-admin-shortcuts">';

		foreach ( $groups as $group_label => $links ) {
			echo '<section class="pera-admin-shortcuts__group">';
			echo '<h3>' . esc_html( $group_label ) . '</h3>';
			echo '<ul>';

			foreach ( $links as $link_label => $link_url ) {
				echo '<li><a href="' . esc_url( $link_url ) . '">' . esc_html( $link_label ) . '</a></li>';
			}

			echo '</ul>';
			echo '</section>';
		}

		echo '</div>';
	}
}

if ( ! function_exists( 'pera_admin_dashboard_shortcuts_styles' ) ) {
	/**
	 * Small styling pass for widget readability.
	 */
	function pera_admin_dashboard_shortcuts_styles(): void {
		echo '<style>
		#pera_admin_shortcuts .inside{margin-top:12px}
		.pera-admin-shortcuts{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px}
		.pera-admin-shortcuts__group h3{margin:0 0 8px;font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#50575e}
		.pera-admin-shortcuts__group ul{margin:0;padding:0;list-style:none}
		.pera-admin-shortcuts__group li{margin:0 0 6px}
		.pera-admin-shortcuts__group li:last-child{margin-bottom:0}
		</style>';
	}
}
add_action( 'admin_head-index.php', 'pera_admin_dashboard_shortcuts_styles' );
