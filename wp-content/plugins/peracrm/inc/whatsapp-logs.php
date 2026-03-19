<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_whatsapp_allowed_page_sizes()
{
    return [20, 50, 100];
}

function peracrm_whatsapp_sanitize_page_size($page_size)
{
    $page_size = (int) $page_size;
    $allowed = peracrm_whatsapp_allowed_page_sizes();

    return in_array($page_size, $allowed, true) ? $page_size : 20;
}

function peracrm_whatsapp_sanitize_page_number($page_number)
{
    return max(1, absint($page_number));
}

function peracrm_whatsapp_get_logs_view_state(array $source = null)
{
    if ($source === null) {
        $source = $_REQUEST;
    }

    $page_size = isset($source['per_page']) ? peracrm_whatsapp_sanitize_page_size(wp_unslash($source['per_page'])) : 20;
    $paged = isset($source['paged']) ? peracrm_whatsapp_sanitize_page_number(wp_unslash($source['paged'])) : 1;

    return [
        'per_page' => $page_size,
        'paged' => $paged,
    ];
}

function peracrm_whatsapp_logs_user_can_access()
{
    if (function_exists('peracrm_can_view_operational_logs') && peracrm_can_view_operational_logs()) {
        return true;
    }

    return function_exists('peracrm_admin_user_can_manage') && peracrm_admin_user_can_manage();
}

function peracrm_whatsapp_logs_validate_blog_id($blog_id)
{
    $blog_id = (int) $blog_id;
    if ($blog_id <= 0 || !is_multisite()) {
        return 0;
    }

    return get_blog_details($blog_id) ? $blog_id : 0;
}

function peracrm_get_whatsapp_logs_blog_id()
{
    if (!is_multisite()) {
        return 0;
    }

    $configured_blog_id = defined('PERACRM_WHATSAPP_LOGS_BLOG_ID') ? peracrm_whatsapp_logs_validate_blog_id(PERACRM_WHATSAPP_LOGS_BLOG_ID) : 0;
    if ($configured_blog_id > 0) {
        return $configured_blog_id;
    }

    $target_blog_id = defined('PERACRM_TARGET_BLOG_ID') ? peracrm_whatsapp_logs_validate_blog_id(PERACRM_TARGET_BLOG_ID) : 0;
    if ($target_blog_id > 0) {
        return $target_blog_id;
    }

    if (function_exists('peracrm_membership_is_target_site_url')) {
        $sites = get_sites([
            'number' => 0,
            'fields' => 'ids',
        ]);

        foreach ($sites as $site_id) {
            $site_id = (int) $site_id;
            if ($site_id > 0 && peracrm_membership_is_target_site_url($site_id)) {
                return $site_id;
            }
        }
    }

    return 0;
}

function peracrm_whatsapp_logs_resolution_error($message = '')
{
    $message = is_string($message) && $message !== '' ? $message : __('WhatsApp click logs data source is unavailable.', 'peracrm');

    return new WP_Error('peracrm_whatsapp_logs_target_blog_unresolved', $message);
}

function peracrm_whatsapp_logs_with_target_blog(callable $callback)
{
    if (!is_multisite()) {
        return $callback();
    }

    $target_blog_id = peracrm_get_whatsapp_logs_blog_id();
    if ($target_blog_id <= 0) {
        return peracrm_whatsapp_logs_resolution_error();
    }

    if ((int) get_current_blog_id() === $target_blog_id) {
        return $callback();
    }

    switch_to_blog($target_blog_id);
    try {
        return $callback();
    } finally {
        restore_current_blog();
    }
}

function peracrm_whatsapp_logs_is_frontend_screen()
{
    return !is_admin()
        && function_exists('pera_is_crm_route')
        && pera_is_crm_route()
        && sanitize_key((string) get_query_var('pera_crm_view', '')) === 'whatsapp_logs';
}

