<?php

if (!defined('ABSPATH')) {
    exit;
}

function so_portal_register_cpt_floor()
{
    $labels = [
        'name' => __('Floors', 'pera-portal'),
        'singular_name' => __('Floor', 'pera-portal'),
        'add_new_item' => __('Add New Floor', 'pera-portal'),
        'edit_item' => __('Edit Floor', 'pera-portal'),
        'new_item' => __('New Floor', 'pera-portal'),
        'view_item' => __('View Floor', 'pera-portal'),
        'search_items' => __('Search Floors', 'pera-portal'),
        'not_found' => __('No floors found', 'pera-portal'),
        'menu_name' => __('Floors', 'pera-portal'),
    ];

    register_post_type('pera_floor', [
        'labels' => $labels,
        'public' => false,
        'show_ui' => true,
        'show_in_rest' => true,
        'supports' => ['title'],
        'menu_icon' => 'dashicons-grid-view',
        'capability_type' => 'post',
    ]);
}

add_action('init', 'so_portal_register_cpt_floor', 15);
