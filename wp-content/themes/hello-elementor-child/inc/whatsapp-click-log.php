<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'PERA_WHATSAPP_CLICKS_DB_VERSION' ) ) {
	define( 'PERA_WHATSAPP_CLICKS_DB_VERSION', '1.0.0' );
}

if ( ! function_exists( 'pera_whatsapp_clicks_table_name' ) ) {
	function pera_whatsapp_clicks_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'pera_whatsapp_clicks';
	}
}

if ( ! function_exists( 'pera_whatsapp_clicks_install' ) ) {
	function pera_whatsapp_clicks_install() {
		global $wpdb;

		$table_name      = pera_whatsapp_clicks_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			page_type varchar(100) NOT NULL DEFAULT '',
			page_url text NULL,
			post_id bigint(20) unsigned NULL,
			post_title text NULL,
			message_text text NULL,
			referrer text NULL,
			user_agent text NULL,
			ip_address varchar(100) NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY page_type (page_type),
			KEY post_id (post_id)
		) {$charset_collate};";

		dbDelta( $sql );

		$installed_table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		if ( $installed_table === $table_name ) {
			update_option( 'pera_whatsapp_clicks_db_version', PERA_WHATSAPP_CLICKS_DB_VERSION, false );
		}
	}
}

add_action( 'after_switch_theme', 'pera_whatsapp_clicks_install' );

add_action(
	'init',
	function () {
		if ( get_option( 'pera_whatsapp_clicks_db_version' ) !== PERA_WHATSAPP_CLICKS_DB_VERSION ) {
			pera_whatsapp_clicks_install();
		}
	},
	5
);

if ( ! function_exists( 'pera_whatsapp_log_allowed_page_types' ) ) {
	function pera_whatsapp_log_allowed_page_types(): array {
		return array(
			'generic',
			'single-property',
			'citizenship-by-investment',
			'sell-with-pera',
			'rent-with-pera',
		);
	}
}

if ( ! function_exists( 'pera_whatsapp_normalize_payload' ) ) {
	/**
	 * Normalize and tighten AJAX payload before insert.
	 */
	function pera_whatsapp_normalize_payload( array $payload ): array {
		$allowed_page_types = pera_whatsapp_log_allowed_page_types();
		$page_type          = sanitize_key( (string) ( $payload['page_type'] ?? '' ) );

		if ( '' === $page_type || ! in_array( $page_type, $allowed_page_types, true ) ) {
			$page_type = 'generic';
		}

		$post_id = isset( $payload['post_id'] ) ? absint( $payload['post_id'] ) : 0;
		if ( $post_id <= 0 ) {
			$post_id = 0;
		}

		$post_title = sanitize_text_field( (string) ( $payload['post_title'] ?? '' ) );
		$page_url   = esc_url_raw( (string) ( $payload['page_url'] ?? '' ) );

		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( $post instanceof WP_Post ) {
				$post_title = sanitize_text_field( get_the_title( $post_id ) );
				if ( '' === $page_url ) {
					$post_permalink = get_permalink( $post_id );
					$page_url       = is_string( $post_permalink ) ? esc_url_raw( $post_permalink ) : '';
				}
			}
		}

		if ( '' === $page_url && function_exists( 'pera_get_current_request_url' ) ) {
			$page_url = esc_url_raw( pera_get_current_request_url() );
		}

		$referrer = esc_url_raw( (string) ( $payload['referrer'] ?? '' ) );
		if ( '' === $referrer && isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referrer = esc_url_raw( wp_unslash( (string) $_SERVER['HTTP_REFERER'] ) );
		}

		$ip_address = sanitize_text_field( (string) ( $payload['ip_address'] ?? '' ) );
		if ( '' === $ip_address && isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip_address = sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) );
		}

		return array(
			'page_type'    => $page_type,
			'page_url'     => $page_url,
			'post_id'      => $post_id,
			'post_title'   => mb_substr( $post_title, 0, 500 ),
			'message_text' => mb_substr( sanitize_textarea_field( (string) ( $payload['message_text'] ?? '' ) ), 0, 2000 ),
			'referrer'     => mb_substr( $referrer, 0, 1000 ),
			'user_agent'   => mb_substr( sanitize_text_field( (string) ( $payload['user_agent'] ?? '' ) ), 0, 500 ),
			'ip_address'   => mb_substr( $ip_address, 0, 100 ),
		);
	}
}

