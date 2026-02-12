<?php
/**
 * Front-end CRM client info template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$client_id = (int) get_query_var( 'pera_crm_client_id', 0 );
$client    = $client_id > 0 ? get_post( $client_id ) : null;

if ( ! ( $client instanceof WP_Post ) || 'crm_client' !== $client->post_type ) {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}

$owner_user_id = $client_id > 0 && function_exists( 'peracrm_client_get_assigned_advisor_id' )
	? (int) peracrm_client_get_assigned_advisor_id( $client_id )
	: (int) get_post_meta( $client_id, '_peracrm_owner_user_id', true );

$owner_name = $owner_user_id > 0 ? (string) get_the_author_meta( 'display_name', $owner_user_id ) : '';
$party      = $client_id > 0 && function_exists( 'peracrm_party_get_status' ) ? peracrm_party_get_status( $client_id ) : array();
$stage      = sanitize_key( (string) ( $party['lead_pipeline_stage'] ?? 'new_enquiry' ) );
$stages     = function_exists( 'pera_crm_get_pipeline_stages' ) ? pera_crm_get_pipeline_stages() : array();

get_header();
?>

<main id="primary" class="site-main crm-page crm-page--client">
  <section class="hero hero--left hero--fit" id="crm-hero">
    <div class="hero-content container">
      <h1><?php echo esc_html( $client instanceof WP_Post ? get_the_title( $client ) : __( 'Lead not found', 'hello-elementor-child' ) ); ?></h1>
      <div class="hero-actions hero-pills">
        <a class="pill pill--outline" href="<?php echo esc_url( home_url( '/crm/' ) ); ?>"><?php echo esc_html__( 'Back to CRM', 'hello-elementor-child' ); ?></a>
      </div>
    </div>
  </section>

  <section class="content-panel content-panel--overlap-hero">
    <div class="content-panel-box border-dm">
      <?php if ( $client instanceof WP_Post ) : ?>
        <article class="card-shell crm-client-summary">
          <p><strong><?php echo esc_html__( 'Title:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( get_the_title( $client ) ); ?></p>
          <p><strong><?php echo esc_html__( 'Assigned owner:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( '' !== $owner_name ? $owner_name : __( 'Unassigned', 'hello-elementor-child' ) ); ?></p>
          <p><strong><?php echo esc_html__( 'Stage:', 'hello-elementor-child' ); ?></strong> <?php echo esc_html( (string) ( $stages[ $stage ] ?? $stage ) ); ?></p>
          <p><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $client_id . '&action=edit' ) ); ?>"><?php echo esc_html__( 'Open in wp-admin', 'hello-elementor-child' ); ?></a></p>
        </article>
      <?php else : ?>
        <article class="card-shell">
          <p><?php echo esc_html__( 'Lead not found.', 'hello-elementor-child' ); ?></p>
          <p><a href="<?php echo esc_url( home_url( '/crm/' ) ); ?>"><?php echo esc_html__( 'Back to CRM', 'hello-elementor-child' ); ?></a></p>
        </article>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php
get_footer();

