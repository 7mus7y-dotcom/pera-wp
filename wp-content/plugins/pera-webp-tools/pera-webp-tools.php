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

		public static function init() {
			add_filter( 'wp_get_attachment_image_src', array( __CLASS__, 'maybe_swap_to_webp' ), 10, 3 );
			add_filter( 'media_row_actions', array( __CLASS__, 'add_media_row_actions' ), 10, 2 );
			add_action( 'admin_init', array( __CLASS__, 'handle_single_conversion_request' ) );

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
				$log['message']    = 'Attachment file missing on disk.';
				$log['error_type'] = 'missing_file';
				update_post_meta( $attachment_id, self::LAST_RESULT_META_KEY, 'error' );
				update_post_meta( $attachment_id, self::LAST_ERROR_META_KEY, $log['message'] );
				return $log;
			}

			$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
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
				update_post_meta( $attachment_id, self::LAST_RESULT_META_KEY, self::LAST_RESULT_CONVERTED );
				delete_post_meta( $attachment_id, self::LAST_ERROR_META_KEY );
			} else {
				update_post_meta( $attachment_id, self::LAST_RESULT_META_KEY, 'error' );
				update_post_meta( $attachment_id, self::LAST_ERROR_META_KEY, $log['message'] );
			}

			return $log;
		}

		public static function get_attachment_status( $attachment_id ) {
			$file = get_attached_file( $attachment_id );
			if ( ! $file || ! file_exists( $file ) ) {
				return 'error';
			}

			$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
				return 'skipped';
			}

			if ( ! file_exists( preg_replace( '/\.(jpe?g|png)$/i', '.webp', $file ) ) ) {
				return self::has_recorded_error( $attachment_id ) ? 'error' : 'missing';
			}

			$meta = wp_get_attachment_metadata( $attachment_id );
			if ( is_array( $meta ) && ! empty( $meta['sizes'] ) ) {
				$base_dir = trailingslashit( pathinfo( $file, PATHINFO_DIRNAME ) );
				foreach ( $meta['sizes'] as $info ) {
					if ( empty( $info['file'] ) ) {
						continue;
					}
					$size_file = $base_dir . $info['file'];
					if ( file_exists( $size_file ) && ! file_exists( preg_replace( '/\.(jpe?g|png)$/i', '.webp', $size_file ) ) ) {
						return self::has_recorded_error( $attachment_id ) ? 'error' : 'missing';
					}
				}
			}

			return 'converted';
		}

		public static function has_recorded_error( $attachment_id ) {
			$last_result = get_post_meta( $attachment_id, self::LAST_RESULT_META_KEY, true );
			return in_array( $last_result, array( 'error', 'skipped' ), true );
		}

		public static function normalize_last_result_meta( $attachment_id ) {
			$last_result = get_post_meta( $attachment_id, self::LAST_RESULT_META_KEY, true );
			if ( 'ok' === $last_result ) {
				update_post_meta( $attachment_id, self::LAST_RESULT_META_KEY, self::LAST_RESULT_CONVERTED );
			}
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
						'error'     => 'Errors/Skipped',
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
					<strong>Errors/Skipped:</strong> <?php echo esc_html( (string) $stats['error'] ); ?>
				</p>

				<?php if ( ! empty( $webp_environment_warning ) ) : ?>
					<div class="notice notice-warning inline">
						<p><strong>WebP environment warning:</strong> <?php echo esc_html( $webp_environment_warning ); ?></p>
					</div>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:1em 0;">
					<input type="hidden" name="action" value="pera_webp_tools_action" />
					<input type="hidden" name="webp_tools_action" value="convert_all_missing" />
					<?php wp_nonce_field( 'pera_webp_tools_action' ); ?>
					<?php submit_button( 'Convert Next Missing Batch (up to 30)', 'primary', 'submit', false ); ?>
				</form>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="pera_webp_tools_action" />
					<input type="hidden" name="webp_tools_action" value="bulk_convert" />
					<input type="hidden" name="redirect_status" value="<?php echo esc_attr( $status ); ?>" />
					<?php wp_nonce_field( 'pera_webp_tools_action' ); ?>
					<?php $table->display(); ?>
					<?php submit_button( 'Convert Selected', 'secondary', 'submit', false ); ?>
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
			$status = isset( $_POST['redirect_status'] ) ? sanitize_key( wp_unslash( $_POST['redirect_status'] ) ) : 'all';
			$redirect = add_query_arg(
				array(
					'page'        => 'pera-webp-tools',
					'webp_status' => $status,
				),
				admin_url( 'upload.php' )
			);

			if ( 'convert_single' === $action ) {
				$attachment_id = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;
				if ( $attachment_id > 0 ) {
					$result = self::convert_attachment( $attachment_id, true, 82 );
					self::set_notice( ! empty( $result['ok'] ) ? 'success' : 'error', $result['message'] );
				}
			} elseif ( 'bulk_convert' === $action ) {
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
			} elseif ( 'convert_all_missing' === $action ) {
				$processed = self::convert_missing_batch( 30 );
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
			$stats = array(
				'all'       => 0,
				'converted' => 0,
				'missing'   => 0,
				'error'     => 0,
			);

			$ids = self::get_filtered_attachment_ids( 'all', 0 );
			foreach ( $ids as $id ) {
				$status = self::get_attachment_status( (int) $id );
				$stats['all']++;
				if ( 'converted' === $status ) {
					$stats['converted']++;
				} elseif ( 'missing' === $status ) {
					$stats['missing']++;
				} else {
					$stats['error']++;
				}
			}

			return $stats;
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
				self::normalize_last_result_meta( (int) $id );
				$current = self::get_attachment_status( (int) $id );
				if ( $status === $current || ( 'error' === $status && in_array( $current, array( 'error', 'skipped' ), true ) ) ) {
					$filtered[] = (int) $id;
				}
			}

			return $filtered;
		}
	}

	Pera_WebP_Tools::init();
}
