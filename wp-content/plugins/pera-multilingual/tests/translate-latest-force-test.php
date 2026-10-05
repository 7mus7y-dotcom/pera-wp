<?php
/** Focused CLI force/regeneration and filter safety tests. */

if ( isset( $argv[1] ) && 'scenario' === $argv[1] ) {
	function absint( $value ) { return abs( (int) $value ); }
	function is_wp_error( $value ) { return $value instanceof WP_Error; }
	function wp_cache_flush() { $GLOBALS['force_flushes']++; }
	class WP_Error {}
	final class Force_Test_Dependency {}
	final class Force_Test_Registry {
		public function get( $code ) { return in_array( $code, array( 'ru', 'de' ), true ) ? array( 'name' => strtoupper( $code ), 'enabled' => true, 'source' => false ) : null; }
	}
	final class Pera_ML_Plugin {
		public static function instance() { return new self(); }
		public function status() { return new Force_Test_Dependency(); }
		public function storage() { return new Force_Test_Dependency(); }
		public function translator() { return new Force_Test_Dependency(); }
		public function ui() { return new Force_Test_Dependency(); }
		public function ui_registry() { return new Force_Test_Dependency(); }
		public function registry() { return new Force_Test_Registry(); }
	}
	final class Pera_ML_Translation_Health {
		public function __construct() {}
		public function inventory() { return array( 'rows' => $GLOBALS['force_rows'] ); }
	}
	final class Pera_ML_Translation_Health_Orchestrator {
		public function __construct() {}
		public function translate( $row, $regenerate = false ) { $GLOBALS['force_calls'][] = array( $row, $regenerate ); return true; }
	}
	$GLOBALS['force_rows'] = json_decode( getenv( 'PERA_FORCE_ROWS' ), true );
	$GLOBALS['force_calls'] = array(); $GLOBALS['force_flushes'] = 0;
	$args = json_decode( getenv( 'PERA_FORCE_ARGS' ), true );
	ob_start(); include dirname( __DIR__ ) . '/tools/pera-translate-latest.php'; $output = ob_get_clean();
	echo json_encode( array( 'output' => $output, 'calls' => $GLOBALS['force_calls'], 'flushes' => $GLOBALS['force_flushes'] ) ); exit;
}

function force_expect( $expected, $actual, $label ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAIL {$label}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" ); exit( 1 ); } }
function force_run( $args ) {
	$rows=array(
		array('object_type'=>'ui','object_id'=>101,'field'=>'label_one','language'=>'ru','status'=>'current'),
		array('object_type'=>'ui','object_id'=>102,'field'=>'label_two','language'=>'ru','status'=>'current'),
		array('object_type'=>'ui','object_id'=>101,'field'=>'label_one','language'=>'de','status'=>'current'),
		array('object_type'=>'page','object_id'=>101,'field'=>'post_title','language'=>'ru','status'=>'current'),
		array('object_type'=>'ui','object_id'=>101,'field'=>'label_stale','language'=>'ru','status'=>'stale'),
	);
	$command='PERA_FORCE_ROWS='.escapeshellarg(json_encode($rows)).' PERA_FORCE_ARGS='.escapeshellarg(json_encode($args)).' '.escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' scenario';
	return json_decode(shell_exec($command),true);
}

$normal=force_run(array('object_type=ui','object_id=101','language=ru','field=label_stale'));
force_expect(1,count($normal['calls']),'normal run retains stale translation behavior');
force_expect(false,$normal['calls'][0][1],'normal run passes regenerate false');

$forced=force_run(array('force','object_type=ui','object_id=101','language=ru','field=label_one'));
force_expect(1,count($forced['calls']),'force filters select only the exact current row');
force_expect('current',$forced['calls'][0][0]['status'],'force uses a real Translation Health row');
force_expect(true,$forced['calls'][0][1],'force passes regenerate true');

$dry=force_run(array('dry-run','force','object_type=ui','object_id=101','language=ru','field=label_one'));
force_expect(0,count($dry['calls']),'dry-run force performs no translation');
force_expect(0,$dry['flushes'],'dry-run force performs no write-related cache flush');
force_expect(1,substr_count($dry['output'],'  DRY RUN'),'dry-run force preserves exact filters');

$absent=force_run(array('force','object_type=ui','object_id=999','language=ru'));
force_expect(0,count($absent['calls']),'force does not fabricate jobs absent from Translation Health');
force_expect(true,false!==strpos($absent['output'],'No incomplete translations found.'),'empty forced selection is reported without a synthetic fallback');

echo "Pera ML translate-latest force tests passed\n";
