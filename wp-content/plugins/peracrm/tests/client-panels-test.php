<?php
/** Standalone WordPress doubles: visibility changes can write only user metadata. */
define('ABSPATH', __DIR__);
class WP_Error {
    public function __construct(private string $code, private string $message) {}
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}
class PanelJson extends Exception { public function __construct(public array $data, public int $status, public bool $success) {} }
function __($v, $domain = '') { return $v; }
function esc_attr($v) { return htmlspecialchars((string) $v, ENT_QUOTES); }
function esc_html($v) { return esc_attr($v); }
function esc_html_e($v, $domain = '') { echo esc_html($v); }
function esc_attr_e($v, $domain = '') { echo esc_attr($v); }
function checked($v) { if ($v) { echo 'checked'; } }
function add_action($hook, $callback) { $GLOBALS['hooks'][$hook] = $callback; }
function is_wp_error($v) { return $v instanceof WP_Error; }
function is_user_logged_in() { return $GLOBALS['user'] > 0; }
function get_current_user_id() { return $GLOBALS['user']; }
function get_current_blog_id() { return $GLOBALS['blog']; }
function peracrm_with_target_blog($fn) { $old=$GLOBALS['blog']; $GLOBALS['blog']=3; try { return $fn(); } finally { $GLOBALS['blog']=$old; } }
function pera_crm_client_view_access_state($id) { return ['allowed' => in_array($GLOBALS['user'], [7,8], true) && in_array($id, [125,126], true)]; }
function peracrm_client_get_profile($id) { return ['client_type' => $GLOBALS['types'][$id] ?? 'seller']; }
function peracrm_party_get($id) { return ['lead_pipeline_stage' => $GLOBALS['stages'][$id] ?? 'offer_made']; }
function get_user_meta($user, $key, $single) { return $GLOBALS['meta'][$user][$key] ?? ''; }
function add_user_meta($user, $key, $value, $unique) {
    if (isset($GLOBALS['meta'][$user][$key]) || !empty($GLOBALS['fail_write'])) { return false; }
    $GLOBALS['meta'][$user][$key]=$value; return 1;
}
function update_user_meta($user, $key, $value, $previous) {
    if (!empty($GLOBALS['fail_write']) || get_user_meta($user,$key,true) !== $previous) { return false; }
    $GLOBALS['meta'][$user][$key]=$value; return true;
}
function wp_unslash($v) { return $v; }
function absint($v) { return abs((int) $v); }
function wp_create_nonce($action) { return 'valid-' . $action; }
function wp_verify_nonce($nonce, $action) { return $nonce === wp_create_nonce($action); }
function wp_send_json_error($data, $status = 200) { throw new PanelJson($data,$status,false); }
function wp_send_json_success($data) { throw new PanelJson($data,200,true); }
$GLOBALS['user']=7; $GLOBALS['blog']=1; $GLOBALS['meta']=[];
require dirname(__DIR__) . '/inc/client-panels.php';
require dirname(__DIR__) . '/inc/stages.php';
$checks=0;
function panel_expect($value, $label) { global $checks; if (!$value) { throw new RuntimeException($label); } $checks++; }
function panel_request($data, $method='POST') {
    $_POST=$data; $_SERVER['REQUEST_METHOD']=$method;
    try { peracrm_client_panels_ajax(); } catch (PanelJson $response) { return $response; }
    throw new RuntimeException('AJAX must terminate');
}
$registry=peracrm_client_panel_registry();
panel_expect(peracrm_client_panels_hidden(125) === [] && $GLOBALS['meta'] === [], 'default reads create no preferences');
foreach ($registry as $key=>$panel) {
    if (!$panel['optional']) {
        panel_expect(is_wp_error(peracrm_client_panels_change(125,'hide',$key)), 'protected key rejected: ' . $key);
        ob_start(); peracrm_client_panel_attributes($key,[$key]); peracrm_client_panel_dismiss($key); $html=ob_get_clean();
        panel_expect($html === '', 'protected panel never receives hidden attribute or dismiss control');
    }
}
foreach (['deals','seller_properties','enquiries','theme_portfolio'] as $key) {
    panel_expect(!is_wp_error(peracrm_client_panels_change(125,'hide',$key)), 'optional panel can hide: ' . $key);
}
panel_expect(in_array('enquiries',peracrm_client_panels_hidden(125),true), 'preferences survive separate reads');
panel_expect(peracrm_client_panels_hidden(126) === [], 'client isolation');
$GLOBALS['user']=8;
panel_expect(peracrm_client_panels_hidden(125) === [], 'employee isolation');
peracrm_client_panels_change(125,'hide','theme_portfolio');
$GLOBALS['user']=7;
panel_expect(!is_wp_error(peracrm_client_panels_change(125,'show','deals')) && !in_array('deals',peracrm_client_panels_hidden(125),true), 'individual restore');
peracrm_client_panels_change(126,'hide','enquiries');
$GLOBALS['types'][125]='landlord';
panel_expect(in_array('seller_properties',peracrm_client_panels_hidden(125),true), 'type changes retain saved preference');
panel_expect(is_wp_error(peracrm_client_panels_change(125,'show','seller_properties')), 'ineligible toggle rejected without deleting preference');
panel_expect(!is_wp_error(peracrm_client_panels_change(125,'hide','landlord_properties')), 'landlord panel can hide');
$GLOBALS['types'][125]='seller';
panel_expect(in_array('seller_properties',peracrm_client_panels_hidden(125),true), 'seller preference returns after switching back');
foreach (array_keys(peracrm_lead_stage_options()) as $stage) {
    $eligible=peracrm_client_panels_eligible(['client_type'=>'seller'],['lead_pipeline_stage'=>$stage]);
    panel_expect(isset($eligible['deals']) === in_array($stage,['offer_made','deal_closed'],true), 'exact canonical deals stage: ' . $stage);
}
foreach (['seller','landlord','investor','','Seller'] as $type) {
    $eligible=peracrm_client_panels_eligible(['client_type'=>$type],[]);
    panel_expect(isset($eligible['seller_properties']) === ($type==='seller'), 'seller eligibility: ' . $type);
    panel_expect(isset($eligible['landlord_properties']) === ($type==='landlord'), 'landlord eligibility: ' . $type);
}
$before=$GLOBALS['meta'];
foreach (['profile','reminders','bogus','DEALS','deals!'] as $key) { panel_expect(is_wp_error(peracrm_client_panels_change(125,'hide',$key)), 'invalid/protected key rejected: ' . $key); }
panel_expect($GLOBALS['meta']===$before,'rejections do not write');
panel_expect(!is_wp_error(peracrm_client_panels_change(125,'restore_all')), 'restore all succeeds');
panel_expect(peracrm_client_panels_hidden(125)===[] && peracrm_client_panels_hidden(126)===['enquiries'],'restore all scoped to current client, including ineligible keys');
$GLOBALS['user']=8;
panel_expect(peracrm_client_panels_hidden(125)===['theme_portfolio'],'restore all does not change another employee');
$GLOBALS['user']=7;
$key=peracrm_client_panels_meta_key(125);
$GLOBALS['meta'][7][$key]=['version'=>1,'hidden'=>['profile','notes','deals','unknown','crm-client-summary']];
panel_expect(peracrm_client_panels_hidden(125)===['deals'],'tampered protected preferences ignored on read');
$GLOBALS['meta'][7][$key]=['version'=>2,'hidden'=>['deals']];
panel_expect(peracrm_client_panels_hidden(125)===[],'unknown version ignored');
$GLOBALS['fail_write']=true;
panel_expect(is_wp_error(peracrm_client_panels_change(125,'hide','deals')),'persistence failure returned');
$GLOBALS['fail_write']=false;
$GLOBALS['user']=9;
panel_expect(peracrm_client_panels_hidden(125)===[] && is_wp_error(peracrm_client_panels_change(125,'hide','deals')),'unassigned employee cannot read/write');
$GLOBALS['user']=7;
panel_expect(is_wp_error(peracrm_client_panels_change(999,'hide','deals')),'inaccessible client rejected');
$request=['client_id'=>125,'nonce'=>wp_create_nonce('peracrm_client_panels_125'),'operation'=>'hide','panel'=>'enquiries'];
foreach (['', 'wrong', wp_create_nonce('peracrm_client_panels_126'), ['invalid']] as $nonce) {
    panel_expect(panel_request(array_merge($request,['nonce'=>$nonce]))->status===403,'missing/invalid/client-mismatched nonce rejected');
}
panel_expect(panel_request($request,'GET')->status===403,'GET rejected');
$GLOBALS['user']=0;
panel_expect(panel_request($request)->status===403,'unauthenticated request rejected');
$GLOBALS['user']=9;
panel_expect(panel_request($request)->status===403,'AJAX authorization enforced');
$GLOBALS['user']=7;
panel_expect(panel_request(array_merge($request,['panel'=>'profile']))->status===400,'AJAX protected key rejected');
panel_expect(panel_request(array_merge($request,['panel'=>'unknown']))->status===400,'AJAX unknown key rejected');
panel_expect(panel_request($request)->success,'valid authorized AJAX succeeds');
panel_expect($GLOBALS['blog']===1,'target blog context restored');
// Render actual Manage panels markup, not a duplicate client registry.
$client_id=125; $profile=['client_type'=>'seller']; $party=['lead_pipeline_stage'=>'offer_made']; $hidden_panels=['enquiries'];
ob_start(); require dirname(__DIR__) . '/inc/views/partials/manage-panels.php'; $html=ob_get_clean();
$dom=new DOMDocument(); @$dom->loadHTML($html); $xpath=new DOMXPath($dom);
panel_expect($xpath->query('//section[@data-crm-manage-panels and @aria-labelledby="crm-manage-panels-title"]')->length===1,'permanently expanded named section');
panel_expect($xpath->query('//details | //summary')->length===0,'no disclosure control');
panel_expect($xpath->query('//h2[@id="crm-manage-panels-title" and @tabindex="-1"]')->length===1,'heading accepts dismissal focus');
foreach ($xpath->query('//input[@data-crm-panel-toggle]') as $input) {
    panel_expect($xpath->query('//label[@for="' . $input->getAttribute('id') . '"]')->length===1,'checkbox has a visible label');
    panel_expect($xpath->query('//span[@id="' . $input->getAttribute('aria-describedby') . '" and @data-crm-panel-state]')->length===1,'checkbox describes visibility state');
}
panel_expect($xpath->query('//input[@type="checkbox" and @data-crm-panel-toggle]')->length===4,'only eligible optional panels have toggles');
panel_expect($xpath->query('//input[@data-crm-panel-toggle="enquiries" and not(@checked)]')->length===1,'hidden checkbox reflects server state');
panel_expect($xpath->query('//input[@data-crm-panel-toggle="profile" or @data-crm-panel-toggle="landlord_properties"]')->length===0,'no protected or ineligible toggles');
ob_start(); peracrm_client_panel_dismiss('deals'); peracrm_client_panel_attributes('deals',['deals']); $button=ob_get_clean();
panel_expect(str_contains($button,'type="button"') && str_contains($button,'aria-label="Hide Deals panel"') && str_contains($button,'aria-hidden="true"'),'dismiss button keyboard and accessible naming semantics');
panel_expect(str_contains($button,'data-crm-optional-panel="deals" hidden'),'initial hidden state rendered server-side');
$fragments=peracrm_client_panels_fragments('<div data-crm-panel-slot="deals"></div><div data-crm-panel-slot="instructions"><article>History</article></div>' . $html);
panel_expect(isset($fragments['manage'],$fragments['deals'],$fragments['instructions']),'conditional refresh extracts all workspace fragments');
$source=file_get_contents(dirname(__DIR__) . '/inc/views/pages/crm-client.php');
panel_expect(strpos($source, "require PERACRM_INC . '/views/partials/manage-panels.php'") > strpos($source, 'peracrm_whatsapp_render_client_conversation'), 'Manage panels after all client content');
// Production module has no writes to post metadata, deals, instructions or activity.
$source=file_get_contents(dirname(__DIR__) . '/inc/client-panels.php');
panel_expect(!preg_match('/(?:delete_post_meta|update_post_meta|wpdb|deals_delete|instruction_mutate|wp_delete_post)\s*\(/', $source),'visibility has no CRM data write/delete path');
echo "OK: $checks client panel checks\n";
