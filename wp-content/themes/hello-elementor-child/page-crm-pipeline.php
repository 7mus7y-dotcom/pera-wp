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

get_header();
?>

<main id="primary" class="site-main crm-page crm-page--pipeline">
  <section class="hero hero--left hero--fit" id="crm-hero">
    <div class="hero-content container">
      <h1><?php echo esc_html__( 'Pipeline', 'hello-elementor-child' ); ?></h1>
      <p class="lead"><?php echo esc_html__( 'Manage leads and clients by stage', 'hello-elementor-child' ); ?></p>
      <div class="hero-actions hero-pills">
        <a class="pill pill--outline" href="<?php echo esc_url( home_url( '/crm/' ) ); ?>"><?php echo esc_html__( 'Back to CRM', 'hello-elementor-child' ); ?></a>
      </div>
    </div>
  </section>

  <section class="content-panel content-panel--overlap-hero">
    <div class="content-panel-box border-dm">
      <article class="card-shell crm-pipeline-filters">
        <div class="crm-pipeline-filters-grid grid-3">
          <label>
            <span class="screen-reader-text"><?php esc_html_e( 'Search clients', 'hello-elementor-child' ); ?></span>
            <input type="search" placeholder="<?php echo esc_attr__( 'Search (coming soon)', 'hello-elementor-child' ); ?>" disabled>
          </label>
          <label>
            <span class="screen-reader-text"><?php esc_html_e( 'Stage', 'hello-elementor-child' ); ?></span>
            <select disabled>
              <option><?php esc_html_e( 'All stages', 'hello-elementor-child' ); ?></option>
            </select>
          </label>
          <?php if ( $can_view_all ) : ?>
          <label>
            <span class="screen-reader-text"><?php esc_html_e( 'Advisor', 'hello-elementor-child' ); ?></span>
            <select disabled>
              <option><?php esc_html_e( 'All advisors', 'hello-elementor-child' ); ?></option>
            </select>
          </label>
          <?php endif; ?>
        </div>
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
