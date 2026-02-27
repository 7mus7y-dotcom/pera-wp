<?php

if (!defined('ABSPATH')) {
    exit;
}

function pera_portal_render_shortcode($atts = [])
{
    // Do not include theme CRM router; access check must stay pure.
    $can_access = function_exists('pera_portal_current_user_can_access')
        ? (bool) pera_portal_current_user_can_access()
        : (function_exists('pera_portal_user_can_access') && (bool) pera_portal_user_can_access());

    if (!$can_access) {
        return '<p class="pera-portal-access-denied">' . esc_html__('Access denied.', 'pera-portal') . '</p>';
    }

    $atts = shortcode_atts([
        'building' => '',
        'floor' => '',
        'mode' => 'external',
    ], $atts, PERA_PORTAL_SHORTCODE_TAG);

    $building_id = absint($atts['building']);
    $floor_id = absint($atts['floor']);
    $allowed_modes = ['internal', 'external', 'investor'];
    $mode = sanitize_key((string) $atts['mode']);

    if (!in_array($mode, $allowed_modes, true)) {
        $mode = 'external';
    }

    if ($building_id > 0 && $floor_id <= 0) {
        $query = new WP_Query([
            'post_type' => 'pera_floor',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_query' => [
                [
                    'key' => 'building',
                    'value' => (string) $building_id,
                    'compare' => '=',
                ],
            ],
        ]);

        $floors = [];
        foreach ($query->posts as $floor_post) {
            $candidate_id = (int) $floor_post->ID;
            $floor_number = function_exists('get_field') ? get_field('floor_number', $candidate_id) : get_post_meta($candidate_id, 'floor_number', true);
            $floors[] = [
                'id' => $candidate_id,
                'title' => (string) get_the_title($candidate_id),
                'floor_number' => $floor_number === null ? '' : (string) $floor_number,
            ];
        }

        usort($floors, static function ($a, $b) {
            $a_number = trim((string) ($a['floor_number'] ?? ''));
            $b_number = trim((string) ($b['floor_number'] ?? ''));
            $a_numeric = $a_number !== '' && is_numeric($a_number);
            $b_numeric = $b_number !== '' && is_numeric($b_number);

            if ($a_numeric && $b_numeric) {
                return (float) $a_number <=> (float) $b_number;
            }

            if ($a_numeric) {
                return -1;
            }

            if ($b_numeric) {
                return 1;
            }

            return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        });

        if (!empty($floors[0]['id'])) {
            $floor_id = (int) $floors[0]['id'];
        }
    }

    if (function_exists('pera_portal_mark_assets_needed')) {
        pera_portal_mark_assets_needed();
    }

    if (function_exists('pera_portal_set_script_config')) {
        pera_portal_set_script_config([
            'rest_url' => esc_url_raw(rest_url(PERA_PORTAL_REST_NAMESPACE . '/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'building_id' => $building_id,
            'floor_id' => $floor_id,
            'mode' => $mode,
        ]);
    }

    if (function_exists('pera_portal_enqueue_assets')) {
        pera_portal_enqueue_assets();
    }

    $GLOBALS['pera_portal_is_page'] = true;

    ob_start();
    $template_path = PERA_PORTAL_PATH . '/templates/portal-shell.php';
    if (file_exists($template_path)) {
        include $template_path;
    }

    return (string) ob_get_clean();
}

add_shortcode(PERA_PORTAL_SHORTCODE_TAG, 'pera_portal_render_shortcode');
