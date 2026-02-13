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
$is_tasks     = 'tasks' === $view;

$crm_dashboard = function_exists( 'pera_crm_get_dashboard_data' )
	? pera_crm_get_dashboard_data()
	: array(
		'kpis'          => array(),
		'pipeline'      => array(),
		'activity'      => array(),
		'todays_tasks'  => array(),
		'overdue_tasks' => array(),
		'new_leads'     => array(),
		'notices'       => array( __( 'CRM data unavailable.', 'hello-elementor-child' ) ),
	);

$leads_data = $is_leads && function_exists( 'pera_crm_get_leads_view_data' )
	? pera_crm_get_leads_view_data( $current_page, 20 )
	: array();

$tasks_data = $is_tasks && function_exists( 'pera_crm_get_tasks_view_data' )
	? pera_crm_get_tasks_view_data()
	: array();

$kpis          = is_array( $crm_dashboard['kpis'] ?? null ) ? $crm_dashboard['kpis'] : array();
$pipeline      = is_array( $crm_dashboard['pipeline'] ?? null ) ? $crm_dashboard['pipeline'] : array();
$activity      = is_array( $crm_dashboard['activity'] ?? null ) ? $crm_dashboard['activity'] : array();
$todays_tasks  = is_array( $crm_dashboard['todays_tasks'] ?? null ) ? $crm_dashboard['todays_tasks'] : array();
$overdue_tasks = is_array( $crm_dashboard['overdue_tasks'] ?? null ) ? $crm_dashboard['overdue_tasks'] : array();
$new_leads     = is_array( $crm_dashboard['new_leads'] ?? null ) ? $crm_dashboard['new_leads'] : array();
$notices       = is_array( $crm_dashboard['notices'] ?? null ) ? $crm_dashboard['notices'] : array();

