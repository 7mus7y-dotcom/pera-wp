<?php
/**
 * CRM impersonation helpers.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('PERACRM_VIEW_AS_USER_META_KEY')) {
    define('PERACRM_VIEW_AS_USER_META_KEY', '_peracrm_view_as_user_id');
}

if (!function_exists('peracrm_get_real_user_id')) {
    /**
     * Get the real logged-in WordPress user ID.
     */
    function peracrm_get_real_user_id(): int
    {
        return (int) get_current_user_id();
    }
}

if (!function_exists('peracrm_get_actor_user_id')) {
    /**
     * Get the actor user ID for write/audit actions.
     */
    function peracrm_get_actor_user_id(): int
    {
        return peracrm_get_real_user_id();
    }
}

if (!function_exists('peracrm_is_request_on_crm_route')) {
    /**
     * Detect CRM frontend requests without depending on route-gated load timing.
     */
    function peracrm_is_request_on_crm_route(): bool
    {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return false;
        }

        if (function_exists('pera_is_crm_route') && pera_is_crm_route()) {
            return true;
        }

        if (get_query_var('pera_crm')) {
            return true;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash((string) $_SERVER['REQUEST_URI']) : '';
        $request_path = wp_parse_url($request_uri, PHP_URL_PATH);
        $request_path = is_string($request_path) ? untrailingslashit($request_path) : '';

        return $request_path === '/crm' || strpos(trailingslashit($request_path), '/crm/') === 0;
    }
}

if (!function_exists('peracrm_current_user_can_view_as_advisor')) {
    /**
     * Determine whether the real user may impersonate CRM staff read scope.
     */
    function peracrm_current_user_can_view_as_advisor(): bool
    {
        $real_user_id = peracrm_get_real_user_id();
        if ($real_user_id <= 0) {
            return false;
        }

        $has_crm_access = function_exists('peracrm_user_can_access_crm')
            ? (bool) peracrm_user_can_access_crm($real_user_id)
            : (bool) (user_can($real_user_id, 'edit_crm_clients') || user_can($real_user_id, 'edit_crm_leads') || user_can($real_user_id, 'edit_crm_deals'));

        if (!$has_crm_access) {
            return false;
        }

        return user_can($real_user_id, 'peracrm_manage_all_clients')
            || user_can($real_user_id, 'peracrm_manage_assignments')
            || user_can($real_user_id, 'manage_options');
    }
}

if (!function_exists('peracrm_user_is_impersonatable_target')) {
    /**
     * Validate explicit advisor/employee impersonation targets only.
     *
     * Admins/managers are not valid targets unless they also qualify as an
     * employee/advisor assignee under the CRM role model.
     */
    function peracrm_user_is_impersonatable_target(int $user_id): bool
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0 || $user_id === peracrm_get_real_user_id()) {
            return false;
        }

        $user = get_userdata($user_id);
        if (!$user instanceof WP_User) {
            return false;
        }

        if (function_exists('peracrm_user_can_access_crm') && !peracrm_user_can_access_crm($user_id)) {
            return false;
        }

        if (function_exists('pera_crm_user_is_employee')) {
            return (bool) pera_crm_user_is_employee($user_id);
        }

        if (function_exists('peracrm_user_is_employee_advisor')) {
            return (bool) peracrm_user_is_employee_advisor($user_id);
        }

        return in_array('employee', (array) $user->roles, true)
            && !in_array('manager', (array) $user->roles, true)
            && !in_array('administrator', (array) $user->roles, true);
    }
}

if (!function_exists('peracrm_get_impersonation_targets')) {
    /**
     * Return valid advisor impersonation targets for selector UIs.
     *
     * @return array<int,array{id:int,display_name:string,role_label:string}>
     */
    function peracrm_get_impersonation_targets(): array
    {
        if (!peracrm_current_user_can_view_as_advisor()) {
            return [];
        }

        $users = get_users([
            'role__in' => ['employee'],
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => ['ID', 'display_name', 'roles'],
        ]);

        $targets = [];
        foreach ($users as $user) {
            if (!$user instanceof WP_User) {
                continue;
            }

            $user_id = (int) $user->ID;
            if (!peracrm_user_is_impersonatable_target($user_id)) {
                continue;
            }

            $targets[] = [
                'id' => $user_id,
                'display_name' => (string) $user->display_name,
                'role_label' => __('Advisor', 'peracrm'),
            ];
        }

        usort($targets, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['display_name'] ?? ''), (string) ($right['display_name'] ?? ''));
        });

        return $targets;
    }
}

