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
      <a class="btn btn--solid btn--white" href="<?php echo esc_url( home_url( '/crm/new/' ) ); ?>"><?php echo esc_html__( 'Create lead', 'hello-elementor-child' ); ?></a>
    </div>
    <nav class="crm-subnav" aria-label="<?php echo esc_attr__( 'CRM sections', 'hello-elementor-child' ); ?>">
      <?php foreach ( $sections as $section_key => $section ) : ?>
        <a class="btn btn--ghost <?php echo esc_attr( $section_key === $active_view ? 'btn--red is-active' : 'btn--white' ); ?>" href="<?php echo esc_url( (string) $section['url'] ); ?>"><?php echo esc_html( (string) $section['label'] ); ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</section>
