<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the canonical CRM client scope for a user.
 *
 * The explicit type is important: an empty assigned scope must never be
 * mistaken for an unrestricted query.
 *
 * @return array{type:string,ids:int[]}
 */
function peracrm_get_client_access_scope($user_id)
{
    $user_id = (int) $user_id;
    if ($user_id <= 0 || !(get_userdata($user_id) instanceof WP_User)) {
        return ['type' => 'empty', 'ids' => []];
    }

    return peracrm_with_target_blog(static function () use ($user_id) {
        if (user_can($user_id, 'manage_options') || user_can($user_id, 'peracrm_manage_all_clients')) {
            return ['type' => 'full', 'ids' => []];
        }

        // Client assignment is ownership, not a substitute for WordPress
        // permission. Require a client read/edit primitive, but deliberately do
        // not use edit_post: its author-sensitive mapping would make post
        // authorship a second, accidental CRM ownership boundary.
        $has_client_baseline = user_can($user_id, 'read_crm_client')
            || user_can($user_id, 'edit_crm_clients');
        if (!peracrm_user_can_access_crm($user_id) || !$has_client_baseline) {
            return ['type' => 'empty', 'ids' => []];
        }

        $candidate_ids = get_posts([
            'post_type' => 'crm_client',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'suppress_filters' => true,
            'meta_query' => [
                'relation' => 'OR',
                ['key' => 'assigned_advisor_user_id', 'value' => $user_id, 'compare' => '='],
                ['key' => 'crm_assigned_advisor', 'value' => $user_id, 'compare' => '='],
            ],
        ]);

        // Re-resolve each candidate so conflicting dual metadata fails closed.
        $ids = array_values(array_filter(array_map('intval', (array) $candidate_ids), static function ($client_id) use ($user_id) {
            return peracrm_client_get_assigned_advisor_id($client_id) === $user_id;
        }));

        return ['type' => empty($ids) ? 'empty' : 'assigned', 'ids' => array_values(array_unique($ids))];
    });
}

function peracrm_user_can_access_client($user_id, $client_id)
{
    $user_id = (int) $user_id;
    $client_id = (int) $client_id;
    if ($user_id <= 0 || $client_id <= 0) {
        return false;
    }

    return peracrm_with_target_blog(static function () use ($user_id, $client_id) {
        if (get_post_type($client_id) !== 'crm_client') {
            return false;
        }

        $scope = peracrm_get_client_access_scope($user_id);
        return $scope['type'] === 'full'
            || ($scope['type'] === 'assigned' && in_array($client_id, $scope['ids'], true));
    });
}

/** @return int[] */
function peracrm_resolve_allowed_client_ids_for_user($user_id)
{
    $scope = peracrm_get_client_access_scope($user_id);
    return $scope['type'] === 'assigned' ? $scope['ids'] : [];
}

function peracrm_allowed_client_ids_for_user_filter($ids, $user_id)
{
    return peracrm_resolve_allowed_client_ids_for_user($user_id);
}

add_filter('peracrm_allowed_client_ids_for_user', 'peracrm_allowed_client_ids_for_user_filter', 10, 2);
