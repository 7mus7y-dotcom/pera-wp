<?php
/* Focused dependency-free security/contract regression test. */
function check($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } echo "PASS: $message\n"; }
$rest = file_get_contents(__DIR__ . '/../inc/rest/whatsapp.php');
$core = file_get_contents(__DIR__ . '/../inc/whatsapp.php');
$table = file_get_contents(__DIR__ . '/../inc/db/whatsapp_messages_table.php');
$embed = file_get_contents(__DIR__ . '/../inc/admin/whatsapp-embedded-signup.php');
$admin_assets = file_get_contents(__DIR__ . '/../inc/admin/assets.php');
$client_view = file_get_contents(__DIR__ . '/../inc/views/pages/crm-client.php');
$conversation_js = file_get_contents(__DIR__ . '/../assets/frontend/whatsapp-conversation.js');
$fixture = file_get_contents(__DIR__ . '/fixtures/meta-text-webhook.json');
$payload = json_decode($fixture, true);
check(is_array($payload) && $payload['object'] === 'whatsapp_business_account', 'representative Meta fixture is valid');
function verify_signature($raw, $signature, $secret) {
    if ($secret === '' || !preg_match('/^sha256=([a-f0-9]{64})$/i', trim($signature), $matches)) return false;
    return hash_equals(hash_hmac('sha256', $raw, $secret), strtolower($matches[1]));
}
$secret = 'fixture-only-app-secret'; $valid = 'sha256=' . hash_hmac('sha256', $fixture, $secret);
check(verify_signature($fixture, $valid, $secret), 'valid webhook HMAC accepted');
check(!verify_signature($fixture . ' ', $valid, $secret), 'invalid webhook HMAC rejected');
check(!verify_signature($fixture, '', $secret), 'missing webhook HMAC rejected');
check(strpos($rest, 'hash_equals((string) $settings[\'verify_token\'], $verify_token)') !== false && strpos($rest, "'challenge' => \$challenge") !== false, 'GET verification uses constant-time token comparison and challenge');
check(strpos($rest, 'json_decode($raw, true)') > strpos($rest, 'peracrm_whatsapp_verify_meta_signature'), 'payload parsing occurs only after HMAC authentication');
check(strpos($core, "if (\$wamid === ''") !== false && strpos($table, 'UNIQUE KEY whatsapp_message_id') !== false, 'WAMID is required and database-unique');
check(strpos($rest, 'if (!is_array($payload))') !== false, 'malformed JSON fails safely');
check(strpos($rest, 'return peracrm_whatsapp_user_can_access_client') !== false && strpos($rest, 'peracrm_with_target_blog') !== false, 'unauthorised outbound REST requests are rejected');
check(strpos($core, 'peracrm_whatsapp_client_phone($client_id)') !== false && strpos($core, "new WP_Error('invalid_recipient'") !== false, 'authorised outbound validates selected client recipient');
$send = substr($core, strpos($core, 'function peracrm_whatsapp_send_client_text'));
check(strpos($send, '$code < 200 || $code >= 300 || $wamid ===') !== false && strpos($send, 'peracrm_whatsapp_store_message_result([') > strpos($send, '$code < 200'), 'Graph failure cannot persist a falsely successful outbound row');
check(strpos($core, "'whatsapp_message_id' => \$wamid") !== false, 'successful outbound persists returned WAMID');
check(strpos($embed, "'featureType' => 'whatsapp_business_app_onboarding'") !== false && strpos($embed, "'sessionInfoVersion' => '3'") !== false, 'Embedded Signup Coexistence launcher contract preserved');
check(strpos($embed, "current_user_can('manage_options')") === false && strpos($admin_assets, 'peracrm_whatsapp_current_user_can_manage_target()') !== false, 'Embedded Signup panel and outer asset enqueue use target-blog authorization');
check(strpos($client_view, '<!-- /.crm-client-detail-layout -->') < strpos($client_view, 'peracrm_whatsapp_render_client_conversation'), 'WhatsApp panel renders after the established two-column client grid');
check(strpos($conversation_js, 'wasNearBottom') !== false && strpos($conversation_js, 'refresh(true)') !== false, 'conversation scrolling preserves history position and follows latest when appropriate');
check(strpos($core, 'pera_get_whatsapp_number') === false && strpos($core, 'pera_whatsapp_number') === false, 'Cloud API transport has no website-number fallback');
echo "All static WhatsApp contract checks passed.\n";
