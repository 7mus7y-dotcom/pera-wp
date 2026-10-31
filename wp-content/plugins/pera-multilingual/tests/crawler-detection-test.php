<?php
define( 'ABSPATH', __DIR__ );
define( 'PERA_ML_GEO_CRAWLER_MODE', 'dns' );
define( 'PERA_ML_GEO_REDIRECT_ENABLED', true );
define( 'PERA_ML_GEO_CACHE_READY', true );
$options = array(); $events = array(); $count = 0;
function get_option( $key, $default = false ) { return $GLOBALS['options'][$key] ?? $default; }
function update_option( $key, $value, $autoload = false ) { $GLOBALS['options'][$key] = $value; }
function add_option( $key, $value, $unused = '', $autoload = false ) { if ( isset( $GLOBALS['options'][$key] ) ) return false; $GLOBALS['options'][$key] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['options'][$key] ); }
function wp_next_scheduled( $name ) { return $GLOBALS['events'][$name] ?? false; }
function wp_schedule_single_event( $time, $name ) { $GLOBALS['events'][$name] = $time; }
function add_action() {}
function assert_crawler( $expected, $actual, $label ) { ++$GLOBALS['count']; if ( $expected !== $actual ) { fwrite( STDERR, "FAIL $label: " . var_export( $actual, true ) . "\n" ); exit( 1 ); } }
require dirname( __DIR__ ) . '/includes/class-local-geoip.php';
require dirname( __DIR__ ) . '/includes/class-crawler-detection.php';
require dirname( __DIR__ ) . '/includes/class-geo-redirect.php';
$reverse = static function ( $ip ) { return 'crawl-66-249-66-1.googlebot.com'; };
$forward = static function ( $name ) { return array( array( 'ip' => '66.249.66.1' ) ); };
assert_crawler( 'bot', Pera_ML_Crawler_Detection::verify( '66.249.66.1', $reverse, $forward ), 'Google FCrDNS' );
assert_crawler( '', Pera_ML_Crawler_Detection::verify( '66.249.66.2', $reverse, $forward ), 'PTR without matching forward is unknown' );
assert_crawler( 'visitor', Pera_ML_Crawler_Detection::verify( '66.249.66.1', static function () { return 'googlebot.com.evil.example.org'; }, $forward ), 'suffix boundary is not provider verification' );
assert_crawler( '', Pera_ML_Crawler_Detection::verify( '66.249.66.1', static function ( $ip ) { return $ip; }, $forward ), 'missing PTR fails closed' );
assert_crawler( '', Pera_ML_Crawler_Detection::verify( '66.249.66.1', $reverse, static function () { return false; } ), 'forward DNS failure fails closed' );
assert_crawler( '', Pera_ML_Crawler_Detection::verify( '66.249.66.1', static function () { throw new RuntimeException( 'DNS unavailable' ); }, $forward ), 'resolver exception fails closed' );
assert_crawler( 'bot', Pera_ML_Crawler_Detection::verify( '2001:4860:4801:10::1', static function () { return 'crawler.googlebot.com.'; }, static function () { return array( array( 'ipv6' => '2001:4860:4801:0010::1' ) ); } ), 'IPv6 normalized confirmation' );
assert_crawler( 'bot', Pera_ML_Crawler_Detection::verify( '13.66.139.0', static function () { return 'msnbot.search.msn.com'; }, static function () { return array( array( 'ip' => '13.66.139.0' ) ); } ), 'Bing FCrDNS' );
$detector = new Pera_ML_Crawler_Detection();
$_SERVER = array( 'REMOTE_ADDR' => '81.2.69.160', 'HTTP_USER_AGENT' => 'Mozilla/5.0', 'HTTP_SEC_FETCH_USER' => '?1', 'HTTP_SEC_FETCH_MODE' => 'navigate', 'HTTP_SEC_FETCH_DEST' => 'document' );
assert_crawler( 'visitor', $detector->audience(), 'first browser navigation allowed immediately without DNS' );
assert_crawler( 1, count( get_option( Pera_ML_Crawler_Detection::QUEUE ) ), 'bounded background queue populated' );
assert_crawler( 1, count( $events ), 'single shared cron event' );
$detector->audience(); assert_crawler( 1, count( get_option( Pera_ML_Crawler_Detection::QUEUE ) ), 'same IP does not duplicate jobs' );
$key = hash( 'sha256', $_SERVER['REMOTE_ADDR'] );
update_option( Pera_ML_Crawler_Detection::CACHE, array( $key => array( 'result' => 'bot', 'expires' => time() + 60 ) ) );
assert_crawler( 'bot', $detector->audience(), 'verified bot bypasses despite browser UA' );
$_SERVER['HTTP_USER_AGENT'] = 'Googlebot'; assert_crawler( 'bot', $detector->audience(), 'cached verified bot retains identity with crawler UA' );
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
update_option( Pera_ML_Crawler_Detection::CACHE, array( $key => array( 'result' => 'visitor', 'expires' => time() + 60 ) ) );
assert_crawler( 'visitor', $detector->audience(), 'confirmed non-provider browser navigation' );
$_SERVER['HTTP_USER_AGENT'] = 'Googlebot'; assert_crawler( '', $detector->audience(), 'spoofed bot UA only bypasses, never verified' );
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0'; unset( $_SERVER['HTTP_SEC_FETCH_USER'] ); assert_crawler( 'visitor', $detector->audience(), 'ordinary navigation does not require Sec-Fetch-User' );
unset( $_SERVER['HTTP_SEC_FETCH_MODE'], $_SERVER['HTTP_SEC_FETCH_DEST'] ); assert_crawler( '', $detector->audience(), 'unknown traffic without navigation evidence bypasses' );
$_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml'; assert_crawler( 'visitor', $detector->audience(), 'legacy HTML browser navigation allowed' );
$_SERVER['HTTP_SEC_FETCH_MODE'] = 'cors'; $_SERVER['HTTP_SEC_FETCH_DEST'] = 'empty'; assert_crawler( '', $detector->audience(), 'non-navigation metadata cannot fall back to HTML Accept' );
$_SERVER['HTTP_SEC_FETCH_MODE'] = 'navigate'; $_SERVER['HTTP_SEC_FETCH_DEST'] = 'document';
update_option( Pera_ML_Crawler_Detection::CACHE, array( $key => array( 'result' => 'visitor', 'expires' => time() - 1 ) ) );
assert_crawler( 'visitor', $detector->audience(), 'expired DNS does not block ordinary navigation' );
update_option( Pera_ML_Crawler_Detection::CACHE, array( $key => array( 'result' => '', 'expires' => time() + 60 ) ) );
assert_crawler( 'visitor', $detector->audience(), 'missing PTR or failed DNS does not block ordinary navigation' );
$queue = array(); for ( $i = 0; $i < 64; ++$i ) $queue['existing-' . $i] = array( 'ip' => '8.8.8.8', 'queued' => time() );
update_option( Pera_ML_Crawler_Detection::QUEUE, $queue ); $detector->audience();
assert_crawler( 64, count( get_option( Pera_ML_Crawler_Detection::QUEUE ) ), 'attacker cannot grow queue beyond cap' );
// Exercise worker bounds without external DNS: private IPs are rejected before lookup.
$queue = array(); $cache = array();
for ( $i = 0; $i < 70; ++$i ) $queue['queued-' . $i] = array( 'ip' => '127.0.0.1', 'queued' => time() );
for ( $i = 0; $i < 256; ++$i ) $cache['cached-' . $i] = array( 'result' => 'bot', 'expires' => time() + 600 );
update_option( Pera_ML_Crawler_Detection::QUEUE, array_slice( $queue, 0, 64, true ) );
update_option( Pera_ML_Crawler_Detection::CACHE, $cache ); $events = array(); $detector->work();
assert_crawler( 59, count( get_option( Pera_ML_Crawler_Detection::QUEUE ) ), 'worker processes five addresses' );
assert_crawler( 256, count( get_option( Pera_ML_Crawler_Detection::CACHE ) ), 'cache remains bounded' );
assert_crawler( false, get_option( Pera_ML_Crawler_Detection::LOCK ), 'worker releases lock' );
assert_crawler( 1, count( $events ), 'remaining queue rescheduled once' );
echo "Crawler detection: $count assertions passed\n";
