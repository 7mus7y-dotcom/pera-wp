<?php
/**
 * Front-end CRM template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$crm_dashboard = function_exists( 'pera_crm_get_dashboard_data' )
	? pera_crm_get_dashboard_data()
	: array(
		'kpis'     => array(),
		'pipeline' => array(),
		'activity' => array(),
		'notices'  => array( __( 'CRM data unavailable.', 'hello-elementor-child' ) ),
	);

$kpis     = is_array( $crm_dashboard['kpis'] ?? null ) ? $crm_dashboard['kpis'] : array();
$pipeline = is_array( $crm_dashboard['pipeline'] ?? null ) ? $crm_dashboard['pipeline'] : array();
$activity = is_array( $crm_dashboard['activity'] ?? null ) ? $crm_dashboard['activity'] : array();
$notices  = is_array( $crm_dashboard['notices'] ?? null ) ? $crm_dashboard['notices'] : array();

$kpi_tiles = array(
	array(
		'label' => __( 'Total open leads', 'hello-elementor-child' ),
		'key'   => 'total_open_leads',
	),
	array(
		'label' => __( 'New enquiries', 'hello-elementor-child' ),
		'key'   => 'new_enquiries',
	),
	array(
		'label' => __( 'Qualified', 'hello-elementor-child' ),
		'key'   => 'qualified',
	),
	array(
		'label' => __( 'Viewing arranged', 'hello-elementor-child' ),
		'key'   => 'viewing_arranged',
	),
	array(
		'label' => __( 'Offer made', 'hello-elementor-child' ),
		'key'   => 'offer_made',
	),
	array(
		'label' => __( 'Overdue reminders', 'hello-elementor-child' ),
		'key'   => 'overdue_reminders',
	),
);

get_header();
?>

<main id="primary" class="site-main crm-page">
  <section class="hero hero--left hero--fit" id="crm-hero">
    <div class="hero-content container">
      <h1><?php echo esc_html__( 'CRM', 'hello-elementor-child' ); ?></h1>
      <p class="lead"><?php echo esc_html__( 'Staff workspace for daily pipeline, workload, and account visibility.', 'hello-elementor-child' ); ?></p>
      <div class="hero-actions hero-pills" aria-label="<?php echo esc_attr__( 'CRM quick statuses', 'hello-elementor-child' ); ?>">
        <span class="pill pill--brand"><?php echo esc_html__( 'Live', 'hello-elementor-child' ); ?></span>
        <span class="pill pill--outline"><?php echo esc_html__( 'Internal', 'hello-elementor-child' ); ?></span>
        <span class="pill pill--outline"><?php echo esc_html__( 'Read only', 'hello-elementor-child' ); ?></span>
      </div>
    </div>
  </section>

  <section class="content-panel content-panel--overlap-hero">
    <div class="content-panel-box border-dm">
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
				  <?php
				  $summary = (string) ( $item['summary'] ?? '' );
				  $edit    = (string) ( $item['edit_url'] ?? '' );
				  ?>
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
    </div>
  </section>
</main>

<?php
get_footer();
