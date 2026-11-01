<?php
/** Run with PHP CLI. Isolated WP hook harness; no WordPress/database required. */
define( 'ABSPATH', __DIR__ );
$GLOBALS['hooks'] = array();
function add_action( $hook, $callback, $priority = 10 ) { $GLOBALS['hooks'][$hook][$priority][] = $callback; }
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { add_action( $hook, $callback, $priority ); }
function is_admin() { return ( $GLOBALS['context'] ?? '' ) === 'admin'; }
function is_user_logged_in() { return ( $GLOBALS['context'] ?? '' ) === 'authenticated'; }
function status_header( $status ) { $GLOBALS['status'] = $status; }
function nocache_headers() { $GLOBALS['nocache'] = true; }
function esc_html( $text ) { return htmlspecialchars( $text, ENT_QUOTES ); }
function get_the_author() { return 'Author & Name'; }
function wp_parse_url( $url ) { return parse_url( $url ); }
function home_url() { return $GLOBALS['author_home'] ?? 'https://www.peraproperty.com/'; }
// Standalone adapters for WP's tokenizer/tag API; DOM reads attributes only.
// Production uses WordPress's HTML API and never serializes the fragment.
function wp_html_split( $html ) { return preg_split( '~(<!--.*?-->|<(?:[^>\'\"]|\"[^\"]*\"|\'[^\']*\')*>)~s', $html, -1, PREG_SPLIT_DELIM_CAPTURE ); }
class WP_HTML_Tag_Processor {
	private $anchor;
	public function __construct( $html ) {
		$dom = new DOMDocument();
		@$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
		$this->anchor = $dom->getElementsByTagName( 'a' )->item( 0 );
	}
	public function next_tag( $name ) { return null !== $this->anchor; }
	public function get_attribute( $name ) { return $this->anchor->hasAttribute( $name ) ? $this->anchor->getAttribute( $name ) : null; }
}
class Pera_ML_Plugin {
	public static function instance() { return new self(); }
	public function registry() { return $this; }
	public function enabled() { return array_map( static function ( $prefix ) { return array( 'prefix' => $prefix ); }, array( '', 'de', 'ar', 'zh', 'ru', 'tr' ) ); }
}
require dirname( __DIR__ ) . '/inc/modules/author-archives.php';
require dirname( __DIR__ ) . '/inc/modules/sitemaps.php';

function author_expect( $expected, $actual, $label ) {
	if ( $expected !== $actual ) { fwrite( STDERR, "FAIL: $label\n" ); exit( 1 ); }
}

// Run terminating callbacks in their own process and capture the final response.
if ( isset( $argv[1] ) && 'request' === $argv[1] ) {
	$wp = (object) array( 'query_vars' => json_decode( $argv[2], true ) );
	$GLOBALS['context'] = $argv[3];
	if ( in_array( $argv[3], array( 'REST_REQUEST', 'DOING_AJAX', 'DOING_CRON', 'XMLRPC_REQUEST' ), true ) ) define( $argv[3], true );
	ob_start();
	register_shutdown_function( static function () {
		$body = ob_get_clean();
		echo json_encode( array( $GLOBALS['status'] ?? 200, $body, $GLOBALS['nocache'] ?? false ) );
	} );
	pera_author_archives_maybe_retire( $wp );
	echo 'normal response';
	exit;
}

