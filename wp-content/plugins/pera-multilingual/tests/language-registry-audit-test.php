<?php
/** Prevent production target-language loops from drifting away from the registry. */

$root = dirname( __DIR__ );
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$violations = array();

foreach ( $iterator as $file ) {
	if ( ! $file->isFile() || 'php' !== $file->getExtension() ) continue;
	$path = $file->getPathname();
	if ( false !== strpos( $path, DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR ) ) continue;
	$source = file_get_contents( $path );
	if ( preg_match( "/array\\(\\s*'zh'\\s*,\\s*'ar'\\s*,\\s*'de'\\s*\\)/", $source ) ) $violations[] = substr( $path, strlen( $root ) + 1 );
}

if ( $violations ) {
	fwrite( STDERR, 'FAIL hard-coded target-language array: ' . implode( ', ', $violations ) . PHP_EOL );
	exit( 1 );
}

echo "Pera ML language registry audit tests passed\n";
