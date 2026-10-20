<?php
/** Standalone regression: real router, AJAX context, content filters and card templates. */
define( 'ABSPATH', __DIR__ );
$GLOBALS['admin'] = false;
$GLOBALS['ajax'] = false;
$GLOBALS['nonce_valid'] = true;
$GLOBALS['hooks'] = array();
function add_action($tag,$callback,$priority=10){$GLOBALS['hooks'][$tag][$priority][]=$callback;}
function add_filter(){} function add_shortcode(){}
function is_admin(){return $GLOBALS['admin'];} function wp_doing_ajax(){return $GLOBALS['ajax'];}
function is_feed(){return false;} function in_the_loop(){return true;} function is_main_query(){return false;}
function sanitize_key($s){return preg_replace('/[^a-z0-9_-]/','',strtolower($s));}
function sanitize_text_field($s){return $s;} function wp_unslash($s){return $s;}
function wp_parse_url($s,$part=-1){return parse_url($s,$part);}
function home_url($path='/'){return 'https://example.org/'.ltrim($path,'/');}
function trailingslashit($s){return rtrim($s,'/').'/';} function untrailingslashit($s){return rtrim($s,'/');}
function check_ajax_referer($action,$key,$die=true){return $GLOBALS['nonce_valid'] && isset($_POST[$key]) && $_POST[$key] === $action;}
function __($s){return $s;} function wp_send_json_error($data,$status){throw new RuntimeException('HTTP '.$status);}
function get_the_ID(){return $GLOBALS['post']->ID;}
function get_post($id){return $GLOBALS['posts'][$id] ?? null;}
function get_post_type_object(){return (object)array('public'=>true);}
function get_query_var(){return array();}
function get_the_title($id=null){$p=get_post($id ?: get_the_ID());return $GLOBALS['content']->title($p->post_title,$p->ID);}
function the_title(){echo esc_html(get_the_title());}
function get_the_excerpt(){return $GLOBALS['content']->excerpt($GLOBALS['post']->post_excerpt,$GLOBALS['post']);}
function has_excerpt(){return '' !== $GLOBALS['post']->post_excerpt;}
function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function esc_attr($s){return esc_html($s);} function esc_url($s){return esc_html($s);}
function wp_strip_all_tags($s){return strip_tags($s);} function wp_trim_words($s){return $s;}
function get_permalink($id){return $GLOBALS['router']->localize_url(home_url('/item-'.$id.'/'));}
function post_class($s){echo 'class="'.esc_attr($s).'"';}
function get_the_terms(){return false;} function get_the_category(){return array();}
function get_post_meta(){return '';} function get_the_time(){return 0;}
function get_the_date(){return '2026-10-20';} function get_the_modified_date(){return '2026-10-20';}
function has_post_thumbnail(){return false;} function get_stylesheet_directory_uri(){return '/theme';}
function pera_ml_ui($s){return $s;} function pera_is_frontend_admin_equivalent(){return false;}
class WP_Post {public $ID;public $post_type;public $post_title;public $post_excerpt;public $post_content;}
class ArchiveRegistry {
    function enabled(){return array_fill_keys(array('en','zh','ru','de','ar'),true);}
    function get($code){return isset($this->enabled()[$code])?array('code'=>$code,'prefix'=>'en'===$code?'':$code,'enabled'=>true):null;}
    function from_prefix($code){return 'en'===$code?null:$this->get($code);}
    function all(){return array_map(array($this,'get'),array_keys($this->enabled()));}
}
class ArchiveStorage {
    public $status='current'; public $text=null; public $missing=false;
    public $translations=array('zh'=>array('中文房源标题','中文房源描述'),'ru'=>array('Заголовок недвижимости','Описание недвижимости'),'de'=>array('Deutscher Immobilientitel','Deutsche Immobilienbeschreibung'),'ar'=>array('عنوان العقار العربي','وصف العقار العربي'));
    function get($type,$id,$field,$lang,$source=''){
        if($this->missing || !isset($this->translations[$lang])) return null;
        return array('status'=>$this->status,'translated_text'=>$this->text ?? $this->translations[$lang]['post_title'===$field?0:1]);
    }
}
require dirname(__DIR__).'/includes/class-router.php';
require dirname(__DIR__).'/includes/class-content.php';
require dirname(__DIR__).'/includes/class-ajax.php';
function expect($ok,$label){if(!$ok){fwrite(STDERR,"FAIL $label\n");exit(1);}}
$registry=new ArchiveRegistry();$storage=new ArchiveStorage();
foreach(array('property','post') as $type){
    foreach(array(1,2) as $page){$p=new WP_Post();$p->ID=('property'===$type?100:200)+$page;$p->post_type=$type;$p->post_title='English title '.$page;$p->post_excerpt='English description '.$page;$p->post_content='English body';$GLOBALS['posts'][$p->ID]=$p;}
}
function request_context($path){
    $GLOBALS['admin']=$GLOBALS['ajax']=false;
    $GLOBALS['router']=new Pera_ML_Router(new ArchiveRegistry());
    $GLOBALS['content']=new Pera_ML_Content(null,$GLOBALS['router'],$GLOBALS['storage']);
    $_SERVER['REQUEST_URI']=$path;
    $GLOBALS['router']->detect_and_strip_prefix();
    $wp=(object)array('query_vars'=>array());
    $GLOBALS['router']->restore_public_uri($wp);
}
function card($type,$page){
    $GLOBALS['post']=$GLOBALS['posts'][('property'===$type?100:200)+$page];
    $theme=dirname(__DIR__,3).'/themes/hello-elementor-child';
    ob_start();include $theme.'/parts/'.('property'===$type?'property-card-v2':'post-card').'.php';return ob_get_clean();
}
function assert_card($type,$page,$language,$label){
    $html=card($type,$page);$strings=$GLOBALS['storage']->translations[$language];
    expect((bool)preg_match('/<h[23][^>]*>.*?'.preg_quote($strings[0],'/').'.*?<\/h[23]>/su',$html),$label.' actual card heading');
    expect((bool)preg_match('/class="[^"]*(?:property-card__excerpt|post-card-excerpt)[^"]*"[^>]*>\s*'.preg_quote($strings[1],'/').'/u',$html),$label.' actual card description');
    expect(false===strpos($html,'English title') && false===strpos($html,'English description'),$label.' no English card text');
}
foreach(array('zh','ru','de','ar') as $lang){
    foreach(array('property'=>array('/property/','/district/istanbul/','/property_type/apartment/','/property_tags/investment/','/region/istanbul/','/special/project/'),'post'=>array('/blog/','/category/regional-guides/','/tag/investment/')) as $type=>$paths){
        foreach($paths as $base){
            foreach(array(1,2) as $page){
                $url='/'.$lang.$base.($page>1?('property'===$type?'page/2/':'?paged=2'):'');
                request_context($url);assert_card($type,$page,$lang,'SSR/direct '.$url);
                foreach(array(false,true) as $authenticated){
                    request_ajax($lang,$type,$authenticated);
                    assert_card($type,$page,$lang,'AJAX render '.$url);
                }
            }
            // Header switcher URLs retain page 2 and establish a fresh SSR context.
            $switch=$GLOBALS['router']->url_for_language(home_url('/zh'.$base.('property'===$type?'page/2/':'?paged=2')),$lang);
            request_context(parse_url($switch,PHP_URL_PATH).(parse_url($switch,PHP_URL_QUERY)?'?'.parse_url($switch,PHP_URL_QUERY):''));
            assert_card($type,2,$lang,'switch language on page 2');
        }
    }
}
function request_ajax($lang,$type,$authenticated){
    // A real HTTP request constructs a new router and registers hooks once.
    $GLOBALS['router']=new Pera_ML_Router(new ArchiveRegistry());
    $GLOBALS['content']=new Pera_ML_Content(null,$GLOBALS['router'],$GLOBALS['storage']);
    $GLOBALS['hooks']=array();(new Pera_ML_Ajax(new ArchiveRegistry(),$GLOBALS['router']))->hooks();
    // Keep those callbacks/router together when dispatching the action.
    $GLOBALS['admin']=$GLOBALS['ajax']=true;
    $_POST=array('pera_ml_lang'=>$lang,'pera_ml_nonce'=>'pera_ml_property_filter','nonce'=>'pera_blog_search');
    $tag=($authenticated?'wp_ajax_':'wp_ajax_nopriv_').('property'===$type?'pera_filter_properties_v2':'pera_blog_search');
    expect(isset($GLOBALS['hooks'][$tag][0]),'context registered at priority zero before endpoint rendering');
    foreach($GLOBALS['hooks'][$tag][0] as $callback) call_user_func($callback);
}
foreach(array('property','post') as $type){
    request_ajax('de',$type,false);
    $storage->status='stale';assert_card($type,2,'de','stale translation remains visible');
    foreach(array('pending','failed') as $status){$storage->status=$status;expect(false!==strpos(card($type,2),'English description 2'),$status.' falls back');}
    $storage->status='current';$storage->text='   ';expect(false!==strpos(card($type,2),'English title 2'),'blank translation falls back');$storage->text=null;
    $storage->missing=true;expect(false!==strpos(card($type,2),'English description 2'),'missing translation falls back');$storage->missing=false;
    foreach(array('en','xx',array('de')) as $language){request_ajax($language,$type,false);expect(false!==strpos(card($type,2),'English description 2'),'English/invalid language falls back');}
}
foreach(array('property','post') as $type){
    $GLOBALS['nonce_valid']=false;
    try{request_ajax('de',$type,false);expect(false,'invalid nonce rejected');}catch(RuntimeException $e){expect('HTTP 403'===$e->getMessage(),'invalid nonce status');}
    expect(!Pera_ML_Ajax::has_archive_context(),'invalid nonce cannot enable admin translation');
    $GLOBALS['router']->set_request_language('de');
    expect('English title 2'===$GLOBALS['content']->title('English title 2',102),'unvalidated admin request remains canonical');
}
$GLOBALS['ajax']=false;
expect(!Pera_ML_Ajax::has_archive_context(),'ordinary admin is not public archive AJAX');
echo "Archive card language tests passed (zh, ru, de, ar; actual property/blog templates)\n";
