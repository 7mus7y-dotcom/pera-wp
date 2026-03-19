<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_admin_is_crm_client_screen($hook)
{
    if (!in_array($hook, ['post.php', 'post-new.php', 'edit.php'], true)) {
        return false;
    }

    $screen = get_current_screen();
    if (!$screen) {
        return false;
    }

    return $screen->post_type === 'crm_client';
}

function peracrm_admin_enqueue_assets($hook)
{
    if (!peracrm_admin_is_crm_client_screen($hook)
        && !peracrm_admin_is_my_reminders_screen($hook)
        && !peracrm_admin_is_pipeline_screen($hook)
        && !peracrm_admin_is_client_view_screen($hook)
        && !peracrm_admin_is_whatsapp_screen($hook)
        && !peracrm_admin_is_import_data_screen($hook)
    ) {
        return;
    }

    $admin_css_path = PERACRM_PATH . '/assets/admin.css';
    $version = defined('PERACRM_VERSION') ? PERACRM_VERSION : (file_exists($admin_css_path) ? filemtime($admin_css_path) : null);

    wp_enqueue_style(
        'peracrm-admin',
        PERACRM_URL . '/assets/admin.css',
        [],
        $version
    );


    if (peracrm_admin_is_whatsapp_screen($hook)) {
        wp_register_script('peracrm-admin', false, [], $version, true);
        wp_enqueue_script('peracrm-admin');
        wp_localize_script('peracrm-admin', 'peracrmWhatsAppAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('peracrm_whatsapp_logs'),
            'pageSizeOptions' => peracrm_whatsapp_allowed_page_sizes(),
            'strings' => [
                'deleteConfirm' => __('Delete selected logs?', 'peracrm'),
                'deleteSuccess' => __('Selected logs deleted.', 'peracrm'),
                'deleteError' => __('Unable to delete selected logs.', 'peracrm'),
                'loadError' => __('Unable to load WhatsApp logs.', 'peracrm'),
            ],
        ]);
        wp_add_inline_script('peracrm-admin', peracrm_whatsapp_admin_inline_script());
    }

}
