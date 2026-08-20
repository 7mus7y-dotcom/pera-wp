<?php
/** Regression tests for canonical post_content discrimination. */
define( 'ABSPATH', __DIR__ );
class WP_Post { public $ID; public $post_content; public $post_type = 'property'; public function __construct( $id, $content ) { $this->ID = $id; $this->post_content = $content; } }
$GLOBALS['pera_test_post'] = new WP_Post( 17, '<p>Canonical English body.</p>' );
function add_filter() {} function add_action() {} function add_shortcode() {} function is_admin(){return false;} function is_feed(){return false;}
function get_the_ID(){return 17;} function get_post($id){return 17===(int)$id?$GLOBALS['pera_test_post']:null;} function get_post_type_object(){return (object)array('public'=>true);}
function expect_same($expected,$actual,$label){if($expected!==$actual){fwrite(STDERR,"FAIL $label\n");exit(1);}}
class ContentTestRouter { function is_translated(){return true;} function current_language(){return 'zh';} }
class ContentTestStorage { function get($type,$id,$field,$language,$source=''){return 'post_content'===$field?array('translated_text'=>'<p>规范中文正文。</p>'):null;} }
require dirname(__DIR__).'/includes/class-content.php';
$content = new Pera_ML_Content( null, new ContentTestRouter(), new ContentTestStorage() );
expect_same('<p>规范中文正文。</p>',$content->content('<p>Canonical English body.</p>'),'canonical post content translates');
expect_same('<section>Translated ACF project summary.</section>',$content->content('<section>Translated ACF project summary.</section>'),'manual ACF the_content value is untouched');
echo "Pera ML content filter tests passed\n";