$today_tasks_page   = max( 1, isset( $_GET['today_page'] ) ? absint( wp_unslash( (string) $_GET['today_page'] ) ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$overdue_tasks_page = max( 1, isset( $_GET['overdue_page'] ) ? absint( wp_unslash( (string) $_GET['overdue_page'] ) ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$tasks_per_page     = 20;
$crm_current_url    = home_url( wp_unslash( (string) ( $_SERVER['REQUEST_URI'] ?? '/crm/' ) ) );

$kpi_tiles = array(
	array( 'label' => __( 'Total open leads', 'hello-elementor-child' ), 'key' => 'total_open_leads' ),
	array( 'label' => __( 'New enquiries', 'hello-elementor-child' ), 'key' => 'new_enquiries' ),
	array( 'label' => __( 'Qualified', 'hello-elementor-child' ), 'key' => 'qualified' ),
	array( 'label' => __( 'Viewing arranged', 'hello-elementor-child' ), 'key' => 'viewing_arranged' ),
	array( 'label' => __( 'Offer made', 'hello-elementor-child' ), 'key' => 'offer_made' ),
	array( 'label' => __( 'Overdue reminders', 'hello-elementor-child' ), 'key' => 'overdue_reminders' ),
);

$stages       = function_exists( 'pera_crm_get_pipeline_stages' ) ? pera_crm_get_pipeline_stages() : array();
$new_lead_url = home_url( '/crm/new/' );

get_header();
?>

<main id="primary" class="site-main crm-page crm-page--<?php echo esc_attr( $is_leads ? 'leads' : ( $is_tasks ? 'tasks' : 'overview' ) ); ?>">
  <section class="hero hero--left hero--fit" id="crm-hero">
    <div class="hero-content container">
      <h1><?php echo esc_html__( 'CRM', 'hello-elementor-child' ); ?></h1>
      <p class="lead"><?php echo esc_html__( 'Staff workspace for daily pipeline, workload, and account visibility.', 'hello-elementor-child' ); ?></p>
      <div class="hero-actions hero-pills" aria-label="<?php echo esc_attr__( 'CRM quick statuses', 'hello-elementor-child' ); ?>">
        <span class="pill pill--brand"><?php echo esc_html__( 'Live', 'hello-elementor-child' ); ?></span>
        <span class="pill pill--outline"><?php echo esc_html__( 'Internal', 'hello-elementor-child' ); ?></span>
        <span class="pill pill--outline"><?php echo esc_html__( 'Read only', 'hello-elementor-child' ); ?></span>
        <a class="btn btn--solid btn--blue" href="<?php echo esc_url( $new_lead_url ); ?>"><?php echo esc_html__( 'Add new lead', 'hello-elementor-child' ); ?></a>
      </div>
      <nav class="crm-subnav" aria-label="<?php echo esc_attr__( 'CRM sections', 'hello-elementor-child' ); ?>">
        <a class="btn <?php echo esc_attr( ( ! $is_leads && ! $is_tasks ) ? 'btn--solid' : 'btn--ghost' ); ?> btn--blue" href="<?php echo esc_url( home_url( '/crm/' ) ); ?>"><?php echo esc_html__( 'Overview', 'hello-elementor-child' ); ?></a>
	        <a class="btn <?php echo esc_attr( $is_leads ? 'btn--solid' : 'btn--ghost' ); ?> btn--blue" href="<?php echo esc_url( home_url( '/crm/clients/' ) ); ?>"><?php echo esc_html__( 'Clients', 'hello-elementor-child' ); ?></a>
        <a class="btn <?php echo esc_attr( $is_tasks ? 'btn--solid' : 'btn--ghost' ); ?> btn--blue" href="<?php echo esc_url( home_url( '/crm/tasks/' ) ); ?>"><?php echo esc_html__( 'Tasks', 'hello-elementor-child' ); ?></a>
        <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( home_url( '/crm/pipeline/' ) ); ?>"><?php echo esc_html__( 'Pipeline', 'hello-elementor-child' ); ?></a>
      </nav>
    </div>
  </section>

  <section class="content-panel content-panel--overlap-hero">
    <div class="content-panel-box border-dm">
		<?php if ( ! $is_leads && ! $is_tasks ) : ?>
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

      <section class="section" aria-labelledby="crm-today-tasks-heading">
        <article class="card-shell">
          <header class="section-header">
            <h2 id="crm-today-tasks-heading"><?php echo esc_html__( "Today's Tasks", 'hello-elementor-child' ); ?></h2>
          </header>
          <?php if ( empty( $todays_tasks ) ) : ?>
            <p><?php echo esc_html__( 'No tasks due today.', 'hello-elementor-child' ); ?></p>
          <?php else : ?>
            <?php
            $today_total      = count( $todays_tasks );
            $today_total_page = max( 1, (int) ceil( $today_total / $tasks_per_page ) );
            $today_tasks      = array_slice( $todays_tasks, ( $today_tasks_page - 1 ) * $tasks_per_page, $tasks_per_page );
            ?>
            <ul class="crm-list">
            <?php foreach ( $today_tasks as $task ) : ?>
              <li>
                <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( home_url( '/crm/client/' . (int) $task['lead_id'] . '/' ) ); ?>"><?php echo esc_html( (string) ( $task['lead_name'] ?: __( 'Untitled lead', 'hello-elementor-child' ) ) ); ?></a>
                <span class="pill pill--outline"><?php echo esc_html( (string) $task['due_date'] ); ?></span>
                <span><?php echo esc_html( (string) $task['reminder_note'] ); ?></span>
                <?php $task_status = sanitize_key( (string) ( $task['status'] ?? 'pending' ) ); ?>
                <?php if ( ! empty( $task['reminder_id'] ) && 'pending' === $task_status ) : ?>
                <form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>">
                  <input type="hidden" name="action" value="peracrm_update_reminder_status">
                  <input type="hidden" name="peracrm_reminder_id" value="<?php echo esc_attr( (string) absint( $task['reminder_id'] ) ); ?>">
                  <input type="hidden" name="peracrm_status" value="done">
                  <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $crm_current_url ); ?>">
                  <input type="hidden" name="peracrm_context" value="frontend">
                  <?php wp_nonce_field( 'peracrm_update_reminder_status', 'peracrm_update_reminder_status_nonce' ); ?>
                  <button type="submit" class="btn btn--ghost btn--blue"><?php echo esc_html__( 'Mark done', 'hello-elementor-child' ); ?></button>
                </form>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
            </ul>
            <?php if ( $today_total_page > 1 ) : ?>
              <?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'today_page', '%#%', home_url( '/crm/' ) ), 'format' => '', 'current' => $today_tasks_page, 'total' => $today_total_page, 'type' => 'list', 'prev_text' => __( 'Previous', 'hello-elementor-child' ), 'next_text' => __( 'Next', 'hello-elementor-child' ) ) ) ); ?>
            <?php endif; ?>
          <?php endif; ?>
        </article>
      </section>

      <section class="section" aria-labelledby="crm-overdue-tasks-heading">
        <article class="card-shell">
          <header class="section-header">
            <h2 id="crm-overdue-tasks-heading"><?php echo esc_html__( 'Overdue Tasks', 'hello-elementor-child' ); ?></h2>
          </header>
          <?php if ( empty( $overdue_tasks ) ) : ?>
            <p><?php echo esc_html__( 'No overdue tasks.', 'hello-elementor-child' ); ?></p>
          <?php else : ?>
            <?php
            $overdue_total      = count( $overdue_tasks );
            $overdue_total_page = max( 1, (int) ceil( $overdue_total / $tasks_per_page ) );
            $overdue_page_rows  = array_slice( $overdue_tasks, ( $overdue_tasks_page - 1 ) * $tasks_per_page, $tasks_per_page );
            ?>
            <ul class="crm-list">
            <?php foreach ( $overdue_page_rows as $task ) : ?>
              <li>
                <a class="btn btn--ghost btn--red" href="<?php echo esc_url( home_url( '/crm/client/' . (int) $task['lead_id'] . '/' ) ); ?>"><?php echo esc_html( (string) ( $task['lead_name'] ?: __( 'Untitled lead', 'hello-elementor-child' ) ) ); ?></a>
                <span class="pill pill--red"><?php echo esc_html( (string) $task['due_date'] ); ?></span>
                <span><?php echo esc_html( (string) $task['reminder_note'] ); ?></span>
                <?php $task_status = sanitize_key( (string) ( $task['status'] ?? 'pending' ) ); ?>
                <?php if ( ! empty( $task['reminder_id'] ) && 'pending' === $task_status ) : ?>
                <form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>">
                  <input type="hidden" name="action" value="peracrm_update_reminder_status">
                  <input type="hidden" name="peracrm_reminder_id" value="<?php echo esc_attr( (string) absint( $task['reminder_id'] ) ); ?>">
                  <input type="hidden" name="peracrm_status" value="done">
                  <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $crm_current_url ); ?>">
                  <input type="hidden" name="peracrm_context" value="frontend">
                  <?php wp_nonce_field( 'peracrm_update_reminder_status', 'peracrm_update_reminder_status_nonce' ); ?>
                  <button type="submit" class="btn btn--ghost btn--blue"><?php echo esc_html__( 'Mark done', 'hello-elementor-child' ); ?></button>
                </form>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
            </ul>
            <?php if ( $overdue_total_page > 1 ) : ?>
              <?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'overdue_page', '%#%', home_url( '/crm/' ) ), 'format' => '', 'current' => $overdue_tasks_page, 'total' => $overdue_total_page, 'type' => 'list', 'prev_text' => __( 'Previous', 'hello-elementor-child' ), 'next_text' => __( 'Next', 'hello-elementor-child' ) ) ) ); ?>
            <?php endif; ?>
          <?php endif; ?>
        </article>
      </section>

      <section class="section" aria-labelledby="crm-new-leads-heading">
        <article class="card-shell">
          <header class="section-header">
            <h2 id="crm-new-leads-heading"><?php echo esc_html__( 'New Leads', 'hello-elementor-child' ); ?></h2>
          </header>
          <?php if ( empty( $new_leads ) ) : ?>
            <p><?php echo esc_html__( 'No new leads found.', 'hello-elementor-child' ); ?></p>
          <?php else : ?>
            <ul class="crm-list">
            <?php foreach ( $new_leads as $lead ) : ?>
              <li><a class="btn btn--ghost btn--blue" href="<?php echo esc_url( (string) $lead['url'] ); ?>"><?php echo esc_html( (string) $lead['name'] ); ?></a></li>
            <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </article>
      </section>

      <section class="section" aria-labelledby="crm-activity-heading">
        <article class="card-shell">
          <header class="section-header">
            <h2 id="crm-activity-heading"><?php echo esc_html__( 'Latest Activity', 'hello-elementor-child' ); ?></h2>
          </header>
          <?php if ( empty( $activity ) ) : ?>
            <p><?php echo esc_html__( 'No activity available.', 'hello-elementor-child' ); ?></p>
          <?php else : ?>
          <ul class="crm-list">
            <?php foreach ( $activity as $item ) : ?>
              <li>
                <span class="pill pill--outline"><?php echo esc_html( (string) ( $item['type'] ?? '' ) ); ?></span>
                <strong><?php echo esc_html( (string) ( $item['time'] ?? '' ) ); ?></strong>
                <span><?php echo esc_html( (string) ( $item['summary'] ?? '' ) ); ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </article>
      </section>

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

      <section class="section" aria-labelledby="crm-pipeline-heading">
        <article class="card-shell">
          <header class="section-header">
            <h2 id="crm-pipeline-heading"><?php echo esc_html__( 'Pipeline View', 'hello-elementor-child' ); ?></h2>
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


		<?php elseif ( $is_tasks ) : ?>
			<?php
			$task_rows      = is_array( $tasks_data['all'] ?? null ) ? $tasks_data['all'] : array();
			$today_rows     = is_array( $tasks_data['today'] ?? null ) ? $tasks_data['today'] : array();
			$outstanding    = is_array( $tasks_data['outstanding'] ?? null ) ? $tasks_data['outstanding'] : array();
			$upcoming       = is_array( $tasks_data['upcoming'] ?? null ) ? $tasks_data['upcoming'] : array();
			$show_assigned  = ! empty( $task_rows ) && empty( $tasks_data['is_employee'] );
			$tasks_page_url = home_url( wp_unslash( (string) ( $_SERVER['REQUEST_URI'] ?? '/crm/tasks/' ) ) );
			?>
      <div class="crm-leads-toolbar">
        <div>
          <h2><?php echo esc_html__( 'Tasks', 'hello-elementor-child' ); ?></h2>
          <p><?php echo esc_html( sprintf( __( '%d open tasks (reminders)', 'hello-elementor-child' ), count( $task_rows ) ) ); ?></p>
        </div>
        <div class="crm-toolbar-actions">
          <div class="crm-view-toggle" data-crm-view-toggle data-storage-key="peracrm_tasks_view">
            <button type="button" class="btn btn--solid btn--blue" data-view="cards" aria-pressed="true"><?php echo esc_html__( 'Cards', 'hello-elementor-child' ); ?></button>
            <button type="button" class="btn btn--ghost btn--blue" data-view="table" aria-pressed="false"><?php echo esc_html__( 'Table', 'hello-elementor-child' ); ?></button>
          </div>
        </div>
      </div>

      <div class="crm-lead-cards" data-crm-view="cards">
        <section class="section" aria-labelledby="crm-tasks-today-heading">
          <article class="card-shell">
            <header class="section-header"><h3 id="crm-tasks-today-heading"><?php echo esc_html__( "Today's Tasks", 'hello-elementor-child' ); ?></h3></header>
            <?php if ( empty( $today_rows ) ) : ?>
              <p><?php echo esc_html__( 'No tasks due today.', 'hello-elementor-child' ); ?></p>
            <?php else : ?>
              <ul class="crm-list">
                <?php foreach ( $today_rows as $task ) : ?>
                  <li>
                    <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( (string) $task['client_url'] ); ?>"><?php echo esc_html( (string) $task['client_name'] ); ?></a>
                    <span class="pill pill--outline"><?php echo esc_html( (string) $task['due_display'] ); ?></span>
                    <span><?php echo esc_html( (string) $task['note'] ); ?></span>
                    <?php if ( $show_assigned ) : ?><span><strong><?php echo esc_html__( 'Assigned:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( '' !== (string) $task['assigned_to'] ? (string) $task['assigned_to'] : '—' ); ?></span><?php endif; ?>
                    <span class="pill pill--outline"><?php echo esc_html( (string) $task['status_label'] ); ?></span>
                    <?php if ( ! empty( $task['reminder_id'] ) ) : ?>
                    <form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>">
                      <input type="hidden" name="action" value="peracrm_update_reminder_status">
                      <input type="hidden" name="peracrm_reminder_id" value="<?php echo esc_attr( (string) absint( $task['reminder_id'] ) ); ?>">
                      <input type="hidden" name="peracrm_status" value="done">
                      <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $tasks_page_url ); ?>">
                      <input type="hidden" name="peracrm_context" value="frontend">
                      <?php wp_nonce_field( 'peracrm_update_reminder_status', 'peracrm_update_reminder_status_nonce' ); ?>
                      <button type="submit" class="btn btn--ghost btn--blue"><?php echo esc_html__( 'Mark done', 'hello-elementor-child' ); ?></button>
                    </form>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </article>
        </section>

        <section class="section" aria-labelledby="crm-tasks-outstanding-heading">
          <article class="card-shell">
            <header class="section-header"><h3 id="crm-tasks-outstanding-heading"><?php echo esc_html__( 'Outstanding Tasks', 'hello-elementor-child' ); ?></h3></header>
            <?php if ( empty( $outstanding ) ) : ?>
              <p><?php echo esc_html__( 'No outstanding tasks.', 'hello-elementor-child' ); ?></p>
            <?php else : ?>
              <ul class="crm-list">
                <?php foreach ( $outstanding as $task ) : ?>
                  <li>
                    <a class="btn btn--ghost btn--red" href="<?php echo esc_url( (string) $task['client_url'] ); ?>"><?php echo esc_html( (string) $task['client_name'] ); ?></a>
                    <span class="pill pill--red"><?php echo esc_html( (string) $task['due_display'] ); ?></span>
                    <span><?php echo esc_html( (string) $task['note'] ); ?></span>
                    <?php if ( $show_assigned ) : ?><span><strong><?php echo esc_html__( 'Assigned:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( '' !== (string) $task['assigned_to'] ? (string) $task['assigned_to'] : '—' ); ?></span><?php endif; ?>
                    <span class="pill pill--red"><?php echo esc_html( (string) $task['status_label'] ); ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </article>
        </section>

        <section class="section" aria-labelledby="crm-tasks-upcoming-heading">
          <article class="card-shell">
            <header class="section-header"><h3 id="crm-tasks-upcoming-heading"><?php echo esc_html__( 'Upcoming Tasks', 'hello-elementor-child' ); ?></h3></header>
            <?php if ( empty( $upcoming ) ) : ?>
              <p><?php echo esc_html__( 'No upcoming tasks.', 'hello-elementor-child' ); ?></p>
            <?php else : ?>
              <ul class="crm-list">
                <?php foreach ( $upcoming as $task ) : ?>
                  <li>
                    <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( (string) $task['client_url'] ); ?>"><?php echo esc_html( (string) $task['client_name'] ); ?></a>
                    <span class="pill pill--outline"><?php echo esc_html( (string) $task['due_display'] ); ?></span>
                    <span><?php echo esc_html( (string) $task['note'] ); ?></span>
                    <?php if ( $show_assigned ) : ?><span><strong><?php echo esc_html__( 'Assigned:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( '' !== (string) $task['assigned_to'] ? (string) $task['assigned_to'] : '—' ); ?></span><?php endif; ?>
                    <span class="pill pill--outline"><?php echo esc_html( (string) $task['status_label'] ); ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </article>
        </section>
      </div>

      <div class="crm-leads-table-wrap is-hidden" data-crm-view="table">
        <table class="crm-leads-table" data-crm-sort-table>
          <thead>
            <tr>
              <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="due"><?php echo esc_html__( 'Due', 'hello-elementor-child' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
              <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="client"><?php echo esc_html__( 'Client', 'hello-elementor-child' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
              <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="note"><?php echo esc_html__( 'Note', 'hello-elementor-child' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
              <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="assigned"><?php echo esc_html__( 'Assigned', 'hello-elementor-child' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
              <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="status"><?php echo esc_html__( 'Status', 'hello-elementor-child' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
            </tr>
          </thead>
          <tbody>
            <?php if ( empty( $task_rows ) ) : ?>
            <tr><td colspan="5"><?php echo esc_html__( 'No open tasks found.', 'hello-elementor-child' ); ?></td></tr>
            <?php else : ?>
              <?php foreach ( $task_rows as $task ) : ?>
              <tr data-sort-row data-row-url="<?php echo esc_url( (string) $task['client_url'] ); ?>" data-due="<?php echo esc_attr( (string) ( $task['due_ts'] ?? 0 ) ); ?>" data-client="<?php echo esc_attr( strtolower( (string) $task['client_name'] ) ); ?>" data-note="<?php echo esc_attr( strtolower( (string) $task['note'] ) ); ?>" data-assigned="<?php echo esc_attr( strtolower( (string) $task['assigned_to'] ) ); ?>" data-status="<?php echo esc_attr( strtolower( (string) $task['status_label'] ) ); ?>">
                <td><?php echo esc_html( (string) $task['due_display'] ); ?></td>
                <td><a href="<?php echo esc_url( (string) $task['client_url'] ); ?>"><?php echo esc_html( (string) $task['client_name'] ); ?></a></td>
                <td><?php echo esc_html( '' !== (string) $task['note'] ? (string) $task['note'] : '—' ); ?></td>
                <td><?php echo esc_html( '' !== (string) $task['assigned_to'] ? (string) $task['assigned_to'] : '—' ); ?></td>
                <td><span class="pill <?php echo esc_attr( ! empty( $task['is_overdue'] ) ? 'pill--red' : 'pill--outline' ); ?>"><?php echo esc_html( (string) $task['status_label'] ); ?></span></td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
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
          <h2><?php echo esc_html__( 'Clients', 'hello-elementor-child' ); ?></h2>
          <p><?php echo esc_html( sprintf( __( 'Showing %1$d–%2$d of %3$d leads', 'hello-elementor-child' ), $from, $to, $total ) ); ?></p>
        </div>
        <div class="crm-toolbar-actions">
          <a class="btn btn--solid btn--blue" href="<?php echo esc_url( $new_lead_url ); ?>"><?php echo esc_html__( 'Add new lead', 'hello-elementor-child' ); ?></a>
          <div class="crm-view-toggle" data-crm-view-toggle data-storage-key="peracrm_clients_view">
            <button type="button" class="btn btn--solid btn--blue" data-view="cards" aria-pressed="true"><?php echo esc_html__( 'Cards', 'hello-elementor-child' ); ?></button>
            <button type="button" class="btn btn--ghost btn--blue" data-view="table" aria-pressed="false"><?php echo esc_html__( 'Table', 'hello-elementor-child' ); ?></button>
          </div>
        </div>
      </div>

      <div class="crm-leads-table-wrap" data-crm-view="table">
        <table class="crm-leads-table" data-crm-sort-table>
          <thead>
            <tr>
              <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="name"><?php echo esc_html__( 'Name', 'hello-elementor-child' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
              <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="status"><?php echo esc_html__( 'Status', 'hello-elementor-child' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
              <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="source"><?php echo esc_html__( 'Source', 'hello-elementor-child' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
              <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="assigned"><?php echo esc_html__( 'Assigned to', 'hello-elementor-child' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
              <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="updated"><?php echo esc_html__( 'Last activity', 'hello-elementor-child' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
              <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="created"><?php echo esc_html__( 'Created', 'hello-elementor-child' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
            </tr>
          </thead>
          <tbody>
				<?php if ( empty( $items ) ) : ?>
            <tr>
              <td colspan="6"><?php echo esc_html__( 'No leads found for this scope.', 'hello-elementor-child' ); ?></td>
            </tr>
				<?php else : ?>
					<?php foreach ( $items as $lead ) : ?>
            <tr data-sort-row data-row-url="<?php echo esc_url( (string) $lead['crm_url'] ); ?>" data-name="<?php echo esc_attr( strtolower( (string) $lead['title'] ) ); ?>" data-status="<?php echo esc_attr( strtolower( (string) ( $stages[ $lead['stage'] ] ?? $lead['stage'] ) ) ); ?>" data-source="<?php echo esc_attr( strtolower( (string) ( $lead['source'] ?? '' ) ) ); ?>" data-assigned="<?php echo esc_attr( strtolower( (string) ( $lead['assigned_to'] ?? '' ) ) ); ?>" data-updated="<?php echo esc_attr( (string) ( $lead['updated_ts'] ?? 0 ) ); ?>" data-created="<?php echo esc_attr( (string) ( $lead['created_ts'] ?? 0 ) ); ?>">
              <td><a href="<?php echo esc_url( (string) $lead['crm_url'] ); ?>"><?php echo esc_html( (string) $lead['title'] ); ?></a></td>
              <td><span class="pill pill--outline"><?php echo esc_html( (string) ( $stages[ $lead['stage'] ] ?? $lead['stage'] ) ); ?></span></td>
              <td><?php echo esc_html( '' !== (string) ( $lead['source'] ?? '' ) ? (string) $lead['source'] : '—' ); ?></td>
              <td><?php echo esc_html( '' !== (string) ( $lead['assigned_to'] ?? '' ) ? (string) $lead['assigned_to'] : '—' ); ?></td>
              <td><?php echo esc_html( '' !== $lead['last_activity'] ? (string) $lead['last_activity'] : ( '' !== (string) ( $lead['updated'] ?? '' ) ? (string) $lead['updated'] : '—' ) ); ?></td>
              <td><?php echo esc_html( '' !== (string) ( $lead['created'] ?? '' ) ? (string) $lead['created'] : '—' ); ?></td>
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
          <p><a class="btn btn--ghost btn--blue" href="<?php echo esc_url( (string) $lead['crm_url'] ); ?>"><?php echo esc_html__( 'View Lead', 'hello-elementor-child' ); ?></a></p>
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
