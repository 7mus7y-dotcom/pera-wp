<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once PERACRM_INC . '/admin/metaboxes/timeline.php';

function peracrm_admin_disable_client_metaboxes()
{
    return defined('PERACRM_DISABLE_CLIENT_METABOXES') && PERACRM_DISABLE_CLIENT_METABOXES;
}

function peracrm_admin_is_metabox_disabled($constant)
{
    return defined($constant) && constant($constant);
}

function peracrm_register_metaboxes($post_type, $post)
{
    if ('crm_client' !== $post_type) {
        return;
    }

    $post_id = $post && isset($post->ID) ? (int) $post->ID : 0;
    $should_log = defined('WP_DEBUG') && WP_DEBUG;
    if ($should_log) {
        error_log(sprintf('[peracrm] register crm_client metaboxes start post_id=%d', $post_id));
    }

    if (peracrm_admin_disable_client_metaboxes()) {
        if ($should_log) {
            error_log(sprintf('[peracrm] register crm_client metaboxes disabled post_id=%d', $post_id));
        }
        return;
    }

    if (!peracrm_admin_is_metabox_disabled('PERACRM_DISABLE_PROFILE_METABOX')) {
        add_meta_box(
            'peracrm_client_profile',
            'Client Profile',
            'peracrm_render_client_profile_metabox',
            'crm_client',
            'normal',
            'high'
        );
    }

    if (!peracrm_admin_is_metabox_disabled('PERACRM_DISABLE_NOTES_METABOX')) {
        add_meta_box(
            'peracrm_notes',
            'Advisor Notes',
            'peracrm_render_notes_metabox',
            'crm_client',
            'normal',
            'default'
        );
    }

    if (!peracrm_admin_is_metabox_disabled('PERACRM_DISABLE_REMINDERS_METABOX')) {
        add_meta_box(
            'peracrm_reminders',
            'Reminders',
            'peracrm_render_reminders_metabox',
            'crm_client',
            'normal',
            'default'
        );
    }

    if (
        $post
        && peracrm_admin_is_crm_client_edit_screen($post->ID)
        && current_user_can('edit_post', $post->ID)
        && current_user_can('manage_options')
        && !peracrm_admin_is_metabox_disabled('PERACRM_DISABLE_TIMELINE_METABOX')
    ) {
        add_meta_box(
            'peracrm_client_timeline',
            'Timeline',
            'peracrm_render_timeline_metabox',
            'crm_client',
            'normal',
            'default'
        );
    }

    if (
        $post
        && current_user_can('edit_post', $post->ID)
        && !peracrm_admin_is_metabox_disabled('PERACRM_DISABLE_ACTIVITY_METABOX')
    ) {
        add_meta_box(
            'peracrm_activity_timeline',
            'Activity Timeline',
            'peracrm_render_activity_timeline_metabox',
            'crm_client',
            'normal',
            'default'
        );
    }

    if (!peracrm_admin_is_metabox_disabled('PERACRM_DISABLE_PROPERTIES_METABOX')) {
        add_meta_box(
            'peracrm_properties',
            'Linked Properties',
            'peracrm_render_properties_metabox',
            'crm_client',
            'side',
            'default'
        );
    }

    if (!peracrm_admin_is_metabox_disabled('PERACRM_DISABLE_ACCOUNT_METABOX')) {
        add_meta_box(
            'peracrm_account_link',
            'CRM lead',
            'peracrm_render_account_metabox',
            'crm_client',
            'side',
            'default'
        );
    }

    if (!peracrm_admin_is_metabox_disabled('PERACRM_DISABLE_HEALTH_METABOX')) {
        add_meta_box(
            'peracrm_client_health',
            'Client Health',
            'peracrm_render_client_health_metabox',
            'crm_client',
            'side',
            'default'
        );
    }

    add_meta_box(
        'peracrm_crm_status',
        'CRM Status',
        'peracrm_render_crm_status_metabox',
        'crm_client',
        'side',
        'high'
    );

    add_meta_box(
        'peracrm_deals',
        'Deals',
        'peracrm_render_deals_metabox',
        'crm_client',
        'normal',
        'default'
    );

    if (!peracrm_admin_is_metabox_disabled('PERACRM_DISABLE_ASSIGNED_ADVISOR_METABOX')) {
        add_meta_box(
            'peracrm_assigned_advisor',
            'Assigned Advisor',
            'peracrm_render_assigned_advisor_metabox',
            'crm_client',
            'side',
            'default'
        );
    }

    if ($should_log) {
        error_log(sprintf('[peracrm] register crm_client metaboxes end post_id=%d', $post_id));
    }
}