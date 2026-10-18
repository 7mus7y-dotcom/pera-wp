<?php
if (!defined('ABSPATH')) { exit; }

/** Staff reads are authorized independently of menu visibility and route checks. */
function peracrm_subscribers_require_staff($write = false)
{
    if (!peracrm_campaigns_staff_can('peracrm_manage_subscribers', $write)) {
        throw new RuntimeException('Subscriber access is not permitted.');
    }
}

function peracrm_subscribers_url($suffix = '')
{
    return peracrm_with_target_blog(static function () use ($suffix) {
        return home_url('/crm/subscribers/' . $suffix);
    });
}

function peracrm_subscribers_list($filters = [], $page = 1)
{
    peracrm_subscribers_require_staff();
    return peracrm_with_target_blog(static function () use ($filters, $page) {
        global $wpdb;
        $s = peracrm_table('peracrm_subscribers');
        $x = peracrm_table('peracrm_email_suppressions');
        $where = ['1=1'];
        if (!empty($filters['search'])) {
            $where[] = $wpdb->prepare('s.normalized_email LIKE %s', '%' . $wpdb->esc_like(substr($filters['search'], 0, 254)) . '%');
        }
        if (!empty($filters['state'])) {
            if (!in_array($filters['state'], ['unknown', 'pending', 'subscribed', 'unsubscribed'], true)) { return ['rows' => [], 'total' => 0]; }
            $where[] = $wpdb->prepare('s.subscription_state = %s', $filters['state']);
        }
        if (!empty($filters['language'])) { $where[] = $wpdb->prepare('s.preferred_language = %s', strtolower(substr($filters['language'], 0, 35))); }
        if (!empty($filters['topic'])) {
            // JSON is canonical, quoted element matching avoids partial topic names.
            $where[] = $wpdb->prepare('s.preferences_json LIKE %s', '%' . $wpdb->esc_like(wp_json_encode($filters['topic'])) . '%');
        }
        $suppressed = '(COALESCE(x.unsubscribe,0)+COALESCE(x.hard_bounce,0)+COALESCE(x.complaint,0)+COALESCE(x.administrative,0)>0)';
        if (($filters['suppression'] ?? '') === 'yes') { $where[] = $suppressed; }
        if (($filters['suppression'] ?? '') === 'no') { $where[] = 'NOT ' . $suppressed; }
        $sql = " FROM {$s} s LEFT JOIN {$x} x ON x.normalized_email=s.normalized_email WHERE " . implode(' AND ', $where);
        $total = (int) $wpdb->get_var('SELECT COUNT(*)' . $sql);
        $rows = $wpdb->get_results($wpdb->prepare('SELECT s.*, x.unsubscribe, x.hard_bounce, x.complaint, x.administrative' . $sql . ' ORDER BY s.id DESC LIMIT 25 OFFSET %d', (max(1, min(100000, (int) $page)) - 1) * 25), ARRAY_A);
        peracrm_campaigns_db_require(true);
        return ['rows' => $rows, 'total' => $total];
    });
}

function peracrm_subscribers_detail($id)
{
    peracrm_subscribers_require_staff();
    return peracrm_with_target_blog(static function () use ($id) {
        global $wpdb;
        $table = peracrm_table('peracrm_subscribers');
        $email = $wpdb->get_var($wpdb->prepare("SELECT normalized_email FROM {$table} WHERE id=%d", $id));
        peracrm_campaigns_db_require(true);
        if (!$email) { return null; }
        $row = peracrm_campaigns_repository_identity($email);
        $row['can_unlink_client'] = !empty($row['client_id'])
            && peracrm_campaigns_staff_can('peracrm_manage_subscribers', true)
            && peracrm_campaigns_can_unlink_client(peracrm_get_actor_user_id(), (int) $row['client_id']);
        // A client identifier is only useful when the viewer can access that client.
        if (!empty($row['client_id']) && !peracrm_user_can_access_client(peracrm_get_actor_user_id(), (int) $row['client_id'])) { $row['client_id'] = null; }
        return $row;
    });
}

/** Real intake is off by default; only a real administrator can record these decisions. */
function peracrm_subscribers_readiness($decisions = null)
{
    peracrm_subscribers_require_staff($decisions !== null);
    return peracrm_with_target_blog(static function () use ($decisions) {
        if ($decisions !== null) {
            if (!user_can(peracrm_get_actor_user_id(), 'manage_options')) { throw new RuntimeException('Administrator required.'); }
            $saved = [];
            foreach (['consent_mapping', 'suppression_sources', 'retention'] as $key) {
                $value = trim((string) ($decisions[$key] ?? ''));
                if ($value === '' || strlen($value) > 191 || sanitize_text_field($value) !== $value) { throw new InvalidArgumentException('Record each decision reference before enabling intake.'); }
                $saved[$key] = $value;
            }
            $saved['enabled'] = !empty($decisions['enabled']);
            $saved['actor'] = peracrm_get_actor_user_id();
            $saved['recorded_at'] = gmdate('Y-m-d H:i:s');
            update_option('peracrm_subscriber_intake_readiness', $saved, false);
        }
        // Fresh SQL read avoids a stale cached enablement at apply time.
        return peracrm_campaigns_schema_option('peracrm_subscriber_intake_readiness', []);
    });
}

function peracrm_subscribers_intake_allowed($synthetic)
{
    if ($synthetic) { return true; }
    $gate = peracrm_subscribers_readiness();
    return !empty($gate['enabled']) && !empty($gate['consent_mapping']) && !empty($gate['suppression_sources']) && !empty($gate['retention']);
}

/** Safe for every exported cell, including leading whitespace/control characters. */
function peracrm_subscribers_csv_cell($value)
{
    $value = (string) $value;
    return preg_match('/^[\x00-\x20]*[=+@-]/', $value) || preg_match('/^[\t\r\n]/', $value) ? "'" . $value : $value;
}

function peracrm_subscribers_suppressions($search = '')
{
    peracrm_subscribers_require_staff();
    return peracrm_with_target_blog(static function () use ($search) {
        global $wpdb;
        $table = peracrm_table('peracrm_email_suppressions');
        $events = peracrm_table('peracrm_email_suppression_events');
        $rows = $wpdb->get_results($wpdb->prepare("SELECT x.*, e.source, e.source_ref, e.occurred_at FROM {$table} x LEFT JOIN {$events} e ON e.id=(SELECT MAX(z.id) FROM {$events} z WHERE z.normalized_email=x.normalized_email) WHERE (x.unsubscribe+x.hard_bounce+x.complaint+x.administrative)>0 AND x.normalized_email LIKE %s ORDER BY x.updated_at DESC LIMIT 50", '%' . $wpdb->esc_like(substr($search,0,254)) . '%'), ARRAY_A);
        peracrm_campaigns_db_require(true);
        return $rows;
    });
}
