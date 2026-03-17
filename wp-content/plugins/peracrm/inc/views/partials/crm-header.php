<?php
/**
 * CRM shared hero header.
 *
 * @var array $args
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title       = isset( $args['title'] ) ? (string) $args['title'] : __( 'CRM', 'hello-elementor-child' );
$description = isset( $args['description'] ) ? (string) $args['description'] : '';
$active_view = isset( $args['active_view'] ) ? sanitize_key( (string) $args['active_view'] ) : '';

$show_client_filters = ! empty( $args['show_client_filters'] );
$stages              = is_array( $args['stages'] ?? null ) ? $args['stages'] : array();
$advisors            = is_array( $args['advisors'] ?? null ) ? $args['advisors'] : array();
$clients_type_view   = isset( $args['clients_type_view'] ) ? sanitize_key( (string) $args['clients_type_view'] ) : 'leads';
$filter_q            = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) : '';
$filter_stage        = isset( $_GET['stage'] ) ? sanitize_key( wp_unslash( (string) $_GET['stage'] ) ) : '';
$filter_advisor      = isset( $_GET['advisor'] ) ? absint( wp_unslash( (string) $_GET['advisor'] ) ) : 0;


$sections = array(
	'overview' => array(
		'label' => __( 'Overview', 'hello-elementor-child' ),
		'url'   => home_url( '/crm/' ),
	),
	'clients'  => array(
		'label' => __( 'Clients', 'hello-elementor-child' ),
		'url'   => home_url( '/crm/clients/' ),
	),
	'tasks'    => array(
		'label' => __( 'Tasks', 'hello-elementor-child' ),
		'url'   => home_url( '/crm/tasks/' ),
	),
	'pipeline' => array(
		'label' => __( 'Pipeline', 'hello-elementor-child' ),
		'url'   => home_url( '/crm/pipeline/' ),
	),
);
?>
<section class="hero hero--left hero--fit" id="crm-hero">
  <div class="hero-content container">
    <h1><?php echo esc_html( $title ); ?></h1>
    <?php if ( '' !== $description ) : ?>
      <p class="lead"><?php echo esc_html( $description ); ?></p>
    <?php endif; ?>
    <div class="hero-actions">
      <a class="btn btn--solid btn--green" href="<?php echo esc_url( home_url( '/crm/new/' ) ); ?>"><?php echo esc_html__( 'Create lead', 'hello-elementor-child' ); ?></a>
    </div>
    <nav class="crm-subnav" aria-label="<?php echo esc_attr__( 'CRM sections', 'hello-elementor-child' ); ?>">
      <?php foreach ( $sections as $section_key => $section ) : ?>
        <a class="btn btn--ghost <?php echo esc_attr( $section_key === $active_view ? 'btn--red is-active' : 'btn--white' ); ?>" href="<?php echo esc_url( (string) $section['url'] ); ?>"><?php echo esc_html( (string) $section['label'] ); ?></a>
      <?php endforeach; ?>
    </nav>
    <?php if ( $show_client_filters ) : ?>
    <form method="get" action="<?php echo esc_url( home_url( '/crm/clients/' ) ); ?>" class="crm-client-hero-filters">
      <input type="hidden" name="type" value="<?php echo esc_attr( $clients_type_view ); ?>">
      <div class="crm-client-hero-filters-grid">
        <label>
          <span class="screen-reader-text"><?php esc_html_e( 'Search clients', 'hello-elementor-child' ); ?></span>
          <input class="cta-control" type="search" name="q" value="<?php echo esc_attr( $filter_q ); ?>" placeholder="<?php echo esc_attr__( 'Search clients', 'hello-elementor-child' ); ?>">
        </label>

        <label>
          <span class="screen-reader-text"><?php esc_html_e( 'Stage', 'hello-elementor-child' ); ?></span>
          <select class="cta-control" name="stage">
            <option value=""><?php esc_html_e( 'All stages', 'hello-elementor-child' ); ?></option>
            <?php foreach ( $stages as $stage_key => $stage_label ) : ?>
              <option value="<?php echo esc_attr( (string) $stage_key ); ?>" <?php selected( $filter_stage, $stage_key ); ?>>
                <?php echo esc_html( (string) $stage_label ); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label>
          <span class="screen-reader-text"><?php esc_html_e( 'Advisor', 'hello-elementor-child' ); ?></span>
          <select class="cta-control" name="advisor">
            <option value="0"><?php esc_html_e( 'All advisors', 'hello-elementor-child' ); ?></option>
            <?php foreach ( $advisors as $advisor ) : ?>
              <?php
              $advisor_id    = isset( $advisor['id'] ) ? (int) $advisor['id'] : 0;
              $advisor_label = isset( $advisor['label'] ) ? (string) $advisor['label'] : '';
              if ( $advisor_id <= 0 || '' === $advisor_label ) {
                continue;
              }
              ?>
              <option value="<?php echo esc_attr( (string) $advisor_id ); ?>" <?php selected( $filter_advisor, $advisor_id ); ?>>
                <?php echo esc_html( $advisor_label ); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <div class="crm-client-hero-filter-actions">
          <button type="submit" class="btn btn--solid btn--green"><?php esc_html_e( 'Apply filters', 'hello-elementor-child' ); ?></button>
          <a class="btn btn--ghost btn--white" href="<?php echo esc_url( home_url( '/crm/clients/' ) ); ?>"><?php esc_html_e( 'Clear', 'hello-elementor-child' ); ?></a>
        </div>
      </div>
    </form>
    <?php endif; ?>
  </div>
</section>
