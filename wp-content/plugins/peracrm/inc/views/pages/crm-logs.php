<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( function_exists( 'pera_crm_gate_or_redirect' ) ) {
	pera_crm_gate_or_redirect();
}

$view        = sanitize_key( (string) get_query_var( 'pera_crm_view', '' ) );
$is_whatsapp = 'whatsapp_logs' === $view;
$is_email    = 'email_logs' === $view;

// Reuse existing assignment-management helper as the closest existing log visibility gate.
$can_manage_options = current_user_can( 'manage_options' );
$can_manage_crm_logs = function_exists( 'peracrm_admin_user_can_reassign' ) && peracrm_admin_user_can_reassign();
$allowed             = $can_manage_options || $can_manage_crm_logs;

$read_whatsapp_logs = static function (): array {
	if ( ! function_exists( 'pera_whatsapp_clicks_table_name' ) ) {
		return array();
	}

	global $wpdb;
	$table_name = (string) pera_whatsapp_clicks_table_name();
	$table      = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	if ( $table_name !== $table ) {
		return array();
	}

	return (array) $wpdb->get_results(
		"SELECT created_at, page_type, post_title, message_text, ip_address FROM {$table_name} ORDER BY id DESC LIMIT 100",
		ARRAY_A
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
};

$read_email_logs = static function (): array {
	if ( ! function_exists( 'pera_enquiry_email_log_table_name' ) ) {
		return array();
	}

	global $wpdb;
	$table_name = (string) pera_enquiry_email_log_table_name();
	$table      = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	if ( $table_name !== $table ) {
		return array();
	}

	return (array) $wpdb->get_results(
		"SELECT created_at, form_key, mail_context, recipient, subject, status FROM {$table_name} ORDER BY id DESC LIMIT 100",
		ARRAY_A
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
};

peracrm_frontend_render_shell_header();
?>
<main id="primary" class="site-main crm-page crm-page--logs">
<?php
if ( function_exists( 'peracrm_frontend_render_partial' ) ) {
	peracrm_frontend_render_partial(
		'crm-header',
		array(
			'title'       => $is_whatsapp ? __( 'WhatsApp Logs', 'peracrm' ) : __( 'Email Logs', 'peracrm' ),
			'description' => __( 'Operational communication logs', 'peracrm' ),
			'active_view' => $view,
		)
	);
}
?>
<section class="content-panel content-panel--overlap-hero">
  <div class="content-panel-box border-dm crm-layout">
    <div class="crm-layout__main">
      <article class="card-shell crm-client-section">
      <?php if ( ! $allowed ) : ?>
        <p><?php esc_html_e( 'You are not allowed to view this page.', 'peracrm' ); ?></p>
      <?php elseif ( $is_whatsapp ) : ?>
        <?php $rows = $read_whatsapp_logs(); ?>
        <?php if ( empty( $rows ) ) : ?>
          <p><?php esc_html_e( 'No WhatsApp logs found.', 'peracrm' ); ?></p>
        <?php else : ?>
          <table class="crm-log-table">
            <thead>
              <tr>
                <th><?php esc_html_e( 'Time', 'peracrm' ); ?></th>
                <th><?php esc_html_e( 'Type', 'peracrm' ); ?></th>
                <th><?php esc_html_e( 'Title', 'peracrm' ); ?></th>
                <th><?php esc_html_e( 'Message', 'peracrm' ); ?></th>
                <th><?php esc_html_e( 'IP', 'peracrm' ); ?></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ( (array) $rows as $row ) : ?>
              <tr>
                <td><?php echo esc_html( (string) $row['created_at'] ); ?></td>
                <td><?php echo esc_html( (string) $row['page_type'] ); ?></td>
                <td><?php echo esc_html( (string) $row['post_title'] ); ?></td>
                <td><?php echo esc_html( (string) $row['message_text'] ); ?></td>
                <td><?php echo esc_html( (string) $row['ip_address'] ); ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      <?php elseif ( $is_email ) : ?>
        <?php $rows = $read_email_logs(); ?>
        <?php if ( empty( $rows ) ) : ?>
          <p><?php esc_html_e( 'No email logs found.', 'peracrm' ); ?></p>
        <?php else : ?>
          <table class="crm-log-table">
            <thead>
              <tr>
                <th><?php esc_html_e( 'Time', 'peracrm' ); ?></th>
                <th><?php esc_html_e( 'Form', 'peracrm' ); ?></th>
                <th><?php esc_html_e( 'Context', 'peracrm' ); ?></th>
                <th><?php esc_html_e( 'Recipient', 'peracrm' ); ?></th>
                <th><?php esc_html_e( 'Subject', 'peracrm' ); ?></th>
                <th><?php esc_html_e( 'Status', 'peracrm' ); ?></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ( (array) $rows as $row ) : ?>
              <tr>
                <td><?php echo esc_html( (string) $row['created_at'] ); ?></td>
                <td><?php echo esc_html( (string) $row['form_key'] ); ?></td>
                <td><?php echo esc_html( (string) $row['mail_context'] ); ?></td>
                <td><?php echo esc_html( (string) $row['recipient'] ); ?></td>
                <td><?php echo esc_html( (string) $row['subject'] ); ?></td>
                <td><?php echo esc_html( (string) $row['status'] ); ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      <?php else : ?>
        <p><?php esc_html_e( 'Log source is unavailable in this environment.', 'peracrm' ); ?></p>
      <?php endif; ?>
      </article>
    </div>
    <?php
    if ( function_exists( 'peracrm_frontend_render_partial' ) ) {
		peracrm_frontend_render_partial( 'crm-side-nav', array( 'active_view' => $view ) );
    }
    ?>
  </div>
</section>
</main>
<?php peracrm_frontend_render_shell_footer();
