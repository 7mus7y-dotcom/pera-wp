<?php
/**
 * Front-end CRM new lead form template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$error_raw = isset( $_GET['crm_error'] ) ? wp_unslash( (string) $_GET['crm_error'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$error     = sanitize_key( $error_raw );

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
        <?php elseif ( 'create_failed' === $error ) : ?>
          <p class="pill pill--outline"><?php echo esc_html__( 'Could not create lead. Please try again.', 'hello-elementor-child' ); ?></p>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( home_url( '/crm/new/' ) ); ?>" class="crm-form-stack">
          <?php wp_nonce_field( 'pera_crm_create_lead', 'pera_crm_create_lead_nonce' ); ?>

          <p>
            <label for="crm-lead-title"><?php echo esc_html__( 'Name / Title *', 'hello-elementor-child' ); ?></label>
            <input id="crm-lead-title" name="lead_title" type="text" required />
          </p>

          <p>
            <label for="crm-lead-email"><?php echo esc_html__( 'Email', 'hello-elementor-child' ); ?></label>
            <input id="crm-lead-email" name="email" type="email" />
          </p>

          <p>
            <label for="crm-lead-phone"><?php echo esc_html__( 'Phone', 'hello-elementor-child' ); ?></label>
            <input id="crm-lead-phone" name="phone" type="text" />
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
