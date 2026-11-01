<?php
/** Run each case in a fresh PHP process to test request-local cache constants. */
define( 'ABSPATH', __DIR__ );
$role = $argv[1] ?? 'anonymous';
$GLOBALS['manage_options'] = in_array($role,array('administrator','administrator-other'),true);
$GLOBALS['edit_posts'] = in_array($role,array('employee','administrator','administrator-other'),true);
$GLOBALS['category'] = 'administrator-other' !== $role;
$GLOBALS['hooks']=array();$GLOBALS['assets']=array();$GLOBALS['queries']=0;$GLOBALS['nocache']=0;
function add_action($hook,$callback,$priority=10){$GLOBALS['hooks'][$hook]=$callback;}
function current_user_can($cap){return 'manage_options' === $cap ? $GLOBALS['manage_options'] : $GLOBALS['edit_posts'];}
function is_user_logged_in(){return 'anonymous' !== $GLOBALS['role'];}
function is_category($slug){return 'regional-guides' === $slug && $GLOBALS['category'];}
function get_stylesheet_directory_uri(){return '/theme';} function pera_get_asset_version(){return 'test';}
function wp_enqueue_style($handle){$GLOBALS['assets'][]=$handle;}
function wp_enqueue_script($handle){$GLOBALS['assets'][]=$handle;}
function nocache_headers(){$GLOBALS['nocache']++;}
function get_posts(){$GLOBALS['queries']++;return array((object)array('ID'=>1,'post_status'=>'publish'));}
function get_post_meta($id,$key){return '_pera_guide_map_lat' === $key ? '41' : ('_pera_guide_map_lng' === $key ? '29' : '1');}
function get_the_title(){return 'Guide';} function get_permalink(){return 'https://example.test/guide/';}
function pera_ml_url($url){return str_replace('example.test/','example.test/'.$GLOBALS['language'].'/',$url);}
function pera_ml_ui($s){return $s;} function wp_json_encode($v){return json_encode($v);}
function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');} function esc_attr($s){return esc_html($s);} function esc_url($s){return esc_html($s);}
function get_current_screen(){return (object)array('post_type'=>$GLOBALS['screen_type'] ?? 'post');}
function add_meta_box($id,$title,$callback){$GLOBALS['metabox']=$callback;}
function wp_nonce_field(){} function checked(){}
function expect($ok,$label){if(!$ok)throw new RuntimeException($label);}
require dirname(__DIR__).'/inc/modules/regional-guides-map.php';
$visible = 'administrator' === $role;
// Neither language nor pagination changes capability gating on the canonical category.
foreach(array('en','zh','ru','de','ar') as $language){
    foreach(array(1,2) as $page){
        $GLOBALS['language']=$language;$_SERVER['REQUEST_URI']='/'.$language.'/category/regional-guides/'.(2===$page?'page/2/':'');
        $GLOBALS['assets']=array();$GLOBALS['queries']=0;
        expect($visible === pera_regional_map_visible(),'gate for '.$role);
        $GLOBALS['hooks']['wp_enqueue_scripts']();
        expect($visible ? in_array('pera-leaflet',$GLOBALS['assets'],true) && in_array('pera-regional-map',$GLOBALS['assets'],true) : array() === $GLOBALS['assets'],'frontend asset isolation '.$role);
        ob_start();pera_render_regional_guides_map();$html=ob_get_clean();
        if($visible){
            expect(false !== strpos($html,'data-map-config='),'admin map payload');
            expect(false !== strpos($html,'Explore Istanbul by neighbourhood'),'admin heading');
            expect(false !== strpos($html,'Galata Tower'),'admin landmarks');
            expect(false !== strpos($html,'https://example.test/'.$language.'/guide/'),'localized admin link');
            expect(1 === $GLOBALS['queries'],'admin map query');
        }else{
            expect('' === $html,'no map HTML, headings, landmarks, links or coordinate payload for '.$role);
            expect(0 === $GLOBALS['queries'],'no map retrieval for '.$role);
        }
    }
}
$GLOBALS['hooks']['wp']();
expect($visible === defined('DONOTCACHEPAGE'),'cache bypass scoped to administrator map response');
expect(($visible ? 1 : 0) === $GLOBALS['nocache'],'no-cache headers scoped to administrator map response');
if($visible)expect(true === DONOTCACHEPAGE,'cache flag enabled');
// Backend registration/picker remain independent of manage_options.
if($GLOBALS['edit_posts']){
    $GLOBALS['hooks']['add_meta_boxes_post']();
    expect('pera_regional_map_metabox' === $GLOBALS['metabox'],'post editor metabox retained');
    ob_start();pera_regional_map_metabox((object)array('ID'=>1));$html=ob_get_clean();
    expect(false !== strpos($html,'pera-guide-map-lat') && false !== strpos($html,'data-regional-map-picker'),'manual coordinates and picker retained');
    $GLOBALS['assets']=array();$GLOBALS['hooks']['admin_enqueue_scripts']('post.php');
    expect(in_array('pera-leaflet',$GLOBALS['assets'],true) && in_array('pera-regional-map-admin',$GLOBALS['assets'],true),'employee/editor mapping assets retained');
    $GLOBALS['assets']=array();$GLOBALS['hooks']['admin_enqueue_scripts']('edit.php');expect(array() === $GLOBALS['assets'],'unrelated admin screen unchanged');
    $GLOBALS['screen_type']='page';$GLOBALS['hooks']['admin_enqueue_scripts']('post.php');expect(array() === $GLOBALS['assets'],'other post type unchanged');
}
echo "Regional map visibility passed: {$role}\n";
