<?php
/** Executable tests of the real WhatsApp functions with small WordPress boundary fakes. */
define('ABSPATH', __DIR__ . '/tmp-wordpress/');
if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');
define('PERACRM_WHATSAPP_ACCESS_TOKEN', 'environment-access-token');
define('PERACRM_WHATSAPP_VERIFY_TOKEN', 'environment-verify-token');
define('PERACRM_WHATSAPP_APP_SECRET', 'environment-app-secret');
define('PERACRM_URL', 'https://example.test/wp-content/plugins/peracrm');

function assert_same($expected, $actual, $label) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$label}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}
class WP_Error {
    private $code; private $message; private $data;
    public function __construct($code, $message, $data = []) { $this->code=$code; $this->message=$message; $this->data=$data; }
    public function get_error_code(){ return $this->code; } public function get_error_message(){ return $this->message; }
    public function get_error_data(){ return $this->data; }
}
function is_wp_error($value){ return $value instanceof WP_Error; }
class WP_REST_Response {
    private $data; private $status;
    public function __construct($data=null,$status=200){$this->data=$data;$this->status=$status;}
    public function get_data(){return $this->data;} public function get_status(){return $this->status;}
}
class WP_HTTP_Response extends WP_REST_Response {}
class WP_REST_Server { const READABLE='GET'; const CREATABLE='POST'; const EDITABLE='PUT'; }
class WP_REST_Request implements ArrayAccess {
    private $method; private $route; private $params=[]; private $headers=[]; private $body='';
    public function __construct($method='GET',$route=''){ $this->method=$method; $this->route=$route; }
    public function set_body($body){$this->body=$body;} public function get_body(){return $this->body;}
    public function set_header($key,$value){$this->headers[strtolower($key)]=$value;} public function get_header($key){return $this->headers[strtolower($key)]??'';}
    public function set_param($key,$value){$this->params[$key]=$value;} public function get_param($key){return $this->params[$key]??null;}
    public function get_method(){return $this->method;} public function get_route(){return $this->route;}
    public function offsetExists($o):bool{return isset($this->params[$o]);} public function offsetGet($o):mixed{return $this->params[$o]??null;}
    public function offsetSet($o,$v):void{$this->params[$o]=$v;} public function offsetUnset($o):void{unset($this->params[$o]);}
}
class FakeWpdb {
    public $rows=[]; public $insert_id=0; public $fail_next_insert=false; public $simulate_concurrent_insert=false; public $simulate_status_race=false; public $status_race_rechecks=0; public $options='wp_options';
    public function prepare($query,...$args){ if(count($args)===1 && is_array($args[0]))$args=$args[0]; foreach($args as $arg){$replacement=is_int($arg)?(string)$arg:"'".addslashes((string)$arg)."'";$query=preg_replace('/%[ds]/',$replacement,$query,1);} return $query; }
    public function get_var($query){
        if (preg_match("/whatsapp_message_id = '([^']+)'/",$query,$m)) foreach($this->rows as $row) if($row['whatsapp_message_id']===$m[1]) return $row['id'];
        if(strpos($query,'COUNT(*)')!==false) return count($this->rows); return null;
    }
    public function insert($table,$data,$formats=[]){
        if (is_callable($GLOBALS['before_message_insert']??null)) { $callback=$GLOBALS['before_message_insert'];$GLOBALS['before_message_insert']=null;$callback($data); }
        if ($this->fail_next_insert) { $this->fail_next_insert=false; return false; }
        if ($this->simulate_concurrent_insert) { $this->simulate_concurrent_insert=false;$data['id']=++$this->insert_id;$this->rows[]=$data;return false; }
        foreach($this->rows as $row) if(($data['whatsapp_message_id']??'')!=='' && $row['whatsapp_message_id']===$data['whatsapp_message_id']) return false;
        $data['id']=++$this->insert_id; $this->rows[]=$data; return 1;
    }
    public function update($table,$data,$where,$formats=[],$where_formats=[]){
        if($table===$this->options){$name=$where['option_name']??'';$current=$GLOBALS['options'][$GLOBALS['current_blog_id']][$name]??null;if(maybe_serialize($current)!==($where['option_value']??null))return 0;$GLOBALS['options'][$GLOBALS['current_blog_id']][$name]=unserialize($data['option_value']);return 1;}
        foreach($this->rows as &$row){
            $match=true;foreach($where as $k=>$v)if(($row[$k]??null)!==$v)$match=false;
            if($match){
                if($this->simulate_status_race && ($where['message_status']??'')==='sent' && ($data['message_status']??'')==='read'){
                    $this->simulate_status_race=false;$this->status_race_rechecks++;$row['message_status']='delivered';$row['status_timestamp']='2023-11-14 22:15:00';return 0;
                }
                $row=array_merge($row,$data);return 1;
            }
        }
        return 0;
    }
    public function get_row($query){
        if(preg_match("/whatsapp_message_id = '([^']+)'/",$query,$m)) foreach($this->rows as $row) if(($row['whatsapp_message_id']??'')===$m[1]) return $row;
        return null;
    }
    public function get_results($query){
        $rows=$this->rows;
        if(preg_match('/WHERE client_id = (\d+)/',$query,$m)) $rows=array_values(array_filter($rows,function($row)use($m){return (int)($row['client_id']??0)===(int)$m[1];}));
        if(strpos($query,'(meta_timestamp IS NOT NULL OR created_at_utc IS NOT NULL)')!==false) $rows=array_values(array_filter($rows,function($row){return !empty($row['meta_timestamp'])||!empty($row['created_at_utc']);}));
        if(strpos($query,'meta_timestamp IS NULL AND created_at_utc IS NULL')!==false) $rows=array_values(array_filter($rows,function($row){return empty($row['meta_timestamp'])&&empty($row['created_at_utc']);}));
        usort($rows,function($a,$b){$at=$a['meta_timestamp']??$a['created_at_utc']??$a['created_at']??'';$bt=$b['meta_timestamp']??$b['created_at_utc']??$b['created_at']??'';$time=strcmp($bt,$at);return $time!==0?$time:(((int)$b['id'])<=>((int)$a['id']));});
        $limit=count($rows);$offset=0;if(preg_match('/LIMIT (\d+)(?: OFFSET (\d+))?/',$query,$m)){ $limit=(int)$m[1];$offset=isset($m[2])?(int)$m[2]:0; }
        return array_slice($rows,$offset,$limit);
    } public function delete($table,$where,$formats=[]){if($table==='wp_peracrm_party'){$id=(int)($where['party_id']??0);if(!isset($GLOBALS['party_rows'][$id]))return 0;unset($GLOBALS['party_rows'][$id]);return 1;}if($table!==$this->options)return 0;$name=$where['option_name']??'';$current=$GLOBALS['options'][$GLOBALS['current_blog_id']][$name]??null;if(maybe_serialize($current)!==($where['option_value']??null))return 0;unset($GLOBALS['options'][$GLOBALS['current_blog_id']][$name]);return 1;} public function query(){return 0;}
}
$GLOBALS['wpdb']=new FakeWpdb();
$GLOBALS['settings_by_blog']=[
    1=>['enabled'=>0,'test_mode'=>0,'phone_number_id'=>'SOURCE_PHONE_ID','waba_id'=>'SOURCE_WABA','access_token'=>'source-token','verify_token'=>'source-verify','app_secret'=>'source-secret','graph_api_version'=>'v21.0'],
    2=>['enabled'=>1,'test_mode'=>1,'phone_number_id'=>'TEST_PHONE_ID','waba_id'=>'TEST_WABA','access_token'=>'stored-access-token','verify_token'=>'stored-verify-token','app_secret'=>'stored-app-secret','graph_api_version'=>'v22.0'],
];
$GLOBALS['saved_option']=null;$GLOBALS['current_blog_id']=1;$GLOBALS['target_blog_id']=2;$GLOBALS['target_switches']=0;$GLOBALS['assigned_advisor']=0;
$GLOBALS['logged_in']=true;$GLOBALS['caps_by_blog']=[1=>['manage_options'=>true],2=>['manage_options'=>false]];
$GLOBALS['post_meta_by_blog']=[1=>[123=>['_peracrm_phone'=>'+19999999999']],2=>[123=>['_peracrm_phone'=>'+15551112222']]];
$GLOBALS['titles_by_blog']=[1=>[123=>'Source-blog collision'],2=>[123=>'Target CRM Client']];$GLOBALS['enqueued']=[];$GLOBALS['localized']=[];
$GLOBALS['http_code']=200; $GLOBALS['http_body']='{"messages":[{"id":"wamid.OUTBOUND_1"}]}'; $GLOBALS['captured_http']=null; $GLOBALS['created_clients']=0;$GLOBALS['options']=[];$GLOBALS['events']=[];$GLOBALS['next_post_id']=999;$GLOBALS['party_rows']=[];$GLOBALS['deleted_clients']=[];$GLOBALS['before_message_insert']=null;$GLOBALS['fail_next_client_insert']=false;$GLOBALS['fail_next_party_upsert']=false;$GLOBALS['rollback_had_lock']=false;
function get_option($key,$default=[]){if($key==='peracrm_whatsapp_settings')return $GLOBALS['settings_by_blog'][$GLOBALS['current_blog_id']]??$default;return $GLOBALS['options'][$GLOBALS['current_blog_id']][$key]??$default;} function update_option($key,$value){if($key==='peracrm_whatsapp_settings'){$GLOBALS['saved_option']=$value;$GLOBALS['settings_by_blog'][$GLOBALS['current_blog_id']]=$value;}else{$GLOBALS['options'][$GLOBALS['current_blog_id']][$key]=$value;}return true;}
function add_option($key,$value){if(isset($GLOBALS['options'][$GLOBALS['current_blog_id']][$key]))return false;$GLOBALS['options'][$GLOBALS['current_blog_id']][$key]=$value;return true;} function delete_option($key){unset($GLOBALS['options'][$GLOBALS['current_blog_id']][$key]);return true;}
function wp_parse_args($a,$b=[]){return array_merge($b,$a);} function sanitize_text_field($v){return trim(strip_tags((string)$v));} function sanitize_textarea_field($v){return trim(strip_tags((string)$v));}
function maybe_serialize($value){return is_array($value)||is_object($value)?serialize($value):$value;}
function sanitize_key($v){return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v));} function absint($v){return abs((int)$v);} function esc_url_raw($v){return (string)$v;}
function wp_json_encode($v){return json_encode($v);} function peracrm_json_encode($v){return json_encode($v);} function peracrm_now_mysql(){return '2026-09-15 12:00:00';} function current_time(){return '2026-09-15 12:00:00';} function get_gmt_from_date($date){return gmdate('Y-m-d H:i:s',strtotime($date)-((int)($GLOBALS['site_utc_offset_hours']??0)*3600));}
function peracrm_table($name=''){return $name==='peracrm_party'?'wp_peracrm_party':'wp_peracrm_whatsapp_messages';} function get_posts($args=[]){foreach(($GLOBALS['post_meta_by_blog'][$GLOBALS['current_blog_id']]??[]) as $id=>$meta)foreach((array)($args['meta_query']??[]) as $query)if(in_array($meta[$query['key']]??null,(array)$query['value'],true))return [$id];return [];} function get_post_type($id){return isset($GLOBALS['post_meta_by_blog'][$GLOBALS['current_blog_id']][$id])?'crm_client':false;}
function get_the_title($id){return $GLOBALS['titles_by_blog'][$GLOBALS['current_blog_id']][$id]??'';} function get_edit_post_link($id,$context='display'){return 'https://blog-'.$GLOBALS['current_blog_id'].'.test/wp-admin/post.php?post='.$id.'&action=edit';}
function get_post_meta($id,$key){return $GLOBALS['post_meta_by_blog'][$GLOBALS['current_blog_id']][$id][$key]??'';} function get_current_user_id(){return 7;} function is_user_logged_in(){return $GLOBALS['logged_in'];}
function user_can($id,$cap){return !empty($GLOBALS['caps_by_blog'][$GLOBALS['current_blog_id']][$cap]);} function current_user_can($cap){return user_can(7,$cap);} function peracrm_client_get_assigned_advisor_id(){return $GLOBALS['current_blog_id']===$GLOBALS['target_blog_id']?$GLOBALS['assigned_advisor']:0;}
function peracrm_with_target_blog($cb){$before=$GLOBALS['current_blog_id'];if($before!==$GLOBALS['target_blog_id']){$GLOBALS['current_blog_id']=$GLOBALS['target_blog_id'];$GLOBALS['target_switches']++;}try{return $cb();}finally{$GLOBALS['current_blog_id']=$before;}} function peracrm_log_event($id,$type,$data=[]){$GLOBALS['events'][]=[$id,$type,$data];return true;} function wp_insert_post(){if($GLOBALS['fail_next_client_insert']){$GLOBALS['fail_next_client_insert']=false;return new WP_Error('insert_failed','failed');}$GLOBALS['created_clients']++;$id=$GLOBALS['next_post_id']++;$GLOBALS['post_meta_by_blog'][$GLOBALS['current_blog_id']][$id]=[];return $id; } function update_post_meta($id,$key,$value){$GLOBALS['post_meta_by_blog'][$GLOBALS['current_blog_id']][$id][$key]=$value;return true;} function get_users(){return [1];} function wp_generate_uuid4(){return uniqid('uuid',true);}
function peracrm_party_upsert_status($id,$data){if($GLOBALS['fail_next_party_upsert']){$GLOBALS['fail_next_party_upsert']=false;return false;}$GLOBALS['party_rows'][(int)$id]=$data;return true;} function peracrm_party_table_exists(){return true;} function wp_delete_post($id,$force=false){foreach(($GLOBALS['options'][$GLOBALS['current_blog_id']]??[]) as $key=>$value)if(strpos($key,'peracrm_wa_client_lock_')===0)$GLOBALS['rollback_had_lock']=true;unset($GLOBALS['post_meta_by_blog'][$GLOBALS['current_blog_id']][(int)$id],$GLOBALS['party_rows'][(int)$id]);$GLOBALS['deleted_clients'][]=(int)$id;return (object)['ID'=>(int)$id];}
function wp_remote_post($url,$args){$GLOBALS['captured_http']=[$url,$args];return ['response'=>['code'=>$GLOBALS['http_code']],'body'=>$GLOBALS['http_body']];}
function wp_remote_retrieve_response_code($r){return $r['response']['code'];} function wp_remote_retrieve_body($r){return $r['body'];}
function wp_enqueue_script($handle){$GLOBALS['enqueued'][]=$handle;} function wp_localize_script($handle,$name,$data){$GLOBALS['localized'][$name]=$data;} function wp_create_nonce(){return 'nonce';} function admin_url($path=''){return 'https://example.test/wp-admin/'.$path;} function __($v){return $v;} function esc_html__($v){return $v;} function esc_html($v){return htmlspecialchars((string)$v);} function esc_attr($v){return htmlspecialchars((string)$v);} function esc_url($v){return (string)$v;}
function add_action(){} function add_filter(){} function register_rest_route($namespace,$route,$args){$GLOBALS['routes'][$route]=$args;} function __return_true(){return true;}