if ( ! function_exists( 'pera_whatsapp_clicks_insert' ) ) {
	function pera_whatsapp_clicks_insert( array $payload ): bool {
		global $wpdb;

		$normalized = pera_whatsapp_normalize_payload( $payload );
		$table_name = pera_whatsapp_clicks_table_name();

		$data = array(
			'page_type'    => $normalized['page_type'],
			'page_url'     => $normalized['page_url'],
			'post_title'   => $normalized['post_title'],
			'message_text' => $normalized['message_text'],
			'referrer'     => $normalized['referrer'],
			'user_agent'   => $normalized['user_agent'],
			'ip_address'   => $normalized['ip_address'],
		);

		$data['post_id'] = (int) $normalized['post_id'];

		$formats  = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' );
		$inserted = $wpdb->insert( $table_name, $data, $formats );

		return false !== $inserted;
	}
}

if ( ! function_exists( 'pera_log_whatsapp_click_ajax' ) ) {
	function pera_log_whatsapp_click_ajax() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'pera_whatsapp_click' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid security token.', 'hello-elementor-child' ),
				),
				403
			);
		}

		$payload = array(
			'page_type'    => isset( $_POST['page_type'] ) ? wp_unslash( (string) $_POST['page_type'] ) : '',
			'page_url'     => isset( $_POST['page_url'] ) ? wp_unslash( (string) $_POST['page_url'] ) : '',
			'post_id'      => isset( $_POST['post_id'] ) ? wp_unslash( (string) $_POST['post_id'] ) : '',
			'post_title'   => isset( $_POST['post_title'] ) ? wp_unslash( (string) $_POST['post_title'] ) : '',
			'message_text' => isset( $_POST['message_text'] ) ? wp_unslash( (string) $_POST['message_text'] ) : '',
			'referrer'     => isset( $_POST['referrer'] ) ? wp_unslash( (string) $_POST['referrer'] ) : '',
			'user_agent'   => isset( $_POST['user_agent'] ) ? wp_unslash( (string) $_POST['user_agent'] ) : '',
			'ip_address'   => isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) : '',
		);

		if ( ! pera_whatsapp_clicks_insert( $payload ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Could not save click log.', 'hello-elementor-child' ),
				),
				500
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Click logged.', 'hello-elementor-child' ),
			)
		);
	}
}

add_action( 'wp_ajax_pera_log_whatsapp_click', 'pera_log_whatsapp_click_ajax' );
add_action( 'wp_ajax_nopriv_pera_log_whatsapp_click', 'pera_log_whatsapp_click_ajax' );

if ( ! function_exists( 'pera_whatsapp_logs_admin_menu' ) ) {
	function pera_whatsapp_logs_admin_menu() {
		add_menu_page(
			__( 'WhatsApp Click Logs', 'hello-elementor-child' ),
			__( 'WhatsApp Logs', 'hello-elementor-child' ),
			'manage_options',
			'pera-whatsapp-logs',
			'pera_whatsapp_logs_render_admin_page',
			'dashicons-format-chat',
			59
		);
	}
}
add_action( 'admin_menu', 'pera_whatsapp_logs_admin_menu' );

