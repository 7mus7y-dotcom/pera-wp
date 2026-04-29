<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'PERA_META_PIXEL_ID' ) ) {
	define( 'PERA_META_PIXEL_ID', '539517871655580' );
}

if ( ! function_exists( 'pera_analytics_meta_pixel_should_track' ) ) {
	function pera_analytics_meta_pixel_should_track(): bool {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		if ( is_feed() || is_preview() || is_trackback() || is_robots() ) {
			return false;
		}

		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return false;
		}

		return true;
	}
}

if ( ! function_exists( 'pera_analytics_meta_pixel_print_head' ) ) {
	function pera_analytics_meta_pixel_print_head(): void {
		if ( ! pera_analytics_meta_pixel_should_track() ) {
			return;
		}

		if ( defined( 'PERA_META_PIXEL_PRINTED' ) ) {
			return;
		}
		define( 'PERA_META_PIXEL_PRINTED', true );
		?>
		<!-- Meta Pixel Code -->
		<script>
		!function(f,b,e,v,n,t,s)
		{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
		n.callMethod.apply(n,arguments):n.queue.push(arguments)};
		if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
		n.queue=[];t=b.createElement(e);t.async=!0;
		t.src=v;s=b.getElementsByTagName(e)[0];
		s.parentNode.insertBefore(t,s)}(window, document,'script',
		'https://connect.facebook.net/en_US/fbevents.js');
		fbq('init', '<?php echo esc_js( PERA_META_PIXEL_ID ); ?>');
		fbq('track', 'PageView');
		</script>
		<!-- End Meta Pixel Code -->
		<?php
	}
}

if ( ! function_exists( 'pera_analytics_meta_pixel_print_noscript' ) ) {
	function pera_analytics_meta_pixel_print_noscript(): void {
		if ( ! pera_analytics_meta_pixel_should_track() ) {
			return;
		}

		if ( defined( 'PERA_META_PIXEL_NOSCRIPT_PRINTED' ) ) {
			return;
		}
		define( 'PERA_META_PIXEL_NOSCRIPT_PRINTED', true );
		?>
		<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?php echo rawurlencode( PERA_META_PIXEL_ID ); ?>&amp;ev=PageView&amp;noscript=1" alt="" /></noscript>
		<?php
	}
}