require __DIR__ . '/../inc/db/whatsapp_messages_table.php';
require __DIR__ . '/../inc/whatsapp.php';
require __DIR__ . '/../inc/rest/whatsapp.php';
require __DIR__ . '/../inc/admin/whatsapp-embedded-signup.php';
peracrm_rest_register_whatsapp_routes();
$associate_permission=$GLOBALS['routes']['/whatsapp/associate']['permission_callback'];
assert_same(false,$associate_permission(),'source-blog administrator without target authority cannot associate');
$GLOBALS['caps_by_blog'][2]['manage_options']=true;
assert_same(true,$associate_permission(),'target-blog administrator can associate');
$GLOBALS['caps_by_blog'][1]=[];
$GLOBALS['enqueued']=[];peracrm_whatsapp_embedded_signup_enqueue_assets();
assert_same(true,in_array('peracrm-whatsapp-embedded-signup',$GLOBALS['enqueued'],true),'target-blog administrator receives Embedded Signup assets without request-blog administration');
ob_start();peracrm_whatsapp_embedded_signup_render_panel();$panel_html=ob_get_clean();
assert_same(true,strpos($panel_html,'Diagnostic Embedded Signup tool')!==false,'target-blog administrator receives Embedded Signup panel');
$GLOBALS['caps_by_blog'][1]=['manage_options'=>true];$GLOBALS['caps_by_blog'][2]=[];$GLOBALS['enqueued']=[];
peracrm_whatsapp_embedded_signup_enqueue_assets();ob_start();peracrm_whatsapp_embedded_signup_render_panel();$denied_panel=ob_get_clean();
assert_same([], $GLOBALS['enqueued'],'request-blog administrator without target authority receives no Embedded Signup assets');
assert_same('', $denied_panel,'request-blog administrator without target authority receives no Embedded Signup panel');
$GLOBALS['caps_by_blog'][2]=['manage_options'=>true];
$runtime=peracrm_whatsapp_get_settings();
assert_same('environment-access-token',$runtime['access_token'],'environment access-token constant overrides runtime value');
assert_same('environment-verify-token',$runtime['verify_token'],'environment verify-token constant overrides runtime value');
assert_same('environment-app-secret',$runtime['app_secret'],'environment App Secret constant overrides runtime value');
assert_same('TEST_PHONE_ID',$runtime['phone_number_id'],'source-blog WhatsApp option cannot override target-blog configuration');
peracrm_whatsapp_save_settings(['enabled'=>1,'test_mode'=>1,'phone_number_id'=>'SAVED_TARGET_PHONE_ID','waba_id'=>'TEST_WABA','access_token'=>'','verify_token'=>'','app_secret'=>'','graph_api_version'=>'v22.0']);
assert_same('stored-access-token',$GLOBALS['saved_option']['access_token'],'blank save retains stored access token, not environment override');
assert_same('stored-verify-token',$GLOBALS['saved_option']['verify_token'],'blank save retains stored verify token, not environment override');
assert_same('stored-app-secret',$GLOBALS['saved_option']['app_secret'],'blank save retains stored App Secret, not environment override');
assert_same('SAVED_TARGET_PHONE_ID',$GLOBALS['settings_by_blog'][2]['phone_number_id'],'admin save writes WhatsApp configuration to target blog');
assert_same('SOURCE_PHONE_ID',$GLOBALS['settings_by_blog'][1]['phone_number_id'],'admin save does not create or overwrite source-blog configuration');
$verify_request=new WP_REST_Request('GET');$verify_request->set_param('hub_mode','subscribe');$verify_request->set_param('hub_verify_token','environment-verify-token');$verify_request->set_param('hub_challenge','target-config-ok');
assert_same(200,peracrm_rest_whatsapp_verify_webhook($verify_request)->get_status(),'inbound verification reads effective target-blog configuration');
$GLOBALS['settings_by_blog'][2]['test_mode']=0;
assert_same(200,peracrm_rest_whatsapp_verify_webhook($verify_request)->get_status(),'GET verification remains enabled when test_mode is disabled');
$GLOBALS['settings_by_blog'][2]['enabled']=0;
assert_same(403,peracrm_rest_whatsapp_verify_webhook($verify_request)->get_status(),'disabled integration refuses webhook verification');
assert_same(true,in_array('enabled',peracrm_whatsapp_configuration_errors(),true),'disabled integration refuses outbound configuration');
$GLOBALS['settings_by_blog'][2]['enabled']=1;$GLOBALS['settings_by_blog'][2]['test_mode']=1;
assert_same(1,$GLOBALS['current_blog_id'],'test begins outside target blog');
assert_same(true,peracrm_whatsapp_user_can_access_client(123),'target-only client is authorised after switching blogs');
assert_same(1,$GLOBALS['current_blog_id'],'client authorization restores originating blog');
$panel=peracrm_whatsapp_get_client_panel_context(123);
assert_same('+15551112222',$panel['phone'],'UI-facing recipient resolves target-blog phone despite colliding source post ID');
ob_start();peracrm_whatsapp_render_client_conversation(123);$test_conversation=ob_get_clean();
assert_same(true,strpos($test_conversation,'META TEST MODE')!==false,'test-mode conversation displays the test label');
$GLOBALS['settings_by_blog'][2]['test_mode']=0;
ob_start();peracrm_whatsapp_render_client_conversation(123);$production_conversation=ob_get_clean();
assert_same(false,strpos($production_conversation,'META TEST MODE')!==false,'production conversation omits the test label');
assert_same(true,strpos($production_conversation,'WhatsApp Business')!==false,'production conversation uses neutral WhatsApp wording');
$GLOBALS['settings_by_blog'][2]['test_mode']=1;
$GLOBALS['wpdb']->rows=[['id'=>1,'client_id'=>123,'message_body'=>'preview','meta_timestamp'=>'2026-09-15 12:00:00','created_at'=>'2026-09-15 12:00:00']];
$preview=peracrm_whatsapp_get_admin_preview_messages(10);
assert_same('Target CRM Client',$preview['rows'][0]['client_label'],'admin preview resolves target-blog client title');
assert_same('https://blog-2.test/wp-admin/post.php?post=123&action=edit',$preview['rows'][0]['client_edit_url'],'admin preview resolves target-blog edit URL');
$GLOBALS['wpdb']->rows=[];$GLOBALS['wpdb']->insert_id=0;
$GLOBALS['caps_by_blog'][2]=['peracrm_manage_all_clients'=>true];
assert_same(true,peracrm_whatsapp_user_can_access_client(123),'manage_all_clients grants global client access');
$GLOBALS['caps_by_blog'][2]=['peracrm_manage_all_reminders'=>true,'edit_crm_clients'=>true];
assert_same(false,peracrm_whatsapp_user_can_access_client(123),'manage_all_reminders alone does not grant global client access');
$GLOBALS['caps_by_blog'][2]=['edit_crm_clients'=>true];$GLOBALS['assigned_advisor']=7;
assert_same(true,peracrm_whatsapp_user_can_access_client(123),'assigned adviser retains access to own client');
$GLOBALS['assigned_advisor']=8;
assert_same(false,peracrm_whatsapp_user_can_access_client(123),'substituted client ID assigned to another adviser is rejected');
$GLOBALS['assigned_advisor']=0;$GLOBALS['caps_by_blog'][2]=['manage_options'=>true];

