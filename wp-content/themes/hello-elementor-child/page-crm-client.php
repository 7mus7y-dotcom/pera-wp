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
$deal_stage_options = function_exists( 'peracrm_deal_stage_options' ) ? (array) peracrm_deal_stage_options() : array();
$source_pills       = function_exists( 'pera_crm_client_view_source_pills' ) ? (array) pera_crm_client_view_source_pills( $client_id, $data['activity'] ?? array() ) : array();

$can_manage_assignments = function_exists( 'peracrm_admin_user_can_reassign' ) && peracrm_admin_user_can_reassign();
$can_delete_client      = false;
$delete_cap_check       = static function () use ( $client_id ) {
	if ( $client_id <= 0 ) {
		return false;
	}

	return current_user_can( 'delete_post', $client_id );
};

if ( function_exists( 'peracrm_with_target_blog' ) ) {
	$can_delete_client = (bool) peracrm_with_target_blog( $delete_cap_check );
} else {
	$can_delete_client = (bool) $delete_cap_check();
}

if ( function_exists( 'peracrm_admin_user_can_reassign' ) ) {
	$can_delete_client = $can_delete_client && ( peracrm_admin_user_can_reassign() || current_user_can( 'manage_options' ) );
}

$can_set_dormant        = $can_manage_assignments;

