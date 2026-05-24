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

$hero_heading = $args['hero_heading'] ?? 'Talk to Pera about your Istanbul plans';
$hero_intro   = $args['hero_intro']   ?? 'Whether you’re buying, selling, or renting in Istanbul, our team can walk you through the numbers, the legal steps, and the neighbourhoods that fit your strategy.';

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

        
            <h1>Property management in Istanbul for overseas and local owners</h1>
            <?php if ( $is_rent_page_seo_preview ) : ?>
                <p class="lead">Rent out property in Istanbul with a trusted Istanbul property management company. We focus on long-term rental management in Istanbul for local and foreign owners, covering tenant sourcing, rent collection, property care, and clear owner reporting.</p>
            <?php else : ?>
                <p class="lead">
                  Pera provides full-service rental and property management in Istanbul, including tenant sourcing, contracts, rent collection, maintenance coordination, renewals, and dedicated owner support.
                </p>
            <?php endif; ?>
        
            <div class="hero-actions">
              <a href="#pricing" class="btn btn--solid btn--blue">See pricing</a>
              <a href="#contact" class="btn btn--solid btn--green">Get a valuation</a>
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
                        <h2>Why choose Pera for property management in Istanbul?</h2>
                        <p>
                            We deliver rental management for Istanbul property owners through professional marketing,
                            strong tenant checks, and hands-on service that protects your time and your investment.
                            If you are preparing <a href="/property/">a property for rent in Istanbul</a>, or considering
                            <a href="/sell-your-istanbul-real-estate/">selling your Istanbul property</a>, our team can advise on the best route.
                        </p>
                    </header>

                    <ul class="checklist">

                        <li>
                            <svg class="icon icon-tick" aria-hidden="true">
                                <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                            </svg>
                            Marketing on all major platforms.
                        </li>

                        <li>
                            <svg class="icon icon-tick" aria-hidden="true">
                                <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                            </svg>
                            Full tenant screening &amp; ID verification.
                        </li>

                        <li>
                            <svg class="icon icon-tick" aria-hidden="true">
                                <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                            </svg>
                            Maintenance, repairs and inspections.
                        </li>

                        <li>
                            <svg class="icon icon-tick" aria-hidden="true">
                                <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                            </svg>
                            24/7 support for tenants.
                        </li>

                        <li>
                            <svg class="icon icon-tick" aria-hidden="true">
                                <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                            </svg>
                            Monthly statements &amp; full transparency.
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
            <header class="section-header section-header--center"><h2>Property Management Services in Istanbul</h2></header>
            <div class="feature-grid">
                <article class="feature-card"><div class="feature-card-header"><h3>Long-term rental management</h3></div><div class="feature-card-body"><p>Our Istanbul rental management service includes valuation, marketing, tenant sourcing, tenancy coordination, Istanbul rent collection, and ongoing apartment management for local and overseas owners.</p></div></article>
                <article class="feature-card"><div class="feature-card-header"><h3>Landlord administration</h3></div><div class="feature-card-body"><p>We support Istanbul landlord services including utility setup, DASK and annual property tax support, deposit handling, and owner reporting tailored to overseas landlords with property in Istanbul.</p></div></article>
                <article class="feature-card"><div class="feature-card-header"><h3>Asset care and approvals</h3></div><div class="feature-card-body"><p>Maintenance coordination, inspections, and contractor management are handled with owner approvals before third-party costs are committed.</p></div></article>
            </div>
        </div>
    </section>
    <section class="section"><div class="content-panel-box"><header class="section-header"><h2>Long-Term Rental Management for Foreign Owners</h2><p>As a property management company in Istanbul, we are set up for foreign owners who need dependable local execution. If you are abroad, we can manage your Istanbul apartment end-to-end and keep decisions clear, documented, and approval-led.</p></header></div></section>
    <section class="section section-soft"><div class="content-panel-box"><header class="section-header section-header--center"><h2>Best Istanbul Areas for Rental Demand</h2><p>We regularly manage and let homes in <a href="/district/istanbul/besiktas/">Beşiktaş</a>, <a href="/district/istanbul/sisli/">Şişli</a> (including Bomonti and Nişantaşı), <a href="/district/istanbul/kagithane/">Kağıthane</a>, <a href="/district/istanbul/kadikoy/">Kadıköy</a>, <a href="/district/istanbul/uskudar/">Üsküdar</a>, <a href="/district/istanbul/bakirkoy/">Bakırköy</a>, and Atakent / Küçükçekmece. Explore our <a href="/property/">property listings</a> and <a href="/buyer-guides/">buyer guides</a> for district context.</p></header></div></section>
    <section class="section"><div class="content-panel-box"><header class="section-header section-header--center"><h2>Why International Owners Choose Pera Property</h2><p>Owners choose us for practical execution, transparent communication, and reliable process controls across tenant checks, rent collection, maintenance, and reporting. Contact us through <a href="/contact/">our contact page</a> to discuss your rental plan.</p></header></div></section>
    <?php endif; ?>

    <!-- PRICING -->
    <section id="pricing" class="section section-soft">
        <div class="section-header section-header--center">
            <h2>Our rental management services</h2>
            <p>Choose the service level that fits your investment strategy.</p>
        </div>

        <div class="feature-grid">

            <!-- LETTINGS ONLY -->
            <article class="feature-card">
                <div class="feature-card-header">
                    <h3>Lettings Only</h3>
                    <p class="price-tag">8% + VAT</p>
                </div>

                <div class="feature-card-body">
                    <ul class="checklist">
                        <li>
                            <svg class="icon icon-tick" aria-hidden="true">
                                <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                            </svg>
                            Advertising on all major rental platforms
                        </li>
                        <li>
                            <svg class="icon icon-tick" aria-hidden="true">
                                <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                            </svg>
                            Tenant viewings and shortlisting
                        </li>
                        <li>
                            <svg class="icon icon-tick" aria-hidden="true">
                                <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                            </svg>
                            Full tenant screening &amp; ID verification
                        </li>
                        <li>
                            <svg class="icon icon-tick" aria-hidden="true">
                                <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                            </svg>
                            Tenancy agreement preparation and signing
                        </li>
                    </ul>
                </div>

                <div class="feature-card-footer">
                    <a href="#contact" class="btn btn--solid btn--green">Get valuation</a>
                </div>
            </article>

            <!-- FULL MANAGEMENT -->
            <article class="feature-card">
                <div class="feature-card-header">
                    <h3>Full Management</h3>
                    <p class="price-tag">12% + VAT</p>
                </div>

                <div class="feature-card-body">
                    <ul class="checklist">
                        <li>
                            <svg class="icon icon-tick" aria-hidden="true">
                                <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                            </svg>
                            Everything in Lettings Only
                        </li>
                        <li>
                            <svg class="icon icon-tick" aria-hidden="true">
                                <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                            </svg>
                            Dedicated property manager in Istanbul
                        </li>
                        <li>
                            <svg class="icon icon-tick" aria-hidden="true">
                                <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                            </svg>
                            Rent collection and arrears management
                        </li>
                        <li>
                            <svg class="icon icon-tick" aria-hidden="true">
                                <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                            </svg>
                            Organising repairs, quotes &amp; contractors
                        </li>
                        <li>
                            <svg class="icon icon-tick" aria-hidden="true">
                                <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                            </svg>
                            Regular inspections with condition reports
                        </li>
                        <li>
                            <svg class="icon icon-tick" aria-hidden="true">
                                <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                            </svg>
                            Utility transfers, deposits &amp; move-out checks
                        </li>
                    </ul>
                </div>

                <div class="feature-card-footer">
                    <a href="#contact" class="btn btn--solid btn--green">Get valuation</a>
                </div>
            </article>

        </div>
    </section>

    <section class="section section-soft" id="rental-management-process">
        <div class="content-panel-box">
            <header class="section-header section-header--center">
                <h2>How our Istanbul property management service works</h2>
                <p>Our process is designed to keep rental management straightforward for Istanbul property owners.</p>
            </header>

            <div class="feature-grid">
                <article class="feature-card">
                    <div class="feature-card-header">
                        <h3>1) Rental valuation</h3>
                    </div>
                    <div class="feature-card-body">
                        <ul class="checklist">
                            <li>
                                <svg class="icon icon-tick" aria-hidden="true">
                                    <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                                </svg>
                                We assess market demand, building profile, and district comparables in areas such as Beşiktaş, Şişli, Beyoğlu, and Kadıköy.
                            </li>
                        </ul>
                    </div>
                </article>

                <article class="feature-card">
                    <div class="feature-card-header">
                        <h3>2) Marketing and tenant sourcing</h3>
                    </div>
                    <div class="feature-card-body">
                        <ul class="checklist">
                            <li>
                                <svg class="icon icon-tick" aria-hidden="true">
                                    <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                                </svg>
                                We market your home across major channels to attract qualified long-term tenants quickly.
                            </li>
                        </ul>
                    </div>
                </article>

                <article class="feature-card">
                    <div class="feature-card-header">
                        <h3>3) Tenant screening</h3>
                    </div>
                    <div class="feature-card-body">
                        <ul class="checklist">
                            <li>
                                <svg class="icon icon-tick" aria-hidden="true">
                                    <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                                </svg>
                                We complete documentation and affordability checks before presenting tenants for your approval.
                            </li>
                        </ul>
                    </div>
                </article>

                <article class="feature-card">
                    <div class="feature-card-header">
                        <h3>4) Contract and deposit setup</h3>
                    </div>
                    <div class="feature-card-body">
                        <ul class="checklist">
                            <li>
                                <svg class="icon icon-tick" aria-hidden="true">
                                    <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                                </svg>
                                We prepare compliant tenancy contracts and organise deposit handling with clear owner sign-off.
                            </li>
                        </ul>
                    </div>
                </article>

                <article class="feature-card">
                    <div class="feature-card-header">
                        <h3>5) Rent collection and maintenance</h3>
                    </div>
                    <div class="feature-card-body">
                        <ul class="checklist">
                            <li>
                                <svg class="icon icon-tick" aria-hidden="true">
                                    <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                                </svg>
                                Our Istanbul rental management team supports collections, maintenance coordination, and tenant communication.
                            </li>
                        </ul>
                    </div>
                </article>

                <article class="feature-card">
                    <div class="feature-card-header">
                        <h3>6) Renewal or exit management</h3>
                    </div>
                    <div class="feature-card-body">
                        <ul class="checklist">
                            <li>
                                <svg class="icon icon-tick" aria-hidden="true">
                                    <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                                </svg>
                                We manage rent reviews, renewals, and move-out processes while keeping you informed at every stage.
                            </li>
                        </ul>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="section" id="rental-management-faq">
        <div class="content-panel-box">
            <header class="section-header section-header--center">
                <h2><?php echo $is_rent_page_seo_preview ? 'Istanbul Property Management FAQ' : 'Rental management FAQ'; ?></h2>
                <p>Everything you need to know about property management in Istanbul and how our rental management service works in practice.</p>
            </header>

            <div class="faq-list">

                <details>
                    <summary>What does your full property management in Istanbul service include?</summary>
                    <p>Our service is fully hands-off for Istanbul property owners. We handle tenant sourcing, marketing, viewings, lease preparation, tenant screening, contract negotiation, renewals, maintenance coordination, and ongoing tenant communication. We also assist with utility setup, tax guidance, and end-of-tenancy processes.</p>
                </details>

                <details>
                    <summary>What is your rental management fee?</summary>
                    <p>Our full property management service in Istanbul is charged at <strong>12% + VAT</strong>. This covers the ongoing management of the property throughout the tenancy, including renewals and day-to-day tenant management.</p>
                </details>

                <details>
                    <summary>Are there any additional costs?</summary>
                    <p>Yes — the management fee covers our service only. Property-related costs such as maintenance, repairs, taxes, insurance, utilities, or building charges are separate and always subject to your approval before any work is carried out.</p>
                </details>

                <details>
                    <summary>How do you find and select tenants?</summary>
                    <p>As part of our rental management in Istanbul, we market your property across our network and screen all applicants carefully. This typically includes employment and income checks, documentation review, and — where appropriate — requiring a Turkish guarantor. Our focus is always on placing reliable, financially stable tenants.</p>
                </details>

                <details>
                    <summary>Will I approve the tenant before the contract is signed?</summary>
                    <p>Yes. We present you with the proposed tenant and agreed terms before any contract is finalised. No tenancy is confirmed without your approval.</p>
                </details>

                <details>
                    <summary>Do you provide the rental contract in English?</summary>
                    <p>Yes. We can prepare bilingual Turkish and English contracts so that you fully understand the terms of the agreement while ensuring compliance with local regulations.</p>
                </details>

                <details>
                    <summary>How are rent increases handled?</summary>
                    <p>Rent increases are managed in line with Turkish law, typically based on the official CPI (TÜFE) cap. We handle negotiations with the tenant and advise you on the optimal approach at each renewal period.</p>
                </details>

                <details>
                    <summary>Do you use any legal protection for the landlord?</summary>
                    <p>Yes. Where appropriate, we arrange a notarised exit undertaking (tahliye taahhütnamesi), which provides additional legal protection in case the tenant does not vacate at the end of the agreed term.</p>
                </details>

                <details>
                    <summary>How is the tenant deposit handled?</summary>
                    <p>We typically secure a two-month deposit, which is held in accordance with Turkish rental practices. At the end of the tenancy, the property is inspected and any agreed deductions are applied before the remaining balance is returned.</p>
                </details>

                <details>
                    <summary>How are utilities managed?</summary>
                    <p>For tenanted properties, utilities are usually transferred into the tenant’s name. For new properties, the owner may need to open the accounts initially. We manage and coordinate this process on your behalf.</p>
                </details>

                <details>
                    <summary>How do you handle maintenance and repairs?</summary>
                    <p>If an issue arises, we coordinate with trusted contractors, obtain quotes where necessary, and seek your approval before proceeding. No expense is incurred without your consent, so Istanbul property owners stay in control.</p>
                </details>

                <details>
                    <summary>Do I receive reports or updates?</summary>
                    <p>Rent is typically paid directly to the owner, so formal monthly reporting is not always required. However, we keep you informed of any key developments and can provide structured reporting if you prefer a more hands-on overview.</p>
                </details>

                <details>
                    <summary>Can I take over management myself later?</summary>
                    <p>Yes. You are free to take over management at any time with reasonable notice. We will ensure a smooth handover of all relevant documents and tenant information.</p>
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
                        <h2>The short term rental market (“Airbnb”)</h2>
                        <p>
                            Our core service is long-term property management in Istanbul, while short-term rental support is available where suitable for the asset and location.
                            If you need dedicated holiday-let support, see our
                            <a href="/short-term-rental-airbnb-in-istanbul_49220/">short-term rental and Airbnb management service</a>.
                        </p>
                    </header>

                    <ul class="checklist">

                        <li>
                            <svg class="icon icon-tick" aria-hidden="true">
                                <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                            </svg>
                            Check-in / check-out management
                        </li>

                        <li>
                            <svg class="icon icon-tick" aria-hidden="true">
                                <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                            </svg>
                            Cleaning &amp; maintenance
                        </li>

                        <li>
                            <svg class="icon icon-tick" aria-hidden="true">
                                <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                            </svg>
                            Guest communication
                        </li>

                        <li>
                            <svg class="icon icon-tick" aria-hidden="true">
                                <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                            </svg>
                            Supplies &amp; inventory management
                        </li>

                    </ul>
                </div>

                <!-- RIGHT -->
                <div>
                    <div class="media-frame">
                        <img class="media-embed"
                             src="<?php echo get_stylesheet_directory_uri(); ?>/images/airbnb-istanbul.jpg"
                             alt="Airbnb management Istanbul – Pera Property">
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
        
                        <ul class="checklist">
                            <li>
                                <svg class="icon icon-tick" aria-hidden="true">
                                    <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                                </svg>
                                Reliable, data-driven advice.
                            </li>
        
                            <li>
                                <svg class="icon icon-tick" aria-hidden="true">
                                    <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                                </svg>
                                On-the-ground Istanbul expertise.
                            </li>
        
                            <li>
                                <svg class="icon icon-tick" aria-hidden="true">
                                    <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                                </svg>
                                Multi-lingual support.
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
                            <h3 class="text-light">Speak with a Consultant</h3>
        
                            <div class="hero-actions flex-center">
                                <a href="https://www.peraproperty.com/contact-us/" class="btn btn--solid btn--green">
                                    Book a consultation
                                </a>
        
                                <a href="https://wa.me/905452054356?text=Hello%20Pera%20Property%2C%20I%27d%20like%20to%20discuss%20Istanbul%20real%20estate."
                                   class="btn btn--solid btn--green"
                                   data-whatsapp="1"
                                   data-whatsapp-type="service_cta"
                                   data-track-channel="whatsapp"
                                   data-track-intent="high"
                                   data-track-source="template"
                                   data-track-context="rent_with_pera"
                                   data-track-ga4-event="whatsapp_click"
                                   data-track-crm-event="whatsapp_click">
                                    Chat on WhatsApp
                                </a>
                            </div>
                        </div>
        
                    </div><!-- .media-frame -->
        
                </div><!-- .content-panel-grid -->
    
                <div>
        
                    <?php if ( isset( $_GET['sr_status'] ) && $_GET['sr_status'] === 'sent' ) : ?>
                        <div class="form-success">
                            Thank you – we have received your details. A Pera consultant will contact you shortly.
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
