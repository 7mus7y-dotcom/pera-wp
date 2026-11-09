<?php
/** Canonical appointments boundary. All public operations switch to the CRM blog. */
if (!defined('ABSPATH')) { exit; }
function peracrm_appointment_types(): array
{
    return ['property_viewing'=>'Property viewing','property_tour'=>'Multiple property tour','office_consultation'=>'Office consultation','hotel_meeting'=>'Hotel meeting','online_meeting'=>'Online / video meeting','other'=>'Other'];
}
function peracrm_appointment_statuses(): array
{
    return ['tentative'=>'Tentative','confirmed'=>'Confirmed','completed'=>'Completed','cancelled'=>'Cancelled','no_show'=>'No-show'];
}
function peracrm_appointment_team_manager(): bool
{
    return current_user_can('manage_options') || current_user_can('peracrm_manage_all_clients');
}
function peracrm_appointment_can_manage(int $client_id, ?array $row = null): bool
{
    if (!is_user_logged_in() || !peracrm_user_can_access_crm() || !current_user_can('edit_crm_clients')
        || !peracrm_user_can_access_client(get_current_user_id(), $client_id)) { return false; }
    return !$row || peracrm_appointment_team_manager() || in_array(get_current_user_id(), $row['participant_ids'], true);
}
/** Strict wall-clock parsing: reject normalization, nonexistent and ambiguous DST times. */
function peracrm_appointment_utc(string $local, string $zone)
{
    if (!in_array($zone, timezone_identifiers_list(), true)) { return new WP_Error('timezone','Select a valid IANA timezone.'); }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D', $local)) { return new WP_Error('datetime','Enter a valid date and time.'); }
    $tz = new DateTimeZone($zone);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $local, $tz);
    if (!$date || $date->format('Y-m-d\TH:i') !== $local) { return new WP_Error('datetime','This local time does not exist. Select another time.'); }
    $stamp = $date->getTimestamp();
    $transitions = $tz->getTransitions($stamp-172800, $stamp+172800);
    $offset = $transitions[0]['offset'];
    foreach ($transitions as $transition) {
        $delta = $offset - $transition['offset'];
        if ($delta > 0 && $stamp >= $transition['ts']-$delta && $stamp < $transition['ts']+$delta) {
            return new WP_Error('datetime','This time occurs twice during daylight saving. Select an unambiguous time.');
        }
        $offset = $transition['offset'];
    }
    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}
