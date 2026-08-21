<?php
defined( 'ABSPATH' ) || exit;

final class Pera_ML_Language_Registry {
	/** @return array<string,array<string,mixed>> */
	public function all() {
		$languages = array(
			'en' => array( 'code' => 'en', 'name' => 'English', 'native_name' => 'English', 'compact_name' => 'EN', 'prefix' => '', 'direction' => 'ltr', 'enabled' => true, 'source' => true ),
			'de' => array(
				'code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'compact_name' => 'DE', 'prefix' => 'de',
				'direction' => 'ltr', 'enabled' => true, 'source' => false, 'hreflang' => 'de-DE',
				'instructions' => 'Translate into natural, professional German suitable for a German-speaking property buyer or investor. Use standard German as used in Germany. Preserve proper nouns, Turkish place names, project names, company names and glossary-protected terms exactly as instructed. Use natural German real-estate and investment terminology rather than literal English calques.',
			),
			'zh' => array( 'code' => 'zh', 'name' => 'Simplified Chinese', 'native_name' => '简体中文', 'compact_name' => '中文', 'prefix' => 'zh', 'direction' => 'ltr', 'enabled' => true, 'source' => false ),
			'ar' => array( 'code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'compact_name' => 'AR', 'prefix' => 'ar', 'direction' => 'rtl', 'enabled' => true, 'source' => false ),
		);
		$enabled = get_option( 'pera_ml_enabled_languages', array( 'en', 'zh', 'ar', 'de' ) );
		$enabled = is_array( $enabled ) ? array_map( 'sanitize_key', $enabled ) : array( 'en' );
		$enabled[] = 'en';
		foreach ( $languages as $code => &$language ) {
			$language['enabled'] = in_array( $code, $enabled, true );
		}
		unset( $language );
		return apply_filters( 'pera_ml_languages', $languages );
	}

	public function get( $code ) {
		$languages = $this->all();
		return isset( $languages[ $code ] ) ? $languages[ $code ] : null;
	}

	/** @return array<string,array<string,mixed>> */
	public function enabled() {
		return array_filter( $this->all(), static function ( $language ) { return ! empty( $language['enabled'] ); } );
	}

	public function from_prefix( $prefix ) {
		foreach ( $this->enabled() as $language ) {
			if ( ! empty( $language['prefix'] ) && $language['prefix'] === $prefix ) {
				return $language;
			}
		}
		return null;
	}
}
