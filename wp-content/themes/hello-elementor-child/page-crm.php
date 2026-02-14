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
$clients_type_view = 'clients' === $clients_type_view ? 'clients' : 'leads';
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
		'notices'       => array( __( 'CRM data unavailable.', 'hello-elementor-child' ) ),
	);

$leads_data = $is_leads && function_exists( 'pera_crm_get_leads_view_data' )
	? pera_crm_get_leads_view_data( $current_page, 20, $derived_type_filter )
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
	$push_notice_text = __( 'Test push notification sent.', 'hello-elementor-child' );
} elseif ( 'test_push_failed' === $push_notice_key ) {
	$push_notice_text = __( 'Unable to send test push. Make sure push is enabled on this device.', 'hello-elementor-child' );
}

$kpi_tiles = array(
	array( 'label' => __( 'Total open leads', 'hello-elementor-child' ), 'key' => 'total_open_leads' ),
	array( 'label' => __( 'New enquiries', 'hello-elementor-child' ), 'key' => 'new_enquiries' ),
	array( 'label' => __( 'Qualified', 'hello-elementor-child' ), 'key' => 'qualified' ),
	array( 'label' => __( 'Viewing arranged', 'hello-elementor-child' ), 'key' => 'viewing_arranged' ),
	array( 'label' => __( 'Offer made', 'hello-elementor-child' ), 'key' => 'offer_made' ),
	array( 'label' => __( 'Overdue reminders', 'hello-elementor-child' ), 'key' => 'overdue_reminders' ),
);

$stages = function_exists( 'pera_crm_get_pipeline_stages' ) ? pera_crm_get_pipeline_stages() : array();

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

get_header();
?>

