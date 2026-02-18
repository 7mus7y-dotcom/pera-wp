<?php
/**
 * Public Portfolio Token page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$context = function_exists( 'pera_portfolio_token_get_request_context' )
	? pera_portfolio_token_get_request_context()
	: array(
		'is_request'   => false,
		'is_valid'     => false,
		'status'       => 404,
		'portfolio_id' => 0,
		'property_ids' => array(),
		'client_name'  => '',
	);

$status_code = (int) ( $context['status'] ?? 404 );
if ( ! empty( $context['is_request'] ) && $status_code >= 400 ) {
	if ( 404 === $status_code && isset( $GLOBALS['wp_query'] ) && $GLOBALS['wp_query'] instanceof WP_Query ) {
		$GLOBALS['wp_query']->set_404();
	}
	status_header( $status_code );
	nocache_headers();
}

get_header();
?>

<main id="primary" class="site-main content-rail">
	<section class="hero hero--left">
		<h1 class="hero__title">Property Portfolio</h1>
		<?php if ( ! empty( $context['is_valid'] ) ) : ?>
			<?php if ( ! empty( $context['client_name'] ) ) : ?>
				<p class="hero__sub"><?php echo esc_html( $context['client_name'] ); ?></p>
			<?php endif; ?>
		<?php else : ?>
			<p class="hero__sub">This portfolio link is unavailable.</p>
		<?php endif; ?>
	</section>

	<?php if ( ! empty( $context['is_valid'] ) ) : ?>
		<?php
		$property_ids = isset( $context['property_ids'] ) && is_array( $context['property_ids'] )
			? array_values( array_filter( array_map( 'absint', $context['property_ids'] ) ) )
			: array();

		$properties_query = new WP_Query(
			array(
				'post_type'              => 'property',
				'post_status'            => 'publish',
				'post__in'               => $property_ids,
				'orderby'                => 'post__in',
				'posts_per_page'         => count( $property_ids ),
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
			)
		);
		?>

		<p class="results-count">
			<?php echo esc_html( sprintf( '%d properties', (int) $properties_query->post_count ) ); ?>
		</p>

		<div class="cards-grid">
			<?php if ( $properties_query->have_posts() ) : ?>
				<?php while ( $properties_query->have_posts() ) : $properties_query->the_post(); ?>
					<?php
					if ( function_exists( 'pera_render_property_card' ) ) {
						pera_render_property_card( array( 'variant' => 'archive' ) );
					}
					?>
				<?php endwhile; ?>
			<?php else : ?>
				<p class="no-results">No properties available in this portfolio right now.</p>
			<?php endif; ?>
		</div>

		<?php wp_reset_postdata(); ?>
	<?php else : ?>
		<div class="content-block">
			<p>We couldn't find that portfolio. Please contact your advisor for a fresh link.</p>
		</div>
	<?php endif; ?>
</main>

<?php
get_footer();
