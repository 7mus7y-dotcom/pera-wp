<?php

if (!defined('ABSPATH')) {
    exit;
}

function pera_portal_register_diagnostics_submenu()
{
    add_submenu_page(
        'pera-portal',
        __('Diagnostics', 'pera-portal'),
        __('Diagnostics', 'pera-portal'),
        'read',
        'pera-portal-diagnostics',
        'pera_portal_render_diagnostics_page'
    );
}

function pera_portal_hide_disallowed_diagnostics_submenu()
{
    if (function_exists('pera_portal_user_is_allowed_for_admin_ui') && pera_portal_user_is_allowed_for_admin_ui()) {
        return;
    }

    remove_submenu_page('pera-portal', 'pera-portal-diagnostics');
}

function pera_portal_get_building_options_for_diagnostics()
{
    return get_posts([
        'post_type' => 'pera_building',
        'post_status' => ['publish', 'private', 'draft'],
        'orderby' => 'title',
        'order' => 'ASC',
        'posts_per_page' => -1,
    ]);
}

function pera_portal_get_floor_options_for_building_diagnostics($building_id)
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

function pera_portal_render_diagnostics_page()
{
    if (!function_exists('pera_portal_current_user_can_access') || !pera_portal_current_user_can_access()) {
        wp_die(esc_html__('Access denied.', 'pera-portal'));
    }

    $building_id = isset($_GET['building_id']) ? absint(wp_unslash($_GET['building_id'])) : 0;
    $floor_id = isset($_GET['floor_id']) ? absint(wp_unslash($_GET['floor_id'])) : 0;
    $run_diagnostics = isset($_GET['run_diagnostics']) && (string) wp_unslash($_GET['run_diagnostics']) === '1';
    $export = isset($_GET['export']) ? sanitize_key((string) wp_unslash($_GET['export'])) : '';

    $buildings = pera_portal_get_building_options_for_diagnostics();
    $floors = pera_portal_get_floor_options_for_building_diagnostics($building_id);

    $floor_valid = false;
    foreach ($floors as $floor_post) {
        if ((int) $floor_post->ID === $floor_id) {
            $floor_valid = true;
            break;
        }
    }

    if (!$floor_valid) {
        $floor_id = 0;
        $run_diagnostics = false;
    }

    $report = null;
    $error_message = '';

    if ($run_diagnostics && $floor_id > 0) {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'pera_portal_run_diagnostics')) {
            $error_message = __('Security check failed. Please run diagnostics again.', 'pera-portal');
        } else {
            $result = PeraPortalDiagnosticsService::runForFloor($floor_id);
            if (is_wp_error($result)) {
                $error_message = $result->get_error_message();
            } else {
                $report = $result;
            }
        }
    }

    if ($report !== null && $export === 'json') {
        nocache_headers();
        header('Content-Type: application/json; charset=' . get_option('blog_charset'));
        echo wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $run_url = wp_nonce_url(add_query_arg([
        'page' => 'pera-portal-diagnostics',
        'building_id' => $building_id,
        'floor_id' => $floor_id,
        'run_diagnostics' => 1,
    ], admin_url('admin.php')), 'pera_portal_run_diagnostics');

    $export_url = '';
    if ($report !== null) {
        $export_url = wp_nonce_url(add_query_arg([
            'page' => 'pera-portal-diagnostics',
            'building_id' => $building_id,
            'floor_id' => $floor_id,
            'run_diagnostics' => 1,
            'export' => 'json',
        ], admin_url('admin.php')), 'pera_portal_run_diagnostics');
    }

    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Pera Portal Diagnostics', 'pera-portal'); ?></h1>

        <form method="get">
            <input type="hidden" name="page" value="pera-portal-diagnostics" />
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="pera_portal_diag_building_id"><?php echo esc_html__('Building', 'pera-portal'); ?></label>
                        </th>
                        <td>
                            <select class="regular-text" name="building_id" id="pera_portal_diag_building_id">
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
                        <th scope="row">
                            <label for="pera_portal_diag_floor_id"><?php echo esc_html__('Floor', 'pera-portal'); ?></label>
                        </th>
                        <td>
                            <select class="regular-text" name="floor_id" id="pera_portal_diag_floor_id" <?php disabled($building_id <= 0); ?>>
                                <option value="0"><?php echo esc_html__('Select a floor', 'pera-portal'); ?></option>
                                <?php foreach ($floors as $floor_post) : ?>
                                    <option value="<?php echo esc_attr((string) $floor_post->ID); ?>" <?php selected($floor_id, (int) $floor_post->ID); ?>>
                                        <?php echo esc_html((string) $floor_post->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p>
                <button type="submit" class="button"><?php echo esc_html__('Load Selection', 'pera-portal'); ?></button>
                <?php if ($floor_id > 0) : ?>
                    <a class="button button-primary" href="<?php echo esc_url($run_url); ?>"><?php echo esc_html__('Run Diagnostics', 'pera-portal'); ?></a>
                <?php endif; ?>
                <?php if ($export_url !== '') : ?>
                    <a class="button" href="<?php echo esc_url($export_url); ?>"><?php echo esc_html__('Export JSON', 'pera-portal'); ?></a>
                <?php endif; ?>
            </p>
        </form>

        <?php if ($error_message !== '') : ?>
            <div class="notice notice-error"><p><?php echo esc_html($error_message); ?></p></div>
        <?php endif; ?>

        <?php if ($report !== null) : ?>
            <h2><?php echo esc_html__('Summary', 'pera-portal'); ?></h2>
            <table class="widefat striped" style="max-width:900px">
                <tbody>
                    <tr>
                        <th><?php echo esc_html__('Building', 'pera-portal'); ?></th>
                        <td><?php echo esc_html((string) ($report['context']['building_title'] ?? '')); ?> (#<?php echo esc_html((string) ($report['context']['building_id'] ?? 0)); ?>)</td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('Floor', 'pera-portal'); ?></th>
                        <td><?php echo esc_html((string) ($report['context']['floor_title'] ?? '')); ?> (#<?php echo esc_html((string) ($report['context']['floor_id'] ?? 0)); ?>)</td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__('SVG', 'pera-portal'); ?></th>
                        <td>
                            <?php echo !empty($report['svg']['has_svg']) ? esc_html__('Uploaded', 'pera-portal') : esc_html__('Missing', 'pera-portal'); ?>
                            (<?php echo esc_html__('source', 'pera-portal'); ?>: <?php echo esc_html((string) ($report['svg']['svg_source'] ?? '')); ?>,
                            <?php echo esc_html__('attachment', 'pera-portal'); ?>: #<?php echo esc_html((string) ($report['svg']['svg_attachment_id'] ?? 0)); ?>)
                        </td>
                    </tr>
                </tbody>
            </table>

            <h2><?php echo esc_html__('Counts', 'pera-portal'); ?></h2>
            <p>
                <strong><?php echo esc_html__('Units:', 'pera-portal'); ?></strong> <?php echo esc_html((string) ($report['summary']['total_units'] ?? 0)); ?> &nbsp;|&nbsp;
                <strong><?php echo esc_html__('SVG IDs:', 'pera-portal'); ?></strong> <?php echo esc_html((string) ($report['summary']['total_svg_ids'] ?? 0)); ?> &nbsp;|&nbsp;
                <strong><?php echo esc_html__('Matched Units:', 'pera-portal'); ?></strong> <?php echo esc_html((string) ($report['summary']['matched_units'] ?? 0)); ?> &nbsp;|&nbsp;
                <strong><?php echo esc_html__('Errors:', 'pera-portal'); ?></strong> <?php echo esc_html((string) ($report['summary']['error_count'] ?? 0)); ?> &nbsp;|&nbsp;
                <strong><?php echo esc_html__('Warnings:', 'pera-portal'); ?></strong> <?php echo esc_html((string) ($report['summary']['warning_count'] ?? 0)); ?> &nbsp;|&nbsp;
                <strong><?php echo esc_html__('Info:', 'pera-portal'); ?></strong> <?php echo esc_html((string) ($report['summary']['info_count'] ?? 0)); ?>
            </p>

            <?php $issues_by_rule = isset($report['issues_by_rule']) && is_array($report['issues_by_rule']) ? $report['issues_by_rule'] : []; ?>
            <?php if (empty($issues_by_rule)) : ?>
                <div class="notice notice-success"><p><?php echo esc_html__('No issues found for this floor.', 'pera-portal'); ?></p></div>
            <?php else : ?>
                <h2><?php echo esc_html__('Issues by Rule', 'pera-portal'); ?></h2>
                <?php foreach ($issues_by_rule as $rule => $rule_issues) : ?>
                    <h3><?php echo esc_html((string) $rule); ?> (<?php echo esc_html((string) count($rule_issues)); ?>)</h3>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Severity', 'pera-portal'); ?></th>
                                <th><?php echo esc_html__('Message', 'pera-portal'); ?></th>
                                <th><?php echo esc_html__('Unit ID', 'pera-portal'); ?></th>
                                <th><?php echo esc_html__('Unit Code', 'pera-portal'); ?></th>
                                <th><?php echo esc_html__('SVG ID', 'pera-portal'); ?></th>
                                <th><?php echo esc_html__('Recommended Action', 'pera-portal'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rule_issues as $issue) : ?>
                                <tr>
                                    <td><?php echo esc_html((string) ($issue['severity'] ?? '')); ?></td>
                                    <td><?php echo esc_html((string) ($issue['message'] ?? '')); ?></td>
                                    <td><?php echo esc_html((string) ($issue['unit_id'] ?? 0)); ?></td>
                                    <td><?php echo esc_html((string) ($issue['unit_code'] ?? '')); ?></td>
                                    <td><?php echo esc_html((string) ($issue['svg_id'] ?? '')); ?></td>
                                    <td><?php echo esc_html((string) ($issue['recommended_action'] ?? '')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}

add_action('admin_menu', 'pera_portal_register_diagnostics_submenu');
add_action('network_admin_menu', 'pera_portal_register_diagnostics_submenu');
add_action('admin_menu', 'pera_portal_hide_disallowed_diagnostics_submenu', 99);
add_action('network_admin_menu', 'pera_portal_hide_disallowed_diagnostics_submenu', 99);
