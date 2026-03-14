<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_client_autoreply_enabled' ) ) {
	/**
	 * Global client auto-reply toggle for enquiry forms.
	 */
	function pera_client_autoreply_enabled(): bool {
		$value = get_option( 'pera_enable_client_autoreply', '1' );

		return (string) $value === '1';
	}
}

if ( is_admin() ) {
	/**
	 * Register minimal theme settings for enquiry behaviour.
	 */
	function pera_theme_settings_register(): void {
		register_setting(
			'pera_theme_settings',
			'pera_enable_client_autoreply',
			array(
				'type'              => 'string',
				'sanitize_callback' => static function ( $value ): string {
					return ! empty( $value ) ? '1' : '0';
				},
				'default'           => '1',
			)
		);
	}
	add_action( 'admin_init', 'pera_theme_settings_register' );
}

if ( ! function_exists( 'pera_enquiry_email_log_table_name' ) ) {
	function pera_enquiry_email_log_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'pera_enquiry_email_log';
	}
}

if ( ! function_exists( 'pera_enquiry_email_last_failure_get' ) ) {
	function pera_enquiry_email_last_failure_get() {
		return isset( $GLOBALS['pera_enquiry_email_last_failure'] )
			? sanitize_text_field( (string) $GLOBALS['pera_enquiry_email_last_failure'] )
			: '';
	}
}

if ( ! function_exists( 'pera_enquiry_email_last_failure_set' ) ) {
	function pera_enquiry_email_last_failure_set( $message ) {
		$GLOBALS['pera_enquiry_email_last_failure'] = sanitize_text_field( (string) $message );
	}
}

if ( ! function_exists( 'pera_enquiry_email_last_failure_clear' ) ) {
	function pera_enquiry_email_last_failure_clear() {
		unset( $GLOBALS['pera_enquiry_email_last_failure'] );
	}
}

add_action(
	'wp_mail_failed',
	function ( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return;
		}

		pera_enquiry_email_last_failure_set( $error->get_error_message() );
	}
);

if ( ! function_exists( 'pera_enquiry_email_log_install' ) ) {
	function pera_enquiry_email_log_install() {
		global $wpdb;

		$table_name      = pera_enquiry_email_log_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			form_key varchar(80) NOT NULL DEFAULT '',
			mail_context varchar(80) NOT NULL DEFAULT '',
			recipient text NOT NULL,
			subject text NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'unknown',
			request_id varchar(80) NOT NULL DEFAULT '',
			meta longtext NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY form_key (form_key),
			KEY mail_context (mail_context),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql );

		$installed_table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		if ( $installed_table === $table_name ) {
			update_option( 'pera_enquiry_email_log_db_version', '1.0.0', false );
		}
	}
}

add_action( 'after_switch_theme', 'pera_enquiry_email_log_install' );

add_action(
	'init',
	function () {
		if ( get_option( 'pera_enquiry_email_log_db_version' ) !== '1.0.0' ) {
			pera_enquiry_email_log_install();
		}
	},
	5
);

