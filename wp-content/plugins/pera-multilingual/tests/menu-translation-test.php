<?php
/** Shared footer/off-canvas WordPress menu translation regressions. */
define( 'ABSPATH', __DIR__ );
$GLOBALS['menu_admin'] = false;
function is_admin() { return $GLOBALS['menu_admin']; }
function absint( $value ) { return abs( (int) $value ); }
function is_wp_error() { return false; }
function add_filter() {}
function get_post_field( $field, $id ) { return 41 === (int) $id ? 'Canonical page' : ''; }
class WP_Term { public $term_id = 51; public $taxonomy = 'category'; public $name = 'Canonical category'; }
function get_term( $id ) { return 51 === (int) $id ? new WP_Term() : null; }
function menu_expect( $expected, $actual, $label ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAIL {$label}\n" . var_export( $actual, true ) . "\n" ); exit( 1 ); } }

final class Menu_Router {
	public $language = 'zh';
	public function current_language() { return $this->language; }
	public function is_translated() { return 'en' !== $this->language; }
	public function url_for_language( $url, $language ) { return 0 === strpos( $url, 'https://pera.test/' ) ? 'https://pera.test/' . $language . '/' . substr( $url, 18 ) : $url; }
}
final class Menu_Content { public function title( $source, $id ) { return 'zh' === $GLOBALS['menu_router']->language ? '页面标题' : strtoupper( $GLOBALS['menu_router']->language ) . ' page'; } }
final class Menu_Fields { public function term() { return 'zh' === $GLOBALS['menu_router']->language ? '行政区' : strtoupper( $GLOBALS['menu_router']->language ) . ' term'; } }
final class Menu_UI {
	public $calls = array();
	public function get( $source, $key ) {
		$this->calls[] = array( $source, $key );
		if ( 61 === (int) substr( $key, 10 ) ) return 'zh' === $GLOBALS['menu_router']->language ? '自定义标签' : strtoupper( $GLOBALS['menu_router']->language ) . ' custom';
		if ( 64 === (int) substr( $key, 10 ) ) return '   ';
		return $source; // missing, stale and non-current stored rows all reach this safe contract.
	}
}

require dirname( __DIR__ ) . '/includes/class-menu.php';
$GLOBALS['menu_router'] = new Menu_Router();
$ui = new Menu_UI();
$menu = new Pera_ML_Menu( $GLOBALS['menu_router'], new Menu_Content(), new Menu_Fields(), $ui );
$items = array(
	(object) array( 'ID' => 60, 'type' => 'post_type', 'object_id' => 41, 'title' => 'Edited menu label', 'url' => 'https://pera.test/about/', 'classes' => array( 'menu-item', 'current-menu-item' ) ),
	(object) array( 'ID' => 62, 'type' => 'taxonomy', 'object' => 'category', 'object_id' => 51, 'title' => 'Edited term label', 'url' => 'https://pera.test/category/guides/' ),
	(object) array( 'ID' => 61, 'type' => 'custom', 'object_id' => 61, 'title' => 'Custom label', 'url' => 'https://external.test/path' ),
	(object) array( 'ID' => 63, 'type' => 'custom', 'title' => 'Missing label', 'url' => '#section' ),
	(object) array( 'ID' => 64, 'type' => 'custom', 'title' => 'Blank label', 'url' => 'mailto:test@example.com' ),
);

foreach ( array( 'zh', 'ar', 'de' ) as $language ) {
	$GLOBALS['menu_router']->language = $language;
	foreach ( array( 'footer_menu', 'guidance', 'main_menu_v1' ) as $location ) {
		$out = $menu->translate_items( $items, (object) array( 'theme_location' => $location ) );
		menu_expect( 'zh' === $language ? '页面标题' : strtoupper( $language ) . ' page', $out[0]->title, "{$language} {$location} page title" );
		menu_expect( 'zh' === $language ? '行政区' : strtoupper( $language ) . ' term', $out[1]->title, "{$language} {$location} term title" );
		menu_expect( 'https://pera.test/' . $language . '/category/guides/', $out[1]->url, "{$language} {$location} category URL localized" );
		menu_expect( 'zh' === $language ? '自定义标签' : strtoupper( $language ) . ' custom', $out[2]->title, "{$language} {$location} custom title" );
		menu_expect( 'Missing label', $out[3]->title, 'missing/stale/non-current custom translation fallback' );
		menu_expect( 'Blank label', $out[4]->title, 'blank custom translation fallback' );
		menu_expect( 'https://pera.test/' . $language . '/about/', $out[0]->url, 'internal URL localized' );
		menu_expect( 'https://external.test/path', $out[2]->url, 'external URL unchanged' );
		menu_expect( $items[0]->classes, $out[0]->classes, 'classes/current state unchanged' );
		menu_expect( 60, $out[0]->ID, 'menu item ID unchanged' );
		menu_expect( 'Edited menu label', $items[0]->title, 'canonical object not mutated' );
	}
}
menu_expect( array( 'Custom label', 'menu.item.61.title' ), $ui->calls[0], 'custom identity includes stable menu item ID' );
$GLOBALS['menu_router']->language = 'en';
menu_expect( $items, $menu->translate_items( $items, (object) array( 'theme_location' => 'main_menu_v1' ) ), 'English menu objects unchanged' );
$GLOBALS['menu_admin'] = true;
$GLOBALS['menu_router']->language = 'zh';
menu_expect( $items, $menu->translate_items( $items, (object) array( 'theme_location' => 'footer_menu' ) ), 'wp-admin menu editing unchanged' );
menu_expect( true, ! method_exists( $ui, 'translate_and_store' ), 'frontend menu path has no provider method' );
echo "Pera ML menu translation tests passed\n";
