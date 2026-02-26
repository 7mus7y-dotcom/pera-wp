<?php

if (!defined('ABSPATH')) {
    exit;
}

function pera_portal_register_admin_menu()
{
    add_menu_page(
        __('Pera Portal', 'pera-portal'),
        __('Pera Portal', 'pera-portal'),
        'read',
        'pera-portal',
        'pera_portal_render_admin_page',
        'dashicons-admin-multisite',
        3
    );
}

function pera_portal_render_admin_page()
{
    if (!function_exists('pera_portal_current_user_can_access') || !pera_portal_current_user_can_access()) {
        wp_die(esc_html__('Access denied.', 'pera-portal'));
    }

    $floor_id = isset($_GET['floor_id']) ? absint(wp_unslash($_GET['floor_id'])) : 0;

    $floors = get_posts([
        'post_type' => 'pera_floor',
        'post_status' => 'any',
        'orderby' => 'title',
        'order' => 'ASC',
        'posts_per_page' => -1,
    ]);

    if (function_exists('pera_portal_mark_assets_needed')) {
        pera_portal_mark_assets_needed();
    }

    if (function_exists('pera_portal_set_script_config')) {
        pera_portal_set_script_config([
            'rest_url' => esc_url_raw(rest_url(PERA_PORTAL_REST_NAMESPACE . '/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'building_id' => 0,
            'floor_id' => $floor_id,
        ]);
    }

    if (wp_script_is('pera-portal-viewer', 'enqueued') && !empty($GLOBALS['pera_portal_script_config']) && is_array($GLOBALS['pera_portal_script_config'])) {
        wp_localize_script('pera-portal-viewer', 'PeraPortalConfig', $GLOBALS['pera_portal_script_config']);
    }

    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Pera Portal', 'pera-portal'); ?></h1>
        <form method="get" style="margin:12px 0;">
            <input type="hidden" name="page" value="pera-portal" />
            <label for="pera_portal_floor_id"><?php echo esc_html__('Select floor', 'pera-portal'); ?></label>
            <select name="floor_id" id="pera_portal_floor_id">
                <option value="0"><?php echo esc_html__('All floors', 'pera-portal'); ?></option>
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
            <button class="button button-primary"><?php echo esc_html__('Load', 'pera-portal'); ?></button>
        </form>
        <?php
        $template_path = PERA_PORTAL_PATH . '/templates/portal-shell.php';
        if (file_exists($template_path)) {
            include $template_path;
        }
        ?>
    </div>
    <?php
}

add_action('admin_menu', 'pera_portal_register_admin_menu');