if (!function_exists('peracrm_get_impersonated_crm_user_id')) {
    /**
     * Get the stored impersonated CRM user ID for the current real user.
     */
    function peracrm_get_impersonated_crm_user_id(): int
    {
        $real_user_id = peracrm_get_real_user_id();
        if ($real_user_id <= 0 || !peracrm_current_user_can_view_as_advisor()) {
            return 0;
        }

        $stored_user_id = (int) get_user_meta($real_user_id, PERACRM_VIEW_AS_USER_META_KEY, true);
        if ($stored_user_id <= 0) {
            return 0;
        }

        if (!peracrm_user_is_impersonatable_target($stored_user_id)) {
            delete_user_meta($real_user_id, PERACRM_VIEW_AS_USER_META_KEY);
            return 0;
        }

        return $stored_user_id;
    }
}

if (!function_exists('peracrm_is_impersonating_crm_user')) {
    /**
     * Whether the current user is actively impersonating CRM scope.
     */
    function peracrm_is_impersonating_crm_user(): bool
    {
        return peracrm_get_impersonated_crm_user_id() > 0;
    }
}

if (!function_exists('peracrm_get_effective_crm_user_id')) {
    /**
     * Get the CRM user ID that should drive read-only scoping.
     */
    function peracrm_get_effective_crm_user_id(): int
    {
        $real_user_id = peracrm_get_real_user_id();
        $impersonated_user_id = peracrm_get_impersonated_crm_user_id();

        if ($impersonated_user_id > 0 && peracrm_current_user_can_view_as_advisor()) {
            return $impersonated_user_id;
        }

        return $real_user_id;
    }
}


if (!function_exists('peracrm_get_default_assignee_user_id')) {
    /**
     * Resolve the default advisor/owner user ID for CRM writes.
     *
     * Uses the effective CRM user during impersonation when that target is a
     * valid advisor, otherwise falls back to the provided default or actor.
     */
    function peracrm_get_default_assignee_user_id(int $fallback_user_id = 0): int
    {
        $effective_user_id = peracrm_get_effective_crm_user_id();
        if ($effective_user_id > 0 && function_exists('peracrm_user_is_valid_advisor') && peracrm_user_is_valid_advisor($effective_user_id)) {
            return $effective_user_id;
        }

        $fallback_user_id = (int) $fallback_user_id;
        if ($fallback_user_id > 0 && function_exists('peracrm_user_is_valid_advisor') && peracrm_user_is_valid_advisor($fallback_user_id)) {
            return $fallback_user_id;
        }

        $actor_user_id = peracrm_get_actor_user_id();
        if ($actor_user_id > 0 && function_exists('peracrm_user_is_valid_advisor') && peracrm_user_is_valid_advisor($actor_user_id)) {
            return $actor_user_id;
        }

        return $fallback_user_id > 0 ? $fallback_user_id : $actor_user_id;
    }
}

if (!function_exists('peracrm_resolve_assignee_user_id')) {
    /**
     * Resolve an explicit advisor/owner request when valid, otherwise use the default CRM assignee.
     */
    function peracrm_resolve_assignee_user_id(int $requested_user_id = 0, int $fallback_user_id = 0): int
    {
        $requested_user_id = (int) $requested_user_id;
        if ($requested_user_id > 0 && function_exists('peracrm_user_is_valid_advisor') && peracrm_user_is_valid_advisor($requested_user_id)) {
            return $requested_user_id;
        }

        return peracrm_get_default_assignee_user_id($fallback_user_id);
    }
}

if (!function_exists('peracrm_set_impersonated_crm_user_id')) {
    /**
     * Persist an impersonated CRM target for the current real user.
     */
    function peracrm_set_impersonated_crm_user_id(int $user_id): bool
    {
        $real_user_id = peracrm_get_real_user_id();
        $user_id = (int) $user_id;

        if ($real_user_id <= 0 || !peracrm_current_user_can_view_as_advisor() || !peracrm_user_is_impersonatable_target($user_id)) {
            return false;
        }

        return false !== update_user_meta($real_user_id, PERACRM_VIEW_AS_USER_META_KEY, $user_id);
    }
}

if (!function_exists('peracrm_clear_impersonated_crm_user_id')) {
    /**
     * Clear the stored impersonation target for the current real user.
     */
    function peracrm_clear_impersonated_crm_user_id(): void
    {
        $real_user_id = peracrm_get_real_user_id();
        if ($real_user_id <= 0) {
            return;
        }

        delete_user_meta($real_user_id, PERACRM_VIEW_AS_USER_META_KEY);
    }
}

if (!function_exists('peracrm_get_effective_crm_user_label')) {
    /**
     * Get the current effective CRM user display name.
     */
    function peracrm_get_effective_crm_user_label(): string
    {
        $user = get_userdata(peracrm_get_effective_crm_user_id());

        return $user instanceof WP_User ? (string) $user->display_name : '';
    }
}

