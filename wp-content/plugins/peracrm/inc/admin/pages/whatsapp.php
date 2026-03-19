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

function peracrm_whatsapp_render_logs_table(array $state)
{
    $result = peracrm_with_target_blog(static function () use ($state) {
        return peracrm_whatsapp_get_messages([
            'per_page' => $state['per_page'],
            'paged' => $state['paged'],
        ]);
    });

    if (!is_array($result)) {
        $result = peracrm_whatsapp_get_messages([
            'per_page' => $state['per_page'],
            'paged' => $state['paged'],
        ]);
    }

    $rows = isset($result['rows']) && is_array($result['rows']) ? $result['rows'] : [];
    $pagination = isset($result['pagination']) && is_array($result['pagination']) ? $result['pagination'] : [];
    $current_page = isset($pagination['paged']) ? (int) $pagination['paged'] : 1;
    $page_size = isset($pagination['per_page']) ? (int) $pagination['per_page'] : $state['per_page'];
    $total = isset($pagination['total']) ? (int) $pagination['total'] : 0;
    $total_pages = isset($pagination['total_pages']) ? (int) $pagination['total_pages'] : 1;

    ob_start();
    echo '<div class="peracrm-whatsapp-log-controls">';
    echo '<div class="peracrm-whatsapp-log-bulk">';
    echo '<button type="button" class="button button-secondary" data-peracrm-delete-selected disabled>Delete selected</button>';
    echo '</div>';
    echo '<div class="peracrm-whatsapp-log-page-size">';
    echo '<label for="peracrm-whatsapp-per-page">Rows per page</label>';
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

    echo '<table class="widefat striped peracrm-whatsapp-logs-table">';
    echo '<thead><tr>';
    echo '<td class="check-column"><input type="checkbox" data-peracrm-select-all aria-label="Select all visible WhatsApp logs" /></td>';
    echo '<th scope="col">Created</th>';
    echo '<th scope="col">Direction</th>';
    echo '<th scope="col">Client</th>';
    echo '<th scope="col">Contact</th>';
    echo '<th scope="col">Phone</th>';
    echo '<th scope="col">Message</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    if (empty($rows)) {
        echo '<tr><td colspan="7">No WhatsApp logs found.</td></tr>';
    } else {
        foreach ($rows as $row) {
            $client_id = isset($row['client_id']) ? (int) $row['client_id'] : 0;
            $client_label = '—';
            if ($client_id > 0) {
                $client_label = get_the_title($client_id);
                if ($client_label === '') {
                    $client_label = 'Client #' . $client_id;
                }
                $edit_link = get_edit_post_link($client_id);
                if ($edit_link) {
                    $client_label = '<a href="' . esc_url($edit_link) . '">' . esc_html($client_label) . '</a>';
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
                echo '<a href="' . esc_url((string) $row['media_url']) . '" target="_blank" rel="noopener">Media attachment</a>';
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
    echo '<span class="displaying-num">' . esc_html(sprintf('%d items', $total)) . '</span>';
    if ($total_pages > 1) {
        echo '<span class="pagination-links">';
        if ($current_page > 1) {
            echo '<a class="button prev-page" href="#" data-peracrm-page="' . esc_attr((string) ($current_page - 1)) . '">&lsaquo;</a>';
        }
        echo '<span class="paging-input">Page ' . esc_html((string) $current_page) . ' of <span class="total-pages">' . esc_html((string) $total_pages) . '</span></span>';
        if ($current_page < $total_pages) {
            echo '<a class="button next-page" href="#" data-peracrm-page="' . esc_attr((string) ($current_page + 1)) . '">&rsaquo;</a>';
        }
        echo '</span>';
    }
    echo '</div>';
    echo '</div>';

    return ob_get_clean();
}

function peracrm_whatsapp_admin_inline_script()
{
    return <<<'JS'
(function () {
  var config = window.peracrmWhatsAppAdmin || {};
  var container = document.querySelector('[data-peracrm-whatsapp-logs]');
  if (!container || !config.ajaxUrl || !config.nonce) {
    return;
  }

  var state = {
    per_page: parseInt(container.getAttribute('data-per-page'), 10) || 20,
    paged: parseInt(container.getAttribute('data-paged'), 10) || 1
  };

  function allowedPageSize(size) {
    var allowed = Array.isArray(config.pageSizeOptions) ? config.pageSizeOptions.map(function (value) {
      return parseInt(value, 10);
    }) : [20, 50, 100];
    return allowed.indexOf(size) !== -1 ? size : 20;
  }

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
})();
JS;
}

function peracrm_ajax_whatsapp_logs_table()
{
    if (!peracrm_admin_user_can_manage()) {
        wp_send_json_error(['message' => __('Unauthorized.', 'peracrm')], 403);
    }

    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['nonce'])) : '';
    if ($nonce === '' || !wp_verify_nonce($nonce, 'peracrm_whatsapp_logs')) {
        wp_send_json_error(['message' => __('Invalid nonce.', 'peracrm')], 403);
    }

    $state = peracrm_whatsapp_get_logs_view_state($_POST);
    $logs = peracrm_with_target_blog(static function () use ($state) {
        return peracrm_whatsapp_get_messages($state);
    });

    wp_send_json_success([
        'html' => peracrm_whatsapp_render_logs_table($state),
        'pagination' => isset($logs['pagination']) ? $logs['pagination'] : ['per_page' => $state['per_page'], 'paged' => $state['paged']],
    ]);
}

function peracrm_ajax_whatsapp_delete_logs()
{
    if (!peracrm_admin_user_can_manage()) {
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
    $delete_result = peracrm_with_target_blog(static function () use ($ids) {
        return peracrm_whatsapp_delete_messages_by_ids($ids);
    });

    if (empty($delete_result['deleted'])) {
        wp_send_json_error(['message' => __('No log entries were deleted.', 'peracrm')], 500);
    }

    $logs = peracrm_with_target_blog(static function () use ($state) {
        return peracrm_whatsapp_get_messages($state);
    });

    wp_send_json_success([
        'message' => sprintf(_n('%d log deleted.', '%d logs deleted.', (int) $delete_result['deleted'], 'peracrm'), (int) $delete_result['deleted']),
        'deleted' => (int) $delete_result['deleted'],
        'pagination' => isset($logs['pagination']) ? $logs['pagination'] : ['per_page' => $state['per_page'], 'paged' => 1],
    ]);
}

function peracrm_render_whatsapp_page()
{
    if (!peracrm_admin_user_can_manage()) {
        wp_die('Unauthorized');
    }

    $settings = peracrm_whatsapp_get_settings();
    $diag = peracrm_whatsapp_get_diagnostic();
    $state = peracrm_whatsapp_get_logs_view_state($_GET);
    $count = (int) peracrm_with_target_blog(static function () {
        return function_exists('peracrm_whatsapp_count_messages') ? peracrm_whatsapp_count_messages() : 0;
    });

    $endpoint = esc_url_raw(rest_url('peracrm/v1/whatsapp/webhook'));

    echo '<div class="wrap">';
    echo '<h1>WhatsApp Inbound</h1>';

    if (isset($_GET['updated'])) {
        echo '<div class="notice notice-success"><p>WhatsApp settings saved.</p></div>';
    }

    echo '<h2>Webhook diagnostics</h2>';
    echo '<table class="widefat striped" style="max-width:900px">';
    echo '<tbody>';
    echo '<tr><th scope="row">Webhook endpoint</th><td><code>' . esc_html($endpoint) . '</code></td></tr>';
    echo '<tr><th scope="row">Enabled</th><td>' . (!empty($settings['enabled']) ? 'Yes' : 'No') . '</td></tr>';
    echo '<tr><th scope="row">Phone number ID</th><td>' . ($settings['phone_number_id'] !== '' ? esc_html($settings['phone_number_id']) : 'Missing') . '</td></tr>';
    echo '<tr><th scope="row">Access token</th><td>' . ($settings['access_token'] !== '' ? esc_html(peracrm_whatsapp_mask_secret($settings['access_token'])) : 'Missing') . '</td></tr>';
    echo '<tr><th scope="row">Verify token</th><td>' . ($settings['verify_token'] !== '' ? esc_html(peracrm_whatsapp_mask_secret($settings['verify_token'])) : 'Missing') . '</td></tr>';
    echo '<tr><th scope="row">Last inbound webhook received</th><td>' . ($diag['last_received_at'] ? esc_html($diag['last_received_at']) : '—') . '</td></tr>';
    echo '<tr><th scope="row">Last webhook status</th><td>' . ($diag['last_status'] ? esc_html($diag['last_status']) : '—') . '</td></tr>';
    echo '<tr><th scope="row">Latest error summary</th><td>' . ($diag['last_error'] ? esc_html($diag['last_error']) : '—') . '</td></tr>';
    echo '<tr><th scope="row">Stored WhatsApp messages</th><td>' . esc_html((string) $count) . '</td></tr>';
    echo '</tbody>';
    echo '</table>';

    echo '<h2>Logs</h2>';
    echo '<div data-peracrm-whatsapp-feedback></div>';
    echo '<div class="peracrm-whatsapp-logs" data-peracrm-whatsapp-logs data-per-page="' . esc_attr((string) $state['per_page']) . '" data-paged="' . esc_attr((string) $state['paged']) . '">';
    echo peracrm_whatsapp_render_logs_table($state);
    echo '</div>';

    echo '<h2>Settings</h2>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:900px">';
    wp_nonce_field('peracrm_whatsapp_settings');
    echo '<input type="hidden" name="action" value="peracrm_save_whatsapp_settings" />';
    echo '<table class="form-table" role="presentation"><tbody>';
    echo '<tr><th scope="row">Enable inbound webhook</th><td><label><input type="checkbox" name="peracrm_whatsapp[enabled]" value="1" ' . checked(!empty($settings['enabled']), true, false) . ' /> Enabled</label></td></tr>';
    echo '<tr><th scope="row">Phone Number ID</th><td><input type="text" name="peracrm_whatsapp[phone_number_id]" class="regular-text" value="' . esc_attr((string) $settings['phone_number_id']) . '" /></td></tr>';
    echo '<tr><th scope="row">Access Token</th><td><input type="password" name="peracrm_whatsapp[access_token]" class="regular-text" value="" autocomplete="new-password" /><p class="description">Leave blank to keep existing token.</p></td></tr>';
    echo '<tr><th scope="row">Verify Token</th><td><input type="text" name="peracrm_whatsapp[verify_token]" class="regular-text" value="" placeholder="' . esc_attr(peracrm_whatsapp_mask_secret((string) $settings['verify_token'])) . '" /><p class="description">Set token used by Meta webhook verification.</p></td></tr>';
    echo '<tr><th scope="row">Test mode</th><td><label><input type="checkbox" name="peracrm_whatsapp[test_mode]" value="1" ' . checked(!empty($settings['test_mode']), true, false) . ' /> Enable non-production handling</label></td></tr>';
    echo '</tbody></table>';
    submit_button('Save WhatsApp settings');
    echo '</form>';

    echo '</div>';
}
