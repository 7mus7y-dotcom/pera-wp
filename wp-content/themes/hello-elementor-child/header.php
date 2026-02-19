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
<html <?php language_attributes(); ?>>
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

<a href="#primary" class="skip-link">Skip to content</a>

<input type="checkbox" id="nav-toggle" class="nav-toggle" hidden>

<?php
$logo_path = get_stylesheet_directory() . '/logos-icons/pera-logo.svg';

// Do not depend on crm-router.php load timing: it is route-gated to /crm/* requests.
if ( function_exists( 'peracrm_user_can_access_crm' ) ) {
  $crm_header_access_allowed = (bool) peracrm_user_can_access_crm();
} elseif ( function_exists( 'pera_crm_user_can_access' ) ) {
  $crm_header_access_allowed = (bool) pera_crm_user_can_access();
} else {
  $crm_header_access_allowed = current_user_can( 'manage_options' ) || current_user_can( 'edit_crm_clients' );
}

$show_crm_header_button = is_user_logged_in() && $crm_header_access_allowed && current_user_can( 'edit_crm_clients' );
$crm_overdue_count      = $show_crm_header_button && function_exists( 'pera_crm_get_overdue_reminders_count_for_current_user' )
  ? (int) pera_crm_get_overdue_reminders_count_for_current_user()
  : 0;
$crm_label              = $crm_overdue_count > 0
  ? sprintf( 'CRM (%d overdue reminders)', $crm_overdue_count )
  : 'CRM';
?>

<header id="site-header" class="site-header">
  <div class="container header-inner">

    <!-- LEFT: LOGO -->
    <div class="site-branding">
      <a href="<?php echo esc_url( home_url('/') ); ?>"
         class="site-logo logo-pera"
         aria-label="Pera Property">

        <?php
        if ( file_exists( $logo_path ) ) {
          echo file_get_contents( $logo_path );
        } else {
          ?>
          <img
            src="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/logo-white.svg' ); ?>"
            alt="Pera Property Logo"
            width="120"
          />
        <?php } ?>

      </a>
    </div>

    <!-- RIGHT: ICONS -->
    <div class="header-icons">

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
         aria-label="Browse Istanbul properties">
        <svg class="icon" aria-hidden="true">
          <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-search' ); ?>"></use>
        </svg>
      </a>

      <label for="nav-toggle"
             class="header-menu-toggle"
             aria-label="Open main menu">
        <svg class="icon" aria-hidden="true">
          <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-bars' ); ?>"></use>
        </svg>
      </label>

    </div>

  </div>
</header>

<!-- OFF-CANVAS MENU -->
<nav class="offcanvas-nav" aria-label="Main">
  <div class="offcanvas-inner">

    <div class="offcanvas-top">
      <a href="<?php echo esc_url( home_url('/') ); ?>"
         class="site-logo logo-pera"
         aria-label="Pera Property">

        <?php
        if ( file_exists( $logo_path ) ) {
          echo file_get_contents( $logo_path );
        } else {
          ?>
          <img
            src="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/logo-white.svg' ); ?>"
            alt="Pera Property Logo"
            width="250"
          />
        <?php } ?>

      </a>

      <label for="nav-toggle"
             class="offcanvas-close"
             aria-label="Close menu">&times;</label>
    </div>

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
        $login_url = wp_login_url( home_url( '/my-favourites/' ) );
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
            <h2 class="offcanvas-director-title">Welcome back</h2>
            <div class="offcanvas-contact-details">
              <a href="<?php echo esc_url( $logout_url ); ?>" class="btn btn--solid btn--green" rel="nofollow">
                Log out
              </a>
              <a href="<?php echo esc_url( $favourites_url ); ?>" class="btn btn--solid btn--black">
                Favourites
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
                <h3 class="offcanvas-director-title offcanvas-user-heading">Your latest favourites</h3>
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
            <h2 class="offcanvas-director-title">Client area</h2>
            <p class="offcanvas-director-text">Log in to keep your favourites synced across devices.</p>
            <div class="offcanvas-contact-details">
              <a href="<?php echo esc_url( $login_url ); ?>" class="btn btn--solid btn--green">
                Client login
              </a>
              <a
                href="<?php echo esc_url( $favourites_url ); ?>"
                class="btn btn--solid btn--black offcanvas-fav-link"
                data-guest-fav-link
                hidden
              >
                Favourites
              </a>
            </div>
            <div class="offcanvas-latest-favs" data-guest-latest-favs hidden>
              <h3 class="offcanvas-director-title offcanvas-user-heading">Your latest favourites</h3>
              <div class="offcanvas-favourites-summary">
                <ul class="offcanvas-menu" data-guest-latest-favs-list></ul>
              </div>
            </div>
          <?php endif; ?>
        </section>

        <h2 class="offcanvas-director-title">Message from our Director</h2>
        <p class="offcanvas-director-text">
          Istanbul real estate is a long-term, relationship-based business.
          Our team has been advising local and international buyers since 2016.
        </p>
        <p class="offcanvas-director-text">
          If you have questions about any property or neighbourhood,
          reach us directly via WhatsApp or a quick call.
        </p>
        <p class="offcanvas-director-name">
          — D. Koray Dillioglu<br>
          Founder &amp; CEO, Pera Property
        </p>
      </aside>

    </div>

    <div class="offcanvas-contact">

      <div class="offcanvas-contact-text">
        <p>Reach our Istanbul team by phone, WhatsApp or social media.</p>
      </div>

      <div class="offcanvas-contact-social footer-social">
        <a href="https://wa.me/905320639978?text=Hello%20Pera%20Property%2C%20I%27d%20like%20to%20learn%20more%20about%20your%20Istanbul%20properties."
           class="footer-social-link"
           aria-label="WhatsApp Pera Property"
           target="_blank"
           rel="noopener">
          <svg class="icon" aria-hidden="true">
            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-whatsapp' ); ?>"></use>
          </svg>
        </a>

        <a href="https://instagram.com/peraproperty"
           class="footer-social-link"
           aria-label="Pera Property on Instagram"
           target="_blank"
           rel="noopener">
          <svg class="icon" aria-hidden="true">
            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-instagram' ); ?>"></use>
          </svg>
        </a>

        <a href="https://www.youtube.com/channel/UCCCiEx5X14mJizqXcsYh1fQ"
           class="footer-social-link"
           aria-label="Pera Property on YouTube"
           target="_blank"
           rel="noopener">
          <svg class="icon" aria-hidden="true">
            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-youtube' ); ?>"></use>
          </svg>
        </a>

        <a href="https://facebook.com/perapropertycom"
           class="footer-social-link"
           aria-label="Pera Property on Facebook"
           target="_blank"
           rel="noopener">
          <svg class="icon" aria-hidden="true">
            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-facebook' ); ?>"></use>
          </svg>
        </a>

        <a href="https://tr.linkedin.com/company/peraproperty"
           class="footer-social-link"
           aria-label="Pera Property on LinkedIn"
           target="_blank"
           rel="noopener">
          <svg class="icon" aria-hidden="true">
            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-linkedin' ); ?>"></use>
          </svg>
        </a>

        <a href="mailto:info@peraproperty.com"
           class="footer-social-link"
           aria-label="Email Pera Property">
          <svg class="icon" aria-hidden="true">
            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-envelope' ); ?>"></use>
          </svg>
        </a>
      </div>

    </div>

  </div>
</nav>

<div class="offcanvas-backdrop" aria-hidden="true"></div>
