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
foreach ( array( 'admin', 'authenticated', 'REST_REQUEST', 'DOING_AJAX', 'DOING_CRON', 'XMLRPC_REQUEST' ) as $context ) {
	author_expect( array( 200, 'normal response', false ), author_request( array( 'author' => 1 ), $context ), $context . ' preserved' );
}
author_expect( true, isset( $GLOBALS['hooks']['parse_request'][1] ), 'runs after multilingual restoration at priority 0' );
$provider_filter = $GLOBALS['hooks']['wp_sitemaps_add_provider'][10][0];
author_expect( false, $provider_filter( new stdClass(), 'users' ), 'authors excluded from sitemap index and direct provider requests' );
$provider = new stdClass();
author_expect( $provider, $provider_filter( $provider, 'posts' ), 'post sitemap preserved' );
author_expect( $provider, $provider_filter( $provider, 'taxonomies' ), 'taxonomy sitemap preserved' );
$byline = $GLOBALS['hooks']['the_author_posts_link'][10][0];
author_expect( 'Author &amp; Name', $byline( '<a href="/author/admin/">Author</a>' ), 'escaped visible byline' );
author_expect( '<span>Author</span>', pera_author_archives_unlink( '<span><a href="/ar/author/admin/">Author</a></span>' ), 'blocks and author lists retain names' );
$GLOBALS['context'] = 'admin';
author_expect( '<a>Author</a>', pera_author_archives_unlink( '<a>Author</a>' ), 'admin output preserved' );
$seo = file_get_contents( dirname( __DIR__ ) . '/inc/seo-all.php' );
author_expect( false, strpos( $seo, 'get_author_posts_url' ), 'schema has no retired archive URL' );
author_expect( true, strpos( $seo, "'name'  => \$author_name" ) !== false, 'schema retains individual author name' );
$template = file_get_contents( dirname( __DIR__ ) . '/single-post.php' );
author_expect( false, strpos( $template, 'get_author_posts_url' ), 'template has no archive link' );
echo "Author archive regression tests passed\n";
