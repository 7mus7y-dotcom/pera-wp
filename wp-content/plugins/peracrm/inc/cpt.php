<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_register_cpt_crm_client()
{
    $labels = [
        'name' => 'CRM Clients',
        'singular_name' => 'CRM Lead',
        'add_new' => 'Add lead',
        'add_new_item' => 'Add lead',
        'edit_item' => 'Lead information',
    ];

    register_post_type('crm_client', [
        'labels' => $labels,
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-id',
        'supports' => ['title'],
        // PeraCRM has purpose-built, assignment-scoped REST endpoints. The
        // generic wp/v2 controller cannot express that boundary safely.
        'show_in_rest' => false,
        'capability_type' => ['crm_client', 'crm_clients'],
        'map_meta_cap' => true,
        'has_archive' => false,
        'rewrite' => false,
    ]);
}