$frontend_url = function_exists( 'pera_crm_client_view_url' ) ? pera_crm_client_view_url( $client_id ) : home_url( '/crm/client/' . $client_id . '/' );
$clients_url  = home_url( '/crm/clients/' );
$leads_url    = home_url( '/crm/leads/' );
$notice_key   = isset( $_GET['peracrm_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['peracrm_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$notice_data  = function_exists( 'pera_crm_client_view_notice_message' ) ? pera_crm_client_view_notice_message( $notice_key ) : array( '', '' );

$derived_type      = sanitize_key( (string) ( $data['derived_type'] ?? 'lead' ) );
$derived_type      = in_array( $derived_type, array( 'lead', 'client' ), true ) ? $derived_type : 'lead';
$derived_type_label = 'client' === $derived_type ? __( 'Client', 'hello-elementor-child' ) : __( 'Lead', 'hello-elementor-child' );
$delete_redirect_url = 'lead' === $derived_type ? $leads_url : $clients_url;
$client_type_options = is_array( $data['client_type_options'] ?? null ) ? $data['client_type_options'] : array( 'citizenship' => 'Citizenship', 'investor' => 'Investor', 'lifestyle' => 'Lifestyle', 'seller' => 'Seller', 'landlord' => 'Landlord' );
$status_options      = function_exists( 'peracrm_status_options' ) ? (array) peracrm_status_options() : array( 'enquiry' => 'Enquiry', 'active' => 'Active', 'dormant' => 'Dormant', 'closed' => 'Closed' );
$client_type_value = sanitize_key( (string) ( $profile['client_type'] ?? '' ) );

$profile_phone_value = isset( $profile['phone'] ) ? trim( (string) $profile['phone'] ) : '';
$crm_phone_country_options = array(
  '+1'   => '+1',
  '+27'  => '+27',
  '+30'  => '+30',
  '+31'  => '+31',
  '+32'  => '+32',
  '+33'  => '+33',
  '+34'  => '+34',
  '+39'  => '+39',
  '+41'  => '+41',
  '+43'  => '+43',
  '+44'  => '+44',
  '+45'  => '+45',
  '+46'  => '+46',
  '+47'  => '+47',
  '+49'  => '+49',
  '+65'  => '+65',
  '+86'  => '+86',
  '+90'  => '+90',
  '+353' => '+353',
  '+852' => '+852',
  '+880' => '+880',
  '+961' => '+961',
  '+962' => '+962',
  '+965' => '+965',
  '+966' => '+966',
  '+968' => '+968',
  '+971' => '+971',
  '+973' => '+973',
  '+974' => '+974',
);

$crm_phone_country_value  = '+90';
$crm_phone_national_value = '';
$profile_phone_trimmed = ltrim( $profile_phone_value );
if ( '' !== $profile_phone_trimmed && '+' === substr( $profile_phone_trimmed, 0, 1 ) ) {
  $digits = preg_replace( '/\D+/', '', $profile_phone_trimmed );
  $sorted_codes = array_keys( $crm_phone_country_options );
  usort(
    $sorted_codes,
    static function ( $left, $right ) {
      return strlen( (string) $right ) <=> strlen( (string) $left );
    }
  );

  foreach ( $sorted_codes as $country_code ) {
    $country_digits = preg_replace( '/\D+/', '', (string) $country_code );
    if ( '' === $country_digits ) {
      continue;
    }

    if ( strpos( $digits, $country_digits ) === 0 ) {
      $crm_phone_country_value  = (string) $country_code;
      $crm_phone_national_value = substr( $digits, strlen( $country_digits ) );
      break;
    }
  }
}

if ( '' === $crm_phone_national_value && isset( $_POST['peracrm_phone_national'] ) ) {
  $crm_phone_national_value = sanitize_text_field( wp_unslash( (string) $_POST['peracrm_phone_national'] ) );
}
if ( isset( $_POST['peracrm_phone_country'] ) ) {
  $posted_country = sanitize_text_field( wp_unslash( (string) $_POST['peracrm_phone_country'] ) );
  if ( isset( $crm_phone_country_options[ $posted_country ] ) ) {
    $crm_phone_country_value = $posted_country;
  }
}

$call_link = '' !== $profile_phone_value ? 'tel:' . rawurlencode( $profile_phone_value ) : '';
$whatsapp_link = function_exists( 'peracrm_whatsapp_url_from_phone' )
  ? peracrm_whatsapp_url_from_phone( $profile_phone_value )
  : '';
$email_value_raw = isset( $profile['email'] ) ? (string) $profile['email'] : '';
$email_value = '' !== $email_value_raw ? sanitize_email( $email_value_raw ) : '';
if ( '' !== $email_value && ! is_email( $email_value ) ) {
  $email_value = '';
}
$email_link = '' !== $email_value ? 'mailto:' . rawurlencode( $email_value ) : '';

$deal_edit_id   = isset( $_GET['deal_id'] ) ? absint( wp_unslash( (string) $_GET['deal_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$editing_deal   = null;
$deal_form_mode = 'create';

$reminder_buckets  = function_exists( 'pera_crm_client_view_bucket_reminders' )
	? (array) pera_crm_client_view_bucket_reminders( $reminders )
	: array();
$today_reminders   = is_array( $reminder_buckets['today'] ?? null ) ? $reminder_buckets['today'] : array();
$overdue_task_rows = is_array( $reminder_buckets['overdue'] ?? null ) ? $reminder_buckets['overdue'] : array();
$upcoming_rows     = is_array( $reminder_buckets['upcoming'] ?? null ) ? $reminder_buckets['upcoming'] : array();
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
			  'title' => __( 'Client View', 'hello-elementor-child' ),
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
          <article class="card-shell crm-client-section crm-client-profile-panel">
            <h3><?php esc_html_e( 'Client Profile', 'hello-elementor-child' ); ?></h3>
            <form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>" class="crm-form-stack">
						<?php wp_nonce_field( 'peracrm_save_client_profile', 'peracrm_save_client_profile_nonce' ); ?>
              <input type="hidden" name="action" value="peracrm_save_client_profile" />
              <input type="hidden" name="peracrm_client_id" value="<?php echo esc_attr( (string) $client_id ); ?>" />
              <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $frontend_url ); ?>" />
              <input type="hidden" name="form_context" value="profile" />
              <label><?php esc_html_e( 'Status', 'hello-elementor-child' ); ?>
                <select name="peracrm_status" id="peracrm-status" class="widefat">
                  <?php foreach ( $status_options as $status_key => $status_text ) : ?>
                    <option value="<?php echo esc_attr( (string) $status_key ); ?>" <?php selected( (string) ( $profile['status'] ?? '' ), (string) $status_key ); ?>><?php echo esc_html( (string) $status_text ); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <div class="crm-phone-field">
                <span><?php esc_html_e( 'Mobile / WhatsApp', 'hello-elementor-child' ); ?></span>
                <div class="crm-phone-row">
                  <select name="peracrm_phone_country" class="crm-phone-country" aria-label="Country code">
                    <?php foreach ( $crm_phone_country_options as $country_value => $country_label ) : ?>
                      <option value="<?php echo esc_attr( (string) $country_value ); ?>" <?php selected( $crm_phone_country_value, (string) $country_value ); ?>><?php echo esc_html( (string) $country_label ); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <input type="text" name="peracrm_phone_national" value="<?php echo esc_attr( (string) $crm_phone_national_value ); ?>" inputmode="tel" autocomplete="tel-national" placeholder="Phone number" aria-label="Phone number" />
                </div>
                <input type="hidden" name="peracrm_phone" value="<?php echo esc_attr( $profile_phone_value ); ?>" />
              </div>
              <label><?php esc_html_e( 'Email', 'hello-elementor-child' ); ?><input type="email" name="peracrm_email" value="<?php echo esc_attr( (string) ( $profile['email'] ?? '' ) ); ?>" /></label>
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
              <label><?php esc_html_e( 'Preferred contact', 'hello-elementor-child' ); ?><input type="text" name="peracrm_preferred_contact" value="<?php echo esc_attr( (string) ( $profile['preferred_contact'] ?? '' ) ); ?>" /></label>
              <div class="crm-form-row-2">
                <label><?php esc_html_e( 'Budget min (USD)', 'hello-elementor-child' ); ?><input type="number" min="0" name="peracrm_budget_min_usd" value="<?php echo esc_attr( (string) ( $profile['budget_min_usd'] ?? '' ) ); ?>" /></label>
                <label><?php esc_html_e( 'Budget max (USD)', 'hello-elementor-child' ); ?><input type="number" min="0" name="peracrm_budget_max_usd" value="<?php echo esc_attr( (string) ( $profile['budget_max_usd'] ?? '' ) ); ?>" /></label>
              </div>
              <label><?php esc_html_e( 'Bedrooms', 'hello-elementor-child' ); ?><input type="number" min="0" step="1" name="peracrm_bedrooms" value="<?php echo esc_attr( (string) ( $profile['bedrooms'] ?? '' ) ); ?>" /></label>
              <button type="submit" class="btn btn--solid btn--blue"><?php esc_html_e( 'Save profile', 'hello-elementor-child' ); ?></button>
            </form>
          </article>

          <article class="card-shell crm-client-section crm-status-panel">
            <h3><?php esc_html_e( 'CRM Status', 'hello-elementor-child' ); ?></h3>
            <span class="crm-derived-badge crm-derived-badge--<?php echo esc_attr( $derived_type ); ?>"><?php echo esc_html( $derived_type_label ); ?></span>
            <form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>" class="crm-form-stack crm-status-form" id="crm-status-form">
					<?php wp_nonce_field( 'peracrm_save_party_status' ); ?>
              <input type="hidden" name="action" value="peracrm_save_party_status" />
              <input type="hidden" name="peracrm_client_id" value="<?php echo esc_attr( (string) $client_id ); ?>" />
              <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $frontend_url ); ?>" />
              <input type="hidden" name="form_context" value="status" />
              <div class="crm-status-grid">
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
              </div>
              </form>
              <div class="crm-status-actions">
                <button type="submit" form="crm-status-form" class="btn btn--solid btn--blue"><?php esc_html_e( 'Save status', 'hello-elementor-child' ); ?></button>
                <?php if ( 'lead' === $derived_type ) : ?>
                <form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>" class="crm-inline-form">
                  <?php wp_nonce_field( 'peracrm_convert_to_client', 'peracrm_convert_to_client_nonce' ); ?>
                  <input type="hidden" name="action" value="peracrm_convert_to_client" />
                  <input type="hidden" name="peracrm_client_id" value="<?php echo esc_attr( (string) $client_id ); ?>" />
                  <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $frontend_url ); ?>" />
                  <button type="submit" class="btn btn--ghost btn--blue"><?php esc_html_e( 'Convert to client', 'hello-elementor-child' ); ?></button>
                </form>
                <?php endif; ?>
              </div>
            </article>

          <article class="card-shell crm-client-section">
            <h3><?php esc_html_e( 'Advisor Notes', 'hello-elementor-child' ); ?></h3>
            <?php if ( empty( $notes ) ) : ?>
              <p><?php esc_html_e( 'No notes yet.', 'hello-elementor-child' ); ?></p>
            <?php else : ?>
              <div class="archive-hero-desc crm-client-notes-truncate" data-collapsed="true">
                <div id="crm-client-notes-content" class="archive-hero-desc__content">
                  <ul class="crm-list crm-client-notes-list">
                    <?php foreach ( $notes as $note ) : ?>
                      <?php
                      $note_author      = isset( $note['advisor_user_id'] ) ? get_userdata( (int) $note['advisor_user_id'] ) : false;
                      $note_author_name = $note_author instanceof WP_User ? $note_author->display_name : __( 'Advisor', 'hello-elementor-child' );
                      $note_created_at  = isset( $note['created_at'] ) ? (string) $note['created_at'] : '';
                      $note_created_at  = '' !== $note_created_at ? mysql2date( 'Y-m-d H:i', $note_created_at ) : __( 'Unknown time', 'hello-elementor-child' );
                      ?>
                      <li>
                        <span class="pill pill--outline"><?php echo esc_html( (string) $note_created_at ); ?></span>
                        <p class="crm-client-notes-list__meta"><?php echo esc_html( (string) $note_author_name ); ?></p>
                        <p><?php echo esc_html( (string) ( $note['note_body'] ?? '' ) ); ?></p>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                  <button type="button" class="pill pill--green archive-hero-desc__toggle archive-hero-desc__toggle--bottom" aria-expanded="false" aria-controls="crm-client-notes-content" data-label-more="<?php echo esc_attr__( 'See more', 'hello-elementor-child' ); ?>" data-label-less="<?php echo esc_attr__( 'See less', 'hello-elementor-child' ); ?>" hidden><?php esc_html_e( 'See more', 'hello-elementor-child' ); ?></button>
                </div>
                <button type="button" class="pill pill--green archive-hero-desc__toggle archive-hero-desc__toggle--top" aria-expanded="false" aria-controls="crm-client-notes-content" data-label-more="<?php echo esc_attr__( 'See more', 'hello-elementor-child' ); ?>" data-label-less="<?php echo esc_attr__( 'See less', 'hello-elementor-child' ); ?>" hidden><?php esc_html_e( 'See more', 'hello-elementor-child' ); ?></button>
              </div>
            <?php endif; ?>

            <div class="crm-add-note">
              <form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>" class="crm-form-stack">
              <?php wp_nonce_field( 'peracrm_add_note', 'peracrm_add_note_nonce' ); ?>
              <input type="hidden" name="action" value="peracrm_add_note" />
              <input type="hidden" name="peracrm_client_id" value="<?php echo esc_attr( (string) $client_id ); ?>" />
              <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $frontend_url ); ?>" />
              <label for="peracrm_note_body_frontend"><?php esc_html_e( 'Add note', 'hello-elementor-child' ); ?></label>
              <textarea name="peracrm_note_body" id="peracrm_note_body_frontend" rows="4"></textarea>
              <button type="submit" class="btn btn--solid btn--blue"><?php esc_html_e( 'Add note', 'hello-elementor-child' ); ?></button>
              </form>
            </div>
          </article>

        <article class="card-shell crm-client-section crm-client-reminders">
          <header class="section-header">
            <h3><?php esc_html_e( 'Tasks / Reminders', 'hello-elementor-child' ); ?></h3>
          </header>
          <div class="crm-client-reminders-grid">
            <section>
              <p class="pill pill--outline"><?php echo esc_html( sprintf( __( 'Today (%d)', 'hello-elementor-child' ), count( $today_reminders ) ) ); ?></p>
              <ul class="crm-list">
                <?php if ( empty( $today_reminders ) ) : ?>
                <li><?php esc_html_e( 'No reminders due today.', 'hello-elementor-child' ); ?></li>
                <?php else : ?>
                  <?php foreach ( $today_reminders as $reminder_row ) : ?>
                  <li>
                    <span class="pill pill--outline"><?php echo esc_html( (string) ( $reminder_row['due_display'] ?? '' ) ); ?></span>
                    <p><?php echo esc_html( (string) ( $reminder_row['note'] ?? '' ) ); ?></p>
                    <?php if ( ! empty( $reminder_row['id'] ) ) : ?>
                    <form class="crm-task-action" method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>">
                      <input type="hidden" name="action" value="peracrm_update_reminder_status">
                      <input type="hidden" name="peracrm_reminder_id" value="<?php echo esc_attr( (string) absint( $reminder_row['id'] ) ); ?>">
                      <input type="hidden" name="peracrm_status" value="done">
                      <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $frontend_url ); ?>">
                      <input type="hidden" name="peracrm_context" value="frontend">
                      <?php wp_nonce_field( 'peracrm_update_reminder_status', 'peracrm_update_reminder_status_nonce' ); ?>
                      <button type="submit" class="btn btn--ghost btn--blue crm-task-done-btn"><?php esc_html_e( 'Done', 'hello-elementor-child' ); ?></button>
                    </form>
                    <?php endif; ?>
                  </li>
                  <?php endforeach; ?>
                <?php endif; ?>
              </ul>
            </section>

            <section>
              <p class="pill pill--red"><?php echo esc_html( sprintf( __( 'Overdue (%d)', 'hello-elementor-child' ), count( $overdue_task_rows ) ) ); ?></p>
              <ul class="crm-list">
                <?php if ( empty( $overdue_task_rows ) ) : ?>
                <li><?php esc_html_e( 'No overdue reminders.', 'hello-elementor-child' ); ?></li>
                <?php else : ?>
                  <?php foreach ( $overdue_task_rows as $reminder_row ) : ?>
                  <li>
                    <span class="pill pill--red"><?php echo esc_html( (string) ( $reminder_row['due_display'] ?? '' ) ); ?></span>
                    <p><?php echo esc_html( (string) ( $reminder_row['note'] ?? '' ) ); ?></p>
                    <?php if ( ! empty( $reminder_row['id'] ) ) : ?>
                    <form class="crm-task-action" method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>">
                      <input type="hidden" name="action" value="peracrm_update_reminder_status">
                      <input type="hidden" name="peracrm_reminder_id" value="<?php echo esc_attr( (string) absint( $reminder_row['id'] ) ); ?>">
                      <input type="hidden" name="peracrm_status" value="done">
                      <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $frontend_url ); ?>">
                      <input type="hidden" name="peracrm_context" value="frontend">
                      <?php wp_nonce_field( 'peracrm_update_reminder_status', 'peracrm_update_reminder_status_nonce' ); ?>
                      <button type="submit" class="btn btn--ghost btn--blue crm-task-done-btn"><?php esc_html_e( 'Done', 'hello-elementor-child' ); ?></button>
                    </form>
                    <?php endif; ?>
                  </li>
                  <?php endforeach; ?>
                <?php endif; ?>
              </ul>
            </section>

            <section>
              <p class="pill pill--outline"><?php echo esc_html( sprintf( __( 'Upcoming (%d)', 'hello-elementor-child' ), count( $upcoming_rows ) ) ); ?></p>
              <ul class="crm-list">
                <?php if ( empty( $upcoming_rows ) ) : ?>
                <li><?php esc_html_e( 'No upcoming reminders.', 'hello-elementor-child' ); ?></li>
                <?php else : ?>
                  <?php foreach ( $upcoming_rows as $reminder_row ) : ?>
                  <li>
                    <span class="pill pill--outline"><?php echo esc_html( (string) ( $reminder_row['due_display'] ?? '' ) ); ?></span>
                    <p><?php echo esc_html( (string) ( $reminder_row['note'] ?? '' ) ); ?></p>
                    <?php if ( ! empty( $reminder_row['id'] ) ) : ?>
                    <form class="crm-task-action" method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>">
                      <input type="hidden" name="action" value="peracrm_update_reminder_status">
                      <input type="hidden" name="peracrm_reminder_id" value="<?php echo esc_attr( (string) absint( $reminder_row['id'] ) ); ?>">
                      <input type="hidden" name="peracrm_status" value="done">
                      <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $frontend_url ); ?>">
                      <input type="hidden" name="peracrm_context" value="frontend">
                      <?php wp_nonce_field( 'peracrm_update_reminder_status', 'peracrm_update_reminder_status_nonce' ); ?>
                      <button type="submit" class="btn btn--ghost btn--blue crm-task-done-btn"><?php esc_html_e( 'Done', 'hello-elementor-child' ); ?></button>
                    </form>
                    <?php endif; ?>
                  </li>
                  <?php endforeach; ?>
                <?php endif; ?>
              </ul>
            </section>
          </div>
          <form id="crm-add-reminder" method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>" class="crm-form-stack">
            <?php wp_nonce_field( 'peracrm_add_reminder', 'peracrm_add_reminder_nonce' ); ?>
            <input type="hidden" name="action" value="peracrm_add_reminder" />
            <input type="hidden" name="peracrm_client_id" value="<?php echo esc_attr( (string) $client_id ); ?>" />
            <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $frontend_url ); ?>" />
            <div class="crm-form-row-2">
              <label><?php esc_html_e( 'Due date & time', 'hello-elementor-child' ); ?><input type="datetime-local" name="peracrm_due_at" required /></label>
              <label><?php esc_html_e( 'Reminder note', 'hello-elementor-child' ); ?><textarea name="peracrm_reminder_note" rows="2" maxlength="5000" placeholder="<?php echo esc_attr__( 'Add a reminder note…', 'hello-elementor-child' ); ?>"></textarea></label>
            </div>
            <button type="submit" class="btn btn--solid btn--blue"><?php esc_html_e( 'Add reminder', 'hello-elementor-child' ); ?></button>
          </form>
        </article>

          <section class="card-shell crm-client-section crm-client-timeline">
            <h3><?php esc_html_e( 'Timeline', 'hello-elementor-child' ); ?></h3>
            <div class="hero-pills">
					<?php foreach ( array( 'all' => 'All', 'activity' => 'Activity', 'notes' => 'Notes', 'reminders' => 'Reminders' ) as $key => $label ) : ?>
						<?php $url = add_query_arg( 'peracrm_timeline', $key, $frontend_url ); ?>
						<a class="pill <?php echo esc_attr( $timeline_filter === $key ? 'pill--brand' : 'pill--outline' ); ?>" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
            </div>
            <div class="archive-hero-desc" data-collapsed="true">
              <div id="crm-client-timeline-content" class="archive-hero-desc__content">
                <ul class="crm-list crm-client-timeline__list">
						<?php if ( empty( $timeline_items ) ) : ?>
                    <li><?php esc_html_e( 'No timeline items yet.', 'hello-elementor-child' ); ?></li>
						<?php else : ?>
							<?php foreach ( $timeline_items as $item ) : ?>
                      <?php
                      $item_type_label = (string) ( $item['type_label'] ?? ( $item['type'] ?? '' ) );
                      $item_time       = is_array( $item['time'] ?? null ) ? (array) $item['time'] : array( 'relative' => '', 'title' => '' );
                      $item_meta_line  = (string) ( $item['meta_line'] ?? '' );
                      ?>
                      <li class="crm-client-timeline__item">
                        <div class="crm-client-timeline__header">
                          <span class="pill pill--outline"><?php echo esc_html( $item_type_label ); ?></span>
                          <?php if ( ! empty( $item_time['relative'] ) ) : ?>
                            <span class="crm-client-timeline__time" title="<?php echo esc_attr( (string) ( $item_time['title'] ?? '' ) ); ?>"><?php echo esc_html( (string) $item_time['relative'] ); ?></span>
                          <?php endif; ?>
                        </div>
                        <strong><?php echo esc_html( (string) ( $item['title'] ?? '' ) ); ?></strong>
								<?php if ( ! empty( $item['detail'] ) ) : ?>
                          <span class="crm-client-timeline__detail"><?php echo esc_html( (string) $item['detail'] ); ?></span>
								<?php endif; ?>
                        <?php if ( ! empty( $item['details_html'] ) ) : ?>
                          <div class="crm-client-timeline__details peracrm-timeline-detail peracrm-timeline-detail--structured"><?php echo wp_kses_post( (string) $item['details_html'] ); ?></div>
                        <?php endif; ?>
                        <?php if ( '' !== $item_meta_line ) : ?>
                          <span class="crm-client-timeline__meta"><?php echo esc_html( $item_meta_line ); ?></span>
                        <?php endif; ?>
                      </li>
							<?php endforeach; ?>
						<?php endif; ?>
                </ul>
              </div>
              <button type="button" class="pill pill--green archive-hero-desc__toggle" aria-expanded="false" aria-controls="crm-client-timeline-content" data-label-more="<?php echo esc_attr__( 'See more', 'hello-elementor-child' ); ?>" data-label-less="<?php echo esc_attr__( 'See less', 'hello-elementor-child' ); ?>"><?php esc_html_e( 'See more', 'hello-elementor-child' ); ?></button>
            </div>
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
                <ul class="crm-list peracrm-linked-properties-grid">
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
                    <li class="peracrm-linked-properties-grid__item">
								<?php if ( '' !== $property_url ) : ?>
                        <a class="crm-linked-property-link" href="<?php echo esc_url( $property_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $property_label ); ?></a>
								<?php else : ?>
                        <span><?php echo esc_html( $property_label ); ?></span>
								<?php endif; ?>
                      <form method="post" class="peracrm-linked-property-unlink-form">
									<?php wp_nonce_field( 'pera_crm_property_action', 'pera_crm_property_nonce' ); ?>
                        <input type="hidden" name="pera_crm_property_action" value="unlink" />
                        <input type="hidden" name="peracrm_client_id" value="<?php echo esc_attr( (string) $client_id ); ?>" />
                        <input type="hidden" name="property_id" value="<?php echo esc_attr( (string) $property_id ); ?>" />
                        <input type="hidden" name="relation_type" value="<?php echo esc_attr( (string) $relation ); ?>" />
                        <button type="submit" class="btn btn--ghost btn--blue peracrm-linked-property-unlink-btn" aria-label="<?php esc_attr_e( 'Unlink property', 'hello-elementor-child' ); ?>">
                          <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#icon-broken-chain"></use>
                          </svg>
                        </button>
                      </form>
                    </li>
						<?php endforeach; ?>
                </ul>
					<?php endif; ?>
				<?php endforeach; ?>
          </article>

          <article class="card-shell crm-client-section">
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
            <form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>" class="crm-form-stack crm-deals-form">
              <input type="hidden" name="action" value="<?php echo esc_attr( 'update' === $deal_form_mode ? 'peracrm_update_deal' : 'peracrm_create_deal' ); ?>" />
              <input type="hidden" name="peracrm_deal_nonce" value="<?php echo esc_attr( wp_create_nonce( 'update' === $deal_form_mode ? 'peracrm_update_deal' : 'peracrm_create_deal' ) ); ?>" />
              <input type="hidden" name="peracrm_deal_submit" value="1" />
              <input type="hidden" name="peracrm_client_id" value="<?php echo esc_attr( (string) $client_id ); ?>" />
              <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $frontend_url ); ?>" />
					<?php if ( 'update' === $deal_form_mode && is_array( $editing_deal ) ) : ?>
                <input type="hidden" name="deal_id" value="<?php echo esc_attr( (string) ( (int) ( $editing_deal['id'] ?? 0 ) ) ); ?>" />
					<?php endif; ?>
	              <div class="crm-deals-grid">
	                <label><?php esc_html_e( 'Title', 'hello-elementor-child' ); ?><input type="text" name="title" value="<?php echo esc_attr( (string) ( $editing_deal['title'] ?? '' ) ); ?>" required /></label>
	                <label><?php esc_html_e( 'Stage', 'hello-elementor-child' ); ?>
	                  <select name="stage">
						  <?php foreach ( $deal_stage_options as $value => $label ) : ?>
	                    <option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( (string) ( $editing_deal['stage'] ?? 'reservation_taken' ), (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option>
						  <?php endforeach; ?>
	                  </select>
	                </label>
	                <label><?php esc_html_e( 'Primary property ID', 'hello-elementor-child' ); ?><input type="number" min="0" name="primary_property_id" value="<?php echo esc_attr( (string) ( $editing_deal['primary_property_id'] ?? '' ) ); ?>" /></label>
	                <label><?php esc_html_e( 'Deal value', 'hello-elementor-child' ); ?>
	                  <div class="crm-deal-value-row">
	                    <div class="crm-deal-value-input">
	                      <input type="number" step="0.01" min="0" name="deal_value" value="<?php echo esc_attr( (string) ( $editing_deal['deal_value'] ?? '' ) ); ?>" />
	                    </div>
	                    <div class="crm-deal-currency">
	                      <select name="currency" aria-label="<?php esc_attr_e( 'Currency', 'hello-elementor-child' ); ?>">
	                        <?php foreach ( array( 'USD', 'EUR', 'GBP', 'TRY' ) as $currency_option ) : ?>
	                          <option value="<?php echo esc_attr( $currency_option ); ?>" <?php selected( strtoupper( (string) ( $editing_deal['currency'] ?? 'USD' ) ), $currency_option ); ?>><?php echo esc_html( $currency_option ); ?></option>
	                        <?php endforeach; ?>
	                      </select>
	                    </div>
	                  </div>
	                </label>
	              </div>
	              <button type="submit" class="btn btn--solid btn--blue"><?php echo esc_html( 'update' === $deal_form_mode ? __( 'Update deal', 'hello-elementor-child' ) : __( 'Create deal', 'hello-elementor-child' ) ); ?></button>
            </form>
          </article>

          <article class="card-shell crm-client-section">
            <h3><?php esc_html_e( 'Assigned Advisor', 'hello-elementor-child' ); ?></h3>
            <?php if ( function_exists( 'peracrm_render_assigned_advisor_box' ) ) : ?>
              <?php peracrm_render_assigned_advisor_box( $client_id, array( 'context' => 'frontend', 'redirect' => $frontend_url ) ); ?>
            <?php endif; ?>
          </article>

        </div>

        <?php if ( $can_delete_client ) : ?>
          <div class="crm-client-panel-breaker" aria-hidden="true"></div>
          <article class="card-shell crm-client-section crm-client-panel--full crm-danger-zone">
            <h3><?php esc_html_e( 'Danger zone', 'hello-elementor-child' ); ?></h3>
            <p><?php esc_html_e( 'Delete this client permanently, or set it to dormant.', 'hello-elementor-child' ); ?></p>
            <?php if ( $can_delete_client ) : ?>
            <button type="button" class="btn btn--ghost btn--red crm-danger-zone__trigger" data-crm-danger-open="crm-client-danger-dialog"><?php esc_html_e( 'Delete client', 'hello-elementor-child' ); ?></button>
            <?php endif; ?>

            <dialog class="crm-danger-dialog" id="crm-client-danger-dialog" aria-labelledby="crm-danger-title-<?php echo esc_attr( (string) $client_id ); ?>">
              <h4 id="crm-danger-title-<?php echo esc_attr( (string) $client_id ); ?>"><?php esc_html_e( 'Delete client', 'hello-elementor-child' ); ?></h4>
              <p><?php esc_html_e( 'Are you sure you want to delete this client? This cannot be undone. You can alternatively make it dormant.', 'hello-elementor-child' ); ?></p>
              <div class="crm-danger-dialog__actions">
                <?php if ( $can_delete_client ) : ?>
                <form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>">
                  <?php wp_nonce_field( 'peracrm_delete_client', 'peracrm_delete_client_nonce' ); ?>
                  <input type="hidden" name="action" value="peracrm_delete_client" />
                  <input type="hidden" name="peracrm_client_id" value="<?php echo esc_attr( (string) $client_id ); ?>" />
                  <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $delete_redirect_url ); ?>" />
                  <button type="submit" class="btn btn--solid btn--red"><?php esc_html_e( 'Yes (Delete)', 'hello-elementor-child' ); ?></button>
                </form>
                <?php endif; ?>
                <?php if ( $can_set_dormant ) : ?>
                <form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>">
                  <?php wp_nonce_field( 'peracrm_save_client_profile', 'peracrm_save_client_profile_nonce' ); ?>
                  <input type="hidden" name="action" value="peracrm_save_client_profile" />
                  <input type="hidden" name="peracrm_client_id" value="<?php echo esc_attr( (string) $client_id ); ?>" />
                  <input type="hidden" name="peracrm_redirect" value="<?php echo esc_url( $frontend_url ); ?>" />
                  <input type="hidden" name="form_context" value="profile" />
                  <input type="hidden" name="peracrm_status" value="dormant" />
                  <input type="hidden" name="peracrm_client_type" value="<?php echo esc_attr( (string) ( $profile['client_type'] ?? '' ) ); ?>" />
                  <input type="hidden" name="peracrm_preferred_contact" value="<?php echo esc_attr( (string) ( $profile['preferred_contact'] ?? '' ) ); ?>" />
                  <input type="hidden" name="peracrm_phone_country" value="<?php echo esc_attr( (string) $crm_phone_country_value ); ?>" />
                  <input type="hidden" name="peracrm_phone_national" value="<?php echo esc_attr( (string) $crm_phone_national_value ); ?>" />
                  <input type="hidden" name="peracrm_phone" value="<?php echo esc_attr( $profile_phone_value ); ?>" />
                  <input type="hidden" name="peracrm_email" value="<?php echo esc_attr( (string) ( $profile['email'] ?? '' ) ); ?>" />
                  <input type="hidden" name="peracrm_budget_min_usd" value="<?php echo esc_attr( (string) ( $profile['budget_min_usd'] ?? '' ) ); ?>" />
                  <input type="hidden" name="peracrm_budget_max_usd" value="<?php echo esc_attr( (string) ( $profile['budget_max_usd'] ?? '' ) ); ?>" />
                  <input type="hidden" name="peracrm_bedrooms" value="<?php echo esc_attr( (string) ( $profile['bedrooms'] ?? '' ) ); ?>" />
                  <button type="submit" class="btn btn--ghost btn--blue"><?php esc_html_e( 'Make it dormant', 'hello-elementor-child' ); ?></button>
                </form>
                <?php endif; ?>
                <button type="button" class="btn btn--ghost btn--blue" data-crm-danger-close="crm-client-danger-dialog"><?php esc_html_e( 'No (Close)', 'hello-elementor-child' ); ?></button>
              </div>
            </dialog>
          </article>
        <?php endif; ?>

	  <?php endif; ?>
	    </div>
	  </section>

	  <?php if ( ! empty( $access['allowed'] ) ) : ?>
	  <a href="#crm-add-reminder" class="crm-floating-add-task" aria-label="<?php esc_attr_e( 'Add task', 'hello-elementor-child' ); ?>">
		<svg class="icon" aria-hidden="true">
		  <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-check' ); ?>"></use>
		</svg>
	  </a>
	  <?php endif; ?>
</main>
<?php get_footer(); ?>
