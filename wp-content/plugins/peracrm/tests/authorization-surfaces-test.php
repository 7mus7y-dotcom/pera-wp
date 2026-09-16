<?php

function surface_expect($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

$root = dirname(__DIR__);
$rest = file_get_contents($root . '/inc/rest.php');
$cpt = file_get_contents($root . '/inc/cpt.php');
$routing = file_get_contents($root . '/inc/frontend/routing.php');
$search = file_get_contents($root . '/inc/services/header_search_service.php');
$whatsapp = file_get_contents($root . '/inc/whatsapp.php');
$admin = file_get_contents($root . '/inc/admin/actions.php');
$roles = file_get_contents($root . '/inc/roles.php');

surface_expect(substr_count($rest, 'peracrm_get_client_access_scope(get_current_user_id())') >= 3, 'all custom REST collections resolve canonical scope');
surface_expect(strpos($rest, "party_id IN") !== false, 'deal REST collection is scoped through party_id');
surface_expect(strpos($rest, "if (\$scope['type'] === 'empty')") !== false, 'REST empty scope returns no records');
surface_expect(strpos($cpt, "'show_in_rest' => false") !== false, 'generic wp/v2 CRM client exposure is disabled');

$auth_position = strpos($routing, 'peracrm_user_can_access_client(get_current_user_id(), $client_id)');
$title_position = strpos($routing, 'get_the_title($client_id)');
$meta_position = strpos($routing, "get_post_meta(\$client_id, '_peracrm_client_type'");
surface_expect($auth_position !== false && $auth_position < $title_position && $auth_position < $meta_position, 'document title authorizes before client title and type reads');

surface_expect(strpos($search, 'peracrm_get_client_access_scope') !== false, 'header search uses canonical scope');
surface_expect(strpos($whatsapp, 'peracrm_user_can_access_client') !== false, 'WhatsApp client access uses canonical policy');
surface_expect(strpos($admin, "\$query->set('post__in'") !== false, 'WordPress admin client collection receives a server-side ID boundary');
surface_expect(strpos($roles, "get_users(['role' => 'advisor'") !== false && strpos($roles, "remove_role('advisor')") !== false, 'legacy advisor role removal is conditional on user inventory');

echo "PeraCRM authorization surface checks passed\n";
