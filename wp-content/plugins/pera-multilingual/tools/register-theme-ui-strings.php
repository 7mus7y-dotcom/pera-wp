<?php
/** Discover explicit child-theme pera_ml_ui() declarations and register them.
 * Run: wp eval-file wp-content/plugins/pera-multilingual/tools/register-theme-ui-strings.php [-- --dry-run]
 */
defined( 'ABSPATH' ) || exit;
require_once dirname( __DIR__ ) . '/includes/class-theme-ui-discovery.php';

if ( ! defined( 'PERA_ML_THEME_UI_DISCOVERY_NO_RUN' ) ) {
	$arguments = isset( $args ) && is_array( $args ) ? $args : array();
	$dry_run = in_array( '--dry-run', $arguments, true );
	$stats = ( new Pera_ML_Theme_UI_Discovery( new Pera_ML_UI_Registry() ) )->run(
		Pera_ML_Theme_UI_Discovery::approved_directories(),
		$dry_run
	);
	$labels = array(
		'files_scanned' => 'Files scanned', 'discovered' => 'Explicit registrations discovered',
		'newly_registered' => 'Newly registered', 'already_current' => 'Already registered/current',
		'source_changed' => 'Source-changed registrations', 'dynamic_skipped' => 'Dynamic/non-literal calls skipped',
	);
	if ( $dry_run ) echo "Dry run (registry was not modified)\n";
	foreach ( $labels as $key => $label ) echo $label . ': ' . $stats[ $key ] . "\n";
}
