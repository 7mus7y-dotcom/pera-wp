<?php
/** Focused health aggregation test; inventory classification itself remains provider-free. */
define( 'ABSPATH', __DIR__ );
function health_expect( $expected, $actual, $label ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAIL {$label}\n" . var_export( $actual, true ) . "\n" ); exit( 1 ); } }
require dirname( __DIR__ ) . '/includes/class-translation-health.php';
$health = new Pera_ML_Translation_Health( null, null, null );
$method = new ReflectionMethod( $health, 'counts' ); $method->setAccessible( true );
$rows = array(
	array( 'object_type'=>'ui', 'language'=>'zh', 'status'=>'current' ),
	array( 'object_type'=>'ui', 'language'=>'zh', 'status'=>'missing' ),
	array( 'object_type'=>'post', 'language'=>'ar', 'status'=>'stale' ),
	array( 'object_type'=>'property', 'language'=>'ar', 'status'=>'current' ),
	array( 'object_type'=>'taxonomy:district', 'language'=>'de', 'status'=>'missing' ),
);
$counts = $method->invoke( $health, $rows );
health_expect( array( 'current'=>1, 'missing'=>1, 'stale'=>0 ), $counts['ui']['zh'], 'UI counts' );
health_expect( array( 'current'=>1, 'missing'=>0, 'stale'=>1 ), $counts['content']['ar'], 'content counts' );
health_expect( array( 'current'=>0, 'missing'=>1, 'stale'=>0 ), $counts['taxonomies']['de'], 'taxonomy counts' );
echo "Pera ML translation health tests passed\n";
