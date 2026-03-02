<?php

if (!defined('ABSPATH')) {
    exit;
}

function pera_portal_register_viewer_submenu()
{
    add_submenu_page(
        'pera-portal',
        __('Viewer', 'pera-portal'),
        __('Viewer', 'pera-portal'),
        'read',
        'pera-portal-viewer',
        'pera_portal_render_viewer_page'
    );
}

function pera_portal_hide_disallowed_viewer_submenu()
{
    if (function_exists('pera_portal_user_is_allowed_for_admin_ui') && pera_portal_user_is_allowed_for_admin_ui()) {
        return;
    }

    remove_submenu_page('pera-portal', 'pera-portal-viewer');
}

function pera_portal_render_viewer_page()
{
    if (!function_exists('pera_portal_current_user_can_access') || !pera_portal_current_user_can_access()) {
        wp_die(esc_html__('Access denied.', 'pera-portal'));
    }

    $building_id = isset($_GET['building_id']) ? absint(wp_unslash($_GET['building_id'])) : 0;
    $floor_id = isset($_GET['floor_id']) ? absint(wp_unslash($_GET['floor_id'])) : 0;

    $buildings = get_posts([
        'post_type' => 'pera_building',
        'post_status' => 'publish',
        'orderby' => 'title',
        'order' => 'ASC',
        'posts_per_page' => -1,
    ]);

    $floors = [];
    if ($building_id > 0) {
        $floors = get_posts([
            'post_type' => 'pera_floor',
            'post_status' => 'publish',
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

    $selected_floor_exists = false;
    foreach ($floors as $floor_post) {
        if ((int) $floor_post->ID === $floor_id) {
            $selected_floor_exists = true;
            break;
        }
    }

    if (!$selected_floor_exists) {
        $floor_id = 0;
    }

    if (function_exists('pera_portal_set_script_config')) {
        pera_portal_set_script_config([
            'rest_url' => esc_url_raw(rest_url(PERA_PORTAL_REST_NAMESPACE . '/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'building_id' => (string) $building_id,
            'floor_id' => (string) $floor_id,
        ]);
    }

    if (function_exists('pera_portal_mark_assets_needed')) {
        pera_portal_mark_assets_needed();
    }

    $open_viewer_url = add_query_arg([
        'page' => 'pera-portal-viewer',
        'building_id' => $building_id,
        'floor_id' => $floor_id,
    ], admin_url('admin.php'));

    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Pera Portal Viewer', 'pera-portal'); ?></h1>
        <form method="get">
            <input type="hidden" name="page" value="pera-portal-viewer" />
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="pera_portal_building_id"><?php echo esc_html__('Building', 'pera-portal'); ?></label>
                        </th>
                        <td>
                            <select class="regular-text" name="building_id" id="pera_portal_building_id">
                                <option value="0"><?php echo esc_html__('Select a building', 'pera-portal'); ?></option>
                                <?php foreach ($buildings as $building_post) : ?>
                                    <option value="<?php echo esc_attr((string) $building_post->ID); ?>" <?php selected($building_id, (int) $building_post->ID); ?>>
                                        <?php echo esc_html($building_post->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="pera_portal_floor_id"><?php echo esc_html__('Floor', 'pera-portal'); ?></label>
                        </th>
                        <td>
                            <select class="regular-text" name="floor_id" id="pera_portal_floor_id" <?php disabled($building_id <= 0); ?>>
                                <option value="0"><?php echo esc_html__('Select a floor', 'pera-portal'); ?></option>
                                <?php foreach ($floors as $floor_post) : ?>
                                    <?php
                                    $floor_number = get_post_meta($floor_post->ID, 'floor_number', true);
                                    $label = $floor_post->post_title;
                                    if ($floor_number !== '' && $floor_number !== null) {
                                        $label = sprintf(
                                            /* translators: 1: floor number, 2: floor post title */
                                            __('Floor %1$s — %2$s', 'pera-portal'),
                                            $floor_number,
                                            $floor_post->post_title
                                        );
                                    }
                                    ?>
                                    <option value="<?php echo esc_attr((string) $floor_post->ID); ?>" <?php selected($floor_id, (int) $floor_post->ID); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($building_id <= 0) : ?>
                                <p class="description"><?php echo esc_html__('Select a building first to load floors.', 'pera-portal'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p>
                <button type="submit" class="button button-primary"><?php echo esc_html__('Load', 'pera-portal'); ?></button>
                <a href="<?php echo esc_url($open_viewer_url); ?>#pera-portal-viewer-shell" target="_blank" rel="noopener" class="button"><?php echo esc_html__('Open viewer in new tab', 'pera-portal'); ?></a>
            </p>
        </form>

        <?php if ($floor_id > 0) : ?>
            <p><strong><?php echo esc_html__('Selected floor ID:', 'pera-portal'); ?></strong> <?php echo esc_html((string) $floor_id); ?></p>
            <div id="pera-portal-viewer-shell">
                <?php
                $template_path = PERA_PORTAL_PATH . '/templates/portal-shell.php';
                if (file_exists($template_path)) {
                    include $template_path;
                }
                ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

add_action('admin_menu', 'pera_portal_register_viewer_submenu');
add_action('network_admin_menu', 'pera_portal_register_viewer_submenu');
add_action('admin_menu', 'pera_portal_hide_disallowed_viewer_submenu', 99);
add_action('network_admin_menu', 'pera_portal_hide_disallowed_viewer_submenu', 99);
