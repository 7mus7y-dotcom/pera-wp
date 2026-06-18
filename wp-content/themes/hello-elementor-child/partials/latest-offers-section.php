<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args = is_array( $args ?? null ) ? $args : array();

$section_class      = isset( $args['section_class'] ) ? trim( (string) $args['section_class'] ) : 'section';
$section_id         = isset( $args['section_id'] ) ? trim( (string) $args['section_id'] ) : '';
$aria_label         = isset( $args['aria_label'] ) ? trim( (string) $args['aria_label'] ) : '';
$kicker             = isset( $args['kicker'] ) ? trim( (string) $args['kicker'] ) : '';
$title              = isset( $args['title'] ) ? trim( (string) $args['title'] ) : '';
$description        = isset( $args['description'] ) ? trim( (string) $args['description'] ) : '';
$slider_id          = isset( $args['slider_id'] ) ? trim( (string) $args['slider_id'] ) : 'latest-offers-slider';
$cards              = isset( $args['cards'] ) && is_array( $args['cards'] ) ? $args['cards'] : array();
// Suffix only, for example "home" or "citizenship"; the shared class prefix is added below.
$card_list_modifier = isset( $args['card_list_modifier'] ) ? sanitize_html_class( (string) $args['card_list_modifier'] ) : '';
$primary_cta        = isset( $args['primary_cta'] ) && is_array( $args['primary_cta'] ) ? $args['primary_cta'] : array();
$secondary_cta      = isset( $args['secondary_cta'] ) && is_array( $args['secondary_cta'] ) ? $args['secondary_cta'] : array();
$previous_aria_label = isset( $args['previous_aria_label'] ) ? trim( (string) $args['previous_aria_label'] ) : __( 'Previous offers', 'hello-elementor-child' );
$next_aria_label     = isset( $args['next_aria_label'] ) ? trim( (string) $args['next_aria_label'] ) : __( 'Next offers', 'hello-elementor-child' );

if ( empty( $cards ) ) {
	return;
}

$section_attributes = array(
	'class="' . esc_attr( $section_class ) . '"',
);

if ( '' !== $section_id ) {
	$section_attributes[] = 'id="' . esc_attr( $section_id ) . '"';
}

if ( '' !== $aria_label ) {
	$section_attributes[] = 'aria-label="' . esc_attr( $aria_label ) . '"';
}

$card_list_classes = array(
	'pera-latest-offers-card-list',
	'cards-slider',
	'cards-slider--snap',
	'home-editorial-posts__slider',
);

if ( '' !== $card_list_modifier ) {
	$card_list_classes[] = 'pera-latest-offers-card-list--' . $card_list_modifier;
}

$ctas = array_filter(
	array( $primary_cta, $secondary_cta ),
	static function ( array $cta ): bool {
		return ! empty( $cta['label'] ) && ! empty( $cta['url'] );
	}
);
?>
<section <?php echo implode( ' ', $section_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="container">
		<?php if ( '' !== $kicker || '' !== $title || '' !== $description ) : ?>
			<header class="section-header section-header--center">
				<?php if ( '' !== $kicker ) : ?>
					<p class="u-eyebrow"><?php echo esc_html( $kicker ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $title ) : ?>
					<h2><?php echo esc_html( $title ); ?></h2>
				<?php endif; ?>
				<?php if ( '' !== $description ) : ?>
					<p><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
			</header>
		<?php endif; ?>

		<div class="cards-slider-shell--nav">
			<button
				type="button"
				class="cards-slider-nav cards-slider-nav--prev"
				data-slider-target="<?php echo esc_attr( $slider_id ); ?>"
				aria-label="<?php echo esc_attr( $previous_aria_label ); ?>"
			>
				<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
					<use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-chevron-left' ); ?>"></use>
				</svg>
			</button>

			<div
				class="<?php echo esc_attr( implode( ' ', $card_list_classes ) ); ?>"
				id="<?php echo esc_attr( $slider_id ); ?>"
				aria-label="<?php echo esc_attr( '' !== $aria_label ? $aria_label : __( 'Latest offers list', 'hello-elementor-child' ) ); ?>"
			>
				<?php foreach ( $cards as $card ) : ?>
					<?php pera_latest_offers_render_card( $card ); ?>
				<?php endforeach; ?>
			</div>

			<button
				type="button"
				class="cards-slider-nav cards-slider-nav--next"
				data-slider-target="<?php echo esc_attr( $slider_id ); ?>"
				aria-label="<?php echo esc_attr( $next_aria_label ); ?>"
			>
				<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
					<use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-chevron-right' ); ?>"></use>
				</svg>
			</button>
		</div>

		<?php if ( ! empty( $ctas ) ) : ?>
			<div class="section-cta latest-offers-section__actions">
				<?php foreach ( $ctas as $cta ) : ?>
					<?php
					$cta_label = (string) $cta['label'];
					$cta_url   = (string) $cta['url'];
					$cta_class = isset( $cta['class'] ) ? (string) $cta['class'] : 'btn btn--solid';
					?>
					<a href="<?php echo esc_url( $cta_url ); ?>" class="<?php echo esc_attr( $cta_class ); ?>">
						<?php echo esc_html( $cta_label ); ?>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
