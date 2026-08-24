<?php
/**
 * Template Name: Turkish Citizenship by Investment (Lean)
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<main id="primary" class="site-main">

<?php
$citizenship_requirements = array(
    'Minimum real estate investment: $400,000',
    'Property must be held for at least 3 years',
    'Investment must be paid in foreign currency (DAB required)',
    'Must obtain a valid expertise report',
    'Application includes spouse and children under 18',
    'Process typically takes 3–6 months',
);
?>



  <section class="hero hero--left hero--citizenship citizenship-hero" id="citizenship-hero">
    <div class="hero__media" aria-hidden="true">
      <?php
      echo wp_get_attachment_image(
          55756,
          'full',
          false,
          array(
              'class'         => 'hero-media',
              'alt'           => 'Turkish citizenship by investment through Istanbul real estate',
              'fetchpriority' => 'high',
              'loading'       => 'eager',
              'decoding'      => 'async',
          )
      );
      ?>
      <div class="hero-overlay" aria-hidden="true"></div>
    </div>
    <div class="hero-content">
      <div class="citizenship-hero-grid">
        <div class="citizenship-hero-copy">
          <h1><?php echo esc_html( pera_ml_ui( 'Turkish Citizenship by Investment Through Real Estate', 'theme.template.page_citizenship.turkish_citizenship_by_investment_through_real_estate' ) ); ?></h1>
          <p>
            <?php echo esc_html( pera_ml_ui( 'Apply for Turkish citizenship by investment through a qualifying USD 400,000 real estate purchase in Turkey. Pera Property helps international investors find eligible Istanbul properties, complete legal checks, and manage the citizenship application from purchase to passport.', 'theme.template.page_citizenship.apply_for_turkish_citizenship_by_investment_through_a_qualifying_usd_400' ) ); ?>
          </p>
          <article class="feature-card citizenship-hero-card" aria-label="<?php echo esc_attr( pera_ml_ui( 'Turkish Citizenship by Investment Requirements (2026)', 'theme.template.page_citizenship.aria_label.turkish_citizenship_by_investment_requirements_2026' ) ); ?>">
            <div class="feature-card-header">
              <h2><?php echo esc_html( pera_ml_ui( 'Requirements (2026)', 'theme.template.page_citizenship.requirements_2026' ) ); ?></h2>
            </div>
            <div class="feature-card-body">
              <div class="citizenship-requirements-group">
                <h3><?php echo esc_html( pera_ml_ui( 'Investment', 'theme.template.page_citizenship.investment' ) ); ?></h3>
                <ul class="checklist checklist--circle">
                  <?php foreach ( array_slice( $citizenship_requirements, 0, 4 ) as $requirement ) : ?>
                    <li>
                      <?php echo esc_html( $requirement ); ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
              <div class="citizenship-requirements-group">
                <h3><?php echo esc_html( pera_ml_ui( 'Family & process', 'theme.template.page_citizenship.family_and_process' ) ); ?></h3>
                <ul class="checklist checklist--circle">
                  <?php foreach ( array_slice( $citizenship_requirements, 4 ) as $requirement ) : ?>
                    <li>
                      <?php echo esc_html( $requirement ); ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </div>
          </article>
          <div class="hero-actions">
            <a href="#citizenship-callback" class="btn btn--solid btn--green"><?php echo esc_html( pera_ml_ui( 'Book a consultation', 'theme.template.page_citizenship.book_a_consultation' ) ); ?></a>
            <a href="https://www.peraproperty.com/turkish-citizenship-properties/?view=cards" 
               class="btn btn--solid btn--blue" 
               target="_blank" 
               rel="noopener">
              <?php echo esc_html( pera_ml_ui( 'View Turkish citizenship properties for sale', 'theme.template.page_citizenship.view_turkish_citizenship_properties_for_sale' ) ); ?>
            </a>          
          </div>
          <p class="citizenship-trust-strip text-light"><?php echo esc_html( pera_ml_ui( 'Since 2016 • Istanbul-based team • Legal process clarity', 'theme.template.page_citizenship.since_2016_istanbul_based_team_legal_process_clarity' ) ); ?></p>
        </div>
      </div>
    </div>
  </section>

  <section class="content-panel citizenship-seo-intro">
    <div class="content-panel-box">
      <div class="content-panel-grid--single">
        <header class="section-header section-header--center">
          <h2><?php echo esc_html( pera_ml_ui( 'Turkey Citizenship by Investment with Pera Property', 'theme.template.page_citizenship.turkey_citizenship_by_investment_with_pera_property' ) ); ?></h2>
          <p><?php echo esc_html( pera_ml_ui( 'Turkey citizenship by investment can be a practical route for families seeking a second nationality while owning high-potential Istanbul real estate.', 'theme.template.page_citizenship.turkey_citizenship_by_investment_can_be_a_practical_route_for_families_s' ) ); ?></p>
        </header>
        <div class="citizenship-seo-copy">
          <p><?php echo esc_html( pera_ml_ui( 'Turkey’s citizenship by investment programme allows eligible investors to apply for Turkish citizenship through a qualifying real estate investment of at least USD 400,000. For many families, the Turkish citizenship property route is attractive because it combines a second passport strategy with ownership of a tangible Istanbul property asset.', 'theme.template.page_citizenship.turkey_s_citizenship_by_investment_programme_allows_eligible_investors_t' ) ); ?></p>
          <p><?php echo esc_html( pera_ml_ui( 'Pera Property supports international buyers through the full Turkey citizenship by investment process, from selecting citizenship-eligible property options for sale in Istanbul to coordinating legal due diligence, valuation reports, title deed transfer, foreign currency documentation, residency steps, and the final citizenship application through to passport issuance.', 'theme.template.page_citizenship.pera_property_supports_international_buyers_through_the_full_turkey_citi' ) ); ?></p>
        </div>
      </div>
    </div>
  </section>

  <section class="section section-soft" id="what-is-turkish-citizenship-by-investment">
    <div class="container">
      <header class="section-header section-header--center">
        <h2><?php echo esc_html( pera_ml_ui( 'What Is Turkish Citizenship by Investment?', 'theme.template.page_citizenship.what_is_turkish_citizenship_by_investment' ) ); ?></h2>
        <p>
          <?php echo esc_html( pera_ml_ui( 'Turkish Citizenship by Investment is a legal route that allows eligible foreign investors to apply for Turkish citizenship after completing a qualifying investment, most commonly through real estate.', 'theme.template.page_citizenship.turkish_citizenship_by_investment_is_a_legal_route_that_allows_eligible_' ) ); ?>
        </p>
      </header>

      <div class="feature-grid feature-grid--tablet-3">
        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'The real estate route', 'theme.template.page_citizenship.the_real_estate_route' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p>
              <?php echo esc_html( pera_ml_ui( 'The most popular route is a qualifying property purchase of at least USD 400,000, subject to valuation, title deed, foreign currency and land registry requirements.', 'theme.template.page_citizenship.the_most_popular_route_is_a_qualifying_property_purchase_of_at_least_usd' ) ); ?>
            </p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'Family application', 'theme.template.page_citizenship.family_application' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p>
              <?php echo esc_html( pera_ml_ui( 'The main applicant can usually include a spouse and children under 18 in the same citizenship file, making it suitable for family relocation, second passport and long-term planning.', 'theme.template.page_citizenship.the_main_applicant_can_usually_include_a_spouse_and_children_under_18_in' ) ); ?>
            </p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'Property-led strategy', 'theme.template.page_citizenship.property_led_strategy' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p>
              <?php echo esc_html( pera_ml_ui( 'A well-selected Istanbul property can support the citizenship application while also offering rental income, resale liquidity and long-term exposure to the Turkish real estate market.', 'theme.template.page_citizenship.a_well_selected_istanbul_property_can_support_the_citizenship_applicatio' ) ); ?>
            </p>
          </div>
        </article>
      </div>
    </div>
  </section>

  <section class="section citizenship-consultancy">
    <div class="container">
        <div class="content-panel-grid--single">
          <header class="section-header section-header--center">
            <h2><?php echo esc_html( pera_ml_ui( 'Full-service citizenship consultancy in Istanbul', 'theme.template.page_citizenship.full_service_citizenship_consultancy_in_istanbul' ) ); ?></h2>
            <p><?php echo esc_html( pera_ml_ui( 'Since 2016, Pera’s founders and legal partners have assisted international clients with Turkish Citizenship by Investment, combining specialist real estate knowledge with a dedicated immigration and legal team.', 'theme.template.page_citizenship.since_2016_pera_s_founders_and_legal_partners_have_assisted_internationa' ) ); ?></p>
          </header>
          <div class="feature-grid feature-grid--tablet-3 citizenship-value-grid">
            <article class="feature-card"><div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'Strategic property shortlists aligned with citizenship eligibility and your family’s lifestyle goals.', 'theme.template.page_citizenship.strategic_property_shortlists_aligned_with_citizenship_eligibility_and_y' ) ); ?></p></div></article>
            <article class="feature-card"><div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'Coordinated legal and administrative execution from title deed checks to citizenship filing readiness.', 'theme.template.page_citizenship.coordinated_legal_and_administrative_execution_from_title_deed_checks_to' ) ); ?></p></div></article>
            <article class="feature-card"><div class="feature-card-body"><p><?php echo esc_html( pera_ml_ui( 'One accountable team with clear updates, timeline visibility, and practical next steps at every stage.', 'theme.template.page_citizenship.one_accountable_team_with_clear_updates_timeline_visibility_and_practica' ) ); ?></p></div></article>
          </div>
        </div>
    </div>
  </section>

  <?php get_template_part( 'partials/citizenship-latest-offers' ); ?>

  <!-- =====================================================
       OUR FULL-PACKAGE CITIZENSHIP SERVICE
       ====================================================== -->
  <section class="section section-soft">
    <div class="container">

      <header class="section-header section-header--center">
        <h2><?php echo esc_html( pera_ml_ui( 'Our full-package citizenship service', 'theme.template.page_citizenship.our_full_package_citizenship_service' ) ); ?></h2>
        <p>
          <?php echo esc_html( pera_ml_ui( 'Everything you need for a successful Turkish citizenship application,
          delivered as a single coordinated package by Pera and our legal partners.', 'theme.template.page_citizenship.everything_you_need_for_a_successful_turkish_citizenship_application_del' ) ); ?>
        </p>
      </header>

      <div class="feature-grid">

        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'CBI-eligible properties', 'theme.template.page_citizenship.cbi_eligible_properties' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p>
              <?php echo esc_html( pera_ml_ui( 'Access a curated list of Istanbul properties that fully comply with
              the USD 400,000 citizenship requirement and land registry rules.', 'theme.template.page_citizenship.access_a_curated_list_of_istanbul_properties_that_fully_comply_with_the_' ) ); ?>
            </p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'Legal due diligence', 'theme.template.page_citizenship.legal_due_diligence' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p>
              <?php echo esc_html( pera_ml_ui( 'Independent Turkish lawyers check title deeds, permits and encumbrances
              before you commit to any investment.', 'theme.template.page_citizenship.independent_turkish_lawyers_check_title_deeds_permits_and_encumbrances_b' ) ); ?>
            </p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'Banking & tax numbers', 'theme.template.page_citizenship.banking_and_tax_numbers' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p>
              <?php echo esc_html( pera_ml_ui( 'Support with obtaining your Turkish tax number, opening local bank
              accounts and arranging secure fund transfers.', 'theme.template.page_citizenship.support_with_obtaining_your_turkish_tax_number_opening_local_bank_accoun' ) ); ?>
            </p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'Purchase & title deed', 'theme.template.page_citizenship.purchase_and_title_deed' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p>
              <?php echo esc_html( pera_ml_ui( 'End-to-end assistance with the property purchase, including sales
              contracts, valuation reports and title deed transfer.', 'theme.template.page_citizenship.end_to_end_assistance_with_the_property_purchase_including_sales_contrac' ) ); ?>
            </p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'Citizenship filing', 'theme.template.page_citizenship.citizenship_filing' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p>
              <?php echo esc_html( pera_ml_ui( 'Preparation and submission of residency and citizenship files for
              all eligible family members with ongoing follow-up.', 'theme.template.page_citizenship.preparation_and_submission_of_residency_and_citizenship_files_for_all_el' ) ); ?>
            </p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'After-sales & rentals', 'theme.template.page_citizenship.after_sales_and_rentals' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p>
              <?php echo esc_html( pera_ml_ui( 'Optional property management and rental services to help you
              generate income from your citizenship investment.', 'theme.template.page_citizenship.optional_property_management_and_rental_services_to_help_you_generate_in' ) ); ?>
            </p>
          </div>
        </article>

      </div><!-- /.feature-grid -->

    </div><!-- /.container -->
  </section>

  <!-- =====================================================
       KEY FACTS ABOUT THE PROGRAMME
       ====================================================== -->
  <section class="section section-soft" id="citizenship-key-facts">
    <div class="container">

      <header class="section-header section-header--center">
        <h2><?php echo esc_html( pera_ml_ui( 'Turkey Citizenship by Investment: Key Facts', 'theme.template.page_citizenship.turkey_citizenship_by_investment_key_facts' ) ); ?></h2>
        <p>
          <?php echo esc_html( pera_ml_ui( 'A fast, flexible route to Turkish citizenship for families who invest in qualifying real estate.', 'theme.template.page_citizenship.a_fast_flexible_route_to_turkish_citizenship_for_families_who_invest_in_' ) ); ?>
        </p>
      </header>

      <div class="info-steps">

        <article class="info-step">
          <div class="info-step-icon">
            <span class="info-step-number">1</span>
          </div>
          <div class="info-step-body">
            <h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'USD 400,000+ real estate', 'theme.template.page_citizenship.usd_400_000_real_estate' ) ); ?></h3>
            <p class="info-step-text">
              <?php echo esc_html( pera_ml_ui( 'You must invest at least USD 400,000 in one or more Turkish properties that meet the
              programme’s legal and valuation requirements.', 'theme.template.page_citizenship.you_must_invest_at_least_usd_400_000_in_one_or_more_turkish_properties_t' ) ); ?>
            </p>
          </div>
        </article>

        <article class="info-step">
          <div class="info-step-icon">
            <span class="info-step-number">2</span>
          </div>
          <div class="info-step-body">
            <h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Spouse & children included', 'theme.template.page_citizenship.spouse_and_children_included' ) ); ?></h3>
            <p class="info-step-text">
              <?php echo esc_html( pera_ml_ui( 'Your spouse and children under 18 can be included in the same citizenship application.
              Adult children or parents may require separate routes.', 'theme.template.page_citizenship.your_spouse_and_children_under_18_can_be_included_in_the_same_citizenshi' ) ); ?>
            </p>
          </div>
        </article>

        <article class="info-step">
          <div class="info-step-icon">
            <span class="info-step-number">3</span>
          </div>
          <div class="info-step-body">
            <h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Approx. 4–6 month timeline', 'theme.template.page_citizenship.approx_4_6_month_timeline' ) ); ?></h3>
            <p class="info-step-text">
              <?php echo esc_html( pera_ml_ui( 'From property purchase and residence permits to full citizenship approval, most complete
              files are finalised within around 4–6 months.', 'theme.template.page_citizenship.from_property_purchase_and_residence_permits_to_full_citizenship_approva' ) ); ?>
            </p>
          </div>
        </article>

        <article class="info-step">
          <div class="info-step-icon">
            <span class="info-step-number">4</span>
          </div>
          <div class="info-step-body">
            <h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Dual citizenship possible', 'theme.template.page_citizenship.dual_citizenship_possible' ) ); ?></h3>
            <p class="info-step-text">
              <?php echo esc_html( pera_ml_ui( 'Many nationalities can keep their existing passport when obtaining Turkish citizenship,
              subject to their home country’s rules on dual nationality.', 'theme.template.page_citizenship.many_nationalities_can_keep_their_existing_passport_when_obtaining_turki' ) ); ?>
            </p>
          </div>
        </article>

      </div><!-- /.info-steps -->

    </div><!-- /.container -->
  </section>

  <!-- =====================================================
       CONDITIONS TO BE MET
       ====================================================== -->
  <section class="section" id="conditions">
    <div class="container">

      <header class="section-header section-header--center">
        <h2><?php echo esc_html( pera_ml_ui( 'Turkish Citizenship Property Investment Requirements', 'theme.template.page_citizenship.turkish_citizenship_property_investment_requirements' ) ); ?></h2>
      </header>

      <div class="info-steps">

        <!-- VALUE -->
        <article class="info-step">
          <div class="info-step-icon">
            <svg class="icon" aria-hidden="true" width="24" height="24">
              <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-currency"></use>
            </svg>
          </div>
          <div class="info-step-body">
            <h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Value', 'theme.template.page_citizenship.value' ) ); ?></h3>
            <p class="info-step-text">
              <?php echo esc_html( pera_ml_ui( 'The total value of the assets must be at least USD 400,000 at the time of purchase and valuation.', 'theme.template.page_citizenship.the_total_value_of_the_assets_must_be_at_least_usd_400_000_at_the_time_o' ) ); ?>
            </p>
          </div>
        </article>

        <!-- TITLE -->
        <article class="info-step">
          <div class="info-step-icon">
            <svg class="icon" aria-hidden="true" width="24" height="24">
              <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-tapu"></use>
            </svg>
          </div>
          <div class="info-step-body">
            <h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Title', 'theme.template.page_citizenship.title' ) ); ?></h3>
            <p class="info-step-text">
              <?php echo esc_html( pera_ml_ui( 'Each asset must have its own legal title deed (TAPU) and be properly registered at the land registry.', 'theme.template.page_citizenship.each_asset_must_have_its_own_legal_title_deed_tapu_and_be_properly_regis' ) ); ?>
            </p>
          </div>
        </article>

        <!-- MULTIPLE PROPERTY -->
        <article class="info-step">
          <div class="info-step-icon">
            <svg class="icon" aria-hidden="true" width="24" height="24">
              <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-property"></use>
            </svg>
          </div>
          <div class="info-step-body">
            <h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Multiple properties', 'theme.template.page_citizenship.multiple_properties' ) ); ?></h3>
            <p class="info-step-text">
              <?php echo esc_html( pera_ml_ui( 'You may use one or more properties to reach the minimum amount. They do not have to be in the same building or project.', 'theme.template.page_citizenship.you_may_use_one_or_more_properties_to_reach_the_minimum_amount_they_do_n' ) ); ?>
            </p>
          </div>
        </article>

        <!-- LEGAL CHARGE -->
        <article class="info-step">
          <div class="info-step-icon">
            <svg class="icon" aria-hidden="true" width="24" height="24">
              <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-hammer"></use>
            </svg>
          </div>
          <div class="info-step-body">
            <h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Legal charge', 'theme.template.page_citizenship.legal_charge' ) ); ?></h3>
            <p class="info-step-text">
              <?php echo esc_html( pera_ml_ui( 'A restriction is registered on the title deed(s) confirming the property(ies)
              cannot be sold for at least three years.', 'theme.template.page_citizenship.a_restriction_is_registered_on_the_title_deed_s_confirming_the_property_' ) ); ?>
            </p>
          </div>
        </article>

        <!-- VALUATION -->
        <article class="info-step">
          <div class="info-step-icon">
            <svg class="icon" aria-hidden="true" width="24" height="24">
              <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-valuation"></use>
            </svg>
          </div>
          <div class="info-step-body">
            <h3 class="info-step-title"><?php echo esc_html( pera_ml_ui( 'Valuation', 'theme.template.page_citizenship.valuation' ) ); ?></h3>
            <p class="info-step-text">
              <?php echo esc_html( pera_ml_ui( 'The total value must be confirmed by a valuation report issued by an SPK-licensed surveyor.
              Properties sold by GYOs are exempt.', 'theme.template.page_citizenship.the_total_value_must_be_confirmed_by_a_valuation_report_issued_by_an_spk' ) ); ?>
            </p>
          </div>
        </article>

      </div><!-- /.info-steps -->

    </div><!-- /.container -->
  </section>

  <!-- =====================================================
       WHO CAN APPLY?
       ====================================================== -->
  <section class="section section-soft" id="who-can-apply">
    <div class="container">

      <header class="section-header section-header--center">
        <h2><?php echo esc_html( pera_ml_ui( 'Who can apply under one citizenship file?', 'theme.template.page_citizenship.who_can_apply_under_one_citizenship_file' ) ); ?></h2>
        <p>
          <?php echo esc_html( pera_ml_ui( 'The Turkish Citizenship by Investment programme allows the main investor to include
          their immediate family under a single coordinated application.', 'theme.template.page_citizenship.the_turkish_citizenship_by_investment_programme_allows_the_main_investor' ) ); ?>
        </p>
      </header>

      <div class="feature-grid">

        <!-- MAIN APPLICANT -->
        <article class="feature-card">
          <div class="feature-card-header">
            <svg class="icon" width="28" height="28" aria-hidden="true">
              <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-user"></use>
            </svg>
            <h3><?php echo esc_html( pera_ml_ui( 'Main investor', 'theme.template.page_citizenship.main_investor' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p>
              <?php echo esc_html( pera_ml_ui( 'The primary applicant making the qualifying real estate investment of at least
              USD 400,000.', 'theme.template.page_citizenship.the_primary_applicant_making_the_qualifying_real_estate_investment_of_at' ) ); ?>
            </p>
          </div>
        </article>

        <!-- SPOUSE -->
        <article class="feature-card">
          <div class="feature-card-header">
            <svg class="icon" width="28" height="28" aria-hidden="true">
              <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-users"></use>
            </svg>
            <h3><?php echo esc_html( pera_ml_ui( 'Spouse', 'theme.template.page_citizenship.spouse' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p>
              <?php echo esc_html( pera_ml_ui( 'Your husband or wife can be added to the same citizenship file as a dependent
              without any extra investment requirement.', 'theme.template.page_citizenship.your_husband_or_wife_can_be_added_to_the_same_citizenship_file_as_a_depe' ) ); ?>
            </p>
          </div>
        </article>

        <!-- CHILDREN UNDER 18 -->
        <article class="feature-card">
          <div class="feature-card-header">
            <svg class="icon" width="28" height="28" aria-hidden="true">
              <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-user-add"></use>
            </svg>
            <h3><?php echo esc_html( pera_ml_ui( 'Children under 18', 'theme.template.page_citizenship.children_under_18' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p>
              <?php echo esc_html( pera_ml_ui( 'All children below 18 years of age can be included under the same application
              as long as the family relationship is documented.', 'theme.template.page_citizenship.all_children_below_18_years_of_age_can_be_included_under_the_same_applic' ) ); ?>
            </p>
          </div>
        </article>

        <!-- ADULT CHILDREN (SPECIAL NEEDS) -->
        <article class="feature-card">
          <div class="feature-card-header">
            <svg class="icon" width="28" height="28" aria-hidden="true">
              <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-shield-check"></use>
            </svg>
            <h3><?php echo esc_html( pera_ml_ui( 'Adult children (special needs)', 'theme.template.page_citizenship.adult_children_special_needs' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p>
              <?php echo esc_html( pera_ml_ui( 'Adult children with officially recognised disabilities or special needs may
              qualify as dependents, subject to supporting medical reports.', 'theme.template.page_citizenship.adult_children_with_officially_recognised_disabilities_or_special_needs_' ) ); ?>
            </p>
          </div>
        </article>

        <!-- PARENTS (SEPARATE ROUTE) -->
        <article class="feature-card">
          <div class="feature-card-header">
            <svg class="icon" width="28" height="28" aria-hidden="true">
              <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-home"></use>
            </svg>
            <h3>
              <?php echo esc_html( pera_ml_ui( 'Parents', 'theme.template.page_citizenship.parents' ) ); ?> <span style="opacity:.6; font-weight:400;"><?php echo esc_html( pera_ml_ui( '(separate route)', 'theme.template.page_citizenship.separate_route' ) ); ?></span>
            </h3>
          </div>
          <div class="feature-card-body">
            <p>
              <?php echo esc_html( pera_ml_ui( 'Parents are not included in the main citizenship file, but we can advise on
              suitable residency options or parallel applications.', 'theme.template.page_citizenship.parents_are_not_included_in_the_main_citizenship_file_but_we_can_advise_' ) ); ?>
            </p>
          </div>
        </article>

        <!-- COORDINATED FILE -->
        <article class="feature-card">
          <div class="feature-card-header">
            <svg class="icon" width="28" height="28" aria-hidden="true">
              <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-folder-duplicate"></use>
            </svg>
            <h3><?php echo esc_html( pera_ml_ui( 'One coordinated process', 'theme.template.page_citizenship.one_coordinated_process' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p>
              <?php echo esc_html( pera_ml_ui( 'Your lawyers prepare and file all family applications together, keeping the
              documentation, timelines and approvals under tight control.', 'theme.template.page_citizenship.your_lawyers_prepare_and_file_all_family_applications_together_keeping_t' ) ); ?>
            </p>
          </div>
        </article>

      </div><!-- /.feature-grid -->

    </div><!-- /.container -->
  </section>

  <!-- =====================================================
       CITIZENSHIP BENEFITS
       ====================================================== -->
  <section class="section" id="citizenship-benefits">
    <div class="container">

      <header class="section-header section-header--center">
        <h2><?php echo esc_html( pera_ml_ui( 'Benefits of Turkish Citizenship by Investment', 'theme.template.page_citizenship.benefits_of_turkish_citizenship_by_investment' ) ); ?></h2>
      </header>

      <div class="feature-grid">

        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'Fast-track citizenship', 'theme.template.page_citizenship.fast_track_citizenship' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p><?php echo esc_html( pera_ml_ui( 'Passports typically granted within 4–6 months, subject to government processing times.', 'theme.template.page_citizenship.passports_typically_granted_within_4_6_months_subject_to_government_proc' ) ); ?></p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'Affordable second passport', 'theme.template.page_citizenship.affordable_second_passport' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p><?php echo esc_html( pera_ml_ui( 'Obtain Turkish citizenship with a qualifying real estate investment from USD 400,000.', 'theme.template.page_citizenship.obtain_turkish_citizenship_with_a_qualifying_real_estate_investment_from' ) ); ?></p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'Visa-free travel', 'theme.template.page_citizenship.visa_free_travel' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p><?php echo esc_html( pera_ml_ui( 'Access to a wide network of countries with a Turkish passport, plus easy visas to key markets.', 'theme.template.page_citizenship.access_to_a_wide_network_of_countries_with_a_turkish_passport_plus_easy_' ) ); ?></p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'No residence requirement', 'theme.template.page_citizenship.no_residence_requirement' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p><?php echo esc_html( pera_ml_ui( 'No obligation to live in Türkiye before or after citizenship approval.', 'theme.template.page_citizenship.no_obligation_to_live_in_t_rkiye_before_or_after_citizenship_approval' ) ); ?></p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'Entire family eligible', 'theme.template.page_citizenship.entire_family_eligible' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p><?php echo esc_html( pera_ml_ui( 'Include your spouse and children under 18 in the same application.', 'theme.template.page_citizenship.include_your_spouse_and_children_under_18_in_the_same_application' ) ); ?></p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'Real estate investment', 'theme.template.page_citizenship.real_estate_investment' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p><?php echo esc_html( pera_ml_ui( 'Invest in income-generating, Istanbul property with long-term capital growth potential.', 'theme.template.page_citizenship.invest_in_income_generating_istanbul_property_with_long_term_capital_gro' ) ); ?></p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( '3-year holding period', 'theme.template.page_citizenship.3_year_holding_period' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p><?php echo esc_html( pera_ml_ui( 'After three years you are free to restructure, sell or reinvest your property portfolio.', 'theme.template.page_citizenship.after_three_years_you_are_free_to_restructure_sell_or_reinvest_your_prop' ) ); ?></p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'Attractive tax planning', 'theme.template.page_citizenship.attractive_tax_planning' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p><?php echo esc_html( pera_ml_ui( 'Citizenship can form part of a broader tax, residency and asset-planning strategy.', 'theme.template.page_citizenship.citizenship_can_form_part_of_a_broader_tax_residency_and_asset_planning_' ) ); ?></p>
          </div>
        </article>

      </div><!-- /.feature-grid -->

    </div><!-- /.container -->
  </section>
            
           
        

            
    <!-- =====================================================
       REQUEST A CALLBACK (FORM CTA)
       ====================================================== -->
                   
             

  <section class="section section-soft" id="citizenship-callback">
    <div class="container">

      <div class="enquiry-cta">
        <?php if ( isset( $_GET['enquiry'] ) && $_GET['enquiry'] === 'ok' ) : ?>
          <div class="alert alert-success">
            <?php echo esc_html( pera_ml_ui( 'Thank you – your enquiry has been received. We’ll contact you shortly.', 'theme.template.page_citizenship.thank_you_your_enquiry_has_been_received_we_ll_contact_you_shortly' ) ); ?>
          </div>
        <?php endif; ?>

          <header class="enquiry-cta-header">
            <h2><?php echo esc_html( pera_ml_ui( 'Request your citizenship property shortlist', 'theme.template.page_citizenship.request_your_citizenship_property_shortlist' ) ); ?></h2>
            <p>
              <?php echo esc_html( pera_ml_ui( 'Share your budget, family details and timeline, and our team will contact you with suitable Istanbul property options for your Turkish citizenship application.', 'theme.template.page_citizenship.share_your_budget_family_details_and_timeline_and_our_team_will_contact_' ) ); ?>
            </p>
          </header>


    <section id="citizenship-form" class="citizenship-form-section">
        <?php if ( isset( $_GET['enquiry'] ) ) : ?>
            <?php
            $status     = sanitize_text_field( $_GET['enquiry'] );
            $is_success = ( $status === 'ok' );
            ?>
            <div class="citizenship-alert citizenship-alert--<?php echo $is_success ? 'success' : 'error'; ?>">
                <?php if ( $is_success ) : ?>
                    <p><?php echo esc_html( pera_ml_ui( 'Thank you for your enquiry. Our team will contact you shortly.', 'theme.template.page_citizenship.thank_you_for_your_enquiry_our_team_will_contact_you_shortly' ) ); ?></p>
                <?php else : ?>
                    <p><?php echo esc_html( pera_ml_ui( 'Sorry, your message could not be sent. Please try again or contact us directly.', 'theme.template.page_citizenship.sorry_your_message_could_not_be_sent_please_try_again_or_contact_us_dire' ) ); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>


        <form
          class="enquiry-cta-form"
          method="post"
          action="<?php echo esc_url( get_permalink() ); ?>"
        >
          <?php wp_nonce_field( 'pera_citizenship_enquiry', 'pera_citizenship_nonce'); ?>
          <input type="hidden" name="pera_citizenship_action" value="1">
          <input type="hidden" name="form_start" value="<?php echo esc_attr( time() ); ?>">

          <div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
            <label for="citizenship-company"><?php echo esc_html( pera_ml_ui( 'Company', 'theme.template.page_citizenship.company' ) ); ?></label>
            <input type="text" id="citizenship-company" name="citizenship_company" value="" tabindex="-1" autocomplete="off">
          </div>

      
          <div class="enquiry-cta-grid">

            <!-- LEFT: REQUIRED INFORMATION -->
            <div class="enquiry-cta-column">
              <h3 class="enquiry-cta-subtitle"><?php echo esc_html( pera_ml_ui( 'Required information', 'theme.template.page_citizenship.required_information' ) ); ?></h3>

              <div class="cta-fieldset">
                <label class="cta-field">
                  <span class="cta-label"><?php echo esc_html( pera_ml_ui( 'Name', 'theme.template.page_citizenship.name' ) ); ?></span>
                  <input
                    type="text"
                    name="name"
                    class="cta-control"
                    placeholder="<?php echo esc_attr( pera_ml_ui( 'Your full name', 'theme.template.page_citizenship.placeholder.your_full_name' ) ); ?>"
                    required
                  >
                </label>

                <label class="cta-field">
                  <span class="cta-label"><?php echo esc_html( pera_ml_ui( 'Phone', 'theme.template.page_citizenship.phone' ) ); ?></span>
                  <input
                    type="tel"
                    name="phone"
                    class="cta-control"
                    placeholder="+90 ..."
                    required
                  >
                </label>

                <label class="cta-field">
                  <span class="cta-label"><?php echo esc_html( pera_ml_ui( 'Email', 'theme.template.page_citizenship.email' ) ); ?></span>
                  <input
                    type="email"
                    name="email"
                    class="cta-control"
                    placeholder="<?php echo esc_attr( pera_ml_ui( 'you@example.com', 'theme.template.page_citizenship.placeholder.you_example_com' ) ); ?>"
                    required
                  >
                </label>
              </div>

              <div class="cta-fieldset cta-fieldset--inline">
                <span class="cta-label cta-label--muted"><?php echo esc_html( pera_ml_ui( 'Preferred contact method', 'theme.template.page_citizenship.preferred_contact_method' ) ); ?></span>
                <div class="cta-options">
                  <label class="cta-checkbox">
                    <input type="checkbox" name="contact_method[]" value="phone">
                    <span><?php echo esc_html( pera_ml_ui( 'Phone', 'theme.template.page_citizenship.phone' ) ); ?></span>
                  </label>
                  <label class="cta-checkbox">
                    <input type="checkbox" name="contact_method[]" value="email">
                    <span><?php echo esc_html( pera_ml_ui( 'Email', 'theme.template.page_citizenship.email' ) ); ?></span>
                  </label>
                  <label class="cta-checkbox">
                    <input type="checkbox" name="contact_method[]" value="whatsapp">
                    <span><?php echo esc_html( pera_ml_ui( 'WhatsApp', 'theme.template.page_citizenship.whatsapp' ) ); ?></span>
                  </label>
                </div>
              </div>
            </div>

            <!-- RIGHT: ADDITIONAL INFORMATION -->
            <div class="enquiry-cta-column">
              <h3 class="enquiry-cta-subtitle"><?php echo esc_html( pera_ml_ui( 'Additional information', 'theme.template.page_citizenship.additional_information' ) ); ?></h3>

              <div class="cta-fieldset">
                <label class="cta-field">
                  <span class="cta-label"><?php echo esc_html( pera_ml_ui( 'Type of enquiry', 'theme.template.page_citizenship.type_of_enquiry' ) ); ?></span>
                  <select name="enquiry_type" class="cta-control">
                    <option value="general"><?php echo esc_html( pera_ml_ui( 'General enquiry', 'theme.template.page_citizenship.general_enquiry' ) ); ?></option>
                    <option value="citizenship-only"><?php echo esc_html( pera_ml_ui( 'Citizenship only', 'theme.template.page_citizenship.citizenship_only' ) ); ?></option>
                    <option value="citizenship-property"><?php echo esc_html( pera_ml_ui( 'Citizenship & property investment', 'theme.template.page_citizenship.citizenship_and_property_investment' ) ); ?></option>
                    <option value="consultation"><?php echo esc_html( pera_ml_ui( 'Schedule a video consultation', 'theme.template.page_citizenship.schedule_a_video_consultation' ) ); ?></option>
                  </select>
                </label>

                <label class="cta-field">
                  <span class="cta-label"><?php echo esc_html( pera_ml_ui( 'Family members', 'theme.template.page_citizenship.family_members' ) ); ?></span>
                  <input
                    type="text"
                    name="family"
                    class="cta-control"
                    placeholder="<?php echo esc_attr( pera_ml_ui( 'Number of applicants, ages of children, etc.', 'theme.template.page_citizenship.placeholder.number_of_applicants_ages_of_children_etc' ) ); ?>"
                  >
                </label>

                <label class="cta-field">
                  <span class="cta-label"><?php echo esc_html( pera_ml_ui( 'Questions or comments', 'theme.template.page_citizenship.questions_or_comments' ) ); ?></span>
                  <textarea
                    name="message"
                    rows="3"
                    class="cta-control"
                    placeholder="<?php echo esc_attr( pera_ml_ui( 'Tell us a little about your situation or preferred timeline.', 'theme.template.page_citizenship.placeholder.tell_us_a_little_about_your_situation_or_preferred_timeline' ) ); ?>"
                  ></textarea>
                </label>
              </div>
            </div>

          </div><!-- /.enquiry-cta-grid -->

          <!-- CONSENT + SUBMIT -->
          <div class="enquiry-cta-footer">
            <label class="cta-checkbox">
              <input type="checkbox" name="policy" required>
              <span>
                <?php echo esc_html( pera_ml_ui( 'I agree to the terms of the', 'theme.template.page_citizenship.i_agree_to_the_terms_of_the' ) ); ?>
                <a href="/privacy-policy/" target="_blank" rel="noopener"><?php echo esc_html( pera_ml_ui( 'Privacy Policy', 'theme.template.page_citizenship.privacy_policy' ) ); ?></a>.
              </span>
            </label>

            <?php $turnstile_site_key = defined( 'PERA_TURNSTILE_SITE_KEY' ) ? sanitize_text_field( (string) PERA_TURNSTILE_SITE_KEY ) : ''; ?>
            <?php if ( $turnstile_site_key !== '' ) : ?>
              <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
              <div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $turnstile_site_key ); ?>"></div>
            <?php endif; ?>

            <button type="submit" class="btn btn--ghost btn--green">
              <?php echo esc_html( pera_ml_ui( 'Send my shortlist request', 'theme.template.page_citizenship.send_my_shortlist_request' ) ); ?>
            </button>
          </div>

        </form>
        </section>

      </div><!-- /.enquiry-cta -->
    </div><!-- /.container -->
  </section>



  <!-- =====================================================
       WHY CHOOSE PERA
       ====================================================== -->
  <section class="section">
    <div class="container">

      <header class="section-header section-header--center">
        <h2><?php echo esc_html( pera_ml_ui( 'Why Choose Pera Property for Turkish Citizenship by Investment?', 'theme.template.page_citizenship.why_choose_pera_property_for_turkish_citizenship_by_investment' ) ); ?></h2>
        <p>
          <?php echo esc_html( pera_ml_ui( 'A specialist Istanbul real estate agency helping international clients complete the Turkey citizenship application process with clear guidance from property selection to Turkish passport by investment approval.', 'theme.template.page_citizenship.a_specialist_istanbul_real_estate_agency_helping_international_clients_c' ) ); ?>
        </p>
      </header>

      <div class="feature-grid">

        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'Specialist CBI portfolio', 'theme.template.page_citizenship.specialist_cbi_portfolio' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p>
              <?php echo esc_html( pera_ml_ui( 'We focus on modern, well-located Istanbul developments that appeal
              both to citizenship investors and future tenants.', 'theme.template.page_citizenship.we_focus_on_modern_well_located_istanbul_developments_that_appeal_both_t' ) ); ?>
            </p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'Transparent fees', 'theme.template.page_citizenship.transparent_fees' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p>
              <?php echo esc_html( pera_ml_ui( 'Clear, upfront fee structures for both real estate and legal work,
              with no hidden extras during the process.', 'theme.template.page_citizenship.clear_upfront_fee_structures_for_both_real_estate_and_legal_work_with_no' ) ); ?>
            </p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'Local + international team', 'theme.template.page_citizenship.local_international_team' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p>
              <?php echo esc_html( pera_ml_ui( 'English and Turkish-speaking consultants based in Istanbul,
              backed by experienced immigration lawyers.', 'theme.template.page_citizenship.english_and_turkish_speaking_consultants_based_in_istanbul_backed_by_exp' ) ); ?>
            </p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'End-to-end project management', 'theme.template.page_citizenship.end_to_end_project_management' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p>
              <?php echo esc_html( pera_ml_ui( 'One point of contact coordinating developers, valuers, banks and
              lawyers so your application stays on track.', 'theme.template.page_citizenship.one_point_of_contact_coordinating_developers_valuers_banks_and_lawyers_s' ) ); ?>
            </p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'Istanbul market insight', 'theme.template.page_citizenship.istanbul_market_insight' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p>
              <?php echo esc_html( pera_ml_ui( 'Advice on which districts hold long-term value, rental demand
              and resale liquidity once your lock-in period ends.', 'theme.template.page_citizenship.advice_on_which_districts_hold_long_term_value_rental_demand_and_resale_' ) ); ?>
            </p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'Long-term relationship', 'theme.template.page_citizenship.long_term_relationship' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p>
              <?php echo esc_html( pera_ml_ui( 'Ongoing support with rentals, resale or portfolio restructuring
              once your citizenship has been granted.', 'theme.template.page_citizenship.ongoing_support_with_rentals_resale_or_portfolio_restructuring_once_your' ) ); ?>
            </p>
          </div>
        </article>

      </div><!-- /.feature-grid -->

    </div><!-- /.container -->
  </section>


<!-- =====================================================
     CITIZENSHIP TIMELINE
     ====================================================== -->
<section class="section section-soft" id="citizenship-timeline">
  <div class="container">

    <header class="section-header section-header--center">
      <h2><?php echo esc_html( pera_ml_ui( 'Turkish citizenship acquisition timeline', 'theme.template.page_citizenship.turkish_citizenship_acquisition_timeline' ) ); ?></h2>
      <p>
        <?php echo esc_html( pera_ml_ui( 'An indicative timeline from your first consultation with Pera to receiving
        your Turkish passports, assuming a complete and correctly prepared file.', 'theme.template.page_citizenship.an_indicative_timeline_from_your_first_consultation_with_pera_to_receivi' ) ); ?>
      </p>
    </header>

    <ol class="timeline">
      <!-- 1 -->
      <li class="timeline-step">
        <div class="timeline-side">
          <span class="timeline-duration"><?php echo esc_html( pera_ml_ui( '3–5 days', 'theme.template.page_citizenship.3_5_days' ) ); ?></span>
          <span class="timeline-phase"><?php echo esc_html( pera_ml_ui( 'Preparation', 'theme.template.page_citizenship.preparation' ) ); ?></span>
        </div>

        <div class="timeline-marker">
          <span class="timeline-number">1</span>
        </div>

        <div class="timeline-body">
          <h3><?php echo esc_html( pera_ml_ui( 'Consultation & planning', 'theme.template.page_citizenship.consultation_and_planning' ) ); ?></h3>
          <p>
            <?php echo esc_html( pera_ml_ui( 'We assess your family situation, timeline and budget, and explain the latest programme rules and documentation requirements.', 'theme.template.page_citizenship.we_assess_your_family_situation_timeline_and_budget_and_explain_the_late' ) ); ?>
          </p>
        </div>
      </li>

      <!-- 2 -->
      <li class="timeline-step">
        <div class="timeline-side">
          <span class="timeline-duration"><?php echo esc_html( pera_ml_ui( '2–4 weeks', 'theme.template.page_citizenship.2_4_weeks' ) ); ?></span>
          <span class="timeline-phase"><?php echo esc_html( pera_ml_ui( 'Document collection', 'theme.template.page_citizenship.document_collection' ) ); ?></span>
        </div>

        <div class="timeline-marker">
          <span class="timeline-number">2</span>
        </div>

        <div class="timeline-body">
          <h3><?php echo esc_html( pera_ml_ui( 'Prepare documents', 'theme.template.page_citizenship.prepare_documents' ) ); ?></h3>
          <p>
            <?php echo esc_html( pera_ml_ui( 'Our lawyers provide a detailed checklist and help you gather passports,
            civil documents, photos, powers of attorney and any required translations
            and apostilles.', 'theme.template.page_citizenship.our_lawyers_provide_a_detailed_checklist_and_help_you_gather_passports_c' ) ); ?>
          </p>
        </div>
      </li>

      <!-- 3 -->
      <li class="timeline-step">
        <div class="timeline-side">
          <span class="timeline-duration"><?php echo esc_html( pera_ml_ui( '1–2 weeks', 'theme.template.page_citizenship.1_2_weeks' ) ); ?></span>
          <span class="timeline-phase"><?php echo esc_html( pera_ml_ui( 'Investment', 'theme.template.page_citizenship.investment' ) ); ?></span>
        </div>

        <div class="timeline-marker">
          <span class="timeline-number">3</span>
        </div>

        <div class="timeline-body">
          <h3><?php echo esc_html( pera_ml_ui( 'Select & reserve property', 'theme.template.page_citizenship.select_and_reserve_property' ) ); ?></h3>
          <p>
            <?php echo esc_html( pera_ml_ui( 'Together we shortlist CBI-eligible projects in Istanbul, arrange viewings
            (in person or remote) and reserve your chosen units with the developer.', 'theme.template.page_citizenship.together_we_shortlist_cbi_eligible_projects_in_istanbul_arrange_viewings' ) ); ?>
          </p>
        </div>
      </li>

      <!-- 4 -->
      <li class="timeline-step">
        <div class="timeline-side">
          <span class="timeline-duration"><?php echo esc_html( pera_ml_ui( '2–4 weeks', 'theme.template.page_citizenship.2_4_weeks' ) ); ?></span>
          <span class="timeline-phase"><?php echo esc_html( pera_ml_ui( 'Completion', 'theme.template.page_citizenship.completion' ) ); ?></span>
        </div>

        <div class="timeline-marker">
          <span class="timeline-number">4</span>
        </div>

        <div class="timeline-body">
          <h3><?php echo esc_html( pera_ml_ui( 'Complete investment & title deed', 'theme.template.page_citizenship.complete_investment_and_title_deed' ) ); ?></h3>
          <p>
            <?php echo esc_html( pera_ml_ui( 'You transfer funds, we obtain the valuation report and our lawyers complete title deed
            registrations with the 3-year no-sale restriction recorded on the TAPU.', 'theme.template.page_citizenship.you_transfer_funds_we_obtain_the_valuation_report_and_our_lawyers_comple' ) ); ?>
          </p>
        </div>
      </li>

      <!-- 5 -->
      <li class="timeline-step">
        <div class="timeline-side">
          <span class="timeline-duration"><?php echo esc_html( pera_ml_ui( '4–8 weeks', 'theme.template.page_citizenship.4_8_weeks' ) ); ?></span>
          <span class="timeline-phase"><?php echo esc_html( pera_ml_ui( 'Processing', 'theme.template.page_citizenship.processing' ) ); ?></span>
        </div>

        <div class="timeline-marker">
          <span class="timeline-number">5</span>
        </div>

        <div class="timeline-body">
          <h3><?php echo esc_html( pera_ml_ui( 'Residence permits & citizenship filing', 'theme.template.page_citizenship.residence_permits_and_citizenship_filing' ) ); ?></h3>
          <p>
            <?php echo esc_html( pera_ml_ui( 'Your family’s residence permits and citizenship files are submitted.
            Your lawyers track the application and respond to any requests from
            the authorities.', 'theme.template.page_citizenship.your_family_s_residence_permits_and_citizenship_files_are_submitted_your' ) ); ?>
          </p>
        </div>
      </li>

      <!-- 6 -->
      <li class="timeline-step">
        <div class="timeline-side">
          <span class="timeline-duration"><?php echo esc_html( pera_ml_ui( 'Approx. 4–6 months total', 'theme.template.page_citizenship.approx_4_6_months_total' ) ); ?></span>
          <span class="timeline-phase"><?php echo esc_html( pera_ml_ui( 'Approval', 'theme.template.page_citizenship.approval' ) ); ?></span>
        </div>

        <div class="timeline-marker">
          <span class="timeline-number">6</span>
        </div>

        <div class="timeline-body">
          <h3><?php echo esc_html( pera_ml_ui( 'Receive Turkish passports', 'theme.template.page_citizenship.receive_turkish_passports' ) ); ?></h3>
          <p>
            <?php echo esc_html( pera_ml_ui( 'Once approved, ID cards and passports are issued for all successful
            applicants, either in Türkiye or via your local consulate.', 'theme.template.page_citizenship.once_approved_id_cards_and_passports_are_issued_for_all_successful_appli' ) ); ?>
          </p>
        </div>
      </li>
    </ol>

  </div>
</section>

<!-- =====================================================
     FAST-TRACK PROCESS UPDATE
     ====================================================== -->
<section class="section">
  <div class="container">
    <header class="section-header">
      <h2><?php echo esc_html( pera_ml_ui( 'Fast-Track Investor Residency and Citizenship Process', 'theme.template.page_citizenship.fast_track_investor_residency_and_citizenship_process' ) ); ?></h2>
      <p>
        <?php echo esc_html( pera_ml_ui( 'Turkey has introduced a fast-track option for investor residency applications,
        allowing qualified applicants to complete both residency and citizenship
        application steps in a significantly shorter timeframe.', 'theme.template.page_citizenship.turkey_has_introduced_a_fast_track_option_for_investor_residency_applica' ) ); ?>
      </p>
    </header>

    <h3><?php echo esc_html( pera_ml_ui( 'Standard Process:', 'theme.template.page_citizenship.standard_process' ) ); ?></h3>
    <ul class="checklist checklist--circle">
      <li>
        <?php echo esc_html( pera_ml_ui( 'Residency application and approval', 'theme.template.page_citizenship.residency_application_and_approval' ) ); ?>
      </li>
      <li>
        <?php echo esc_html( pera_ml_ui( 'Separate citizenship application', 'theme.template.page_citizenship.separate_citizenship_application' ) ); ?>
      </li>
      <li>
        <?php echo esc_html( pera_ml_ui( 'Multiple steps and timelines', 'theme.template.page_citizenship.multiple_steps_and_timelines' ) ); ?>
      </li>
    </ul>

    <h3><?php echo esc_html( pera_ml_ui( 'Fast-Track Option:', 'theme.template.page_citizenship.fast_track_option' ) ); ?></h3>
    <ul class="checklist checklist--circle">
      <li>
        <?php echo esc_html( pera_ml_ui( 'Residency application, biometrics, and citizenship submission can be completed in one visit', 'theme.template.page_citizenship.residency_application_biometrics_and_citizenship_submission_can_be_compl' ) ); ?>
      </li>
      <li>
        <?php echo esc_html( pera_ml_ui( 'Biometric processing completed on arrival', 'theme.template.page_citizenship.biometric_processing_completed_on_arrival' ) ); ?>
      </li>
      <li>
        <?php echo esc_html( pera_ml_ui( 'Citizenship submission may be completed the same day', 'theme.template.page_citizenship.citizenship_submission_may_be_completed_the_same_day' ) ); ?>
      </li>
      <li>
        <?php echo esc_html( pera_ml_ui( 'Optional expedited service', 'theme.template.page_citizenship.optional_expedited_service' ) ); ?>
      </li>
    </ul>
  </div>
</section>

<!-- =====================================================
     DOCUMENTS REQUIRED
     ====================================================== -->
<section class="section section-soft" id="citizenship-documents">
  <div class="container">

    <header class="section-header section-header--center">
      <h2><?php echo esc_html( pera_ml_ui( 'Documents required', 'theme.template.page_citizenship.documents_required' ) ); ?></h2>
      <p>
        <?php echo esc_html( pera_ml_ui( 'Key documents typically needed for a complete Turkish citizenship
        application. Your lawyer will confirm the exact list based on your
        family situation and nationality.', 'theme.template.page_citizenship.key_documents_typically_needed_for_a_complete_turkish_citizenship_applic' ) ); ?>
      </p>
    </header>

    <div class="docs-list">

      <!-- 1. Passport -->
      <details class="doc-item">
        <summary>
          <span class="doc-title"><?php echo esc_html( pera_ml_ui( 'Valid passport', 'theme.template.page_citizenship.valid_passport' ) ); ?></span>
          <span class="doc-icon" aria-hidden="true"></span>
        </summary>
        <div class="doc-body">
          <p><?php echo esc_html( pera_ml_ui( 'A valid passport or recognised travel document for each applicant.', 'theme.template.page_citizenship.a_valid_passport_or_recognised_travel_document_for_each_applicant' ) ); ?></p>
        </div>
      </details>

      <!-- 2. Marriage / marital status -->
      <details class="doc-item">
        <summary>
          <span class="doc-title"><?php echo esc_html( pera_ml_ui( 'Marriage certificate / marital status', 'theme.template.page_citizenship.marriage_certificate_marital_status' ) ); ?></span>
          <span class="doc-icon" aria-hidden="true"></span>
        </summary>
        <div class="doc-body">
          <p>
            <?php echo esc_html( pera_ml_ui( 'If married, a marriage certificate. If divorced, a divorce certificate.
            If never married, an official certificate of single status.', 'theme.template.page_citizenship.if_married_a_marriage_certificate_if_divorced_a_divorce_certificate_if_n' ) ); ?>
          </p>
        </div>
      </details>

      <!-- 3. Birth certificate -->
      <details class="doc-item">
        <summary>
          <span class="doc-title"><?php echo esc_html( pera_ml_ui( 'Birth certificates', 'theme.template.page_citizenship.birth_certificates' ) ); ?></span>
          <span class="doc-icon" aria-hidden="true"></span>
        </summary>
        <div class="doc-body">
          <p>
            <?php echo esc_html( pera_ml_ui( 'Birth certificates for all applicants. If unavailable, a consular
            affidavit confirming your place and date of birth may be needed.', 'theme.template.page_citizenship.birth_certificates_for_all_applicants_if_unavailable_a_consular_affidavi' ) ); ?>
          </p>
        </div>
      </details>

      <!-- 4. Spouse & children -->
      <details class="doc-item">
        <summary>
          <span class="doc-title"><?php echo esc_html( pera_ml_ui( 'Details of spouse and children', 'theme.template.page_citizenship.details_of_spouse_and_children' ) ); ?></span>
          <span class="doc-icon" aria-hidden="true"></span>
        </summary>
        <div class="doc-body">
          <p>
            <?php echo esc_html( pera_ml_ui( 'Passports and birth certificates for your spouse and all children
            included in the application.', 'theme.template.page_citizenship.passports_and_birth_certificates_for_your_spouse_and_all_children_includ' ) ); ?>
          </p>
        </div>
      </details>

      <!-- 5. Turkish tax number -->
      <details class="doc-item">
        <summary>
          <span class="doc-title"><?php echo esc_html( pera_ml_ui( 'Turkish tax number', 'theme.template.page_citizenship.turkish_tax_number' ) ); ?></span>
          <span class="doc-icon" aria-hidden="true"></span>
        </summary>
        <div class="doc-body">
          <p>
            <?php echo esc_html( pera_ml_ui( 'A Turkish tax number for the main applicant, issued by any tax
            office or online through the Revenue Administration.', 'theme.template.page_citizenship.a_turkish_tax_number_for_the_main_applicant_issued_by_any_tax_office_or_' ) ); ?>
          </p>
        </div>
      </details>

      <!-- 6. Appraisal reports -->
      <details class="doc-item">
        <summary>
          <span class="doc-title"><?php echo esc_html( pera_ml_ui( 'Appraisal reports', 'theme.template.page_citizenship.appraisal_reports' ) ); ?></span>
          <span class="doc-icon" aria-hidden="true"></span>
        </summary>
        <div class="doc-body">
          <p>
            <?php echo esc_html( pera_ml_ui( 'Each property must be
            independently valued by an SPK-licensed surveyor to confirm that
            the total investment meets the legal threshold.', 'theme.template.page_citizenship.each_property_must_be_independently_valued_by_an_spk_licensed_surveyor_t' ) ); ?>
          </p>
        </div>
      </details>

      <!-- 7. Title deeds -->
      <details class="doc-item">
        <summary>
          <span class="doc-title"><?php echo esc_html( pera_ml_ui( 'Title deeds of the purchased assets', 'theme.template.page_citizenship.title_deeds_of_the_purchased_assets' ) ); ?></span>
          <span class="doc-icon" aria-hidden="true"></span>
        </summary>
        <div class="doc-body">
          <p>
            <?php echo esc_html( pera_ml_ui( 'Title deeds (TAPU) for each qualifying asset, with the three-year
            no-sale restriction registered where required.', 'theme.template.page_citizenship.title_deeds_tapu_for_each_qualifying_asset_with_the_three_year_no_sale_r' ) ); ?>
          </p>
        </div>
      </details>

      <!-- 8. Confirmation of investment -->
      <details class="doc-item">
        <summary>
          <span class="doc-title"><?php echo esc_html( pera_ml_ui( 'Confirmation of investment', 'theme.template.page_citizenship.confirmation_of_investment' ) ); ?></span>
          <span class="doc-icon" aria-hidden="true"></span>
        </summary>
        <div class="doc-body">
          <p>
            <?php echo esc_html( pera_ml_ui( 'Official confirmation from the land registry or relevant authority
            that the investment has been completed in line with the citizenship rules.', 'theme.template.page_citizenship.official_confirmation_from_the_land_registry_or_relevant_authority_that_' ) ); ?>
          </p>
        </div>
      </details>

      <!-- 9. Authentication / legalisation -->
      <details class="doc-item">
        <summary>
          <span class="doc-title"><?php echo esc_html( pera_ml_ui( 'Authentication & legalisation', 'theme.template.page_citizenship.authentication_and_legalisation' ) ); ?></span>
          <span class="doc-icon" aria-hidden="true"></span>
        </summary>
        <div class="doc-body">
          <p>
            <?php echo esc_html( pera_ml_ui( 'Certain documents (birth, marriage, divorce certificates or
            single-status confirmations) must be apostilled or otherwise
            legalised and officially translated into Turkish. Your lawyer will
            advise on the exact process for your country.', 'theme.template.page_citizenship.certain_documents_birth_marriage_divorce_certificates_or_single_status_c' ) ); ?>
          </p>
        </div>
      </details>

    </div><!-- /.docs-list -->

  </div>
</section>

<!-- =====================================================
     LEGAL AND COMPLIANCE CHECKS
     ====================================================== -->
<section class="section" id="citizenship-compliance-checks">
  <div class="container">
    <header class="section-header section-header--center">
      <h2><?php echo esc_html( pera_ml_ui( 'Legal and compliance checks before you buy', 'theme.template.page_citizenship.legal_and_compliance_checks_before_you_buy' ) ); ?></h2>
      <p>
        <?php echo esc_html( pera_ml_ui( 'Buying property for Turkish citizenship is not the same as buying a normal investment apartment. Before a property is treated as citizenship-eligible, the legal, title deed, valuation, payment and conformity requirements must be checked carefully.', 'theme.template.page_citizenship.buying_property_for_turkish_citizenship_is_not_the_same_as_buying_a_norm' ) ); ?>
      </p>
    </header>

    <div class="content-panel-box citizenship-advisory-panel">
      <div class="citizenship-advisory-copy">
        <p>
          <?php echo esc_html( pera_ml_ui( 'Pera Property coordinates the property search and transaction process with licensed Turkish legal partners so that investors understand the requirements before committing to a purchase. Eligibility should be confirmed before funds are committed, especially where the property, seller, payment route or valuation needs further review.', 'theme.template.page_citizenship.pera_property_coordinates_the_property_search_and_transaction_process_wi' ) ); ?>
        </p>
        <p>
          <?php echo esc_html( pera_ml_ui( 'These checks typically include an independent Turkish lawyer review, title deed checks, seller and property eligibility checks, official valuation report review, DAB / foreign currency compliance, land registry annotation for the mandatory 3-year holding period, and the Certificate of Conformity stage.', 'theme.template.page_citizenship.these_checks_typically_include_an_independent_turkish_lawyer_review_titl' ) ); ?>
        </p>
        <p>
          <?php echo esc_html( pera_ml_ui( 'If you are comparing', 'theme.template.page_citizenship.if_you_are_comparing' ) ); ?> <a href="<?php echo esc_url( home_url( '/property/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'citizenship-eligible properties in Istanbul', 'theme.template.page_citizenship.citizenship_eligible_properties_in_istanbul' ) ); ?></a><?php echo esc_html( pera_ml_ui( ', our team can explain which items need legal confirmation before reservation, title deed transfer and citizenship filing.', 'theme.template.page_citizenship.our_team_can_explain_which_items_need_legal_confirmation_before_reservat' ) ); ?>
        </p>
      </div>

      <div class="content-note" role="note" aria-label="<?php echo esc_attr( pera_ml_ui( 'Legal services disclaimer', 'theme.template.page_citizenship.aria_label.legal_services_disclaimer' ) ); ?>">
        <strong><?php echo esc_html( pera_ml_ui( 'Pera Property is not a law firm.', 'theme.template.page_citizenship.pera_property_is_not_a_law_firm' ) ); ?></strong> <?php echo esc_html( pera_ml_ui( 'Citizenship applications are handled with licensed Turkish legal partners. Pera Property checks eligibility of all property which is claimed to be eligible before offering it to our clients. We strongly advise no payments are made before eligibility, particularly valuation reports, are checked.', 'theme.template.page_citizenship.citizenship_applications_are_handled_with_licensed_turkish_legal_partner' ) ); ?>
      </div>
    </div>
  </div>
</section>

<!-- =====================================================
     FIT ASSESSMENT
     ====================================================== -->
<section class="section section-soft" id="is-turkish-citizenship-right-for-you">
  <div class="container">
    <header class="section-header section-header--center">
      <h2><?php echo esc_html( pera_ml_ui( 'Is Turkish Citizenship by Investment right for you?', 'theme.template.page_citizenship.is_turkish_citizenship_by_investment_right_for_you' ) ); ?></h2>
      <p>
        <?php echo esc_html( pera_ml_ui( 'Turkish citizenship by investment can be a strong fit for some buyers, but it is not the right route for every objective. The best decision depends on your family priorities, documentation position, exit timeline and appetite for real estate ownership.', 'theme.template.page_citizenship.turkish_citizenship_by_investment_can_be_a_strong_fit_for_some_buyers_bu' ) ); ?>
      </p>
    </header>

    <div class="feature-grid citizenship-fit-grid">
      <article class="feature-card citizenship-fit-card">
        <div class="feature-card-header">
          <h3><?php echo esc_html( pera_ml_ui( 'Good fit for', 'theme.template.page_citizenship.good_fit_for' ) ); ?></h3>
        </div>
        <div class="feature-card-body">
          <ul class="checklist">
            <li><?php echo esc_html( pera_ml_ui( 'Families seeking a second citizenship', 'theme.template.page_citizenship.families_seeking_a_second_citizenship' ) ); ?></li>
            <li><?php echo esc_html( pera_ml_ui( 'Investors who want a tangible real estate asset', 'theme.template.page_citizenship.investors_who_want_a_tangible_real_estate_asset' ) ); ?></li>
            <li><?php echo esc_html( pera_ml_ui( 'Buyers focused on Istanbul liquidity', 'theme.template.page_citizenship.buyers_focused_on_istanbul_liquidity' ) ); ?></li>
            <li><?php echo esc_html( pera_ml_ui( 'Applicants who can hold the property for at least 3 years', 'theme.template.page_citizenship.applicants_who_can_hold_the_property_for_at_least_3_years' ) ); ?></li>
            <li><?php echo esc_html( pera_ml_ui( 'Investors who want a relatively fast citizenship route', 'theme.template.page_citizenship.investors_who_want_a_relatively_fast_citizenship_route' ) ); ?></li>
          </ul>
        </div>
      </article>

      <article class="feature-card citizenship-fit-card">
        <div class="feature-card-header">
          <h3><?php echo esc_html( pera_ml_ui( 'Not ideal for', 'theme.template.page_citizenship.not_ideal_for' ) ); ?></h3>
        </div>
        <div class="feature-card-body">
          <ul class="checklist checklist--cross">
            <li><?php echo esc_html( pera_ml_ui( 'Buyers who specifically need visa-free US or Schengen access', 'theme.template.page_citizenship.buyers_who_specifically_need_visa_free_us_or_schengen_access' ) ); ?></li>
            <li><?php echo esc_html( pera_ml_ui( 'Investors who want immediate resale flexibility', 'theme.template.page_citizenship.investors_who_want_immediate_resale_flexibility' ) ); ?></li>
            <li><?php echo esc_html( pera_ml_ui( 'Applicants unwilling to complete bank, legal and source-of-funds documentation', 'theme.template.page_citizenship.applicants_unwilling_to_complete_bank_legal_and_source_of_funds_document' ) ); ?></li>
            <li><?php echo esc_html( pera_ml_ui( 'Buyers focused only on the cheapest qualifying property rather than eligibility, location and resale potential', 'theme.template.page_citizenship.buyers_focused_only_on_the_cheapest_qualifying_property_rather_than_elig' ) ); ?></li>
          </ul>
        </div>
      </article>
    </div>
  </div>
</section>

<!-- =====================================================
     INVESTMENT ROUTE COMPARISON
     ====================================================== -->
<section class="section" id="turkish-citizenship-investment-routes">
  <div class="container">
    <header class="section-header section-header--center">
      <h2><?php echo esc_html( pera_ml_ui( 'Turkish citizenship investment routes compared', 'theme.template.page_citizenship.turkish_citizenship_investment_routes_compared' ) ); ?></h2>
      <p>
        <?php echo esc_html( pera_ml_ui( 'The real estate route is the most common option for many international investors, but it is not the only Turkish citizenship by investment route. The best route depends on your investment objective, documentation position, timeline and appetite for property ownership.', 'theme.template.page_citizenship.the_real_estate_route_is_the_most_common_option_for_many_international_i' ) ); ?>
      </p>
    </header>

    <div class="citizenship-table-wrap" role="region" aria-label="<?php echo esc_attr( pera_ml_ui( 'Turkish citizenship investment route comparison', 'theme.template.page_citizenship.aria_label.turkish_citizenship_investment_route_comparison' ) ); ?>" tabindex="0">
      <table class="citizenship-route-table">
        <thead>
          <tr>
            <th scope="col"><?php echo esc_html( pera_ml_ui( 'Route', 'theme.template.page_citizenship.route' ) ); ?></th>
            <th scope="col"><?php echo esc_html( pera_ml_ui( 'Minimum investment', 'theme.template.page_citizenship.minimum_investment' ) ); ?></th>
            <th scope="col"><?php echo esc_html( pera_ml_ui( 'Common use case', 'theme.template.page_citizenship.common_use_case' ) ); ?></th>
            <th scope="col"><?php echo esc_html( pera_ml_ui( 'Notes', 'theme.template.page_citizenship.notes' ) ); ?></th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <th scope="row"><?php echo esc_html( pera_ml_ui( 'Real estate', 'theme.template.page_citizenship.real_estate' ) ); ?></th>
            <td><?php echo esc_html( pera_ml_ui( 'USD 400,000', 'theme.template.page_citizenship.usd_400_000' ) ); ?></td>
            <td><?php echo esc_html( pera_ml_ui( 'Most popular route', 'theme.template.page_citizenship.most_popular_route' ) ); ?></td>
            <td><?php echo esc_html( pera_ml_ui( 'Property must usually be held for at least 3 years', 'theme.template.page_citizenship.property_must_usually_be_held_for_at_least_3_years' ) ); ?></td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html( pera_ml_ui( 'Bank deposit', 'theme.template.page_citizenship.bank_deposit' ) ); ?></th>
            <td><?php echo esc_html( pera_ml_ui( 'USD 500,000', 'theme.template.page_citizenship.usd_500_000' ) ); ?></td>
            <td><?php echo esc_html( pera_ml_ui( 'Capital preservation', 'theme.template.page_citizenship.capital_preservation' ) ); ?></td>
            <td><?php echo esc_html( pera_ml_ui( 'Rules, bank requirements and documentation process differ', 'theme.template.page_citizenship.rules_bank_requirements_and_documentation_process_differ' ) ); ?></td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html( pera_ml_ui( 'Government bonds', 'theme.template.page_citizenship.government_bonds' ) ); ?></th>
            <td><?php echo esc_html( pera_ml_ui( 'USD 500,000', 'theme.template.page_citizenship.usd_500_000' ) ); ?></td>
            <td><?php echo esc_html( pera_ml_ui( 'Passive investment', 'theme.template.page_citizenship.passive_investment' ) ); ?></td>
            <td><?php echo esc_html( pera_ml_ui( 'Less exposure to property market performance', 'theme.template.page_citizenship.less_exposure_to_property_market_performance' ) ); ?></td>
          </tr>
          <tr>
            <th scope="row"><?php echo esc_html( pera_ml_ui( 'Business or employment routes', 'theme.template.page_citizenship.business_or_employment_routes' ) ); ?></th>
            <td><?php echo esc_html( pera_ml_ui( 'Varies', 'theme.template.page_citizenship.varies' ) ); ?></td>
            <td><?php echo esc_html( pera_ml_ui( 'Operational investors', 'theme.template.page_citizenship.operational_investors' ) ); ?></td>
            <td><?php echo esc_html( pera_ml_ui( 'Usually more complex and case-specific', 'theme.template.page_citizenship.usually_more_complex_and_case_specific' ) ); ?></td>
          </tr>
        </tbody>
      </table>
    </div>

    <p class="citizenship-route-note">
      <?php echo esc_html( pera_ml_ui( 'Investment thresholds and application practice can change. Requirements should be checked with a licensed Turkish legal adviser before making an investment decision. To discuss your objectives,', 'theme.template.page_citizenship.investment_thresholds_and_application_practice_can_change_requirements_s' ) ); ?> <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'speak to Pera Property', 'theme.template.page_citizenship.speak_to_pera_property' ) ); ?></a>.
    </p>
  </div>
</section>

<?php get_template_part( 'parts/citizenship-guide-posts' ); ?>

<!-- =====================================================
     FREQUENTLY ASKED QUESTIONS
     ====================================================== -->
<?php get_template_part( 'partials/faq', 'citizenship' ); ?>




  <!-- =====================================================
       FINAL CTA (EMAIL / WHATSAPP)
       ====================================================== -->
  <section class="section cta" id="citizenship-enquiry">
    <div class="container">
      <h2><?php echo esc_html( pera_ml_ui( 'Ready to explore Turkish citizenship by investment?', 'theme.template.page_citizenship.ready_to_explore_turkish_citizenship_by_investment' ) ); ?></h2>
      <p>
        <?php echo esc_html( pera_ml_ui( 'Share your details and one of our consultants will contact you to discuss
        your plans and outline the best options for your family.', 'theme.template.page_citizenship.share_your_details_and_one_of_our_consultants_will_contact_you_to_discus' ) ); ?>
      </p>

      <div class="hero-actions">
        <a
          href="#citizenship-callback"
          class="btn btn--solid btn--green"
        >
          <?php echo esc_html( pera_ml_ui( 'Contact our citizenship team', 'theme.template.page_citizenship.contact_our_citizenship_team' ) ); ?>
        </a>
        <a
          href="<?php echo esc_url( pera_get_whatsapp_url( pera_ml_ui( 'Hello Pera Property, I\'m interested in Turkish citizenship by investment.', 'theme.template.page_citizenship.whatsapp_prefill' ) ) ); ?>"
          class="btn btn--ghost btn--green"
          data-whatsapp="1"
          data-whatsapp-type="citizenship_cta"
          data-track-channel="whatsapp"
          data-track-intent="high"
          data-track-source="template"
          data-track-context="citizenship_page"
          data-track-ga4-event="whatsapp_click"
          data-track-crm-event="whatsapp_click"
        >
          Chat on WhatsApp
        </a>
      </div>
    </div>
  </section>

</main>

<?php
get_footer();
