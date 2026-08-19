<?php
defined( 'ABSPATH' ) || exit;

final class Pera_ML_Content {
	private $registry; private $router; private $storage;
	public function __construct( $registry, $router, $storage ) { $this->registry = $registry; $this->router = $router; $this->storage = $storage; }
	public function hooks() {
		add_filter( 'the_title', array( $this, 'title' ), 20, 2 );
		add_filter( 'the_content', array( $this, 'content' ), 20 );
		add_filter( 'get_the_excerpt', array( $this, 'excerpt' ), 20, 2 );
		add_filter( 'body_class', array( $this, 'body_classes' ) );
		add_filter( 'language_attributes', array( $this, 'language_attributes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'rtl_style' ) );
		add_shortcode( 'pera_language_switcher', array( $this, 'language_switcher' ) );
		add_action( 'save_post', array( $this, 'stale_post' ), 20, 3 );
		add_action( 'edited_term', array( $this, 'stale_term' ), 20 );
	}
	private function translated( $id, $field, $source ) {
		if ( ! $this->router->is_translated() || $id <= 0 ) return $source;
		$row = $this->storage->get( 'post', $id, $field, $this->router->current_language(), $source );
		return $row && isset( $row['translated_text'] ) ? $row['translated_text'] : $source;
	}
	public function title( $title, $id = 0 ) { return $this->translated( (int) $id, 'post_title', $title ); }
	public function content( $content ) { return $this->translated( (int) get_the_ID(), 'post_content', $content ); }
	public function excerpt( $excerpt, $post = null ) { $id = $post instanceof WP_Post ? $post->ID : get_the_ID(); return $this->translated( (int) $id, 'post_excerpt', $excerpt ); }
	public function body_classes( $classes ) { $language = $this->registry->get( $this->router->current_language() ); $classes[] = 'pera-ml-lang-' . $this->router->current_language(); if ( $language && 'rtl' === $language['direction'] ) $classes[] = 'pera-ml-rtl'; return $classes; }
	public function language_attributes( $output ) { $language = $this->registry->get( $this->router->current_language() ); return $language ? 'lang="' . esc_attr( $language['code'] ) . '" dir="' . esc_attr( $language['direction'] ) . '"' : $output; }
	public function rtl_style() { $language = $this->registry->get( $this->router->current_language() ); if ( $language && 'rtl' === $language['direction'] ) wp_enqueue_style( 'pera-ml-rtl', PERA_ML_URL . 'assets/css/rtl.css', array(), PERA_ML_VERSION ); }
	public function stale_post( $post_id, $post = null, $update = false ) {
		if ( ! $update || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) return;
		$post = $post instanceof WP_Post ? $post : get_post( $post_id );
		if ( ! $post || in_array( $post->post_status, array( 'auto-draft', 'inherit', 'trash' ), true ) ) return;
		$post_type = get_post_type_object( $post->post_type );
		if ( ! $post_type || empty( $post_type->public ) ) return;
		$this->storage->mark_object_stale( 'post', (int) $post_id );
	}
	public function stale_term( $term_id ) { $this->storage->mark_object_stale( 'term', (int) $term_id ); }
	public function language_switcher( $args = array() ) {
		$current = $this->router->current_language();
		$request = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '/';
		$url = home_url( $request );
		$html = '<nav class="pera-ml-switcher" aria-label="' . esc_attr__( 'Language', 'pera-multilingual' ) . '"><ul>';
		foreach ( $this->registry->enabled() as $code => $language ) {
			$html .= '<li><a hreflang="' . esc_attr( $code ) . '" lang="' . esc_attr( $code ) . '"' . ( $code === $current ? ' aria-current="page"' : '' ) . ' href="' . esc_url( $this->router->url_for_language( $url, $code ) ) . '">' . esc_html( $language['native_name'] ) . '</a></li>';
		}
		return $html . '</ul></nav>';
	}
}
