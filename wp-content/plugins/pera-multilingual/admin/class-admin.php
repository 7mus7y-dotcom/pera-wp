<?php
defined( 'ABSPATH' ) || exit;

final class Pera_ML_Admin {
	private $registry; private $translation_forms = array();
	public function __construct( $registry ) { $this->registry = $registry; }
	public function hooks() { add_action( 'admin_menu', array( $this, 'menu' ) ); add_action( 'admin_init', array( $this, 'settings' ) ); add_action( 'add_meta_boxes', array( $this, 'meta_box' ) ); add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_translation_queue' ) ); add_action( 'admin_footer-post.php', array( $this, 'translation_forms' ) ); add_action( 'admin_footer-post-new.php', array( $this, 'translation_forms' ) ); add_action( 'admin_post_pera_ml_translate_object', array( $this, 'translate_object' ) ); add_action( 'admin_post_pera_ml_translate_ui', array( $this, 'translate_ui' ) ); add_action( 'admin_post_pera_ml_complete_ui', array( $this, 'complete_ui' ) ); add_action( 'admin_post_pera_ml_scan_theme_ui', array( $this, 'scan_theme_ui' ) ); add_action( 'wp_ajax_pera_ml_health_translate', array( $this, 'ajax_health_translate' ) ); add_action( 'wp_ajax_pera_ml_translation_queue', array( $this, 'ajax_translation_queue' ) ); add_action( 'wp_ajax_pera_ml_translate_field', array( $this, 'ajax_translate_field' ) ); add_action( 'admin_notices', array( $this, 'translation_notice' ) ); add_filter( 'manage_post_posts_columns', array( $this, 'post_columns' ) ); add_action( 'manage_post_posts_custom_column', array( $this, 'post_column' ), 10, 2 ); add_action( 'the_posts', array( $this, 'preload_post_statuses' ), 10, 2 ); }
	public function translation_notice() {
		if ( empty( $_GET['pera_ml_notice'] ) || empty( $_GET['post'] ) ) return;
		$key = $this->notice_key( get_current_user_id(), absint( $_GET['post'] ), sanitize_key( wp_unslash( $_GET['pera_ml_notice'] ) ) );
		$notice = get_transient( $key ); if ( ! is_array( $notice ) ) return; delete_transient( $key );
		$failures = isset( $notice['failures'] ) && is_array( $notice['failures'] ) ? array_slice( $notice['failures'], 0, 50 ) : array();
		$successes = isset( $notice['successes'] ) ? absint( $notice['successes'] ) : 0;
		$message = sprintf(
			/* translators: 1: successful field count, 2: failed field count */
			__( 'Translation finished: %1$d fields succeeded; %2$d fields failed.', 'pera-multilingual' ),
			$successes,
			count( $failures )
		);
		if ( $failures ) $message .= ' ' . sprintf( __( 'Failed fields: %s', 'pera-multilingual' ), implode( ', ', $failures ) );
		echo '<div class="notice ' . ( $failures ? 'notice-warning' : 'notice-success' ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}
	public function meta_box() { foreach ( get_post_types( array( 'public' => true ) ) as $type ) add_meta_box( 'pera-ml-translate', __( 'Pera Multilingual', 'pera-multilingual' ), array( $this, 'meta_box_html' ), $type, 'side' ); }
	public function meta_box_html( $post ) { $supported = in_array( $post->post_type, array( 'post', 'property' ), true ); foreach ( $this->registry->enabled() as $code => $language ) { if ( 'en' === $code ) continue; $form_id = 'pera-ml-translate-' . (int) $post->ID . '-' . sanitize_html_class( $code ); $this->translation_forms[ $form_id ] = array( 'post_id' => (int) $post->ID, 'language' => $code ); $status = $supported ? Pera_ML_Plugin::instance()->status()->get( $post->ID, $code, $post->post_type ) : null; $queue_attributes = $supported ? ' data-pera-ml-queue data-post-id="' . (int) $post->ID . '" data-language="' . esc_attr( $code ) . '" data-language-name="' . esc_attr( $language['name'] ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'pera_ml_translate_' . $post->ID . '_' . $code ) ) . '"' : ''; echo '<div class="pera-ml-language-status"' . $queue_attributes . '><p><strong>' . esc_html( $language['name'] ) . '</strong><br><span class="pera-ml-queue-status">'; if ( $status ) { echo esc_html( $this->status_summary( $status ) ); $details = $this->status_details( $status ); if ( $details ) echo '<br><small>' . esc_html( $details ) . '</small>'; } echo '</span></p><div class="pera-ml-queue-fields" aria-live="polite"></div><p><button class="button' . ( $supported ? ' pera-ml-queue-button' : '' ) . '" type="submit" form="' . esc_attr( $form_id ) . '" data-mode="' . esc_attr( $status && $status['complete'] ? 'regenerate' : 'complete' ) . '">' . esc_html( $status && $status['complete'] ? sprintf( __( 'Regenerate %s', 'pera-multilingual' ), $language['name'] ) : sprintf( __( 'Translate / complete %s', 'pera-multilingual' ), $language['name'] ) ) . '</button></p></div>'; } }
	public function enqueue_translation_queue( $hook ) { if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) return; wp_enqueue_script( 'pera-ml-admin-queue', PERA_ML_URL . 'admin/translation-queue.js', array(), PERA_ML_VERSION, true ); wp_localize_script( 'pera-ml-admin-queue', 'peraMLQueue', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ) ); }
	public function post_columns( $columns ) { foreach ( $this->target_languages() as $code => $language ) $columns[ 'pera_ml_' . $code ] = strtoupper( $code ); return $columns; }
	public function preload_post_statuses( $posts, $query ) { if ( ! is_admin() || ! $query->is_main_query() || ! isset( $GLOBALS['pagenow'] ) || 'edit.php' !== $GLOBALS['pagenow'] ) return $posts; $ids = array(); foreach ( $posts as $post ) if ( 'post' === $post->post_type ) $ids[] = $post->ID; Pera_ML_Plugin::instance()->status()->preload( $ids, array_keys( $this->target_languages() ) ); return $posts; }
	public function post_column( $column, $post_id ) { if ( 0 !== strpos( $column, 'pera_ml_' ) ) return; $code = substr( $column, 8 ); $languages = $this->target_languages(); if ( ! isset( $languages[ $code ] ) ) return; $status = Pera_ML_Plugin::instance()->status()->get( $post_id, $code ); $label = $this->accessible_status( $languages[ $code ]['name'], $status ); if ( $status['complete'] ) $indicator = '✅'; elseif ( $status['stale'] ) $indicator = '⚠'; elseif ( $status['existing'] ) $indicator = $status['existing'] . '/' . $status['applicable']; else $indicator = '—'; echo '<a href="' . esc_url( get_edit_post_link( $post_id ) ) . '" title="' . esc_attr( $label ) . '" aria-label="' . esc_attr( $label ) . '">' . esc_html( $indicator ) . '</a>'; }
	private function target_languages() { return array_filter( $this->registry->enabled(), static function ( $language ) { return empty( $language['source'] ); } ); }
	private function status_summary( $status ) { if ( $status['complete'] ) return __( '✅ Complete', 'pera-multilingual' ); if ( ! $status['existing'] ) return __( '— Not translated', 'pera-multilingual' ); if ( $status['stale'] ) return sprintf( __( '⚠ %1$d current / %2$d stale', 'pera-multilingual' ), $status['current'], count( $status['stale'] ) ); return sprintf( __( '⚠ %1$d/%2$d translated', 'pera-multilingual' ), $status['existing'], $status['applicable'] ); }
	private function status_details( $status ) { $parts = array(); if ( $status['missing'] ) $parts[] = sprintf( __( 'Missing: %s', 'pera-multilingual' ), implode( ', ', $status['missing'] ) ); if ( $status['stale'] ) $parts[] = sprintf( __( 'Stale: %s', 'pera-multilingual' ), implode( ', ', $status['stale'] ) ); return implode( '. ', $parts ); }
	private function accessible_status( $language, $status ) { $text = sprintf( __( '%1$s: %2$d of %3$d fields current.', 'pera-multilingual' ), $language, $status['current'], $status['applicable'] ); $details = $this->status_details( $status ); return $details ? $text . ' ' . $details . '.' : $text; }
	public function translation_forms() { foreach ( $this->translation_forms as $form_id => $data ) { echo '<form hidden id="' . esc_attr( $form_id ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="pera_ml_translate_object"><input type="hidden" name="post_id" value="' . (int) $data['post_id'] . '"><input type="hidden" name="language" value="' . esc_attr( $data['language'] ) . '">'; wp_nonce_field( 'pera_ml_translate_' . $data['post_id'] . '_' . $data['language'] ); echo '</form>'; } }
	private function ajax_request() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$language = isset( $_POST['language'] ) ? sanitize_key( wp_unslash( $_POST['language'] ) ) : '';
		if ( ! is_user_logged_in() ) return new WP_Error( 'not_authenticated' );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) return new WP_Error( 'insufficient_capability' );
		if ( ! check_ajax_referer( 'pera_ml_translate_' . $post_id . '_' . $language, 'nonce', false ) ) return new WP_Error( 'invalid_nonce' );
		$config = $this->registry->get( $language );
		if ( ! $config || empty( $config['enabled'] ) || ! empty( $config['source'] ) ) return new WP_Error( 'invalid_language' );
		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, array( 'post', 'property' ), true ) ) return new WP_Error( 'invalid_object' );
		return array( $post_id, $language, $post->post_type );
	}
	private function ajax_error( $error, $field = '', $status = 400 ) { wp_send_json( array( 'success' => false, 'field' => $this->bounded_field_identifier( $field ), 'error_code' => $this->public_error_code( $error ) ), $status ); }
	private function public_error_code( $error ) {
		$code = is_wp_error( $error ) ? $error->get_error_code() : (string) $error;
		$allowed = array( 'not_authenticated', 'insufficient_capability', 'invalid_nonce', 'invalid_language', 'invalid_object', 'invalid_field', 'pera_ml_rate_limited', 'pera_ml_provider_transient', 'http_request_failed' );
		return in_array( $code, $allowed, true ) ? $code : 'translation_failed';
	}
	/** Queue contract shared by AJAX and tests; this method never calls the provider. */
	public function translation_queue( $post_id, $language, $mode = 'complete', $status_service = null ) {
		$status_service = $status_service ? $status_service : Pera_ML_Plugin::instance()->status();
		$post = get_post( $post_id ); $post_type = $post ? $post->post_type : 'post';
		$sources = $status_service->applicable_sources( $post_id, $post_type );
		$status = $status_service->get( $post_id, $language, $post_type );
		$fields = 'regenerate' === $mode ? array_keys( $sources ) : array_values( array_unique( array_merge( $status['missing'], $status['stale'] ) ) );
		// Content is deliberately first without changing the canonical inventory definitions.
		if ( false !== ( $position = array_search( 'post_content', $fields, true ) ) ) { unset( $fields[ $position ] ); array_unshift( $fields, 'post_content' ); }
		return array( 'fields' => array_values( $fields ), 'applicable_fields' => array_keys( $sources ), 'status' => $status );
	}
	public function ajax_translation_queue() {
		$request = $this->ajax_request(); if ( is_wp_error( $request ) ) $this->ajax_error( $request, '', in_array( $request->get_error_code(), array( 'not_authenticated', 'insufficient_capability' ), true ) ? 403 : 400 );
		list( $post_id, $language ) = $request; $mode = isset( $_POST['mode'] ) && 'regenerate' === sanitize_key( wp_unslash( $_POST['mode'] ) ) ? 'regenerate' : 'complete';
		wp_send_json( array_merge( array( 'success' => true ), $this->translation_queue( $post_id, $language, $mode ) ) );
	}
	/** Translate and immediately store exactly one server-approved canonical field. */
	public function translate_field( $post_id, $language, $field, $translator = null, $status_service = null ) {
		$status_service = $status_service ? $status_service : Pera_ML_Plugin::instance()->status();
		$post = get_post( $post_id ); $post_type = $post ? $post->post_type : 'post';
		$sources = $status_service->applicable_sources( $post_id, $post_type );
		if ( ! isset( $sources[ $field ] ) ) return new WP_Error( 'invalid_field' );
		$translator = $translator ? $translator : Pera_ML_Plugin::instance()->translator();
		$result = $this->translate_with_retry( $translator, 'post', $post_id, $field, $language, $sources[ $field ] );
		if ( ! is_wp_error( $result ) ) $status_service->invalidate( $post_id, $language );
		return $result;
	}
	public function ajax_translate_field() {
		$request = $this->ajax_request(); $field = isset( $_POST['field'] ) ? $this->bounded_field_identifier( wp_unslash( $_POST['field'] ) ) : '';
		if ( is_wp_error( $request ) ) $this->ajax_error( $request, $field, in_array( $request->get_error_code(), array( 'not_authenticated', 'insufficient_capability' ), true ) ? 403 : 400 );
		list( $post_id, $language ) = $request; $result = $this->translate_field( $post_id, $language, $field );
		if ( is_wp_error( $result ) ) $this->ajax_error( $result, $field );
		wp_send_json( array( 'success' => true, 'field' => $field, 'status' => 'current' ) );
	}
	public function translate_object() {
		if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) wp_die( esc_html__( 'Translation requests must use POST.', 'pera-multilingual' ), 405 );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0; $language = isset( $_POST['language'] ) ? sanitize_key( wp_unslash( $_POST['language'] ) ) : '';
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) wp_die( esc_html__( 'You cannot translate this object.', 'pera-multilingual' ), 403 );
		check_admin_referer( 'pera_ml_translate_' . $post_id . '_' . $language ); $config = $this->registry->get( $language ); if ( ! $config || empty( $config['enabled'] ) || ! empty( $config['source'] ) ) wp_die( esc_html__( 'Invalid target language.', 'pera-multilingual' ), 400 );
		$post = get_post( $post_id );
		// Translate the largest and most important field first. A synchronous request that is
		// terminated by the host can then no longer skip content after saving smaller fields.
		$sources = Pera_ML_Plugin::instance()->status()->applicable_sources( $post_id, $post->post_type );
		$summary = $this->translate_fields( $post_id, $language, $sources ); $failures = $summary['failures']; $successes = $summary['successes'];
		foreach ( array( 'district', 'region', 'property_type', 'property_tags', 'special' ) as $taxonomy ) { $terms = wp_get_post_terms( $post_id, $taxonomy ); if ( is_wp_error( $terms ) ) { $failures[] = $taxonomy . ':terms'; continue; } foreach ( $terms as $term ) {
			$name = Pera_ML_Plugin::instance()->vocabulary()->translate( $term->name, $language );
			$result = $name !== $term->name ? Pera_ML_Plugin::instance()->storage()->put( 'term', $term->term_id, 'term_name', $language, $term->name, $name, 'vocabulary' ) : $this->translate_with_retry( Pera_ML_Plugin::instance()->translator(), 'term', $term->term_id, 'term_name', $language, $term->name ); if ( is_wp_error( $result ) ) $failures[] = $taxonomy . ':' . $term->term_id . ':name'; else $successes++;
			if ( '' !== trim( $term->description ) ) { $result = $this->translate_with_retry( Pera_ML_Plugin::instance()->translator(), 'term', $term->term_id, 'term_description', $language, $term->description ); if ( is_wp_error( $result ) ) $failures[] = $taxonomy . ':' . $term->term_id . ':description'; else $successes++; }
			if ( 'district' === $taxonomy ) foreach ( array( 'district_archive_subtitle', 'district_archive_body' ) as $field ) { $source = get_term_meta( $term->term_id, $field, true ); if ( is_string( $source ) && '' !== trim( $source ) ) { $result = $this->translate_with_retry( Pera_ML_Plugin::instance()->translator(), 'term', $term->term_id, 'meta:' . $field, $language, $source ); if ( is_wp_error( $result ) ) $failures[] = $taxonomy . ':' . $term->term_id . ':' . $field; else $successes++; } }
		} }
		$failures = array_slice( array_map( array( $this, 'bounded_field_identifier' ), $failures ), 0, 50 );
		$notice_id = wp_generate_password( 12, false, false ); set_transient( $this->notice_key( get_current_user_id(), $post_id, $notice_id ), array( 'language' => $language, 'successes' => $successes, 'failures' => $failures ), 5 * MINUTE_IN_SECONDS );
		$redirect = add_query_arg( 'pera_ml_notice', $notice_id, get_edit_post_link( $post_id, 'url' ) ); wp_safe_redirect( $redirect ); exit;
	}
	/** Run independent field writes, retaining every successful row when a sibling fails. */
	public function translate_fields( $post_id, $language, array $sources, $translator = null ) {
		$translator = $translator ? $translator : Pera_ML_Plugin::instance()->translator();
		$summary = array( 'successes' => 0, 'failures' => array() );
		foreach ( $sources as $field => $source ) {
			if ( ! is_string( $source ) || '' === trim( $source ) ) continue;
			$result = $this->translate_with_retry( $translator, 'post', $post_id, $field, $language, $source );
			if ( is_wp_error( $result ) ) $summary['failures'][] = $this->bounded_field_identifier( $field );
			else $summary['successes']++;
		}
		return $summary;
	}
	private function translate_with_retry( $translator, $type, $id, $field, $language, $source ) {
		$result = $translator->translate_and_store( $type, $id, $field, $language, $source );
		if ( ! is_wp_error( $result ) || ! in_array( $result->get_error_code(), array( 'pera_ml_rate_limited', 'pera_ml_provider_transient', 'http_request_failed' ), true ) ) return $result;
		// One orchestration retry only; post_content source-echo segment retries remain inside the translator.
		$delay = (int) apply_filters( 'pera_ml_admin_retry_delay', 1, $result, $field );
		if ( $delay > 0 ) sleep( min( 2, $delay ) );
		return $translator->translate_and_store( $type, $id, $field, $language, $source );
	}
	private function bounded_field_identifier( $field ) {
		$field = preg_replace( '/[^a-zA-Z0-9_:-]/', '', (string) $field );
		return substr( $field, 0, 100 );
	}
	private function notice_key( $user_id, $post_id, $notice_id ) { return 'pera_ml_notice_' . absint( $user_id ) . '_' . absint( $post_id ) . '_' . sanitize_key( $notice_id ); }
	public function menu() {
		add_menu_page( __( 'Pera Multilingual', 'pera-multilingual' ), __( 'Pera Multilingual', 'pera-multilingual' ), 'manage_options', 'pera-multilingual', array( $this, 'page' ), 'dashicons-translation' );
		add_submenu_page( 'pera-multilingual', __( 'Settings', 'pera-multilingual' ), __( 'Settings', 'pera-multilingual' ), 'manage_options', 'pera-multilingual', array( $this, 'page' ) );
		add_submenu_page( 'pera-multilingual', __( 'Translation Health', 'pera-multilingual' ), __( 'Translation Health', 'pera-multilingual' ), 'manage_options', 'pera-multilingual-health', array( $this, 'translation_health_page' ) );
		add_submenu_page( 'pera-multilingual', __( 'UI Strings', 'pera-multilingual' ), __( 'UI Strings', 'pera-multilingual' ), 'manage_options', 'pera-multilingual-ui', array( $this, 'ui_strings_page' ) );
	}
	public function ui_strings_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$items = Pera_ML_Plugin::instance()->ui()->inventory();
		$labels = array( 'current' => __( 'Current', 'pera-multilingual' ), 'missing' => __( 'Missing', 'pera-multilingual' ), 'stale' => __( 'Stale', 'pera-multilingual' ) );
		?>
		<div class="wrap"><h1><?php esc_html_e( 'UI Strings', 'pera-multilingual' ); ?></h1>
		<p><?php esc_html_e( 'This inventory contains only copy registered through pera_ml_ui(). Viewing frontend pages can register copy, but never creates translations or calls a provider.', 'pera-multilingual' ); ?></p>
		<?php if ( isset( $_GET['pera_ml_ui_updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'UI-string translation work completed.', 'pera-multilingual' ); ?></p></div><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="pera_ml_complete_ui"><?php wp_nonce_field( 'pera_ml_complete_ui' ); ?><?php submit_button( __( 'Complete all Missing / Stale', 'pera-multilingual' ), 'primary', 'submit', false ); ?></form>
		<table class="widefat striped" style="margin-top:16px"><thead><tr><th><?php esc_html_e( 'Semantic key', 'pera-multilingual' ); ?></th><th><?php esc_html_e( 'Canonical English source', 'pera-multilingual' ); ?></th><?php foreach ( array( 'zh', 'ar', 'de' ) as $language ) : ?><th><?php echo esc_html( strtoupper( $language ) ); ?></th><?php endforeach; ?></tr></thead><tbody>
		<?php if ( ! $items ) : ?><tr><td colspan="5"><?php esc_html_e( 'No UI strings have been registered yet.', 'pera-multilingual' ); ?></td></tr><?php endif; ?>
		<?php foreach ( $items as $identity => $item ) : ?><tr><td><code><?php echo esc_html( $item['semantic_key'] ); ?></code></td><td><?php echo esc_html( $item['source'] ); ?></td>
		<?php foreach ( array( 'zh', 'ar', 'de' ) as $language ) : $status = $item['statuses'][ $language ]; ?><td><strong><?php echo esc_html( $labels[ $status ] ); ?></strong><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:6px"><input type="hidden" name="action" value="pera_ml_translate_ui"><input type="hidden" name="identity" value="<?php echo esc_attr( $identity ); ?>"><input type="hidden" name="language" value="<?php echo esc_attr( $language ); ?>"><?php wp_nonce_field( 'pera_ml_translate_ui_' . $identity . '_' . $language ); ?><button class="button button-small" type="submit"><?php echo esc_html( 'current' === $status ? __( 'Regenerate', 'pera-multilingual' ) : __( 'Translate', 'pera-multilingual' ) ); ?></button></form></td><?php endforeach; ?></tr><?php endforeach; ?>
		</tbody></table></div><?php
	}
	private function ui_admin_request( $bulk = false ) {
		if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) wp_die( esc_html__( 'Translation requests must use POST.', 'pera-multilingual' ), 405 );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You cannot translate UI strings.', 'pera-multilingual' ), 403 );
		if ( $bulk ) { check_admin_referer( 'pera_ml_complete_ui' ); return array(); }
		$identity = isset( $_POST['identity'] ) ? sanitize_text_field( wp_unslash( $_POST['identity'] ) ) : '';
		$language = isset( $_POST['language'] ) ? sanitize_key( wp_unslash( $_POST['language'] ) ) : '';
		check_admin_referer( 'pera_ml_translate_ui_' . $identity . '_' . $language );
		if ( ! in_array( $language, array( 'zh', 'ar', 'de' ), true ) || ! Pera_ML_Plugin::instance()->ui_registry()->find( $identity ) ) wp_die( esc_html__( 'Invalid UI-string translation request.', 'pera-multilingual' ), 400 );
		return array( $identity, $language );
	}
	public function translate_ui() {
		list( $identity, $language ) = $this->ui_admin_request();
		Pera_ML_Plugin::instance()->ui()->translate_registered( $identity, $language );
		$this->redirect_ui_strings();
	}
	/** Complete exactly the registered Missing/Stale rows. Public for focused orchestration tests. */
	public function complete_ui_translations( $ui = null ) {
		$ui = $ui ? $ui : Pera_ML_Plugin::instance()->ui(); $summary = array( 'attempted' => 0, 'failures' => 0 );
		foreach ( $ui->inventory() as $identity => $item ) foreach ( array( 'zh', 'ar', 'de' ) as $language ) {
			if ( 'current' === $item['statuses'][ $language ] ) continue;
			$summary['attempted']++; if ( is_wp_error( $ui->translate_registered( $identity, $language ) ) ) $summary['failures']++;
		}
		return $summary;
	}
	public function complete_ui() { $this->ui_admin_request( true ); $this->complete_ui_translations(); $this->redirect_ui_strings(); }
	private function redirect_ui_strings() { wp_safe_redirect( add_query_arg( 'pera_ml_ui_updated', '1', admin_url( 'admin.php?page=pera-multilingual-ui' ) ) ); exit; }
	public function scan_theme_ui() {
		if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) wp_die( esc_html__( 'Scans must use POST.', 'pera-multilingual' ), 405 );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You cannot scan UI strings.', 'pera-multilingual' ), 403 );
		check_admin_referer( 'pera_ml_scan_theme_ui' );
		$stats = ( new Pera_ML_Theme_UI_Discovery( Pera_ML_Plugin::instance()->ui_registry() ) )->run( Pera_ML_Theme_UI_Discovery::approved_directories() );
		set_transient( 'pera_ml_scan_' . get_current_user_id(), $stats, MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=pera-multilingual-health&scanned=1' ) ); exit;
	}

	public function translation_health_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$health = new Pera_ML_Translation_Health( Pera_ML_Plugin::instance()->status(), Pera_ML_Plugin::instance()->storage(), Pera_ML_Plugin::instance()->ui() );
		$inventory = $health->inventory(); $rows = $inventory['rows'];
		$filter_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; $filter_group = isset( $_GET['group'] ) ? sanitize_key( wp_unslash( $_GET['group'] ) ) : ''; $filter_language = isset( $_GET['language'] ) ? sanitize_key( wp_unslash( $_GET['language'] ) ) : '';
		$scan = get_transient( 'pera_ml_scan_' . get_current_user_id() ); if ( $scan ) delete_transient( 'pera_ml_scan_' . get_current_user_id() );
		wp_enqueue_script( 'pera-ml-health', PERA_ML_URL . 'admin/translation-health.js', array(), PERA_ML_VERSION, true ); wp_localize_script( 'pera-ml-health', 'peraMLHealth', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'pera_ml_health_translate' ) ) );
		?>
		<div class="wrap"><h1><?php esc_html_e( 'Translation Health', 'pera-multilingual' ); ?></h1><p><?php esc_html_e( 'Read-only coverage from approved canonical fields and stored source hashes. Inventory and scans never call a translation provider.', 'pera-multilingual' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="pera_ml_scan_theme_ui"><?php wp_nonce_field( 'pera_ml_scan_theme_ui' ); ?><?php submit_button( __( 'Scan theme UI strings', 'pera-multilingual' ), 'secondary', 'submit', false ); ?></form>
		<?php if ( is_array( $scan ) ) : ?><div class="notice notice-success"><p><?php foreach ( array( 'discovered', 'newly_registered', 'already_current', 'source_changed', 'dynamic_skipped' ) as $key ) echo '<strong>' . esc_html( str_replace( '_', ' ', ucfirst( $key ) ) ) . ':</strong> ' . absint( $scan[ $key ] ) . ' &nbsp;'; ?></p></div><?php endif; ?>
		<h2><?php echo esc_html( sprintf( __( 'UI Strings — %d registered', 'pera-multilingual' ), $inventory['ui_total'] ) ); ?></h2>
		<?php foreach ( array( 'ui' => __( 'UI Strings', 'pera-multilingual' ), 'content' => __( 'Posts / Pages / Properties', 'pera-multilingual' ), 'taxonomies' => __( 'Taxonomies', 'pera-multilingual' ) ) as $group => $heading ) : ?><h2><?php echo esc_html( $heading ); ?></h2><table class="widefat striped" style="max-width:760px"><thead><tr><th><?php esc_html_e( 'Language', 'pera-multilingual' ); ?></th><th><?php esc_html_e( 'Current', 'pera-multilingual' ); ?></th><th><?php esc_html_e( 'Missing', 'pera-multilingual' ); ?></th><th><?php esc_html_e( 'Stale', 'pera-multilingual' ); ?></th></tr></thead><tbody><?php foreach ( Pera_ML_Translation_Health::LANGUAGES as $language ) : $counts = isset( $inventory['counts'][ $group ][ $language ] ) ? $inventory['counts'][ $group ][ $language ] : array( 'current'=>0,'missing'=>0,'stale'=>0 ); ?><tr><th><?php echo esc_html( strtoupper( $language ) ); ?></th><?php foreach ( array( 'current','missing','stale' ) as $state ) : ?><td><?php if ( 'current' !== $state && $counts[ $state ] ) : ?><a href="<?php echo esc_url( add_query_arg( array( 'page'=>'pera-multilingual-health','group'=>$group,'language'=>$language,'status'=>$state ), admin_url( 'admin.php' ) ) ); ?>"><?php echo absint( $counts[ $state ] ); ?></a><?php else : echo absint( $counts[ $state ] ); endif; ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table><?php endforeach; ?>
		<?php if ( $filter_status && $filter_language ) : ?><h2><?php esc_html_e( 'Translation details', 'pera-multilingual' ); ?></h2><p><button type="button" class="button button-primary" id="pera-ml-health-bulk"><?php esc_html_e( 'Complete shown Missing / Stale', 'pera-multilingual' ); ?></button> <span id="pera-ml-health-progress" aria-live="polite"></span></p><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Object type', 'pera-multilingual' ); ?></th><th><?php esc_html_e( 'Object ID', 'pera-multilingual' ); ?></th><th><?php esc_html_e( 'Title / name', 'pera-multilingual' ); ?></th><th><?php esc_html_e( 'Field', 'pera-multilingual' ); ?></th><th><?php esc_html_e( 'Language', 'pera-multilingual' ); ?></th><th><?php esc_html_e( 'Status', 'pera-multilingual' ); ?></th><th><?php esc_html_e( 'Action', 'pera-multilingual' ); ?></th></tr></thead><tbody><?php foreach ( $rows as $row ) { $group = 'ui' === $row['object_type'] ? 'ui' : ( 0 === strpos( $row['object_type'], 'taxonomy:' ) ? 'taxonomies' : 'content' ); if ( $group !== $filter_group || $row['language'] !== $filter_language || $row['status'] !== $filter_status ) continue; $payload = wp_json_encode( $row ); ?><tr class="pera-ml-health-row" data-row="<?php echo esc_attr( $payload ); ?>"><td><?php echo esc_html( $row['object_type'] ); ?></td><td><?php echo absint( $row['object_id'] ); ?></td><td><?php echo esc_html( $row['title'] ); ?></td><td><code><?php echo esc_html( $row['field'] ); ?></code></td><td><?php echo esc_html( strtoupper( $row['language'] ) ); ?></td><td class="status"><?php echo esc_html( ucfirst( $row['status'] ) ); ?></td><td><button type="button" class="button pera-ml-health-one"><?php echo esc_html( 'stale' === $row['status'] ? __( 'Regenerate', 'pera-multilingual' ) : __( 'Translate', 'pera-multilingual' ) ); ?></button></td></tr><?php } ?></tbody></table><?php endif; ?></div><?php
	}

	public function ajax_health_translate() {
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'code' => 'forbidden' ), 403 ); check_ajax_referer( 'pera_ml_health_translate', 'nonce' );
		$row = isset( $_POST['row'] ) ? json_decode( wp_unslash( $_POST['row'] ), true ) : null; if ( ! is_array( $row ) || ! in_array( $row['language'], Pera_ML_Translation_Health::LANGUAGES, true ) || ! in_array( $row['status'], array( 'missing', 'stale' ), true ) ) wp_send_json_error( array( 'code'=>'invalid_row' ), 400 );
		$type = sanitize_text_field( $row['object_type'] ); $id = absint( $row['object_id'] ); $field = Pera_ML_Storage::normalize_field_key( $row['field'] ); $language = sanitize_key( $row['language'] );
		if ( 'ui' === $type ) { $item = Pera_ML_Plugin::instance()->ui_registry()->find( $row['field'] ); $actual = $item ? Pera_ML_Plugin::instance()->ui()->status( $item, $language ) : 'invalid'; $result = $item && 'current' !== $actual ? Pera_ML_Plugin::instance()->ui()->translate_registered( $row['field'], $language ) : new WP_Error( 'invalid_row' ); }
		elseif ( 0 === strpos( $type, 'taxonomy:' ) ) { $term = get_term( $id, substr( $type, 9 ) ); $source = $term && 'term_name' === $field ? $term->name : ( $term && 'term_description' === $field ? $term->description : ( $term && 0 === strpos( $field, 'meta:' ) ? get_term_meta( $id, substr( $field, 5 ), true ) : '' ) ); $stored = $term ? Pera_ML_Plugin::instance()->storage()->get( 'term', $id, $field, $language, $source ) : null; $current = is_array( $stored ) && '' !== trim( (string) $stored['translated_text'] ) && empty( $stored['is_stale'] ) && ( ! isset( $stored['status'] ) || 'current' === $stored['status'] ); $result = $term && ! $current && '' !== trim( (string) $source ) ? Pera_ML_Plugin::instance()->translator()->translate_and_store( 'term', $id, $field, $language, $source ) : new WP_Error( 'invalid_row' ); }
		else { $post = get_post( $id ); $sources = $post ? Pera_ML_Plugin::instance()->status()->applicable_sources( $id, $post->post_type ) : array(); $state = $post ? Pera_ML_Plugin::instance()->status()->get( $id, $language, $post->post_type ) : array( 'missing'=>array(), 'stale'=>array() ); $eligible = in_array( $field, array_merge( $state['missing'], $state['stale'] ), true ); $result = isset( $sources[ $field ] ) && $eligible ? Pera_ML_Plugin::instance()->translator()->translate_and_store( 'post', $id, $field, $language, $sources[ $field ] ) : new WP_Error( 'invalid_row' ); }
		if ( is_wp_error( $result ) ) wp_send_json_error( array( 'code' => $this->public_error_code( $result ) ), 400 ); wp_send_json_success( array( 'status'=>'current' ) );
	}

	public function settings() {
		register_setting( 'pera_ml', 'pera_ml_enabled_languages', array( 'type' => 'array', 'sanitize_callback' => array( $this, 'sanitize_languages' ) ) );
		register_setting( 'pera_ml', 'pera_ml_provider', array( 'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_provider' ), 'default' => 'openai' ) );
		register_setting( 'pera_ml', 'pera_ml_automatic', array( 'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => false ) );
		register_setting( 'pera_ml', 'pera_ml_openai_api_key', array( 'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_secret' ), 'show_in_rest' => false ) );
		register_setting( 'pera_ml', 'pera_ml_openai_model', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'gpt-4.1-mini' ) );
		register_setting( 'pera_ml', 'pera_ml_glossary_text', array( 'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_glossary' ) ) );
	}
	public function sanitize_languages( $value ) { $allowed = array_keys( $this->registry->all() ); $value = is_array( $value ) ? array_intersect( array_map( 'sanitize_key', $value ), $allowed ) : array(); $value[] = 'en'; return array_values( array_unique( $value ) ); }
	public function sanitize_provider( $value ) { return in_array( $value, array( 'openai', 'mock' ), true ) ? $value : 'openai'; }
	public function sanitize_secret( $value ) { if ( '' === trim( (string) $value ) ) return get_option( 'pera_ml_openai_api_key', '' ); return trim( sanitize_text_field( $value ) ); }
	public function sanitize_glossary( $value ) {
		$text = sanitize_textarea_field( $value ); $entries = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) { $parts = array_map( 'trim', explode( '=>', $line, 2 ) ); if ( '' !== $parts[0] ) $entries[] = array( 'source' => $parts[0], 'translation' => isset( $parts[1] ) ? $parts[1] : '' ); }
		update_option( 'pera_ml_glossary', $entries ); return $text;
	}
	public function page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$languages = $this->registry->all(); $enabled = get_option( 'pera_ml_enabled_languages', array( 'en', 'zh', 'ar', 'de' ) );
		?>
		<div class="wrap"><h1><?php esc_html_e( 'Pera Multilingual', 'pera-multilingual' ); ?></h1>
		<p><?php esc_html_e( 'Translated requests resolve the original English WordPress object. Frontend requests only read saved translations; they never call a provider.', 'pera-multilingual' ); ?></p>
		<form method="post" action="options.php"><?php settings_fields( 'pera_ml' ); ?>
		<table class="form-table" role="presentation"><tr><th><?php esc_html_e( 'Enabled languages', 'pera-multilingual' ); ?></th><td>
		<?php foreach ( $languages as $code => $language ) : ?><label style="display:block"><input type="checkbox" name="pera_ml_enabled_languages[]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, (array) $enabled, true ) ); disabled( 'en' === $code ); ?>> <?php echo esc_html( $language['name'] . ' — ' . $language['native_name'] ); ?></label><?php endforeach; ?></td></tr>
		<tr><th><label for="pera-ml-provider"><?php esc_html_e( 'Provider', 'pera-multilingual' ); ?></label></th><td><select id="pera-ml-provider" name="pera_ml_provider"><option value="openai" <?php selected( get_option( 'pera_ml_provider', 'openai' ), 'openai' ); ?>>OpenAI</option><option value="mock" <?php selected( get_option( 'pera_ml_provider' ), 'mock' ); ?>><?php esc_html_e( 'Deterministic mock (testing)', 'pera-multilingual' ); ?></option></select></td></tr>
		<tr><th><?php esc_html_e( 'Automatic translation', 'pera-multilingual' ); ?></th><td><label><input type="checkbox" name="pera_ml_automatic" value="1" <?php checked( get_option( 'pera_ml_automatic', false ) ); ?>> <?php esc_html_e( 'Enable for future background jobs (never runs during page rendering)', 'pera-multilingual' ); ?></label></td></tr>
		<tr><th><label for="pera-ml-key"><?php esc_html_e( 'OpenAI API key', 'pera-multilingual' ); ?></label></th><td><input id="pera-ml-key" class="regular-text" type="password" autocomplete="new-password" name="pera_ml_openai_api_key" value="" placeholder="<?php echo get_option( 'pera_ml_openai_api_key' ) ? esc_attr__( 'Saved — leave blank to retain', 'pera-multilingual' ) : ''; ?>"><p class="description"><?php esc_html_e( 'Prefer PERA_ML_OPENAI_API_KEY in wp-config.php. Keys are never exposed to frontend code.', 'pera-multilingual' ); ?></p></td></tr>
		<tr><th><label for="pera-ml-model"><?php esc_html_e( 'OpenAI model', 'pera-multilingual' ); ?></label></th><td><input id="pera-ml-model" class="regular-text" name="pera_ml_openai_model" value="<?php echo esc_attr( get_option( 'pera_ml_openai_model', 'gpt-4.1-mini' ) ); ?>"></td></tr>
		<tr><th><label for="pera-ml-glossary"><?php esc_html_e( 'Glossary / protected terms', 'pera-multilingual' ); ?></label></th><td><textarea id="pera-ml-glossary" class="large-text code" rows="10" name="pera_ml_glossary_text" placeholder="Tapu => PRESERVE&#10;Title Deed => ..."><?php echo esc_textarea( get_option( 'pera_ml_glossary_text', "Pera Property => PRESERVE\nBeyoğlu => PRESERVE\nBeşiktaş => PRESERVE\nKadıköy => PRESERVE" ) ); ?></textarea><p class="description"><?php esc_html_e( 'One “source => required translation” rule per line. Use PRESERVE to protect a term.', 'pera-multilingual' ); ?></p></td></tr></table>
		<?php submit_button(); ?></form></div><?php
	}
}