$fixture=file_get_contents(__DIR__.'/fixtures/meta-text-webhook.json');
$fixture=str_replace(['TEST_PHONE_NUMBER_ID','TEST_WABA_ID'],['SAVED_TARGET_PHONE_ID','TEST_WABA'],$fixture);
$fixture=str_replace('15551112222','905551112233',$fixture);
$signature='sha256='.hash_hmac('sha256',$fixture,'environment-app-secret');
$request=new WP_REST_Request('POST','/peracrm/v1/whatsapp/webhook'); $request->set_body($fixture); $request->set_header('X-Hub-Signature-256',$signature);
$response=peracrm_rest_whatsapp_receive_webhook($request);
assert_same(200,$response->get_status(),'valid signature is accepted by the real REST callback');
assert_same(1,count($GLOBALS['wpdb']->rows),'valid inbound message is persisted');
assert_same(999,$GLOBALS['wpdb']->rows[0]['client_id'],'unknown sender is linked to its new client');
assert_same('+905551112233',$GLOBALS['post_meta_by_blog'][2][999]['_peracrm_phone'],'new client stores canonical phone');
assert_same(1,$GLOBALS['created_clients'],'first inbound creates one crm_client');
assert_same('new_enquiry',$GLOBALS['party_rows'][999]['lead_pipeline_stage'],'new WhatsApp client receives canonical New enquiry stage');
$new_enquiry_count=count(array_filter($GLOBALS['party_rows'],function($party){return ($party['lead_pipeline_stage']??'')==='new_enquiry';}));
assert_same(1,$new_enquiry_count,'new WhatsApp client participates in the canonical New enquiry stage-count path');
peracrm_rest_whatsapp_receive_webhook($request);
assert_same(1,count($GLOBALS['wpdb']->rows),'duplicate WAMID does not create a duplicate row');
assert_same(1,$GLOBALS['created_clients'],'duplicate WAMID does not create another client');
$competing_fixture=str_replace('wamid.TEST_INBOUND_001','wamid.TEST_INBOUND_002',$fixture);
$competing_request=new WP_REST_Request('POST','/peracrm/v1/whatsapp/webhook');$competing_request->set_body($competing_fixture);$competing_request->set_header('X-Hub-Signature-256','sha256='.hash_hmac('sha256',$competing_fixture,'environment-app-secret'));
peracrm_rest_whatsapp_receive_webhook($competing_request);
assert_same(1,$GLOBALS['created_clients'],'competing first-message path reuses the claimed phone identity');
assert_same(999,end($GLOBALS['wpdb']->rows)['client_id'],'competing message attaches to the single client');

