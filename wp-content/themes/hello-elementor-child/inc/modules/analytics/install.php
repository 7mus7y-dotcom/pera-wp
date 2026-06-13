<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/source-classification.php';

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
		$target_version    = '1.3.0';

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
			referer_host VARCHAR(255) NULL,
			source_type VARCHAR(32) NOT NULL DEFAULT 'direct',
			is_internal TINYINT(1) NOT NULL DEFAULT 0,
			is_direct TINYINT(1) NOT NULL DEFAULT 0,
			is_suspected_bot TINYINT(1) NOT NULL DEFAULT 0,
			country_code VARCHAR(2) NOT NULL DEFAULT 'XX',
			country_name VARCHAR(100) NOT NULL DEFAULT 'Unknown',
			user_agent_hash VARCHAR(64) NULL,
			PRIMARY KEY  (id),
			KEY visited_at (visited_at),
			KEY page_path (page_path),
			KEY post_id (post_id),
			KEY visitor_id (visitor_id),
			KEY is_suspected_bot (is_suspected_bot),
			KEY source_type (source_type),
			KEY referer_host (referer_host),
			KEY country_code (country_code)
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
		pera_analytics_backfill_visit_sources();

		update_option( 'pera_analytics_schema_version', $target_version, false );
	}
}
add_action( 'init', 'pera_analytics_install_schema', 5 );

if ( ! function_exists( 'pera_analytics_backfill_visit_sources' ) ) {
	function pera_analytics_backfill_visit_sources( int $batch_size = 500 ): void {
		global $wpdb;

		$raw_table = pera_analytics_raw_table_name();
		$cutoff    = gmdate( 'Y-m-d H:i:s', strtotime( '-90 days' ) );
		$site_host = pera_analytics_normalize_host( wp_parse_url( home_url(), PHP_URL_HOST ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, referer
				FROM {$raw_table}
				WHERE visited_at >= %s
				  AND (
					referer_host IS NULL
					OR source_type IS NULL
					OR source_type = ''
				  )
				ORDER BY id ASC
				LIMIT %d",
				$cutoff,
				$batch_size
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			$classification = pera_analytics_classify_referer_source( (string) $row['referer'], $site_host );
			$wpdb->update(
				$raw_table,
				array(
					'referer_host' => $classification['referer_host'],
					'source_type'  => $classification['source_type'],
					'is_internal'  => $classification['is_internal'],
					'is_direct'    => $classification['is_direct'],
				),
				array( 'id' => (int) $row['id'] ),
				array( '%s', '%s', '%d', '%d' ),
				array( '%d' )
			);
		}
	}
}

if ( ! function_exists( 'pera_analytics_schedule_events' ) ) {
	function pera_analytics_schedule_events(): void {
		if ( ! wp_next_scheduled( 'pera_analytics_daily_aggregate' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'pera_analytics_daily_aggregate' );
		}
	}
}
add_action( 'init', 'pera_analytics_schedule_events', 20 );
