<?php
/** Verify converted child-theme UI calls use explicit, stable semantic keys. */
$theme = dirname( dirname( dirname( __DIR__ ) ) ) . '/themes/hello-elementor-child';
$directories = array( $theme . '/inc', $theme . '/partials', $theme . '/parts' );
$template_files = array(
	'404.php', 'archive.php', 'archive-property.php', 'archive/single-property-v2.php',
	'attachment.php', 'footer.php', 'header.php', 'home-page.php', 'home.php',
	'page-about-new.php', 'page-book-a-consultancy.php', 'page-citizenship.php',
	'page-citizenship-properties.php', 'page-contact.php', 'page-favourites.php',
	'page-join-our-team.php', 'page-luxury-property.php', 'page-posts.php',
	'page-privacy-policy.php', 'page-property-map.php', 'page-register.php',
	'page-rent-with-pera.php', 'page-sell-with-pera.php', 'page-vop-besiktas.php',
	'single-bodrum-property.php', 'single-post.php', 'single-property.php',
);
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

foreach ( $template_files as $relative_path ) {
	$path = $theme . '/' . $relative_path;
	if ( ! is_file( $path ) ) continue;
	$source = file_get_contents( $path );
	$template_calls = substr_count( $source, 'pera_ml_ui(' );
	preg_match_all( "/pera_ml_ui\s*\(\s*'(?:\\\\.|[^'])*'\s*,\s*'theme\.template\.[a-z0-9_.-]+'\s*\)/s", $source, $matches );
	$calls += $template_calls;
	if ( $template_calls !== count( $matches[0] ) ) {
		$failures[] = $path . ': template call without an explicit theme.template key';
	}

	$hard_coded_patterns = array(
		// Visitor-copy variables must not be assigned a raw English phrase.
		'/\$(?:[a-z0-9_]*(?:title|heading|label|text|message|desc|description|intro|subtext|empty|loading|error|result)[a-z0-9_]*)\s*=\s*[\'\"][A-Za-z][^\'\"]*\s+[^\'\"]*[\'\"]/i',
		// Catch raw visitor-facing fallback phrases in ternaries and null coalescing expressions.
		'/(?:\?\?|\?:|\?|:)\s*[\'\"][A-Z][A-Za-z’\' -]*(?:\s+[A-Za-z’\' -]+)+[.!?]?[\'\"]/',
		// Client-created labels and states need the same registration as server-rendered UI.
		'/(?:textContent|innerHTML)\s*=\s*[\'\"][A-Za-z][^\'\"]*[\'\"]/',
		'/setAttribute\(\s*[\'\"]aria-label[\'\"]\s*,\s*[\'\"][A-Za-z][^\'\"]*[\'\"]\s*\)/',
		// Rendered format strings and WhatsApp prefills must be translated before interpolation/URL construction.
		'/sprintf\(\s*[\'\"][^\'\"]*%[a-z0-9$][^\'\"]*[\'\"]/i',
		'/pera_get_whatsapp_url\(\s*[\'\"][A-Za-z][^\'\"]*[\'\"]\s*\)/',
	);
	foreach ( $hard_coded_patterns as $pattern ) {
		if ( preg_match( $pattern, $source, $hard_coded_match, PREG_OFFSET_CAPTURE ) ) {
			$line = substr_count( substr( $source, 0, $hard_coded_match[0][1] ), "\n" ) + 1;
			$failures[] = $path . ':' . $line . ': obvious hard-coded visitor-facing English';
		}
	}
}

if ( $calls < 1 || $failures ) {
	fwrite( STDERR, 'FAIL explicit child-theme UI registrations: ' . implode( ', ', $failures ) . "\n" );
	exit( 1 );
}

echo 'Pera ML child-theme UI registration tests passed (' . $calls . " calls)\n";
