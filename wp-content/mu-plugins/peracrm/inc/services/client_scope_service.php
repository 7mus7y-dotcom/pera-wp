<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_resolve_allowed_client_ids_for_user($user_id)
{
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return [];
    }

    $query_args = [
        'post_type' => 'crm_client',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'suppress_filters' => true,
        'meta_query' => [
            'relation' => 'OR',
            [
                'key' => 'assigned_advisor_user_id',
                'value' => $user_id,
                'compare' => '=',
            ],
            [
                'key' => 'crm_assigned_advisor',
                'value' => $user_id,
                'compare' => '=',
            ],
        ],
    ];

    $ids = [];
    $switched_blog = false;
    $target_blog_id = defined('PERACRM_TARGET_BLOG_ID') ? (int) PERACRM_TARGET_BLOG_ID : 0;

    if (
        $target_blog_id > 0
        && function_exists('get_current_blog_id')
        && function_exists('switch_to_blog')
        && function_exists('restore_current_blog')
    ) {
        $current_blog_id = (int) get_current_blog_id();
        if ($current_blog_id !== $target_blog_id) {
            switch_to_blog($target_blog_id);
            $switched_blog = true;
        }
    }

    $ids = get_posts($query_args);

    if ($switched_blog) {
        restore_current_blog();
    }

    return array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
}

function peracrm_allowed_client_ids_for_user_filter($ids, $user_id)
{
    if (is_array($ids) && !empty($ids)) {
        return $ids;
    }

    return peracrm_resolve_allowed_client_ids_for_user($user_id);
}

add_filter('peracrm_allowed_client_ids_for_user', 'peracrm_allowed_client_ids_for_user_filter', 10, 2);
