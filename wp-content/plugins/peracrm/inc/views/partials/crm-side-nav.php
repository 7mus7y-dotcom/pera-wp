<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active_view = isset( $args['active_view'] ) ? sanitize_key( (string) $args['active_view'] ) : '';
$can_view_logs = function_exists( 'peracrm_can_view_operational_logs' )
	? peracrm_can_view_operational_logs()
	: ( current_user_can( 'manage_options' ) || ( function_exists( 'peracrm_admin_user_can_reassign' ) && peracrm_admin_user_can_reassign() ) );
$site_name = (string) get_bloginfo( 'name' );
$logo_id = (int) get_theme_mod( 'custom_logo' );
$custom_logo_html = '';

if ( $logo_id > 0 ) {
	$custom_logo_html = wp_get_attachment_image( $logo_id, 'full', false, array( 'class' => 'custom-logo pera-site-logo-image peracrm-shell-logo-image', 'alt' => $site_name, 'loading' => 'lazy' ) );
}

$plugin_logo_path = trailingslashit( PERACRM_PATH ) . 'logos-icons/pera-logo.svg';
$plugin_logo_url = trailingslashit( PERACRM_URL ) . 'logos-icons/pera-logo.svg';
$has_plugin_logo = file_exists( $plugin_logo_path );

$items = array(
	'overview' => array( 'label' => __( 'Overview', 'peracrm' ), 'url' => home_url( '/crm/' ) ),
	'clients' => array( 'label' => __( 'Clients', 'peracrm' ), 'url' => home_url( '/crm/clients/' ) ),
	'tasks' => array( 'label' => __( 'Tasks', 'peracrm' ), 'url' => home_url( '/crm/tasks/' ) ),
	'pipeline' => array( 'label' => __( 'Pipeline', 'peracrm' ), 'url' => home_url( '/crm/pipeline/' ) ),
	'create_lead' => array( 'label' => __( 'Create lead', 'peracrm' ), 'url' => home_url( '/crm/new/' ) ),
);
if ( $can_view_logs ) {
	$items['whatsapp_logs'] = array( 'label' => __( 'WhatsApp logs', 'peracrm' ), 'url' => home_url( '/crm/whatsapp-logs/' ) );
	$items['email_logs']    = array( 'label' => __( 'Email logs', 'peracrm' ), 'url' => home_url( '/crm/email-logs/' ) );
}
?>
<div class="crm-nav-shell" data-crm-nav>
  <div class="crm-side-nav__overlay" data-crm-nav-overlay hidden></div>

  <aside class="crm-side-nav" aria-label="<?php echo esc_attr__( 'CRM navigation', 'peracrm' ); ?>">
    <p class="crm-side-nav__title"><?php esc_html_e( 'Menu', 'peracrm' ); ?></p>
    <ul class="crm-side-nav__list">
      <?php foreach ( $items as $key => $item ) : ?>
        <li><a class="<?php echo esc_attr( $active_view === $key ? 'is-active' : '' ); ?>" href="<?php echo esc_url( (string) $item['url'] ); ?>"><?php echo esc_html( (string) $item['label'] ); ?></a></li>
      <?php endforeach; ?>
    </ul>
  </aside>

  <aside class="crm-side-nav crm-side-nav--drawer" id="crm-side-nav-drawer" aria-label="<?php echo esc_attr__( 'CRM navigation', 'peracrm' ); ?>" data-crm-nav-drawer hidden tabindex="-1">
    <div class="crm-side-nav__drawer-inner">
      <div class="crm-side-nav__drawer-header">
        <a class="crm-side-nav__drawer-brand" href="<?php echo esc_url( home_url( '/crm/' ) ); ?>">
          <?php if ( '' !== $custom_logo_html ) : ?>
            <?php echo $custom_logo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
          <?php elseif ( $has_plugin_logo ) : ?>
            <img src="<?php echo esc_url( $plugin_logo_url ); ?>" class="pera-site-logo-image peracrm-shell-logo-image" alt="<?php echo esc_attr( $site_name ); ?>" loading="lazy">
          <?php else : ?>
            <span class="crm-side-nav__drawer-brand-text"><?php echo esc_html( $site_name ); ?></span>
          <?php endif; ?>
        </a>
        <button type="button" class="crm-side-nav__close" data-crm-nav-close aria-label="<?php esc_attr_e( 'Close menu', 'peracrm' ); ?>">
          <span aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
              <path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.85"></path>
            </svg>
          </span>
        </button>
      </div>

      <div class="crm-side-nav__drawer-content">
        <div class="crm-side-nav__drawer-section crm-side-nav__drawer-section--nav">
          <p class="crm-side-nav__eyebrow"><?php esc_html_e( 'CRM workspace', 'peracrm' ); ?></p>
          <ul class="crm-side-nav__list crm-side-nav__list--drawer">
            <?php foreach ( $items as $key => $item ) : ?>
              <li><a class="<?php echo esc_attr( $active_view === $key ? 'is-active' : '' ); ?>" href="<?php echo esc_url( (string) $item['url'] ); ?>"><?php echo esc_html( (string) $item['label'] ); ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>

        <div class="crm-side-nav__drawer-section crm-side-nav__drawer-section--utility" aria-label="<?php echo esc_attr__( 'CRM utility links', 'peracrm' ); ?>">
          <p class="crm-side-nav__eyebrow"><?php esc_html_e( 'Quick access', 'peracrm' ); ?></p>
          <div class="crm-side-nav__utility-list">
            <a href="<?php echo esc_url( home_url( '/crm/' ) ); ?>"><?php esc_html_e( 'Dashboard home', 'peracrm' ); ?></a>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Return to website', 'peracrm' ); ?></a>
          </div>
        </div>
      </div>
    </div>
  </aside>
</div>
