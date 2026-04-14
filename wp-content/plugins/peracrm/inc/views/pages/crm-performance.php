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
		'sources' => array(),
		'stage_distribution' => array(),
	);

$cards = is_array( $summary['cards'] ?? null ) ? $summary['cards'] : array();

$attention = is_array( $summary['attention'] ?? null ) ? $summary['attention'] : array();
$progress  = is_array( $summary['progress'] ?? null ) ? $summary['progress'] : array();
$sources   = is_array( $summary['sources'] ?? null ) ? $summary['sources'] : array();
$comparison = is_array( $summary['comparison'] ?? null ) ? $summary['comparison'] : array();
$comparison_delta = is_array( $comparison['delta'] ?? null ) ? $comparison['delta'] : array();
$stage_distribution = is_array( $summary['stage_distribution'] ?? null ) ? $summary['stage_distribution'] : array();
$stage_distribution_total = max( 0, (int) ( $progress['leads'] ?? 0 ) );

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

$comparison_defs = array(
	'leads'          => array( 'label' => __( 'Leads', 'peracrm' ), 'type' => 'count' ),
	'qualified'      => array( 'label' => __( 'Qualified', 'peracrm' ), 'type' => 'count' ),
	'viewings'       => array( 'label' => __( 'Viewings', 'peracrm' ), 'type' => 'count' ),
	'deals_created'  => array( 'label' => __( 'Deals Created', 'peracrm' ), 'type' => 'count' ),
	'qualified_rate' => array( 'label' => __( 'Qualified Rate', 'peracrm' ), 'type' => 'rate' ),
	'viewing_rate'   => array( 'label' => __( 'Viewing Rate', 'peracrm' ), 'type' => 'rate' ),
	'deal_rate'      => array( 'label' => __( 'Deal Rate', 'peracrm' ), 'type' => 'rate' ),
);

$format_signed = static function ( float $value, int $decimals = 0 ): string {
	$rounded = round( $value, $decimals );
	$prefix  = $rounded > 0 ? '+' : '';
	return $prefix . number_format_i18n( $rounded, $decimals );
};

$format_comparison_delta = static function ( array $metric, string $type ) use ( $format_signed ): string {
	$abs = (float) ( $metric['abs'] ?? 0 );
	$pct = $metric['pct'] ?? 0;

	if ( 'rate' === $type ) {
		return sprintf( '%s pts', $format_signed( $abs * 100, 1 ) );
	}

	$abs_text = $format_signed( $abs, 0 );
	if ( null === $pct ) {
		return $abs > 0 ? sprintf( '%s (%s)', $abs_text, __( 'New', 'peracrm' ) ) : $abs_text;
	}

	return sprintf( '%s (%s%%)', $abs_text, $format_signed( (float) $pct, 1 ) );
};

