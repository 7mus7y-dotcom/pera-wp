<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$card = get_query_var( 'pera_latest_offer_card', array() );
if ( ! is_array( $card ) || empty( $card ) ) {
	return;
}

$property_title = isset( $card['property_title'] ) ? (string) $card['property_title'] : '';
$property_url   = isset( $card['property_url'] ) ? (string) $card['property_url'] : '';
$image_id       = isset( $card['image_id'] ) ? (int) $card['image_id'] : 0;
$region_name    = isset( $card['region_name'] ) ? (string) $card['region_name'] : '';
$district_name  = isset( $card['district_name'] ) ? (string) $card['district_name'] : '';
$floor          = isset( $card['floor'] ) ? (string) $card['floor'] : '';
$net_sqm        = isset( $card['net_sqm'] ) ? (string) $card['net_sqm'] : '—';
$gross_sqm      = isset( $card['gross_sqm'] ) ? (string) $card['gross_sqm'] : '—';
$list_price     = isset( $card['list_price'] ) ? (string) $card['list_price'] : '—';
$cash_price     = isset( $card['cash_price'] ) ? (string) $card['cash_price'] : '—';
$notes          = isset( $card['notes'] ) ? (string) $card['notes'] : '';
$floor_plan_url = isset( $card['floor_plan_url'] ) ? (string) $card['floor_plan_url'] : '';
$map_url        = isset( $card['map_url'] ) ? (string) $card['map_url'] : '';
?>
<article class="citizenship-map-popup" aria-label="<?php echo esc_attr__( 'Latest offer popup', 'hello-elementor-child' ); ?>">
	<?php if ( $image_id > 0 ) : ?>
		<?php
		echo wp_get_attachment_image(
			$image_id,
			'medium_large',
			false,
			array(
				'class'    => 'citizenship-map-popup__thumb',
				'alt'      => esc_attr( $property_title ),
				'loading'  => 'lazy',
				'decoding' => 'async',
			)
		);
		?>
	<?php endif; ?>

	<h4 class="citizenship-map-popup__title">
		<?php if ( '' !== $property_url ) : ?>
			<a href="<?php echo esc_url( $property_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $property_title ); ?></a>
		<?php else : ?>
			<?php echo esc_html( $property_title ); ?>
		<?php endif; ?>
	</h4>

	<?php if ( '' !== $district_name || '' !== $region_name ) : ?>
		<p class="citizenship-map-popup__meta">
			<?php echo esc_html( implode( ', ', array_filter( array( $district_name, $region_name ) ) ) ); ?>
		</p>
	<?php endif; ?>

	<div class="citizenship-map-popup__pricing">
		<p class="citizenship-map-popup__meta"><strong><?php esc_html_e( 'List', 'hello-elementor-child' ); ?>:</strong> <?php echo esc_html( $list_price ); ?></p>
		<p class="citizenship-map-popup__meta"><strong><?php esc_html_e( 'Cash', 'hello-elementor-child' ); ?>:</strong> <?php echo esc_html( $cash_price ); ?></p>
	</div>

	<p class="citizenship-map-popup__meta">
		<?php echo esc_html( sprintf( __( 'Net: %s', 'hello-elementor-child' ), $net_sqm ) ); ?>
		<span aria-hidden="true">•</span>
		<?php echo esc_html( sprintf( __( 'Gross: %s', 'hello-elementor-child' ), $gross_sqm ) ); ?>
		<?php if ( '' !== $floor ) : ?>
			<span aria-hidden="true">•</span>
			<?php echo esc_html( $floor ); ?>
		<?php endif; ?>
	</p>

	<?php if ( '' !== $notes ) : ?>
		<p class="citizenship-map-popup__notes"><?php echo nl2br( esc_html( $notes ) ); ?></p>
	<?php endif; ?>

	<div class="citizenship-map-popup__actions">
		<?php if ( '' !== $floor_plan_url ) : ?>
			<a class="citizenship-map-popup__cta" href="<?php echo esc_url( $floor_plan_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Floor plan', 'hello-elementor-child' ); ?></a>
		<?php endif; ?>
		<?php if ( '' !== $map_url ) : ?>
			<a class="citizenship-map-popup__cta" href="<?php echo esc_url( $map_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Map', 'hello-elementor-child' ); ?></a>
		<?php endif; ?>
		<?php if ( '' !== $property_url ) : ?>
			<a class="citizenship-map-popup__cta" href="<?php echo esc_url( $property_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Project details', 'hello-elementor-child' ); ?></a>
		<?php endif; ?>
	</div>
</article>
