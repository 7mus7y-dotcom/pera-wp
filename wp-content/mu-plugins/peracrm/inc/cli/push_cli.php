<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

class Peracrm_Push_CLI_Command extends WP_CLI_Command
{
    /**
     * Send a push test to a CRM user.
     *
     * ## OPTIONS
     *
     * --user=<id>
     * : User ID to send a test push to.
     */
    public function test($args, $assoc_args)
    {
        $user_id = isset($assoc_args['user']) ? absint($assoc_args['user']) : 0;
        if ($user_id <= 0) {
            WP_CLI::error('Missing --user=<id>.');
            return;
        }

        $result = peracrm_push_in_target_blog(static function () use ($user_id) {
            return peracrm_push_send_test_for_user($user_id);
        });

        WP_CLI::log(wp_json_encode($result));
        if (empty($result['ok'])) {
            WP_CLI::error('Push test failed.');
            return;
        }

        WP_CLI::success('Push test sent successfully.');
    }

    /**
     * Run reminder digest now.
     */
    public function digest($args, $assoc_args)
    {
        $summary = peracrm_push_in_target_blog(static function () {
            return peracrm_push_run_digest_for_current_window();
        });

        WP_CLI::log(wp_json_encode($summary));
        WP_CLI::success('Digest run complete.');
    }
}

WP_CLI::add_command('peracrm push', 'Peracrm_Push_CLI_Command');
