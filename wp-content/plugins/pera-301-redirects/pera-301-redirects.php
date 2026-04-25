<?php
/**
 * Plugin Name: Pera 301 Redirects
 * Description: Lightweight 301 redirect manager for administrators.
 * Version: 1.0.0
 * Author: Pera Property
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get redirect table name.
 *
 * @return string
 */
function pera_redirects_table_name()
{
    global $wpdb;

    return $wpdb->prefix . 'pera_301_redirects';
}

/**
 * Create redirects table on activation.
 *
 * @return void
 */
function pera_redirects_activate()
{
    global $wpdb;

    $table_name = pera_redirects_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        source_path VARCHAR(255) NOT NULL,
        target_url TEXT NOT NULL,
        status_code SMALLINT NOT NULL DEFAULT 301,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        hit_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
        last_hit_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY source_path (source_path)
    ) {$charset_collate};";

    dbDelta($sql);
}
register_activation_hook(__FILE__, 'pera_redirects_activate');

/**
 * Normalize source path.
 *
 * @param string $source Source value.
 * @return string
 */
function pera_redirects_normalize_source_path($source)
{
    $source = trim((string) $source);
    if ($source === '') {
        return '';
    }

    if (filter_var($source, FILTER_VALIDATE_URL)) {
        $host = wp_parse_url($source, PHP_URL_HOST);
        if ($host) {
            $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
            if ($site_host && strtolower($host) !== strtolower($site_host)) {
                return '';
            }
        }

        $path = (string) wp_parse_url($source, PHP_URL_PATH);
        $source = $path;
    }

    $source = strtok($source, '?');
    $source = trim((string) $source);
    if ($source === '') {
        return '';
    }

    if ($source[0] !== '/') {
        $source = '/' . ltrim($source, '/');
    }

    // Normalize repeated slashes and trailing spaces.
    $source = preg_replace('#/+#', '/', $source);

    return untrailingslashit($source) === '' ? '/' : untrailingslashit($source) . '/';
}

/**
 * Normalize target path when target is internal.
 *
 * @param string $target Target URL.
 * @return string
 */
function pera_redirects_normalize_target_path_if_internal($target)
{
    $target = trim((string) $target);
    if ($target === '') {
        return '';
    }

    if (0 === strpos($target, '/')) {
        $target_path = strtok($target, '?');
        return pera_redirects_normalize_source_path($target_path);
    }

    if (!filter_var($target, FILTER_VALIDATE_URL)) {
        return '';
    }

    $target_host = wp_parse_url($target, PHP_URL_HOST);
    $site_host = wp_parse_url(home_url(), PHP_URL_HOST);

    if (!$target_host || !$site_host || strtolower($target_host) !== strtolower($site_host)) {
        return '';
    }

    $path = (string) wp_parse_url($target, PHP_URL_PATH);

    return pera_redirects_normalize_source_path($path);
}

/**
 * Check if a target is a valid redirect destination saved by the plugin.
 *
 * @param string $target_url Target URL from DB/form.
 * @return bool
 */
function pera_redirects_is_valid_target_url($target_url)
{
    $target_url = trim((string) $target_url);
    if ($target_url === '') {
        return false;
    }

    // Allow internal relative paths.
    if (0 === strpos($target_url, '/')) {
        return true;
    }

    if (!wp_http_validate_url($target_url)) {
        return false;
    }

    $parsed = wp_parse_url($target_url);
    if (empty($parsed['scheme']) || empty($parsed['host'])) {
        return false;
    }

    // Hard allow only http/https for absolute targets.
    return in_array(strtolower($parsed['scheme']), array('http', 'https'), true);
}

/**
 * Validate redirect data.
 *
 * @param array $data Raw form data.
 * @return array{errors:array, sanitized:array}
 */
