<?php
/** Synthetic-only instruction checks in the existing disposable WordPress fixture. */
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE') || !PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE) {
    throw new RuntimeException('Disposable fixture required.');
}
function instruction_fixture_expect($condition, $message)
{
    if (!$condition) { WP_CLI::error('Property instructions: ' . $message); }
    WP_CLI::log('PASS instruction: ' . $message);
}
$instruction_original_blog = get_current_blog_id();
$instruction_original_user = get_current_user_id();
peracrm_with_target_blog(static function () {
    global $wpdb;
    $admin = get_user_by('login', 'fixture-admin');
    wp_set_current_user($admin->ID);
    peracrm_instructions_assert_schema();
    instruction_fixture_expect((int) get_option('peracrm_schema_version') >= 41, 'schema 41 installed');
    $client = wp_insert_post(['post_type' => 'crm_client', 'post_status' => 'private', 'post_title' => 'Synthetic instruction owner']);
    $other = wp_insert_post(['post_type' => 'crm_client', 'post_status' => 'private', 'post_title' => 'Synthetic unrelated owner']);
    instruction_fixture_expect($client > 0 && $other > 0, 'clients created');
    $employee = wp_insert_user(['user_login' => 'instruction-employee', 'user_pass' => 'synthetic-fixture-only', 'role' => 'employee']);
    instruction_fixture_expect(!is_wp_error($employee), 'employee created on CRM blog');
    update_post_meta($client, 'assigned_advisor_user_id', $employee);
    update_post_meta($client, 'crm_assigned_advisor', $employee);
    update_post_meta($client, '_peracrm_client_type', 'seller');
    wp_set_current_user($employee);
    $nonce = wp_create_nonce('peracrm_property_instruction_' . $client);
    $public_before = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'property'");
    $create = ['instruction_type' => 'sale', 'property' => ['name' => 'Synthetic building'], 'identifier' => 'Unit 12'];
    $id = peracrm_instruction_mutate($client, $create, $nonce, 'POST');
    instruction_fixture_expect(is_int($id) && $id > 0, 'assigned employee creates sale');
    $rows = peracrm_instructions_list($client);
    instruction_fixture_expect(count($rows) === 1 && $rows[0]['id'] == $id, 'client-scoped retrieval');
    $row = $rows[0];
    $edit = $row;
    $edit['instruction_id'] = $id;
    $edit['property'] = peracrm_json_decode($row['property_details']);
    $edit['details'] = peracrm_json_decode($row['details']);
    $edit['sahibinden_url'] = 'https://www.sahibinden.com/ilan/synthetic-fixture';
    $edit['pera_url'] = 'https://example.test/property/synthetic-fixture';
    $edit['status'] = 'marketing';
    instruction_fixture_expect(peracrm_instruction_mutate($client, $edit, $nonce, 'POST') === $id, 'edit physical details and optional URLs');
    instruction_fixture_expect(is_wp_error(peracrm_instruction_mutate($client, $edit, $nonce, 'POST')), 'real revision rejects stale form');
    $rental = peracrm_instruction_mutate($client, ['instruction_type' => 'rental', 'property_id' => $row['property_id']], $nonce, 'POST');
    instruction_fixture_expect(is_int($rental) && $rental !== $id, 'rental reuses sale asset');
    $assets = peracrm_table('crm_instruction_properties');
    instruction_fixture_expect((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$assets} WHERE client_id = %d", $client)) === 1, 'one physical asset for both instructions');
    update_post_meta($client, '_peracrm_client_type', 'landlord');
    update_post_meta($client, '_peracrm_client_type', 'investor');
    instruction_fixture_expect(count(peracrm_instructions_list($client)) === 2, 'client-type changes preserve instructions');
    instruction_fixture_expect(is_wp_error(peracrm_instruction_mutate($client, $create, '', 'POST')), 'missing real nonce rejected');
    instruction_fixture_expect(is_wp_error(peracrm_instruction_mutate($other, $create, wp_create_nonce('peracrm_property_instruction_' . $other), 'POST')), 'employee cannot mutate unrelated client');
    instruction_fixture_expect(peracrm_instructions_list($other) === [], 'employee cannot read unrelated client');
    foreach (peracrm_instructions_list($client) as $saved) {
        if ((int) $saved['id'] !== $id) { continue; }
        $edit = $saved;
        $edit['instruction_id'] = $id;
        $edit['property'] = peracrm_json_decode($saved['property_details']);
        $edit['details'] = peracrm_json_decode($saved['details']);
        $edit['status'] = 'archived';
        instruction_fixture_expect(is_wp_error(peracrm_instruction_mutate($client, $edit, $nonce, 'POST')), 'archive requires reason');
        $edit['transition_reason'] = 'Synthetic owner withdrew instruction';
        instruction_fixture_expect(peracrm_instruction_mutate($client, $edit, $nonce, 'POST') === $id, 'archive persists');
    }
    $activity = peracrm_table('crm_activity');
    instruction_fixture_expect((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$activity} WHERE client_id = %d AND event_type = %s", $client, 'property_instruction_saved')) > 0, 'real CRM activity recorded');
    $reminder = peracrm_reminder_add($client, $employee, '2026-12-01 10:00:00', '[Property Unit 12 / instruction #' . $rental . '] Review tenancy');
    instruction_fixture_expect($reminder > 0, 'existing reminder storage accepts property reference');
    instruction_fixture_expect((int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'property'") === $public_before, 'zero public property creation');
    // Leave existing campaign fixtures with their original synthetic population.
    foreach (['crm_property_instructions', 'crm_instruction_properties', 'crm_activity', 'crm_reminders'] as $suffix) {
        $wpdb->delete(peracrm_table($suffix), ['client_id' => $client]);
    }
    wp_set_current_user($admin->ID);
    wp_delete_post($client, true);
    wp_delete_post($other, true);
    if (is_multisite()) {
        remove_user_from_blog($employee, get_current_blog_id());
    } else {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($employee);
    }
});
wp_set_current_user($instruction_original_user);
instruction_fixture_expect(get_current_blog_id() === $instruction_original_blog, 'target blog restored');
WP_CLI::success('Real WordPress/MySQL property instruction checks passed.');