$race_fixture=str_replace(['905551112233','wamid.TEST_INBOUND_001'],['905551119999','wamid.CONCURRENT_WINNER'],$fixture);
$race_request=new WP_REST_Request('POST','/peracrm/v1/whatsapp/webhook');$race_request->set_body($race_fixture);$race_request->set_header('X-Hub-Signature-256','sha256='.hash_hmac('sha256',$race_fixture,'environment-app-secret'));
$deletions_before=count($GLOBALS['deleted_clients']);$events_before=count($GLOBALS['events']);$rows_before=count($GLOBALS['wpdb']->rows);$GLOBALS['wpdb']->simulate_concurrent_insert=true;
assert_same(200,peracrm_rest_whatsapp_receive_webhook($race_request)->get_status(),'concurrent WAMID winner is acknowledged as idempotent success');
assert_same($rows_before+1,count($GLOBALS['wpdb']->rows),'concurrent WAMID winner leaves exactly one durable message');
$race_row=end($GLOBALS['wpdb']->rows);assert_same('wamid.CONCURRENT_WINNER',$race_row['whatsapp_message_id'],'concurrent winner preserves WAMID');assert_same(true,(int)$race_row['client_id']>0,'concurrent winner message remains linked to resolved client');
assert_same($deletions_before,count($GLOBALS['deleted_clients']),'existing durable WAMID never rolls back newly resolved client');assert_same($events_before,count($GLOBALS['events']),'idempotent concurrent outcome emits no duplicate activity');

