<?php

if (!defined('ABSPATH')) {
    exit;
}

function pera_portal_register_cpt_building()
{
    $labels = [
        'name' => __('Buildings', 'pera-portal'),
        'singular_name' => __('Building', 'pera-portal'),
        'add_new_item' => __('Add New Building', 'pera-portal'),
        'edit_item' => __('Edit Building', 'pera-portal'),
        'new_item' => __('New Building', 'pera-portal'),
        'view_item' => __('View Building', 'pera-portal'),
        'search_items' => __('Search Buildings', 'pera-portal'),
        'not_found' => __('No buildings found', 'pera-portal'),
        'menu_name' => __('Buildings', 'pera-portal'),
    ];

    register_post_type('pera_building', [
        'labels' => $labels,
        'public' => false,
        'show_ui' => true,
        'show_in_rest' => true,
        'supports' => ['title'],
        'menu_icon' => 'dashicons-building',
        'capability_type' => 'post',
    ]);
}

add_action('init', 'pera_portal_register_cpt_building', 15);
