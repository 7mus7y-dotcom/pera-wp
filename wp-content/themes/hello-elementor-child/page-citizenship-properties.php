<?php
/**
 * Template Name: Turkish Citizenship Properties
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$cards = function_exists( 'pera_latest_offers_collect_cards' )
	? pera_latest_offers_collect_cards(
		12,
		60,
		array(
			'tax_query' => array(
				array(
					'taxonomy' => 'special',
					'field'    => 'slug',
					'terms'    => array( 'citizenship' ),
				),
			),
		)
	)
	: array();

$description_content = trim( (string) get_post_field( 'post_content', get_queried_object_id() ) );
$hero_title          = get_the_title();
$hero_desc_html      = '';

if ( '' !== trim( wp_strip_all_tags( (string) $description_content ) ) ) {
	$hero_desc_html = wpautop( wp_kses_post( $description_content ) );
	$hero_desc_html = str_replace( '<p>', '<p class="text-light">', $hero_desc_html );
} else {
	$hero_desc_html = '<p class="text-light">' . esc_html__( 'Browse latest property offers tagged for Turkish citizenship eligibility.', 'hello-elementor-child' ) . '</p>';
}
?>

<main id="primary" class="site-main">
	<section class="hero hero--left property-archive-hero">
		<?php
		$term = get_queried_object();
		$term_id = ( isset( $term->term_id ) ) ? (int) $term->term_id : 0;
		$acf_ref = ( $term_id && ! empty( $term->taxonomy ) ) ? ( $term->taxonomy . '_' . $term_id ) : '';

		$district_image = ( function_exists( 'get_field' ) && $acf_ref )
			? get_field( 'district_image', $acf_ref )
			: null;

		$district_img_id = 0;
		if ( is_array( $district_image ) && ! empty( $district_image['ID'] ) ) {
			$district_img_id = (int) $district_image['ID'];
		} elseif ( is_numeric( $district_image ) ) {
			$district_img_id = (int) $district_image;
		}

		$fallback_img_id = 55482;
		$hero_img_id     = $district_img_id ?: $fallback_img_id;
		?>

		<?php if ( $hero_img_id ) : ?>
			<div class="hero__media" aria-hidden="true">
				<?php
				echo wp_get_attachment_image(
					$hero_img_id,
					'full',
					false,
					array(
						'class'         => 'hero-media',
						'loading'       => 'eager',
						'decoding'      => 'async',
						'fetchpriority' => 'high',
					)
				);
				?>
				<div class="hero-overlay" aria-hidden="true"></div>
			</div>
		<?php endif; ?>

		<div class="hero-content">
			<h1><?php echo esc_html( $hero_title ); ?></h1>
			<?php if ( '' !== $hero_desc_html ) : ?>
				<?php echo $hero_desc_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		</div>
	</section>

	<section class="section pera-citizenship-properties">
		<div class="container">
			<?php if ( ! empty( $cards ) ) : ?>
				<div class="pera-latest-offers-card-list pera-latest-offers-card-list--grid-4">
					<?php foreach ( $cards as $card ) : ?>
						<?php pera_latest_offers_render_card( $card ); ?>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'No citizenship-tagged property offers are available right now. Please check back soon.', 'hello-elementor-child' ); ?></p>
			<?php endif; ?>
		</div>
	</section>
</main>

<?php
get_footer();