function peracrm_whatsapp_click_logs_table_name()
{
    return function_exists('pera_whatsapp_clicks_table_name') ? (string) pera_whatsapp_clicks_table_name() : '';
}

function peracrm_whatsapp_click_logs_table_exists($table_name = '')
{
    global $wpdb;

    $table_name = $table_name !== '' ? $table_name : peracrm_whatsapp_click_logs_table_name();
    if ($table_name === '') {
        return false;
    }

    $table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

    return $table_name === $table;
}

function peracrm_whatsapp_click_log_empty_result(array $state, $message = '')
{
    return [
        'rows' => [],
        'pagination' => [
            'total' => 0,
            'total_pages' => 1,
            'per_page' => isset($state['per_page']) ? max(1, (int) $state['per_page']) : 20,
            'paged' => isset($state['paged']) ? max(1, (int) $state['paged']) : 1,
        ],
        'message' => $message !== '' ? $message : __('WhatsApp click logs data source is unavailable.', 'peracrm'),
        'table_name' => peracrm_whatsapp_click_logs_table_name(),
    ];
}

function peracrm_whatsapp_click_logs_count()
{
    global $wpdb;

    $table_name = peracrm_whatsapp_click_logs_table_name();
    if (!peracrm_whatsapp_click_logs_table_exists($table_name)) {
        return 0;
    }

    return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

function peracrm_whatsapp_click_logs_list(array $state)
{
    global $wpdb;

    $table_name = peracrm_whatsapp_click_logs_table_name();
    if (!peracrm_whatsapp_click_logs_table_exists($table_name)) {
        return peracrm_whatsapp_click_log_empty_result($state);
    }

    $per_page = peracrm_whatsapp_sanitize_page_size($state['per_page'] ?? 20);
    $requested_page = peracrm_whatsapp_sanitize_page_number($state['paged'] ?? 1);
    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $total_pages = max(1, (int) ceil($total / $per_page));
    $paged = min($requested_page, $total_pages);
    $offset = ($paged - 1) * $per_page;

    $sql = $wpdb->prepare(
        "SELECT id, created_at, page_type, page_url, post_id, post_title, message_text, referrer, user_agent, ip_address
        FROM {$table_name}
        ORDER BY id DESC
        LIMIT %d OFFSET %d",
        $per_page,
        $offset
    );

    $rows = (array) $wpdb->get_results($sql, ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

    return [
        'rows' => $rows,
        'pagination' => [
            'total' => $total,
            'total_pages' => $total_pages,
            'per_page' => $per_page,
            'paged' => $paged,
        ],
        'message' => '',
        'table_name' => $table_name,
    ];
}

function peracrm_whatsapp_delete_click_logs_by_ids(array $ids)
{
    global $wpdb;

    $table_name = peracrm_whatsapp_click_logs_table_name();
    if (!peracrm_whatsapp_click_logs_table_exists($table_name)) {
        return [
            'deleted' => 0,
            'table_name' => $table_name,
        ];
    }

    $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
    if (empty($ids)) {
        return [
            'deleted' => 0,
            'table_name' => $table_name,
        ];
    }

    $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
    $sql = $wpdb->prepare("DELETE FROM {$table_name} WHERE id IN ({$placeholders})", $ids);
    $deleted = $wpdb->query($sql); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

    return [
        'deleted' => max(0, (int) $deleted),
        'table_name' => $table_name,
    ];
}

function peracrm_whatsapp_logs_get_fetch_result(array $state, $context = 'admin')
{
    $callback = static function () use ($state) {
        return peracrm_whatsapp_click_logs_list($state);
    };

    $result = peracrm_whatsapp_logs_with_target_blog($callback);
    if (is_wp_error($result)) {
        return peracrm_whatsapp_click_log_empty_result($state, $result->get_error_message());
    }

    return is_array($result) ? $result : peracrm_whatsapp_click_log_empty_result($state);
}

function peracrm_whatsapp_click_log_page_type_label($page_type)
{
    if (function_exists('pera_whatsapp_logs_page_type_label')) {
        return pera_whatsapp_logs_page_type_label((string) $page_type);
    }

    $page_type = sanitize_key((string) $page_type);
    if ($page_type === '') {
        return __('Unknown', 'peracrm');
    }

    return ucwords(str_replace('-', ' ', $page_type));
}

function peracrm_whatsapp_click_log_url_label($url, $length = 56)
{
    if (function_exists('pera_whatsapp_logs_url_label')) {
        return pera_whatsapp_logs_url_label((string) $url, (int) $length);
    }

    $url = (string) $url;
    if ($url === '') {
        return '';
    }

    return wp_html_excerpt($url, (int) $length, '…');
}

function peracrm_whatsapp_click_log_message_label($message_text, $length = 24)
{
    $message_text = trim((string) $message_text);
    if ($message_text === '') {
        return '—';
    }

    return esc_html(wp_trim_words($message_text, $length, '…'));
}

function peracrm_whatsapp_render_logs_table(array $state, $context = 'admin')
{
    $result = peracrm_whatsapp_logs_get_fetch_result($state, $context);
    $rows = isset($result['rows']) && is_array($result['rows']) ? $result['rows'] : [];
    $pagination = isset($result['pagination']) && is_array($result['pagination']) ? $result['pagination'] : [];
    $current_page = isset($pagination['paged']) ? (int) $pagination['paged'] : 1;
    $page_size = isset($pagination['per_page']) ? (int) $pagination['per_page'] : $state['per_page'];
    $total = isset($pagination['total']) ? (int) $pagination['total'] : 0;
    $total_pages = max(1, isset($pagination['total_pages']) ? (int) $pagination['total_pages'] : 1);
    $context = $context === 'frontend' ? 'frontend' : 'admin';

    ob_start();

    if (!empty($result['message']) && empty($rows)) {
        echo '<p class="description">' . esc_html((string) $result['message']) . '</p>';
    }

    echo '<div class="peracrm-whatsapp-log-controls">';
    echo '<div class="peracrm-whatsapp-log-bulk">';
    echo '<button type="button" class="button button-secondary" data-peracrm-delete-selected disabled>' . esc_html__('Delete selected', 'peracrm') . '</button>';
    echo '</div>';
    echo '<div class="peracrm-whatsapp-log-page-size">';
    echo '<label for="peracrm-whatsapp-per-page">' . esc_html__('Rows per page', 'peracrm') . '</label>';
    echo '<select id="peracrm-whatsapp-per-page" data-peracrm-page-size>';
    foreach (peracrm_whatsapp_allowed_page_sizes() as $allowed_size) {
        printf(
            '<option value="%1$d"%2$s>%1$d</option>',
            (int) $allowed_size,
            selected($page_size, $allowed_size, false)
        );
    }
    echo '</select>';
    echo '</div>';
    echo '</div>';

    $table_class = $context === 'frontend'
        ? 'crm-log-table peracrm-whatsapp-logs-table'
        : 'widefat striped peracrm-whatsapp-logs-table';

    echo '<table class="' . esc_attr($table_class) . '">';
    echo '<thead><tr>';
    echo '<td class="check-column"><input type="checkbox" data-peracrm-select-all aria-label="' . esc_attr__('Select all visible WhatsApp click logs', 'peracrm') . '" /></td>';
    echo '<th scope="col">' . esc_html__('Created', 'peracrm') . '</th>';
    echo '<th scope="col">' . esc_html__('Page type', 'peracrm') . '</th>';
    echo '<th scope="col">' . esc_html__('Page', 'peracrm') . '</th>';
    echo '<th scope="col">' . esc_html__('Post', 'peracrm') . '</th>';
    echo '<th scope="col">' . esc_html__('Message', 'peracrm') . '</th>';
    echo '<th scope="col">' . esc_html__('Referrer', 'peracrm') . '</th>';
    echo '<th scope="col">' . esc_html__('IP', 'peracrm') . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    if (empty($rows)) {
        echo '<tr><td colspan="8">' . esc_html__('No WhatsApp click logs found.', 'peracrm') . '</td></tr>';
    } else {
        foreach ($rows as $row) {
            $log_id = isset($row['id']) ? (int) $row['id'] : 0;
            $post_id = isset($row['post_id']) ? (int) $row['post_id'] : 0;
            $post_title = trim((string) ($row['post_title'] ?? ''));
            if ($post_title === '' && $post_id > 0) {
                $post_title = get_the_title($post_id);
            }
            if ($post_title === '') {
                $post_title = '—';
            }

            $post_url = $post_id > 0 ? get_permalink($post_id) : '';
            if (!is_string($post_url)) {
                $post_url = '';
            }

            $page_url = (string) ($row['page_url'] ?? '');
            $referrer = (string) ($row['referrer'] ?? '');

            echo '<tr data-log-id="' . esc_attr((string) $log_id) . '">';
            echo '<th scope="row" class="check-column"><input type="checkbox" value="' . esc_attr((string) $log_id) . '" data-peracrm-log-checkbox /></th>';
            echo '<td>' . esc_html((string) ($row['created_at'] ?? '')) . '</td>';
            echo '<td>' . esc_html(peracrm_whatsapp_click_log_page_type_label((string) ($row['page_type'] ?? ''))) . '</td>';
            echo '<td>';
            if ($page_url !== '') {
                echo '<a href="' . esc_url($page_url) . '" target="_blank" rel="noopener">' . esc_html(peracrm_whatsapp_click_log_url_label($page_url)) . '</a>';
            } else {
                echo '—';
            }
            echo '</td>';
            echo '<td>';
            if ($post_id > 0 && $post_url !== '') {
                echo '<a href="' . esc_url($post_url) . '" target="_blank" rel="noopener">' . esc_html($post_title) . '</a>';
            } else {
                echo esc_html($post_title);
            }
            echo '</td>';
            echo '<td><div class="peracrm-whatsapp-log-message">' . peracrm_whatsapp_click_log_message_label((string) ($row['message_text'] ?? '')) . '</div></td>';
            echo '<td>';
            if ($referrer !== '') {
                echo '<a href="' . esc_url($referrer) . '" target="_blank" rel="noopener">' . esc_html(peracrm_whatsapp_click_log_url_label($referrer)) . '</a>';
            } else {
                echo '—';
            }
            echo '</td>';
            echo '<td><code>' . esc_html((string) ($row['ip_address'] ?? '')) . '</code></td>';
            echo '</tr>';
        }
    }

    echo '</tbody>';
    echo '</table>';

    echo '<div class="tablenav bottom">';
    echo '<div class="tablenav-pages" data-total-pages="' . esc_attr((string) $total_pages) . '">';
    echo '<span class="displaying-num">' . esc_html(sprintf(_n('%d item', '%d items', $total, 'peracrm'), $total)) . '</span>';
    if ($total_pages > 1) {
        echo '<span class="pagination-links">';
        if ($current_page > 1) {
            echo '<a class="button prev-page" href="#" data-peracrm-page="' . esc_attr((string) ($current_page - 1)) . '">&lsaquo;</a>';
        }
        echo '<span class="paging-input">' . esc_html(sprintf(__('Page %1$d of %2$d', 'peracrm'), $current_page, $total_pages)) . '</span>';
        if ($current_page < $total_pages) {
            echo '<a class="button next-page" href="#" data-peracrm-page="' . esc_attr((string) ($current_page + 1)) . '">&rsaquo;</a>';
        }
        echo '</span>';
    }
    echo '</div>';
    echo '</div>';

    return ob_get_clean();
}

function peracrm_whatsapp_render_logs_panel(array $state, $context = 'admin')
{
    $context = $context === 'frontend' ? 'frontend' : 'admin';
    $wrapper_class = $context === 'frontend' ? 'peracrm-whatsapp-logs crm-log-table-wrap' : 'peracrm-whatsapp-logs';

    ob_start();
    echo '<div data-peracrm-whatsapp-feedback></div>';
    echo '<div class="' . esc_attr($wrapper_class) . '" data-peracrm-whatsapp-logs data-peracrm-context="' . esc_attr($context) . '" data-per-page="' . esc_attr((string) $state['per_page']) . '" data-paged="' . esc_attr((string) $state['paged']) . '">';
    echo peracrm_whatsapp_render_logs_table($state, $context);
    echo '</div>';

    return ob_get_clean();
}

function peracrm_whatsapp_admin_inline_script()
{
    return <<<'JS'
(function () {
  var config = window.peracrmWhatsAppAdmin || {};
  var containers = Array.prototype.slice.call(document.querySelectorAll('[data-peracrm-whatsapp-logs]'));
  if (!containers.length || !config.ajaxUrl || !config.nonce) {
    return;
  }

  function allowedPageSize(size) {
    var allowed = Array.isArray(config.pageSizeOptions) ? config.pageSizeOptions.map(function (value) {
      return parseInt(value, 10);
    }) : [20, 50, 100];
    return allowed.indexOf(size) !== -1 ? size : 20;
  }

  function bindContainer(container) {
    var state = {
      per_page: parseInt(container.getAttribute('data-per-page'), 10) || 20,
      paged: parseInt(container.getAttribute('data-paged'), 10) || 1
    };

    function selectedIds() {
      return Array.prototype.slice.call(container.querySelectorAll('[data-peracrm-log-checkbox]:checked')).map(function (input) {
        return parseInt(input.value, 10);
      }).filter(Boolean);
    }

    function syncSelectionUi() {
      var rowCheckboxes = Array.prototype.slice.call(container.querySelectorAll('[data-peracrm-log-checkbox]'));
      var checked = rowCheckboxes.filter(function (input) { return input.checked; });
      var master = container.querySelector('[data-peracrm-select-all]');
      if (master) {
        master.checked = rowCheckboxes.length > 0 && checked.length === rowCheckboxes.length;
        master.indeterminate = checked.length > 0 && checked.length < rowCheckboxes.length;
      }
      var button = container.querySelector('[data-peracrm-delete-selected]');
      if (button) {
        button.disabled = checked.length === 0;
      }
    }

    function renderNotice(message, type) {
      var notice = container.parentNode.querySelector('[data-peracrm-whatsapp-feedback]');
      if (!notice) {
        return;
      }
      if (!message) {
        notice.innerHTML = '';
        return;
      }
      notice.innerHTML = '<div class="notice notice-' + type + ' inline"><p>' + message + '</p></div>';
    }

    function updateUrl() {
      if (!window.history || !window.history.replaceState) {
        return;
      }
      var url = new URL(window.location.href);
      url.searchParams.set('per_page', String(state.per_page));
      url.searchParams.set('paged', String(state.paged));
      window.history.replaceState({}, '', url.toString());
    }

    function loadTable(extraData, noticeMessage, noticeType) {
      var formData = new window.FormData();
      formData.append('action', 'peracrm_whatsapp_logs_table');
      formData.append('nonce', config.nonce);
      formData.append('per_page', String(state.per_page));
      formData.append('paged', String(state.paged));
      if (extraData) {
        Object.keys(extraData).forEach(function (key) {
          formData.append(key, extraData[key]);
        });
      }

      container.classList.add('is-loading');
      window.fetch(config.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
      }).then(function (response) {
        return response.json();
      }).then(function (payload) {
        if (!payload || !payload.success || !payload.data || typeof payload.data.html !== 'string') {
          throw new Error(config.strings && config.strings.loadError ? config.strings.loadError : 'Unable to load WhatsApp logs.');
        }
        container.innerHTML = payload.data.html;
        if (payload.data.pagination) {
          state.per_page = allowedPageSize(parseInt(payload.data.pagination.per_page, 10) || state.per_page);
          state.paged = parseInt(payload.data.pagination.paged, 10) || 1;
        }
        container.setAttribute('data-per-page', String(state.per_page));
        container.setAttribute('data-paged', String(state.paged));
        updateUrl();
        syncSelectionUi();
        renderNotice(noticeMessage || '', noticeType || 'success');
      }).catch(function (error) {
        renderNotice(error.message || (config.strings && config.strings.loadError) || 'Unable to load WhatsApp logs.', 'error');
      }).finally(function () {
        container.classList.remove('is-loading');
      });
    }

    container.addEventListener('change', function (event) {
      var target = event.target;
      if (target.matches('[data-peracrm-page-size]')) {
        state.per_page = allowedPageSize(parseInt(target.value, 10));
        state.paged = 1;
        loadTable();
        return;
      }

      if (target.matches('[data-peracrm-select-all]')) {
        Array.prototype.forEach.call(container.querySelectorAll('[data-peracrm-log-checkbox]'), function (input) {
          input.checked = target.checked;
        });
        syncSelectionUi();
        return;
      }

      if (target.matches('[data-peracrm-log-checkbox]')) {
        syncSelectionUi();
      }
    });

    container.addEventListener('click', function (event) {
      var paginationLink = event.target.closest('[data-peracrm-page]');
      if (paginationLink) {
        event.preventDefault();
        state.paged = parseInt(paginationLink.getAttribute('data-peracrm-page'), 10) || 1;
        loadTable();
        return;
      }

      var deleteButton = event.target.closest('[data-peracrm-delete-selected]');
      if (!deleteButton) {
        return;
      }

      var ids = selectedIds();
      if (!ids.length) {
        syncSelectionUi();
        return;
      }

      if (!window.confirm((config.strings && config.strings.deleteConfirm) || 'Delete selected logs?')) {
        return;
      }

      var formData = new window.FormData();
      formData.append('action', 'peracrm_whatsapp_delete_logs');
      formData.append('nonce', config.nonce);
      formData.append('per_page', String(state.per_page));
      formData.append('paged', String(state.paged));
      ids.forEach(function (id) {
        formData.append('ids[]', String(id));
      });

      deleteButton.disabled = true;
      window.fetch(config.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData
      }).then(function (response) {
        return response.json();
      }).then(function (payload) {
        if (!payload || !payload.success) {
          var message = payload && payload.data && payload.data.message ? payload.data.message : ((config.strings && config.strings.deleteError) || 'Unable to delete selected logs.');
          throw new Error(message);
        }
        if (payload.data && payload.data.pagination) {
          state.paged = parseInt(payload.data.pagination.paged, 10) || 1;
        }
        loadTable({}, payload.data.message || ((config.strings && config.strings.deleteSuccess) || 'Selected logs deleted.'), 'success');
      }).catch(function (error) {
        renderNotice(error.message || ((config.strings && config.strings.deleteError) || 'Unable to delete selected logs.'), 'error');
        syncSelectionUi();
      });
    });

    syncSelectionUi();
  }

  containers.forEach(bindContainer);
})();
JS;
}

