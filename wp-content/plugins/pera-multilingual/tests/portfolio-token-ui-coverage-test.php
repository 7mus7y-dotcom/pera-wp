<?php
/** Static regressions for public portfolio-token visitor UI routing. */
$theme    = dirname( dirname( dirname( __DIR__ ) ) ) . '/themes/hello-elementor-child';
$template = file_get_contents( $theme . '/page-portfolio-token.php' );
$helper   = file_get_contents( $theme . '/inc/portfolio-token.php' );

function expect_portfolio_ui( $condition, $label ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL {$label}\n" ); exit( 1 ); }
}

foreach ( array(
	'theme.template.portfolio_token.heading',
	'theme.template.portfolio_token.lead_client_advisor',
	'theme.template.portfolio_token.property_count_plural',
	'theme.template.portfolio_token.valid_until',
	'theme.template.portfolio_token.view_label',
	'theme.template.portfolio_token.empty_state',
	'theme.template.portfolio_token.table.project_property',
	'theme.template.portfolio_token.table.view_notes',
	'theme.template.portfolio_token.invalid_token',
) as $semantic_key ) {
	expect_portfolio_ui( false !== strpos( $template, "pera_ml_ui( " ) && false !== strpos( $template, "'{$semantic_key}'" ), 'semantic UI key is present: ' . $semantic_key );
}

expect_portfolio_ui( false !== strpos( $helper, "'theme.template.portfolio_token.document_title'" ), 'document title format uses semantic UI' );
expect_portfolio_ui( false === strpos( $template, 'esc_html_e(' ) && false === strpos( $template, 'esc_attr_e(' ), 'portfolio template has no gettext-only visitor labels' );
foreach ( array( '<h1>A Custom Portfolio</h1>', '>No properties available in this portfolio right now.<', '>We couldn\'t find that portfolio.' ) as $raw_ui ) {
	expect_portfolio_ui( false === strpos( $template, $raw_ui ), 'material English is not emitted raw: ' . $raw_ui );
}

echo "Pera ML portfolio token UI coverage tests passed\n";
