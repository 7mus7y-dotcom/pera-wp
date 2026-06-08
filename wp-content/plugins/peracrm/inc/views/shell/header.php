<?php

if (!defined('ABSPATH')) {
    exit;
}

do_action('get_header', null, array());

$site_name = (string) get_bloginfo('name');
$logo_id = (int) get_theme_mod('custom_logo');
$custom_logo_html = '';

if ($logo_id > 0) {
    $custom_logo_html = wp_get_attachment_image($logo_id, 'full', false, array('class' => 'custom-logo pera-site-logo-image peracrm-shell-logo-image', 'alt' => $site_name, 'loading' => 'eager'));
}

$plugin_logo_path = trailingslashit(PERACRM_PATH) . 'logos-icons/pera-logo.svg';
$plugin_logo_url = trailingslashit(PERACRM_URL) . 'logos-icons/pera-logo.svg';
$has_plugin_logo = file_exists($plugin_logo_path);
$show_crm_nav_toggle = isset($args['show_crm_nav_toggle']) ? (bool) $args['show_crm_nav_toggle'] : true;
$show_header_search = is_user_logged_in()
    && function_exists('peracrm_header_search_user_can_access')
    && peracrm_header_search_user_can_access();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php wp_head(); ?>
</head>
<body <?php body_class('crm-route'); ?>>
<?php wp_body_open(); ?>

<a href="#primary" class="skip-link"><?php esc_html_e('Skip to content', 'peracrm'); ?></a>

<header id="site-header" class="site-header peracrm-shell-header" role="banner">
  <div class="container header-inner">
    <div class="site-branding peracrm-shell-branding">
      <div class="peracrm-shell-logo-block">
        <a class="site-logo logo-pera peracrm-shell-logo-link" href="<?php echo esc_url(home_url('/')); ?>" rel="home" aria-label="<?php echo esc_attr($site_name); ?>">
          <?php if ('' !== $custom_logo_html) : ?>
            <?php echo $custom_logo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
          <?php elseif ($has_plugin_logo) : ?>
            <img src="<?php echo esc_url($plugin_logo_url); ?>" class="pera-site-logo-image peracrm-shell-logo-image" alt="<?php echo esc_attr($site_name); ?>" loading="eager">
          <?php else : ?>
            <span class="peracrm-shell-logo-text"><?php echo esc_html($site_name); ?></span>
          <?php endif; ?>
        </a>
      </div>
    </div>

    <?php if ($show_header_search) : ?>
    <form class="peracrm-header-search" data-peracrm-header-search role="search" action="<?php echo esc_url(home_url('/crm/clients/')); ?>" method="get">
      <label class="screen-reader-text" for="peracrm-header-search-input"><?php esc_html_e('Search CRM clients', 'peracrm'); ?></label>
      <input
        id="peracrm-header-search-input"
        class="crm-search-control peracrm-header-search__input"
        type="search"
        name="q"
        placeholder="<?php esc_attr_e('Search clients', 'peracrm'); ?>"
        autocomplete="off"
        data-peracrm-header-search-input
        aria-controls="peracrm-header-search-results"
        aria-expanded="false"
      >
      <div
        id="peracrm-header-search-results"
        class="peracrm-header-search__results"
        data-peracrm-header-search-results
        hidden
      ></div>
    </form>
    <?php endif; ?>

    <?php if ($show_crm_nav_toggle) : ?>
    <div class="peracrm-header-actions" data-peracrm-header-actions>
      <div class="peracrm-header-actions__cluster">
        <button
          type="button"
          class="btn btn--ghost crm-side-nav__toggle crm-side-nav__toggle--header"
          data-crm-nav-toggle
          aria-expanded="false"
          aria-controls="crm-side-nav-drawer"
          aria-label="<?php esc_attr_e('Open CRM menu', 'peracrm'); ?>"
        >
          <span class="crm-side-nav__toggle-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
              <path d="M4 7h16M4 12h16M4 17h16" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.9"></path>
            </svg>
          </span>
        </button>
      </div>
    </div>
    <?php endif; ?>

  </div>
</header>
