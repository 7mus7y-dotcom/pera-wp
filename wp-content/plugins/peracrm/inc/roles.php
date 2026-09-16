<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_ensure_roles_and_caps()
{
    peracrm_with_target_blog(static function () {
        $roles_to_ensure = [
            'manager' => 'Manager',
            'employee' => 'Employee',
        ];

        foreach ($roles_to_ensure as $slug => $label) {
            if (!get_role($slug)) {
                add_role($slug, $label, ['read' => true]);
            }
        }

        $caps_common = [
            'edit_crm_leads',
            'edit_crm_clients',
            'edit_crm_deals',
        ];

        $caps_reports = ['view_crm_reports'];

        $caps_management = [
            'peracrm_manage_all_clients',
            'peracrm_manage_assignments',
            'peracrm_manage_all_reminders',
        ];

        $caps_cpt_admin = [
            'edit_crm_client',
            'read_crm_client',
            'delete_crm_client',
            'edit_crm_clients',
            'edit_others_crm_clients',
            'publish_crm_clients',
            'read_private_crm_clients',
            'delete_crm_clients',
            'delete_private_crm_clients',
            'delete_published_crm_clients',
            'delete_others_crm_clients',
            'edit_private_crm_clients',
            'edit_published_crm_clients',
        ];

        $caps_cpt_employee = [
            'read_crm_client',
            'edit_crm_client',
            'edit_crm_clients',
            'read_private_crm_clients',
        ];

        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach (array_merge($caps_common, $caps_reports, $caps_management, $caps_cpt_admin) as $cap) {
                $admin_role->add_cap($cap);
            }
        }

        $manager_role = get_role('manager');
        if ($manager_role) {
            foreach (array_merge($caps_common, $caps_reports, $caps_management, $caps_cpt_employee) as $cap) {
                $manager_role->add_cap($cap);
            }
        }

        $employee_role = get_role('employee');
        if ($employee_role) {
            foreach (array_merge($caps_common, $caps_cpt_employee) as $cap) {
                $employee_role->add_cap($cap);
            }
            $employee_role->remove_cap('view_crm_reports');
        }
    });
}

/** Version 20: install the authorization-management capabilities. */
function peracrm_upgrade_roles_and_caps_v20()
{
    peracrm_ensure_roles_and_caps();
}

/**
 * Remove the temporary role only after operators have migrated every account.
 * Keeping the role while users remain is deliberate: silently deleting a role
 * would strand those users without an auditable migration decision.
 */
function peracrm_maybe_remove_legacy_advisor_role()
{
    peracrm_with_target_blog(static function () {
        if (!get_role('advisor')) {
            delete_option('peracrm_legacy_advisor_user_ids');
            return;
        }

        $legacy_user_ids = get_users(['role' => 'advisor', 'fields' => 'ID']);
        if (empty($legacy_user_ids)) {
            remove_role('advisor');
            delete_option('peracrm_legacy_advisor_user_ids');
            return;
        }

        update_option('peracrm_legacy_advisor_user_ids', array_values(array_map('intval', $legacy_user_ids)), false);
    });
}

add_action('init', 'peracrm_maybe_remove_legacy_advisor_role', 100);

function peracrm_legacy_advisor_migration_notice()
{
    $ids = peracrm_with_target_blog(static function () {
        // Evaluate the real user's authority in the same blog whose migration
        // state is being read. peracrm_with_target_blog always restores the
        // originating admin context, including on an early return.
        if (!current_user_can('manage_options')) {
            return [];
        }

        return (array) get_option('peracrm_legacy_advisor_user_ids', []);
    });

    if (empty($ids)) {
        return;
    }
    echo '<div class="notice notice-warning"><p>' . esc_html(sprintf(
        'PeraCRM: migrate legacy advisor role users before role removal. User IDs: %s',
        implode(', ', array_map('intval', $ids))
    )) . '</p></div>';
}

add_action('admin_notices', 'peracrm_legacy_advisor_migration_notice');
