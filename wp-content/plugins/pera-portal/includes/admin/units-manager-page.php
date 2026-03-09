<?php

if (!defined('ABSPATH')) {
    exit;
}

function pera_portal_register_units_manager_submenu()
{
    add_submenu_page(
        'pera-portal',
        __('Units Manager', 'pera-portal'),
        __('Units Manager', 'pera-portal'),
        'read',
        'pera-portal-units-manager',
        'pera_portal_render_units_manager_page'
    );
}

function pera_portal_hide_disallowed_units_manager_submenu()
{
    if (function_exists('pera_portal_user_is_allowed_for_admin_ui') && pera_portal_user_is_allowed_for_admin_ui()) {
        return;
    }

    remove_submenu_page('pera-portal', 'pera-portal-units-manager');
}

function pera_portal_units_manager_get_field($field_name, $post_id)
{
    if (function_exists('get_field')) {
        return get_field($field_name, $post_id);
    }

    return get_post_meta($post_id, $field_name, true);
}

function pera_portal_units_manager_get_building_options()
{
    return get_posts([
        'post_type' => 'pera_building',
        'post_status' => ['publish', 'private', 'draft'],
        'orderby' => 'title',
        'order' => 'ASC',
        'posts_per_page' => -1,
    ]);
}

function pera_portal_units_manager_get_floor_options_for_building($building_id)
{
    $building_id = absint($building_id);
    if ($building_id <= 0) {
        return [];
    }

    return get_posts([
        'post_type' => 'pera_floor',
        'post_status' => ['publish', 'private', 'draft'],
        'orderby' => 'title',
        'order' => 'ASC',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'building',
                'value' => (string) $building_id,
                'compare' => '=',
            ],
        ],
    ]);
}

function pera_portal_units_manager_get_units_for_floor($floor_id)
{
    $query = new WP_Query([
        'post_type' => 'pera_unit',
        'post_status' => ['publish', 'private', 'draft'],
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'meta_query' => [
            [
                'key' => 'floor',
                'value' => (string) absint($floor_id),
                'compare' => '=',
            ],
        ],
    ]);

    $rows = [];

    foreach ($query->posts as $unit_post) {
        $unit_id = (int) $unit_post->ID;

        $rows[] = [
            'id' => $unit_id,
            'title' => (string) $unit_post->post_title,
            'unit_code' => sanitize_text_field((string) pera_portal_units_manager_get_field('unit_code', $unit_id)),
            'unit_type' => sanitize_text_field((string) pera_portal_units_manager_get_field('unit_type', $unit_id)),
            'net_size' => pera_portal_units_manager_get_field('net_size', $unit_id),
            'gross_size' => pera_portal_units_manager_get_field('gross_size', $unit_id),
            'price' => pera_portal_units_manager_get_field('price', $unit_id),
            'currency' => sanitize_text_field((string) pera_portal_units_manager_get_field('currency', $unit_id)),
            'status' => sanitize_key((string) pera_portal_units_manager_get_field('status', $unit_id)),
            'sort_order' => pera_portal_units_manager_get_field('sort_order', $unit_id),
            'unit_detail_plan' => pera_portal_units_manager_get_field('unit_detail_plan', $unit_id),
        ];
    }

    $has_sort_order = false;
    foreach ($rows as $row) {
        if ($row['sort_order'] !== '' && $row['sort_order'] !== null && is_numeric($row['sort_order'])) {
            $has_sort_order = true;
            break;
        }
    }

    usort($rows, static function ($left, $right) use ($has_sort_order) {
        if ($has_sort_order) {
            $left_sort_order = is_numeric($left['sort_order']) ? (float) $left['sort_order'] : PHP_FLOAT_MAX;
            $right_sort_order = is_numeric($right['sort_order']) ? (float) $right['sort_order'] : PHP_FLOAT_MAX;

            if ($left_sort_order < $right_sort_order) {
                return -1;
            }

            if ($left_sort_order > $right_sort_order) {
                return 1;
            }
        }

        $left_code = strtolower((string) $left['unit_code']);
        $right_code = strtolower((string) $right['unit_code']);

        if ($left_code !== '' || $right_code !== '') {
            if ($left_code < $right_code) {
                return -1;
            }

            if ($left_code > $right_code) {
                return 1;
            }
        }

        $left_title = strtolower((string) $left['title']);
        $right_title = strtolower((string) $right['title']);

        if ($left_title < $right_title) {
            return -1;
        }

        if ($left_title > $right_title) {
            return 1;
        }

        return (int) $left['id'] <=> (int) $right['id'];
    });

    return $rows;
}

