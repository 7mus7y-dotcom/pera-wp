<?php
/**
 * Front-end CRM new lead form template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$error_raw = isset( $_GET['crm_error'] ) ? wp_unslash( (string) $_GET['crm_error'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$error     = sanitize_key( $error_raw );

$existing_client_id_raw = isset( $_GET['existing_client_id'] ) ? wp_unslash( (string) $_GET['existing_client_id'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$existing_client_id     = absint( $existing_client_id_raw );
$existing_client_url    = $existing_client_id > 0 ? home_url( '/crm/client/' . $existing_client_id . '/' ) : '';

$prefill_first_name = isset( $_GET['first_name'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['first_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$prefill_last_name  = isset( $_GET['last_name'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['last_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$prefill_email      = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( (string) $_GET['email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$prefill_phone      = isset( $_GET['phone'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['phone'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$prefill_source     = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( (string) $_GET['source'] ) ) : 'website'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$prefill_notes      = isset( $_GET['notes'] ) ? sanitize_textarea_field( wp_unslash( (string) $_GET['notes'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$cancel_url         = home_url( '/crm/clients/' );

$source_options = array(
	'meta_ads'     => __( 'Meta Ads', 'peracrm' ),
	'instagram_dm' => __( 'Instagram DM', 'peracrm' ),
	'whatsapp_dm'  => __( 'WhatsApp DM', 'peracrm' ),
	'website'      => __( 'Website', 'peracrm' ),
	'referral'     => __( 'Referral', 'peracrm' ),
	'other'        => __( 'Other', 'peracrm' ),
);

if ( ! isset( $source_options[ $prefill_source ] ) ) {
	$prefill_source = 'website';
}

$default_phone_country = '+90';
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
$available_phone_countries = $crm_phone_country_options;

$prefill_phone_country  = $default_phone_country;
$prefill_phone_national = '';
$prefill_phone_trimmed  = ltrim( $prefill_phone );

if ( '' !== $prefill_phone_trimmed && '+' === substr( $prefill_phone_trimmed, 0, 1 ) ) {
	$phone_digits  = preg_replace( '/\D+/', '', $prefill_phone_trimmed );
	$sorted_codes  = array_keys( $available_phone_countries );
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

		if ( strpos( $phone_digits, $country_digits ) === 0 ) {
			$prefill_phone_country  = (string) $country_code;
			$prefill_phone_national = substr( $phone_digits, strlen( $country_digits ) );
			break;
		}
	}
}

if ( '' === $prefill_phone_national && '' !== $prefill_phone ) {
	$prefill_phone_national = preg_replace( '/\D+/', '', $prefill_phone );
}

if ( ! isset( $crm_phone_country_options[ $prefill_phone_country ] ) ) {
	$prefill_phone_country = $default_phone_country;
}

peracrm_frontend_render_shell_header( array( 'show_crm_nav_toggle' => false ) );
?>

<main id="primary" class="site-main crm-page crm-page--new">
  <?php
  if ( function_exists( 'peracrm_frontend_render_partial' ) ) {
	  peracrm_frontend_render_partial(
		  'crm-header',
		  array(
			  'title'       => __( 'Create new lead', 'peracrm' ),
			  'description' => __( 'Capture a new lead with clear field groups, calmer form density, and one obvious next step.', 'peracrm' ),
			  'meta'        => __( 'New lead workspace', 'peracrm' ),
			  'actions'     => array(
				array(
					'label' => __( 'Back to clients', 'peracrm' ),
					'url'   => $cancel_url,
					'class' => 'btn btn--ghost btn--blue',
					'type'  => 'secondary',
				),
			  ),
		  )
	  );
  }
  ?>

  <section class="content-panel content-panel--overlap-hero">
    <div class="content-panel-box border-dm container">
      <article class="card-shell crm-form-card crm-form-workspace">
        <header class="crm-form-workspace__header">
          <div class="crm-form-workspace__intro">
            <span class="crm-form-workspace__eyebrow"><?php echo esc_html__( 'Lead creation', 'peracrm' ); ?></span>
            <h2 class="crm-form-workspace__title"><?php echo esc_html__( 'New lead details', 'peracrm' ); ?></h2>
            <p class="crm-form-workspace__description"><?php echo esc_html__( 'Use the grouped sections below to capture identity, contact details, source, and optional context before creating the record.', 'peracrm' ); ?></p>
          </div>
          <div class="crm-form-workspace__meta">
            <div class="crm-meta-line">
              <span><strong><?php esc_html_e( 'Required:', 'peracrm' ); ?></strong> <?php esc_html_e( 'First name, last name, email, and source', 'peracrm' ); ?></span>
              <span><strong><?php esc_html_e( 'Optional:', 'peracrm' ); ?></strong> <?php esc_html_e( 'Phone and notes', 'peracrm' ); ?></span>
            </div>
          </div>
        </header>

        <?php if ( 'invalid_nonce' === $error ) : ?>
          <div class="crm-inline-notice crm-inline-notice--error" role="alert"><?php echo esc_html__( 'Security check failed. Please try again.', 'peracrm' ); ?></div>
        <?php elseif ( 'missing_required' === $error ) : ?>
          <div class="crm-inline-notice crm-inline-notice--error" role="alert"><?php echo esc_html__( 'First name, last name, email, and source are required.', 'peracrm' ); ?></div>
        <?php elseif ( 'invalid_email' === $error ) : ?>
          <div class="crm-inline-notice crm-inline-notice--error" role="alert"><?php echo esc_html__( 'Please enter a valid email address.', 'peracrm' ); ?></div>
        <?php elseif ( 'invalid_source' === $error ) : ?>
          <div class="crm-inline-notice crm-inline-notice--error" role="alert"><?php echo esc_html__( 'Please choose a valid lead source.', 'peracrm' ); ?></div>
        <?php elseif ( 'create_failed' === $error ) : ?>
          <div class="crm-inline-notice crm-inline-notice--error" role="alert"><?php echo esc_html__( 'Could not create lead. Please try again.', 'peracrm' ); ?></div>
        <?php elseif ( 'duplicate_email' === $error ) : ?>
          <div class="crm-inline-notice crm-inline-notice--error" role="alert">
            <?php echo esc_html__( 'A lead with this email already exists. No duplicate record was created.', 'peracrm' ); ?>
            <?php if ( $existing_client_url ) : ?>
              <a href="<?php echo esc_url( $existing_client_url ); ?>"><?php echo esc_html__( 'Open existing client', 'peracrm' ); ?></a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( home_url( '/crm/new/' ) ); ?>" class="crm-form-stack crm-form-workspace__form">
          <?php wp_nonce_field( 'pera_crm_create_lead', 'pera_crm_create_lead_nonce' ); ?>

          <section class="crm-section crm-section--flush crm-form-section" aria-labelledby="crm-new-lead-identity-heading">
            <header class="crm-section__header">
              <div class="crm-section__heading-group">
                <h3 id="crm-new-lead-identity-heading" class="crm-section__title"><?php esc_html_e( 'Basic identity', 'peracrm' ); ?></h3>
                <p class="crm-section__description"><?php esc_html_e( 'Capture the lead name and primary email first so duplicate checks and follow-up routing stay reliable.', 'peracrm' ); ?></p>
              </div>
            </header>
            <div class="crm-section__body">
              <div class="crm-form-grid crm-form-grid--split">
                <div class="crm-form-field">
                  <label for="crm-first-name"><?php echo esc_html__( 'First name *', 'peracrm' ); ?></label>
                  <input id="crm-first-name" name="first_name" type="text" required value="<?php echo esc_attr( $prefill_first_name ); ?>" />
                </div>

                <div class="crm-form-field">
                  <label for="crm-last-name"><?php echo esc_html__( 'Last name *', 'peracrm' ); ?></label>
                  <input id="crm-last-name" name="last_name" type="text" required value="<?php echo esc_attr( $prefill_last_name ); ?>" />
                </div>
              </div>

              <div class="crm-form-field">
                <label for="crm-email"><?php echo esc_html__( 'Email *', 'peracrm' ); ?></label>
                <input id="crm-email" name="email" type="email" required value="<?php echo esc_attr( $prefill_email ); ?>" />
              </div>
            </div>
          </section>

          <section class="crm-section crm-section--flush crm-form-section" aria-labelledby="crm-new-lead-contact-heading">
            <header class="crm-section__header">
              <div class="crm-section__heading-group">
                <h3 id="crm-new-lead-contact-heading" class="crm-section__title"><?php esc_html_e( 'Contact details', 'peracrm' ); ?></h3>
                <p class="crm-section__description"><?php esc_html_e( 'Add the best reachable number when available. The country code and national number stay separated for existing phone handling.', 'peracrm' ); ?></p>
              </div>
            </header>
            <div class="crm-section__body">
              <div class="crm-phone-field crm-form-field">
                <label for="crm-phone-national"><?php echo esc_html__( 'Mobile / WhatsApp', 'peracrm' ); ?></label>
                <div class="crm-phone-row">
                  <select id="crm-phone-country" name="peracrm_phone_country" class="crm-phone-country" aria-label="<?php echo esc_attr__( 'Country code', 'peracrm' ); ?>">
                    <?php foreach ( $crm_phone_country_options as $country_value => $country_label ) : ?>
                      <option value="<?php echo esc_attr( (string) $country_value ); ?>" <?php selected( $prefill_phone_country, (string) $country_value ); ?>><?php echo esc_html( (string) $country_label ); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <input id="crm-phone-national" type="tel" name="peracrm_phone_national" value="<?php echo esc_attr( (string) $prefill_phone_national ); ?>" inputmode="tel" autocomplete="tel-national" placeholder="<?php echo esc_attr__( 'Phone number', 'peracrm' ); ?>" aria-label="<?php echo esc_attr__( 'Phone number', 'peracrm' ); ?>" />
                </div>
              </div>
            </div>
          </section>

          <section class="crm-section crm-section--flush crm-form-section" aria-labelledby="crm-new-lead-classification-heading">
            <header class="crm-section__header">
              <div class="crm-section__heading-group">
                <h3 id="crm-new-lead-classification-heading" class="crm-section__title"><?php esc_html_e( 'Lead source and context', 'peracrm' ); ?></h3>
                <p class="crm-section__description"><?php esc_html_e( 'Classify how the lead entered the CRM and add any optional notes that help the next advisor pick up the conversation safely.', 'peracrm' ); ?></p>
              </div>
            </header>
            <div class="crm-section__body">
              <div class="crm-form-field">
                <label for="crm-source"><?php echo esc_html__( 'Source *', 'peracrm' ); ?></label>
                <select id="crm-source" name="source" required>
                  <?php foreach ( $source_options as $source_key => $source_label ) : ?>
                    <option value="<?php echo esc_attr( (string) $source_key ); ?>" <?php selected( $prefill_source, (string) $source_key ); ?>><?php echo esc_html( (string) $source_label ); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="crm-form-field">
                <label for="crm-notes"><?php echo esc_html__( 'Notes', 'peracrm' ); ?></label>
                <textarea id="crm-notes" name="notes" rows="4"><?php echo esc_textarea( $prefill_notes ); ?></textarea>
              </div>
            </div>
          </section>

          <footer class="crm-form-workspace__footer">
            <div class="crm-meta-line">
              <span><?php esc_html_e( 'Primary action creates the lead immediately.', 'peracrm' ); ?></span>
              <span><?php esc_html_e( 'Cancel returns to the client workspace without saving.', 'peracrm' ); ?></span>
            </div>
            <div class="crm-action-group crm-action-group--form">
              <a class="btn btn--ghost btn--blue crm-action-group__item crm-action-group__item--secondary" href="<?php echo esc_url( $cancel_url ); ?>"><?php esc_html_e( 'Cancel', 'peracrm' ); ?></a>
              <button type="submit" class="btn btn--solid btn--green crm-action-group__item crm-action-group__item--primary"><?php echo esc_html__( 'Create lead', 'peracrm' ); ?></button>
            </div>
          </footer>
        </form>
      </article>
    </div>
  </section>
</main>

<?php
peracrm_frontend_render_shell_footer();
