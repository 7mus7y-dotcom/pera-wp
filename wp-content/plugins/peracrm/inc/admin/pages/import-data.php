<?php

if (!defined('ABSPATH')) {
    exit;
}

function peracrm_render_import_data_page()
{
    if (!peracrm_import_user_can_manage()) {
        wp_die('Unauthorized');
    }

    $upload = peracrm_import_get_upload_state();
    $validation = peracrm_import_get_validation_state();
    $step = !empty($validation) ? 3 : (!empty($upload) ? 2 : 1);
    $field_options = peracrm_import_destination_fields();

    echo '<div class="wrap peracrm-import-page">';
    echo '<h1>Import Data</h1>';
    echo '<p>Import basic Zoho-exported leads or clients via a safe three-step CSV workflow.</p>';

    echo '<ol class="peracrm-import-steps">';
    foreach ([1 => 'Upload CSV', 2 => 'Map + dry run', 3 => 'Commit import'] as $number => $label) {
        $class = $number === $step ? 'current' : ($number < $step ? 'done' : '');
        echo '<li class="' . esc_attr($class) . '"><strong>Step ' . esc_html((string) $number) . ':</strong> ' . esc_html($label) . '</li>';
    }
    echo '</ol>';

    if ($step === 1) {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data" class="peracrm-import-card">';
        wp_nonce_field('peracrm_import_upload');
        echo '<input type="hidden" name="action" value="peracrm_import_upload_csv" />';
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="peracrm-import-file">CSV file</label></th><td><input type="file" id="peracrm-import-file" name="peracrm_import_file" accept=".csv,text/csv" required /><p class="description">Maximum upload size: ' . esc_html(size_format(PERACRM_IMPORT_MAX_FILESIZE)) . '.</p></td></tr>';
        echo '</tbody></table>';
        submit_button('Upload and parse headers', 'primary');
        echo '</form>';
    }

    if ($step >= 2 && !empty($upload['headers'])) {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="peracrm-import-card">';
        wp_nonce_field('peracrm_import_validate');
        echo '<input type="hidden" name="action" value="peracrm_import_validate_csv" />';
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row">File</th><td>' . esc_html((string) ($upload['file_name'] ?? '')) . '</td></tr>';
        echo '<tr><th scope="row"><label for="peracrm-record-type">Record type</label></th><td><select name="record_type" id="peracrm-record-type">';
        foreach (peracrm_import_record_type_options() as $value => $label) {
            printf('<option value="%1$s"%2$s>%3$s</option>', esc_attr($value), selected($upload['record_type'] ?? 'lead', $value, false), esc_html($label));
        }
        echo '</select></td></tr>';
        echo '<tr><th scope="row"><label for="peracrm-import-mode">Import mode</label></th><td><select name="mode" id="peracrm-import-mode">';
        foreach (peracrm_import_mode_options() as $value => $label) {
            printf('<option value="%1$s"%2$s>%3$s</option>', esc_attr($value), selected($upload['mode'] ?? 'create_only', $value, false), esc_html($label));
        }
        echo '</select></td></tr>';
        echo '</tbody></table>';
        echo '<h2>Column mapping</h2>';
        echo '<table class="widefat striped peracrm-import-mapping"><thead><tr><th>Source header</th><th>Sample value</th><th>Destination field</th></tr></thead><tbody>';
        foreach ((array) $upload['headers'] as $header) {
            $sample = '';
            foreach ((array) ($upload['sample_rows'] ?? []) as $sample_row) {
                if (!empty($sample_row[$header])) {
                    $sample = $sample_row[$header];
                    break;
                }
            }
            echo '<tr>';
            echo '<td>' . esc_html((string) $header) . '</td>';
            echo '<td>' . esc_html((string) $sample) . '</td>';
            echo '<td><select name="mapping[' . esc_attr((string) $header) . ']">';
            foreach ($field_options as $value => $label) {
                printf('<option value="%1$s"%2$s>%3$s</option>', esc_attr($value), selected(($upload['mapping'][$header] ?? ''), $value, false), esc_html($label));
            }
            echo '</select></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        submit_button('Run dry run validation', 'primary');
        echo '</form>';
    }

    if ($step === 3 && !empty($validation['summary'])) {
        $summary = $validation['summary'];
        echo '<div class="peracrm-import-card">';
        echo '<h2>Dry run summary</h2>';
        echo '<ul class="peracrm-import-summary">';
        foreach ([
            'total_rows' => 'Total rows',
            'valid_rows' => 'Valid rows',
            'rows_missing_identity' => 'Rows missing identity',
            'rows_invalid_email' => 'Rows with invalid email',
            'rows_would_create' => 'Rows that would create new',
            'rows_would_update' => 'Rows that would update existing',
            'duplicate_rows' => 'Duplicate rows inside CSV',
        ] as $key => $label) {
            echo '<li><strong>' . esc_html($label) . ':</strong> ' . esc_html((string) ($summary[$key] ?? 0)) . '</li>';
        }
        echo '</ul>';
        echo '<h2>Preview (first 20 rows)</h2>';
        echo '<table class="widefat striped peracrm-import-preview"><thead><tr><th>Row</th><th>Action</th><th>Status</th><th>Mapped data</th></tr></thead><tbody>';
        foreach ((array) ($validation['preview_rows'] ?? []) as $result) {
            echo '<tr>';
            echo '<td>' . esc_html((string) $result['row_number']) . '</td>';
            echo '<td>' . esc_html(ucfirst((string) $result['action'])) . '</td>';
            echo '<td>' . esc_html(empty($result['errors']) ? 'Valid' : implode('; ', $result['errors'])) . '</td>';
            echo '<td><code>' . esc_html(wp_json_encode($result['mapped'])) . '</code></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<p class="description">The import commit will process rows in batches of ' . esc_html((string) PERACRM_IMPORT_BATCH_SIZE) . '.</p>';
        echo '<div class="peracrm-import-actions">';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-right:12px;">';
        wp_nonce_field('peracrm_import_commit');
        echo '<input type="hidden" name="action" value="peracrm_import_commit_csv" />';
        submit_button('Commit import', 'primary', 'submit', false);
        echo '</form>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;">';
        wp_nonce_field('peracrm_import_reset');
        echo '<input type="hidden" name="action" value="peracrm_import_reset" />';
        submit_button('Start over', 'secondary', 'submit', false);
        echo '</form>';
        echo '</div></div>';
    }

    if ($step >= 2) {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="peracrm-import-reset">';
        wp_nonce_field('peracrm_import_reset');
        echo '<input type="hidden" name="action" value="peracrm_import_reset" />';
        submit_button('Discard import session', 'secondary');
        echo '</form>';
    }

    echo '</div>';
}