function peracrm_whatsapp_enqueue_logs_assets($context = 'admin', $version = null)
{
    $context = $context === 'frontend' ? 'frontend' : 'admin';
    $version = $version ?: (defined('PERACRM_VERSION') ? PERACRM_VERSION : null);
    $handle = $context === 'frontend' ? 'peracrm-whatsapp-logs-frontend' : 'peracrm-whatsapp-logs-admin';

    if ($context === 'frontend') {
        $admin_css_path = PERACRM_PATH . '/assets/admin.css';
        if (file_exists($admin_css_path)) {
            wp_enqueue_style(
                'peracrm-whatsapp-logs-shared',
                PERACRM_URL . '/assets/admin.css',
                ['pera-crm-css'],
                $version ?: (string) filemtime($admin_css_path)
            );
        }
    }

    wp_register_script($handle, false, [], $version, true);
    wp_enqueue_script($handle);
    wp_localize_script($handle, 'peracrmWhatsAppAdmin', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('peracrm_whatsapp_logs'),
        'pageSizeOptions' => peracrm_whatsapp_allowed_page_sizes(),
        'strings' => [
            'deleteConfirm' => __('Delete selected click logs?', 'peracrm'),
            'deleteSuccess' => __('Selected click logs deleted.', 'peracrm'),
            'deleteError' => __('Unable to delete selected click logs.', 'peracrm'),
            'loadError' => __('Unable to load WhatsApp click logs.', 'peracrm'),
        ],
    ]);
    wp_add_inline_script($handle, peracrm_whatsapp_admin_inline_script());
}

