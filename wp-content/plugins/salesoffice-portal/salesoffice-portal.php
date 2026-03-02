<?php
/**
 * Plugin Name: Salesoffice Portal
 * Description: Salesoffice portal module.
 * Version: 0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('PERA_PORTAL_PATH')) {
    define('PERA_PORTAL_PATH', __DIR__);
}

if (!defined('PERA_PORTAL_URL')) {
    define('PERA_PORTAL_URL', plugin_dir_url(__FILE__));
}

if (!defined('PERA_PORTAL_VERSION')) {
    define('PERA_PORTAL_VERSION', '0.1.0');
}

if (!defined('SALESOFFICE_PORTAL_PATH')) {
    define('SALESOFFICE_PORTAL_PATH', PERA_PORTAL_PATH);
}

if (!defined('SALESOFFICE_PORTAL_URL')) {
    define('SALESOFFICE_PORTAL_URL', PERA_PORTAL_URL);
}

if (!defined('SALESOFFICE_PORTAL_VERSION')) {
    define('SALESOFFICE_PORTAL_VERSION', PERA_PORTAL_VERSION);
}

require_once PERA_PORTAL_PATH . '/includes/bootstrap.php';
require_once PERA_PORTAL_PATH . '/includes/frontend/template-routing.php';

if (!function_exists('pera_portal_prepare_portal_route_assets')) {
    function pera_portal_prepare_portal_route_assets()
    {
        if (!function_exists('salesoffice_is_portal_route') || !salesoffice_is_portal_route()) {
            return;
        }

        $GLOBALS['pera_portal_is_page'] = true;
        $GLOBALS['pera_portal_enqueue_assets'] = true;

        if (function_exists('pera_portal_mark_assets_needed')) {
            pera_portal_mark_assets_needed();
        }

        if (function_exists('pera_portal_set_script_config') && defined('PERA_PORTAL_REST_NAMESPACE')) {
            pera_portal_set_script_config([
                'rest_url' => esc_url_raw(rest_url(PERA_PORTAL_REST_NAMESPACE . '/')),
                'nonce' => wp_create_nonce('wp_rest'),
                'building_id' => 0,
                'floor_id' => 0,
                'mode' => 'external',
            ]);
        }
    }
}

add_action('wp', 'pera_portal_prepare_portal_route_assets', 0);

if (!function_exists('salesoffice_portal_render_app')) {
    function salesoffice_portal_render_app($module, $view)
    {
        unset($view);

        if ('portal' !== (string) $module) {
            return;
        }

        // Marker so app-shell fallback cannot trigger if handler is registered.
        echo "\n<!-- salesoffice-portal:handler-loaded -->\n";

        $shortcode_tag = defined('PERA_PORTAL_SHORTCODE_TAG') ? (string) PERA_PORTAL_SHORTCODE_TAG : '';
        if ('' === $shortcode_tag) {
            echo '<section class="container"><article class="card-shell"><p class="pill pill--outline">Portal shortcode tag not defined</p></article></section>';

            return;
        }

        $buildings = get_posts([
            'post_type' => 'pera_building',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids',
        ]);

        $building_id = isset($_GET['building_id']) ? absint(wp_unslash($_GET['building_id'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $building_ids_int = [];
        if (!empty($buildings)) {
            $building_ids_int = array_map('intval', $buildings);
        }

        // If building_id is missing/invalid, show building picker UI (NO VIEWER).
        if ($building_id <= 0 || (!empty($building_ids_int) && !in_array((int) $building_id, $building_ids_int, true))) {
            echo '<section class="container" style="padding:16px 0;">';
            echo '<article class="card-shell">';
            echo '<p class="pill pill--outline">Portal</p>';
            echo '<h2 style="margin:8px 0 4px;">Select a building</h2>';

            if (empty($building_ids_int)) {
                echo '<p>No buildings found in pera_building.</p>';
                echo '</article></section>';

                return;
            }

            echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;margin-top:12px;">';
            foreach ($building_ids_int as $bid) {
                $url = add_query_arg(['building_id' => (int) $bid], home_url('/so/portal/'));
                echo '<a class="card-shell" href="' . esc_url($url) . '" style="display:block;text-decoration:none;color:inherit;">';
                echo '<p class="pill pill--outline" style="margin-bottom:8px;">Building</p>';
                echo '<h3 style="margin:0 0 6px;">' . esc_html(get_the_title($bid)) . '</h3>';
                echo '<p class="text-sm" style="margin:0;opacity:.8;">Open viewer</p>';
                echo '</a>';
            }
            echo '</div>';

            echo '</article>';
            echo '</section>';

            return;
        }

        // Inside a building context.
        echo '<section class="container" style="padding:16px 0;">';
        echo '<article class="card-shell" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">';
        echo '<div>';
        echo '<p class="pill pill--outline">Building</p>';
        echo '<h2 style="margin:6px 0 0;">' . esc_html(get_the_title($building_id)) . '</h2>';
        echo '</div>';
        echo '<a class="btn btn--ghost btn--blue" href="' . esc_url(home_url('/so/portal/')) . '">Change building</a>';
        echo '</article>';
        echo '</section>';

        // Ensure portal assets flag is set (defensive).
        if (function_exists('pera_portal_mark_assets_needed')) {
            pera_portal_mark_assets_needed();
        }

        $out = do_shortcode('[' . $shortcode_tag . ' building="' . esc_attr((string) $building_id) . '" mode="external"]');
        $out = trim((string) $out);

        if ('' === $out) {
            echo '<section class="container"><article class="card-shell"><p class="pill pill--outline">Portal rendered empty</p></article></section>';

            return;
        }

        echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}

// Force registration even if template routing changes.
add_action('salesoffice_render_app', 'salesoffice_portal_render_app', 10, 2);
