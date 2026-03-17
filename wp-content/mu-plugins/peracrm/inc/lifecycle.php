<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('peracrm_register_lifecycle_hooks')) {
    function peracrm_register_lifecycle_hooks(): void
    {
        if (!function_exists('register_activation_hook') || !function_exists('register_deactivation_hook')) {
            return;
        }

        register_activation_hook(PERACRM_MAIN_FILE, 'peracrm_on_activation');
        register_deactivation_hook(PERACRM_MAIN_FILE, 'peracrm_on_deactivation');
    }
}

if (!function_exists('peracrm_on_activation')) {
    function peracrm_on_activation(): void
    {
        if (function_exists('peracrm_ensure_roles_and_caps')) {
            peracrm_ensure_roles_and_caps();
        }

        if (function_exists('peracrm_register_cpt_crm_client')) {
            peracrm_register_cpt_crm_client();
        }

        if (function_exists('peracrm_maybe_upgrade_schema')) {
            peracrm_maybe_upgrade_schema();
        }

        if (function_exists('pera_crm_register_route')) {
            pera_crm_register_route();
        }

        if (function_exists('peracrm_rewrite_version')) {
            update_option('peracrm_rewrite_version', peracrm_rewrite_version(), false);
        }

        if (function_exists('peracrm_push_schedule_digest')) {
            peracrm_push_schedule_digest();
        }

        flush_rewrite_rules(false);
    }
}

if (!function_exists('peracrm_on_deactivation')) {
    function peracrm_on_deactivation(): void
    {
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook('peracrm_push_digest');
        }

        flush_rewrite_rules(false);
    }
}
