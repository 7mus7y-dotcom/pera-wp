<?php
/**
 * Template Name: Contact Us (New)
 * Custom Contact page using lean header/footer + main.css hero
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$contact_faq_items = array(
    array(
        'question' => 'Can I contact Pera Property in English?',
        'answer'   => 'Yes. Our English-speaking property consultants regularly advise international buyers, sellers and landlords interested in Istanbul real estate.',
    ),
    array(
        'question' => 'Can you help me buy property in Istanbul remotely?',
        'answer'   => 'Yes. We can discuss your brief, shortlist suitable properties, arrange video calls or virtual viewings, and explain the steps before you travel or appoint a representative.',
    ),
    array(
        'question' => 'Do you help with Turkish citizenship property purchases?',
        'answer'   => 'Yes. We advise on Turkish citizenship property enquiries, including suitable real estate options, investment thresholds and the practical purchase process with specialist legal support where needed.',
    ),
    array(
        'question' => 'Can I visit your Istanbul office?',
        'answer'   => 'Yes. Our office is at Ömer Avni, Balçık Sk. No:6, 34437 Beyoğlu/İstanbul. Appointments are recommended so the right consultant is available for your enquiry.',
    ),
    array(
        'question' => 'Can you help sell or rent out my Istanbul property?',
        'answer'   => 'Yes. We help owners with valuation advice, sales marketing, tenant search, rental management and practical guidance for selling or renting property in Istanbul.',
    ),
    array(
        'question' => 'Which Istanbul districts do you cover?',
        'answer'   => 'We advise across Istanbul, including central European-side areas such as Beşiktaş, Şişli, Sarıyer and Beyoğlu, plus Asian-side districts such as Kadıköy and Üsküdar.',
    ),
);

add_action(
    'wp_head',
    static function () use ( $contact_faq_items ) {
        $contact_faq_entities = array();

        foreach ( $contact_faq_items as $contact_faq_item ) {
            if ( empty( $contact_faq_item['question'] ) || empty( $contact_faq_item['answer'] ) ) {
                continue;
            }

            $contact_faq_entities[] = array(
                '@type'          => 'Question',
                'name'           => (string) $contact_faq_item['question'],
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text'  => (string) $contact_faq_item['answer'],
                ),
            );
        }

        if ( empty( $contact_faq_entities ) ) {
            return;
        }

        $contact_faq_schema = array(
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $contact_faq_entities,
        );

        $GLOBALS['pera_schema_faq_emitted'] = true;
        echo '<script type="application/ld+json">' . wp_json_encode( $contact_faq_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
    }
);

get_header();
?>

        <!-- =====================================================
         HERO – CONTACT PAGE
         Canonical structure + WP image ID 55756
         ===================================================== -->
        <section class="hero hero--left hero--contact" id="contact-hero">
        
          <div class="hero__media" aria-hidden="true">
            <?php
              // Prefer a featured image if you ever add one; otherwise fallback to vopbesiktas.svg (ID 55756)
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
                    'class'         => 'hero-media',
                    'fetchpriority' => 'high',
                    'loading'       => 'eager',
                    'decoding'      => 'async',
                  )
                );
              }
            ?>
            <div class="hero-overlay" aria-hidden="true"></div>
          </div>
        
          <div class="hero-content">
            <h1><?php echo esc_html( pera_ml_ui( 'Contact Pera Property — Istanbul Real Estate Agency & Property Consultants', 'theme.template.page_contact.contact_pera_property_istanbul_real_estate_agency_and_property_consultan' ) ); ?></h1>
        
            <p class="lead">
              <?php echo esc_html( pera_ml_ui( 'Speak with Pera Property, an Istanbul-based real estate agency and team of English-speaking property consultants helping international buyers, sellers and landlords make clear decisions about property investment in Istanbul. Whether you want to buy, sell, rent out your property or discuss Turkish citizenship property enquiries, our consultants can guide you from the first conversation.', 'theme.template.page_contact.speak_with_pera_property_an_istanbul_based_real_estate_agency_and_team_o' ) ); ?>
            </p>
        
            <div class="hero-actions">
              <a href="tel:+905320639978" class="btn btn--solid btn--blue"><?php echo esc_html( pera_ml_ui( 'Call Our Istanbul Office', 'theme.template.page_contact.call_our_istanbul_office' ) ); ?></a>
              <a
                href="<?php echo esc_url( pera_get_whatsapp_url( pera_ml_ui( 'Hi, I would like to speak with an Istanbul property consultant about buying, selling or investing in property.', 'theme.template.page_contact.whatsapp_prefill' ) ) ); ?>"
                class="btn btn--solid btn--green"
                target="_blank"
                rel="noopener"
                data-whatsapp="1"
                data-whatsapp-type="contact_cta"
                data-track-channel="whatsapp"
                data-track-intent="high"
                data-track-source="template"
                data-track-context="contact_hero"
                data-track-ga4-event="whatsapp_click"
                data-track-crm-event="whatsapp_click">
                  <svg class="icon" aria-hidden="true">
                    <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-whatsapp' ); ?>"></use>
                  </svg> <?php echo esc_html( pera_ml_ui( 'WhatsApp Our Team', 'theme.template.page_contact.whatsapp_our_team' ) ); ?>
              </a>
            </div>
          </div>
        
        </section>


  <section class="section section--compact" id="contact-trust" aria-label="<?php echo esc_attr( pera_ml_ui( 'Why clients contact Pera Property', 'theme.template.page_contact.aria_label.why_clients_contact_pera_property' ) ); ?>">
    <div class="container">
      <div class="contact-trust-grid">
        <div class="contact-trust-card"><?php echo esc_html( pera_ml_ui( 'Istanbul-based since 2016', 'theme.template.page_contact.istanbul_based_since_2016' ) ); ?></div>
        <div class="contact-trust-card"><?php echo esc_html( pera_ml_ui( 'English-speaking consultants', 'theme.template.page_contact.english_speaking_consultants' ) ); ?></div>
        <div class="contact-trust-card"><?php echo esc_html( pera_ml_ui( 'Licensed real estate agency in Turkey', 'theme.template.page_contact.licensed_real_estate_agency_in_turkey' ) ); ?></div>
        <div class="contact-trust-card"><?php echo esc_html( pera_ml_ui( 'Office near Taksim and Dolmabahçe', 'theme.template.page_contact.office_near_taksim_and_dolmabah_e' ) ); ?></div>
      </div>
    </div>
  </section>


  <!-- SEO SUPPORTING CONTENT -->
  <section class="section" id="contact-help">
    <div class="container">
      <div class="card-shell">
        <h2><?php echo esc_html( pera_ml_ui( 'Why contact Pera Property?', 'theme.template.page_contact.why_contact_pera_property' ) ); ?></h2>
        <p>
          <?php echo esc_html( pera_ml_ui( 'As an Istanbul real estate agency, Pera Property gives foreign buyers, sellers and landlords direct access to property consultants in Istanbul who understand the local market, legal process and long-term investment considerations.', 'theme.template.page_contact.as_an_istanbul_real_estate_agency_pera_property_gives_foreign_buyers_sel' ) ); ?>
        </p>
        <div class="contact-help-grid">
          <article class="contact-help-card">
            <h3><?php echo esc_html( pera_ml_ui( 'Buying in Istanbul', 'theme.template.page_contact.buying_in_istanbul' ) ); ?></h3>
            <p><?php echo esc_html( pera_ml_ui( 'Advice on districts, budgets, negotiation and suitable property options for foreign buyers.', 'theme.template.page_contact.advice_on_districts_budgets_negotiation_and_suitable_property_options_fo' ) ); ?></p>
          </article>
          <article class="contact-help-card">
            <h3><?php echo esc_html( pera_ml_ui( 'Selling Property', 'theme.template.page_contact.selling_property' ) ); ?></h3>
            <p><?php echo esc_html( pera_ml_ui( 'Valuation guidance, marketing advice and qualified buyer introductions for Istanbul property owners.', 'theme.template.page_contact.valuation_guidance_marketing_advice_and_qualified_buyer_introductions_fo' ) ); ?></p>
          </article>
          <article class="contact-help-card">
            <h3><?php echo esc_html( pera_ml_ui( 'Renting & Management', 'theme.template.page_contact.renting_and_management' ) ); ?></h3>
            <p><?php echo esc_html( pera_ml_ui( 'Support for landlords looking to rent, manage or protect their Istanbul property.', 'theme.template.page_contact.support_for_landlords_looking_to_rent_manage_or_protect_their_istanbul_p' ) ); ?></p>
          </article>
          <article class="contact-help-card">
            <h3><?php echo esc_html( pera_ml_ui( 'Citizenship Enquiries', 'theme.template.page_contact.citizenship_enquiries' ) ); ?></h3>
            <p><?php echo esc_html( pera_ml_ui( 'Guidance on Turkish citizenship by investment, compliant property selection and practical next steps.', 'theme.template.page_contact.guidance_on_turkish_citizenship_by_investment_compliant_property_selecti' ) ); ?></p>
          </article>
        </div>
      </div>
    </div>
  </section>


  <section class="section" id="popular-districts">
    <div class="container">

      <div class="section-header">
        <h2><?php echo esc_html( pera_ml_ui( 'Areas We Cover in Istanbul', 'theme.template.page_contact.areas_we_cover_in_istanbul' ) ); ?></h2>
        <p>
          <?php echo esc_html( pera_ml_ui( 'Our consultants regularly advise international buyers, investors and sellers across Istanbul’s most established residential and investment districts.', 'theme.template.page_contact.our_consultants_regularly_advise_international_buyers_investors_and_sell' ) ); ?>
        </p>
      </div>

      <div class="wp-block-table wp-block-table--responsive">
        <table>
          <thead>
            <tr>
              <th><?php echo esc_html( pera_ml_ui( 'District', 'theme.template.page_contact.district' ) ); ?></th>
              <th><?php echo esc_html( pera_ml_ui( 'Why buyers ask about it', 'theme.template.page_contact.why_buyers_ask_about_it' ) ); ?></th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>
                <a href="<?php echo esc_url( home_url( '/district/istanbul/besiktas/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Beşiktaş property', 'theme.template.page_contact.be_ikta_property' ) ); ?></a>
              </td>
              <td><?php echo esc_html( pera_ml_ui( 'Central Bosphorus living close to Nişantaşı, Dolmabahçe and business districts.', 'theme.template.page_contact.central_bosphorus_living_close_to_ni_anta_dolmabah_e_and_business_distri' ) ); ?></td>
            </tr>
            <tr>
              <td>
                <a href="<?php echo esc_url( home_url( '/district/istanbul/sisli/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Şişli property', 'theme.template.page_contact.i_li_property' ) ); ?></a>
              </td>
              <td><?php echo esc_html( pera_ml_ui( 'Modern city living with luxury residences, offices and shopping districts.', 'theme.template.page_contact.modern_city_living_with_luxury_residences_offices_and_shopping_districts' ) ); ?></td>
            </tr>
            <tr>
              <td>
                <a href="<?php echo esc_url( home_url( '/district/istanbul/sariyer/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Sarıyer property', 'theme.template.page_contact.sar_yer_property' ) ); ?></a>
              </td>
              <td><?php echo esc_html( pera_ml_ui( 'Bosphorus villas, waterfront homes and premium northern Istanbul districts.', 'theme.template.page_contact.bosphorus_villas_waterfront_homes_and_premium_northern_istanbul_district' ) ); ?></td>
            </tr>
            <tr>
              <td>
                <a href="<?php echo esc_url( home_url( '/district/istanbul/beyoglu/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Beyoğlu property', 'theme.template.page_contact.beyo_lu_property' ) ); ?></a>
              </td>
              <td><?php echo esc_html( pera_ml_ui( 'Historic Istanbul neighbourhoods including Galata, Cihangir and Taksim.', 'theme.template.page_contact.historic_istanbul_neighbourhoods_including_galata_cihangir_and_taksim' ) ); ?></td>
            </tr>
            <tr>
              <td>
                <a href="<?php echo esc_url( home_url( '/district/istanbul/kadikoy/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Kadıköy property', 'theme.template.page_contact.kad_k_y_property' ) ); ?></a>
              </td>
              <td><?php echo esc_html( pera_ml_ui( 'Popular Asian-side lifestyle districts with strong long-term demand.', 'theme.template.page_contact.popular_asian_side_lifestyle_districts_with_strong_long_term_demand' ) ); ?></td>
            </tr>
            <tr>
              <td>
                <a href="<?php echo esc_url( home_url( '/district/istanbul/uskudar/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Üsküdar property', 'theme.template.page_contact.sk_dar_property' ) ); ?></a>
              </td>
              <td><?php echo esc_html( pera_ml_ui( 'Traditional Bosphorus neighbourhoods including Kandilli and Çengelköy.', 'theme.template.page_contact.traditional_bosphorus_neighbourhoods_including_kandilli_and_engelk_y' ) ); ?></td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </section>


  <!-- CONTACT DETAILS + MAP -->
  <section class="section" id="contact_details">
    <div class="container">
      <div class="content-panel-grid">

        <!-- LEFT: DETAILS -->
        <div class="content-panel-left">

          <div class="section-header">
            <h2><?php echo esc_html( pera_ml_ui( 'Visit Our Istanbul Office', 'theme.template.page_contact.visit_our_istanbul_office' ) ); ?></h2>
            <p>
              <?php echo esc_html( pera_ml_ui( 'Our consultants are based at Ömer Avni, Balçık Sk. No:6, 34437 Beyoğlu/İstanbul.', 'theme.template.page_contact.our_consultants_are_based_at_omer_avni_balcik_sk_no_6' ) ); ?>
            </p>
          </div>

          <div class="stacked-text">

         
            <div class="contact-details">
              <h3><?php echo esc_html( pera_ml_ui( 'Telephone', 'theme.template.page_contact.telephone' ) ); ?></h3>
            
              <div class="contact-card">
                <div class="contact-card__icon" aria-hidden="true">
                  <svg class="icon">
                    <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-whatsapp"></use>
                  </svg>
                </div>
            
                <div class="contact-card__body">
                  <a href="tel:+905320639978" class="contact-card__number">+90 532 063 99 78</a>
                  <a
                    href="<?php echo esc_url( pera_get_whatsapp_url() ); ?>"
                    class="contact-card__action"
                    target="_blank"
                    rel="noopener"
                    data-whatsapp="1"
                    data-whatsapp-type="agent_card"
                    data-track-channel="whatsapp"
                    data-track-intent="medium"
                    data-track-source="template"
                    data-track-context="contact_agent_card"
                    data-track-ga4-event="whatsapp_click"
                    data-track-crm-event="whatsapp_click">
                    <?php echo esc_html( pera_ml_ui( 'Message on WhatsApp', 'theme.template.page_contact.message_on_whatsapp' ) ); ?>
                  </a>
                </div>
              </div>
            
              <div class="contact-card">
                <div class="contact-card__icon" aria-hidden="true">
                  <svg class="icon">
                    <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-whatsapp"></use>
                  </svg>
                </div>
            
                <div class="contact-card__body">
                  <a href="tel:+905452054356" class="contact-card__number">+90 545 205 43 56</a>
                  <a
                    href="<?php echo esc_url( pera_get_whatsapp_url() ); ?>"
                    class="contact-card__action"
                    target="_blank"
                    rel="noopener"
                    data-whatsapp="1"
                    data-whatsapp-type="agent_card"
                    data-track-channel="whatsapp"
                    data-track-intent="medium"
                    data-track-source="template"
                    data-track-context="contact_agent_card"
                    data-track-ga4-event="whatsapp_click"
                    data-track-crm-event="whatsapp_click">
                    <?php echo esc_html( pera_ml_ui( 'Message on WhatsApp', 'theme.template.page_contact.message_on_whatsapp' ) ); ?>
                  </a>
                </div>
              </div>
            </div>



        
          <h3><?php echo esc_html( pera_ml_ui( 'Email', 'theme.template.page_contact.email' ) ); ?></h3>
          <p>
            <a href="mailto:info@peraproperty.com"><?php echo esc_html( pera_ml_ui( 'info@peraproperty.com', 'theme.template.page_contact.info_peraproperty_com' ) ); ?></a>
          </p>
        
          <h3><?php echo esc_html( pera_ml_ui( 'Address', 'theme.template.page_contact.address' ) ); ?></h3>
          <p>
            <?php echo esc_html( pera_ml_ui( 'Ömer Avni, Balçık Sk. No:6, 34437 Beyoğlu/İstanbul', 'theme.template.page_contact.omer_avni_balcik_sk_no_6_34437_beyoglu_istanbul' ) ); ?>
          </p>
        
          <h3><?php echo esc_html( pera_ml_ui( 'Working hours', 'theme.template.page_contact.working_hours' ) ); ?></h3>
          <p>
            <?php echo esc_html( pera_ml_ui( 'Monday – Friday: 09:30 – 18:00', 'theme.template.page_contact.monday_friday_09_30_18_00' ) ); ?><br>
            <?php echo esc_html( pera_ml_ui( 'Saturday & Sunday: By appointment only', 'theme.template.page_contact.saturday_and_sunday_by_appointment_only' ) ); ?>
          </p>
        
          <a href="https://www.google.com/maps/search/?api=1&amp;query=%C3%96mer%20Avni%2C%20Bal%C3%A7%C4%B1k%20Sk.%20No%3A6%2C%2034437%20Beyo%C4%9Flu%2F%C4%B0stanbul"
             class="btn btn--solid btn--blue btn-card"
             target="_blank" rel="noopener">
            <?php echo esc_html( pera_ml_ui( 'Get Directions', 'theme.template.page_contact.get_directions' ) ); ?>
          </a>
        
        </div>

        </div>

        <!-- RIGHT: GOOGLE MAP -->
        <div class="content-panel-right">
            <div class="media-frame media-frame--map">
                <iframe
                class="media-embed media-embed--map"
                src="https://www.google.com/maps?q=%C3%96mer%20Avni%2C%20Bal%C3%A7%C4%B1k%20Sk.%20No%3A6%2C%2034437%20Beyo%C4%9Flu%2F%C4%B0stanbul&amp;output=embed"
                  style="border:0;"
                  allowfullscreen=""
                  loading="lazy"
                  referrerpolicy="no-referrer-when-downgrade"
                ></iframe>
          </div>
        </div>



      </div><!-- /.content-panel-grid -->
    </div><!-- /.content-panel-box -->
  </section>


  <!-- USEFUL RESOURCES -->
  <section class="section" id="contact-resources">
    <div class="container">
      <header class="section-header section-header--center">
        <h2><?php echo esc_html( pera_ml_ui( 'Useful Resources Before Contacting Us', 'theme.template.page_contact.useful_resources_before_contacting_us' ) ); ?></h2>
        <p>
          <?php echo esc_html( pera_ml_ui( 'If you are still comparing options, these guides and search pages can help you prepare clearer questions for our Istanbul property consultants.', 'theme.template.page_contact.if_you_are_still_comparing_options_these_guides_and_search_pages_can_hel' ) ); ?>
        </p>
      </header>

      <div class="feature-grid">
        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'Browse Istanbul Properties', 'theme.template.page_contact.browse_istanbul_properties' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p><?php echo esc_html( pera_ml_ui( 'Review current listings before asking our team about suitable districts, budgets and availability.', 'theme.template.page_contact.review_current_listings_before_asking_our_team_about_suitable_districts_' ) ); ?></p>
          </div>
          <div class="feature-card-footer">
            <a href="<?php echo esc_url( home_url( '/property/' ) ); ?>" class="btn btn--solid btn--blue"><?php echo esc_html( pera_ml_ui( 'Search property for sale in Istanbul', 'theme.template.page_contact.search_property_for_sale_in_istanbul' ) ); ?></a>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'Istanbul Buyer Guides', 'theme.template.page_contact.istanbul_buyer_guides' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p><?php echo esc_html( pera_ml_ui( 'Read market explainers, buying advice and local insight before speaking with a consultant.', 'theme.template.page_contact.read_market_explainers_buying_advice_and_local_insight_before_speaking_w' ) ); ?></p>
          </div>
          <div class="feature-card-footer">
            <a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>" class="btn btn--solid btn--blue"><?php echo esc_html( pera_ml_ui( 'Read Istanbul real estate guides', 'theme.template.page_contact.read_istanbul_real_estate_guides' ) ); ?></a>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'Beşiktaş Area Advice', 'theme.template.page_contact.be_ikta_area_advice' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p><?php echo esc_html( pera_ml_ui( 'Explore one of Istanbul’s most requested central districts before discussing neighbourhood fit.', 'theme.template.page_contact.explore_one_of_istanbul_s_most_requested_central_districts_before_discus' ) ); ?></p>
          </div>
          <div class="feature-card-footer">
            <a href="<?php echo esc_url( home_url( '/district/istanbul/besiktas/' ) ); ?>" class="btn btn--solid btn--blue"><?php echo esc_html( pera_ml_ui( 'View Beşiktaş property guidance', 'theme.template.page_contact.view_be_ikta_property_guidance' ) ); ?></a>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'Citizenship Property Advice', 'theme.template.page_contact.citizenship_property_advice' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p><?php echo esc_html( pera_ml_ui( 'Understand the Turkish citizenship by investment property route before sending your enquiry.', 'theme.template.page_contact.understand_the_turkish_citizenship_by_investment_property_route_before_s' ) ); ?></p>
          </div>
          <div class="feature-card-footer">
            <a href="<?php echo esc_url( home_url( '/turkish-citizenship-by-real-estate-investment_6292/' ) ); ?>" class="btn btn--solid btn--blue"><?php echo esc_html( pera_ml_ui( 'Read Turkish citizenship property guidance', 'theme.template.page_contact.read_turkish_citizenship_property_guidance' ) ); ?></a>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'Selling in Istanbul', 'theme.template.page_contact.selling_in_istanbul' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p><?php echo esc_html( pera_ml_ui( 'Learn how Pera Property supports owners who want valuation, marketing and sales advice.', 'theme.template.page_contact.learn_how_pera_property_supports_owners_who_want_valuation_marketing_and' ) ); ?></p>
          </div>
          <div class="feature-card-footer">
            <a href="<?php echo esc_url( home_url( '/sell-your-istanbul-real-estate/' ) ); ?>" class="btn btn--solid btn--blue"><?php echo esc_html( pera_ml_ui( 'Get help selling property in Istanbul', 'theme.template.page_contact.get_help_selling_property_in_istanbul' ) ); ?></a>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3><?php echo esc_html( pera_ml_ui( 'Renting Out Your Property', 'theme.template.page_contact.renting_out_your_property' ) ); ?></h3>
          </div>
          <div class="feature-card-body">
            <p><?php echo esc_html( pera_ml_ui( 'See how our rental and management service helps landlords protect and manage Istanbul homes.', 'theme.template.page_contact.see_how_our_rental_and_management_service_helps_landlords_protect_and_ma' ) ); ?></p>
          </div>
          <div class="feature-card-footer">
            <a href="<?php echo esc_url( home_url( '/rent-your-istanbul-real-estate/' ) ); ?>" class="btn btn--solid btn--blue"><?php echo esc_html( pera_ml_ui( 'Explore Istanbul rental management services', 'theme.template.page_contact.explore_istanbul_rental_management_services' ) ); ?></a>
          </div>
        </article>
      </div>
    </div>
  </section>


  <!-- FAQ -->
  <section class="faq-section section" id="contact-faq">
    <div class="container">
      <h2><?php echo esc_html( pera_ml_ui( 'Contact Pera Property FAQs', 'theme.template.page_contact.contact_pera_property_faqs' ) ); ?></h2>

      <div class="faq-accordion">
        <?php foreach ( $contact_faq_items as $contact_faq_index => $contact_faq_item ) : ?>
          <details class="faq-item"<?php echo $contact_faq_index === 0 ? ' open' : ''; ?>>
            <summary><?php echo esc_html( $contact_faq_item['question'] ); ?></summary>
            <div class="faq-answer">
              <?php echo wp_kses_post( wpautop( $contact_faq_item['answer'] ) ); ?>
            </div>
          </details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>


<?php get_template_part( 'parts/our-services-card' ); ?>

  

</main>

<?php
get_footer();
