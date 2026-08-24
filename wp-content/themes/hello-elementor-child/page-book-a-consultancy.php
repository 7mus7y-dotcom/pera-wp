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
        <h1><?php echo esc_html( pera_ml_ui( 'Book a Consultancy', 'theme.template.page_book_a_consultancy.book_a_consultancy' ) ); ?></h1>

        <p class="lead">
          <?php echo esc_html( pera_ml_ui( 'Speak to a senior consultant about buying or investing in Istanbul.', 'theme.template.page_book_a_consultancy.speak_to_a_senior_consultant_about_buying_or_investing_in_istanbul' ) ); ?>
        </p>

        <div class="hero-actions">
          <a href="#booking" class="btn btn--solid btn--green">
            <?php echo esc_html( pera_ml_ui( 'Book your session', 'theme.template.page_book_a_consultancy.book_your_session' ) ); ?>
          </a>

          <a href="mailto:info@peraproperty.com" class="btn btn--solid btn--blue">
            <?php echo esc_html( pera_ml_ui( 'Email our team', 'theme.template.page_book_a_consultancy.email_our_team' ) ); ?>
          </a>
        </div>

        <div class="pillars">
          <div><?php echo esc_html( pera_ml_ui( 'Senior consultants', 'theme.template.page_book_a_consultancy.senior_consultants' ) ); ?></div>
          <div><?php echo esc_html( pera_ml_ui( 'Data-led guidance', 'theme.template.page_book_a_consultancy.data_led_guidance' ) ); ?></div>
          <div><?php echo esc_html( pera_ml_ui( 'Private & bilingual', 'theme.template.page_book_a_consultancy.private_and_bilingual' ) ); ?></div>
          <div><?php echo esc_html( pera_ml_ui( 'Zero-obligation advice', 'theme.template.page_book_a_consultancy.zero_obligation_advice' ) ); ?></div>
        </div>
      </div>

    </section>

    <!-- CONSULTANCY TYPE CARDS -->
    <section class="section" id="consultancy-types">
      <div class="section-header section-header--center">
        <h2><?php echo esc_html( pera_ml_ui( 'Choose the consultancy that fits your goals', 'theme.template.page_book_a_consultancy.choose_the_consultancy_that_fits_your_goals' ) ); ?></h2>
        <p><?php echo esc_html( pera_ml_ui( 'Each session is tailored to your plans, investment horizon, and preferred districts.', 'theme.template.page_book_a_consultancy.each_session_is_tailored_to_your_plans_investment_horizon_and_preferred_' ) ); ?></p>
      </div>

      <div class="feature-grid">
        <article class="feature-card">
          <div class="feature-card-header">
            <div class="pill pill--outline pill--sm"><?php echo esc_html( pera_ml_ui( '30 mins', 'theme.template.page_book_a_consultancy.30_mins' ) ); ?></div>
            <h3><?php echo esc_html( pera_ml_ui( 'Buyer Discovery Call', 'theme.template.page_book_a_consultancy.buyer_discovery_call' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <ul class="checklist checklist--circle">
              <li>
                <?php echo esc_html( pera_ml_ui( 'Clarify lifestyle and budget priorities', 'theme.template.page_book_a_consultancy.clarify_lifestyle_and_budget_priorities' ) ); ?>
              </li>
              <li>
                <?php echo esc_html( pera_ml_ui( 'Shortlist Istanbul districts that fit', 'theme.template.page_book_a_consultancy.shortlist_istanbul_districts_that_fit' ) ); ?>
              </li>
              <li>
                <?php echo esc_html( pera_ml_ui( 'Outline next steps for viewings', 'theme.template.page_book_a_consultancy.outline_next_steps_for_viewings' ) ); ?>
              </li>
            </ul>
          </div>
          <div class="feature-card-footer">
            <a href="#booking" class="btn btn--solid btn--green"><?php echo esc_html( pera_ml_ui( 'Select this call', 'theme.template.page_book_a_consultancy.select_this_call' ) ); ?></a>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <div class="pill pill--outline pill--sm"><?php echo esc_html( pera_ml_ui( '45 mins', 'theme.template.page_book_a_consultancy.45_mins' ) ); ?></div>
            <h3><?php echo esc_html( pera_ml_ui( 'Investor Strategy Call', 'theme.template.page_book_a_consultancy.investor_strategy_call' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <ul class="checklist checklist--circle">
              <li>
                <?php echo esc_html( pera_ml_ui( 'Yield, resale, and exit strategy review', 'theme.template.page_book_a_consultancy.yield_resale_and_exit_strategy_review' ) ); ?>
              </li>
              <li>
                <?php echo esc_html( pera_ml_ui( 'Compare new-build vs. resale options', 'theme.template.page_book_a_consultancy.compare_new_build_vs_resale_options' ) ); ?>
              </li>
              <li>
                <?php echo esc_html( pera_ml_ui( 'Funding and ownership structure guidance', 'theme.template.page_book_a_consultancy.funding_and_ownership_structure_guidance' ) ); ?>
              </li>
            </ul>
          </div>
          <div class="feature-card-footer">
            <a href="#booking" class="btn btn--solid btn--green"><?php echo esc_html( pera_ml_ui( 'Select this call', 'theme.template.page_book_a_consultancy.select_this_call' ) ); ?></a>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <div class="pill pill--outline pill--sm pill--nowrap"><?php echo esc_html( pera_ml_ui( '45 mins', 'theme.template.page_book_a_consultancy.45_mins' ) ); ?></div>
            <h3><?php echo esc_html( pera_ml_ui( 'Residency / CBI Consultation', 'theme.template.page_book_a_consultancy.residency_cbi_consultation' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <ul class="checklist checklist--circle">
              <li>
                <?php echo esc_html( pera_ml_ui( 'Understand eligibility and timelines', 'theme.template.page_book_a_consultancy.understand_eligibility_and_timelines' ) ); ?>
              </li>
              <li>
                <?php echo esc_html( pera_ml_ui( 'Review qualifying property options', 'theme.template.page_book_a_consultancy.review_qualifying_property_options' ) ); ?>
              </li>
              <li>
                <?php echo esc_html( pera_ml_ui( 'Coordinate legal and advisory steps', 'theme.template.page_book_a_consultancy.coordinate_legal_and_advisory_steps' ) ); ?>
              </li>
            </ul>
          </div>
          <div class="feature-card-footer">
            <a href="#booking" class="btn btn--solid btn--green"><?php echo esc_html( pera_ml_ui( 'Select this call', 'theme.template.page_book_a_consultancy.select_this_call' ) ); ?></a>
          </div>
        </article>
      </div>
    </section>

    <!-- BOOKING MODULE -->
    <section id="booking" class="section section-soft">
      <div class="content-panel-box">
          <div class="content-panel-right">
            <div class="enquiry-cta-header m-sm">
              <h2><?php echo esc_html( pera_ml_ui( 'Request a tailored briefing', 'theme.template.page_book_a_consultancy.request_a_tailored_briefing' ) ); ?></h2>
              <p><?php echo esc_html( pera_ml_ui( 'Share a few details so we can prepare before the call.', 'theme.template.page_book_a_consultancy.share_a_few_details_so_we_can_prepare_before_the_call' ) ); ?></p>
            </div>

            <form class="enquiry-cta-form m-sm" action="" method="post">
              <input type="hidden" name="sr_action" value="1">
              <input type="hidden" name="form_context" value="consultancy">

              <?php wp_nonce_field( 'pera_seller_landlord_enquiry', 'sr_nonce' ); ?>

              <div class="cta-fieldset">
                <div class="cta-field">
                  <label class="cta-label" for="sr_name"><?php echo esc_html( pera_ml_ui( 'Full name', 'theme.template.page_book_a_consultancy.full_name' ) ); ?></label>
                  <input type="text" id="sr_name" name="sr_name" class="cta-control" required placeholder="<?php echo esc_attr( pera_ml_ui( 'Your full name', 'theme.template.page_book_a_consultancy.placeholder.your_full_name' ) ); ?>">
                </div>

                <div class="cta-field">
                  <label class="cta-label" for="sr_email"><?php echo esc_html( pera_ml_ui( 'Email', 'theme.template.page_book_a_consultancy.email' ) ); ?></label>
                  <input type="email" id="sr_email" name="sr_email" class="cta-control" required placeholder="<?php echo esc_attr( pera_ml_ui( 'name@example.com', 'theme.template.page_book_a_consultancy.placeholder.name_example_com' ) ); ?>">
                </div>

                <div class="cta-field">
                  <label class="cta-label" for="sr_phone"><?php echo esc_html( pera_ml_ui( 'WhatsApp number', 'theme.template.page_book_a_consultancy.whatsapp_number' ) ); ?></label>
                  <input type="text" id="sr_phone" name="sr_phone" class="cta-control" required placeholder="<?php echo esc_attr( pera_ml_ui( '+90 … or your international number', 'theme.template.page_book_a_consultancy.placeholder.90_or_your_international_number' ) ); ?>">
                </div>

                <div class="cta-field">
                  <label class="cta-label" for="sr_consultancy_type"><?php echo esc_html( pera_ml_ui( 'Consultancy type', 'theme.template.page_book_a_consultancy.consultancy_type' ) ); ?></label>
                  <select id="sr_consultancy_type" name="sr_consultancy_type" class="cta-control" required>
                    <option value="Buyer Discovery Call"><?php echo esc_html( pera_ml_ui( 'Buyer Discovery Call (30 mins)', 'theme.template.page_book_a_consultancy.buyer_discovery_call_30_mins' ) ); ?></option>
                    <option value="Investor Strategy Call"><?php echo esc_html( pera_ml_ui( 'Investor Strategy Call (45 mins)', 'theme.template.page_book_a_consultancy.investor_strategy_call_45_mins' ) ); ?></option>
                    <option value="Residency / CBI"><?php echo esc_html( pera_ml_ui( 'Residency / CBI (45 mins)', 'theme.template.page_book_a_consultancy.residency_cbi_45_mins' ) ); ?></option>
                  </select>
                </div>

                <div class="cta-field">
                  <label class="cta-label" for="sr_language"><?php echo esc_html( pera_ml_ui( 'Preferred language', 'theme.template.page_book_a_consultancy.preferred_language' ) ); ?></label>
                  <select id="sr_language" name="sr_language" class="cta-control">
                    <option value="English"><?php echo esc_html( pera_ml_ui( 'English', 'theme.template.page_book_a_consultancy.english' ) ); ?></option>
                    <option value="Turkish"><?php echo esc_html( pera_ml_ui( 'Turkish', 'theme.template.page_book_a_consultancy.turkish' ) ); ?></option>
                    <option value="Arabic"><?php echo esc_html( pera_ml_ui( 'Arabic', 'theme.template.page_book_a_consultancy.arabic' ) ); ?></option>
                    <option value="Russian"><?php echo esc_html( pera_ml_ui( 'Russian', 'theme.template.page_book_a_consultancy.russian' ) ); ?></option>
                    <option value="Other"><?php echo esc_html( pera_ml_ui( 'Other', 'theme.template.page_book_a_consultancy.other' ) ); ?></option>
                  </select>
                </div>

                <div class="cta-field">
                  <label class="cta-label" for="sr_interest"><?php echo esc_html( pera_ml_ui( 'Primary interest', 'theme.template.page_book_a_consultancy.primary_interest' ) ); ?></label>
                  <select id="sr_interest" name="sr_interest" class="cta-control">
                    <option value="Lifestyle home"><?php echo esc_html( pera_ml_ui( 'Lifestyle home', 'theme.template.page_book_a_consultancy.lifestyle_home' ) ); ?></option>
                    <option value="Investment rental"><?php echo esc_html( pera_ml_ui( 'Investment rental', 'theme.template.page_book_a_consultancy.investment_rental' ) ); ?></option>
                    <option value="New development"><?php echo esc_html( pera_ml_ui( 'New development', 'theme.template.page_book_a_consultancy.new_development' ) ); ?></option>
                    <option value="Resale opportunities"><?php echo esc_html( pera_ml_ui( 'Resale opportunities', 'theme.template.page_book_a_consultancy.resale_opportunities' ) ); ?></option>
                    <option value="Residency / citizenship"><?php echo esc_html( pera_ml_ui( 'Residency / citizenship', 'theme.template.page_book_a_consultancy.residency_citizenship' ) ); ?></option>
                  </select>
                </div>

                <div class="cta-field">
                  <label class="cta-label" for="sr_budget_range"><?php echo esc_html( pera_ml_ui( 'Budget range (EUR)', 'theme.template.page_book_a_consultancy.budget_range_eur' ) ); ?></label>
                  <input type="text" id="sr_budget_range" name="sr_budget_range" class="cta-control" placeholder="<?php echo esc_attr( pera_ml_ui( 'e.g. €250k – €400k', 'theme.template.page_book_a_consultancy.placeholder.e_g_250k_400k' ) ); ?>">
                </div>

                <div class="cta-field">
                  <label class="cta-label" for="sr_timeline"><?php echo esc_html( pera_ml_ui( 'Purchase timeline', 'theme.template.page_book_a_consultancy.purchase_timeline' ) ); ?></label>
                  <select id="sr_timeline" name="sr_timeline" class="cta-control">
                    <option value="Immediately"><?php echo esc_html( pera_ml_ui( 'Immediately', 'theme.template.page_book_a_consultancy.immediately' ) ); ?></option>
                    <option value="1–3 months"><?php echo esc_html( pera_ml_ui( '1–3 months', 'theme.template.page_book_a_consultancy.1_3_months' ) ); ?></option>
                    <option value="3–6 months"><?php echo esc_html( pera_ml_ui( '3–6 months', 'theme.template.page_book_a_consultancy.3_6_months' ) ); ?></option>
                    <option value="6+ months"><?php echo esc_html( pera_ml_ui( '6+ months', 'theme.template.page_book_a_consultancy.6_months' ) ); ?></option>
                    <option value="Exploring"><?php echo esc_html( pera_ml_ui( 'Exploring', 'theme.template.page_book_a_consultancy.exploring' ) ); ?></option>
                  </select>
                </div>

                <div class="cta-field">
                  <label class="cta-label" for="sr_area_preference"><?php echo esc_html( pera_ml_ui( 'Preferred area', 'theme.template.page_book_a_consultancy.preferred_area' ) ); ?></label>
                  <input type="text" id="sr_area_preference" name="sr_area_preference" class="cta-control" placeholder="<?php echo esc_attr( pera_ml_ui( 'e.g. Beşiktaş, Şişli, Kadıköy', 'theme.template.page_book_a_consultancy.placeholder.e_g_be_ikta_i_li_kad_k_y' ) ); ?>">
                </div>

                <div class="cta-field">
                  <label class="cta-label" for="sr_notes"><?php echo esc_html( pera_ml_ui( 'Notes / questions', 'theme.template.page_book_a_consultancy.notes_questions' ) ); ?></label>
                  <textarea id="sr_notes" name="sr_notes" rows="4" class="cta-control" placeholder="<?php echo esc_attr( pera_ml_ui( 'Tell us what you want to cover during the call.', 'theme.template.page_book_a_consultancy.placeholder.tell_us_what_you_want_to_cover_during_the_call' ) ); ?>"></textarea>
                </div>

                <div class="enquiry-cta-footer">
                  <label class="cta-checkbox">
                    <input type="checkbox" name="sr_consent" value="1" required>
                    <span>
                      <?php echo esc_html( pera_ml_ui( 'I agree for Pera Property to contact me regarding this enquiry and to
                      process my personal data in accordance with the', 'theme.template.page_book_a_consultancy.i_agree_for_pera_property_to_contact_me_regarding_this_enquiry_and_to_pr' ) ); ?>
                      <a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>" target="_blank" rel="noopener">
                        <?php echo esc_html( pera_ml_ui( 'Privacy Policy', 'theme.template.page_book_a_consultancy.privacy_policy' ) ); ?>
                      </a>.
                    </span>
                  </label>

                  <button type="submit" class="btn btn--solid btn--green">
                    <?php echo esc_html( pera_ml_ui( 'Send booking request', 'theme.template.page_book_a_consultancy.send_booking_request' ) ); ?>
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
        <h2><?php echo esc_html( pera_ml_ui( 'What happens next', 'theme.template.page_book_a_consultancy.what_happens_next' ) ); ?></h2>
        <p><?php echo esc_html( pera_ml_ui( 'We keep the process simple so you can move quickly and confidently.', 'theme.template.page_book_a_consultancy.we_keep_the_process_simple_so_you_can_move_quickly_and_confidently' ) ); ?></p>
      </div>

      <div class="feature-grid">
        <article class="feature-card">
          <div class="feature-card-header">
            <div class="pill pill--outline pill--sm"><?php echo esc_html( pera_ml_ui( 'Step 1', 'theme.template.page_book_a_consultancy.step_1' ) ); ?></div>
            <h3><?php echo esc_html( pera_ml_ui( 'We review your goals', 'theme.template.page_book_a_consultancy.we_review_your_goals' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p><?php echo esc_html( pera_ml_ui( 'Our team confirms your requirements, timeline, and preferred areas.', 'theme.template.page_book_a_consultancy.our_team_confirms_your_requirements_timeline_and_preferred_areas' ) ); ?></p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <div class="pill pill--outline pill--sm"><?php echo esc_html( pera_ml_ui( 'Step 2', 'theme.template.page_book_a_consultancy.step_2' ) ); ?></div>
            <h3><?php echo esc_html( pera_ml_ui( 'You meet your consultant', 'theme.template.page_book_a_consultancy.you_meet_your_consultant' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p><?php echo esc_html( pera_ml_ui( 'We run through market data, pricing, and options relevant to you.', 'theme.template.page_book_a_consultancy.we_run_through_market_data_pricing_and_options_relevant_to_you' ) ); ?></p>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <div class="pill pill--outline pill--sm"><?php echo esc_html( pera_ml_ui( 'Step 3', 'theme.template.page_book_a_consultancy.step_3' ) ); ?></div>
            <h3><?php echo esc_html( pera_ml_ui( 'We send your tailored plan', 'theme.template.page_book_a_consultancy.we_send_your_tailored_plan' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p><?php echo esc_html( pera_ml_ui( 'Expect a curated shortlist and next-step checklist within 48 hours.', 'theme.template.page_book_a_consultancy.expect_a_curated_shortlist_and_next_step_checklist_within_48_hours' ) ); ?></p>
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
              <h2><?php echo esc_html( pera_ml_ui( 'Why Pera Property', 'theme.template.page_book_a_consultancy.why_pera_property' ) ); ?></h2>
              <p>
                <?php echo esc_html( pera_ml_ui( 'We blend local intelligence with international investor standards, so every
                recommendation is grounded in real data and on-the-ground experience.', 'theme.template.page_book_a_consultancy.we_blend_local_intelligence_with_international_investor_standards_so_eve' ) ); ?>
              </p>
            </header>

            <ul class="checklist checklist--circle">
              <li>
                <?php echo esc_html( pera_ml_ui( 'Istanbul-specific insights from a dedicated advisory team', 'theme.template.page_book_a_consultancy.istanbul_specific_insights_from_a_dedicated_advisory_team' ) ); ?>
              </li>
              <li>
                <?php echo esc_html( pera_ml_ui( 'Multi-lingual support and clear documentation', 'theme.template.page_book_a_consultancy.multi_lingual_support_and_clear_documentation' ) ); ?>
              </li>
              <li>
                <?php echo esc_html( pera_ml_ui( 'Access to trusted legal, banking, and residency partners', 'theme.template.page_book_a_consultancy.access_to_trusted_legal_banking_and_residency_partners' ) ); ?>
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
                <h5><?php echo esc_html( pera_ml_ui( 'Meet your consultants', 'theme.template.page_book_a_consultancy.meet_your_consultants' ) ); ?></h5>
                <p>
                  <?php echo esc_html( pera_ml_ui( 'Your call is led by a senior consultant who has guided hundreds of buyers and
                  investors across Istanbul’s top districts.', 'theme.template.page_book_a_consultancy.your_call_is_led_by_a_senior_consultant_who_has_guided_hundreds_of_buyer' ) ); ?>
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- FAQ -->
    <section class="faq-section section" id="faq">
      <div class="container">
        <h2><?php echo esc_html( pera_ml_ui( 'Frequently asked questions', 'theme.template.page_book_a_consultancy.frequently_asked_questions' ) ); ?></h2>
        <p><?php echo esc_html( pera_ml_ui( 'Quick answers to common consultancy questions.', 'theme.template.page_book_a_consultancy.quick_answers_to_common_consultancy_questions' ) ); ?></p>

        <div class="faq-accordion">
          <details class="faq-item" open>
            <summary><?php echo esc_html( pera_ml_ui( 'How much does the consultancy call cost?', 'theme.template.page_book_a_consultancy.how_much_does_the_consultancy_call_cost' ) ); ?></summary>
            <div class="faq-answer">
              <p><?php echo esc_html( pera_ml_ui( 'Our initial consultancy calls are complimentary and focused on giving you clarity.', 'theme.template.page_book_a_consultancy.our_initial_consultancy_calls_are_complimentary_and_focused_on_giving_yo' ) ); ?></p>
            </div>
          </details>

          <details class="faq-item">
            <summary><?php echo esc_html( pera_ml_ui( 'Can I bring a friend or family member?', 'theme.template.page_book_a_consultancy.can_i_bring_a_friend_or_family_member' ) ); ?></summary>
            <div class="faq-answer">
              <p><?php echo esc_html( pera_ml_ui( 'Yes. Let us know in advance and we will share the meeting link accordingly.', 'theme.template.page_book_a_consultancy.yes_let_us_know_in_advance_and_we_will_share_the_meeting_link_accordingl' ) ); ?></p>
            </div>
          </details>

          <details class="faq-item">
            <summary><?php echo esc_html( pera_ml_ui( 'Do you support remote or in-person meetings?', 'theme.template.page_book_a_consultancy.do_you_support_remote_or_in_person_meetings' ) ); ?></summary>
            <div class="faq-answer">
              <p><?php echo esc_html( pera_ml_ui( 'We primarily host calls online, but in-person meetings in Istanbul can be arranged.', 'theme.template.page_book_a_consultancy.we_primarily_host_calls_online_but_in_person_meetings_in_istanbul_can_be' ) ); ?></p>
            </div>
          </details>

          <details class="faq-item">
            <summary><?php echo esc_html( pera_ml_ui( 'What if I need a translator?', 'theme.template.page_book_a_consultancy.what_if_i_need_a_translator' ) ); ?></summary>
            <div class="faq-answer">
              <p><?php echo esc_html( pera_ml_ui( 'We can provide bilingual consultants or translators for Arabic, Russian, and Turkish.', 'theme.template.page_book_a_consultancy.we_can_provide_bilingual_consultants_or_translators_for_arabic_russian_a' ) ); ?></p>
            </div>
          </details>
        </div>
      </div>
    </section>

    <!-- FINAL CTA STRIP -->
    <section class="section section-soft" id="final-cta">
      <div class="section-header section-header--center">
        <h2><?php echo esc_html( pera_ml_ui( 'Ready to speak with a consultant?', 'theme.template.page_book_a_consultancy.ready_to_speak_with_a_consultant' ) ); ?></h2>
        <p><?php echo esc_html( pera_ml_ui( 'Secure your slot or reach us directly for urgent questions.', 'theme.template.page_book_a_consultancy.secure_your_slot_or_reach_us_directly_for_urgent_questions' ) ); ?></p>
      </div>

      <div class="hero-actions flex-center">
        <a href="#booking" class="btn btn--solid btn--green"><?php echo esc_html( pera_ml_ui( 'Book your session', 'theme.template.page_book_a_consultancy.book_your_session' ) ); ?></a>
        <a
          href="<?php echo esc_url( pera_get_whatsapp_url( pera_ml_ui( 'Hello I would like to book a consultancy call with Pera Property', 'theme.template.page_book_a_consultancy.whatsapp_prefill' ) ) ); ?>"
          target="_blank"
          rel="noopener" data-whatsapp="1" data-whatsapp-type="service_cta" data-track-channel="whatsapp" data-track-intent="high" data-track-source="template" data-track-context="book_consultancy" data-track-ga4-event="whatsapp_click" data-track-crm-event="whatsapp_click"
          class="btn btn--solid btn--blue"
        >
          WhatsApp our team
        </a>
      </div>
    </section>

</main>

<?php
get_footer();
