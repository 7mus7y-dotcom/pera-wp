<?php
/**
 * Template Name: Book a Consultancy
 * Description: Landing page for booking a consultancy call with Pera Property.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<main id="primary" class="site-main">

    <!-- =====================================
     HERO (BOOK A CONSULTANCY)
     Canonical structure + existing content
     ===================================== -->
    <section class="hero hero--left hero--sell" id="consultancy-hero">

      <div class="hero__media" aria-hidden="true">
        <?php
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
        <h1>Book a Consultancy</h1>

        <p class="lead">
          Speak to a senior consultant about buying or investing in Istanbul.
        </p>

        <div class="hero-actions">
          <a href="#booking" class="btn btn--solid btn--green">
            Book your session
          </a>

          <a href="mailto:info@peraproperty.com" class="btn btn--solid btn--blue">
            Email our team
          </a>
        </div>

        <div class="pillars">
          <div>Senior consultants</div>
          <div>Data-led guidance</div>
          <div>Private &amp; bilingual</div>
          <div>Zero-obligation advice</div>
        </div>
      </div>

    </section>

    <!-- CONSULTANCY TYPE CARDS -->
    <section class="section" id="consultancy-types">
      <div class="section-header section-header--center">
        <h2>Choose the consultancy that fits your goals</h2>
        <p>Each session is tailored to your plans, investment horizon, and preferred districts.</p>
      </div>

      <div class="feature-grid">
        <article class="feature-card">
          <div class="feature-card-header">
            <div class="pill pill--outline pill--sm">30 mins</div>
            <h3>Buyer Discovery Call</h3>
          </div>
          <div class="feature-card-body">
            <ul class="checklist">
              <li>
                <svg class="icon icon-tick" aria-hidden="true">
                  <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                </svg>
                Clarify lifestyle and budget priorities
              </li>
              <li>
                <svg class="icon icon-tick" aria-hidden="true">
                  <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                </svg>
                Shortlist Istanbul districts that fit
              </li>
              <li>
                <svg class="icon icon-tick" aria-hidden="true">
                  <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                </svg>
                Outline next steps for viewings
              </li>
            </ul>
          </div>
          <div class="feature-card-footer">
            <a href="#booking" class="btn btn--solid btn--green">Select this call</a>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <div class="pill pill--outline pill--sm">45 mins</div>
            <h3>Investor Strategy Call</h3>
          </div>
          <div class="feature-card-body">
            <ul class="checklist">
              <li>
                <svg class="icon icon-tick" aria-hidden="true">
                  <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                </svg>
                Yield, resale, and exit strategy review
              </li>
              <li>
                <svg class="icon icon-tick" aria-hidden="true">
                  <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                </svg>
                Compare new-build vs. resale options
              </li>
              <li>
                <svg class="icon icon-tick" aria-hidden="true">
                  <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                </svg>
                Funding and ownership structure guidance
              </li>
            </ul>
          </div>
          <div class="feature-card-footer">
            <a href="#booking" class="btn btn--solid btn--green">Select this call</a>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <div class="pill pill--outline pill--sm pill--nowrap">45 mins</div>
            <h3>Residency / CBI Consultation</h3>
          </div>
          <div class="feature-card-body">
            <ul class="checklist">
              <li>
                <svg class="icon icon-tick" aria-hidden="true">
                  <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                </svg>
                Understand eligibility and timelines
              </li>
              <li>
                <svg class="icon icon-tick" aria-hidden="true">
                  <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                </svg>
                Review qualifying property options
              </li>
              <li>
                <svg class="icon icon-tick" aria-hidden="true">
                  <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                </svg>
                Coordinate legal and advisory steps
              </li>
            </ul>
          </div>
          <div class="feature-card-footer">
            <a href="#booking" class="btn btn--solid btn--green">Select this call</a>
          </div>
        </article>
      </div>
    </section>

    <!-- BOOKING MODULE -->
    <section id="booking" class="section section-soft">
      <div class="content-panel-box">
          <div class="content-panel-right">
            <div class="enquiry-cta-header m-sm">
              <h2>Request a tailored briefing</h2>
              <p>Share a few details so we can prepare before the call.</p>
            </div>

            <form class="enquiry-cta-form m-sm" action="" method="post">
              <input type="hidden" name="sr_action" value="1">
              <input type="hidden" name="form_context" value="consultancy">

              <?php wp_nonce_field( 'pera_seller_landlord_enquiry', 'sr_nonce' ); ?>

              <div class="cta-fieldset">
                <div class="cta-field">
                  <label class="cta-label" for="sr_name">Full name</label>
                  <input type="text" id="sr_name" name="sr_name" class="cta-control" required placeholder="Your full name">
                </div>

                <div class="cta-field">
                  <label class="cta-label" for="sr_email">Email</label>
                  <input type="email" id="sr_email" name="sr_email" class="cta-control" required placeholder="name@example.com">
                </div>

                <div class="cta-field">
                  <label class="cta-label" for="sr_phone">WhatsApp number</label>
                  <input type="text" id="sr_phone" name="sr_phone" class="cta-control" required placeholder="+90 … or your international number">
                </div>

                <div class="cta-field">
                  <label class="cta-label" for="sr_consultancy_type">Consultancy type</label>
                  <select id="sr_consultancy_type" name="sr_consultancy_type" class="cta-control" required>
                    <option value="Buyer Discovery Call">Buyer Discovery Call (30 mins)</option>
                    <option value="Investor Strategy Call">Investor Strategy Call (45 mins)</option>
                    <option value="Residency / CBI">Residency / CBI (45 mins)</option>
                  </select>
                </div>

                <div class="cta-field">
                  <label class="cta-label" for="sr_language">Preferred language</label>
                  <select id="sr_language" name="sr_language" class="cta-control">
                    <option value="English">English</option>
                    <option value="Turkish">Turkish</option>
                    <option value="Arabic">Arabic</option>
                    <option value="Russian">Russian</option>
                    <option value="Other">Other</option>
                  </select>
                </div>

                <div class="cta-field">
                  <label class="cta-label" for="sr_interest">Primary interest</label>
                  <select id="sr_interest" name="sr_interest" class="cta-control">
                    <option value="Lifestyle home">Lifestyle home</option>
                    <option value="Investment rental">Investment rental</option>
                    <option value="New development">New development</option>
                    <option value="Resale opportunities">Resale opportunities</option>
                    <option value="Residency / citizenship">Residency / citizenship</option>
                  </select>
                </div>

                <div class="cta-field">
                  <label class="cta-label" for="sr_budget_range">Budget range (EUR)</label>
                  <input type="text" id="sr_budget_range" name="sr_budget_range" class="cta-control" placeholder="e.g. €250k – €400k">
                </div>

                <div class="cta-field">
                  <label class="cta-label" for="sr_timeline">Purchase timeline</label>
                  <select id="sr_timeline" name="sr_timeline" class="cta-control">
                    <option value="Immediately">Immediately</option>
                    <option value="1–3 months">1–3 months</option>
                    <option value="3–6 months">3–6 months</option>
                    <option value="6+ months">6+ months</option>
                    <option value="Exploring">Exploring</option>
                  </select>
                </div>

                <div class="cta-field">
                  <label class="cta-label" for="sr_area_preference">Preferred area</label>
                  <input type="text" id="sr_area_preference" name="sr_area_preference" class="cta-control" placeholder="e.g. Beşiktaş, Şişli, Kadıköy">
                </div>

                <div class="cta-field">
                  <label class="cta-label" for="sr_notes">Notes / questions</label>
                  <textarea id="sr_notes" name="sr_notes" rows="4" class="cta-control" placeholder="Tell us what you want to cover during the call."></textarea>
                </div>

                <div class="enquiry-cta-footer">
                  <label class="cta-checkbox">
                    <input type="checkbox" name="sr_consent" value="1" required>
                    <span>
                      I agree for Pera Property to contact me regarding this enquiry and to
                      process my personal data in accordance with the
                      <a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>" target="_blank" rel="noopener">
                        Privacy Policy
                      </a>.
                    </span>
                  </label>

                  <button type="submit" class="btn btn--solid btn--green">
                    Send booking request
                  </button>
                </div>
              </div>
            </form>
          </div>
      </div>
    </section>

    <!-- WHAT HAPPENS NEXT -->
    <section class="section" id="what-happens-next">
      <div class="section-header section-header--center">
        <h2>What happens next</h2>
        <p>We keep the process simple so you can move quickly and confidently.</p>
      </div>

      <div class="feature-grid">
        <article class="feature-card">
          <div class="feature-card-header">
            <div class="pill pill--outline pill--sm">Step 1</div>
            <h3>We review your goals</h3>
          </div>
          <div class="feature-card-body">
            <p>Our team confirms your requirements, timeline, and preferred areas.</p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <div class="pill pill--outline pill--sm">Step 2</div>
            <h3>You meet your consultant</h3>
          </div>
          <div class="feature-card-body">
            <p>We run through market data, pricing, and options relevant to you.</p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <div class="pill pill--outline pill--sm">Step 3</div>
            <h3>We send your tailored plan</h3>
          </div>
          <div class="feature-card-body">
            <p>Expect a curated shortlist and next-step checklist within 48 hours.</p>
          </div>
        </article>
      </div>
    </section>

    <!-- WHY PERA -->
    <section class="section" id="why-pera">
      <div class="content-panel-box">
        <div class="content-panel-grid">
          <div>
            <header class="section-header">
              <h2>Why Pera Property</h2>
              <p>
                We blend local intelligence with international investor standards, so every
                recommendation is grounded in real data and on-the-ground experience.
              </p>
            </header>

            <ul class="checklist">
              <li>
                <svg class="icon icon-tick" aria-hidden="true">
                  <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                </svg>
                Istanbul-specific insights from a dedicated advisory team
              </li>
              <li>
                <svg class="icon icon-tick" aria-hidden="true">
                  <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                </svg>
                Multi-lingual support and clear documentation
              </li>
              <li>
                <svg class="icon icon-tick" aria-hidden="true">
                  <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-check"></use>
                </svg>
                Access to trusted legal, banking, and residency partners
              </li>
            </ul>
          </div>

          <div>
            <div class="signoff-card">
              <div class="signoff-avatar">
                <?php
                echo wp_get_attachment_image(
                  55700,
                  'full',
                  false,
                  array(
                    'alt'      => 'Pera Property Consultant',
                    'loading'  => 'lazy',
                    'decoding' => 'async',
                  )
                );
                ?>
              </div>
              <div class="signoff-text">
                <h5>Meet your consultants</h5>
                <p>
                  Your call is led by a senior consultant who has guided hundreds of buyers and
                  investors across Istanbul’s top districts.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- FAQ -->
    <section class="section" id="faq">
      <div class="section-header section-header--center">
        <h2>Frequently asked questions</h2>
        <p>Quick answers to common consultancy questions.</p>
      </div>

      <div class="stacked-text">
        <details>
          <summary>How much does the consultancy call cost?</summary>
          <p>Our initial consultancy calls are complimentary and focused on giving you clarity.</p>
        </details>

        <details>
          <summary>Can I bring a friend or family member?</summary>
          <p>Yes. Let us know in advance and we will share the meeting link accordingly.</p>
        </details>

        <details>
          <summary>Do you support remote or in-person meetings?</summary>
          <p>We primarily host calls online, but in-person meetings in Istanbul can be arranged.</p>
        </details>

        <details>
          <summary>What if I need a translator?</summary>
          <p>We can provide bilingual consultants or translators for Arabic, Russian, and Turkish.</p>
        </details>
      </div>
    </section>

    <!-- FINAL CTA STRIP -->
    <section class="section section-soft" id="final-cta">
      <div class="section-header section-header--center">
        <h2>Ready to speak with a consultant?</h2>
        <p>Secure your slot or reach us directly for urgent questions.</p>
      </div>

      <div class="hero-actions flex-center">
        <a href="#booking" class="btn btn--solid btn--green">Book your session</a>
        <a
          href="https://wa.me/905452054356?text=Hello%20I%20would%20like%20to%20book%20a%20consultancy%20call%20with%20Pera%20Property"
          target="_blank"
          rel="noopener"
          class="btn btn--solid btn--blue"
        >
          WhatsApp our team
        </a>
      </div>
    </section>

</main>

<?php
get_footer();
