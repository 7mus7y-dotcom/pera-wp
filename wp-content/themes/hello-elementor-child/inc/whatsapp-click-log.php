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
		if ( function_exists( 'peracrm_render_whatsapp_page' ) ) {
			peracrm_render_whatsapp_page();
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'WhatsApp Click Logs', 'hello-elementor-child' ) . '</h1><p>' . esc_html__( 'WhatsApp logs UI is unavailable because the CRM plugin renderer is missing.', 'hello-elementor-child' ) . '</p></div>';
	}
}
