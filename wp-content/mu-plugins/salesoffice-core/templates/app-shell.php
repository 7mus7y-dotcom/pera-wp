<?php
if (!defined('ABSPATH')) {
    exit;
}

$module = sanitize_key((string) get_query_var('salesoffice_app_module', ''));
$view = sanitize_key((string) get_query_var('salesoffice_app_view', 'overview'));
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('salesoffice-shell'); ?>>
<?php wp_body_open(); ?>
<main class="salesoffice-app" id="salesoffice-app">
    <?php
    ob_start();
    do_action('salesoffice_render_app', $module, $view);
    $content = trim((string) ob_get_clean());

    if ('' === $content) {
        echo '<section class="container"><article class="card-shell"><p class="pill pill--outline">Module not available</p></article></section>';
    } else {
        echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
    ?>
</main>
<?php wp_footer(); ?>
</body>
</html>