$cases = array(
	'/author/admin/' => array( 'author_name' => 'admin' ),
	'/author/admin/page/2/' => array( 'author_name' => 'admin', 'paged' => 2 ),
	'/?author=1' => array( 'author' => '1' ),
	'/?author_name=admin' => array( 'author_name' => 'admin' ),
	'/author/unknown/' => array( 'author_name' => 'unknown' ),
	'/author/admin/feed/' => array( 'author_name' => 'admin', 'feed' => 'rss2' ),
);
foreach ( array( 'de', 'ar', 'zh', 'ru', 'tr' ) as $language ) {
	$cases[ '/' . $language . '/author/admin/page/2/' ] = array( 'author_name' => 'admin', 'paged' => 2, 'pera_ml_lang' => $language );
}
function author_request( $vars, $context = '' ) {
	$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' request ' . escapeshellarg( json_encode( $vars ) ) . ' ' . escapeshellarg( $context );
	return json_decode( shell_exec( $command ), true );
}
foreach ( $cases as $label => $vars ) {
	author_expect( array( 410, "Gone\n", true ), author_request( $vars ), $label . ' ends before redirects/head output' );
	author_expect( array( 410, "Gone\n", true ), author_request( $vars, 'authenticated' ), $label . ' also retired for logged-in frontend visitors' );
}
foreach ( array(
	'normal blog post' => array( 'name' => 'blog-post' ),
	'blog archive' => array( 'pagename' => 'blog' ),
	'property listing' => array( 'post_type' => 'property', 'name' => 'property' ),
	'multilingual blog post' => array( 'name' => 'blog-post', 'pera_ml_lang' => 'ar' ),
	'XML sitemap' => array( 'sitemap' => 'index' ),
	'REST query URL' => array( 'rest_route' => '/wp/v2/posts', 'author' => 1 ),
) as $label => $vars ) {
	author_expect( array( 200, 'normal response', false ), author_request( $vars ), $label . ' passes through' );
}
foreach ( array( 'admin', 'REST_REQUEST', 'DOING_AJAX', 'DOING_CRON', 'XMLRPC_REQUEST' ) as $context ) {
	author_expect( array( 200, 'normal response', false ), author_request( array( 'author' => 1 ), $context ), $context . ' preserved' );
}
author_expect( true, isset( $GLOBALS['hooks']['parse_request'][1] ), 'runs after multilingual restoration at priority 0' );
$provider_filter = $GLOBALS['hooks']['wp_sitemaps_add_provider'][10][0];
author_expect( false, $provider_filter( new stdClass(), 'users' ), 'authors excluded from sitemap index and direct provider requests' );
$provider = new stdClass();
author_expect( $provider, $provider_filter( $provider, 'posts' ), 'post sitemap preserved' );
author_expect( $provider, $provider_filter( $provider, 'taxonomies' ), 'taxonomy sitemap preserved' );
$byline = $GLOBALS['hooks']['the_author_posts_link'][10][0];
author_expect( 'Author &amp; Name', $byline( '<a href="/author/admin/">Author &amp; Name</a>' ), 'escaped visible byline' );
author_expect( '<span>Author</span>', pera_author_archives_unlink( '<span><a href="/ar/author/admin/">Author</a></span>' ), 'blocks and author lists retain names' );
foreach ( array(
	'/author/admin/', '/author/admin/page/2/', '/de/author/admin/',
	'https://www.peraproperty.com/zh/author/admin/', '//www.peraproperty.com/author/admin/',
	'/?author=1', '?author_name=admin', '/ar/?author_name=admin&amp;paged=2',
) as $url ) {
	author_expect( '<strong>Author &amp; Name</strong>', pera_author_archives_unlink( '<a class="fn" href="' . $url . '"><strong>Author &amp; Name</strong></a>' ), 'only anchor wrapper removed: ' . $url );
}
$unrelated = '<a href="https://external.test/author/admin/">External profile</a><a href="/contact/">Contact</a><a href="/blog/?guest_author=1">Blog</a><a href="#author">Jump</a><a href="mailto:author@example.test">Mail</a><a href="/author-guide/">Guide</a><a title="a > b" href="/property/">Property</a>';
$unrelated .= '<a href="/wp-admin/edit.php?author=1">Admin</a><a href="/wp-json/wp/v2/posts?author=1">REST</a><a href="/?rest_route=/wp/v2/posts&amp;author=1">REST query</a>';
$mixed = '<div><a href="/author/admin/"><em>Author</em><img src="/avatar.png" alt="Author"></a><p>' . $unrelated . '</p></div>';
author_expect( '<div><em>Author</em><img src="/avatar.png" alt="Author"><p>' . $unrelated . '</p></div>', pera_author_archives_unlink( $mixed ), 'mixed biography keeps unrelated links and safe markup exactly' );
author_expect( $unrelated, $byline( $unrelated ), 'linked-byline filter also preserves unrelated links' );
author_expect( '<a href="/author/admin/">Unclosed', pera_author_archives_unlink( '<a href="/author/admin/">Unclosed' ), 'incomplete markup left intact' );
$GLOBALS['author_home'] = 'https://www.peraproperty.com/site/';
$GLOBALS['wp_rewrite'] = (object) array( 'author_base' => 'writers' );
author_expect( 'Author', pera_author_archives_unlink( '<a href="/site/de/writers/admin/">Author</a>' ), 'subdirectory and configured author base' );
author_expect( '<a href="/writers/admin/">Other site</a>', pera_author_archives_unlink( '<a href="/writers/admin/">Other site</a>' ), 'outside site path preserved' );
unset( $GLOBALS['author_home'], $GLOBALS['wp_rewrite'] );
$GLOBALS['context'] = 'authenticated';
author_expect( '<em>Author</em>' . $unrelated, pera_author_archives_unlink( '<a href="/author/admin/"><em>Author</em></a>' . $unrelated ), 'logged-in frontend link handling matches anonymous visitors' );
author_expect( array( 200, 'normal response', false ), author_request( array( 'name' => 'blog-post' ), 'authenticated' ), 'normal logged-in frontend preserved' );
$GLOBALS['context'] = 'admin';
author_expect( '<a>Author</a>', pera_author_archives_unlink( '<a>Author</a>' ), 'admin output preserved' );
$seo = file_get_contents( dirname( __DIR__ ) . '/inc/seo-all.php' );
author_expect( false, strpos( $seo, 'get_author_posts_url' ), 'schema has no retired archive URL' );
author_expect( true, strpos( $seo, "'name'  => \$author_name" ) !== false, 'schema retains individual author name' );
$template = file_get_contents( dirname( __DIR__ ) . '/single-post.php' );
author_expect( false, strpos( $template, 'get_author_posts_url' ), 'template has no archive link' );
echo "Author archive regression tests passed\n";
