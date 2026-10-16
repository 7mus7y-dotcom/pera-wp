<?php
/** Exercise menu and SEO rendering with the actual translation readers. */
require __DIR__ . '/content-filter-test.php';
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function apply_filters( $tag, $value ) { return $value; }
function get_post_type() { return 'page'; }
function get_post_field() { return 'English page'; }
function get_post_meta() { return 'English SEO title'; }
function is_singular() { return true; }
function get_queried_object_id() { return 17; }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function is_wp_error() { return false; }
class WP_Term { public $term_id = 51; public $taxonomy = 'category'; public $name = 'English category'; public $description = ''; }
function get_term() { return new WP_Term(); }
require dirname( __DIR__ ) . '/includes/class-fields.php';
require dirname( __DIR__ ) . '/includes/class-ui.php';
require dirname( __DIR__ ) . '/includes/class-menu.php';
require dirname( __DIR__ ) . '/includes/class-seo.php';
$router = new class {
    public $language = 'de';
    public function current_language() { return $this->language; }
    public function is_translated() { return 'en' !== $this->language; }
    public function url_for_language( $url, $language ) { return '/' . $language . $url; }
};
$storage = new ContentTestStorage();
$content = new Pera_ML_Content( null, $router, $storage );
$fields = new Pera_ML_Fields( $router, $storage, null );
$registry = new class { public function register() {} };
$translator = new class { public function translate_and_store() { throw new RuntimeException( 'Frontend contacted provider' ); } };
$ui = new Pera_ML_UI( $router, $storage, $translator, $registry );
$menu = new Pera_ML_Menu( $router, $content, $fields, $ui );
$seo = new Pera_ML_SEO( null, $router, $storage );
$items = array(
    (object) array( 'ID' => 1, 'type' => 'post_type', 'object_id' => 17, 'title' => 'English page', 'url' => '/page/' ),
    (object) array( 'ID' => 2, 'type' => 'taxonomy', 'object_id' => 51, 'object' => 'category', 'title' => 'English category', 'url' => '/category/' ),
    (object) array( 'ID' => 3, 'type' => 'custom', 'title' => 'English custom', 'url' => '/custom/' ),
);
foreach ( array( 'current', 'stale' ) as $status ) {
    foreach ( array( false, true ) as $mismatch ) {
        $row = array( 'translated_text' => 'Gespeichert', 'status' => $status, 'is_stale' => $mismatch );
        $storage->rows = array_fill_keys( array( 'post_title', 'term_name', 'meta:seo_title', Pera_ML_UI::identity( 'English custom', 'menu.item.3.title' ) ), $row );
        foreach ( array( 'main_menu_v1', 'footer_menu', 'guidance' ) as $location ) {
            $out = $menu->translate_items( $items, (object) array( 'theme_location' => $location ) );
            foreach ( $out as $item ) expect_same( 'Gespeichert', $item->title, 'all menu kinds retain valid translations' );
            expect_same( '/de/custom/', $out[2]->url, 'menu language routing preserved' );
            expect_same( 'English custom', $items[2]->title, 'canonical menu object unchanged' );
        }
        expect_same( 'Gespeichert', $seo->document_title( 'English document title' ), 'SEO title retains valid translation' );
        $router->language = 'en';
        expect_same( $items, $menu->translate_items( $items, (object) array( 'theme_location' => 'footer_menu' ) ), 'English menus unchanged' );
        expect_same( 'English document title', $seo->document_title( 'English document title' ), 'English SEO unchanged' );
        $router->language = 'de';
    }
}
foreach ( array( null, array( 'translated_text' => '' ), array( 'translated_text' => array() ), array( 'translated_text' => 'Pending', 'status' => 'pending' ) ) as $row ) {
    $storage->rows['meta:seo_title'] = $row;
    expect_same( 'English document title', $seo->document_title( 'English document title' ), 'missing or invalid SEO translation falls back' );
}
echo "Pera ML stale frontend integration tests passed\n";
