<?php
/**
 * Regenerate Thumbnails: REST API controller class
 *
 * @package RegenerateThumbnails
 * @since 3.0.0
 */

/**
 * Registers new REST API endpoints.
 *
 * @since 3.0.0
 */
class RegenerateThumbnails_REST_Controller extends WP_REST_Controller {
	/**
	 * Cache key used for missing-thumbnail scan snapshots.
	 *
	 * @since 3.1.7
	 *
	 * @var string
	 */
	const MISSING_CACHE_KEY = 'regenerate_thumbnails_missing_snapshot_v1';

	/**
	 * The namespace for the REST API routes.
	 *
	 * @since 3.0.0
	 *
	 * @var string
	 */
	public $namespace = 'regenerate-thumbnails/v1';

	/**
	 * Register the new routes and endpoints.
	 *
	 * @since 3.0.0
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/regenerate/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => array( $this, 'regenerate_item' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'only_regenerate_missing_thumbnails'    => array(
						'description' => __( "Whether to only regenerate missing thumbnails. It's faster with this enabled.", 'regenerate-thumbnails' ),
						'type'        => 'boolean',
						'default'     => true,
					),
					'delete_unregistered_thumbnail_files'   => array(
						'description' => __( 'Whether to delete any old, now unregistered thumbnail files.', 'regenerate-thumbnails' ),
						'type'        => 'boolean',
						'default'     => false,
					),
					'update_usages_in_posts'                => array(
						'description' => __( 'Whether to update the image tags in any posts that make use of this attachment.', 'regenerate-thumbnails' ),
						'type'        => 'boolean',
						'default'     => true,
					),
					'update_usages_in_posts_post_type'      => array(
						'description'       => __( 'The types of posts to update. Defaults to all public post types.', 'regenerate-thumbnails' ),
						'type'              => 'array',
						'default'           => array(),
						'validate_callback' => array( $this, 'is_array' ),
					),
					'update_usages_in_posts_post_ids'       => array(
						'description'       => __( 'Specific post IDs to update rather than any posts that use this attachment.', 'regenerate-thumbnails' ),
						'type'              => 'array',
						'default'           => array(),
						'validate_callback' => array( $this, 'is_array' ),
					),
					'update_usages_in_posts_posts_per_loop' => array(
						'description'       => __( "Posts to process per loop. This is to control memory usage and you likely don't need to adjust this.", 'regenerate-thumbnails' ),
						'type'              => 'integer',
						'default'           => 10,
						'sanitize_callback' => 'absint',
					),
				),
			),
		) );

		register_rest_route( $this->namespace, '/attachmentinfo/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'attachment_info' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			),
		) );

		register_rest_route( $this->namespace, '/featuredimages', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'featured_images' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => $this->get_paging_collection_params(),
			),
		) );

		register_rest_route( $this->namespace, '/missing', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'missing_attachments' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array_merge(
					$this->get_paging_collection_params(),
					array(
						'include_summary' => array(
							'description' => __( 'Whether to include summary totals for all regeneratable attachments.', 'regenerate-thumbnails' ),
							'type'        => 'boolean',
							'default'     => true,
						),
					)
				),
			),
		) );
	}

	/**
	 * Register a filter to allow excluding site icons via a query parameter.
	 *
	 * @since 3.0.0
	 */
	public function register_filters() {
		add_filter( 'rest_attachment_query', array( $this, 'maybe_filter_out_site_icons' ), 10, 2 );
		add_filter( 'rest_attachment_query', array( $this, 'maybe_filter_mimes_types' ), 10, 2 );
		add_filter( 'wp_update_attachment_metadata', array( $this, 'invalidate_missing_cache_on_attachment_metadata_update' ), 10, 2 );
		add_action( 'add_attachment', array( $this, 'invalidate_missing_cache_for_attachment_change' ) );
		add_action( 'delete_attachment', array( $this, 'invalidate_missing_cache_for_attachment_change' ) );
	}

