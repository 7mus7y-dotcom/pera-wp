<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_get_current_request_url' ) ) {
	/**
	 * Return current request URL across singular, archive, and generic pages.
	 */
	function pera_get_current_request_url(): string {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '';

		if ( is_string( $request_uri ) && '' !== $request_uri ) {
			$current_url = home_url( $request_uri );
			if ( is_string( $current_url ) && '' !== $current_url ) {
				return esc_url_raw( $current_url );
			}
		}

		if ( is_singular() ) {
			$queried_id = get_queried_object_id();
			if ( $queried_id > 0 ) {
				$permalink = get_permalink( $queried_id );
				if ( is_string( $permalink ) && '' !== $permalink ) {
					return esc_url_raw( $permalink );
				}
			}
		}

		return esc_url_raw( home_url( '/' ) );
	}
}

if ( ! function_exists( 'pera_get_property_reference' ) ) {
	/**
	 * Build the listing reference used across property templates/enquiries.
	 */
	function pera_get_property_reference( int $post_id ): string {
		if ( $post_id <= 0 ) {
			return '';
		}

		$post = get_post( $post_id );
		if ( ! ( $post instanceof WP_Post ) || 'property' !== $post->post_type ) {
			return '';
		}

		// single-property.php and enquiry handlers expose the listing reference as the property post ID.
		return (string) (int) $post->ID;
	}
}

if ( ! function_exists( 'pera_get_whatsapp_context' ) ) {
	function pera_get_whatsapp_context(): array {
		$page_url = pera_get_current_request_url();

		$context = array(
			'page_type'     => 'generic',
			'post_id'       => 0,
			'post_title'    => '',
			'page_url'      => $page_url,
			'message_text'  => "Hi, I'd like more information about property in Istanbul.",
			'whatsapp_url'  => '',
		);

		if ( is_singular( 'property' ) ) {
			$post_id    = (int) get_queried_object_id();
			$post_title = $post_id > 0 ? (string) get_the_title( $post_id ) : '';
			$page_url   = $post_id > 0 ? (string) get_permalink( $post_id ) : $page_url;

			$context['page_type']  = 'single-property';
			$context['post_id']    = $post_id;
			$context['post_title'] = $post_title;
			$context['page_url']   = esc_url_raw( $page_url );
		} elseif ( is_page( 'citizenship-by-investment' ) ) {
			$context['page_type']    = 'citizenship-by-investment';
		} elseif ( is_page( 'sell-with-pera' ) ) {
			$context['page_type']    = 'sell-with-pera';
		} elseif ( is_page( 'rent-with-pera' ) ) {
			$context['page_type']    = 'rent-with-pera';
		}

		$context['message_text'] = pera_get_whatsapp_message();
		$context['whatsapp_url'] = pera_get_whatsapp_url();

		return $context;
	}
}

if ( ! function_exists( 'pera_get_whatsapp_message' ) ) {
	function pera_get_whatsapp_message(): string {
		if ( is_singular( 'property' ) ) {
			$post_id    = (int) get_queried_object_id();
			$post_title = $post_id > 0 ? (string) get_the_title( $post_id ) : '';
			$page_url   = $post_id > 0 ? (string) get_permalink( $post_id ) : pera_get_current_request_url();
			$reference  = function_exists( 'pera_get_property_reference' ) ? pera_get_property_reference( $post_id ) : '';

			return sprintf(
				'Hi, I\'d like more info on property called "%s" with reference %s. %s',
				$post_title,
				$reference !== '' ? $reference : (string) $post_id,
				esc_url_raw( $page_url )
			);
		}

		if ( is_page( 'citizenship-by-investment' ) ) {
			return "Hi, I'd like more info on citizenship by investment.";
		}

		if ( is_page( 'sell-with-pera' ) ) {
			return "Hi, I'm interested in selling my property in Istanbul. Can you provide more information?";
		}

		if ( is_page( 'rent-with-pera' ) ) {
			return "Hi, I'm interested in renting out my property in Istanbul. Can you provide more information?";
		}

		return "Hi, I'd like more information about property in Istanbul.";
	}
}

if ( ! function_exists( 'pera_get_whatsapp_url' ) ) {
	function pera_get_whatsapp_url(): string {
		$phone = '905452054356';

		return 'https://wa.me/' . $phone . '?text=' . rawurlencode( pera_get_whatsapp_message() );
	}
}

if ( ! function_exists( 'pera_floating_whatsapp_button' ) ) {
	function pera_floating_whatsapp_button() {
		if ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'] ) {
			return;
		}

		$is_crm_route = function_exists( 'pera_is_crm_route' ) && pera_is_crm_route();

		if ( $is_crm_route && is_user_logged_in() && function_exists( 'pera_crm_user_can_access' ) && pera_crm_user_can_access() ) {
			$crm_overdue_count = function_exists( 'pera_crm_get_overdue_reminders_count_for_current_user' )
				? (int) pera_crm_get_overdue_reminders_count_for_current_user()
				: 0;
			$crm_label = $crm_overdue_count > 0
				? sprintf( 'CRM (%d overdue reminders)', $crm_overdue_count )
				: 'CRM';
			?>
			<a href="<?php echo esc_url( home_url( '/crm' ) ); ?>"
			   class="header-crm-toggle crm-floating-toggle"
			   aria-label="<?php echo esc_attr( $crm_label ); ?>">
				<svg class="icon" aria-hidden="true">
					<use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-users-group' ); ?>"></use>
				</svg>
				<?php if ( $crm_overdue_count > 0 ) : ?>
					<span class="header-icon-dot" aria-hidden="true"></span>
				<?php endif; ?>
			</a>
			<?php
			return;
		}

		$whatsapp_context = pera_get_whatsapp_context();
		?>
		<a href="<?php echo esc_url( $whatsapp_context['whatsapp_url'] ); ?>"
		   class="floating-whatsapp"
		   id="floating-whatsapp"
		   aria-label="Chat on WhatsApp"
		   target="_blank"
		   rel="noopener"
		   data-whatsapp-url="<?php echo esc_url( $whatsapp_context['whatsapp_url'] ); ?>"
		   data-page-type="<?php echo esc_attr( $whatsapp_context['page_type'] ); ?>"
		   data-post-id="<?php echo esc_attr( (string) $whatsapp_context['post_id'] ); ?>"
		   data-post-title="<?php echo esc_attr( $whatsapp_context['post_title'] ); ?>"
		   data-page-url="<?php echo esc_url( $whatsapp_context['page_url'] ); ?>"
		   data-message-text="<?php echo esc_attr( $whatsapp_context['message_text'] ); ?>">

			<span class="floating-whatsapp__tooltip">
				Chat on WhatsApp
			</span>

			<svg class="icon" aria-hidden="true">
				<use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-whatsapp' ); ?>"></use>
			</svg>

		</a>
		<?php
	}
}
