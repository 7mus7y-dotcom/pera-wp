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
    public $rows=[]; public $insert_id=0; public $fail_next_insert=false;
    public function prepare($query,...$args){ if(count($args)===1 && is_array($args[0]))$args=$args[0]; foreach($args as $arg){$replacement=is_int($arg)?(string)$arg:"'".addslashes((string)$arg)."'";$query=preg_replace('/%[ds]/',$replacement,$query,1);} return $query; }
    public function get_var($query){
        if (preg_match("/whatsapp_message_id = '([^']+)'/",$query,$m)) foreach($this->rows as $row) if($row['whatsapp_message_id']===$m[1]) return $row['id'];
        if(strpos($query,'COUNT(*)')!==false) return count($this->rows); return null;
    }
    public function insert($table,$data,$formats=[]){
        if ($this->fail_next_insert) { $this->fail_next_insert=false; return false; }
        foreach($this->rows as $row) if(($data['whatsapp_message_id']??'')!=='' && $row['whatsapp_message_id']===$data['whatsapp_message_id']) return false;
        $data['id']=++$this->insert_id; $this->rows[]=$data; return 1;
    }
    public function update($table,$data,$where,$formats=[],$where_formats=[]){foreach($this->rows as &$row){$match=true;foreach($where as $k=>$v)if(($row[$k]??null)!==$v)$match=false;if($match){$row=array_merge($row,$data);return 1;}}return 0;}
    public function get_row($query){
        if(preg_match("/whatsapp_message_id = '([^']+)'/",$query,$m)) foreach($this->rows as $row) if(($row['whatsapp_message_id']??'')===$m[1]) return $row;
        return null;
    }
    public function get_results($query){
        $rows=$this->rows;
        if(preg_match('/WHERE client_id = (\d+)/',$query,$m)) $rows=array_values(array_filter($rows,function($row)use($m){return (int)($row['client_id']??0)===(int)$m[1];}));
        usort($rows,function($a,$b){$at=$a['meta_timestamp']??$a['created_at']??'';$bt=$b['meta_timestamp']??$b['created_at']??'';$time=strcmp($bt,$at);return $time!==0?$time:(((int)$b['id'])<=>((int)$a['id']));});
        $limit=count($rows);$offset=0;if(preg_match('/LIMIT (\d+) OFFSET (\d+)/',$query,$m)){ $limit=(int)$m[1];$offset=(int)$m[2]; }
        return array_slice($rows,$offset,$limit);
    } public function query(){return 0;}
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
$GLOBALS['http_code']=200; $GLOBALS['http_body']='{"messages":[{"id":"wamid.OUTBOUND_1"}]}'; $GLOBALS['captured_http']=null; $GLOBALS['created_clients']=0;
function get_option($key,$default=[]){return $key==='peracrm_whatsapp_settings'?($GLOBALS['settings_by_blog'][$GLOBALS['current_blog_id']]??$default):$default;} function update_option($key,$value){if($key==='peracrm_whatsapp_settings'){$GLOBALS['saved_option']=$value;$GLOBALS['settings_by_blog'][$GLOBALS['current_blog_id']]=$value;}return true;}
function wp_parse_args($a,$b=[]){return array_merge($b,$a);} function sanitize_text_field($v){return trim(strip_tags((string)$v));} function sanitize_textarea_field($v){return trim(strip_tags((string)$v));}
function sanitize_key($v){return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v));} function absint($v){return abs((int)$v);} function esc_url_raw($v){return (string)$v;}
function wp_json_encode($v){return json_encode($v);} function peracrm_json_encode($v){return json_encode($v);} function peracrm_now_mysql(){return '2026-09-15 12:00:00';} function current_time(){return '2026-09-15 12:00:00';}
function peracrm_table(){return 'wp_peracrm_whatsapp_messages';} function get_posts(){return [];} function get_post_type($id){return $GLOBALS['current_blog_id']===$GLOBALS['target_blog_id'] && $id===123?'crm_client':false;}
function get_the_title($id){return $GLOBALS['titles_by_blog'][$GLOBALS['current_blog_id']][$id]??'';} function get_edit_post_link($id,$context='display'){return 'https://blog-'.$GLOBALS['current_blog_id'].'.test/wp-admin/post.php?post='.$id.'&action=edit';}
function get_post_meta($id,$key){return $GLOBALS['post_meta_by_blog'][$GLOBALS['current_blog_id']][$id][$key]??'';} function get_current_user_id(){return 7;} function is_user_logged_in(){return $GLOBALS['logged_in'];}
function user_can($id,$cap){return !empty($GLOBALS['caps_by_blog'][$GLOBALS['current_blog_id']][$cap]);} function current_user_can($cap){return user_can(7,$cap);} function peracrm_client_get_assigned_advisor_id(){return $GLOBALS['current_blog_id']===$GLOBALS['target_blog_id']?$GLOBALS['assigned_advisor']:0;}
function peracrm_with_target_blog($cb){$before=$GLOBALS['current_blog_id'];if($before!==$GLOBALS['target_blog_id']){$GLOBALS['current_blog_id']=$GLOBALS['target_blog_id'];$GLOBALS['target_switches']++;}try{return $cb();}finally{$GLOBALS['current_blog_id']=$before;}} function peracrm_log_event(){return true;} function wp_insert_post(){ $GLOBALS['created_clients']++; return 999; }
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
assert_same(1,$GLOBALS['current_blog_id'],'test begins outside target blog');
assert_same(true,peracrm_whatsapp_user_can_access_client(123),'target-only client is authorised after switching blogs');
assert_same(1,$GLOBALS['current_blog_id'],'client authorization restores originating blog');
$panel=peracrm_whatsapp_get_client_panel_context(123);
assert_same('+15551112222',$panel['phone'],'UI-facing recipient resolves target-blog phone despite colliding source post ID');
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
$GLOBALS['assigned_advisor']=0;$GLOBALS['caps_by_blog'][2]=['manage_options'=>true];

