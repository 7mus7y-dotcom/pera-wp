<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_push_register_cron_schedule($schedules)
{
    if (!isset($schedules['peracrm_five_minutes'])) {
        $schedules['peracrm_five_minutes'] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display' => 'PeraCRM every 5 minutes',
        ];
    }

    return $schedules;
}
add_filter('cron_schedules', 'peracrm_push_register_cron_schedule');

function peracrm_push_schedule_tick()
{
    if (!wp_next_scheduled('peracrm_push_tick')) {
        wp_schedule_event(time() + 60, 'peracrm_five_minutes', 'peracrm_push_tick');
    }
}
add_action('init', 'peracrm_push_schedule_tick', 20);

function peracrm_push_tick_handler()
{
    if (function_exists('peracrm_push_run_tick')) {
        peracrm_push_run_tick();
    }
}
add_action('peracrm_push_tick', 'peracrm_push_tick_handler');

function peracrm_push_manual_runner()
{
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        return;
    }

    if (!isset($_GET['peracrm_push_run_now'])) {
        return;
    }

    check_admin_referer('peracrm_push_run_now');

    peracrm_push_tick_handler();

    wp_safe_redirect(remove_query_arg(['peracrm_push_run_now', '_wpnonce']));
    exit;
}
add_action('admin_init', 'peracrm_push_manual_runner');
