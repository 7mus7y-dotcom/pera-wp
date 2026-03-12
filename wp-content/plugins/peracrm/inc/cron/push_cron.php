<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_push_register_cron_schedule($schedules)
{
    if (!isset($schedules['peracrm_fifteen_minutes'])) {
        $schedules['peracrm_fifteen_minutes'] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display' => 'PeraCRM every 15 minutes',
        ];
    }

    return $schedules;
}
add_filter('cron_schedules', 'peracrm_push_register_cron_schedule');

function peracrm_push_schedule_digest()
{
    if (!wp_next_scheduled('peracrm_push_digest')) {
        wp_schedule_event(time() + 60, 'peracrm_fifteen_minutes', 'peracrm_push_digest');
    }
}
add_action('init', 'peracrm_push_schedule_digest', 20);

function peracrm_push_digest_handler()
{
    if (function_exists('peracrm_push_run_digest')) {
        $summary = peracrm_push_run_digest();
        if (function_exists('peracrm_push_should_log_payload_bodies') && peracrm_push_should_log_payload_bodies()) {
            error_log('peracrm_push_digest cron run: ' . wp_json_encode($summary));
        }
    }
}
add_action('peracrm_push_digest', 'peracrm_push_digest_handler');
