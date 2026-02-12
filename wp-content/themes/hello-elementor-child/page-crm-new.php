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

get_header();
?>

<main id="primary" class="site-main crm-page crm-page--new">
  <section class="hero hero--left hero--fit" id="crm-hero">
    <div class="hero-content container">
      <h1><?php echo esc_html__( 'Create new lead', 'hello-elementor-child' ); ?></h1>
      <p class="lead"><?php echo esc_html__( 'Add a lead directly from the front-end CRM workspace.', 'hello-elementor-child' ); ?></p>
      <div class="hero-actions hero-pills">
        <a class="pill pill--outline" href="<?php echo esc_url( home_url( '/crm/' ) ); ?>"><?php echo esc_html__( 'Back to CRM', 'hello-elementor-child' ); ?></a>
      </div>
    </div>
  </section>

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

          <p>
            <label for="crm-phone"><?php echo esc_html__( 'Phone', 'hello-elementor-child' ); ?></label>
            <input id="crm-phone" name="phone" type="text" value="<?php echo esc_attr( $prefill_phone ); ?>" />
          </p>

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
