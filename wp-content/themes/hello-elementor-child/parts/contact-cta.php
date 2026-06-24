<?php
$cta_args = get_query_var( 'pera_contact_cta_args', array() );
$cta_args = is_array( $cta_args ) ? $cta_args : array();

$heading = isset( $cta_args['heading'] ) && is_scalar( $cta_args['heading'] ) && trim( (string) $cta_args['heading'] ) !== ''
	? (string) $cta_args['heading']
	: 'Talk to Pera about your Istanbul plans';

$text = isset( $cta_args['text'] ) && is_scalar( $cta_args['text'] ) && trim( (string) $cta_args['text'] ) !== ''
	? (string) $cta_args['text']
	: 'Whether you’re buying, selling, or renting in Istanbul, our team can walk you through the numbers, the legal steps, and the neighbourhoods that fit your strategy.';

$whatsapp_message = isset( $cta_args['whatsapp_message'] ) && is_scalar( $cta_args['whatsapp_message'] ) && trim( (string) $cta_args['whatsapp_message'] ) !== ''
	? (string) $cta_args['whatsapp_message']
	: "Hello Pera Property, I'd like to discuss Istanbul real estate.";

$whatsapp_url = esc_url( pera_get_whatsapp_url( $whatsapp_message ) );
$icon_sprite  = esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-check' );
?>
<section class="section section-soft">
	<div class="content-panel-box border-dm">
		<div class="content-panel-grid">

			<!-- LEFT COLUMN -->
			<div>
				<header class="section-header">
					<h2><?php echo esc_html( $heading ); ?></h2>
					<p><?php echo esc_html( $text ); ?></p>
				</header>

				<ul class="checklist checklist--circle">
					<li>
						Reliable, data-driven advice.
					</li>

					<li>
						On-the-ground Istanbul expertise.
					</li>

					<li>
						Multi-lingual support.
					</li>
				</ul>
			</div>

			<!-- RIGHT COLUMN -->
			<div class="media-frame">
				<div class="media-frame__bg">
					<?php
					echo wp_get_attachment_image(
						55686,
						'pera-card',
						false,
						array(
							'class'    => 'media-frame__bg-img',
							'loading'  => 'lazy',
							'decoding' => 'async',
							'alt'      => 'Isometric illustration of Beşiktaş',
						)
					);
					?>
				</div>

				<div class="hero-overlay"></div>

				<div class="hero-content section--center">
					<h3 class="text-light">Speak with a Consultant</h3>

					<div class="hero-actions flex-center">
						<a class="btn btn--ghost btn--blue" href="<?php echo esc_url( home_url( '/book-a-consultancy/' ) ); ?>">
						    <?php echo esc_html__( 'Book a Consultancy', 'hello-elementor-child' ); ?>
						</a>
						<a href="<?php echo $whatsapp_url; ?>" class="btn btn--solid btn--green" data-whatsapp="1" data-whatsapp-type="inline_cta" data-track-channel="whatsapp" data-track-intent="high" data-track-source="partial" data-track-context="reusable_contact_cta" data-track-ga4-event="whatsapp_click" data-track-crm-event="whatsapp_click">
							Chat on WhatsApp
						</a>
					</div>
				</div>
			</div>

		</div>
	</div>
</section>