if ( ! function_exists( 'pera_whatsapp_logs_render_admin_page' ) ) {
	function pera_whatsapp_logs_render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$table_name = pera_whatsapp_clicks_table_name();
		$exists     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		if ( $exists !== $table_name ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'WhatsApp Click Logs', 'hello-elementor-child' ) . '</h1><p>' . esc_html__( 'Log table is not available yet.', 'hello-elementor-child' ) . '</p></div>';
			return;
		}

		$page_type = isset( $_GET['page_type'] ) ? sanitize_key( wp_unslash( (string) $_GET['page_type'] ) ) : '';
		$search    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ) : '';
		$paged     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page  = 20;
		$offset    = ( $paged - 1 ) * $per_page;

		$where_sql = ' WHERE 1=1 ';
		$args      = array();

		if ( '' !== $page_type ) {
			$where_sql .= ' AND page_type = %s ';
			$args[]     = $page_type;
		}

		if ( '' !== $search ) {
			$like       = '%' . $wpdb->esc_like( $search ) . '%';
			$where_sql .= ' AND (post_title LIKE %s OR page_url LIKE %s OR CAST(post_id AS CHAR) LIKE %s) ';
			$args[]     = $like;
			$args[]     = $like;
			$args[]     = $like;
		}

		$count_sql = "SELECT COUNT(*) FROM {$table_name} {$where_sql}";
		$total     = empty( $args )
			? (int) $wpdb->get_var( $count_sql )
			: (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) );

		$query_sql  = "SELECT * FROM {$table_name} {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
		$query_args = array_merge( $args, array( $per_page, $offset ) );
		$rows       = $wpdb->get_results( $wpdb->prepare( $query_sql, $query_args ) );

		$page_types  = $wpdb->get_col( "SELECT DISTINCT page_type FROM {$table_name} ORDER BY page_type ASC" );
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WhatsApp Click Logs', 'hello-elementor-child' ); ?></h1>

			<form method="get" class="tablenav top" style="margin:12px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
				<input type="hidden" name="page" value="pera-whatsapp-logs" />
				<label for="pera-whatsapp-page-type" class="screen-reader-text"><?php esc_html_e( 'Filter by page type', 'hello-elementor-child' ); ?></label>
				<select name="page_type" id="pera-whatsapp-page-type">
					<option value=""><?php esc_html_e( 'All page types', 'hello-elementor-child' ); ?></option>
					<?php foreach ( $page_types as $type ) : ?>
						<option value="<?php echo esc_attr( (string) $type ); ?>" <?php selected( $page_type, $type ); ?>><?php echo esc_html( (string) $type ); ?></option>
					<?php endforeach; ?>
				</select>
				<label for="pera-whatsapp-search" class="screen-reader-text"><?php esc_html_e( 'Search', 'hello-elementor-child' ); ?></label>
				<input type="search" id="pera-whatsapp-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search title, URL or post ID', 'hello-elementor-child' ); ?>" />
				<button class="button"><?php esc_html_e( 'Filter', 'hello-elementor-child' ); ?></button>
			</form>

			<?php if ( empty( $rows ) ) : ?>
				<p><?php esc_html_e( 'No WhatsApp click logs found.', 'hello-elementor-child' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped table-view-list pera-whatsapp-logs-table">
					<thead>
						<tr>
							<th style="width:140px;"><?php esc_html_e( 'Date', 'hello-elementor-child' ); ?></th>
							<th style="width:130px;"><?php esc_html_e( 'Page Type', 'hello-elementor-child' ); ?></th>
							<th><?php esc_html_e( 'Property / Title', 'hello-elementor-child' ); ?></th>
							<th style="width:80px;"><?php esc_html_e( 'Post ID', 'hello-elementor-child' ); ?></th>
							<th><?php esc_html_e( 'Page URL', 'hello-elementor-child' ); ?></th>
							<th><?php esc_html_e( 'Message', 'hello-elementor-child' ); ?></th>
							<th><?php esc_html_e( 'Referrer', 'hello-elementor-child' ); ?></th>
							<th style="width:120px;"><?php esc_html_e( 'IP', 'hello-elementor-child' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td><?php echo esc_html( mysql2date( 'Y-m-d H:i:s', (string) $row->created_at ) ); ?></td>
								<td><?php echo esc_html( (string) $row->page_type ); ?></td>
								<td style="word-break:break-word;"><?php echo esc_html( (string) $row->post_title ); ?></td>
								<td><?php echo esc_html( (string) $row->post_id ); ?></td>
								<td style="word-break:break-word;">
									<?php if ( ! empty( $row->page_url ) ) : ?>
										<a href="<?php echo esc_url( (string) $row->page_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) $row->page_url ); ?></a>
									<?php endif; ?>
								</td>
								<td style="word-break:break-word;"><?php echo esc_html( (string) $row->message_text ); ?></td>
								<td style="word-break:break-word;"><?php echo esc_html( (string) $row->referrer ); ?></td>
								<td><?php echo esc_html( (string) $row->ip_address ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php
			echo '<div class="tablenav"><div class="tablenav-pages">';
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'current'   => $paged,
						'total'     => $total_pages,
						'prev_text' => __( '&laquo;', 'hello-elementor-child' ),
						'next_text' => __( '&raquo;', 'hello-elementor-child' ),
					)
				)
			);
			echo '</div></div>';
			?>
		</div>
		<?php
	}
}
