<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_enquiry_email_log_table_name' ) ) {
	function pera_enquiry_email_log_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'pera_enquiry_email_log';
	}
}

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
		add_management_page(
			__( 'Enquiry Email Log', 'hello-elementor-child' ),
			__( 'Enquiry Email Log', 'hello-elementor-child' ),
			'manage_options',
			'pera-enquiry-email-log',
			'pera_enquiry_email_log_render_admin_page'
		);
	}
}
add_action( 'admin_menu', 'pera_enquiry_email_log_admin_menu' );

if ( ! function_exists( 'pera_enquiry_email_log_render_admin_page' ) ) {
	function pera_enquiry_email_log_render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$table_name = pera_enquiry_email_log_table_name();

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
		<div class="wrap">
			<h1><?php esc_html_e( 'Enquiry Email Log', 'hello-elementor-child' ); ?></h1>
			<p><?php esc_html_e( 'Recent enquiry email attempts from theme handlers.', 'hello-elementor-child' ); ?></p>
			<table class="widefat striped">
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
								<td><?php echo esc_html( (string) $row['created_at'] ); ?></td>
								<td><?php echo esc_html( (string) $row['form_key'] ); ?></td>
								<td><?php echo esc_html( (string) $row['mail_context'] ); ?></td>
								<td><?php echo esc_html( (string) $row['recipient'] ); ?></td>
								<td><?php echo esc_html( (string) $row['subject'] ); ?></td>
								<td><?php echo esc_html( (string) $row['status'] ); ?></td>
								<td><?php echo esc_html( (string) $row['request_id'] ); ?></td>
							</tr>
							<?php if ( ! empty( $row['meta'] ) ) : ?>
								<tr>
									<td colspan="7"><code><?php echo esc_html( (string) $row['meta'] ); ?></code></td>
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

