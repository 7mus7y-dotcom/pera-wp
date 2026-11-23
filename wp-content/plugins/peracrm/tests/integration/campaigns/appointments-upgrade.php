<?php
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE') || !PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE) { throw new RuntimeException('Disposable fixture required'); }
peracrm_with_target_blog(static function(){
 global $wpdb;
 require_once ABSPATH.'wp-admin/includes/upgrade.php';
 $before=[];
 foreach(['peracrm_appointments','peracrm_appointment_participants','peracrm_appointment_properties','peracrm_party','peracrm_deals','crm_notes'] as $name){$before[$name]=$wpdb->get_results('SELECT * FROM '.peracrm_table($name),ARRAY_A);}
 $posts=$wpdb->get_results("SELECT * FROM {$wpdb->posts}",ARRAY_A);$meta=$wpdb->get_results("SELECT * FROM {$wpdb->postmeta}",ARRAY_A);
 update_option('peracrm_schema_version',41);
 if(!peracrm_maybe_upgrade_schema() || (int)get_option('peracrm_schema_version')!==PERACRM_SCHEMA_VERSION){WP_CLI::error('Appointment upgrade did not publish the current schema version.');}
 if(!peracrm_maybe_upgrade_schema()){WP_CLI::error('Repeated upgrade failed.');}
 foreach($before as $name=>$rows){if($rows!==$wpdb->get_results('SELECT * FROM '.peracrm_table($name),ARRAY_A)){WP_CLI::error('Upgrade changed existing '.$name);}}
 $meta_without_lifecycle_mirror=static function($rows){return array_values(array_filter($rows,static function($row){return ($row['meta_key']??'')!=='_peracrm_status';}));};
 $after_meta=$wpdb->get_results("SELECT * FROM {$wpdb->postmeta}",ARRAY_A);
 if($posts!==$wpdb->get_results("SELECT * FROM {$wpdb->posts}",ARRAY_A) || $meta_without_lifecycle_mirror($meta)!==$meta_without_lifecycle_mirror($after_meta)){WP_CLI::error('Upgrade changed existing posts or non-lifecycle metadata.');}
 foreach($wpdb->get_results('SELECT party_id, engagement_state FROM '.peracrm_table('peracrm_party'),ARRAY_A) as $party){
  if(get_post_meta((int)$party['party_id'],'_peracrm_status',true)!==$party['engagement_state']){WP_CLI::error('Lifecycle compatibility mirror does not match canonical party status.');}
 }
 peracrm_appointments_assert_indexes();
 if(!empty($GLOBALS['peracrm_schema_migrating'])){WP_CLI::error('Migration context leaked.');}
 WP_CLI::success('Version 41 → 42 upgrade and repeated init preserve exact appointments, clients, property posts, metadata, party, notes and deals.');
});
