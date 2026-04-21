<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_analytics_raw_table_name' ) ) {
	function pera_analytics_raw_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'pera_page_visits';
	}
}

if ( ! function_exists( 'pera_analytics_daily_table_name' ) ) {
	function pera_analytics_daily_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'pera_page_visit_daily';
	}
}

if ( ! function_exists( 'pera_analytics_install_schema' ) ) {
	function pera_analytics_install_schema(): void {
		global $wpdb;

		$installed_version = get_option( 'pera_analytics_schema_version', '' );
		$target_version    = '1.0.0';

		if ( $installed_version === $target_version ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$raw_table       = pera_analytics_raw_table_name();
		$daily_table     = pera_analytics_daily_table_name();

		$raw_sql = "CREATE TABLE {$raw_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			visited_at DATETIME NOT NULL,
			visitor_id VARCHAR(64) NOT NULL,
			page_url TEXT NOT NULL,
			page_path VARCHAR(255) NOT NULL,
			page_title VARCHAR(255) NULL,
			post_id BIGINT UNSIGNED NULL,
			post_type VARCHAR(64) NULL,
			referer TEXT NULL,
			user_agent_hash VARCHAR(64) NULL,
			PRIMARY KEY  (id),
			KEY visited_at (visited_at),
			KEY page_path (page_path),
			KEY post_id (post_id),
			KEY visitor_id (visitor_id)
		) {$charset_collate};";

		$daily_sql = "CREATE TABLE {$daily_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			summary_date DATE NOT NULL,
			page_path VARCHAR(255) NOT NULL,
			post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			page_title VARCHAR(255) NULL,
			visits INT UNSIGNED NOT NULL DEFAULT 0,
			unique_visitors INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY date_path_post (summary_date, page_path, post_id),
			KEY summary_date (summary_date),
			KEY page_path (page_path),
			KEY post_id (post_id)
		) {$charset_collate};";

		dbDelta( $raw_sql );
		dbDelta( $daily_sql );

		update_option( 'pera_analytics_schema_version', $target_version, false );
	}
}
add_action( 'init', 'pera_analytics_install_schema', 5 );

if ( ! function_exists( 'pera_analytics_schedule_events' ) ) {
	function pera_analytics_schedule_events(): void {
		if ( ! wp_next_scheduled( 'pera_analytics_daily_aggregate' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'pera_analytics_daily_aggregate' );
		}
	}
}
add_action( 'init', 'pera_analytics_schedule_events', 20 );
