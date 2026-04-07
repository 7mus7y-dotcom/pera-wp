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
$type           = isset( $card['type'] ) ? (string) $card['type'] : '—';
$floor          = isset( $card['floor'] ) ? (string) $card['floor'] : '';
$net_sqm        = isset( $card['net_sqm'] ) ? (string) $card['net_sqm'] : '—';
$gross_sqm      = isset( $card['gross_sqm'] ) ? (string) $card['gross_sqm'] : '—';
$list_price     = isset( $card['list_price'] ) ? (string) $card['list_price'] : '—';
$cash_price     = isset( $card['cash_price'] ) ? (string) $card['cash_price'] : '—';
$notes          = isset( $card['notes'] ) ? (string) $card['notes'] : '';
$floor_plan_url = isset( $card['floor_plan_url'] ) ? (string) $card['floor_plan_url'] : '';
?>
<article class="pera-latest-offer-card" aria-label="<?php echo esc_attr__( 'Latest offer card', 'hello-elementor-child' ); ?>">
	<h3 class="pera-latest-offer-card__title">
		<?php if ( '' !== $property_url ) : ?>
			<a href="<?php echo esc_url( $property_url ); ?>"><?php echo esc_html( $property_title ); ?></a>
		<?php else : ?>
			<?php echo esc_html( $property_title ); ?>
		<?php endif; ?>
	</h3>

	<div class="pera-latest-offer-card__summary">
		<p class="pera-latest-offer-card__heading"><?php esc_html_e( 'Apartment details', 'hello-elementor-child' ); ?></p>
		<p class="pera-latest-offer-card__list"><?php echo esc_html( sprintf( __( 'List price: %s', 'hello-elementor-child' ), $list_price ) ); ?></p>
		<p class="pera-latest-offer-card__cash"><?php echo esc_html( sprintf( __( 'Cash price: %s', 'hello-elementor-child' ), $cash_price ) ); ?></p>
		<p class="pera-latest-offer-card__meta">
			<span><?php echo esc_html( sprintf( __( 'Type: %s', 'hello-elementor-child' ), $type ) ); ?></span>
			<span aria-hidden="true">•</span>
			<span><?php echo esc_html( sprintf( __( 'Net: %s', 'hello-elementor-child' ), $net_sqm ) ); ?></span>
			<span aria-hidden="true">•</span>
			<span><?php echo esc_html( sprintf( __( 'Gross: %s', 'hello-elementor-child' ), $gross_sqm ) ); ?></span>
			<?php if ( '' !== $floor ) : ?>
				<span aria-hidden="true">•</span>
				<span><?php echo esc_html( $floor ); ?></span>
			<?php endif; ?>
		</p>
	</div>

	<div class="pera-latest-offer-card__utility">
		<?php if ( '' !== $floor_plan_url ) : ?>
			<a class="pera-latest-offer-card__pill" href="<?php echo esc_url( $floor_plan_url ); ?>" target="_blank" rel="noopener noreferrer">
				<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 3h8v2H5v6H3V3zm10 0h8v8h-2V5h-6V3zM3 13h2v6h6v2H3v-8zm16 0h2v8h-8v-2h6v-6z"/></svg>
				<span><?php esc_html_e( 'Floor plan', 'hello-elementor-child' ); ?></span>
			</a>
		<?php endif; ?>

		<?php if ( '' !== $notes ) : ?>
			<details class="pera-latest-offer-card__note">
				<summary class="pera-latest-offer-card__pill">
					<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 3h14a2 2 0 012 2v14l-4-3H5a2 2 0 01-2-2V5a2 2 0 012-2zm2 5v2h10V8H7zm0 4v2h7v-2H7z"/></svg>
					<span><?php esc_html_e( 'Notes', 'hello-elementor-child' ); ?></span>
				</summary>
				<div class="pera-latest-offer-card__note-panel"><?php echo nl2br( esc_html( $notes ) ); ?></div>
			</details>
		<?php endif; ?>

		<?php if ( '' !== $property_url ) : ?>
			<a class="pera-latest-offer-card__pill pera-latest-offer-card__pill--blue" href="<?php echo esc_url( $property_url ); ?>" target="_blank" rel="noopener noreferrer">
				<span><?php esc_html_e( 'Project details', 'hello-elementor-child' ); ?></span>
				<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14 3h7v7h-2V6.41l-9.29 9.3-1.42-1.42 9.3-9.29H14V3z"/><path d="M5 5h6v2H7v10h10v-4h2v6H5V5z"/></svg>
			</a>
		<?php endif; ?>
	</div>
</article>
