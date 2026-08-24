<?php
/**
 * Template Name: Sell with Pera
 * Description: Landing page for property owners who want to sell with Pera Property.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


$hero_heading = $args['hero_heading'] ?? pera_ml_ui( 'Talk to Pera about your Istanbul plans', 'theme.template.page_sell_with_pera.hero_heading_fallback' );
$hero_intro   = $args['hero_intro']   ?? pera_ml_ui( 'Whether you’re buying, selling, or renting in Istanbul, our team can walk you through the numbers, the legal steps, and the neighbourhoods that fit your strategy.', 'theme.template.page_sell_with_pera.hero_intro_fallback' );

$sell_with_pera_faq_items = array(
    array(
        'question' => 'How do I sell my property in Istanbul?',
        'answer'   => 'Start with a valuation and sales strategy. We then prepare marketing, coordinate viewings, negotiate offers and support the title deed transfer process until completion.',
    ),
    array(
        'question' => 'How long does it take to sell a property in Istanbul?',
        'answer'   => 'Timelines vary by district, price point and property condition. Well-priced homes in active areas can attract offers quickly, while premium listings may require a longer marketing window.',
    ),
    array(
        'question' => 'What documents do I need to sell property in Turkey?',
        'answer'   => 'Most sellers need the tapu, ID or passport, tax number and supporting compliance documents such as DASK or iskan where relevant, plus debt and encumbrance checks.',
    ),
    array(
        'question' => 'Can I sell my Istanbul property if I live abroad?',
        'answer'   => 'Yes. We can manage valuation, marketing and viewings remotely, and coordinate power of attorney and lawyer-led documentation so the sale can progress while you are overseas.',
    ),
    array(
        'question' => 'How much does Pera Property charge to sell my property?',
        'answer'   => 'Pera Property\'s standard sales agency fee is 4% unless otherwise agreed in writing.',
    ),
    array(
        'question' => 'How is my Istanbul property valuation calculated?',
        'answer'   => 'We assess recent comparables where available, micro-location, building condition, floor and view quality, layout, demand trends and rental/investment potential.',
    ),
    array(
        'question' => 'Do I need a lawyer to sell property in Turkey?',
        'answer'   => 'A lawyer is not always legally mandatory, but many sellers choose one for contract review, tax coordination and risk management during the transfer process.',
    ),
    array(
        'question' => 'Can you manage viewings if the property is tenanted?',
        'answer'   => 'Yes. We coordinate with tenants or caretakers, schedule qualified buyer visits and keep disruption to occupants as limited as possible.',
    ),
);

add_action( 'wp_head', static function () use ( $sell_with_pera_faq_items ) {
    $faq_entities = array();

    foreach ( $sell_with_pera_faq_items as $faq_item ) {
        $question = isset( $faq_item['question'] ) ? trim( (string) $faq_item['question'] ) : '';
        $answer   = isset( $faq_item['answer'] ) ? trim( (string) $faq_item['answer'] ) : '';

        if ( $question === '' || $answer === '' ) {
            continue;
        }

        $faq_entities[] = array(
            '@type' => 'Question',
            'name'  => $question,
            'acceptedAnswer' => array(
                '@type' => 'Answer',
                'text'  => $answer,
            ),
        );
    }

    if ( empty( $faq_entities ) ) {
        return;
    }

    $faq_schema = array(
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $faq_entities,
    );

    $GLOBALS['pera_schema_faq_emitted'] = true;
    echo '<script type="application/ld+json">' . wp_json_encode( $faq_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}, 12 );

get_header();
?>

<main id="primary" class="site-main">

    <!-- =====================================
     HERO (SELL WITH PERA)
     Canonical structure + existing content
     ===================================== -->
        <section class="hero hero--left hero--sell" id="sell-hero">
        
          <div class="hero__media" aria-hidden="true">
            <?php
              // If you later set a featured image for this page, it will be used.
              $hero_img_id = get_post_thumbnail_id();
        
              if ( $hero_img_id ) {
                echo wp_get_attachment_image(
                  $hero_img_id,
                  'full',
                  false,
                  array(
                    'class'    => 'hero-media',
                    'loading'  => 'eager',
                    'decoding' => 'async',
                  )
                );
              } else {
                // Fallback background (vopbesiktas.svg uploaded to WP)
                echo wp_get_attachment_image(
                  55756,
                  'full',
                  false,
                  array(
                    'class'    => 'hero-media',
                    'loading'  => 'eager',
                    'decoding' => 'async',
                  )
                );
              }
            ?>
            <div class="hero-overlay" aria-hidden="true"></div>
          </div>
        
          <div class="hero-content">
            <h1><?php echo esc_html( pera_ml_ui( 'Sell Property in Istanbul with a Trusted Local Agency', 'theme.template.page_sell_with_pera.sell_property_in_istanbul_with_a_trusted_local_agency' ) ); ?></h1>
        
            <p class="lead">
              <?php echo esc_html( pera_ml_ui( 'Get a free valuation from Istanbul property market specialists who manage pricing, marketing,
              viewings, negotiation and the title deed process, with dedicated support for owners based abroad.', 'theme.template.page_sell_with_pera.get_a_free_valuation_from_istanbul_property_market_specialists_who_manag' ) ); ?>
            </p>
        
            <div class="hero-actions">
              <a href="#contact" class="btn btn--solid btn--green">
                <?php echo esc_html( pera_ml_ui( 'Request a free valuation', 'theme.template.page_sell_with_pera.request_a_free_valuation' ) ); ?>
              </a>
        
              <a href="#process" class="btn btn--solid btn--blue">
                <?php echo esc_html( pera_ml_ui( 'How our selling process works', 'theme.template.page_sell_with_pera.how_our_selling_process_works' ) ); ?>
              </a>
        
              <a
                href="<?php echo esc_url( pera_get_whatsapp_url( pera_ml_ui( 'Hello I would like to sell my property with Pera Property', 'theme.template.page_sell_with_pera.sell_whatsapp_prefill' ) ) ); ?>"
                target="_blank"
                rel="noopener"
                class="btn btn-icon-circle btn-whatsapp"
                aria-label="<?php echo esc_attr( pera_ml_ui( 'Contact Pera Property via WhatsApp', 'theme.template.page_sell_with_pera.aria_label.contact_pera_property_via_whatsapp' ) ); ?>" data-whatsapp="1" data-whatsapp-type="service_cta" data-track-channel="whatsapp" data-track-intent="high" data-track-source="template" data-track-context="sell_with_pera_hero" data-track-ga4-event="whatsapp_click" data-track-crm-event="whatsapp_click"
              >
                <svg class="icon" aria-hidden="true">
                  <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-whatsapp' ); ?>"></use>
                </svg>
              </a>
            </div>
          </div>
        
        </section>


    <!-- CONTENT PANEL (overlapping hero) -->
    <section class="content-panel content-panel--overlap-hero">
        <div class="content-panel-box">
            <div class="content-panel-grid">
                <!-- LEFT: TEXT -->
                <div>
                    <header class="section-header">
                        <h2><?php echo esc_html( pera_ml_ui( 'Why sell your property with Pera?', 'theme.template.page_sell_with_pera.why_sell_your_property_with_pera' ) ); ?></h2>
                        <p>
                            <?php echo esc_html( pera_ml_ui( 'We are an Istanbul-focused, data-driven agency that treats every listing
                            like a bespoke investment project. Our goal is simple: secure the right
                            buyer at the right price, on the right terms.', 'theme.template.page_sell_with_pera.we_are_an_istanbul_focused_data_driven_agency_that_treats_every_listing_' ) ); ?>
                        </p>
                    </header>

                    <ul class="checklist checklist--circle">
                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Honest valuation based on real comparable data', 'theme.template.page_sell_with_pera.honest_valuation_based_on_real_comparable_data' ) ); ?>
                        </li>
                    
                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Access to both local and international buyers', 'theme.template.page_sell_with_pera.access_to_both_local_and_international_buyers' ) ); ?>
                        </li>
                    
                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Professional presentation: photos, videos, floor plans', 'theme.template.page_sell_with_pera.professional_presentation_photos_videos_floor_plans' ) ); ?>
                        </li>
                    
                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Negotiation, paperwork and follow-up handled end-to-end', 'theme.template.page_sell_with_pera.negotiation_paperwork_and_follow_up_handled_end_to_end' ) ); ?>
                        </li>
                    </ul>


                    <div class="signoff-card">
                        <div class="signoff-avatar">
                                        <?php
                                        echo wp_get_attachment_image(
                                            55700,
                                            'full',
                                            false,
                                            array(
                                                'class'   => '',
                                                'alt'     => 'Pera Property Director',
                                                'loading' => 'lazy',
                                                'decoding'=> 'async',
                                            )
                                        );
                                        ?>
                                    </div>
                        <div class="signoff-text">
                            <h5><?php echo esc_html( pera_ml_ui( 'Your dedicated consultant', 'theme.template.page_sell_with_pera.your_dedicated_consultant' ) ); ?></h5>
                            <p>
                                <?php echo esc_html( pera_ml_ui( 'One point of contact from first valuation to key handover. Direct,
                                clear and honest communication throughout the process.', 'theme.template.page_sell_with_pera.one_point_of_contact_from_first_valuation_to_key_handover_direct_clear_a' ) ); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: MEDIA / VISUAL -->
                <div>
                    <div class="media-frame media-frame--image-fill">
                        <?php
                        echo wp_get_attachment_image(
                            55704,
                            'full',
                            false,
                            array(
                                'class'    => 'media-image', // IMPORTANT: this class
                                'loading'  => 'lazy',
                                'decoding' => 'async',
                                'alt'      => esc_attr(
                                    'Istanbul real estate market overview by Pera Property'
                                ),
                            )
                        );
                        ?>
                    </div>
                </div>





            </div>
        </div>
    </section>

    <!-- WHY SELL WITH US – FEATURE GRID -->
    <section class="section">
        <div class="section-header section-header--center">
            <h2><?php echo esc_html( pera_ml_ui( 'What you gain when you sell with Pera', 'theme.template.page_sell_with_pera.what_you_gain_when_you_sell_with_pera' ) ); ?></h2>
            <p>
                <?php echo esc_html( pera_ml_ui( 'We combine Istanbul market experience, international investor reach and a
                structured selling process to protect both your price and your time.', 'theme.template.page_sell_with_pera.we_combine_istanbul_market_experience_international_investor_reach_and_a' ) ); ?>
            </p>
        </div>

        <div class="feature-grid">
            <!-- FEATURE 1 -->
            <article class="feature-card">
                <div class="feature-card-header">
                    <div class="feature-card-icon">
                        <svg class="icon" aria-hidden="true">
                            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-map' ); ?>"></use>
                        </svg>
                    </div>
                    <h3><?php echo esc_html( pera_ml_ui( 'Accurate pricing strategy', 'theme.template.page_sell_with_pera.accurate_pricing_strategy' ) ); ?></h3>
                </div>
                <div class="feature-card-body">
                    <p>
                        <?php echo esc_html( pera_ml_ui( 'We benchmark your property against recent sales, active listings and
                        investor demand in your specific micro-location, not just the district
                        average.', 'theme.template.page_sell_with_pera.we_benchmark_your_property_against_recent_sales_active_listings_and_inve' ) ); ?>
                    </p>
                </div>
            </article>

            <!-- FEATURE 2 -->
            <article class="feature-card">
                <div class="feature-card-header">
                    <div class="feature-card-icon">
                        <svg class="icon" aria-hidden="true">
                            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-pdf' ); ?>"></use>
                        </svg>
                    </div>
                    <h3><?php echo esc_html( pera_ml_ui( 'Professional marketing', 'theme.template.page_sell_with_pera.professional_marketing' ) ); ?></h3>
                </div>
                <div class="feature-card-body">
                    <p>
                        <?php echo esc_html( pera_ml_ui( 'Clean photography, clear plans, bilingual presentation and targeted
                        campaigns ensure your property stands out instead of getting lost among
                        generic listings.', 'theme.template.page_sell_with_pera.clean_photography_clear_plans_bilingual_presentation_and_targeted_campai' ) ); ?>
                    </p>
                </div>
            </article>

            <!-- FEATURE 3 -->
            <article class="feature-card">
                <div class="feature-card-header">
                    <div class="feature-card-icon">
                        <svg class="icon" aria-hidden="true">
                            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-map' ); ?>"></use>
                        </svg>
                    </div>
                    <h3><?php echo esc_html( pera_ml_ui( 'Serious buyers only', 'theme.template.page_sell_with_pera.serious_buyers_only' ) ); ?></h3>
                </div>
                <div class="feature-card-body">
                    <p>
                        <?php echo esc_html( pera_ml_ui( 'We pre-qualify buyers, manage viewing schedules and filter out “property
                        tourists”, so the people walking through your door are real prospects.', 'theme.template.page_sell_with_pera.we_pre_qualify_buyers_manage_viewing_schedules_and_filter_out_property_t' ) ); ?>
                    </p>
                </div>
            </article>
        </div>
    </section>

    <!-- OUR PROCESS – INFO STEPS -->
    <section id="process" class="section section-soft">
        <div class="section-header section-header--center">
            <h2><?php echo esc_html( pera_ml_ui( 'How the selling process works', 'theme.template.page_sell_with_pera.how_the_selling_process_works' ) ); ?></h2>
            <p>
                <?php echo esc_html( pera_ml_ui( 'A clear, structured roadmap from first chat to completed sale. You always know
                what is happening, and what comes next.', 'theme.template.page_sell_with_pera.a_clear_structured_roadmap_from_first_chat_to_completed_sale_you_always_' ) ); ?>
            </p>
        </div>

        <div class="info-steps">
            <!-- STEP 1 -->
            <div class="info-step">
                <div class="info-step-icon">
                    <span class="info-step-number">1</span>
                </div>
                <div class="info-step-body">
                    <h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Initial conversation & property review', 'theme.template.page_sell_with_pera.initial_conversation_and_property_review' ) ); ?></h3>
                    <p class="info-step-text">
                        <?php echo esc_html( pera_ml_ui( 'We listen to your goals, review your property details and documents,
                        and advise whether a sale, rental or hold strategy makes most sense.', 'theme.template.page_sell_with_pera.we_listen_to_your_goals_review_your_property_details_and_documents_and_a' ) ); ?>
                    </p>
                </div>
            </div>

            <!-- STEP 2 -->
            <div class="info-step">
                <div class="info-step-icon">
                    <span class="info-step-number">2</span>
                </div>
                <div class="info-step-body">
                    <h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Valuation & pricing strategy', 'theme.template.page_sell_with_pera.valuation_and_pricing_strategy' ) ); ?></h3>
                    <p class="info-step-text">
                        <?php echo esc_html( pera_ml_ui( 'We prepare a realistic price range backed by comps and demand data,
                        then agree the asking price and negotiation boundaries with you.', 'theme.template.page_sell_with_pera.we_prepare_a_realistic_price_range_backed_by_comps_and_demand_data_then_' ) ); ?>
                    </p>
                </div>
            </div>

            <!-- STEP 3 -->
            <div class="info-step">
                <div class="info-step-icon">
                    <span class="info-step-number">3</span>
                </div>
                <div class="info-step-body">
                    <h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Marketing & viewings', 'theme.template.page_sell_with_pera.marketing_and_viewings' ) ); ?></h3>
                    <p class="info-step-text">
                        <?php echo esc_html( pera_ml_ui( 'Your listing goes live across our channels and direct investor network.
                        We handle enquiries, schedule viewings and keep you updated with
                        feedback.', 'theme.template.page_sell_with_pera.your_listing_goes_live_across_our_channels_and_direct_investor_network_w' ) ); ?>
                    </p>
                </div>
            </div>

            <!-- STEP 4 -->
            <div class="info-step">
                <div class="info-step-icon">
                    <span class="info-step-number">4</span>
                </div>
                <div class="info-step-body">
                    <h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Offer, negotiation & paperwork', 'theme.template.page_sell_with_pera.offer_negotiation_and_paperwork' ) ); ?></h3>
                    <p class="info-step-text">
                        <?php echo esc_html( pera_ml_ui( 'Once offers arrive, we negotiate terms in your favour, coordinate the
                        sales contract, legal checks and tapu process together with your chosen
                        lawyer or our partner firms.', 'theme.template.page_sell_with_pera.once_offers_arrive_we_negotiate_terms_in_your_favour_coordinate_the_sale' ) ); ?>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- WHAT WE HANDLE FOR YOU – 2 COL LAYOUT -->
    <section class="section">
        <div class="container grid-2">
            <div>
                <h2><?php echo esc_html( pera_ml_ui( 'Everything taken care of, from start to finish.', 'theme.template.page_sell_with_pera.everything_taken_care_of_from_start_to_finish' ) ); ?></h2>
                <p>
                    <?php echo esc_html( pera_ml_ui( 'Selling a property in Istanbul doesn’t have to be chaotic. We project-manage
                    the entire journey so you can focus on your life, not paperwork and phone calls.', 'theme.template.page_sell_with_pera.selling_a_property_in_istanbul_doesn_t_have_to_be_chaotic_we_project_man' ) ); ?>
                </p>
            </div>
            <div>
                <ul class="checklist checklist--circle">
                  <li>
                    <?php echo esc_html( pera_ml_ui( 'Pre-sale advice on minor improvements that increase value', 'theme.template.page_sell_with_pera.pre_sale_advice_on_minor_improvements_that_increase_value' ) ); ?>
                  </li>
                
                  <li>
                    <?php echo esc_html( pera_ml_ui( 'Document check: tapu, plans, iskan, mortgage and encumbrances', 'theme.template.page_sell_with_pera.document_check_tapu_plans_iskan_mortgage_and_encumbrances' ) ); ?>
                  </li>
                
                  <li>
                    <?php echo esc_html( pera_ml_ui( 'Professional photos and listing preparation', 'theme.template.page_sell_with_pera.professional_photos_and_listing_preparation' ) ); ?>
                  </li>
                
                  <li>
                    <?php echo esc_html( pera_ml_ui( 'Coordinating viewings with tenants or caretakers where relevant', 'theme.template.page_sell_with_pera.coordinating_viewings_with_tenants_or_caretakers_where_relevant' ) ); ?>
                  </li>
                
                  <li>
                    <?php echo esc_html( pera_ml_ui( 'Negotiation strategy and best-offer analysis', 'theme.template.page_sell_with_pera.negotiation_strategy_and_best_offer_analysis' ) ); ?>
                  </li>
                
                  <li>
                    <?php echo esc_html( pera_ml_ui( 'Guidance on tax, fees and timelines together with your advisors', 'theme.template.page_sell_with_pera.guidance_on_tax_fees_and_timelines_together_with_your_advisors' ) ); ?>
                  </li>
                </ul>

            </div>
        </div>

    <section class="section">
        <div class="container">
            <header class="section-header section-header--center">
                <h2><?php echo esc_html( pera_ml_ui( 'How to sell your property in Istanbul', 'theme.template.page_sell_with_pera.how_to_sell_your_property_in_istanbul' ) ); ?></h2>
            </header>

            <div class="content-panel-box">
                <p><?php echo esc_html( pera_ml_ui( 'Our seller journey starts with an initial review of your property, goals and timing, then moves to a realistic valuation based on Istanbul market conditions and your building profile.', 'theme.template.page_sell_with_pera.our_seller_journey_starts_with_an_initial_review_of_your_property_goals_' ) ); ?></p>
                <p><?php echo esc_html( pera_ml_ui( 'Once we agree the strategy, we help prepare key documents, launch professional marketing, coordinate buyer viewings and report back with feedback that supports pricing decisions.', 'theme.template.page_sell_with_pera.once_we_agree_the_strategy_we_help_prepare_key_documents_launch_professi' ) ); ?></p>
                <p><?php echo esc_html( pera_ml_ui( 'When offers arrive, we negotiate terms in line with your priorities, coordinate due diligence and support the title deed transfer process so the sale completes smoothly.', 'theme.template.page_sell_with_pera.when_offers_arrive_we_negotiate_terms_in_line_with_your_priorities_coord' ) ); ?></p>
            </div>
        </div>
    </section>

    <section class="section section-soft">
        <div class="container">
            <header class="section-header section-header--center">
                <h2><?php echo esc_html( pera_ml_ui( 'Free Istanbul property valuation', 'theme.template.page_sell_with_pera.free_istanbul_property_valuation' ) ); ?></h2>
            </header>

            <div class="content-panel-box">
                <p><?php echo esc_html( pera_ml_ui( 'Our free valuation combines recent comparable listings and sales where available with on-the-ground insight from active buyer demand in your micro-location.', 'theme.template.page_sell_with_pera.our_free_valuation_combines_recent_comparable_listings_and_sales_where_a' ) ); ?></p>
                <p><?php echo esc_html( pera_ml_ui( 'We assess building age and condition, floor level, view quality, layout efficiency and any outdoor space, then adjust for demand from end-users and investors. We also consider rental and investment potential to recommend a defensible asking range for today’s Istanbul market.', 'theme.template.page_sell_with_pera.we_assess_building_age_and_condition_floor_level_view_quality_layout_eff' ) ); ?></p>
            </div>
        </div>
    </section>

    <section class="section section-soft">
        <div class="container">
            <header class="section-header section-header--center">
                <h2><?php echo esc_html( pera_ml_ui( 'Typical timeline for selling property in Istanbul', 'theme.template.page_sell_with_pera.typical_timeline_for_selling_property_in_istanbul' ) ); ?></h2>
                <p><?php echo esc_html( pera_ml_ui( 'Every sale moves at a different pace depending on district, price point, property condition and buyer demand. We keep you updated at each stage so decisions stay clear and practical.', 'theme.template.page_sell_with_pera.every_sale_moves_at_a_different_pace_depending_on_district_price_point_p' ) ); ?></p>
            </header>

            <div class="info-steps">
                <div class="info-step">
                    <div class="info-step-icon"><span class="info-step-number">1</span></div>
                    <div class="info-step-body">
                        <h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Initial review and valuation', 'theme.template.page_sell_with_pera.initial_review_and_valuation' ) ); ?></h3>
                        <p class="info-step-text"><?php echo esc_html( pera_ml_ui( 'We review your property details, location, key documents and seller goals, then agree an indicative valuation range for the current market.', 'theme.template.page_sell_with_pera.we_review_your_property_details_location_key_documents_and_seller_goals_' ) ); ?></p>
                    </div>
                </div>

                <div class="info-step">
                    <div class="info-step-icon"><span class="info-step-number">2</span></div>
                    <div class="info-step-body">
                        <h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Listing requirements and document check', 'theme.template.page_sell_with_pera.listing_requirements_and_document_check' ) ); ?></h3>
                        <p class="info-step-text"><?php echo esc_html( pera_ml_ui( 'Before launch, we usually verify tapu, ID/passport, tax number, DASK where applicable, iskan where relevant, mortgage or debt checks, and power of attorney documentation for overseas owners.', 'theme.template.page_sell_with_pera.before_launch_we_usually_verify_tapu_id_passport_tax_number_dask_where_a' ) ); ?></p>
                    </div>
                </div>

                <div class="info-step">
                    <div class="info-step-icon"><span class="info-step-number">3</span></div>
                    <div class="info-step-body">
                        <h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Marketing preparation', 'theme.template.page_sell_with_pera.marketing_preparation' ) ); ?></h3>
                        <p class="info-step-text"><?php echo esc_html( pera_ml_ui( 'We prepare professional photos, video where suitable, floor plans, bilingual listing copy and a launch pricing strategy aligned with your timeline.', 'theme.template.page_sell_with_pera.we_prepare_professional_photos_video_where_suitable_floor_plans_bilingua' ) ); ?></p>
                    </div>
                </div>

                <div class="info-step">
                    <div class="info-step-icon"><span class="info-step-number">4</span></div>
                    <div class="info-step-body">
                        <h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Launch and qualified viewings', 'theme.template.page_sell_with_pera.launch_and_qualified_viewings' ) ); ?></h3>
                        <p class="info-step-text"><?php echo esc_html( pera_ml_ui( 'Your listing goes live, buyer enquiries are screened, and viewings are arranged with you, your tenant or your caretaker as needed.', 'theme.template.page_sell_with_pera.your_listing_goes_live_buyer_enquiries_are_screened_and_viewings_are_arr' ) ); ?></p>
                    </div>
                </div>

                <div class="info-step">
                    <div class="info-step-icon"><span class="info-step-number">5</span></div>
                    <div class="info-step-body">
                        <h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Offer negotiation', 'theme.template.page_sell_with_pera.offer_negotiation' ) ); ?></h3>
                        <p class="info-step-text"><?php echo esc_html( pera_ml_ui( 'We compare offers by price, payment terms, deposit structure, timing and buyer readiness so you can choose the strongest path to completion.', 'theme.template.page_sell_with_pera.we_compare_offers_by_price_payment_terms_deposit_structure_timing_and_bu' ) ); ?></p>
                    </div>
                </div>

                <div class="info-step">
                    <div class="info-step-icon"><span class="info-step-number">6</span></div>
                    <div class="info-step-body">
                        <h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Contract, legal checks and tapu transfer', 'theme.template.page_sell_with_pera.contract_legal_checks_and_tapu_transfer' ) ); ?></h3>
                        <p class="info-step-text"><?php echo esc_html( pera_ml_ui( 'We coordinate with your lawyer through contract checks, title deed office steps, final payment and handover, while keeping communication consistent until completion.', 'theme.template.page_sell_with_pera.we_coordinate_with_your_lawyer_through_contract_checks_title_deed_office' ) ); ?></p>
                    </div>
                </div>
            </div>

            <div class="content-panel-box" style="margin-top:16px;">
                <p><strong><?php echo esc_html( pera_ml_ui( 'Before we list your property, we usually review:', 'theme.template.page_sell_with_pera.before_we_list_your_property_we_usually_review' ) ); ?></strong></p>
                <ul class="checklist checklist--circle">
                    <li><?php echo esc_html( pera_ml_ui( 'Tapu/title deed and ownership details', 'theme.template.page_sell_with_pera.tapu_title_deed_and_ownership_details' ) ); ?></li>
                    <li><?php echo esc_html( pera_ml_ui( 'Building age, condition and occupancy/tenant status', 'theme.template.page_sell_with_pera.building_age_condition_and_occupancy_tenant_status' ) ); ?></li>
                    <li><?php echo esc_html( pera_ml_ui( 'Asking price expectations and launch strategy', 'theme.template.page_sell_with_pera.asking_price_expectations_and_launch_strategy' ) ); ?></li>
                    <li><?php echo esc_html( pera_ml_ui( 'DASK and iskan where relevant', 'theme.template.page_sell_with_pera.dask_and_iskan_where_relevant' ) ); ?></li>
                    <li><?php echo esc_html( pera_ml_ui( 'Debts, mortgages or site-management dues', 'theme.template.page_sell_with_pera.debts_mortgages_or_site_management_dues' ) ); ?></li>
                </ul>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container grid-2">
            <div>
                <h2><?php echo esc_html( pera_ml_ui( 'Documents needed to sell property in Turkey', 'theme.template.page_sell_with_pera.documents_needed_to_sell_property_in_turkey' ) ); ?></h2>
                <p><?php echo esc_html( pera_ml_ui( 'Requirements can vary by property and ownership structure, but sellers should usually prepare the following before launch:', 'theme.template.page_sell_with_pera.requirements_can_vary_by_property_and_ownership_structure_but_sellers_sh' ) ); ?></p>
            </div>
            <div>
                <ul class="checklist checklist--circle">
                    <li><?php echo esc_html( pera_ml_ui( 'Tapu (title deed)', 'theme.template.page_sell_with_pera.tapu_title_deed' ) ); ?></li>
                    <li><?php echo esc_html( pera_ml_ui( 'Passport or Turkish ID', 'theme.template.page_sell_with_pera.passport_or_turkish_id' ) ); ?></li>
                    <li><?php echo esc_html( pera_ml_ui( 'Tax number', 'theme.template.page_sell_with_pera.tax_number' ) ); ?></li>
                    <li><?php echo esc_html( pera_ml_ui( 'DASK (earthquake insurance), where applicable', 'theme.template.page_sell_with_pera.dask_earthquake_insurance_where_applicable' ) ); ?></li>
                    <li><?php echo esc_html( pera_ml_ui( 'Iskan (habitation certificate), where relevant', 'theme.template.page_sell_with_pera.iskan_habitation_certificate_where_relevant' ) ); ?></li>
                    <li><?php echo esc_html( pera_ml_ui( 'Mortgage, debt and encumbrance checks', 'theme.template.page_sell_with_pera.mortgage_debt_and_encumbrance_checks' ) ); ?></li>
                    <li><?php echo esc_html( pera_ml_ui( 'Power of attorney documents if the seller is based abroad', 'theme.template.page_sell_with_pera.power_of_attorney_documents_if_the_seller_is_based_abroad' ) ); ?></li>
                </ul>
            </div>
        </div>
    </section>

    <section class="section section-soft">
        <div class="container grid-2">
            <div>
                <h2><?php echo esc_html( pera_ml_ui( 'Seller fees, taxes and commission', 'theme.template.page_sell_with_pera.seller_fees_taxes_and_commission' ) ); ?></h2>
                <p><strong><?php echo esc_html( pera_ml_ui( 'Pera Property’s standard sales agency fee is 4% unless otherwise agreed in writing.', 'theme.template.page_sell_with_pera.pera_property_s_standard_sales_agency_fee_is_4_unless_otherwise_agreed_i' ) ); ?></strong></p>
                <p><?php echo esc_html( pera_ml_ui( 'Every sale is different, so we recommend confirming your total transaction costs early and reviewing tax points with qualified advisors before signing.', 'theme.template.page_sell_with_pera.every_sale_is_different_so_we_recommend_confirming_your_total_transactio' ) ); ?></p>
            </div>
            <div>
                <ul class="checklist checklist--circle">
                    <li><?php echo esc_html( pera_ml_ui( 'Title deed expenses and transfer costs', 'theme.template.page_sell_with_pera.title_deed_expenses_and_transfer_costs' ) ); ?></li>
                    <li><?php echo esc_html( pera_ml_ui( 'Your capital gains tax position', 'theme.template.page_sell_with_pera.your_capital_gains_tax_position' ) ); ?></li>
                    <li><?php echo esc_html( pera_ml_ui( 'Independent legal and accounting advice', 'theme.template.page_sell_with_pera.independent_legal_and_accounting_advice' ) ); ?></li>
                    <li><?php echo esc_html( pera_ml_ui( 'Outstanding mortgage or site-management debts', 'theme.template.page_sell_with_pera.outstanding_mortgage_or_site_management_debts' ) ); ?></li>
                </ul>
                <p><?php echo esc_html( pera_ml_ui( 'We provide practical sale guidance, but formal tax and legal advice should come from your lawyer or accountant.', 'theme.template.page_sell_with_pera.we_provide_practical_sale_guidance_but_formal_tax_and_legal_advice_shoul' ) ); ?></p>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <header class="section-header section-header--center">
                <h2><?php echo esc_html( pera_ml_ui( 'Selling your Istanbul property from abroad', 'theme.template.page_sell_with_pera.selling_your_istanbul_property_from_abroad' ) ); ?></h2>
            </header>
            <div class="content-panel-box">
                <p><?php echo esc_html( pera_ml_ui( 'If you live outside Turkey, we can coordinate valuation remotely, arrange photos and videos, and manage access with tenants or caretakers for buyer viewings.', 'theme.template.page_sell_with_pera.if_you_live_outside_turkey_we_can_coordinate_valuation_remotely_arrange_' ) ); ?></p>
                <p><?php echo esc_html( pera_ml_ui( 'Our team handles offer negotiation and can coordinate with your lawyer on power of attorney and transaction documents, while supporting you through each tapu milestone until completion.', 'theme.template.page_sell_with_pera.our_team_handles_offer_negotiation_and_can_coordinate_with_your_lawyer_o' ) ); ?></p>
                <p><?php echo esc_html( pera_ml_ui( 'To plan next steps,', 'theme.template.page_sell_with_pera.to_plan_next_steps' ) ); ?> <a href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'contact us', 'theme.template.page_sell_with_pera.contact_us' ) ); ?></a><?php echo esc_html( pera_ml_ui( ', learn more', 'theme.template.page_sell_with_pera.learn_more' ) ); ?> <a href="<?php echo esc_url( home_url( '/about-us/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'about our team', 'theme.template.page_sell_with_pera.about_our_team' ) ); ?></a><?php echo esc_html( pera_ml_ui( ', or explore our', 'theme.template.page_sell_with_pera.or_explore_our' ) ); ?> <a href="<?php echo esc_url( home_url( '/rent-your-istanbul-real-estate/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'property management services in Istanbul', 'theme.template.page_sell_with_pera.property_management_services_in_istanbul' ) ); ?></a><?php echo esc_html( pera_ml_ui( '. You can also read our', 'theme.template.page_sell_with_pera.you_can_also_read_our' ) ); ?> <a href="<?php echo esc_url( home_url( '/category/regional-guides/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Istanbul area guides', 'theme.template.page_sell_with_pera.istanbul_area_guides' ) ); ?></a> <?php echo esc_html( pera_ml_ui( 'for district-level demand insights.', 'theme.template.page_sell_with_pera.for_district_level_demand_insights' ) ); ?></p>
            </div>
        </div>
    </section>

    <section class="faq-section section section-soft">
        <div class="container">
            <h2><?php echo esc_html( pera_ml_ui( 'Frequently asked questions about selling property in Istanbul', 'theme.template.page_sell_with_pera.frequently_asked_questions_about_selling_property_in_istanbul' ) ); ?></h2>

            <div class="faq-accordion">
                <?php
                $faq_index = 0;
                foreach ( $sell_with_pera_faq_items as $faq_item ) :
                    $question = isset( $faq_item['question'] ) ? trim( (string) $faq_item['question'] ) : '';
                    $answer   = isset( $faq_item['answer'] ) ? trim( (string) $faq_item['answer'] ) : '';
                    if ( $question === '' || $answer === '' ) {
                        continue;
                    }
                ?>
                    <details class="faq-item"<?php echo $faq_index === 0 ? ' open' : ''; ?>>
                        <summary><?php echo esc_html( $question ); ?></summary>
                        <div class="faq-answer">
                            <?php echo wp_kses_post( wpautop( $answer ) ); ?>
                        </div>
                    </details>
                    <?php $faq_index++; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>


    <!-- ABOUT PERA -->
    <?php get_template_part( 'parts/about-pera' ); ?>

    
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
                            <?php echo esc_html( pera_ml_ui( 'Reliable, data-driven advice.', 'theme.template.page_sell_with_pera.reliable_data_driven_advice' ) ); ?>
                        </li>
    
                        <li>
                            <?php echo esc_html( pera_ml_ui( 'On-the-ground Istanbul expertise.', 'theme.template.page_sell_with_pera.on_the_ground_istanbul_expertise' ) ); ?>
                        </li>
    
                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Multi-lingual support.', 'theme.template.page_sell_with_pera.multi_lingual_support' ) ); ?>
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
                            'large',
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
                        <h3 class="text-light"><?php echo esc_html( pera_ml_ui( 'Speak with a Consultant', 'theme.template.page_sell_with_pera.speak_with_a_consultant' ) ); ?></h3>
    
                        <div class="hero-actions flex-center">
                            <a href="https://www.peraproperty.com/contact-us/" class="btn btn--solid btn--blue">
                                <?php echo esc_html( pera_ml_ui( 'Book a consultation', 'theme.template.page_sell_with_pera.book_a_consultation' ) ); ?>
                            </a>
    
                            <a href="<?php echo esc_url( pera_get_whatsapp_url( pera_ml_ui( 'Hello Pera Property, I\'d like to discuss Istanbul real estate.', 'theme.template.page_sell_with_pera.whatsapp_prefill' ) ) ); ?>"
                               class="btn btn--solid btn--green"
                               data-whatsapp="1"
                               data-whatsapp-type="service_cta"
                               data-track-channel="whatsapp"
                               data-track-intent="high"
                               data-track-source="template"
                               data-track-context="sell_with_pera"
                               data-track-ga4-event="whatsapp_click"
                               data-track-crm-event="whatsapp_click">
                                <?php echo esc_html( pera_ml_ui( 'Chat on WhatsApp', 'theme.template.page_sell_with_pera.chat_on_whatsapp' ) ); ?>
                            </a>
                        </div>
                    </div>
    
                </div><!-- .media-frame -->
    
            </div><!-- .content-panel-grid -->

            <div>
    
                <?php if ( isset( $_GET['sr_status'] ) && $_GET['sr_status'] === 'sent' ) : ?>
                    <div class="form-success">
                        <?php echo esc_html( pera_ml_ui( 'Thank you – we have received your details. A Pera consultant will contact you shortly.', 'theme.template.page_sell_with_pera.thank_you_we_have_received_your_details_a_pera_consultant_will_contact_y' ) ); ?>
                    </div>
                <?php endif; ?>
    

    
                <?php
                get_template_part('parts/enquiry-form', null, array(
                  'context'      => 'sell',
                  'heading'      => 'Request a free appraisal',
                  'intro'        => 'Share a few details and we will prepare an initial sale strategy and price guidance for your property in Istanbul.',
                  'submit_label' => 'Send my details',
                  'form_context' => 'sell-page',
                ));

                ?>
    
    
                
            </div><!-- .enquiry-cta -->
    
    
        </div><!-- .content-panel-box -->
    </section>


</main>

<?php
get_footer();
