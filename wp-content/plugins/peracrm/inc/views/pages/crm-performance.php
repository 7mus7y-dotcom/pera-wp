<?php
/**
 * Front-end CRM performance summary view.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( function_exists( 'pera_crm_gate_or_redirect' ) ) {
	pera_crm_gate_or_redirect();
}

$range_options = function_exists( 'pera_crm_get_performance_range_options' )
	? pera_crm_get_performance_range_options()
	: array(
		'7d'         => __( 'Last 7 days', 'peracrm' ),
		'30d'        => __( 'Last 30 days', 'peracrm' ),
		'this_month' => __( 'This month', 'peracrm' ),
		'last_month' => __( 'Last month', 'peracrm' ),
	);

$selected_range = isset( $_GET['range'] ) ? sanitize_key( wp_unslash( (string) $_GET['range'] ) ) : '30d';
if ( ! isset( $range_options[ $selected_range ] ) ) {
	$selected_range = '30d';
}

$summary = function_exists( 'pera_crm_get_performance_summary' )
	? pera_crm_get_performance_summary( array( 'range_key' => $selected_range ) )
	: array(
		'range' => array(
			'key'       => $selected_range,
			'label'     => $range_options[ $selected_range ] ?? __( 'Last 30 days', 'peracrm' ),
			'date_from' => '',
			'date_to'   => '',
		),
		'cards' => array(
			'new_leads'     => 0,
			'qualified'     => 0,
			'junk'          => 0,
			'viewings'      => 0,
			'deals_created' => 0,
		),
		'attention' => array(
			'no_activity' => 0,
			'no_reminder' => 0,
			'untouched'   => 0,
			'overdue'     => 0,
		),
	);

$cards = is_array( $summary['cards'] ?? null ) ? $summary['cards'] : array();

$attention = is_array( $summary['attention'] ?? null ) ? $summary['attention'] : array();
$progress  = is_array( $summary['progress'] ?? null ) ? $summary['progress'] : array();

$attention_defs = array(
	'no_activity' => __( 'No Activity Yet', 'peracrm' ),
	'no_reminder' => __( 'No Reminder Set', 'peracrm' ),
	'untouched'   => __( 'Untouched (3+ days)', 'peracrm' ),
	'overdue'     => __( 'Overdue Reminders', 'peracrm' ),
);

$card_defs = array(
	'new_leads'     => __( 'Leads', 'peracrm' ),
	'qualified'     => __( 'Qualified', 'peracrm' ),
	'junk'          => __( 'Junk Leads', 'peracrm' ),
	'viewings'      => __( 'Viewings', 'peracrm' ),
	'deals_created' => __( 'Deals Created', 'peracrm' ),
);

$progress_count_defs = array(
        'leads'         => __( 'Leads', 'peracrm' ),
        'qualified'     => __( 'Qualified', 'peracrm' ),
        'viewings'      => __( 'Viewings', 'peracrm' ),
        'deals_created' => __( 'Deals Created', 'peracrm' ),
);

$progress_rate_defs = array(
        'qualified_rate' => __( 'Qualified Rate', 'peracrm' ),
        'viewing_rate'   => __( 'Viewing Rate', 'peracrm' ),
        'deal_rate'      => __( 'Deal Rate', 'peracrm' ),
);

$format_percent = static function ( float $ratio ): string {
        $percent       = max( 0, $ratio ) * 100;
        $rounded       = round( $percent, 1 );
        $decimals      = ( abs( $rounded - round( $rounded ) ) < 0.00001 ) ? 0 : 1;
        $number_string = number_format_i18n( $rounded, $decimals );
        return $number_string . '%';
};

peracrm_frontend_render_shell_header();
?>

<main id="primary" class="site-main crm-page crm-page--performance">
  <?php
  if ( function_exists( 'peracrm_frontend_render_partial' ) ) {
	  peracrm_frontend_render_partial(
		  'crm-header',
		  array(
			  'title'       => __( 'Performance', 'peracrm' ),
			  'description' => __( 'Topline CRM production metrics for the selected period.', 'peracrm' ),
			  'meta'        => (string) ( $summary['range']['label'] ?? '' ),
			  'active_view' => 'performance',
		  )
	  );
  }
  ?>

  <section class="content-panel content-panel--overlap-hero">
    <div class="content-panel-box border-dm crm-layout">
      <div class="crm-layout__main">
        <section class="crm-section crm-section--flush crm-performance-summary" aria-labelledby="crm-performance-summary-title">
          <header class="crm-section__header">
            <div class="crm-section__heading-group">
              <h2 id="crm-performance-summary-title" class="crm-section__title"><?php esc_html_e( 'Performance summary', 'peracrm' ); ?></h2>
              <p class="crm-section__description"><?php esc_html_e( 'Simple first-version dashboard by date range.', 'peracrm' ); ?></p>
            </div>
          </header>
          <div class="crm-section__body">
            <form method="get" action="<?php echo esc_url( home_url( '/crm/performance/' ) ); ?>" class="crm-performance-filters" aria-label="<?php esc_attr_e( 'Performance date range', 'peracrm' ); ?>">
              <label for="crm-performance-range">
                <span><?php esc_html_e( 'Date range', 'peracrm' ); ?></span>
                <select id="crm-performance-range" class="crm-search-control" name="range">
                  <?php foreach ( $range_options as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( (string) $key ); ?>" <?php selected( $selected_range, $key ); ?>><?php echo esc_html( (string) $label ); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <button type="submit" class="btn btn--solid btn--blue"><?php esc_html_e( 'Apply', 'peracrm' ); ?></button>
            </form>

            <div class="crm-performance-cards" role="list" aria-label="<?php esc_attr_e( 'Performance cards', 'peracrm' ); ?>">
              <?php foreach ( $card_defs as $key => $label ) : ?>
                <?php $value = isset( $cards[ $key ] ) ? max( 0, (int) $cards[ $key ] ) : 0; ?>
                <article class="crm-performance-card" role="listitem">
                  <p class="crm-performance-card__value"><?php echo esc_html( number_format_i18n( $value ) ); ?></p>
                  <p class="crm-performance-card__label"><?php echo esc_html( $label ); ?></p>
                </article>
              <?php endforeach; ?>
            </div>
            <section class="content-panel" aria-labelledby="crm-performance-attention-title">
              <h2 id="crm-performance-attention-title" class="crm-section__title"><?php esc_html_e( 'Attention Needed', 'peracrm' ); ?></h2>
              <div class="crm-performance-cards crm-performance-grid" role="list" aria-label="<?php esc_attr_e( 'Attention needed cards', 'peracrm' ); ?>">
                <?php foreach ( $attention_defs as $key => $label ) : ?>
                  <?php $value = isset( $attention[ $key ] ) ? max( 0, (int) $attention[ $key ] ) : 0; ?>
                  <article class="crm-performance-card" role="listitem">
                    <p class="crm-performance-card__value"><?php echo esc_html( number_format_i18n( $value ) ); ?></p>
                    <p class="crm-performance-card__label"><?php echo esc_html( $label ); ?></p>
                  </article>
                <?php endforeach; ?>
              </div>
            </section>
            <section class="content-panel" aria-labelledby="crm-performance-progress-title">
              <h2 id="crm-performance-progress-title" class="crm-section__title"><?php esc_html_e( 'Cohort Progress', 'peracrm' ); ?></h2>
              <div class="crm-performance-cards crm-performance-grid" role="list" aria-label="<?php esc_attr_e( 'Cohort progress count cards', 'peracrm' ); ?>">
                <?php foreach ( $progress_count_defs as $key => $label ) : ?>
                  <?php $value = isset( $progress[ $key ] ) ? max( 0, (int) $progress[ $key ] ) : 0; ?>
                  <article class="crm-performance-card" role="listitem">
                    <p class="crm-performance-card__value"><?php echo esc_html( number_format_i18n( $value ) ); ?></p>
                    <p class="crm-performance-card__label"><?php echo esc_html( $label ); ?></p>
                  </article>
                <?php endforeach; ?>
              </div>
              <div class="crm-performance-cards crm-performance-grid" role="list" aria-label="<?php esc_attr_e( 'Cohort progress rate cards', 'peracrm' ); ?>">
                <?php foreach ( $progress_rate_defs as $key => $label ) : ?>
                  <?php $value = isset( $progress[ $key ] ) ? (float) $progress[ $key ] : 0.0; ?>
                  <article class="crm-performance-card" role="listitem">
                    <p class="crm-performance-card__value"><?php echo esc_html( $format_percent( $value ) ); ?></p>
                    <p class="crm-performance-card__label"><?php echo esc_html( $label ); ?></p>
                  </article>
                <?php endforeach; ?>
              </div>
            </section>
          </div>
        </section>
      </div>
      <?php if ( function_exists( 'peracrm_frontend_render_partial' ) ) { peracrm_frontend_render_partial( 'crm-side-nav', array( 'active_view' => 'performance' ) ); } ?>
    </div>
  </section>
</main>

<?php
peracrm_frontend_render_shell_footer();
