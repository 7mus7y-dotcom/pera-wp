<?php
/** Focused CLI object-type filtering regression tests. */

if ( isset( $argv[1] ) && 'scenario' === $argv[1] ) {
	function absint( $value ) { return abs( (int) $value ); }
	function is_wp_error( $value ) { return $value instanceof WP_Error; }
	function wp_cache_flush() {}

	class WP_Error {}
	final class Latest_Test_Dependency {}
	final class Pera_ML_Plugin {
		public static function instance() { return new self(); }
		public function status() { return new Latest_Test_Dependency(); }
		public function storage() { return new Latest_Test_Dependency(); }
		public function translator() { return new Latest_Test_Dependency(); }
		public function ui() { return new Latest_Test_Dependency(); }
		public function ui_registry() { return new Latest_Test_Dependency(); }
	}
	final class Pera_ML_Translation_Health {
		public function __construct() {}
		public function inventory() { return array( 'rows' => $GLOBALS['latest_test_rows'] ); }
	}
	final class Pera_ML_Translation_Health_Orchestrator {
		public function __construct() {}
		public function translate( $row ) {
			$GLOBALS['latest_test_translated'][] = $row['object_type'] . ':' . $row['status'];
			return true;
		}
	}

	$GLOBALS['latest_test_rows'] = json_decode( getenv( 'PERA_LATEST_ROWS' ), true );
	$GLOBALS['latest_test_translated'] = array();
	$args = json_decode( getenv( 'PERA_LATEST_ARGS' ), true );

	ob_start();
	include dirname( __DIR__ ) . '/tools/pera-translate-latest.php';
	$output = ob_get_clean();

	echo json_encode(
		array(
			'output'     => $output,
			'translated' => $GLOBALS['latest_test_translated'],
		)
	);
	exit;
}

function latest_expect( $expected, $actual, $label ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL {$label}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

function latest_run( $args ) {
	$rows = array(
		array( 'object_type' => 'property', 'object_id' => 1, 'field' => 'post_title', 'language' => 'de', 'status' => 'missing' ),
		array( 'object_type' => 'property', 'object_id' => 2, 'field' => 'post_title', 'language' => 'de', 'status' => 'stale' ),
		array( 'object_type' => 'page', 'object_id' => 3, 'field' => 'post_title', 'language' => 'de', 'status' => 'stale' ),
		array( 'object_type' => 'post', 'object_id' => 4, 'field' => 'post_title', 'language' => 'de', 'status' => 'stale' ),
		array( 'object_type' => 'taxonomy:region', 'object_id' => 5, 'field' => 'term_name', 'language' => 'de', 'status' => 'stale' ),
		array( 'object_type' => 'ui', 'object_id' => 6, 'field' => 'label', 'language' => 'de', 'status' => 'stale' ),
	);
	$command = 'PERA_LATEST_ROWS=' . escapeshellarg( json_encode( $rows ) )
		. ' PERA_LATEST_ARGS=' . escapeshellarg( json_encode( $args ) )
		. ' ' . escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' scenario';
	$result = shell_exec( $command );
	return json_decode( $result, true );
}

$unfiltered = latest_run( array( 'dry-run' ) );
latest_expect( 6, substr_count( $unfiltered['output'], '  DRY RUN' ), 'no object_type preserves the unfiltered pending queue' );
latest_expect( false, false !== strpos( $unfiltered['output'], 'Object type:' ), 'no object_type preserves the existing summary' );

$property_dry_run = latest_run( array( 'dry-run', 'object_type=property' ) );
latest_expect( 2, substr_count( $property_dry_run['output'], '  DRY RUN' ), 'dry-run includes all property rows' );
foreach ( array( 'page', 'post', 'taxonomy:region', 'ui' ) as $excluded_type ) {
	latest_expect( false, false !== strpos( $property_dry_run['output'], '| ' . $excluded_type . ' #' ), 'dry-run excludes ' . strtolower( $excluded_type ) . ' rows' );
}
latest_expect( true, false !== strpos( $property_dry_run['output'], 'Object type: property' ), 'dry-run reports the active filter' );

$stale = latest_run( array( 'status=stale', 'object_type=property' ) );
latest_expect( array( 'property:stale' ), $stale['translated'], 'live stale execution requires status and object type' );
latest_expect( true, false !== strpos( $stale['output'], 'Object type: property' ), 'live completion reports the active filter' );

$missing = latest_run( array( 'status=missing', 'object_type=property' ) );
latest_expect( array( 'property:missing' ), $missing['translated'], 'live missing execution requires status and object type' );

$limited = latest_run( array( 'object_type=property', 'limit=1' ) );
latest_expect( array( 'property:missing' ), $limited['translated'], 'limit applies after object-type filtering' );

foreach ( array( 'object_type=', 'object_type=property!' ) as $invalid_filter ) {
	$invalid = latest_run( array( $invalid_filter ) );
	latest_expect( 0, count( $invalid['translated'] ), 'invalid object_type does not execute translations' );
	latest_expect( true, 0 === strpos( $invalid['output'], 'ERROR: Invalid object_type filter' ), 'invalid object_type reports an error' );
}

$invalid_status = latest_run( array( 'status=obsolete', 'object_type=property' ) );
latest_expect( 0, count( $invalid_status['translated'] ), 'invalid status still does not execute translations' );
latest_expect( true, 0 === strpos( $invalid_status['output'], 'ERROR: Invalid status filter' ), 'existing status validation is preserved' );

echo "Pera ML translate-latest object-type tests passed\n";