function pera_portal_units_manager_get_plan_meta($plan_field)
{
    $attachment_id = 0;
    $url = '';

    if (is_array($plan_field)) {
        if (!empty($plan_field['ID'])) {
            $attachment_id = absint($plan_field['ID']);
        }

        if (!empty($plan_field['url'])) {
            $url = esc_url_raw((string) $plan_field['url']);
        }
    } elseif (is_numeric($plan_field)) {
        $attachment_id = absint($plan_field);
    } elseif (is_string($plan_field) && filter_var($plan_field, FILTER_VALIDATE_URL)) {
        $url = esc_url_raw($plan_field);
    }

    if ($attachment_id > 0 && $url === '') {
        $attachment_url = wp_get_attachment_url($attachment_id);
        if (is_string($attachment_url) && $attachment_url !== '') {
            $url = esc_url_raw($attachment_url);
        }
    }

    return [
        'has_plan' => $attachment_id > 0 || $url !== '',
        'attachment_id' => $attachment_id,
        'url' => $url,
    ];
}

function pera_portal_units_manager_build_unit_diagnostics_map(array $issues)
{
    $map = [];

    foreach ($issues as $issue) {
        $unit_id = isset($issue['unit_id']) ? absint($issue['unit_id']) : 0;
        if ($unit_id <= 0) {
            continue;
        }

        if (!isset($map[$unit_id])) {
            $map[$unit_id] = [
                'error' => 0,
                'warning' => 0,
            ];
        }

        $severity = isset($issue['severity']) ? (string) $issue['severity'] : 'info';

        if ($severity === 'error') {
            $map[$unit_id]['error']++;
            continue;
        }

        if ($severity === 'warning') {
            $map[$unit_id]['warning']++;
        }
    }

    return $map;
}