$parallel_a=str_replace(['905551112233','wamid.TEST_INBOUND_001'],['905551118888','wamid.PARALLEL_A'],$fixture);$parallel_b=str_replace(['905551112233','wamid.TEST_INBOUND_001'],['905551118888','wamid.PARALLEL_B'],$fixture);
$parallel_a_request=new WP_REST_Request('POST','/peracrm/v1/whatsapp/webhook');$parallel_a_request->set_body($parallel_a);$parallel_a_request->set_header('X-Hub-Signature-256','sha256='.hash_hmac('sha256',$parallel_a,'environment-app-secret'));
$parallel_b_request=new WP_REST_Request('POST','/peracrm/v1/whatsapp/webhook');$parallel_b_request->set_body($parallel_b);$parallel_b_request->set_header('X-Hub-Signature-256','sha256='.hash_hmac('sha256',$parallel_b,'environment-app-secret'));
$parallel_b_during=null;$GLOBALS['before_message_insert']=function()use($parallel_b_request,&$parallel_b_during){$parallel_b_during=peracrm_rest_whatsapp_receive_webhook($parallel_b_request)->get_status();};
$parallel_rows=count($GLOBALS['wpdb']->rows);assert_same(200,peracrm_rest_whatsapp_receive_webhook($parallel_a_request)->get_status(),'first different-WAMID request commits while owning identity claim');assert_same(500,$parallel_b_during,'concurrent different-WAMID request cannot use provisional client');
assert_same(200,peracrm_rest_whatsapp_receive_webhook($parallel_b_request)->get_status(),'different-WAMID retry reuses committed client after claim release');
$parallel_messages=array_values(array_filter($GLOBALS['wpdb']->rows,function($row){return in_array($row['whatsapp_message_id']??'', ['wamid.PARALLEL_A','wamid.PARALLEL_B'],true);}));assert_same(2,count($parallel_messages),'different concurrent WAMIDs both persist');assert_same($parallel_messages[0]['client_id'],$parallel_messages[1]['client_id'],'different concurrent WAMIDs link to one client');assert_same(true,isset($GLOBALS['post_meta_by_blog'][2][$parallel_messages[0]['client_id']]),'parallel message activities reference a retained client');

$failure_fixture=str_replace(['905551112233','wamid.TEST_INBOUND_001'],['905551110000','wamid.PERSIST_FAIL'],$fixture);
$failure_request=new WP_REST_Request('POST','/peracrm/v1/whatsapp/webhook');$failure_request->set_body($failure_fixture);$failure_request->set_header('X-Hub-Signature-256','sha256='.hash_hmac('sha256',$failure_fixture,'environment-app-secret'));
$events_before=count($GLOBALS['events']);$GLOBALS['wpdb']->fail_next_insert=true;
assert_same(500,peracrm_rest_whatsapp_receive_webhook($failure_request)->get_status(),'new-client message persistence failure is retryable');
$failed_client_id=end($GLOBALS['deleted_clients']);
assert_same(false,isset($GLOBALS['post_meta_by_blog'][2][$failed_client_id]),'failed inbound persistence rolls back only its newly created client');
assert_same(false,isset($GLOBALS['party_rows'][$failed_client_id]),'failed inbound persistence removes the new canonical party row');
assert_same($events_before,count($GLOBALS['events']),'failed inbound persistence leaves no durable client-created activity');
assert_same(true,$GLOBALS['rollback_had_lock'],'first-message compensation occurs while identity claim is owned');
assert_same(200,peracrm_rest_whatsapp_receive_webhook($failure_request)->get_status(),'retry after rolled-back creation succeeds');
$retry_row=end($GLOBALS['wpdb']->rows);assert_same('wamid.PERSIST_FAIL',$retry_row['whatsapp_message_id'],'retry durably stores the original WAMID');assert_same(false,in_array((int)$retry_row['client_id'],$GLOBALS['deleted_clients'],true),'retry links a fresh single client');
$retry_count=count(array_filter($GLOBALS['wpdb']->rows,function($row){return ($row['whatsapp_message_id']??'')==='wamid.PERSIST_FAIL';}));assert_same(1,$retry_count,'retry creates exactly one message row');

$existing_failure=str_replace(['905551112233','wamid.TEST_INBOUND_001'],['15551112222','wamid.EXISTING_FAIL'],$fixture);
$existing_failure_request=new WP_REST_Request('POST','/peracrm/v1/whatsapp/webhook');$existing_failure_request->set_body($existing_failure);$existing_failure_request->set_header('X-Hub-Signature-256','sha256='.hash_hmac('sha256',$existing_failure,'environment-app-secret'));
$GLOBALS['wpdb']->fail_next_insert=true;assert_same(500,peracrm_rest_whatsapp_receive_webhook($existing_failure_request)->get_status(),'existing-client message persistence failure is retryable');
assert_same(false,in_array(123,$GLOBALS['deleted_clients'],true),'existing matched client is never rolled back');assert_same(true,isset($GLOBALS['post_meta_by_blog'][2][123]),'existing matched client remains intact');
$insert_failure=str_replace(['905551112233','wamid.TEST_INBOUND_001'],['905551117777','wamid.INSERT_FAIL'],$fixture);$insert_failure_request=new WP_REST_Request('POST','/peracrm/v1/whatsapp/webhook');$insert_failure_request->set_body($insert_failure);$insert_failure_request->set_header('X-Hub-Signature-256','sha256='.hash_hmac('sha256',$insert_failure,'environment-app-secret'));$GLOBALS['fail_next_client_insert']=true;$insert_rows=count($GLOBALS['wpdb']->rows);
assert_same(500,peracrm_rest_whatsapp_receive_webhook($insert_failure_request)->get_status(),'wp_insert_post failure is retryable');assert_same($insert_rows,count($GLOBALS['wpdb']->rows),'wp_insert_post failure stores no unlinked WAMID');
$party_failure=str_replace(['905551112233','wamid.TEST_INBOUND_001'],['905551116666','wamid.PARTY_FAIL'],$fixture);$party_failure_request=new WP_REST_Request('POST','/peracrm/v1/whatsapp/webhook');$party_failure_request->set_body($party_failure);$party_failure_request->set_header('X-Hub-Signature-256','sha256='.hash_hmac('sha256',$party_failure,'environment-app-secret'));$GLOBALS['fail_next_party_upsert']=true;$party_rows_before=count($GLOBALS['wpdb']->rows);$deleted_before=count($GLOBALS['deleted_clients']);
assert_same(500,peracrm_rest_whatsapp_receive_webhook($party_failure_request)->get_status(),'canonical party initialization failure is retryable');assert_same($party_rows_before,count($GLOBALS['wpdb']->rows),'party initialization failure stores no unlinked WAMID');assert_same($deleted_before+1,count($GLOBALS['deleted_clients']),'party initialization failure cleans up created post');
assert_same('+905452054356',peracrm_whatsapp_normalize_phone('+90 545 205 4356'),'formatted Turkish number canonicalizes');
assert_same('+905452054356',peracrm_whatsapp_normalize_phone('905452054356'),'international Turkish digits canonicalize');
assert_same('+905452054356',peracrm_whatsapp_normalize_phone('05452054356'),'trunk-prefixed Turkish number canonicalizes');
assert_same('+905452054356',peracrm_whatsapp_normalize_phone('5452054356'),'local Turkish number canonicalizes');
assert_same('+4712345678',peracrm_whatsapp_normalize_meta_wa_id('4712345678'),'Meta WA ID preserves non-Turkish international country code');
assert_same('+905452054356',peracrm_whatsapp_normalize_meta_wa_id('905452054356'),'Meta Turkish WA ID remains canonical');
$GLOBALS['settings_by_blog'][2]['business_phone_e164']='+90 545 205 4356';
assert_same(true,peracrm_whatsapp_is_business_phone('05452054356'),'business-number guard compares canonical identities');
assert_same(true,peracrm_whatsapp_is_business_phone('5452054356'),'business number cannot become a client identity');
$GLOBALS['post_meta_by_blog'][2][124]=['_peracrm_phone'=>'05452050000'];
assert_same(124,peracrm_with_target_blog(function(){return peracrm_whatsapp_find_client_by_phone('+905452050000');}),'alternate Turkish representation reuses existing client');