if (!function_exists('peracrm_get_real_user_label')) {
    /**
     * Get the current real user display name.
     */
    function peracrm_get_real_user_label(): string
    {
        $user = get_userdata(peracrm_get_real_user_id());

        return $user instanceof WP_User ? (string) $user->display_name : '';
    }
}

if (!function_exists('peracrm_get_impersonation_ui_state')) {
    /**
     * Build UI data for CRM impersonation controls.
     *
     * @return array<string,mixed>
     */
    function peracrm_get_impersonation_ui_state(): array
    {
        $can_impersonate = is_user_logged_in()
            && peracrm_is_request_on_crm_route()
            && peracrm_current_user_can_view_as_advisor();

        $targets = $can_impersonate ? peracrm_get_impersonation_targets() : [];
        $is_impersonating = $can_impersonate && peracrm_is_impersonating_crm_user();
        $effective_user_id = $can_impersonate ? peracrm_get_effective_crm_user_id() : 0;

        return [
            'can_impersonate' => $can_impersonate,
            'is_impersonating' => $is_impersonating,
            'targets' => $targets,
            'effective_user_id' => $effective_user_id,
            'effective_user_label' => $is_impersonating ? peracrm_get_effective_crm_user_label() : __('My view', 'peracrm'),
            'real_user_label' => $is_impersonating ? peracrm_get_real_user_label() : '',
            'admin_post_url' => $can_impersonate ? admin_url('admin-post.php') : '',
        ];
    }
}

if (!function_exists('peracrm_get_impersonation_redirect_url')) {
    /**
     * Resolve a safe CRM redirect target after impersonation state changes.
     */
    function peracrm_get_impersonation_redirect_url(): string
    {
        $fallback = home_url('/crm/');
        $redirect = wp_get_referer();
        $redirect = $redirect ? wp_validate_redirect($redirect, $fallback) : $fallback;

        $path = wp_parse_url($redirect, PHP_URL_PATH);
        $path = is_string($path) ? $path : '';
        if (0 !== strpos(trailingslashit($path), '/crm/')) {
            return $fallback;
        }

        return $redirect;
    }
}

if (!function_exists('peracrm_handle_set_view_as_advisor')) {
    /**
     * Persist impersonation state for the current real user.
     */
    function peracrm_handle_set_view_as_advisor(): void
    {
        if (!is_user_logged_in() || !peracrm_current_user_can_view_as_advisor()) {
            wp_die(esc_html__('Unauthorized.', 'peracrm'), 403);
        }

        check_admin_referer('peracrm_set_view_as_advisor', 'peracrm_view_as_nonce');

        $target_user_id = isset($_POST['peracrm_view_as_user_id']) ? absint(wp_unslash((string) $_POST['peracrm_view_as_user_id'])) : 0;
        if ($target_user_id <= 0) {
            peracrm_clear_impersonated_crm_user_id();
            if (function_exists('pera_crm_set_flash_message')) {
                pera_crm_set_flash_message(__('Returned to your CRM view.', 'peracrm'), 'success');
            }
            wp_safe_redirect(peracrm_get_impersonation_redirect_url());
            exit;
        }

        if (!peracrm_set_impersonated_crm_user_id($target_user_id)) {
            if (function_exists('pera_crm_set_flash_message')) {
                pera_crm_set_flash_message(__('Unable to switch CRM view.', 'peracrm'), 'error');
            }
            wp_safe_redirect(peracrm_get_impersonation_redirect_url());
            exit;
        }

        if (function_exists('pera_crm_set_flash_message')) {
            pera_crm_set_flash_message(__('CRM view updated.', 'peracrm'), 'success');
        }

        wp_safe_redirect(peracrm_get_impersonation_redirect_url());
        exit;
    }
}
add_action('admin_post_peracrm_set_view_as_advisor', 'peracrm_handle_set_view_as_advisor');

if (!function_exists('peracrm_handle_clear_view_as_advisor')) {
    /**
     * Clear impersonation state for the current real user.
     */
    function peracrm_handle_clear_view_as_advisor(): void
    {
        if (!is_user_logged_in() || !peracrm_current_user_can_view_as_advisor()) {
            wp_die(esc_html__('Unauthorized.', 'peracrm'), 403);
        }

        check_admin_referer('peracrm_clear_view_as_advisor', 'peracrm_view_as_nonce');

        peracrm_clear_impersonated_crm_user_id();

        if (function_exists('pera_crm_set_flash_message')) {
            pera_crm_set_flash_message(__('Returned to your CRM view.', 'peracrm'), 'success');
        }

        wp_safe_redirect(peracrm_get_impersonation_redirect_url());
        exit;
    }
}
add_action('admin_post_peracrm_clear_view_as_advisor', 'peracrm_handle_clear_view_as_advisor');
