<?php

function client_search_markup_expect( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}

	echo "PASS: {$message}\n";
}

$root           = dirname( __DIR__ );
$shell_header   = file_get_contents( $root . '/inc/views/shell/header.php' );
$client_filters = file_get_contents( $root . '/inc/views/partials/crm-header.php' );

client_search_markup_expect( false !== strpos( $shell_header, 'data-peracrm-header-search role="search"' ), 'live client search keeps search landmark semantics' );
client_search_markup_expect( false !== strpos( $shell_header, 'method="get" autocomplete="off"' ), 'live client search form disables autocomplete' );
client_search_markup_expect( false !== strpos( $shell_header, 'type="search"' ), 'live client search uses a search input' );
client_search_markup_expect( false !== strpos( $shell_header, 'name="q"' ), 'live client search preserves the q backend parameter' );
client_search_markup_expect( false !== strpos( $shell_header, "esc_html_e('Search clients', 'peracrm')" ), 'live client search has a clear accessible label' );
client_search_markup_expect( false !== strpos( $shell_header, 'autocomplete="off"' ), 'live client search input disables autocomplete' );

client_search_markup_expect( false !== strpos( $client_filters, 'class="crm-client-filters"' ), 'client list search form is present' );
client_search_markup_expect( false !== strpos( $client_filters, 'class="crm-client-filters" aria-label=' ) && false !== strpos( $client_filters, 'autocomplete="off"' ), 'client list search form disables autocomplete' );
client_search_markup_expect( false !== strpos( $client_filters, 'class="crm-search-control" type="search" name="q"' ), 'client list search keeps search semantics and the q backend parameter' );
client_search_markup_expect( false !== strpos( $client_filters, 'placeholder="<?php echo esc_attr__( \'Search clients\', \'peracrm\' ); ?>" autocomplete="off"' ), 'client list search input disables autocomplete' );

echo "PeraCRM client search markup checks passed\n";
