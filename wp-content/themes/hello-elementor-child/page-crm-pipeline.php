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
                    $lead_source = (string) ( $item['lead_source'] ?? '' );
                    $min     = (int) ( $item['budget_min'] ?? 0 );
                    $max     = (int) ( $item['budget_max'] ?? 0 );
                    ?>
                    <article class="crm-pipeline-item">
                      <h3><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title !== '' ? $title : __( '(no title)', 'hello-elementor-child' ) ); ?></a></h3>

                      <div class="crm-pipeline-item-meta" aria-label="<?php echo esc_attr__( 'Lead details', 'hello-elementor-child' ); ?>">
                        <?php if ( $lead_source !== '' ) : ?>
                          <span class="pill pill--outline"><?php echo esc_html( sprintf( __( 'Source: %s', 'hello-elementor-child' ), $lead_source ) ); ?></span>
                        <?php endif; ?>

                        <?php if ( $min > 0 || $max > 0 ) : ?>
                          <span class="pill pill--outline">
                            <?php
                            if ( $min > 0 && $max > 0 ) {
                              echo esc_html( sprintf( __( 'Budget: $%1$s – $%2$s', 'hello-elementor-child' ), number_format_i18n( $min ), number_format_i18n( $max ) ) );
                            } elseif ( $min > 0 ) {
                              echo esc_html( sprintf( __( 'Budget: from $%s', 'hello-elementor-child' ), number_format_i18n( $min ) ) );
                            } else {
                              echo esc_html( sprintf( __( 'Budget: up to $%s', 'hello-elementor-child' ), number_format_i18n( $max ) ) );
                            }
                            ?>
                          </span>
                        <?php endif; ?>
                      </div>

                      <div class="hero-pills">
                        <?php if ( $advisor !== '' ) : ?>
                          <span class="pill pill--outline"><?php echo esc_html( sprintf( __( 'Advisor: %s', 'hello-elementor-child' ), $advisor ) ); ?></span>
                        <?php endif; ?>
                        <?php if ( $last !== '' ) : ?>
                          <span class="pill pill--outline"><?php echo esc_html( sprintf( __( 'Last activity: %s', 'hello-elementor-child' ), $last ) ); ?></span>
                        <?php endif; ?>
                      </div>
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
