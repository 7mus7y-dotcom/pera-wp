<?php
/** Focused CLI language filtering regression tests. */

if ( isset( $argv[1] ) && 'scenario' === $argv[1] ) {
	function absint( $value ) { return abs( (int) $value ); }
	function is_wp_error( $value ) { return $value instanceof WP_Error; }
	function wp_cache_flush() { $GLOBALS['latest_language_writes']++; }

	class WP_Error {}
	final class Latest_Language_Test_Dependency {}
	final class Latest_Language_Test_Registry {
		public function get( $code ) {
			$languages = array(
				'en' => array( 'name' => 'English', 'enabled' => true, 'source' => true ),
				'de' => array( 'name' => 'German', 'enabled' => true, 'source' => false ),
				'ru' => array( 'name' => 'Russian', 'enabled' => true, 'source' => false ),
				'zh' => array( 'name' => 'Chinese', 'enabled' => false, 'source' => false ),
			);
			return isset( $languages[ $code ] ) ? $languages[ $code ] : null;
		}
	}
	final class Pera_ML_Plugin {
		public static function instance() { return new self(); }
		public function status() { return new Latest_Language_Test_Dependency(); }
		public function storage() { return new Latest_Language_Test_Dependency(); }
		public function translator() { return new Latest_Language_Test_Dependency(); }
		public function ui() { return new Latest_Language_Test_Dependency(); }
		public function ui_registry() { return new Latest_Language_Test_Dependency(); }
		public function registry() { return new Latest_Language_Test_Registry(); }
	}
	final class Pera_ML_Translation_Health {
		public function __construct() {}
		public function inventory() { return array( 'rows' => $GLOBALS['latest_language_rows'] ); }
	}
	final class Pera_ML_Translation_Health_Orchestrator {
		public function __construct() {}
		public function translate( $row ) {
			$GLOBALS['latest_language_calls'][] = $row['language'] . ':' . $row['object_type'] . ':' . $row['object_id'] . ':' . $row['status'];
			$GLOBALS['latest_language_writes']++;
			return true;
		}
	}

	$GLOBALS['latest_language_rows']  = json_decode( getenv( 'PERA_LATEST_LANGUAGE_ROWS' ), true );
	$GLOBALS['latest_language_calls'] = array();
	$GLOBALS['latest_language_writes'] = 0;
	$args = json_decode( getenv( 'PERA_LATEST_LANGUAGE_ARGS' ), true );

	ob_start();
	include dirname( __DIR__ ) . '/tools/pera-translate-latest.php';
	$output = ob_get_clean();

	echo json_encode( array( 'output' => $output, 'calls' => $GLOBALS['latest_language_calls'], 'writes' => $GLOBALS['latest_language_writes'] ) );
	exit;
}

function language_expect( $expected, $actual, $label ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL {$label}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

function language_run( $args ) {
	$rows = array(
		array( 'object_type' => 'post', 'object_id' => 55649, 'field' => 'post_title', 'language' => 'ru', 'status' => 'missing' ),
		array( 'object_type' => 'post', 'object_id' => 55649, 'field' => 'post_content', 'language' => 'ru', 'status' => 'stale' ),
		array( 'object_type' => 'post', 'object_id' => 55649, 'field' => 'post_title', 'language' => 'de', 'status' => 'missing' ),
		array( 'object_type' => 'post', 'object_id' => 99, 'field' => 'post_title', 'language' => 'ru', 'status' => 'missing' ),
	);
	$command = 'PERA_LATEST_LANGUAGE_ROWS=' . escapeshellarg( json_encode( $rows ) )
		. ' PERA_LATEST_LANGUAGE_ARGS=' . escapeshellarg( json_encode( $args ) )
		. ' ' . escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' scenario';
	return json_decode( shell_exec( $command ), true );
}

$russian = language_run( array( 'language=ru' ) );
language_expect( 3, count( $russian['calls'] ), 'Russian filter selects every and only Russian row' );
language_expect( true, false !== strpos( $russian['output'], 'Language: Russian (ru)' ), 'summary names the active Russian filter' );

$german = language_run( array( 'language=de' ) );
language_expect( array( 'de:post:55649:missing' ), $german['calls'], 'German remains a supported target filter' );

foreach ( array( 'language=en', 'language=unknown', 'language=zh' ) as $invalid_language ) {
	$invalid = language_run( array( $invalid_language ) );
	language_expect( array(), $invalid['calls'], $invalid_language . ' does not translate' );
	language_expect( true, 0 === strpos( $invalid['output'], 'ERROR: Invalid target language filter' ), $invalid_language . ' fails clearly' );
}

$strict = language_run( array( 'language=ru', 'object_type=post', 'object_id=55649', 'status=missing' ) );
language_expect( array( 'ru:post:55649:missing' ), $strict['calls'], 'language composes with strict object and status filters' );

$unfiltered = language_run( array() );
language_expect( 4, count( $unfiltered['calls'] ), 'omitting language preserves the multi-language queue' );
language_expect( false, false !== strpos( $unfiltered['output'], 'Language:' ), 'omitting language preserves the summary' );

$dry_run = language_run( array( 'language=ru', 'dry-run' ) );
language_expect( array(), $dry_run['calls'], 'dry-run makes no provider calls' );
language_expect( 0, $dry_run['writes'], 'dry-run makes no writes or cache flushes' );
language_expect( 3, substr_count( $dry_run['output'], '  DRY RUN' ), 'dry-run uses the same Russian filtering' );

echo "Pera ML translate-latest language tests passed\n";
