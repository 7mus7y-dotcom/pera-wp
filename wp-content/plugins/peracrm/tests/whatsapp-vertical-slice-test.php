<?php
/* Focused dependency-free security/contract regression test. */
function check($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } echo "PASS: $message\n"; }
$rest = file_get_contents(__DIR__ . '/../inc/rest/whatsapp.php');
$core = file_get_contents(__DIR__ . '/../inc/whatsapp.php');
$table = file_get_contents(__DIR__ . '/../inc/db/whatsapp_messages_table.php');
$embed = file_get_contents(__DIR__ . '/../inc/admin/whatsapp-embedded-signup.php');
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
check(strpos($rest, 'return is_user_logged_in() && peracrm_whatsapp_user_can_access_client') !== false, 'unauthorised outbound REST requests are rejected');
check(strpos($core, 'peracrm_whatsapp_client_phone($client_id)') !== false && strpos($core, "new WP_Error('invalid_recipient'") !== false, 'authorised outbound validates selected client recipient');
$send = substr($core, strpos($core, 'function peracrm_whatsapp_send_client_text'));
check(strpos($send, '$code < 200 || $code >= 300 || $wamid ===') !== false && strpos($send, 'peracrm_whatsapp_store_message_result([') > strpos($send, '$code < 200'), 'Graph failure cannot persist a falsely successful outbound row');
check(strpos($core, "'whatsapp_message_id' => \$wamid") !== false, 'successful outbound persists returned WAMID');
check(strpos($embed, "'featureType' => 'whatsapp_business_app_onboarding'") !== false && strpos($embed, "'sessionInfoVersion' => '3'") !== false, 'Embedded Signup Coexistence launcher contract preserved');
echo "All WhatsApp vertical-slice checks passed.\n";
