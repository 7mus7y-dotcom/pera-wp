<?php
/** Focused UI admin bulk-completion test. */
define( 'ABSPATH', __DIR__ );
function __( $value ) { return $value; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function expect_same( $expected, $actual, $label ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAIL {$label}\n" . var_export( $actual, true ) . "\n" ); exit( 1 ); } }
class WP_Error {}

final class UI_Admin_Test_Service {
	public $calls = array();
	public function inventory() {
		return array(
			'key:first' => array( 'statuses' => array( 'zh' => 'current', 'ar' => 'missing', 'de' => 'stale' ) ),
			'key:second' => array( 'statuses' => array( 'zh' => 'current', 'ar' => 'current', 'de' => 'current' ) ),
		);
	}
	public function translate_registered( $identity, $language ) { $this->calls[] = array( $identity, $language ); return 'translated'; }
}

require dirname( __DIR__ ) . '/admin/class-admin.php';
$ui = new UI_Admin_Test_Service();
$admin = new Pera_ML_Admin( new stdClass() );
$summary = $admin->complete_ui_translations( $ui );
expect_same( array( array( 'key:first', 'ar' ), array( 'key:first', 'de' ) ), $ui->calls, 'bulk completion targets only missing and stale rows' );
expect_same( array( 'attempted' => 2, 'failures' => 0 ), $summary, 'bulk completion summary' );
echo "Pera ML UI admin tests passed\n";