$fixture=file_get_contents(__DIR__.'/fixtures/meta-text-webhook.json');
$fixture=str_replace(['TEST_PHONE_NUMBER_ID','TEST_WABA_ID'],['SAVED_TARGET_PHONE_ID','TEST_WABA'],$fixture);
$signature='sha256='.hash_hmac('sha256',$fixture,'environment-app-secret');
$request=new WP_REST_Request('POST','/peracrm/v1/whatsapp/webhook'); $request->set_body($fixture); $request->set_header('X-Hub-Signature-256',$signature);
$response=peracrm_rest_whatsapp_receive_webhook($request);
assert_same(200,$response->get_status(),'valid signature is accepted by the real REST callback');
assert_same(1,count($GLOBALS['wpdb']->rows),'valid inbound message is persisted');
assert_same(null,$GLOBALS['wpdb']->rows[0]['client_id'],'unknown sender remains unlinked');
assert_same(0,$GLOBALS['created_clients'],'unknown sender does not create a crm_client');
peracrm_rest_whatsapp_receive_webhook($request);
assert_same(1,count($GLOBALS['wpdb']->rows),'duplicate WAMID does not create a duplicate row');

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
$result=peracrm_whatsapp_send_client_text(123,'must fail closed');
assert_same('not_configured',$result->get_error_code(),'outbound fails closed when test_mode is disabled');
$GLOBALS['settings_by_blog'][2]['test_mode']=1;
$wrong=str_replace('SAVED_TARGET_PHONE_ID','OTHER_PHONE_ID',$fixture);$wrong_req=new WP_REST_Request('POST');$wrong_req->set_body($wrong);$wrong_req->set_header('X-Hub-Signature-256','sha256='.hash_hmac('sha256',$wrong,'environment-app-secret'));
$before=count($GLOBALS['wpdb']->rows);peracrm_rest_whatsapp_receive_webhook($wrong_req);
assert_same($before,count($GLOBALS['wpdb']->rows),'signed payload for a different Phone Number ID is ignored');
$GLOBALS['wpdb']->rows=[['id'=>1,'whatsapp_message_id'=>'wamid.STATUS','message_status'=>'sent','status_timestamp'=>'2023-11-14 22:13:20']];
peracrm_whatsapp_apply_status('wamid.STATUS','delivered',1700000100);peracrm_whatsapp_apply_status('wamid.STATUS','read',1700000200);
assert_same('read',$GLOBALS['wpdb']->rows[0]['message_status'],'sent to delivered to read remains read');
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
$GLOBALS['wpdb']->rows=[];
for($id=1;$id<=105;$id++) $GLOBALS['wpdb']->rows[]=['id'=>$id,'client_id'=>123,'message_body'=>'message-'.$id,'meta_timestamp'=>gmdate('Y-m-d H:i:s',1700000000+$id),'created_at'=>gmdate('Y-m-d H:i:s',1700000000+$id)];
$window=peracrm_whatsapp_get_messages(['client_id'=>123,'per_page'=>100,'paged'=>1]);
assert_same(100,count($window['rows']),'conversation returns a 100-message window');
assert_same(6,$window['rows'][0]['id'],'conversation window excludes the five oldest messages');
assert_same(105,$window['rows'][99]['id'],'conversation window includes and ends with newest message');
echo "All executable WhatsApp behavior tests passed.\n";
