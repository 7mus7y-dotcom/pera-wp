<?php
/**
 * Combined CTA + Sell/Rent form panel (single card)
 *
 * Usage:
 * get_template_part(
 *     'parts/form-sell-rent',
 *     null,
 *     array(
 *         'hero_heading'  => 'Sell your Istanbul property with confidence',
 *         'hero_intro'    => 'We combine data-driven valuation with on-the-ground experience to position your property for the right buyers.',
 *         'form_heading'  => 'Request a free appraisal',
 *         'form_intro'    => 'Share a few details and we will prepare an initial sale strategy and price guidance for your property in Istanbul.',
 *         'form_context'  => 'sell-page',
 *     )
 * );
 */

$hero_heading = $args['hero_heading'] ?? pera_ml_ui( 'Talk to Pera about your Istanbul plans', 'theme.contact_cta.default_heading' );
$hero_intro   = $args['hero_intro']   ?? pera_ml_ui( 'Whether you’re buying, selling, or renting in Istanbul, our team can walk you through the numbers, the legal steps, and the neighbourhoods that fit your strategy.', 'theme.contact_cta.default_intro' );

$form_heading = $args['form_heading'] ?? pera_ml_ui( 'Request a valuation / rental appraisal', 'theme.sell_rent.default_form_heading' );
$form_intro   = $args['form_intro']   ?? pera_ml_ui( 'Share a few details and we will prepare an initial sale strategy and price guidance for your apartment or villa.', 'theme.sell_rent.default_form_intro' );

$form_context = $args['form_context'] ?? 'general-contact';
?>

<section class="section section-soft" id="contact">
    <div class="content-panel-box">

        <!-- =========================
             1) HERO CTA GRID (LEFT TEXT + RIGHT IMAGE)
             ========================== -->
        <div class="content-panel-grid">

            <!-- LEFT COLUMN -->
            <div>
                <header class="section-header">
                    <h2><?php echo esc_html( $hero_heading ); ?></h2>
                    <p><?php echo esc_html( $hero_intro ); ?></p>
                </header>

                <ul class="checklist checklist--circle">
                    <li>
                        <?php echo esc_html( pera_ml_ui( 'Reliable, data-driven advice.', 'theme.contact_cta.reliable_advice' ) ); ?>
                    </li>

                    <li>
                        <?php echo esc_html( pera_ml_ui( 'On-the-ground Istanbul expertise.', 'theme.contact_cta.istanbul_expertise' ) ); ?>
                    </li>

                    <li>
                        <?php echo esc_html( pera_ml_ui( 'Multi-lingual support.', 'theme.contact_cta.multilingual_support' ) ); ?>
                    </li>
                </ul>
            </div>

            <!-- RIGHT COLUMN -->
            <div class="media-frame">

                <!-- RESPONSIVE BACKGROUND IMAGE -->
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
                            'alt'      => 'Isometric illustration of Beşiktaş'
                        )
                    );
                    ?>
                </div>

                <div class="hero-overlay"></div>

                <div class="hero-content section--center">
                    <h3 class="text-light"><?php echo esc_html( pera_ml_ui( 'Speak with a Consultant', 'theme.contact_cta.speak_with_consultant' ) ); ?></h3>

                    <div class="hero-actions flex-center">
                        <a href="https://www.peraproperty.com/contact-us/" class="btn btn-primary">
                            <?php echo esc_html( pera_ml_ui( 'Book a consultation', 'theme.contact_cta.book_consultation' ) ); ?>
                        </a>

                        <a href="<?php echo esc_url( pera_get_whatsapp_url( pera_ml_ui( 'Hello Pera Property, I\'d like to discuss Istanbul real estate.', 'theme.contact_cta.whatsapp_message' ) ) ); ?>"
                           class="btn btn-secondary"
                           data-whatsapp="1"
                           data-whatsapp-type="service_cta"
                           data-track-channel="whatsapp"
                           data-track-intent="medium"
                           data-track-source="partial"
                           data-track-context="sell_rent_form_side"
                           data-track-ga4-event="whatsapp_click"
                           data-track-crm-event="whatsapp_click">
                            <?php echo esc_html( pera_ml_ui( 'Chat on WhatsApp', 'theme.contact_cta.chat_whatsapp' ) ); ?>
                        </a>
                    </div>
                </div>

            </div><!-- .media-frame -->

        </div><!-- .content-panel-grid -->


        <!-- Optional divider between CTA and form -->
        <hr class="content-panel-divider" style="margin: 32px 0 24px; border: 0; border-top: 1px solid #e5e7eb;">

        <div class="enquiry-cta enquiry-cta--panel">

            <?php if ( isset( $_GET['sr_status'] ) && $_GET['sr_status'] === 'sent' ) : ?>
                <div class="form-success">
                    <?php echo esc_html( pera_ml_ui( 'Thank you – we have received your details. A Pera consultant will contact you shortly.', 'theme.sell_rent.success' ) ); ?>
                </div>
            <?php endif; ?>

            <div class="enquiry-cta-header">
                <h2><?php echo esc_html( $form_heading ); ?></h2>
                <p><?php echo esc_html( $form_intro ); ?></p>
            </div>

            <?php
            get_template_part(
              'parts/enquiry-form',
              null,
              array(
                'context'      => $form_context === 'sell-page' ? 'sell' : 'rent',
                'heading'      => $form_heading,
                'intro'        => $form_intro,
                'submit_label' => pera_ml_ui( 'Send my details', 'theme.enquiry_form.submit' ),
                'form_context' => $form_context,
              )
            );
            ?>


            
        </div><!-- .enquiry-cta -->


    </div><!-- .content-panel-box -->
</section>
