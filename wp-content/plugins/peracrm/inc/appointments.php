<?php
/** Authenticated transport and CRM integration. */
if (!defined('ABSPATH')) { exit; }
function peracrm_appointments_ajax(): void
{
    if (($_SERVER['REQUEST_METHOD']??'')!=='POST' || !is_user_logged_in()) { wp_send_json_error(['message'=>'Access denied.'],403); }
    $nonce = is_string($_POST['nonce']??null) ? wp_unslash($_POST['nonce']) : '';
    $blog = peracrm_with_target_blog(static function () { return get_current_blog_id(); });
    if (!wp_verify_nonce($nonce,'peracrm_appointments_'.$blog)) { wp_send_json_error(['message'=>'Session expired. Refresh and try again.'],403); }
    $raw = is_string($_POST['payload']??null)?wp_unslash($_POST['payload']):'';
    $input = json_decode($raw,true);
    if (!is_array($input) || strlen($raw)>50000) { wp_send_json_error(['message'=>'Invalid request.'],400); }
    $result = peracrm_with_target_blog(static function () use ($input) {
        if (!peracrm_user_can_access_crm()) { return new WP_Error('forbidden','Access denied.'); }
        $operation = $input['operation']??'';
        if ($operation==='save') { return peracrm_appointment_save($input); }
        if ($operation==='list') { return peracrm_appointment_list($input); }
        if ($operation==='get') {
            $row=peracrm_appointment_row((int)($input['id']??0));
            return $row ? peracrm_appointment_present($row) : new WP_Error('missing','Appointment unavailable.');
        }
        if ($operation==='refresh') {
            $id=(int)($input['client_id']??0);
            if (!peracrm_user_can_access_client(get_current_user_id(),$id)) { return new WP_Error('forbidden','Access denied.'); }
            $markup=pera_crm_client_view_render_full_html($id);
            return ['status'=>pera_crm_client_view_extract_panel_html($markup,'status'),'timeline'=>pera_crm_client_view_extract_panel_html($markup,'timeline'),'workspace'=>peracrm_client_panels_fragments($markup)];
        }
        if ($operation==='client') {
            $id=(int)($input['client_id']??0);
            if (!peracrm_appointment_can_manage($id)) { return new WP_Error('forbidden','Access denied.'); }
            $now=gmdate('Y-m-d H:i:s');
            $upcoming=peracrm_appointment_query(['client_id'=>$id,'start'=>$now,'end'=>gmdate('Y-m-d H:i:s',time()+366*DAY_IN_SECONDS),'active'=>true,'limit'=>20]);
            return ['id'=>$id,'name'=>get_the_title($id),'advisor_id'=>peracrm_client_get_assigned_advisor_id($id),'stage'=>peracrm_party_get($id)['lead_pipeline_stage'],'upcoming'=>array_map('peracrm_appointment_present',$upcoming)];
        }
        if ($operation==='history') {
            $id=(int)($input['client_id']??0);
            if (!peracrm_user_can_access_client(get_current_user_id(),$id)) { return new WP_Error('forbidden','Access denied.'); }
            global $wpdb;
            $t=peracrm_table('peracrm_appointments'); $offset=max(0,(int)($input['offset']??0));
            $rows=(array)$wpdb->get_results($wpdb->prepare("SELECT * FROM {$t} WHERE client_id=%d ORDER BY CASE WHEN end_at>UTC_TIMESTAMP() AND status IN ('confirmed','tentative') THEN 0 ELSE 1 END, CASE WHEN end_at>UTC_TIMESTAMP() AND status IN ('confirmed','tentative') THEN start_at END ASC, start_at DESC,id DESC LIMIT 21 OFFSET %d",$id,$offset),ARRAY_A);
            return ['items'=>array_map('peracrm_appointment_present',peracrm_appointment_hydrate_rows(array_slice($rows,0,20))),'more'=>count($rows)>20];
        }
        if ($operation==='conflicts') {
            if (!peracrm_appointment_can_manage((int)($input['client_id']??0))) { return new WP_Error('forbidden','Access denied.'); }
            $data=peracrm_appointment_validate($input);
            if (is_wp_error($data)) { return $data; }
            return array_map(static function ($r) { $p=peracrm_appointment_present($r); unset($p['internal_notes'],$p['outcome_notes']); return $p; },peracrm_appointment_conflict_rows($data,(int)($input['id']??0)));
        }
        if ($operation==='search') {
            $kind=$input['kind']??''; $term=is_string($input['term']??null)?sanitize_text_field($input['term']):'';
            if (strlen($term)<2) { return []; }
            if (!in_array($kind,['client','property'],true)) { return new WP_Error('invalid','Invalid search.'); }
            $args=['post_type'=>$kind==='client'?'crm_client':['property','bodrum-property'],'post_status'=>$kind==='client'?['publish','private']:'publish','posts_per_page'=>20,'s'=>$term,'no_found_rows'=>true,'suppress_filters'=>true];
            if ($kind==='client') {
                $scope=peracrm_get_client_access_scope(get_current_user_id());
                if ($scope['type']==='empty') { return []; }
                if ($scope['type']==='assigned') { $args['post__in']=$scope['ids']; }
            }
            $results=[];
            foreach (get_posts($args) as $post) {
                if ($kind==='client' && !peracrm_user_can_access_client(get_current_user_id(),$post->ID)) { continue; }
                if ($post->post_password!=='') { continue; }
                $results[]=['id'=>$post->ID,'title'=>$post->post_title,'detail'=>$kind==='property'?get_post_meta($post->ID,'district',true):''];
            }
            return $results;
        }
        return new WP_Error('invalid','Unknown operation.');
    });
    if (is_wp_error($result)) {
        wp_send_json_error(['code'=>$result->get_error_code(),'message'=>$result->get_error_message(),'details'=>$result->get_error_data()], $result->get_error_code()==='forbidden'?403:($result->get_error_code()==='missing'?404:409));
    }
    wp_send_json_success($result);
}
add_action('wp_ajax_peracrm_appointments','peracrm_appointments_ajax');
function peracrm_appointments_enqueue(): void
{
    if (!pera_is_crm_route() || !is_user_logged_in()) { return; }
    $view=(string)get_query_var('pera_crm_view','');
    if (!in_array($view,['','overview','client','appointments','pipeline'],true)) { return; }
    $file=PERACRM_PATH.'/assets/frontend/appointments.js';
    wp_enqueue_script('peracrm-appointments',PERACRM_URL.'/assets/frontend/appointments.js',[],(string)filemtime($file),true);
    peracrm_frontend_enqueue_style_asset('peracrm-appointments','assets/frontend/appointments.css',['peracrm-frontend-css']);
    $blog=peracrm_with_target_blog(static function () { return get_current_blog_id(); });
    $staff=peracrm_with_target_blog(static function () { return array_values(array_map(static function ($u) { return ['id'=>$u->ID,'name'=>$u->display_name]; },array_filter(peracrm_get_staff_users(),static function ($u) { return peracrm_user_is_valid_advisor($u->ID) && peracrm_user_can_access_crm($u->ID); }))); });
    wp_localize_script('peracrm-appointments','peraAppointments',['url'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('peracrm_appointments_'.$blog),'timezone'=>'Europe/Istanbul','staff'=>$staff,'types'=>peracrm_appointment_types(),'statuses'=>peracrm_appointment_statuses(),'calendarUrl'=>home_url('/crm/appointments/')]);
}
add_action('wp_enqueue_scripts','peracrm_appointments_enqueue',45);
/** Legacy admin-post/post-editor transitions route to the same booking UI, before mutation. */
function peracrm_appointments_admin_booking(): void
{
    if (($_SERVER['REQUEST_METHOD']??'')!=='POST' || !is_user_logged_in()) { return; }
    $stage=$_POST['lead_pipeline_stage']??$_POST['to_status']??'';
    if ($stage!=='viewing_arranged') { return; }
    $action=$_POST['action']??'';
    $nonce_action = '';
    if (in_array($action,['peracrm_save_party_status','peracrm_pipeline_move_stage','peracrm_pipeline_bulk_action'],true)) { $nonce_action=$action; }
    elseif ($action==='editpost' && isset($_POST['peracrm_save_party_status_nonce'])) { $nonce_action='peracrm_save_party_status'; }
    if (!$nonce_action) { return; }
    $nonce=$_POST['peracrm_save_party_status_nonce']??$_POST['_wpnonce']??'';
    if (!is_string($nonce) || !wp_verify_nonce(wp_unslash($nonce),$nonce_action)) { return; }
    $ids=$_POST['client_ids']??[$_POST['peracrm_client_id']??$_POST['client_id']??$_POST['post_ID']??0];
    if (!is_array($ids)) { return; }
    $pending=peracrm_with_target_blog(static function () use ($ids) {
        return array_values(array_filter(array_map('absint',array_slice($ids,0,100)),static function ($id) { return peracrm_appointment_can_manage($id) && peracrm_party_get($id)['lead_pipeline_stage']!=='viewing_arranged'; }));
    });
    if ($pending) {
        wp_safe_redirect(add_query_arg(['book_client'=>$pending[0],'booking_queue'=>implode(',',$pending)],home_url('/crm/appointments/'))); exit;
    }
}
add_action('admin_init','peracrm_appointments_admin_booking',1);
