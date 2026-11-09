<?php
/** Real persistence, rollback, permission and migration assertions. Disposable only. */
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE') || !PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE) { throw new RuntimeException('Disposable fixture required'); }
require_once ABSPATH.'wp-admin/includes/upgrade.php';
function ap_expect($v,$m){if(!$v){WP_CLI::error('Appointments: '.$m);}WP_CLI::log('PASS appointments: '.$m);}
$original=get_current_blog_id();
peracrm_with_target_blog(static function () {
    global $wpdb;
    $admin=get_user_by('login','fixture-admin');wp_set_current_user($admin->ID);
    foreach (['employee-a'=>'employee','employee-b'=>'employee','appointment-manager'=>'manager'] as $name=>$role){$u=get_user_by('login',$name);if(!$u){$id=wp_insert_user(['user_login'=>$name,'user_pass'=>'synthetic-appointments-only','role'=>$role]);ap_expect(!is_wp_error($id),'create '.$name);}else{$id=$u->ID;} $users[$name]=$id;}
    $employee=$users['employee-a'];$other=$users['employee-b'];$manager=$users['appointment-manager'];
    $client=wp_insert_post(['post_type'=>'crm_client','post_status'=>'publish','post_title'=>'Appointment client A']);
    $restricted=wp_insert_post(['post_type'=>'crm_client','post_status'=>'publish','post_title'=>'CONFIDENTIAL client B']);
    foreach ([$client=>$employee,$restricted=>$other] as $cid=>$uid){update_post_meta($cid,'assigned_advisor_user_id',$uid);update_post_meta($cid,'crm_assigned_advisor',$uid);peracrm_party_upsert_status($cid,['lead_pipeline_stage'=>'qualified']);}
    register_post_type('property',['public'=>true]); update_option('campaign_fixture_property_enabled',true);
    $property=wp_insert_post(['post_type'=>'property','post_status'=>'publish','post_title'=>'Synthetic property A']);
    $property2=wp_insert_post(['post_type'=>'property','post_status'=>'publish','post_title'=>'Synthetic property B']);
    $data=['client_id'=>$client,'advisor_id'=>$employee,'participant_ids'=>[$other],'property_ids'=>[$property,$property2],'type'=>'property_tour','start_local'=>'2026-11-10T10:00','end_local'=>'2026-11-10T11:00','timezone'=>'Europe/Istanbul','request_key'=>wp_generate_uuid4()];
    $created=peracrm_appointment_save($data);ap_expect(!is_wp_error($created),'create valid multi-property multi-advisor appointment');
    $id=(int)$created['id'];ap_expect($created['start_at']==='2026-11-10T07:00:00Z','UTC storage / projection');
    ap_expect($created['property_ids']===[$property,$property2] && $created['participant_ids']===[$employee,$other],'association ordering');
    $retry=peracrm_appointment_save($data);ap_expect(!is_wp_error($retry) && (int)$retry['id']===$id,'idempotent duplicate create');
    $changed=peracrm_appointment_save(array_replace($data,['title'=>'Changed']));ap_expect(is_wp_error($changed) && $changed->get_error_code()==='duplicate','mismatched duplicate key rejected');
    $conflict=peracrm_appointment_save(array_replace($data,['request_key'=>wp_generate_uuid4(),'advisor_id'=>$other,'participant_ids'=>[]]));ap_expect(is_wp_error($conflict) && $conflict->get_error_code()==='conflict','additional employee overlap detected');
    $second=peracrm_appointment_save(array_replace($data,['request_key'=>wp_generate_uuid4(),'start_local'=>'2026-11-10T11:00','end_local'=>'2026-11-10T12:00']));ap_expect(!is_wp_error($second),'back-to-back appointments and multiple per client');
    $override=peracrm_appointment_save(array_replace($data,['request_key'=>wp_generate_uuid4(),'allow_overlap'=>true]));ap_expect(!is_wp_error($override),'explicit overlap confirmation');
    $edit=array_replace($data,['id'=>$id,'version'=>1,'request_key'=>wp_generate_uuid4(),'start_local'=>'2026-11-10T13:00','end_local'=>'2026-11-10T14:00','property_ids'=>[$property2,$property]]);
    $edited=peracrm_appointment_save($edit);ap_expect(!is_wp_error($edited) && (int)$edited['version']===2 && $edited['property_ids']===[$property2,$property],'edit reschedule and tour reorder');
    $stale=peracrm_appointment_save(array_replace($edit,['request_key'=>wp_generate_uuid4()]));ap_expect(is_wp_error($stale) && $stale->get_error_code()==='version','concurrent stale edit rejected');
    foreach (['cancelled','completed','no_show','confirmed'] as $status){$edit['version']=$edited['version'];$edit['request_key']=wp_generate_uuid4();$edit['status']=$status;$edit['outcome_notes']='Synthetic outcome';$edited=peracrm_appointment_save($edit);ap_expect(!is_wp_error($edited),'status '.$status);}
    ap_expect(peracrm_party_get($client)['lead_pipeline_stage']==='qualified','lifecycle leaves pipeline unchanged');
    ap_expect(!peracrm_party_upsert_status($client,['lead_pipeline_stage'=>'viewing_arranged']),'raw stage transition requires booking');
    $bad=array_replace($data,['pipeline'=>true,'expected_stage'=>'qualified','request_key'=>wp_generate_uuid4(),'end_local'=>'invalid']);
    $failed=peracrm_appointment_save($bad);ap_expect(is_wp_error($failed) && peracrm_party_get($client)['lead_pipeline_stage']==='qualified','failed booking preserves stage');
    $pipeline=array_replace($data,['pipeline'=>true,'expected_stage'=>'qualified','request_key'=>wp_generate_uuid4(),'start_local'=>gmdate('Y-m-d',time()+14*DAY_IN_SECONDS).'T10:00','end_local'=>gmdate('Y-m-d',time()+14*DAY_IN_SECONDS).'T11:00','engagement_state'=>'engaged','disposition'=>'none']);
    $booked=peracrm_appointment_save($pipeline);ap_expect(!is_wp_error($booked) && peracrm_party_get($client)['lead_pipeline_stage']==='viewing_arranged','atomic pipeline booking');
    $bookedAgain=peracrm_appointment_save($pipeline);ap_expect(!is_wp_error($bookedAgain) && $bookedAgain['id']===$booked['id'],'pipeline repeated submit deduplicated');
    $secret=array_replace($data,['client_id'=>$restricted,'advisor_id'=>$other,'participant_ids'=>[],'title'=>'SECRET TITLE','internal_notes'=>'SECRET NOTES','start_local'=>'2026-11-11T10:00','end_local'=>'2026-11-11T11:00','request_key'=>wp_generate_uuid4()]);
    $private=peracrm_appointment_save($secret);ap_expect(!is_wp_error($private),'restricted team fixture');
    wp_set_current_user($employee);
    $range=['start'=>'2026-11-09T00:00','end'=>'2026-11-16T00:00','timezone'=>'Europe/Istanbul'];
    $list=peracrm_appointment_list($range);ap_expect(!is_wp_error($list) && count($list['items'])>=4,'employee global team calendar');
    $minimal=array_values(array_filter($list['items'],static function($r)use($private){return $r['id']==$private['id'];}))[0];
    ap_expect($minimal['restricted'] && !isset($minimal['client_id']) && !str_contains(wp_json_encode($minimal),'SECRET'),'restricted calendar projection hides private fields');
    $denied=peracrm_appointment_save(array_replace($secret,['request_key'=>wp_generate_uuid4()]));ap_expect(is_wp_error($denied) && $denied->get_error_code()==='forbidden','unassigned employee write rejected');
    $reassign=peracrm_appointment_save(array_replace($edit,['version'=>$edited['version'],'advisor_id'=>$other,'request_key'=>wp_generate_uuid4()]));ap_expect(is_wp_error($reassign) && $reassign->get_error_code()==='forbidden','employee cannot reassign primary');
    $allowed=peracrm_appointment_save(array_replace($data,['request_key'=>wp_generate_uuid4(),'start_local'=>'2026-11-12T10:00','end_local'=>'2026-11-12T11:00']));ap_expect(!is_wp_error($allowed),'assigned employee creates');
    wp_set_current_user($other);$denyEdit=peracrm_appointment_save(array_replace($edit,['version'=>$edited['version'],'request_key'=>wp_generate_uuid4()]));ap_expect(is_wp_error($denyEdit),'participant without client access cannot edit');
    wp_set_current_user($manager);ap_expect(peracrm_appointment_can_manage($client),'manager team access');
    wp_set_current_user(0);ap_expect(is_wp_error(peracrm_appointment_list($range)) && is_wp_error(peracrm_appointment_save($data)),'anonymous service denied');
    wp_set_current_user($admin->ID);
    $filtered=peracrm_appointment_list($range+['advisor_id'=>$other,'type'=>'property_tour','status'=>'confirmed']);ap_expect(count($filtered['items'])>=1,'advisor type status filters');
    ap_expect(is_wp_error(peracrm_appointment_list(['start'=>'2026-01-01T00:00','end'=>'2027-01-01T00:00'])),'oversized range rejected');
    $t=peracrm_table('peracrm_appointments');$before=$wpdb->get_results("SELECT * FROM {$t} ORDER BY id",ARRAY_A);
    foreach(peracrm_appointments_schema_definitions() as $name=>$definition){dbDelta('CREATE TABLE '.peracrm_table($name)." (\n{$definition}\n) ENGINE=InnoDB ".$wpdb->get_charset_collate().';');}
    ap_expect($before===$wpdb->get_results("SELECT * FROM {$t} ORDER BY id",ARRAY_A),'repeated migration preserves appointments');
    // Inject an activity write failure after appointment insertion: both booking and stage roll back.
    $count=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$t}");peracrm_party_upsert_status($client,['lead_pipeline_stage'=>'qualified']);
    $block=static function($query){return strpos($query,'INSERT INTO '.peracrm_table('crm_activity'))===0 ? 'INVALID APPOINTMENT AUDIT QUERY' : $query;};
    add_filter('query',$block);$previous=$wpdb->suppress_errors(true);
    $rollback=peracrm_appointment_save(array_replace($pipeline,['request_key'=>wp_generate_uuid4(),'start_local'=>'2027-01-11T10:00','end_local'=>'2027-01-11T11:00']));
    remove_filter('query',$block);$wpdb->suppress_errors($previous);
    ap_expect(is_wp_error($rollback) && (int)$wpdb->get_var("SELECT COUNT(*) FROM {$t}")===$count && peracrm_party_get($client)['lead_pipeline_stage']==='qualified','audit failure rolls back appointment and stage');
    if(is_multisite()){$target=get_current_blog_id();switch_to_blog(1);$cross=peracrm_appointment_list($range);ap_expect(!is_wp_error($cross) && get_current_blog_id()===1,'cross-blog call stays target scoped and restores caller');restore_current_blog();ap_expect($target===get_current_blog_id(),'target restored');}
    file_put_contents('/var/www/html/appointments-fixture.json',wp_json_encode(['client'=>$client,'restricted'=>$restricted,'employee'=>$employee,'other'=>$other,'appointment'=>$id,'property'=>$property,'target'=>home_url()]));
});
ap_expect(get_current_blog_id()===$original,'caller blog restored');WP_CLI::success('Appointment integration checks passed.');
