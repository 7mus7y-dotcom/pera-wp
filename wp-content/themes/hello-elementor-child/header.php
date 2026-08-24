<?php
/**
 * The header for our theme
 *
 * Displays all of the <head> section and everything up until <main>.
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}
?>
<!doctype html>
<?php if ( function_exists( 'pera_is_chinese_content' ) && pera_is_chinese_content() ) : ?>
<html lang="zh-CN">
<?php else : ?>
<html <?php language_attributes(); ?>>
<?php endif; ?>
<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Favicons -->
  <link rel="icon" type="image/png" sizes="32x32"
        href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/favicon-32x32.png' ); ?>">
  <link rel="icon" type="image/png" sizes="512x512"
        href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/favicon.png' ); ?>">
  <link rel="apple-touch-icon"
        href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/apple-touch-icon.png' ); ?>">

  <meta name="theme-color" content="#ffed00" media="(prefers-color-scheme: light)">
  <meta name="theme-color" content="#000080" media="(prefers-color-scheme: dark)">
  <meta name="color-scheme" content="light dark">

  <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a href="#primary" class="skip-link"><?php echo esc_html( pera_ml_ui( 'Skip to content', 'theme.template.header.skip_to_content' ) ); ?></a>

<input type="checkbox" id="nav-toggle" class="nav-toggle" hidden>

<?php
// Prefer canonical plugin helper; keep a minimal capability fallback for plugin-off/backward-compatible contexts.
if ( function_exists( 'peracrm_user_can_access_crm' ) ) {
  $crm_header_access_allowed = (bool) peracrm_user_can_access_crm();
} else {
  $crm_header_access_allowed = current_user_can( 'manage_options' ) || current_user_can( 'edit_crm_clients' );
}

$show_crm_header_button     = is_user_logged_in() && $crm_header_access_allowed && current_user_can( 'edit_crm_clients' );
$crm_overdue_count          = $show_crm_header_button && function_exists( 'pera_crm_get_overdue_reminders_count_for_current_user' )
  ? (int) pera_crm_get_overdue_reminders_count_for_current_user()
  : 0;
$crm_label                  = $crm_overdue_count > 0
  ? sprintf( pera_ml_ui( 'CRM (%d overdue reminders)', 'theme.template.header.crm_overdue_reminders' ), $crm_overdue_count )
  : 'CRM';

?>

<header id="site-header" class="site-header">
  <div class="container header-inner">

    <!-- LEFT: LOGO -->
    <div class="site-branding">
      <?php
      echo pera_get_site_logo_markup( array(
        'link_class' => 'site-logo logo-pera',
        'aria_label' => 'Pera Property',
        'title'      => 'Pera Property',
        'home_url'   => home_url( '/' ),
        'show_since' => true,
      ) );
      ?>
    </div>

    <!-- RIGHT: ICONS -->
    <div class="header-icons">

      <?php pera_render_header_language_switcher( 'desktop' ); ?>

      <?php if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) : ?>
        <a href="<?php echo esc_url( admin_url() ); ?>"
           class="header-crm-toggle"
           aria-label="<?php echo esc_attr( pera_ml_ui( 'Open WordPress admin', 'theme.template.header.aria_label.open_wordpress_admin' ) ); ?>">
          <svg class="icon" aria-hidden="true">
            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-wp-admin' ); ?>"></use>
          </svg>
        </a>
      <?php endif; ?>

      <?php if ( $show_crm_header_button ) : ?>
        <a href="<?php echo esc_url( home_url( '/crm' ) ); ?>"
           class="header-crm-toggle"
           aria-label="<?php echo esc_attr( $crm_label ); ?>">
          <svg class="icon" aria-hidden="true">
            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-users-group' ); ?>"></use>
          </svg>
          <?php if ( $crm_overdue_count > 0 ) : ?>
            <span class="header-icon-dot" aria-hidden="true"></span>
          <?php endif; ?>
        </a>
      <?php endif; ?>

      <a href="<?php echo esc_url( get_post_type_archive_link( 'property' ) ); ?>"
         class="header-search-toggle"
         aria-label="<?php echo esc_attr( pera_ml_ui( 'Browse Istanbul properties', 'theme.template.header.aria_label.browse_istanbul_properties' ) ); ?>">
        <svg class="icon" aria-hidden="true">
          <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-search' ); ?>"></use>
        </svg>
      </a>

      <label for="nav-toggle"
             class="header-menu-toggle"
             aria-label="<?php echo esc_attr( pera_ml_ui( 'Open main menu', 'theme.template.header.aria_label.open_main_menu' ) ); ?>">
        <svg class="icon" aria-hidden="true">
          <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-bars' ); ?>"></use>
        </svg>
      </label>

    </div>

  </div>
</header>

<!-- OFF-CANVAS MENU -->
<nav class="offcanvas-nav" aria-label="<?php echo esc_attr( pera_ml_ui( 'Main', 'theme.template.header.aria_label.main' ) ); ?>">
  <div class="offcanvas-inner">

    <div class="offcanvas-top">
      <?php
      echo pera_get_site_logo_markup( array(
        'link_class'     => 'site-logo logo-pera',
        'aria_label'     => 'Pera Property',
        'title'          => 'Pera Property',
        'fallback_width' => 250,
      ) );
      ?>

      <label for="nav-toggle"
             class="offcanvas-close"
             aria-label="<?php echo esc_attr( pera_ml_ui( 'Close menu', 'theme.template.header.aria_label.close_menu' ) ); ?>">&times;</label>
    </div>

    <?php pera_render_header_language_switcher( 'mobile' ); ?>

    <div class="offcanvas-main">

      <div class="offcanvas-main-left">
        <?php
        wp_nav_menu( array(
          'theme_location' => 'main_menu_v1',
          'container'      => false,
          'menu_class'     => 'offcanvas-menu',
          'fallback_cb'    => false,
        ) );
        ?>
      </div>

      <aside class="offcanvas-main-right">
        <?php
        $favourites_page = get_page_by_path( 'my-favourites' );
        $favourites_url = $favourites_page ? get_permalink( $favourites_page ) : home_url( '/my-favourites/' );
        $login_url = function_exists( 'pera_get_public_client_login_url' )
          ? pera_get_public_client_login_url( $favourites_url )
          : add_query_arg( 'redirect_to', $favourites_url, home_url( '/client-login/' ) );
        $logout_url = wp_logout_url( home_url( '/' ) );
        $recent_favourite_ids = array();

        if ( is_user_logged_in() && function_exists( 'pera_get_user_favourites' ) ) {
          $favourites = pera_get_user_favourites( get_current_user_id() );
          if ( ! empty( $favourites ) ) {
            $recent_favourite_ids = array_slice( array_reverse( $favourites ), 0, 3 );
            if ( function_exists( 'pera_is_valid_property_post' ) ) {
              $recent_favourite_ids = array_values( array_filter( $recent_favourite_ids, 'pera_is_valid_property_post' ) );
            }
          }
        }
        ?>

        <section id="offcanvas-user-panel" class="offcanvas-user-panel">
          <?php if ( is_user_logged_in() ) : ?>
            <h2 class="offcanvas-director-title"><?php echo esc_html( pera_ml_ui( 'Welcome back', 'theme.template.header.welcome_back' ) ); ?></h2>
            <div class="offcanvas-contact-details">
              <a href="<?php echo esc_url( $logout_url ); ?>" class="btn btn--solid btn--green" rel="nofollow">
                <?php echo esc_html( pera_ml_ui( 'Log out', 'theme.template.header.log_out' ) ); ?>
              </a>
              <a href="<?php echo esc_url( $favourites_url ); ?>" class="btn btn--solid btn--black">
                <?php echo esc_html( pera_ml_ui( 'Favourites', 'theme.template.header.favourites' ) ); ?>
              </a>
            </div>
            <?php if ( ! empty( $recent_favourite_ids ) ) : ?>
              <?php
              $recent_query = new WP_Query(
                array(
                  'post_type'      => 'property',
                  'post_status'    => 'publish',
                  'post__in'       => $recent_favourite_ids,
                  'orderby'        => 'post__in',
                  'posts_per_page' => 3,
                )
              );
              ?>
              <?php if ( $recent_query->have_posts() ) : ?>
                <h3 class="offcanvas-director-title offcanvas-user-heading"><?php echo esc_html( pera_ml_ui( 'Your latest favourites', 'theme.template.header.your_latest_favourites' ) ); ?></h3>
                <div class="offcanvas-favourites-summary">
                  <ul class="offcanvas-menu">
                    <?php while ( $recent_query->have_posts() ) : ?>
                      <?php $recent_query->the_post(); ?>
                      <li>
                        <span>
                          <a href="<?php the_permalink(); ?>" class="offcanvas-favourites-link text-sm"><?php the_title(); ?></a>
                        </span>
                      </li>
                    <?php endwhile; ?>
                  </ul>
                </div>
              <?php endif; ?>
              <?php wp_reset_postdata(); ?>
            <?php endif; ?>
          <?php else : ?>
            <h2 class="offcanvas-director-title"><?php echo esc_html( pera_ml_ui( 'Client area', 'theme.template.header.client_area' ) ); ?></h2>
            <p class="offcanvas-director-text"><?php echo esc_html( pera_ml_ui( 'Log in to keep your favourites synced across devices.', 'theme.template.header.log_in_to_keep_your_favourites_synced_across_devices' ) ); ?></p>
            <div class="offcanvas-contact-details">
              <a href="<?php echo esc_url( $login_url ); ?>" class="btn btn--solid btn--green">
                <?php echo esc_html( pera_ml_ui( 'Client login', 'theme.template.header.client_login' ) ); ?>
              </a>
              <a
                href="<?php echo esc_url( $favourites_url ); ?>"
                class="btn btn--solid btn--black offcanvas-fav-link"
                data-guest-fav-link
                hidden
              >
                <?php echo esc_html( pera_ml_ui( 'Favourites', 'theme.template.header.favourites' ) ); ?>
              </a>
            </div>
            <div class="offcanvas-latest-favs" data-guest-latest-favs hidden>
              <h3 class="offcanvas-director-title offcanvas-user-heading"><?php echo esc_html( pera_ml_ui( 'Your latest favourites', 'theme.template.header.your_latest_favourites' ) ); ?></h3>
              <div class="offcanvas-favourites-summary">
                <ul class="offcanvas-menu" data-guest-latest-favs-list></ul>
              </div>
            </div>
          <?php endif; ?>
        </section>

        <h2 class="offcanvas-director-title"><?php echo esc_html( pera_ml_ui( 'Message from our Director', 'theme.template.header.message_from_our_director' ) ); ?></h2>
        <p class="offcanvas-director-text">
          <?php echo esc_html( pera_ml_ui( 'Istanbul real estate is a long-term, relationship-based business.
          Our team has been advising local and international buyers since 2016.', 'theme.template.header.istanbul_real_estate_is_a_long_term_relationship_based_business_our_team' ) ); ?>
        </p>
        <p class="offcanvas-director-text">
          <?php echo esc_html( pera_ml_ui( 'If you have questions about any property or neighbourhood,
          reach us directly via WhatsApp or a quick call.', 'theme.template.header.if_you_have_questions_about_any_property_or_neighbourhood_reach_us_direc' ) ); ?>
        </p>
        <p class="offcanvas-director-name">
          — D. Koray Dillioglu<br>
          <?php echo esc_html( pera_ml_ui( 'Founder & CEO, Pera Property', 'theme.template.header.founder_and_ceo_pera_property' ) ); ?>
        </p>
      </aside>

    </div>

    <div class="offcanvas-contact">

      <div class="offcanvas-contact-text">
        <p><?php echo esc_html( pera_ml_ui( 'Reach our Istanbul team by phone, WhatsApp or social media.', 'theme.template.header.reach_our_istanbul_team_by_phone_whatsapp_or_social_media' ) ); ?></p>
      </div>

      <div class="offcanvas-contact-social footer-social">
        <a href="<?php echo esc_url( pera_get_whatsapp_url( pera_ml_ui( 'Hello Pera Property, I\'d like to learn more about your Istanbul properties.', 'theme.template.header.whatsapp_prefill' ) ) ); ?>"
           class="footer-social-link"
           aria-label="<?php echo esc_attr( pera_ml_ui( 'WhatsApp Pera Property', 'theme.template.header.aria_label.whatsapp_pera_property' ) ); ?>"
           target="_blank"
           rel="noopener"
           data-whatsapp="1"
           data-whatsapp-type="header_primary"
           data-track-channel="whatsapp"
           data-track-intent="high"
           data-track-source="template"
           data-track-context="site_header"
           data-track-ga4-event="whatsapp_click"
           data-track-crm-event="whatsapp_click">
          <svg class="icon" aria-hidden="true">
            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-whatsapp' ); ?>"></use>
          </svg>
        </a>

        <a href="https://instagram.com/peraproperty"
           class="footer-social-link"
           aria-label="<?php echo esc_attr( pera_ml_ui( 'Pera Property on Instagram', 'theme.template.header.aria_label.pera_property_on_instagram' ) ); ?>"
           target="_blank"
           rel="noopener">
          <svg class="icon" aria-hidden="true">
            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-instagram' ); ?>"></use>
          </svg>
        </a>

        <a href="https://www.youtube.com/channel/UCCCiEx5X14mJizqXcsYh1fQ"
           class="footer-social-link"
           aria-label="<?php echo esc_attr( pera_ml_ui( 'Pera Property on YouTube', 'theme.template.header.aria_label.pera_property_on_youtube' ) ); ?>"
           target="_blank"
           rel="noopener">
          <svg class="icon" aria-hidden="true">
            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-youtube' ); ?>"></use>
          </svg>
        </a>

        <a href="https://facebook.com/perapropertycom"
           class="footer-social-link"
           aria-label="<?php echo esc_attr( pera_ml_ui( 'Pera Property on Facebook', 'theme.template.header.aria_label.pera_property_on_facebook' ) ); ?>"
           target="_blank"
           rel="noopener">
          <svg class="icon" aria-hidden="true">
            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-facebook' ); ?>"></use>
          </svg>
        </a>

        <a href="https://tr.linkedin.com/company/peraproperty"
           class="footer-social-link"
           aria-label="<?php echo esc_attr( pera_ml_ui( 'Pera Property on LinkedIn', 'theme.template.header.aria_label.pera_property_on_linkedin' ) ); ?>"
           target="_blank"
           rel="noopener">
          <svg class="icon" aria-hidden="true">
            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-linkedin' ); ?>"></use>
          </svg>
        </a>

        <a href="mailto:info@peraproperty.com"
           class="footer-social-link"
           aria-label="<?php echo esc_attr( pera_ml_ui( 'Email Pera Property', 'theme.template.header.aria_label.email_pera_property' ) ); ?>">
          <svg class="icon" aria-hidden="true">
            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-envelope' ); ?>"></use>
          </svg>
        </a>
      </div>

    </div>

  </div>
</nav>

<div class="offcanvas-backdrop" aria-hidden="true"></div>
