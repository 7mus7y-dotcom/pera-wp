<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active_view = isset( $args['active_view'] ) ? sanitize_key( (string) $args['active_view'] ) : '';
$can_view_logs = function_exists( 'peracrm_can_view_operational_logs' )
	? peracrm_can_view_operational_logs()
	: ( current_user_can( 'manage_options' ) || ( function_exists( 'peracrm_admin_user_can_reassign' ) && peracrm_admin_user_can_reassign() ) );
$theme_icons = trailingslashit( get_stylesheet_directory_uri() ) . 'logos-icons/icons.svg';

$items = array(
	'overview' => array( 'label' => __( 'Overview', 'peracrm' ), 'url' => home_url( '/crm/' ) ),
	'clients' => array( 'label' => __( 'Clients', 'peracrm' ), 'url' => home_url( '/crm/clients/' ) ),
	'tasks' => array( 'label' => __( 'Tasks', 'peracrm' ), 'url' => home_url( '/crm/tasks/' ) ),
	'pipeline' => array( 'label' => __( 'Pipeline', 'peracrm' ), 'url' => home_url( '/crm/pipeline/' ) ),
	'create_lead' => array( 'label' => __( 'Create lead', 'peracrm' ), 'url' => home_url( '/crm/new/' ) ),
);
if ( $can_view_logs ) {
	$items['whatsapp_logs'] = array( 'label' => __( 'WhatsApp logs', 'peracrm' ), 'url' => home_url( '/crm/whatsapp-logs/' ) );
	$items['email_logs'] = array( 'label' => __( 'Email logs', 'peracrm' ), 'url' => home_url( '/crm/email-logs/' ) );
}
?>
<div class="crm-nav-shell" data-crm-nav>
  <div class="crm-side-nav__overlay" data-crm-nav-overlay hidden></div>

  <aside class="crm-side-nav" aria-label="<?php echo esc_attr__( 'CRM navigation', 'peracrm' ); ?>">
    <p class="crm-side-nav__title">
      <svg class="icon" aria-hidden="true"><use href="<?php echo esc_url( $theme_icons . '#icon-bars' ); ?>"></use></svg>
      <?php esc_html_e( 'Menu', 'peracrm' ); ?>
    </p>
    <ul class="crm-side-nav__list">
      <?php foreach ( $items as $key => $item ) : ?>
        <li><a class="<?php echo esc_attr( $active_view === $key ? 'is-active' : '' ); ?>" href="<?php echo esc_url( (string) $item['url'] ); ?>"><?php echo esc_html( (string) $item['label'] ); ?></a></li>
      <?php endforeach; ?>
    </ul>
  </aside>

  <aside class="crm-side-nav crm-side-nav--drawer" id="crm-side-nav-drawer" aria-label="<?php echo esc_attr__( 'CRM navigation', 'peracrm' ); ?>" data-crm-nav-drawer hidden>
    <div class="crm-side-nav__drawer-header">
      <p class="crm-side-nav__title">
        <svg class="icon" aria-hidden="true"><use href="<?php echo esc_url( $theme_icons . '#icon-bars' ); ?>"></use></svg>
        <?php esc_html_e( 'Menu', 'peracrm' ); ?>
      </p>
      <button type="button" class="btn btn--ghost btn--blue crm-side-nav__close" data-crm-nav-close aria-label="<?php esc_attr_e( 'Close menu', 'peracrm' ); ?>">&times;</button>
    </div>
    <ul class="crm-side-nav__list">
      <?php foreach ( $items as $key => $item ) : ?>
        <li><a class="<?php echo esc_attr( $active_view === $key ? 'is-active' : '' ); ?>" href="<?php echo esc_url( (string) $item['url'] ); ?>"><?php echo esc_html( (string) $item['label'] ); ?></a></li>
      <?php endforeach; ?>
    </ul>
  </aside>
</div>
