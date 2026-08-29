<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the site content and all content after.
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}
?>

<footer class="site-footer">
  <div class="footer-inner">
    <!-- COLUMN 1 -->
    <div class="footer-col">
      <h3><?php echo esc_html( pera_ml_ui( 'ABOUT PERA', 'theme.template.footer.about_pera' ) ); ?></h3>
      <p><?php echo esc_html( pera_ml_ui( 'Pera has helped hundreds of people buy, sell, and rent property in Istanbul!', 'theme.template.footer.pera_has_helped_hundreds_of_people_buy_sell_and_rent_property_in_istanbu' ) ); ?></p>
      <ul class="footer-links">
        <li><a href="<?php echo esc_url( pera_ml_url( home_url( '/about-us/' ) ) ); ?>"><?php echo esc_html( pera_ml_ui( 'About Pera Property', 'theme.template.footer.about_pera_property' ) ); ?></a></li>
        <li><a href="<?php echo esc_url( pera_ml_url( home_url( '/about-us/#meet_the_team' ) ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Meet the team', 'theme.template.footer.meet_the_team' ) ); ?></a></li>
        <li><a href="<?php echo esc_url( pera_ml_url( home_url( '/about-us/#why_pera' ) ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Why Pera Property?', 'theme.template.footer.why_pera_property' ) ); ?></a></li>
        <li><a href="<?php echo esc_url( pera_ml_url( home_url( '/about-us/#our_services' ) ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Our services', 'theme.template.footer.our_services' ) ); ?></a></li>
        <li><a href="<?php echo esc_url( pera_ml_url( home_url( '/join-our-team/' ) ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Join us', 'theme.template.footer.join_us' ) ); ?></a></li>
        <li><a href="<?php echo esc_url( pera_ml_url( home_url( '/contact-us/' ) ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Contact us', 'theme.template.footer.contact_us' ) ); ?></a></li>
      </ul>

      <div class="footer-social">
        <!-- socials unchanged -->
        <a href="<?php echo esc_url( pera_get_whatsapp_url( pera_ml_ui( 'Hello Pera Property, I\'d like to learn more about your Istanbul properties.', 'theme.template.footer.whatsapp_prefill' ) ) ); ?>"
           class="footer-social-link"
           aria-label="<?php echo esc_attr( pera_ml_ui( 'WhatsApp Pera Property', 'theme.template.footer.aria_label.whatsapp_pera_property' ) ); ?>"
           target="_blank"
           rel="noopener"
           data-whatsapp="1"
           data-whatsapp-type="footer_social"
           data-track-channel="whatsapp"
           data-track-intent="medium"
           data-track-source="template"
           data-track-context="site_footer"
           data-track-ga4-event="whatsapp_click"
           data-track-crm-event="whatsapp_click">
          <svg class="icon" aria-hidden="true">
            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-whatsapp' ); ?>"></use>
          </svg>
        </a>

        <a href="https://instagram.com/peraproperty"
           class="footer-social-link"
           aria-label="<?php echo esc_attr( pera_ml_ui( 'Pera Property on Instagram', 'theme.template.footer.aria_label.pera_property_on_instagram' ) ); ?>"
           target="_blank"
           rel="noopener">
          <svg class="icon" aria-hidden="true">
            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-instagram' ); ?>"></use>
          </svg>
        </a>

        <a href="https://www.youtube.com/channel/UCCCiEx5X14mJizqXcsYh1fQ"
           class="footer-social-link"
           aria-label="<?php echo esc_attr( pera_ml_ui( 'Pera Property on YouTube', 'theme.template.footer.aria_label.pera_property_on_youtube' ) ); ?>"
           target="_blank"
           rel="noopener">
          <svg class="icon" aria-hidden="true">
            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-youtube' ); ?>"></use>
          </svg>
        </a>

        <a href="https://facebook.com/perapropertycom"
           class="footer-social-link"
           aria-label="<?php echo esc_attr( pera_ml_ui( 'Pera Property on Facebook', 'theme.template.footer.aria_label.pera_property_on_facebook' ) ); ?>"
           target="_blank"
           rel="noopener">
          <svg class="icon" aria-hidden="true">
            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-facebook' ); ?>"></use>
          </svg>
        </a>

        <a href="https://tr.linkedin.com/company/peraproperty"
           class="footer-social-link"
           aria-label="<?php echo esc_attr( pera_ml_ui( 'Pera Property on LinkedIn', 'theme.template.footer.aria_label.pera_property_on_linkedin' ) ); ?>"
           target="_blank"
           rel="noopener">
          <svg class="icon" aria-hidden="true">
            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-linkedin' ); ?>"></use>
          </svg>
        </a>

        <a href="mailto:info@peraproperty.com"
           class="footer-social-link"
           aria-label="<?php echo esc_attr( pera_ml_ui( 'Email Pera Property', 'theme.template.footer.aria_label.email_pera_property' ) ); ?>">
          <svg class="icon" aria-hidden="true">
            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-envelope' ); ?>"></use>
          </svg>
        </a>
      </div>
    </div>

    <!-- COLUMN 2 -->
    <div class="footer-col">
      <h3><?php echo esc_html( pera_ml_ui( 'OUR PORTFOLIO', 'theme.template.footer.our_portfolio' ) ); ?></h3>
      <p><?php echo esc_html( pera_ml_ui( 'See all our 1000+ property for sale in Istanbul', 'theme.template.footer.see_all_our_1000_property_for_sale_in_istanbul' ) ); ?></p>
      <?php
      if ( has_nav_menu( 'footer_menu' ) ) {
        wp_nav_menu( array(
          'theme_location' => 'footer_menu',
          'container'      => false,
          'menu_class'     => 'footer-links',
          'fallback_cb'    => false,
        ) );
      }
      ?>
    </div>

    <!-- COLUMN 3 -->
    <div class="footer-col">
      <h3><?php echo esc_html( pera_ml_ui( 'OUR GUIDANCE', 'theme.template.footer.our_guidance' ) ); ?></h3>
      <p><?php echo esc_html( pera_ml_ui( 'Read through hundreds of articles written by the experts', 'theme.template.footer.read_through_hundreds_of_articles_written_by_the_experts' ) ); ?></p>
      <?php
      if ( has_nav_menu( 'guidance' ) ) {
        wp_nav_menu( array(
          'theme_location' => 'guidance',
          'container'      => false,
          'menu_class'     => 'footer-links',
          'fallback_cb'    => false,
        ) );
      }
      ?>
    </div>
  </div>
