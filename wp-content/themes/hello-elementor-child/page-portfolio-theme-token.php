<?php
/**
 * Public Theme Portfolio Token page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$context = function_exists( 'pera_theme_portfolio_token_get_request_context' )
	? pera_theme_portfolio_token_get_request_context()
	: array(
		'is_request'  => false,
		'is_valid'    => false,
		'status'      => 404,
		'client_id'   => 0,
		'client_name' => '',
		'advisor_name'=> '',
		'property_ids'=> array(),
		'created_at'  => 0,
		'updated_at'  => 0,
	);

$status_code = (int) ( $context['status'] ?? 404 );
if ( ! empty( $context['is_request'] ) && $status_code >= 400 ) {
	if ( 404 === $status_code && isset( $GLOBALS['wp_query'] ) && $GLOBALS['wp_query'] instanceof WP_Query ) {
		$GLOBALS['wp_query']->set_404();
	}
	status_header( $status_code );
	nocache_headers();
}

$offer_groups = array();
if ( ! empty( $context['is_valid'] ) && function_exists( 'pera_theme_portfolio_token_build_offer_groups' ) ) {
	$offer_groups = pera_theme_portfolio_token_build_offer_groups( (array) ( $context['property_ids'] ?? array() ) );
}

if ( function_exists( 'pera_latest_offers_enqueue_card_styles' ) ) {
	pera_latest_offers_enqueue_card_styles();
}

get_header();
?>

<main id="primary" class="site-main content-rail portfolio-token-page portfolio-theme-token-page">
	<section class="hero hero--left hero--fit" id="crm-hero">
		<div class="hero-content container">
			<h1><?php esc_html_e( 'Theme Portfolio', 'hello-elementor-child' ); ?></h1>
			<?php if ( ! empty( $context['is_valid'] ) ) : ?>
				<?php
				$client_name  = isset( $context['client_name'] ) ? trim( (string) $context['client_name'] ) : '';
				$advisor_name = isset( $context['advisor_name'] ) ? trim( (string) $context['advisor_name'] ) : '';
				$updated_at   = isset( $context['updated_at'] ) ? (int) $context['updated_at'] : 0;

				$prepared_for = '' !== $client_name ? sprintf( __( ' for %s', 'hello-elementor-child' ), $client_name ) : '';
				$advisor_by   = '' !== $advisor_name ? sprintf( __( ' by your advisor %s', 'hello-elementor-child' ), $advisor_name ) : '';
				?>
				<p class="lead">
					<?php echo esc_html( sprintf( __( 'A theme portfolio prepared%s%s.', 'hello-elementor-child' ), $prepared_for, $advisor_by ) ); ?>
				</p>
				<?php if ( $updated_at > 0 ) : ?>
					<p class="lead"><?php echo esc_html( sprintf( __( 'Updated on %s', 'hello-elementor-child' ), wp_date( get_option( 'date_format' ), $updated_at ) ) ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</section>

	<?php if ( ! empty( $context['is_valid'] ) ) : ?>
		<section class="section section-soft">
			<div class="container">
				<div class="pb-md portfolio-token-summary">
					<p>
						<?php
						$offer_count = 0;
						foreach ( $offer_groups as $group ) {
							$offer_count += is_array( $group['offers'] ?? null ) ? count( $group['offers'] ) : 0;
						}
						echo esc_html( sprintf( __( '%1$d projects · %2$d offers', 'hello-elementor-child' ), count( $offer_groups ), $offer_count ) );
						?>
					</p>
				</div>

				<?php if ( ! empty( $offer_groups ) ) : ?>
					<?php foreach ( $offer_groups as $group ) : ?>
						<?php
						$property_title = isset( $group['property_title'] ) ? trim( (string) $group['property_title'] ) : '';
						$property_url   = isset( $group['property_url'] ) ? (string) $group['property_url'] : '';
						$cards          = isset( $group['cards'] ) && is_array( $group['cards'] ) ? $group['cards'] : array();
						$offers         = isset( $group['offers'] ) && is_array( $group['offers'] ) ? $group['offers'] : array();
						?>
						<section class="mb-lg portfolio-theme-token-page__project" aria-label="<?php echo esc_attr( $property_title ); ?>">
							<header class="mb-sm portfolio-theme-token-page__project-header">
								<div>
									<p class="section-kicker"><?php esc_html_e( 'Project', 'hello-elementor-child' ); ?></p>
									<h2 class="portfolio-theme-token-page__project-title"><?php echo esc_html( '' !== $property_title ? $property_title : __( 'Untitled property', 'hello-elementor-child' ) ); ?></h2>
								</div>
								<?php if ( '' !== $property_url ) : ?>
									<a class="pill pill--outline" href="<?php echo esc_url( $property_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Project details', 'hello-elementor-child' ); ?></a>
								<?php endif; ?>
							</header>

							<?php if ( ! empty( $cards ) ) : ?>
								<div class="pera-latest-offers-card-list">
									<?php foreach ( $cards as $card ) : ?>
										<?php if ( function_exists( 'pera_latest_offers_render_card' ) ) { pera_latest_offers_render_card( $card ); } ?>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>

							<?php if ( ! empty( $offers ) ) : ?>
								<div class="table-wrap portfolio-table-wrap portfolio-theme-token-page__table-wrap">
									<table class="portfolio-table">
										<thead>
											<tr>
												<th scope="col"><?php esc_html_e( 'Type', 'hello-elementor-child' ); ?></th>
												<th scope="col"><?php esc_html_e( 'Floor', 'hello-elementor-child' ); ?></th>
												<th scope="col"><?php esc_html_e( 'Net (m²)', 'hello-elementor-child' ); ?></th>
												<th scope="col"><?php esc_html_e( 'Gross (m²)', 'hello-elementor-child' ); ?></th>
												<th scope="col"><?php esc_html_e( 'List ($)', 'hello-elementor-child' ); ?></th>
												<th scope="col"><?php esc_html_e( 'Cash ($)', 'hello-elementor-child' ); ?></th>
												<th scope="col" class="portfolio-notes-col"><?php esc_html_e( 'Notes', 'hello-elementor-child' ); ?></th>
											</tr>
										</thead>
										<tbody>
											<?php foreach ( $offers as $offer ) : ?>
												<?php
												$field_or_dash = static function ( array $row, string $key ): string {
													$value = isset( $row[ $key ] ) ? trim( (string) $row[ $key ] ) : '';
													return '' !== $value ? $value : '—';
												};
												?>
												<tr>
													<td><?php echo esc_html( $field_or_dash( $offer, 'type' ) ); ?></td>
													<td><?php echo esc_html( $field_or_dash( $offer, 'floor' ) ); ?></td>
													<td><?php echo esc_html( $field_or_dash( $offer, 'net_sqm' ) ); ?></td>
													<td><?php echo esc_html( $field_or_dash( $offer, 'gross_sqm' ) ); ?></td>
													<td><?php echo esc_html( $field_or_dash( $offer, 'list_price' ) ); ?></td>
													<td><?php echo esc_html( $field_or_dash( $offer, 'cash_price' ) ); ?></td>
													<td><?php echo esc_html( $field_or_dash( $offer, 'notes' ) ); ?></td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								</div>
							<?php else : ?>
								<p><?php esc_html_e( 'No offers currently available for this project.', 'hello-elementor-child' ); ?></p>
							<?php endif; ?>
						</section>
					<?php endforeach; ?>
				<?php else : ?>
					<p><?php esc_html_e( 'No properties are currently linked to this theme portfolio.', 'hello-elementor-child' ); ?></p>
				<?php endif; ?>
			</div>
		</section>
	<?php else : ?>
		<div class="content-block">
			<p><?php esc_html_e( "We couldn't find that theme portfolio. Please contact your advisor for a fresh link.", 'hello-elementor-child' ); ?></p>
		</div>
	<?php endif; ?>
</main>

<?php
get_footer();
