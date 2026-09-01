<?php
/** Focused property archive settings contract regressions. */
define( 'ABSPATH', __DIR__ );
function archive_expect( $expected, $actual, $label ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAIL {$label}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" ); exit( 1 ); } }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function apply_filters( $hook, $value ) { return $value; }
function get_post_type( $id ) { $post = get_post( $id ); return $post ? $post->post_type : false; }
function get_post( $id ) { return isset( $GLOBALS['archive_posts'][ $id ] ) ? $GLOBALS['archive_posts'][ $id ] : null; }
function get_page_by_path( $slug, $output, $type ) { return 'property-archive-seo-settings' === $slug && 'page' === $type ? get_post( 91 ) : null; }
function get_post_meta( $id, $key ) { return isset( $GLOBALS['archive_meta'][ $id ][ $key ] ) ? $GLOBALS['archive_meta'][ $id ][ $key ] : ''; }
function get_field( $name, $id, $formatted = true ) { return get_post_meta( $id, $name ); }
class WP_Post {}
class WP_Term {}
final class Archive_Storage {
	public $rows = array();
	public function get( $type, $id, $field, $language, $source ) {
		$key = "{$id}|{$field}|{$language}";
		if ( ! isset( $this->rows[ $key ] ) ) return null;
		$row = $this->rows[ $key ];
		$row['is_stale'] = ! hash_equals( $row['source_hash'], hash( 'sha256', $source ) );
		return $row;
	}
}
final class Archive_Vocabulary {}
require dirname( __DIR__ ) . '/includes/class-fields.php';
require dirname( __DIR__ ) . '/includes/class-translation-status.php';

$archive = (object) array( 'ID' => 91, 'post_type' => 'page', 'post_name' => 'property-archive-seo-settings' );
$normal  = (object) array( 'ID' => 92, 'post_type' => 'page', 'post_name' => 'ordinary-page' );
$GLOBALS['archive_posts'] = array( 91 => $archive, 92 => $normal );
$archive_fields = array( 'archive_h1', 'archive_subtitle', 'archive_intro_content', 'archive_bottom_content', 'archive_cta_heading', 'archive_cta_text', 'archive_whatsapp_message' );
$GLOBALS['archive_meta'][91] = array_combine( $archive_fields, array( 'Raw H1', 'Raw subtitle', '<h2>Raw intro</h2>', '', 'Raw CTA', 'Raw CTA text', 'Hello & welcome' ) );

$storage = new Archive_Storage();
$router = new class { public function current_language() { return 'zh'; } };
$fields = new Pera_ML_Fields( $router, $storage, new Archive_Vocabulary() );
archive_expect( 91, Pera_ML_Fields::archive_settings_object_id(), 'the settings page is discovered dynamically by its established slug' );
archive_expect( true, Pera_ML_Fields::is_archive_settings_object( 91 ), 'the real settings page is identified' );
archive_expect( false, Pera_ML_Fields::is_archive_settings_object( 92 ), 'an ordinary page is not identified as archive settings' );
foreach ( $archive_fields as $field ) archive_expect( true, in_array( $field, $fields->approved_for_object( 91, 'page' ), true ), "{$field} belongs to the archive settings contract" );
foreach ( $archive_fields as $field ) archive_expect( false, in_array( $field, $fields->approved_for_object( 92, 'page' ), true ), "{$field} is not inherited by ordinary pages" );

$status = new Pera_ML_Translation_Status( $storage );
$sources = $status->applicable_sources( 91, 'page' );
foreach ( $archive_fields as $field ) archive_expect( 'archive_bottom_content' !== $field, isset( $sources[ 'meta:' . $field ] ), "health includes only populated {$field}" );
archive_expect( 'Hello & welcome', $sources['meta:archive_whatsapp_message'], 'health hashes the canonical raw WhatsApp message' );

$current = array( 'translated_text' => '你好 & 欢迎', 'status' => 'current', 'source_hash' => hash( 'sha256', 'Hello & welcome' ) );
$storage->rows['91|meta:archive_whatsapp_message|zh'] = $current;
archive_expect( '你好 & 欢迎', $fields->get( 91, 'archive_whatsapp_message', 'Hello & welcome' ), 'current runtime translation is selected before URL encoding' );
archive_expect( '%E4%BD%A0%E5%A5%BD%20%26%20%E6%AC%A2%E8%BF%8E', rawurlencode( $fields->get( 91, 'archive_whatsapp_message', 'Hello & welcome' ) ), 'the selected translated message can then be URL encoded' );
foreach ( array( null, array( 'translated_text'=>'', 'status'=>'current', 'source_hash'=>$current['source_hash'] ), array( 'translated_text'=>'旧', 'status'=>'stale', 'source_hash'=>$current['source_hash'] ), array( 'translated_text'=>'待定', 'status'=>'pending', 'source_hash'=>$current['source_hash'] ), array( 'translated_text'=>'旧源', 'status'=>'current', 'source_hash'=>hash( 'sha256', 'Old source' ) ) ) as $row ) {
	if ( null === $row ) unset( $storage->rows['91|meta:archive_whatsapp_message|zh'] ); else $storage->rows['91|meta:archive_whatsapp_message|zh'] = $row;
	archive_expect( 'Hello & welcome', $fields->get( 91, 'archive_whatsapp_message', 'Hello & welcome' ), 'missing, blank, stale, or non-current translations fall back to raw English' );
}

$template = file_get_contents( dirname( __DIR__, 3 ) . '/themes/hello-elementor-child/archive-property.php' );
foreach ( $archive_fields as $field ) archive_expect( true, false !== strpos( $template, "property_archive_get_field( '{$field}' )" ), "archive-property reads {$field} through the translated ACF pathway" );
archive_expect( true, strpos( $template, "pera_get_whatsapp_url( \$archive_whatsapp_message )" ) > strpos( $template, "property_archive_get_field( 'archive_whatsapp_message' )" ), 'WhatsApp URL construction happens after translated field selection' );
echo "Pera ML archive settings contract tests passed\n";
