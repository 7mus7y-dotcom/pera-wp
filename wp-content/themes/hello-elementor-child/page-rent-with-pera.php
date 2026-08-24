<?php
/**
 * Template Name: Rent with Pera
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$is_rent_page_seo_preview = current_user_can( 'manage_options' );
if ( $is_rent_page_seo_preview && isset( $_GET['rent_page_version'] ) && 'seo' === sanitize_key( wp_unslash( $_GET['rent_page_version'] ) ) ) {
    if ( ! defined( 'DONOTCACHEPAGE' ) ) {
        define( 'DONOTCACHEPAGE', true );
    }
    nocache_headers();
}

$hero_heading = $args['hero_heading'] ?? pera_ml_ui( 'Talk to Pera about your Istanbul plans', 'theme.template.page_rent_with_pera.hero_heading_fallback' );
$hero_intro   = $args['hero_intro']   ?? pera_ml_ui( 'Whether you’re buying, selling, or renting in Istanbul, our team can walk you through the numbers, the legal steps, and the neighbourhoods that fit your strategy.', 'theme.template.page_rent_with_pera.hero_intro_fallback' );

if ( ! function_exists( 'pera_rent_with_pera_faq_schema' ) ) {
    function pera_rent_with_pera_faq_schema() {
        if ( ! is_page_template( 'page-rent-with-pera.php' ) ) {
            return;
        }
        $is_preview = current_user_can( 'manage_options' );

        $faq_entities = array(
            array(
                'question' => 'What does your full property management in Istanbul service include?',
                'answer'   => 'Our service is fully hands-off for Istanbul property owners. We handle tenant sourcing, marketing, viewings, lease preparation, tenant screening, contract negotiation, renewals, maintenance coordination, and ongoing tenant communication. We also assist with utility setup, tax guidance, and end-of-tenancy processes.',
            ),
            array(
                'question' => 'What is your rental management fee?',
                'answer'   => 'Our full property management service in Istanbul is charged at 12% + VAT. This covers the ongoing management of the property throughout the tenancy, including renewals and day-to-day tenant management.',
            ),
            array(
                'question' => 'Are there any additional costs?',
                'answer'   => 'Yes — the management fee covers our service only. Property-related costs such as maintenance, repairs, taxes, insurance, utilities, or building charges are separate and always subject to your approval before any work is carried out.',
            ),
            array(
                'question' => 'How do you find and select tenants?',
                'answer'   => 'As part of our rental management in Istanbul, we market your property across our network and screen all applicants carefully. This typically includes employment and income checks, documentation review, and — where appropriate — requiring a Turkish guarantor. Our focus is always on placing reliable, financially stable tenants.',
            ),
            array(
                'question' => 'Will I approve the tenant before the contract is signed?',
                'answer'   => 'Yes. We present you with the proposed tenant and agreed terms before any contract is finalised. No tenancy is confirmed without your approval.',
            ),
            array(
                'question' => 'Do you provide the rental contract in English?',
                'answer'   => 'Yes. We can prepare bilingual Turkish and English contracts so that you fully understand the terms of the agreement while ensuring compliance with local regulations.',
            ),
            array(
                'question' => 'How are rent increases handled?',
                'answer'   => 'Rent increases are managed in line with Turkish law, typically based on the official CPI (TÜFE) cap. We handle negotiations with the tenant and advise you on the optimal approach at each renewal period.',
            ),
            array(
                'question' => 'Do you use any legal protection for the landlord?',
                'answer'   => 'Yes. Where appropriate, we arrange a notarised exit undertaking (tahliye taahhütnamesi), which provides additional legal protection in case the tenant does not vacate at the end of the agreed term.',
            ),
            array(
                'question' => 'How is the tenant deposit handled?',
                'answer'   => 'We typically secure a two-month deposit, which is held in accordance with Turkish rental practices. At the end of the tenancy, the property is inspected and any agreed deductions are applied before the remaining balance is returned.',
            ),
            array(
                'question' => 'How are utilities managed?',
                'answer'   => 'For tenanted properties, utilities are usually transferred into the tenant’s name. For new properties, the owner may need to open the accounts initially. We manage and coordinate this process on your behalf.',
            ),
            array(
                'question' => 'How do you handle maintenance and repairs?',
                'answer'   => 'If an issue arises, we coordinate with trusted contractors, obtain quotes where necessary, and seek your approval before proceeding. No expense is incurred without your consent, so Istanbul property owners stay in control.',
            ),
            array(
                'question' => 'Do I receive reports or updates?',
                'answer'   => 'Rent is typically paid directly to the owner, so formal monthly reporting is not always required. However, we keep you informed of any key developments and can provide structured reporting if you prefer a more hands-on overview.',
            ),
            array(
                'question' => 'Can I take over management myself later?',
                'answer'   => 'Yes. You are free to take over management at any time with reasonable notice. We will ensure a smooth handover of all relevant documents and tenant information.',
            ),
        );

        if ( $is_preview ) {
            $faq_entities = array(
                array( 'question' => 'Can foreigners rent out property in Istanbul?', 'answer' => 'Yes. Foreign owners can rent out eligible property in Istanbul. We help with practical steps, tenancy setup, and ongoing landlord support.' ),
                array( 'question' => 'What does property management in Istanbul include?', 'answer' => 'Our property management service covers rental valuation, marketing, tenant sourcing, screening, tenancy coordination, rent collection, maintenance oversight, inspections, and owner reporting.' ),
                array( 'question' => 'Do you provide long-term rental management?', 'answer' => 'Yes. Long-term rental management is our core service, with optional short-term support only where suitable for the property and location.' ),
                array( 'question' => 'Can you manage my Istanbul apartment if I live overseas?', 'answer' => 'Yes. We support overseas landlords with day-to-day tenant communication, maintenance coordination, approvals, and regular updates.' ),
                array( 'question' => 'Do you help with utilities, DASK and property tax?', 'answer' => 'Yes. We coordinate utility setup and support owners with DASK and annual property tax practicalities as part of our landlord services.' ),
                array( 'question' => 'How do you handle repairs and maintenance costs?', 'answer' => 'We coordinate contractors, share quotes, and secure owner approval before third-party costs are committed.' ),
                array( 'question' => 'Which Istanbul areas are best for rental demand?', 'answer' => 'Demand is often strong in central and well-connected districts such as Beşiktaş, Şişli, Nişantaşı, Bomonti, Kağıthane, Kadıköy, Üsküdar, Bakırköy and Atakent/Küçükçekmece.' ),
                array( 'question' => 'Do you help furnish or prepare a property for rent?', 'answer' => 'Yes. We advise on furnishing, presentation, and practical setup to improve rental demand and tenant quality.' ),
            );
        }

        $main_entity = array_map(
            static function ( array $item ): array {
                return array(
                    '@type'          => 'Question',
                    'name'           => $item['question'],
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text'  => $item['answer'],
                    ),
                );
            },
            $faq_entities
        );

        $schema = array(
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $main_entity,
        );

        echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
    }
}
add_action( 'wp_head', 'pera_rent_with_pera_faq_schema', 25 );


get_header();
?>

<main id="primary" class="site-main">

    <!-- =====================================
     HERO (RENT WITH PERA)
     Canonical structure + existing content
     ===================================== -->
        <section class="hero hero--left hero--rent" id="rent-hero">
        
          <div class="hero__media" aria-hidden="true">
            <?php
              // Optional featured image support (future-proof)
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
                // Fallback background (vopbesiktas.svg – attachment ID 55756)
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

        
            <h1><?php echo esc_html( pera_ml_ui( 'Property management in Istanbul for overseas and local owners', 'theme.template.page_rent_with_pera.property_management_in_istanbul_for_overseas_and_local_owners' ) ); ?></h1>
            <?php if ( $is_rent_page_seo_preview ) : ?>
                <p class="lead"><?php echo esc_html( pera_ml_ui( 'Rent out property in Istanbul with a trusted Istanbul property management company. We focus on long-term rental management in Istanbul for local and foreign owners, covering tenant sourcing, rent collection, property care, and clear owner reporting.', 'theme.template.page_rent_with_pera.rent_out_property_in_istanbul_with_a_trusted_istanbul_property_managemen' ) ); ?></p>
            <?php else : ?>
                <p class="lead">
                  <?php echo esc_html( pera_ml_ui( 'Pera provides full-service rental and property management in Istanbul, including tenant sourcing, contracts, rent collection, maintenance coordination, renewals, and dedicated owner support.', 'theme.template.page_rent_with_pera.pera_provides_full_service_rental_and_property_management_in_istanbul_in' ) ); ?>
                </p>
            <?php endif; ?>
        
            <div class="hero-actions">
              <a href="#pricing" class="btn btn--solid btn--blue"><?php echo esc_html( pera_ml_ui( 'See pricing', 'theme.template.page_rent_with_pera.see_pricing' ) ); ?></a>
              <a href="#contact" class="btn btn--solid btn--green"><?php echo esc_html( pera_ml_ui( 'Get a valuation', 'theme.template.page_rent_with_pera.get_a_valuation' ) ); ?></a>
            </div>
          </div>
        
        </section>



    <!-- WHY RENT WITH PERA -->
    <section class="content-panel content-panel--overlap-hero">
        <div class="content-panel-box">
            <div class="content-panel-grid">

                <!-- LEFT -->
                <div>
                    <header class="section-header">
                        <h2><?php echo esc_html( pera_ml_ui( 'Why choose Pera for property management in Istanbul?', 'theme.template.page_rent_with_pera.why_choose_pera_for_property_management_in_istanbul' ) ); ?></h2>
                        <p>
                            <?php echo esc_html( pera_ml_ui( 'We deliver rental management for Istanbul property owners through professional marketing,
                            strong tenant checks, and hands-on service that protects your time and your investment.
                            If you are preparing', 'theme.template.page_rent_with_pera.we_deliver_rental_management_for_istanbul_property_owners_through_profes' ) ); ?> <a href="/property/"><?php echo esc_html( pera_ml_ui( 'a property for rent in Istanbul', 'theme.template.page_rent_with_pera.a_property_for_rent_in_istanbul' ) ); ?></a><?php echo esc_html( pera_ml_ui( ', or considering', 'theme.template.page_rent_with_pera.or_considering' ) ); ?>
                            <a href="/sell-your-istanbul-real-estate/"><?php echo esc_html( pera_ml_ui( 'selling your Istanbul property', 'theme.template.page_rent_with_pera.selling_your_istanbul_property' ) ); ?></a><?php echo esc_html( pera_ml_ui( ', our team can advise on the best route.', 'theme.template.page_rent_with_pera.our_team_can_advise_on_the_best_route' ) ); ?>
                        </p>
                    </header>

                    <ul class="checklist checklist--circle">

                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Marketing on all major platforms.', 'theme.template.page_rent_with_pera.marketing_on_all_major_platforms' ) ); ?>
                        </li>

                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Full tenant screening & ID verification.', 'theme.template.page_rent_with_pera.full_tenant_screening_and_id_verification' ) ); ?>
                        </li>

                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Maintenance, repairs and inspections.', 'theme.template.page_rent_with_pera.maintenance_repairs_and_inspections' ) ); ?>
                        </li>

                        <li>
                            <?php echo esc_html( pera_ml_ui( '24/7 support for tenants.', 'theme.template.page_rent_with_pera.24_7_support_for_tenants' ) ); ?>
                        </li>

                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Monthly statements & full transparency.', 'theme.template.page_rent_with_pera.monthly_statements_and_full_transparency' ) ); ?>
                        </li>

                    </ul>
                </div>
                
                <!-- RIGHT: MEDIA / VISUAL -->
                <div>
                    <div class="media-frame media-frame--image-fill">
                        <?php
                        echo wp_get_attachment_image(
                            55695,
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
    <?php if ( $is_rent_page_seo_preview ) : ?>
    <section class="section section-soft">
        <div class="content-panel-box">
            <header class="section-header section-header--center"><h2><?php echo esc_html( pera_ml_ui( 'Property Management Services in Istanbul', 'theme.template.page_rent_with_pera.property_management_services_in_istanbul' ) ); ?></h2></header>
            <div class="feature-grid">
                <article class="feature-card"><div class="feature-card-header"><h3><?php echo esc_html( pera_ml_ui( 'Long-term rental management', 'theme.template.page_rent_with_pera.long_term_rental_management' ) ); ?></h3></div><div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'Our Istanbul rental management service includes valuation, marketing, tenant sourcing, tenancy coordination, Istanbul rent collection, and ongoing apartment management for local and overseas owners.', 'theme.template.page_rent_with_pera.our_istanbul_rental_management_service_includes_valuation_marketing_tena' ) ); ?></p></div></article>
                <article class="feature-card"><div class="feature-card-header"><h3><?php echo esc_html( pera_ml_ui( 'Landlord administration', 'theme.template.page_rent_with_pera.landlord_administration' ) ); ?></h3></div><div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'We support Istanbul landlord services including utility setup, DASK and annual property tax support, deposit handling, and owner reporting tailored to overseas landlords with property in Istanbul.', 'theme.template.page_rent_with_pera.we_support_istanbul_landlord_services_including_utility_setup_dask_and_a' ) ); ?></p></div></article>
                <article class="feature-card"><div class="feature-card-header"><h3><?php echo esc_html( pera_ml_ui( 'Asset care and approvals', 'theme.template.page_rent_with_pera.asset_care_and_approvals' ) ); ?></h3></div><div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'Maintenance coordination, inspections, and contractor management are handled with owner approvals before third-party costs are committed.', 'theme.template.page_rent_with_pera.maintenance_coordination_inspections_and_contractor_management_are_handl' ) ); ?></p></div></article>
            </div>
        </div>
    </section>
    <section class="section"><div class="content-panel-box"><header class="section-header"><h2><?php echo esc_html( pera_ml_ui( 'Long-Term Rental Management for Foreign Owners', 'theme.template.page_rent_with_pera.long_term_rental_management_for_foreign_owners' ) ); ?></h2><p><?php echo esc_html( pera_ml_ui( 'As a property management company in Istanbul, we are set up for foreign owners who need dependable local execution. If you are abroad, we can manage your Istanbul apartment end-to-end and keep decisions clear, documented, and approval-led.', 'theme.template.page_rent_with_pera.as_a_property_management_company_in_istanbul_we_are_set_up_for_foreign_o' ) ); ?></p></header></div></section>
    <section class="section section-soft"><div class="content-panel-box"><header class="section-header section-header--center"><h2><?php echo esc_html( pera_ml_ui( 'Best Istanbul Areas for Rental Demand', 'theme.template.page_rent_with_pera.best_istanbul_areas_for_rental_demand' ) ); ?></h2><p><?php echo esc_html( pera_ml_ui( 'We regularly manage and let homes in', 'theme.template.page_rent_with_pera.we_regularly_manage_and_let_homes_in' ) ); ?> <a href="/district/istanbul/besiktas/">Beşiktaş</a>, <a href="/district/istanbul/sisli/">Şişli</a> <?php echo esc_html( pera_ml_ui( '(including Bomonti and Nişantaşı),', 'theme.template.page_rent_with_pera.including_bomonti_and_ni_anta' ) ); ?> <a href="/district/istanbul/kagithane/">Kağıthane</a>, <a href="/district/istanbul/kadikoy/">Kadıköy</a>, <a href="/district/istanbul/uskudar/">Üsküdar</a>, <a href="/district/istanbul/bakirkoy/">Bakırköy</a><?php echo esc_html( pera_ml_ui( ', and Atakent / Küçükçekmece. Explore our', 'theme.template.page_rent_with_pera.and_atakent_k_k_ekmece_explore_our' ) ); ?> <a href="/property/"><?php echo esc_html( pera_ml_ui( 'property listings', 'theme.template.page_rent_with_pera.property_listings' ) ); ?></a> <?php echo esc_html( pera_ml_ui( 'and', 'theme.template.page_rent_with_pera.and' ) ); ?> <a href="/buyer-guides/"><?php echo esc_html( pera_ml_ui( 'buyer guides', 'theme.template.page_rent_with_pera.buyer_guides' ) ); ?></a> <?php echo esc_html( pera_ml_ui( 'for district context.', 'theme.template.page_rent_with_pera.for_district_context' ) ); ?></p></header></div></section>
    <section class="section"><div class="content-panel-box"><header class="section-header section-header--center"><h2><?php echo esc_html( pera_ml_ui( 'Why International Owners Choose Pera Property', 'theme.template.page_rent_with_pera.why_international_owners_choose_pera_property' ) ); ?></h2><p><?php echo esc_html( pera_ml_ui( 'Owners choose us for practical execution, transparent communication, and reliable process controls across tenant checks, rent collection, maintenance, and reporting. Contact us through', 'theme.template.page_rent_with_pera.owners_choose_us_for_practical_execution_transparent_communication_and_r' ) ); ?> <a href="/contact/"><?php echo esc_html( pera_ml_ui( 'our contact page', 'theme.template.page_rent_with_pera.our_contact_page' ) ); ?></a> <?php echo esc_html( pera_ml_ui( 'to discuss your rental plan.', 'theme.template.page_rent_with_pera.to_discuss_your_rental_plan' ) ); ?></p></header></div></section>
    <?php endif; ?>

    <!-- PRICING -->
    <section id="pricing" class="section section-soft">
        <div class="section-header section-header--center">
            <h2><?php echo esc_html( pera_ml_ui( 'Our rental management services', 'theme.template.page_rent_with_pera.our_rental_management_services' ) ); ?></h2>
            <p><?php echo esc_html( pera_ml_ui( 'Choose the service level that fits your investment strategy.', 'theme.template.page_rent_with_pera.choose_the_service_level_that_fits_your_investment_strategy' ) ); ?></p>
        </div>

        <div class="feature-grid">

            <!-- LETTINGS ONLY -->
            <article class="feature-card">
                <div class="feature-card-header">
                    <h3><?php echo esc_html( pera_ml_ui( 'Lettings Only', 'theme.template.page_rent_with_pera.lettings_only' ) ); ?></h3>
                    <p class="price-tag"><?php echo esc_html( pera_ml_ui( '8% + VAT', 'theme.template.page_rent_with_pera.8_vat' ) ); ?></p>
                </div>

                <div class="feature-card-body">
                    <ul class="checklist checklist--circle">
                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Advertising on all major rental platforms', 'theme.template.page_rent_with_pera.advertising_on_all_major_rental_platforms' ) ); ?>
                        </li>
                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Tenant viewings and shortlisting', 'theme.template.page_rent_with_pera.tenant_viewings_and_shortlisting' ) ); ?>
                        </li>
                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Full tenant screening & ID verification', 'theme.template.page_rent_with_pera.full_tenant_screening_and_id_verification_2' ) ); ?>
                        </li>
                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Tenancy agreement preparation and signing', 'theme.template.page_rent_with_pera.tenancy_agreement_preparation_and_signing' ) ); ?>
                        </li>
                    </ul>
                </div>

                <div class="feature-card-footer">
                    <a href="#contact" class="btn btn--solid btn--green"><?php echo esc_html( pera_ml_ui( 'Get valuation', 'theme.template.page_rent_with_pera.get_valuation' ) ); ?></a>
                </div>
            </article>

            <!-- FULL MANAGEMENT -->
            <article class="feature-card">
                <div class="feature-card-header">
                    <h3><?php echo esc_html( pera_ml_ui( 'Full Management', 'theme.template.page_rent_with_pera.full_management' ) ); ?></h3>
                    <p class="price-tag"><?php echo esc_html( pera_ml_ui( '12% + VAT', 'theme.template.page_rent_with_pera.12_vat' ) ); ?></p>
                </div>

                <div class="feature-card-body">
                    <ul class="checklist checklist--circle">
                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Everything in Lettings Only', 'theme.template.page_rent_with_pera.everything_in_lettings_only' ) ); ?>
                        </li>
                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Dedicated property manager in Istanbul', 'theme.template.page_rent_with_pera.dedicated_property_manager_in_istanbul' ) ); ?>
                        </li>
                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Rent collection and arrears management', 'theme.template.page_rent_with_pera.rent_collection_and_arrears_management' ) ); ?>
                        </li>
                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Organising repairs, quotes & contractors', 'theme.template.page_rent_with_pera.organising_repairs_quotes_and_contractors' ) ); ?>
                        </li>
                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Regular inspections with condition reports', 'theme.template.page_rent_with_pera.regular_inspections_with_condition_reports' ) ); ?>
                        </li>
                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Utility transfers, deposits & move-out checks', 'theme.template.page_rent_with_pera.utility_transfers_deposits_and_move_out_checks' ) ); ?>
                        </li>
                    </ul>
                </div>

                <div class="feature-card-footer">
                    <a href="#contact" class="btn btn--solid btn--green"><?php echo esc_html( pera_ml_ui( 'Get valuation', 'theme.template.page_rent_with_pera.get_valuation' ) ); ?></a>
                </div>
            </article>

        </div>
    </section>

    <section class="section section-soft" id="rental-management-process">
        <div class="content-panel-box">
            <header class="section-header section-header--center">
                <h2><?php echo esc_html( pera_ml_ui( 'How our Istanbul property management service works', 'theme.template.page_rent_with_pera.how_our_istanbul_property_management_service_works' ) ); ?></h2>
                <p><?php echo esc_html( pera_ml_ui( 'Our process is designed to keep rental management straightforward for Istanbul property owners.', 'theme.template.page_rent_with_pera.our_process_is_designed_to_keep_rental_management_straightforward_for_is' ) ); ?></p>
            </header>

            <div class="feature-grid">
                <article class="feature-card">
                    <div class="feature-card-header">
                        <h3><?php echo esc_html( pera_ml_ui( '1) Rental valuation', 'theme.template.page_rent_with_pera.1_rental_valuation' ) ); ?></h3>
                    </div>
                    <div class="feature-card-body">
                        <ul class="checklist checklist--circle">
                            <li>
                                <?php echo esc_html( pera_ml_ui( 'We assess market demand, building profile, and district comparables in areas such as Beşiktaş, Şişli, Beyoğlu, and Kadıköy.', 'theme.template.page_rent_with_pera.we_assess_market_demand_building_profile_and_district_comparables_in_are' ) ); ?>
                            </li>
                        </ul>
                    </div>
                </article>

                <article class="feature-card">
                    <div class="feature-card-header">
                        <h3><?php echo esc_html( pera_ml_ui( '2) Marketing and tenant sourcing', 'theme.template.page_rent_with_pera.2_marketing_and_tenant_sourcing' ) ); ?></h3>
                    </div>
                    <div class="feature-card-body">
                        <ul class="checklist checklist--circle">
                            <li>
                                <?php echo esc_html( pera_ml_ui( 'We market your home across major channels to attract qualified long-term tenants quickly.', 'theme.template.page_rent_with_pera.we_market_your_home_across_major_channels_to_attract_qualified_long_term' ) ); ?>
                            </li>
                        </ul>
                    </div>
                </article>

                <article class="feature-card">
                    <div class="feature-card-header">
                        <h3><?php echo esc_html( pera_ml_ui( '3) Tenant screening', 'theme.template.page_rent_with_pera.3_tenant_screening' ) ); ?></h3>
                    </div>
                    <div class="feature-card-body">
                        <ul class="checklist checklist--circle">
                            <li>
                                <?php echo esc_html( pera_ml_ui( 'We complete documentation and affordability checks before presenting tenants for your approval.', 'theme.template.page_rent_with_pera.we_complete_documentation_and_affordability_checks_before_presenting_ten' ) ); ?>
                            </li>
                        </ul>
                    </div>
                </article>

                <article class="feature-card">
                    <div class="feature-card-header">
                        <h3><?php echo esc_html( pera_ml_ui( '4) Contract and deposit setup', 'theme.template.page_rent_with_pera.4_contract_and_deposit_setup' ) ); ?></h3>
                    </div>
                    <div class="feature-card-body">
                        <ul class="checklist checklist--circle">
                            <li>
                                <?php echo esc_html( pera_ml_ui( 'We prepare compliant tenancy contracts and organise deposit handling with clear owner sign-off.', 'theme.template.page_rent_with_pera.we_prepare_compliant_tenancy_contracts_and_organise_deposit_handling_wit' ) ); ?>
                            </li>
                        </ul>
                    </div>
                </article>

                <article class="feature-card">
                    <div class="feature-card-header">
                        <h3><?php echo esc_html( pera_ml_ui( '5) Rent collection and maintenance', 'theme.template.page_rent_with_pera.5_rent_collection_and_maintenance' ) ); ?></h3>
                    </div>
                    <div class="feature-card-body">
                        <ul class="checklist checklist--circle">
                            <li>
                                <?php echo esc_html( pera_ml_ui( 'Our Istanbul rental management team supports collections, maintenance coordination, and tenant communication.', 'theme.template.page_rent_with_pera.our_istanbul_rental_management_team_supports_collections_maintenance_coo' ) ); ?>
                            </li>
                        </ul>
                    </div>
                </article>

                <article class="feature-card">
                    <div class="feature-card-header">
                        <h3><?php echo esc_html( pera_ml_ui( '6) Renewal or exit management', 'theme.template.page_rent_with_pera.6_renewal_or_exit_management' ) ); ?></h3>
                    </div>
                    <div class="feature-card-body">
                        <ul class="checklist checklist--circle">
                            <li>
                                <?php echo esc_html( pera_ml_ui( 'We manage rent reviews, renewals, and move-out processes while keeping you informed at every stage.', 'theme.template.page_rent_with_pera.we_manage_rent_reviews_renewals_and_move_out_processes_while_keeping_you' ) ); ?>
                            </li>
                        </ul>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="faq-section section" id="rental-management-faq">
        <div class="container">
            <h2><?php echo esc_html( $is_rent_page_seo_preview
              ? pera_ml_ui( 'Istanbul Property Management FAQ', 'theme.template.page_rent_with_pera.property_management_faq_heading' )
              : pera_ml_ui( 'Rental management FAQ', 'theme.template.page_rent_with_pera.rental_management_faq_heading' ) ); ?></h2>
            <p><?php echo esc_html( pera_ml_ui( 'Everything you need to know about property management in Istanbul and how our rental management service works in practice.', 'theme.template.page_rent_with_pera.everything_you_need_to_know_about_property_management_in_istanbul_and_ho' ) ); ?></p>

            <div class="faq-accordion">

                <details class="faq-item" open>
                    <summary><?php echo esc_html( pera_ml_ui( 'What does your full property management in Istanbul service include?', 'theme.template.page_rent_with_pera.what_does_your_full_property_management_in_istanbul_service_include' ) ); ?></summary>
                    <div class="faq-answer">
                        <p><?php echo esc_html( pera_ml_ui( 'Our service is fully hands-off for Istanbul property owners. We handle tenant sourcing, marketing, viewings, lease preparation, tenant screening, contract negotiation, renewals, maintenance coordination, and ongoing tenant communication. We also assist with utility setup, tax guidance, and end-of-tenancy processes.', 'theme.template.page_rent_with_pera.our_service_is_fully_hands_off_for_istanbul_property_owners_we_handle_te' ) ); ?></p>
                    </div>
                </details>

                <details class="faq-item">
                    <summary><?php echo esc_html( pera_ml_ui( 'What is your rental management fee?', 'theme.template.page_rent_with_pera.what_is_your_rental_management_fee' ) ); ?></summary>
                    <div class="faq-answer">
                        <p><?php echo esc_html( pera_ml_ui( 'Our full property management service in Istanbul is charged at', 'theme.template.page_rent_with_pera.our_full_property_management_service_in_istanbul_is_charged_at' ) ); ?> <strong><?php echo esc_html( pera_ml_ui( '12% + VAT', 'theme.template.page_rent_with_pera.12_vat' ) ); ?></strong><?php echo esc_html( pera_ml_ui( '. This covers the ongoing management of the property throughout the tenancy, including renewals and day-to-day tenant management.', 'theme.template.page_rent_with_pera.this_covers_the_ongoing_management_of_the_property_throughout_the_tenanc' ) ); ?></p>
                    </div>
                </details>

                <details class="faq-item">
                    <summary><?php echo esc_html( pera_ml_ui( 'Are there any additional costs?', 'theme.template.page_rent_with_pera.are_there_any_additional_costs' ) ); ?></summary>
                    <div class="faq-answer">
                        <p><?php echo esc_html( pera_ml_ui( 'Yes — the management fee covers our service only. Property-related costs such as maintenance, repairs, taxes, insurance, utilities, or building charges are separate and always subject to your approval before any work is carried out.', 'theme.template.page_rent_with_pera.yes_the_management_fee_covers_our_service_only_property_related_costs_su' ) ); ?></p>
                    </div>
                </details>

                <details class="faq-item">
                    <summary><?php echo esc_html( pera_ml_ui( 'How do you find and select tenants?', 'theme.template.page_rent_with_pera.how_do_you_find_and_select_tenants' ) ); ?></summary>
                    <div class="faq-answer">
                        <p><?php echo esc_html( pera_ml_ui( 'As part of our rental management in Istanbul, we market your property across our network and screen all applicants carefully. This typically includes employment and income checks, documentation review, and — where appropriate — requiring a Turkish guarantor. Our focus is always on placing reliable, financially stable tenants.', 'theme.template.page_rent_with_pera.as_part_of_our_rental_management_in_istanbul_we_market_your_property_acr' ) ); ?></p>
                    </div>
                </details>

                <details class="faq-item">
                    <summary><?php echo esc_html( pera_ml_ui( 'Will I approve the tenant before the contract is signed?', 'theme.template.page_rent_with_pera.will_i_approve_the_tenant_before_the_contract_is_signed' ) ); ?></summary>
                    <div class="faq-answer">
                        <p><?php echo esc_html( pera_ml_ui( 'Yes. We present you with the proposed tenant and agreed terms before any contract is finalised. No tenancy is confirmed without your approval.', 'theme.template.page_rent_with_pera.yes_we_present_you_with_the_proposed_tenant_and_agreed_terms_before_any_' ) ); ?></p>
                    </div>
                </details>

                <details class="faq-item">
                    <summary><?php echo esc_html( pera_ml_ui( 'Do you provide the rental contract in English?', 'theme.template.page_rent_with_pera.do_you_provide_the_rental_contract_in_english' ) ); ?></summary>
                    <div class="faq-answer">
                        <p><?php echo esc_html( pera_ml_ui( 'Yes. We can prepare bilingual Turkish and English contracts so that you fully understand the terms of the agreement while ensuring compliance with local regulations.', 'theme.template.page_rent_with_pera.yes_we_can_prepare_bilingual_turkish_and_english_contracts_so_that_you_f' ) ); ?></p>
                    </div>
                </details>

                <details class="faq-item">
                    <summary><?php echo esc_html( pera_ml_ui( 'How are rent increases handled?', 'theme.template.page_rent_with_pera.how_are_rent_increases_handled' ) ); ?></summary>
                    <div class="faq-answer">
                        <p><?php echo esc_html( pera_ml_ui( 'Rent increases are managed in line with Turkish law, typically based on the official CPI (TÜFE) cap. We handle negotiations with the tenant and advise you on the optimal approach at each renewal period.', 'theme.template.page_rent_with_pera.rent_increases_are_managed_in_line_with_turkish_law_typically_based_on_t' ) ); ?></p>
                    </div>
                </details>

                <details class="faq-item">
                    <summary><?php echo esc_html( pera_ml_ui( 'Do you use any legal protection for the landlord?', 'theme.template.page_rent_with_pera.do_you_use_any_legal_protection_for_the_landlord' ) ); ?></summary>
                    <div class="faq-answer">
                        <p><?php echo esc_html( pera_ml_ui( 'Yes. Where appropriate, we arrange a notarised exit undertaking (tahliye taahhütnamesi), which provides additional legal protection in case the tenant does not vacate at the end of the agreed term.', 'theme.template.page_rent_with_pera.yes_where_appropriate_we_arrange_a_notarised_exit_undertaking_tahliye_ta' ) ); ?></p>
                    </div>
                </details>

                <details class="faq-item">
                    <summary><?php echo esc_html( pera_ml_ui( 'How is the tenant deposit handled?', 'theme.template.page_rent_with_pera.how_is_the_tenant_deposit_handled' ) ); ?></summary>
                    <div class="faq-answer">
                        <p><?php echo esc_html( pera_ml_ui( 'We typically secure a two-month deposit, which is held in accordance with Turkish rental practices. At the end of the tenancy, the property is inspected and any agreed deductions are applied before the remaining balance is returned.', 'theme.template.page_rent_with_pera.we_typically_secure_a_two_month_deposit_which_is_held_in_accordance_with' ) ); ?></p>
                    </div>
                </details>

                <details class="faq-item">
                    <summary><?php echo esc_html( pera_ml_ui( 'How are utilities managed?', 'theme.template.page_rent_with_pera.how_are_utilities_managed' ) ); ?></summary>
                    <div class="faq-answer">
                        <p><?php echo esc_html( pera_ml_ui( 'For tenanted properties, utilities are usually transferred into the tenant’s name. For new properties, the owner may need to open the accounts initially. We manage and coordinate this process on your behalf.', 'theme.template.page_rent_with_pera.for_tenanted_properties_utilities_are_usually_transferred_into_the_tenan' ) ); ?></p>
                    </div>
                </details>

                <details class="faq-item">
                    <summary><?php echo esc_html( pera_ml_ui( 'How do you handle maintenance and repairs?', 'theme.template.page_rent_with_pera.how_do_you_handle_maintenance_and_repairs' ) ); ?></summary>
                    <div class="faq-answer">
                        <p><?php echo esc_html( pera_ml_ui( 'If an issue arises, we coordinate with trusted contractors, obtain quotes where necessary, and seek your approval before proceeding. No expense is incurred without your consent, so Istanbul property owners stay in control.', 'theme.template.page_rent_with_pera.if_an_issue_arises_we_coordinate_with_trusted_contractors_obtain_quotes_' ) ); ?></p>
                    </div>
                </details>

                <details class="faq-item">
                    <summary><?php echo esc_html( pera_ml_ui( 'Do I receive reports or updates?', 'theme.template.page_rent_with_pera.do_i_receive_reports_or_updates' ) ); ?></summary>
                    <div class="faq-answer">
                        <p><?php echo esc_html( pera_ml_ui( 'Rent is typically paid directly to the owner, so formal monthly reporting is not always required. However, we keep you informed of any key developments and can provide structured reporting if you prefer a more hands-on overview.', 'theme.template.page_rent_with_pera.rent_is_typically_paid_directly_to_the_owner_so_formal_monthly_reporting' ) ); ?></p>
                    </div>
                </details>

                <details class="faq-item">
                    <summary><?php echo esc_html( pera_ml_ui( 'Can I take over management myself later?', 'theme.template.page_rent_with_pera.can_i_take_over_management_myself_later' ) ); ?></summary>
                    <div class="faq-answer">
                        <p><?php echo esc_html( pera_ml_ui( 'Yes. You are free to take over management at any time with reasonable notice. We will ensure a smooth handover of all relevant documents and tenant information.', 'theme.template.page_rent_with_pera.yes_you_are_free_to_take_over_management_at_any_time_with_reasonable_not' ) ); ?></p>
                    </div>
                </details>

            </div>
        </div>
    </section>

    <!-- SHORT TERM RENTALS -->
    <section class="section">
        <div class="content-panel-box">
            <div class="content-panel-grid">

                <!-- LEFT -->
                <div>
                    <header class="section-header">
                        <h2><?php echo esc_html( pera_ml_ui( 'The short term rental market (“Airbnb”)', 'theme.template.page_rent_with_pera.the_short_term_rental_market_airbnb' ) ); ?></h2>
                        <p>
                            <?php echo esc_html( pera_ml_ui( 'Our core service is long-term property management in Istanbul, while short-term rental support is available where suitable for the asset and location.
                            If you need dedicated holiday-let support, see our', 'theme.template.page_rent_with_pera.our_core_service_is_long_term_property_management_in_istanbul_while_shor' ) ); ?>
                            <a href="/short-term-rental-airbnb-in-istanbul_49220/"><?php echo esc_html( pera_ml_ui( 'short-term rental and Airbnb management service', 'theme.template.page_rent_with_pera.short_term_rental_and_airbnb_management_service' ) ); ?></a>.
                        </p>
                    </header>

                    <ul class="checklist checklist--circle">

                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Check-in / check-out management', 'theme.template.page_rent_with_pera.check_in_check_out_management' ) ); ?>
                        </li>

                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Cleaning & maintenance', 'theme.template.page_rent_with_pera.cleaning_and_maintenance' ) ); ?>
                        </li>

                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Guest communication', 'theme.template.page_rent_with_pera.guest_communication' ) ); ?>
                        </li>

                        <li>
                            <?php echo esc_html( pera_ml_ui( 'Supplies & inventory management', 'theme.template.page_rent_with_pera.supplies_and_inventory_management' ) ); ?>
                        </li>

                    </ul>
                </div>

                <!-- RIGHT -->
                <div>
                    <div class="media-frame">
                        <img class="media-embed"
                         src="<?php echo esc_url( wp_get_attachment_image_url( 59614, 'full' ) ); ?>"
                         alt="<?php echo esc_attr( pera_ml_ui( 'Airbnb management Istanbul – Pera Property', 'theme.template.page_rent_with_pera.alt.airbnb_management_istanbul_pera_property' ) ); ?>">
                    </div>
                </div>

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
                                <?php echo esc_html( pera_ml_ui( 'Reliable, data-driven advice.', 'theme.template.page_rent_with_pera.reliable_data_driven_advice' ) ); ?>
                            </li>
        
                            <li>
                                <?php echo esc_html( pera_ml_ui( 'On-the-ground Istanbul expertise.', 'theme.template.page_rent_with_pera.on_the_ground_istanbul_expertise' ) ); ?>
                            </li>
        
                            <li>
                                <?php echo esc_html( pera_ml_ui( 'Multi-lingual support.', 'theme.template.page_rent_with_pera.multi_lingual_support' ) ); ?>
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
                            <h3 class="text-light"><?php echo esc_html( pera_ml_ui( 'Speak with a Consultant', 'theme.template.page_rent_with_pera.speak_with_a_consultant' ) ); ?></h3>
        
                            <div class="hero-actions flex-center">
                                <a href="https://www.peraproperty.com/book-a-consultancy/" class="btn btn--solid btn--green">
                                    <?php echo esc_html( pera_ml_ui( 'Book a consultation', 'theme.template.page_rent_with_pera.book_a_consultation' ) ); ?>
                                </a>
        
                                <a href="<?php echo esc_url( pera_get_whatsapp_url( pera_ml_ui( 'Hello Pera Property, I\'d like to discuss Istanbul real estate.', 'theme.template.page_rent_with_pera.whatsapp_prefill' ) ) ); ?>"
                                   class="btn btn--solid btn--green"
                                   data-whatsapp="1"
                                   data-whatsapp-type="service_cta"
                                   data-track-channel="whatsapp"
                                   data-track-intent="high"
                                   data-track-source="template"
                                   data-track-context="rent_with_pera"
                                   data-track-ga4-event="whatsapp_click"
                                   data-track-crm-event="whatsapp_click">
                                    <?php echo esc_html( pera_ml_ui( 'Chat on WhatsApp', 'theme.template.page_rent_with_pera.chat_on_whatsapp' ) ); ?>
                                </a>
                            </div>
                        </div>
        
                    </div><!-- .media-frame -->
        
                </div><!-- .content-panel-grid -->
    
                <div>
        
                    <?php if ( isset( $_GET['sr_status'] ) && $_GET['sr_status'] === 'sent' ) : ?>
                        <div class="form-success">
                            <?php echo esc_html( pera_ml_ui( 'Thank you – we have received your details. A Pera consultant will contact you shortly.', 'theme.template.page_rent_with_pera.thank_you_we_have_received_your_details_a_pera_consultant_will_contact_y' ) ); ?>
                        </div>
                    <?php endif; ?>
        
        
                     <?php
                    get_template_part('parts/enquiry-form', null, array(
                      'context'      => 'rent',
                      'heading'      => 'Request a free appraisal',
                      'intro'        => 'Share a few details and we will prepare an initial rent strategy and price guidance for your property in Istanbul.',
                      'submit_label' => 'Send my details',
                      'form_context' => 'rent-page',
                    ));
            
                    ?>
                    
                </div><!-- .enquiry-cta -->
            </div><!-- .content-panel-box -->
        </section>

        

</main>

<?php get_footer(); ?>
