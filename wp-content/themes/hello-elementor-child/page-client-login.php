<?php
/**
 * Template Name: Client Login
 */

if ( ! defined( 'ABSPATH' ) ) {
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

$background_image = wp_get_attachment_image_url( 55484, 'full' );

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class( 'client-login-standalone' ); ?>>
<?php wp_body_open(); ?>
<div class="client-login-wrapper">
    <main id="primary" class="site-main">
        <section class="client-login-section">
            <div class="client-login-shell">
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
                    }
                    ?>
                </div>
                <div class="client-login-container">
                <?php if ( is_user_logged_in() ) : ?>

                    <?php $current_user = wp_get_current_user(); ?>
                    <p class="client-login-subtitle">
                        Hello <?php echo esc_html( $current_user->display_name ); ?>, you are already logged in.
                    </p>

                    <p>
                        <a class="button button-primary" href="<?php echo esc_url( home_url( '/client-portal/' ) ); ?>">
                            Go to client portal
                        </a>
                    </p>

                    <div class="client-login-links">
                        <a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>">
                            Log out
                        </a>
                    </div>

                <?php else : ?>
                    <p class="client-login-subtitle">
                        Sign in to access your reserved project documents and reports.
                    </p>

                    <?php if ( isset( $_GET['registered'] ) && '1' === sanitize_key( wp_unslash( $_GET['registered'] ) ) ) : ?>
                        <?php $crm_sync = isset( $_GET['crm_sync'] ) ? sanitize_key( wp_unslash( $_GET['crm_sync'] ) ) : 'ok'; ?>
                        <div class="client-login-success" role="status">
                            <?php if ( 'pending' === $crm_sync ) : ?>
                                <?php esc_html_e( 'Your account has been created. Please sign in. If account linking is still processing, our team will complete it shortly.', 'hello-elementor-child' ); ?>
                            <?php else : ?>
                                <?php esc_html_e( 'Your account has been created. Please sign in.', 'hello-elementor-child' ); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php $verify_status = isset( $_GET['verify_status'] ) ? sanitize_key( wp_unslash( $_GET['verify_status'] ) ) : ''; ?>
                    <?php if ( 'success' === $verify_status ) : ?>
                        <div class="client-login-success" role="status">
                            <?php esc_html_e( 'Email verified successfully. You can now access the client portal.', 'hello-elementor-child' ); ?>
                        </div>
                    <?php elseif ( 'required' === $verify_status ) : ?>
                        <div class="client-login-error" role="alert">
                            <?php esc_html_e( 'Please verify your email before accessing the client portal.', 'hello-elementor-child' ); ?>
                        </div>
                    <?php elseif ( 'invalid' === $verify_status ) : ?>
                        <div class="client-login-error" role="alert">
                            <?php esc_html_e( 'Verification link is invalid or expired. Please contact support.', 'hello-elementor-child' ); ?>
                        </div>
                    <?php elseif ( 'expired' === $verify_status ) : ?>
                        <div class="client-login-error" role="alert">
                            <?php esc_html_e( 'Verification link has expired (48 hours). Please contact support for a new verification email.', 'hello-elementor-child' ); ?>
                        </div>
                    <?php endif; ?>

                    <?php
                    $requested_redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';
                    $default_redirect   = home_url( '/client-portal/' );
                    $redirect_target    = wp_validate_redirect( $requested_redirect, $default_redirect );

                    $login_args = array(
                        'echo'           => true,
                        'redirect'       => $redirect_target,
                        'form_id'        => 'pera-client-login-form',
                        'label_username' => __( 'Email or Username', 'pera' ),
                        'label_password' => __( 'Password', 'pera' ),
                        'label_remember' => __( 'Remember me', 'pera' ),
                        'label_log_in'   => __( 'Login', 'pera' ),
                        'id_username'    => 'user_login',
                        'id_password'    => 'user_pass',
                        'id_remember'    => 'rememberme',
                        'id_submit'      => 'wp-submit',
                        'remember'       => true,
                        'value_username' => '',
                        'value_remember' => false,
                    );

                    wp_login_form( $login_args );
                    ?>

                    <div class="client-login-links">
                        <a href="<?php echo esc_url( get_permalink( get_page_by_path( 'client-forgot-password' ) ) ); ?>">
                          Lost your password?
                        </a>
                    </div>

                <?php endif; ?>
                </div>
            </div>
        </section>
    </main>
</div>
<?php if ( $background_image ) : ?>
<style>
.client-login-standalone{background-image:url('<?php echo esc_url( $background_image ); ?>');}
</style>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
