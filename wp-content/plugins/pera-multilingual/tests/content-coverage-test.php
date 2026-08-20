<?php
/** Standalone coverage tests: php tests/content-coverage-test.php */
define( 'ABSPATH', __DIR__ );
function apply_filters( $tag, $value ) { return $value; } function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_:-]/', '', strtolower( $v ) ); }
function __( $v ) { return $v; } function is_wp_error( $v ) { return $v instanceof WP_Error; }
class WP_Error { private $code; public function __construct( $code ) { $this->code=$code; } public function get_error_code(){return $this->code;} }
class WP_Term { public $term_id=4; public $name='Villa'; public $description='English description'; }
class WP_Post { public $ID=9; }
function expect_same($e,$a,$l){if($e!==$a){fwrite(STDERR,"FAIL $l\n".var_export($a,true)."\n");exit(1);}}
class TestRouter { public $language='zh'; function current_language(){return $this->language;} }
class TestStorage { public $rows=array(); function get($t,$id,$f,$l,$s=''){ $k="$t|$id|$f|$l"; return isset($this->rows[$k])?array('translated_text'=>$this->rows[$k]):null;} function put(){return true;} }
require dirname(__DIR__).'/includes/class-vocabulary.php'; require dirname(__DIR__).'/includes/class-fields.php'; require dirname(__DIR__).'/includes/class-translator.php'; require dirname(__DIR__).'/includes/class-ajax.php';
$router=new TestRouter();$storage=new TestStorage();$vocab=new Pera_ML_Vocabulary();$fields=new Pera_ML_Fields($router,$storage,$vocab);
$storage->rows['post|9|meta:project_name|zh']='海景项目'; expect_same('海景项目',$fields->get(9,'project_name','Sea Project'),'translated field');
expect_same('Sea Project',$fields->get(9,'project_name','Sea Project','en'),'English unchanged');
$storage->rows['term|123|meta:district_archive_body|zh']='地区正文';
expect_same('地区正文',$fields->acf_value('District body','term_123',array('name'=>'district_archive_body')),'ACF term_N object');
expect_same('地区正文',$fields->acf_value('District body','district_123',array('name'=>'district_archive_body')),'ACF taxonomy_N object');
expect_same('海景项目',$fields->acf_value('Sea Project',9,array('name'=>'project_name')),'ACF numeric post object');
expect_same('Options copy',$fields->acf_value('Options copy','option',array('name'=>'archive_h1')),'ACF options object preserved'); expect_same('42',$fields->get(9,'price_usd','42'),'unapproved numeric preserved');
expect_same('别墅',$vocab->translate('Villa','zh'),'Chinese vocabulary'); expect_same('فيلا',$vocab->translate('Villa','ar'),'Arabic vocabulary');
$term=new WP_Term(); expect_same('别墅',$fields->term($term,'name','zh'),'term vocabulary fallback'); $storage->rows['term|4|term_name|ar']='فيلا مراجعة'; expect_same('فيلا مراجعة',$fields->term($term,'name','ar'),'stored taxonomy name');
$ajax_registry=new class { function enabled(){return array('en'=>array(),'zh'=>array(),'ar'=>array());} }; $ajax=new Pera_ML_Ajax($ajax_registry,$router); expect_same('zh',$ajax->validate_language('zh'),'AJAX Chinese accepted'); expect_same('en',$ajax->validate_language('evil'),'AJAX invalid fallback');
$registry=new class { function get($l){return array('name'=>$l,'source'=>false);} }; $translator=new Pera_ML_Translator($registry,$storage);
$source='<p class="hero" id="x">Call +90 555 123 4567 or a@b.com: $250,000 / 120 m² [gallery id="2"] https://example.com/x</p>';$protected=$translator->protect($source); expect_same($source,$translator->restore($protected['text'],$protected['tokens']),'protected round trip');
$bad=$translator->restore(str_replace('PERAMLPROTECTED0TOKEN','',$protected['text']),$protected['tokens']);expect_same('pera_ml_structure_changed',$bad->get_error_code(),'lost token rejected');
$admin_source=file_get_contents(dirname(__DIR__).'/admin/class-admin.php');
expect_same(true,strpos($admin_source,"current_user_can( 'edit_post'")!==false,'translation action capability check');
expect_same(true,strpos($admin_source,"check_admin_referer( 'pera_ml_translate_'")!==false,'translation action nonce check');
expect_same(true,strpos($admin_source,'method="post"')!==false,'translation action uses POST');
expect_same(false,strpos($admin_source,'pera_ml_failed_fields')!==false,'provider failures absent from redirect');
expect_same(true,strpos($admin_source,'set_transient')!==false,'user-scoped notice stored server side');
echo "Pera ML content coverage tests passed\n";
