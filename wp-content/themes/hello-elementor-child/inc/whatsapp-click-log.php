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

if ( ! function_exists( 'pera_whatsapp_logs_page_type_label' ) ) {
	function pera_whatsapp_logs_page_type_label( string $page_type ): string {
		$labels = array(
			'single-property'           => __( 'Single Property', 'hello-elementor-child' ),
			'sell-with-pera'            => __( 'Sell With Pera', 'hello-elementor-child' ),
			'rent-with-pera'            => __( 'Rent With Pera', 'hello-elementor-child' ),
			'citizenship-by-investment' => __( 'Citizenship by Investment', 'hello-elementor-child' ),
			'generic'                   => __( 'Generic Page', 'hello-elementor-child' ),
		);

		if ( isset( $labels[ $page_type ] ) ) {
			return $labels[ $page_type ];
		}

		if ( '' === $page_type ) {
			return __( 'Unknown', 'hello-elementor-child' );
		}

		return ucwords( str_replace( '-', ' ', $page_type ) );
	}
}

if ( ! function_exists( 'pera_whatsapp_logs_truncate' ) ) {
	function pera_whatsapp_logs_truncate( string $text, int $length = 80 ): string {
		$text = trim( $text );

		if ( '' === $text ) {
			return '';
		}

		if ( mb_strlen( $text ) <= $length ) {
			return $text;
		}

		return rtrim( mb_substr( $text, 0, $length - 1 ) ) . '…';
	}
}

if ( ! function_exists( 'pera_whatsapp_logs_url_label' ) ) {
	function pera_whatsapp_logs_url_label( string $url, int $length = 56 ): string {
		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return pera_whatsapp_logs_truncate( $url, $length );
		}

		$label = $parts['host'];

		if ( isset( $parts['path'] ) && '' !== $parts['path'] ) {
			$label .= $parts['path'];
		}

		if ( isset( $parts['query'] ) && '' !== $parts['query'] ) {
			$label .= '?' . $parts['query'];
		}

		return pera_whatsapp_logs_truncate( $label, $length );
	}
}

