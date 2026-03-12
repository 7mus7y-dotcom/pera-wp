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
            foreach (array_merge($caps_common, $caps_reports, $caps_cpt_admin) as $cap) {
                $admin_role->add_cap($cap);
            }
        }

        $manager_role = get_role('manager');
        if ($manager_role) {
            foreach (array_merge($caps_common, $caps_reports, $caps_cpt_employee) as $cap) {
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
