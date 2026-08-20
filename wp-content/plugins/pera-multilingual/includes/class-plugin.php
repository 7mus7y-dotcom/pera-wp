<?php
defined( 'ABSPATH' ) || exit;

final class Pera_ML_Plugin {
	private static $instance; private $registry; private $storage; private $router; private $content; private $seo; private $translator; private $fields; private $vocabulary; private $ajax;
	public static function instance() { if ( ! self::$instance ) self::$instance = new self(); return self::$instance; }
	private function __construct() {
		$this->registry = new Pera_ML_Language_Registry(); $this->storage = new Pera_ML_Storage();
		$this->router = new Pera_ML_Router( $this->registry ); $this->content = new Pera_ML_Content( $this->registry, $this->router, $this->storage );
		$this->vocabulary = new Pera_ML_Vocabulary(); $this->fields = new Pera_ML_Fields( $this->router, $this->storage, $this->vocabulary ); $this->ajax = new Pera_ML_Ajax( $this->registry, $this->router );
		$this->seo = new Pera_ML_SEO( $this->registry, $this->router, $this->storage ); $this->translator = new Pera_ML_Translator( $this->registry, $this->storage );
	}
	public function boot() { $this->router->hooks(); $this->content->hooks(); $this->fields->hooks(); $this->seo->hooks(); $this->ajax->hooks(); if ( is_admin() ) ( new Pera_ML_Admin( $this->registry ) )->hooks(); }
	public static function activate() { Pera_ML_Storage::install(); update_option( 'pera_ml_db_version', PERA_ML_VERSION ); flush_rewrite_rules( false ); }
	public static function deactivate() { flush_rewrite_rules( false ); }
	public function storage() { return $this->storage; } public function content() { return $this->content; } public function translator() { return $this->translator; } public function router() { return $this->router; }
	public function fields() { return $this->fields; } public function vocabulary() { return $this->vocabulary; }
}
