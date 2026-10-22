<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once PERACRM_INC . '/helpers.php';
require_once PERACRM_INC . '/phone-countries.php';
require_once PERACRM_INC . '/impersonation.php';
require_once PERACRM_INC . '/stages.php';
require_once PERACRM_INC . '/campaigns/management-schema.php';
require_once PERACRM_INC . '/campaigns/draft-schema.php';
require_once PERACRM_INC . '/campaigns/snapshot-schema.php';
require_once PERACRM_INC . '/campaigns/freeze-schema.php';
require_once PERACRM_INC . '/campaigns/queue-schema.php';
require_once PERACRM_INC . '/campaigns/schema.php';
require_once PERACRM_INC . '/campaigns/repository.php';
require_once PERACRM_INC . '/campaigns/service.php';
require_once PERACRM_INC . '/campaigns/schema-lock.php';
require_once PERACRM_INC . '/campaigns/readiness.php';
require_once PERACRM_INC . '/campaigns/management.php';
require_once PERACRM_INC . '/campaigns/import.php';
require_once PERACRM_INC . '/campaigns/preferences.php';
require_once PERACRM_INC . '/campaigns/routes.php';
require_once PERACRM_INC . '/campaigns/recipient-withdrawal-routes.php';
require_once PERACRM_INC . '/campaigns/audience.php';
require_once PERACRM_INC . '/campaigns/drafts.php';
require_once PERACRM_INC . '/campaigns/content.php';
require_once PERACRM_INC . '/campaigns/renderer.php';
require_once PERACRM_INC . '/campaigns/snapshots.php';
require_once PERACRM_INC . '/campaigns/freeze.php';
require_once PERACRM_INC . '/campaigns/queue.php';
require_once PERACRM_INC . '/campaigns/worker.php';
require_once PERACRM_INC . '/campaigns/operations.php';
require_once PERACRM_INC . '/campaigns/scheduler.php';
require_once PERACRM_INC . '/campaigns/freeze-ui.php';
require_once PERACRM_INC . '/campaigns/queue-ui.php';
require_once PERACRM_INC . '/campaigns/preview-routes.php';
require_once PERACRM_INC . '/campaigns/draft-routes.php';
require_once PERACRM_INC . '/schema.php';
require_once PERACRM_INC . '/roles.php';
require_once PERACRM_INC . '/lifecycle.php';
require_once PERACRM_INC . '/admin-block-leads.php';
require_once PERACRM_INC . '/cpt.php';
require_once PERACRM_INC . '/favourites.php';
require_once PERACRM_INC . '/activity.php';
require_once PERACRM_INC . '/activity-capture.php';
require_once PERACRM_INC . '/health.php';
require_once PERACRM_INC . '/whatsapp.php';
require_once PERACRM_INC . '/whatsapp-logs.php';
require_once PERACRM_INC . '/db/whatsapp_messages_table.php';
require_once PERACRM_INC . '/db/notification_log_table.php';
require_once PERACRM_INC . '/notifications/enquiry-events.php';
require_once PERACRM_INC . '/notifications/providers/class-peracrm-email-provider.php';
require_once PERACRM_INC . '/notifications/providers/class-peracrm-whatsapp-meta-provider.php';
require_once PERACRM_INC . '/notifications/enquiry-notification-service.php';
require_once PERACRM_INC . '/integrations/enquiries.php';
require_once PERACRM_INC . '/integrations/facebook-leads/bootstrap.php';
require_once PERACRM_INC . '/db/push_log_table.php';
require_once PERACRM_INC . '/frontend/view-loader.php';
require_once PERACRM_INC . '/frontend/helper-bridge.php';
require_once PERACRM_INC . '/frontend/routing.php';
require_once PERACRM_INC . '/frontend/assets.php';

require_once PERACRM_INC . '/repositories/notes.php';
require_once PERACRM_INC . '/repositories/reminders.php';
require_once PERACRM_INC . '/repositories/activity.php';
require_once PERACRM_INC . '/repositories/client_property.php';
require_once PERACRM_INC . '/repositories/party.php';
require_once PERACRM_INC . '/repositories/deals.php';

require_once PERACRM_INC . '/services/client_service.php';
require_once PERACRM_INC . '/services/header_search_service.php';
require_once PERACRM_INC . '/services/client_scope_service.php';
require_once PERACRM_INC . '/services/activity_service.php';
require_once PERACRM_INC . '/services/reminder_service.php';
require_once PERACRM_INC . '/services/user_link_service.php';
require_once PERACRM_INC . '/services/user_membership_service.php';
require_once PERACRM_INC . '/services/push_service.php';
require_once PERACRM_INC . '/cron/push_cron.php';
require_once PERACRM_INC . '/rest/push.php';
require_once PERACRM_INC . '/rest/whatsapp.php';
require_once PERACRM_INC . '/rest/facebook.php';
require_once PERACRM_INC . '/rest.php';
require_once PERACRM_INC . '/admin/metaboxes/timeline.php';


if (defined('WP_CLI') && WP_CLI) {
    require_once PERACRM_INC . '/cli/push_cli.php';
    require_once PERACRM_INC . '/cli/campaign_cli.php';
}

if (is_admin()) {
    $admin_dir = __DIR__ . '/admin';
    $admin_files = [
        'importer.php',
        'admin.php',
        'metaboxes.php',
        'pages.php',
        'actions.php',
        'assets.php',
    ];

    foreach ($admin_files as $admin_file) {
        $admin_path = $admin_dir . '/' . $admin_file;
        if (file_exists($admin_path)) {
            require_once $admin_path;
        }
    }
}

add_action('init', 'peracrm_register_cpt_crm_client', 5);
