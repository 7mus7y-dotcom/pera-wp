<?php
/** Real user metadata, target-blog isolation and data preservation in disposable CI. */
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE') || !PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE) { throw new RuntimeException('Disposable fixture required.'); }
function panels_fixture_expect($value, $message) {
    if (!$value) { WP_CLI::error('Client panels: ' . $message); }
    WP_CLI::log('PASS client panels: ' . $message);
}
$panels_original_user = get_current_user_id();
$panels_original_blog = get_current_blog_id();
peracrm_with_target_blog(static function () {
    global $wpdb;
    $admin = get_user_by('login', 'fixture-admin');
    wp_set_current_user($admin->ID);
    $client = wp_insert_post(['post_type'=>'crm_client','post_status'=>'private','post_title'=>'Synthetic panel visibility client']);
    $other = wp_insert_post(['post_type'=>'crm_client','post_status'=>'private','post_title'=>'Synthetic panel isolation client']);
    $employee = wp_insert_user(['user_login'=>'panel-employee','user_pass'=>'synthetic-panel-only','role'=>'employee']);
    panels_fixture_expect($client > 0 && $other > 0 && !is_wp_error($employee), 'synthetic clients and employee created');
    foreach ([$client,$other] as $id) {
        update_post_meta($id, 'assigned_advisor_user_id', $employee);
        update_post_meta($id, 'crm_assigned_advisor', $employee);
        update_post_meta($id, '_peracrm_client_type', 'seller');
        peracrm_party_upsert_status($id, ['lead_pipeline_stage'=>'offer_made']);
    }
    wp_set_current_user($employee);
    panels_fixture_expect(peracrm_client_panels_allowed($client), 'canonical employee client access');
    $instruction = peracrm_instruction_mutate($client, ['instruction_type'=>'sale','property'=>['name'=>'Synthetic panel property'],'identifier'=>'Unit 8'], wp_create_nonce('peracrm_property_instruction_' . $client), 'POST');
    $deal = peracrm_deals_create(['party_id'=>$client,'title'=>'Synthetic panel deal','stage'=>'payment_in_progress','deal_value'=>100]);
    panels_fixture_expect(is_int($instruction) && $instruction > 0 && $deal > 0, 'instruction and in-progress deal saved');
    $instructions_before = peracrm_instructions_list($client);
    $deals_before = peracrm_deals_get_by_party($client);
    panels_fixture_expect(peracrm_client_panels_hidden($client)===[], 'new client defaults visible');
    foreach (['crm-client-summary','profile','status','reminders','notes','timeline'] as $key) {
        panels_fixture_expect(is_wp_error(peracrm_client_panels_change($client,'hide',$key)), 'protected panel rejected: ' . $key);
    }
    panels_fixture_expect(!is_wp_error(peracrm_client_panels_change($client,'hide','seller_properties')), 'seller panel dismissible');
    panels_fixture_expect(!is_wp_error(peracrm_client_panels_change($client,'hide','deals')), 'deals dismissible');
    wp_cache_flush();
    panels_fixture_expect(in_array('deals',peracrm_client_panels_hidden($client),true), 'real user metadata survives cache flush');
    panels_fixture_expect(peracrm_client_panels_hidden($other)===[], 'other client unaffected');
    wp_set_current_user($admin->ID);
    panels_fixture_expect(peracrm_client_panels_hidden($client)===[], 'other employee unaffected');
    wp_set_current_user($employee);
    update_post_meta($client, '_peracrm_client_type', 'landlord');
    panels_fixture_expect(in_array('seller_properties',peracrm_client_panels_hidden($client),true), 'type change retains hidden sale preference');
    panels_fixture_expect(is_wp_error(peracrm_client_panels_change($client,'show','seller_properties')), 'ineligible toggle rejected');
    peracrm_party_upsert_status($client, ['lead_pipeline_stage'=>'qualified']);
    panels_fixture_expect(is_wp_error(peracrm_client_panels_change($client,'show','deals')), 'ineligible deal toggle rejected');
    panels_fixture_expect(peracrm_instructions_list($client)===$instructions_before && peracrm_deals_get_by_party($client)===$deals_before, 'dismissal and type/stage changes preserve exact instruction and deal records');
    update_post_meta($client, '_peracrm_client_type', 'seller');
    peracrm_party_upsert_status($client, ['lead_pipeline_stage'=>'offer_made']);
    panels_fixture_expect(!is_wp_error(peracrm_client_panels_change($client,'show','deals')), 'individual restore');
    panels_fixture_expect(peracrm_client_panels_hidden($client)===['seller_properties'], 'other hidden preference retained');
    panels_fixture_expect(!is_wp_error(peracrm_client_panels_change($client,'restore_all')) && peracrm_client_panels_hidden($client)===[], 'restore all on real metadata');
    wp_set_current_user(0);
    panels_fixture_expect(is_wp_error(peracrm_client_panels_change($client,'hide','deals')), 'logged-out mutation rejected');
    wp_set_current_user($employee);
    // The final fixture runs after campaign assertions; retain synthetic data for browser QA.
    file_put_contents('/var/www/html/client-panels-fixture.json', wp_json_encode(['client'=>$client,'other'=>$other,'employee'=>$employee,'target'=>home_url()]));
});
wp_set_current_user($panels_original_user);
panels_fixture_expect(get_current_blog_id()===$panels_original_blog, 'target blog restored');
WP_CLI::success('Real WordPress client panel persistence checks passed.');
