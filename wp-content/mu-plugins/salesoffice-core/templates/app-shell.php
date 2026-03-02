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

    $show_debug = current_user_can('manage_options')
        && isset($_GET['so_debug']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    if ($show_debug) {
        global $wp_filter;

        $callbacks = [];
        if (isset($wp_filter['salesoffice_render_app']) && is_object($wp_filter['salesoffice_render_app'])) {
            $raw = $wp_filter['salesoffice_render_app']->callbacks ?? [];
            if (is_array($raw)) {
                foreach ($raw as $priority => $bucket) {
                    if (!is_array($bucket)) {
                        continue;
                    }

                    foreach ($bucket as $cb) {
                        $fn = $cb['function'] ?? null;
                        if (is_string($fn)) {
                            $callbacks[] = $priority . ': ' . $fn;
                        } elseif (is_array($fn) && isset($fn[0], $fn[1])) {
                            $callbacks[] = $priority . ': ' . (is_object($fn[0]) ? get_class($fn[0]) : (string) $fn[0]) . '::' . (string) $fn[1];
                        } else {
                            $callbacks[] = $priority . ': (closure/unknown)';
                        }
                    }
                }
            }
        }

        echo '<section class="container"><article class="card-shell" style="margin:16px auto;">';
        echo '<p class="pill pill--outline">SO Debug</p>';
        echo '<p><strong>module/view:</strong> ' . esc_html($module . ' / ' . $view) . '</p>';
        echo '<p><strong>has_action(salesoffice_render_app):</strong> ' . esc_html((string) has_action('salesoffice_render_app')) . '</p>';
        echo '<p><strong>portal handler exists:</strong> ' . esc_html(function_exists('salesoffice_portal_render_app') ? 'yes' : 'no') . '</p>';
        echo '<p><strong>crm handler exists:</strong> ' . esc_html(function_exists('salesoffice_crm_render_app') ? 'yes' : 'no') . '</p>';

        if (!empty($callbacks)) {
            echo '<p><strong>callbacks:</strong></p><ul>';
            foreach ($callbacks as $line) {
                echo '<li>' . esc_html($line) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p><strong>callbacks:</strong> (none detected)</p>';
        }

        echo '</article></section>';
    }

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
