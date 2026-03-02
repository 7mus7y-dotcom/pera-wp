<?php

if (!defined('ABSPATH')) {
    exit;
}

function pera_portal_diag_probe($url)
{
    $response = wp_remote_get($url, [
        'timeout' => 10,
        'headers' => [
            'Accept' => 'application/json',
        ],
        'cookies' => [],
    ]);

    if (is_wp_error($response)) {
        return [
            'status' => 0,
            'body' => $response->get_error_message(),
            'json_ok' => false,
            'json_summary' => 'WP_Error',
            'decoded' => null,
        ];
    }

    $status = (int) wp_remote_retrieve_response_code($response);
    $body = (string) wp_remote_retrieve_body($response);
    $decoded = json_decode($body, true);
    $json_ok = json_last_error() === JSON_ERROR_NONE;
    $json_summary = 'Not JSON';

    if ($json_ok) {
        if (is_array($decoded)) {
            if (wp_is_numeric_array($decoded)) {
                $sample = array_slice($decoded, 0, 2);
                $json_summary = 'Array count: ' . count($decoded) . '; sample: ' . wp_json_encode($sample);
            } else {
                $json_summary = 'Object keys: ' . implode(', ', array_keys($decoded));
            }
        } else {
            $json_summary = 'JSON scalar';
        }
    }

    return [
        'status' => $status,
        'body' => mb_substr($body, 0, 300),
        'json_ok' => $json_ok,
        'json_summary' => $json_summary,
        'decoded' => $decoded,
    ];
}

function pera_portal_render_shortcode($atts = [])
{
    $diag = isset($_GET['portal_diag']) && $_GET['portal_diag'] === '1';

    // Do not include theme CRM router; access check must stay pure.
    $can_access = function_exists('pera_portal_current_user_can_access')
        ? (bool) pera_portal_current_user_can_access()
        : (function_exists('pera_portal_user_can_access') && (bool) pera_portal_user_can_access());

    if (!$can_access && !$diag) {
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

    $script_config = [
        'rest_url' => esc_url_raw(rest_url(PERA_PORTAL_REST_NAMESPACE . '/')),
        'nonce' => wp_create_nonce('wp_rest'),
        'building_id' => $building_id,
        'floor_id' => $floor_id,
        'mode' => $mode,
    ];

    if (function_exists('pera_portal_set_script_config')) {
        pera_portal_set_script_config($script_config);
    }

    if (function_exists('pera_portal_enqueue_assets')) {
        pera_portal_enqueue_assets();
    }

    $GLOBALS['pera_portal_is_page'] = true;

    ob_start();

    if ($diag) {
        $diag_config = $script_config;
        $diag_config['nonce'] = substr((string) $diag_config['nonce'], 0, 6) . '…';

        $base_url = rest_url(PERA_PORTAL_REST_NAMESPACE . '/');
        $probe_urls = [
            'floors' => add_query_arg(['building_id' => $building_id], $base_url . 'floors'),
            'floor' => add_query_arg(['floor_id' => $floor_id], $base_url . 'floor'),
            'units' => add_query_arg(['floor_id' => $floor_id], $base_url . 'units'),
        ];
        $probes = [];
        foreach ($probe_urls as $label => $probe_url) {
            $probes[$label] = pera_portal_diag_probe($probe_url);
        }

        $public_mode_enabled = false;
        if (function_exists('pera_portal_rest_is_public_read_enabled')) {
            $public_mode_enabled = (bool) pera_portal_rest_is_public_read_enabled();
        } elseif (defined('PERA_PORTAL_EXTERNAL_PUBLIC')) {
            $public_mode_enabled = (bool) PERA_PORTAL_EXTERNAL_PUBLIC;
        }

        $units_has_non_publish = false;
        $units_decoded = isset($probes['units']['decoded']) ? $probes['units']['decoded'] : null;
        if (is_array($units_decoded) && wp_is_numeric_array($units_decoded)) {
            foreach ($units_decoded as $unit_item) {
                if (!is_array($unit_item) || empty($unit_item['id'])) {
                    continue;
                }

                $unit_status = get_post_status((int) $unit_item['id']);
                if ($unit_status && $unit_status !== 'publish') {
                    $units_has_non_publish = true;
                    break;
                }
            }
        }

        echo '<section class="pera-portal-diagnostics" style="border:1px solid #d0d7de;border-radius:8px;padding:12px;margin:0 0 12px;background:#fff;font-size:13px;line-height:1.4">';
        echo '<h3 style="margin:0 0 10px;font-size:16px">Portal diagnostics</h3>';
        echo '<div><strong>Shortcode attrs:</strong> building=' . esc_html((string) $atts['building']) . ', floor=' . esc_html((string) $atts['floor']) . ', mode=' . esc_html((string) $atts['mode']) . '</div>';
        echo '<div><strong>Resolved:</strong> building_id=' . esc_html((string) $building_id) . ', floor_id=' . esc_html((string) $floor_id) . ', mode=' . esc_html((string) $mode) . '</div>';
        echo '<div><strong>Access gate:</strong> can_access=' . ($can_access ? 'true' : 'false') . '</div>';
        echo '<div style="margin-top:8px"><strong>Script config passed to pera_portal_set_script_config():</strong></div>';
        echo '<pre style="white-space:pre-wrap;word-break:break-word;margin:6px 0 10px">' . esc_html(wp_json_encode($diag_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>';

        echo '<div><strong>REST probes (anonymous):</strong></div>';
        foreach ($probes as $label => $probe_result) {
            echo '<div style="margin-top:8px"><strong>' . esc_html($label) . '</strong> ' . esc_html($probe_urls[$label]) . '</div>';
            echo '<pre style="white-space:pre-wrap;word-break:break-word;margin:4px 0">'
                . esc_html('status=' . $probe_result['status'] . "\n"
                . 'body=' . $probe_result['body'] . "\n"
                . 'json_ok=' . ($probe_result['json_ok'] ? 'true' : 'false') . "\n"
                . 'json_summary=' . $probe_result['json_summary'])
                . '</pre>';
        }

        if ($public_mode_enabled && $units_has_non_publish) {
            echo '<p style="margin:10px 0 0;color:#b42318"><strong>WARNING:</strong> public mode enabled; units endpoint should be publish-only.</p>';
        }

        echo '</section>';

        if (!$can_access) {
            echo '<p class="pera-portal-access-denied">' . esc_html__('Access denied.', 'pera-portal') . '</p>';
        }
    }

    if ($can_access) {
        $template_path = PERA_PORTAL_PATH . '/templates/portal-shell.php';
        if (file_exists($template_path)) {
            include $template_path;
        }
    }

    return (string) ob_get_clean();
}

add_shortcode(PERA_PORTAL_SHORTCODE_TAG, 'pera_portal_render_shortcode');
