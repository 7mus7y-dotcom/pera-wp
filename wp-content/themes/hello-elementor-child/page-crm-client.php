<?php
/**
 * Front-end CRM client view.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$client_id = function_exists( 'pera_crm_client_view_get_client_id' ) ? pera_crm_client_view_get_client_id() : (int) get_query_var( 'pera_crm_client_id', 0 );
$access    = function_exists( 'pera_crm_client_view_access_state' ) ? pera_crm_client_view_access_state( $client_id ) : array( 'allowed' => false, 'message' => __( 'Access denied.', 'hello-elementor-child' ) );
$data      = ! empty( $access['allowed'] ) && function_exists( 'pera_crm_client_view_load_data' ) ? pera_crm_client_view_load_data( $client_id ) : array();

$client = $data['client'] ?? null;
if ( ! ( $client instanceof WP_Post ) ) {
	$access = array(
		'allowed' => false,
		'message' => __( 'Client not found.', 'hello-elementor-child' ),
	);
}

$profile         = is_array( $data['profile'] ?? null ) ? $data['profile'] : array();
$party           = is_array( $data['party'] ?? null ) ? $data['party'] : array();
$health          = is_array( $data['health'] ?? null ) ? $data['health'] : array();
$assigned_id     = (int) ( $data['assigned_id'] ?? 0 );
$assigned_user   = $data['assigned_user'] ?? null;
$linked_user     = $data['linked_user'] ?? null;
$notes           = is_array( $data['notes'] ?? null ) ? $data['notes'] : array();
$reminders       = is_array( $data['reminders'] ?? null ) ? $data['reminders'] : array();
$deals           = is_array( $data['deals'] ?? null ) ? $data['deals'] : array();
$property_groups = is_array( $data['property_groups'] ?? null ) ? $data['property_groups'] : array();
$timeline_items  = is_array( $data['timeline'] ?? null ) ? $data['timeline'] : array();
$timeline_filter = sanitize_key( (string) ( $data['timeline_filter'] ?? 'all' ) );

$open_reminders    = (int) ( $data['open_reminders'] ?? 0 );
$overdue_reminders = (int) ( $data['overdue_reminders'] ?? 0 );
$property_total    = (int) ( $data['property_total'] ?? 0 );
$deals_count       = count( $deals );
$last_activity     = (string) ( $data['last_activity'] ?? '—' );

$health_label = isset( $health['label'] ) ? (string) $health['label'] : __( 'None', 'hello-elementor-child' );
$status_label = isset( $profile['status'] ) && '' !== (string) $profile['status'] ? ucfirst( str_replace( '_', ' ', (string) $profile['status'] ) ) : __( '—', 'hello-elementor-child' );
$advisor_label = $assigned_user instanceof WP_User ? (string) $assigned_user->display_name : __( 'Unassigned', 'hello-elementor-child' );
$link_label    = $linked_user instanceof WP_User ? sprintf( __( 'Linked (%s)', 'hello-elementor-child' ), (string) $linked_user->user_login ) : __( 'Not linked', 'hello-elementor-child' );

$party_stages       = function_exists( 'peracrm_party_stage_options' ) ? (array) peracrm_party_stage_options() : array();
$engagement_options = function_exists( 'peracrm_party_engagement_options' ) ? (array) peracrm_party_engagement_options() : array();
$disposition_opts   = function_exists( 'peracrm_party_disposition_options' ) ? (array) peracrm_party_disposition_options() : array();
$staff_users        = function_exists( 'peracrm_get_staff_users' ) ? (array) peracrm_get_staff_users() : array();
$deal_stage_options = function_exists( 'peracrm_deal_stage_options' ) ? (array) peracrm_deal_stage_options() : array();
$source_pills       = function_exists( 'pera_crm_client_view_source_pills' ) ? (array) pera_crm_client_view_source_pills( $client_id, $data['activity'] ?? array() ) : array();

$frontend_url = function_exists( 'pera_crm_client_view_url' ) ? pera_crm_client_view_url( $client_id ) : home_url( '/crm/client/' . $client_id . '/' );
$notice_key   = isset( $_GET['peracrm_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['peracrm_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$notice_data  = function_exists( 'pera_crm_client_view_notice_message' ) ? pera_crm_client_view_notice_message( $notice_key ) : array( '', '' );

$derived_type      = sanitize_key( (string) ( $data['derived_type'] ?? 'lead' ) );
$derived_type      = in_array( $derived_type, array( 'lead', 'client' ), true ) ? $derived_type : 'lead';
$derived_type_label = 'client' === $derived_type ? __( 'Client', 'hello-elementor-child' ) : __( 'Lead', 'hello-elementor-child' );
$client_type_options = is_array( $data['client_type_options'] ?? null ) ? $data['client_type_options'] : array( 'seller' => 'Seller', 'landlord' => 'Landlord' );
$client_type_value = sanitize_key( (string) ( $profile['client_type'] ?? '' ) );

$profile_phone_raw   = trim( (string) ( $profile['phone'] ?? '' ) );
$profile_phone_digits = preg_replace( '/\D+/', '', $profile_phone_raw );
$profile_phone_digits = is_string( $profile_phone_digits ) ? $profile_phone_digits : '';

$profile_email = sanitize_email( (string) ( $profile['email'] ?? '' ) );
if ( '' !== $profile_email && ! is_email( $profile_email ) ) {
	$profile_email = '';
}

$call_link     = '' !== $profile_phone_digits ? 'tel:' . $profile_phone_digits : '';
$whatsapp_link = '' !== $profile_phone_digits ? 'https://wa.me/' . $profile_phone_digits : '';
$email_link    = '' !== $profile_email ? 'mailto:' . rawurlencode( $profile_email ) : '';

$can_delete_deals = current_user_can( 'manage_options' ) || current_user_can( 'view_crm_reports' );

$deal_edit_id   = isset( $_GET['deal_id'] ) ? absint( wp_unslash( (string) $_GET['deal_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$editing_deal   = null;
$deal_form_mode = 'create';
if ( $deal_edit_id > 0 ) {
	foreach ( $deals as $deal_row ) {
		if ( (int) ( $deal_row['id'] ?? 0 ) === $deal_edit_id ) {
			$editing_deal   = $deal_row;
			$deal_form_mode = 'update';
			break;
		}
	}
}

get_header();
?>

<main id="primary" class="site-main crm-page crm-page--client-view">
  <?php
  get_template_part(
	  'parts/crm-header',
	  null,
	  array(
		  'title' => $client instanceof WP_Post ? get_the_title( $client ) : __( 'Client View', 'hello-elementor-child' ),
	  )
  );
  ?>

  <section class="content-panel content-panel--overlap-hero">
    <div class="content-panel-box border-dm">
	  <?php if ( empty( $access['allowed'] ) ) : ?>
        <article class="card-shell">
          <p class="pill pill--outline"><?php echo esc_html__( 'Access denied', 'hello-elementor-child' ); ?></p>
          <p><?php echo esc_html( (string) ( $access['message'] ?? __( 'You do not have access to this client.', 'hello-elementor-child' ) ) ); ?></p>
        </article>
	  <?php else : ?>
		<?php if ( ! empty( $notice_data[0] ) && ! empty( $notice_data[1] ) ) : ?>
          <article class="card-shell crm-client-notice crm-client-notice--<?php echo esc_attr( (string) $notice_data[0] ); ?>">
            <p><?php echo esc_html( (string) $notice_data[1] ); ?></p>
          </article>
		<?php endif; ?>

        <div class="card-shell crm-client-header-strip">
          <h2><?php echo esc_html( get_the_title( $client ) ); ?></h2>
          <div class="hero-pills">
            <span class="pill pill--outline"><?php echo esc_html( sprintf( __( 'Status: %s', 'hello-elementor-child' ), $status_label ) ); ?></span>
            <span class="pill pill--outline"><?php echo esc_html( sprintf( __( 'Health: %s', 'hello-elementor-child' ), $health_label ) ); ?></span>
            <span class="pill pill--outline"><?php echo esc_html( sprintf( __( 'Advisor: %s', 'hello-elementor-child' ), $advisor_label ) ); ?></span>
            <span class="pill pill--outline"><?php echo esc_html( sprintf( __( 'Derived type: %s', 'hello-elementor-child' ), $derived_type_label ) ); ?></span>
            <?php if ( '' !== $client_type_value ) : ?>
            <span class="pill pill--outline"><?php echo esc_html( sprintf( __( 'Client type: %s', 'hello-elementor-child' ), ucfirst( str_replace( '_', ' ', $client_type_value ) ) ) ); ?></span>
            <?php endif; ?>
            <span class="pill pill--outline"><?php echo esc_html( $link_label ); ?></span>
          </div>
          <?php if ( ! empty( $source_pills ) ) : ?>
          <div class="crm-source-row">
            <span class="crm-source-label"><?php esc_html_e( 'Source', 'hello-elementor-child' ); ?></span>
            <div class="hero-pills">
              <?php foreach ( $source_pills as $source_pill ) : ?>
                <?php if ( '' === (string) $source_pill ) { continue; } ?>
                <span class="pill pill--outline"><?php echo esc_html( (string) $source_pill ); ?></span>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <div class="crm-client-kpis cards-slider cards-slider--snap cards-slider--grid-lg">
          <article class="card-shell slider-card crm-client-kpi-card"><p class="pill pill--outline"><?php esc_html_e( 'Open reminders', 'hello-elementor-child' ); ?></p><h3><?php echo esc_html( (string) $open_reminders ); ?></h3></article>
          <article class="card-shell slider-card crm-client-kpi-card"><p class="pill pill--outline"><?php esc_html_e( 'Overdue reminders', 'hello-elementor-child' ); ?></p><h3><?php echo esc_html( (string) $overdue_reminders ); ?></h3></article>
          <article class="card-shell slider-card crm-client-kpi-card"><p class="pill pill--outline"><?php esc_html_e( 'Linked properties', 'hello-elementor-child' ); ?></p><h3><?php echo esc_html( (string) $property_total ); ?></h3></article>
          <article class="card-shell slider-card crm-client-kpi-card"><p class="pill pill--outline"><?php esc_html_e( 'Deals', 'hello-elementor-child' ); ?></p><h3><?php echo esc_html( (string) $deals_count ); ?></h3></article>
          <article class="card-shell slider-card crm-client-kpi-card"><p class="pill pill--outline"><?php esc_html_e( 'Last activity', 'hello-elementor-child' ); ?></p><h3><?php echo esc_html( $last_activity ); ?></h3></article>
        </div>

        <div class="crm-client-panels-grid">
          <article class="card-shell crm-client-section">
            <h3><?php esc_html_e( 'Client Profile', 'hello-elementor-child' ); ?></h3>
            <?php if ( '' !== $call_link || '' !== $whatsapp_link || '' !== $email_link ) : ?>
            <div class="crm-client-quick-actions">
              <?php if ( '' !== $call_link ) : ?>
              <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( $call_link ); ?>"><?php esc_html_e( 'Call', 'hello-elementor-child' ); ?></a>
              <?php endif; ?>
              <?php if ( '' !== $whatsapp_link ) : ?>
              <a class="btn btn--ghost btn--green" href="<?php echo esc_url( $whatsapp_link ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'WhatsApp', 'hello-elementor-child' ); ?></a>
              <?php endif; ?>
              <?php if ( '' !== $email_link ) : ?>
              <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( $email_link ); ?>"><?php esc_html_e( 'Email', 'hello-elementor-child' ); ?></a>
              <?php endif; ?>
            </div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>" class="crm-form-stack">
					<?php wp_nonce_field( 'peracrm_save_client_profile', 'peracrm_save_client_profile_nonce' ); ?>
              <input type="hidden" name="action" value="peracrm_save_client_profile" />
              <input type="hidden" name="peracrm_client_id" value="<?php echo esc_attr( (string) $client_id ); ?>" />
              <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $frontend_url ); ?>" />
              <label><?php esc_html_e( 'Status', 'hello-elementor-child' ); ?><input type="text" name="peracrm_status" value="<?php echo esc_attr( (string) ( $profile['status'] ?? '' ) ); ?>" /></label>
              <label><?php esc_html_e( 'Client type', 'hello-elementor-child' ); ?>
                <select name="peracrm_client_type">
                  <option value=""><?php esc_html_e( 'Select type', 'hello-elementor-child' ); ?></option>
                  <?php foreach ( $client_type_options as $type_key => $type_label ) : ?>
                    <option value="<?php echo esc_attr( (string) $type_key ); ?>" <?php selected( $client_type_value, (string) $type_key ); ?>><?php echo esc_html( (string) $type_label ); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label><?php esc_html_e( 'Preferred contact', 'hello-elementor-child' ); ?><input type="text" name="peracrm_preferred_contact" value="<?php echo esc_attr( (string) ( $profile['preferred_contact'] ?? '' ) ); ?>" /></label>
              <label><?php esc_html_e( 'Budget min (USD)', 'hello-elementor-child' ); ?><input type="number" min="0" name="peracrm_budget_min_usd" value="<?php echo esc_attr( (string) ( $profile['budget_min_usd'] ?? '' ) ); ?>" /></label>
              <label><?php esc_html_e( 'Budget max (USD)', 'hello-elementor-child' ); ?><input type="number" min="0" name="peracrm_budget_max_usd" value="<?php echo esc_attr( (string) ( $profile['budget_max_usd'] ?? '' ) ); ?>" /></label>
              <label><?php esc_html_e( 'Phone', 'hello-elementor-child' ); ?><input type="text" name="peracrm_phone" value="<?php echo esc_attr( (string) ( $profile['phone'] ?? '' ) ); ?>" /></label>
              <label><?php esc_html_e( 'Email', 'hello-elementor-child' ); ?><input type="email" name="peracrm_email" value="<?php echo esc_attr( (string) ( $profile['email'] ?? '' ) ); ?>" /></label>
              <button type="submit" class="btn btn--solid btn--blue"><?php esc_html_e( 'Save profile', 'hello-elementor-child' ); ?></button>
            </form>
          </article>

          <article class="card-shell crm-client-section">
            <h3><?php esc_html_e( 'CRM Status', 'hello-elementor-child' ); ?></h3>
            <form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>" class="crm-form-stack">
					<?php wp_nonce_field( 'peracrm_save_party_status' ); ?>
              <input type="hidden" name="action" value="peracrm_save_party_status" />
              <input type="hidden" name="peracrm_client_id" value="<?php echo esc_attr( (string) $client_id ); ?>" />
              <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $frontend_url ); ?>" />
              <label>
						<?php esc_html_e( 'Lead pipeline stage', 'hello-elementor-child' ); ?>
                <select name="lead_pipeline_stage">
							<?php foreach ( $party_stages as $value => $label ) : ?>
                    <option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( (string) ( $party['lead_pipeline_stage'] ?? '' ), (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
							<?php endforeach; ?>
                </select>
              </label>
              <label>
						<?php esc_html_e( 'Engagement', 'hello-elementor-child' ); ?>
                <select name="engagement_state">
							<?php foreach ( $engagement_options as $value => $label ) : ?>
                    <option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( (string) ( $party['engagement_state'] ?? '' ), (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
							<?php endforeach; ?>
                </select>
              </label>
              <p class="text-sm"><strong><?php esc_html_e( 'Derived type:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( $derived_type_label ); ?></p>
              <label>
                <?php esc_html_e( 'Client type', 'hello-elementor-child' ); ?>
                <select name="peracrm_client_type">
                  <option value=""><?php esc_html_e( 'Select type', 'hello-elementor-child' ); ?></option>
                  <?php foreach ( $client_type_options as $type_key => $type_label ) : ?>
                    <option value="<?php echo esc_attr( (string) $type_key ); ?>" <?php selected( $client_type_value, (string) $type_key ); ?>><?php echo esc_html( (string) $type_label ); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>
						<?php esc_html_e( 'Disposition', 'hello-elementor-child' ); ?>
                <select name="disposition">
							<?php foreach ( $disposition_opts as $value => $label ) : ?>
                    <option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( (string) ( $party['disposition'] ?? '' ), (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
							<?php endforeach; ?>
                </select>
              </label>
              <button type="submit" class="btn btn--solid btn--blue"><?php esc_html_e( 'Save status', 'hello-elementor-child' ); ?></button>
            </form>
            <?php if ( 'lead' === $derived_type ) : ?>
            <form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>" class="crm-inline-form">
              <?php wp_nonce_field( 'peracrm_convert_to_client', 'peracrm_convert_to_client_nonce' ); ?>
              <input type="hidden" name="action" value="peracrm_convert_to_client" />
              <input type="hidden" name="peracrm_client_id" value="<?php echo esc_attr( (string) $client_id ); ?>" />
              <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $frontend_url ); ?>" />
              <button type="submit" class="btn btn--ghost btn--blue"><?php esc_html_e( 'Convert to client', 'hello-elementor-child' ); ?></button>
            </form>
            <?php endif; ?>
          </article>

          <article class="card-shell crm-client-section">
            <h3><?php esc_html_e( 'Notes', 'hello-elementor-child' ); ?></h3>
            <form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>" class="crm-form-stack">
					<?php wp_nonce_field( 'peracrm_add_note', 'peracrm_add_note_nonce' ); ?>
              <input type="hidden" name="action" value="peracrm_add_note" />
              <input type="hidden" name="peracrm_client_id" value="<?php echo esc_attr( (string) $client_id ); ?>" />
              <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $frontend_url ); ?>" />
              <label><?php esc_html_e( 'Add note', 'hello-elementor-child' ); ?><textarea name="peracrm_note_body" required></textarea></label>
              <button type="submit" class="btn btn--solid btn--blue"><?php esc_html_e( 'Add note', 'hello-elementor-child' ); ?></button>
            </form>
            <ul class="crm-list">
					<?php foreach ( $notes as $note ) : ?>
                <li>
                  <strong><?php echo esc_html( (string) ( $note['created_at'] ?? '' ) ); ?></strong>
                  <span><?php echo esc_html( (string) ( $note['note_body'] ?? '' ) ); ?></span>
                </li>
					<?php endforeach; ?>
            </ul>
          </article>

          <article class="card-shell crm-client-section">
            <h3><?php esc_html_e( 'Reminders', 'hello-elementor-child' ); ?></h3>
            <form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>" class="crm-form-stack">
					<?php wp_nonce_field( 'peracrm_add_reminder', 'peracrm_add_reminder_nonce' ); ?>
              <input type="hidden" name="action" value="peracrm_add_reminder" />
              <input type="hidden" name="peracrm_client_id" value="<?php echo esc_attr( (string) $client_id ); ?>" />
              <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $frontend_url ); ?>" />
              <label><?php esc_html_e( 'Due at', 'hello-elementor-child' ); ?><input type="datetime-local" name="peracrm_due_at" required /></label>
              <label><?php esc_html_e( 'Note', 'hello-elementor-child' ); ?><textarea name="peracrm_reminder_note"></textarea></label>
              <button type="submit" class="btn btn--solid btn--blue"><?php esc_html_e( 'Add reminder', 'hello-elementor-child' ); ?></button>
            </form>
            <ul class="crm-list">
					<?php foreach ( $reminders as $reminder ) : ?>
						<?php if ( 'pending' !== (string) ( $reminder['status'] ?? '' ) ) { continue; } ?>
                <li>
                  <strong><?php echo esc_html( (string) ( $reminder['due_at'] ?? '' ) ); ?></strong>
                  <span><?php echo esc_html( (string) ( $reminder['note'] ?? '' ) ); ?></span>
                  <form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'peracrm_update_reminder_status', 'peracrm_update_reminder_status_nonce' ); ?>
                    <input type="hidden" name="action" value="peracrm_update_reminder_status" />
                    <input type="hidden" name="peracrm_context" value="frontend" />
                    <input type="hidden" name="peracrm_reminder_id" value="<?php echo esc_attr( (string) ( (int) ( $reminder['id'] ?? 0 ) ) ); ?>" />
                    <input type="hidden" name="peracrm_status" value="done" />
                    <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $frontend_url ); ?>" />
                    <button type="submit" class="btn btn--ghost btn--blue"><?php esc_html_e( 'Mark done', 'hello-elementor-child' ); ?></button>
                  </form>
                </li>
					<?php endforeach; ?>
            </ul>
          </article>

          <section class="card-shell crm-client-section crm-client-timeline">
            <h3><?php esc_html_e( 'Timeline', 'hello-elementor-child' ); ?></h3>
            <div class="hero-pills">
					<?php foreach ( array( 'all' => 'All', 'activity' => 'Activity', 'notes' => 'Notes', 'reminders' => 'Reminders' ) as $key => $label ) : ?>
						<?php $url = add_query_arg( 'peracrm_timeline', $key, $frontend_url ); ?>
						<a class="pill <?php echo esc_attr( $timeline_filter === $key ? 'pill--brand' : 'pill--outline' ); ?>" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
            </div>
            <ul class="crm-list">
					<?php if ( empty( $timeline_items ) ) : ?>
                <li><?php esc_html_e( 'No timeline items yet.', 'hello-elementor-child' ); ?></li>
					<?php else : ?>
						<?php foreach ( $timeline_items as $item ) : ?>
                  <li>
                    <span class="pill pill--outline"><?php echo esc_html( (string) ( $item['type'] ?? '' ) ); ?></span>
                    <strong><?php echo esc_html( (string) ( $item['title'] ?? '' ) ); ?></strong>
							<?php if ( ! empty( $item['detail'] ) ) : ?>
                      <span><?php echo esc_html( (string) $item['detail'] ); ?></span>
							<?php endif; ?>
                  </li>
						<?php endforeach; ?>
					<?php endif; ?>
            </ul>
          </section>

          <article class="card-shell crm-client-section">
            <h3><?php esc_html_e( 'Linked Properties', 'hello-elementor-child' ); ?></h3>
            <form method="post" class="crm-form-stack">
					<?php wp_nonce_field( 'pera_crm_property_action', 'pera_crm_property_nonce' ); ?>
              <input type="hidden" name="pera_crm_property_action" value="link" />
              <input type="hidden" name="peracrm_client_id" value="<?php echo esc_attr( (string) $client_id ); ?>" />
              <div class="crm-property-search" data-crm-property-search>
                <label><?php esc_html_e( 'Project', 'hello-elementor-child' ); ?><input type="text" data-crm-property-query placeholder="<?php echo esc_attr__( 'Search project name', 'hello-elementor-child' ); ?>" autocomplete="off" /></label>
                <input type="hidden" name="property_id" data-crm-property-id required />
                <div class="crm-property-search-results" data-crm-property-results hidden></div>
                <p class="text-sm" data-crm-property-feedback><?php esc_html_e( 'Type at least 2 letters and choose a project.', 'hello-elementor-child' ); ?></p>
              </div>
              <label><?php esc_html_e( 'Relation type', 'hello-elementor-child' ); ?>
                <select name="relation_type">
                  <option value="favourite">Favourite</option>
                  <option value="enquiry">Enquiry</option>
                  <option value="viewed">Viewed</option>
                  <option value="portfolio">Portfolio</option>
                </select>
              </label>
              <button type="submit" class="btn btn--ghost btn--blue"><?php esc_html_e( 'Link property', 'hello-elementor-child' ); ?></button>
            </form>
				<?php foreach ( $property_groups as $relation => $items ) : ?>
              <h4><?php echo esc_html( ucfirst( (string) $relation ) ); ?></h4>
					<?php if ( empty( $items ) ) : ?>
                <p><?php esc_html_e( 'No properties.', 'hello-elementor-child' ); ?></p>
					<?php else : ?>
                <ul class="crm-list">
						<?php foreach ( $items as $item ) : ?>
							<?php
							$property_id = (int) ( $item['property_id'] ?? 0 );
							$property_label = '';
							$property_url = '';
							if ( $property_id > 0 ) {
								$property_label = pera_crm_client_view_with_target_blog(
									static function () use ( $property_id ): string {
										return function_exists( 'pera_crm_client_view_property_project_name' ) ? (string) pera_crm_client_view_property_project_name( $property_id ) : (string) get_the_title( $property_id );
									}
								);
								$property_url = pera_crm_client_view_with_target_blog(
									static function () use ( $property_id ): string {
										return (string) get_permalink( $property_id );
									}
								);
							}
							?>
                    <li>
								<?php if ( '' !== $property_url ) : ?>
                        <a class="crm-linked-property-link" href="<?php echo esc_url( $property_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $property_label ); ?></a>
								<?php else : ?>
                        <span><?php echo esc_html( $property_label ); ?></span>
								<?php endif; ?>
                      <form method="post">
									<?php wp_nonce_field( 'pera_crm_property_action', 'pera_crm_property_nonce' ); ?>
                        <input type="hidden" name="pera_crm_property_action" value="unlink" />
                        <input type="hidden" name="peracrm_client_id" value="<?php echo esc_attr( (string) $client_id ); ?>" />
                        <input type="hidden" name="property_id" value="<?php echo esc_attr( (string) $property_id ); ?>" />
                        <input type="hidden" name="relation_type" value="<?php echo esc_attr( (string) $relation ); ?>" />
                        <button type="submit" class="btn btn--ghost btn--blue"><?php esc_html_e( 'Unlink', 'hello-elementor-child' ); ?></button>
                      </form>
                    </li>
						<?php endforeach; ?>
                </ul>
					<?php endif; ?>
				<?php endforeach; ?>
          </article>

          <article class="card-shell crm-client-section crm-client-panel--full">
            <h3><?php esc_html_e( 'Deals', 'hello-elementor-child' ); ?></h3>
            <ul class="crm-list crm-deals-list">
					<?php foreach ( $deals as $deal ) : ?>
						<?php
						$deal_id = (int) ( $deal['id'] ?? 0 );
						$deal_title = (string) ( $deal['title'] ?? '' );
						$deal_stage = (string) ( $deal_stage_options[ $deal['stage'] ?? '' ] ?? ( $deal['stage'] ?? '' ) );
						$deal_property_id = (int) ( $deal['primary_property_id'] ?? 0 );
						$deal_currency = strtoupper( sanitize_text_field( (string) ( $deal['currency'] ?? 'USD' ) ) );
						$deal_value_raw = $deal['deal_value'] ?? '';
						$deal_value = is_numeric( $deal_value_raw ) ? number_format_i18n( (float) $deal_value_raw, 0 ) : '';
						?>
                <li>
                  <strong><?php echo esc_html( $deal_title ); ?></strong>
                  <span><?php echo esc_html( sprintf( __( 'Stage: %s', 'hello-elementor-child' ), $deal_stage ) ); ?></span>
                  <span><?php echo esc_html( sprintf( __( 'Property ID: %d', 'hello-elementor-child' ), $deal_property_id ) ); ?></span>
                  <span><?php echo esc_html( sprintf( __( 'Value: %1$s %2$s', 'hello-elementor-child' ), '' !== $deal_value ? $deal_value : '—', $deal_currency ) ); ?></span>
                  <div class="crm-inline-form">
                    <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( add_query_arg( 'deal_id', $deal_id, $frontend_url ) ); ?>"><?php esc_html_e( 'Edit', 'hello-elementor-child' ); ?></a>
                    <?php if ( $can_delete_deals ) : ?>
                    <form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>" onsubmit="return window.confirm('<?php echo esc_js( __( 'Delete this deal?', 'hello-elementor-child' ) ); ?>');">
                      <input type="hidden" name="action" value="peracrm_delete_deal" />
                      <input type="hidden" name="peracrm_deal_nonce" value="<?php echo esc_attr( wp_create_nonce( 'peracrm_delete_deal' ) ); ?>" />
                      <input type="hidden" name="peracrm_deal_submit" value="1" />
                      <input type="hidden" name="peracrm_client_id" value="<?php echo esc_attr( (string) $client_id ); ?>" />
                      <input type="hidden" name="deal_id" value="<?php echo esc_attr( (string) $deal_id ); ?>" />
                      <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $frontend_url ); ?>" />
                      <button type="submit" class="btn btn--ghost btn--red"><?php esc_html_e( 'Delete', 'hello-elementor-child' ); ?></button>
                    </form>
                    <?php endif; ?>
                  </div>
                </li>
					<?php endforeach; ?>
            </ul>
            <form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>" class="crm-form-stack">
              <input type="hidden" name="action" value="<?php echo esc_attr( 'update' === $deal_form_mode ? 'peracrm_update_deal' : 'peracrm_create_deal' ); ?>" />
              <input type="hidden" name="peracrm_deal_nonce" value="<?php echo esc_attr( wp_create_nonce( 'update' === $deal_form_mode ? 'peracrm_update_deal' : 'peracrm_create_deal' ) ); ?>" />
              <input type="hidden" name="peracrm_deal_submit" value="1" />
              <input type="hidden" name="peracrm_client_id" value="<?php echo esc_attr( (string) $client_id ); ?>" />
              <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $frontend_url ); ?>" />
					<?php if ( 'update' === $deal_form_mode && is_array( $editing_deal ) ) : ?>
                <input type="hidden" name="deal_id" value="<?php echo esc_attr( (string) ( (int) ( $editing_deal['id'] ?? 0 ) ) ); ?>" />
					<?php endif; ?>
              <label><?php esc_html_e( 'Title', 'hello-elementor-child' ); ?><input type="text" name="title" value="<?php echo esc_attr( (string) ( $editing_deal['title'] ?? '' ) ); ?>" required /></label>
              <label><?php esc_html_e( 'Stage', 'hello-elementor-child' ); ?>
                <select name="stage">
						<?php foreach ( $deal_stage_options as $value => $label ) : ?>
                    <option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( (string) ( $editing_deal['stage'] ?? 'reservation_taken' ), (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
						<?php endforeach; ?>
                </select>
              </label>
              <label><?php esc_html_e( 'Primary property ID', 'hello-elementor-child' ); ?><input type="number" min="0" name="primary_property_id" value="<?php echo esc_attr( (string) ( $editing_deal['primary_property_id'] ?? '' ) ); ?>" /></label>
              <label><?php esc_html_e( 'Deal value', 'hello-elementor-child' ); ?><input type="number" step="0.01" min="0" name="deal_value" value="<?php echo esc_attr( (string) ( $editing_deal['deal_value'] ?? '' ) ); ?>" /></label>
              <label><?php esc_html_e( 'Currency', 'hello-elementor-child' ); ?><input type="text" maxlength="3" name="currency" value="<?php echo esc_attr( (string) ( $editing_deal['currency'] ?? 'USD' ) ); ?>" /></label>
              <button type="submit" class="btn btn--solid btn--blue"><?php echo esc_html( 'update' === $deal_form_mode ? __( 'Update deal', 'hello-elementor-child' ) : __( 'Create deal', 'hello-elementor-child' ) ); ?></button>
            </form>
          </article>

          <article class="card-shell crm-client-section crm-client-panel--full">
            <h3><?php esc_html_e( 'Assigned Advisor', 'hello-elementor-child' ); ?></h3>
            <form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>" class="crm-form-stack">
					<?php wp_nonce_field( 'peracrm_reassign_client_advisor', 'peracrm_reassign_client_advisor_nonce' ); ?>
              <input type="hidden" name="action" value="peracrm_reassign_client_advisor" />
              <input type="hidden" name="peracrm_client_id" value="<?php echo esc_attr( (string) $client_id ); ?>" />
              <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $frontend_url ); ?>" />
              <select name="peracrm_assigned_advisor">
                <option value="0"><?php esc_html_e( 'Unassigned', 'hello-elementor-child' ); ?></option>
						<?php foreach ( $staff_users as $staff_user ) : ?>
                  <option value="<?php echo esc_attr( (string) $staff_user->ID ); ?>" <?php selected( $assigned_id, (int) $staff_user->ID ); ?>><?php echo esc_html( (string) $staff_user->display_name ); ?></option>
						<?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn--ghost btn--blue"><?php esc_html_e( 'Reassign advisor', 'hello-elementor-child' ); ?></button>
            </form>
          </article>
        </div>

	  <?php endif; ?>
    </div>
  </section>
</main>

<?php get_footer(); ?>
