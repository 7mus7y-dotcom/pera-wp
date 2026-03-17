<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active_view = isset( $args['active_view'] ) ? sanitize_key( (string) $args['active_view'] ) : '';
$can_view_logs = current_user_can( 'manage_options' ) || ( function_exists( 'peracrm_admin_user_can_reassign' ) && peracrm_admin_user_can_reassign() );
$theme_icons = trailingslashit( get_stylesheet_directory_uri() ) . 'logos-icons/icons.svg';

$items = array(
	'overview' => array( 'label' => __( 'Overview', 'peracrm' ), 'url' => home_url( '/crm/' ) ),
	'clients' => array( 'label' => __( 'Clients', 'peracrm' ), 'url' => home_url( '/crm/clients/' ) ),
	'tasks' => array( 'label' => __( 'Tasks', 'peracrm' ), 'url' => home_url( '/crm/tasks/' ) ),
	'pipeline' => array( 'label' => __( 'Pipeline', 'peracrm' ), 'url' => home_url( '/crm/pipeline/' ) ),
);
if ( $can_view_logs ) {
	$items['whatsapp_logs'] = array( 'label' => __( 'WhatsApp logs', 'peracrm' ), 'url' => home_url( '/crm/whatsapp-logs/' ) );
	$items['email_logs'] = array( 'label' => __( 'Email logs', 'peracrm' ), 'url' => home_url( '/crm/email-logs/' ) );
}
?>
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
