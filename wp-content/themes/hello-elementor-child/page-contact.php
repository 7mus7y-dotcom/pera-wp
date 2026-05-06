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
            <h1>Contact an Istanbul Real Estate Consultant</h1>
        
            <p class="lead">
              Speak with Pera Property, an Istanbul real estate agency helping international buyers, investors and property owners make informed decisions across the city. Whether you want to buy property in Istanbul, sell your apartment, rent out your property or understand Turkish citizenship investment options, our consultants can guide you clearly from the first conversation.
            </p>
        
            <div class="hero-actions">
              <a href="tel:+905320639978" class="btn btn--solid btn--blue">Call us</a>
              <a href="https://wa.me/905320639978" class="btn btn--solid btn--green" target="_blank" rel="noopener">
                  <svg class="icon">
                    <use href="<?php echo get_stylesheet_directory_uri(); ?>/logos-icons/icons.svg#icon-whatsapp"></use>
                  </svg> WhatsApp
              </a>
            </div>
          </div>
        
        </section>


  <!-- SEO SUPPORTING CONTENT -->
  <section class="section" id="contact-help">
    <div class="container">
      <div class="card-shell">
        <h2>How we can help</h2>
        <ul class="checklist">
          <li>Buying property in Istanbul, including apartments, villas and investment property.</li>
          <li>Selling your Istanbul property with local market guidance and valuation support.</li>
          <li>Renting out or managing your property for long-term or corporate tenants.</li>
          <li>Turkish citizenship by investment consultation and property selection.</li>
          <li>District and investment strategy guidance across Istanbul neighbourhoods.</li>
        </ul>
      </div>
    </div>
  </section>


  <section class="section" id="popular-districts">
    <div class="container">

      <div class="section-header">
        <h2>Popular Istanbul districts we advise on</h2>
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
            <h2>VISIT OUR ISTANBUL OFFICE</h2>
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

<?php get_template_part( 'parts/our-services-card' ); ?>

  

</main>

<?php
get_footer();
