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
          <h1>Turkish Citizenship by Investment Through Real Estate</h1>
          <p>
            Apply for Turkish citizenship by investment through a qualifying USD 400,000 real estate purchase in Turkey. Pera Property helps international investors find eligible Istanbul properties, complete legal checks, and manage the citizenship application from purchase to passport.
          </p>
          <article class="feature-card citizenship-hero-card" aria-label="Turkish Citizenship by Investment Requirements (2026)">
            <div class="feature-card-header">
              <h2>Requirements (2026)</h2>
            </div>
            <div class="feature-card-body">
              <div class="citizenship-requirements-group">
                <h3>Investment</h3>
                <ul class="checklist">
                  <?php foreach ( array_slice( $citizenship_requirements, 0, 4 ) as $requirement ) : ?>
                    <li>
                      <svg class="icon icon-tick" aria-hidden="true">
                        <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                      </svg>
                      <?php echo esc_html( $requirement ); ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
              <div class="citizenship-requirements-group">
                <h3>Family &amp; process</h3>
                <ul class="checklist">
                  <?php foreach ( array_slice( $citizenship_requirements, 4 ) as $requirement ) : ?>
                    <li>
                      <svg class="icon icon-tick" aria-hidden="true">
                        <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                      </svg>
                      <?php echo esc_html( $requirement ); ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </div>
          </article>
          <div class="hero-actions">
            <a href="#citizenship-callback" class="btn btn--solid btn--green">Book a consultation</a>
            <a href="https://www.peraproperty.com/turkish-citizenship-properties/?view=cards" 
               class="btn btn--solid btn--blue" 
               target="_blank" 
               rel="noopener">
              View Turkish citizenship properties for sale
            </a>          
          </div>
          <p class="citizenship-trust-strip text-light">Since 2016 • Istanbul-based team • Legal process clarity</p>
        </div>
      </div>
    </div>
  </section>

  <section class="content-panel citizenship-seo-intro">
    <div class="content-panel-box">
      <div class="content-panel-grid--single">
        <header class="section-header section-header--center">
          <h2>Turkey Citizenship by Investment with Pera Property</h2>
          <p>Turkey citizenship by investment can be a practical route for families seeking a second nationality while owning high-potential Istanbul real estate.</p>
        </header>
        <div class="citizenship-seo-copy">
          <p>Turkey’s citizenship by investment programme allows eligible investors to apply for Turkish citizenship through a qualifying real estate investment of at least USD 400,000. For many families, the Turkish citizenship property route is attractive because it combines a second passport strategy with ownership of a tangible Istanbul property asset.</p>
          <p>Pera Property supports international buyers through the full Turkey citizenship by investment process, from selecting citizenship-eligible property options for sale in Istanbul to coordinating legal due diligence, valuation reports, title deed transfer, foreign currency documentation, residency steps, and the final citizenship application through to passport issuance.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="section section-soft" id="what-is-turkish-citizenship-by-investment">
    <div class="container">
      <header class="section-header section-header--center">
        <h2>What Is Turkish Citizenship by Investment?</h2>
        <p>
          Turkish Citizenship by Investment is a legal route that allows eligible foreign investors to apply for Turkish citizenship after completing a qualifying investment, most commonly through real estate.
        </p>
      </header>

      <div class="feature-grid">
        <article class="feature-card">
          <div class="feature-card-header">
            <h3>The real estate route</h3>
          </div>
          <div class="feature-card-body">
            <p>
              The most popular route is a qualifying property purchase of at least USD 400,000, subject to valuation, title deed, foreign currency and land registry requirements.
            </p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3>Family application</h3>
          </div>
          <div class="feature-card-body">
            <p>
              The main applicant can usually include a spouse and children under 18 in the same citizenship file, making it suitable for family relocation, second passport and long-term planning.
            </p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3>Property-led strategy</h3>
          </div>
          <div class="feature-card-body">
            <p>
              A well-selected Istanbul property can support the citizenship application while also offering rental income, resale liquidity and long-term exposure to the Turkish real estate market.
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
            <h2>Full-service citizenship consultancy in Istanbul</h2>
            <p>Since 2016, Pera’s founders and legal partners have assisted international clients with Turkish Citizenship by Investment, combining specialist real estate knowledge with a dedicated immigration and legal team.</p>
          </header>
          <div class="feature-grid citizenship-value-grid">
            <article class="feature-card"><div class="feature-card-body"><p>Strategic property shortlists aligned with citizenship eligibility and your family’s lifestyle goals.</p></div></article>
            <article class="feature-card"><div class="feature-card-body"><p>Coordinated legal and administrative execution from title deed checks to citizenship filing readiness.</p></div></article>
            <article class="feature-card"><div class="feature-card-body"><p>One accountable team with clear updates, timeline visibility, and practical next steps at every stage.</p></div></article>
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
        <h2>Our full-package citizenship service</h2>
        <p>
          Everything you need for a successful Turkish citizenship application,
          delivered as a single coordinated package by Pera and our legal partners.
        </p>
      </header>

      <div class="feature-grid">

        <article class="feature-card">
          <div class="feature-card-header">
            <h3>CBI-eligible properties</h3>
          </div>
          <div class="feature-card-body">
            <p>
              Access a curated list of Istanbul properties that fully comply with
              the USD&nbsp;400,000 citizenship requirement and land registry rules.
            </p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3>Legal due diligence</h3>
          </div>
          <div class="feature-card-body">
            <p>
              Independent Turkish lawyers check title deeds, permits and encumbrances
              before you commit to any investment.
            </p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3>Banking &amp; tax numbers</h3>
          </div>
          <div class="feature-card-body">
            <p>
              Support with obtaining your Turkish tax number, opening local bank
              accounts and arranging secure fund transfers.
            </p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3>Purchase &amp; title deed</h3>
          </div>
          <div class="feature-card-body">
            <p>
              End-to-end assistance with the property purchase, including sales
              contracts, valuation reports and title deed transfer.
            </p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3>Citizenship filing</h3>
          </div>
          <div class="feature-card-body">
            <p>
              Preparation and submission of residency and citizenship files for
              all eligible family members with ongoing follow-up.
            </p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3>After-sales &amp; rentals</h3>
          </div>
          <div class="feature-card-body">
            <p>
              Optional property management and rental services to help you
              generate income from your citizenship investment.
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
        <h2>Turkey Citizenship by Investment: Key Facts</h2>
        <p>
          A fast, flexible route to Turkish citizenship for families who invest in qualifying real estate.
        </p>
      </header>

      <div class="info-steps">

        <article class="info-step">
          <div class="info-step-icon">
            <span class="info-step-number">1</span>
          </div>
          <div class="info-step-body">
            <h3 class="info-step-title">USD 400,000+ real estate</h3>
            <p class="info-step-text">
              You must invest at least USD&nbsp;400,000 in one or more Turkish properties that meet the
              programme’s legal and valuation requirements.
            </p>
          </div>
        </article>

        <article class="info-step">
          <div class="info-step-icon">
            <span class="info-step-number">2</span>
          </div>
          <div class="info-step-body">
            <h3 class="info-step-title">Spouse &amp; children included</h3>
            <p class="info-step-text">
              Your spouse and children under 18 can be included in the same citizenship application.
              Adult children or parents may require separate routes.
            </p>
          </div>
        </article>

        <article class="info-step">
          <div class="info-step-icon">
            <span class="info-step-number">3</span>
          </div>
          <div class="info-step-body">
            <h3 class="info-step-title">Approx. 4–6 month timeline</h3>
            <p class="info-step-text">
              From property purchase and residence permits to full citizenship approval, most complete
              files are finalised within around 4–6 months.
            </p>
          </div>
        </article>

        <article class="info-step">
          <div class="info-step-icon">
            <span class="info-step-number">4</span>
          </div>
          <div class="info-step-body">
            <h3 class="info-step-title">Dual citizenship possible</h3>
            <p class="info-step-text">
              Many nationalities can keep their existing passport when obtaining Turkish citizenship,
              subject to their home country’s rules on dual nationality.
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
        <h2>Turkish Citizenship Property Investment Requirements</h2>
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
            <h3 class="info-step-title">Value</h3>
            <p class="info-step-text">
              The total value of the assets must be at least USD&nbsp;400,000 at the time of purchase and valuation.
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
            <h3 class="info-step-title">Title</h3>
            <p class="info-step-text">
              Each asset must have its own legal title deed (TAPU) and be properly registered at the land registry.
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
            <h3 class="info-step-title">Multiple properties</h3>
            <p class="info-step-text">
              You may use one or more properties to reach the minimum amount. They do not have to be in the same building or project.
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
            <h3 class="info-step-title">Legal charge</h3>
            <p class="info-step-text">
              A restriction is registered on the title deed(s) confirming the property(ies)
              cannot be sold for at least three years.
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
            <h3 class="info-step-title">Valuation</h3>
            <p class="info-step-text">
              The total value must be confirmed by a valuation report issued by an SPK-licensed surveyor.
              Properties sold by GYOs are exempt.
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
        <h2>Who can apply under one citizenship file?</h2>
        <p>
          The Turkish Citizenship by Investment programme allows the main investor to include
          their immediate family under a single coordinated application.
        </p>
      </header>

      <div class="feature-grid">

        <!-- MAIN APPLICANT -->
        <article class="feature-card">
          <div class="feature-card-header">
            <svg class="icon" width="28" height="28" aria-hidden="true">
              <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-user"></use>
            </svg>
            <h3>Main investor</h3>
          </div>
          <div class="feature-card-body">
            <p>
              The primary applicant making the qualifying real estate investment of at least
              USD&nbsp;400,000.
            </p>
          </div>
        </article>

        <!-- SPOUSE -->
        <article class="feature-card">
          <div class="feature-card-header">
            <svg class="icon" width="28" height="28" aria-hidden="true">
              <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-users"></use>
            </svg>
            <h3>Spouse</h3>
          </div>
          <div class="feature-card-body">
            <p>
              Your husband or wife can be added to the same citizenship file as a dependent
              without any extra investment requirement.
            </p>
          </div>
        </article>

        <!-- CHILDREN UNDER 18 -->
        <article class="feature-card">
          <div class="feature-card-header">
            <svg class="icon" width="28" height="28" aria-hidden="true">
              <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-user-add"></use>
            </svg>
            <h3>Children under 18</h3>
          </div>
          <div class="feature-card-body">
            <p>
              All children below 18 years of age can be included under the same application
              as long as the family relationship is documented.
            </p>
          </div>
        </article>

        <!-- ADULT CHILDREN (SPECIAL NEEDS) -->
        <article class="feature-card">
          <div class="feature-card-header">
            <svg class="icon" width="28" height="28" aria-hidden="true">
              <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-shield-check"></use>
            </svg>
            <h3>Adult children (special needs)</h3>
          </div>
          <div class="feature-card-body">
            <p>
              Adult children with officially recognised disabilities or special needs may
              qualify as dependents, subject to supporting medical reports.
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
              Parents <span style="opacity:.6; font-weight:400;">(separate route)</span>
            </h3>
          </div>
          <div class="feature-card-body">
            <p>
              Parents are not included in the main citizenship file, but we can advise on
              suitable residency options or parallel applications.
            </p>
          </div>
        </article>

        <!-- COORDINATED FILE -->
        <article class="feature-card">
          <div class="feature-card-header">
            <svg class="icon" width="28" height="28" aria-hidden="true">
              <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-folder-duplicate"></use>
            </svg>
            <h3>One coordinated process</h3>
          </div>
          <div class="feature-card-body">
            <p>
              Your lawyers prepare and file all family applications together, keeping the
              documentation, timelines and approvals under tight control.
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
        <h2>Benefits of Turkish Citizenship by Investment</h2>
      </header>

      <div class="feature-grid">

        <article class="feature-card">
          <div class="feature-card-header">
            <h3>Fast-track citizenship</h3>
          </div>
          <div class="feature-card-body">
            <p>Passports typically granted within 4–6 months, subject to government processing times.</p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3>Affordable second passport</h3>
          </div>
          <div class="feature-card-body">
            <p>Obtain Turkish citizenship with a qualifying real estate investment from USD&nbsp;400,000.</p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3>Visa-free travel</h3>
          </div>
          <div class="feature-card-body">
            <p>Access to a wide network of countries with a Turkish passport, plus easy visas to key markets.</p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3>No residence requirement</h3>
          </div>
          <div class="feature-card-body">
            <p>No obligation to live in Türkiye before or after citizenship approval.</p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3>Entire family eligible</h3>
          </div>
          <div class="feature-card-body">
            <p>Include your spouse and children under 18 in the same application.</p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3>Real estate investment</h3>
          </div>
          <div class="feature-card-body">
            <p>Invest in income-generating, Istanbul property with long-term capital growth potential.</p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3>3-year holding period</h3>
          </div>
          <div class="feature-card-body">
            <p>After three years you are free to restructure, sell or reinvest your property portfolio.</p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3>Attractive tax planning</h3>
          </div>
          <div class="feature-card-body">
            <p>Citizenship can form part of a broader tax, residency and asset-planning strategy.</p>
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
            Thank you – your enquiry has been received. We’ll contact you shortly.
          </div>
        <?php endif; ?>

          <header class="enquiry-cta-header">
            <h2>Request your citizenship property shortlist</h2>
            <p>
              Share your budget, family details and timeline, and our team will contact you with suitable Istanbul property options for your Turkish citizenship application.
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
                    <p>Thank you for your enquiry. Our team will contact you shortly.</p>
                <?php else : ?>
                    <p>Sorry, your message could not be sent. Please try again or contact us directly.</p>
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
            <label for="citizenship-company">Company</label>
            <input type="text" id="citizenship-company" name="citizenship_company" value="" tabindex="-1" autocomplete="off">
          </div>

      
          <div class="enquiry-cta-grid">

            <!-- LEFT: REQUIRED INFORMATION -->
            <div class="enquiry-cta-column">
              <h3 class="enquiry-cta-subtitle">Required information</h3>

              <div class="cta-fieldset">
                <label class="cta-field">
                  <span class="cta-label">Name</span>
                  <input
                    type="text"
                    name="name"
                    class="cta-control"
                    placeholder="Your full name"
                    required
                  >
                </label>

                <label class="cta-field">
                  <span class="cta-label">Phone</span>
                  <input
                    type="tel"
                    name="phone"
                    class="cta-control"
                    placeholder="+90 ..."
                    required
                  >
                </label>

                <label class="cta-field">
                  <span class="cta-label">Email</span>
                  <input
                    type="email"
                    name="email"
                    class="cta-control"
                    placeholder="you@example.com"
                    required
                  >
                </label>
              </div>

              <div class="cta-fieldset cta-fieldset--inline">
                <span class="cta-label cta-label--muted">Preferred contact method</span>
                <div class="cta-options">
                  <label class="cta-checkbox">
                    <input type="checkbox" name="contact_method[]" value="phone">
                    <span>Phone</span>
                  </label>
                  <label class="cta-checkbox">
                    <input type="checkbox" name="contact_method[]" value="email">
                    <span>Email</span>
                  </label>
                  <label class="cta-checkbox">
                    <input type="checkbox" name="contact_method[]" value="whatsapp">
                    <span>WhatsApp</span>
                  </label>
                </div>
              </div>
            </div>

            <!-- RIGHT: ADDITIONAL INFORMATION -->
            <div class="enquiry-cta-column">
              <h3 class="enquiry-cta-subtitle">Additional information</h3>

              <div class="cta-fieldset">
                <label class="cta-field">
                  <span class="cta-label">Type of enquiry</span>
                  <select name="enquiry_type" class="cta-control">
                    <option value="general">General enquiry</option>
                    <option value="citizenship-only">Citizenship only</option>
                    <option value="citizenship-property">Citizenship &amp; property investment</option>
                    <option value="consultation">Schedule a video consultation</option>
                  </select>
                </label>

                <label class="cta-field">
                  <span class="cta-label">Family members</span>
                  <input
                    type="text"
                    name="family"
                    class="cta-control"
                    placeholder="Number of applicants, ages of children, etc."
                  >
                </label>

                <label class="cta-field">
                  <span class="cta-label">Questions or comments</span>
                  <textarea
                    name="message"
                    rows="3"
                    class="cta-control"
                    placeholder="Tell us a little about your situation or preferred timeline."
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
                I agree to the terms of the
                <a href="/privacy-policy/" target="_blank" rel="noopener">Privacy Policy</a>.
              </span>
            </label>

            <?php $turnstile_site_key = defined( 'PERA_TURNSTILE_SITE_KEY' ) ? sanitize_text_field( (string) PERA_TURNSTILE_SITE_KEY ) : ''; ?>
            <?php if ( $turnstile_site_key !== '' ) : ?>
              <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
              <div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $turnstile_site_key ); ?>"></div>
            <?php endif; ?>

            <button type="submit" class="btn btn--ghost btn--green">
              Send my shortlist request
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
        <h2>Why Choose Pera Property for Turkish Citizenship by Investment?</h2>
        <p>
          A specialist Istanbul real estate agency helping international clients complete the Turkey citizenship application process with clear guidance from property selection to Turkish passport by investment approval.
        </p>
      </header>

      <div class="feature-grid">

        <article class="feature-card">
          <div class="feature-card-header">
            <h3>Specialist CBI portfolio</h3>
          </div>
          <div class="feature-card-body">
            <p>
              We focus on modern, well-located Istanbul developments that appeal
              both to citizenship investors and future tenants.
            </p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3>Transparent fees</h3>
          </div>
          <div class="feature-card-body">
            <p>
              Clear, upfront fee structures for both real estate and legal work,
              with no hidden extras during the process.
            </p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3>Local + international team</h3>
          </div>
          <div class="feature-card-body">
            <p>
              English and Turkish-speaking consultants based in Istanbul,
              backed by experienced immigration lawyers.
            </p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3>End-to-end project management</h3>
          </div>
          <div class="feature-card-body">
            <p>
              One point of contact coordinating developers, valuers, banks and
              lawyers so your application stays on track.
            </p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3>Istanbul market insight</h3>
          </div>
          <div class="feature-card-body">
            <p>
              Advice on which districts hold long-term value, rental demand
              and resale liquidity once your lock-in period ends.
            </p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3>Long-term relationship</h3>
          </div>
          <div class="feature-card-body">
            <p>
              Ongoing support with rentals, resale or portfolio restructuring
              once your citizenship has been granted.
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
      <h2>Turkish citizenship acquisition timeline</h2>
      <p>
        An indicative timeline from your first consultation with Pera to receiving
        your Turkish passports, assuming a complete and correctly prepared file.
      </p>
    </header>

    <ol class="timeline">
      <!-- 1 -->
      <li class="timeline-step">
        <div class="timeline-side">
          <span class="timeline-duration">3–5 days</span>
          <span class="timeline-phase">Preparation</span>
        </div>

        <div class="timeline-marker">
          <span class="timeline-number">1</span>
        </div>

        <div class="timeline-body">
          <h3>Consultation &amp; planning</h3>
          <p>
            We assess your family situation, timeline and budget, and explain the latest programme rules and documentation requirements.
          </p>
        </div>
      </li>

      <!-- 2 -->
      <li class="timeline-step">
        <div class="timeline-side">
          <span class="timeline-duration">2–4 weeks</span>
          <span class="timeline-phase">Document collection</span>
        </div>

        <div class="timeline-marker">
          <span class="timeline-number">2</span>
        </div>

        <div class="timeline-body">
          <h3>Prepare documents</h3>
          <p>
            Our lawyers provide a detailed checklist and help you gather passports,
            civil documents, photos, powers of attorney and any required translations
            and apostilles.
          </p>
        </div>
      </li>

      <!-- 3 -->
      <li class="timeline-step">
        <div class="timeline-side">
          <span class="timeline-duration">1–2 weeks</span>
          <span class="timeline-phase">Investment</span>
        </div>

        <div class="timeline-marker">
          <span class="timeline-number">3</span>
        </div>

        <div class="timeline-body">
          <h3>Select &amp; reserve property</h3>
          <p>
            Together we shortlist CBI-eligible projects in Istanbul, arrange viewings
            (in person or remote) and reserve your chosen units with the developer.
          </p>
        </div>
      </li>

      <!-- 4 -->
      <li class="timeline-step">
        <div class="timeline-side">
          <span class="timeline-duration">2–4 weeks</span>
          <span class="timeline-phase">Completion</span>
        </div>

        <div class="timeline-marker">
          <span class="timeline-number">4</span>
        </div>

        <div class="timeline-body">
          <h3>Complete investment &amp; title deed</h3>
          <p>
            You transfer funds, we obtain the valuation report and our lawyers complete title deed
            registrations with the 3-year no-sale restriction recorded on the TAPU.
          </p>
        </div>
      </li>

      <!-- 5 -->
      <li class="timeline-step">
        <div class="timeline-side">
          <span class="timeline-duration">4–8 weeks</span>
          <span class="timeline-phase">Processing</span>
        </div>

        <div class="timeline-marker">
          <span class="timeline-number">5</span>
        </div>

        <div class="timeline-body">
          <h3>Residence permits &amp; citizenship filing</h3>
          <p>
            Your family’s residence permits and citizenship files are submitted.
            Your lawyers track the application and respond to any requests from
            the authorities.
          </p>
        </div>
      </li>

      <!-- 6 -->
      <li class="timeline-step">
        <div class="timeline-side">
          <span class="timeline-duration">Approx. 4–6 months total</span>
          <span class="timeline-phase">Approval</span>
        </div>

        <div class="timeline-marker">
          <span class="timeline-number">6</span>
        </div>

        <div class="timeline-body">
          <h3>Receive Turkish passports</h3>
          <p>
            Once approved, ID cards and passports are issued for all successful
            applicants, either in Türkiye or via your local consulate.
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
      <h2>Fast-Track Investor Residency and Citizenship Process</h2>
      <p>
        Turkey has introduced a fast-track option for investor residency applications,
        allowing qualified applicants to complete both residency and citizenship
        application steps in a significantly shorter timeframe.
      </p>
    </header>

    <h3>Standard Process:</h3>
    <ul class="checklist">
      <li>
        <svg class="icon icon-tick" aria-hidden="true">
          <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
        </svg>
        Residency application and approval
      </li>
      <li>
        <svg class="icon icon-tick" aria-hidden="true">
          <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
        </svg>
        Separate citizenship application
      </li>
      <li>
        <svg class="icon icon-tick" aria-hidden="true">
          <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
        </svg>
        Multiple steps and timelines
      </li>
    </ul>

    <h3>Fast-Track Option:</h3>
    <ul class="checklist">
      <li>
        <svg class="icon icon-tick" aria-hidden="true">
          <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
        </svg>
        Residency application, biometrics, and citizenship submission can be completed in one visit
      </li>
      <li>
        <svg class="icon icon-tick" aria-hidden="true">
          <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
        </svg>
        Biometric processing completed on arrival
      </li>
      <li>
        <svg class="icon icon-tick" aria-hidden="true">
          <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
        </svg>
        Citizenship submission may be completed the same day
      </li>
      <li>
        <svg class="icon icon-tick" aria-hidden="true">
          <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
        </svg>
        Optional expedited service
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
      <h2>Documents required</h2>
      <p>
        Key documents typically needed for a complete Turkish citizenship
        application. Your lawyer will confirm the exact list based on your
        family situation and nationality.
      </p>
    </header>

    <div class="docs-list">

      <!-- 1. Passport -->
      <details class="doc-item">
        <summary>
          <span class="doc-title">Valid passport</span>
          <span class="doc-icon" aria-hidden="true"></span>
        </summary>
        <div class="doc-body">
          <p>A valid passport or recognised travel document for each applicant.</p>
        </div>
      </details>

      <!-- 2. Marriage / marital status -->
      <details class="doc-item">
        <summary>
          <span class="doc-title">Marriage certificate / marital status</span>
          <span class="doc-icon" aria-hidden="true"></span>
        </summary>
        <div class="doc-body">
          <p>
            If married, a marriage certificate. If divorced, a divorce certificate.
            If never married, an official certificate of single status.
          </p>
        </div>
      </details>

      <!-- 3. Birth certificate -->
      <details class="doc-item">
        <summary>
          <span class="doc-title">Birth certificates</span>
          <span class="doc-icon" aria-hidden="true"></span>
        </summary>
        <div class="doc-body">
          <p>
            Birth certificates for all applicants. If unavailable, a consular
            affidavit confirming your place and date of birth may be needed.
          </p>
        </div>
      </details>

      <!-- 4. Spouse & children -->
      <details class="doc-item">
        <summary>
          <span class="doc-title">Details of spouse and children</span>
          <span class="doc-icon" aria-hidden="true"></span>
        </summary>
        <div class="doc-body">
          <p>
            Passports and birth certificates for your spouse and all children
            included in the application.
          </p>
        </div>
      </details>

      <!-- 5. Turkish tax number -->
      <details class="doc-item">
        <summary>
          <span class="doc-title">Turkish tax number</span>
          <span class="doc-icon" aria-hidden="true"></span>
        </summary>
        <div class="doc-body">
          <p>
            A Turkish tax number for the main applicant, issued by any tax
            office or online through the Revenue Administration.
          </p>
        </div>
      </details>

      <!-- 6. Appraisal reports -->
      <details class="doc-item">
        <summary>
          <span class="doc-title">Appraisal reports</span>
          <span class="doc-icon" aria-hidden="true"></span>
        </summary>
        <div class="doc-body">
          <p>
            Each property must be
            independently valued by an SPK-licensed surveyor to confirm that
            the total investment meets the legal threshold.
          </p>
        </div>
      </details>

      <!-- 7. Title deeds -->
      <details class="doc-item">
        <summary>
          <span class="doc-title">Title deeds of the purchased assets</span>
          <span class="doc-icon" aria-hidden="true"></span>
        </summary>
        <div class="doc-body">
          <p>
            Title deeds (TAPU) for each qualifying asset, with the three-year
            no-sale restriction registered where required.
          </p>
        </div>
      </details>

      <!-- 8. Confirmation of investment -->
      <details class="doc-item">
        <summary>
          <span class="doc-title">Confirmation of investment</span>
          <span class="doc-icon" aria-hidden="true"></span>
        </summary>
        <div class="doc-body">
          <p>
            Official confirmation from the land registry or relevant authority
            that the investment has been completed in line with the citizenship rules.
          </p>
        </div>
      </details>

      <!-- 9. Authentication / legalisation -->
      <details class="doc-item">
        <summary>
          <span class="doc-title">Authentication &amp; legalisation</span>
          <span class="doc-icon" aria-hidden="true"></span>
        </summary>
        <div class="doc-body">
          <p>
            Certain documents (birth, marriage, divorce certificates or
            single-status confirmations) must be apostilled or otherwise
            legalised and officially translated into Turkish. Your lawyer will
            advise on the exact process for your country.
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
      <h2>Legal and compliance checks before you buy</h2>
      <p>
        Buying property for Turkish citizenship is not the same as buying a normal investment apartment. Before a property is treated as citizenship-eligible, the legal, title deed, valuation, payment and conformity requirements must be checked carefully.
      </p>
    </header>

    <div class="content-panel-box citizenship-advisory-panel">
      <div class="citizenship-advisory-copy">
        <p>
          Pera Property coordinates the property search and transaction process with licensed Turkish legal partners so that investors understand the requirements before committing to a purchase. Eligibility should be confirmed before funds are committed, especially where the property, seller, payment route or valuation needs further review.
        </p>
        <p>
          These checks typically include an independent Turkish lawyer review, title deed checks, seller and property eligibility checks, official valuation report review, DAB / foreign currency compliance, land registry annotation for the mandatory 3-year holding period, and the Certificate of Conformity stage.
        </p>
        <p>
          If you are comparing <a href="<?php echo esc_url( home_url( '/property/' ) ); ?>">citizenship-eligible properties in Istanbul</a>, our team can explain which items need legal confirmation before reservation, title deed transfer and citizenship filing.
        </p>
      </div>

      <div class="content-note" role="note" aria-label="Legal services disclaimer">
        <strong>Pera Property is not a law firm.</strong> Citizenship applications are handled with licensed Turkish legal partners. Pera Property checks eligibility of all property which is claimed to be eligible before offering it to our clients. We strongly advise no payments are made before eligibility, particularly valuation reports, are checked.
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
      <h2>Is Turkish Citizenship by Investment right for you?</h2>
      <p>
        Turkish citizenship by investment can be a strong fit for some buyers, but it is not the right route for every objective. The best decision depends on your family priorities, documentation position, exit timeline and appetite for real estate ownership.
      </p>
    </header>

    <div class="feature-grid citizenship-fit-grid">
      <article class="feature-card citizenship-fit-card">
        <div class="feature-card-header">
          <h3>Good fit for</h3>
        </div>
        <div class="feature-card-body">
          <ul class="checklist">
            <li>Families seeking a second citizenship</li>
            <li>Investors who want a tangible real estate asset</li>
            <li>Buyers focused on Istanbul liquidity</li>
            <li>Applicants who can hold the property for at least 3 years</li>
            <li>Investors who want a relatively fast citizenship route</li>
          </ul>
        </div>
      </article>

      <article class="feature-card citizenship-fit-card">
        <div class="feature-card-header">
          <h3>Not ideal for</h3>
        </div>
        <div class="feature-card-body">
          <ul class="checklist checklist--cross">
            <li>Buyers who specifically need visa-free US or Schengen access</li>
            <li>Investors who want immediate resale flexibility</li>
            <li>Applicants unwilling to complete bank, legal and source-of-funds documentation</li>
            <li>Buyers focused only on the cheapest qualifying property rather than eligibility, location and resale potential</li>
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
      <h2>Turkish citizenship investment routes compared</h2>
      <p>
        The real estate route is the most common option for many international investors, but it is not the only Turkish citizenship by investment route. The best route depends on your investment objective, documentation position, timeline and appetite for property ownership.
      </p>
    </header>

    <div class="citizenship-table-wrap" role="region" aria-label="Turkish citizenship investment route comparison" tabindex="0">
      <table class="citizenship-route-table">
        <thead>
          <tr>
            <th scope="col">Route</th>
            <th scope="col">Minimum investment</th>
            <th scope="col">Common use case</th>
            <th scope="col">Notes</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <th scope="row">Real estate</th>
            <td>USD 400,000</td>
            <td>Most popular route</td>
            <td>Property must usually be held for at least 3 years</td>
          </tr>
          <tr>
            <th scope="row">Bank deposit</th>
            <td>USD 500,000</td>
            <td>Capital preservation</td>
            <td>Rules, bank requirements and documentation process differ</td>
          </tr>
          <tr>
            <th scope="row">Government bonds</th>
            <td>USD 500,000</td>
            <td>Passive investment</td>
            <td>Less exposure to property market performance</td>
          </tr>
          <tr>
            <th scope="row">Business or employment routes</th>
            <td>Varies</td>
            <td>Operational investors</td>
            <td>Usually more complex and case-specific</td>
          </tr>
        </tbody>
      </table>
    </div>

    <p class="citizenship-route-note">
      Investment thresholds and application practice can change. Requirements should be checked with a licensed Turkish legal adviser before making an investment decision. To discuss your objectives, <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">speak to Pera Property</a>.
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
      <h2>Ready to explore Turkish citizenship by investment?</h2>
      <p>
        Share your details and one of our consultants will contact you to discuss
        your plans and outline the best options for your family.
      </p>

      <div class="hero-actions">
        <a
          href="#citizenship-callback"
          class="btn btn--solid btn--green"
        >
          Contact our citizenship team
        </a>
        <a
          href="https://wa.me/905320639978?text=Hello%20Pera%20Property%2C%20I%27m%20interested%20in%20Turkish%20citizenship%20by%20investment."
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