	/**
	 * If the exclude_site_icons parameter is set on a media (attachment) request,
	 * filter out any attachments that are or were being used as a site icon.
	 *
	 * @param array           $args    Key value array of query var to query value.
	 * @param WP_REST_Request $request The request used.
	 *
	 * @return array Key value array of query var to query value.
	 */
	public function maybe_filter_out_site_icons( $args, $request ) {
		if ( empty( $request['exclude_site_icons'] ) ) {
			return $args;
		}

		if ( ! isset( $args['meta_query'] ) ) {
			$args['meta_query'] = array();
		}

		$args['meta_query'][] = array(
			'key'     => '_wp_attachment_context',
			'value'   => 'site-icon',
			'compare' => 'NOT EXISTS',
		);

		return $args;
	}

	/**
	 * If the is_regeneratable parameter is set on a media (attachment) request,
	 * filter results to only include images and PDFs.
	 *
	 * @param array           $args    Key value array of query var to query value.
	 * @param WP_REST_Request $request The request used.
	 *
	 * @return array Key value array of query var to query value.
	 */
	public function maybe_filter_mimes_types( $args, $request ) {
		if ( empty( $request['is_regeneratable'] ) ) {
			return $args;
		}

		$args['post_mime_type'] = array();
		foreach ( get_allowed_mime_types() as $mime_type ) {
			if ( 'image/svg+xml' === $mime_type ) {
				continue;
			}

			if ( 'application/pdf' == $mime_type || 'image/' == substr( $mime_type, 0, 6 ) ) {
				$args['post_mime_type'][] = $mime_type;
			}
		}

		return $args;
	}

	/**
	 * Retrieves the paging query params for the collections.
	 *
	 * @since 3.0.0
	 *
	 * @return array Query parameters for the collection.
	 */
	public function get_paging_collection_params() {
		return array_intersect_key(
			parent::get_collection_params(),
			array_flip( array( 'page', 'per_page' ) )
		);
	}