peracrm_with_target_blog(function(){
    $lock_name=peracrm_whatsapp_client_lock_option_name('+905001112233');
    $owner=peracrm_whatsapp_acquire_client_lock($lock_name);
    $other=['owner'=>'different-owner','created_at'=>$owner['created_at']];
    assert_same(false,peracrm_whatsapp_release_client_lock($lock_name,$other),'one request cannot release another owner active lock');
    assert_same($owner,get_option($lock_name,null),'failed foreign release preserves active lock');
    assert_same(true,peracrm_whatsapp_release_client_lock($lock_name,$owner),'lock owner can release its claim');

    $stale_name=peracrm_whatsapp_client_lock_option_name('+905001112244');
    $stale=['owner'=>'expired-owner','created_at'=>time()-121];add_option($stale_name,$stale,'',false);
    $replacement=peracrm_whatsapp_acquire_client_lock($stale_name);
    assert_same(true,is_array($replacement)&&$replacement['owner']!==$stale['owner'],'stale lock is recovered with a new owner');
    assert_same($replacement,get_option($stale_name,null),'stale recovery installs the exact new claim');
    peracrm_whatsapp_release_client_lock($stale_name,$replacement);
});

$contended_phone='905009998877';
$contended_fixture=str_replace(['905551112233','wamid.TEST_INBOUND_001'],[$contended_phone,'wamid.CONTENDED'],$fixture);
$contended_request=new WP_REST_Request('POST','/peracrm/v1/whatsapp/webhook');$contended_request->set_body($contended_fixture);$contended_request->set_header('X-Hub-Signature-256','sha256='.hash_hmac('sha256',$contended_fixture,'environment-app-secret'));
$contended_claim=peracrm_with_target_blog(function()use($contended_phone){return peracrm_whatsapp_acquire_client_lock(peracrm_whatsapp_client_lock_option_name($contended_phone));});
$rows_before=count($GLOBALS['wpdb']->rows);$clients_before=$GLOBALS['created_clients'];$events_before=count($GLOBALS['events']);
assert_same(500,peracrm_rest_whatsapp_receive_webhook($contended_request)->get_status(),'identity contention timeout is retryable');
assert_same($rows_before,count($GLOBALS['wpdb']->rows),'contention timeout stores no permanent unlinked WAMID');
assert_same($clients_before,$GLOBALS['created_clients'],'active identity lock prevents duplicate client creation');assert_same($events_before,count($GLOBALS['events']),'contention timeout emits no timeline activity');
peracrm_with_target_blog(function()use($contended_phone,$contended_claim){peracrm_whatsapp_release_client_lock(peracrm_whatsapp_client_lock_option_name($contended_phone),$contended_claim);});
assert_same(200,peracrm_rest_whatsapp_receive_webhook($contended_request)->get_status(),'contention retry succeeds after claim release');$contended_row=end($GLOBALS['wpdb']->rows);assert_same('wamid.CONTENDED',$contended_row['whatsapp_message_id'],'contention retry preserves WAMID');assert_same(true,(int)$contended_row['client_id']>0,'contention retry links the resolved client');