<main id="primary" class="site-main crm-page crm-page--<?php echo esc_attr( $is_leads ? 'leads' : ( $is_tasks ? 'tasks' : 'overview' ) ); ?>">
  <?php
  get_template_part(
	  'parts/crm-header',
	  null,
	  array(
		  'title'       => __( 'CRM', 'hello-elementor-child' ),
		  'description' => __( 'Staff workspace for daily pipeline, workload, and account visibility.', 'hello-elementor-child' ),
		  'active_view' => $crm_active_view,
	  )
  );
  ?>

  <section class="content-panel content-panel--overlap-hero">
    <div class="content-panel-box border-dm">
      <?php if ( is_user_logged_in() ) : ?>
      <section class="section" aria-label="<?php echo esc_attr__( 'Push notifications', 'hello-elementor-child' ); ?>">
        <article class="card-shell" data-crm-push-card>
          <p class="pill pill--outline"><?php echo esc_html__( 'Notifications', 'hello-elementor-child' ); ?></p>
          <h2><?php echo esc_html__( 'Reminder Push Notifications', 'hello-elementor-child' ); ?></h2>
          <?php if ( '' !== $push_notice_text ) : ?>
            <p class="pill pill--outline"><?php echo esc_html( $push_notice_text ); ?></p>
          <?php endif; ?>
          <p data-crm-push-status><?php echo esc_html__( 'Checking push notification status…', 'hello-elementor-child' ); ?></p>
          <div class="crm-task-action" style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="button" class="btn btn--ghost btn--blue" data-crm-push-enable><?php echo esc_html__( 'Enable Push Notifications', 'hello-elementor-child' ); ?></button>
            <button type="button" class="btn btn--ghost" data-crm-push-disable disabled><?php echo esc_html__( 'Disable on this device', 'hello-elementor-child' ); ?></button>
            <?php if ( function_exists( 'peracrm_push_is_configured' ) && peracrm_push_is_configured() ) : ?>
            <form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>">
              <input type="hidden" name="action" value="peracrm_send_test_push">
              <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( home_url( '/crm/' ) ); ?>">
              <?php wp_nonce_field( 'peracrm_send_test_push', 'peracrm_send_test_push_nonce' ); ?>
              <button type="submit" class="btn btn--ghost"><?php echo esc_html__( 'Send test notification', 'hello-elementor-child' ); ?></button>
            </form>
            <?php endif; ?>
          </div>
        </article>
      </section>
      <?php endif; ?>
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
            <?php $today_cards = $build_overview_task_cards( $todays_tasks, $overview_task_cap ); ?>
            <div class="crm-overview-task-grid">
            <?php foreach ( $today_cards['tasks'] as $task ) : ?>
              <article class="card-shell crm-task-card">
                <a class="btn btn--ghost btn--blue crm-task-client-btn" href="<?php echo esc_url( home_url( '/crm/client/' . (int) $task['lead_id'] . '/' ) ); ?>"><?php echo esc_html( (string) ( $task['lead_name'] ?: __( 'Untitled lead', 'hello-elementor-child' ) ) ); ?></a>
                <span class="pill pill--outline"><?php echo esc_html( (string) $task['due_date'] ); ?></span>
                <p class="crm-task-note"><strong><?php esc_html_e( 'Task:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( (string) $task['reminder_note'] ); ?></p>
                <p class="text-sm crm-task-last-note"><strong><?php esc_html_e( 'Latest note:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( (string) ( $task['last_note'] ?? __( 'No recent notes yet.', 'hello-elementor-child' ) ) ); ?></p>
                <?php $task_status = sanitize_key( (string) ( $task['status'] ?? 'pending' ) ); ?>
                <?php if ( ! empty( $task['reminder_id'] ) && 'pending' === $task_status ) : ?>
                <form class="crm-task-action" method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>">
                  <input type="hidden" name="action" value="peracrm_update_reminder_status">
                  <input type="hidden" name="peracrm_reminder_id" value="<?php echo esc_attr( (string) absint( $task['reminder_id'] ) ); ?>">
                  <input type="hidden" name="peracrm_status" value="done">
                  <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $crm_current_url ); ?>">
                  <input type="hidden" name="peracrm_context" value="frontend">
                  <?php wp_nonce_field( 'peracrm_update_reminder_status', 'peracrm_update_reminder_status_nonce' ); ?>
                  <button type="submit" class="btn btn--ghost btn--blue crm-task-done-btn"><?php echo esc_html__( 'Mark done', 'hello-elementor-child' ); ?></button>
                </form>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
            <?php if ( $today_cards['has_more'] ) : ?>
              <article class="card-shell crm-task-card crm-task-card--more">
                <p><?php echo esc_html__( 'You have more tasks. Click see all to view them.', 'hello-elementor-child' ); ?></p>
                <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( home_url( '/crm/tasks/' ) ); ?>"><?php echo esc_html__( 'See all tasks', 'hello-elementor-child' ); ?></a>
              </article>
            <?php endif; ?>
            </div>
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
            <?php $overdue_cards = $build_overview_task_cards( $overdue_tasks, $overview_task_cap ); ?>
            <div class="crm-overview-task-grid">
            <?php foreach ( $overdue_cards['tasks'] as $task ) : ?>
              <article class="card-shell crm-task-card">
                <a class="btn btn--ghost btn--red crm-task-client-btn" href="<?php echo esc_url( home_url( '/crm/client/' . (int) $task['lead_id'] . '/' ) ); ?>"><?php echo esc_html( (string) ( $task['lead_name'] ?: __( 'Untitled lead', 'hello-elementor-child' ) ) ); ?></a>
                <span class="pill pill--red"><?php echo esc_html( (string) $task['due_date'] ); ?></span>
                <p class="crm-task-note"><strong><?php esc_html_e( 'Task:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( (string) $task['reminder_note'] ); ?></p>
                <p class="text-sm crm-task-last-note"><strong><?php esc_html_e( 'Latest note:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( (string) ( $task['last_note'] ?? __( 'No recent notes yet.', 'hello-elementor-child' ) ) ); ?></p>
                <?php $task_status = sanitize_key( (string) ( $task['status'] ?? 'pending' ) ); ?>
                <?php if ( ! empty( $task['reminder_id'] ) && 'pending' === $task_status ) : ?>
                <form class="crm-task-action" method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>">
                  <input type="hidden" name="action" value="peracrm_update_reminder_status">
                  <input type="hidden" name="peracrm_reminder_id" value="<?php echo esc_attr( (string) absint( $task['reminder_id'] ) ); ?>">
                  <input type="hidden" name="peracrm_status" value="done">
                  <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $crm_current_url ); ?>">
                  <input type="hidden" name="peracrm_context" value="frontend">
                  <?php wp_nonce_field( 'peracrm_update_reminder_status', 'peracrm_update_reminder_status_nonce' ); ?>
                  <button type="submit" class="btn btn--ghost btn--blue crm-task-done-btn"><?php echo esc_html__( 'Mark done', 'hello-elementor-child' ); ?></button>
                </form>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
            <?php if ( $overdue_cards['has_more'] ) : ?>
              <article class="card-shell crm-task-card crm-task-card--more">
                <p><?php echo esc_html__( 'You have more tasks. Click see all to view them.', 'hello-elementor-child' ); ?></p>
                <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( home_url( '/crm/tasks/' ) ); ?>"><?php echo esc_html__( 'See all tasks', 'hello-elementor-child' ); ?></a>
              </article>
            <?php endif; ?>
            </div>
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
            <div class="crm-new-leads-grid">
            <?php foreach ( $new_leads as $lead ) : ?>
              <article class="card-shell crm-new-lead-card">
                <h3><?php echo esc_html( (string) ( $lead['name'] ?? '' ) ); ?></h3>
                <p><strong><?php esc_html_e( 'Phone:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( (string) ( $lead['phone'] ?? '—' ) ); ?></p>
                <p><strong><?php esc_html_e( 'Source:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( (string) ( $lead['source'] ?? '—' ) ); ?></p>
                <p><strong><?php esc_html_e( 'Enquiry:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( (string) ( $lead['enquiry_at'] ?? '—' ) ); ?></p>
                <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( (string) ( $lead['url'] ?? '' ) ); ?>"><?php echo esc_html__( 'View lead', 'hello-elementor-child' ); ?></a>
              </article>
            <?php endforeach; ?>
            </div>
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
		<div class="grid-3 crm-kpi-grid cards-slider cards-slider--snap cards-slider--grid-lg" aria-label="<?php echo esc_attr__( 'CRM KPI Snapshot', 'hello-elementor-child' ); ?>">
				<?php foreach ( $kpi_tiles as $tile ) : ?>
          <article class="card-shell slider-card crm-kpi-card">
            <p class="pill pill--outline"><?php echo esc_html( $tile['label'] ); ?></p>
            <h3><?php echo esc_html( (string) ( (int) ( $kpis[ $tile['key'] ] ?? 0 ) ) ); ?></h3>
          </article>
				<?php endforeach; ?>
        </div>
      </section>

      <section class="section" aria-labelledby="crm-pipeline-heading">
        <header class="section-header">
          <h2 id="crm-pipeline-heading"><?php echo esc_html__( 'Pipeline Overview', 'hello-elementor-child' ); ?></h2>
        </header>
        <div class="grid-3 crm-kpi-grid cards-slider cards-slider--snap cards-slider--grid-lg" aria-label="<?php echo esc_attr__( 'CRM Pipeline Overview', 'hello-elementor-child' ); ?>">
				<?php foreach ( $pipeline as $stage ) : ?>
            <article class="card-shell slider-card crm-kpi-card">
              <p class="pill pill--outline"><?php echo esc_html( (string) ( $stage['label'] ?? '' ) ); ?></p>
              <h3><?php echo esc_html( (string) ( (int) ( $stage['count'] ?? 0 ) ) ); ?></h3>
            </article>
				<?php endforeach; ?>
        </div>
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
                    <a class="btn btn--ghost btn--blue crm-task-client-btn" href="<?php echo esc_url( (string) $task['client_url'] ); ?>"><?php echo esc_html( (string) $task['client_name'] ); ?></a>
                    <span class="pill pill--outline"><?php echo esc_html( (string) $task['due_display'] ); ?></span>
                    <p class="crm-task-note"><strong><?php esc_html_e( 'Task:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( (string) $task['note'] ); ?></p>
                    <?php if ( $show_assigned ) : ?><span><strong><?php echo esc_html__( 'Assigned:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( '' !== (string) $task['assigned_to'] ? (string) $task['assigned_to'] : '—' ); ?></span><?php endif; ?>
                    <p class="text-sm crm-task-last-note"><strong><?php esc_html_e( 'Latest note:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( (string) ( $task['note'] ?? __( 'No recent notes yet.', 'hello-elementor-child' ) ) ); ?></p>
                    <span class="pill pill--outline"><?php echo esc_html( (string) $task['status_label'] ); ?></span>
                    <?php if ( ! empty( $task['reminder_id'] ) ) : ?>
                    <form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>">
                      <input type="hidden" name="action" value="peracrm_update_reminder_status">
                      <input type="hidden" name="peracrm_reminder_id" value="<?php echo esc_attr( (string) absint( $task['reminder_id'] ) ); ?>">
                      <input type="hidden" name="peracrm_status" value="done">
                      <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $tasks_page_url ); ?>">
                      <input type="hidden" name="peracrm_context" value="frontend">
                      <?php wp_nonce_field( 'peracrm_update_reminder_status', 'peracrm_update_reminder_status_nonce' ); ?>
                      <button type="submit" class="btn btn--ghost btn--blue crm-task-done-btn"><?php echo esc_html__( 'Mark done', 'hello-elementor-child' ); ?></button>
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
                    <a class="btn btn--ghost btn--red crm-task-client-btn" href="<?php echo esc_url( (string) $task['client_url'] ); ?>"><?php echo esc_html( (string) $task['client_name'] ); ?></a>
                    <span class="pill pill--red"><?php echo esc_html( (string) $task['due_display'] ); ?></span>
                    <p class="crm-task-note"><strong><?php esc_html_e( 'Task:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( (string) $task['note'] ); ?></p>
                    <?php if ( $show_assigned ) : ?><span><strong><?php echo esc_html__( 'Assigned:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( '' !== (string) $task['assigned_to'] ? (string) $task['assigned_to'] : '—' ); ?></span><?php endif; ?>
                    <p class="text-sm crm-task-last-note"><strong><?php esc_html_e( 'Latest note:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( (string) ( $task['note'] ?? __( 'No recent notes yet.', 'hello-elementor-child' ) ) ); ?></p>
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
                    <a class="btn btn--ghost btn--blue crm-task-client-btn" href="<?php echo esc_url( (string) $task['client_url'] ); ?>"><?php echo esc_html( (string) $task['client_name'] ); ?></a>
                    <span class="pill pill--outline"><?php echo esc_html( (string) $task['due_display'] ); ?></span>
                    <p class="crm-task-note"><strong><?php esc_html_e( 'Task:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( (string) $task['note'] ); ?></p>
                    <?php if ( $show_assigned ) : ?><span><strong><?php echo esc_html__( 'Assigned:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( '' !== (string) $task['assigned_to'] ? (string) $task['assigned_to'] : '—' ); ?></span><?php endif; ?>
                    <p class="text-sm crm-task-last-note"><strong><?php esc_html_e( 'Latest note:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( (string) ( $task['note'] ?? __( 'No recent notes yet.', 'hello-elementor-child' ) ) ); ?></p>
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
          <h2><?php echo esc_html( 'clients' === $clients_type_view ? __( 'Clients', 'hello-elementor-child' ) : __( 'Leads', 'hello-elementor-child' ) ); ?></h2>
          <p><?php echo esc_html__( 'Leads are those who have not invested with us. Clients are those who have invested with us or have property that they wish to sell or rent.', 'hello-elementor-child' ); ?></p>
          <p><?php echo esc_html( sprintf( __( 'Showing %1$d–%2$d of %3$d', 'hello-elementor-child' ), $from, $to, $total ) ); ?></p>
        </div>
        <div class="crm-toolbar-actions">
          <div class="crm-type-toggle" role="group" aria-label="<?php echo esc_attr__( 'Lead or client listing', 'hello-elementor-child' ); ?>">
            <a class="btn <?php echo esc_attr( 'leads' === $clients_type_view ? 'btn--solid' : 'btn--ghost' ); ?> btn--blue" href="<?php echo esc_url( add_query_arg( 'type', 'leads', home_url( '/crm/clients/' ) ) ); ?>"><?php esc_html_e( 'Leads', 'hello-elementor-child' ); ?></a>
            <a class="btn <?php echo esc_attr( 'clients' === $clients_type_view ? 'btn--solid' : 'btn--ghost' ); ?> btn--blue" href="<?php echo esc_url( add_query_arg( 'type', 'clients', home_url( '/crm/clients/' ) ) ); ?>"><?php esc_html_e( 'Clients', 'hello-elementor-child' ); ?></a>
          </div>
          <a class="btn btn--solid btn--blue" href="<?php echo esc_url( $new_lead_url ); ?>"><?php echo esc_html__( 'Create lead', 'hello-elementor-child' ); ?></a>
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
					'base'      => trailingslashit( home_url( '/crm/clients/%_%' ) ),
					'format'    => 'page/%#%/',
					'current'   => $current_page,
					'total'     => $total_pages,
					'type'      => 'list',
					'prev_text' => __( 'Previous', 'hello-elementor-child' ),
					'next_text' => __( 'Next', 'hello-elementor-child' ),
					'add_args'  => array( 'type' => $clients_type_view ),
				)
			);
			if ( is_string( $pagination ) ) {
				$pagination = str_replace( '/crm/clients/page/1/', '/crm/clients/', $pagination );
				echo wp_kses_post( $pagination );
			}
			?>
		<?php endif; ?>
    </div>
  </section>
</main>

<?php
get_footer();