if ( ! function_exists( 'pera_enquiry_email_log_event' ) ) {
	function pera_enquiry_email_log_event( array $event ) {
		global $wpdb;

		$table_name = pera_enquiry_email_log_table_name();

		$recipient = isset( $event['recipient'] ) ? $event['recipient'] : '';
		if ( is_array( $recipient ) ) {
			$recipient = implode( ',', array_map( 'sanitize_email', $recipient ) );
		}

		$status = isset( $event['status'] ) ? sanitize_key( (string) $event['status'] ) : 'unknown';

		$meta = array();
		if ( isset( $event['meta'] ) && is_array( $event['meta'] ) ) {
			foreach ( $event['meta'] as $meta_key => $meta_value ) {
				$safe_key = sanitize_key( (string) $meta_key );
				if ( is_scalar( $meta_value ) || null === $meta_value ) {
					$meta[ $safe_key ] = (string) $meta_value;
				}
			}
		}

		$wpdb->insert(
			$table_name,
			array(
				'form_key'     => sanitize_key( (string) ( $event['form_key'] ?? '' ) ),
				'mail_context' => sanitize_key( (string) ( $event['mail_context'] ?? '' ) ),
				'recipient'    => sanitize_text_field( (string) $recipient ),
				'subject'      => sanitize_text_field( (string) ( $event['subject'] ?? '' ) ),
				'status'       => $status,
				'request_id'   => function_exists( 'pera_forms_get_request_id' ) ? sanitize_key( (string) pera_forms_get_request_id() ) : '',
				'meta'         => ! empty( $meta ) ? wp_json_encode( $meta ) : null,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}

if ( ! function_exists( 'pera_enquiry_email_log_admin_menu' ) ) {
	function pera_enquiry_email_log_admin_menu() {
		add_menu_page(
			__( 'Enquiry Email Log', 'hello-elementor-child' ),
			__( 'Emails', 'hello-elementor-child' ),
			'manage_options',
			'pera-enquiry-email-log',
			'pera_enquiry_email_log_render_admin_page',
			'dashicons-email-alt',
			58
		);
	}
}
add_action( 'admin_menu', 'pera_enquiry_email_log_admin_menu' );

if ( ! function_exists( 'pera_enquiry_email_log_admin_assets' ) ) {
	function pera_enquiry_email_log_admin_assets( $hook_suffix ) {
		if ( 'toplevel_page_pera-enquiry-email-log' !== $hook_suffix ) {
			return;
		}

		$path = get_stylesheet_directory() . '/inc/admin/enquiry-email-log-admin.css';
		$url  = get_stylesheet_directory_uri() . '/inc/admin/enquiry-email-log-admin.css';

		if ( file_exists( $path ) ) {
			wp_enqueue_style(
				'pera-enquiry-email-log-admin',
				$url,
				array(),
				filemtime( $path )
			);
		}
	}
}
add_action( 'admin_enqueue_scripts', 'pera_enquiry_email_log_admin_assets' );

if ( ! function_exists( 'pera_enquiry_email_log_render_admin_page' ) ) {
	function pera_enquiry_email_log_render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$table_name = pera_enquiry_email_log_table_name();

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$table_name
			)
		);

		if ( $table_name !== $exists ) {
			?>
			<div class="wrap pera-enquiry-email-log-admin">
				<h1><?php esc_html_e( 'Enquiry Email Log', 'hello-elementor-child' ); ?></h1>
				<form action="options.php" method="post">
					<?php settings_fields( 'pera_theme_settings' ); ?>
					<p>
						<label for="pera_enable_client_autoreply">
							<input type="hidden" name="pera_enable_client_autoreply" value="0">
							<input type="checkbox" id="pera_enable_client_autoreply" name="pera_enable_client_autoreply" value="1" <?php checked( (string) get_option( 'pera_enable_client_autoreply', '1' ), '1' ); ?>>
							<?php esc_html_e( 'Enable client auto-reply emails', 'hello-elementor-child' ); ?>
						</label>
					</p>
					<p class="description"><?php esc_html_e( 'When enabled, enquiry forms send an automatic confirmation email to the client. Admin notification emails are not affected.', 'hello-elementor-child' ); ?></p>
					<?php submit_button( __( 'Save Settings', 'hello-elementor-child' ), 'secondary', 'submit', false ); ?>
				</form>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'Enquiry email log table has not been created yet.', 'hello-elementor-child' ); ?></p>
				</div>
			</div>
			<?php

			return;
		}

		$page     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page = 50;
		$offset   = ( $page - 1 ) * $per_page;

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, created_at, form_key, mail_context, recipient, subject, status, request_id, meta FROM {$table_name} ORDER BY id DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);

		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		?>
		<div class="wrap pera-enquiry-email-log-admin">
			<h1><?php esc_html_e( 'Enquiry Email Log', 'hello-elementor-child' ); ?></h1>
			<form action="options.php" method="post">
				<?php settings_fields( 'pera_theme_settings' ); ?>
				<p>
					<label for="pera_enable_client_autoreply">
						<input type="hidden" name="pera_enable_client_autoreply" value="0">
						<input type="checkbox" id="pera_enable_client_autoreply" name="pera_enable_client_autoreply" value="1" <?php checked( (string) get_option( 'pera_enable_client_autoreply', '1' ), '1' ); ?>>
						<?php esc_html_e( 'Enable client auto-reply emails', 'hello-elementor-child' ); ?>
					</label>
				</p>
				<p class="description"><?php esc_html_e( 'When enabled, enquiry forms send an automatic confirmation email to the client. Admin notification emails are not affected.', 'hello-elementor-child' ); ?></p>
				<?php submit_button( __( 'Save Settings', 'hello-elementor-child' ), 'secondary', 'submit', false ); ?>
			</form>
			<p><?php esc_html_e( 'Recent enquiry email attempts from theme handlers.', 'hello-elementor-child' ); ?></p>
			<table class="widefat striped pera-enquiry-email-log-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'hello-elementor-child' ); ?></th>
						<th><?php esc_html_e( 'Form', 'hello-elementor-child' ); ?></th>
						<th><?php esc_html_e( 'Context', 'hello-elementor-child' ); ?></th>
						<th><?php esc_html_e( 'Recipient', 'hello-elementor-child' ); ?></th>
						<th><?php esc_html_e( 'Subject', 'hello-elementor-child' ); ?></th>
						<th><?php esc_html_e( 'Status', 'hello-elementor-child' ); ?></th>
						<th><?php esc_html_e( 'Request ID', 'hello-elementor-child' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'No log entries found.', 'hello-elementor-child' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td data-label="<?php esc_attr_e( 'Time', 'hello-elementor-child' ); ?>"><?php echo esc_html( (string) $row['created_at'] ); ?></td>
								<td data-label="<?php esc_attr_e( 'Form', 'hello-elementor-child' ); ?>"><?php echo esc_html( (string) $row['form_key'] ); ?></td>
								<td data-label="<?php esc_attr_e( 'Context', 'hello-elementor-child' ); ?>"><?php echo esc_html( (string) $row['mail_context'] ); ?></td>
								<td data-label="<?php esc_attr_e( 'Recipient', 'hello-elementor-child' ); ?>"><?php echo esc_html( (string) $row['recipient'] ); ?></td>
								<td data-label="<?php esc_attr_e( 'Subject', 'hello-elementor-child' ); ?>"><?php echo esc_html( (string) $row['subject'] ); ?></td>
								<td data-label="<?php esc_attr_e( 'Status', 'hello-elementor-child' ); ?>"><?php echo esc_html( (string) $row['status'] ); ?></td>
								<td data-label="<?php esc_attr_e( 'Request ID', 'hello-elementor-child' ); ?>"><?php echo esc_html( (string) $row['request_id'] ); ?></td>
							</tr>
							<?php if ( ! empty( $row['meta'] ) ) : ?>
								<tr class="pera-enquiry-email-log-meta-row">
									<td colspan="7" data-label="<?php esc_attr_e( 'Meta', 'hello-elementor-child' ); ?>"><code><?php echo esc_html( (string) $row['meta'] ); ?></code></td>
								</tr>
							<?php endif; ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php
			echo '<div class="tablenav"><div class="tablenav-pages">';
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'current'   => $page,
						'total'     => $total_pages,
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
					)
				)
			);
			echo '</div></div>';
			?>
		</div>
		<?php
	}
}
