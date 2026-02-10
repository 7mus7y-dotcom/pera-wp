<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_client_table_has_linked_user_column()
{
    global $wpdb;

    static $has_column = null;
    if (null !== $has_column) {
        return $has_column;
    }

    $table = peracrm_table('crm_client');
    $column = $wpdb->get_col("SHOW COLUMNS FROM {$table} LIKE 'linked_user_id'");
    $has_column = !empty($column);

    return $has_column;
}

function peracrm_get_client_linked_user_id($client_id)
{
    global $wpdb;

    $client_id = (int) $client_id;
    if ($client_id <= 0) {
        return 0;
    }

    if (peracrm_client_table_has_linked_user_column()) {
        $table = peracrm_table('crm_client');
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT linked_user_id FROM {$table} WHERE id = %d", $client_id)
        );

        if ($row) {
            return (int) $row->linked_user_id;
        }
    }

    return (int) get_post_meta($client_id, 'linked_user_id', true);
}

function peracrm_update_client_linked_user_id($client_id, $user_id)
{
    global $wpdb;

    $client_id = (int) $client_id;
    $user_id = (int) $user_id;

    if ($client_id <= 0) {
        return false;
    }

    if (peracrm_client_table_has_linked_user_column()) {
        $table = peracrm_table('crm_client');

        $updated = $wpdb->update(
            $table,
            ['linked_user_id' => $user_id > 0 ? $user_id : null],
            ['id' => $client_id],
            ['%d'],
            ['%d']
        );

        if (false === $updated) {
            return false;
        }

        if ($user_id > 0) {
            return (bool) update_post_meta($client_id, 'linked_user_id', $user_id);
        }

        delete_post_meta($client_id, 'linked_user_id');
        return true;
    }

    if ($user_id > 0) {
        return (bool) update_post_meta($client_id, 'linked_user_id', $user_id);
    }

    delete_post_meta($client_id, 'linked_user_id');
    return true;
}

function peracrm_sync_theme_favourites_to_client($user_id, $client_id, $max_ids = 50)
{
    // One-way sync only for now: usermeta -> CRM relation.
    // We intentionally do not remove CRM favourite links when usermeta entries are removed.
    if (!function_exists('peracrm_client_property_link')) {
        return;
    }

    $user_id = (int) $user_id;
    $client_id = (int) $client_id;
    $max_ids = max(1, min(200, (int) $max_ids));

    if ($user_id <= 0 || $client_id <= 0) {
        return;
    }

    $favourites = get_user_meta($user_id, 'pera_favourites', true);
    if (!is_array($favourites) || empty($favourites)) {
        return;
    }

    $favourites = array_values(array_unique(array_filter(array_map('absint', $favourites))));
    $favourites = array_slice($favourites, 0, $max_ids);

    foreach ($favourites as $property_id) {
        if ($property_id <= 0 || get_post_type($property_id) !== 'property') {
            continue;
        }

        peracrm_client_property_link($client_id, $property_id, 'favourite');
    }
}

function peracrm_autolink_user_to_client($user)
{
    if (!$user instanceof WP_User) {
        return 0;
    }

    // Do not auto-link staff accounts.
    if (user_can($user, 'manage_options') || user_can($user, 'edit_crm_clients')) {
        return 0;
    }

    $email = sanitize_email($user->user_email);
    if ($email === '') {
        return 0;
    }

    $client_id = (int) peracrm_find_or_create_client_by_email($email, [
        'first_name' => get_user_meta($user->ID, 'first_name', true),
        'last_name' => get_user_meta($user->ID, 'last_name', true),
        'phone' => get_user_meta($user->ID, 'phone', true),
        'source' => 'portal_login',
        'status' => 'active',
    ]);

    if ($client_id <= 0) {
        return 0;
    }

    $linked_user_id = peracrm_get_client_linked_user_id($client_id);

    if ($linked_user_id > 0 && $linked_user_id !== (int) $user->ID) {
        delete_user_meta($user->ID, 'crm_client_id');

        if (function_exists('peracrm_log_event')) {
            peracrm_log_event($client_id, 'security', [
                'context' => 'auto_link_conflict',
                'user_id' => (int) $user->ID,
            ]);
        }

        return 0;
    }

    update_user_meta($user->ID, 'crm_client_id', $client_id);
    peracrm_update_client_linked_user_id($client_id, (int) $user->ID);

    peracrm_sync_theme_favourites_to_client((int) $user->ID, $client_id, 50);

    return $client_id;
}

function peracrm_autolink_user_on_login($user_login, $user)
{
    peracrm_autolink_user_to_client($user);
}
add_action('wp_login', 'peracrm_autolink_user_on_login', 20, 2);
