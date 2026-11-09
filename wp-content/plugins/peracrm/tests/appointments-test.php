<?php
/** Pure validation and privacy regression suite; persistence is tested in real MySQL. */
define('ABSPATH',__DIR__);define('DAY_IN_SECONDS',86400);
class WP_Error { public $code; public $message; function __construct($c,$m,$d=null){$this->code=$c;$this->message=$m;} }
function is_wp_error($v){return $v instanceof WP_Error;}
function get_post_type($id){return $id===100?'crm_client':($id===200?'property':'page');}
function get_post_status($id){return 'publish';}
function post_password_required($id){return false;}
function peracrm_user_is_valid_advisor($id){return in_array($id,[1,7,8],true);}
function peracrm_user_can_access_crm($id=0){return $id!==8;}
function is_multisite(){return false;}
function get_current_user_id(){return 7;}
function sanitize_textarea_field($v){return strip_tags($v);}
function wp_http_validate_url($v){return filter_var($v,FILTER_VALIDATE_URL);}
function wp_parse_url($v,$part){return parse_url($v,$part);}
function esc_url_raw($v,$protocols=[]){return $v;}
function get_userdata($id){return (object)['display_name'=>'Advisor'];}
function peracrm_user_can_access_client($u,$c){return false;}
require dirname(__DIR__).'/inc/services/appointment_service.php';
$n=0;function check($v,$message){global $n;$n++;if(!$v)throw new Exception($message);}
check(peracrm_appointment_utc('2026-11-09T10:00','Europe/Istanbul')==='2026-11-09 07:00:00','Istanbul UTC save');
foreach ([['2026-02-30T10:00','Europe/Istanbul'],['2026-03-29T01:30','Europe/London'],['2026-10-25T01:30','Europe/London'],['2026-11-01T01:30','America/New_York'],['2026-11-09T10:00','Bad/Zone'],['tomorrow','UTC']] as [$date,$zone])check(is_wp_error(peracrm_appointment_utc($date,$zone)),'invalid/DST date rejected');
check(peracrm_appointment_utc('2026-03-29T02:30','Europe/London')==='2026-03-29 01:30:00','DST conversion');
$valid=['client_id'=>100,'advisor_id'=>7,'type'=>'property_tour','attendees'=>2,'start_local'=>'2026-11-09T10:00','end_local'=>'2026-11-09T11:00','participant_ids'=>[1],'property_ids'=>[200]];
$d=peracrm_appointment_validate($valid);check(!is_wp_error($d),'valid data');check($d['participant_ids']===[7,1],'primary participant');check($d['status']==='confirmed','confirmed default');
foreach (['attendees'=>0,'end_local'=>'2026-11-09T09:00','participant_ids'=>[7,7],'property_ids'=>[200,200],'advisor_id'=>8,'client_id'=>200,'status'=>'bad','timezone'=>'UTC+3','map_url'=>'javascript:alert(1)','property_ids'=>[300]] as $k=>$v)check(is_wp_error(peracrm_appointment_validate(array_replace($valid,[$k=>$v]))),'reject '.$k);
foreach (peracrm_appointment_statuses() as $s=>$label)check(!is_wp_error(peracrm_appointment_validate(array_replace($valid,['status'=>$s]))),'status '.$s);
$r=peracrm_appointment_present(['id'=>1,'client_id'=>100,'advisor_id'=>7,'start_at'=>'2026-11-09 07:00:00','end_at'=>'2026-11-09 08:00:00','type'=>'hotel_meeting','status'=>'confirmed','internal_notes'=>'SECRET','title'=>'SECRET','client_name'=>'SECRET','hotel_name'=>'SECRET']);
check($r['restricted'] && !str_contains(json_encode($r),'SECRET') && !isset($r['client_id']),'restricted projection');
echo "appointments PHP: {$n} assertions passed\n";
