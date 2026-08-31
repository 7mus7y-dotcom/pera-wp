<?php
/** Focused regression coverage for shared V2 property-card pricing semantics. */

define( 'ABSPATH', __DIR__ );

function add_action() {}
function get_transient() { return false; }
function number_format_i18n( $number ) { return number_format( $number ); }
function pera_ml_ui( $source ) { return $source; }

function expect_card_pricing( $condition, $label ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL {$label}\n" );
		exit( 1 );
	}
}

$theme = dirname( __DIR__ );
require_once $theme . '/inc/v2-units-index.php';

$price_for = function( array $rows, bool $has_project, bool $has_resale ): string {
	$totals = pera_v2_units_aggregate( $rows );
	return pera_v2_units_format_price_text(
		$totals['price_min'],
		$totals['price_max'],
		$has_project && ! $has_resale
	);
};

$row = function( $min, $max ): array {
	return array(
		'v2_bedrooms'     => 2,
		'v2_price_usd_min' => $min,
		'v2_price_usd_max' => $max,
	);
};

expect_card_pricing( 'From $250,000' === $price_for( array( $row( 250000, 0 ) ), true, false ), 'project minimum uses From' );
expect_card_pricing( '$250,000' === $price_for( array( $row( 250000, 0 ) ), false, true ), 'resale minimum is fixed' );
expect_card_pricing( '$275,000' === $price_for( array( $row( 0, 275000 ) ), false, true ), 'resale maximum-only value is present' );
expect_card_pricing( '$300,000' === $price_for( array( $row( 300000, 300000 ) ), false, true ), 'equal resale bounds render once' );
expect_card_pricing( '$300,000–$350,000' === $price_for( array( $row( 300000, 350000 ) ), false, true ), 'different resale bounds retain range' );
expect_card_pricing( '$300,000–$350,000' === $price_for( array( $row( 350000, 300000 ) ), false, true ), 'reversed bounds render ascending' );
expect_card_pricing( '$250,000' === $price_for( array( $row( 250000, 0 ) ), true, true ), 'resale overrides project pricing' );
expect_card_pricing( '' === $price_for( array( $row( 0, 0 ) ), false, true ), 'zero price remains omitted' );

$card = file_get_contents( $theme . '/parts/property-card-v2.php' );
$home = file_get_contents( $theme . '/parts/home-featured-property.php' );
$single = file_get_contents( $theme . '/single-property.php' );
$archive = file_get_contents( $theme . '/archive-property.php' );
$ajax = file_get_contents( $theme . '/inc/ajax-property-archive.php' );

expect_card_pricing( false !== strpos( $card, 'pera_v2_get_units_rows( $post_id )' ), 'card uses shared row reader' );
expect_card_pricing( false !== strpos( $card, 'pera_v2_units_aggregate( $units, $v2_beds_selected )' ), 'card uses shared bedroom-aware aggregation' );
expect_card_pricing( false !== strpos( $card, '$show_project_price = $has_project && ! $has_resale;' ), 'card separates effective pricing semantics' );
expect_card_pricing( strpos( $card, "isset( \$specials_by_slug['resales'] )" ) < strpos( $card, "isset( \$specials_by_slug['project'] )" ), 'resale badge has precedence' );
expect_card_pricing( false !== strpos( $card, 'if ( $price_txt )' ), 'empty price only omits price element' );
expect_card_pricing( false !== strpos( $card, 'property-card__updated' ), 'footer update content remains independent' );
expect_card_pricing( false !== strpos( $archive, 'pera_render_property_card(' ), 'SSR archive uses shared renderer' );
expect_card_pricing( false !== strpos( $ajax, 'pera_render_property_card(' ), 'AJAX archive uses shared renderer' );
expect_card_pricing( false !== strpos( $home, '$is_project  = $has_project && ! $has_resale;' ), 'featured component gives resale precedence' );
expect_card_pricing( false !== strpos( $single, "'is_project' => \$is_project && ! \$is_resale" ), 'single helper receives effective project flag' );
expect_card_pricing( false !== strpos( $single, 'pera_render_property_card(' ), 'related properties use shared renderer' );

echo "Property-card resale pricing tests passed\n";
