<?php
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE') || !PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE) { throw new RuntimeException('Disposable fixture required'); }
$mode=$args[0]??'';
peracrm_with_target_blog(static function()use($mode){
 global $wpdb;
 $admin=get_user_by('login','fixture-admin');wp_set_current_user($admin->ID);
 $path='/var/www/html/appointments-race.json';
 if($mode==='prepare-create'){
  $client=wp_insert_post(['post_type'=>'crm_client','post_status'=>'private','post_title'=>'Synthetic concurrent appointment']);
  $data=['client_id'=>$client,'advisor_id'=>$admin->ID,'type'=>'office_consultation','start_local'=>'2028-06-01T10:00','end_local'=>'2028-06-01T11:00','request_key'=>wp_generate_uuid4()];
  file_put_contents($path,wp_json_encode($data));return;
 }
 $data=json_decode(file_get_contents($path),true);
 if($mode==='prepare-edit'){
  $t=peracrm_table('peracrm_appointments');$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE client_id=%d",$data['client_id']),ARRAY_A);
  $data['id']=(int)$row['id'];$data['version']=(int)$row['version'];file_put_contents($path,wp_json_encode($data));return;
 }
 if(strpos($mode,'verify-')===0){
  $t=peracrm_table('peracrm_appointments');$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$t} WHERE client_id=%d",$data['client_id']),ARRAY_A);
  if(count($rows)!==1 || ($mode==='verify-edit' && (int)$rows[0]['version']!==2)){WP_CLI::error('Concurrent writes created duplicates or silently overwrote a version.');}
  WP_CLI::success('Real concurrent appointment '.substr($mode,7).' serialized safely.');return;
 }
 if(strpos($mode,'edit-')===0){$data['request_key']=wp_generate_uuid4();$data['title']=$mode;}
 $result=peracrm_appointment_save($data);
 if(is_wp_error($result) && !(strpos($mode,'edit-')===0 && $result->get_error_code()==='version')){WP_CLI::error($result->get_error_message());}
 WP_CLI::log($mode.': '.(is_wp_error($result)?$result->get_error_code():'saved'));
});
