<?php
/**
 * CRM lead view template (read-only).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lead_id = (int) get_query_var( 'pera_crm_id', 0 );
$party   = function_exists( 'peracrm_party_get' ) ? peracrm_party_get( $lead_id ) : null;

if ( $lead_id <= 0 || ! is_array( $party ) ) {
	get_header();
	?>
	<main id="primary" class="site-main crm-page crm-page--view">
	  <section class="hero hero--left hero--fit" id="crm-hero">
	    <div class="hero-content container">
	      <h1><?php echo esc_html__( 'Lead View', 'hello-elementor-child' ); ?></h1>
	    </div>
	  </section>
	  <section class="content-panel content-panel--overlap-hero">
	    <div class="content-panel-box border-dm container">
	      <article class="card-shell">
	        <p class="pill pill--outline"><?php echo esc_html__( 'Notice', 'hello-elementor-child' ); ?></p>
	        <p><?php echo esc_html__( 'CRM data unavailable', 'hello-elementor-child' ); ?></p>
	      </article>
	    </div>
	  </section>
	</main>
	<?php
	get_footer();
	return;
}

$lead_name = (string) ( $party['full_name'] ?? $party['name'] ?? get_the_title( $lead_id ) );
$email     = (string) ( $party['email'] ?? get_post_meta( $lead_id, '_peracrm_email', true ) );
$phone     = (string) ( $party['phone'] ?? get_post_meta( $lead_id, '_peracrm_phone', true ) );
$source    = (string) ( $party['source'] ?? get_post_meta( $lead_id, 'crm_source', true ) );
$advisor   = (int) ( $party['assigned_advisor_user_id'] ?? get_post_meta( $lead_id, 'assigned_advisor_user_id', true ) );
$stage     = (string) ( $party['lead_pipeline_stage'] ?? 'new_enquiry' );
$notes     = (string) ( $party['notes'] ?? '' );
$advisor_name = $advisor > 0 ? (string) get_the_author_meta( 'display_name', $advisor ) : __( 'Unassigned', 'hello-elementor-child' );
$stages = function_exists( 'pera_crm_get_pipeline_stages' ) ? pera_crm_get_pipeline_stages() : array();

$activity_rows = function_exists( 'peracrm_activity_list' ) ? peracrm_activity_list( array( 'party_ids' => array( $lead_id ), 'limit' => 20 ) ) : array();
$task_rows     = function_exists( 'peracrm_reminders_list' ) ? peracrm_reminders_list( array( 'party_ids' => array( $lead_id ), 'limit' => 50 ) ) : array();
$crm_current_url = home_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/crm/' ) );

get_header();
?>

<main id="primary" class="site-main crm-page crm-page--view">
  <section class="hero hero--left hero--fit" id="crm-hero">
    <div class="hero-content container">
      <h1><?php echo esc_html( $lead_name ); ?></h1>
      <div class="hero-actions">
        <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( home_url( '/crm/' ) ); ?>"><?php echo esc_html__( 'Back to CRM', 'hello-elementor-child' ); ?></a>
      </div>
    </div>
  </section>

  <section class="content-panel content-panel--overlap-hero">
    <div class="content-panel-box border-dm container">
      <article class="card-shell">
        <p><strong><?php echo esc_html__( 'Lead full name:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( $lead_name ); ?></p>
        <p><strong><?php echo esc_html__( 'Email:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( $email ); ?></p>
        <p><strong><?php echo esc_html__( 'Phone:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( $phone ); ?></p>
        <p><strong><?php echo esc_html__( 'Source:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( $source ); ?></p>
        <p><strong><?php echo esc_html__( 'Assigned advisor:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( $advisor_name ); ?></p>
        <p><strong><?php echo esc_html__( 'Current stage:', 'hello-elementor-child' ); ?></strong> <span class="pill pill--outline"><?php echo esc_html( (string) ( $stages[ $stage ] ?? $stage ) ); ?></span></p>
        <p><strong><?php echo esc_html__( 'Notes:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( '' !== $notes ? $notes : '—' ); ?></p>
      </article>

      <article class="card-shell">
        <h2><?php echo esc_html__( 'Activity timeline', 'hello-elementor-child' ); ?></h2>
        <?php if ( empty( $activity_rows ) || ! is_array( $activity_rows ) ) : ?>
          <p><?php echo esc_html__( 'No activity available.', 'hello-elementor-child' ); ?></p>
        <?php else : ?>
        <ul class="crm-list">
          <?php foreach ( $activity_rows as $row ) : ?>
            <?php if ( ! is_array( $row ) ) { continue; } ?>
            <li>
              <span class="pill pill--outline"><?php echo esc_html( (string) ( $row['type'] ?? $row['activity_type'] ?? '' ) ); ?></span>
              <strong><?php echo esc_html( (string) ( $row['created_at'] ?? $row['time'] ?? '' ) ); ?></strong>
              <span><?php echo esc_html( wp_strip_all_tags( (string) ( $row['summary'] ?? $row['message'] ?? '' ) ) ); ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </article>

      <article class="card-shell">
        <h2><?php echo esc_html__( 'Tasks list', 'hello-elementor-child' ); ?></h2>
        <?php if ( empty( $task_rows ) || ! is_array( $task_rows ) ) : ?>
          <p><?php echo esc_html__( 'No tasks available.', 'hello-elementor-child' ); ?></p>
        <?php else : ?>
        <ul class="crm-list">
          <?php foreach ( $task_rows as $task ) : ?>
            <?php if ( ! is_array( $task ) ) { continue; } ?>
            <li>
              <span class="pill pill--outline"><?php echo esc_html( (string) ( $task['due_at'] ?? $task['due_date'] ?? '' ) ); ?></span>
              <span><?php echo esc_html( wp_strip_all_tags( (string) ( $task['note'] ?? $task['reminder_note'] ?? $task['message'] ?? '' ) ) ); ?></span>
              <?php $task_status = sanitize_key( (string) ( $task['status'] ?? 'pending' ) ); ?>
              <?php $reminder_id = absint( $task['reminder_id'] ?? $task['id'] ?? 0 ); ?>
              <?php if ( $reminder_id > 0 && 'pending' === $task_status ) : ?>
              <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="peracrm_update_reminder_status">
                <input type="hidden" name="peracrm_reminder_id" value="<?php echo esc_attr( (string) $reminder_id ); ?>">
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
        <?php endif; ?>
      </article>
    </div>
  </section>
</main>

<?php
get_footer();
