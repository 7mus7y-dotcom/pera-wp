<?php
/**
 * Template Name: Client Portal
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! is_user_logged_in() ) {
    $target = function_exists( 'pera_client_portal_get_login_redirect_target' )
        ? pera_client_portal_get_login_redirect_target()
        : wp_validate_redirect( home_url( '/client-login/' ), home_url( '/client-login/' ) );

    wp_safe_redirect( $target );
    exit;
}

$client_login_asset = '/css/client-login.css';
$client_login_ver   = function_exists( 'pera_get_asset_version' )
    ? pera_get_asset_version( $client_login_asset )
    : (string) filemtime( get_stylesheet_directory() . $client_login_asset );

wp_enqueue_style(
    'pera-client-login',
    get_stylesheet_directory_uri() . $client_login_asset,
    array(),
    $client_login_ver
);

$background_image = function_exists( 'pera_get_login_background_image_url' )
    ? pera_get_login_background_image_url()
    : '';

$current_user = wp_get_current_user();
$user_id      = (int) $current_user->ID;

$first_name = (string) get_user_meta( $user_id, 'first_name', true );
$last_name  = (string) get_user_meta( $user_id, 'last_name', true );
$phone      = (string) get_user_meta( $user_id, 'phone', true );

$client_id = (int) get_user_meta( $user_id, 'crm_client_id', true );
$profile   = function_exists( 'peracrm_client_get_profile' ) && $client_id > 0
    ? peracrm_client_get_profile( $client_id )
    : array();

$preferred_contact = isset( $profile['preferred_contact'] ) ? (string) $profile['preferred_contact'] : '';
$budget_min_usd    = isset( $profile['budget_min_usd'] ) ? (string) $profile['budget_min_usd'] : '';
$budget_max_usd    = isset( $profile['budget_max_usd'] ) ? (string) $profile['budget_max_usd'] : '';

if ( '' === $phone && isset( $profile['phone'] ) ) {
    $phone = (string) $profile['phone'];
}

$updated = isset( $_GET['updated'] ) && '1' === sanitize_key( wp_unslash( $_GET['updated'] ) );

?><!doctype html>
<html <?php language_attributes(); ?> class="client-login-standalone-html">
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class( 'client-login-standalone client-portal-standalone' ); ?>>
<?php wp_body_open(); ?>
<div class="client-login-wrapper">
    <main id="primary" class="site-main">
        <section class="client-login-section">
            <div class="client-login-shell client-portal-shell">
                <div class="client-login-branding">
                    <?php
                    if ( function_exists( 'pera_get_site_logo_markup' ) ) {
                        echo pera_get_site_logo_markup(
                            array(
                                'link_class' => 'site-logo logo-pera',
                                'aria_label' => 'Pera Property',
                                'title'      => 'Pera Property',
                                'home_url'   => home_url( '/' ),
                                'show_since' => true,
                            )
                        );
                    } else {
                        ?>
                        <a class="site-logo logo-pera" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'Pera Property', 'hello-elementor-child' ); ?>" title="<?php esc_attr_e( 'Pera Property', 'hello-elementor-child' ); ?>">
                            <span class="site-logo__mark" aria-hidden="true">
                                <img class="pera-site-logo-image" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/logo-white.svg' ); ?>" alt="" width="200" loading="eager">
                            </span>
                            <span class="site-logo__since"><?php esc_html_e( 'SINCE 2016', 'hello-elementor-child' ); ?></span>
                        </a>
                        <?php
                    }
                    ?>
                </div>

                <div class="client-login-container client-portal-container">
                    <h1 class="client-login-title"><?php esc_html_e( 'My Account', 'hello-elementor-child' ); ?></h1>
                    <p class="client-login-subtitle"><?php esc_html_e( 'Manage your profile and preferences for property updates.', 'hello-elementor-child' ); ?></p>

                    <?php if ( $updated ) : ?>
                        <div class="client-login-success" role="status">
                            <?php esc_html_e( 'Your profile has been updated.', 'hello-elementor-child' ); ?>
                        </div>
                    <?php endif; ?>

                    <form id="pera-client-portal-form" method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="pera_client_portal_update_profile">
                        <?php wp_nonce_field( 'pera_client_portal_update', 'pera_client_portal_nonce' ); ?>

                        <p>
                            <label for="first_name"><?php esc_html_e( 'First name', 'hello-elementor-child' ); ?></label>
                            <input type="text" id="first_name" name="first_name" value="<?php echo esc_attr( $first_name ); ?>" required>
                        </p>

                        <p>
                            <label for="last_name"><?php esc_html_e( 'Last name', 'hello-elementor-child' ); ?></label>
                            <input type="text" id="last_name" name="last_name" value="<?php echo esc_attr( $last_name ); ?>" required>
                        </p>

                        <p>
                            <label for="email"><?php esc_html_e( 'Email', 'hello-elementor-child' ); ?></label>
                            <input type="email" id="email" value="<?php echo esc_attr( $current_user->user_email ); ?>" disabled>
                        </p>

                        <p>
                            <label for="phone"><?php esc_html_e( 'Phone', 'hello-elementor-child' ); ?></label>
                            <input type="text" id="phone" name="phone" value="<?php echo esc_attr( $phone ); ?>" placeholder="+90...">
                        </p>

                        <p>
                            <label for="preferred_contact"><?php esc_html_e( 'Preferred contact', 'hello-elementor-child' ); ?></label>
                            <select id="preferred_contact" name="preferred_contact">
                                <option value=""><?php esc_html_e( 'Select', 'hello-elementor-child' ); ?></option>
                                <option value="phone" <?php selected( $preferred_contact, 'phone' ); ?>><?php esc_html_e( 'Phone', 'hello-elementor-child' ); ?></option>
                                <option value="whatsapp" <?php selected( $preferred_contact, 'whatsapp' ); ?>><?php esc_html_e( 'WhatsApp', 'hello-elementor-child' ); ?></option>
                                <option value="email" <?php selected( $preferred_contact, 'email' ); ?>><?php esc_html_e( 'Email', 'hello-elementor-child' ); ?></option>
                            </select>
                        </p>

                        <p>
                            <label for="budget_min_usd"><?php esc_html_e( 'Budget min (USD)', 'hello-elementor-child' ); ?></label>
                            <input type="number" id="budget_min_usd" name="budget_min_usd" min="0" step="1000" value="<?php echo esc_attr( $budget_min_usd ); ?>">
                        </p>

                        <p>
                            <label for="budget_max_usd"><?php esc_html_e( 'Budget max (USD)', 'hello-elementor-child' ); ?></label>
                            <input type="number" id="budget_max_usd" name="budget_max_usd" min="0" step="1000" value="<?php echo esc_attr( $budget_max_usd ); ?>">
                        </p>

                        <p class="client-portal-actions">
                            <button type="submit" class="button button-primary"><?php esc_html_e( 'Save profile', 'hello-elementor-child' ); ?></button>
                            <a class="button button-secondary" href="<?php echo esc_url( home_url( '/my-favourites/' ) ); ?>"><?php esc_html_e( 'Go to favourites', 'hello-elementor-child' ); ?></a>
                        </p>
                    </form>
                </div>
            </div>
        </section>
    </main>
</div>
<?php if ( $background_image ) : ?>
<style>
body.client-login-standalone {
  background-image: url('<?php echo esc_url( $background_image ); ?>');
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
}
</style>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
