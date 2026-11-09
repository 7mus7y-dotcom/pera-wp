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

// Static outline artwork shared by the desktop and off-canvas navigation.
$nav_icons = array(
	'overview' => '<path d="m3 10 9-7 9 7v11h-6v-7H9v7H3z"/>',
	'clients' => '<circle cx="9" cy="7" r="4"/><path d="M2 21v-2a7 7 0 0 1 14 0v2M16 3a4 4 0 0 1 0 8m3 3a6 6 0 0 1 3 5v2"/>',
	'tasks' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
	'appointments' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 11h18"/>',
	'pipeline' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18m6-18v18M5 7h2m4 4h2m4 4h2"/>',
	'performance' => '<path d="M3 3v18h18M7 17v-5m5 5V8m5 9V4"/>',
	'campaigns' => '<path d="m3 9 17-6v18L3 15V9zm5 8 2 5H6l-2-6M20 9h2m-2 6h2"/>',
	'subscribers' => '<circle cx="9" cy="7" r="4"/><path d="M2 21v-2a7 7 0 0 1 14 0v2m3-14v8m-4-4h8"/>',
	'whatsapp_logs' => '<path d="M21 12a9 9 0 0 1-9 9 10 10 0 0 1-4-1l-5 1 1-5a10 10 0 0 1-1-4 9 9 0 0 1 18 0Z M7 10h10M7 14h7"/>',
	'email_logs' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
);

$items = array(
	'overview' => array( 'label' => __( 'Overview', 'peracrm' ), 'url' => home_url( '/crm/' ) ),
	'clients' => array( 'label' => __( 'Clients', 'peracrm' ), 'url' => home_url( '/crm/clients/' ) ),
	'tasks' => array( 'label' => __( 'Tasks', 'peracrm' ), 'url' => home_url( '/crm/tasks/' ) ),
	'appointments' => array( 'label' => __( 'Appointments', 'peracrm' ), 'url' => home_url( '/crm/appointments/' ) ),
	'pipeline' => array( 'label' => __( 'Pipeline', 'peracrm' ), 'url' => home_url( '/crm/pipeline/' ) ),
	'performance' => array( 'label' => __( 'Performance', 'peracrm' ), 'url' => home_url( '/crm/performance/' ) ),
);
if ( function_exists('peracrm_campaigns_staff_can') && peracrm_campaigns_staff_can('peracrm_manage_campaigns') ) {
    $items['campaigns'] = array('label' => __('Campaigns', 'peracrm'), 'url' => peracrm_campaign_drafts_url());
}
if ( function_exists('peracrm_campaigns_staff_can') && peracrm_campaigns_staff_can('peracrm_manage_subscribers') ) {
    $items['subscribers'] = array('label' => __('Subscribers', 'peracrm'), 'url' => peracrm_subscribers_url());
}
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
        <li>
          <a class="<?php echo esc_attr( $active_view === $key ? 'is-active' : '' ); ?>" href="<?php echo esc_url( (string) $item['url'] ); ?>">
            <svg class="crm-side-nav__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><?php echo $nav_icons[$key]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG artwork defined above. ?></svg>
            <span class="crm-side-nav__label"><?php echo esc_html( (string) $item['label'] ); ?></span>
          </a>
        </li>
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
              <li>
                <a class="<?php echo esc_attr( $active_view === $key ? 'is-active' : '' ); ?>" href="<?php echo esc_url( (string) $item['url'] ); ?>">
                  <svg class="crm-side-nav__icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><?php echo $nav_icons[$key]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG artwork defined above. ?></svg>
                  <span class="crm-side-nav__label"><?php echo esc_html( (string) $item['label'] ); ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>

        <div class="crm-side-nav__drawer-section crm-side-nav__drawer-section--utility" aria-label="<?php echo esc_attr__( 'CRM utility links', 'peracrm' ); ?>">
          <p class="crm-side-nav__eyebrow"><?php esc_html_e( 'Quick access', 'peracrm' ); ?></p>
          <div class="crm-side-nav__utility-list">
            <a class="btn btn--ghost crm-side-nav__utility-action" href="<?php echo esc_url( home_url( '/crm/new/' ) ); ?>"><?php esc_html_e( 'Create lead', 'peracrm' ); ?></a>
            <a class="btn btn--ghost crm-side-nav__utility-action" href="<?php echo esc_url( home_url( '/crm/' ) ); ?>"><?php esc_html_e( 'Dashboard home', 'peracrm' ); ?></a>
            <a class="btn btn--ghost crm-side-nav__utility-action" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Return to website', 'peracrm' ); ?></a>
          </div>
        </div>
      </div>
    </div>
  </aside>
</div>
