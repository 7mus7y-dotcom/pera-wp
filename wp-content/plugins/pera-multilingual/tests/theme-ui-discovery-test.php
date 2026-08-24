<?php
/** Standalone tests for the maintenance-only theme UI discovery tool. */
define( 'ABSPATH', __DIR__ );
define( 'PERA_ML_THEME_UI_DISCOVERY_NO_RUN', true );
$GLOBALS['ui_registry_option'] = array();
$GLOBALS['translation_rows'] = array();
function get_option( $key, $default = false ) { return 'pera_ml_ui_registry' === $key ? $GLOBALS['ui_registry_option'] : $default; }
function update_option( $key, $value, $autoload = null ) { if ( 'pera_ml_ui_registry' === $key ) $GLOBALS['ui_registry_option'] = $value; return true; }
function current_time() { return '2026-08-24 12:00:00'; }
function expect_discovery( $expected, $actual, $label ) {
	if ( $expected !== $actual ) { fwrite( STDERR, "FAIL {$label}: expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . "\n" ); exit( 1 ); }
}

require dirname( __DIR__ ) . '/includes/class-ui.php';
require dirname( __DIR__ ) . '/includes/class-ui-registry.php';
require dirname( __DIR__ ) . '/tools/register-theme-ui-strings.php';

$root = sys_get_temp_dir() . '/pera-ui-discovery-' . getmypid();
mkdir( $root . '/inc/_archive', 0777, true );
mkdir( $root . '/partials', 0777, true );
file_put_contents( $root . '/inc/ui.php', <<<'PHP'
<?php
pera_ml_ui( 'English', 'theme.key' );
pera_ml_ui( $dynamic, 'theme.dynamic_source' );
pera_ml_ui( 'Dynamic key', $dynamic_key );
__( 'Unrelated gettext', 'theme.key' );
$admin_notice = 'Admin string';
PHP
);
file_put_contents( $root . '/partials/other.php', "<?php pera_ml_ui( 'Wrong namespace', 'admin.key' );\n" );
file_put_contents( $root . '/inc/_archive/old.php', "<?php pera_ml_ui( 'Archived', 'theme.archived' );\n" );

$tool = new Pera_ML_Theme_UI_Discovery( new Pera_ML_UI_Registry() );
$first = $tool->run( array( $root . '/inc', $root . '/partials' ) );
expect_discovery( 2, $first['files_scanned'], 'archive files are excluded' );
expect_discovery( 1, $first['discovered'], 'only explicit theme registration discovered' );
expect_discovery( 1, $first['newly_registered'], 'new registration count' );
expect_discovery( 2, $first['dynamic_skipped'], 'dynamic calls skipped' );
expect_discovery( 1, count( $GLOBALS['ui_registry_option'] ), 'only registry row created' );
expect_discovery( 0, count( $GLOBALS['translation_rows'] ), 'no translation rows created' );

$second = $tool->run( array( $root . '/inc', $root . '/partials' ) );
expect_discovery( 0, $second['newly_registered'], 'repeat creates no registrations' );
expect_discovery( 1, $second['already_current'], 'repeat reports current registration' );
expect_discovery( 1, count( $GLOBALS['ui_registry_option'] ), 'repeat is idempotent' );

file_put_contents( $root . '/inc/ui.php', "<?php pera_ml_ui( 'Revised English', 'theme.key' );\n" );
$changed = $tool->run( array( $root . '/inc', $root . '/partials' ) );
expect_discovery( 1, $changed['source_changed'], 'changed canonical source is reported' );
expect_discovery( 'Revised English', reset( $GLOBALS['ui_registry_option'] )['source'], 'registry API updates canonical source' );

$dry_root = $root . '/parts';
mkdir( $dry_root );
file_put_contents( $dry_root . '/dry.php', "<?php pera_ml_ui( 'Dry', 'theme.dry' );\n" );
$dry = $tool->run( array( $dry_root ), true );
expect_discovery( 1, $dry['newly_registered'], 'dry run reports prospective registration' );
expect_discovery( 1, count( $GLOBALS['ui_registry_option'] ), 'dry run does not write registry' );

echo "Pera ML theme UI discovery tests passed\n";