	/**
	 * Regenerate the thumbnails for a specific media item.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return true|WP_Error True on success, otherwise a WP_Error object.
	 */
	public function regenerate_item( $request ) {
		$regenerator = RegenerateThumbnails_Regenerator::get_instance( $request->get_param( 'id' ) );

		if ( is_wp_error( $regenerator ) ) {
			return $regenerator;
		}

		$result = $regenerator->regenerate( array(
			'only_regenerate_missing_thumbnails'  => $request->get_param( 'only_regenerate_missing_thumbnails' ),
			'delete_unregistered_thumbnail_files' => $request->get_param( 'delete_unregistered_thumbnail_files' ),
		) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( $request->get_param( 'update_usages_in_posts' ) ) {
			$posts_updated = $regenerator->update_usages_in_posts( array(
				'post_type'      => $request->get_param( 'update_usages_in_posts_post_type' ),
				'post_ids'       => $request->get_param( 'update_usages_in_posts_post_ids' ),
				'posts_per_loop' => $request->get_param( 'update_usages_in_posts_posts_per_loop' ),
			) );

			// If wp_update_post() failed for any posts, return that error.
			foreach ( $posts_updated as $post_updated_result ) {
				if ( is_wp_error( $post_updated_result ) ) {
					return $post_updated_result;
				}
			}
		}

		self::invalidate_missing_thumbnails_cache();

		return $this->attachment_info( $request );
	}

	/**
	 * Return a bunch of information about the current attachment for use in the UI
	 * including details about the thumbnails.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return array|WP_Error The data array or a WP_Error object on error.
	 */
	public function attachment_info( $request ) {
		$attachment_id = (int) $request->get_param( 'id' );
		$start         = microtime( true );
		$this->maybe_log_attachmentinfo_event( 'attachment_info:start', array( 'attachment_id' => $attachment_id ) );

		$regenerator = RegenerateThumbnails_Regenerator::get_instance( $request->get_param( 'id' ) );

		if ( is_wp_error( $regenerator ) ) {
			$this->maybe_log_attachmentinfo_event( 'attachment_info:error:get_instance', array(
				'attachment_id' => $attachment_id,
				'error_code'    => $regenerator->get_error_code(),
				'duration_ms'   => round( ( microtime( true ) - $start ) * 1000, 2 ),
			) );
			return $regenerator;
		}

		$response = $regenerator->get_attachment_info();

		if ( is_wp_error( $response ) ) {
			$this->maybe_log_attachmentinfo_event( 'attachment_info:error:get_attachment_info', array(
				'attachment_id' => $attachment_id,
				'error_code'    => $response->get_error_code(),
				'duration_ms'   => round( ( microtime( true ) - $start ) * 1000, 2 ),
			) );
		} else {
			$this->maybe_log_attachmentinfo_event( 'attachment_info:success', array(
				'attachment_id' => $attachment_id,
				'duration_ms'   => round( ( microtime( true ) - $start ) * 1000, 2 ),
			) );
		}

		return $response;
	}

	/**
	 * Conditional logger for attachmentinfo lifecycle diagnostics.
	 *
	 * Enable with `add_filter( 'regenerate_thumbnails_debug_attachmentinfo_logging', '__return_true' );`
	 * or define the `REGENERATE_THUMBNAILS_DEBUG_ATTACHMENTINFO` constant as true.
	 *
	 * @since 3.1.6
	 *
	 * @param string $event   Event name.
	 * @param array  $context Optional event context.
	 */
	private function maybe_log_attachmentinfo_event( $event, $context = array() ) {
		$enabled = (
			( defined( 'REGENERATE_THUMBNAILS_DEBUG_ATTACHMENTINFO' ) && REGENERATE_THUMBNAILS_DEBUG_ATTACHMENTINFO )
			|| apply_filters( 'regenerate_thumbnails_debug_attachmentinfo_logging', false )
		);

		if ( ! $enabled ) {
			return;
		}

		error_log(
			sprintf(
				'Regenerate Thumbnails attachmentinfo [%s] %s',
				$event,
				wp_json_encode( $context )
			)
		);
	}

	/**
	 * Return attachment IDs that are being used as featured images.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function featured_images( $request ) {
		global $wpdb;

		$page     = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );

		if ( 0 == $per_page ) {
			$per_page = 10;
		}

		$featured_image_ids = $wpdb->get_results( $wpdb->prepare(
			"SELECT SQL_CALC_FOUND_ROWS meta_value AS id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' GROUP BY meta_value ORDER BY MIN(meta_id) LIMIT %d OFFSET %d",
			$per_page,
			( $per_page * $page ) - $per_page
		) );

		$total     = $wpdb->get_var( "SELECT FOUND_ROWS()" );
		$max_pages = ceil( $total / $per_page );

		if ( $page > $max_pages && $total > 0 ) {
			return new WP_Error( 'rest_post_invalid_page_number', __( 'The page number requested is larger than the number of pages available.' ), array( 'status' => 400 ) );
		}

		$response = rest_ensure_response( $featured_image_ids );

		$response->header( 'X-WP-Total', (int) $total );
		$response->header( 'X-WP-TotalPages', (int) $max_pages );

		$request_params = $request->get_query_params();
		$base           = add_query_arg( $request_params, rest_url( $this->namespace . '/featuredimages' ) );

		if ( $page > 1 ) {
			$prev_page = $page - 1;

			if ( $prev_page > $max_pages ) {
				$prev_page = $max_pages;
			}

			$prev_link = add_query_arg( 'page', $prev_page, $base );
			$response->link_header( 'prev', $prev_link );
		}

		if ( $max_pages > $page ) {
			$next_page = $page + 1;
			$next_link = add_query_arg( 'page', $next_page, $base );

			$response->link_header( 'next', $next_link );
		}

		return $response;
	}

	/**
	 * Return a paginated list of regeneratable attachments missing one or more currently registered thumbnail sizes.
	 *
	 * @since 3.1.7
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
	 */
	public function missing_attachments( $request ) {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = max( 1, (int) $request->get_param( 'per_page' ) );
		$snapshot = $this->get_missing_attachments_snapshot();
		if ( is_wp_error( $snapshot ) ) {
			return $snapshot;
		}

		$total_missing = count( $snapshot['missing_ids'] );
		$max_pages     = (int) ceil( $total_missing / $per_page );

		if ( $page > $max_pages && $total_missing > 0 ) {
			return new WP_Error( 'rest_post_invalid_page_number', __( 'The page number requested is larger than the number of pages available.' ), array( 'status' => 400 ) );
		}

		$offset        = ( $per_page * $page ) - $per_page;
		$page_item_ids = array_slice( $snapshot['missing_ids'], $offset, $per_page );
		$items         = $this->build_missing_items_data( $page_item_ids );

		$response_data = array(
			'items'                     => $items,
			'total_regeneratable'       => (int) $snapshot['total_regeneratable'],
			'attachments_checked'       => (int) $snapshot['attachments_checked'],
			'total_missing_attachments' => $total_missing,
		);

		// allow opting out of broad summary fields if the UI only needs the filtered page dataset.
		if ( ! $request->get_param( 'include_summary' ) ) {
			unset( $response_data['total_regeneratable'], $response_data['attachments_checked'] );
		}

		$response = rest_ensure_response( $response_data );

		$response->header( 'X-WP-Total', $total_missing );
		$response->header( 'X-WP-TotalPages', max( 1, $max_pages ) );

		return $response;
	}

	/**
	 * Returns the list of mime types that are regeneratable by this plugin.
	 *
	 * @since 3.1.7
	 *
	 * @return array
	 */
	private function get_regeneratable_mime_types() {
		$mime_types = array();
		foreach ( get_allowed_mime_types() as $mime_type ) {
			if ( 'image/svg+xml' === $mime_type ) {
				continue;
			}

			if ( 'application/pdf' === $mime_type || 0 === strpos( $mime_type, 'image/' ) ) {
				$mime_types[] = $mime_type;
			}
		}

		return $mime_types;
	}

	/**
	 * Build and cache a missing-thumbnails snapshot for stable filtered pagination.
	 *
	 * Uses a short-lived transient to reduce repeated filesystem scans from the admin UI.
	 *
	 * @since 3.1.7
	 *
	 * @return array|WP_Error {
	 *     Snapshot data.
	 *
	 *     @type array $missing_ids         Filtered list of attachment IDs with missing thumbnails.
	 *     @type int   $total_regeneratable Total regeneratable candidate attachments.
	 *     @type int   $attachments_checked Number of candidate attachments inspected while building the snapshot.
	 * }
	 */
	private function get_missing_attachments_snapshot() {
		$cached = get_transient( self::MISSING_CACHE_KEY );
		if ( is_array( $cached ) && isset( $cached['missing_ids'], $cached['total_regeneratable'], $cached['attachments_checked'] ) ) {
			return $cached;
		}

		$missing_ids         = array();
		$total_regeneratable = 0;
		$attachments_checked = 0;
		$page                = 1;
		$per_page            = 100;

		do {
			$query = new WP_Query( array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'posts_per_page'         => $per_page,
				'paged'                  => $page,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'post_mime_type'         => $this->get_regeneratable_mime_types(),
				'meta_query'             => array(
					array(
						'key'     => '_wp_attachment_context',
						'value'   => 'site-icon',
						'compare' => 'NOT EXISTS',
					),
				),
			) );

			$total_regeneratable += count( $query->posts );

			foreach ( $query->posts as $attachment_id ) {
				$attachments_checked++;

				$regenerator = RegenerateThumbnails_Regenerator::get_instance( $attachment_id );
				if ( is_wp_error( $regenerator ) ) {
					continue;
				}

				$summary = $regenerator->get_missing_thumbnails_summary();
				if ( is_wp_error( $summary ) || empty( $summary['missing_sizes'] ) ) {
					continue;
				}

				$missing_ids[] = (int) $attachment_id;
			}

			$page++;
		} while ( ! empty( $query->posts ) );

		$snapshot = array(
			'missing_ids'         => $missing_ids,
			'total_regeneratable' => $total_regeneratable,
			'attachments_checked' => $attachments_checked,
		);

		set_transient( self::MISSING_CACHE_KEY, $snapshot, 10 * MINUTE_IN_SECONDS );

		return $snapshot;
	}

