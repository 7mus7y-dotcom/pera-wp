<?php
// Standalone: php wp-content/plugins/pera-webp-tools/tests/naming-test.php
const ABSPATH = __DIR__;
function add_filter( ...$args ) {}
function add_action( ...$args ) {}
function wp_upload_dir() {
    return array( 'baseurl' => 'https://example.test/uploads', 'basedir' => $GLOBALS['fixture_dir'] );
}
require dirname( __DIR__ ) . '/pera-webp-tools.php';
class Test_WebP_Tools extends Pera_WebP_Tools {
    public static function swap( $url ) { return parent::maybe_swap_single_url_to_webp( $url ); }
}
function same( $actual, $expected ) {
    if ( $actual !== $expected ) { throw new RuntimeException( var_export( array( $actual, $expected ), true ) ); }
}
$fixture_dir = sys_get_temp_dir() . '/pera-webp-' . bin2hex( random_bytes( 8 ) );
mkdir( $fixture_dir );
$fixtures = array( 'pair.jpg', 'pair.jpeg', 'pair.webp', 'old.PNG', 'old.webp', 'space name.jpg', 'space name.jpg.webp', 'zero.png', 'zero.png.webp' );
try {
    foreach ( $fixtures as $name ) { file_put_contents( "$fixture_dir/$name", 'fixture' ); }
    file_put_contents( "$fixture_dir/zero.png.webp", '' );
    same( Pera_WebP_Tools::existing_webp_path( "$fixture_dir/pair.jpg" ), '' );
    same( Pera_WebP_Tools::existing_webp_path( "$fixture_dir/pair.jpeg" ), '' );
    same( Pera_WebP_Tools::existing_webp_path( "$fixture_dir/old.PNG" ), "$fixture_dir/old.webp" );
    same( Pera_WebP_Tools::existing_webp_path( "$fixture_dir/zero.png" ), '' );
    $base = 'https://example.test/uploads/';
    same( Test_WebP_Tools::swap( $base . 'pair.jpg' ), $base . 'pair.jpg' );
    same( Test_WebP_Tools::swap( $base . 'old.PNG?v=1#image' ), $base . 'old.webp?v=1#image' );
    same( Test_WebP_Tools::swap( $base . 'space%20name.jpg?v=1' ), $base . 'space%20name.jpg.webp?v=1' );
    same( Test_WebP_Tools::swap( 'https://other.test/uploads/old.PNG' ), 'https://other.test/uploads/old.PNG' );
    same( Test_WebP_Tools::swap( $base . 'missing.jpg' ), $base . 'missing.jpg' );
    file_put_contents( "$fixture_dir/pair.jpg.webp", 'jpg-sidecar' );
    file_put_contents( "$fixture_dir/pair.jpeg.webp", 'jpeg-sidecar' );
    same( Test_WebP_Tools::swap( $base . 'pair.jpg' ), $base . 'pair.jpg.webp' );
    same( Test_WebP_Tools::swap( $base . 'pair.jpeg' ), $base . 'pair.jpeg.webp' );
    $attrs = Test_WebP_Tools::maybe_swap_attachment_image_attrs_to_webp(
        array( 'src' => $base . 'pair.jpg', 'srcset' => $base . 'pair.jpg 400w, ' . $base . 'old.PNG 800w' ), null, null
    );
    same( $attrs['src'], $base . 'pair.jpg.webp' );
    same( $attrs['srcset'], $base . 'pair.jpg.webp 400w, ' . $base . 'old.webp 800w' );
    echo "PASS: naming, collisions, legacy fallback, URL suffixes, external URLs, srcset\n";
} finally {
    foreach ( scandir( $fixture_dir ) as $name ) {
        if ( '.' !== $name && '..' !== $name ) { unlink( "$fixture_dir/$name" ); }
    }
    rmdir( $fixture_dir );
}
