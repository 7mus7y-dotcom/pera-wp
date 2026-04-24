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
$clients_type_view = in_array( $clients_type_view, array( 'leads', 'clients', 'inactive', 'agent' ), true ) ? $clients_type_view : 'leads';
$derived_type_filter = 'clients' === $clients_type_view
	? 'client'
	: ( 'agent' === $clients_type_view ? 'agent' : 'lead' );
$requested_clients_view = isset( $_GET['clients_view'] ) ? sanitize_key( wp_unslash( (string) $_GET['clients_view'] ) ) : '';
$requested_clients_view = in_array( $requested_clients_view, array( 'table', 'cards' ), true ) ? $requested_clients_view : 'table';
$clients_is_cards_view  = 'cards' === $requested_clients_view;
$requested_tasks_view   = isset( $_GET['tasks_view'] ) ? sanitize_key( wp_unslash( (string) $_GET['tasks_view'] ) ) : '';
$requested_tasks_view   = in_array( $requested_tasks_view, array( 'table', 'cards' ), true ) ? $requested_tasks_view : 'table';
$tasks_is_cards_view    = 'cards' === $requested_tasks_view;

$crm_dashboard = function_exists( 'pera_crm_get_dashboard_data' )
	? pera_crm_get_dashboard_data()
	: array(
			'kpis'          => array(),
			'pipeline'      => array(),
			'pipeline_health' => array(),
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

$pipeline_health = is_array( $crm_dashboard['pipeline_health'] ?? null ) ? $crm_dashboard['pipeline_health'] : array();
$pipeline_health_links = array(
	'overdue_reminders' => home_url( '/crm/tasks/?filter=overdue' ),
	'due_today'         => home_url( '/crm/tasks/?filter=today' ),
	'new_leads_72h'     => home_url( '/crm/clients/?type=leads&filter=new72' ),
	'unassigne_open'    => home_url( '/crm/clients/?type=leads&filter=unassigned' ),
	'unassigned_open'   => home_url( '/crm/clients/?type=leads&filter=unassigned' ),
	'stale_open'        => home_url( '/crm/clients/?type=leads&filter=stale' ),
	'open_pipeline'     => home_url( '/crm/clients/?type=leads&filter=open_scope' ),
);
$activity      = is_array( $crm_dashboard['activity'] ?? null ) ? $crm_dashboard['activity'] : array();
$todays_tasks  = is_array( $crm_dashboard['todays_tasks'] ?? null ) ? $crm_dashboard['todays_tasks'] : array();
$overdue_tasks = is_array( $crm_dashboard['overdue_tasks'] ?? null ) ? $crm_dashboard['overdue_tasks'] : array();
$new_leads     = is_array( $crm_dashboard['new_leads'] ?? null ) ? $crm_dashboard['new_leads'] : array();
$notices       = is_array( $crm_dashboard['notices'] ?? null ) ? $crm_dashboard['notices'] : array();

$crm_current_url    = home_url( wp_unslash( (string) ( $_SERVER['REQUEST_URI'] ?? '/crm/' ) ) );
$new_lead_url       = home_url( '/crm/new/' );
$new_leads_count    = ( ! $is_leads && ! $is_tasks && function_exists( 'pera_crm_count_new_leads_for_user' ) ) ? (int) pera_crm_count_new_leads_for_user( 0, 72 ) : 0;
$new_leads_url      = home_url( '/crm/clients/?type=leads&filter=new72' );
$strict_new_leads   = array();
$overview_task_cap  = 8;
$push_notice_key    = isset( $_GET['peracrm_push_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['peracrm_push_notice'] ) ) : '';
$push_notice_text   = '';

if ( 'test_push_sent' === $push_notice_key ) {
	$push_notice_text = __( 'Test push notification sent.', 'peracrm' );
} elseif ( 'test_push_failed' === $push_notice_key ) {
	$push_notice_text = __( 'Unable to send test push. Make sure push is enabled on this device.', 'peracrm' );
}

$stages = function_exists( 'pera_crm_get_pipeline_stages' ) ? pera_crm_get_pipeline_stages() : array();
$advisors = function_exists( 'pera_crm_get_pipeline_advisor_options' ) ? pera_crm_get_pipeline_advisor_options() : array();
$clients_new_leads_items = array();

if ( ! $is_leads && ! $is_tasks && function_exists( 'pera_crm_get_new_lead_ids_for_user' ) ) {
	$strict_new_lead_ids = array_map( 'intval', pera_crm_get_new_lead_ids_for_user( 0, 72 ) );
	$strict_new_lead_ids = array_values( array_filter( $strict_new_lead_ids ) );
	$strict_new_lead_ids = array_slice( $strict_new_lead_ids, 0, 10 );
	$strict_new_leads    = function_exists( 'pera_crm_hydrate_lead_rows_for_panel' )
		? pera_crm_hydrate_lead_rows_for_panel( $strict_new_lead_ids, 10 )
		: array();
}

if ( $is_leads && 'leads' === $clients_type_view && function_exists( 'pera_crm_get_new_lead_ids_for_user' ) ) {
	$new_lead_ids = array_map( 'intval', pera_crm_get_new_lead_ids_for_user( 0, 72 ) );
	$new_lead_ids = array_values( array_filter( $new_lead_ids ) );
	$new_lead_ids = array_slice( $new_lead_ids, 0, 8 );
	$clients_new_leads_items = function_exists( 'pera_crm_hydrate_lead_rows_for_panel' )
		? pera_crm_hydrate_lead_rows_for_panel( $new_lead_ids, 8 )
		: array();
}

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
			<li class="<?php echo esc_attr( $row_class ); ?>" data-crm-reminder-row data-reminder-id="<?php echo esc_attr( (string) absint( $task['reminder_id'] ?? 0 ) ); ?>">
				<div class="crm-row-list__content">
					<div class="crm-row-list__header">
						<h3 class="crm-row-list__title"><a href="<?php echo esc_url( home_url( '/crm/client/' . (int) $task['lead_id'] . '/' ) ); ?>"><?php echo esc_html( (string) ( $task['lead_name'] ?: __( 'Untitled lead', 'peracrm' ) ) ); ?></a></h3>
						<span class="<?php echo esc_attr( $due_chip_class ); ?>"><?php echo esc_html( (string) ( $task['due_date'] ?? '' ) ); ?></span>
					</div>
					<p class="crm-row-list__summary">
						<?php echo esc_html( (string) ( $task['reminder_note'] ?? '' ) ); ?>
						<?php if ( ! empty( $task['has_note'] ) && ! empty( $task['note_preview'] ) ) : ?>
							<span class="crm-task-note-wrap" data-crm-note-wrap>
								<button
									type="button"
									class="pill crm-task-note-trigger"
									data-crm-note-trigger
									aria-expanded="false"
									aria-label="<?php echo esc_attr__( 'Show latest note', 'peracrm' ); ?>"
								>
									<span class="crm-task-note-trigger__icon" aria-hidden="true">📝</span>
									<span class="crm-task-note-trigger__label"><?php echo esc_html__( 'Has note', 'peracrm' ); ?></span>
								</button>
								<span class="crm-task-note-popover" data-crm-note-popover role="tooltip" hidden>
									<?php echo esc_html( (string) $task['note_preview'] ); ?>
								</span>
							</span>
						<?php endif; ?>
					</p>
				</div>
				<div class="crm-row-list__aside">
					<a class="btn btn--ghost <?php echo esc_attr( $is_urgent ? 'btn--red' : 'btn--blue' ); ?>" href="<?php echo esc_url( home_url( '/crm/client/' . (int) $task['lead_id'] . '/' ) ); ?>"><?php echo esc_html__( 'Open client', 'peracrm' ); ?></a>
					<?php if ( ! empty( $task['reminder_id'] ) && 'pending' === $task_status ) : ?>
						<form class="crm-task-action" method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>" data-crm-reminder-action-form="1">
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
					<a class="btn btn--ghost btn--blue" href="<?php echo esc_url( home_url( '/crm/tasks/' ) ); ?>"><?php echo esc_html__( 'Open task workspace', 'peracrm' ); ?></a>
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
  $header_title = $is_leads ? ( $clients_type_view === 'clients' ? __( 'Clients', 'peracrm' ) : ( $clients_type_view === 'inactive' ? __( 'Inactive records', 'peracrm' ) : ( $clients_type_view === 'agent' ? __( 'Agents', 'peracrm' ) : __( 'Leads', 'peracrm' ) ) ) ) : ( $is_tasks ? __( 'Tasks', 'peracrm' ) : __( 'CRM overview', 'peracrm' ) );
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
	  'date_pill_label' => __( 'Today', 'peracrm' ),
	  'actions'     => $header_actions,
	  'active_view' => $crm_active_view,
	  'show_client_filters' => $is_leads,
	  'stages'      => $stages,
	  'advisors'    => $advisors,
	  'clients_type_view' => $clients_type_view,
	  'new_leads_summary'  => ! $is_leads && ! $is_tasks ? array(
		  'count' => $new_leads_count,
		  'url'   => $new_leads_url,
	  ) : array(),
  );

  if ( function_exists( 'peracrm_frontend_render_partial' ) ) {
	  peracrm_frontend_render_partial( 'crm-header', $header_args );
  }
  ?>

	  <section class="content-panel content-panel--overlap-hero">
	    <div class="content-panel-box border-dm crm-layout">
      <div class="crm-layout__main">
			<?php if ( ! $is_leads && ! $is_tasks ) : ?>
      <section class="crm-overview-band crm-overview-band--primary" aria-labelledby="crm-priority-work-heading">
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

      <section class="crm-overview-band crm-overview-band--queue" aria-labelledby="crm-strict-new-leads-heading">
        <article class="crm-section crm-section--flush crm-overview-queue">
          <header class="crm-section__header">
            <div class="crm-section__heading-group">
              <h2 id="crm-strict-new-leads-heading" class="crm-section__title"><?php echo esc_html__( 'New leads', 'peracrm' ); ?></h2>
              <p class="crm-section__description"><?php echo esc_html__( 'Assigned leads created in the last 72 hours.', 'peracrm' ); ?></p>
            </div>
            <div class="crm-section__actions">
              <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( home_url( '/crm/clients/?type=leads&filter=new72' ) ); ?>"><?php echo esc_html__( 'Open new leads', 'peracrm' ); ?></a>
            </div>
          </header>
          <div class="crm-section__body">
          <?php if ( empty( $strict_new_leads ) ) : ?>
            <p><?php echo esc_html__( 'No newly assigned leads in the last 72 hours.', 'peracrm' ); ?></p>
          <?php else : ?>
            <ul class="crm-row-list">
            <?php foreach ( $strict_new_leads as $lead ) : ?>
              <li class="crm-row-list__item">
                <div class="crm-row-list__content">
                  <div class="crm-row-list__header">
                    <h3 class="crm-row-list__title"><a href="<?php echo esc_url( (string) ( $lead['url'] ?? '' ) ); ?>"><?php echo esc_html( (string) ( $lead['name'] ?? '' ) ); ?></a></h3>
                    <?php if ( ! empty( $lead['source'] ) ) : ?>
                      <span class="crm-chip crm-chip--status"><?php echo esc_html( (string) $lead['source'] ); ?></span>
                    <?php endif; ?>
                  </div>
                  <div class="crm-row-list__meta">
                    <span><strong><?php esc_html_e( 'Phone:', 'peracrm' ); ?></strong> <?php echo esc_html( (string) ( $lead['phone'] ?? '—' ) ); ?></span>
                    <span><strong><?php esc_html_e( 'Enquiry:', 'peracrm' ); ?></strong> <?php echo esc_html( (string) ( $lead['enquiry_at'] ?? '—' ) ); ?></span>
                  </div>
                </div>
                <div class="crm-row-list__aside">
                  <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( (string) ( $lead['url'] ?? '' ) ); ?>"><?php echo esc_html__( 'Open lead', 'peracrm' ); ?></a>
                </div>
              </li>
            <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          </div>
        </article>
      </section>

      <section class="crm-overview-band crm-overview-band--queue" aria-labelledby="crm-new-leads-heading">
        <article class="crm-section crm-section--flush crm-overview-queue">
          <header class="crm-section__header">
            <div class="crm-section__heading-group">
              <h2 id="crm-new-leads-heading" class="crm-section__title"><?php echo esc_html__( 'Recent leads', 'peracrm' ); ?></h2>
              <p class="crm-section__description"><?php echo esc_html__( 'Most recently created lead records.', 'peracrm' ); ?></p>
            </div>
            <div class="crm-section__actions">
              <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( home_url( '/crm/clients/?type=leads' ) ); ?>"><?php echo esc_html__( 'Open leads workspace', 'peracrm' ); ?></a>
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
                  <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( (string) ( $lead['url'] ?? '' ) ); ?>"><?php echo esc_html__( 'Open lead', 'peracrm' ); ?></a>
                </div>
              </li>
            <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          </div>
        </article>
      </section>

      <section class="crm-overview-band" aria-labelledby="crm-task-focus-heading">
        <article class="crm-section crm-section--flush">
          <header class="crm-section__header">
            <div class="crm-section__heading-group">
              <h2 id="crm-task-focus-heading" class="crm-section__title"><?php echo esc_html__( 'Task focus', 'peracrm' ); ?></h2>
              <p class="crm-section__description"><?php echo esc_html__( 'Use the task workspace for the full queue, sorting, and table view.', 'peracrm' ); ?></p>
            </div>
            <div class="crm-section__actions">
              <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( home_url( '/crm/tasks/' ) ); ?>"><?php echo esc_html__( 'Open task workspace', 'peracrm' ); ?></a>
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

      <section class="crm-overview-band crm-overview-band--secondary" aria-labelledby="crm-activity-heading">
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

      <section class="crm-overview-band crm-overview-band--tertiary" aria-labelledby="crm-pipeline-health-heading">
        <article class="crm-section crm-section--flush crm-overview-metrics crm-overview-health">
          <header class="crm-section__header">
            <div class="crm-section__heading-group">
              <h2 id="crm-pipeline-health-heading" class="crm-section__title"><?php echo esc_html__( 'Pipeline Health', 'peracrm' ); ?></h2>
              <p class="crm-section__description"><?php echo esc_html__( 'Action-focused indicators for ownership, urgency, and lead momentum.', 'peracrm' ); ?></p>
            </div>
          </header>
          <div class="crm-section__body">
            <div class="crm-health-grid" aria-label="<?php echo esc_attr__( 'CRM Pipeline Health', 'peracrm' ); ?>">
              <?php foreach ( $pipeline_health as $metric ) : ?>
              <?php
              $metric_key = sanitize_key( (string) ( $metric['key'] ?? '' ) );
              $metric_url = (string) ( $pipeline_health_links[ $metric_key ] ?? '' );
              ?>
              <?php if ( '' !== $metric_url ) : ?>
              <a class="card-shell crm-health-card" href="<?php echo esc_url( $metric_url ); ?>" aria-label="<?php echo esc_attr( sprintf( __( '%1$s: %2$d', 'peracrm' ), (string) ( $metric['label'] ?? '' ), (int) ( $metric['value'] ?? 0 ) ) ); ?>">
              <?php else : ?>
              <article class="card-shell crm-health-card">
              <?php endif; ?>
                <p class="crm-health-card__label"><?php echo esc_html( (string) ( $metric['label'] ?? '' ) ); ?></p>
                <h3><?php echo esc_html( (string) ( (int) ( $metric['value'] ?? 0 ) ) ); ?></h3>
                <?php if ( ! empty( $metric['context'] ) ) : ?>
                <p class="crm-health-card__meta"><?php echo esc_html( (string) $metric['context'] ); ?></p>
                <?php endif; ?>
              <?php if ( '' !== $metric_url ) : ?>
              </a>
              <?php else : ?>
              </article>
              <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </div>
        </article>
      </section>

      <?php if ( ! empty( $notices ) ) : ?>
      <section class="crm-overview-band crm-overview-band--secondary" aria-label="<?php echo esc_attr__( 'CRM notices', 'peracrm' ); ?>">
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
	      <section class="crm-overview-band crm-overview-band--secondary crm-overview-band--push" aria-labelledby="crm-push-notifications-heading">
	        <article class="crm-section crm-section--flush crm-push-panel crm-list-workspace__group crm-notifications-panel" data-crm-push-card>
	          <header class="crm-section__header">
            <div class="crm-section__heading-group">
              <span class="crm-chip crm-chip--neutral crm-notifications-panel__eyebrow"><?php echo esc_html__( 'Notifications', 'peracrm' ); ?></span>
              <h2 id="crm-push-notifications-heading" class="crm-section__title crm-notifications-panel__title"><?php echo esc_html__( 'Reminder Push Notifications', 'peracrm' ); ?></h2>
            </div>
          </header>
          <div class="crm-section__body crm-notifications-panel__body">
	          <?php if ( '' !== $push_notice_text ) : ?>
	            <span class="crm-chip crm-chip--neutral crm-notifications-panel__notice"><?php echo esc_html( $push_notice_text ); ?></span>
	          <?php endif; ?>
	          <p class="crm-notifications-panel__status" data-crm-push-status><?php echo esc_html__( 'Checking push notification status…', 'peracrm' ); ?></p>
	          <div class="crm-notifications-panel__diagnostics">
	            <p class="text-sm" data-crm-push-sw-status><?php echo esc_html__( 'Service worker status: checking…', 'peracrm' ); ?></p>
	            <p class="text-sm" data-crm-push-cron-health><?php echo esc_html__( 'Digest cron health: checking…', 'peracrm' ); ?></p>
	            <p class="text-sm" data-crm-push-diagnostics hidden></p>
	            <p class="text-sm" data-crm-push-digest-result hidden></p>
	          </div>
	          <div class="crm-task-action crm-push-panel__actions crm-notifications-panel__actions">
	            <button type="button" class="btn btn--ghost btn--blue crm-notifications-panel__button" data-crm-push-enable><?php echo esc_html__( 'Enable Push Notifications', 'peracrm' ); ?></button>
	            <button type="button" class="btn btn--ghost btn--blue crm-notifications-panel__button" data-crm-push-disable disabled><?php echo esc_html__( 'Disable on this device', 'peracrm' ); ?></button>
	            <button type="button" class="btn btn--ghost btn--blue crm-notifications-panel__button" data-crm-push-run-digest hidden><?php echo esc_html__( 'Run digest now', 'peracrm' ); ?></button>
	            <button type="button" class="btn btn--ghost btn--blue crm-notifications-panel__button" data-crm-push-refresh-diagnostics hidden><?php echo esc_html__( 'Refresh diagnostics', 'peracrm' ); ?></button>
	            <?php if ( function_exists( 'peracrm_push_is_configured' ) && peracrm_push_is_configured() ) : ?>
	            <form class="crm-push-panel__form crm-notifications-panel__form" method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>">
	              <input type="hidden" name="action" value="peracrm_send_test_push">
	              <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( home_url( '/crm/' ) ); ?>">
	              <?php wp_nonce_field( 'peracrm_send_test_push', 'peracrm_send_test_push_nonce' ); ?>
	              <button type="submit" class="btn btn--ghost btn--blue crm-notifications-panel__button"><?php echo esc_html__( 'Send test notification', 'peracrm' ); ?></button>
	            </form>
	            <?php endif; ?>
	          </div>
          </div>
        </article>
	      </section>
	      <?php endif; ?>


			<?php elseif ( $is_tasks ) : ?>
			<?php
			$task_rows_all  = is_array( $tasks_data['all'] ?? null ) ? $tasks_data['all'] : array();
			$today_rows     = is_array( $tasks_data['today'] ?? null ) ? $tasks_data['today'] : array();
			$outstanding    = is_array( $tasks_data['outstanding'] ?? null ) ? $tasks_data['outstanding'] : array();
			$upcoming       = is_array( $tasks_data['upcoming'] ?? null ) ? $tasks_data['upcoming'] : array();
			$active_rows    = is_array( $tasks_data['active_rows'] ?? null ) ? $tasks_data['active_rows'] : array();
			$tasks_active_filter = sanitize_key( (string) ( $tasks_data['active_filter'] ?? '' ) );
			$task_rows      = '' !== $tasks_active_filter ? $active_rows : $task_rows_all;
			$show_assigned  = ! empty( $task_rows ) && empty( $tasks_data['is_employee'] );
			$task_filter_labels  = array(
				'overdue' => __( 'Overdue reminders', 'peracrm' ),
				'today'   => __( 'Due today', 'peracrm' ),
				'open'    => __( 'Open reminders', 'peracrm' ),
			);
			$tasks_active_filter_label = isset( $task_filter_labels[ $tasks_active_filter ] ) ? (string) $task_filter_labels[ $tasks_active_filter ] : '';
			$tasks_page_url = home_url( wp_unslash( (string) ( $_SERVER['REQUEST_URI'] ?? '/crm/tasks/' ) ) );
			$render_task_rows = static function ( array $rows, string $empty_message, string $heading_id, string $heading, string $description, string $tasks_page_url, bool $show_assigned, string $tone = 'default' ) {
				$section_class = 'urgent' === $tone ? ' crm-list-workspace__group--urgent' : '';
				?>
      <section class="crm-section crm-section--flush crm-list-workspace__group<?php echo esc_attr( $section_class ); ?>" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
        <header class="crm-section__header">
          <div class="crm-section__heading-group">
            <h3 id="<?php echo esc_attr( $heading_id ); ?>" class="crm-section__title"><?php echo esc_html( $heading ); ?></h3>
            <p class="crm-section__description"><?php echo esc_html( $description ); ?></p>
          </div>
          <div class="crm-section__actions">
            <span class="crm-chip <?php echo esc_attr( 'urgent' === $tone ? 'crm-chip--urgent' : 'crm-chip--neutral' ); ?>"><?php echo esc_html( sprintf( _n( '%d task', '%d tasks', count( $rows ), 'peracrm' ), count( $rows ) ) ); ?></span>
          </div>
        </header>
        <div class="crm-section__body">
          <?php if ( empty( $rows ) ) : ?>
            <p class="crm-overview-empty"><?php echo esc_html( $empty_message ); ?></p>
          <?php else : ?>
            <ul class="crm-row-list crm-list-workspace__rows">
              <?php foreach ( $rows as $task ) : ?>
                <?php $task_is_overdue = ! empty( $task['is_overdue'] ) || 'urgent' === $tone; ?>
                <li class="crm-row-list__item<?php echo esc_attr( $task_is_overdue ? ' crm-row-list__item--urgent' : '' ); ?>" data-crm-reminder-row data-reminder-id="<?php echo esc_attr( (string) absint( $task['reminder_id'] ?? 0 ) ); ?>">
                  <div class="crm-row-list__content">
                    <div class="crm-row-list__header">
                      <h4 class="crm-row-list__title"><a href="<?php echo esc_url( (string) $task['client_url'] ); ?>"><?php echo esc_html( (string) $task['client_name'] ); ?></a></h4>
                      <span class="crm-chip <?php echo esc_attr( $task_is_overdue ? 'crm-chip--urgent' : 'crm-chip--status' ); ?>"><?php echo esc_html( (string) $task['due_display'] ); ?></span>
                    </div>
                    <p class="crm-row-list__summary"><?php echo esc_html( '' !== (string) ( $task['note'] ?? '' ) ? (string) $task['note'] : __( 'No task note yet.', 'peracrm' ) ); ?></p>
                    <div class="crm-meta-line">
                      <span><strong><?php esc_html_e( 'Status:', 'peracrm' ); ?></strong> <?php echo esc_html( (string) $task['status_label'] ); ?></span>
                      <?php if ( $show_assigned ) : ?>
                        <span><strong><?php esc_html_e( 'Assigned:', 'peracrm' ); ?></strong> <?php echo esc_html( '' !== (string) $task['assigned_to'] ? (string) $task['assigned_to'] : '—' ); ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="crm-row-list__aside">
                    <div class="crm-action-group crm-action-group--toolbar">
                      <a class="btn btn--ghost <?php echo esc_attr( $task_is_overdue ? 'btn--red' : 'btn--blue' ); ?> crm-action-group__item crm-action-group__item--secondary" href="<?php echo esc_url( (string) $task['client_url'] ); ?>"><?php echo esc_html__( 'Open client', 'peracrm' ); ?></a>
                      <?php if ( ! empty( $task['reminder_id'] ) && 'done' !== sanitize_key( (string) ( $task['status'] ?? 'pending' ) ) ) : ?>
                      <form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>" data-crm-reminder-action-form="1">
                        <input type="hidden" name="action" value="peracrm_update_reminder_status">
                        <input type="hidden" name="peracrm_reminder_id" value="<?php echo esc_attr( (string) absint( $task['reminder_id'] ) ); ?>">
                        <input type="hidden" name="peracrm_status" value="done">
                        <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $tasks_page_url ); ?>">
                        <input type="hidden" name="peracrm_context" value="frontend">
                        <?php wp_nonce_field( 'peracrm_update_reminder_status', 'peracrm_update_reminder_status_nonce' ); ?>
                        <button type="submit" class="btn btn--ghost btn--blue crm-task-done-btn crm-action-group__item crm-action-group__item--secondary"><?php echo esc_html__( 'Mark done', 'peracrm' ); ?></button>
                      </form>
                      <?php endif; ?>
                    </div>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </section>
				<?php
			};
			?>
      <section class="crm-toolbar crm-toolbar--content crm-list-workspace-toolbar crm-list-workspace-toolbar--tasks" aria-label="<?php echo esc_attr__( 'Tasks workspace controls', 'peracrm' ); ?>">
        <div class="crm-toolbar__row crm-list-workspace-toolbar__row">
          <div class="crm-list-workspace-toolbar__summary">
            <h2><?php echo esc_html__( 'Task workspace', 'peracrm' ); ?></h2>
            <p><?php echo esc_html__( 'Work from the table first on desktop, with grouped row lists available as a secondary responsive view.', 'peracrm' ); ?></p>
            <?php if ( '' !== $tasks_active_filter_label ) : ?>
              <div class="crm-list-workspace-toolbar__active-filter" aria-live="polite">
                <span class="crm-chip crm-chip--status"><?php echo esc_html( sprintf( __( 'Showing: %s', 'peracrm' ), $tasks_active_filter_label ) ); ?></span>
                <a class="btn btn--ghost btn--blue crm-list-workspace-toolbar__clear-filter" href="<?php echo esc_url( home_url( '/crm/tasks/' ) ); ?>"><?php esc_html_e( 'Clear', 'peracrm' ); ?></a>
              </div>
            <?php endif; ?>
            <div class="crm-meta-line crm-list-workspace-toolbar__meta">
              <span><strong><?php esc_html_e( 'Open:', 'peracrm' ); ?></strong> <?php echo esc_html( (string) count( $task_rows_all ) ); ?></span>
              <span><strong><?php esc_html_e( 'Overdue:', 'peracrm' ); ?></strong> <?php echo esc_html( (string) count( $outstanding ) ); ?></span>
              <span><strong><?php esc_html_e( 'Due today:', 'peracrm' ); ?></strong> <?php echo esc_html( (string) count( $today_rows ) ); ?></span>
              <?php if ( '' !== $tasks_active_filter_label ) : ?>
                <span><strong><?php esc_html_e( 'Filter:', 'peracrm' ); ?></strong> <?php echo esc_html( $tasks_active_filter_label ); ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="crm-toolbar-actions crm-list-workspace-toolbar__actions">
            <div class="crm-action-group crm-action-group--toolbar crm-list-workspace-toolbar__scope" aria-label="<?php echo esc_attr__( 'Task scope summary', 'peracrm' ); ?>">
              <span class="crm-chip crm-chip--urgent crm-action-group__item"><?php echo esc_html( sprintf( _n( '%d overdue', '%d overdue', count( $outstanding ), 'peracrm' ), count( $outstanding ) ) ); ?></span>
              <span class="crm-chip crm-chip--status crm-action-group__item"><?php echo esc_html( sprintf( _n( '%d due today', '%d due today', count( $today_rows ), 'peracrm' ), count( $today_rows ) ) ); ?></span>
            </div>
            <div class="crm-view-toggle crm-view-toggle--secondary" data-crm-view-toggle data-storage-key="peracrm_tasks_view" data-default-desktop="table" data-default-mobile="cards">
              <button type="button" class="btn <?php echo esc_attr( $tasks_is_cards_view ? 'btn--solid btn--blue' : 'btn--ghost' ); ?>" data-view="cards" aria-pressed="<?php echo esc_attr( $tasks_is_cards_view ? 'true' : 'false' ); ?>"><?php echo esc_html__( 'List view', 'peracrm' ); ?></button>
              <button type="button" class="btn <?php echo esc_attr( $tasks_is_cards_view ? 'btn--ghost' : 'btn--solid btn--blue' ); ?>" data-view="table" aria-pressed="<?php echo esc_attr( $tasks_is_cards_view ? 'false' : 'true' ); ?>"><?php echo esc_html__( 'Table view', 'peracrm' ); ?></button>
            </div>
          </div>
        </div>
      </section>

      <section class="crm-section crm-section--flush crm-list-workspace crm-list-workspace--table-first" data-crm-view="table" aria-labelledby="crm-tasks-table-heading">
        <header class="crm-section__header">
          <div class="crm-section__heading-group">
            <h2 id="crm-tasks-table-heading" class="crm-section__title"><?php echo esc_html__( 'Open task queue', 'peracrm' ); ?></h2>
            <p class="crm-section__description"><?php echo esc_html__( 'Scan due dates, clients, notes, ownership, and completion status in one table-first workspace.', 'peracrm' ); ?></p>
          </div>
        </header>
        <div class="crm-section__body">
          <div class="crm-leads-table-wrap crm-table-wrap crm-table-wrap--primitive crm-list-workspace__table-wrap">
            <table class="crm-leads-table crm-table crm-list-workspace__table" data-crm-sort-table>
              <thead>
                <tr>
                  <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="due"><?php echo esc_html__( 'Due', 'peracrm' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
                  <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="client"><?php echo esc_html__( 'Client', 'peracrm' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
                  <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="note"><?php echo esc_html__( 'Note', 'peracrm' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
                  <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="assigned"><?php echo esc_html__( 'Assigned advisor', 'peracrm' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
                  <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="status"><?php echo esc_html__( 'Status', 'peracrm' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
                  <th><?php echo esc_html__( 'Action', 'peracrm' ); ?></th>
                </tr>
              </thead>
              <tbody>
                <?php if ( empty( $task_rows ) ) : ?>
                <tr class="crm-table__empty"><td colspan="6"><?php echo esc_html__( 'No open tasks found.', 'peracrm' ); ?></td></tr>
                <?php else : ?>
                  <?php foreach ( $task_rows as $task ) : ?>
                  <?php $task_is_overdue = ! empty( $task['is_overdue'] ); ?>
                  <tr data-sort-row data-crm-reminder-row data-reminder-id="<?php echo esc_attr( (string) absint( $task['reminder_id'] ?? 0 ) ); ?>" data-row-url="<?php echo esc_url( (string) $task['client_url'] ); ?>" data-due="<?php echo esc_attr( (string) ( $task['due_ts'] ?? 0 ) ); ?>" data-client="<?php echo esc_attr( strtolower( (string) $task['client_name'] ) ); ?>" data-note="<?php echo esc_attr( strtolower( (string) $task['note'] ) ); ?>" data-assigned="<?php echo esc_attr( strtolower( (string) $task['assigned_to'] ) ); ?>" data-status="<?php echo esc_attr( strtolower( (string) $task['status_label'] ) ); ?>">
                    <td>
                      <div class="crm-table__primary"><?php echo esc_html( (string) $task['due_display'] ); ?></div>
                      <?php if ( $task_is_overdue ) : ?><span class="crm-table__subtext"><?php echo esc_html__( 'Overdue', 'peracrm' ); ?></span><?php endif; ?>
                    </td>
                    <td class="crm-table__cell--primary"><div class="crm-table__primary"><a href="<?php echo esc_url( (string) $task['client_url'] ); ?>"><?php echo esc_html( (string) $task['client_name'] ); ?></a></div></td>
                    <td><?php echo esc_html( '' !== (string) $task['note'] ? (string) $task['note'] : '—' ); ?></td>
                    <td><?php echo esc_html( '' !== (string) $task['assigned_to'] ? (string) $task['assigned_to'] : '—' ); ?></td>
                    <td><span class="crm-chip <?php echo esc_attr( $task_is_overdue ? 'crm-chip--urgent' : 'crm-chip--status' ); ?>"><?php echo esc_html( (string) $task['status_label'] ); ?></span></td>
                    <td>
                      <?php if ( ! empty( $task['reminder_id'] ) && 'done' !== sanitize_key( (string) ( $task['status'] ?? 'pending' ) ) ) : ?>
                      <form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>" onclick="event.stopPropagation();" data-crm-reminder-action-form="1">
                        <input type="hidden" name="action" value="peracrm_update_reminder_status">
                        <input type="hidden" name="peracrm_reminder_id" value="<?php echo esc_attr( (string) absint( $task['reminder_id'] ) ); ?>">
                        <input type="hidden" name="peracrm_status" value="done">
                        <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $tasks_page_url ); ?>">
                        <input type="hidden" name="peracrm_context" value="frontend">
                        <?php wp_nonce_field( 'peracrm_update_reminder_status', 'peracrm_update_reminder_status_nonce' ); ?>
                        <button type="submit" class="btn btn--ghost btn--blue crm-task-done-btn"><?php echo esc_html__( 'Mark done', 'peracrm' ); ?></button>
                      </form>
                      <?php else : ?>
                      <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( (string) $task['client_url'] ); ?>"><?php echo esc_html__( 'Open client', 'peracrm' ); ?></a>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <div class="crm-list-workspace crm-list-workspace--grouped is-hidden" data-crm-view="cards">
        <?php if ( 'overdue' === $tasks_active_filter ) : ?>
          <?php $render_task_rows( $task_rows, __( 'No overdue tasks.', 'peracrm' ), 'crm-tasks-outstanding-heading', __( 'Overdue tasks', 'peracrm' ), __( 'Resolve past-due follow-up before moving into the rest of the queue.', 'peracrm' ), $tasks_page_url, $show_assigned, 'urgent' ); ?>
        <?php elseif ( 'today' === $tasks_active_filter ) : ?>
          <?php $render_task_rows( $task_rows, __( 'No tasks due today.', 'peracrm' ), 'crm-tasks-today-heading', __( "Today's tasks", 'peracrm' ), __( 'Today’s due work stays grouped separately so quick processing is easier on smaller screens.', 'peracrm' ), $tasks_page_url, $show_assigned ); ?>
        <?php elseif ( 'open' === $tasks_active_filter ) : ?>
          <?php $render_task_rows( $task_rows, __( 'No open tasks found.', 'peracrm' ), 'crm-tasks-open-heading', __( 'Open tasks', 'peracrm' ), __( 'Showing the full open queue for this scope in a single list view section.', 'peracrm' ), $tasks_page_url, $show_assigned ); ?>
        <?php else : ?>
          <?php $render_task_rows( $outstanding, __( 'No outstanding tasks.', 'peracrm' ), 'crm-tasks-outstanding-heading', __( 'Overdue tasks', 'peracrm' ), __( 'Resolve past-due follow-up before moving into the rest of the queue.', 'peracrm' ), $tasks_page_url, $show_assigned, 'urgent' ); ?>
          <?php $render_task_rows( $today_rows, __( 'No tasks due today.', 'peracrm' ), 'crm-tasks-today-heading', __( "Today's tasks", 'peracrm' ), __( 'Today’s due work stays grouped separately so quick processing is easier on smaller screens.', 'peracrm' ), $tasks_page_url, $show_assigned ); ?>
          <?php $render_task_rows( $upcoming, __( 'No upcoming tasks.', 'peracrm' ), 'crm-tasks-upcoming-heading', __( 'Upcoming tasks', 'peracrm' ), __( 'Future reminders remain available as a calmer secondary queue.', 'peracrm' ), $tasks_page_url, $show_assigned ); ?>
        <?php endif; ?>
      </div>
		<?php else : ?>
			<?php
			$items         = is_array( $leads_data['items'] ?? null ) ? $leads_data['items'] : array();
			$total         = (int) ( $leads_data['total'] ?? 0 );
			$per_page      = (int) ( $leads_data['per_page'] ?? 20 );
			$total_pages   = max( 1, (int) ( $leads_data['total_pages'] ?? 1 ) );
			$current_page  = max( 1, (int) ( $leads_data['current_page'] ?? 1 ) );
			$from          = $total > 0 ? ( ( $current_page - 1 ) * $per_page ) + 1 : 0;
			$to            = min( $current_page * $per_page, $total );
			$is_inactive   = 'inactive' === $clients_type_view;
			$is_agent      = 'agent' === $clients_type_view;
			$filter_q      = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) : '';
			$filter_stage  = isset( $_GET['stage'] ) ? sanitize_key( wp_unslash( (string) $_GET['stage'] ) ) : '';
			$filter_advisor = isset( $_GET['advisor'] ) ? absint( wp_unslash( (string) $_GET['advisor'] ) ) : 0;
			$status_labels = array(
				'closed'  => __( 'Closed', 'peracrm' ),
				'dormant' => __( 'Dormant', 'peracrm' ),
			);
			$scope_label = $is_inactive ? __( 'Inactive records', 'peracrm' ) : ( $is_agent ? __( 'Agents', 'peracrm' ) : ( 'clients' === $clients_type_view ? __( 'Clients', 'peracrm' ) : __( 'Leads', 'peracrm' ) ) );
			$active_filter_bits = array();
			$clients_active_filter = sanitize_key( (string) ( $leads_data['active_filter'] ?? '' ) );
			$clients_filter_labels = array(
				'unassigned' => __( 'Unassigned leads', 'peracrm' ),
				'stale'      => __( 'Stale leads', 'peracrm' ),
				'new72'      => __( 'New leads (72h)', 'peracrm' ),
				'open_scope' => __( 'Open leads in scope', 'peracrm' ),
			);
			$clients_active_filter_label = isset( $clients_filter_labels[ $clients_active_filter ] ) ? (string) $clients_filter_labels[ $clients_active_filter ] : '';
			if ( '' !== $filter_q ) {
				$active_filter_bits[] = sprintf( __( 'Search: %s', 'peracrm' ), $filter_q );
			}
			if ( '' !== $filter_stage ) {
				$active_filter_bits[] = sprintf( __( 'Stage: %s', 'peracrm' ), (string) ( $stages[ $filter_stage ] ?? $filter_stage ) );
			}
			if ( $filter_advisor > 0 ) {
				foreach ( $advisors as $advisor ) {
					if ( (int) ( $advisor['id'] ?? 0 ) === $filter_advisor ) {
						$active_filter_bits[] = sprintf( __( 'Advisor: %s', 'peracrm' ), (string) ( $advisor['label'] ?? '' ) );
						break;
					}
				}
			}
			if ( '' !== $clients_active_filter_label ) {
				$active_filter_bits[] = sprintf( __( 'Filter: %s', 'peracrm' ), $clients_active_filter_label );
			}
			?>
      <div data-crm-clients-workspace>
      <?php if ( ! empty( $clients_new_leads_items ) ) : ?>
      <section class="crm-section crm-section--flush crm-list-workspace crm-list-workspace--rows" aria-labelledby="crm-clients-new-leads-heading">
        <header class="crm-section__header">
          <div class="crm-section__heading-group">
            <h2 id="crm-clients-new-leads-heading" class="crm-section__title"><?php echo esc_html__( 'New leads', 'peracrm' ); ?></h2>
            <p class="crm-section__description"><?php echo esc_html__( 'Recently assigned leads that still need first-touch review.', 'peracrm' ); ?></p>
          </div>
        </header>
        <div class="crm-section__body">
          <ul class="crm-row-list crm-list-workspace__rows">
				<?php foreach ( $clients_new_leads_items as $lead ) : ?>
              <li class="crm-row-list__item">
                <div class="crm-row-list__content">
                  <div class="crm-row-list__header">
                    <h3 class="crm-row-list__title"><a href="<?php echo esc_url( (string) $lead['url'] ); ?>"><?php echo esc_html( (string) $lead['name'] ); ?></a></h3>
                    <span class="crm-chip crm-chip--status"><?php echo esc_html__( 'New lead', 'peracrm' ); ?></span>
                  </div>
                  <div class="crm-meta-line">
                    <span><strong><?php esc_html_e( 'Source:', 'peracrm' ); ?></strong> <?php echo esc_html( (string) ( $lead['source'] ?? '—' ) ); ?></span>
                    <span><strong><?php esc_html_e( 'Received:', 'peracrm' ); ?></strong> <?php echo esc_html( (string) ( $lead['enquiry_at'] ?? '—' ) ); ?></span>
                  </div>
                </div>
                <div class="crm-row-list__aside">
                  <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( (string) $lead['url'] ); ?>"><?php echo esc_html__( 'Open lead', 'peracrm' ); ?></a>
                </div>
              </li>
				<?php endforeach; ?>
          </ul>
        </div>
      </section>
      <?php endif; ?>

      <section class="crm-toolbar crm-toolbar--content crm-list-workspace-toolbar crm-list-workspace-toolbar--leads" aria-label="<?php echo esc_attr__( 'Lead and client workspace controls', 'peracrm' ); ?>">
        <div class="crm-toolbar__row crm-list-workspace-toolbar__row">
          <div class="crm-list-workspace-toolbar__summary">
            <h2><?php echo esc_html( $scope_label ); ?></h2>
            <p><?php echo esc_html__( 'Use the workspace table to scan status, source, advisor ownership, and recent activity before opening individual records.', 'peracrm' ); ?></p>
            <?php if ( '' !== $clients_active_filter_label ) : ?>
              <div class="crm-list-workspace-toolbar__active-filter" aria-live="polite">
                <span class="crm-chip crm-chip--status"><?php echo esc_html( sprintf( __( 'Showing: %s', 'peracrm' ), $clients_active_filter_label ) ); ?></span>
                <a class="btn btn--ghost btn--blue crm-list-workspace-toolbar__clear-filter" href="<?php echo esc_url( home_url( '/crm/clients/?type=' . $clients_type_view ) ); ?>"><?php esc_html_e( 'Clear', 'peracrm' ); ?></a>
              </div>
            <?php endif; ?>
            <div class="crm-meta-line crm-list-workspace-toolbar__meta">
              <span><strong><?php esc_html_e( 'Showing:', 'peracrm' ); ?></strong> <?php echo esc_html( sprintf( __( '%1$d–%2$d of %3$d', 'peracrm' ), $from, $to, $total ) ); ?></span>
              <span><strong><?php esc_html_e( 'Scope:', 'peracrm' ); ?></strong> <?php echo esc_html( $scope_label ); ?></span>
              <?php if ( ! empty( $active_filter_bits ) ) : ?>
                <span><strong><?php esc_html_e( 'Filters:', 'peracrm' ); ?></strong> <?php echo esc_html( implode( ' • ', $active_filter_bits ) ); ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="crm-toolbar-actions crm-list-workspace-toolbar__actions">
            <div class="crm-type-toggle crm-action-group crm-action-group--toolbar" data-crm-clients-type-toggle role="group" aria-label="<?php echo esc_attr__( 'Lead or client listing', 'peracrm' ); ?>">
              <a class="btn <?php echo esc_attr( 'leads' === $clients_type_view ? 'btn--solid' : 'btn--ghost' ); ?> btn--blue crm-action-group__item" href="<?php echo esc_url( add_query_arg( 'type', 'leads', home_url( '/crm/clients/' ) ) ); ?>"><?php esc_html_e( 'Leads', 'peracrm' ); ?></a>
              <a class="btn <?php echo esc_attr( 'clients' === $clients_type_view ? 'btn--solid' : 'btn--ghost' ); ?> btn--blue crm-action-group__item" href="<?php echo esc_url( add_query_arg( 'type', 'clients', home_url( '/crm/clients/' ) ) ); ?>"><?php esc_html_e( 'Clients', 'peracrm' ); ?></a>
              <a class="btn <?php echo esc_attr( $is_inactive ? 'btn--solid' : 'btn--ghost' ); ?> btn--blue crm-action-group__item" href="<?php echo esc_url( add_query_arg( 'type', 'inactive', home_url( '/crm/clients/' ) ) ); ?>"><?php esc_html_e( 'Inactive', 'peracrm' ); ?></a>
              <a class="btn <?php echo esc_attr( $is_agent ? 'btn--solid' : 'btn--ghost' ); ?> btn--blue crm-action-group__item" href="<?php echo esc_url( add_query_arg( 'type', 'agent', home_url( '/crm/clients/' ) ) ); ?>"><?php esc_html_e( 'Agent', 'peracrm' ); ?></a>
            </div>
            <div class="crm-view-toggle crm-view-toggle--secondary" data-crm-view-toggle data-storage-key="peracrm_clients_view" data-default-desktop="table" data-default-mobile="cards">
              <button type="button" class="btn <?php echo esc_attr( $clients_is_cards_view ? 'btn--solid btn--blue' : 'btn--ghost' ); ?>" data-view="cards" aria-pressed="<?php echo esc_attr( $clients_is_cards_view ? 'true' : 'false' ); ?>"><?php echo esc_html__( 'List view', 'peracrm' ); ?></button>
              <button type="button" class="btn <?php echo esc_attr( $clients_is_cards_view ? 'btn--ghost' : 'btn--solid btn--blue' ); ?>" data-view="table" aria-pressed="<?php echo esc_attr( $clients_is_cards_view ? 'false' : 'true' ); ?>"><?php echo esc_html__( 'Table view', 'peracrm' ); ?></button>
            </div>
          </div>
        </div>
      </section>

      <section class="crm-section crm-section--flush crm-list-workspace crm-list-workspace--table-first" data-crm-view="table" aria-labelledby="crm-leads-table-heading">
        <header class="crm-section__header">
          <div class="crm-section__heading-group">
            <h2 id="crm-leads-table-heading" class="crm-section__title"><?php echo esc_html__( 'Record list', 'peracrm' ); ?></h2>
            <p class="crm-section__description"><?php echo esc_html__( 'Desktop defaults to a table-first list so names, status, source, owner, and recency remain visible in a single scan path.', 'peracrm' ); ?></p>
          </div>
        </header>
        <div class="crm-section__body">
          <div class="crm-leads-table-wrap crm-table-wrap crm-table-wrap--primitive crm-list-workspace__table-wrap">
            <table class="crm-leads-table crm-table crm-list-workspace__table" data-crm-sort-table>
              <thead>
                <tr>
                  <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="name" data-sort-type="text"><?php echo esc_html__( 'Name', 'peracrm' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
                  <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="status" data-sort-type="text"><?php echo esc_html__( 'Status / stage', 'peracrm' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
                  <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="source" data-sort-type="text" data-sort-empty-last="true"><?php echo esc_html__( 'Source', 'peracrm' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
                  <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="assigned" data-sort-type="text" data-sort-empty-last="true"><?php echo esc_html__( 'Assigned advisor', 'peracrm' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
                  <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="health" data-sort-type="text" data-sort-empty-last="true"><?php echo esc_html__( 'Record health', 'peracrm' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
                  <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="budget" data-sort-type="number" data-sort-empty-last="true"><?php echo esc_html__( 'Budget', 'peracrm' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
                  <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="next-task" data-sort-type="date" data-sort-empty-last="true"><?php echo esc_html__( 'Next task due', 'peracrm' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
                  <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="updated" data-sort-type="date"><?php echo esc_html__( 'Last activity', 'peracrm' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
                  <th aria-sort="none"><button type="button" class="crm-table-sort" data-sort="created" data-sort-type="date"><?php echo esc_html__( 'Created', 'peracrm' ); ?> <span class="peracrm-sort-indicator" aria-hidden="true"></span></button></th>
                </tr>
              </thead>
              <tbody>
					<?php if ( empty( $items ) ) : ?>
                <tr>
                  <td class="crm-table__empty" colspan="9"><?php echo esc_html__( 'No records found for this scope.', 'peracrm' ); ?></td>
                </tr>
					<?php else : ?>
					<?php foreach ( $items as $lead ) : ?>
						<?php
						$engagement_key = sanitize_key( (string) ( $lead['engagement_state'] ?? '' ) );
						$status_label   = isset( $status_labels[ $engagement_key ] ) ? $status_labels[ $engagement_key ] : (string) ( $stages[ $lead['stage'] ] ?? $lead['stage'] );
						?>
                <tr data-sort-row data-row-url="<?php echo esc_url( (string) $lead['crm_url'] ); ?>" data-name="<?php echo esc_attr( strtolower( (string) $lead['title'] ) ); ?>" data-status="<?php echo esc_attr( strtolower( (string) $status_label ) ); ?>" data-source="<?php echo esc_attr( strtolower( (string) ( $lead['source'] ?? '' ) ) ); ?>" data-assigned="<?php echo esc_attr( strtolower( (string) ( $lead['assigned_to'] ?? '' ) ) ); ?>" data-health="<?php echo esc_attr( strtolower( (string) ( $lead['record_health'] ?? '' ) ) ); ?>" data-budget="<?php echo esc_attr( (string) ( $lead['budget_value'] ?? 0 ) ); ?>" data-next-task="<?php echo esc_attr( (string) ( $lead['next_task_due_ts'] ?? 0 ) ); ?>" data-updated="<?php echo esc_attr( (string) ( $lead['updated_ts'] ?? 0 ) ); ?>" data-created="<?php echo esc_attr( (string) ( $lead['created_ts'] ?? 0 ) ); ?>">
                  <td class="crm-table__cell--primary">
                    <div class="crm-table__primary">
                      <a href="<?php echo esc_url( (string) $lead['crm_url'] ); ?>"><?php echo esc_html( (string) $lead['title'] ); ?></a>
                      <span class="crm-table__subtext"><?php echo esc_html( (string) $lead['engagement_state'] ); ?></span>
                    </div>
                  </td>
                  <td><span class="crm-chip crm-chip--status"><?php echo esc_html( (string) $status_label ); ?></span></td>
                  <td><?php echo esc_html( '' !== (string) ( $lead['source'] ?? '' ) ? (string) $lead['source'] : '—' ); ?></td>
                  <td><?php echo esc_html( '' !== (string) ( $lead['assigned_to'] ?? '' ) ? (string) $lead['assigned_to'] : '—' ); ?></td>
                  <td>
						<?php if ( ! empty( $lead['record_health_badge_html'] ) ) : ?>
							<?php echo wp_kses_post( (string) $lead['record_health_badge_html'] ); ?>
						<?php else : ?>
                    <?php echo esc_html( '' !== (string) ( $lead['record_health'] ?? '' ) ? (string) $lead['record_health'] : '—' ); ?>
						<?php endif; ?>
                  </td>
                  <td><?php echo esc_html( '' !== (string) ( $lead['budget_display'] ?? '' ) ? (string) $lead['budget_display'] : '—' ); ?></td>
                  <td>
						<?php $next_task_due_text = trim( (string) ( $lead['next_task_due'] ?? '' ) ); ?>
						<?php if ( '' !== $next_task_due_text || ! empty( $lead['next_task_due_ts'] ) ) : ?>
                      <span class="<?php echo esc_attr( ! empty( $lead['next_task_overdue'] ) ? 'crm-chip crm-chip--urgent' : ( ! empty( $lead['next_task_today'] ) ? 'crm-chip crm-chip--selected' : 'pill pill--green' ) ); ?>" title="<?php echo esc_attr( (string) ( $lead['next_task_detail'] ?? '' ) ); ?>" aria-label="<?php echo esc_attr( (string) ( $lead['next_task_detail'] ?? '' ) ); ?>"><?php echo esc_html( $next_task_due_text ); ?></span>
							<?php if ( '' !== (string) ( $lead['next_task_detail'] ?? '' ) ) : ?>
                        <span class="crm-table__subtext crm-next-task-detail"><?php echo esc_html( (string) $lead['next_task_detail'] ); ?></span>
							<?php endif; ?>
						<?php else : ?>
                      <a class="btn btn--ghost btn--blue crm-table__add-task-btn" href="<?php echo esc_url( (string) ( $lead['next_task_url'] ?? ( (string) $lead['crm_url'] . '#crm-client-next-actions' ) ) ); ?>"><?php echo esc_html__( 'Add task', 'peracrm' ); ?></a>
						<?php endif; ?>
                  </td>
                  <td><?php echo esc_html( '' !== $lead['last_activity'] ? (string) $lead['last_activity'] : ( '' !== (string) ( $lead['updated'] ?? '' ) ? (string) $lead['updated'] : '—' ) ); ?></td>
                  <td><?php echo esc_html( '' !== (string) ( $lead['created'] ?? '' ) ? (string) $lead['created'] : '—' ); ?></td>
                </tr>
						<?php endforeach; ?>
					<?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

	      <section class="crm-section crm-section--flush crm-list-workspace crm-list-workspace--rows is-hidden" data-crm-view="cards" aria-labelledby="crm-leads-row-list-heading">
          <header class="crm-section__header">
            <div class="crm-section__heading-group">
              <h2 id="crm-leads-row-list-heading" class="crm-section__title"><?php echo esc_html__( 'Structured list view', 'peracrm' ); ?></h2>
              <p class="crm-section__description"><?php echo esc_html__( 'This compact row-list fallback is kept for narrower screens and secondary browsing, not as the main desktop workspace.', 'peracrm' ); ?></p>
            </div>
          </header>
          <div class="crm-section__body">
            <?php if ( empty( $items ) ) : ?>
              <p class="crm-overview-empty"><?php echo esc_html__( 'No leads found for this scope.', 'peracrm' ); ?></p>
            <?php else : ?>
              <ul class="crm-row-list crm-list-workspace__rows">
					<?php foreach ( $items as $lead ) : ?>
						<?php
						$engagement_key = sanitize_key( (string) ( $lead['engagement_state'] ?? '' ) );
						$status_label   = isset( $status_labels[ $engagement_key ] ) ? $status_labels[ $engagement_key ] : (string) ( $stages[ $lead['stage'] ] ?? $lead['stage'] );
						$view_label     = 'clients' === $clients_type_view || $is_inactive || $is_agent ? __( 'View Client', 'peracrm' ) : __( 'View Lead', 'peracrm' );
						?>
                  <li class="crm-row-list__item">
                    <div class="crm-row-list__content">
                      <div class="crm-row-list__header">
                        <h3 class="crm-row-list__title"><a href="<?php echo esc_url( (string) $lead['crm_url'] ); ?>"><?php echo esc_html( (string) $lead['title'] ); ?></a></h3>
                        <span class="crm-chip crm-chip--status"><?php echo esc_html( (string) $status_label ); ?></span>
                      </div>
                      <div class="crm-meta-line">
                        <span><strong><?php esc_html_e( 'Source:', 'peracrm' ); ?></strong> <?php echo esc_html( '' !== (string) ( $lead['source'] ?? '' ) ? (string) $lead['source'] : '—' ); ?></span>
                        <span><strong><?php esc_html_e( 'Advisor:', 'peracrm' ); ?></strong> <?php echo esc_html( '' !== (string) ( $lead['assigned_to'] ?? '' ) ? (string) $lead['assigned_to'] : '—' ); ?></span>
                        <span><strong><?php esc_html_e( 'Last activity:', 'peracrm' ); ?></strong> <?php echo esc_html( '' !== $lead['last_activity'] ? (string) $lead['last_activity'] : '—' ); ?></span>
                      </div>
                      <p class="crm-row-list__summary"><strong><?php esc_html_e( 'Engagement:', 'peracrm' ); ?></strong> <?php echo esc_html( (string) $lead['engagement_state'] ); ?><?php echo '' !== (string) ( $lead['disposition'] ?? '' ) ? esc_html( ' • ' . (string) $lead['disposition'] ) : ''; ?></p>
                    </div>
                    <div class="crm-row-list__aside">
                      <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( (string) $lead['crm_url'] ); ?>"><?php echo esc_html( $view_label ); ?></a>
                    </div>
                  </li>
					<?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </section>

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
				?>
				<nav class="crm-clients-pagination" aria-label="<?php esc_attr_e( 'Clients pagination', 'peracrm' ); ?>">
					<?php echo wp_kses_post( $pagination ); ?>
				</nav>
				<?php
			}
			?>
      </div>
		<?php endif; ?>
      </div>
      <?php if ( function_exists( 'peracrm_frontend_render_partial' ) ) { peracrm_frontend_render_partial( 'crm-side-nav', array( 'active_view' => $crm_active_view ) ); } ?>
    </div>
  </section>
</main>

<?php
peracrm_frontend_render_shell_footer();