	/**
	 * Convert attachment IDs into REST response data for missing-thumbnail items.
	 *
	 * @since 3.1.7
	 *
	 * @param array $attachment_ids Attachment IDs.
	 *
	 * @return array
	 */
	private function build_missing_items_data( $attachment_ids ) {
		$items = array();

		foreach ( $attachment_ids as $attachment_id ) {
			$regenerator = RegenerateThumbnails_Regenerator::get_instance( $attachment_id );
			if ( is_wp_error( $regenerator ) ) {
				continue;
			}

			$summary = $regenerator->get_missing_thumbnails_summary();
			if ( is_wp_error( $summary ) || empty( $summary['missing_sizes'] ) ) {
				continue;
			}

			$attachment = get_post( $attachment_id );
			if ( ! $attachment ) {
				continue;
			}

			$file     = get_attached_file( $attachment_id );
			$filename = $file ? wp_basename( $file ) : '';
			$title    = $attachment->post_title ? $attachment->post_title : $filename;

			$items[] = array(
				'id'                   => $attachment_id,
				'title'                => $title,
				'filename'             => $filename,
				'mime_type'            => get_post_mime_type( $attachment ),
				'edit_url'             => get_edit_post_link( $attachment_id, 'raw' ),
				'regenerate_url'       => admin_url( 'tools.php?page=regenerate-thumbnails#/regenerate/' . $attachment_id ),
				'missing_sizes'        => array_values( $summary['missing_sizes'] ),
				'expected_sizes_count' => (int) $summary['expected_sizes_count'],
				'present_sizes_count'  => (int) $summary['present_sizes_count'],
			);
		}

		return $items;
	}

