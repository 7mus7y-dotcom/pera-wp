<?php
require __DIR__ . '/property-instructions-test.php';
function __($v) { return $v; }
function esc_html($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function esc_attr($v) { return esc_html($v); }
function esc_textarea($v) { return esc_html($v); }
function esc_url($v) { return esc_html($v); }
function esc_html_e($v) { echo esc_html($v); }
function selected($a,$b) { if ((string) $a === (string) $b) { echo 'selected'; } }
function home_url($v) { return 'https://crm.example.com' . ($GLOBALS['blog_depth'] ? '/target-blog' : '') . $v; }
function wp_nonce_field($action, $name='_wpnonce') { echo '<input type="hidden" name="' . esc_attr($name) . '" value="valid-' . esc_attr($action) . '" />'; }
function get_userdata($id) { return (object) ['ID'=>$id,'display_name'=>'Advisor ' . $id]; }
function peracrm_get_staff_users() { return [get_userdata(7)]; }
function get_permalink($id) { return 'https://example.com/property/' . $id; }
require dirname(__DIR__) . '/inc/client-panels.php';
function render_instructions($type, $hidden_panels = []) {
    $instruction_post_url=home_url('/wp-admin/admin-post.php');
    $instruction_client_url=home_url('/crm/client/101/');
    return peracrm_with_target_blog(static function () use ($type, $instruction_post_url, $instruction_client_url, $hidden_panels) {
        $client_id=101; $client_type_value=$type;
        ob_start(); require dirname(__DIR__) . '/inc/views/partials/property-instructions.php'; return ob_get_clean();
    });
}
$edit=edit_row(1); $edit['property']['name']='<img src=x onerror=alert(1)>'; $edit['sahibinden_url']='https://example.com/a'; $edit['pera_url']='https://example.com/b';
expect(save_instruction($edit)===1,'render fixture saved');
$seller=render_instructions('seller');
expect(str_contains($seller,'Properties for sale'),'seller panel title');
expect(str_contains($seller,'+ Add property'),'add property stays after first save');
expect(str_contains($seller,'1. Basic details') && str_contains($seller,'2. Additional details'),'two stages rendered');
expect(!str_contains($seller,'<img src=x'),'untrusted property names not executable');
expect(str_contains($seller,'target="_blank" rel="noopener noreferrer"'),'external link security');
expect(str_contains($seller,'View Sahibinden') && str_contains($seller,'View Pera website'),'listing actions rendered');
expect(str_contains($seller,'View linked website property'),'existing public listing link rendered');
expect(!str_contains($seller,'peracrm_add_reminder') && !str_contains($seller,'Create reminder'),'redundant property-card reminder action removed');
expect(str_contains($seller,'crm-instruction-card__controls') && str_contains($seller,'crm-instruction-delete-form'),'edit and delete controls retained in compact card');
expect(str_contains($seller,'name="client_id" value="101"'),'property forms use current client');
expect(!str_contains($seller, '/target-blog/'), 'property forms retain originating blog URLs');
$hidden_sale = render_instructions('seller', ['seller_properties']);
expect(str_contains($hidden_sale, 'data-crm-optional-panel="seller_properties" hidden'), 'sale hidden at server render');
expect(str_contains($hidden_sale, '+ Add property') && str_contains($hidden_sale, 'name="instruction_id"'), 'dismissal retains instruction forms inside hidden panel');
$landlord=render_instructions('landlord', ['seller_properties']);
expect(str_contains($landlord, 'data-crm-optional-panel="landlord_properties"') && !str_contains($landlord, 'data-crm-optional-panel="landlord_properties" hidden'), 'sale preference does not hide rent panel');
expect(str_contains($landlord,'Properties for rent') && str_contains($landlord,'Tenant name / contact reference'),'landlord panel and operational fields');
$history=render_instructions('investor', ['seller_properties', 'landlord_properties']);
expect(!str_contains($history, 'data-crm-optional-panel') && !str_contains($history, 'data-crm-panel-hide'), 'historical recovery is never dismissible');
expect(str_contains($history,'Property instruction history') && str_contains($history,'Historical / other instruction type'),'history accessible for changed client type');
expect(!str_contains($history,'+ Add property'),'history does not change unrelated client-type behavior');
$edit=edit_row(1); $edit['sahibinden_url']=''; $edit['pera_url']=''; expect(save_instruction($edit)===1,'clear render URLs');
$empty=render_instructions('seller');
expect(!str_contains($empty,'View Sahibinden') && !str_contains($empty,'View Pera website'),'empty external actions hidden');
$GLOBALS['actor']=8;
expect(render_instructions('seller')==='','unauthorized render contains no forms or records');
echo "OK: property instruction rendering and compact card actions\n";

function wp_strip_all_tags($v) { return strip_tags($v); }
function wp_trim_words($v, $count, $suffix) { return $v; }
require dirname(__DIR__) . '/inc/admin/metaboxes/timeline.php';
$event=['created_at'=>'2026-10-28 12:00:00','event_type'=>'property_instruction_saved', 'event_payload'=>json_encode([
    'instruction_id'=>1,'instruction_property_id'=>5,'property_reference'=>'Unit A','previous_status'=>'archived','status'=>'draft','reason'=>'Owner renewed instruction','actor_user_id'=>7,
])];
$normalized=peracrm_timeline_normalize_activity($event,[7=>'Advisor 7']);
expect($normalized['title']==='Property instruction saved','instruction activity title');
expect(str_contains($normalized['detail'],'Archived → Draft') && str_contains($normalized['detail'],'Owner renewed instruction'),'lifecycle and reason visible in activity');
expect($normalized['meta']['by']==='Advisor 7','timeline preserves actor');
echo "OK: instruction timeline formatting without public property lookup\n";
