<?php
/**
 * Land for sale archive.
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/inc/property-pagination.php';

global $wp_query;

$filters     = (array) $wp_query->get( 'pera_land_filters', array() );
$archive_url = get_post_type_archive_link( 'land' );
$districts   = pera_land_archive_districts();
$budget_unavailable = isset( $filters['land_max_price'] ) && null === pera_land_budget_usd( $filters );
$paged       = max( 1, (int) get_query_var( 'paged' ) );

get_header();
?>
<main id="primary" class="site-main content-rail">
	<section class="hero hero--left hero--compact property-archive-hero">
		<div class="hero-content">
			<h1><?php echo esc_html( pera_ml_ui( 'Land for Sale in Istanbul', 'theme.template.archive_land.title' ) ); ?></h1>
			<p class="text-light"><?php echo esc_html( pera_land_archive_description() ); ?></p>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<form class="property-filters card-shell mb-md" method="get" action="<?php echo esc_url( $archive_url ); ?>" aria-label="<?php echo esc_attr( pera_ml_ui( 'Filter land listings', 'theme.template.archive_land.filter_label' ) ); ?>">
				<?php if ( ! get_option( 'permalink_structure' ) ) : ?>
					<input type="hidden" name="post_type" value="land">
				<?php endif; ?>
				<div class="filter-row">
					<div class="filter-group">
						<label class="filter-group__label" for="land-district"><?php echo esc_html( pera_ml_ui( 'District', 'theme.template.archive_land.district' ) ); ?></label>
						<select class="cta-control" name="land_district" id="land-district">
							<option value=""><?php echo esc_html( pera_ml_ui( 'All districts', 'theme.template.archive_land.all_districts' ) ); ?></option>
							<?php foreach ( $districts as $district ) : ?>
								<option value="<?php echo esc_attr( $district->slug ); ?>" <?php selected( $filters['land_district'] ?? '', $district->slug ); ?>><?php echo esc_html( pera_ml_term( $district ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="filter-group">
						<label class="filter-group__label" for="land-min-area"><?php echo esc_html( pera_ml_ui( 'Minimum plot area (m²)', 'theme.template.archive_land.min_area' ) ); ?></label>
						<input id="land-min-area" name="land_min_area" type="number" min="0.01" step="0.01" value="<?php echo esc_attr( $filters['land_min_area'] ?? '' ); ?>">
					</div>
					<div class="filter-group">
						<label class="filter-group__label" for="land-max-area"><?php echo esc_html( pera_ml_ui( 'Maximum plot area (m²)', 'theme.template.archive_land.max_area' ) ); ?></label>
						<input id="land-max-area" name="land_max_area" type="number" min="0.01" step="0.01" value="<?php echo esc_attr( $filters['land_max_area'] ?? '' ); ?>">
					</div>
					<div class="filter-group">
						<label class="filter-group__label" for="land-max-price"><?php echo esc_html( pera_ml_ui( 'Maximum asking price', 'theme.template.archive_land.max_price' ) ); ?></label>
						<input id="land-max-price" name="land_max_price" type="number" min="0.01" step="0.01" value="<?php echo esc_attr( $filters['land_max_price'] ?? '' ); ?>" aria-describedby="land-budget-note">
					</div>
					<div class="filter-group">
						<label class="filter-group__label" for="land-currency"><?php echo esc_html( pera_ml_ui( 'Budget currency', 'theme.template.archive_land.budget_currency' ) ); ?></label>
						<select class="cta-control" id="land-currency" name="land_currency">
							<?php foreach ( pera_land_currencies() as $currency ) : ?>
								<?php if ( 'USD' !== $currency && null === pera_currency_get_rate( $currency ) ) { continue; } ?>
								<option value="<?php echo esc_attr( $currency ); ?>" <?php selected( $filters['land_currency'] ?? 'USD', $currency ); ?>><?php echo esc_html( $currency ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<p class="text-soft text-sm mt-xs" id="land-budget-note"><?php echo esc_html( pera_ml_ui( 'The budget is converted to USD and applied to public asking prices.', 'theme.template.archive_land.budget_note' ) ); ?></p>
				<div class="form-actions">
					<button type="submit" class="btn btn--solid btn--blue"><?php echo esc_html( pera_ml_ui( 'Apply filters', 'theme.template.archive_land.apply_filters' ) ); ?></button>
					<a class="btn btn--ghost btn--blue" href="<?php echo esc_url( $archive_url ); ?>"><?php echo esc_html( pera_ml_ui( 'Reset filters', 'theme.template.archive_land.reset_filters' ) ); ?></a>
				</div>
			</form>

			<?php if ( have_posts() ) : ?>
				<h2><?php echo esc_html( pera_ml_ui( 'Land listings', 'theme.template.archive_land.listings' ) ); ?></h2>
				<div class="cards-grid">
					<?php while ( have_posts() ) : the_post(); ?>
						<?php get_template_part( 'parts/land-card' ); ?>
					<?php endwhile; ?>
				</div>
				<?php
				$pagination_args = $filters;
				if ( ! get_option( 'permalink_structure' ) ) {
					$pagination_args['post_type'] = 'land';
				}
				$pagination = pera_render_property_pagination( $wp_query, $paged, $pagination_args, $archive_url );
				?>
				<?php if ( $pagination !== '' ) : ?>
					<nav class="property-pagination mt-md" aria-label="<?php echo esc_attr( pera_ml_ui( 'Land results pages', 'theme.template.archive_land.pagination' ) ); ?>">
						<?php echo $pagination; // Core pagination HTML. ?>
					</nav>
				<?php endif; ?>
			<?php else : ?>
				<div class="content-panel-box">
					<h2><?php echo esc_html( $filters ? pera_ml_ui( 'No land listings match these filters', 'theme.template.archive_land.no_matches' ) : pera_ml_ui( 'No land listings currently available', 'theme.template.archive_land.no_listings' ) ); ?></h2>
					<?php if ( $budget_unavailable ) : ?>
						<p><?php echo esc_html( pera_ml_ui( 'This budget currency is unavailable. Choose USD or another currency and try again.', 'theme.template.archive_land.currency_unavailable' ) ); ?></p>
					<?php endif; ?>
					<?php if ( $filters ) : ?>
						<a href="<?php echo esc_url( $archive_url ); ?>"><?php echo esc_html( pera_ml_ui( 'View all land listings', 'theme.template.archive_land.view_all' ) ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>
</main>
<?php get_footer(); ?>