	/**
	 * Invalidate missing-thumbnail cache when attachment metadata changes.
	 *
	 * @since 3.1.7
	 *
	 * @param array $metadata      Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 *
	 * @return array
	 */
	public function invalidate_missing_cache_on_attachment_metadata_update( $metadata, $attachment_id ) {
		unset( $attachment_id );
		self::invalidate_missing_thumbnails_cache();

		return $metadata;
	}

	/**
	 * Invalidate missing-thumbnail cache when attachments are added/deleted.
	 *
	 * @since 3.1.7
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public function invalidate_missing_cache_for_attachment_change( $attachment_id ) {
		unset( $attachment_id );
		self::invalidate_missing_thumbnails_cache();
	}

	/**
	 * Invalidate missing-thumbnail cache.
	 *
	 * @since 3.1.7
	 */
	public static function invalidate_missing_thumbnails_cache() {
		delete_transient( self::MISSING_CACHE_KEY );
	}

	/**
	 * Check to see if the current user is allowed to use this endpoint.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return bool Whether the current user has permission to regenerate thumbnails.
	 */
	public function permissions_check( $request ) {
		return current_user_can( RegenerateThumbnails()->capability );
	}

	/**
	 * Returns whether a variable is an array or not. This is needed because 3 arguments are
	 * passed to validation callbacks but is_array() only accepts one argument.
	 *
	 * @since 3.0.0
	 *
	 * @see   https://core.trac.wordpress.org/ticket/34659
	 *
	 * @param mixed           $param   The parameter value to validate.
	 * @param WP_REST_Request $request The REST request.
	 * @param string          $key     The parameter name.
	 *
	 * @return bool Whether the parameter is an array or not.
	 */
	public function is_array( $param, $request, $key ) {
		return is_array( $param );
	}
}
