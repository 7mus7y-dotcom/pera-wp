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
$clients_type_view = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( (string) $_GET['type'] ) ) : 'leads'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$clients_type_view = in_array( $clients_type_view, array( 'leads', 'clients', 'inactive' ), true ) ? $clients_type_view : 'leads';
$derived_type_filter = 'clients' === $clients_type_view ? 'client' : 'lead';

$crm_dashboard = function_exists( 'pera_crm_get_dashboard_data' )
	? pera_crm_get_dashboard_data()
	: array(
		'kpis'          => array(),
		'pipeline'      => array(),
		'activity'      => array(),
		'todays_tasks'  => array(),
		'overdue_tasks' => array(),
		'new_leads'     => array(),
		'notices'       => array( __( 'CRM data unavailable.', 'peracrm' ) ),
	);

$leads_data = $is_leads && function_exists( 'pera_crm_get_leads_view_data' )
	? pera_crm_get_leads_view_data( $current_page, 20, $derived_type_filter, $clients_type_view )
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

$crm_current_url    = home_url( wp_unslash( (string) ( $_SERVER['REQUEST_URI'] ?? '/crm/' ) ) );
$new_lead_url       = home_url( '/crm/new/' );
$overview_task_cap  = 8;
$push_notice_key    = isset( $_GET['peracrm_push_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['peracrm_push_notice'] ) ) : '';
$push_notice_text   = '';

if ( 'test_push_sent' === $push_notice_key ) {
	$push_notice_text = __( 'Test push notification sent.', 'peracrm' );
} elseif ( 'test_push_failed' === $push_notice_key ) {
	$push_notice_text = __( 'Unable to send test push. Make sure push is enabled on this device.', 'peracrm' );
}

$kpi_tiles = array(
	array( 'label' => __( 'Total open leads', 'peracrm' ), 'key' => 'total_open_leads' ),
	array( 'label' => __( 'New enquiries', 'peracrm' ), 'key' => 'new_enquiries' ),
	array( 'label' => __( 'Qualified', 'peracrm' ), 'key' => 'qualified' ),
	array( 'label' => __( 'Viewing arranged', 'peracrm' ), 'key' => 'viewing_arranged' ),
	array( 'label' => __( 'Offer made', 'peracrm' ), 'key' => 'offer_made' ),
	array( 'label' => __( 'Overdue reminders', 'peracrm' ), 'key' => 'overdue_reminders' ),
);

$stages = function_exists( 'pera_crm_get_pipeline_stages' ) ? pera_crm_get_pipeline_stages() : array();
$advisors = function_exists( 'pera_crm_get_pipeline_advisor_options' ) ? pera_crm_get_pipeline_advisor_options() : array();

$crm_active_view = ! $is_leads && ! $is_tasks ? 'overview' : ( $is_leads ? 'clients' : 'tasks' );

$build_overview_task_cards = static function ( array $tasks, int $cap ) {
	$has_more = count( $tasks ) > $cap;
	if ( $has_more ) {
		$tasks = array_slice( $tasks, 0, $cap - 1 );
	} else {
		$tasks = array_slice( $tasks, 0, $cap );
	}

	return array(
		'tasks'    => $tasks,
		'has_more' => $has_more,
	);
};

$render_overview_task_rows = static function ( array $tasks, string $empty_message, string $crm_current_url, string $variant = 'today' ) use ( $build_overview_task_cards, $overview_task_cap ) {
	$task_set = $build_overview_task_cards( $tasks, $overview_task_cap );
	$is_urgent = 'overdue' === $variant;
	$due_chip_class = $is_urgent ? 'crm-chip crm-chip--urgent' : 'crm-chip crm-chip--status';
	$row_class      = $is_urgent ? 'crm-row-list__item crm-row-list__item--urgent' : 'crm-row-list__item';

	if ( empty( $task_set['tasks'] ) ) {
		?>
		<p class="crm-overview-empty"><?php echo esc_html( $empty_message ); ?></p>
		<?php
		return;
	}
	?>
	<ul class="crm-row-list crm-overview-task-list">
		<?php foreach ( $task_set['tasks'] as $task ) : ?>
			<?php $task_status = sanitize_key( (string) ( $task['status'] ?? 'pending' ) ); ?>
			<li class="<?php echo esc_attr( $row_class ); ?>">
				<div class="crm-row-list__content">
					<div class="crm-row-list__header">
						<h3 class="crm-row-list__title"><a href="<?php echo esc_url( home_url( '/crm/client/' . (int) $task['lead_id'] . '/' ) ); ?>"><?php echo esc_html( (string) ( $task['lead_name'] ?: __( 'Untitled lead', 'peracrm' ) ) ); ?></a></h3>
						<span class="<?php echo esc_attr( $due_chip_class ); ?>"><?php echo esc_html( (string) ( $task['due_date'] ?? '' ) ); ?></span>
					</div>
					<p class="crm-row-list__summary"><?php echo esc_html( (string) ( $task['reminder_note'] ?? '' ) ); ?></p>
					<div class="crm-meta-line">
						<span><strong><?php esc_html_e( 'Latest note:', 'peracrm' ); ?></strong> <?php echo esc_html( (string) ( $task['last_note'] ?? __( 'No recent notes yet.', 'peracrm' ) ) ); ?></span>
					</div>
				</div>
				<div class="crm-row-list__aside">
					<a class="btn btn--ghost <?php echo esc_attr( $is_urgent ? 'btn--red' : 'btn--blue' ); ?>" href="<?php echo esc_url( home_url( '/crm/client/' . (int) $task['lead_id'] . '/' ) ); ?>"><?php echo esc_html__( 'Open client', 'peracrm' ); ?></a>
					<?php if ( ! empty( $task['reminder_id'] ) && 'pending' === $task_status ) : ?>
						<form class="crm-task-action" method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="peracrm_update_reminder_status">
							<input type="hidden" name="peracrm_reminder_id" value="<?php echo esc_attr( (string) absint( $task['reminder_id'] ) ); ?>">
							<input type="hidden" name="peracrm_status" value="done">
							<input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $crm_current_url ); ?>">
							<input type="hidden" name="peracrm_context" value="frontend">
							<?php wp_nonce_field( 'peracrm_update_reminder_status', 'peracrm_update_reminder_status_nonce' ); ?>
							<button type="submit" class="btn btn--ghost btn--blue crm-task-done-btn"><?php echo esc_html__( 'Mark done', 'peracrm' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
			</li>
		<?php endforeach; ?>
		<?php if ( $task_set['has_more'] ) : ?>
			<li class="crm-row-list__item crm-row-list__item--more">
				<div class="crm-row-list__content">
					<h3 class="crm-row-list__title"><?php echo esc_html__( 'More work waiting', 'peracrm' ); ?></h3>
					<p class="crm-row-list__summary"><?php echo esc_html__( 'Open the full task workspace to review the remaining reminders and assignments.', 'peracrm' ); ?></p>
				</div>
				<div class="crm-row-list__aside">
					<a class="btn btn--ghost btn--blue" href="<?php echo esc_url( home_url( '/crm/tasks/' ) ); ?>"><?php echo esc_html__( 'See all tasks', 'peracrm' ); ?></a>
				</div>
			</li>
		<?php endif; ?>
	</ul>
	<?php
};

peracrm_frontend_render_shell_header();
?>

<main id="primary" class="site-main crm-page crm-page--<?php echo esc_attr( $is_leads ? 'leads' : ( $is_tasks ? 'tasks' : 'overview' ) ); ?>">
  <?php
  $header_title = $is_leads ? ( $clients_type_view === 'clients' ? __( 'Clients', 'peracrm' ) : ( $clients_type_view === 'inactive' ? __( 'Inactive records', 'peracrm' ) : __( 'Leads', 'peracrm' ) ) ) : ( $is_tasks ? __( 'Tasks', 'peracrm' ) : __( 'CRM overview', 'peracrm' ) );
  $header_description = $is_leads ? __( 'Manage lead and client records without hero-framed filters.', 'peracrm' ) : ( $is_tasks ? __( 'Track open reminders and due work in a compact workspace shell.', 'peracrm' ) : __( 'Staff workspace for daily pipeline, workload, and account visibility.', 'peracrm' ) );
  $header_meta = $is_leads ? sprintf( __( '%d total records', 'peracrm' ), (int) ( $leads_data['total'] ?? 0 ) ) : ( $is_tasks ? sprintf( __( '%d open tasks', 'peracrm' ), count( is_array( $tasks_data['all'] ?? null ) ? $tasks_data['all'] : array() ) ) : __( 'Operational workspace', 'peracrm' ) );
  $header_actions = array();
  if ( $is_leads || ! $is_tasks ) {
	  $header_actions[] = array(
		  'label' => __( 'Create lead', 'peracrm' ),
		  'url'   => $new_lead_url,
		  'class' => 'btn btn--solid btn--blue',
		  'type'  => 'primary',
	  );
  }

  $header_args = array(
	  'title'       => $header_title,
	  'description' => $header_description,
	  'meta'        => $header_meta,
	  'actions'     => $header_actions,
	  'active_view' => $crm_active_view,
	  'show_client_filters' => $is_leads,
	  'stages'      => $stages,
	  'advisors'    => $advisors,
	  'clients_type_view' => $clients_type_view,
  );

  if ( function_exists( 'peracrm_frontend_render_partial' ) ) {
	  peracrm_frontend_render_partial( 'crm-header', $header_args );
  }
  ?>

	  <section class="content-panel content-panel--overlap-hero">
	    <div class="content-panel-box border-dm crm-layout">
      <div class="crm-layout__main">
			<?php if ( ! $is_leads && ! $is_tasks ) : ?>
      <section class="section crm-overview-band crm-overview-band--primary" aria-labelledby="crm-priority-work-heading">
        <article class="crm-section crm-section--flush crm-overview-priority">
          <header class="crm-section__header">
            <div class="crm-section__heading-group">
              <h2 id="crm-priority-work-heading" class="crm-section__title"><?php echo esc_html__( 'Priority work', 'peracrm' ); ?></h2>
              <p class="crm-section__description"><?php echo esc_html__( 'Start here: overdue follow-up first, then anything due today.', 'peracrm' ); ?></p>
            </div>
            <div class="crm-section__actions">
              <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( home_url( '/crm/tasks/' ) ); ?>"><?php echo esc_html__( 'Open task workspace', 'peracrm' ); ?></a>
            </div>
          </header>
          <div class="crm-section__body crm-overview-priority__body">
            <section class="crm-overview-priority-card crm-overview-priority-card--urgent" aria-labelledby="crm-overdue-tasks-heading">
              <header class="crm-overview-priority-card__header">
                <div>
                  <p class="crm-overview-eyebrow"><?php echo esc_html__( 'Urgent', 'peracrm' ); ?></p>
                  <h3 id="crm-overdue-tasks-heading" class="crm-section__title"><?php echo esc_html__( 'Overdue tasks', 'peracrm' ); ?></h3>
                </div>
                <span class="crm-chip crm-chip--urgent"><?php echo esc_html( sprintf( _n( '%d overdue', '%d overdue', count( $overdue_tasks ), 'peracrm' ), count( $overdue_tasks ) ) ); ?></span>
              </header>
              <?php $render_overview_task_rows( $overdue_tasks, __( 'No overdue tasks.', 'peracrm' ), $crm_current_url, 'overdue' ); ?>
            </section>
            <section class="crm-overview-priority-card" aria-labelledby="crm-today-tasks-heading">
              <header class="crm-overview-priority-card__header">
                <div>
                  <p class="crm-overview-eyebrow"><?php echo esc_html__( 'Due now', 'peracrm' ); ?></p>
                  <h3 id="crm-today-tasks-heading" class="crm-section__title"><?php echo esc_html__( "Today's tasks", 'peracrm' ); ?></h3>
                </div>
                <span class="crm-chip crm-chip--status"><?php echo esc_html( sprintf( _n( '%d due today', '%d due today', count( $todays_tasks ), 'peracrm' ), count( $todays_tasks ) ) ); ?></span>
              </header>
              <?php $render_overview_task_rows( $todays_tasks, __( 'No tasks due today.', 'peracrm' ), $crm_current_url ); ?>
            </section>
          </div>
        </article>
      </section>

      <section class="section crm-overview-band crm-overview-band--queue" aria-labelledby="crm-new-leads-heading">
        <article class="crm-section crm-section--flush crm-overview-queue">
          <header class="crm-section__header">
            <div class="crm-section__heading-group">
              <h2 id="crm-new-leads-heading" class="crm-section__title"><?php echo esc_html__( 'New leads queue', 'peracrm' ); ?></h2>
              <p class="crm-section__description"><?php echo esc_html__( 'Fresh enquiries that still need first-touch review or assignment.', 'peracrm' ); ?></p>
            </div>
            <div class="crm-section__actions">
              <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( home_url( '/crm/clients/?type=leads' ) ); ?>"><?php echo esc_html__( 'View all leads', 'peracrm' ); ?></a>
            </div>
          </header>
          <div class="crm-section__body">
          <?php if ( empty( $new_leads ) ) : ?>
            <p><?php echo esc_html__( 'No new leads found.', 'peracrm' ); ?></p>
          <?php else : ?>
            <ul class="crm-row-list">
            <?php foreach ( $new_leads as $lead ) : ?>
              <li class="crm-row-list__item">
                <div class="crm-row-list__content">
                  <div class="crm-row-list__header">
                    <h3 class="crm-row-list__title"><a href="<?php echo esc_url( (string) ( $lead['url'] ?? '' ) ); ?>"><?php echo esc_html( (string) ( $lead['name'] ?? '' ) ); ?></a></h3>
                    <?php if ( ! empty( $lead['source'] ) ) : ?>
                      <span class="crm-chip crm-chip--neutral"><?php echo esc_html( (string) $lead['source'] ); ?></span>
                    <?php endif; ?>
                  </div>
                  <div class="crm-row-list__meta">
                    <span><strong><?php esc_html_e( 'Phone:', 'peracrm' ); ?></strong> <?php echo esc_html( (string) ( $lead['phone'] ?? '—' ) ); ?></span>
                    <span><strong><?php esc_html_e( 'Enquiry:', 'peracrm' ); ?></strong> <?php echo esc_html( (string) ( $lead['enquiry_at'] ?? '—' ) ); ?></span>
                  </div>
                </div>
                <div class="crm-row-list__aside">
                  <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( (string) ( $lead['url'] ?? '' ) ); ?>"><?php echo esc_html__( 'View lead', 'peracrm' ); ?></a>
                </div>
              </li>
            <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          </div>
        </article>
      </section>

      <section class="section crm-overview-band" aria-labelledby="crm-task-focus-heading">
        <article class="crm-section crm-section--flush">
          <header class="crm-section__header">
            <div class="crm-section__heading-group">
              <h2 id="crm-task-focus-heading" class="crm-section__title"><?php echo esc_html__( 'Task focus', 'peracrm' ); ?></h2>
              <p class="crm-section__description"><?php echo esc_html__( 'Use the task workspace for the full queue, sorting, and table view.', 'peracrm' ); ?></p>
            </div>
            <div class="crm-section__actions">
              <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( home_url( '/crm/tasks/' ) ); ?>"><?php echo esc_html__( 'Go to tasks', 'peracrm' ); ?></a>
            </div>
          </header>
          <div class="crm-section__body crm-overview-task-summary">
            <div class="crm-overview-task-summary__item">
              <span class="crm-chip crm-chip--urgent"><?php echo esc_html( sprintf( _n( '%d overdue', '%d overdue', count( $overdue_tasks ), 'peracrm' ), count( $overdue_tasks ) ) ); ?></span>
              <p><?php echo esc_html__( 'Overdue follow-up remains the first priority every time the dashboard loads.', 'peracrm' ); ?></p>
            </div>
            <div class="crm-overview-task-summary__item">
              <span class="crm-chip crm-chip--status"><?php echo esc_html( sprintf( _n( '%d due today', '%d due today', count( $todays_tasks ), 'peracrm' ), count( $todays_tasks ) ) ); ?></span>
              <p><?php echo esc_html__( 'Today’s reminders stay close to the urgent queue so the next click is obvious.', 'peracrm' ); ?></p>
            </div>
          </div>
        </article>
      </section>

      <section class="section crm-overview-band crm-overview-band--secondary" aria-labelledby="crm-activity-heading">
        <article class="crm-section crm-section--flush crm-overview-activity">
          <header class="crm-section__header">
            <div class="crm-section__heading-group">
              <h2 id="crm-activity-heading" class="crm-section__title"><?php echo esc_html__( 'Latest Activity', 'peracrm' ); ?></h2>
              <p class="crm-section__description"><?php echo esc_html__( 'Recent history and context, kept quieter than the live queues above.', 'peracrm' ); ?></p>
            </div>
          </header>
          <div class="crm-section__body">
          <?php if ( empty( $activity ) ) : ?>
            <p><?php echo esc_html__( 'No activity available.', 'peracrm' ); ?></p>
          <?php else : ?>
          <ul class="crm-activity-list">
            <?php foreach ( $activity as $item ) : ?>
              <li class="crm-activity-list__item">
                <div class="crm-activity-list__meta">
                  <?php if ( ! empty( $item['type'] ) ) : ?><span class="crm-chip crm-chip--neutral"><?php echo esc_html( (string) $item['type'] ); ?></span><?php endif; ?>
                  <strong><?php echo esc_html( (string) ( $item['time'] ?? '' ) ); ?></strong>
                </div>
                <p class="crm-activity-list__summary"><?php echo esc_html( (string) ( $item['summary'] ?? '' ) ); ?></p>
              </li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
          </div>
        </article>
      </section>

      <section class="section crm-overview-band crm-overview-band--tertiary" aria-labelledby="crm-kpi-heading">
        <article class="crm-section crm-section--flush crm-overview-metrics">
          <header class="crm-section__header">
            <div class="crm-section__heading-group">
              <h2 id="crm-kpi-heading" class="crm-section__title"><?php echo esc_html__( 'KPI Snapshot', 'peracrm' ); ?></h2>
              <p class="crm-section__description"><?php echo esc_html__( 'Broad counts stay available below the action queues for quick health checks.', 'peracrm' ); ?></p>
            </div>
          </header>
		  <div class="crm-section__body">
		    <div class="grid-3 crm-kpi-grid cards-slider cards-slider--snap cards-slider--grid-lg" aria-label="<?php echo esc_attr__( 'CRM KPI Snapshot', 'peracrm' ); ?>">
					<?php foreach ( $kpi_tiles as $tile ) : ?>
            <article class="card-shell slider-card crm-kpi-card">
              <p class="crm-chip crm-chip--neutral"><?php echo esc_html( $tile['label'] ); ?></p>
              <h3><?php echo esc_html( (string) ( (int) ( $kpis[ $tile['key'] ] ?? 0 ) ) ); ?></h3>
            </article>
					<?php endforeach; ?>
          </div>
		  </div>
        </article>
      </section>

	      <section class="section crm-overview-band crm-overview-band--tertiary" aria-labelledby="crm-pipeline-heading">
	        <article class="crm-section crm-section--flush crm-overview-metrics">
	          <header class="crm-section__header">
	            <div class="crm-section__heading-group">
	              <h2 id="crm-pipeline-heading" class="crm-section__title"><?php echo esc_html__( 'Pipeline Overview', 'peracrm' ); ?></h2>
	              <p class="crm-section__description"><?php echo esc_html__( 'Stage totals remain visible, but do not outrank current work queues.', 'peracrm' ); ?></p>
	            </div>
	          </header>
          <div class="crm-section__body">
            <div class="grid-3 crm-kpi-grid cards-slider cards-slider--snap cards-slider--grid-lg" aria-label="<?php echo esc_attr__( 'CRM Pipeline Overview', 'peracrm' ); ?>">
					<?php foreach ( $pipeline as $stage ) : ?>
              <article class="card-shell slider-card crm-kpi-card">
                <p class="crm-chip crm-chip--neutral"><?php echo esc_html( (string) ( $stage['label'] ?? '' ) ); ?></p>
                <h3><?php echo esc_html( (string) ( (int) ( $stage['count'] ?? 0 ) ) ); ?></h3>
              </article>
					<?php endforeach; ?>
	          </div>
          </div>
	        </article>
	      </section>

      <?php if ( ! empty( $notices ) ) : ?>
      <section class="section crm-overview-band crm-overview-band--secondary" aria-label="<?php echo esc_attr__( 'CRM notices', 'peracrm' ); ?>">
        <article class="crm-section crm-section--flush">
          <header class="crm-section__header">
            <div class="crm-section__heading-group">
              <h2 class="crm-section__title"><?php echo esc_html__( 'Workspace notices', 'peracrm' ); ?></h2>
            </div>
          </header>
          <div class="crm-section__body">
				<?php foreach ( $notices as $notice ) : ?>
            <article class="crm-overview-note">
              <span class="crm-chip crm-chip--neutral"><?php echo esc_html__( 'Notice', 'peracrm' ); ?></span>
              <p><?php echo esc_html( (string) $notice ); ?></p>
            </article>
				<?php endforeach; ?>
          </div>
        </article>
      </section>
      <?php endif; ?>

			<?php if ( is_user_logged_in() ) : ?>
	      <section class="section" aria-label="<?php echo esc_attr__( 'Push notifications', 'peracrm' ); ?>">
	        <article class="card-shell" data-crm-push-card>
	          <p class="pill pill--outline"><?php echo esc_html__( 'Notifications', 'peracrm' ); ?></p>
	          <h2><?php echo esc_html__( 'Reminder Push Notifications', 'peracrm' ); ?></h2>
	          <?php if ( '' !== $push_notice_text ) : ?>
	            <p class="pill pill--outline"><?php echo esc_html( $push_notice_text ); ?></p>
	          <?php endif; ?>
	          <p data-crm-push-status><?php echo esc_html__( 'Checking push notification status…', 'peracrm' ); ?></p>
	          <p class="text-sm" data-crm-push-sw-status><?php echo esc_html__( 'Service worker status: checking…', 'peracrm' ); ?></p>
	          <p class="text-sm" data-crm-push-cron-health><?php echo esc_html__( 'Digest cron health: checking…', 'peracrm' ); ?></p>
	          <p class="text-sm" data-crm-push-diagnostics hidden></p>
	          <p class="text-sm" data-crm-push-digest-result hidden></p>
	          <div class="crm-task-action" style="display:flex;gap:8px;flex-wrap:wrap;">
	            <button type="button" class="btn btn--ghost btn--blue" data-crm-push-enable><?php echo esc_html__( 'Enable Push Notifications', 'peracrm' ); ?></button>
	            <button type="button" class="btn btn--ghost" data-crm-push-disable disabled><?php echo esc_html__( 'Disable on this device', 'peracrm' ); ?></button>
	            <button type="button" class="btn btn--ghost" data-crm-push-run-digest hidden><?php echo esc_html__( 'Run digest now', 'peracrm' ); ?></button>
	            <button type="button" class="btn btn--ghost" data-crm-push-refresh-diagnostics hidden><?php echo esc_html__( 'Refresh diagnostics', 'peracrm' ); ?></button>
	            <?php if ( function_exists( 'peracrm_push_is_configured' ) && peracrm_push_is_configured() ) : ?>
	            <form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>">
	              <input type="hidden" name="action" value="peracrm_send_test_push">
	              <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( home_url( '/crm/' ) ); ?>">
	              <?php wp_nonce_field( 'peracrm_send_test_push', 'peracrm_send_test_push_nonce' ); ?>
	              <button type="submit" class="btn btn--ghost"><?php echo esc_html__( 'Send test notification', 'peracrm' ); ?></button>
	            </form>
	            <?php endif; ?>
	          </div>
	        </article>
	      </section>
	      <?php endif; ?>


			<?php elseif ( $is_tasks ) : ?>
			<?php
			$task_rows      = is_array( $tasks_data['all'] ?? null ) ? $tasks_data['all'] : array();
			$today_rows     = is_array( $tasks_data['today'] ?? null ) ? $tasks_data['today'] : array();
			$outstanding    = is_array( $tasks_data['outstanding'] ?? null ) ? $tasks_data['outstanding'] : array();
			$upcoming       = is_array( $tasks_data['upcoming'] ?? null ) ? $tasks_data['upcoming'] : array();
			$show_assigned  = ! empty( $task_rows ) && empty( $tasks_data['is_employee'] );
			$tasks_page_url = home_url( wp_unslash( (string) ( $_SERVER['REQUEST_URI'] ?? '/crm/tasks/' ) ) );
			?>
      <div class="crm-toolbar crm-toolbar--content"><div class="crm-toolbar__row crm-leads-toolbar">
        <div>
          <h2><?php echo esc_html__( 'Tasks', 'peracrm' ); ?></h2>
          <p><?php echo esc_html( sprintf( __( '%d open tasks (reminders)', 'peracrm' ), count( $task_rows ) ) ); ?></p>
        </div>
        <div class="crm-toolbar-actions">
          <div class="crm-view-toggle" data-crm-view-toggle data-storage-key="peracrm_tasks_view">
            <button type="button" class="btn btn--solid btn--blue" data-view="cards" aria-pressed="true"><?php echo esc_html__( 'Cards', 'peracrm' ); ?></button>
            <button type="button" class="btn btn--ghost btn--blue" data-view="table" aria-pressed="false"><?php echo esc_html__( 'Table', 'peracrm' ); ?></button>
          </div>
        </div>
      </div></div>

      <div class="crm-lead-cards" data-crm-view="cards">
        <section class="section" aria-labelledby="crm-tasks-today-heading">
          <article class="card-shell">
            <header class="section-header"><h3 id="crm-tasks-today-heading"><?php echo esc_html__( "Today's Tasks", 'peracrm' ); ?></h3></header>
            <?php if ( empty( $today_rows ) ) : ?>
              <p><?php echo esc_html__( 'No tasks due today.', 'peracrm' ); ?></p>
            <?php else : ?>
              <ul class="crm-list">
                <?php foreach ( $today_rows as $task ) : ?>
                  <li>
                    <a class="btn btn--ghost btn--blue crm-task-client-btn" href="<?php echo esc_url( (string) $task['client_url'] ); ?>"><?php echo esc_html( (string) $task['client_name'] ); ?></a>
                    <span class="pill pill--outline"><?php echo esc_html( (string) $task['due_display'] ); ?></span>
                    <p class="crm-task-note"><strong><?php esc_html_e( 'Task:', 'peracrm' ); ?></strong> <?php echo esc_html( (string) $task['note'] ); ?></p>
                    <?php if ( $show_assigned ) : ?><span><strong><?php echo esc_html__( 'Assigned:', 'peracrm' ); ?></strong> <?php echo esc_html( '' !== (string) $task['assigned_to'] ? (string) $task['assigned_to'] : '—' ); ?></span><?php endif; ?>
                    <p class="text-sm crm-task-last-note"><strong><?php esc_html_e( 'Task note:', 'peracrm' ); ?></strong> <?php echo esc_html( (string) ( $task['note'] ?? __( 'No task note yet.', 'peracrm' ) ) ); ?></p>
                    <span class="pill pill--outline"><?php echo esc_html( (string) $task['status_label'] ); ?></span>
                    <?php if ( ! empty( $task['reminder_id'] ) ) : ?>
                    <form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>">
                      <input type="hidden" name="action" value="peracrm_update_reminder_status">
                      <input type="hidden" name="peracrm_reminder_id" value="<?php echo esc_attr( (string) absint( $task['reminder_id'] ) ); ?>">
                      <input type="hidden" name="peracrm_status" value="done">
                      <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $tasks_page_url ); ?>">
                      <input type="hidden" name="peracrm_context" value="frontend">
                      <?php wp_nonce_field( 'peracrm_update_reminder_status', 'peracrm_update_reminder_status_nonce' ); ?>
                      <button type="submit" class="btn btn--ghost btn--blue crm-task-done-btn"><?php echo esc_html__( 'Mark done', 'peracrm' ); ?></button>
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
            <header class="section-header"><h3 id="crm-tasks-outstanding-heading"><?php echo esc_html__( 'Outstanding Tasks', 'peracrm' ); ?></h3></header>
            <?php if ( empty( $outstanding ) ) : ?>
              <p><?php echo esc_html__( 'No outstanding tasks.', 'peracrm' ); ?></p>
            <?php else : ?>
              <ul class="crm-list">
                <?php foreach ( $outstanding as $task ) : ?>
                  <li>
                    <a class="btn btn--ghost btn--red crm-task-client-btn" href="<?php echo esc_url( (string) $task['client_url'] ); ?>"><?php echo esc_html( (string) $task['client_name'] ); ?></a>
                    <span class="pill pill--red"><?php echo esc_html( (string) $task['due_display'] ); ?></span>
                    <p class="crm-task-note"><strong><?php esc_html_e( 'Task:', 'peracrm' ); ?></strong> <?php echo esc_html( (string) $task['note'] ); ?></p>
                    <?php if ( $show_assigned ) : ?><span><strong><?php echo esc_html__( 'Assigned:', 'peracrm' ); ?></strong> <?php echo esc_html( '' !== (string) $task['assigned_to'] ? (string) $task['assigned_to'] : '—' ); ?></span><?php endif; ?>
                    <p class="text-sm crm-task-last-note"><strong><?php esc_html_e( 'Task note:', 'peracrm' ); ?></strong> <?php echo esc_html( (string) ( $task['note'] ?? __( 'No task note yet.', 'peracrm' ) ) ); ?></p>
                    <span class="pill pill--red"><?php echo esc_html( (string) $task['status_label'] ); ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </article>
        </section>

        <section class="section" aria-labelledby="crm-tasks-upcoming-heading">
          <article class="card-shell">
            <header class="section-header"><h3 id="crm-tasks-upcoming-heading"><?php echo esc_html__( 'Upcoming Tasks', 'peracrm' ); ?></h3></header>
            <?php if ( empty( $upcoming ) ) : ?>
              <p><?php echo esc_html__( 'No upcoming tasks.', 'peracrm' ); ?></p>
            <?php else : ?>
              <ul class="crm-list">
                <?php foreach ( $upcoming as $task ) : ?>
                  <li>
                    <a class="btn btn--ghost btn--blue crm-task-client-btn" href="<?php echo esc_url( (string) $task['client_url'] ); ?>"><?php echo esc_html( (string) $task['client_name'] ); ?></a>
                    <span class="pill pill--outline"><?php echo esc_html( (string) $task['due_display'] ); ?></span>
                    <p class="crm-task-note"><strong><?php esc_html_e( 'Task:', 'peracrm' ); ?></strong> <?php echo esc_html( (string) $task['note'] ); ?></p>
                    <?php if ( $show_assigned ) : ?><span><strong><?php echo esc_html__( 'Assigned:', 'peracrm' ); ?></strong> <?php echo esc_html( '' !== (string) $task['assigned_to'] ? (string) $task['assigned_to'] : '—' ); ?></span><?php endif; ?>
                    <p class="text-sm crm-task-last-note"><strong><?php esc_html_e( 'Task note:', 'peracrm' ); ?></strong> <?php echo esc_html( (string) ( $task['note'] ?? __( 'No task note yet.', 'peracrm' ) ) ); ?></p>
                    <span class="pill pill--outline"><?php echo esc_html( (string) $task['status_label'] ); ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </article>
        </section>
      </div>

      <div class="crm-leads-table-wrap crm-table-wrap crm-table-wrap--primitive is-hidden" data-crm-view="table">
        <table class="crm-leads-table crm-table" data-crm-sort-table>
          <thead>
            <tr>
              <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="due"><?php echo esc_html__( 'Due', 'peracrm' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
              <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="client"><?php echo esc_html__( 'Client', 'peracrm' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
              <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="note"><?php echo esc_html__( 'Note', 'peracrm' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
              <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="assigned"><?php echo esc_html__( 'Assigned', 'peracrm' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
              <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="status"><?php echo esc_html__( 'Status', 'peracrm' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
            </tr>
          </thead>
          <tbody>
            <?php if ( empty( $task_rows ) ) : ?>
            <tr class="crm-table__empty"><td colspan="5"><?php echo esc_html__( 'No open tasks found.', 'peracrm' ); ?></td></tr>
            <?php else : ?>
              <?php foreach ( $task_rows as $task ) : ?>
              <tr data-sort-row data-row-url="<?php echo esc_url( (string) $task['client_url'] ); ?>" data-due="<?php echo esc_attr( (string) ( $task['due_ts'] ?? 0 ) ); ?>" data-client="<?php echo esc_attr( strtolower( (string) $task['client_name'] ) ); ?>" data-note="<?php echo esc_attr( strtolower( (string) $task['note'] ) ); ?>" data-assigned="<?php echo esc_attr( strtolower( (string) $task['assigned_to'] ) ); ?>" data-status="<?php echo esc_attr( strtolower( (string) $task['status_label'] ) ); ?>">
                <td><?php echo esc_html( (string) $task['due_display'] ); ?></td>
                <td class="crm-table__cell--primary"><div class="crm-table__primary"><a href="<?php echo esc_url( (string) $task['client_url'] ); ?>"><?php echo esc_html( (string) $task['client_name'] ); ?></a></div></td>
                <td><?php echo esc_html( '' !== (string) $task['note'] ? (string) $task['note'] : '—' ); ?></td>
                <td><?php echo esc_html( '' !== (string) $task['assigned_to'] ? (string) $task['assigned_to'] : '—' ); ?></td>
                <td><span class="crm-chip <?php echo esc_attr( ! empty( $task['is_overdue'] ) ? 'crm-chip--urgent' : 'crm-chip--status' ); ?>"><?php echo esc_html( (string) $task['status_label'] ); ?></span></td>
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
			$is_inactive  = 'inactive' === $clients_type_view;
			$status_labels = array(
				'closed'  => __( 'Closed', 'peracrm' ),
				'dormant' => __( 'Dormant', 'peracrm' ),
			);
			?>
      <div class="crm-toolbar crm-toolbar--content"><div class="crm-toolbar__row crm-leads-toolbar">
        <div>
          <h2><?php echo esc_html( $is_inactive ? __( 'Inactive', 'peracrm' ) : ( 'clients' === $clients_type_view ? __( 'Clients', 'peracrm' ) : __( 'Leads', 'peracrm' ) ) ); ?></h2>
          <p><?php echo esc_html__( 'Leads are those who have not invested with us. Clients are those who have invested with us or have property that they wish to sell or rent.', 'peracrm' ); ?></p>
          <p><?php echo esc_html( sprintf( __( 'Showing %1$d–%2$d of %3$d', 'peracrm' ), $from, $to, $total ) ); ?></p>
        </div>
        <div class="crm-toolbar-actions">
          <div class="crm-type-toggle" role="group" aria-label="<?php echo esc_attr__( 'Lead or client listing', 'peracrm' ); ?>">
            <a class="btn <?php echo esc_attr( 'leads' === $clients_type_view ? 'btn--solid' : 'btn--ghost' ); ?> btn--blue" href="<?php echo esc_url( add_query_arg( 'type', 'leads', home_url( '/crm/clients/' ) ) ); ?>"><?php esc_html_e( 'Leads', 'peracrm' ); ?></a>
            <a class="btn <?php echo esc_attr( 'clients' === $clients_type_view ? 'btn--solid' : 'btn--ghost' ); ?> btn--blue" href="<?php echo esc_url( add_query_arg( 'type', 'clients', home_url( '/crm/clients/' ) ) ); ?>"><?php esc_html_e( 'Clients', 'peracrm' ); ?></a>
            <a class="btn <?php echo esc_attr( $is_inactive ? 'btn--solid' : 'btn--ghost' ); ?> btn--blue" href="<?php echo esc_url( add_query_arg( 'type', 'inactive', home_url( '/crm/clients/' ) ) ); ?>"><?php esc_html_e( 'Inactive', 'peracrm' ); ?></a>
          </div>
          <div class="crm-view-toggle" data-crm-view-toggle data-storage-key="peracrm_clients_view">
            <button type="button" class="btn btn--solid btn--blue" data-view="cards" aria-pressed="true"><?php echo esc_html__( 'Cards', 'peracrm' ); ?></button>
            <button type="button" class="btn btn--ghost btn--blue" data-view="table" aria-pressed="false"><?php echo esc_html__( 'Table', 'peracrm' ); ?></button>
          </div>
        </div>
      </div></div>

      <div class="crm-leads-table-wrap crm-table-wrap crm-table-wrap--primitive is-hidden" data-crm-view="table">
        <table class="crm-leads-table crm-table" data-crm-sort-table>
          <thead>
            <tr>
              <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="name"><?php echo esc_html__( 'Name', 'peracrm' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
              <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="status"><?php echo esc_html__( 'Status', 'peracrm' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
              <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="source"><?php echo esc_html__( 'Source', 'peracrm' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
              <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="assigned"><?php echo esc_html__( 'Assigned to', 'peracrm' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
              <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="updated"><?php echo esc_html__( 'Last activity', 'peracrm' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
              <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="created"><?php echo esc_html__( 'Created', 'peracrm' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
            </tr>
          </thead>
          <tbody>
				<?php if ( empty( $items ) ) : ?>
            <tr>
              <td class="crm-table__empty" colspan="6"><?php echo esc_html__( 'No leads found for this scope.', 'peracrm' ); ?></td>
            </tr>
				<?php else : ?>
				<?php foreach ( $items as $lead ) : ?>
					<?php
					$engagement_key = sanitize_key( (string) ( $lead['engagement_state'] ?? '' ) );
					$status_label   = isset( $status_labels[ $engagement_key ] ) ? $status_labels[ $engagement_key ] : (string) ( $stages[ $lead['stage'] ] ?? $lead['stage'] );
					?>
            <tr data-sort-row data-row-url="<?php echo esc_url( (string) $lead['crm_url'] ); ?>" data-name="<?php echo esc_attr( strtolower( (string) $lead['title'] ) ); ?>" data-status="<?php echo esc_attr( strtolower( (string) $status_label ) ); ?>" data-source="<?php echo esc_attr( strtolower( (string) ( $lead['source'] ?? '' ) ) ); ?>" data-assigned="<?php echo esc_attr( strtolower( (string) ( $lead['assigned_to'] ?? '' ) ) ); ?>" data-updated="<?php echo esc_attr( (string) ( $lead['updated_ts'] ?? 0 ) ); ?>" data-created="<?php echo esc_attr( (string) ( $lead['created_ts'] ?? 0 ) ); ?>">
              <td class="crm-table__cell--primary"><div class="crm-table__primary"><a href="<?php echo esc_url( (string) $lead['crm_url'] ); ?>"><?php echo esc_html( (string) $lead['title'] ); ?></a><span class="crm-table__subtext"><?php echo esc_html( (string) $lead['engagement_state'] ); ?></span></div></td>
              <td><span class="crm-chip crm-chip--status"><?php echo esc_html( (string) $status_label ); ?></span></td>
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
				<?php
				$engagement_key = sanitize_key( (string) ( $lead['engagement_state'] ?? '' ) );
				$status_label   = isset( $status_labels[ $engagement_key ] ) ? $status_labels[ $engagement_key ] : (string) ( $stages[ $lead['stage'] ] ?? $lead['stage'] );
				$view_label     = 'clients' === $clients_type_view || $is_inactive ? __( 'View Client', 'peracrm' ) : __( 'View Lead', 'peracrm' );
				?>
	        <article class="card-shell">
          <h3><?php echo esc_html( (string) $lead['title'] ); ?></h3>
          <p><span class="pill pill--outline"><?php echo esc_html( (string) $status_label ); ?></span></p>
          <p><strong><?php echo esc_html__( 'Engagement:', 'peracrm' ); ?></strong> <?php echo esc_html( (string) $lead['engagement_state'] ); ?></p>
          <p><strong><?php echo esc_html__( 'Disposition:', 'peracrm' ); ?></strong> <?php echo esc_html( (string) $lead['disposition'] ); ?></p>
          <p><strong><?php echo esc_html__( 'Last activity:', 'peracrm' ); ?></strong> <?php echo esc_html( '' !== $lead['last_activity'] ? (string) $lead['last_activity'] : '—' ); ?></p>
	          <p><a class="btn btn--ghost btn--blue" href="<?php echo esc_url( (string) $lead['crm_url'] ); ?>"><?php echo esc_html( $view_label ); ?></a></p>
	        </article>
				<?php endforeach; ?>
	      </div>

			<?php
			$request_uri      = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '/crm/clients/';
			$request_path     = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
			$pagination_root  = 0 === strpos( trailingslashit( $request_path ), '/crm/leads/' ) ? '/crm/leads/' : '/crm/clients/';

			$pagination = paginate_links(
				array(
					'base'      => trailingslashit( home_url( $pagination_root . '%_%' ) ),
					'format'    => 'page/%#%/',
					'current'   => $current_page,
					'total'     => $total_pages,
					'type'      => 'list',
					'prev_text' => __( 'Previous', 'peracrm' ),
					'next_text' => __( 'Next', 'peracrm' ),
					'add_args'  => array( 'type' => $clients_type_view ),
				)
			);
			if ( is_string( $pagination ) ) {
				$pagination = str_replace( trailingslashit( $pagination_root ) . 'page/1/', trailingslashit( $pagination_root ), $pagination );
				echo wp_kses_post( $pagination );
			}
			?>
		<?php endif; ?>
      </div>
      <?php if ( function_exists( 'peracrm_frontend_render_partial' ) ) { peracrm_frontend_render_partial( 'crm-side-nav', array( 'active_view' => $crm_active_view ) ); } ?>
    </div>
  </section>
</main>

<?php
peracrm_frontend_render_shell_footer();
