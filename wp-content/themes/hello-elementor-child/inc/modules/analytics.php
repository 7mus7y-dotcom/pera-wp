<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! is_admin() ) {
	add_action( 'wp_head', 'pera_analytics_head_scripts', 20 );
}

function pera_analytics_head_scripts() {
	if ( is_admin() ) {
		return;
	}

	pera_analytics_render_ahrefs();
}

function pera_analytics_render_ahrefs() {
	?>
	<script src="https://analytics.ahrefs.com/analytics.js" data-key="qBXJ/3s644JbBBMoxm/6ZQ" async></script>
	<?php
}
