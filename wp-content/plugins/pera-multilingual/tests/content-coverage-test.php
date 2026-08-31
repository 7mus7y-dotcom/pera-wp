<?php
/** Standalone coverage tests: php tests/content-coverage-test.php */
define( 'ABSPATH', __DIR__ );
function apply_filters( $tag, $value ) { return $value; } function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_:-]/', '', strtolower( $v ) ); }
function __( $v ) { return $v; } function is_wp_error( $v ) { return $v instanceof WP_Error; }
class WP_Error { private $code; public function __construct( $code ) { $this->code=$code; } public function get_error_code(){return $this->code;} }
class WP_Term { public $term_id=4; public $taxonomy='region'; public $name='Villa'; public $description='English description'; }
class WP_Post { public $ID=9; }
function expect_same($e,$a,$l){if($e!==$a){fwrite(STDERR,"FAIL $l\n".var_export($a,true)."\n");exit(1);}}
class TestRouter { public $language='zh'; function current_language(){return $this->language;} }
class TestStorage { public $rows=array(); function get($t,$id,$f,$l,$s=''){ $k="$t|$id|$f|$l"; if ( ! isset( $this->rows[$k] ) ) return null; return is_array( $this->rows[$k] ) ? $this->rows[$k] : array( 'translated_text' => $this->rows[$k] ); } function put(){return true;} }
require dirname(__DIR__).'/includes/class-storage.php'; require dirname(__DIR__).'/includes/class-vocabulary.php'; require dirname(__DIR__).'/includes/class-fields.php'; require dirname(__DIR__).'/includes/class-translator.php'; require dirname(__DIR__).'/includes/class-ajax.php';
$router=new TestRouter();$storage=new TestStorage();$vocab=new Pera_ML_Vocabulary();$fields=new Pera_ML_Fields($router,$storage,$vocab);
$storage->rows['post|9|meta:project_name|zh']='海景项目'; expect_same('海景项目',$fields->get(9,'project_name','Sea Project'),'translated field');
expect_same('Sea Project',$fields->get(9,'project_name','Sea Project','en'),'English unchanged');
$storage->rows['term|123|meta:district_archive_body|zh']='地区正文';
expect_same('地区正文',$fields->acf_value('District body','term_123',array('name'=>'district_archive_body')),'ACF term_N object');
expect_same('地区正文',$fields->acf_value('District body','district_123',array('name'=>'district_archive_body')),'ACF taxonomy_N object');
expect_same('海景项目',$fields->acf_value('Sea Project',9,array('name'=>'project_name')),'ACF numeric post object');
expect_same('Options copy',$fields->acf_value('Options copy','option',array('name'=>'archive_h1')),'ACF options object preserved'); expect_same('42',$fields->get(9,'price_usd','42'),'unapproved numeric preserved');
expect_same('别墅',$vocab->translate('Villa','zh'),'Chinese vocabulary'); expect_same('فيلا',$vocab->translate('Villa','ar'),'Arabic vocabulary');
expect_same('Wohnung',$vocab->translate('Apartment','de'),'German apartment vocabulary'); expect_same('Bosporusblick',$vocab->translate('Bosphorus View','de'),'German property vocabulary');
expect_same('Pool',$vocab->translate('Swimming Pool','de'),'German swimming pool vocabulary'); expect_same('Für die Staatsbürgerschaft geeignet',$vocab->translate('Citizenship Suitable','de'),'German citizenship vocabulary');
$term=new WP_Term();
$storage->rows['term|4|term_name|ar']=array('translated_text'=>'فيلا مراجعة','is_stale'=>false,'status'=>'current'); expect_same('فيلا مراجعة',$fields->term($term,'name','ar'),'current non-empty taxonomy name returned');
$storage->rows['term|4|term_name|zh']=array('translated_text'=>'旧别墅','is_stale'=>true,'status'=>'current'); expect_same('别墅',$fields->term($term,'name','zh'),'stale taxonomy name rejected and vocabulary used');
$storage->rows['term|4|term_name|zh']=array('translated_text'=>'旧别墅','is_stale'=>false,'status'=>'pending'); expect_same('别墅',$fields->term($term,'name','zh'),'non-current taxonomy name rejected and vocabulary used');
$storage->rows['term|4|term_name|zh']=array('translated_text'=>'   ','is_stale'=>false,'status'=>'current'); expect_same('别墅',$fields->term($term,'name','zh'),'empty taxonomy name rejected and vocabulary used');
unset($storage->rows['term|4|term_name|zh']); expect_same('别墅',$fields->term($term,'name','zh'),'term name fallback still uses vocabulary');
$storage->rows['term|4|term_description|zh']=array('is_stale'=>false,'status'=>'current'); expect_same('English description',$fields->term($term,'description','zh'),'term description without translation falls back to canonical source');
$category=new WP_Term(); $category->term_id=102; $category->taxonomy='category'; $category->name='Buyer guides'; $category->description='Canonical category description';
$storage->rows['term|102|term_name|de']=array('translated_text'=>'Kaufratgeber','is_stale'=>false,'status'=>'current'); expect_same('Kaufratgeber',$fields->term($category,'name','de'),'current category name translation returned');
$storage->rows['term|102|term_name|de']=array('translated_text'=>'Alter Kaufratgeber','is_stale'=>true,'status'=>'current'); expect_same('Buyer guides',$fields->term($category,'name','de'),'stale category name falls back to canonical source');
$storage->rows['term|102|term_description|de']=array('translated_text'=>'Kategorie Beschreibung','is_stale'=>false,'status'=>'current'); expect_same('Kategorie Beschreibung',$fields->term($category,'description','de'),'current category description translation returned');
$storage->rows['term|102|term_description|de']=array('translated_text'=>'Alt','is_stale'=>false,'status'=>'pending'); expect_same('Canonical category description',$fields->term($category,'description','de'),'non-current category description falls back to canonical source');
$storage->rows['term|4|meta:archive_subtitle|zh']='地区副标题'; expect_same('地区副标题',$fields->term_meta($term,'meta:archive_subtitle','Region subtitle','zh'),'approved taxonomy meta translation');
expect_same('Private',$fields->term_meta($term,'meta:private_copy','Private','zh'),'unapproved taxonomy meta preserved'); expect_same('Region subtitle',$fields->term_meta($term,'meta:archive_subtitle','Region subtitle','en'),'English taxonomy meta preserved');
$ajax_registry=new class { function enabled(){return array('en'=>array(),'zh'=>array(),'ar'=>array(),'de'=>array());} }; $ajax=new Pera_ML_Ajax($ajax_registry,$router); expect_same('zh',$ajax->validate_language('zh'),'AJAX Chinese accepted'); expect_same('de',$ajax->validate_language('de'),'AJAX German accepted'); expect_same('en',$ajax->validate_language('evil'),'AJAX invalid fallback');
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
