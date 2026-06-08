<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('peracrm_header_search_user_can_access')) {
    function peracrm_header_search_user_can_access(): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        if (function_exists('pera_crm_user_can_access') && pera_crm_user_can_access()) {
            return true;
        }

        if (function_exists('peracrm_user_can_access_crm') && peracrm_user_can_access_crm()) {
            return true;
        }

        return current_user_can('manage_options')
            || current_user_can('edit_crm_clients')
            || current_user_can('edit_crm_leads')
            || current_user_can('edit_crm_deals');
    }
}

if (!function_exists('peracrm_header_search_scope_client_ids')) {
    /**
     * Resolve the client scope for the active CRM view.
     *
     * Managers/admins keep full scope unless they are impersonating a scoped CRM user.
     * Employees and impersonated scoped views are limited to allowed client IDs.
     *
     * @return int[]|null Null means full scope; array means restrict to those IDs.
     */
    function peracrm_header_search_scope_client_ids(): ?array
    {
        $real_user_id = get_current_user_id();
        $effective_user_id = function_exists('peracrm_get_effective_crm_user_id') ? peracrm_get_effective_crm_user_id() : $real_user_id;
        $is_impersonating = function_exists('peracrm_is_impersonating_crm_user') && peracrm_is_impersonating_crm_user();
        $effective_is_employee = function_exists('pera_crm_user_is_employee') && pera_crm_user_is_employee((int) $effective_user_id);
        $can_manage_all = current_user_can('manage_options') || current_user_can('peracrm_manage_all_clients');

        if ($can_manage_all && !$is_impersonating && !$effective_is_employee) {
            return null;
        }

        if (!$is_impersonating && !$effective_is_employee) {
            return null;
        }

        if (function_exists('pera_crm_get_allowed_client_ids_for_user')) {
            return array_values(array_unique(array_filter(array_map('absint', pera_crm_get_allowed_client_ids_for_user((int) $effective_user_id)))));
        }

        $allowed_ids = apply_filters('peracrm_allowed_client_ids_for_user', null, (int) $effective_user_id);
        return is_array($allowed_ids) ? array_values(array_unique(array_filter(array_map('absint', $allowed_ids)))) : [];
    }
}

if (!function_exists('peracrm_header_search_first_meta_value')) {
    function peracrm_header_search_first_meta_value(int $post_id, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) get_post_meta($post_id, (string) $key, true));
            if ('' !== $value) {
                return wp_strip_all_tags($value);
            }
        }

        return '';
    }
}

if (!function_exists('peracrm_header_search_label_from_key')) {
    function peracrm_header_search_label_from_key(string $key, array $labels): string
    {
        $key = sanitize_key($key);
        if ('' === $key) {
            return '';
        }

        if (isset($labels[$key])) {
            return wp_strip_all_tags((string) $labels[$key]);
        }

        return ucwords(str_replace(['_', '-'], ' ', $key));
    }
}