$get_delta_state_class = static function ( array $metric ): string {
	$abs = (float) ( $metric['abs'] ?? 0 );
	if ( $abs > 0 ) {
		return 'is-positive';
	}

	if ( $abs < 0 ) {
		return 'is-negative';
	}

	return 'is-neutral';
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
            <section class="content-panel crm-performance-subsection" aria-labelledby="crm-performance-attention-title">
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
            <section class="content-panel crm-performance-subsection" aria-labelledby="crm-performance-progress-title">
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
            <section class="content-panel crm-performance-subsection crm-performance-subsection--sources" aria-labelledby="crm-performance-sources-title">
              <h2 id="crm-performance-sources-title" class="crm-section__title"><?php esc_html_e( 'Lead Sources', 'peracrm' ); ?></h2>
              <div class="crm-table-wrap crm-table-wrap--primitive">
                <table class="crm-table">
                  <thead>
                    <tr>
                      <th><?php esc_html_e( 'Source', 'peracrm' ); ?></th>
                      <th><?php esc_html_e( 'Leads', 'peracrm' ); ?></th>
                      <th><?php esc_html_e( 'Qualified', 'peracrm' ); ?></th>
                      <th><?php esc_html_e( 'Junk', 'peracrm' ); ?></th>
                      <th><?php esc_html_e( 'Viewings', 'peracrm' ); ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ( empty( $sources ) ) : ?>
                      <tr class="crm-table__empty">
                        <td colspan="5"><?php esc_html_e( 'No source data for this period.', 'peracrm' ); ?></td>
                      </tr>
                    <?php else : ?>
                      <?php foreach ( $sources as $row ) : ?>
                        <tr>
                          <td data-label="<?php esc_attr_e( 'Source', 'peracrm' ); ?>"><?php echo esc_html( (string) ( $row['source'] ?? '' ) ); ?></td>
                          <td data-label="<?php esc_attr_e( 'Leads', 'peracrm' ); ?>"><?php echo esc_html( number_format_i18n( max( 0, (int) ( $row['leads'] ?? 0 ) ) ) ); ?></td>
                          <td data-label="<?php esc_attr_e( 'Qualified', 'peracrm' ); ?>"><?php echo esc_html( number_format_i18n( max( 0, (int) ( $row['qualified'] ?? 0 ) ) ) ); ?></td>
                          <td data-label="<?php esc_attr_e( 'Junk', 'peracrm' ); ?>"><?php echo esc_html( number_format_i18n( max( 0, (int) ( $row['junk'] ?? 0 ) ) ) ); ?></td>
                          <td data-label="<?php esc_attr_e( 'Viewings', 'peracrm' ); ?>"><?php echo esc_html( number_format_i18n( max( 0, (int) ( $row['viewings'] ?? 0 ) ) ) ); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </section>
            <section class="content-panel crm-performance-subsection crm-performance-subsection--comparison" aria-labelledby="crm-performance-comparison-title">
              <h2 id="crm-performance-comparison-title" class="crm-section__title"><?php esc_html_e( 'Performance Comparison', 'peracrm' ); ?></h2>
              <div class="crm-performance-cards crm-performance-grid" role="list" aria-label="<?php esc_attr_e( 'Performance comparison cards', 'peracrm' ); ?>">
                <?php foreach ( $comparison_defs as $key => $metric_def ) : ?>
                  <?php
                  $metric = is_array( $comparison_delta[ $key ] ?? null ) ? $comparison_delta[ $key ] : array();
                  $current_value = (float) ( $metric['current'] ?? 0 );
                  $previous_value = (float) ( $metric['previous'] ?? 0 );
                  $type = (string) ( $metric_def['type'] ?? 'count' );
                  $current_display = 'rate' === $type ? $format_percent( $current_value ) : number_format_i18n( (int) round( $current_value ) );
                  $previous_display = 'rate' === $type ? $format_percent( $previous_value ) : number_format_i18n( (int) round( $previous_value ) );
                  $delta_state_class = $get_delta_state_class( $metric );
                  ?>
                  <article class="crm-performance-card crm-performance-card--comparison" role="listitem">
                    <p class="crm-performance-card__label"><?php echo esc_html( (string) $metric_def['label'] ); ?></p>
                    <p class="crm-performance-card__value"><?php echo esc_html( $current_display ); ?></p>
                    <p class="crm-performance-card__meta"><?php echo esc_html( sprintf( __( 'Prev: %s', 'peracrm' ), $previous_display ) ); ?></p>
                    <p class="crm-performance-card__delta <?php echo esc_attr( $delta_state_class ); ?>"><?php echo esc_html( $format_comparison_delta( $metric, $type ) ); ?></p>
                  </article>
                <?php endforeach; ?>
              </div>
            </section>
            <section class="content-panel crm-performance-subsection crm-performance-subsection--stage-distribution" aria-labelledby="crm-performance-stage-distribution-title">
              <h2 id="crm-performance-stage-distribution-title" class="crm-section__title"><?php esc_html_e( 'Stage Distribution', 'peracrm' ); ?></h2>
              <div class="crm-table-wrap crm-table-wrap--primitive">
                <table class="crm-table crm-table--stage-distribution">
                  <thead>
                    <tr>
                      <th><?php esc_html_e( 'Stage', 'peracrm' ); ?></th>
                      <th><?php esc_html_e( 'Count', 'peracrm' ); ?></th>
                      <th><?php esc_html_e( 'Share', 'peracrm' ); ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ( empty( $stage_distribution ) ) : ?>
                      <tr class="crm-table__empty">
                        <td colspan="3"><?php esc_html_e( 'No cohort records in this period.', 'peracrm' ); ?></td>
                      </tr>
                    <?php else : ?>
                      <?php foreach ( $stage_distribution as $row ) : ?>
                        <?php
                        $label = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
                        $count = max( 0, (int) ( $row['count'] ?? 0 ) );
                        $share = $stage_distribution_total > 0 ? $format_percent( $count / $stage_distribution_total ) : $format_percent( 0.0 );
                        ?>
                        <tr>
                          <td data-label="<?php esc_attr_e( 'Stage', 'peracrm' ); ?>"><?php echo esc_html( $label ); ?></td>
                          <td data-label="<?php esc_attr_e( 'Count', 'peracrm' ); ?>"><?php echo esc_html( number_format_i18n( $count ) ); ?></td>
                          <td data-label="<?php esc_attr_e( 'Share', 'peracrm' ); ?>"><?php echo esc_html( $share ); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
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
