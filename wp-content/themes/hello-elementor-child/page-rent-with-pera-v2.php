<?php
/**
 * Template Name: Rent with Pera V2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Development template: keep temporary V2 pages out of search indexes.
add_filter( 'wp_robots', static function ( array $robots ): array {
    $robots['noindex'] = true;
    $robots['nofollow'] = true;
    return $robots;
} );


$hero_heading = $args['hero_heading'] ?? pera_ml_ui( 'Talk to Pera about your Istanbul plans', 'theme.template.page_rent_with_pera.hero_heading_fallback' );
$hero_intro   = $args['hero_intro']   ?? pera_ml_ui( 'Whether you’re buying, selling, or renting in Istanbul, our team can walk you through the numbers, the legal steps, and the neighbourhoods that fit your strategy.', 'theme.template.page_rent_with_pera.hero_intro_fallback' );

if ( ! function_exists( 'pera_rent_with_pera_v2_faq_schema' ) ) {
    function pera_rent_with_pera_v2_faq_schema() {
        if ( ! is_page_template( 'page-rent-with-pera-v2.php' ) ) {
            return;
        }

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
add_action( 'wp_head', 'pera_rent_with_pera_v2_faq_schema', 25 );


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

        
            <h1><?php echo esc_html( pera_ml_ui( 'Property management in Istanbul for overseas owners', 'theme.template.page_rent_with_pera_v2.hero_heading' ) ); ?></h1>
            <p class="lead">
              <?php echo esc_html( pera_ml_ui( 'Own property in Istanbul but live elsewhere? Pera acts as your local property manager, handling tenants, repairs, inspections and the day-to-day work while keeping you informed and in control.', 'theme.template.page_rent_with_pera_v2.hero_lead' ) ); ?>
            </p>

            <div class="hero-actions">
              <a href="#pricing" class="btn btn--solid btn--blue"><?php echo esc_html( pera_ml_ui( 'Compare services', 'theme.template.page_rent_with_pera_v2.compare_services' ) ); ?></a>
              <a href="#contact" class="btn btn--solid btn--green"><?php echo esc_html( pera_ml_ui( 'Get a rental assessment', 'theme.template.page_rent_with_pera_v2.get_rental_assessment' ) ); ?></a>
            </div>
          </div>

        </section>

    <!-- OVERSEAS OWNER POSITIONING -->
    <section class="content-panel content-panel--overlap-hero">
        <div class="content-panel-box">
            <div class="content-panel-grid">
                <div>
                    <header class="section-header">
                        <h2><?php echo esc_html( pera_ml_ui( 'Your property manager is in Istanbul when you are not', 'theme.template.page_rent_with_pera_v2.local_manager_heading' ) ); ?></h2>
                        <p><?php echo esc_html( pera_ml_ui( 'Managing an Istanbul property from another country is rarely difficult when everything is going well. The problem is dealing with the things that need somebody on the ground: a new tenant, a repair, a building-management issue, an inspection, a missed payment or a property that needs preparing before it can be rented.', 'theme.template.page_rent_with_pera_v2.local_manager_intro' ) ); ?></p>
                        <p><?php echo esc_html( pera_ml_ui( 'Pera provides that local point of contact. Our Istanbul team can manage the tenancy from initial rental valuation through to renewal or check-out, with important decisions and third-party costs referred back to you for approval.', 'theme.template.page_rent_with_pera_v2.local_manager_body' ) ); ?></p>
                    </header>

                    <ul class="checklist checklist--circle">
                        <li><?php echo esc_html( pera_ml_ui( 'Istanbul-based property manager and owner support', 'theme.template.page_rent_with_pera_v2.local_team' ) ); ?></li>
                        <li><?php echo esc_html( pera_ml_ui( 'Tenant sourcing, screening and tenancy coordination', 'theme.template.page_rent_with_pera_v2.tenant_coordination' ) ); ?></li>
                        <li><?php echo esc_html( pera_ml_ui( 'Repairs, contractors and property inspections', 'theme.template.page_rent_with_pera_v2.repairs_inspections' ) ); ?></li>
                        <li><?php echo esc_html( pera_ml_ui( 'Rent, renewal and tenant communication support', 'theme.template.page_rent_with_pera_v2.rent_renewal' ) ); ?></li>
                        <li><?php echo esc_html( pera_ml_ui( 'Clear owner approval before third-party costs are committed', 'theme.template.page_rent_with_pera_v2.owner_approval' ) ); ?></li>
                    </ul>
                </div>

                <div>
                    <div class="media-frame media-frame--image-fill">
                        <?php
                        echo wp_get_attachment_image(
                            55695,
                            'full',
                            false,
                            array(
                                'class'    => 'media-image',
                                'loading'  => 'lazy',
                                'decoding' => 'async',
                                'alt'      => esc_attr( 'Property management in Istanbul for overseas owners' ),
                            )
                        );
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SERVICE CHOICE -->
    <section id="pricing" class="section section-soft">
        <div class="section-header section-header--center">
            <h2><?php echo esc_html( pera_ml_ui( 'Do you need a tenant or full property management?', 'theme.template.page_rent_with_pera_v2.service_choice_heading' ) ); ?></h2>
            <p><?php echo esc_html( pera_ml_ui( 'The difference is simple. Lettings Only gets the tenancy established. Full Management keeps Pera involved as your local property manager after the tenant moves in.', 'theme.template.page_rent_with_pera_v2.service_choice_intro' ) ); ?></p>
        </div>

        <div class="feature-grid">
            <article class="feature-card">
                <div class="feature-card-header">
                    <h3><?php echo esc_html( pera_ml_ui( 'Lettings Only', 'theme.template.page_rent_with_pera.lettings_only' ) ); ?></h3>
                    <p class="price-tag"><?php echo esc_html( pera_ml_ui( '8% + VAT', 'theme.template.page_rent_with_pera.8_vat' ) ); ?></p>
                </div>
                <div class="feature-card-body">
                    <p><?php echo esc_html( pera_ml_ui( 'For owners who want Pera to find and establish a suitable tenant, but are comfortable managing the property themselves once the tenancy begins.', 'theme.template.page_rent_with_pera_v2.lettings_only_intro' ) ); ?></p>
                    <ul class="checklist checklist--circle">
                        <li><?php echo esc_html( pera_ml_ui( 'Rental valuation and marketing', 'theme.template.page_rent_with_pera_v2.rental_valuation_marketing' ) ); ?></li>
                        <li><?php echo esc_html( pera_ml_ui( 'Tenant viewings and shortlisting', 'theme.template.page_rent_with_pera.tenant_viewings_and_shortlisting' ) ); ?></li>
                        <li><?php echo esc_html( pera_ml_ui( 'Tenant screening and ID verification', 'theme.template.page_rent_with_pera_v2.tenant_screening' ) ); ?></li>
                        <li><?php echo esc_html( pera_ml_ui( 'Tenancy agreement preparation and signing', 'theme.template.page_rent_with_pera.tenancy_agreement_preparation_and_signing' ) ); ?></li>
                    </ul>
                </div>
                <div class="feature-card-footer">
                    <a href="#contact" class="btn btn--solid btn--green"><?php echo esc_html( pera_ml_ui( 'Request an assessment', 'theme.template.page_rent_with_pera_v2.request_assessment' ) ); ?></a>
                </div>
            </article>

            <article class="feature-card">
                <div class="feature-card-header">
                    <h3><?php echo esc_html( pera_ml_ui( 'Full Management', 'theme.template.page_rent_with_pera.full_management' ) ); ?></h3>
                    <p class="price-tag"><?php echo esc_html( pera_ml_ui( '12% + VAT', 'theme.template.page_rent_with_pera.12_vat' ) ); ?></p>
                </div>
                <div class="feature-card-body">
                    <p><?php echo esc_html( pera_ml_ui( 'For overseas owners and landlords who want an Istanbul-based team to remain responsible for the day-to-day tenancy and property coordination.', 'theme.template.page_rent_with_pera_v2.full_management_intro' ) ); ?></p>
                    <ul class="checklist checklist--circle">
                        <li><?php echo esc_html( pera_ml_ui( 'Everything included in Lettings Only', 'theme.template.page_rent_with_pera_v2.everything_lettings' ) ); ?></li>
                        <li><?php echo esc_html( pera_ml_ui( 'Dedicated property manager in Istanbul', 'theme.template.page_rent_with_pera.dedicated_property_manager_in_istanbul' ) ); ?></li>
                        <li><?php echo esc_html( pera_ml_ui( 'Rent monitoring, arrears follow-up and tenant communication', 'theme.template.page_rent_with_pera_v2.rent_monitoring' ) ); ?></li>
                        <li><?php echo esc_html( pera_ml_ui( 'Repairs, quotes and contractor coordination', 'theme.template.page_rent_with_pera_v2.contractor_coordination' ) ); ?></li>
                        <li><?php echo esc_html( pera_ml_ui( 'Property inspections and condition reporting', 'theme.template.page_rent_with_pera_v2.condition_reporting' ) ); ?></li>
                        <li><?php echo esc_html( pera_ml_ui( 'Utility coordination, deposits and move-out checks', 'theme.template.page_rent_with_pera_v2.utility_coordination' ) ); ?></li>
                    </ul>
                </div>
                <div class="feature-card-footer">
                    <a href="#contact" class="btn btn--solid btn--green"><?php echo esc_html( pera_ml_ui( 'Discuss full management', 'theme.template.page_rent_with_pera_v2.discuss_full_management' ) ); ?></a>
                </div>
            </article>
        </div>
    </section>

    <!-- MANAGEMENT PROCESS -->
    <section class="section" id="rental-management-process">
        <div class="content-panel-box">
            <header class="section-header section-header--center">
                <h2><?php echo esc_html( pera_ml_ui( 'From an empty property to an established tenancy', 'theme.template.page_rent_with_pera_v2.process_heading' ) ); ?></h2>
                <p><?php echo esc_html( pera_ml_ui( 'We can become involved before the property is advertised and remain involved for the full tenancy if you choose Full Management.', 'theme.template.page_rent_with_pera_v2.process_intro' ) ); ?></p>
            </header>

            <div class="feature-grid">
                <article class="feature-card">
                    <div class="feature-card-header"><h3><?php echo esc_html( pera_ml_ui( '1) Assess the property', 'theme.template.page_rent_with_pera_v2.process_1_heading' ) ); ?></h3></div>
                    <div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'We assess the property, building and local rental market, then give you realistic rental guidance before marketing begins.', 'theme.template.page_rent_with_pera_v2.process_1_body' ) ); ?></p></div>
                </article>
                <article class="feature-card">
                    <div class="feature-card-header"><h3><?php echo esc_html( pera_ml_ui( '2) Prepare and market', 'theme.template.page_rent_with_pera_v2.process_2_heading' ) ); ?></h3></div>
                    <div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'We identify practical preparation required for letting, arrange the marketing and manage enquiries and viewings.', 'theme.template.page_rent_with_pera_v2.process_2_body' ) ); ?></p></div>
                </article>
                <article class="feature-card">
                    <div class="feature-card-header"><h3><?php echo esc_html( pera_ml_ui( '3) Select the tenant', 'theme.template.page_rent_with_pera_v2.process_3_heading' ) ); ?></h3></div>
                    <div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'Applicants are screened and the proposed tenant and commercial terms are presented to you before a tenancy is agreed.', 'theme.template.page_rent_with_pera_v2.process_3_body' ) ); ?></p></div>
                </article>
                <article class="feature-card">
                    <div class="feature-card-header"><h3><?php echo esc_html( pera_ml_ui( '4) Establish the tenancy', 'theme.template.page_rent_with_pera_v2.process_4_heading' ) ); ?></h3></div>
                    <div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'We coordinate the tenancy agreement, deposit, handover and the practical steps required for the tenant to move in.', 'theme.template.page_rent_with_pera_v2.process_4_body' ) ); ?></p></div>
                </article>
                <article class="feature-card">
                    <div class="feature-card-header"><h3><?php echo esc_html( pera_ml_ui( '5) Manage the tenancy', 'theme.template.page_rent_with_pera_v2.process_5_heading' ) ); ?></h3></div>
                    <div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'With Full Management, Pera remains the local contact for tenant communication, payment follow-up, inspections, maintenance and contractor coordination.', 'theme.template.page_rent_with_pera_v2.process_5_body' ) ); ?></p></div>
                </article>
                <article class="feature-card">
                    <div class="feature-card-header"><h3><?php echo esc_html( pera_ml_ui( '6) Renew or check out', 'theme.template.page_rent_with_pera_v2.process_6_heading' ) ); ?></h3></div>
                    <div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'We coordinate renewal discussions or the move-out process, including property checks and the next steps for re-letting where required.', 'theme.template.page_rent_with_pera_v2.process_6_body' ) ); ?></p></div>
                </article>
            </div>
        </div>
    </section>

    <!-- PROBLEM HANDLING -->
    <section class="section section-soft" id="property-management-problems">
        <div class="content-panel-box">
            <header class="section-header">
                <h2><?php echo esc_html( pera_ml_ui( 'What happens when there is a problem?', 'theme.template.page_rent_with_pera_v2.problems_heading' ) ); ?></h2>
                <p><?php echo esc_html( pera_ml_ui( 'This is where local management matters most. Instead of trying to solve an Istanbul property problem from another country, you have a team here that can establish what has happened, speak to the relevant people and coordinate the next step.', 'theme.template.page_rent_with_pera_v2.problems_intro' ) ); ?></p>
            </header>

            <div class="feature-grid">
                <article class="feature-card">
                    <div class="feature-card-header"><h3><?php echo esc_html( pera_ml_ui( 'A tenant pays late', 'theme.template.page_rent_with_pera_v2.problem_late_heading' ) ); ?></h3></div>
                    <div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'We follow up with the tenant, establish the reason for the delay and keep you informed if further action is required.', 'theme.template.page_rent_with_pera_v2.problem_late_body' ) ); ?></p></div>
                </article>
                <article class="feature-card">
                    <div class="feature-card-header"><h3><?php echo esc_html( pera_ml_ui( 'Something needs repairing', 'theme.template.page_rent_with_pera_v2.problem_repair_heading' ) ); ?></h3></div>
                    <div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'We assess the issue, coordinate contractors and obtain approval before chargeable third-party work proceeds.', 'theme.template.page_rent_with_pera_v2.problem_repair_body' ) ); ?></p></div>
                </article>
                <article class="feature-card">
                    <div class="feature-card-header"><h3><?php echo esc_html( pera_ml_ui( 'The building contacts the owner', 'theme.template.page_rent_with_pera_v2.problem_building_heading' ) ); ?></h3></div>
                    <div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'We can communicate locally about practical building-management and property matters and explain anything that requires your decision.', 'theme.template.page_rent_with_pera_v2.problem_building_body' ) ); ?></p></div>
                </article>
                <article class="feature-card">
                    <div class="feature-card-header"><h3><?php echo esc_html( pera_ml_ui( 'The tenant wants to leave', 'theme.template.page_rent_with_pera_v2.problem_leave_heading' ) ); ?></h3></div>
                    <div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'We coordinate communication, the property check and handover, then discuss the most practical route to getting the property rented again.', 'theme.template.page_rent_with_pera_v2.problem_leave_body' ) ); ?></p></div>
                </article>
            </div>
        </div>
    </section>

    <section class="faq-section section" id="rental-management-faq">
        <div class="container">
            <h2><?php echo esc_html( pera_ml_ui( 'Rental management FAQ', 'theme.template.page_rent_with_pera.rental_management_faq_heading' ) ); ?></h2>
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
