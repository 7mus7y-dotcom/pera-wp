<?php
/**
 * Template Name: Rent with Pera
 */

if ( ! defined( 'ABSPATH' ) ) exit;


$hero_heading = $args['hero_heading'] ?? pera_ml_ui( 'Tell us what you need managed in Istanbul', 'theme.template.page_rent_with_pera_v2.contact_heading' );
$hero_intro   = $args['hero_intro']   ?? pera_ml_ui( 'Tell us whether the property is currently rented, ready to let, or kept for your own use. We can then recommend the appropriate level of local support.', 'theme.template.page_rent_with_pera_v2.contact_intro' );

if ( ! function_exists( 'pera_rent_with_pera_faq_schema' ) ) {
    function pera_rent_with_pera_faq_schema() {
        if ( ! is_page_template( 'page-rent-with-pera.php' ) ) {
            return;
        }

        $faq_entities = array(
            array(
                'question' => 'Can you manage my Istanbul property if I live overseas?',
                'answer'   => 'Yes. The service is designed for owners who need a reliable local point of contact in Istanbul. We can manage a long-term tenancy or look after a second home that is not rented.',
            ),
            array(
                'question' => 'Do I have to rent out my property to use your management service?',
                'answer'   => 'No. Second Home Care is for owner-occupied apartments and villas that need local oversight while the owner is away. The scope is agreed around the property and the level of support you require.',
            ),
            array(
                'question' => 'What is the difference between Lettings Only and Full Management?',
                'answer'   => 'Lettings Only is for finding, screening and establishing the tenant. Full Management keeps Pera involved after move-in as the local contact for the ongoing tenancy, property issues and tenant communication.',
            ),
            array(
                'question' => 'What happens if a repair is needed while I am abroad?',
                'answer'   => 'We can establish the issue, coordinate suitable contractors and keep you updated. Chargeable third-party work is referred to you for approval unless a different authority has been agreed in advance.',
            ),
            array(
                'question' => 'How much does property management cost?',
                'answer'   => 'Our Lettings Only service is 8% + VAT and Full Management is 12% + VAT. Second Home Care is quoted separately because the required level of inspection, access and ongoing property support varies considerably between homes.',
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

            <article class="feature-card">
                <div class="feature-card-header">
                    <h3><?php echo esc_html( pera_ml_ui( 'Second Home Care', 'theme.template.page_rent_with_pera_v2.second_home_care' ) ); ?></h3>
                    <p class="price-tag"><?php echo esc_html( pera_ml_ui( 'Bespoke', 'theme.template.page_rent_with_pera_v2.bespoke' ) ); ?></p>
                </div>
                <div class="feature-card-body">
                    <p><?php echo esc_html( pera_ml_ui( 'For owners who use their Istanbul property themselves but need somebody local to look after it while they are away.', 'theme.template.page_rent_with_pera_v2.second_home_intro' ) ); ?></p>
                    <ul class="checklist checklist--circle">
                        <li><?php echo esc_html( pera_ml_ui( 'Scheduled property checks while the home is empty', 'theme.template.page_rent_with_pera_v2.second_home_checks' ) ); ?></li>
                        <li><?php echo esc_html( pera_ml_ui( 'Local access for maintenance and contractors', 'theme.template.page_rent_with_pera_v2.second_home_access' ) ); ?></li>
                        <li><?php echo esc_html( pera_ml_ui( 'Building-management and practical property coordination', 'theme.template.page_rent_with_pera_v2.second_home_building' ) ); ?></li>
                        <li><?php echo esc_html( pera_ml_ui( 'Utility and property issue coordination', 'theme.template.page_rent_with_pera_v2.second_home_utilities' ) ); ?></li>
                        <li><?php echo esc_html( pera_ml_ui( 'Preparing the property before you return to Istanbul', 'theme.template.page_rent_with_pera_v2.second_home_return' ) ); ?></li>
                    </ul>
                </div>
                <div class="feature-card-footer">
                    <a href="#contact" class="btn btn--solid btn--green"><?php echo esc_html( pera_ml_ui( 'Discuss home care', 'theme.template.page_rent_with_pera_v2.discuss_home_care' ) ); ?></a>
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

    <!-- SECOND HOME MANAGEMENT -->
    <section class="section" id="second-home-management">
        <div class="content-panel-box panel-gradient-brand">
            <div class="content-panel-grid">
                <div>
                    <header class="section-header">
                        <h2><?php echo esc_html( pera_ml_ui( 'Property management for your Istanbul second home', 'theme.template.page_rent_with_pera_v2.second_home_heading' ) ); ?></h2>
                        <p><?php echo esc_html( pera_ml_ui( 'Not every property needs a tenant. Many overseas owners bought an Istanbul apartment or villa for their own use and simply need somebody they trust to take care of it between visits.', 'theme.template.page_rent_with_pera_v2.second_home_body_1' ) ); ?></p>
                        <p><?php echo esc_html( pera_ml_ui( 'An empty home still needs attention. A leak, power issue, building notice or maintenance problem is much easier to deal with when somebody can physically visit the property. We can provide an Istanbul-based point of contact, arrange scheduled checks and coordinate access when work is required.', 'theme.template.page_rent_with_pera_v2.second_home_body_2' ) ); ?></p>
                    </header>
                </div>
                <div>
                    <ul class="checklist checklist--circle">
                        <li><?php echo esc_html( pera_ml_ui( 'Periodic visual checks and owner updates', 'theme.template.page_rent_with_pera_v2.home_periodic_checks' ) ); ?></li>
                        <li><?php echo esc_html( pera_ml_ui( 'Key holding and agreed property access', 'theme.template.page_rent_with_pera_v2.home_key_holding' ) ); ?></li>
                        <li><?php echo esc_html( pera_ml_ui( 'Contractor access and repair coordination', 'theme.template.page_rent_with_pera_v2.home_contractors' ) ); ?></li>
                        <li><?php echo esc_html( pera_ml_ui( 'Communication on practical building-management matters', 'theme.template.page_rent_with_pera_v2.home_building_management' ) ); ?></li>
                        <li><?php echo esc_html( pera_ml_ui( 'Pre-arrival checks and property preparation by agreement', 'theme.template.page_rent_with_pera_v2.home_prearrival' ) ); ?></li>
                    </ul>
                    <a href="#contact" class="btn btn--solid btn--green"><?php echo esc_html( pera_ml_ui( 'Tell us about your property', 'theme.template.page_rent_with_pera_v2.tell_us_property' ) ); ?></a>
                </div>
            </div>
        </div>
    </section>

    <!-- OWNER PRACTICALITIES -->
    <section class="section section-soft">
        <div class="content-panel-box">
            <header class="section-header section-header--center">
                <h2><?php echo esc_html( pera_ml_ui( 'One local contact for your Istanbul property', 'theme.template.page_rent_with_pera_v2.one_local_contact' ) ); ?></h2>
                <p><?php echo esc_html( pera_ml_ui( 'Whether the property is rented or kept for your own use, the value of management is having somebody in Istanbul who knows the property and can coordinate practical issues when you cannot be here.', 'theme.template.page_rent_with_pera_v2.one_local_contact_intro' ) ); ?></p>
            </header>
            <div class="feature-grid">
                <article class="feature-card"><div class="feature-card-header"><h3><?php echo esc_html( pera_ml_ui( 'Property access', 'theme.template.page_rent_with_pera_v2.access_heading' ) ); ?></h3></div><div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'We can attend the property or coordinate agreed access when an inspection, contractor visit or practical issue requires somebody on site.', 'theme.template.page_rent_with_pera_v2.access_body' ) ); ?></p></div></article>
                <article class="feature-card"><div class="feature-card-header"><h3><?php echo esc_html( pera_ml_ui( 'Maintenance coordination', 'theme.template.page_rent_with_pera_v2.maintenance_heading' ) ); ?></h3></div><div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'We can obtain information or quotes, coordinate the work and keep you updated rather than leaving you to manage contractors remotely.', 'theme.template.page_rent_with_pera_v2.maintenance_body' ) ); ?></p></div></article>
                <article class="feature-card"><div class="feature-card-header"><h3><?php echo esc_html( pera_ml_ui( 'Owner control', 'theme.template.page_rent_with_pera_v2.control_heading' ) ); ?></h3></div><div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'You remain the decision-maker. We handle the local execution and refer material decisions and chargeable third-party work to you for approval.', 'theme.template.page_rent_with_pera_v2.control_body' ) ); ?></p></div></article>
            </div>
        </div>
    </section>

    <!-- LANDLORD GUIDES -->
    <section class="section">
        <div class="content-panel-box">
            <header class="section-header">
                <h2><?php echo esc_html( pera_ml_ui( 'Guides for Istanbul property owners', 'theme.template.page_rent_with_pera_v2.guides_heading' ) ); ?></h2>
                <p><?php echo esc_html( pera_ml_ui( 'If you are deciding whether to rent your property, these Pera guides cover the practical issues overseas owners most often ask us about.', 'theme.template.page_rent_with_pera_v2.guides_intro' ) ); ?></p>
            </header>
            <div class="feature-grid">
                <article class="feature-card"><div class="feature-card-header"><h3><?php echo esc_html( pera_ml_ui( 'How to rent out property in Istanbul', 'theme.template.page_rent_with_pera_v2.guide_rent_heading' ) ); ?></h3></div><div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'Our landlord guide covers the practical process of preparing, marketing and renting an Istanbul property.', 'theme.template.page_rent_with_pera_v2.guide_rent_body' ) ); ?></p></div><div class="feature-card-footer"><a class="btn btn--ghost btn--green" href="/how-to-rent-out-property-in-istanbul-2026-landlord-guide_58969/"><?php echo esc_html( pera_ml_ui( 'Read the landlord guide', 'theme.template.page_rent_with_pera_v2.read_landlord_guide' ) ); ?></a></div></article>
                <article class="feature-card"><div class="feature-card-header"><h3><?php echo esc_html( pera_ml_ui( 'Common mistakes foreign owners make', 'theme.template.page_rent_with_pera_v2.guide_mistakes_heading' ) ); ?></h3></div><div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'A practical look at avoidable problems when an overseas owner rents property in Istanbul.', 'theme.template.page_rent_with_pera_v2.guide_mistakes_body' ) ); ?></p></div><div class="feature-card-footer"><a class="btn btn--ghost btn--green" href="/common-mistakes-foreign-owners-make-when-renting-property-in-istanbul_58964/"><?php echo esc_html( pera_ml_ui( 'Read the guide', 'theme.template.page_rent_with_pera_v2.read_guide' ) ); ?></a></div></article>
                <article class="feature-card"><div class="feature-card-header"><h3><?php echo esc_html( pera_ml_ui( 'Running costs and maintenance fees', 'theme.template.page_rent_with_pera_v2.guide_costs_heading' ) ); ?></h3></div><div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'Understand the recurring costs that continue whether an Istanbul property is rented or used as a second home.', 'theme.template.page_rent_with_pera_v2.guide_costs_body' ) ); ?></p></div><div class="feature-card-footer"><a class="btn btn--ghost btn--green" href="/maintenance-fees-and-annual-running-costs_3100/"><?php echo esc_html( pera_ml_ui( 'Read about running costs', 'theme.template.page_rent_with_pera_v2.read_running_costs' ) ); ?></a></div></article>
            </div>
        </div>
    </section>

    <!-- SHORT TERM RENTALS -->
    <section class="section section-soft">
        <div class="content-panel-box">
            <header class="section-header">
                <h2><?php echo esc_html( pera_ml_ui( 'Looking for short-term rental management?', 'theme.template.page_rent_with_pera_v2.airbnb_heading' ) ); ?></h2>
                <p><?php echo esc_html( pera_ml_ui( 'Short-term letting is a separate service with different operational and regulatory requirements. Our main property management service on this page is designed around long-term tenancies and second-home care.', 'theme.template.page_rent_with_pera_v2.airbnb_intro' ) ); ?></p>
                <p><a href="/short-term-rental-airbnb-in-istanbul_49220/"><?php echo esc_html( pera_ml_ui( 'Read about Pera short-term rental and Airbnb management in Istanbul.', 'theme.template.page_rent_with_pera_v2.airbnb_link' ) ); ?></a></p>
            </header>
        </div>
    </section>

    <!-- FAQ -->
    <section class="faq-section section" id="rental-management-faq">
        <div class="container">
            <h2><?php echo esc_html( pera_ml_ui( 'Istanbul property management FAQ', 'theme.template.page_rent_with_pera_v2.faq_heading' ) ); ?></h2>
            <p><?php echo esc_html( pera_ml_ui( 'Practical questions about long-term rental management and looking after an Istanbul home while you are overseas.', 'theme.template.page_rent_with_pera_v2.faq_intro' ) ); ?></p>
            <div class="faq-accordion">
                <details class="faq-item" open><summary><?php echo esc_html( pera_ml_ui( 'Can you manage my Istanbul property if I live overseas?', 'theme.template.page_rent_with_pera_v2.faq_overseas_q' ) ); ?></summary><div class="faq-answer"><p><?php echo esc_html( pera_ml_ui( 'Yes. The service is designed for owners who need a reliable local point of contact in Istanbul. We can manage a long-term tenancy or look after a second home that is not rented.', 'theme.template.page_rent_with_pera_v2.faq_overseas_a' ) ); ?></p></div></details>
                <details class="faq-item"><summary><?php echo esc_html( pera_ml_ui( 'Do I have to rent out my property to use your management service?', 'theme.template.page_rent_with_pera_v2.faq_second_q' ) ); ?></summary><div class="faq-answer"><p><?php echo esc_html( pera_ml_ui( 'No. Second Home Care is for owner-occupied apartments and villas that need local oversight while the owner is away. The scope is agreed around the property and the level of support you require.', 'theme.template.page_rent_with_pera_v2.faq_second_a' ) ); ?></p></div></details>
                <details class="faq-item"><summary><?php echo esc_html( pera_ml_ui( 'What is the difference between Lettings Only and Full Management?', 'theme.template.page_rent_with_pera_v2.faq_difference_q' ) ); ?></summary><div class="faq-answer"><p><?php echo esc_html( pera_ml_ui( 'Lettings Only is for finding, screening and establishing the tenant. Full Management keeps Pera involved after move-in as the local contact for the ongoing tenancy, property issues and tenant communication.', 'theme.template.page_rent_with_pera_v2.faq_difference_a' ) ); ?></p></div></details>
                <details class="faq-item"><summary><?php echo esc_html( pera_ml_ui( 'What happens if a repair is needed while I am abroad?', 'theme.template.page_rent_with_pera_v2.faq_repair_q' ) ); ?></summary><div class="faq-answer"><p><?php echo esc_html( pera_ml_ui( 'We can establish the issue, coordinate suitable contractors and keep you updated. Chargeable third-party work is referred to you for approval unless a different authority has been agreed in advance.', 'theme.template.page_rent_with_pera_v2.faq_repair_a' ) ); ?></p></div></details>
                <details class="faq-item"><summary><?php echo esc_html( pera_ml_ui( 'How much does property management cost?', 'theme.template.page_rent_with_pera_v2.faq_cost_q' ) ); ?></summary><div class="faq-answer"><p><?php echo esc_html( pera_ml_ui( 'Our Lettings Only service is 8% + VAT and Full Management is 12% + VAT. Second Home Care is quoted separately because the required level of inspection, access and ongoing property support varies considerably between homes.', 'theme.template.page_rent_with_pera_v2.faq_cost_a' ) ); ?></p></div></details>
            </div>
        </div>
    </section>

    <!-- ABOUT PERA -->
    <?php get_template_part( 'parts/about-pera' ); ?>

    <!-- ABOUT PERA V2 — TEMPORARY VISUAL COMPARISON -->
    <?php get_template_part( 'parts/about-pera-v2' ); ?>


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
                      'heading'      => 'Get a rental and management assessment',
                      'intro'        => 'Share a few details about your Istanbul property and tell us whether you need tenant sourcing, full rental management or second-home care while you are away.',
                      'submit_label' => 'Request an assessment',
                      'form_context' => 'rent-page',
                    ));
            
                    ?>
                    
                </div><!-- .enquiry-cta -->
            </div><!-- .content-panel-box -->
        </section>

        

</main>

<?php get_footer(); ?>
