<?php

if (!defined('ABSPATH')) {
    exit;
}

do_action('get_header', null, array());

$site_name = (string) get_bloginfo('name');
$logo_id = (int) get_theme_mod('custom_logo');
$theme_logo_html = '';

if ($logo_id > 0) {
    $theme_logo_html = wp_get_attachment_image($logo_id, 'full', false, array('class' => 'custom-logo pera-site-logo-image', 'alt' => $site_name, 'loading' => 'eager'));
}

$plugin_fallback_path = trailingslashit(PERACRM_PATH) . 'logos-icons/pera-logo.svg';
$plugin_fallback_url = trailingslashit(PERACRM_URL) . 'logos-icons/pera-logo.svg';
$has_plugin_fallback = file_exists($plugin_fallback_path);
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
    <div class="site-branding">
      <a class="site-logo logo-pera" href="<?php echo esc_url(home_url('/')); ?>" rel="home" aria-label="<?php echo esc_attr($site_name); ?>">
        <?php if ('' !== $theme_logo_html) : ?>
          <?php echo $theme_logo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php elseif ($has_plugin_fallback) : ?>
          <img src="<?php echo esc_url($plugin_fallback_url); ?>" class="pera-site-logo-image peracrm-shell-logo-image" alt="<?php echo esc_attr($site_name); ?>" loading="eager">
        <?php else : ?>
          <span class="peracrm-shell-logo-text"><?php echo esc_html($site_name); ?></span>
        <?php endif; ?>
      </a>
    </div>
  </div>
</header>
