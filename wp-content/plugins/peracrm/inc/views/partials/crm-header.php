<?php
/**
 * CRM shared page header.
 *
 * @var array $args
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title               = isset( $args['title'] ) ? (string) $args['title'] : __( 'CRM', 'peracrm' );
$description         = isset( $args['description'] ) ? (string) $args['description'] : '';
$meta                = isset( $args['meta'] ) ? (string) $args['meta'] : '';
$show_client_filters = ! empty( $args['show_client_filters'] );
$stages              = is_array( $args['stages'] ?? null ) ? $args['stages'] : array();
$advisors            = is_array( $args['advisors'] ?? null ) ? $args['advisors'] : array();
$clients_type_view   = isset( $args['clients_type_view'] ) ? sanitize_key( (string) $args['clients_type_view'] ) : 'leads';
$actions             = is_array( $args['actions'] ?? null ) ? $args['actions'] : array();
$toolbar_content     = isset( $args['toolbar_content'] ) ? (string) $args['toolbar_content'] : '';
$filter_q            = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) : '';
$filter_stage        = isset( $_GET['stage'] ) ? sanitize_key( wp_unslash( (string) $_GET['stage'] ) ) : '';
$filter_advisor      = isset( $_GET['advisor'] ) ? absint( wp_unslash( (string) $_GET['advisor'] ) ) : 0;

$impersonation_ui    = function_exists( 'peracrm_get_impersonation_ui_state' ) ? peracrm_get_impersonation_ui_state() : array();
$can_impersonate     = ! empty( $impersonation_ui['can_impersonate'] );
$is_impersonating    = ! empty( $impersonation_ui['is_impersonating'] );
$impersonation_label = isset( $impersonation_ui['effective_user_label'] ) ? (string) $impersonation_ui['effective_user_label'] : '';
$real_user_label     = isset( $impersonation_ui['real_user_label'] ) ? (string) $impersonation_ui['real_user_label'] : '';
$impersonation_targets = is_array( $impersonation_ui['targets'] ?? null ) ? $impersonation_ui['targets'] : array();
$impersonation_action_url = isset( $impersonation_ui['admin_post_url'] ) ? (string) $impersonation_ui['admin_post_url'] : '';
$selected_impersonation_user = isset( $impersonation_ui['effective_user_id'] ) ? (int) $impersonation_ui['effective_user_id'] : 0;

$has_toolbar = $show_client_filters || '' !== $toolbar_content;
?>
<section class="crm-page-header" aria-label="<?php echo esc_attr__( 'CRM page header', 'peracrm' ); ?>">
  <div class="container crm-page-header__inner">
    <div class="crm-page-header__main">
      <div class="crm-page-header__identity">
        <h1 class="crm-page-header__title"><?php echo esc_html( $title ); ?></h1>
        <?php if ( '' !== $meta || '' !== $description ) : ?>
        <div class="crm-page-header__meta">
          <?php if ( '' !== $meta ) : ?>
          <p class="crm-page-header__context"><?php echo esc_html( $meta ); ?></p>
          <?php endif; ?>
          <?php if ( '' !== $description ) : ?>
          <p class="crm-page-header__subtitle"><?php echo esc_html( $description ); ?></p>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <?php if ( ! empty( $actions ) ) : ?>
      <div class="crm-action-group" aria-label="<?php echo esc_attr__( 'Page actions', 'peracrm' ); ?>">
        <?php foreach ( $actions as $action ) : ?>
          <?php
          $action_url   = isset( $action['url'] ) ? (string) $action['url'] : '';
          $action_label = isset( $action['label'] ) ? (string) $action['label'] : '';
          $action_class = isset( $action['class'] ) ? (string) $action['class'] : 'btn btn--ghost btn--blue';
          $action_type  = isset( $action['type'] ) ? sanitize_key( (string) $action['type'] ) : 'secondary';
          $action_attr  = isset( $action['attributes'] ) ? (string) $action['attributes'] : '';

          if ( '' === $action_url || '' === $action_label ) {
            continue;
          }
          ?>
          <a class="<?php echo esc_attr( trim( $action_class ) ); ?> crm-action-group__item crm-action-group__item--<?php echo esc_attr( $action_type ); ?>" href="<?php echo esc_url( $action_url ); ?>"<?php echo '' !== $action_attr ? ' ' . $action_attr : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $action_label ); ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <?php if ( $can_impersonate || $is_impersonating ) : ?>
    <div class="pera-crm-viewing-as-banner" role="status" aria-live="polite">
      <div class="pera-crm-viewing-as-banner__inner">
        <div class="pera-crm-viewing-as-banner__meta">
          <strong><?php echo esc_html( sprintf( $is_impersonating ? __( 'Viewing as: %s', 'peracrm' ) : __( 'Current view: %s', 'peracrm' ), $impersonation_label ) ); ?></strong>
          <?php if ( $is_impersonating && '' !== $real_user_label ) : ?>
            <span><?php echo esc_html( sprintf( __( 'Signed in as: %s', 'peracrm' ), $real_user_label ) ); ?></span>
          <?php endif; ?>
        </div>

        <?php if ( $can_impersonate && '' !== $impersonation_action_url ) : ?>
        <div class="pera-crm-viewing-as-banner__actions">
          <form method="post" action="<?php echo esc_url( $impersonation_action_url ); ?>" class="peracrm-impersonation-switcher">
            <input type="hidden" name="action" value="peracrm_set_view_as_advisor">
            <?php wp_nonce_field( 'peracrm_set_view_as_advisor', 'peracrm_view_as_nonce' ); ?>
            <label class="screen-reader-text" for="peracrm-view-as-user-id"><?php esc_html_e( 'Select advisor view', 'peracrm' ); ?></label>
            <select id="peracrm-view-as-user-id" name="peracrm_view_as_user_id" class="crm-search-control peracrm-impersonation-switcher__select">
              <option value="0"><?php esc_html_e( 'My view', 'peracrm' ); ?></option>
              <?php foreach ( $impersonation_targets as $target ) : ?>
                <?php
                $target_id = isset( $target['id'] ) ? (int) $target['id'] : 0;
                $target_name = isset( $target['display_name'] ) ? (string) $target['display_name'] : '';
                if ( $target_id <= 0 || '' === $target_name ) {
                  continue;
                }
                ?>
                <option value="<?php echo esc_attr( (string) $target_id ); ?>" <?php selected( $selected_impersonation_user, $target_id ); ?>><?php echo esc_html( $target_name ); ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn--solid btn--blue"><?php esc_html_e( 'Switch view', 'peracrm' ); ?></button>
          </form>

          <?php if ( $is_impersonating ) : ?>
          <form method="post" action="<?php echo esc_url( $impersonation_action_url ); ?>" class="peracrm-impersonation-reset">
            <input type="hidden" name="action" value="peracrm_clear_view_as_advisor">
            <?php wp_nonce_field( 'peracrm_clear_view_as_advisor', 'peracrm_view_as_nonce' ); ?>
            <button type="submit" class="btn btn--ghost btn--white"><?php esc_html_e( 'Return to my view', 'peracrm' ); ?></button>
          </form>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ( $has_toolbar ) : ?>
    <div class="crm-toolbar">
      <?php if ( $show_client_filters ) : ?>
      <form method="get" action="<?php echo esc_url( home_url( '/crm/clients/' ) ); ?>" class="crm-client-filters" aria-label="<?php echo esc_attr__( 'Client filters', 'peracrm' ); ?>">
        <input type="hidden" name="type" value="<?php echo esc_attr( $clients_type_view ); ?>">
        <div class="crm-toolbar__row crm-client-filters__grid">
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

          <div class="crm-action-group crm-action-group--toolbar crm-client-filters__actions">
            <button type="submit" class="btn btn--solid btn--blue crm-action-group__item crm-action-group__item--primary"><?php esc_html_e( 'Apply filters', 'peracrm' ); ?></button>
            <a class="btn btn--ghost btn--blue crm-action-group__item crm-action-group__item--secondary" href="<?php echo esc_url( home_url( '/crm/clients/' ) ); ?>"><?php esc_html_e( 'Clear', 'peracrm' ); ?></a>
          </div>
        </div>
      </form>
      <?php endif; ?>

      <?php if ( '' !== $toolbar_content ) : ?>
        <?php echo $toolbar_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</section>
