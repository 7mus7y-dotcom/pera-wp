<?php
/** Standalone behavioral suite: php tests/property-instructions-test.php */
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
class WP_Error {
    public $code; public $message;
    function __construct($code, $message) { $this->code=$code; $this->message=$message; }
    function get_error_message() { return $this->message; }
}
function is_wp_error($v) { return $v instanceof WP_Error; }
function add_action() {}
function absint($v) { return abs((int) $v); }
function sanitize_text_field($v) { return trim(strip_tags($v)); }
function sanitize_textarea_field($v) { return trim(strip_tags($v)); }
function esc_url_raw($v) { return $v; }
function wp_verify_nonce($v, $action) { return $v === 'valid-' . $action; }
function peracrm_with_target_blog($f) { $GLOBALS['blog_depth']++; try { return $f(); } finally { $GLOBALS['blog_depth']--; } }
function peracrm_table($v) { if (!$GLOBALS['blog_depth']) { throw new Exception('Missing target blog context'); } return 'wp_2_' . $v; }
function peracrm_get_actor_user_id() { return $GLOBALS['actor']; }
function user_can($id, $cap) { return $id === 1 || ($id === 7 && $cap === 'edit_crm_clients'); }
function peracrm_user_can_access_client($id, $client) { return in_array($client, [101,102], true) && ($id === 1 || ($id === 7 && $client === 101)); }
function peracrm_user_is_valid_advisor($id) { return in_array($id, [1,7], true); }
function peracrm_resolve_assignee_user_id($v, $fallback) { return $fallback ?: $GLOBALS['actor']; }
function peracrm_client_get_assigned_advisor_id($client) { return $client === 101 ? 7 : 1; }
function peracrm_now_mysql() { return '2026-10-28 12:00:00'; }
function peracrm_json_encode($v) { return json_encode($v); }
function peracrm_json_decode($v) { return json_decode($v, true) ?: []; }
function get_post_type($id) { return $id === 300 ? 'property' : 'crm_client'; }
function get_post_status($id) { return $id === 300 ? 'publish' : 'private'; }
function peracrm_log_event($client, $event, $data) { $GLOBALS['events'][] = [$client,$event,$data]; }
function wp_insert_post() { throw new Exception('Public post creation prohibited'); }
class InstructionDB {
    public $tables = []; public $insert_id = 0; public $snapshot; public $fail = false;
    function prepare($sql, ...$args) { return [$sql, $args]; }
    function query($sql) {
        if ($sql === 'START TRANSACTION') { $this->snapshot = $this->tables; }
        if ($sql === 'ROLLBACK') { $this->tables = $this->snapshot; }
        return 1;
    }
    function insert($table, $data) {
        if ($this->fail && str_contains($table, 'property_instructions')) { return false; }
        $this->insert_id = count($this->tables[$table] ?? []) + 1;
        $this->tables[$table][$this->insert_id] = $data + ['id' => $this->insert_id, 'revision' => 1];
        return 1;
    }
    function update($table, $data, $where) {
        foreach ($this->tables[$table] ?? [] as $id => $row) {
            if (array_intersect_assoc($row, $where) == $where) { $this->tables[$table][$id] = array_merge($row, $data); return 1; }
        }
        return false;
    }
    function get_results($query, $format) {
        [$sql, $args] = $query; $out=[];
        foreach ($this->tables['wp_2_crm_property_instructions'] ?? [] as $row) {
            $p=$this->tables['wp_2_crm_instruction_properties'][$row['property_id']] ?? null;
            if ($row['client_id'] === $args[0] && $p && $p['client_id'] === $row['client_id']) {
                $out[]=$row+['identifier'=>$p['identifier'],'property_details'=>$p['details'],'property_revision'=>$p['revision']];
            }
        }
        return $out;
    }
    function get_row($query, $format) {
        [$sql,$args]=$query;
        $table=str_contains($sql, 'crm_instruction_properties') ? 'wp_2_crm_instruction_properties' : 'wp_2_crm_property_instructions';
        $row=$this->tables[$table][$args[0]] ?? null;
        return $row && $row['client_id'] === $args[1] ? $row : null;
    }
}
$GLOBALS['actor']=7; $GLOBALS['blog_depth']=0; $GLOBALS['events']=[]; $wpdb=new InstructionDB();
require dirname(__DIR__) . '/inc/property-instructions.php';
$checks=0;
function expect($condition, $message) { global $checks; $checks++; if (!$condition) { throw new Exception('FAIL: ' . $message); } }
function save_instruction($raw, $client=101, $nonce=null, $method='POST') {
    return peracrm_instruction_mutate($client,$raw,$nonce ?? 'valid-peracrm_property_instruction_' . $client,$method);
}
function raw_row($id) {
    foreach (peracrm_instructions_list(101) as $row) {
        if ($row['id'] === $id) { return $row + ['instruction_id'=>$id,'property'=>peracrm_json_decode($row['property_details'])] + []; }
    }
    throw new Exception('Missing fixture');
}
function edit_row($id) { $r=raw_row($id); $r['details']=peracrm_json_decode($r['details']); return $r; }
$sale=['instruction_type'=>'sale', 'property'=>['name'=>'Pera building'], 'identifier'=>'12A'];
$id=save_instruction($sale);
expect($id===1,'create seller instruction');
$rent=save_instruction(['instruction_type'=>'rental','identifier'=>'Unit B']);
expect($rent===2,'create landlord rental with optional fields absent');
expect(count(peracrm_instructions_list(101))===2,'multiple instructions per client');
expect(count(peracrm_instructions_list(102))===0,'client specific retrieval');
$edit=edit_row(1); $edit['property']['district']='Beyoğlu'; $edit['sahibinden_url']='https://www.sahibinden.com/ilan/123'; $edit['pera_url']='http://peraproperty.com/property/123';
expect(save_instruction($edit)===1,'edit and both external URLs');
$row=raw_row(1); expect(peracrm_json_decode($row['property_details'])['district']==='Beyoğlu','edit persisted');
expect($row['sahibinden_url']===$edit['sahibinden_url'] && $row['pera_url']===$edit['pera_url'],'URLs persisted');
foreach (['javascript:alert(1)','ftp://example.com','//example.com','https://','https://example.com/ bad', ['https://example.com']] as $url) {
    $edit=edit_row(1); $edit['pera_url']=$url; expect(is_wp_error(save_instruction($edit)),'invalid URL rejected');
}
$edit=edit_row(1); $edit['pera_url']=''; $edit['sahibinden_url']=''; expect(save_instruction($edit)===1,'URLs may be cleared');
expect(is_wp_error(save_instruction($sale,101,'wrong')),'forged nonce rejected');
expect(is_wp_error(save_instruction($sale,101,null,'GET')),'GET mutation rejected');
expect(is_wp_error(save_instruction($sale,102)),'employee cannot mutate another client');
expect(peracrm_instructions_list(102)===[],'employee cannot read another client');
$GLOBALS['actor']=1;
$foreign=save_instruction(['instruction_type'=>'sale','identifier'=>'Other owner'],102);
$GLOBALS['actor']=7;
expect(is_wp_error(save_instruction(edit_row(1),102)),'forged client relationship rejected');
expect(is_wp_error(save_instruction($sale+['instruction_id'=>$foreign])),'forged instruction ID rejected');
$reuse=save_instruction(['instruction_type'=>'rental','property_id'=>1]);
expect(is_int($reuse),'reuse property without entering duplicate physical details');
expect(count($wpdb->tables['wp_2_crm_instruction_properties'])===3,'reuse does not create duplicate asset');
expect(raw_row($reuse)['property_id']===1,'sale and rent share asset');
$stale=edit_row($reuse); $edit=edit_row(1); $edit['property']['name']='Updated'; expect(save_instruction($edit)===1,'shared asset update');
expect(is_wp_error(save_instruction($stale)),'shared property stale edit rejected');
$edit=edit_row(1); expect(save_instruction($edit)===1,'unchanged edit');
$edit=edit_row(1); $edit['status']='bogus'; expect(is_wp_error(save_instruction($edit)),'invalid lifecycle state rejected');
foreach (['sale'=>1,'rental'=>2] as $type=>$instruction_id) {
    foreach (array_keys(peracrm_instruction_statuses($type)) as $status) {
        $edit=edit_row($instruction_id); $edit['status']=$status; $edit['transition_reason']='Advisor documented transition';
        expect(save_instruction($edit)===$instruction_id,$type . ' lifecycle ' . $status);
    }
    $edit=edit_row($instruction_id); $edit['status']='draft'; expect(is_wp_error(save_instruction($edit)),'reopen requires reason');
    $edit['transition_reason']='Owner renewed instruction'; expect(save_instruction($edit)===$instruction_id,'reopen with reason');
    $edit=edit_row($instruction_id); $edit['status']='marketing'; expect(save_instruction($edit)===$instruction_id,'forward transition');
    $edit=edit_row($instruction_id); $edit['status']='draft'; expect(is_wp_error(save_instruction($edit)),'backward requires reason');
    $edit['transition_reason']='Owner needs preparation'; expect(save_instruction($edit)===$instruction_id,'backward with reason');
}
$edit=edit_row(1); $edit['public_property_id']=300; expect(save_instruction($edit)===1,'link existing public listing');
$edit=edit_row(1); $edit['public_property_id']=999; expect(is_wp_error(save_instruction($edit)),'invalid public link rejected');
$edit=edit_row(1); $edit['advisor_user_id']=8; expect(is_wp_error(save_instruction($edit)),'out of scope advisor rejected');
$edit=edit_row(1); $edit['details']['asking_price']='-2'; expect(is_wp_error(save_instruction($edit)),'negative price rejected');
$edit=edit_row(2); $edit['details']['contract_start']='2026-02-30'; expect(is_wp_error(save_instruction($edit)),'invalid tenancy date rejected');
$edit=edit_row(1); $edit['property']['address']=str_repeat('İ', 5001); $edit['notes']=str_repeat('ğ', 5001);
expect(save_instruction($edit)===1,'long Unicode fields saved');
expect(peracrm_json_decode(raw_row(1)['property_details'])['address']===str_repeat('İ',5000),'Unicode boundaries preserved in structured JSON');
expect(raw_row(1)['notes']===str_repeat('ğ',5000),'Unicode notes bounded without split bytes');
$before=count($wpdb->tables['wp_2_crm_instruction_properties']); $wpdb->fail=true;
expect(is_wp_error(save_instruction($sale)),'write failure reported');
expect(count($wpdb->tables['wp_2_crm_instruction_properties'])===$before,'write failure rolls back asset'); $wpdb->fail=false;
// Retrieval never depends on client profile/type or writes to the lead pipeline.
$GLOBALS['client_type']='investor'; expect(count(peracrm_instructions_list(101))===3,'instructions persist after client type changes');
expect($GLOBALS['blog_depth']===0,'blog context restored');
expect(count($GLOBALS['events'])>20,'activity logged');
expect(end($GLOBALS['events'])[2]['actor_user_id']===7,'activity actor attribution');
echo "OK: {$checks} instruction behavioral checks\n";
