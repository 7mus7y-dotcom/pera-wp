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
$crm_data       = file_get_contents( $root . '/inc/frontend-data/crm-data.php' );
$crm_script     = file_get_contents( $root . '/assets/frontend/crm.js' );
$crm_styles     = file_get_contents( $root . '/assets/frontend/crm.css' );

preg_match( '/(<form class="peracrm-header-search".*?)<label/s', $shell_header, $header_form_match );
preg_match( '/(<input\s+id="peracrm-header-client-search-input".*?)<div/s', $shell_header, $header_input_match );
$header_form  = $header_form_match[1] ?? '';
$header_input = $header_input_match[1] ?? '';

client_search_markup_expect( false !== strpos( $shell_header, 'data-peracrm-header-search role="search"' ), 'live client search keeps search landmark semantics' );
client_search_markup_expect( '' !== $header_form && false !== strpos( $header_form, 'method="get"' ), 'live client search supports native GET submission' );
client_search_markup_expect( false !== strpos( $header_form, 'autocomplete="off"' ), 'live client search form disables autocomplete' );
client_search_markup_expect( false === strpos( $header_form, 'name="type"' ), 'header submission does not force the leads listing' );
client_search_markup_expect( '' !== $header_input && false !== strpos( $header_input, 'type="search"' ), 'live client search uses a search input' );
client_search_markup_expect( false !== strpos( $header_input, 'name="crm_client_search"' ), 'live client search uses a client-specific GET parameter' );
client_search_markup_expect( false !== strpos( $header_input, 'value="<?php echo esc_attr($header_search_term); ?>"' ), 'submitted header term remains visible' );
client_search_markup_expect( false !== strpos( $header_input, 'id="peracrm-header-client-search-input"' ), 'live client search uses a client-specific id' );
client_search_markup_expect( false !== strpos( $shell_header, 'for="peracrm-header-client-search-input"' ), 'live client search label is associated with its input' );
client_search_markup_expect( false !== strpos( $header_input, 'autocomplete="off"' ), 'live client search input disables autocomplete independently of its form' );
client_search_markup_expect( 1 === substr_count( $shell_header, 'id="peracrm-header-client-search-input"' ), 'live client search input id is unique in the shell' );
client_search_markup_expect( false === strpos( $crm_styles, '.peracrm-header-search {\n    display: none;' ), 'live client search is not hidden at narrow viewport widths' );
client_search_markup_expect( false !== strpos( $crm_styles, 'flex: 1 0 100%;' ) && false !== strpos( $crm_styles, 'flex-wrap: wrap;' ), 'live client search uses a full-width wrapped mobile row' );
client_search_markup_expect( false !== strpos( $crm_styles, 'max-height: min(360px, calc(100dvh - 150px));' ) && false !== strpos( $crm_styles, 'overflow-y: auto;' ), 'live client results remain viewport constrained and scrollable' );
client_search_markup_expect( false !== strpos( $crm_script, "form.querySelector('[data-peracrm-header-search-input]')" ) && false !== strpos( $crm_script, "payload.append('q', term)" ), 'live search keeps its data selector and AJAX q contract' );
client_search_markup_expect( false !== strpos( $crm_data, "isset( \$_GET['crm_client_search'] )" ) && false !== strpos( $crm_data, "isset( \$_GET['q'] )" ), 'client list parsing accepts the new parameter and existing q URLs' );
client_search_markup_expect( false !== strpos( $crm_data, '$q               = pera_crm_get_client_search_term();' ), 'client list filtering uses the compatible search parser' );

client_search_markup_expect( false !== strpos( $client_filters, 'class="crm-client-filters"' ), 'client list search form is present' );
client_search_markup_expect( false !== strpos( $client_filters, 'class="crm-client-filters" aria-label=' ) && false !== strpos( $client_filters, 'autocomplete="off"' ), 'client list search form disables autocomplete' );
client_search_markup_expect( false !== strpos( $client_filters, 'class="crm-search-control" type="search" name="q"' ), 'client list search keeps search semantics and the q backend parameter' );
client_search_markup_expect( false !== strpos( $client_filters, 'placeholder="<?php echo esc_attr__( \'Search clients\', \'peracrm\' ); ?>" autocomplete="off"' ), 'client list search input disables autocomplete' );

echo "PeraCRM client search markup checks passed\n";