</footer>

<div class="footer-disclaimer">
  <div class="footer-inner disclaimer-inner">
    <p>
      <?php echo esc_html( pera_ml_ui( 'PeraProperty.com is a licensed real estate agency in Turkey. Our fees are set at 4% for property sales and vary for rental services depending on the level of service required. Please refer to our dedicated', 'theme.template.footer.peraproperty_com_is_a_licensed_real_estate_agency_in_turkey_our_fees_are' ) ); ?>
      <a href="<?php echo esc_url( pera_ml_url( home_url( '/sell-your-istanbul-real-estate/' ) ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Sales', 'theme.template.footer.sales' ) ); ?></a>
      <?php echo esc_html( pera_ml_ui( 'and', 'theme.template.footer.and' ) ); ?>
      <a href="<?php echo esc_url( pera_ml_url( home_url( '/rent-your-istanbul-real-estate/' ) ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Rental', 'theme.template.footer.rental' ) ); ?></a>
      <?php echo esc_html( pera_ml_ui( 'pages for the most current advice and information. While we strive to provide accurate and up-to-date content, all information on our website is subject to change. For specific queries, we recommend contacting our team directly.', 'theme.template.footer.pages_for_the_most_current_advice_and_information_while_we_strive_to_pro' ) ); ?>
    </p>

    <p class="footer-rights">
      <?php echo esc_html( pera_ml_ui( 'All rights reserved.', 'theme.template.footer.all_rights_reserved' ) ); ?> <strong>©</strong> 2025 <strong>Pera Property Ltd Şti</strong>
      <span class="footer-separator">¦</span>

      <a class="cookie-settings-link"
         href="<?php echo esc_url( pera_ml_url( home_url( '/privacy-policy/' ) ) ); ?>"
         target="_blank"
         rel="noopener">
        <?php echo esc_html( pera_ml_ui( 'Privacy policy', 'theme.template.footer.privacy_policy' ) ); ?>
      </a>
      <span class="footer-separator">¦</span>

      <a href="javascript:void(0)"
         class="cookie-settings-link"
         onclick="if (window.peraOpenCookieSettings) { window.peraOpenCookieSettings(); }">
        <svg class="icon cookie-icon" aria-hidden="true">
          <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-cookie' ); ?>"></use>
        </svg>
        <?php echo esc_html( pera_ml_ui( 'Cookie settings', 'theme.template.footer.cookie_settings' ) ); ?>
      </a>
    </p>
  </div>
