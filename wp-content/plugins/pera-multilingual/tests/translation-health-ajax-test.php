<?php
/** AJAX boundary failures stop before Translation Health orchestration. */
define( 'ABSPATH', __DIR__ );
$GLOBALS['health_capability']=false; $GLOBALS['health_nonce']=false;
function current_user_can( $cap ) { return $GLOBALS['health_capability']; } function check_ajax_referer(){return $GLOBALS['health_nonce'];} function wp_unslash($v){return $v;} function is_wp_error($v){return $v instanceof WP_Error;}
class WP_Error { private $code; public function __construct($code){$this->code=$code;} public function get_error_code(){return $this->code;} }
function ajax_expect($expected,$actual,$label){if($expected!==$actual){fwrite(STDERR,"FAIL {$label}\n");exit(1);}}
require dirname(__DIR__).'/admin/class-admin.php'; $admin=new Pera_ML_Admin(new stdClass()); $_POST['row']='{"object_type":"post"}';
ajax_expect('forbidden',$admin->health_ajax_request()->get_error_code(),'manage_options required');
$GLOBALS['health_capability']=true; ajax_expect('invalid_nonce',$admin->health_ajax_request()->get_error_code(),'nonce required');
$GLOBALS['health_nonce']=true; ajax_expect(array('object_type'=>'post'),$admin->health_ajax_request(),'valid boundary returns parsed row only');
echo "Pera ML translation health AJAX boundary tests passed\n";
