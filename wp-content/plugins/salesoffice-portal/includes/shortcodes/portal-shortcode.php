<?php

if (!defined('ABSPATH')) {
    exit;
}

function so_portal_diag_probe($url)
{
    $response = wp_remote_get($url, [
        'timeout' => 10,
        'headers' => ['Accept' => 'application/json'],
        'cookies' => [],
    ]);

    if (is_wp_error($response)) {
        return [
            'status' => 0,
            'body' => mb_substr((string) $response->get_error_message(), 0, 400),
            'json_type' => 'error',
            'json_meta' => ['message' => 'WP_Error'],
        ];
    }

    $status = (int) wp_remote_retrieve_response_code($response);
    $body = (string) wp_remote_retrieve_body($response);
    $decoded = json_decode($body, true);
    $json_ok = json_last_error() === JSON_ERROR_NONE;

    $json_type = 'invalid_json';
    $json_meta = [];

    if ($json_ok && is_array($decoded)) {
        if (wp_is_numeric_array($decoded)) {
            $json_type = 'array';
            $json_meta = [
                'count' => count($decoded),
                'first_rows' => array_slice($decoded, 0, 2),
            ];
        } else {
            $json_type = 'object';
            $json_meta = ['keys' => array_keys($decoded)];
        }
    } elseif ($json_ok) {
        $json_type = 'scalar';
    }

    return [
        'status' => $status,
        'body' => mb_substr($body, 0, 400),
        'json_type' => $json_type,
        'json_meta' => $json_meta,
    ];
}

function so_portal_render_shortcode($atts = [])
{
    $diag = isset($_GET['portal_diag']) && $_GET['portal_diag'] === '1';

    $atts = shortcode_atts([
        'building' => '',
        'floor' => '',
        'mode' => 'external',
    ], $atts, SO_PORTAL_SHORTCODE_TAG);

    $building_id = absint($atts['building']);
    $floor_id = absint($atts['floor']);
    $allowed_modes = ['internal', 'external', 'investor'];
    $mode = sanitize_key((string) $atts['mode']);
    if (!in_array($mode, $allowed_modes, true)) {
        $mode = 'external';
    }

    so_portal_mark_assets_needed();

    $script_config = [
        'rest_url' => esc_url_raw(rest_url(SO_PORTAL_REST_NAMESPACE . '/')),
        'nonce' => '',
        'building_id' => $building_id,
        'floor_id' => $floor_id,
        'mode' => $mode,
    ];
    so_portal_set_script_config($script_config);

    ob_start();

    if ($diag) {
        $base_url = rest_url(SO_PORTAL_REST_NAMESPACE . '/');
        $probe_urls = [
            'floors' => add_query_arg(['building_id' => $building_id], $base_url . 'floors'),
            'floor' => add_query_arg(['floor_id' => $floor_id], $base_url . 'floor'),
            'units' => add_query_arg(['floor_id' => $floor_id], $base_url . 'units'),
        ];

        $probes = [];
        foreach ($probe_urls as $label => $probe_url) {
            $probes[$label] = so_portal_diag_probe($probe_url);
        }

        echo '<section class="so-portal-diagnostics" style="border:1px solid #d0d7de;border-radius:8px;padding:12px;margin:0 0 12px;background:#fff;font-size:13px;line-height:1.4">';
        echo '<h3 style="margin:0 0 10px;font-size:16px">Portal diagnostics (salesoffice-portal)</h3>';
        echo '<div><strong>Raw shortcode atts:</strong> building=' . esc_html((string) $atts['building']) . ', floor=' . esc_html((string) $atts['floor']) . ', mode=' . esc_html((string) $atts['mode']) . '</div>';
        echo '<div><strong>Resolved IDs:</strong> building_id=' . esc_html((string) $building_id) . ', floor_id=' . esc_html((string) $floor_id) . '</div>';
        echo '<div style="margin-top:8px"><strong>Final JS config:</strong></div>';
        echo '<pre style="white-space:pre-wrap;word-break:break-word;margin:6px 0 10px">' . esc_html(wp_json_encode($script_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>';
        echo '<div><strong>REST probes:</strong> anonymous requests may return 401 for protected routes and that is expected.</div>';

        foreach ($probes as $label => $probe_result) {
            echo '<div style="margin-top:8px"><strong>' . esc_html($label) . '</strong> ' . esc_html($probe_urls[$label]) . ' — status ' . esc_html((string) $probe_result['status']) . '</div>';
            $probe_dump = [
                'status' => $probe_result['status'],
                'body_first_400_chars' => $probe_result['body'],
                'json_type' => $probe_result['json_type'],
                'json_meta' => $probe_result['json_meta'],
            ];
            echo '<pre style="white-space:pre-wrap;word-break:break-word;margin:4px 0">' . esc_html(wp_json_encode($probe_dump, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>';
        }

        echo '</section>';
    }

    $template_path = SO_PORTAL_PATH . 'templates/portal-shell.php';
    if (file_exists($template_path)) {
        include $template_path;
    } else {
        echo '<div class="so-portal-missing-template">Missing template: portal-shell.php</div>';
    }

    return (string) ob_get_clean();
}

add_shortcode(SO_PORTAL_SHORTCODE_TAG, 'so_portal_render_shortcode');