function peracrm_ajax_whatsapp_logs_table()
{
    if (!peracrm_whatsapp_logs_user_can_access()) {
        wp_send_json_error(['message' => __('Unauthorized.', 'peracrm')], 403);
    }

    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['nonce'])) : '';
    if ($nonce === '' || !wp_verify_nonce($nonce, 'peracrm_whatsapp_logs')) {
        wp_send_json_error(['message' => __('Invalid nonce.', 'peracrm')], 403);
    }

    $state = peracrm_whatsapp_get_logs_view_state($_POST);
    $logs = peracrm_whatsapp_logs_get_fetch_result($state);

    wp_send_json_success([
        'html' => peracrm_whatsapp_render_logs_table($state, peracrm_whatsapp_logs_is_frontend_screen() ? 'frontend' : 'admin'),
        'pagination' => isset($logs['pagination']) ? $logs['pagination'] : ['per_page' => $state['per_page'], 'paged' => $state['paged']],
    ]);
}

function peracrm_ajax_whatsapp_delete_logs()
{
    if (!peracrm_whatsapp_logs_user_can_access()) {
        wp_send_json_error(['message' => __('Unauthorized.', 'peracrm')], 403);
    }

    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['nonce'])) : '';
    if ($nonce === '' || !wp_verify_nonce($nonce, 'peracrm_whatsapp_logs')) {
        wp_send_json_error(['message' => __('Invalid nonce.', 'peracrm')], 403);
    }

    $raw_ids = isset($_POST['ids']) ? (array) wp_unslash($_POST['ids']) : [];
    $ids = array_values(array_unique(array_filter(array_map('absint', $raw_ids))));
    if (empty($ids)) {
        wp_send_json_error(['message' => __('Please select at least one log entry.', 'peracrm')], 400);
    }

    $state = peracrm_whatsapp_get_logs_view_state($_POST);
    $delete_callback = static function () use ($ids) {
        return peracrm_whatsapp_delete_click_logs_by_ids($ids);
    };
    $delete_result = peracrm_whatsapp_logs_with_target_blog($delete_callback);

    if (is_wp_error($delete_result)) {
        wp_send_json_error(['message' => $delete_result->get_error_message()], 500);
    }

    if (empty($delete_result['deleted'])) {
        wp_send_json_error(['message' => __('No log entries were deleted.', 'peracrm')], 500);
    }

    $logs = peracrm_whatsapp_logs_get_fetch_result($state);

    wp_send_json_success([
        'message' => sprintf(_n('%d click log deleted.', '%d click logs deleted.', (int) $delete_result['deleted'], 'peracrm'), (int) $delete_result['deleted']),
        'deleted' => (int) $delete_result['deleted'],
        'pagination' => isset($logs['pagination']) ? $logs['pagination'] : ['per_page' => $state['per_page'], 'paged' => 1],
    ]);
}
