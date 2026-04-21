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
	<?php if ( '' !== $region_name || '' !== $district_name ) : ?>
		<div class="pera-latest-offer-card__pills">
			<?php if ( '' !== $district_name ) : ?>
				<span class="pill pill--green"><?php echo esc_html( $district_name ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $region_name ) : ?>
				<span class="pill pill--green"><?php echo esc_html( $region_name ); ?></span>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<h4 class="pera-latest-offer-card__title">
		<?php if ( '' !== $property_url ) : ?>
			<a href="<?php echo esc_url( $property_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $property_title ); ?></a>
		<?php else : ?>
			<?php echo esc_html( $property_title ); ?>
		<?php endif; ?>
	</h4>

	<div class="pera-latest-offer-card__summary">
		<p class="pera-latest-offer-card__list"><?php echo esc_html( sprintf( __( 'List price: %s', 'hello-elementor-child' ), $list_price ) ); ?></p>
		<p class="pera-latest-offer-card__cash"><?php echo esc_html( sprintf( __( 'Cash price: %s', 'hello-elementor-child' ), $cash_price ) ); ?></p>
		<p class="pera-latest-offer-card__meta">
			<span><?php echo esc_html( sprintf( __( 'Net: %s', 'hello-elementor-child' ), $net_sqm ) ); ?></span>
			<span aria-hidden="true">•</span>
			<span><?php echo esc_html( sprintf( __( 'Gross: %s', 'hello-elementor-child' ), $gross_sqm ) ); ?></span>
			<?php if ( '' !== $floor ) : ?>
				<span aria-hidden="true">•</span>
				<span><?php echo esc_html( $floor ); ?></span>
			<?php endif; ?>
		</p>
	</div>

	<?php if ( $image_id > 0 ) : ?>
		<?php
		echo wp_get_attachment_image(
			$image_id,
			'medium_large',
			false,
			array(
				'class'    => 'pera-latest-offer-card__img',
				'alt'      => esc_attr( $property_title ),
				'loading'  => 'lazy',
				'decoding' => 'async',
			)
		);
		?>
	<?php endif; ?>

	<?php if ( '' !== $notes ) : ?>
		<p class="pera-latest-offer-card__meta"><?php echo nl2br( esc_html( $notes ) ); ?></p>
	<?php endif; ?>

	<div class="pera-latest-offer-card__utility">
		<?php if ( '' !== $floor_plan_url ) : ?>
			<a class="pill pill--subtle pera-latest-offer-card__pill" href="<?php echo esc_url( $floor_plan_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Floor plan', 'hello-elementor-child' ); ?></a>
		<?php endif; ?>
		<?php if ( '' !== $map_url ) : ?>
			<a class="pill pill--subtle pera-latest-offer-card__pill" href="<?php echo esc_url( $map_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Map', 'hello-elementor-child' ); ?></a>
		<?php endif; ?>
		<?php if ( '' !== $property_url ) : ?>
			<a class="pill pill--subtle pera-latest-offer-card__pill pera-latest-offer-card__pill--blue" href="<?php echo esc_url( $property_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Project details', 'hello-elementor-child' ); ?></a>
		<?php endif; ?>
	</div>
</article>
