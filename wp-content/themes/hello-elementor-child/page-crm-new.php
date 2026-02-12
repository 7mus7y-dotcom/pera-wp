<?php
/**
 * Front-end CRM new lead form template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$error_raw = isset( $_GET['crm_error'] ) ? wp_unslash( (string) $_GET['crm_error'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$error     = sanitize_key( $error_raw );

$prefill_title = isset( $_GET['lead_title'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['lead_title'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$prefill_email = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( (string) $_GET['email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$prefill_phone = isset( $_GET['phone'] ) ? preg_replace( '/[^0-9+\-\s()]/', '', wp_unslash( (string) $_GET['phone'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$prefill_stage = isset( $_GET['pipeline_stage'] ) ? sanitize_key( wp_unslash( (string) $_GET['pipeline_stage'] ) ) : 'new_enquiry'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$pipeline_stages = function_exists( 'pera_crm_get_pipeline_stages' ) ? pera_crm_get_pipeline_stages() : array();
if ( empty( $pipeline_stages ) ) {
	$pipeline_stages = array(
		'new_enquiry' => __( 'New enquiry', 'hello-elementor-child' ),
		'qualified'   => __( 'Qualified', 'hello-elementor-child' ),
	);
}

if ( ! isset( $pipeline_stages[ $prefill_stage ] ) ) {
	$prefill_stage = 'new_enquiry';
}

get_header();
?>

<main id="primary" class="site-main crm-page crm-page--new">
  <section class="hero hero--left hero--fit" id="crm-hero">
    <div class="hero-content container">
      <h1><?php echo esc_html__( 'Add new lead', 'hello-elementor-child' ); ?></h1>
      <p class="lead"><?php echo esc_html__( 'Create a CRM lead and continue to its info page.', 'hello-elementor-child' ); ?></p>
      <div class="hero-actions hero-pills">
        <a class="pill pill--outline" href="<?php echo esc_url( home_url( '/crm/' ) ); ?>"><?php echo esc_html__( 'Back to CRM', 'hello-elementor-child' ); ?></a>
      </div>
    </div>
  </section>

  <section class="content-panel content-panel--overlap-hero">
    <div class="content-panel-box border-dm">
      <article class="card-shell crm-form-card">
        <?php if ( 'missing_title' === $error ) : ?>
          <p class="pill pill--outline"><?php echo esc_html__( 'Name / Title is required.', 'hello-elementor-child' ); ?></p>
        <?php elseif ( 'invalid_email' === $error ) : ?>
          <p class="pill pill--outline"><?php echo esc_html__( 'Please enter a valid email address.', 'hello-elementor-child' ); ?></p>
        <?php elseif ( 'create_failed' === $error ) : ?>
          <p class="pill pill--outline"><?php echo esc_html__( 'Could not create lead. Please try again.', 'hello-elementor-child' ); ?></p>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="crm-form-stack">
          <input type="hidden" name="action" value="peracrm_front_create_lead" />
          <?php wp_nonce_field( 'pera_crm_create_lead', 'pera_crm_create_lead_nonce' ); ?>

          <p>
            <label for="crm-lead-title"><?php echo esc_html__( 'Name / Title *', 'hello-elementor-child' ); ?></label>
            <input id="crm-lead-title" name="lead_title" type="text" required value="<?php echo esc_attr( $prefill_title ); ?>" />
          </p>

          <p>
            <label for="crm-lead-email"><?php echo esc_html__( 'Email', 'hello-elementor-child' ); ?></label>
            <input id="crm-lead-email" name="email" type="email" value="<?php echo esc_attr( $prefill_email ); ?>" />
          </p>

          <p>
            <label for="crm-lead-phone"><?php echo esc_html__( 'Phone', 'hello-elementor-child' ); ?></label>
            <input id="crm-lead-phone" name="phone" type="text" value="<?php echo esc_attr( $prefill_phone ); ?>" />
          </p>

          <p>
            <label for="crm-lead-stage"><?php echo esc_html__( 'Pipeline stage', 'hello-elementor-child' ); ?></label>
            <select id="crm-lead-stage" name="pipeline_stage">
              <?php foreach ( $pipeline_stages as $stage_key => $stage_label ) : ?>
                <option value="<?php echo esc_attr( (string) $stage_key ); ?>" <?php selected( $prefill_stage, (string) $stage_key ); ?>><?php echo esc_html( (string) $stage_label ); ?></option>
              <?php endforeach; ?>
            </select>
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
