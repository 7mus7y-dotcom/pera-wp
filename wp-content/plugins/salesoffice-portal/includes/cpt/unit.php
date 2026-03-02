<?php

if (!defined('ABSPATH')) {
    exit;
}

function so_portal_register_cpt_unit()
{
    $labels = [
        'name' => __('Units', 'pera-portal'),
        'singular_name' => __('Unit', 'pera-portal'),
        'add_new_item' => __('Add New Unit', 'pera-portal'),
        'edit_item' => __('Edit Unit', 'pera-portal'),
        'new_item' => __('New Unit', 'pera-portal'),
        'view_item' => __('View Unit', 'pera-portal'),
        'search_items' => __('Search Units', 'pera-portal'),
        'not_found' => __('No units found', 'pera-portal'),
        'menu_name' => __('Units', 'pera-portal'),
    ];

    register_post_type('pera_unit', [
        'labels' => $labels,
        'public' => false,
        'show_ui' => true,
        'show_in_rest' => true,
        'supports' => ['title'],
        'menu_icon' => 'dashicons-admin-multisite',
        'capability_type' => 'post',
    ]);
}

add_action('init', 'so_portal_register_cpt_unit', 15);
