<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_activate()
{
    if (function_exists('peracrm_ensure_roles_and_caps')) {
        peracrm_ensure_roles_and_caps();
    }

    if (function_exists('peracrm_maybe_upgrade_schema')) {
        peracrm_maybe_upgrade_schema();
    }

    if (function_exists('pera_crm_register_route')) {
        pera_crm_register_route();
    }

    flush_rewrite_rules();
}

function peracrm_deactivate()
{
    flush_rewrite_rules();
}
