<?php
/**
 * Plugin Name: Pera WebP Tools
 * Description: WebP conversion tools for the Media Library, including conversion actions and a review screen.
 * Version: 1.0.0
 * Author: Pera
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Pera_WebP_Tools' ) ) {
	class Pera_WebP_Tools {
		const NOTICE_TRANSIENT_PREFIX = 'pera_webp_notice_';
		const LAST_RESULT_META_KEY    = '_pera_webp_last_result';
		const LAST_ERROR_META_KEY     = '_pera_webp_last_error';
		const LAST_RESULT_CONVERTED   = 'converted';
		const STATS_CACHE_KEY         = 'pera_webp_tools_stats_v1';
		const STATS_CACHE_TTL         = 60;
		const DEFAULT_BATCH_SIZE      = 30;
		const ACTION_CONVERT_SELECTED = 'pera_webp_tools_convert_selected';
		const ACTION_CONVERT_MISSING  = 'pera_webp_tools_convert_missing_batch';
		protected static $stats_runtime_cache = null;

		public static function init() {
			add_filter( 'wp_get_attachment_image_attributes', array( __CLASS__, 'maybe_swap_attachment_image_attrs_to_webp' ), 10, 3 );
			add_filter( 'media_row_actions', array( __CLASS__, 'add_media_row_actions' ), 10, 2 );
			add_action( 'admin_init', array( __CLASS__, 'handle_single_conversion_request' ) );
			add_action( 'admin_init', array( __CLASS__, 'handle_single_delete_request' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );

			add_filter( 'bulk_actions-upload', array( __CLASS__, 'register_media_bulk_action' ) );
			add_filter( 'handle_bulk_actions-upload', array( __CLASS__, 'handle_media_bulk_action' ), 10, 3 );

			add_action( 'admin_menu', array( __CLASS__, 'register_webp_tools_page' ) );
			add_action( 'admin_post_pera_webp_tools_action', array( __CLASS__, 'handle_tools_page_actions' ) );
			add_action( 'admin_notices', array( __CLASS__, 'render_admin_notices' ) );
		}

		public static function maybe_swap_attachment_image_attrs_to_webp( $attr, $attachment, $size ) {
			if ( ! is_array( $attr ) ) {
				return $attr;
			}

			if ( ! empty( $attr['src'] ) ) {
				$attr['src'] = self::maybe_swap_single_url_to_webp( (string) $attr['src'] );
			}

			if ( empty( $attr['srcset'] ) || ! is_string( $attr['srcset'] ) ) {
				return $attr;
			}

			$srcset_items    = array_map( 'trim', explode( ',', $attr['srcset'] ) );
			$rewritten_items = array();

			foreach ( $srcset_items as $item ) {
				if ( '' === $item ) {
					continue;
				}

				$parts      = preg_split( '/\s+/', $item, 2 );
				$url        = isset( $parts[0] ) ? trim( $parts[0] ) : '';
				$descriptor = isset( $parts[1] ) ? trim( $parts[1] ) : '';

				if ( '' === $url ) {
					continue;
				}

				$rewritten_url = self::maybe_swap_single_url_to_webp( $url );
				$rewritten_items[] = '' !== $descriptor ? ( $rewritten_url . ' ' . $descriptor ) : $rewritten_url;
			}

			if ( ! empty( $rewritten_items ) ) {
				$attr['srcset'] = implode( ', ', $rewritten_items );
			}

			return $attr;
		}

		protected static function maybe_swap_single_url_to_webp( $url ) {
			if ( '' === $url ) {
				return $url;
			}

			if ( ! preg_match( '/\.(jpe?g|png)(\?.*)?$/i', $url ) ) {
				return $url;
			}

			$uploads = wp_upload_dir();
			if ( empty( $uploads['baseurl'] ) || empty( $uploads['basedir'] ) ) {
				return $url;
			}

			$webp_url  = preg_replace( '/\.(jpe?g|png)(\?.*)?$/i', '.webp$2', $url );
			$webp_path = str_replace( $uploads['baseurl'], $uploads['basedir'], (string) $webp_url );

			if ( $webp_path && file_exists( $webp_path ) ) {
				return (string) $webp_url;
			}

			return $url;
		}

		public static function can_encode() {
			return function_exists( 'wp_get_image_editor' );
		}

		public static function get_webp_environment_warning() {
			if ( ! function_exists( 'wp_image_editor_supports' ) ) {
				return 'WordPress image editor support checks are unavailable. WebP conversion may fail on this server.';
			}

			if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
				return 'This server does not report WebP encoding support in the active WordPress image editor stack (Imagick/GD). Conversions will likely fail until WebP support is enabled.';
			}

			return '';
		}

		public static function get_environment_info() {
			$webp_encoding_supported = function_exists( 'wp_image_editor_supports' ) ? wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) : false;
			$editor_stack            = 'Unknown';
			$cwebp_path              = self::pera_webp_find_cwebp_binary();

			if ( class_exists( 'WP_Image_Editor_Imagick' ) && class_exists( 'Imagick' ) ) {
				$editor_stack = 'Imagick';
			} elseif ( class_exists( 'WP_Image_Editor_GD' ) && function_exists( 'gd_info' ) ) {
				$editor_stack = 'GD';
			}

			return array(
				'webp_encoding_supported' => (bool) $webp_encoding_supported,
				'editor_stack'            => $editor_stack,
				'batch_size'              => self::DEFAULT_BATCH_SIZE,
				'cwebp_available'         => '' !== $cwebp_path,
				'cwebp_path'              => $cwebp_path,
				'conversion_engine'       => 'cwebp preferred, WP editor fallback',
			);
		}

		public static function pera_webp_find_cwebp_binary() {
			$candidate_paths = array(
				'/usr/bin/cwebp',
				'/usr/local/bin/cwebp',
				'/opt/cpanel/ea-webp/bin/cwebp',
			);

			foreach ( $candidate_paths as $candidate_path ) {
				if ( file_exists( $candidate_path ) && is_executable( $candidate_path ) ) {
					return $candidate_path;
				}
			}

			$command_result = array();
			$exit_code      = 1;
			@exec( 'command -v cwebp 2>/dev/null', $command_result, $exit_code ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( 0 === $exit_code && ! empty( $command_result[0] ) ) {
				$binary_path = trim( (string) $command_result[0] );
				if ( '' !== $binary_path && file_exists( $binary_path ) && is_executable( $binary_path ) ) {
					return $binary_path;
				}
			}

			return '';
		}

		public static function pera_webp_convert_with_cwebp( $source_path, $webp_path, $quality = 82 ) {
			if ( ! $source_path || ! file_exists( $source_path ) ) {
				return array( 'ok' => false, 'error' => 'Source file missing on disk.' );
			}

			$ext = strtolower( pathinfo( $source_path, PATHINFO_EXTENSION ) );
			if ( 'svg' === $ext ) {
				return array( 'ok' => false, 'error' => 'SVG conversion is not supported.' );
			}

			if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
				return array( 'ok' => false, 'error' => 'Only JPG/JPEG/PNG are supported by cwebp conversion.' );
			}

			$cwebp_path = self::pera_webp_find_cwebp_binary();
			if ( '' === $cwebp_path ) {
				return array( 'ok' => false, 'error' => 'cwebp binary is not available.' );
			}

			$quality      = max( 1, min( 100, (int) $quality ) );
			$command      = escapeshellarg( $cwebp_path ) . ' -quiet -q ' . $quality . ' ' . escapeshellarg( $source_path ) . ' -o ' . escapeshellarg( $webp_path ) . ' 2>&1';
			$cmd_output   = array();
			$command_code = 1;
			exec( $command, $cmd_output, $command_code );

			if ( 0 === $command_code && file_exists( $webp_path ) ) {
				return array( 'ok' => true );
			}

			$error = ! empty( $cmd_output ) ? trim( implode( "\n", $cmd_output ) ) : 'cwebp conversion failed.';
			return array(
				'ok'    => false,
				'error' => $error,
			);
		}

		public static function convert_file( $filepath, $quality = 82 ) {
			$ext = strtolower( pathinfo( $filepath, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
				return false;
			}

			$webp_path = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $filepath );
			if ( ! $webp_path ) {
				return false;
			}

			if ( file_exists( $webp_path ) ) {
				return true;
			}

			$editor = wp_get_image_editor( $filepath );
			if ( is_wp_error( $editor ) ) {
				return false;
			}

			if ( method_exists( $editor, 'set_quality' ) ) {
				$editor->set_quality( $quality );
			}

			$saved = $editor->save( $webp_path, 'image/webp' );
			if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
				return false;
			}

			return file_exists( $webp_path );
		}

		public static function convert_attachment( $attachment_id, $include_sizes = true, $quality = 82 ) {
			$log = array(
				'ok'         => false,
				'message'    => '',
				'details'    => array(),
				'error_type' => '',
			);

			$file = get_attached_file( $attachment_id );
			if ( ! $file || ! file_exists( $file ) ) {
				self::maybe_invalidate_stats_cache();
				$log['message']    = 'Attachment file missing on disk.';
				$log['error_type'] = 'missing_file';
				update_post_meta( $attachment_id, self::LAST_RESULT_META_KEY, 'error' );
				update_post_meta( $attachment_id, self::LAST_ERROR_META_KEY, $log['message'] );
				return $log;
			}

			$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
				self::maybe_invalidate_stats_cache();
				$log['message']    = 'Only JPG/PNG can be converted.';
				$log['error_type'] = 'skipped';
				update_post_meta( $attachment_id, self::LAST_RESULT_META_KEY, 'skipped' );
				update_post_meta( $attachment_id, self::LAST_ERROR_META_KEY, $log['message'] );
				return $log;
			}

			$cwebp_path = self::pera_webp_find_cwebp_binary();
			$ok_main    = self::convert_single_image_with_fallback( $file, $quality, $cwebp_path, 'original', $log );

			if ( $include_sizes ) {
				$meta = wp_get_attachment_metadata( $attachment_id );
				if ( is_array( $meta ) && ! empty( $meta['sizes'] ) ) {
					$base_dir = trailingslashit( pathinfo( $file, PATHINFO_DIRNAME ) );
					foreach ( $meta['sizes'] as $size_key => $info ) {
						if ( empty( $info['file'] ) ) {
							continue;
						}
						$size_file = $base_dir . $info['file'];
						if ( file_exists( $size_file ) ) {
							self::convert_single_image_with_fallback( $size_file, $quality, $cwebp_path, "size: {$size_key}", $log );
						}
					}
				}
			}

			$log['ok']      = (bool) $ok_main;
			$log['message'] = implode( ' ', $log['details'] );

			if ( $log['ok'] ) {
				self::maybe_invalidate_stats_cache();
				update_post_meta( $attachment_id, self::LAST_RESULT_META_KEY, self::LAST_RESULT_CONVERTED );
				delete_post_meta( $attachment_id, self::LAST_ERROR_META_KEY );
			} else {
				self::maybe_invalidate_stats_cache();
				update_post_meta( $attachment_id, self::LAST_RESULT_META_KEY, 'error' );
				update_post_meta( $attachment_id, self::LAST_ERROR_META_KEY, $log['message'] );
			}

			return $log;
		}

		protected static function convert_single_image_with_fallback( $source_path, $quality, $cwebp_path, $label, &$log ) {
			$webp_path = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $source_path );
			if ( ! $webp_path ) {
				$log['details'][] = "Skip {$label}: could not derive WebP path.";
				return false;
			}

			if ( file_exists( $webp_path ) ) {
				$log['details'][] = "OK {$label}: WebP already exists.";
				return true;
			}

			if ( '' !== $cwebp_path ) {
				$cwebp_result = self::pera_webp_convert_with_cwebp( $source_path, $webp_path, $quality );
				if ( ! empty( $cwebp_result['ok'] ) ) {
					$log['details'][] = "Converted {$label} with cwebp.";
					return true;
				}
				$log['details'][] = "cwebp failed for {$label}, using WP editor fallback.";
			}

			$wp_ok = self::convert_file( $source_path, $quality );
			$log['details'][] = $wp_ok ? "Converted {$label} with WP editor." : "Could not convert {$label} with WP editor.";
			return $wp_ok;
		}

		public static function get_attachment_status( $attachment_id ) {
			$status_context = self::get_attachment_status_context( $attachment_id );
			return $status_context['status'];
		}

		public static function get_attachment_status_context( $attachment_id ) {
			$file = get_attached_file( $attachment_id );
			if ( ! $file || ! file_exists( $file ) ) {
				return array(
					'status' => 'broken',
					'detail' => 'Attachment file missing on disk.',
				);
			}

			$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
				return array(
					'status' => 'skipped',
					'detail' => 'Not a convertible JPG/PNG attachment.',
				);
			}

			$last_result = self::get_last_result_meta( $attachment_id );

			$original_webp_exists = file_exists( preg_replace( '/\.(jpe?g|png)$/i', '.webp', $file ) );
			if ( ! $original_webp_exists ) {
				$meta = wp_get_attachment_metadata( $attachment_id );
				if ( is_array( $meta ) && ! empty( $meta['sizes'] ) ) {
					$base_dir               = trailingslashit( pathinfo( $file, PATHINFO_DIRNAME ) );
					$has_sizes              = false;
					$has_existing_size_webp = false;

					foreach ( $meta['sizes'] as $info ) {
						if ( empty( $info['file'] ) ) {
							continue;
						}
						$has_sizes = true;
						$size_file = $base_dir . $info['file'];
						if ( file_exists( $size_file ) && file_exists( preg_replace( '/\.(jpe?g|png)$/i', '.webp', $size_file ) ) ) {
							$has_existing_size_webp = true;
						}
					}

					if ( $has_sizes && $has_existing_size_webp ) {
						return array(
							'status' => 'missing_original_only',
							'detail' => 'Original WebP missing but generated sizes exist.',
						);
					}
				}

				$status = ( 'error' === $last_result ) ? 'error' : ( 'skipped' === $last_result ? 'skipped' : 'missing' );
				return array(
					'status' => $status,
					'detail' => 'Original file WebP is missing.',
				);
			}

			$meta = wp_get_attachment_metadata( $attachment_id );
			if ( is_array( $meta ) && ! empty( $meta['sizes'] ) ) {
				$base_dir = trailingslashit( pathinfo( $file, PATHINFO_DIRNAME ) );
				$missing_sizes = array();
				foreach ( $meta['sizes'] as $info ) {
					if ( empty( $info['file'] ) ) {
						continue;
					}
					$size_file = $base_dir . $info['file'];
					if ( file_exists( $size_file ) && ! file_exists( preg_replace( '/\.(jpe?g|png)$/i', '.webp', $size_file ) ) ) {
						$missing_sizes[] = wp_basename( $size_file );
					}
				}
				if ( ! empty( $missing_sizes ) ) {
					$status = ( 'error' === $last_result ) ? 'error' : ( 'skipped' === $last_result ? 'skipped' : 'missing' );
					return array(
						'status' => $status,
						'detail' => sprintf( 'One or more generated size WebP files are missing (%d).', count( $missing_sizes ) ),
					);
				}
			}

			return array(
				'status' => 'converted',
				'detail' => '',
			);
		}

		public static function has_recorded_error( $attachment_id ) {
			$last_result = self::get_last_result_meta( $attachment_id );
			return in_array( $last_result, array( 'error', 'skipped' ), true );
		}

		public static function normalize_last_result_meta( $attachment_id ) {
			$last_result = get_post_meta( $attachment_id, self::LAST_RESULT_META_KEY, true );
			if ( 'ok' === $last_result ) {
				update_post_meta( $attachment_id, self::LAST_RESULT_META_KEY, self::LAST_RESULT_CONVERTED );
			}
		}

		public static function get_last_result_meta( $attachment_id ) {
			self::normalize_last_result_meta( $attachment_id );
			return get_post_meta( $attachment_id, self::LAST_RESULT_META_KEY, true );
		}

		public static function add_media_row_actions( $actions, $post ) {
			if ( ! current_user_can( 'upload_files' ) || empty( $post->ID ) ) {
				return $actions;
			}

			$mime = get_post_mime_type( $post->ID );
			if ( ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) {
				return $actions;
			}

			$base_url = admin_url( 'upload.php' );
			$nonce    = wp_create_nonce( 'pera_webp_convert_' . $post->ID );

			$url1 = add_query_arg(
				array(
					'pera_webp_convert' => 1,
					'id'                => $post->ID,
					'sizes'             => 0,
					'_wpnonce'          => $nonce,
				),
				$base_url
			);

			$url2 = add_query_arg(
				array(
					'pera_webp_convert' => 1,
					'id'                => $post->ID,
					'sizes'             => 1,
					'_wpnonce'          => $nonce,
				),
				$base_url
			);

			$actions['pera_webp_convert']       = '<a href="' . esc_url( $url1 ) . '">Convert to WebP</a>';
			$actions['pera_webp_convert_sizes'] = '<a href="' . esc_url( $url2 ) . '">Convert to WebP (incl. sizes)</a>';

			return $actions;
		}

		public static function handle_single_conversion_request() {
			if ( ! is_admin() || ! isset( $_GET['pera_webp_convert'], $_GET['id'] ) ) {
				return;
			}

			if ( ! current_user_can( 'upload_files' ) ) {
				wp_die( 'You do not have permission to convert images.' );
			}

			$attachment_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
			if ( $attachment_id <= 0 ) {
				wp_die( 'Invalid attachment ID.' );
			}

			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'pera_webp_convert_' . $attachment_id ) ) {
				wp_die( 'Nonce check failed.' );
			}

			$sizes  = ! empty( $_GET['sizes'] );
			$result = self::convert_attachment( $attachment_id, $sizes, 82 );

			self::set_notice(
				! empty( $result['ok'] ) ? 'success' : 'error',
				$result['message']
			);

			$redirect = remove_query_arg( array( 'pera_webp_convert', 'id', 'sizes', '_wpnonce' ) );
			wp_safe_redirect( $redirect );
			exit;
		}

		public static function handle_single_delete_request() {
			if (
				! is_admin() ||
				! isset( $_GET['pera_webp_delete'], $_GET['id'] ) ||
				! isset( $_GET['page'] ) ||
				$_GET['page'] !== 'pera-webp-tools'
			) {
				return;
			}

			if ( ! current_user_can( 'delete_posts' ) ) {
				wp_die( 'You do not have permission to delete attachments.' );
			}

			$attachment_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
			if ( $attachment_id <= 0 ) {
				wp_die( 'Invalid attachment ID.' );
			}

			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'pera_webp_delete_' . $attachment_id ) ) {
				wp_die( 'Nonce check failed.' );
			}

			$mime = get_post_mime_type( $attachment_id );
			if ( ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) {
				self::set_notice( 'error', 'Only image attachments shown in WebP Tools can be deleted from this action.' );
			} else {
				$deleted = wp_delete_attachment( $attachment_id, true );
				if ( $deleted ) {
					do_action( 'pera_webp_tools_attachment_deleted', $attachment_id );
					self::maybe_invalidate_stats_cache();
					self::set_notice( 'success', 'Attachment deleted.' );
				} else {
					self::set_notice( 'error', 'Could not delete attachment.' );
				}
			}

			$status   = isset( $_GET['webp_status'] ) ? sanitize_key( wp_unslash( $_GET['webp_status'] ) ) : 'all';
			$redirect = add_query_arg(
				array(
					'page'        => 'pera-webp-tools',
					'webp_status' => $status,
				),
				admin_url( 'upload.php' )
			);
			wp_safe_redirect( $redirect );
			exit;
		}

		public static function register_media_bulk_action( $bulk_actions ) {
			if ( current_user_can( 'upload_files' ) ) {
				$bulk_actions['pera_webp_bulk_sizes'] = 'Convert to WebP (incl. sizes)';
			}
			return $bulk_actions;
		}

		public static function handle_media_bulk_action( $redirect_url, $action, $post_ids ) {
			if ( 'pera_webp_bulk_sizes' !== $action || ! current_user_can( 'upload_files' ) ) {
				return $redirect_url;
			}

			$ok = 0;
			$fail = 0;

			foreach ( $post_ids as $id ) {
				$mime = get_post_mime_type( $id );
				if ( ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) {
					continue;
				}
				$r = self::convert_attachment( (int) $id, true, 82 );
				if ( ! empty( $r['ok'] ) ) {
					$ok++;
				} else {
					$fail++;
				}
			}

			return add_query_arg(
				array(
					'pera_webp_bulk_done' => $ok,
					'pera_webp_bulk_fail' => $fail,
				),
				$redirect_url
			);
		}

		public static function register_webp_tools_page() {
			add_submenu_page(
				'upload.php',
				'WebP Tools',
				'WebP Tools',
				'upload_files',
				'pera-webp-tools',
				array( __CLASS__, 'render_webp_tools_page' )
			);
		}

		public static function render_webp_tools_page() {
			if ( ! current_user_can( 'upload_files' ) ) {
				wp_die( 'You do not have permission to access this page.' );
			}

			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
			require_once __DIR__ . '/includes/class-pera-webp-tools-list-table.php';

			$status = isset( $_GET['webp_status'] ) ? sanitize_key( wp_unslash( $_GET['webp_status'] ) ) : 'all';
			$stats  = self::calculate_stats();
			$webp_environment_warning = self::get_webp_environment_warning();
			$env_info                = self::get_environment_info();

			$table = new Pera_WebP_Tools_List_Table( array(
				'status_filter' => $status,
			) );
			$table->prepare_items();
			?>
			<div class="wrap">
				<h1 class="wp-heading-inline">WebP Tools</h1>
				<hr class="wp-header-end">

				<ul class="subsubsub">
					<?php
					$links = array(
						'all'       => 'All',
						'converted' => 'Converted',
						'missing'   => 'Missing WebP',
						'missing_original_only' => 'Missing Original Only',
						'broken'    => 'Broken (Missing File)',
						'error'     => 'Errors',
						'skipped'   => 'Skipped',
					);
					$total = count( $links );
					$index = 0;
					foreach ( $links as $key => $label ) {
						$index++;
						$class = ( $status === $key ) ? 'current' : '';
						$url   = add_query_arg( array( 'page' => 'pera-webp-tools', 'webp_status' => $key ), admin_url( 'upload.php' ) );
						$count = isset( $stats[ $key ] ) ? (int) $stats[ $key ] : 0;
						echo '<li><a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . ' <span class="count">(' . esc_html( (string) $count ) . ')</span></a>';
						if ( $index < $total ) {
							echo ' | ';
						}
						echo '</li>';
					}
					?>
				</ul>

				<p>
					<strong>Total scanned:</strong> <?php echo esc_html( (string) $stats['all'] ); ?> &nbsp;|&nbsp;
					<strong>Converted:</strong> <?php echo esc_html( (string) $stats['converted'] ); ?> &nbsp;|&nbsp;
					<strong>Missing WebP:</strong> <?php echo esc_html( (string) $stats['missing'] ); ?> &nbsp;|&nbsp;
					<strong>Missing Original Only:</strong> <?php echo esc_html( (string) $stats['missing_original_only'] ); ?> &nbsp;|&nbsp;
					<strong>Broken:</strong> <?php echo esc_html( (string) $stats['broken'] ); ?> &nbsp;|&nbsp;
					<strong>Errors:</strong> <?php echo esc_html( (string) $stats['error'] ); ?> &nbsp;|&nbsp;
					<strong>Skipped:</strong> <?php echo esc_html( (string) $stats['skipped'] ); ?>
				</p>
				<?php if ( ! empty( $stats['broken'] ) ) : ?>
					<div class="notice notice-error inline">
						<p>
							<?php
							echo esc_html( sprintf( '%d broken attachments detected (missing source files). Review now.', (int) $stats['broken'] ) );
							echo ' ';
							$broken_url = add_query_arg(
								array(
									'page'        => 'pera-webp-tools',
									'webp_status' => 'broken',
								),
								admin_url( 'upload.php' )
							);
							?>
							<a href="<?php echo esc_url( $broken_url ); ?>">Review now</a>
						</p>
					</div>
				<?php endif; ?>

				<div class="notice notice-info inline">
					<p>
						<strong>Environment:</strong>
						WebP encoding support: <?php echo esc_html( $env_info['webp_encoding_supported'] ? 'Yes' : 'No' ); ?> &nbsp;|&nbsp;
						Editor stack: <?php echo esc_html( $env_info['editor_stack'] ); ?> &nbsp;|&nbsp;
						cwebp available: <?php echo esc_html( $env_info['cwebp_available'] ? 'Yes' : 'No' ); ?> &nbsp;|&nbsp;
						cwebp path: <?php echo esc_html( ! empty( $env_info['cwebp_path'] ) ? $env_info['cwebp_path'] : 'N/A' ); ?> &nbsp;|&nbsp;
						Conversion engine: <?php echo esc_html( $env_info['conversion_engine'] ); ?> &nbsp;|&nbsp;
						Batch size: <?php echo esc_html( (string) $env_info['batch_size'] ); ?>
					</p>
				</div>

				<?php if ( ! empty( $webp_environment_warning ) ) : ?>
					<div class="notice notice-warning inline">
						<p><strong>WebP environment warning:</strong> <?php echo esc_html( $webp_environment_warning ); ?></p>
					</div>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:1em 0;">
					<input type="hidden" name="action" value="pera_webp_tools_action" />
					<input type="hidden" name="webp_tools_action" value="convert_all_missing" />
					<?php wp_nonce_field( self::ACTION_CONVERT_MISSING, 'pera_webp_tools_nonce' ); ?>
					<?php submit_button( 'Convert Next Missing Batch (up to ' . (int) self::DEFAULT_BATCH_SIZE . ')', 'primary', 'submit', false ); ?>
				</form>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="pera_webp_tools_action" />
					<input type="hidden" name="webp_tools_action" value="bulk_convert" />
					<input type="hidden" name="redirect_status" value="<?php echo esc_attr( $status ); ?>" />
					<?php wp_nonce_field( self::ACTION_CONVERT_SELECTED, 'pera_webp_tools_nonce' ); ?>
					<?php $dry_checked = ! empty( $_POST['dry_run_delete'] ) ? 'checked' : ''; ?>
					<p>
						<label>
							<input type="checkbox" name="dry_run_delete" value="1" <?php echo esc_attr( $dry_checked ); ?> />
							Dry run (preview only, no deletion)
						</label>
					</p>
					<?php submit_button( 'Convert Selected', 'secondary', 'submit-top', false ); ?>
					<?php if ( current_user_can( 'delete_posts' ) ) : ?>
						<?php submit_button( 'Delete Selected', 'secondary delete', 'submit-delete-top', false, array( 'onclick' => "return confirm('Delete selected attachments permanently?');" ) ); ?>
					<?php endif; ?>
					<?php $table->display(); ?>
					<?php submit_button( 'Convert Selected', 'secondary', 'submit', false ); ?>
					<?php if ( current_user_can( 'delete_posts' ) ) : ?>
						<?php submit_button( 'Delete Selected', 'secondary delete', 'submit-delete', false, array( 'onclick' => "return confirm('Delete selected attachments permanently?');" ) ); ?>
					<?php endif; ?>
				</form>
			</div>
			<?php
		}

		public static function handle_tools_page_actions() {
			if ( ! current_user_can( 'upload_files' ) ) {
				wp_die( 'You do not have permission to convert images.' );
			}

			$action = isset( $_POST['webp_tools_action'] ) ? sanitize_key( wp_unslash( $_POST['webp_tools_action'] ) ) : '';
			if ( isset( $_POST['submit-delete-top'] ) || isset( $_POST['submit-delete'] ) || 'bulk_convert' === $action ) {
				check_admin_referer( self::ACTION_CONVERT_SELECTED, 'pera_webp_tools_nonce' );
			} elseif ( 'convert_all_missing' === $action ) {
				check_admin_referer( self::ACTION_CONVERT_MISSING, 'pera_webp_tools_nonce' );
			} else {
				wp_die( 'Invalid tools action.' );
			}

			if ( isset( $_POST['submit-delete-top'] ) || isset( $_POST['submit-delete'] ) ) {
				$action = 'bulk_delete';
			}
			$status = isset( $_POST['redirect_status'] ) ? sanitize_key( wp_unslash( $_POST['redirect_status'] ) ) : 'all';
			$redirect = add_query_arg(
				array(
					'page'        => 'pera-webp-tools',
					'webp_status' => $status,
				),
				admin_url( 'upload.php' )
			);

			if ( 'bulk_convert' === $action ) {
				$raw_ids = isset( $_POST['attachments'] ) ? (array) wp_unslash( $_POST['attachments'] ) : array();
				$ids     = array_filter( array_map( 'absint', $raw_ids ) );
				if ( empty( $ids ) ) {
					self::set_notice( 'error', 'No attachments selected for conversion.' );
					wp_safe_redirect( $redirect );
					exit;
				}

				$ok = 0;
				$fail = 0;
				foreach ( $ids as $id ) {
					$result = self::convert_attachment( (int) $id, true, 82 );
					if ( ! empty( $result['ok'] ) ) {
						$ok++;
					} else {
						$fail++;
					}
				}
				$redirect = add_query_arg(
					array(
						'converted' => $ok,
						'failed'    => $fail,
					),
					$redirect
				);
				self::set_notice( 'info', sprintf( 'Bulk conversion completed. Converted: %d. Failed/Skipped: %d.', $ok, $fail ) );
			} elseif ( 'bulk_delete' === $action ) {
				if ( ! current_user_can( 'delete_posts' ) ) {
					wp_die( 'You do not have permission to delete attachments.' );
				}

				$raw_ids = isset( $_POST['attachments'] ) ? (array) wp_unslash( $_POST['attachments'] ) : array();
				$ids     = array_filter( array_map( 'absint', $raw_ids ) );
				$dry_run = ! empty( $_POST['dry_run_delete'] );
				if ( empty( $ids ) ) {
					self::set_notice( 'error', 'No attachments selected for deletion.' );
					wp_safe_redirect( $redirect );
					exit;
				}

				$ok = 0;
				$fail = 0;
				$eligible = 0;
				$total_size = 0;
				foreach ( $ids as $id ) {
					$attachment_id = (int) $id;
					$post          = get_post( $attachment_id );
					if ( ! $post || 'attachment' !== $post->post_type ) {
						$fail++;
						continue;
					}
					$mime          = get_post_mime_type( $attachment_id );
					if ( ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) {
						$fail++;
						continue;
					}
					$eligible++;

					$file = get_attached_file( $attachment_id );
					if ( $file && file_exists( $file ) ) {
						$total_size += filesize( $file );
						$meta = wp_get_attachment_metadata( $attachment_id );
						if ( is_array( $meta ) && ! empty( $meta['sizes'] ) ) {
							$base_dir = trailingslashit( pathinfo( $file, PATHINFO_DIRNAME ) );
							foreach ( $meta['sizes'] as $info ) {
								if ( empty( $info['file'] ) ) {
									continue;
								}
								$size_file = $base_dir . $info['file'];
								if ( file_exists( $size_file ) ) {
									$total_size += filesize( $size_file );
								}
							}
						}
					}

					if ( $dry_run ) {
						continue;
					}

					$deleted = wp_delete_attachment( $attachment_id, true );
					if ( $deleted ) {
						$ok++;
						do_action( 'pera_webp_tools_attachment_deleted', $attachment_id );
					} else {
						$fail++;
					}
				}

				if ( $dry_run ) {
					self::set_notice(
						'info',
						sprintf(
							'Dry run: %d attachments would be deleted. Estimated space freed: %s.',
							$eligible,
							size_format( $total_size )
						)
					);
				} else {
					self::maybe_invalidate_stats_cache();
					self::set_notice( 'info', sprintf( 'Deleted: %d attachments. Skipped/Failed: %d.', $ok, $fail ) );
				}
			} elseif ( 'convert_all_missing' === $action ) {
				$processed = self::convert_missing_batch( self::DEFAULT_BATCH_SIZE );
				$message = sprintf(
					'Batch processed %d missing attachment(s). Converted: %d. Failed/Skipped: %d. Remaining missing: %d.',
					$processed['processed'],
					$processed['ok'],
					$processed['fail'],
					$processed['remaining']
				);
				self::set_notice( 'info', $message );
			}

			wp_safe_redirect( $redirect );
			exit;
		}

		public static function convert_missing_batch( $batch_size = 30 ) {
			$ids = self::get_filtered_attachment_ids( 'missing', $batch_size );
			$ok = 0;
			$fail = 0;

			foreach ( $ids as $id ) {
				$result = self::convert_attachment( (int) $id, true, 82 );
				if ( ! empty( $result['ok'] ) ) {
					$ok++;
				} else {
					$fail++;
				}
			}

			$remaining = self::calculate_stats();

			return array(
				'processed' => count( $ids ),
				'ok'        => $ok,
				'fail'      => $fail,
				'remaining' => isset( $remaining['missing'] ) ? (int) $remaining['missing'] : 0,
			);
		}

		public static function set_notice( $type, $message ) {
			set_transient(
				self::NOTICE_TRANSIENT_PREFIX . get_current_user_id(),
				array(
					'type' => $type,
					'msg'  => $message,
				),
				90
			);
		}

		public static function render_admin_notices() {
			$data = get_transient( self::NOTICE_TRANSIENT_PREFIX . get_current_user_id() );

			$converted = isset( $_GET['converted'] ) ? absint( wp_unslash( $_GET['converted'] ) ) : null;
			$failed    = isset( $_GET['failed'] ) ? absint( wp_unslash( $_GET['failed'] ) ) : null;
			if ( null !== $converted || null !== $failed ) {
				echo '<div class="notice notice-info"><p>' .
					esc_html( sprintf( 'Conversion results. Converted: %d. Failed/Skipped: %d.', (int) $converted, (int) $failed ) ) .
					'</p></div>' ;
			}
			if ( ! $data ) {
				if ( isset( $_GET['pera_webp_bulk_done'], $_GET['pera_webp_bulk_fail'] ) ) {
					$ok   = isset( $_GET['pera_webp_bulk_done'] ) ? (int) $_GET['pera_webp_bulk_done'] : 0;
					$fail = isset( $_GET['pera_webp_bulk_fail'] ) ? (int) $_GET['pera_webp_bulk_fail'] : 0;
					echo '<div class="notice notice-info"><p><strong>WebP bulk conversion:</strong> ' .
						esc_html( "Converted: {$ok}. Failed/Skipped: {$fail}." ) .
						'</p></div>';
				}
				return;
			}

			delete_transient( self::NOTICE_TRANSIENT_PREFIX . get_current_user_id() );

			$class = 'notice notice-info';
			if ( 'success' === $data['type'] ) {
				$class = 'notice notice-success';
			} elseif ( 'error' === $data['type'] ) {
				$class = 'notice notice-error';
			}

			echo '<div class="' . esc_attr( $class ) . '"><p><strong>WebP:</strong> ' . esc_html( $data['msg'] ) . '</p></div>';
		}

		public static function calculate_stats() {
			if ( is_array( self::$stats_runtime_cache ) ) {
				return self::$stats_runtime_cache;
			}

			$cached = get_transient( self::STATS_CACHE_KEY );
			if ( is_array( $cached ) ) {
				self::$stats_runtime_cache = $cached;
				return $cached;
			}

			$stats = array(
				'all'       => 0,
				'converted' => 0,
				'missing'   => 0,
				'missing_original_only' => 0,
				'broken'    => 0,
				'error'     => 0,
				'skipped'   => 0,
			);

			$ids = self::get_filtered_attachment_ids( 'all', 0 );
			foreach ( $ids as $id ) {
				$status = self::get_attachment_status( (int) $id );
				$stats['all']++;
				if ( isset( $stats[ $status ] ) ) {
					$stats[ $status ]++;
				} else {
					$stats['error']++;
				}
			}

			set_transient( self::STATS_CACHE_KEY, $stats, self::STATS_CACHE_TTL );
			self::$stats_runtime_cache = $stats;

			return $stats;
		}

		public static function invalidate_stats_cache() {
			self::$stats_runtime_cache = null;
			delete_transient( self::STATS_CACHE_KEY );
		}

		public static function maybe_invalidate_stats_cache() {
			$last = get_transient( 'pera_webp_last_invalidate' );
			if ( ! $last || ( time() - $last ) > 10 ) {
				self::invalidate_stats_cache();
				set_transient( 'pera_webp_last_invalidate', time(), 30 );
			}
		}

		public static function enqueue_admin_assets() {
			$screen = get_current_screen();
			if ( ! $screen || $screen->id !== 'upload_page_pera-webp-tools' ) {
				return;
			}

			$handle = 'pera-webp-tools-admin';
			wp_register_style( $handle, false, array(), '1.0.0' );
			wp_enqueue_style( $handle );
			wp_add_inline_style(
				$handle,
				'.pera-webp-broken-text { color: #b32d2e; }'
			);
		}

		public static function get_filtered_attachment_ids( $status = 'all', $limit = 0, $offset = 0 ) {
			$args = array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => array( 'image/jpeg', 'image/png' ),
				'posts_per_page' => $limit > 0 ? $limit : -1,
				'offset'         => $offset,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			);
			$ids = get_posts( $args );

			if ( 'all' === $status ) {
				return $ids;
			}

			$filtered = array();
			foreach ( $ids as $id ) {
				$current = self::get_attachment_status( (int) $id );
				if ( $status === $current ) {
					$filtered[] = (int) $id;
				}
			}

			return $filtered;
		}
	}

	Pera_WebP_Tools::init();
}
