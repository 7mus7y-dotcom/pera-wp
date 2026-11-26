<?php
/**
 * Public land listing.
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="primary" class="site-main pera-single-property content-rail">
	<?php while ( have_posts() ) : the_post(); ?>
		<?php
		$listing_id = get_the_ID();
		if ( post_password_required( $listing_id ) ) {
			echo '<section class="section"><div class="container">' . get_the_password_form( $listing_id ) . '</div></section>';
			continue;
		}

		$title     = get_the_title();
		$permalink = get_permalink();
		$district  = pera_get_deepest_term( $listing_id, 'district' );
		$location  = $district ? pera_ml_term( $district ) : '';
		if ( $district && $district->parent ) {
			$parent = get_term( $district->parent, 'district' );
			if ( $parent instanceof WP_Term ) {
				$location .= ', ' . pera_ml_term( $parent );
			}
		}

		$text_field = static function ( string $name ) use ( $listing_id ): string {
			return pera_land_text_field( $listing_id, $name );
		};
		$area        = (float) $text_field( 'land_plot_area_sqm' );
		$price       = pera_land_public_price( $listing_id );
		$price_label = $price
			? $price['currency'] . ' ' . pera_land_format_number( $price['amount'] )
			: pera_ml_ui( 'Price on request', 'theme.template.single_land.price_on_request' );

		$availability_labels = array(
			'available'   => pera_ml_ui( 'Available', 'theme.template.single_land.available' ),
			'under_offer' => pera_ml_ui( 'Under offer', 'theme.template.single_land.under_offer' ),
			'sold'        => pera_ml_ui( 'Sold', 'theme.template.single_land.sold' ),
		);
		$availability_key = $text_field( 'land_availability' );
		$availability = $availability_labels[ $availability_key ] ?? '';
		$verified = pera_land_planning_is_verified( $listing_id );
		$planning = $verified ? array_filter(
			array(
				pera_ml_ui( 'Documented planning use', 'theme.template.single_land.planning_use' ) => $text_field( 'land_planning_use' ),
				pera_ml_ui( 'Emsal / KAKS', 'theme.template.single_land.emsal' ) => $text_field( 'land_emsal' ),
				pera_ml_ui( 'TAKS', 'theme.template.single_land.taks' ) => $text_field( 'land_taks' ),
				pera_ml_ui( 'Height limit', 'theme.template.single_land.height_limit' ) => $text_field( 'land_height_limit' ),
			),
			'strlen'
		) : array();
		$planning_notes = $verified ? $text_field( 'land_planning_notes' ) : '';

		$access_labels = array(
			'yes'     => pera_ml_ui( 'Yes', 'theme.template.single_land.yes' ),
			'no'      => pera_ml_ui( 'No', 'theme.template.single_land.no' ),
			'unknown' => pera_ml_ui( 'Unknown', 'theme.template.single_land.unknown' ),
		);
		$frontage = (float) $text_field( 'land_frontage_m' );
		$site_facts = array_filter(
			array(
				pera_ml_ui( 'Title deed description', 'theme.template.single_land.title_description' ) => $text_field( 'land_title_description' ),
				pera_ml_ui( 'Parcels in this offer', 'theme.template.single_land.parcel_count' ) => $text_field( 'land_parcel_count' ),
				pera_ml_ui( 'Road access', 'theme.template.single_land.road_access' ) => $access_labels[ $text_field( 'land_road_access' ) ] ?? '',
				pera_ml_ui( 'Road frontage', 'theme.template.single_land.frontage' ) => $frontage > 0 ? pera_land_format_number( $frontage ) . ' m' : '',
				pera_ml_ui( 'Slope', 'theme.template.single_land.slope' ) => $text_field( 'land_slope' ),
				pera_ml_ui( 'Utilities', 'theme.template.single_land.utilities' ) => $text_field( 'land_utilities' ),
			),
			'strlen'
		);

		$map_mode = $text_field( 'land_map_visibility' );
		$pin      = in_array( $map_mode, array( 'approximate', 'exact' ), true ) ? pera_land_field( $listing_id, 'land_' . $map_mode . '_map_pin' ) : null;
		$map_url  = '';
		if ( is_array( $pin ) && is_numeric( $pin['lat'] ?? null ) && is_numeric( $pin['lng'] ?? null )
			&& abs( (float) $pin['lat'] ) <= 90 && abs( (float) $pin['lng'] ) <= 180 ) {
			$map_url = 'https://www.google.com/maps?q=' . rawurlencode( $pin['lat'] . ',' . $pin['lng'] );
		}

		$plan_id  = absint( pera_land_field( $listing_id, 'land_public_plot_plan' ) );
		$plan_url = $plan_id && 'attachment' === get_post_type( $plan_id ) && 'trash' !== get_post_status( $plan_id )
			? wp_get_attachment_url( $plan_id )
			: '';
		$featured_id = (int) get_post_thumbnail_id( $listing_id );
		$gallery_ids = (array) pera_land_field( $listing_id, 'land_gallery' );
		$gallery_ids[] = pera_land_field( $listing_id, 'land_drone_video' );
		$whatsapp_url = pera_get_whatsapp_url(
			sprintf(
				/* translators: 1: listing title, 2: listing reference, 3: listing URL. */
				pera_ml_ui( 'Hello Pera Property, I would like more information about this land listing: %1$s. Ref: %2$d. %3$s', 'theme.template.single_land.whatsapp_message' ),
				wp_strip_all_tags( $title ),
				$listing_id,
				$permalink
			)
		);
		$request_details = pera_ml_ui( 'Request planning details', 'theme.template.single_land.request_details' );
		$icons = get_stylesheet_directory_uri() . '/logos-icons/icons.svg#';
		?>
		<section class="hero hero--left property-hero">
			<?php if ( $featured_id ) : ?>
				<?php
				echo wp_get_attachment_image(
					$featured_id,
					'full',
					false,
					array(
						'class'         => 'hero-media',
						'loading'       => 'eager',
						'fetchpriority' => 'high',
						'decoding'      => 'async',
					)
				);
				?>
				<div class="hero-overlay" aria-hidden="true"></div>
			<?php endif; ?>

			<div class="hero-content property-hero__content">
				<?php if ( $location !== '' || $availability !== '' ) : ?>
					<div class="property-hero__pills">
						<?php if ( $location !== '' ) : ?>
							<span class="pill pill--green">
								<svg class="pill__icon" width="14" height="14" aria-hidden="true" focusable="false"><use href="<?php echo esc_url( $icons . 'icon-map' ); ?>"></use></svg>
								<?php echo esc_html( $location ); ?>
							</span>
						<?php endif; ?>
						<?php if ( $availability !== '' ) : ?>
							<span class="pill <?php echo 'sold' === $availability_key ? 'pill--red' : 'pill--brand'; ?>"><?php echo esc_html( $availability ); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<h1 class="property-hero__title"><?php echo esc_html( $title ); ?></h1>
				<?php if ( has_excerpt() ) : ?><p class="property-hero__excerpt text-light"><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>

				<div class="property-hero__meta">
					<div class="property-hero__price price--xl">
						<span class="property-price__current"><?php echo esc_html( $price_label ); ?></span>
						<?php if ( $price && $area > 0 ) : ?>
							<span class="property-hero__price-link text-light text-sm"><?php echo esc_html( $price['currency'] . ' ' . pera_land_format_number( round( $price['amount'] / $area, 2 ) ) . ' / m²' ); ?></span>
						<?php endif; ?>
					</div>
					<?php if ( $area > 0 ) : ?>
						<div class="property-hero__facts">
							<span class="pill pill--green">
								<svg class="pill__icon" width="14" height="14" aria-hidden="true" focusable="false"><use href="<?php echo esc_url( $icons . 'icon-arrows-expand' ); ?>"></use></svg>
								<span class="screen-reader-text"><?php echo esc_html( pera_ml_ui( 'Plot area', 'theme.template.single_land.plot_area' ) ); ?></span>
								<?php echo esc_html( pera_land_format_number( $area ) . ' m²' ); ?>
							</span>
						</div>
					<?php endif; ?>

					<div class="property-hero__cta">
						<a class="btn btn--solid btn--blue" href="#contact-form"><?php echo esc_html( $request_details ); ?></a>
						<a class="btn btn--solid btn--green" href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener"
							data-whatsapp="1" data-whatsapp-type="land_detail" data-track-channel="whatsapp" data-track-intent="high"
							data-track-source="template" data-track-context="land_detail" data-track-ga4-event="whatsapp_click"
							data-track-crm-event="whatsapp_click" data-post-id="<?php echo esc_attr( $listing_id ); ?>"
							data-post-title="<?php echo esc_attr( $title ); ?>" data-page-url="<?php echo esc_url( $permalink ); ?>">
							<svg class="icon" aria-hidden="true" focusable="false" width="18" height="18"><use href="<?php echo esc_url( $icons . 'icon-whatsapp' ); ?>"></use></svg>
							<?php echo esc_html( pera_ml_ui( 'WhatsApp', 'theme.template.single_land.whatsapp' ) ); ?>
						</a>
					</div>
				</div>
			</div>
		</section>

		<?php get_template_part( 'parts/land-gallery', null, array( 'ids' => $gallery_ids, 'featured_id' => $featured_id ) ); ?>

		<?php if ( trim( get_the_content() ) !== '' ) : ?>
			<section class="section"><div class="container article-body"><?php the_content(); ?></div></section>
		<?php endif; ?>

		<section class="section section-soft">
			<div class="content-panel-box">
				<h2><?php echo esc_html( pera_ml_ui( 'Planning details', 'theme.template.single_land.planning' ) ); ?></h2>
				<p><?php echo esc_html( $verified ? pera_ml_ui( 'Planning verified', 'theme.template.single_land.planning_verified' ) : pera_ml_ui( 'Planning status pending verification', 'theme.template.single_land.planning_pending' ) ); ?></p>
				<?php if ( $planning ) : ?>
					<div class="grid-2 mb-sm">
						<?php foreach ( $planning as $label => $value ) : ?>
							<p><strong><?php echo esc_html( $label ); ?></strong><br><?php echo esc_html( $value ); ?></p>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<?php if ( $planning_notes !== '' ) : ?><p><?php echo esc_html( $planning_notes ); ?></p><?php endif; ?>
				<?php if ( $plan_url ) : ?>
					<a class="btn btn--ghost btn--blue" href="<?php echo esc_url( $plan_url ); ?>">
						<svg class="icon" width="18" height="18" aria-hidden="true" focusable="false"><use href="<?php echo esc_url( $icons . 'icon-floor-plan' ); ?>"></use></svg>
						<?php echo esc_html( pera_ml_ui( 'View plot plan', 'theme.template.single_land.view_plan' ) ); ?>
					</a>
				<?php endif; ?>
			</div>
		</section>

		<?php if ( $site_facts || $map_url ) : ?>
			<section class="section property-location">
				<div class="card-shell property-location__panel">
					<h2 class="inline-row property-location__heading"><svg class="icon" aria-hidden="true" focusable="false"><use href="<?php echo esc_url( $icons . 'icon-map' ); ?>"></use></svg><?php echo esc_html( pera_ml_ui( 'Site and access', 'theme.template.single_land.site_access' ) ); ?></h2>
					<?php if ( $site_facts ) : ?>
						<div class="grid-2 mb-sm">
							<?php foreach ( $site_facts as $label => $value ) : ?>
								<p><strong><?php echo esc_html( $label ); ?></strong><br><?php echo esc_html( $value ); ?></p>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php if ( $map_url ) : ?>
						<?php
						$approximate = 'approximate' === $map_mode;
						$map_title = $approximate ? pera_ml_ui( 'Approximate location', 'theme.template.single_land.approximate_location' ) : pera_ml_ui( 'Plot location', 'theme.template.single_land.plot_location' );
						?>
						<div class="property-map-card">
							<p class="property-map-card__address text-soft"><?php echo esc_html( $map_title ); ?></p>
							<div class="media-frame media-frame--map">
								<iframe class="media-embed--map" src="<?php echo esc_url( $map_url . ( $approximate ? '&z=13' : '&z=16' ) . '&output=embed' ); ?>" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" title="<?php echo esc_attr( $map_title ); ?>"></iframe>
							</div>
							<div class="property-map-card__actions">
								<a class="btn btn--solid btn--green" href="<?php echo esc_url( $map_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( pera_ml_ui( 'Open in Google Maps', 'theme.template.single_land.open_map' ) ); ?></a>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php
		$faqs = pera_get_single_post_faq_v2_items( $listing_id );
		if ( $faqs ) :
			?>
			<div class="container">
				<?php pera_render_faq_html( $faqs, pera_ml_ui( 'Frequently asked questions', 'theme.template.single_land.faq_heading' ) ); ?>
			</div>
		<?php endif; ?>

		<section class="section section-soft">
			<div class="content-panel-box" id="contact-form" tabindex="-1">
				<?php
				get_template_part(
					'parts/enquiry-form',
					null,
					array(
						'context'        => 'property',
						'heading'        => $request_details,
						'submit_label'   => pera_ml_ui( 'Send enquiry', 'theme.template.single_land.send_enquiry' ),
						'sr_context'     => 'land_listing',
						'property_id'    => $listing_id,
						'property_title' => $title,
						'property_url'   => $permalink,
					)
				);
				?>
				<p class="mt-sm">
					<a href="<?php echo esc_url( get_post_type_archive_link( 'land' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'View all land for sale in Istanbul', 'theme.template.single_land.view_all_land' ) ); ?></a>
				</p>
			</div>
		</section>
	<?php endwhile; ?>
</main>
<?php get_footer(); ?>
