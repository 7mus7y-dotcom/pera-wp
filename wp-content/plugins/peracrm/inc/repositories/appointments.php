<?php
/** Internal SQL operations. Public access must go through appointment_service. */
if (!defined('ABSPATH')) { exit; }
function peracrm_appointment_row(int $id, bool $lock = false)
{
    global $wpdb;
    $t = peracrm_table('peracrm_appointments');
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id=%d" . ($lock ? ' FOR UPDATE' : ''), $id), ARRAY_A);
    if (!$row) { return null; }
    $p = peracrm_table('peracrm_appointment_participants');
    $q = peracrm_table('peracrm_appointment_properties');
    $row['participant_ids'] = array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT user_id FROM {$p} WHERE appointment_id=%d ORDER BY is_primary DESC,user_id", $id)));
    $row['property_ids'] = array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT property_id FROM {$q} WHERE appointment_id=%d ORDER BY position", $id)));
    return $row;
}
function peracrm_appointment_query(array $f): array
{
    global $wpdb;
    $t = peracrm_table('peracrm_appointments');
    $p = peracrm_table('peracrm_appointment_participants');
    $where = ['a.start_at < %s', 'a.end_at > %s'];
    $args = [$f['end'], $f['start']];
    foreach (['client_id','type','status'] as $key) {
        if (!empty($f[$key])) { $where[] = "a.{$key} = %s"; $args[] = $f[$key]; }
    }
    if (!empty($f['advisor_id'])) {
        $where[] = "EXISTS (SELECT 1 FROM {$p} p WHERE p.appointment_id=a.id AND p.user_id=%d)";
        $args[] = $f['advisor_id'];
    }
    if (!empty($f['active'])) { $where[] = "a.status IN ('tentative','confirmed')"; }
    $args[] = min(201, max(1, (int) ($f['limit'] ?? 201)));
    $args[] = max(0, (int) ($f['offset'] ?? 0));
    return peracrm_appointment_hydrate_rows((array) $wpdb->get_results($wpdb->prepare("SELECT a.* FROM {$t} a WHERE " . implode(' AND ', $where) . ' ORDER BY a.start_at,a.id LIMIT %d OFFSET %d', $args), ARRAY_A));
}
function peracrm_appointment_conflict_rows(array $data, int $except = 0): array
{
    global $wpdb;
    if (!in_array($data['status'], ['confirmed','tentative'], true)) { return []; }
    $a = peracrm_table('peracrm_appointments');
    $p = peracrm_table('peracrm_appointment_participants');
    $ids = implode(',', array_map('intval', $data['participant_ids']));
    return (array) $wpdb->get_results($wpdb->prepare("SELECT DISTINCT a.*,p.user_id AS conflict_user_id FROM {$a} a INNER JOIN {$p} p ON p.appointment_id=a.id WHERE p.user_id IN ({$ids}) AND a.id<>%d AND a.status IN ('tentative','confirmed') AND a.start_at<%s AND a.end_at>%s ORDER BY a.start_at,a.id LIMIT 100", $except, $data['end_at'], $data['start_at']), ARRAY_A);
}
function peracrm_appointment_write(array $data, ?array $old): int
{
    global $wpdb;
    $participants = $data['participant_ids']; $properties = $data['property_ids'];
    unset($data['participant_ids'], $data['property_ids']);
    $t = peracrm_table('peracrm_appointments');
    if ($old) {
        $data['version'] = (int) $old['version'] + 1;
        $ok = $wpdb->update($t, $data, ['id'=>(int)$old['id'], 'version'=>(int)$old['version']]);
        if ($ok !== 1) { throw new RuntimeException('Concurrent appointment update. Refresh before saving.'); }
        $id = (int) $old['id'];
    } else {
        $data['created_by'] = get_current_user_id(); $data['created_at'] = gmdate('Y-m-d H:i:s');
        if (!$wpdb->insert($t, $data)) { throw new RuntimeException('Unable to create appointment.'); }
        $id = (int) $wpdb->insert_id;
    }
    $p = peracrm_table('peracrm_appointment_participants'); $q = peracrm_table('peracrm_appointment_properties');
    if ($wpdb->delete($p, ['appointment_id'=>$id]) === false || $wpdb->delete($q, ['appointment_id'=>$id]) === false) { throw new RuntimeException('Unable to update associations.'); }
    foreach ($participants as $uid) {
        if (!$wpdb->insert($p, ['appointment_id'=>$id,'user_id'=>$uid,'is_primary'=>(int)($uid === $data['advisor_id'])])) { throw new RuntimeException('Unable to assign employee.'); }
    }
    foreach ($properties as $position=>$pid) {
        if (!$wpdb->insert($q, ['appointment_id'=>$id,'property_id'=>$pid,'position'=>$position])) { throw new RuntimeException('Unable to link property.'); }
    }
    return $id;
}

/** Load associations in two indexed queries per page, not two per event. */
function peracrm_appointment_hydrate_rows(array $rows): array
{
    if (!$rows) { return []; }
    global $wpdb;
    $ids=implode(',',array_map('intval',array_column($rows,'id')));
    $participants=[]; $properties=[];
    $p=peracrm_table('peracrm_appointment_participants'); $q=peracrm_table('peracrm_appointment_properties');
    foreach((array)$wpdb->get_results("SELECT appointment_id,user_id FROM {$p} WHERE appointment_id IN ({$ids}) ORDER BY is_primary DESC,user_id",ARRAY_A) as $r){$participants[$r['appointment_id']][]=(int)$r['user_id'];}
    foreach((array)$wpdb->get_results("SELECT appointment_id,property_id FROM {$q} WHERE appointment_id IN ({$ids}) ORDER BY position",ARRAY_A) as $r){$properties[$r['appointment_id']][]=(int)$r['property_id'];}
    foreach($rows as &$row){$row['participant_ids']=$participants[$row['id']]??[];$row['property_ids']=$properties[$row['id']]??[];}unset($row);
    return $rows;
}
