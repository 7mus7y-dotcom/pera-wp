<?php
defined( 'ABSPATH' ) || exit;

/** Deterministic discovery of explicit, literal child-theme pera_ml_ui() calls. */
final class Pera_ML_Theme_UI_Discovery {
	private $registry;
	public function __construct( Pera_ML_UI_Registry $registry ) { $this->registry = $registry; }

	public static function approved_directories( $theme = '' ) {
		$theme = $theme ? rtrim( $theme, '/\\' ) : dirname( dirname( dirname( __DIR__ ) ) ) . '/themes/hello-elementor-child';
		$paths = array( $theme . '/inc', $theme . '/partials', $theme . '/parts' );
		// This is the reviewed template inventory from the visitor-copy translation pass.
		// Deliberately do not broaden this to every root PHP file: test/dev templates,
		// language-specific templates and unrelated bootstrap files are not approved copy.
		foreach ( array(
			'404.php', 'archive.php', 'archive-property.php', 'archive/single-property-v2.php',
			'attachment.php', 'footer.php', 'header.php', 'home-page.php', 'home.php',
			'page-about-new.php', 'page-book-a-consultancy.php', 'page-citizenship.php',
			'page-citizenship-properties.php', 'page-contact.php', 'page-favourites.php',
			'page-client-forgot-password.php', 'page-client-login.php', 'page-client-portal.php',
			'page-join-our-team.php', 'page-luxury-property.php', 'page-posts.php',
			'page-portfolio-token.php',
			'page-privacy-policy.php', 'page-property-map.php', 'page-register.php',
			'page-rent-with-pera.php', 'page-sell-with-pera.php', 'page-vop-besiktas.php',
			'single-bodrum-property.php', 'single-post.php', 'single-property.php',
		) as $template ) $paths[] = $theme . '/' . $template;
		return $paths;
	}

	/** @return array<string,int> */
	public function run( array $directories, $dry_run = false ) {
		$stats = array( 'files_scanned' => 0, 'discovered' => 0, 'newly_registered' => 0, 'already_current' => 0, 'source_changed' => 0, 'dynamic_skipped' => 0 );
		foreach ( $this->php_files( $directories ) as $file ) {
			$stats['files_scanned']++;
			$parsed = $this->parse( file_get_contents( $file ) );
			$stats['dynamic_skipped'] += $parsed['dynamic_skipped'];
			foreach ( $parsed['registrations'] as $registration ) {
				list( $source, $key ) = $registration; $stats['discovered']++;
				$identity = Pera_ML_UI::identity( $source, $key ); $existing = $this->registry->find( $identity );
				if ( ! $existing ) $stats['newly_registered']++;
				elseif ( ! isset( $existing['source_hash'] ) || ! hash_equals( (string) $existing['source_hash'], hash( 'sha256', $source ) ) ) $stats['source_changed']++;
				else $stats['already_current']++;
				if ( ! $dry_run ) $this->registry->register( $source, $key );
			}
		}
		return $stats;
	}

	private function php_files( array $directories ) {
		$files = array();
		foreach ( $directories as $directory ) {
			if ( is_file( $directory ) ) { if ( 'php' === strtolower( pathinfo( $directory, PATHINFO_EXTENSION ) ) ) $files[] = $directory; continue; }
			if ( ! is_dir( $directory ) ) continue;
			$iterator = new RecursiveIteratorIterator( new RecursiveCallbackFilterIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ), function ( $item ) { return ! $item->isDir() || '_archive' !== $item->getFilename(); } ) );
			foreach ( $iterator as $file ) if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) $files[] = $file->getPathname();
		}
		sort( $files, SORT_STRING ); return $files;
	}

	private function parse( $php ) {
		$tokens = token_get_all( (string) $php ); $registrations = array(); $skipped = 0;
		for ( $i = 0, $count = count( $tokens ); $i < $count; $i++ ) {
			if ( ! is_array( $tokens[ $i ] ) || T_STRING !== $tokens[ $i ][0] || 'pera_ml_ui' !== strtolower( $tokens[ $i ][1] ) ) continue;
			$previous = $this->previous_significant( $tokens, $i - 1 );
			if ( null !== $previous && ( ( is_array( $previous ) && in_array( $previous[0], array( T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON ), true ) ) || '\\' === $previous ) ) continue;
			$open = $this->next_significant_index( $tokens, $i + 1 ); if ( null === $open || '(' !== $tokens[ $open ] ) continue;
			$first = $this->next_significant_index( $tokens, $open + 1 ); $comma = null === $first ? null : $this->next_significant_index( $tokens, $first + 1 ); $second = null === $comma ? null : $this->next_significant_index( $tokens, $comma + 1 ); $after = null === $second ? null : $this->next_significant_index( $tokens, $second + 1 );
			$valid = null !== $first && null !== $comma && ',' === $tokens[ $comma ] && null !== $second && null !== $after && in_array( $tokens[ $after ], array( ')', ',' ), true );
			if ( ! $valid || ! $this->is_literal( $tokens[ $first ] ) || ! $this->is_literal( $tokens[ $second ] ) ) { $skipped++; continue; }
			$source = $this->literal_value( $tokens[ $first ][1] ); $key = $this->literal_value( $tokens[ $second ][1] );
			if ( 0 === strpos( $key, 'theme.' ) ) $registrations[] = array( $source, $key );
		}
		return array( 'registrations' => $registrations, 'dynamic_skipped' => $skipped );
	}
	private function is_literal( $token ) { return is_array( $token ) && T_CONSTANT_ENCAPSED_STRING === $token[0]; }
	private function literal_value( $literal ) { $quote = substr( $literal, 0, 1 ); $body = substr( $literal, 1, -1 ); return "'" === $quote ? str_replace( array( "\\\\", "\\'" ), array( "\\", "'" ), $body ) : stripcslashes( $body ); }
	private function next_significant_index( array $tokens, $index ) { for ( $count = count( $tokens ); $index < $count; $index++ ) if ( ! is_array( $tokens[ $index ] ) || ! in_array( $tokens[ $index ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) return $index; return null; }
	private function previous_significant( array $tokens, $index ) { for ( ; $index >= 0; $index-- ) if ( ! is_array( $tokens[ $index ] ) || ! in_array( $tokens[ $index ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) return $tokens[ $index ]; return null; }
}
