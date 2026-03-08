<?php

if (!defined('ABSPATH')) {
    exit;
}

function pera_portal_register_cpt_quote()
{
    $labels = [
        'name' => __('Client Quotes', 'pera-portal'),
        'singular_name' => __('Client Quote', 'pera-portal'),
        'add_new_item' => __('Add New Client Quote', 'pera-portal'),
        'edit_item' => __('Edit Client Quote', 'pera-portal'),
        'new_item' => __('New Client Quote', 'pera-portal'),
        'view_item' => __('View Client Quote', 'pera-portal'),
        'search_items' => __('Search Client Quotes', 'pera-portal'),
        'not_found' => __('No client quotes found', 'pera-portal'),
        'menu_name' => __('Client Quotes', 'pera-portal'),
    ];

    register_post_type('pera_quote', [
        'labels' => $labels,
        'public' => false,
        'show_ui' => true,
        'show_in_rest' => false,
        'supports' => ['title'],
        'menu_icon' => 'dashicons-media-spreadsheet',
        'capability_type' => 'post',
    ]);
}

add_action('init', 'pera_portal_register_cpt_quote', 16);
