<?php
defined( 'ABSPATH' ) || exit;

final class Pera_ML_Mock_Provider implements Pera_ML_Provider_Interface {
	public function id() { return 'mock'; }
	public function translate( $source, array $context ) {
		$language = isset( $context['target_language'] ) ? sanitize_key( $context['target_language'] ) : 'xx';
		return '[' . $language . '] ' . $source;
	}
}
