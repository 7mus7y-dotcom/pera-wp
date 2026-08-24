<?php
/** Focused standalone UI-string service tests: php tests/ui-string-test.php */

define( 'ABSPATH', __DIR__ );
$GLOBALS['ui_registry_option'] = array();
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function apply_filters( $tag, $value ) { return $value; }
function get_option( $key, $default = false ) { return 'pera_ml_ui_registry' === $key ? $GLOBALS['ui_registry_option'] : $default; }
function update_option( $key, $value, $autoload = null ) { if ( 'pera_ml_ui_registry' === $key ) $GLOBALS['ui_registry_option'] = $value; return true; }
function current_time() { return '2026-08-24 12:00:00'; }
function __( $value ) { return $value; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WP_Error { private $code; public function __construct( $code ) { $this->code = $code; } public function get_error_code() { return $this->code; } }
function expect_same( $expected, $actual, $label ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL {$label}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

final class UI_Test_Router {
	public $language = 'en';
	public function current_language() { return $this->language; }
}
final class UI_Test_Storage {
	public $rows = array();
	public $puts = array();
	private function row_key( $type, $id, $field, $language ) { return $type . '|' . $id . '|' . $field . '|' . $language; }
	public function get( $type, $id, $field, $language, $source = '' ) {
		$key = $this->row_key( $type, $id, $field, $language );
		if ( ! isset( $this->rows[ $key ] ) ) return null;
		$row = $this->rows[ $key ];
		$row['is_stale'] = $row['source_hash'] !== hash( 'sha256', $source );
		return $row;
	}
	public function put( $type, $id, $field, $language, $source, $translation, $provider = 'manual' ) {
		$this->puts[] = func_get_args();
		$this->rows[ $this->row_key( $type, $id, $field, $language ) ] = array(
			'source_hash' => hash( 'sha256', $source ), 'translated_text' => $translation, 'status' => 'current',
		);
		return true;
	}
}
final class UI_Test_Translator {
	public $calls = array();
	public function translate_and_store() { $this->calls[] = func_get_args(); return 'generated'; }
}

require dirname( __DIR__ ) . '/includes/class-ui.php';
require dirname( __DIR__ ) . '/includes/class-ui-registry.php';
require dirname( __DIR__ ) . '/includes/class-vocabulary.php';

$router = new UI_Test_Router();
$storage = new UI_Test_Storage();
$translator = new UI_Test_Translator();
$registry = new Pera_ML_UI_Registry();
$ui = new Pera_ML_UI( $router, $storage, $translator, $registry );
$source = 'No properties found.';

expect_same( $source, $ui->get( $source, 'property_archive.no_results' ), 'English source passthrough' );
expect_same( '', $ui->get( '', 'empty' ), 'empty source passthrough' );
expect_same( 0, count( $translator->calls ), 'English reads do not call provider path' );
$registered = $ui->inventory();
$registered_item = reset( $registered );
expect_same( array( 'zh' => 'missing', 'ar' => 'missing', 'de' => 'missing' ), $registered_item['statuses'], 'registered untranslated string is missing in every target language' );
expect_same( 1, count( $registered ), 'arbitrary unregistered strings do not enter inventory' );

foreach ( array( 'zh' => '未找到房产。', 'ar' => 'لم يتم العثور على عقارات.', 'de' => 'Keine Immobilien gefunden.' ) as $language => $translation ) {
	$ui->store( 'property_archive.no_results', $source, $language, $translation );
	$router->language = $language;
	expect_same( $translation, $ui->get( $source, 'property_archive.no_results' ), "{$language} stored UI string" );
}
expect_same( 'ui', $storage->puts[0][0], 'UI rows use ui object type' );
$archive_identity = Pera_ML_UI::identity( $source, 'property_archive.no_results' );
expect_same( $archive_identity, $storage->puts[0][2], 'semantic key is normalized and namespaced' );
expect_same( array( 'zh' => 'current', 'ar' => 'current', 'de' => 'current' ), $ui->inventory()[ $archive_identity ]['statuses'], 'stored translations are current' );

$row_count = count( $storage->rows );
$ui->get( 'No matching properties found.', 'property_archive.no_results', 'en' );
$changed = $ui->inventory()[ $archive_identity ];
expect_same( array( 'zh' => 'stale', 'ar' => 'stale', 'de' => 'stale' ), $changed['statuses'], 'changed canonical source makes every old translation stale' );
expect_same( $row_count, count( $storage->rows ), 'stale translation rows remain stored' );
$ui->get( $source, 'property_archive.no_results', 'en' );

$router->language = 'zh';
expect_same( 'Missing copy', $ui->get( 'Missing copy', 'missing.copy' ), 'absent translation falls back to English' );
$implicit_a = Pera_ML_UI::identity( $source );
$implicit_b = Pera_ML_UI::identity( $source );
expect_same( $implicit_a, $implicit_b, 'implicit source identity is deterministic' );
$ui->store( '', $source, 'zh', '隐式翻译' );
expect_same( '隐式翻译', $ui->get( $source, '', 'zh' ), 'implicit identity reads its stored translation' );

$ui->store( 'search.empty', $source, 'de', 'Keine Suchergebnisse.' );
$ui->store( 'property_archive.no_results', $source, 'de', 'Keine Immobilien gefunden.' );
expect_same( 'Keine Suchergebnisse.', $ui->get( $source, 'search.empty', 'de' ), 'first explicit identity is independent' );
expect_same( 'Keine Immobilien gefunden.', $ui->get( $source, 'property_archive.no_results', 'de' ), 'second explicit identity is independent' );
expect_same( 0, count( $translator->calls ), 'all frontend reads avoid the provider path' );

expect_same( 'generated', $ui->translate_and_store( 'search.empty', $source, 'zh', 'mock' ), 'explicit generation delegates to translator' );
$search_identity = Pera_ML_UI::identity( $source, 'search.empty' );
expect_same( array( 'ui', Pera_ML_UI::object_id( $search_identity ), $search_identity, 'zh', $source, 'mock' ), $translator->calls[0], 'generation uses the same UI row identity' );

$before = count( $translator->calls );
$ui->translate_registered( $archive_identity, 'ar' );
expect_same( $before + 1, count( $translator->calls ), 'individual generation invokes one language only' );
expect_same( 'ar', $translator->calls[ $before ][3], 'individual generation targets requested language' );

$vocabulary = new Pera_ML_Vocabulary();
expect_same( '公寓', $vocabulary->translate( 'Apartment', 'zh' ), 'controlled vocabulary remains unchanged' );
expect_same( 'Apartment', $vocabulary->translate( 'Apartment', 'en' ), 'English vocabulary passthrough remains unchanged' );

echo "Pera ML UI-string tests passed\n";
