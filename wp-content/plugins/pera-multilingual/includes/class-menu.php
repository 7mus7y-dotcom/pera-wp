<?php
defined( 'ABSPATH' ) || exit;

/** Translate the theme's canonical WordPress menus without changing stored menu items. */
final class Pera_ML_Menu {
	private $router;
	private $content;
	private $fields;
	private $ui;

	public function __construct( $router, $content, $fields, $ui ) {
		$this->router  = $router;
		$this->content = $content;
		$this->fields  = $fields;
		$this->ui      = $ui;
	}

	public function hooks() {
		add_filter( 'wp_nav_menu_objects', array( $this, 'translate_items' ), 20, 2 );
	}

	/**
	 * Return translated copies so a render never mutates cached canonical menu objects.
	 *
	 * Object-backed items deliberately use the canonical object's title. Custom links
	 * use a menu-item-ID identity which remains stable when another menu uses the same
	 * English label. Calling UI::get only reads stored rows and registers the label for
	 * the existing UI/Translation Health inventory; it cannot invoke the provider.
	 */
	public function translate_items( $items, $args ) {
		if ( is_admin() || ! $this->router->is_translated() || ! is_array( $items ) || ! $this->is_supported_location( $args ) ) return $items;

		$translated = array();
		foreach ( $items as $item ) {
			if ( ! is_object( $item ) ) {
				$translated[] = $item;
				continue;
			}
			$copy = clone $item;
			$copy->title = $this->translated_title( $copy );
			if ( isset( $copy->url ) ) $copy->url = $this->router->url_for_language( $copy->url, $this->router->current_language() );
			$translated[] = $copy;
		}
		return $translated;
	}

	private function translated_title( $item ) {
		$source = isset( $item->title ) ? (string) $item->title : '';
		if ( 'post_type' === ( isset( $item->type ) ? $item->type : '' ) && ! empty( $item->object_id ) ) {
			$canonical = get_post_field( 'post_title', (int) $item->object_id );
			if ( is_string( $canonical ) && '' !== trim( $canonical ) ) return $this->content->title( $canonical, (int) $item->object_id );
		}
		if ( 'taxonomy' === ( isset( $item->type ) ? $item->type : '' ) && ! empty( $item->object_id ) ) {
			$term = get_term( (int) $item->object_id, isset( $item->object ) ? $item->object : '' );
			if ( $term instanceof WP_Term && ! is_wp_error( $term ) ) return $this->fields->term( $term, 'name' );
		}
		if ( '' === trim( $source ) ) return $source;
		$title = $this->ui->get( $source, 'menu.item.' . absint( isset( $item->ID ) ? $item->ID : 0 ) . '.title' );
		return is_string( $title ) && '' !== trim( $title ) ? $title : $source;
	}

	private function is_supported_location( $args ) {
		$location = is_object( $args ) && isset( $args->theme_location ) ? $args->theme_location : '';
		return in_array( $location, array( 'main_menu_v1', 'footer_menu', 'guidance' ), true );
	}
}