function pera_redirects_validate_redirect_data($data)
{
    $errors = array();

    $source_path = pera_redirects_normalize_source_path($data['source_path'] ?? '');
    $target_url = trim((string) ($data['target_url'] ?? ''));
    $status_code = (int) ($data['status_code'] ?? 301);
    $is_active = !empty($data['is_active']) ? 1 : 0;

    if ($source_path === '') {
        $errors[] = __('Source path is required and must be on this site.', 'pera-301-redirects');
    }

    if ($target_url === '') {
        $errors[] = __('Target URL is required.', 'pera-301-redirects');
    }

    if ($target_url !== '' && !pera_redirects_is_valid_target_url($target_url)) {
        $errors[] = __('Target URL must be relative (/path/) or a valid absolute URL.', 'pera-301-redirects');
    }

    if ($status_code < 300 || $status_code > 399) {
        $errors[] = __('Status code must be a valid redirect code (3xx).', 'pera-301-redirects');
    }

    $normalized_target_path = pera_redirects_normalize_target_path_if_internal($target_url);
    if ($source_path !== '' && $normalized_target_path !== '' && $source_path === $normalized_target_path) {
        $errors[] = __('Source and target cannot be identical.', 'pera-301-redirects');
    }

    return array(
        'errors' => $errors,
        'sanitized' => array(
            'source_path' => $source_path,
            'target_url' => esc_url_raw($target_url),
            'status_code' => $status_code,
            'is_active' => $is_active,
        ),
    );
}

/**
 * Register admin menu.
 *
 * @return void
 */
function pera_redirects_register_admin_menu()
{
    add_management_page(
        __('Redirects', 'pera-301-redirects'),
        __('Redirects', 'pera-301-redirects'),
        'manage_options',
        'pera-301-redirects',
        'pera_redirects_render_admin_page'
    );
}
add_action('admin_menu', 'pera_redirects_register_admin_menu');

/**
 * Handle admin create/update/delete/toggle actions.
 *
 * @return void
 */
function pera_redirects_handle_admin_actions()
{
    if (!is_admin()) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    if (!isset($_GET['page']) || $_GET['page'] !== 'pera-301-redirects') {
        return;
    }

    global $wpdb;
    $table_name = pera_redirects_table_name();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pera_redirects_action'])) {
        check_admin_referer('pera_redirects_save_redirect');

        $action = sanitize_text_field(wp_unslash($_POST['pera_redirects_action']));
        $validation = pera_redirects_validate_redirect_data(wp_unslash($_POST));

        if (!empty($validation['errors'])) {
            set_transient('pera_redirects_notice', array('type' => 'error', 'messages' => $validation['errors']), 30);
            return;
        }

        $redirect_data = $validation['sanitized'];
        $now = current_time('mysql');

        if ($action === 'create') {
            $result = $wpdb->insert(
                $table_name,
                array(
                    'source_path' => $redirect_data['source_path'],
                    'target_url' => $redirect_data['target_url'],
                    'status_code' => $redirect_data['status_code'],
                    'is_active' => $redirect_data['is_active'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ),
                array('%s', '%s', '%d', '%d', '%s', '%s')
            );

            if (false === $result) {
                $error_message = __('Could not create redirect.', 'pera-301-redirects');
                if (is_string($wpdb->last_error) && stripos($wpdb->last_error, 'Duplicate entry') !== false) {
                    $error_message = sprintf(
                        /* translators: %s: normalized source path */
                        __('A redirect for source path "%s" already exists. Please edit the existing rule instead.', 'pera-301-redirects'),
                        $redirect_data['source_path']
                    );
                }
                set_transient('pera_redirects_notice', array('type' => 'error', 'messages' => array($error_message)), 30);
            } else {
                set_transient('pera_redirects_notice', array('type' => 'success', 'messages' => array(__('Redirect created.', 'pera-301-redirects'))), 30);
            }
        }

        if ($action === 'update' && !empty($_POST['id'])) {
            $id = (int) $_POST['id'];
            $result = $wpdb->update(
                $table_name,
                array(
                    'source_path' => $redirect_data['source_path'],
                    'target_url' => $redirect_data['target_url'],
                    'status_code' => $redirect_data['status_code'],
                    'is_active' => $redirect_data['is_active'],
                    'updated_at' => $now,
                ),
                array('id' => $id),
                array('%s', '%s', '%d', '%d', '%s'),
                array('%d')
            );

            if (false === $result) {
                $error_message = __('Could not update redirect.', 'pera-301-redirects');
                if (is_string($wpdb->last_error) && stripos($wpdb->last_error, 'Duplicate entry') !== false) {
                    $error_message = sprintf(
                        /* translators: %s: normalized source path */
                        __('A redirect for source path "%s" already exists. Please choose a unique source path.', 'pera-301-redirects'),
                        $redirect_data['source_path']
                    );
                }
                set_transient('pera_redirects_notice', array('type' => 'error', 'messages' => array($error_message)), 30);
            } else {
                set_transient('pera_redirects_notice', array('type' => 'success', 'messages' => array(__('Redirect updated.', 'pera-301-redirects'))), 30);
            }
        }

        wp_safe_redirect(admin_url('tools.php?page=pera-301-redirects'));
        exit;
    }

    if (isset($_GET['pera_redirects_action'], $_GET['_wpnonce']) && $_GET['pera_redirects_action'] !== '') {
        $action = sanitize_text_field(wp_unslash($_GET['pera_redirects_action']));
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id < 1) {
            return;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'pera_redirects_row_action_' . $id)) {
            return;
        }

        if ($action === 'delete') {
            $wpdb->delete($table_name, array('id' => $id), array('%d'));
            set_transient('pera_redirects_notice', array('type' => 'success', 'messages' => array(__('Redirect deleted.', 'pera-301-redirects'))), 30);
        }

        if ($action === 'toggle') {
            $row = $wpdb->get_row($wpdb->prepare("SELECT is_active FROM {$table_name} WHERE id = %d", $id), ARRAY_A);
            if ($row) {
                $next = ((int) $row['is_active']) ? 0 : 1;
                $wpdb->update($table_name, array('is_active' => $next, 'updated_at' => current_time('mysql')), array('id' => $id), array('%d', '%s'), array('%d'));
                set_transient('pera_redirects_notice', array('type' => 'success', 'messages' => array(__('Redirect status updated.', 'pera-301-redirects'))), 30);
            }
        }

        wp_safe_redirect(admin_url('tools.php?page=pera-301-redirects'));
        exit;
    }
}
add_action('admin_init', 'pera_redirects_handle_admin_actions');