if (!function_exists('peracrm_header_search_results')) {
    /**
     * @return array<int,array{id:int,title:string,url:string,type_label:string,stage_label:string,email:string,phone:string}>
     */
    function peracrm_header_search_results(string $term, int $limit = 8): array
    {
        global $wpdb;

        $term = trim(wp_strip_all_tags($term));
        if (strlen($term) < 2) {
            return [];
        }

        $limit = max(1, min(10, absint($limit)));
        $scoped_ids = peracrm_header_search_scope_client_ids();
        if (is_array($scoped_ids) && empty($scoped_ids)) {
            return [];
        }

        $meta_keys = [
            '_peracrm_email',
            'crm_primary_email',
            'primary_email',
            '_peracrm_phone',
            'crm_phone',
            'crm_primary_phone',
            'primary_phone',
            'phone',
        ];
        $post_statuses = ['publish', 'private', 'draft', 'pending', 'future'];
        $like = '%' . $wpdb->esc_like($term) . '%';
        $prefix_like = $wpdb->esc_like($term) . '%';

        $meta_placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));
        $status_placeholders = implode(',', array_fill(0, count($post_statuses), '%s'));
        $params = array_merge($meta_keys, $post_statuses, [$like, $like]);

        $scope_sql = '';
        if (is_array($scoped_ids)) {
            $scope_placeholders = implode(',', array_fill(0, count($scoped_ids), '%d'));
            $scope_sql = " AND p.ID IN ({$scope_placeholders})";
            $params = array_merge($params, $scoped_ids);
        }

        $params[] = $prefix_like;
        $params[] = $like;
        $params[] = $limit;

        $sql = "
            SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm
                ON pm.post_id = p.ID
                AND pm.meta_key IN ({$meta_placeholders})
            WHERE p.post_type = 'crm_client'
                AND p.post_status IN ({$status_placeholders})
                AND (p.post_title LIKE %s OR pm.meta_value LIKE %s)
                {$scope_sql}
            ORDER BY
                CASE
                    WHEN p.post_title LIKE %s THEN 0
                    WHEN p.post_title LIKE %s THEN 1
                    ELSE 2
                END,
                p.post_title ASC,
                p.ID DESC
            LIMIT %d
        ";

        $ids = $wpdb->get_col($wpdb->prepare($sql, $params)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        $ids = array_values(array_filter(array_map('absint', (array) $ids)));
        if (empty($ids)) {
            return [];
        }

        $type_options = function_exists('peracrm_client_type_options') ? (array) peracrm_client_type_options() : [];
        $stage_options = function_exists('pera_crm_get_pipeline_stages') ? (array) pera_crm_get_pipeline_stages() : [];
        $party_map = function_exists('peracrm_party_get_status_by_ids') ? (array) peracrm_party_get_status_by_ids($ids) : [];
        $results = [];

        foreach ($ids as $id) {
            $title = trim(wp_strip_all_tags((string) get_the_title($id)));
            if ('' === $title) {
                $title = sprintf(__('Client #%d', 'peracrm'), $id);
            }

            $type_key = sanitize_key((string) get_post_meta($id, '_peracrm_client_type', true));
            if ('' === $type_key) {
                $type_key = sanitize_key((string) get_post_meta($id, 'peracrm_client_type', true));
            }

            $party = isset($party_map[$id]) && is_array($party_map[$id]) ? $party_map[$id] : [];
            $stage_key = sanitize_key((string) ($party['lead_pipeline_stage'] ?? ''));
            if ('' === $stage_key) {
                $stage_key = sanitize_key((string) get_post_meta($id, '_peracrm_status', true));
            }

            $url = function_exists('pera_crm_get_client_view_url')
                ? (string) pera_crm_get_client_view_url($id)
                : home_url('/crm/client/' . $id . '/');

            $results[] = [
                'id' => $id,
                'title' => $title,
                'url' => esc_url_raw($url),
                'type_label' => peracrm_header_search_label_from_key($type_key, $type_options),
                'stage_label' => peracrm_header_search_label_from_key($stage_key, $stage_options),
                'email' => peracrm_header_search_first_meta_value($id, ['_peracrm_email', 'crm_primary_email', 'primary_email']),
                'phone' => peracrm_header_search_first_meta_value($id, ['_peracrm_phone', 'crm_phone', 'crm_primary_phone', 'primary_phone', 'phone']),
            ];
        }

        return $results;
    }
}

if (!function_exists('peracrm_header_search_ajax')) {
    function peracrm_header_search_ajax(): void
    {
        if (!is_user_logged_in() || !peracrm_header_search_user_can_access()) {
            wp_send_json_error([
                'ok' => false,
                'message' => __('Unauthorized.', 'peracrm'),
            ], 403);
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'peracrm_header_search')) {
            wp_send_json_error([
                'ok' => false,
                'message' => __('Security check failed.', 'peracrm'),
            ], 403);
        }

        $term = isset($_POST['q']) ? sanitize_text_field(wp_unslash((string) $_POST['q'])) : '';
        if (strlen(trim($term)) < 2) {
            wp_send_json_success([
                'ok' => true,
                'results' => [],
            ]);
        }

        $callback = static function () use ($term): void {
            wp_send_json_success([
                'ok' => true,
                'results' => peracrm_header_search_results($term, 8),
            ]);
        };

        if (function_exists('peracrm_with_target_blog')) {
            peracrm_with_target_blog($callback);
            return;
        }

        $callback();
    }
}
add_action('wp_ajax_peracrm_header_search', 'peracrm_header_search_ajax');
