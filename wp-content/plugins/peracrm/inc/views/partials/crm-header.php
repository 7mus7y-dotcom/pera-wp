<?php
/**
 * CRM shared hero header.
 *
 * @var array $args
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title       = isset( $args['title'] ) ? (string) $args['title'] : __( 'CRM', 'peracrm' );
$description = isset( $args['description'] ) ? (string) $args['description'] : '';
$show_client_filters = ! empty( $args['show_client_filters'] );
$stages              = is_array( $args['stages'] ?? null ) ? $args['stages'] : array();
$advisors            = is_array( $args['advisors'] ?? null ) ? $args['advisors'] : array();
$clients_type_view   = isset( $args['clients_type_view'] ) ? sanitize_key( (string) $args['clients_type_view'] ) : 'leads';
$filter_q            = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) : '';
$filter_stage        = isset( $_GET['stage'] ) ? sanitize_key( wp_unslash( (string) $_GET['stage'] ) ) : '';
$filter_advisor      = isset( $_GET['advisor'] ) ? absint( wp_unslash( (string) $_GET['advisor'] ) ) : 0;

?>
<section class="hero hero--left hero--fit" id="crm-hero">
  <div class="hero-content container">
    <h1><?php echo esc_html( $title ); ?></h1>
    <?php if ( '' !== $description ) : ?>
      <p class="lead"><?php echo esc_html( $description ); ?></p>
    <?php endif; ?>
    <?php if ( $show_client_filters ) : ?>
    <form method="get" action="<?php echo esc_url( home_url( '/crm/clients/' ) ); ?>" class="crm-client-hero-filters">
      <input type="hidden" name="type" value="<?php echo esc_attr( $clients_type_view ); ?>">
      <div class="crm-client-hero-filters-grid">
        <label>
          <span class="screen-reader-text"><?php esc_html_e( 'Search clients', 'peracrm' ); ?></span>
          <input class="crm-search-control" type="search" name="q" value="<?php echo esc_attr( $filter_q ); ?>" placeholder="<?php echo esc_attr__( 'Search clients', 'peracrm' ); ?>">
        </label>

        <label>
          <span class="screen-reader-text"><?php esc_html_e( 'Stage', 'peracrm' ); ?></span>
          <select class="crm-search-control" name="stage">
            <option value=""><?php esc_html_e( 'All stages', 'peracrm' ); ?></option>
            <?php foreach ( $stages as $stage_key => $stage_label ) : ?>
              <option value="<?php echo esc_attr( (string) $stage_key ); ?>" <?php selected( $filter_stage, $stage_key ); ?>>
                <?php echo esc_html( (string) $stage_label ); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label>
          <span class="screen-reader-text"><?php esc_html_e( 'Advisor', 'peracrm' ); ?></span>
          <select class="crm-search-control" name="advisor">
            <option value="0"><?php esc_html_e( 'All advisors', 'peracrm' ); ?></option>
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
          <button type="submit" class="btn btn--solid btn--green"><?php esc_html_e( 'Apply filters', 'peracrm' ); ?></button>
          <a class="btn btn--ghost btn--white" href="<?php echo esc_url( home_url( '/crm/clients/' ) ); ?>"><?php esc_html_e( 'Clear', 'peracrm' ); ?></a>
        </div>
      </div>
    </form>
    <?php endif; ?>
  </div>
</section>