</div>

<div id="fav-undo-toast" class="fav-undo-toast" role="status" aria-live="polite" hidden>
  <div class="fav-undo-toast__inner">
    <span class="fav-undo-toast__msg"><?php echo esc_html( pera_ml_ui( 'Removed from favourites', 'theme.template.footer.removed_from_favourites' ) ); ?></span>
    <button type="button" class="btn btn--ghost btn--black fav-undo-toast__btn" data-fav-undo><?php echo esc_html( pera_ml_ui( 'Undo', 'theme.template.footer.undo' ) ); ?></button>
  </div>
</div>

<!-- COOKIE BANNER -->
<div class="cookie-banner" id="cookie-banner">
  <div class="cookie-banner__inner">
    <p class="cookie-banner__description">
      <?php echo esc_html( pera_ml_ui( 'We use some essential cookies to make this website work. We’d also like to set optional analytics and marketing cookies to help us improve it.', 'theme.template.footer.we_use_some_essential_cookies_to_make_this_website_work_we_d_also_like_t' ) ); ?>
    </p>

    <div class="cookie-banner__options">
      <div class="cookie-option">
        <h3><?php echo esc_html( pera_ml_ui( 'Strictly necessary cookies', 'theme.template.footer.strictly_necessary_cookies' ) ); ?></h3>
        <p><?php echo esc_html( pera_ml_ui( 'These cookies are required to make the site work. They are always on.', 'theme.template.footer.these_cookies_are_required_to_make_the_site_work_they_are_always_on' ) ); ?></p>
        <label class="cookie-switch disabled">
          <input type="checkbox" checked disabled>
          <span class="label-text"><?php echo esc_html( pera_ml_ui( 'Always on', 'theme.template.footer.always_on' ) ); ?></span>
        </label>
      </div>

      <div class="cookie-option">
        <h3><?php echo esc_html( pera_ml_ui( 'Analytics cookies', 'theme.template.footer.analytics_cookies' ) ); ?></h3>
        <p><?php echo esc_html( pera_ml_ui( 'We’d like to use analytics cookies so we can understand how visitors use the site.', 'theme.template.footer.we_d_like_to_use_analytics_cookies_so_we_can_understand_how_visitors_use' ) ); ?></p>
        <label class="cookie-switch">
          <input type="checkbox" id="cookie-analytics">
          <span class="label-text"><?php echo esc_html( pera_ml_ui( 'Allow analytics cookies', 'theme.template.footer.allow_analytics_cookies' ) ); ?></span>
        </label>
      </div>

      <div class="cookie-option">
        <h3><?php echo esc_html( pera_ml_ui( 'Marketing cookies', 'theme.template.footer.marketing_cookies' ) ); ?></h3>
        <p><?php echo esc_html( pera_ml_ui( 'We use these to show you relevant advertising and measure its effectiveness.', 'theme.template.footer.we_use_these_to_show_you_relevant_advertising_and_measure_its_effectiven' ) ); ?></p>
        <label class="cookie-switch">
          <input type="checkbox" id="cookie-marketing">
          <span class="label-text"><?php echo esc_html( pera_ml_ui( 'Allow marketing cookies', 'theme.template.footer.allow_marketing_cookies' ) ); ?></span>
        </label>
      </div>
    </div>

    <div class="cookie-banner__actions">
      <button type="button" id="cookie-accept-all" class="btn btn-primary"><?php echo esc_html( pera_ml_ui( 'Accept all cookies', 'theme.template.footer.accept_all_cookies' ) ); ?></button>
      <button type="button" id="cookie-reject" class="btn btn-secondary"><?php echo esc_html( pera_ml_ui( 'Reject optional cookies', 'theme.template.footer.reject_optional_cookies' ) ); ?></button>
      <button type="button" id="cookie-manage" class="btn"><?php echo esc_html( pera_ml_ui( 'Manage cookie settings', 'theme.template.footer.manage_cookie_settings' ) ); ?></button>
    </div>

    <p class="cookie-banner__footer-note">
      <?php echo esc_html( pera_ml_ui( 'You can change your cookie settings at any time via “Cookie settings” in the footer.', 'theme.template.footer.you_can_change_your_cookie_settings_at_any_time_via_cookie_settings_in_t' ) ); ?>
    </p>
  </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