function pera_portal_render_units_manager_page()
{
    if (!function_exists('pera_portal_current_user_can_access') || !pera_portal_current_user_can_access()) {
        wp_die(esc_html__('Access denied.', 'pera-portal'));
    }

    $building_id = isset($_GET['building_id']) ? absint(wp_unslash($_GET['building_id'])) : 0;
    $floor_id = isset($_GET['floor_id']) ? absint(wp_unslash($_GET['floor_id'])) : 0;
    $status_filter = isset($_GET['status']) ? sanitize_key((string) wp_unslash($_GET['status'])) : 'all';
    $unit_code_search = isset($_GET['unit_code']) ? sanitize_text_field((string) wp_unslash($_GET['unit_code'])) : '';

    if (!in_array($status_filter, ['all', 'available', 'reserved', 'sold'], true)) {
        $status_filter = 'all';
    }

    $buildings = pera_portal_units_manager_get_building_options();
    $floors = pera_portal_units_manager_get_floor_options_for_building($building_id);

    $valid_floor_ids = array_map('absint', wp_list_pluck($floors, 'ID'));
    if ($floor_id > 0 && !in_array($floor_id, $valid_floor_ids, true)) {
        $floor_id = 0;
    }

    $diagnostics_report = null;
    $diagnostics_error = '';
    $diagnostics_map = [];

    if ($floor_id > 0) {
        $diagnostics_report = PeraPortalDiagnosticsService::runForFloor($floor_id);
        if (is_wp_error($diagnostics_report)) {
            $diagnostics_error = $diagnostics_report->get_error_message();
            $diagnostics_report = null;
        } else {
            $diagnostics_map = pera_portal_units_manager_build_unit_diagnostics_map(
                isset($diagnostics_report['issues']) && is_array($diagnostics_report['issues']) ? $diagnostics_report['issues'] : []
            );
        }
    }

    $units = [];
    if ($floor_id > 0) {
        $units = pera_portal_units_manager_get_units_for_floor($floor_id);

        if ($status_filter !== 'all') {
            $units = array_values(array_filter($units, static function ($row) use ($status_filter) {
                return (string) $row['status'] === $status_filter;
            }));
        }

        if ($unit_code_search !== '') {
            $units = array_values(array_filter($units, static function ($row) use ($unit_code_search) {
                return stripos((string) $row['unit_code'], $unit_code_search) !== false;
            }));
        }
    }

    $reset_url = add_query_arg([
        'page' => 'pera-portal-units-manager',
    ], admin_url('admin.php'));

    $diagnostics_url = '';
    if ($building_id > 0 && $floor_id > 0) {
        $diagnostics_url = wp_nonce_url(add_query_arg([
            'page' => 'pera-portal-diagnostics',
            'building_id' => $building_id,
            'floor_id' => $floor_id,
            'run_diagnostics' => 1,
        ], admin_url('admin.php')), 'pera_portal_run_diagnostics');
    }

    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Units Manager', 'pera-portal'); ?></h1>

        <form method="get">
            <input type="hidden" name="page" value="pera-portal-units-manager" />
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="pera_portal_units_building_id"><?php echo esc_html__('Building', 'pera-portal'); ?></label></th>
                        <td>
                            <select class="regular-text" name="building_id" id="pera_portal_units_building_id">
                                <option value="0"><?php echo esc_html__('Select a building', 'pera-portal'); ?></option>
                                <?php foreach ($buildings as $building_post) : ?>
                                    <option value="<?php echo esc_attr((string) $building_post->ID); ?>" <?php selected($building_id, (int) $building_post->ID); ?>>
                                        <?php echo esc_html((string) $building_post->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="pera_portal_units_floor_id"><?php echo esc_html__('Floor', 'pera-portal'); ?></label></th>
                        <td>
                            <select class="regular-text" name="floor_id" id="pera_portal_units_floor_id" <?php disabled($building_id <= 0); ?>>
                                <option value="0"><?php echo esc_html__('Select a floor', 'pera-portal'); ?></option>
                                <?php foreach ($floors as $floor_post) : ?>
                                    <option value="<?php echo esc_attr((string) $floor_post->ID); ?>" <?php selected($floor_id, (int) $floor_post->ID); ?>>
                                        <?php echo esc_html((string) $floor_post->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="pera_portal_units_unit_code"><?php echo esc_html__('Search Unit Code', 'pera-portal'); ?></label></th>
                        <td><input class="regular-text" type="text" name="unit_code" id="pera_portal_units_unit_code" value="<?php echo esc_attr($unit_code_search); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="pera_portal_units_status"><?php echo esc_html__('Status', 'pera-portal'); ?></label></th>
                        <td>
                            <select class="regular-text" name="status" id="pera_portal_units_status">
                                <option value="all" <?php selected($status_filter, 'all'); ?>><?php echo esc_html__('All', 'pera-portal'); ?></option>
                                <option value="available" <?php selected($status_filter, 'available'); ?>><?php echo esc_html__('Available', 'pera-portal'); ?></option>
                                <option value="reserved" <?php selected($status_filter, 'reserved'); ?>><?php echo esc_html__('Reserved', 'pera-portal'); ?></option>
                                <option value="sold" <?php selected($status_filter, 'sold'); ?>><?php echo esc_html__('Sold', 'pera-portal'); ?></option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p>
                <button type="submit" class="button button-primary"><?php echo esc_html__('Apply Filters', 'pera-portal'); ?></button>
                <a class="button" href="<?php echo esc_url($reset_url); ?>"><?php echo esc_html__('Reset Filters', 'pera-portal'); ?></a>
                <?php if ($diagnostics_url !== '') : ?>
                    <a class="button" href="<?php echo esc_url($diagnostics_url); ?>"><?php echo esc_html__('View Diagnostics Summary', 'pera-portal'); ?></a>
                <?php endif; ?>
            </p>
        </form>

        <?php if ($diagnostics_error !== '') : ?>
            <div class="notice notice-error"><p><?php echo esc_html($diagnostics_error); ?></p></div>
        <?php endif; ?>

        <?php if ($floor_id <= 0) : ?>
            <div class="notice notice-info"><p><?php echo esc_html__('Select a building and floor to load units.', 'pera-portal'); ?></p></div>
        <?php else : ?>
            <?php if (is_array($diagnostics_report)) : ?>
                <div class="notice notice-info inline">
                    <p>
                        <strong><?php echo esc_html__('Diagnostics:', 'pera-portal'); ?></strong>
                        <?php
                        echo esc_html(sprintf(
                            /* translators: 1: error count, 2: warning count */
                            __('Errors: %1$d, Warnings: %2$d', 'pera-portal'),
                            (int) ($diagnostics_report['summary']['error_count'] ?? 0),
                            (int) ($diagnostics_report['summary']['warning_count'] ?? 0)
                        ));
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Unit ID', 'pera-portal'); ?></th>
                        <th><?php echo esc_html__('Unit Code', 'pera-portal'); ?></th>
                        <th><?php echo esc_html__('Type', 'pera-portal'); ?></th>
                        <th><?php echo esc_html__('Net Size', 'pera-portal'); ?></th>
                        <th><?php echo esc_html__('Gross Size', 'pera-portal'); ?></th>
                        <th><?php echo esc_html__('Price', 'pera-portal'); ?></th>
                        <th><?php echo esc_html__('Currency', 'pera-portal'); ?></th>
                        <th><?php echo esc_html__('Status', 'pera-portal'); ?></th>
                        <th><?php echo esc_html__('Plan', 'pera-portal'); ?></th>
                        <th><?php echo esc_html__('Diagnostics', 'pera-portal'); ?></th>
                        <th><?php echo esc_html__('Actions', 'pera-portal'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($units)) : ?>
                        <tr>
                            <td colspan="11"><?php echo esc_html__('No units found for the selected filters.', 'pera-portal'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($units as $unit_row) : ?>
                            <?php
                            $unit_id = (int) $unit_row['id'];
                            $unit_diagnostics = isset($diagnostics_map[$unit_id]) ? $diagnostics_map[$unit_id] : ['error' => 0, 'warning' => 0];
                            $issue_count = (int) $unit_diagnostics['error'] + (int) $unit_diagnostics['warning'];

                            if ((int) $unit_diagnostics['error'] > 0) {
                                $diagnostics_label = sprintf(
                                    /* translators: %d: issue count */
                                    __('Error (%d)', 'pera-portal'),
                                    $issue_count
                                );
                            } elseif ((int) $unit_diagnostics['warning'] > 0) {
                                $diagnostics_label = sprintf(
                                    /* translators: %d: issue count */
                                    __('Warning (%d)', 'pera-portal'),
                                    $issue_count
                                );
                            } else {
                                $diagnostics_label = __('OK', 'pera-portal');
                            }

                            $plan_meta = pera_portal_units_manager_get_plan_meta($unit_row['unit_detail_plan']);
                            $plan_label = $plan_meta['has_plan'] ? __('Present', 'pera-portal') : __('Missing', 'pera-portal');

                            $edit_url = get_edit_post_link($unit_id, '');
                            $view_url = get_permalink($unit_id);
                            ?>
                            <tr>
                                <td><?php echo esc_html((string) $unit_id); ?></td>
                                <td><?php echo $unit_row['unit_code'] !== '' ? esc_html((string) $unit_row['unit_code']) : '&mdash;'; ?></td>
                                <td><?php echo $unit_row['unit_type'] !== '' ? esc_html((string) $unit_row['unit_type']) : '&mdash;'; ?></td>
                                <td><?php echo $unit_row['net_size'] !== '' && $unit_row['net_size'] !== null ? esc_html((string) $unit_row['net_size']) : '&mdash;'; ?></td>
                                <td><?php echo $unit_row['gross_size'] !== '' && $unit_row['gross_size'] !== null ? esc_html((string) $unit_row['gross_size']) : '&mdash;'; ?></td>
                                <td><?php echo $unit_row['price'] !== '' && $unit_row['price'] !== null ? esc_html((string) $unit_row['price']) : '&mdash;'; ?></td>
                                <td><?php echo $unit_row['currency'] !== '' ? esc_html((string) $unit_row['currency']) : '&mdash;'; ?></td>
                                <td><?php echo $unit_row['status'] !== '' ? esc_html(ucfirst((string) $unit_row['status'])) : '&mdash;'; ?></td>
                                <td>
                                    <?php if ($plan_meta['has_plan'] && $plan_meta['url'] !== '') : ?>
                                        <a href="<?php echo esc_url((string) $plan_meta['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($plan_label); ?></a>
                                    <?php else : ?>
                                        <?php echo esc_html($plan_label); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($diagnostics_label); ?></td>
                                <td>
                                    <?php if (is_string($edit_url) && $edit_url !== '') : ?>
                                        <a href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html__('Edit', 'pera-portal'); ?></a>
                                    <?php endif; ?>
                                    <?php if (is_string($view_url) && $view_url !== '') : ?>
                                        <?php if (is_string($edit_url) && $edit_url !== '') : ?>
                                            &nbsp;|&nbsp;
                                        <?php endif; ?>
                                        <a href="<?php echo esc_url($view_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('View', 'pera-portal'); ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

add_action('admin_menu', 'pera_portal_register_units_manager_submenu');
add_action('network_admin_menu', 'pera_portal_register_units_manager_submenu');
add_action('admin_menu', 'pera_portal_hide_disallowed_units_manager_submenu', 99);
add_action('network_admin_menu', 'pera_portal_hide_disallowed_units_manager_submenu', 99);