/**
 * Render admin page.
 *
 * @return void
 */
function pera_redirects_render_admin_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;
    $table_name = pera_redirects_table_name();

    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $edit_id = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

    $where_sql = '';
    $query_args = array();
    $per_page = 50;
    $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
    $offset = ($paged - 1) * $per_page;

    if ($search !== '') {
        $where_sql = "WHERE source_path LIKE %s OR target_url LIKE %s";
        $like = '%' . $wpdb->esc_like($search) . '%';
        $query_args[] = $like;
        $query_args[] = $like;
    }

    $count_sql = "SELECT COUNT(*) FROM {$table_name} {$where_sql}";
    $total_items = !empty($query_args)
        ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $query_args))
        : (int) $wpdb->get_var($count_sql);
    $total_pages = (int) ceil($total_items / $per_page);

    $rows_sql = "SELECT * FROM {$table_name} {$where_sql} ORDER BY updated_at DESC LIMIT %d OFFSET %d";
    if (!empty($query_args)) {
        $rows = $wpdb->get_results($wpdb->prepare($rows_sql, array_merge($query_args, array($per_page, $offset))), ARRAY_A);
    } else {
        $rows = $wpdb->get_results($wpdb->prepare($rows_sql, $per_page, $offset), ARRAY_A);
    }

    $edit_row = null;
    if ($edit_id > 0) {
        $edit_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $edit_id), ARRAY_A);
    }

    $notice = get_transient('pera_redirects_notice');
    if ($notice) {
        delete_transient('pera_redirects_notice');
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Pera 301 Redirects', 'pera-301-redirects'); ?></h1>

        <?php if ($notice && !empty($notice['messages'])) : ?>
            <div class="notice notice-<?php echo esc_attr($notice['type'] === 'error' ? 'error' : 'success'); ?> is-dismissible">
                <?php foreach ($notice['messages'] as $message) : ?>
                    <p><?php echo esc_html($message); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h2><?php echo $edit_row ? esc_html__('Edit Redirect', 'pera-301-redirects') : esc_html__('Add New Redirect', 'pera-301-redirects'); ?></h2>

        <form method="post" action="<?php echo esc_url(admin_url('tools.php?page=pera-301-redirects')); ?>">
            <?php wp_nonce_field('pera_redirects_save_redirect'); ?>
            <input type="hidden" name="pera_redirects_action" value="<?php echo $edit_row ? 'update' : 'create'; ?>" />
            <?php if ($edit_row) : ?>
                <input type="hidden" name="id" value="<?php echo (int) $edit_row['id']; ?>" />
            <?php endif; ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="source_path"><?php esc_html_e('Source path', 'pera-301-redirects'); ?></label></th>
                    <td>
                        <input name="source_path" id="source_path" type="text" class="regular-text" required value="<?php echo esc_attr($edit_row['source_path'] ?? ''); ?>" />
                        <p class="description"><?php esc_html_e('Example: /old-page/ or https://peraproperty.com/old-page/', 'pera-301-redirects'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="target_url"><?php esc_html_e('Target URL', 'pera-301-redirects'); ?></label></th>
                    <td>
                        <input name="target_url" id="target_url" type="text" class="regular-text" required value="<?php echo esc_attr($edit_row['target_url'] ?? ''); ?>" />
                        <p class="description"><?php esc_html_e('Example: /new-page/ or https://external-site.com/page/', 'pera-301-redirects'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="status_code"><?php esc_html_e('Status code', 'pera-301-redirects'); ?></label></th>
                    <td>
                        <input name="status_code" id="status_code" type="number" class="small-text" value="<?php echo esc_attr((string) ($edit_row['status_code'] ?? 301)); ?>" min="300" max="399" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Active', 'pera-301-redirects'); ?></th>
                    <td>
                        <label>
                            <input name="is_active" type="checkbox" value="1" <?php checked((int) ($edit_row['is_active'] ?? 1), 1); ?> />
                            <?php esc_html_e('Enable this redirect', 'pera-301-redirects'); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <?php submit_button($edit_row ? __('Update Redirect', 'pera-301-redirects') : __('Add Redirect', 'pera-301-redirects')); ?>
            <?php if ($edit_row) : ?>
                <a href="<?php echo esc_url(admin_url('tools.php?page=pera-301-redirects')); ?>" class="button button-secondary"><?php esc_html_e('Cancel', 'pera-301-redirects'); ?></a>
            <?php endif; ?>
        </form>

        <hr />
        <h2><?php esc_html_e('Redirect Rules', 'pera-301-redirects'); ?></h2>

        <form method="get">
            <input type="hidden" name="page" value="pera-301-redirects" />
            <p class="search-box">
                <label class="screen-reader-text" for="redirect-search-input"><?php esc_html_e('Search redirects', 'pera-301-redirects'); ?></label>
                <input type="search" id="redirect-search-input" name="s" value="<?php echo esc_attr($search); ?>" />
                <?php submit_button(__('Search', 'pera-301-redirects'), '', '', false); ?>
            </p>
        </form>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Source', 'pera-301-redirects'); ?></th>
                    <th><?php esc_html_e('Target', 'pera-301-redirects'); ?></th>
                    <th><?php esc_html_e('Code', 'pera-301-redirects'); ?></th>
                    <th><?php esc_html_e('Active', 'pera-301-redirects'); ?></th>
                    <th><?php esc_html_e('Hits', 'pera-301-redirects'); ?></th>
                    <th><?php esc_html_e('Last Hit', 'pera-301-redirects'); ?></th>
                    <th><?php esc_html_e('Actions', 'pera-301-redirects'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)) : ?>
                <tr>
                    <td colspan="7"><?php esc_html_e('No redirects found.', 'pera-301-redirects'); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($rows as $row) : ?>
                    <?php
                    $id = (int) $row['id'];
                    $nonce = wp_create_nonce('pera_redirects_row_action_' . $id);
                    $edit_url = add_query_arg(array('page' => 'pera-301-redirects', 'edit' => $id), admin_url('tools.php'));
                    $toggle_url = add_query_arg(array('page' => 'pera-301-redirects', 'pera_redirects_action' => 'toggle', 'id' => $id, '_wpnonce' => $nonce), admin_url('tools.php'));
                    $delete_url = add_query_arg(array('page' => 'pera-301-redirects', 'pera_redirects_action' => 'delete', 'id' => $id, '_wpnonce' => $nonce), admin_url('tools.php'));
                    $test_url = home_url($row['source_path']);
                    ?>
                    <tr>
                        <td><code><?php echo esc_html($row['source_path']); ?></code></td>
                        <td><code><?php echo esc_html($row['target_url']); ?></code></td>
                        <td><?php echo (int) $row['status_code']; ?></td>
                        <td><?php echo (int) $row['is_active'] === 1 ? esc_html__('Yes', 'pera-301-redirects') : esc_html__('No', 'pera-301-redirects'); ?></td>
                        <td><?php echo number_format_i18n((int) $row['hit_count']); ?></td>
                        <td><?php echo $row['last_hit_at'] ? esc_html(get_date_from_gmt(get_gmt_from_date($row['last_hit_at']), 'Y-m-d H:i:s')) : '—'; ?></td>
                        <td>
                            <a href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Edit', 'pera-301-redirects'); ?></a>
                            |
                            <a href="<?php echo esc_url($toggle_url); ?>"><?php echo (int) $row['is_active'] === 1 ? esc_html__('Disable', 'pera-301-redirects') : esc_html__('Enable', 'pera-301-redirects'); ?></a>
                            |
                            <?php if ((int) $row['is_active'] === 1) : ?>
                                <a href="<?php echo esc_url($test_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Test', 'pera-301-redirects'); ?></a>
                                |
                            <?php endif; ?>
                            <a href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('<?php echo esc_js(__('Delete this redirect?', 'pera-301-redirects')); ?>');"><?php esc_html_e('Delete', 'pera-301-redirects'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <?php
        if ($total_pages > 1) {
            $pagination_base = add_query_arg(
                array(
                    'page' => 'pera-301-redirects',
                    's' => $search,
                    'paged' => '%#%',
                ),
                admin_url('tools.php')
            );

            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo wp_kses_post(
                paginate_links(
                    array(
                        'base' => $pagination_base,
                        'format' => '',
                        'current' => $paged,
                        'total' => max(1, $total_pages),
                        'type' => 'plain',
                        'prev_text' => __('&laquo;', 'pera-301-redirects'),
                        'next_text' => __('&raquo;', 'pera-301-redirects'),
                    )
                )
            );
            echo '</div></div>';
        }
        ?>
    </div>
    <?php
}

/**
 * Run redirect on frontend requests.
 *
 * @return void
 */
function pera_redirects_execute_redirect()
{
    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_preview() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
    $request_path = wp_parse_url($request_uri, PHP_URL_PATH);
    if (!$request_path) {
        return;
    }

    // Ignore admin/login endpoints.
    if (0 === strpos($request_path, '/wp-admin') || 0 === strpos($request_path, '/wp-login.php')) {
        return;
    }

    $normalized_request = pera_redirects_normalize_source_path($request_path);
    if ($normalized_request === '') {
        return;
    }

    global $wpdb;
    $table_name = pera_redirects_table_name();

    $redirect = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE source_path = %s AND is_active = 1 LIMIT 1",
            $normalized_request
        ),
        ARRAY_A
    );

    if (!$redirect) {
        return;
    }

    $target_url = (string) $redirect['target_url'];
    if (!pera_redirects_is_valid_target_url($target_url)) {
        return;
    }

    $normalized_target_path = pera_redirects_normalize_target_path_if_internal($target_url);

    // Prevent loops on same-domain same-path redirects.
    if ($normalized_target_path !== '' && $normalized_target_path === $normalized_request) {
        return;
    }

    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$table_name} SET hit_count = hit_count + 1, last_hit_at = %s, updated_at = %s WHERE id = %d",
            current_time('mysql'),
            current_time('mysql'),
            (int) $redirect['id']
        )
    );

    // Use wp_redirect() because targets are already strictly validated on save and at runtime.
    wp_redirect($target_url, (int) $redirect['status_code'], 'Pera 301 Redirects');
    exit;
}
add_action('template_redirect', 'pera_redirects_execute_redirect', 1);
