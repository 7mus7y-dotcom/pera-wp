<?php
/** Focused regressions for the current-main multilingual cleanup. */
function cleanup_expect( $condition, $label ) { if ( ! $condition ) { fwrite( STDERR, "FAIL {$label}\n" ); exit( 1 ); } }
define( 'ABSPATH', __DIR__ );
function wp_date( $format, $timestamp ) { return gmdate( $format, $timestamp ); }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function get_field( $field, $object, $formatted = true ) {
	if ( 'archive_h1' === $field ) return '<p><br></p>';
	if ( 'archive_heading' === $field ) return "  Canonical <strong>archive heading</strong>\n second line  ";
	return "  Canonical <strong>SEO</strong>\n\n title  ";
}
function get_term_meta() { return ''; }
function pera_ml_term_meta( $term, $field, $source ) {
	$GLOBALS['cleanup_term_lookups'][] = array( $field, $source );
	return "  Übersetzter <em>Titel</em>\n mit   Abstand ";
}
function pera_ml_term( $term ) { return isset( $GLOBALS['cleanup_term_translation'] ) ? $GLOBALS['cleanup_term_translation'] : '伊斯坦布尔'; }
class WP_Term { public $term_id = 7; public $taxonomy = 'district'; public $name = 'Istanbul'; public $slug = 'istanbul'; }
require dirname( dirname( dirname( __DIR__ ) ) ) . '/themes/hello-elementor-child/inc/multilingual-date.php';
$theme = dirname( dirname( dirname( __DIR__ ) ) ) . '/themes/hello-elementor-child';
require $theme . '/inc/seo-helpers.php';
$timestamp = gmmktime( 12, 0, 0, 3, 14, 2026 );
cleanup_expect( '14 March 2026' === pera_ml_format_property_date( $timestamp, 'en' ), 'English property date preserves j F Y output' );
cleanup_expect( '14 März 2026' === pera_ml_format_property_date( $timestamp, 'de' ), 'German property date uses a German month' );
cleanup_expect( '14 مارس 2026' === pera_ml_format_property_date( $timestamp, 'ar' ), 'Arabic property date uses an Arabic month' );
cleanup_expect( '2026年3月14日' === pera_ml_format_property_date( $timestamp, 'zh' ), 'Chinese property date uses a natural numeric representation' );
foreach ( array( 'de', 'ar', 'zh' ) as $language ) cleanup_expect( false === strpos( pera_ml_format_property_date( $timestamp, $language ), 'March' ), "{$language} date has no English month" );

$term = new WP_Term();
foreach ( array( '伊斯坦布尔', 'Istanbul', 'إسطنبول' ) as $translated_istanbul ) {
	$GLOBALS['cleanup_term_translation'] = $translated_istanbul;
	cleanup_expect( $translated_istanbul === pera_get_district_archive_location_name( $term ), 'district canonical Istanbul is returned without a suffix' );
	cleanup_expect( $translated_istanbul === pera_get_region_archive_location_name( $term ), 'region canonical Istanbul is returned without a suffix' );
}
cleanup_expect( 'Übersetzter Titel mit Abstand' === pera_get_property_archive_term_manual_seo_title( $term ), 'translated taxonomy SEO renders after normalization' );
cleanup_expect( array( 'meta:seo_title', "  Canonical <strong>SEO</strong>\n\n title  " ) === $GLOBALS['cleanup_term_lookups'][0], 'taxonomy SEO hashes the exact raw canonical markup and whitespace source' );
cleanup_expect( 'Übersetzter Titel mit Abstand' === pera_get_property_archive_term_manual_heading( $term ), 'markup-only earlier heading candidate is skipped for a later visible heading' );
cleanup_expect( array( 'meta:archive_heading', "  Canonical <strong>archive heading</strong>\n second line  " ) === $GLOBALS['cleanup_term_lookups'][1], 'later valid heading preserves its exact raw canonical source for hashing' );
$single = file_get_contents( $theme . '/single-property.php' );
cleanup_expect( false !== strpos( $single, "pera_ml_ui( 'This listing was last updated on', 'theme.template.single_property.last_updated_label' )" ), 'listing update label uses discoverable UI identity' );
cleanup_expect( false !== strpos( $single, "pera_ml_ui( 'Ref:', 'theme.template.single_property.reference_label' )" ), 'reference label uses discoverable UI identity' );
cleanup_expect( false !== strpos( $single, 'esc_html( $property_id )' ), 'canonical property reference remains unchanged' );
cleanup_expect( false !== strpos( $single, 'pera_ml_format_property_date(' ), 'single property uses deterministic date formatter' );
$units = file_get_contents( $theme . '/inc/v2-units-index.php' );
cleanup_expect( false !== strpos( $units, 'pera_ml_format_property_date(' ), 'unit price date uses deterministic date formatter' );
$pagination = file_get_contents( $theme . '/inc/property-pagination.php' );
cleanup_expect( false !== strpos( $pagination, "pera_ml_ui( 'Prev', 'theme.property.pagination.previous' )" ), 'previous pagination label uses UI identity' );
cleanup_expect( false !== strpos( $pagination, "pera_ml_ui( 'Next', 'theme.property.pagination.next' )" ), 'next pagination label uses UI identity' );
foreach ( array( 'archive-property.php', 'inc/ajax-property-archive.php' ) as $consumer ) cleanup_expect( false !== strpos( file_get_contents( $theme . '/' . $consumer ), 'pera_render_property_pagination(' ), "{$consumer} uses shared pagination" );
$seo = file_get_contents( $theme . '/inc/seo-helpers.php' );
cleanup_expect( false !== strpos( $seo, "pera_ml_term_meta( \$term, 'meta:seo_title', \$manual )" ), 'taxonomy SEO lookup receives raw canonical title source' );
cleanup_expect( false !== strpos( $seo, "pera_ml_term_meta( \$term, 'meta:seo_meta_description', \$manual )" ), 'taxonomy SEO lookup receives raw canonical description source' );
cleanup_expect( false !== strpos( $seo, 'return pera_seo_normalize_meta_text( $manual );' ), 'SEO normalization follows translation resolution' );
cleanup_expect( preg_match( '/\$normalized[\s\S]+\$name = function_exists[\s\S]+if \( in_array\( \$normalized/', $seo ) === 1, 'Istanbul decision derives from canonical name before translated display value' );
$fields = file_get_contents( dirname( __DIR__ ) . '/includes/class-fields.php' );
cleanup_expect( false === strpos( $fields, 'attachment_alt' ) && false === strpos( $fields, 'gallery_alt' ) && false === strpos( $fields, 'floor_plan_alt' ), 'media alt translation remains excluded' );
cleanup_expect( false === strpos( file_get_contents( $theme . '/inc/multilingual-date.php' ), 'pera_ml_ui(' ), 'dates do not enter UI/provider translation' );
echo "Pera ML multilingual cleanup tests passed\n";
