<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_analytics_render_ahrefs' ) ) {
	function pera_analytics_render_ahrefs(): void {
		?>
		<script src="https://analytics.ahrefs.com/analytics.js" data-key="qBXJ/3s644JbBBMoxm/6ZQ" async></script>
		<?php
	}
}
