<?php
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE') || !PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE) { throw new RuntimeException('Disposable fixture required'); }
peracrm_with_target_blog(static function(){
 global $wpdb;
 require_once ABSPATH.'wp-admin/includes/upgrade.php';
 $before=[];
 foreach(['peracrm_appointments','peracrm_appointment_participants','peracrm_appointment_properties','peracrm_party','peracrm_deals','crm_notes'] as $name){$before[$name]=$wpdb->get_results('SELECT * FROM '.peracrm_table($name),ARRAY_A);}
 $posts=$wpdb->get_results("SELECT * FROM {$wpdb->posts}",ARRAY_A);$meta=$wpdb->get_results("SELECT * FROM {$wpdb->postmeta}",ARRAY_A);
 update_option('peracrm_schema_version',41);
 if(!peracrm_maybe_upgrade_schema() || (int)get_option('peracrm_schema_version')!==42){WP_CLI::error('Appointment upgrade did not publish version 42.');}
 if(!peracrm_maybe_upgrade_schema()){WP_CLI::error('Repeated upgrade failed.');}
 foreach($before as $name=>$rows){if($rows!==$wpdb->get_results('SELECT * FROM '.peracrm_table($name),ARRAY_A)){WP_CLI::error('Upgrade changed existing '.$name);}}
 if($posts!==$wpdb->get_results("SELECT * FROM {$wpdb->posts}",ARRAY_A) || $meta!==$wpdb->get_results("SELECT * FROM {$wpdb->postmeta}",ARRAY_A)){WP_CLI::error('Upgrade changed existing clients/properties or metadata.');}
 peracrm_appointments_assert_indexes();
 if(!empty($GLOBALS['peracrm_schema_migrating'])){WP_CLI::error('Migration context leaked.');}
 WP_CLI::success('Version 41 → 42 upgrade and repeated init preserve exact appointments, clients, property posts, metadata, party, notes and deals.');
});
