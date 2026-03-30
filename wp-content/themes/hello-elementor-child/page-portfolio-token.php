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
		'advisor_name' => '',
		'expires_at'   => 0,
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

<main id="primary" class="site-main content-rail portfolio-token-page">
	<section class="hero hero--left hero--fit" id="crm-hero">
		<div class="hero-content container">
			<h1>A Custom Portfolio</h1>
			<?php if ( ! empty( $context['is_valid'] ) ) : ?>
				<?php
				$client_name  = isset( $context['client_name'] ) ? trim( (string) $context['client_name'] ) : '';
				$advisor_name = isset( $context['advisor_name'] ) ? trim( (string) $context['advisor_name'] ) : '';

				$prepared_for = '';
				if ( '' !== $client_name ) {
					$prepared_for = ' for ' . $client_name;
				}

				$advisor_byline = '';
				if ( '' !== $advisor_name ) {
					$advisor_byline = ' by your advisor ' . $advisor_name;
				}
				?>
				<p class="lead">
					<?php echo esc_html( 'A custom portfolio prepared' . $prepared_for . $advisor_byline . '. Let us know what you think!' ); ?>
				</p>
			<?php endif; ?>
		</div>
	</section>

	<?php if ( ! empty( $context['is_valid'] ) ) : ?>
		<?php
		$property_ids = isset( $context['property_ids'] ) && is_array( $context['property_ids'] )
			? array_values( array_filter( array_map( 'absint', $context['property_ids'] ) ) )
			: array();
		$expires_at = isset( $context['expires_at'] ) ? (int) $context['expires_at'] : 0;
		$portfolio_id = isset( $context['portfolio_id'] ) ? (int) $context['portfolio_id'] : 0;
		$client_id    = $portfolio_id > 0 ? (int) get_post_meta( $portfolio_id, '_portfolio_client_id', true ) : 0;

		$portfolio_rows_by_property = array();
		$floor_plan_urls_by_property = array();
		if ( $client_id > 0 && function_exists( 'peracrm_client_property_list' ) ) {
			$portfolio_rows = (array) peracrm_client_property_list( $client_id, 'portfolio', 500 );
			foreach ( $portfolio_rows as $portfolio_row ) {
				if ( ! is_array( $portfolio_row ) ) {
					continue;
				}

				$linked_property_id = isset( $portfolio_row['property_id'] ) ? (int) $portfolio_row['property_id'] : 0;
				if ( $linked_property_id > 0 ) {
					$portfolio_rows_by_property[ $linked_property_id ] = $portfolio_row;

					$floor_plan_attachment_id = isset( $portfolio_row['floor_plan_attachment_id'] ) ? (int) $portfolio_row['floor_plan_attachment_id'] : 0;
					if ( $floor_plan_attachment_id > 0 ) {
						$floor_plan_url = wp_get_attachment_url( $floor_plan_attachment_id );
						if ( is_string( $floor_plan_url ) && '' !== $floor_plan_url ) {
							$floor_plan_urls_by_property[ $linked_property_id ] = $floor_plan_url;
						}
					}
				}
			}
		}

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

		<section class="section section-soft">
			<div class="container">
				<div class="pb-md portfolio-token-summary">
					<p class="text-light">
						<?php echo esc_html( sprintf( '%d properties', (int) $properties_query->post_count ) ); ?>
						<?php if ( $expires_at > 0 ) : ?>
							<span> · <?php echo esc_html( sprintf( 'Valid until %s', wp_date( get_option( 'date_format' ), $expires_at ) ) ); ?></span>
						<?php endif; ?>
					</p>

					<div class="portfolio-view-toggle" role="group" aria-label="Portfolio view">
						<button type="button" class="btn btn--ghost btn--blue" data-portfolio-view-btn="card" aria-pressed="false">CARD</button>
						<button type="button" class="btn btn--ghost btn--blue is-active" data-portfolio-view-btn="table" aria-pressed="true">TABLE</button>
					</div>
				</div>

				<section data-portfolio-view="card" hidden>
					<div id="property-grid" class="cards-grid">
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
				</section>

				<?php $properties_query->rewind_posts(); ?>

				<section data-portfolio-view="table">
					<div class="table-wrap portfolio-table-wrap">
						<table class="portfolio-table">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Project / Property', 'hello-elementor-child' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Type', 'hello-elementor-child' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Floor', 'hello-elementor-child' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Net (m²)', 'hello-elementor-child' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Gross (m²)', 'hello-elementor-child' ); ?></th>
									<th scope="col"><?php esc_html_e( 'List ($)', 'hello-elementor-child' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Cash ($)', 'hello-elementor-child' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Floor plan', 'hello-elementor-child' ); ?></th>
									<th scope="col" class="portfolio-notes-col"><?php esc_html_e( 'Notes', 'hello-elementor-child' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if ( $properties_query->have_posts() ) : ?>
									<?php while ( $properties_query->have_posts() ) : $properties_query->the_post(); ?>
										<?php
										$property_id = (int) get_the_ID();
										$row_data    = isset( $portfolio_rows_by_property[ $property_id ] ) && is_array( $portfolio_rows_by_property[ $property_id ] )
											? $portfolio_rows_by_property[ $property_id ]
											: array();
										$floor_plan_url = isset( $floor_plan_urls_by_property[ $property_id ] ) ? (string) $floor_plan_urls_by_property[ $property_id ] : '';
										$portfolio_note = isset( $row_data['notes'] ) ? trim( (string) $row_data['notes'] ) : '';

										$field_or_dash = static function ( array $row, string $key ): string {
											$value = isset( $row[ $key ] ) ? trim( (string) $row[ $key ] ) : '';
											return '' !== $value ? $value : '—';
										};
										?>
										<tr>
											<td><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></td>
											<td><?php echo esc_html( $field_or_dash( $row_data, 'unit_type' ) ); ?></td>
											<td><?php echo esc_html( $field_or_dash( $row_data, 'floor_number' ) ); ?></td>
											<td><?php echo esc_html( $field_or_dash( $row_data, 'net_size' ) ); ?></td>
											<td><?php echo esc_html( $field_or_dash( $row_data, 'gross_size' ) ); ?></td>
											<td>
												<?php
												$list = $row_data['list_price'] ?? null;
												echo null !== $list && '' !== $list
													? esc_html( number_format( (float) $list, 0, '.', ',' ) )
													: '—';
												?>
											</td>
											<td>
												<?php
												$cash = $row_data['cash_price'] ?? null;
												echo null !== $cash && '' !== $cash
													? esc_html( number_format( (float) $cash, 0, '.', ',' ) )
													: '—';
												?>
											</td>
											<td>
												<?php if ( '' !== $floor_plan_url ) : ?>
													<a href="<?php echo esc_url( $floor_plan_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'hello-elementor-child' ); ?></a>
												<?php else : ?>
													—
												<?php endif; ?>
											</td>
											<td class="portfolio-note-cell">
												<?php if ( '' !== $portfolio_note ) : ?>
													<button type="button" class="portfolio-note-trigger" data-portfolio-note-trigger aria-label="<?php esc_attr_e( 'View notes', 'hello-elementor-child' ); ?>" aria-expanded="false">
														<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
															<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v7A2.5 2.5 0 0 1 17.5 15H9l-4.7 4.1a.8.8 0 0 1-1.3-.6V5.5Zm2.5-1a1 1 0 0 0-1 1v11.3L8.2 14a.8.8 0 0 1 .5-.2h8.8a1 1 0 0 0 1-1v-7a1 1 0 0 0-1-1h-11Z"/>
															<path d="M8 8.25h8a.75.75 0 0 1 0 1.5H8a.75.75 0 0 1 0-1.5Zm0 3h5a.75.75 0 0 1 0 1.5H8a.75.75 0 0 1 0-1.5Z"/>
														</svg>
													</button>
													<div class="portfolio-note-popover" data-portfolio-note-popover hidden role="tooltip"><?php echo nl2br( esc_html( $portfolio_note ) ); ?></div>
												<?php endif; ?>
											</td>
										</tr>
									<?php endwhile; ?>
								<?php else : ?>
									<tr>
										<td colspan="9"><?php esc_html_e( 'No properties available in this portfolio right now.', 'hello-elementor-child' ); ?></td>
									</tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</section>
			</div>
		</section>

		<script>
		(function () {
			var storageKey = 'peraPortfolioView';
			var validViews = { card: true, table: true };
			var sections = document.querySelectorAll('[data-portfolio-view]');
			var buttons = document.querySelectorAll('[data-portfolio-view-btn]');

			if (!sections.length || !buttons.length) {
				return;
			}

			var params = new URLSearchParams(window.location.search);
			var urlView = (params.get('view') || '').toLowerCase();
			var storedView = (window.localStorage && window.localStorage.getItem(storageKey) || '').toLowerCase();
			var activeView = validViews[urlView] ? urlView : (validViews[storedView] ? storedView : 'table');

			var applyView = function (view) {
				if (!validViews[view]) {
					view = 'table';
				}

				sections.forEach(function (section) {
					section.hidden = section.getAttribute('data-portfolio-view') !== view;
				});

				buttons.forEach(function (button) {
					var isActive = button.getAttribute('data-portfolio-view-btn') === view;
					button.classList.toggle('is-active', isActive);
					button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
				});

				if (window.localStorage) {
					window.localStorage.setItem(storageKey, view);
				}
			};

			buttons.forEach(function (button) {
				button.addEventListener('click', function () {
					applyView(button.getAttribute('data-portfolio-view-btn') || 'table');
				});
			});

			applyView(activeView);
		})();

		(function () {
			var active = null;
			var closeActive = function () {
				if (!active) {
					return;
				}
				active.popover.hidden = true;
				active.button.setAttribute('aria-expanded', 'false');
				active = null;
			};

			var openForButton = function (button) {
				var popover = button && button.parentNode ? button.parentNode.querySelector('[data-portfolio-note-popover]') : null;
				if (!popover) {
					return;
				}

				if (active && active.button === button) {
					closeActive();
					return;
				}

				closeActive();
				popover.hidden = false;
				button.setAttribute('aria-expanded', 'true');
				active = { button: button, popover: popover };
			};

			document.addEventListener('click', function (event) {
				var trigger = event.target.closest('[data-portfolio-note-trigger]');
				if (trigger) {
					event.preventDefault();
					openForButton(trigger);
					return;
				}

				if (active && !event.target.closest('.portfolio-note-cell')) {
					closeActive();
				}
			});

			document.addEventListener('keydown', function (event) {
				if (event.key === 'Escape') {
					closeActive();
				}
			});
		})();
		</script>

		<?php wp_reset_postdata(); ?>
	<?php else : ?>
		<div class="content-block">
			<p>We couldn't find that portfolio. Please contact your advisor for a fresh link.</p>
		</div>
	<?php endif; ?>
</main>

<?php
get_footer();
