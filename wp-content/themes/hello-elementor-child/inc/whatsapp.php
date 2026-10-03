<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pera_whatsapp_clean_text' ) ) {
	/** Normalize a queried title or term name before using it in a message. */
	function pera_whatsapp_clean_text( $value ): string {
		$value = html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES, get_bloginfo( 'charset' ) ?: 'UTF-8' );
		$clean = preg_replace( '/\s+/u', ' ', $value );
		return trim( is_string( $clean ) ? $clean : $value );
	}
}

if ( ! function_exists( 'pera_whatsapp_safe_request_path' ) ) {
	/**
	 * Return the public path/query portion of this request.
	 *
	 * The site URL supplies the trusted host. Private/security and attribution
	 * parameters are removed while public property filters and pagination remain.
	 */
	function pera_whatsapp_safe_request_path(): string {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '';
		$parts       = wp_parse_url( $request_uri );
		$path        = is_array( $parts ) && isset( $parts['path'] ) ? (string) $parts['path'] : '/';
		$path        = '/' === substr( $path, 0, 1 ) ? $path : '/' . $path;
		$query       = array();

		if ( is_array( $parts ) && ! empty( $parts['query'] ) ) {
			parse_str( (string) $parts['query'], $query );
			$blocked = array( '_wpnonce', 'nonce', 'security', 'password', 'pwd', 'auth', 'authorization', 'token', 'debug', 'preview_nonce', 'fbclid', 'gclid', 'msclkid' );
			foreach ( array_keys( $query ) as $key ) {
				$normalized_key = strtolower( (string) $key );
				if ( in_array( $normalized_key, $blocked, true ) || 0 === strpos( $normalized_key, 'utm_' ) ) {
					unset( $query[ $key ] );
				}
			}
		}

		return $path . ( $query ? '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ) : '' );
	}
}

if ( ! function_exists( 'pera_get_current_request_url' ) ) {
	/** Return a host-safe current URL, retaining useful public query parameters. */
	function pera_get_current_request_url(): string {
		$current_url = home_url( pera_whatsapp_safe_request_path() );
		if ( is_string( $current_url ) && '' !== $current_url ) {
			return esc_url_raw( $current_url );
		}

		return esc_url_raw( home_url( '/' ) );
	}
}

if ( ! function_exists( 'pera_get_whatsapp_canonical_url' ) ) {
	/** Return a canonical permalink when possible, otherwise the safe current URL. */
	function pera_get_whatsapp_canonical_url( int $post_id = 0 ): string {
		$post_id = $post_id > 0 ? $post_id : (int) get_queried_object_id();
		$url     = $post_id > 0 ? get_permalink( $post_id ) : '';
		return is_string( $url ) && '' !== $url ? esc_url_raw( $url ) : pera_get_current_request_url();
	}
}

if ( ! function_exists( 'pera_get_property_reference' ) ) {
	/** Build the listing reference used across property templates/enquiries. */
	function pera_get_property_reference( int $post_id ): string {
		if ( $post_id <= 0 ) {
			return '';
		}
		$post = get_post( $post_id );
		return $post instanceof WP_Post && 'property' === $post->post_type ? (string) (int) $post->ID : '';
	}
}

if ( ! function_exists( 'pera_whatsapp_queried_title' ) ) {
	/** Obtain a safe title from the queried object rather than request data. */
	function pera_whatsapp_queried_title(): string {
		$object = get_queried_object();
		if ( $object instanceof WP_Term ) {
			return pera_whatsapp_clean_text( $object->name );
		}
		if ( $object instanceof WP_Post ) {
			return pera_whatsapp_clean_text( get_the_title( $object->ID ) );
		}
		return function_exists( 'wp_get_document_title' ) ? pera_whatsapp_clean_text( wp_get_document_title() ) : '';
	}
}

if ( ! function_exists( 'pera_whatsapp_message' ) ) {
	/** Safely interpolate dynamic values into an already translated format string. */
	function pera_whatsapp_message( string $format, ...$values ): string {
		return pera_whatsapp_clean_text( $values ? sprintf( $format, ...$values ) : $format );
	}
}

if ( ! function_exists( 'pera_get_whatsapp_context' ) ) {
	/**
	 * Resolve the floating-button context in deliberately narrow-to-broad order.
	 *
	 * @return array{page_type:string,post_id:int,post_title:string,page_url:string,message_text:string,whatsapp_url:string}
	 */
	function pera_get_whatsapp_context(): array {
		$current_url = pera_get_current_request_url();
		$post_id     = is_singular() ? (int) get_queried_object_id() : 0;
		$title       = pera_whatsapp_queried_title();
		$search_post_types = is_search() ? (array) get_query_var( 'post_type' ) : array();
		$context     = array( 'page_type' => 'generic', 'post_id' => $post_id, 'post_title' => $title, 'page_url' => $current_url, 'message_text' => '', 'whatsapp_url' => '' );

		// 1. Individual property; keep its canonical URL and listing reference.
		if ( is_singular( 'property' ) ) {
			$context['page_type']  = 'single-property';
			$context['post_id']    = $post_id;
			$context['post_title'] = $title;
			$context['page_url']   = pera_get_whatsapp_canonical_url( $post_id );
			$reference            = pera_get_property_reference( $post_id );
			if ( $title && $reference ) {
				$context['message_text'] = pera_whatsapp_message( pera_ml_ui( 'Hi, I\'d like more information about the property “%1$s”, reference %2$s: %3$s', 'theme.whatsapp.single_property_message' ), $title, $reference, $context['page_url'] );
			} elseif ( $reference ) {
				$context['message_text'] = pera_whatsapp_message( pera_ml_ui( 'Hi, I\'d like more information about property reference %1$s: %2$s', 'theme.whatsapp.single_property_reference_message' ), $reference, $context['page_url'] );
			} else {
				$context['message_text'] = pera_whatsapp_message( pera_ml_ui( 'Hi, I\'d like more information about this property: %1$s', 'theme.whatsapp.single_property_url_message' ), $context['page_url'] );
			}

		// 2. Individual editorial post.
		} elseif ( is_singular( 'post' ) ) {
			$context['page_type']    = 'blog-article';
			$context['post_id']      = $post_id;
			$context['post_title']   = $title;
			$context['page_url']     = pera_get_whatsapp_canonical_url( $post_id );
			$context['message_text'] = $title
				? pera_whatsapp_message( pera_ml_ui( 'Hi, I have a question about your article “%1$s”: %2$s', 'theme.whatsapp.blog_article_message' ), $title, $context['page_url'] )
				: pera_whatsapp_message( pera_ml_ui( 'Hi, I have a question about this article: %1$s', 'theme.whatsapp.blog_article_url_message' ), $context['page_url'] );

		// 3. Main property archive and filtered property searches.
		} elseif ( is_post_type_archive( 'property' ) || in_array( 'property', $search_post_types, true ) ) {
			$context['page_type']    = 'property-search';
			$context['message_text'] = pera_whatsapp_message( pera_ml_ui( 'Hi, I\'m viewing your Istanbul property listings and would like help finding a suitable property: %1$s', 'theme.whatsapp.property_search_message' ), $current_url );

		// 4. Citizenship-eligible property results (not the information page).
		} elseif ( is_page( 'turkish-citizenship-properties' ) || is_page_template( 'page-citizenship-properties.php' ) ) {
			$context['page_type']    = 'citizenship-properties';
			$context['post_id']      = $post_id;
			$context['post_title']   = $title;
			$context['message_text'] = pera_whatsapp_message( pera_ml_ui( 'Hi, I\'m viewing your Turkish citizenship-eligible properties and would like some guidance: %1$s', 'theme.whatsapp.citizenship_properties_message' ), $current_url );

		// 5. Taxonomies that feed property search.
		} elseif ( is_tax( array( 'region', 'district', 'property_tags', 'property_type', 'special' ) ) ) {
			$context['page_type']    = 'property-taxonomy';
			$context['message_text'] = $title
				? pera_whatsapp_message( pera_ml_ui( 'Hi, I\'m viewing the property selection for “%1$s” and would like more information: %2$s', 'theme.whatsapp.property_taxonomy_message' ), $title, $current_url )
				: pera_whatsapp_message( pera_ml_ui( 'Hi, I\'m viewing this property selection and would like more information: %1$s', 'theme.whatsapp.property_taxonomy_url_message' ), $current_url );

		// 6. Editorial archives.
		} elseif ( is_category() ) {
			$context['page_type']    = 'blog-category';
			$context['message_text'] = $title
				? pera_whatsapp_message( pera_ml_ui( 'Hi, I\'m reading your articles about “%1$s” and have a question: %2$s', 'theme.whatsapp.blog_category_message' ), $title, $current_url )
				: pera_whatsapp_message( pera_ml_ui( 'Hi, I\'m reading this article category and have a question: %1$s', 'theme.whatsapp.blog_category_url_message' ), $current_url );
		} elseif ( is_tag() ) {
			$context['page_type']    = 'blog-tag';
			$context['message_text'] = $title
				? pera_whatsapp_message( pera_ml_ui( 'Hi, I\'m reading your articles about “%1$s” and have a question: %2$s', 'theme.whatsapp.blog_tag_message' ), $title, $current_url )
				: pera_whatsapp_message( pera_ml_ui( 'Hi, I\'m reading this article topic and have a question: %1$s', 'theme.whatsapp.blog_tag_url_message' ), $current_url );
		} elseif ( is_home() || is_date() || is_author() || is_post_type_archive( 'post' ) ) {
			$context['page_type']    = 'blog-archive';
			$context['message_text'] = pera_whatsapp_message( pera_ml_ui( 'Hi, I\'m browsing your property articles and have a question: %1$s', 'theme.whatsapp.blog_archive_message' ), $current_url );

		// 7. Service and landing pages. Template checks support translated slugs.
		} elseif ( is_page( 'citizenship-by-investment' ) || is_page_template( 'page-citizenship.php' ) ) {
			$context['page_type']    = 'citizenship-by-investment';
			$context['message_text'] = pera_whatsapp_message( pera_ml_ui( 'Hi, I\'d like more information about Turkish citizenship by investment: %1$s', 'theme.whatsapp.citizenship_by_investment_message' ), $current_url );
		} elseif ( is_page( 'sell-with-pera' ) || is_page_template( 'page-sell-with-pera.php' ) ) {
			$context['page_type']    = 'sell-with-pera';
			$context['message_text'] = pera_whatsapp_message( pera_ml_ui( 'Hi, I\'m interested in selling my property in Istanbul with Pera Property. Could you provide more information about your sales service? %1$s', 'theme.whatsapp.sell_with_pera_message' ), $current_url );
		} elseif ( is_page( 'rent-with-pera' ) || is_page_template( 'page-rent-with-pera.php' ) ) {
			$context['page_type']    = 'rent-with-pera';
			$context['message_text'] = pera_whatsapp_message( pera_ml_ui( 'Hi, I\'m interested in property management for my Istanbul property. Could you provide more information about your service? %1$s', 'theme.whatsapp.rent_with_pera_message' ), $current_url );
		} elseif ( is_page( 'developer-sales-office' ) || is_page_template( 'page-developer-sales-office.php' ) ) {
			$context['page_type']    = 'developer-sales-office';
			$context['message_text'] = pera_whatsapp_message( pera_ml_ui( 'Hi, I\'d like to discuss a developer sales and marketing operation with Pera Property: %1$s', 'theme.whatsapp.developer_sales_office_message' ), $current_url );
		} elseif ( is_page( 'book-a-consultancy' ) || is_page_template( 'page-book-a-consultancy.php' ) ) {
			$context['page_type']    = 'book-consultancy';
			$context['message_text'] = pera_whatsapp_message( pera_ml_ui( 'Hi, I\'d like to book a property consultancy with Pera Property: %1$s', 'theme.whatsapp.book_consultancy_message' ), $current_url );
		} elseif ( is_page( 'property-map' ) || is_page_template( 'page-property-map.php' ) ) {
			$context['page_type']    = 'property-map';
			$context['message_text'] = pera_whatsapp_message( pera_ml_ui( 'Hi, I\'m using your Istanbul property map and would like personalised property recommendations: %1$s', 'theme.whatsapp.property_map_message' ), $current_url );
		} elseif ( is_page( array( 'istanbul-luxury-property', 'luxury-property' ) ) || is_page_template( 'page-luxury-property.php' ) ) {
			$context['page_type']    = 'luxury-property';
			$context['message_text'] = pera_whatsapp_message( pera_ml_ui( 'Hi, I\'m interested in luxury property in Istanbul and would like a personalised shortlist: %1$s', 'theme.whatsapp.luxury_property_message' ), $current_url );
		} elseif ( is_page( 'contact-us' ) || is_page_template( 'page-contact.php' ) ) {
			$context['page_type']    = 'contact';
			$context['message_text'] = pera_whatsapp_message( pera_ml_ui( 'Hi, I\'d like to contact Pera Property regarding this page: %1$s', 'theme.whatsapp.contact_message' ), $current_url );

		// 8. Any remaining singular public content.
		} elseif ( is_singular() ) {
			$context['page_type']  = 'content-page';
			$context['post_id']    = $post_id;
			$context['post_title'] = $title;
			$context['page_url']   = pera_get_whatsapp_canonical_url( $post_id );
			$context['message_text'] = $title
				? pera_whatsapp_message( pera_ml_ui( 'Hi, I\'d like more information about “%1$s”: %2$s', 'theme.whatsapp.content_page_message' ), $title, $context['page_url'] )
				: pera_whatsapp_message( pera_ml_ui( 'Hi, I\'d like more information about this page on Pera Property: %1$s', 'theme.whatsapp.untitled_page_message' ), $context['page_url'] );

		// 9. Safe final fallback, never context-free.
		} else {
			$context['message_text'] = $title
				? pera_whatsapp_message( pera_ml_ui( 'Hi, I\'d like more information about this page on Pera Property: “%1$s” — %2$s', 'theme.whatsapp.generic_page_message' ), $title, $current_url )
				: pera_whatsapp_message( pera_ml_ui( 'Hi, I\'d like more information about this page on Pera Property: %1$s', 'theme.whatsapp.generic_url_message' ), $current_url );
		}

		/**
		 * Filter floating WhatsApp context. Expected keys match the return shape above.
		 * Invalid/missing values fall back to the already resolved safe context.
		 */
		$filtered = apply_filters( 'pera_floating_whatsapp_context', $context );
		if ( is_array( $filtered ) ) {
			$context['page_type']    = sanitize_key( (string) ( $filtered['page_type'] ?? $context['page_type'] ) ) ?: $context['page_type'];
			$context['post_id']      = absint( $filtered['post_id'] ?? $context['post_id'] );
			$context['post_title']   = pera_whatsapp_clean_text( $filtered['post_title'] ?? $context['post_title'] );
			$context['page_url']     = esc_url_raw( (string) ( $filtered['page_url'] ?? $context['page_url'] ) ) ?: $context['page_url'];
			$context['message_text'] = pera_whatsapp_clean_text( $filtered['message_text'] ?? $context['message_text'] ) ?: $context['message_text'];
		}
		$context['whatsapp_url'] = pera_get_whatsapp_url( $context['message_text'] );
		return $context;
	}
}

if ( ! function_exists( 'pera_floating_whatsapp_button' ) ) {
	function pera_floating_whatsapp_button() {
		if ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'] ) return;
		if ( function_exists( 'pera_is_standalone_auth_page' ) && pera_is_standalone_auth_page() ) return;

		$is_crm_route = function_exists( 'pera_is_crm_route' ) && pera_is_crm_route();
		if ( $is_crm_route && is_user_logged_in() && function_exists( 'pera_crm_user_can_access' ) && pera_crm_user_can_access() ) {
			$crm_overdue_count = function_exists( 'pera_crm_get_overdue_reminders_count_for_current_user' ) ? (int) pera_crm_get_overdue_reminders_count_for_current_user() : 0;
			$crm_label = $crm_overdue_count > 0 ? sprintf( 'CRM (%d overdue reminders)', $crm_overdue_count ) : 'CRM';
			?><a href="<?php echo esc_url( home_url( '/crm' ) ); ?>" class="header-crm-toggle crm-floating-toggle" aria-label="<?php echo esc_attr( $crm_label ); ?>"><svg class="icon" aria-hidden="true"><use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-users-group' ); ?>"></use></svg><?php if ( $crm_overdue_count > 0 ) : ?><span class="header-icon-dot" aria-hidden="true"></span><?php endif; ?></a><?php
			return;
		}

		$whatsapp_context = pera_get_whatsapp_context();
		$floating_label   = pera_ml_ui( 'Chat on WhatsApp', 'theme.whatsapp.floating_button_label' );
		?>
		<a href="<?php echo esc_url( $whatsapp_context['whatsapp_url'] ); ?>" class="floating-whatsapp" id="floating-whatsapp" aria-label="<?php echo esc_attr( $floating_label ); ?>" target="_blank" rel="noopener" data-whatsapp="1" data-whatsapp-type="floating_global" data-track-channel="whatsapp" data-track-intent="high" data-track-source="helper" data-track-context="floating_whatsapp" data-track-ga4-event="whatsapp_click" data-track-crm-event="whatsapp_click" data-whatsapp-url="<?php echo esc_url( $whatsapp_context['whatsapp_url'] ); ?>" data-page-type="<?php echo esc_attr( $whatsapp_context['page_type'] ); ?>" data-post-id="<?php echo esc_attr( (string) $whatsapp_context['post_id'] ); ?>" data-post-title="<?php echo esc_attr( $whatsapp_context['post_title'] ); ?>" data-page-url="<?php echo esc_url( $whatsapp_context['page_url'] ); ?>" data-message-text="<?php echo esc_attr( $whatsapp_context['message_text'] ); ?>">
			<span class="floating-whatsapp__tooltip"><?php echo esc_html( $floating_label ); ?></span>
			<svg class="icon" aria-hidden="true"><use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-whatsapp' ); ?>"></use></svg>
		</a>
		<?php
	}
}
