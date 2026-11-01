<?php
/** Standalone behavior regression. Run: php tests/regional-guides-map-test.php */
define( 'ABSPATH', __DIR__ );
$GLOBALS['hooks'] = array(); $GLOBALS['meta'] = array(); $GLOBALS['category'] = true; $GLOBALS['allowed'] = true; $GLOBALS['revision'] = false; $GLOBALS['autosave'] = false;
function add_action($key,$fn){$GLOBALS['hooks'][$key]=$fn;}
function get_post_meta($id,$key){return $GLOBALS['meta'][$id][$key] ?? '';}
function update_post_meta($id,$key,$value){$GLOBALS['meta'][$id][$key]=$value;}
function delete_post_meta($id,$key){unset($GLOBALS['meta'][$id][$key]);}
function wp_is_post_autosave(){return $GLOBALS['autosave'];} function wp_is_post_revision(){return $GLOBALS['revision'];}
function current_user_can(){return $GLOBALS['allowed'];} function wp_verify_nonce($nonce){return 'valid' === $nonce;}
function wp_unslash($s){return $s;} function sanitize_text_field($s){return trim(strip_tags($s));}
function is_category($slug){return 'regional-guides' === $slug && $GLOBALS['category'];}
function pera_ml_ui($s){return $s;} function wp_json_encode($v){return json_encode($v);}
function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');} function esc_attr($s){return esc_html($s);}
function esc_url($s){return esc_html(preg_match('#^https?://#',$s)?$s:'');}
function get_permalink($id){return 'https://example.test/guide-'.$id.'/';}
function pera_ml_url($url){return str_replace('example.test/','example.test/'.$GLOBALS['language'].'/',$url);}
function get_the_title($id){return '<script>alert(1)</script>Guide '.$id;}
function get_posts($args){
    $GLOBALS['query_args']=$args;
    return array_values(array_filter($GLOBALS['posts'],function($p)use($args){
        return $p->category === $args['category_name'] && $p->post_status === $args['post_status'] && '1' === get_post_meta($p->ID,'_pera_guide_map_enabled');
    }));
}
function expect($ok,$message){if(!$ok)throw new RuntimeException($message);}
require dirname(__DIR__).'/inc/modules/regional-guides-map.php';
foreach(array('',null,[],INF,NAN,'90x','<b>41</b>') as $value)expect(null === pera_regional_map_coordinate($value,90),'invalid latitude');
foreach(array('-90','90','0','41.02566') as $value)expect(null !== pera_regional_map_coordinate($value,90),'valid latitude');
expect(null === pera_regional_map_coordinate(90.001,90),'latitude range');
expect(null === pera_regional_map_coordinate(-180.1,180),'longitude range');
expect(180.0 === pera_regional_map_coordinate('180',180),'longitude boundary');
$_POST=array('pera_guide_map_nonce'=>'valid','pera_guide_map'=>array('lat'=>'41.1','lng'=>'29.1','label'=>'<b>Place</b>','enabled'=>'1'));
pera_regional_map_save(1);
expect('41.1' === get_post_meta(1,'_pera_guide_map_lat'),'latitude saved');
expect('Place' === get_post_meta(1,'_pera_guide_map_label'),'label sanitized');
expect('1' === get_post_meta(1,'_pera_guide_map_enabled'),'opt in saved');
$before=$GLOBALS['meta'];
foreach(array('nonce','capability','revision','autosave','missing') as $guard){
    $_POST['pera_guide_map_nonce']='nonce' === $guard?'invalid':'valid';
    $GLOBALS['allowed']='capability' !== $guard;$GLOBALS['revision']='revision' === $guard;$GLOBALS['autosave']='autosave' === $guard;
    if('missing' === $guard)unset($_POST['pera_guide_map']);
    pera_regional_map_save(1);expect($before === $GLOBALS['meta'],'save guard '.$guard);
}
$GLOBALS['allowed']=true;$GLOBALS['revision']=false;
$_POST['pera_guide_map']=array('lat'=>'100','lng'=>array('bad'),'enabled'=>'1');pera_regional_map_save(1);
expect('' === get_post_meta(1,'_pera_guide_map_lat') && '0' === get_post_meta(1,'_pera_guide_map_enabled'),'invalid values removed and opt in disabled');
$_POST['pera_guide_map']=array('lat'=>'41','lng'=>'29');pera_regional_map_save(1);
expect('0' === get_post_meta(1,'_pera_guide_map_enabled'),'unchecked disables');
$GLOBALS['posts']=array();
foreach(range(1,20) as $id){$GLOBALS['posts'][]=(object)array('ID'=>$id,'post_status'=>20===$id?'draft':'publish','category'=>19===$id?'buyer-guides':'regional-guides');$GLOBALS['meta'][$id]=array('_pera_guide_map_enabled'=>'1','_pera_guide_map_lat'=>'41','_pera_guide_map_lng'=>'29');}
unset($GLOBALS['meta'][2]['_pera_guide_map_lat']);$GLOBALS['meta'][3]['_pera_guide_map_enabled']='0';$GLOBALS['meta'][4]['_pera_guide_map_lng']='999';
foreach(array('en','zh','ru','de','ar') as $lang){$GLOBALS['language']=$lang;$guides=pera_regional_map_guides();expect(15 === count($guides),'all published enabled valid guides across pages');expect(false !== strpos($guides[0]['url'],'/'.$lang.'/'),'URL routed in '.$lang);}
expect(-1 === $GLOBALS['query_args']['posts_per_page'] && !isset($GLOBALS['query_args']['paged']),'pagination independent query');
expect(false === $GLOBALS['query_args']['has_password'],'password protected articles excluded');
ob_start();pera_render_regional_guides_map();$html=ob_get_clean();
expect(false === strpos($html,'<script>'),'output escaped');expect(false !== strpos($html,'&lt;script&gt;'),'escaped article link present');
expect(false !== strpos($html,'https://example.test/ar/guide-1/'),'crawlable localized links');
expect(3 === count(pera_regional_map_landmarks()),'three landmarks');
foreach(pera_regional_map_landmarks() as $landmark)expect(!isset($landmark['url']),'landmarks have no article links');
$GLOBALS['category']=false;ob_start();pera_render_regional_guides_map();expect('' === ob_get_clean(),'other archives unchanged');
$archive=file_get_contents(dirname(__DIR__).'/archive.php');
expect(strpos($archive,'pera_render_regional_guides_map();') < strpos($archive,'data-blog-results'),'map outside AJAX results');
foreach(array('paginate_links(', 'get_template_part( \'parts/post-card\' )','pera_render_blog_archive_sort_control','pera_render_faq_schema') as $contract)expect(false !== strpos($archive,$contract),'archive retains '.$contract);
echo "Regional Guides map PHP regressions passed\n";
