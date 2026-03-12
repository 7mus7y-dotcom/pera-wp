<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

WP_CLI::add_command('peracrm push test', function ($args, $assoc_args) {
    $user_id = isset($assoc_args['user']) ? absint($assoc_args['user']) : 0;
    if ($user_id <= 0) {
        WP_CLI::error('Please provide --user=<id>.');
    }

    $payload = [
        'type' => 'crm_test',
        'title' => 'PeraCRM',
        'body' => 'CLI test notification',
        'click_url' => function_exists('peracrm_push_default_click_url') ? peracrm_push_default_click_url() : '/crm/tasks/',
    ];

    $results = peracrm_push_send_to_user($user_id, $payload, ['payload_type' => 'test']);
    WP_CLI::line(wp_json_encode([
        'user_id' => $user_id,
        'results' => $results,
    ], JSON_PRETTY_PRINT));
});

WP_CLI::add_command('peracrm push digest', function ($args, $assoc_args) {
    $force = !empty($assoc_args['force']);
    $summary = peracrm_push_run_digest_for_current_window($force);
    WP_CLI::line(wp_json_encode($summary, JSON_PRETTY_PRINT));
});
