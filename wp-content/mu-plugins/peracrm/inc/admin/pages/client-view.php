<?php

if (!defined('ABSPATH')) {
    exit;
}


if (!defined('PERACRM_CLIENT_VIEW_SUBMISSIONS_LIMIT')) {
    define('PERACRM_CLIENT_VIEW_SUBMISSIONS_LIMIT', 50);
}

function peracrm_client_view_form_label($form, $context)
{
    $form = sanitize_key((string) $form);
    $context = sanitize_key((string) $context);

    $form_labels = [
        'sr_enquiry' => 'Seller/Landlord enquiry',
        'favourites_enquiry' => 'Favourites enquiry',
        'citizenship_enquiry' => 'Citizenship enquiry',
    ];

    $context_labels = [
        'property_enquiry' => 'Property',
        'sell' => 'Sell',
        'rent' => 'Rent',
        'favourites' => 'Favourites',
        'citizenship' => 'Citizenship',
    ];

    $form_label = isset($form_labels[$form]) ? $form_labels[$form] : ($form !== '' ? ucfirst(str_replace('_', ' ', $form)) : 'Enquiry');
    $context_label = isset($context_labels[$context]) ? $context_labels[$context] : ($context !== '' ? ucfirst(str_replace('_', ' ', $context)) : '');

    if ($context_label === '') {
        return $form_label;
    }

    return $form_label . ' · ' . $context_label;
}

function peracrm_client_view_is_internal_raw_field($key)
{
    $key = sanitize_key((string) $key);
    if ($key === '') {
        return true;
    }

    $exact = [
        'sr_nonce',
        'fav_nonce',
        'pera_citizenship_nonce',
        'sr_company',
        'fav_company',
        'sr_action',
        'fav_enquiry_action',
        'pera_citizenship_action',
    ];

    if (in_array($key, $exact, true)) {
        return true;
    }

    return (substr($key, -6) === '_nonce') || (substr($key, -7) === '_action') || (substr($key, -8) === '_company');
}

function peracrm_client_view_format_raw_field_value($value)
{
    if (is_array($value)) {
        $parts = [];
        foreach ($value as $item) {
            $parts[] = peracrm_client_view_format_raw_field_value($item);
        }

        $parts = array_values(array_filter(array_map('trim', $parts), static function ($item) {
            return $item !== '';
        }));

        return implode(', ', $parts);
    }

    if (!is_scalar($value) || $value === null) {
        return '';
    }

    return trim((string) $value);
}

function peracrm_client_view_enquiry_field_label($key)
{
    $key = sanitize_key((string) $key);

    $labels = [
        'email' => 'Email',
        'phone' => 'Phone',
        'message' => 'Message',
        'contact_method' => 'Contact method',
        'family' => 'Family',
        'policy' => 'Policy',
        'consent' => 'Consent',
        'property_ids' => 'Properties',
        'property_id' => 'Property',
        'source_page' => 'Source page',
        'form_context' => 'Context',
    ];

    if (isset($labels[$key])) {
        return $labels[$key];
    }

    return ucwords(str_replace('_', ' ', $key));
}

function peracrm_client_view_normalize_enquiry_key($key)
{
    $key = sanitize_key((string) $key);
    if ($key === 'sr_email' || $key === 'fav_email') {
        return 'email';
    }

    if ($key === 'sr_phone' || $key === 'fav_phone') {
        return 'phone';
    }

    return $key;
}

function peracrm_client_view_format_enquiry_field_value($key, $value)
{
    $key = sanitize_key((string) $key);

    if (is_array($value)) {
        $parts = [];
        foreach ($value as $item) {
            $formatted = peracrm_client_view_format_enquiry_field_value($key, $item);
            if ($formatted === '') {
                continue;
            }
            $parts[] = $formatted;
        }

        return implode(', ', $parts);
    }

    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }

    if (!is_scalar($value) || $value === null) {
        return '';
    }

    $raw = trim((string) $value);

    $consent_keys = [
        'consent',
        'policy',
        'privacy',
        'gdpr',
        'terms',
        'optin',
        'opt_in',
    ];
    $is_consent_key = false;
    foreach ($consent_keys as $consent_key) {
        if ($key === $consent_key || strpos($key, $consent_key) !== false) {
            $is_consent_key = true;
            break;
        }
    }

    if ($is_consent_key) {
        $normalized = strtolower($raw);
        if ($normalized === '' || $normalized === '0' || $normalized === 'no' || $normalized === 'false' || $normalized === 'off') {
            return 'No';
        }
        if ($normalized === '1' || $normalized === 'yes' || $normalized === 'true' || $normalized === 'on') {
            return 'Yes';
        }
    }

    return $raw;
}

