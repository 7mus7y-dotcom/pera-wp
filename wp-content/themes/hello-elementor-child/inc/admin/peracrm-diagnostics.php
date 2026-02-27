<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_crm_diag_json_error' ) ) {
	function pera_crm_diag_json_error( string $code, string $message, int $status = 400, array $context = array() ): void {
		wp_send_json_error(
			array(
				'ok'      => false,
				'code'    => sanitize_key( $code ),
				'message' => $message,
				'context' => $context,
			),
			$status
		);
		exit;
	}
}

if ( ! function_exists( 'pera_crm_register_diagnostics_tools_page' ) ) {
	function pera_crm_register_diagnostics_tools_page(): void {
		add_management_page(
			__( 'PeraCRM Diagnostics', 'hello-elementor-child' ),
			__( 'PeraCRM Diagnostics', 'hello-elementor-child' ),
			'manage_options',
			'peracrm_diagnostics',
			'pera_crm_render_diagnostics_tools_page'
		);
	}
}
add_action( 'admin_menu', 'pera_crm_register_diagnostics_tools_page' );

if ( ! function_exists( 'pera_crm_render_diagnostics_tools_page' ) ) {
	function pera_crm_render_diagnostics_tools_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'hello-elementor-child' ) );
		}

		$nonce = wp_create_nonce( 'peracrm_diagnostics_checks' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'PeraCRM Diagnostics', 'hello-elementor-child' ); ?></h1>
			<p><?php esc_html_e( 'Runs lightweight smoke checks for CRM bootstrap gating and AJAX access behavior.', 'hello-elementor-child' ); ?></p>
			<p><button class="button button-primary" id="peracrm-run-diagnostics"><?php esc_html_e( 'Run diagnostics', 'hello-elementor-child' ); ?></button></p>
			<pre id="peracrm-diagnostics-output" style="background:#fff;padding:12px;border:1px solid #ccd0d4;max-width:100%;overflow:auto;"></pre>
		</div>
		<script>
		(function(){
			var btn = document.getElementById('peracrm-run-diagnostics');
			var out = document.getElementById('peracrm-diagnostics-output');
			if (!btn || !out) return;
			btn.addEventListener('click', function(){
				out.textContent = 'Running...';
				fetch(ajaxurl, {
					method: 'POST',
					headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
					credentials: 'same-origin',
					body: new URLSearchParams({action:'peracrm_run_diagnostics', nonce:'<?php echo esc_js( $nonce ); ?>'})
				}).then(function(r){return r.json();}).then(function(data){
					out.textContent = JSON.stringify(data, null, 2);
				}).catch(function(err){
					out.textContent = 'Diagnostics request failed: ' + err;
				});
			});
		})();
		</script>
		<?php
	}
}

if ( ! function_exists( 'pera_crm_run_diagnostics_ajax' ) ) {
	function pera_crm_run_diagnostics_ajax(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			pera_crm_diag_json_error( 'forbidden', __( 'Forbidden', 'hello-elementor-child' ), 403 );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'peracrm_diagnostics_checks' ) ) {
			pera_crm_diag_json_error( 'invalid_nonce', __( 'Invalid nonce', 'hello-elementor-child' ), 403 );
		}

		$can_manage_crm = function_exists( 'pera_crm_client_view_can_manage' ) ? pera_crm_client_view_can_manage() : false;
		$results        = array(
			'routing_gate' => array(
				'non_crm_route_loads_crm' => pera_crm_should_load_integration(
					array(
						'is_wp_admin'          => false,
						'is_crm_route'         => false,
						'is_crm_ajax'          => false,
						'is_crm_capable_user'  => false,
						'is_allowed_admin_crm' => false,
					)
				),
				'capable_user_non_crm_route_loads_crm' => pera_crm_should_load_integration(
					array(
						'is_wp_admin'          => false,
						'is_crm_route'         => false,
						'is_crm_ajax'          => false,
						'is_crm_capable_user'  => true,
						'is_allowed_admin_crm' => false,
					)
				),
				'crm_route_loads_crm' => pera_crm_should_load_integration(
					array(
						'is_wp_admin'          => false,
						'is_crm_route'         => true,
						'is_crm_ajax'          => false,
						'is_crm_capable_user'  => false,
						'is_allowed_admin_crm' => false,
					)
				),
				'router_hooks_registered_this_request' => function_exists( 'pera_crm_router_hooks_registered' ) ? pera_crm_router_hooks_registered() : false,
			),
			'ajax_checks'  => array(
				'unauthenticated_expected' => 'Call /wp-admin/admin-ajax.php?action=pera_crm_property_search without auth cookie => expect 403 JSON error.',
				'authorized_user_can_manage' => $can_manage_crm,
			),
		);

		wp_send_json_success(
			array(
				'ok'      => true,
				'code'    => 'diagnostics_complete',
				'message' => __( 'Diagnostics complete.', 'hello-elementor-child' ),
				'context' => $results,
			)
		);
		exit;
	}
}
add_action( 'wp_ajax_peracrm_run_diagnostics', 'pera_crm_run_diagnostics_ajax' );
