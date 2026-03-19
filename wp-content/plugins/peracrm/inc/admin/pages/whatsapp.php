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

function peracrm_admin_is_real_whatsapp_logs_screen($hook = '')
{
    if (is_admin()) {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
        if ($page === 'pera-whatsapp-logs') {
            return true;
        }

        if ($hook !== '') {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            if ($screen && isset($screen->id) && $screen->id === 'toplevel_page_pera-whatsapp-logs') {
                return true;
            }
        }
    }

    return false;
}

function peracrm_whatsapp_logs_is_frontend_screen()
{
    return !is_admin()
        && function_exists('pera_is_crm_route')
        && pera_is_crm_route()
        && sanitize_key((string) get_query_var('pera_crm_view', '')) === 'whatsapp_logs';
}

function peracrm_whatsapp_logs_get_fetch_result(array $state)
{
    $callback = static function () use ($state) {
        return peracrm_whatsapp_get_messages([
            'per_page' => $state['per_page'],
            'paged' => $state['paged'],
        ]);
    };

    if (function_exists('peracrm_with_target_blog')) {
        $result = peracrm_with_target_blog($callback);
        if (is_array($result)) {
            return $result;
        }
    }

    return $callback();
}

function peracrm_whatsapp_render_logs_table(array $state, $context = 'admin')
{
    $result = peracrm_whatsapp_logs_get_fetch_result($state);
    $rows = isset($result['rows']) && is_array($result['rows']) ? $result['rows'] : [];
    $pagination = isset($result['pagination']) && is_array($result['pagination']) ? $result['pagination'] : [];
    $current_page = isset($pagination['paged']) ? (int) $pagination['paged'] : 1;
    $page_size = isset($pagination['per_page']) ? (int) $pagination['per_page'] : $state['per_page'];
    $total = isset($pagination['total']) ? (int) $pagination['total'] : 0;
    $total_pages = max(1, isset($pagination['total_pages']) ? (int) $pagination['total_pages'] : 1);
    $context = $context === 'frontend' ? 'frontend' : 'admin';

    ob_start();
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
    echo '<td class="check-column"><input type="checkbox" data-peracrm-select-all aria-label="' . esc_attr__('Select all visible WhatsApp logs', 'peracrm') . '" /></td>';
    echo '<th scope="col">' . esc_html__('Created', 'peracrm') . '</th>';
    echo '<th scope="col">' . esc_html__('Direction', 'peracrm') . '</th>';
    echo '<th scope="col">' . esc_html__('Client', 'peracrm') . '</th>';
    echo '<th scope="col">' . esc_html__('Contact', 'peracrm') . '</th>';
    echo '<th scope="col">' . esc_html__('Phone', 'peracrm') . '</th>';
    echo '<th scope="col">' . esc_html__('Message', 'peracrm') . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    if (empty($rows)) {
        echo '<tr><td colspan="7">' . esc_html__('No WhatsApp logs found.', 'peracrm') . '</td></tr>';
    } else {
        foreach ($rows as $row) {
            $client_id = isset($row['client_id']) ? (int) $row['client_id'] : 0;
            $client_label = '—';
            if ($client_id > 0) {
                $client_label = get_the_title($client_id);
                if ($client_label === '') {
                    $client_label = 'Client #' . $client_id;
                }

                $client_url = $context === 'frontend'
                    ? (function_exists('pera_crm_get_client_view_url') ? pera_crm_get_client_view_url($client_id) : home_url('/crm/client/' . $client_id . '/'))
                    : get_edit_post_link($client_id);

                if ($client_url) {
                    $client_label = '<a href="' . esc_url($client_url) . '">' . esc_html($client_label) . '</a>';
                } else {
                    $client_label = esc_html($client_label);
                }
            }

            $contact_parts = [];
            if (!empty($row['whatsapp_contact_name'])) {
                $contact_parts[] = esc_html((string) $row['whatsapp_contact_name']);
            }
            if (!empty($row['message_type'])) {
                $contact_parts[] = '<span class="description">' . esc_html(ucfirst((string) $row['message_type'])) . '</span>';
            }

            echo '<tr data-log-id="' . esc_attr((string) $row['id']) . '">';
            echo '<th scope="row" class="check-column"><input type="checkbox" value="' . esc_attr((string) $row['id']) . '" data-peracrm-log-checkbox /></th>';
            echo '<td>' . esc_html((string) ($row['created_at'] ?? '')) . '</td>';
            echo '<td>' . esc_html(ucfirst((string) ($row['direction'] ?? ''))) . '</td>';
            echo '<td>' . $client_label . '</td>';
            echo '<td>' . (!empty($contact_parts) ? implode('<br />', $contact_parts) : '—') . '</td>';
            echo '<td><code>' . esc_html((string) ($row['phone_e164'] ?? '')) . '</code></td>';
            echo '<td>';
            if (!empty($row['message_body'])) {
                echo '<div class="peracrm-whatsapp-log-message">' . esc_html(wp_trim_words((string) $row['message_body'], 30, '…')) . '</div>';
            } elseif (!empty($row['media_url'])) {
                echo '<a href="' . esc_url((string) $row['media_url']) . '" target="_blank" rel="noopener">' . esc_html__('Media attachment', 'peracrm') . '</a>';
            } else {
                echo '—';
            }
            echo '</td>';
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
            'deleteConfirm' => __('Delete selected logs?', 'peracrm'),
            'deleteSuccess' => __('Selected logs deleted.', 'peracrm'),
            'deleteError' => __('Unable to delete selected logs.', 'peracrm'),
            'loadError' => __('Unable to load WhatsApp logs.', 'peracrm'),
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
        return peracrm_whatsapp_delete_messages_by_ids($ids);
    };
    $delete_result = function_exists('peracrm_with_target_blog') ? peracrm_with_target_blog($delete_callback) : $delete_callback();

    if (empty($delete_result['deleted'])) {
        wp_send_json_error(['message' => __('No log entries were deleted.', 'peracrm')], 500);
    }

    $logs = peracrm_whatsapp_logs_get_fetch_result($state);

    wp_send_json_success([
        'message' => sprintf(_n('%d log deleted.', '%d logs deleted.', (int) $delete_result['deleted'], 'peracrm'), (int) $delete_result['deleted']),
        'deleted' => (int) $delete_result['deleted'],
        'pagination' => isset($logs['pagination']) ? $logs['pagination'] : ['per_page' => $state['per_page'], 'paged' => 1],
    ]);
}

function peracrm_render_whatsapp_page()
{
    if (!peracrm_whatsapp_logs_user_can_access()) {
        wp_die('Unauthorized');
    }

    $settings = peracrm_whatsapp_get_settings();
    $diag = peracrm_whatsapp_get_diagnostic();
    $state = peracrm_whatsapp_get_logs_view_state($_GET);
    $count_callback = static function () {
        return function_exists('peracrm_whatsapp_count_messages') ? (int) peracrm_whatsapp_count_messages() : 0;
    };
    $count = function_exists('peracrm_with_target_blog') ? (int) peracrm_with_target_blog($count_callback) : (int) $count_callback();
    $endpoint = esc_url_raw(rest_url('peracrm/v1/whatsapp/webhook'));

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('WhatsApp Inbound', 'peracrm') . '</h1>';

    if (isset($_GET['updated'])) {
        echo '<div class="notice notice-success"><p>' . esc_html__('WhatsApp settings saved.', 'peracrm') . '</p></div>';
    }

    echo '<h2>' . esc_html__('Webhook diagnostics', 'peracrm') . '</h2>';
    echo '<table class="widefat striped" style="max-width:900px"><tbody>';
    echo '<tr><th scope="row">' . esc_html__('Webhook endpoint', 'peracrm') . '</th><td><code>' . esc_html($endpoint) . '</code></td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Enabled', 'peracrm') . '</th><td>' . (!empty($settings['enabled']) ? esc_html__('Yes', 'peracrm') : esc_html__('No', 'peracrm')) . '</td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Phone number ID', 'peracrm') . '</th><td>' . ($settings['phone_number_id'] !== '' ? esc_html($settings['phone_number_id']) : esc_html__('Missing', 'peracrm')) . '</td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Access token', 'peracrm') . '</th><td>' . ($settings['access_token'] !== '' ? esc_html(peracrm_whatsapp_mask_secret($settings['access_token'])) : esc_html__('Missing', 'peracrm')) . '</td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Verify token', 'peracrm') . '</th><td>' . ($settings['verify_token'] !== '' ? esc_html(peracrm_whatsapp_mask_secret($settings['verify_token'])) : esc_html__('Missing', 'peracrm')) . '</td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Last inbound webhook received', 'peracrm') . '</th><td>' . ($diag['last_received_at'] ? esc_html($diag['last_received_at']) : '—') . '</td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Last webhook status', 'peracrm') . '</th><td>' . ($diag['last_status'] ? esc_html($diag['last_status']) : '—') . '</td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Latest error summary', 'peracrm') . '</th><td>' . ($diag['last_error'] ? esc_html($diag['last_error']) : '—') . '</td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Stored WhatsApp messages', 'peracrm') . '</th><td>' . esc_html((string) $count) . '</td></tr>';
    echo '</tbody></table>';

    echo '<h2>' . esc_html__('Logs', 'peracrm') . '</h2>';
    echo peracrm_whatsapp_render_logs_panel($state, 'admin');

    echo '<h2>' . esc_html__('Settings', 'peracrm') . '</h2>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:900px">';
    wp_nonce_field('peracrm_whatsapp_settings');
    echo '<input type="hidden" name="action" value="peracrm_save_whatsapp_settings" />';
    echo '<table class="form-table" role="presentation"><tbody>';
    echo '<tr><th scope="row">' . esc_html__('Enable inbound webhook', 'peracrm') . '</th><td><label><input type="checkbox" name="peracrm_whatsapp[enabled]" value="1" ' . checked(!empty($settings['enabled']), true, false) . ' /> ' . esc_html__('Enabled', 'peracrm') . '</label></td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Phone Number ID', 'peracrm') . '</th><td><input type="text" name="peracrm_whatsapp[phone_number_id]" class="regular-text" value="' . esc_attr((string) $settings['phone_number_id']) . '" /></td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Access Token', 'peracrm') . '</th><td><input type="password" name="peracrm_whatsapp[access_token]" class="regular-text" value="" autocomplete="new-password" /><p class="description">' . esc_html__('Leave blank to keep existing token.', 'peracrm') . '</p></td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Verify Token', 'peracrm') . '</th><td><input type="text" name="peracrm_whatsapp[verify_token]" class="regular-text" value="" placeholder="' . esc_attr(peracrm_whatsapp_mask_secret((string) $settings['verify_token'])) . '" /><p class="description">' . esc_html__('Set token used by Meta webhook verification.', 'peracrm') . '</p></td></tr>';
    echo '<tr><th scope="row">' . esc_html__('Test mode', 'peracrm') . '</th><td><label><input type="checkbox" name="peracrm_whatsapp[test_mode]" value="1" ' . checked(!empty($settings['test_mode']), true, false) . ' /> ' . esc_html__('Enable non-production handling', 'peracrm') . '</label></td></tr>';
    echo '</tbody></table>';
    submit_button(__('Save WhatsApp settings', 'peracrm'));
    echo '</form>';
    echo '</div>';
}
