<?php
/**
 * Template Name: Contact Us (New)
 * Custom Contact page using lean header/footer + main.css hero
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
            <h1>Contact Pera Property — Istanbul Real Estate Agency &amp; Property Consultants</h1>
        
            <p class="lead">
              Speak with Pera Property, an Istanbul-based real estate agency and team of English-speaking property consultants helping international buyers, sellers and landlords make clear decisions about property investment in Istanbul. Whether you want to buy, sell, rent out your property or discuss Turkish citizenship property enquiries, our consultants can guide you from the first conversation.
            </p>
        
            <div class="hero-actions">
              <a href="tel:+905320639978" class="btn btn--solid btn--blue">Call Our Istanbul Office</a>
              <a href="<?php echo esc_url( add_query_arg( 'text', 'Hi, I would like to speak with an Istanbul property consultant about buying, selling or investing in property.', 'https://wa.me/905320639978' ) ); ?>" class="btn btn--solid btn--green" target="_blank" rel="noopener">
                  <svg class="icon" aria-hidden="true">
                    <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-whatsapp' ); ?>"></use>
                  </svg> WhatsApp an Istanbul Property Consultant
              </a>
            </div>

            <div class="hero-actions" aria-label="Why clients contact Pera Property">
              <span class="pill pill--subtle">Istanbul-based since 2016</span>
              <span class="pill pill--subtle">English-speaking consultants</span>
              <span class="pill pill--subtle">Licensed real estate agency in Turkey</span>
              <span class="pill pill--subtle">Office near Taksim and Dolmabah&ccedil;e</span>
            </div>
          </div>
        
        </section>


  <!-- SEO SUPPORTING CONTENT -->
  <section class="section" id="contact-help">
    <div class="container">
      <div class="card-shell">
        <h2>Why contact Pera Property?</h2>
        <p>
          As an Istanbul real estate agency, Pera Property gives foreign buyers, sellers and landlords direct access to property consultants in Istanbul who understand the local market, legal process and long-term investment considerations.
        </p>
        <ul class="checklist">
          <li>Discuss buying property in Istanbul, including apartments, villas and investment properties that match your budget and lifestyle goals.</li>
          <li>Get clear advice from Istanbul property consultants on districts, pricing, negotiation and the next steps for foreign buyers.</li>
          <li>Plan the sale of your Istanbul property with local market guidance, valuation support and qualified buyer introductions.</li>
          <li>Explore renting out or managing your property in Istanbul for long-term, corporate or carefully selected tenants.</li>
          <li>Ask about Turkish citizenship by investment, suitable property selection and the practical documents needed for a compliant purchase.</li>
        </ul>
      </div>
    </div>
  </section>


  <section class="section" id="popular-districts">
    <div class="container">

      <div class="section-header">
        <h2>Areas We Cover in Istanbul</h2>
        <p>
          Our consultants regularly advise international buyers, investors and sellers across Istanbul’s most established residential and investment districts.
        </p>
      </div>

      <div class="wp-block-table wp-block-table--responsive">
        <table>
          <thead>
            <tr>
              <th>District</th>
              <th>Why buyers ask about it</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>
                <a href="<?php echo esc_url( home_url( '/district/istanbul/besiktas/' ) ); ?>">Beşiktaş property</a>
              </td>
              <td>Central Bosphorus living close to Nişantaşı, Dolmabahçe and business districts.</td>
            </tr>
            <tr>
              <td>
                <a href="<?php echo esc_url( home_url( '/district/istanbul/sisli/' ) ); ?>">Şişli property</a>
              </td>
              <td>Modern city living with luxury residences, offices and shopping districts.</td>
            </tr>
            <tr>
              <td>
                <a href="<?php echo esc_url( home_url( '/district/istanbul/sariyer/' ) ); ?>">Sarıyer property</a>
              </td>
              <td>Bosphorus villas, waterfront homes and premium northern Istanbul districts.</td>
            </tr>
            <tr>
              <td>
                <a href="<?php echo esc_url( home_url( '/district/istanbul/beyoglu/' ) ); ?>">Beyoğlu property</a>
              </td>
              <td>Historic Istanbul neighbourhoods including Galata, Cihangir and Taksim.</td>
            </tr>
            <tr>
              <td>
                <a href="<?php echo esc_url( home_url( '/district/istanbul/kadikoy/' ) ); ?>">Kadıköy property</a>
              </td>
              <td>Popular Asian-side lifestyle districts with strong long-term demand.</td>
            </tr>
            <tr>
              <td>
                <a href="<?php echo esc_url( home_url( '/district/istanbul/uskudar/' ) ); ?>">Üsküdar property</a>
              </td>
              <td>Traditional Bosphorus neighbourhoods including Kandilli and Çengelköy.</td>
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
            <h2>Visit Our Istanbul Office</h2>
            <p>
              Our consultants are based in G&uuml;m&uuml;şsuyu, just above the Bosphorus and
              a short walk from Taksim Square and Dolmabah&ccedil;e.
            </p>
          </div>

          <div class="stacked-text">

         
            <div class="contact-details">
              <h3>Telephone</h3>
            
              <div class="contact-card">
                <div class="contact-card__icon" aria-hidden="true">
                  <svg class="icon">
                    <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-whatsapp"></use>
                  </svg>
                </div>
            
                <div class="contact-card__body">
                  <a href="tel:+905320639978" class="contact-card__number">+90 532 063 99 78</a>
                  <a href="https://wa.me/905320639978" class="contact-card__action" target="_blank" rel="noopener">
                    Message on WhatsApp
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
                  <a href="https://wa.me/905452054356" class="contact-card__action" target="_blank" rel="noopener">
                    Message on WhatsApp
                  </a>
                </div>
              </div>
            </div>



        
          <h3>Email</h3>
          <p>
            <a href="mailto:info@peraproperty.com">info@peraproperty.com</a>
          </p>
        
          <h3>Address</h3>
          <p>
            G&uuml;m&uuml;şsuyu Mah. Ankara Palas, <br>
            İn&ouml;n&uuml; Cd. No 59/1, 34437 Beyoğlu / İstanbul
          </p>
        
          <h3>Working hours</h3>
          <p>
            Monday – Friday: 09:30 – 18:00<br>
            Saturday & Sunday: By appointment only
          </p>
        
          <a href="https://maps.app.goo.gl/QkLhU1YnNGQvEGr59"
             class="btn btn--solid btn--blue btn-card"
             target="_blank" rel="noopener">
            Get Directions
          </a>
        
        </div>

        </div>

        <!-- RIGHT: GOOGLE MAP -->
        <div class="content-panel-right">
            <div class="media-frame media-frame--map">
                <iframe
                class="media-embed media-embed--map"
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3009.4841913041314!2d28.989965700000003!3d41.036539499999996!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x14cab71b29d934f5%3A0x50e7f6acdbad5b91!2sPera%20Property!5e0!3m2!1sen!2str!4v1764243753511!5m2!1sen!2str"
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
        <h2>Useful Resources Before Contacting Us</h2>
        <p>
          If you are still comparing options, these guides and search pages can help you prepare clearer questions for our Istanbul property consultants.
        </p>
      </header>

      <div class="feature-grid">
        <article class="feature-card">
          <div class="feature-card-header">
            <h3>Browse Istanbul Properties</h3>
          </div>
          <div class="feature-card-body">
            <p>Review current listings before asking our team about suitable districts, budgets and availability.</p>
          </div>
          <div class="feature-card-footer">
            <a href="<?php echo esc_url( home_url( '/property/' ) ); ?>" class="btn btn--solid btn--blue">Search property for sale in Istanbul</a>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3>Istanbul Buyer Guides</h3>
          </div>
          <div class="feature-card-body">
            <p>Read market explainers, buying advice and local insight before speaking with a consultant.</p>
          </div>
          <div class="feature-card-footer">
            <a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>" class="btn btn--solid btn--blue">Read Istanbul real estate guides</a>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3>Beşiktaş Area Advice</h3>
          </div>
          <div class="feature-card-body">
            <p>Explore one of Istanbul’s most requested central districts before discussing neighbourhood fit.</p>
          </div>
          <div class="feature-card-footer">
            <a href="<?php echo esc_url( home_url( '/district/istanbul/besiktas/' ) ); ?>" class="btn btn--solid btn--blue">View Beşiktaş property guidance</a>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3>Citizenship Property Advice</h3>
          </div>
          <div class="feature-card-body">
            <p>Understand the Turkish citizenship by investment property route before sending your enquiry.</p>
          </div>
          <div class="feature-card-footer">
            <a href="<?php echo esc_url( home_url( '/turkish-citizenship-by-real-estate-investment_6292/' ) ); ?>" class="btn btn--solid btn--blue">Read Turkish citizenship property guidance</a>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3>Selling in Istanbul</h3>
          </div>
          <div class="feature-card-body">
            <p>Learn how Pera Property supports owners who want valuation, marketing and sales advice.</p>
          </div>
          <div class="feature-card-footer">
            <a href="<?php echo esc_url( home_url( '/sell-your-istanbul-real-estate/' ) ); ?>" class="btn btn--solid btn--blue">Get help selling property in Istanbul</a>
          </div>
        </article>

        <article class="feature-card">
          <div class="feature-card-header">
            <h3>Renting Out Your Property</h3>
          </div>
          <div class="feature-card-body">
            <p>See how our rental and management service helps landlords protect and manage Istanbul homes.</p>
          </div>
          <div class="feature-card-footer">
            <a href="<?php echo esc_url( home_url( '/rent-your-istanbul-real-estate/' ) ); ?>" class="btn btn--solid btn--blue">Explore Istanbul rental management services</a>
          </div>
        </article>
      </div>
    </div>
  </section>


  <!-- FAQ -->
  <section class="section" id="contact-faq">
    <div class="container">
      <div class="card-shell">
        <h2>Contact Pera Property FAQs</h2>

        <div class="stacked-text">
          <h3>Can I contact Pera Property in English?</h3>
          <p>Yes. Our English-speaking property consultants regularly advise international buyers, sellers and landlords interested in Istanbul real estate.</p>

          <h3>Can you help me buy property in Istanbul remotely?</h3>
          <p>Yes. We can discuss your brief, shortlist suitable properties, arrange video calls or virtual viewings, and explain the steps before you travel or appoint a representative.</p>

          <h3>Do you help with Turkish citizenship property purchases?</h3>
          <p>Yes. We advise on Turkish citizenship property enquiries, including suitable real estate options, investment thresholds and the practical purchase process with specialist legal support where needed.</p>

          <h3>Can I visit your Istanbul office?</h3>
          <p>Yes. Our office is in G&uuml;m&uuml;şsuyu, Beyoğlu, close to Taksim and Dolmabah&ccedil;e. Appointments are recommended so the right consultant is available for your enquiry.</p>

          <h3>Can you help sell or rent out my Istanbul property?</h3>
          <p>Yes. We help owners with valuation advice, sales marketing, tenant search, rental management and practical guidance for selling or renting property in Istanbul.</p>

          <h3>Which Istanbul districts do you cover?</h3>
          <p>We advise across Istanbul, including central European-side areas such as Beşiktaş, Şişli, Sarıyer and Beyoğlu, plus Asian-side districts such as Kadıköy and Üsküdar.</p>
        </div>
      </div>
    </div>
  </section>


<?php get_template_part( 'parts/our-services-card' ); ?>

  

</main>

<?php
get_footer();
