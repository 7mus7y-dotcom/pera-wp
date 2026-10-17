<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_get_property_archive_settings_page_id' ) ) {
	/**
	 * Locate the private page that stores ACF fields for the main property archive.
	 */
	function pera_get_property_archive_settings_page_id(): int {
		$page = get_page_by_path( 'property-archive-seo-settings', OBJECT, 'page' );

		if ( $page instanceof WP_Post ) {
			return (int) $page->ID;
		}

		return 0;
	}
}

/**
 * Presentation-only context for the first unfiltered main archive.
 * Keep the actual query string intact for filters, pagination and existing SEO rules.
 */
function pera_property_archive_seo_preview_is_main_request(): bool {
	$query = $_GET;
	unset( $query['seo_archive_preview'] );

	return ! is_admin() && ! wp_doing_ajax()
		&& is_post_type_archive( 'property' ) && ! is_tax() && ! is_search() && ! is_paged()
		&& empty( $query );
}

/** Only explicitly opted-in, logged-in administrators may view the alternate content. */
function pera_property_archive_seo_preview_enabled(): bool {
	return isset( $_GET['seo_archive_preview'] )
		&& is_string( $_GET['seo_archive_preview'] ) && $_GET['seo_archive_preview'] === '1'
		&& is_user_logged_in() && current_user_can( 'manage_options' )
		&& pera_property_archive_seo_preview_is_main_request();
}

/** Send cache protection before headers or template content are emitted. */
add_action( 'template_redirect', function () {
	if ( ! pera_property_archive_seo_preview_enabled() ) {
		return;
	}

	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	nocache_headers();
}, 0 );
