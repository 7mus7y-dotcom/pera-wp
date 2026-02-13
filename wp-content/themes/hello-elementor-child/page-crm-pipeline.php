<?php
/**
 * Front-end CRM pipeline view.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( function_exists( 'pera_crm_gate_or_redirect' ) ) {
	pera_crm_gate_or_redirect();
}

$pipeline = function_exists( 'pera_crm_get_pipeline_view_data' )
	? pera_crm_get_pipeline_view_data()
	: array( 'columns' => array(), 'can_view_all' => false );

$columns      = is_array( $pipeline['columns'] ?? null ) ? $pipeline['columns'] : array();
$can_view_all = ! empty( $pipeline['can_view_all'] );
$filters      = is_array( $pipeline['filters'] ?? null ) ? $pipeline['filters'] : array();
$filter_q     = isset( $filters['q'] ) ? (string) $filters['q'] : '';
$filter_stage = isset( $filters['stage'] ) ? (string) $filters['stage'] : '';
$filter_advisor = isset( $filters['advisor'] ) ? (int) $filters['advisor'] : 0;
$stage_options  = is_array( $filters['stage_options'] ?? null ) ? $filters['stage_options'] : array();
$advisor_options = is_array( $filters['advisor_options'] ?? null ) ? $filters['advisor_options'] : array();

get_header();
?>

<main id="primary" class="site-main crm-page crm-page--pipeline">
  <?php
  get_template_part(
	  'parts/crm-header',
	  null,
	  array(
		  'title'       => __( 'Pipeline', 'hello-elementor-child' ),
		  'description' => __( 'Manage leads and clients by stage', 'hello-elementor-child' ),
		  'active_view' => 'pipeline',
	  )
  );
  ?>

  <section class="content-panel content-panel--overlap-hero">
    <div class="content-panel-box border-dm">
      <article class="card-shell crm-pipeline-filters">
        <form method="get" action="<?php echo esc_url( home_url( '/crm/pipeline/' ) ); ?>">
          <div class="crm-pipeline-filters-grid grid-3">
            <label>
              <span class="screen-reader-text"><?php esc_html_e( 'Search clients', 'hello-elementor-child' ); ?></span>
              <input type="search" name="q" value="<?php echo esc_attr( $filter_q ); ?>" placeholder="<?php echo esc_attr__( 'Search clients', 'hello-elementor-child' ); ?>">
            </label>
            <label>
              <span class="screen-reader-text"><?php esc_html_e( 'Stage', 'hello-elementor-child' ); ?></span>
              <select name="stage">
                <option value=""><?php esc_html_e( 'All stages', 'hello-elementor-child' ); ?></option>
                <?php foreach ( $stage_options as $stage_key => $stage_label ) : ?>
                  <option value="<?php echo esc_attr( (string) $stage_key ); ?>" <?php selected( (string) $stage_key, $filter_stage ); ?>><?php echo esc_html( (string) $stage_label ); ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <?php if ( $can_view_all ) : ?>
            <label>
              <span class="screen-reader-text"><?php esc_html_e( 'Advisor', 'hello-elementor-child' ); ?></span>
              <select name="advisor">
                <option value="0"><?php esc_html_e( 'All advisors', 'hello-elementor-child' ); ?></option>
                <?php foreach ( $advisor_options as $advisor_option ) : ?>
                  <?php
                  $advisor_id    = isset( $advisor_option['id'] ) ? (int) $advisor_option['id'] : 0;
                  $advisor_label = isset( $advisor_option['label'] ) ? (string) $advisor_option['label'] : '';
                  if ( $advisor_id <= 0 || '' === $advisor_label ) {
                    continue;
                  }
                  ?>
                  <option value="<?php echo esc_attr( (string) $advisor_id ); ?>" <?php selected( $advisor_id, $filter_advisor ); ?>><?php echo esc_html( $advisor_label ); ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <?php endif; ?>
          </div>
          <div class="hero-actions hero-pills">
            <button type="submit" class="pill pill--primary"><?php esc_html_e( 'Apply filters', 'hello-elementor-child' ); ?></button>
            <a class="pill pill--outline" href="<?php echo esc_url( home_url( '/crm/pipeline/' ) ); ?>"><?php esc_html_e( 'Clear filters', 'hello-elementor-child' ); ?></a>
          </div>
        </form>
      </article>

      <section class="crm-pipeline-board-wrap" aria-label="<?php echo esc_attr__( 'Pipeline board', 'hello-elementor-child' ); ?>">
        <div class="crm-pipeline-board">
          <?php foreach ( $columns as $column ) : ?>
            <?php
            $label = (string) ( $column['label'] ?? '' );
            $count = (int) ( $column['count'] ?? 0 );
            $items = is_array( $column['items'] ?? null ) ? $column['items'] : array();
            ?>
            <article class="card-shell crm-pipeline-column">
              <header class="crm-pipeline-column-header">
                <h2><?php echo esc_html( $label ); ?></h2>
                <span class="pill pill--outline"><?php echo esc_html( (string) $count ); ?></span>
              </header>

              <div class="crm-pipeline-items">
                <?php if ( empty( $items ) ) : ?>
                  <p class="crm-pipeline-empty"><?php esc_html_e( 'No clients in this stage.', 'hello-elementor-child' ); ?></p>
                <?php else : ?>
                  <?php foreach ( $items as $item ) : ?>
                    <?php
                    $title   = (string) ( $item['title'] ?? '' );
                    $url     = (string) ( $item['client_url'] ?? '' );
                    $advisor = (string) ( $item['advisor_label'] ?? '' );
                    $last    = (string) ( $item['last_activity'] ?? '' );
                    $min     = (int) ( $item['budget_min'] ?? 0 );
                    $max     = (int) ( $item['budget_max'] ?? 0 );
                    ?>
                    <article class="crm-pipeline-item">
                      <h3><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title !== '' ? $title : __( '(no title)', 'hello-elementor-child' ) ); ?></a></h3>

                      <div class="hero-pills">
                        <?php if ( $advisor !== '' ) : ?>
                          <span class="pill pill--outline"><?php echo esc_html( sprintf( __( 'Advisor: %s', 'hello-elementor-child' ), $advisor ) ); ?></span>
                        <?php endif; ?>
                        <?php if ( $last !== '' ) : ?>
                          <span class="pill pill--outline"><?php echo esc_html( sprintf( __( 'Last activity: %s', 'hello-elementor-child' ), $last ) ); ?></span>
                        <?php endif; ?>
                      </div>

                      <?php if ( $min > 0 || $max > 0 ) : ?>
                        <p>
                          <?php
                          if ( $min > 0 && $max > 0 ) {
                            echo esc_html( sprintf( __( 'Budget: $%1$s – $%2$s', 'hello-elementor-child' ), number_format_i18n( $min ), number_format_i18n( $max ) ) );
                          } elseif ( $min > 0 ) {
                            echo esc_html( sprintf( __( 'Budget: from $%s', 'hello-elementor-child' ), number_format_i18n( $min ) ) );
                          } else {
                            echo esc_html( sprintf( __( 'Budget: up to $%s', 'hello-elementor-child' ), number_format_i18n( $max ) ) );
                          }
                          ?>
                        </p>
                      <?php endif; ?>
                    </article>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    </div>
  </section>
</main>

<?php
get_footer();
