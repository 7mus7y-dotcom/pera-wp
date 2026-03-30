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
		protected static $stats_runtime_cache = null;

		public static function init() {
			add_filter( 'wp_get_attachment_image_src', array( __CLASS__, 'maybe_swap_to_webp' ), 10, 3 );
			add_filter( 'media_row_actions', array( __CLASS__, 'add_media_row_actions' ), 10, 2 );
			add_action( 'admin_init', array( __CLASS__, 'handle_single_conversion_request' ) );
			add_action( 'admin_init', array( __CLASS__, 'handle_single_delete_request' ) );

			add_filter( 'bulk_actions-upload', array( __CLASS__, 'register_media_bulk_action' ) );
			add_filter( 'handle_bulk_actions-upload', array( __CLASS__, 'handle_media_bulk_action' ), 10, 3 );

			add_action( 'admin_menu', array( __CLASS__, 'register_webp_tools_page' ) );
			add_action( 'admin_post_pera_webp_tools_action', array( __CLASS__, 'handle_tools_page_actions' ) );
			add_action( 'admin_notices', array( __CLASS__, 'render_admin_notices' ) );
		}

		public static function maybe_swap_to_webp( $image, $attachment_id, $size ) {
			if ( empty( $image[0] ) ) {
				return $image;
			}

			$url = $image[0];
			if ( ! preg_match( '/\.(jpe?g|png)$/i', $url ) ) {
				return $image;
			}

			$uploads = wp_upload_dir();
			if ( empty( $uploads['baseurl'] ) || empty( $uploads['basedir'] ) ) {
				return $image;
			}

			$webp_url  = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $url );
			$webp_path = str_replace( $uploads['baseurl'], $uploads['basedir'], $webp_url );

			if ( $webp_path && file_exists( $webp_path ) ) {
				$image[0] = $webp_url;
			}

			return $image;
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

			if ( class_exists( 'WP_Image_Editor_Imagick' ) && class_exists( 'Imagick' ) ) {
				$editor_stack = 'Imagick';
			} elseif ( class_exists( 'WP_Image_Editor_GD' ) && function_exists( 'gd_info' ) ) {
				$editor_stack = 'GD';
			}

			return array(
				'webp_encoding_supported' => (bool) $webp_encoding_supported,
				'editor_stack'            => $editor_stack,
				'batch_size'              => self::DEFAULT_BATCH_SIZE,
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
				self::invalidate_stats_cache();
				$log['message']    = 'Attachment file missing on disk.';
				$log['error_type'] = 'missing_file';
				update_post_meta( $attachment_id, self::LAST_RESULT_META_KEY, 'error' );
				update_post_meta( $attachment_id, self::LAST_ERROR_META_KEY, $log['message'] );
				return $log;
			}

			$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
				self::invalidate_stats_cache();
				$log['message']    = 'Only JPG/PNG can be converted.';
				$log['error_type'] = 'skipped';
				update_post_meta( $attachment_id, self::LAST_RESULT_META_KEY, 'skipped' );
				update_post_meta( $attachment_id, self::LAST_ERROR_META_KEY, $log['message'] );
				return $log;
			}

			$ok_main          = self::convert_file( $file, $quality );
			$log['details'][] = $ok_main ? 'Converted original.' : 'Could not convert original (no WebP encoder or error).';

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
							$ok              = self::convert_file( $size_file, $quality );
							$log['details'][] = $ok ? "OK size: {$size_key}" : "Skip size: {$size_key}";
						}
					}
				}
			}

			$log['ok']      = (bool) $ok_main;
			$log['message'] = implode( ' ', $log['details'] );

			if ( $log['ok'] ) {
				self::invalidate_stats_cache();
				update_post_meta( $attachment_id, self::LAST_RESULT_META_KEY, self::LAST_RESULT_CONVERTED );
				delete_post_meta( $attachment_id, self::LAST_ERROR_META_KEY );
			} else {
				self::invalidate_stats_cache();
				update_post_meta( $attachment_id, self::LAST_RESULT_META_KEY, 'error' );
				update_post_meta( $attachment_id, self::LAST_ERROR_META_KEY, $log['message'] );
			}

			return $log;
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
					$base_dir             = trailingslashit( pathinfo( $file, PATHINFO_DIRNAME ) );
					$has_generated_sizes  = false;
					$has_existing_size_webp = false;

					foreach ( $meta['sizes'] as $info ) {
						if ( empty( $info['file'] ) ) {
							continue;
						}
						$size_file = $base_dir . $info['file'];
						if ( file_exists( $size_file ) ) {
							$has_generated_sizes = true;
							if ( file_exists( preg_replace( '/\.(jpe?g|png)$/i', '.webp', $size_file ) ) ) {
								$has_existing_size_webp = true;
							}
						}
					}

					if ( $has_generated_sizes && $has_existing_size_webp ) {
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
			if ( ! is_admin() || ! isset( $_GET['pera_webp_delete'], $_GET['id'] ) ) {
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
				self::invalidate_stats_cache();
				if ( $deleted ) {
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

				<div class="notice notice-info inline">
					<p>
						<strong>Environment:</strong>
						WebP encoding support: <?php echo esc_html( $env_info['webp_encoding_supported'] ? 'Yes' : 'No' ); ?> &nbsp;|&nbsp;
						Editor stack: <?php echo esc_html( $env_info['editor_stack'] ); ?> &nbsp;|&nbsp;
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
					<?php wp_nonce_field( 'pera_webp_tools_action' ); ?>
					<?php submit_button( 'Convert Next Missing Batch (up to ' . (int) self::DEFAULT_BATCH_SIZE . ')', 'primary', 'submit', false ); ?>
				</form>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="pera_webp_tools_action" />
					<input type="hidden" name="webp_tools_action" value="bulk_convert" />
					<input type="hidden" name="redirect_status" value="<?php echo esc_attr( $status ); ?>" />
					<?php wp_nonce_field( 'pera_webp_tools_action' ); ?>
					<?php submit_button( 'Convert Selected', 'secondary', 'submit-top', false ); ?>
					<?php if ( current_user_can( 'delete_posts' ) ) : ?>
						<?php submit_button( 'Delete Selected', 'delete', 'submit-delete-top', false, array( 'onclick' => "return confirm('Delete selected attachments permanently?');" ) ); ?>
					<?php endif; ?>
					<?php $table->display(); ?>
					<?php submit_button( 'Convert Selected', 'secondary', 'submit', false ); ?>
					<?php if ( current_user_can( 'delete_posts' ) ) : ?>
						<?php submit_button( 'Delete Selected', 'delete', 'submit-delete', false, array( 'onclick' => "return confirm('Delete selected attachments permanently?');" ) ); ?>
					<?php endif; ?>
				</form>
			</div>
			<?php
		}

		public static function handle_tools_page_actions() {
			if ( ! current_user_can( 'upload_files' ) ) {
				wp_die( 'You do not have permission to convert images.' );
			}

			check_admin_referer( 'pera_webp_tools_action' );

			$action = isset( $_POST['webp_tools_action'] ) ? sanitize_key( wp_unslash( $_POST['webp_tools_action'] ) ) : '';
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
				$ids = isset( $_POST['attachments'] ) ? (array) wp_unslash( $_POST['attachments'] ) : array();
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
				self::set_notice( 'info', sprintf( 'Bulk conversion completed. Converted: %d. Failed/Skipped: %d.', $ok, $fail ) );
			} elseif ( 'bulk_delete' === $action ) {
				if ( ! current_user_can( 'delete_posts' ) ) {
					wp_die( 'You do not have permission to delete attachments.' );
				}

				$ids = isset( $_POST['attachments'] ) ? (array) wp_unslash( $_POST['attachments'] ) : array();
				if ( empty( $ids ) ) {
					self::set_notice( 'error', 'No attachments selected for deletion.' );
					wp_safe_redirect( $redirect );
					exit;
				}

				$ok = 0;
				$fail = 0;
				foreach ( $ids as $id ) {
					$attachment_id = (int) $id;
					$mime          = get_post_mime_type( $attachment_id );
					if ( ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) {
						$fail++;
						continue;
					}

					$deleted = wp_delete_attachment( $attachment_id, true );
					if ( $deleted ) {
						$ok++;
					} else {
						$fail++;
					}
				}

				self::invalidate_stats_cache();
				self::set_notice( 'info', sprintf( 'Deleted: %d. Failed: %d.', $ok, $fail ) );
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
