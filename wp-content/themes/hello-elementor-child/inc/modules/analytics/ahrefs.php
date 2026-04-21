<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_analytics_head_scripts' ) ) {
	function pera_analytics_head_scripts(): void {
		if ( is_admin() ) {
			return;
		}

		pera_analytics_render_ahrefs();
	}
}

if ( ! function_exists( 'pera_analytics_render_ahrefs' ) ) {
	function pera_analytics_render_ahrefs(): void {
		?>
		<script src="https://analytics.ahrefs.com/analytics.js" data-key="qBXJ/3s644JbBBMoxm/6ZQ" async></script>
		<?php
	}
}

if ( ! is_admin() ) {
	add_action( 'wp_head', 'pera_analytics_head_scripts', 20 );
}