function peracrm_appointment_validate(array $input)
{
    foreach ($input as $value) { if (is_object($value)) { return new WP_Error('invalid','Invalid appointment data.'); } }
    foreach (['client_id','advisor_id','attendees'] as $field) {
        $value = $input[$field] ?? ($field === 'attendees' ? 2 : 0);
        if (!is_scalar($value) || !preg_match('/^[1-9][0-9]*$/D', (string)$value) || (float)$value > 2147483647) { return new WP_Error('invalid','Client, advisor and attendees must be positive integers.'); }
    }
    $d = ['client_id'=>(int)$input['client_id'],'advisor_id'=>(int)$input['advisor_id'],'attendees'=>(int)($input['attendees']??2)];
    foreach (['type'=>'other','status'=>'confirmed','timezone'=>'Europe/Istanbul','location_type'=>'other'] as $key=>$default) {
        if (isset($input[$key]) && !is_string($input[$key])) { return new WP_Error('invalid','Invalid selection.'); }
        $d[$key] = $input[$key] ?? $default;
    }
    if (!isset(peracrm_appointment_types()[$d['type']]) || !isset(peracrm_appointment_statuses()[$d['status']])
        || !in_array($d['location_type'], ['property','office','hotel','online','other'], true)) { return new WP_Error('invalid','Select a valid type, location and status.'); }
    foreach (['start_at'=>'start_local','end_at'=>'end_local'] as $out=>$key) {
        $d[$out] = peracrm_appointment_utc(is_string($input[$key]??null)?$input[$key]:'', $d['timezone']);
        if (is_wp_error($d[$out])) { return $d[$out]; }
    }
    if ($d['end_at'] <= $d['start_at'] || strtotime($d['end_at'].' UTC')-strtotime($d['start_at'].' UTC') > 7*DAY_IN_SECONDS) { return new WP_Error('datetime','End must follow start, within seven days.'); }
    if (get_post_type($d['client_id']) !== 'crm_client' || get_post_status($d['client_id']) === 'trash') { return new WP_Error('invalid','Invalid client.'); }
    foreach (['participant_ids','property_ids'] as $key) {
        $values = $input[$key] ?? [];
        if (!is_array($values) || count($values)>50) { return new WP_Error('invalid','Select up to 50 associations.'); }
        $d[$key] = [];
        foreach ($values as $v) {
            if (!is_scalar($v) || !preg_match('/^[1-9][0-9]*$/D', (string)$v)) { return new WP_Error('invalid','Invalid association.'); }
            $d[$key][] = (int)$v;
        }
        if (count(array_unique($d[$key])) !== count($d[$key])) { return new WP_Error('invalid','Duplicate associations are not allowed.'); }
    }
    $additional=array_values(array_diff($d['participant_ids'],[$d['advisor_id']])); sort($additional,SORT_NUMERIC);
    $d['participant_ids'] = array_merge([$d['advisor_id']],$additional);
    foreach ($d['participant_ids'] as $uid) {
        if (!peracrm_user_is_valid_advisor($uid) || !peracrm_user_can_access_crm($uid)
            || (is_multisite() && !is_user_member_of_blog($uid, get_current_blog_id()))) { return new WP_Error('advisor','Select authorized CRM employees on this site.'); }
    }
    foreach ($d['property_ids'] as $pid) {
        if (!in_array(get_post_type($pid), ['property','bodrum-property'], true) || get_post_status($pid) !== 'publish' || post_password_required($pid)) { return new WP_Error('property','Select permitted published properties.'); }
    }
    foreach (['title'=>255,'location_name'=>255,'address'=>1000,'hotel_name'=>255,'internal_notes'=>10000,'outcome_notes'=>10000,'map_url'=>2000,'meeting_url'=>2000] as $key=>$max) {
        $v = $input[$key] ?? '';
        if (!is_string($v) || strlen($v)>$max) { return new WP_Error('invalid','A text field exceeds its maximum length.'); }
        if (in_array($key, ['map_url','meeting_url'], true)) {
            if ($v !== '' && (!filter_var($v,FILTER_VALIDATE_URL) || !in_array(wp_parse_url($v, PHP_URL_SCHEME), ['http','https'], true))) { return new WP_Error('url','Enter a valid HTTP(S) URL.'); }
            $d[$key] = esc_url_raw($v, ['http','https']);
        } else { $d[$key] = sanitize_textarea_field($v); }
    }
    $d['updated_by'] = get_current_user_id(); $d['updated_at'] = gmdate('Y-m-d H:i:s');
    return $d;
}
/** Privacy projection: restricted clients expose time, employee, generic type/status only. */
function peracrm_appointment_present(array $r, ?array $scope = null): array
{
    $advisor = get_userdata((int)$r['advisor_id']);
    $base = ['id'=>(int)$r['id'],'start_at'=>str_replace(' ','T',$r['start_at']).'Z','end_at'=>str_replace(' ','T',$r['end_at']).'Z','type'=>$r['type'],'status'=>$r['status'],'advisor_id'=>(int)$r['advisor_id'],'advisor_name'=>$advisor?$advisor->display_name:'','can_manage'=>false,'restricted'=>true];
    $accessible=$scope===null ? peracrm_user_can_access_client(get_current_user_id(), (int)$r['client_id']) : ($scope['type']==='full' || ($scope['type']==='assigned' && in_array((int)$r['client_id'],$scope['ids'],true)));
    if (!$accessible) { return $base; }
    $r = isset($r['participant_ids']) ? $r : peracrm_appointment_row((int)$r['id']);
    $r['start_at'] = str_replace(' ','T',$r['start_at']).'Z'; $r['end_at'] = str_replace(' ','T',$r['end_at']).'Z';
    $r['client_name'] = get_the_title((int)$r['client_id']);
    $r['client_url'] = home_url('/crm/client/'.(int)$r['client_id'].'/');
    $r['advisor_name'] = $base['advisor_name']; $r['can_manage'] = current_user_can('edit_crm_clients') && (peracrm_appointment_team_manager() || in_array(get_current_user_id(),$r['participant_ids'],true)); $r['restricted'] = false;
    $r['properties'] = [];
    foreach ($r['property_ids'] as $id) {
        if (get_post_status($id) === 'publish' && !post_password_required($id)) { $r['properties'][] = ['id'=>$id,'title'=>get_the_title($id),'url'=>get_permalink($id)]; }
    }
    return $r;
}
function peracrm_appointment_list(array $filters)
{
    return peracrm_with_target_blog(static function () use ($filters) {
        if (!is_user_logged_in() || !peracrm_user_can_access_crm()) { return new WP_Error('forbidden','Access denied.'); }
        $start = peracrm_appointment_utc((string)($filters['start']??''), (string)($filters['timezone']??'Europe/Istanbul'));
        $end = peracrm_appointment_utc((string)($filters['end']??''), (string)($filters['timezone']??'Europe/Istanbul'));
        if (is_wp_error($start) || is_wp_error($end) || $end <= $start || strtotime($end)-strtotime($start)>93*DAY_IN_SECONDS) { return new WP_Error('range','Choose a date range of at most 93 days.'); }
        $client_id = (int)($filters['client_id']??0);
        if ($client_id && !peracrm_user_can_access_client(get_current_user_id(), $client_id)) { return new WP_Error('forbidden','Access denied.'); }
        $f = ['start'=>$start,'end'=>$end,'offset'=>max(0,(int)($filters['offset']??0)),'client_id'=>$client_id,'advisor_id'=>(int)($filters['advisor_id']??0),'type'=>sanitize_key($filters['type']??''),'status'=>sanitize_key($filters['status']??'')];
        $rows = peracrm_appointment_query($f); $more = count($rows)>200;
        $scope=peracrm_get_client_access_scope(get_current_user_id());
        return ['items'=>array_map(static function($r)use($scope){return peracrm_appointment_present($r,$scope);}, array_slice($rows,0,200)), 'more'=>$more,'offset'=>$f['offset']];
    });
}
/** Serialized per target blog, so creates, edits and stage-only reuse share one lock order. */
function peracrm_appointment_save(array $input)
{
    return peracrm_with_target_blog(static function () use ($input) {
        global $wpdb;
        if (!is_user_logged_in() || !peracrm_user_can_access_crm()) { return new WP_Error('forbidden','Access denied.'); }
        $key = $input['request_key'] ?? '';
        if (!is_string($key) || !preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$key)) { return new WP_Error('request','A valid submission key is required.'); }
        $id = (int)($input['id']??0); $client = (int)($input['client_id']??0);
        if (!peracrm_appointment_can_manage($client)) { return new WP_Error('forbidden','Access denied.'); }
        $lock = 'pera-appt-'.substr(hash('sha256',DB_NAME.':'.$wpdb->prefix),0,40);
        if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,5)',$lock)) !== 1) { return new WP_Error('busy','Calendar is busy. Retry this submission.'); }
        $transaction = false;
        try {
            // Fail closed on legacy nontransactional tables: never pretend to roll back.
            foreach (['peracrm_appointments','peracrm_appointment_participants','peracrm_appointment_properties','peracrm_appointment_requests','peracrm_party','crm_activity','postmeta'] as $table) {
                $engine = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name=%s',peracrm_table($table)), ARRAY_A);
                if (strtolower($engine['Engine']??'') !== 'innodb') { return new WP_Error('schema','Appointment storage is unavailable. Ask an administrator to verify the schema and InnoDB tables.'); }
            }
            if ($wpdb->query('START TRANSACTION') === false) { throw new RuntimeException('Unable to start appointment transaction.'); }
            $transaction = true;
            $request = peracrm_table('peracrm_appointment_requests');
            $actor = get_current_user_id();
            $hash_input = $input; unset($hash_input['request_key']);
            $hash = hash('sha256',wp_json_encode($hash_input));
            $receipt = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$request} WHERE actor_id=%d AND request_key=%s",$actor,$key),ARRAY_A);
            if ($receipt) {
                $row = peracrm_appointment_row((int)$receipt['appointment_id']);
                if (!hash_equals($receipt['payload_hash'],$hash)) { return new WP_Error('duplicate','This submission key was already used for different values. Reopen the appointment.'); }
                if (!$row || !peracrm_appointment_can_manage((int)$row['client_id'],$row)) { return new WP_Error('forbidden','Access denied.'); }
                $wpdb->query('ROLLBACK'); $transaction=false;
                return peracrm_appointment_present($row);
            }
            $old = $id ? peracrm_appointment_row($id,true) : null;
            if ($id && (!$old || (int)$old['client_id']!==$client || !peracrm_appointment_can_manage($client,$old))) { return new WP_Error('forbidden','Access denied.'); }
            if ($old && (int)($input['version']??0)!==(int)$old['version']) { return new WP_Error('version','Someone changed this appointment. Reopen it before saving.'); }
            $reuse = !empty($input['reuse']);
            if ($reuse && (!$old || !in_array($old['status'],['confirmed','tentative'],true) || $old['end_at'] <= gmdate('Y-m-d H:i:s'))) { return new WP_Error('invalid','Select an upcoming active appointment.'); }
            // Serialize against legacy stage writers too; never use a cached stage snapshot.
            $party_table=peracrm_table('peracrm_party');
            $wpdb->get_row($wpdb->prepare("SELECT party_id FROM {$party_table} WHERE party_id=%d FOR UPDATE",$client),ARRAY_A);
            $party = peracrm_party_get($client);
            if (!empty($input['pipeline']) && ($input['expected_stage']??'') !== $party['lead_pipeline_stage']) { return new WP_Error('stage','The pipeline stage changed. Refresh and try again.'); }
            if (!$reuse) {
                $data = peracrm_appointment_validate($input);
                if (is_wp_error($data)) { return $data; }
                if (!peracrm_appointment_team_manager()) {
                    $assigned = peracrm_client_get_assigned_advisor_id($client);
                    $allowed = $old ? (int)$old['advisor_id'] : $assigned;
                    if ($data['advisor_id'] !== $allowed) { return new WP_Error('forbidden','Only a manager can reassign the primary advisor.'); }
                }
                $conflicts = peracrm_appointment_conflict_rows($data,$id);
                if ($conflicts && ($input['allow_overlap']??false) !== true) {
                    return new WP_Error('conflict','Assigned employees have overlapping appointments. Review and explicitly confirm to save.',array_map(static function ($r) { $p=peracrm_appointment_present($r); $p['conflict_user_id']=(int)$r['conflict_user_id']; $u=get_userdata($p['conflict_user_id']); $p['conflict_user_name']=$u?$u->display_name:''; unset($p['internal_notes'],$p['outcome_notes']); return $p; },$conflicts));
                }
                $id = peracrm_appointment_write($data,$old);
                $events = [];
                if (!$old) { $events[]='created'; }
                else {
                    if ($data['start_at']!==$old['start_at'] || $data['end_at']!==$old['end_at']) { $events[]='rescheduled'; }
                    if ($data['advisor_id']!==(int)$old['advisor_id'] || $data['participant_ids']!==$old['participant_ids']) { $events[]='reassigned'; }
                    if ($data['status']!==$old['status']) { $events[]=$data['status']; }
                    if (!$events) { $events[]='updated'; }
                }
                if ($conflicts) { $events[]='overlap_confirmed'; }
                foreach ($events as $event) {
                    if (!peracrm_activity_insert($client,'appointment_'.$event,['appointment_id'=>$id,'actor_user_id'=>$actor,'start_at'=>$data['start_at'],'end_at'=>$data['end_at'],'timezone'=>$data['timezone'],'status'=>$data['status'],'previous_start'=>$old['start_at']??null,'previous_status'=>$old['status']??null,'advisor_id'=>$data['advisor_id'],'previous_advisor_id'=>$old['advisor_id']??null,'participant_ids'=>$data['participant_ids'],'previous_participant_ids'=>$old['participant_ids']??[],'property_ids'=>$data['property_ids'],'conflict_ids'=>array_values(array_unique(array_map('intval',array_column($conflicts,'id'))))])) { throw new RuntimeException('Unable to record appointment activity.'); }
                }
            }
            if (!empty($input['pipeline'])) {
                $status = $party;
                $status['lead_pipeline_stage']='viewing_arranged';
                foreach (['engagement_state'=>'peracrm_party_engagement_options','disposition'=>'peracrm_party_disposition_options'] as $field=>$fn) {
                    if (isset($input[$field])) {
                        // Exact canonical options, rather than silently sanitizing invalid values.
                        $options = $fn();
                        if (!is_string($input[$field]) || !isset($options[$input[$field]])) { return new WP_Error('stage','Invalid status selection.'); }
                        $status[$field]=$input[$field];
                    }
                }
                if (array_key_exists('client_type', $input)) {
                    $type=$input['client_type']; $options=peracrm_client_type_options();
                    if (!is_string($type) || ($type!=='' && !isset($options[$type]))) { return new WP_Error('stage','Invalid client type.'); }
                    $previous=get_post_meta($client,'_peracrm_client_type',true);
                    if ($previous!==$type) {
                        $changed=$type==='' ? delete_post_meta($client,'_peracrm_client_type') : update_post_meta($client,'_peracrm_client_type',$type);
                        if (!$changed) { throw new RuntimeException('Unable to save client type.'); }
                    }
                }
                $GLOBALS['peracrm_appointment_stage_client']=$client;
                try { $ok=peracrm_party_upsert_status($client,$status); }
                finally { unset($GLOBALS['peracrm_appointment_stage_client']); }
                if (!$ok) { throw new RuntimeException('Unable to save pipeline stage.'); }
                if ($party['lead_pipeline_stage']!=='viewing_arranged' && !peracrm_activity_insert($client,'status_changed',['from'=>$party['lead_pipeline_stage'],'to'=>'viewing_arranged','actor_user_id'=>$actor,'context'=>'appointment_booking','appointment_id'=>$id])) { throw new RuntimeException('Unable to record stage change.'); }
            }
            if (!$wpdb->insert($request,['actor_id'=>$actor,'request_key'=>$key,'payload_hash'=>$hash,'appointment_id'=>$id,'created_at'=>gmdate('Y-m-d H:i:s')])) { throw new RuntimeException('Unable to record submission.'); }
            if ($wpdb->query('COMMIT')===false) { throw new RuntimeException('Unable to commit appointment.'); }
            $transaction=false;
            return peracrm_appointment_present(peracrm_appointment_row($id));
        } catch (Throwable $e) {
            return new WP_Error('save','Unable to confirm the appointment save. Retry this submission; duplicate protection will check its result.');
        } finally {
            if ($transaction) { $wpdb->query('ROLLBACK'); }
            if (array_key_exists('client_type',$input)) { wp_cache_delete($client,'post_meta'); }
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));
        }
    });
}
