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
$total_items  = 0;

foreach ( $columns as $column_data ) {
	$total_items += (int) ( $column_data['count'] ?? 0 );
}

peracrm_frontend_render_shell_header();
?>

<main id="primary" class="site-main crm-page crm-page--pipeline">
  <?php
  if ( function_exists( 'peracrm_frontend_render_partial' ) ) {
	  peracrm_frontend_render_partial(
		  'crm-header',
		  array(
			  'title'       => __( 'Pipeline', 'peracrm' ),
			  'description' => __( 'Manage leads and clients by stage', 'peracrm' ),
			  'meta'        => sprintf(
				  /* translators: 1: number of stages, 2: number of records. */
				  __( '%1$d stages · %2$d records', 'peracrm' ),
				  count( $columns ),
				  $total_items
			  ),
			  'actions'     => array(
				array(
					'label' => __( 'Create lead', 'peracrm' ),
					'url'   => home_url( '/crm/new/' ),
					'class' => 'btn btn--solid btn--blue',
					'type'  => 'primary',
				),
			  ),
			  'active_view' => 'pipeline',
		  )
	  );
  }
  ?>

  <section class="content-panel content-panel--overlap-hero">
    <div class="content-panel-box border-dm crm-layout">
      <div class="crm-layout__main">
      <section class="crm-pipeline-board-wrap" aria-label="<?php echo esc_attr__( 'Pipeline board', 'peracrm' ); ?>">
        <div class="crm-pipeline-board">
          <?php foreach ( $columns as $column ) : ?>
            <?php
            $label            = (string) ( $column['label'] ?? '' );
            $count            = (int) ( $column['count'] ?? 0 );
            $items            = is_array( $column['items'] ?? null ) ? $column['items'] : array();
            $has_items        = ! empty( $items );
            $descriptor       = 1 === $count ? __( '1 record in lane', 'peracrm' ) : sprintf( __( '%d records in lane', 'peracrm' ), $count );
            ?>
            <article class="crm-pipeline-column" aria-labelledby="crm-pipeline-stage-<?php echo esc_attr( sanitize_title( $label ) ); ?>">
              <header class="crm-pipeline-column-header">
                <div class="crm-pipeline-column-header__identity">
                  <h2 id="crm-pipeline-stage-<?php echo esc_attr( sanitize_title( $label ) ); ?>"><?php echo esc_html( $label ); ?></h2>
                  <p class="crm-pipeline-column-header__meta"><?php echo esc_html( $descriptor ); ?></p>
                </div>
                <span class="crm-chip crm-chip--neutral"><?php echo esc_html( (string) $count ); ?></span>
              </header>

              <div class="crm-pipeline-items">
                <?php if ( ! $has_items ) : ?>
                  <p class="crm-pipeline-empty"><?php esc_html_e( 'No clients in this stage.', 'peracrm' ); ?></p>
                <?php else : ?>
                  <?php foreach ( $items as $item ) : ?>
                    <?php
                    $title        = (string) ( $item['title'] ?? '' );
                    $url          = (string) ( $item['client_url'] ?? '' );
                    $advisor      = (string) ( $item['advisor_label'] ?? '' );
                    $last         = (string) ( $item['last_activity'] ?? '' );
                    $lead_source  = (string) ( $item['lead_source'] ?? '' );
                    $min          = (int) ( $item['budget_min'] ?? 0 );
                    $max          = (int) ( $item['budget_max'] ?? 0 );
                    $identity     = $title !== '' ? $title : __( '(no title)', 'peracrm' );
                    $key_context  = array();
                    $next_step    = '';
                    $state_chip   = '';
                    $budget_label = '';

                    if ( $lead_source !== '' ) {
						$key_context[] = sprintf( __( 'Source: %s', 'peracrm' ), $lead_source );
                    }

                    if ( $min > 0 || $max > 0 ) {
						if ( $min > 0 && $max > 0 ) {
							$budget_label = sprintf( __( '$%1$s – $%2$s', 'peracrm' ), number_format_i18n( $min ), number_format_i18n( $max ) );
						} elseif ( $min > 0 ) {
							$budget_label = sprintf( __( 'From $%s', 'peracrm' ), number_format_i18n( $min ) );
						} else {
							$budget_label = sprintf( __( 'Up to $%s', 'peracrm' ), number_format_i18n( $max ) );
						}

						$key_context[] = sprintf( __( 'Budget: %s', 'peracrm' ), $budget_label );
                    }

                    if ( '' === $advisor ) {
						$state_chip = __( 'Needs owner', 'peracrm' );
						$next_step  = __( 'Assign an advisor to move this stage forward.', 'peracrm' );
                    } elseif ( '' === $last ) {
						$state_chip = __( 'No activity logged', 'peracrm' );
						$next_step  = sprintf( __( 'Advisor: %s · log the next touchpoint.', 'peracrm' ), $advisor );
                    } else {
						$next_step = sprintf( __( 'Advisor: %1$s · last activity %2$s.', 'peracrm' ), $advisor, $last );
                    }

                    if ( '' === $next_step && '' !== $last ) {
						$next_step = sprintf( __( 'Last activity: %s', 'peracrm' ), $last );
                    }
                    ?>
                    <article class="crm-pipeline-item">
                      <div class="crm-pipeline-item__topline">
                        <h3><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $identity ); ?></a></h3>
                        <?php if ( '' !== $state_chip ) : ?>
                          <span class="crm-chip crm-chip--status"><?php echo esc_html( $state_chip ); ?></span>
                        <?php endif; ?>
                      </div>

                      <?php if ( ! empty( $key_context ) ) : ?>
                        <div class="crm-pipeline-item__context" aria-label="<?php echo esc_attr__( 'Lead context', 'peracrm' ); ?>">
                          <?php foreach ( array_slice( $key_context, 0, 2 ) as $context_line ) : ?>
                            <p class="crm-pipeline-item__context-line"><?php echo esc_html( $context_line ); ?></p>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>

                      <div class="crm-pipeline-item-meta" aria-label="<?php echo esc_attr__( 'Lead details', 'peracrm' ); ?>">
                        <?php if ( $advisor !== '' ) : ?>
                          <p class="crm-meta-line"><strong><?php esc_html_e( 'Advisor', 'peracrm' ); ?></strong><span><?php echo esc_html( $advisor ); ?></span></p>
                        <?php endif; ?>
                        <?php if ( $last !== '' ) : ?>
                          <p class="crm-meta-line"><strong><?php esc_html_e( 'Last activity', 'peracrm' ); ?></strong><span><?php echo esc_html( $last ); ?></span></p>
                        <?php endif; ?>
                      </div>

                      <?php if ( '' !== $next_step ) : ?>
                        <p class="crm-pipeline-item__next-step"><?php echo esc_html( $next_step ); ?></p>
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
      <?php if ( function_exists( 'peracrm_frontend_render_partial' ) ) { peracrm_frontend_render_partial( 'crm-side-nav', array( 'active_view' => 'pipeline' ) ); } ?>
    </div>
  </section>
</main>

<?php
peracrm_frontend_render_shell_footer();
