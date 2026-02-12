<?php
/**
 * Front-end CRM template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$view         = sanitize_key( (string) get_query_var( 'pera_crm_view', 'overview' ) );
$current_page = max( 1, (int) get_query_var( 'paged', 1 ) );
$is_leads     = 'leads' === $view;

$crm_dashboard = function_exists( 'pera_crm_get_dashboard_data' )
	? pera_crm_get_dashboard_data()
	: array(
		'kpis'     => array(),
		'pipeline' => array(),
		'activity' => array(),
		'notices'  => array( __( 'CRM data unavailable.', 'hello-elementor-child' ) ),
	);

$leads_data = $is_leads && function_exists( 'pera_crm_get_leads_view_data' )
	? pera_crm_get_leads_view_data( $current_page, 20 )
	: array();

$kpis     = is_array( $crm_dashboard['kpis'] ?? null ) ? $crm_dashboard['kpis'] : array();
$pipeline = is_array( $crm_dashboard['pipeline'] ?? null ) ? $crm_dashboard['pipeline'] : array();
$activity = is_array( $crm_dashboard['activity'] ?? null ) ? $crm_dashboard['activity'] : array();
$notices  = is_array( $crm_dashboard['notices'] ?? null ) ? $crm_dashboard['notices'] : array();

$kpi_tiles = array(
	array( 'label' => __( 'Total open leads', 'hello-elementor-child' ), 'key' => 'total_open_leads' ),
	array( 'label' => __( 'New enquiries', 'hello-elementor-child' ), 'key' => 'new_enquiries' ),
	array( 'label' => __( 'Qualified', 'hello-elementor-child' ), 'key' => 'qualified' ),
	array( 'label' => __( 'Viewing arranged', 'hello-elementor-child' ), 'key' => 'viewing_arranged' ),
	array( 'label' => __( 'Offer made', 'hello-elementor-child' ), 'key' => 'offer_made' ),
	array( 'label' => __( 'Overdue reminders', 'hello-elementor-child' ), 'key' => 'overdue_reminders' ),
);

$stages = function_exists( 'pera_crm_get_pipeline_stages' ) ? pera_crm_get_pipeline_stages() : array();

get_header();
?>

<main id="primary" class="site-main crm-page crm-page--<?php echo esc_attr( $is_leads ? 'leads' : 'overview' ); ?>">
  <section class="hero hero--left hero--fit" id="crm-hero">
    <div class="hero-content container">
      <h1><?php echo esc_html__( 'CRM', 'hello-elementor-child' ); ?></h1>
      <p class="lead"><?php echo esc_html__( 'Staff workspace for daily pipeline, workload, and account visibility.', 'hello-elementor-child' ); ?></p>
      <div class="hero-actions hero-pills" aria-label="<?php echo esc_attr__( 'CRM quick statuses', 'hello-elementor-child' ); ?>">
        <span class="pill pill--brand"><?php echo esc_html__( 'Live', 'hello-elementor-child' ); ?></span>
        <span class="pill pill--outline"><?php echo esc_html__( 'Internal', 'hello-elementor-child' ); ?></span>
        <span class="pill pill--outline"><?php echo esc_html__( 'Read only', 'hello-elementor-child' ); ?></span>
      </div>
      <nav class="crm-subnav" aria-label="<?php echo esc_attr__( 'CRM sections', 'hello-elementor-child' ); ?>">
        <a class="pill <?php echo esc_attr( $is_leads ? 'pill--outline' : 'pill--brand' ); ?>" href="<?php echo esc_url( home_url( '/crm/' ) ); ?>"><?php echo esc_html__( 'Overview', 'hello-elementor-child' ); ?></a>
        <a class="pill <?php echo esc_attr( $is_leads ? 'pill--brand' : 'pill--outline' ); ?>" href="<?php echo esc_url( home_url( '/crm/leads/' ) ); ?>"><?php echo esc_html__( 'Leads', 'hello-elementor-child' ); ?></a>
      </nav>
    </div>
  </section>

  <section class="content-panel content-panel--overlap-hero">
    <div class="content-panel-box border-dm">
		<?php if ( ! $is_leads ) : ?>
      <div class="section-header">
        <h2><?php echo esc_html__( 'Overview', 'hello-elementor-child' ); ?></h2>
        <p><?php echo esc_html__( 'Live CRM dashboard data loaded from available CRM helper functions.', 'hello-elementor-child' ); ?></p>
      </div>

			<?php if ( ! empty( $notices ) ) : ?>
      <section class="section" aria-label="<?php echo esc_attr__( 'CRM notices', 'hello-elementor-child' ); ?>">
				<?php foreach ( $notices as $notice ) : ?>
        <article class="card-shell">
          <p class="pill pill--outline"><?php echo esc_html__( 'Notice', 'hello-elementor-child' ); ?></p>
          <p><?php echo esc_html( (string) $notice ); ?></p>
        </article>
				<?php endforeach; ?>
      </section>
			<?php endif; ?>

      <section class="section" aria-labelledby="crm-kpi-heading">
        <header class="section-header">
          <h2 id="crm-kpi-heading"><?php echo esc_html__( 'KPI Snapshot', 'hello-elementor-child' ); ?></h2>
        </header>
        <div class="grid-3 crm-kpi-grid">
				<?php foreach ( $kpi_tiles as $tile ) : ?>
          <article class="card-shell">
            <p class="pill pill--outline"><?php echo esc_html( $tile['label'] ); ?></p>
            <h3><?php echo esc_html( (string) ( (int) ( $kpis[ $tile['key'] ] ?? 0 ) ) ); ?></h3>
          </article>
				<?php endforeach; ?>
        </div>
      </section>

      <section class="section" aria-labelledby="crm-work-heading">
        <article class="card-shell">
          <header class="section-header">
            <h2 id="crm-work-heading"><?php echo esc_html__( 'Pipeline Preview', 'hello-elementor-child' ); ?></h2>
            <p><?php echo esc_html__( 'Count of leads in each canonical pipeline stage.', 'hello-elementor-child' ); ?></p>
          </header>
          <div class="grid-3 crm-kpi-grid">
				<?php foreach ( $pipeline as $stage ) : ?>
            <article class="card-shell">
              <p class="pill pill--outline"><?php echo esc_html( (string) ( $stage['label'] ?? '' ) ); ?></p>
              <h3><?php echo esc_html( (string) ( (int) ( $stage['count'] ?? 0 ) ) ); ?></h3>
            </article>
				<?php endforeach; ?>
          </div>
        </article>
      </section>

      <section class="section" aria-labelledby="crm-activity-heading">
        <article class="card-shell crm-activity-card">
          <header class="section-header">
            <h2 id="crm-activity-heading"><?php echo esc_html__( 'Recent Activity', 'hello-elementor-child' ); ?></h2>
            <p><?php echo esc_html__( 'Latest 20 CRM activity entries.', 'hello-elementor-child' ); ?></p>
          </header>
				<?php if ( empty( $activity ) ) : ?>
          <p><?php echo esc_html__( 'CRM data unavailable.', 'hello-elementor-child' ); ?></p>
				<?php else : ?>
          <ul>
					<?php foreach ( $activity as $item ) : ?>
            <li>
              <strong><?php echo esc_html( (string) ( $item['time'] ?? '' ) ); ?></strong>
              —
              <span class="pill pill--outline"><?php echo esc_html( (string) ( $item['type'] ?? '' ) ); ?></span>
						<?php $summary = (string) ( $item['summary'] ?? '' ); ?>
						<?php $edit = (string) ( $item['edit_url'] ?? '' ); ?>
						<?php if ( '' !== $edit ) : ?>
              <a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $summary ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $summary ); ?>
						<?php endif; ?>
            </li>
					<?php endforeach; ?>
          </ul>
				<?php endif; ?>
        </article>
      </section>

		<?php else : ?>
			<?php
			$items        = is_array( $leads_data['items'] ?? null ) ? $leads_data['items'] : array();
			$total        = (int) ( $leads_data['total'] ?? 0 );
			$per_page     = (int) ( $leads_data['per_page'] ?? 20 );
			$total_pages  = max( 1, (int) ( $leads_data['total_pages'] ?? 1 ) );
			$current_page = max( 1, (int) ( $leads_data['current_page'] ?? 1 ) );
			$from         = $total > 0 ? ( ( $current_page - 1 ) * $per_page ) + 1 : 0;
			$to           = min( $current_page * $per_page, $total );
			?>
      <div class="crm-leads-toolbar">
        <div>
          <h2><?php echo esc_html__( 'Leads', 'hello-elementor-child' ); ?></h2>
          <p><?php echo esc_html( sprintf( __( 'Showing %1$d–%2$d of %3$d leads', 'hello-elementor-child' ), $from, $to, $total ) ); ?></p>
        </div>
        <div class="crm-view-toggle" data-crm-view-toggle>
          <button type="button" class="pill pill--brand" data-view="table"><?php echo esc_html__( 'Table', 'hello-elementor-child' ); ?></button>
          <button type="button" class="pill pill--outline" data-view="cards"><?php echo esc_html__( 'Cards', 'hello-elementor-child' ); ?></button>
        </div>
      </div>

      <div class="crm-leads-table-wrap" data-crm-view="table">
        <table class="crm-leads-table">
          <thead>
            <tr>
              <th><?php echo esc_html__( 'Name', 'hello-elementor-child' ); ?></th>
              <th><?php echo esc_html__( 'Stage', 'hello-elementor-child' ); ?></th>
              <th><?php echo esc_html__( 'Engagement', 'hello-elementor-child' ); ?></th>
              <th><?php echo esc_html__( 'Disposition', 'hello-elementor-child' ); ?></th>
              <th><?php echo esc_html__( 'Last activity', 'hello-elementor-child' ); ?></th>
              <th><?php echo esc_html__( 'Actions', 'hello-elementor-child' ); ?></th>
            </tr>
          </thead>
          <tbody>
				<?php if ( empty( $items ) ) : ?>
            <tr>
              <td colspan="6"><?php echo esc_html__( 'No leads found for this scope.', 'hello-elementor-child' ); ?></td>
            </tr>
				<?php else : ?>
					<?php foreach ( $items as $lead ) : ?>
            <tr>
              <td><?php echo esc_html( (string) $lead['title'] ); ?></td>
              <td><span class="pill pill--outline"><?php echo esc_html( (string) ( $stages[ $lead['stage'] ] ?? $lead['stage'] ) ); ?></span></td>
              <td><?php echo esc_html( (string) $lead['engagement_state'] ); ?></td>
              <td><?php echo esc_html( (string) $lead['disposition'] ); ?></td>
              <td><?php echo esc_html( '' !== $lead['last_activity'] ? (string) $lead['last_activity'] : '—' ); ?></td>
              <td><a href="<?php echo esc_url( (string) $lead['edit_url'] ); ?>"><?php echo esc_html__( 'Open', 'hello-elementor-child' ); ?></a></td>
            </tr>
					<?php endforeach; ?>
				<?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="grid-3 crm-lead-cards" data-crm-view="cards">
				<?php foreach ( $items as $lead ) : ?>
        <article class="card-shell">
          <h3><?php echo esc_html( (string) $lead['title'] ); ?></h3>
          <p><span class="pill pill--outline"><?php echo esc_html( (string) ( $stages[ $lead['stage'] ] ?? $lead['stage'] ) ); ?></span></p>
          <p><strong><?php echo esc_html__( 'Engagement:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( (string) $lead['engagement_state'] ); ?></p>
          <p><strong><?php echo esc_html__( 'Disposition:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( (string) $lead['disposition'] ); ?></p>
          <p><strong><?php echo esc_html__( 'Last activity:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( '' !== $lead['last_activity'] ? (string) $lead['last_activity'] : '—' ); ?></p>
          <p><a href="<?php echo esc_url( (string) $lead['edit_url'] ); ?>"><?php echo esc_html__( 'Open', 'hello-elementor-child' ); ?></a></p>
        </article>
				<?php endforeach; ?>
      </div>

			<?php
			$pagination = paginate_links(
				array(
					'base'      => trailingslashit( home_url( '/crm/leads/%_%' ) ),
					'format'    => 'page/%#%/',
					'current'   => $current_page,
					'total'     => $total_pages,
					'type'      => 'list',
					'prev_text' => __( 'Previous', 'hello-elementor-child' ),
					'next_text' => __( 'Next', 'hello-elementor-child' ),
					'add_args'  => false,
				)
			);
			if ( is_string( $pagination ) ) {
				$pagination = str_replace( '/crm/leads/page/1/', '/crm/leads/', $pagination );
				echo wp_kses_post( $pagination );
			}
			?>
		<?php endif; ?>
    </div>
  </section>
</main>

<?php
get_footer();
