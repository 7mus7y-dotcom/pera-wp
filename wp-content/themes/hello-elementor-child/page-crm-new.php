<?php
/**
 * Front-end CRM new lead form template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$error_raw = isset( $_GET['crm_error'] ) ? wp_unslash( (string) $_GET['crm_error'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$error     = sanitize_key( $error_raw );

$prefill_first_name = isset( $_GET['first_name'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['first_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$prefill_last_name  = isset( $_GET['last_name'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['last_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$prefill_email      = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( (string) $_GET['email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$prefill_phone      = isset( $_GET['phone'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['phone'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$prefill_source     = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( (string) $_GET['source'] ) ) : 'website'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$prefill_notes      = isset( $_GET['notes'] ) ? sanitize_textarea_field( wp_unslash( (string) $_GET['notes'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$source_options = array(
	'meta_ads'     => __( 'Meta Ads', 'hello-elementor-child' ),
	'instagram_dm' => __( 'Instagram DM', 'hello-elementor-child' ),
	'whatsapp_dm'  => __( 'WhatsApp DM', 'hello-elementor-child' ),
	'website'      => __( 'Website', 'hello-elementor-child' ),
	'referral'     => __( 'Referral', 'hello-elementor-child' ),
	'other'        => __( 'Other', 'hello-elementor-child' ),
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
	'+966' => '+966',
	'+968' => '+968',
	'+971' => '+971',
	'+973' => '+973',
	'+974' => '+974',
	'+965' => '+965',
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

get_header();
?>

<main id="primary" class="site-main crm-page crm-page--new">
  <?php
  get_template_part(
	  'parts/crm-header',
	  null,
	  array(
		  'title'       => __( 'Create new lead', 'hello-elementor-child' ),
		  'description' => __( 'Add a lead directly from the front-end CRM workspace.', 'hello-elementor-child' ),
	  )
  );
  ?>

  <section class="content-panel content-panel--overlap-hero">
    <div class="content-panel-box border-dm container">
      <article class="card-shell crm-form-card">
        <?php if ( 'invalid_nonce' === $error ) : ?>
          <p class="pill pill--outline"><?php echo esc_html__( 'Security check failed. Please try again.', 'hello-elementor-child' ); ?></p>
        <?php elseif ( 'missing_required' === $error ) : ?>
          <p class="pill pill--outline"><?php echo esc_html__( 'First name, last name, email, and source are required.', 'hello-elementor-child' ); ?></p>
        <?php elseif ( 'invalid_email' === $error ) : ?>
          <p class="pill pill--outline"><?php echo esc_html__( 'Please enter a valid email address.', 'hello-elementor-child' ); ?></p>
        <?php elseif ( 'invalid_source' === $error ) : ?>
          <p class="pill pill--outline"><?php echo esc_html__( 'Please choose a valid lead source.', 'hello-elementor-child' ); ?></p>
        <?php elseif ( 'create_failed' === $error ) : ?>
          <p class="pill pill--outline"><?php echo esc_html__( 'Could not create lead. Please try again.', 'hello-elementor-child' ); ?></p>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( home_url( '/crm/new/' ) ); ?>" class="crm-form-stack">
          <?php wp_nonce_field( 'pera_crm_create_lead', 'pera_crm_create_lead_nonce' ); ?>

          <p>
            <label for="crm-first-name"><?php echo esc_html__( 'First name *', 'hello-elementor-child' ); ?></label>
            <input id="crm-first-name" name="first_name" type="text" required value="<?php echo esc_attr( $prefill_first_name ); ?>" />
          </p>

          <p>
            <label for="crm-last-name"><?php echo esc_html__( 'Last name *', 'hello-elementor-child' ); ?></label>
            <input id="crm-last-name" name="last_name" type="text" required value="<?php echo esc_attr( $prefill_last_name ); ?>" />
          </p>

          <p>
            <label for="crm-email"><?php echo esc_html__( 'Email *', 'hello-elementor-child' ); ?></label>
            <input id="crm-email" name="email" type="email" required value="<?php echo esc_attr( $prefill_email ); ?>" />
          </p>

          <div class="crm-phone-field">
            <div class="crm-field-label"><?php echo esc_html__( 'Mobile / WhatsApp', 'hello-elementor-child' ); ?></div>
            <div class="crm-phone-row">
              <select name="peracrm_phone_country" class="crm-phone-country" aria-label="<?php echo esc_attr__( 'Country code', 'hello-elementor-child' ); ?>">
                <?php foreach ( $crm_phone_country_options as $country_value => $country_label ) : ?>
                  <option value="<?php echo esc_attr( (string) $country_value ); ?>" <?php selected( $prefill_phone_country, (string) $country_value ); ?>><?php echo esc_html( (string) $country_label ); ?></option>
                <?php endforeach; ?>
              </select>
              <input type="tel" name="peracrm_phone_national" value="<?php echo esc_attr( (string) $prefill_phone_national ); ?>" inputmode="tel" autocomplete="tel-national" placeholder="<?php echo esc_attr__( 'Phone number', 'hello-elementor-child' ); ?>" aria-label="<?php echo esc_attr__( 'Phone number', 'hello-elementor-child' ); ?>" />
            </div>
          </div>

          <p>
            <label for="crm-source"><?php echo esc_html__( 'Source *', 'hello-elementor-child' ); ?></label>
            <select id="crm-source" name="source" required>
              <?php foreach ( $source_options as $source_key => $source_label ) : ?>
                <option value="<?php echo esc_attr( (string) $source_key ); ?>" <?php selected( $prefill_source, (string) $source_key ); ?>><?php echo esc_html( (string) $source_label ); ?></option>
              <?php endforeach; ?>
            </select>
          </p>

          <p>
            <label for="crm-notes"><?php echo esc_html__( 'Notes', 'hello-elementor-child' ); ?></label>
            <textarea id="crm-notes" name="notes" rows="4"><?php echo esc_textarea( $prefill_notes ); ?></textarea>
          </p>

          <p>
            <button type="submit" class="pill pill--brand"><?php echo esc_html__( 'Create lead', 'hello-elementor-child' ); ?></button>
          </p>
        </form>
      </article>
    </div>
  </section>
</main>

<?php
get_footer();