if ( ! function_exists( 'pera_whatsapp_logs_stats' ) ) {
	function pera_whatsapp_logs_stats( string $table_name ): array {
		global $wpdb;

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );

		$counts_rows = $wpdb->get_results(
			"SELECT page_type, COUNT(*) AS count FROM {$table_name} GROUP BY page_type",
			ARRAY_A
		);

		$counts = array();
		foreach ( (array) $counts_rows as $row ) {
			$type            = sanitize_key( (string) ( $row['page_type'] ?? '' ) );
			$counts[ $type ] = (int) ( $row['count'] ?? 0 );
		}

		return array(
			'total'                     => $total,
			'single-property'           => $counts['single-property'] ?? 0,
			'sell-with-pera'            => $counts['sell-with-pera'] ?? 0,
			'rent-with-pera'            => $counts['rent-with-pera'] ?? 0,
			'citizenship-by-investment' => $counts['citizenship-by-investment'] ?? 0,
		);
	}
}

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
		$stats       = pera_whatsapp_logs_stats( $table_name );

		$base_query_args = array(
			'page' => 'pera-whatsapp-logs',
		);

		if ( '' !== $page_type ) {
			$base_query_args['page_type'] = $page_type;
		}

		if ( '' !== $search ) {
			$base_query_args['s'] = $search;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WhatsApp Click Logs', 'hello-elementor-child' ); ?></h1>

			<div class="pera-whatsapp-logs-summary">
				<div class="pera-whatsapp-logs-summary-card">
					<span class="pera-whatsapp-logs-summary-label"><?php esc_html_e( 'Total Logs', 'hello-elementor-child' ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( (int) $stats['total'] ) ); ?></strong>
				</div>
				<div class="pera-whatsapp-logs-summary-card">
					<span class="pera-whatsapp-logs-summary-label"><?php esc_html_e( 'Property Page Clicks', 'hello-elementor-child' ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( (int) $stats['single-property'] ) ); ?></strong>
				</div>
				<div class="pera-whatsapp-logs-summary-card">
					<span class="pera-whatsapp-logs-summary-label"><?php esc_html_e( 'Sell With Pera', 'hello-elementor-child' ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( (int) $stats['sell-with-pera'] ) ); ?></strong>
				</div>
				<div class="pera-whatsapp-logs-summary-card">
					<span class="pera-whatsapp-logs-summary-label"><?php esc_html_e( 'Rent With Pera', 'hello-elementor-child' ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( (int) $stats['rent-with-pera'] ) ); ?></strong>
				</div>
				<div class="pera-whatsapp-logs-summary-card">
					<span class="pera-whatsapp-logs-summary-label"><?php esc_html_e( 'Citizenship Clicks', 'hello-elementor-child' ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( (int) $stats['citizenship-by-investment'] ) ); ?></strong>
				</div>
			</div>

			<form method="get" class="tablenav top pera-whatsapp-logs-toolbar">
				<input type="hidden" name="page" value="pera-whatsapp-logs" />
				<label for="pera-whatsapp-page-type" class="screen-reader-text"><?php esc_html_e( 'Filter by page type', 'hello-elementor-child' ); ?></label>
				<select name="page_type" id="pera-whatsapp-page-type">
					<option value=""><?php esc_html_e( 'All page types', 'hello-elementor-child' ); ?></option>
					<?php foreach ( $page_types as $type ) : ?>
						<option value="<?php echo esc_attr( (string) $type ); ?>" <?php selected( $page_type, $type ); ?>><?php echo esc_html( pera_whatsapp_logs_page_type_label( (string) $type ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<label for="pera-whatsapp-search" class="screen-reader-text"><?php esc_html_e( 'Search', 'hello-elementor-child' ); ?></label>
				<input type="search" id="pera-whatsapp-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search title, URL or post ID', 'hello-elementor-child' ); ?>" />
				<button class="button button-primary"><?php esc_html_e( 'Apply Filters', 'hello-elementor-child' ); ?></button>
				<?php if ( '' !== $page_type || '' !== $search ) : ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=pera-whatsapp-logs' ) ); ?>"><?php esc_html_e( 'Clear', 'hello-elementor-child' ); ?></a>
				<?php endif; ?>
				<span class="pera-whatsapp-logs-result-count">
					<?php
					printf(
						esc_html__( 'Showing %1$s of %2$s logs', 'hello-elementor-child' ),
						esc_html( number_format_i18n( count( $rows ) ) ),
						esc_html( number_format_i18n( $total ) )
					);
					?>
				</span>
			</form>

			<?php if ( empty( $rows ) ) : ?>
				<div class="notice notice-info inline pera-whatsapp-logs-empty-state">
					<p>
						<?php
						echo ( '' !== $page_type || '' !== $search )
							? esc_html__( 'No logs match your current filters. Try clearing filters or broadening your search.', 'hello-elementor-child' )
							: esc_html__( 'No WhatsApp click logs found yet. Logged clicks will appear here for daily review.', 'hello-elementor-child' );
						?>
					</p>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped table-view-list pera-whatsapp-logs-table">
					<thead>
						<tr>
							<th style="width:140px;"><?php esc_html_e( 'Date', 'hello-elementor-child' ); ?></th>
							<th style="width:145px;"><?php esc_html_e( 'Page Type', 'hello-elementor-child' ); ?></th>
							<th><?php esc_html_e( 'Property / Title', 'hello-elementor-child' ); ?></th>
							<th style="width:75px;"><?php esc_html_e( 'Post ID', 'hello-elementor-child' ); ?></th>
							<th style="width:200px;"><?php esc_html_e( 'Page URL', 'hello-elementor-child' ); ?></th>
							<th style="width:270px;"><?php esc_html_e( 'Message', 'hello-elementor-child' ); ?></th>
							<th style="width:190px;"><?php esc_html_e( 'Referrer', 'hello-elementor-child' ); ?></th>
							<th style="width:110px;"><?php esc_html_e( 'IP', 'hello-elementor-child' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td title="<?php echo esc_attr( (string) $row->created_at ); ?>"><?php echo esc_html( mysql2date( 'M j, Y g:i:s a', (string) $row->created_at ) ); ?></td>
								<td>
									<span class="pera-whatsapp-type-badge"><?php echo esc_html( pera_whatsapp_logs_page_type_label( (string) $row->page_type ) ); ?></span>
								</td>
								<td class="pera-whatsapp-logs-title" title="<?php echo esc_attr( (string) $row->post_title ); ?>"><?php echo esc_html( pera_whatsapp_logs_truncate( (string) $row->post_title, 80 ) ); ?></td>
								<td class="column-postid"><?php echo esc_html( (string) $row->post_id ); ?></td>
								<td>
									<?php if ( ! empty( $row->page_url ) ) : ?>
										<a href="<?php echo esc_url( (string) $row->page_url ); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr( (string) $row->page_url ); ?>"><?php echo esc_html( pera_whatsapp_logs_url_label( (string) $row->page_url ) ); ?></a>
									<?php endif; ?>
								</td>
								<td>
									<div class="pera-whatsapp-logs-message" title="<?php echo esc_attr( (string) $row->message_text ); ?>">
										<?php echo esc_html( pera_whatsapp_logs_truncate( (string) $row->message_text, 130 ) ); ?>
									</div>
									<?php if ( '' !== (string) $row->message_text ) : ?>
										<button type="button" class="button-link pera-whatsapp-copy-message" data-message="<?php echo esc_attr( (string) $row->message_text ); ?>"><?php esc_html_e( 'Copy', 'hello-elementor-child' ); ?></button>
									<?php endif; ?>
								</td>
								<td title="<?php echo esc_attr( (string) $row->referrer ); ?>"><?php echo esc_html( pera_whatsapp_logs_url_label( (string) $row->referrer ) ); ?></td>
								<td class="column-ip"><?php echo esc_html( (string) $row->ip_address ); ?></td>
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
							'base'      => add_query_arg( 'paged', '%#%', admin_url( 'admin.php' ) ),
							'format'    => '',
							'current'   => $paged,
							'total'     => $total_pages,
							'add_args'  => $base_query_args,
							'prev_text' => __( '&laquo;', 'hello-elementor-child' ),
							'next_text' => __( '&raquo;', 'hello-elementor-child' ),
						)
					)
				);
				echo '</div></div>';
			?>

			<style>
				.pera-whatsapp-logs-summary {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
					gap: 10px;
					margin: 14px 0;
				}
				.pera-whatsapp-logs-summary-card {
					background: #fff;
					border: 1px solid #dcdcde;
					border-radius: 4px;
					padding: 10px 12px;
					display: flex;
					flex-direction: column;
					gap: 6px;
				}
				.pera-whatsapp-logs-summary-label {
					color: #50575e;
					font-size: 12px;
				}
				.pera-whatsapp-logs-toolbar {
					margin: 12px 0;
					display: flex;
					gap: 8px;
					align-items: center;
					flex-wrap: wrap;
				}
				.pera-whatsapp-logs-result-count {
					margin-left: auto;
					color: #50575e;
				}
				.pera-whatsapp-type-badge {
					display: inline-block;
					padding: 2px 8px;
					background: #f0f6fc;
					border: 1px solid #c5d9ed;
					border-radius: 999px;
					font-size: 12px;
					line-height: 1.6;
				}
				.pera-whatsapp-logs-table .column-postid,
				.pera-whatsapp-logs-table .column-ip {
					color: #646970;
				}
				.pera-whatsapp-logs-table td {
					vertical-align: top;
				}
				.pera-whatsapp-logs-title,
				.pera-whatsapp-logs-message {
					word-break: break-word;
				}
				.pera-whatsapp-copy-message {
					font-size: 12px;
				}
				.pera-whatsapp-logs-empty-state {
					margin-top: 10px;
				}
				@media (max-width: 900px) {
					.pera-whatsapp-logs-result-count {
						width: 100%;
						margin-left: 0;
					}
				}
			</style>

			<script>
				document.addEventListener('DOMContentLoaded', function () {
					var buttons = document.querySelectorAll('.pera-whatsapp-copy-message');

					buttons.forEach(function (button) {
						button.addEventListener('click', function () {
							var message = button.getAttribute('data-message') || '';

							if (!message || !navigator.clipboard || !navigator.clipboard.writeText) {
								return;
							}

							navigator.clipboard.writeText(message).then(function () {
								var originalText = button.textContent;
								button.textContent = '<?php echo esc_js( __( 'Copied!', 'hello-elementor-child' ) ); ?>';

								window.setTimeout(function () {
									button.textContent = originalText;
								}, 1500);
							});
						});
					});
				});
			</script>
		</div>
		<?php
	}
}