function peracrm_client_view_collect_enquiry_fields(array $payload)
{
    $fields = [];
    $excluded_payload_keys = [
        'submitted_at',
        'raw_fields',
        'page_url',
        'form',
        'form_context',
        'actor_user_id',
        'advisor_user_id',
        'property_ids',
        'property_id',
        'sr_property_title',
        'sr_property_url',
    ];

    foreach ($payload as $key => $value) {
        $normalized_key = peracrm_client_view_normalize_enquiry_key($key);
        if ($normalized_key === '' || in_array($normalized_key, $excluded_payload_keys, true)) {
            continue;
        }
        if (peracrm_client_view_is_internal_raw_field($normalized_key)) {
            continue;
        }

        $formatted = peracrm_client_view_format_enquiry_field_value($normalized_key, $value);
        if ($formatted === '') {
            continue;
        }

        if (!isset($fields[$normalized_key])) {
            $fields[$normalized_key] = $formatted;
        }
    }

    if (!empty($payload['raw_fields']) && is_array($payload['raw_fields'])) {
        foreach ($payload['raw_fields'] as $key => $value) {
            $normalized_key = peracrm_client_view_normalize_enquiry_key($key);
            if ($normalized_key === '' || peracrm_client_view_is_internal_raw_field($normalized_key)) {
                continue;
            }

            if ($normalized_key === 'sr_property_url') {
                continue;
            }

            $formatted = peracrm_client_view_format_enquiry_field_value($normalized_key, $value);
            if ($formatted === '') {
                continue;
            }

            if (!isset($fields[$normalized_key])) {
                $fields[$normalized_key] = $formatted;
            }
        }
    }

    return $fields;
}

