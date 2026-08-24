<?php
/** Verify converted child-theme UI calls use explicit, stable semantic keys. */
$theme = dirname( dirname( dirname( __DIR__ ) ) ) . '/themes/hello-elementor-child';
$directories = array( $theme . '/inc', $theme . '/partials', $theme . '/parts' );
$calls = 0;
$failures = array();

foreach ( $directories as $directory ) {
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory ) );
	foreach ( $iterator as $file ) {
		$path = $file->getPathname();
		if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) || false !== strpos( $path, DIRECTORY_SEPARATOR . '_archive' . DIRECTORY_SEPARATOR ) ) continue;
		$lines = file( $path );
		foreach ( $lines as $number => $line ) {
			if ( false === strpos( $line, 'pera_ml_ui(' ) ) continue;
			$calls += substr_count( $line, 'pera_ml_ui(' );
			if ( ! preg_match( "/pera_ml_ui\(.*?,\s*'theme\.[a-z0-9_.-]+'\s*\)/", $line ) ) $failures[] = $path . ':' . ( $number + 1 );
		}
	}
}

if ( $calls < 1 || $failures ) {
	fwrite( STDERR, 'FAIL explicit child-theme UI registrations: ' . implode( ', ', $failures ) . "\n" );
	exit( 1 );
}

echo 'Pera ML child-theme UI registration tests passed (' . $calls . " calls)\n";
