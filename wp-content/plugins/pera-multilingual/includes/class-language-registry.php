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
			'ru' => array(
				'code' => 'ru', 'name' => 'Russian', 'native_name' => 'Русский', 'compact_name' => 'RU', 'prefix' => 'ru',
				'direction' => 'ltr', 'enabled' => true, 'source' => false, 'hreflang' => 'ru',
				'instructions' => 'Translate into natural, professional Russian suitable for a Russian-speaking property buyer or investor. Preserve proper nouns, Turkish place names, project names, company names and glossary-protected terms exactly as instructed. Use natural Russian real-estate and investment terminology rather than literal English calques.',
			),
			'tr' => array(
				'code' => 'tr', 'name' => 'Turkish', 'native_name' => 'Türkçe', 'compact_name' => 'TR', 'prefix' => 'tr',
				'direction' => 'ltr', 'enabled' => true, 'source' => false, 'hreflang' => 'tr-TR',
				'instructions' => 'Translate into natural, professional Turkish suitable for a Turkish property developer, buyer or investor. Preserve proper nouns, project names, company names and glossary-protected terms exactly as instructed. Use natural Turkish real-estate and commercial terminology rather than literal English calques.',
			),
		);
		$enabled = get_option( 'pera_ml_enabled_languages', array( 'en', 'zh', 'ar', 'de', 'ru', 'tr' ) );
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

	/** Languages advertised through public discovery surfaces such as selectors and hreflang. */
	public function publicly_available() {
		return array_filter( $this->enabled(), array( $this, 'is_publicly_available' ) );
	}

	public function is_publicly_available( $language ) {
		if ( 'ru' === $language['code'] ) return defined( 'PERA_ML_PUBLIC_RUSSIAN_ENABLED' ) && PERA_ML_PUBLIC_RUSSIAN_ENABLED;
		if ( 'tr' === $language['code'] ) return defined( 'PERA_ML_PUBLIC_TURKISH_ENABLED' ) && PERA_ML_PUBLIC_TURKISH_ENABLED;
		return true;
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
