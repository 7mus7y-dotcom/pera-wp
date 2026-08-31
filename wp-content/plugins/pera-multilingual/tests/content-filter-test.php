<?php
/** Regression tests for canonical post_content discrimination. */
define( 'ABSPATH', __DIR__ );
class WP_Post { public $ID; public $post_content; public $post_type = 'property'; public function __construct( $id, $content ) { $this->ID = $id; $this->post_content = $content; } }
$GLOBALS['pera_test_post'] = new WP_Post( 17, '<p>Canonical English body.</p>' );
function add_filter() {} function add_action() {} function add_shortcode() {} function is_admin(){return false;} function is_feed(){return false;}
function get_the_ID(){return 17;} function get_post($id){return 17===(int)$id?$GLOBALS['pera_test_post']:null;} function get_post_type_object(){return (object)array('public'=>true);}
function expect_same($expected,$actual,$label){if($expected!==$actual){fwrite(STDERR,"FAIL $label\n");exit(1);}}
class ContentTestRouter { function is_translated(){return true;} function current_language(){return 'zh';} }
class ContentTestStorage {
	public $rows = array();
	function get($type,$id,$field,$language,$source=''){return array_key_exists($field,$this->rows)?$this->rows[$field]:null;}
}
require dirname(__DIR__).'/includes/class-content.php';
$storage = new ContentTestStorage();
$storage->rows = array(
	'post_title'   => array( 'translated_text' => '当前标题', 'status' => 'current', 'is_stale' => false ),
	'post_content' => array( 'translated_text' => '<p>规范中文正文。</p>', 'status' => 'current', 'is_stale' => false ),
	'post_excerpt' => array( 'translated_text' => '当前摘要', 'status' => 'current', 'is_stale' => false ),
);
$content = new Pera_ML_Content( null, new ContentTestRouter(), $storage );
expect_same('当前标题',$content->title('Canonical English title',17),'current title translates');
expect_same('<p>规范中文正文。</p>',$content->content('<p>Canonical English body.</p>'),'canonical post content translates');
expect_same('当前摘要',$content->excerpt('Canonical English excerpt',$GLOBALS['pera_test_post']),'current excerpt translates');
expect_same('<section>Translated ACF project summary.</section>',$content->content('<section>Translated ACF project summary.</section>'),'manual ACF the_content value is untouched');

$storage->rows['post_title'] = array( 'translated_text' => '旧标题', 'status' => 'current', 'is_stale' => true );
expect_same('Canonical English title',$content->title('Canonical English title',17),'source hash mismatch falls back for title');
$storage->rows['post_content'] = array( 'translated_text' => '<p>待审核正文。</p>', 'status' => 'pending', 'is_stale' => false );
expect_same('<p>Canonical English body.</p>',$content->content('<p>Canonical English body.</p>'),'non-current content falls back');
$storage->rows['post_excerpt'] = array( 'translated_text' => " \n\t ", 'status' => 'current', 'is_stale' => false );
expect_same('Canonical English excerpt',$content->excerpt('Canonical English excerpt',$GLOBALS['pera_test_post']),'blank excerpt falls back');
unset($storage->rows['post_title']);
expect_same('Canonical English title',$content->title('Canonical English title',17),'missing title falls back');
echo "Pera ML content filter tests passed\n";
