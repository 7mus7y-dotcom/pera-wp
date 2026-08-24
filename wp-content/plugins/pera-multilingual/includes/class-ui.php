<?php
defined( 'ABSPATH' ) || exit;

/** Stored, read-only-at-render-time translations for visitor-facing UI copy. */
final class Pera_ML_UI {
	private $router;
	private $storage;
	private $translator;
	private $registry;

	public function __construct( $router, $storage, $translator, $registry ) {
		$this->router = $router;
		$this->storage = $storage;
		$this->translator = $translator;
		$this->registry = $registry;
	}

	/**
	 * Return a current stored UI translation, or the canonical English source.
	 *
	 * This read path deliberately has no provider access. UI translations use
	 * object_type `ui`, a 60-bit SHA-256-derived object_id, and the normalized
	 * identity as field_key. Keeping both parts deterministic also makes a hash
	 * collision unable to merge two differently named strings under the table's
	 * (object_type, object_id, field_key, language) unique key.
	 */
	public function get( $source, $key = '', $language = null ) {
		$source = (string) $source;
		if ( '' === $source ) return $source;
		$this->registry->register( $source, $key );
		$language = null === $language || '' === $language ? $this->router->current_language() : sanitize_key( (string) $language );
		if ( 'en' === $language ) return $source;

		$identity = self::identity( $source, $key );
		$row = $this->storage->get( 'ui', self::object_id( $identity ), $identity, $language, $source );
		if ( ! is_array( $row ) || ! empty( $row['is_stale'] ) ) return $source;
		if ( isset( $row['status'] ) && 'current' !== $row['status'] ) return $source;
		return isset( $row['translated_text'] ) && is_string( $row['translated_text'] ) ? $row['translated_text'] : $source;
	}

	/** Import a reviewed UI translation without contacting a provider. */
	public function store( $key, $source, $language, $translation, $provider = 'manual' ) {
		$source = (string) $source;
		if ( '' === $source ) return false;
		$identity = self::identity( $source, $key );
		return $this->storage->put( 'ui', self::object_id( $identity ), $identity, $language, $source, $translation, $provider );
	}

	/** Explicitly generate and store one UI translation through the configured provider. */
	public function translate_and_store( $key, $source, $language, $provider_id = '' ) {
		$source = (string) $source;
		if ( '' === $source ) return false;
		$identity = self::identity( $source, $key );
		return $this->translator->translate_and_store( 'ui', self::object_id( $identity ), $identity, $language, $source, $provider_id );
	}

	/** Inventory registered canonical strings and their stored status by language. */
	public function inventory( array $languages = array( 'zh', 'ar', 'de' ) ) {
		$inventory = array();
		foreach ( $this->registry->all() as $identity => $item ) {
			$item['statuses'] = array();
			foreach ( $languages as $language ) $item['statuses'][ $language ] = $this->status( $item, $language );
			$inventory[ $identity ] = $item;
		}
		return $inventory;
	}

	public function status( array $item, $language ) {
		$row = $this->storage->get( 'ui', self::object_id( $item['identity'] ), $item['identity'], $language, $item['source'] );
		if ( ! is_array( $row ) || ! isset( $row['translated_text'] ) || ! is_string( $row['translated_text'] ) ) return 'missing';
		return ! empty( $row['is_stale'] ) || ( isset( $row['status'] ) && 'current' !== $row['status'] ) ? 'stale' : 'current';
	}

	/** Generate only a currently registered identity, using its latest canonical source. */
	public function translate_registered( $identity, $language, $provider_id = '' ) {
		$item = $this->registry->find( $identity );
		if ( ! $item ) return new WP_Error( 'pera_ml_invalid_ui_string', __( 'Invalid UI string.', 'pera-multilingual' ) );
		return $this->translator->translate_and_store( 'ui', self::object_id( $identity ), $identity, $language, $item['source'], $provider_id );
	}

	/** Build a stable, storage-safe identity; source-derived identities use its full SHA-256. */
	public static function identity( $source, $key = '' ) {
		if ( '' === trim( (string) $key ) ) return 'source:' . hash( 'sha256', (string) $source );
		$original_key = trim( (string) $key );
		$key = strtolower( $original_key );
		$key = preg_replace( '/[^a-z0-9_-]+/', '_', $key );
		$key = trim( preg_replace( '/_+/', '_', $key ), '_-' );
		if ( '' === $key ) $key = 'ui';
		if ( strlen( $key ) > 153 ) $key = substr( $key, 0, 153 );
		// The original-key digest prevents normalization aliases (for example a.b
		// and a_b) from sharing a translation identity.
		return 'key:' . $key . '_' . substr( hash( 'sha256', $original_key ), 0, 12 );
	}

	/** Return a positive integer that is portable across 64-bit PHP and BIGINT UNSIGNED. */
	public static function object_id( $identity ) {
		return hexdec( substr( hash( 'sha256', (string) $identity ), 0, 15 ) );
	}
}