$echo_payload=['object'=>'whatsapp_business_account','entry'=>[['id'=>'TEST_WABA','changes'=>[['field'=>'smb_message_echoes','value'=>['metadata'=>['phone_number_id'=>'SAVED_TARGET_PHONE_ID'],'message_echoes'=>[['id'=>'wamid.ECHO_1','to'=>'15551112222','timestamp'=>'1700000200','type'=>'text','text'=>['body'=>'Business App reply']]]]]]]]];
$before_events=count($GLOBALS['events']);
assert_same(1,peracrm_with_target_blog(function()use($echo_payload){return peracrm_whatsapp_process_inbound_payload($echo_payload); }),'text echo is processed');
$echo_row=end($GLOBALS['wpdb']->rows);
assert_same('outbound',$echo_row['direction'],'text echo is stored outbound');
assert_same('wamid.ECHO_1',$echo_row['whatsapp_message_id'],'text echo preserves WAMID');
assert_same(123,$echo_row['client_id'],'text echo links customer using to');
assert_same(0,peracrm_with_target_blog(function()use($echo_payload){return peracrm_whatsapp_process_inbound_payload($echo_payload); }),'duplicate echo is ignored');
assert_same($before_events+1,count($GLOBALS['events']),'duplicate echo emits no duplicate activity');
$echo_json=json_encode($echo_payload);$echo_request=new WP_REST_Request('POST','/peracrm/v1/whatsapp/webhook');$echo_request->set_body($echo_json);$echo_request->set_header('X-Hub-Signature-256','sha256='.hash_hmac('sha256',$echo_json,'environment-app-secret'));
$echo_events=count($GLOBALS['events']);assert_same(200,peracrm_rest_whatsapp_receive_webhook($echo_request)->get_status(),'duplicate echo WAMID is acknowledged successfully');assert_same($echo_events,count($GLOBALS['events']),'REST duplicate echo emits no activity');
$failed_echo=$echo_payload;$failed_echo['entry'][0]['changes'][0]['value']['message_echoes'][0]['id']='wamid.ECHO_STORE_FAIL';$failed_echo_json=json_encode($failed_echo);$failed_echo_request=new WP_REST_Request('POST','/peracrm/v1/whatsapp/webhook');$failed_echo_request->set_body($failed_echo_json);$failed_echo_request->set_header('X-Hub-Signature-256','sha256='.hash_hmac('sha256',$failed_echo_json,'environment-app-secret'));$GLOBALS['wpdb']->fail_next_insert=true;
assert_same(500,peracrm_rest_whatsapp_receive_webhook($failed_echo_request)->get_status(),'echo persistence failure is propagated as retryable');
$GLOBALS['post_meta_by_blog'][2][125]=['_peracrm_phone'=>'4712345678'];
$international_echo=$echo_payload;$international_echo['entry'][0]['changes'][0]['value']['message_echoes'][0]['id']='wamid.ECHO_NO';$international_echo['entry'][0]['changes'][0]['value']['message_echoes'][0]['to']='4712345678';
assert_same(1,peracrm_with_target_blog(function()use($international_echo){return peracrm_whatsapp_process_inbound_payload($international_echo); }),'international Meta echo matches canonical CRM client');
$international_row=end($GLOBALS['wpdb']->rows);assert_same('+4712345678',$international_row['phone_e164'],'international Meta echo stores canonical E.164 without Turkish-local interpretation');assert_same(125,$international_row['client_id'],'international Meta echo links the matching CRM client');
$own_echo=$echo_payload;$own_echo['entry'][0]['changes'][0]['value']['message_echoes'][0]['id']='wamid.ECHO_OWN';$own_echo['entry'][0]['changes'][0]['value']['message_echoes'][0]['to']='905452054356';$own_before=count($GLOBALS['wpdb']->rows);
assert_same(0,peracrm_with_target_blog(function()use($own_echo){return peracrm_whatsapp_process_inbound_payload($own_echo); }),'Meta echo business WA ID remains protected');assert_same($own_before,count($GLOBALS['wpdb']->rows),'business-number echo creates no message row');
$unsupported=$echo_payload;$unsupported['entry'][0]['changes'][0]['value']['message_echoes'][0]['id']='wamid.MEDIA';$unsupported['entry'][0]['changes'][0]['value']['message_echoes'][0]['type']='image';
assert_same(0,peracrm_with_target_blog(function()use($unsupported){return peracrm_whatsapp_process_inbound_payload($unsupported); }),'unsupported echo type is acknowledged without persistence');
$unmatched=$echo_payload;$unmatched['entry'][0]['changes'][0]['value']['message_echoes'][0]['id']='wamid.UNMATCHED';$unmatched['entry'][0]['changes'][0]['value']['message_echoes'][0]['to']='905000000000';
$clients_before=$GLOBALS['created_clients'];assert_same(0,peracrm_with_target_blog(function()use($unmatched){return peracrm_whatsapp_process_inbound_payload($unmatched); }),'unmatched echo is ignored');assert_same($clients_before,$GLOBALS['created_clients'],'unmatched echo creates no client');
foreach(['history','smb_app_state_sync','account_update','unknown_field'] as $ignored_field){$ignored=['object'=>'whatsapp_business_account','entry'=>[['id'=>'TEST_WABA','changes'=>[['field'=>$ignored_field,'value'=>[]]]]]];assert_same(0,peracrm_with_target_blog(function()use($ignored){return peracrm_whatsapp_process_inbound_payload($ignored); }),$ignored_field.' is an explicit no-op');}

$bad=new WP_REST_Request('POST');$bad->set_body($fixture);$bad->set_header('X-Hub-Signature-256','sha256='.str_repeat('0',64));
assert_same(401,peracrm_rest_whatsapp_receive_webhook($bad)->get_status(),'invalid signature is rejected');
$missing=new WP_REST_Request('POST');$missing->set_body($fixture);
assert_same(401,peracrm_rest_whatsapp_receive_webhook($missing)->get_status(),'missing signature is rejected');
$malformed='{bad json';$mal=new WP_REST_Request('POST');$mal->set_body($malformed);$mal->set_header('X-Hub-Signature-256','sha256='.hash_hmac('sha256',$malformed,'environment-app-secret'));
assert_same(400,peracrm_rest_whatsapp_receive_webhook($mal)->get_status(),'authenticated malformed JSON is rejected');

$client_request=new WP_REST_Request('POST');$client_request['client_id']=123;
$GLOBALS['logged_in']=false;
assert_same(false,peracrm_rest_whatsapp_client_permission($client_request),'unauthorised client messaging request is rejected');
$GLOBALS['logged_in']=true;
$client_request->set_param('message','Hello test recipient');$client_request->set_param('recipient','19999999999');
$before=count($GLOBALS['wpdb']->rows);$GLOBALS['http_code']=400;$GLOBALS['http_body']='{"error":{"message":"rejected"}}';
$result=peracrm_rest_whatsapp_send_message($client_request);
assert_same('meta_rejected',$result->get_error_code(),'Graph API non-2xx is returned as an error');
assert_same($before,count($GLOBALS['wpdb']->rows),'Graph API non-2xx does not persist outbound success');
$sent_payload=json_decode($GLOBALS['captured_http'][1]['body'],true);
assert_same('15551112222',$sent_payload['to'],'outbound uses same target-blog phone and ignores request recipient');
assert_same(true,strpos($GLOBALS['captured_http'][0],'/SAVED_TARGET_PHONE_ID/messages')!==false,'outbound sees Phone Number ID saved from source-blog admin request into target configuration');
$GLOBALS['http_code']=200;$GLOBALS['http_body']='{"messages":[{"id":"wamid.OUTBOUND_REAL"}]}';
$result=peracrm_rest_whatsapp_send_message($client_request);
assert_same(201,$result->get_status(),'successful Graph response returns created');
assert_same('wamid.OUTBOUND_REAL',end($GLOBALS['wpdb']->rows)['whatsapp_message_id'],'successful Graph response persists returned WAMID');
$GLOBALS['http_body']='{"messages":[{"id":"wamid.ACCEPTED_NOT_STORED"}]}';$GLOBALS['wpdb']->fail_next_insert=true;
$result=peracrm_whatsapp_send_client_text(123,'accepted but not stored');
assert_same('persistence_failed',$result->get_error_code(),'post-acceptance persistence failure is explicit');
assert_same(true,strpos($result->get_error_message(),'do not retry automatically')!==false,'post-acceptance error discourages an unsafe retry');

