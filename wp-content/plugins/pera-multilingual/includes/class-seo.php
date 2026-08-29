<?php
defined( 'ABSPATH' ) || exit;

final class Pera_ML_SEO {
	private $registry; private $router; private $storage;
	public function __construct( $registry, $router, $storage ) { $this->registry = $registry; $this->router = $router; $this->storage = $storage; }
	public function hooks() {
		add_action( 'wp_head', array( $this, 'alternates' ), 2 );
		add_filter( 'pre_get_document_title', array( $this, 'document_title' ), 50 );
		add_filter( 'rank_math/frontend/canonical', array( $this, 'canonical' ) );
	}
	public function current_url() { $uri = isset( $_SERVER['REQUEST_URI'] ) ? strtok( wp_unslash( (string) $_SERVER['REQUEST_URI'] ), '?' ) : '/'; return home_url( $uri ); }
	public function canonical( $canonical = '' ) { return $this->router->is_translated() ? $this->current_url() : $canonical; }
	public function document_title( $title ) {
		if ( ! $this->router->is_translated() || ! is_singular() ) return $title;
		$id = get_queried_object_id(); $source = get_post_meta( $id, 'seo_title', true );
		if ( '' === $source ) $source = function_exists( 'get_post_field' ) ? get_post_field( 'post_title', $id ) : get_the_title( $id );
		$translated = $source;
		if ( function_exists( 'pera_ml_field' ) ) {
			$translated = pera_ml_field( $id, 'seo_title', $source );
		} else {
			// Keep the service independently testable while applying the same
			// current/non-blank contract as the public field reader.
			$row = $this->storage->get( 'post', $id, 'meta:seo_title', $this->router->current_language(), $source );
			if ( is_array( $row ) && empty( $row['is_stale'] ) && ( ! isset( $row['status'] ) || 'current' === $row['status'] ) && isset( $row['translated_text'] ) && '' !== trim( (string) $row['translated_text'] ) ) {
				$translated = $row['translated_text'];
			}
		}
		return (string) $translated !== (string) $source ? wp_strip_all_tags( $translated ) : $title;
	}
	public function alternates() {
		if ( is_404() || is_admin() ) return;
		$current = $this->current_url();
		foreach ( $this->registry->enabled() as $code => $language ) {
			$hreflang = isset( $language['hreflang'] ) ? $language['hreflang'] : $code;
			echo '<link rel="alternate" hreflang="' . esc_attr( $hreflang ) . '" href="' . esc_url( $this->router->url_for_language( $current, $code ) ) . '" />' . "\n";
		}
		echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $this->router->url_for_language( $current, 'en' ) ) . '" />' . "\n";
	}
}