function peracrm_render_client_view_page()
{
    if (!peracrm_admin_user_can_manage()) {
        wp_die('Unauthorized');
    }

    $client_id = isset($_GET['client_id']) ? absint($_GET['client_id']) : 0;
    $client = $client_id ? get_post($client_id) : null;

    if (!$client_id || !$client || $client->post_type !== 'crm_client') {
        wp_die('Client not found.');
    }

    if (!current_user_can('edit_post', $client_id)) {
        wp_die('You do not have permission to view this client.');
    }

    $can_manage_all = current_user_can('manage_options') || current_user_can('peracrm_manage_all_clients');
    if (!$can_manage_all) {
        $assigned_id = function_exists('peracrm_client_get_assigned_advisor_id')
            ? (int) peracrm_client_get_assigned_advisor_id($client_id)
            : 0;
        if ($assigned_id !== get_current_user_id()) {
            wp_die('You are not assigned to this client.');
        }
    }

    $profile = function_exists('peracrm_client_get_profile')
        ? peracrm_client_get_profile($client_id)
        : [
            'status' => '',
            'client_type' => '',
            'preferred_contact' => '',
            'budget_min_usd' => '',
            'budget_max_usd' => '',
            'phone' => '',
            'email' => '',
        ];

    $health = function_exists('peracrm_client_health_get') ? peracrm_client_health_get($client_id) : [];
    $badge = function_exists('peracrm_client_health_badge_html')
        ? peracrm_client_health_badge_html($health)
        : esc_html(isset($health['label']) ? $health['label'] : 'None');

    $now_ts = current_time('timestamp');
    $last_activity_ts = isset($health['last_activity_ts']) ? (int) $health['last_activity_ts'] : 0;
    $last_activity = $last_activity_ts ? human_time_diff($last_activity_ts, $now_ts) . ' ago' : '—';

    $phone = isset($profile['phone']) ? trim((string) $profile['phone']) : '';
    $email_raw = isset($profile['email']) ? (string) $profile['email'] : '';
    $email = $email_raw !== '' ? sanitize_email($email_raw) : '';
    if ($email !== '' && !is_email($email)) {
        $email = '';
    }

    $tel_link = $phone !== '' ? 'tel:' . rawurlencode($phone) : '';
    $wa_link = '';
    $phone_trimmed = ltrim($phone);
    if ($phone_trimmed !== '' && strpos($phone_trimmed, '+') === 0) {
        $wa_digits = preg_replace('/\D+/', '', $phone_trimmed);
        if ($wa_digits !== '') {
            $wa_link = 'https://wa.me/' . $wa_digits;
        }
    }
    $mailto_link = $email !== '' ? 'mailto:' . rawurlencode($email) : '';

    $status_labels = [
        'enquiry' => 'Enquiry',
        'active' => 'Active',
        'dormant' => 'Dormant',
        'closed' => 'Closed',
    ];

    $type_labels = [
        'citizenship' => 'Citizenship',
        'investor' => 'Investor',
        'lifestyle' => 'Lifestyle',
    ];

    $contact_labels = [
        '' => 'No preference',
        'phone' => 'Phone',
        'whatsapp' => 'WhatsApp',
        'email' => 'Email',
    ];

    $status = isset($profile['status']) ? (string) $profile['status'] : '';
    $client_type = isset($profile['client_type']) ? (string) $profile['client_type'] : '';
    $preferred_contact = isset($profile['preferred_contact']) ? (string) $profile['preferred_contact'] : '';

    $status_label = isset($status_labels[$status]) ? $status_labels[$status] : '—';
    $type_label = isset($type_labels[$client_type]) ? $type_labels[$client_type] : '—';
    $contact_label = array_key_exists($preferred_contact, $contact_labels)
        ? $contact_labels[$preferred_contact]
        : '—';

    $budget_min_raw = $profile['budget_min_usd'] ?? '';
    $budget_max_raw = $profile['budget_max_usd'] ?? '';
    $budget_min = is_numeric($budget_min_raw) ? (int) $budget_min_raw : null;
    $budget_max = is_numeric($budget_max_raw) ? (int) $budget_max_raw : null;

    $budget_label = '—';
    if (null !== $budget_min && null !== $budget_max) {
        $budget_label = sprintf(
            '$%s - $%s',
            number_format_i18n($budget_min),
            number_format_i18n($budget_max)
        );
    } elseif (null !== $budget_min) {
        $budget_label = sprintf('$%s+', number_format_i18n($budget_min));
    } elseif (null !== $budget_max) {
        $budget_label = sprintf('Up to $%s', number_format_i18n($budget_max));
    }

    $reminders_url = add_query_arg(
        [
            'post_type' => 'crm_client',
            'page' => 'peracrm-my-reminders',
            'client_id' => $client_id,
        ],
        admin_url('edit.php')
    );

    $has_reminders_table = function_exists('peracrm_reminders_table_exists') && peracrm_reminders_table_exists();
    $open_count = $has_reminders_table && function_exists('peracrm_reminders_count_open_by_client')
        ? peracrm_reminders_count_open_by_client($client_id)
        : null;
    $overdue_count = $has_reminders_table && function_exists('peracrm_reminders_count_overdue_by_client')
        ? peracrm_reminders_count_overdue_by_client($client_id)
        : null;

    $notes = function_exists('peracrm_notes_list') ? peracrm_notes_list($client_id, 10, 0) : [];

    $form_submissions = function_exists('peracrm_activity_list')
        ? peracrm_activity_list($client_id, (int) PERACRM_CLIENT_VIEW_SUBMISSIONS_LIMIT, 0, 'enquiry')
        : [];

    $timeline_filter = function_exists('peracrm_timeline_get_filter') ? peracrm_timeline_get_filter() : 'all';
    $timeline_items = function_exists('peracrm_timeline_get_items')
        ? peracrm_timeline_get_items($client_id, 50, $timeline_filter)
        : [];
    $timeline_missing = function_exists('peracrm_timeline_missing_sources')
        ? peracrm_timeline_missing_sources($timeline_filter)
        : [];

    $edit_link = get_edit_post_link($client_id, '');

    echo '<div class="wrap peracrm-client-view">';
    echo '<h1 class="wp-heading-inline">' . esc_html($client->post_title) . '</h1>';
    if ($edit_link) {
        echo ' <a class="page-title-action" href="' . esc_url($edit_link) . '">Edit</a>';
    }

    echo '<div class="peracrm-client-view__meta">';
    echo '<p><strong>Health:</strong> ' . $badge . ' <span class="description">Last activity ' . esc_html($last_activity) . '</span></p>';
    echo '</div>';

    echo '<hr />';

    echo '<h2>Quick Actions</h2>';
    if ($tel_link || $wa_link || $mailto_link) {
        echo '<p>';
        if ($tel_link) {
            echo '<a class="button" href="' . esc_url($tel_link) . '">Call</a> ';
        }
        if ($wa_link) {
            echo '<a class="button" href="' . esc_url($wa_link) . '" target="_blank" rel="noopener">WhatsApp</a> ';
        }
        if ($mailto_link) {
            echo '<a class="button" href="' . esc_url($mailto_link) . '">Email</a>';
        }
        echo '</p>';
    } else {
        echo '<p class="peracrm-empty">No quick actions available.</p>';
    }

    echo '<h2>Client Profile</h2>';
    echo '<table class="widefat striped">';
    echo '<tbody>';
    echo '<tr><th>Status</th><td>' . esc_html($status_label) . '</td></tr>';
    echo '<tr><th>Client type</th><td>' . esc_html($type_label) . '</td></tr>';
    echo '<tr><th>Budget range</th><td>' . esc_html($budget_label) . '</td></tr>';
    echo '<tr><th>Preferred contact</th><td>' . esc_html($contact_label) . '</td></tr>';
    echo '</tbody>';
    echo '</table>';
    if ($edit_link) {
        echo '<p><a href="' . esc_url($edit_link) . '">Edit profile</a></p>';
    }

    echo '<h2>Reminders</h2>';
    if ($has_reminders_table) {
        echo '<p>Open: <strong>' . esc_html((string) $open_count) . '</strong> · Overdue: <strong>' . esc_html((string) $overdue_count) . '</strong></p>';
    } else {
        echo '<p class="peracrm-empty">Reminders data unavailable.</p>';
    }
    echo '<p><a href="' . esc_url($reminders_url) . '">View reminders</a></p>';

    echo '<h2>Notes</h2>';
    if (empty($notes)) {
        echo '<p class="peracrm-empty">No notes yet.</p>';
    } else {
        echo '<ul class="peracrm-list">';
        foreach ($notes as $note) {
            $author = get_userdata((int) $note['advisor_user_id']);
            $author_name = $author ? $author->display_name : 'Advisor';
            printf(
                '<li><div class="peracrm-list__meta">%1$s · %2$s</div><div class="peracrm-list__body">%3$s</div></li>',
                esc_html(mysql2date('Y-m-d H:i', $note['created_at'])),
                esc_html($author_name),
                esc_html($note['note_body'])
            );
        }
        echo '</ul>';
    }

    if (peracrm_admin_user_can_manage()) {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="peracrm-form">';
        wp_nonce_field('peracrm_add_note');
        echo '<input type="hidden" name="action" value="peracrm_add_note" />';
        echo '<input type="hidden" name="peracrm_client_id" value="' . esc_attr($client_id) . '" />';
        echo '<p><label for="peracrm_note_body">Add note</label></p>';
        echo '<p><textarea name="peracrm_note_body" id="peracrm_note_body" rows="4" class="widefat"></textarea></p>';
        echo '<p><button type="submit" class="button button-primary">Add Note</button></p>';
        echo '</form>';
    }


    echo '<h2>Form Submissions</h2>';
    if (empty($form_submissions)) {
        echo '<p class="peracrm-empty">No form submissions yet.</p>';
    } else {
        echo '<ul class="peracrm-list peracrm-form-submissions">';
        foreach ($form_submissions as $submission) {
            $payload = [];
            if (!empty($submission['event_payload'])) {
                $payload = peracrm_json_decode($submission['event_payload']);
            }
            if (!is_array($payload)) {
                $payload = [];
            }

            $submitted_at = isset($payload['submitted_at']) ? (string) $payload['submitted_at'] : '';
            if ($submitted_at === '') {
                $submitted_at = isset($submission['created_at']) ? (string) $submission['created_at'] : '';
            }

            $submitted_label = $submitted_at !== '' ? mysql2date('Y-m-d H:i', $submitted_at) : 'Unknown time';
            $form_label = peracrm_client_view_form_label($payload['form'] ?? '', $payload['form_context'] ?? '');

            $page_url = isset($payload['page_url']) ? esc_url_raw((string) $payload['page_url']) : '';
            $property_summary = '';
            $property_links = [];

            $property_ids = [];
            if (!empty($payload['property_ids']) && is_array($payload['property_ids'])) {
                $property_ids = array_values(array_filter(array_map('absint', $payload['property_ids'])));
            }

            if (!empty($property_ids)) {
                $property_summary = sprintf('Favourites enquiry (%d properties)', count($property_ids));
                foreach ($property_ids as $property_id) {
                    $property_links[] = sprintf(
                        '<a href="%1$s">Property #%2$d</a>',
                        esc_url(add_query_arg(['post' => $property_id, 'action' => 'edit'], admin_url('post.php'))),
                        $property_id
                    );
                }
            } else {
                $property_id = isset($payload['property_id']) ? absint($payload['property_id']) : 0;
                if ($property_id > 0) {
                    $property_summary = 'Property #' . $property_id;
                    $property_links[] = sprintf(
                        '<a href="%1$s">Property #%2$d</a>',
                        esc_url(add_query_arg(['post' => $property_id, 'action' => 'edit'], admin_url('post.php'))),
                        $property_id
                    );
                }
            }

            $fields = peracrm_client_view_collect_enquiry_fields($payload);

            $submitted_email = '';
            if (!empty($fields['email'])) {
                $submitted_email = (string) $fields['email'];
            } elseif (!empty($profile['email'])) {
                $submitted_email = (string) $profile['email'];
            }

            $submitted_phone = '';
            if (!empty($fields['phone'])) {
                $submitted_phone = (string) $fields['phone'];
            } elseif (!empty($profile['phone'])) {
                $submitted_phone = (string) $profile['phone'];
            }

            unset($fields['email'], $fields['phone']);

            $property_title = isset($payload['sr_property_title']) ? trim((string) $payload['sr_property_title']) : '';
            $property_url = isset($payload['sr_property_url']) ? esc_url_raw((string) $payload['sr_property_url']) : '';
            $has_linked_property = $property_title !== '' && $property_url !== '';
            if ($has_linked_property) {
                unset($fields['sr_property_url'], $fields['sr_property_title']);
            }

            if (!empty($fields['message'])) {
                unset($fields['message']);
            }

            echo '<li class="peracrm-timeline-item">';
            echo '<details open>';
            echo '<summary>';
            echo '<strong>Submitted enquiry</strong>';
            echo ' · Submitted at ' . esc_html($submitted_label);
            if ($page_url !== '') {
                echo ' · <a href="' . esc_url($page_url) . '" target="_blank" rel="noopener">Source page</a>';
            }
            echo ' · ' . esc_html($form_label);
            echo '</summary>';

            echo '<div class="peracrm-timeline-detail peracrm-enquiry-detail">';

            if ($submitted_email !== '' || $submitted_phone !== '') {
                echo '<div class="peracrm-enquiry-contact">';
                echo '<h4>Contact details</h4>';
                echo '<table class="widefat striped peracrm-enquiry-table">';
                echo '<tbody>';
                if ($submitted_email !== '') {
                    echo '<tr><th>Email</th><td>' . esc_html($submitted_email) . '</td></tr>';
                }
                if ($submitted_phone !== '') {
                    echo '<tr><th>Phone</th><td>' . esc_html($submitted_phone) . '</td></tr>';
                }
                echo '</tbody>';
                echo '</table>';
                echo '</div>';
            }

            $detail_rows = [];

            if (!empty($property_ids)) {
                $detail_rows[] = '<tr><th>Properties count</th><td>' . esc_html((string) count($property_ids)) . '</td></tr>';
            }

            if (!empty($property_links)) {
                $detail_rows[] = '<tr><th>Properties</th><td>' . implode(', ', $property_links) . '</td></tr>';
            }

            if ($has_linked_property) {
                $detail_rows[] = '<tr><th>Property</th><td><a href="' . esc_url($property_url) . '" target="_blank" rel="noopener">' . esc_html($property_title) . '</a></td></tr>';
            }

            foreach ($fields as $field_key => $field_value) {
                $label = peracrm_client_view_enquiry_field_label($field_key);

                if (filter_var($field_value, FILTER_VALIDATE_URL) && $field_key !== 'sr_property_url') {
                    $detail_rows[] = '<tr><th>' . esc_html($label) . '</th><td><a href="' . esc_url($field_value) . '" target="_blank" rel="noopener">' . esc_html($field_value) . '</a></td></tr>';
                } else {
                    $detail_rows[] = '<tr><th>' . esc_html($label) . '</th><td>' . esc_html($field_value) . '</td></tr>';
                }
            }

            if (!empty($detail_rows)) {
                echo '<table class="widefat striped peracrm-enquiry-table">';
                echo '<tbody>' . implode('', $detail_rows) . '</tbody>';
                echo '</table>';
            }

            if ($submitted_email === '' && $submitted_phone === '' && empty($property_links) && !$has_linked_property && empty($fields) && $property_summary === '') {
                echo '<p class="peracrm-empty">No additional payload details captured.</p>';
            }

            echo '</div>';
            echo '</details>';
            echo '</li>';
        }
        echo '</ul>';
    }

    echo '<h2>Timeline</h2>';
    $timeline_base_url = add_query_arg(
        [
            'page' => 'peracrm-client-view',
            'client_id' => $client_id,
        ],
        admin_url('admin.php')
    );
    $timeline_filters = [
        'all' => 'All',
        'activity' => 'Activity',
        'notes' => 'Notes',
        'reminders' => 'Reminders',
    ];

    echo '<div class="peracrm-timeline-filters">';
    $filter_links = [];
    foreach ($timeline_filters as $key => $label) {
        $url = add_query_arg('peracrm_timeline', $key, $timeline_base_url);
        if ($key === $timeline_filter) {
            $filter_links[] = '<strong>' . esc_html($label) . '</strong>';
        } else {
            $filter_links[] = '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
    }
    echo implode(' | ', $filter_links);
    echo '</div>';

    if (!empty($timeline_missing)) {
        echo '<p class="description">' . esc_html(implode(' ', $timeline_missing)) . '</p>';
    }

    if (empty($timeline_items)) {
        echo '<p class="peracrm-empty">No timeline items yet.</p>';
    } else {
        echo '<ul class="peracrm-list peracrm-timeline-list">';
        foreach ($timeline_items as $item) {
            $type_label = function_exists('peracrm_timeline_type_label')
                ? peracrm_timeline_type_label($item['type'] ?? '')
                : '';
            $title = isset($item['title']) ? (string) $item['title'] : '';
            $detail = isset($item['detail']) ? (string) $item['detail'] : '';
            $ts = isset($item['ts']) ? (int) $item['ts'] : 0;
            $time = function_exists('peracrm_timeline_time_display')
                ? peracrm_timeline_time_display($ts)
                : ['relative' => '', 'title' => ''];
            $meta_line = function_exists('peracrm_timeline_meta_line')
                ? peracrm_timeline_meta_line($item['meta'] ?? [])
                : '';

            echo '<li class="peracrm-timeline-item">';
            echo '<div class="peracrm-timeline-header">';
            echo '<span class="peracrm-timeline-badge">' . esc_html($type_label) . '</span>';
            if (!empty($time['relative'])) {
                echo ' <span class="peracrm-timeline-time" title="' . esc_attr($time['title']) . '">' . esc_html($time['relative']) . '</span>';
            }
            echo '</div>';
            if ($title !== '') {
                echo '<div class="peracrm-timeline-title"><strong>' . esc_html($title) . '</strong></div>';
            }
            if ($detail !== '') {
                echo '<div class="peracrm-timeline-detail">' . esc_html($detail) . '</div>';
            }
            if ($meta_line !== '') {
                echo '<div class="peracrm-timeline-meta">' . esc_html($meta_line) . '</div>';
            }
            echo '</li>';
        }
        echo '</ul>';
    }

    echo '</div>';
}