$GLOBALS['settings_by_blog'][2]['test_mode']=0;
$GLOBALS['http_body']='{"messages":[{"id":"wamid.PRODUCTION_MODE"}]}';
$result=peracrm_whatsapp_send_client_text(123,'production mode');
assert_same('wamid.PRODUCTION_MODE',$result['wamid'],'outbound remains enabled when test_mode is disabled');
$messages_request=new WP_REST_Request('GET');$messages_request['client_id']=123;
assert_same(false,peracrm_rest_whatsapp_client_messages($messages_request)->get_data()['test_mode'],'conversation API reports the effective production environment');
$production_response=peracrm_rest_whatsapp_receive_webhook($request);
assert_same(200,$production_response->get_status(),'signed webhook remains enabled when test_mode is disabled');
$GLOBALS['settings_by_blog'][2]['test_mode']=1;
$wrong=str_replace('SAVED_TARGET_PHONE_ID','OTHER_PHONE_ID',$fixture);$wrong_req=new WP_REST_Request('POST');$wrong_req->set_body($wrong);$wrong_req->set_header('X-Hub-Signature-256','sha256='.hash_hmac('sha256',$wrong,'environment-app-secret'));
$before=count($GLOBALS['wpdb']->rows);peracrm_rest_whatsapp_receive_webhook($wrong_req);
assert_same($before,count($GLOBALS['wpdb']->rows),'signed payload for a different Phone Number ID is ignored');
$GLOBALS['wpdb']->rows=[['id'=>1,'whatsapp_message_id'=>'wamid.STATUS','message_status'=>'sent','status_timestamp'=>'2023-11-14 22:13:20']];
peracrm_whatsapp_apply_status('wamid.STATUS','delivered',1700000100);peracrm_whatsapp_apply_status('wamid.STATUS','read',1700000200);
assert_same('read',$GLOBALS['wpdb']->rows[0]['message_status'],'sent to delivered to read remains read');
$GLOBALS['wpdb']->rows=[['id'=>4,'whatsapp_message_id'=>'wamid.RACE','message_status'=>'sent','status_timestamp'=>'2023-11-14 22:13:20']];$GLOBALS['wpdb']->simulate_status_race=true;
peracrm_whatsapp_apply_status('wamid.RACE','read',1700000200);
assert_same('read',$GLOBALS['wpdb']->rows[0]['message_status'],'concurrent delivered update is re-read before applying read');
assert_same(1,$GLOBALS['wpdb']->status_race_rechecks,'zero-row conditional update triggers bounded re-evaluation');
$GLOBALS['wpdb']->rows=[['id'=>1,'whatsapp_message_id'=>'wamid.STATUS','message_status'=>'read','status_timestamp'=>'2023-11-14 22:16:40']];
peracrm_whatsapp_apply_status('wamid.STATUS','delivered',1700000150);
assert_same('read',$GLOBALS['wpdb']->rows[0]['message_status'],'read ignores delayed delivered');
peracrm_whatsapp_apply_status('wamid.STATUS','sent',1700000050);
assert_same('read',$GLOBALS['wpdb']->rows[0]['message_status'],'read ignores delayed sent');
peracrm_whatsapp_apply_status('wamid.STATUS','read',1700000200);
assert_same('read',$GLOBALS['wpdb']->rows[0]['message_status'],'duplicate read is harmless');
$GLOBALS['wpdb']->rows=[['id'=>2,'whatsapp_message_id'=>'wamid.DELIVERED','message_status'=>'delivered','status_timestamp'=>'2023-11-14 22:15:00']];
peracrm_whatsapp_apply_status('wamid.DELIVERED','sent',1700000050);
assert_same('delivered',$GLOBALS['wpdb']->rows[0]['message_status'],'delivered ignores delayed sent');
peracrm_whatsapp_apply_status('wamid.DELIVERED','read',1700000000);
assert_same('delivered',$GLOBALS['wpdb']->rows[0]['message_status'],'higher-rank event with older timestamp is ignored');
$GLOBALS['wpdb']->rows=[['id'=>3,'whatsapp_message_id'=>'wamid.FAILED','message_status'=>'sent','status_timestamp'=>'2023-11-14 22:13:20']];
peracrm_whatsapp_apply_status('wamid.FAILED','failed',1700000100);
assert_same('failed',$GLOBALS['wpdb']->rows[0]['message_status'],'new failure before delivery is retained');
peracrm_whatsapp_apply_status('wamid.FAILED','delivered',1700000200);peracrm_whatsapp_apply_status('wamid.FAILED','failed',1700000300);
assert_same('delivered',$GLOBALS['wpdb']->rows[0]['message_status'],'failure cannot overwrite confirmed delivery');
$GLOBALS['site_utc_offset_hours']=3;
$GLOBALS['wpdb']->rows=[
 ['id'=>10,'client_id'=>123,'message_body'=>'legacy older','meta_timestamp'=>null,'created_at_utc'=>null,'created_at'=>'2023-11-15 01:15:00'],
 ['id'=>11,'client_id'=>123,'message_body'=>'Meta newer','meta_timestamp'=>'2023-11-14 22:20:00','created_at_utc'=>null,'created_at'=>'2023-11-15 01:20:00'],
];
$timezone_window=peracrm_whatsapp_get_messages(['client_id'=>123,'per_page'=>1,'paged'=>1]);
assert_same(11,$timezone_window['rows'][0]['id'],'non-UTC legacy offset cannot outrank newer Meta message');
$GLOBALS['site_utc_offset_hours']=0;
$GLOBALS['wpdb']->rows=[
 ['id'=>12,'client_id'=>123,'message_body'=>'UTC legacy older','meta_timestamp'=>null,'created_at_utc'=>null,'created_at'=>'2023-11-14 22:15:00'],
 ['id'=>13,'client_id'=>123,'message_body'=>'UTC Meta newer','meta_timestamp'=>'2023-11-14 22:20:00','created_at_utc'=>null,'created_at'=>'2023-11-14 22:20:00'],
];
$utc_window=peracrm_whatsapp_get_messages(['client_id'=>123,'per_page'=>1,'paged'=>1]);
assert_same(13,$utc_window['rows'][0]['id'],'UTC site message ordering remains correct');
$GLOBALS['wpdb']->rows=[];
for($id=1;$id<=105;$id++) $GLOBALS['wpdb']->rows[]=['id'=>$id,'client_id'=>123,'message_body'=>'message-'.$id,'meta_timestamp'=>gmdate('Y-m-d H:i:s',1700000000+$id),'created_at'=>gmdate('Y-m-d H:i:s',1700000000+$id)];
$window=peracrm_whatsapp_get_messages(['client_id'=>123,'per_page'=>100,'paged'=>1]);
assert_same(100,count($window['rows']),'conversation returns a 100-message window');
assert_same(6,$window['rows'][0]['id'],'conversation window excludes the five oldest messages');
assert_same(105,$window['rows'][99]['id'],'conversation window includes and ends with newest message');
echo "All executable WhatsApp behavior tests passed.\n";
