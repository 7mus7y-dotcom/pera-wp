<?php
defined( 'ABSPATH' ) || exit;

interface Pera_ML_Provider_Interface {
	public function id();
	/** @return string|WP_Error */
	public function translate( $source, array $context );
}
