<?php
/** Prevent production target-language loops from drifting away from the registry. */

$root = dirname( __DIR__ );
$excluded = array(
	'includes/class-language-registry.php', // Canonical language definitions and defaults.
	'includes/class-vocabulary.php',        // Intentional per-language translation data.
);
$target_codes = array( 'zh', 'ar', 'de', 'ru' );
$violations = array();

/** Return language-only list literals, supporting array() and [] with arbitrary formatting. */
function pera_ml_language_lists( $source, $target_codes ) {
	$tokens = token_get_all( $source );
	$lists = array();
	$count = count( $tokens );
	for ( $i = 0; $i < $count; $i++ ) {
		$is_long = is_array( $tokens[ $i ] ) && T_ARRAY === $tokens[ $i ][0];
		$is_short = '[' === $tokens[ $i ];
		if ( ! $is_long && ! $is_short ) continue;
		$open = $i;
		if ( $is_long ) {
			do { $open++; } while ( $open < $count && is_array( $tokens[ $open ] ) && in_array( $tokens[ $open ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) );
			if ( $open >= $count || '(' !== $tokens[ $open ] ) continue;
		}
		$opening = $is_long ? '(' : '['; $closing = $is_long ? ')' : ']'; $depth = 1; $codes = array(); $valid = true;
		for ( $j = $open + 1; $j < $count && $depth; $j++ ) {
			$token = $tokens[ $j ];
			if ( $opening === $token ) { $depth++; $valid = false; continue; }
			if ( $closing === $token ) { $depth--; continue; }
			if ( 1 !== $depth ) { $valid = false; continue; }
			if ( is_array( $token ) && in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) continue;
			if ( ',' === $token ) continue;
			if ( is_array( $token ) && T_CONSTANT_ENCAPSED_STRING === $token[0] ) {
				$value = stripcslashes( substr( $token[1], 1, -1 ) );
				if ( in_array( $value, $target_codes, true ) ) { $codes[] = $value; continue; }
			}
			$valid = false;
		}
		if ( $valid && count( array_unique( $codes ) ) >= 2 ) $lists[] = array_unique( $codes );
	}
	return $lists;
}

$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
foreach ( $iterator as $file ) {
	if ( ! $file->isFile() || 'php' !== $file->getExtension() ) continue;
	$path = $file->getPathname();
	$relative = str_replace( DIRECTORY_SEPARATOR, '/', substr( $path, strlen( $root ) + 1 ) );
	if ( 0 === strpos( $relative, 'tests/' ) || in_array( $relative, $excluded, true ) ) continue;
	foreach ( pera_ml_language_lists( file_get_contents( $path ), $target_codes ) as $codes ) $violations[] = $relative . ' [' . implode( ', ', $codes ) . ']';
}

if ( $violations ) {
	fwrite( STDERR, 'FAIL hard-coded target-language list: ' . implode( '; ', $violations ) . PHP_EOL );
	exit( 1 );
}

// Prove both syntaxes and formatting/comment variations are recognized.
$fixture = "<?php \$a = [ 'zh',\n/* gap */ 'ar' ]; \$b = array(\n'de', 'ru',\n);";
if ( 2 !== count( pera_ml_language_lists( $fixture, $target_codes ) ) ) {
	fwrite( STDERR, "FAIL audit parser did not recognize short/long target-language lists\n" );
	exit( 1 );
}

echo "Pera ML language registry audit tests passed\n";
