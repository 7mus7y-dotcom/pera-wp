<?php
/**
 * Template Name: Client Login
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Logged-in users should use their dashboard instead of the public auth form.
if ( function_exists( 'pera_maybe_redirect_logged_in_auth_pages' ) ) {
    pera_maybe_redirect_logged_in_auth_pages();
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

?><!doctype html>
<html <?php language_attributes(); ?> class="client-login-standalone-html">
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
                                'aria_label' => pera_ml_ui( 'Pera Property', 'theme.template.page_client_login.logo_aria_label' ),
                                'title'      => pera_ml_ui( 'Pera Property', 'theme.template.page_client_login.logo_title' ),
                                'home_url'   => home_url( '/' ),
                                'show_since' => true,
                            )
                        );
                    } else {
                        ?>
                        <a class="site-logo logo-pera" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( pera_ml_ui( 'Pera Property', 'theme.template.page_client_login.logo_aria_label' ) ); ?>" title="<?php echo esc_attr( pera_ml_ui( 'Pera Property', 'theme.template.page_client_login.logo_title' ) ); ?>">
                            <span class="site-logo__mark" aria-hidden="true">
                                <img class="pera-site-logo-image" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/logo-white.svg' ); ?>" alt="" width="200" loading="eager">
                            </span>
                            <span class="site-logo__since"><?php echo esc_html( pera_ml_ui( 'SINCE 2016', 'theme.template.page_client_login.since_2016' ) ); ?></span>
                        </a>
                        <?php
                    }
                    ?>
                </div>
                <div class="client-login-container">
                <?php if ( is_user_logged_in() ) : ?>

                    <?php $current_user = wp_get_current_user(); ?>
                    <p class="client-login-subtitle">
                        <?php echo esc_html( sprintf( pera_ml_ui( 'Hello %s, you are already logged in.', 'theme.template.page_client_login.already_logged_in' ), $current_user->display_name ) ); ?>
                    </p>

                    <p>
                        <a class="button button-primary" href="<?php echo esc_url( home_url( '/client-portal/' ) ); ?>">
                            <?php echo esc_html( pera_ml_ui( 'Go to client portal', 'theme.template.page_client_login.go_to_client_portal' ) ); ?>
                        </a>
                    </p>

                    <div class="client-login-links">
                        <a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>">
                            <?php echo esc_html( pera_ml_ui( 'Log out', 'theme.template.page_client_login.log_out' ) ); ?>
                        </a>
                    </div>

                <?php else : ?>
                    <p class="client-login-subtitle">
                        <?php echo esc_html( pera_ml_ui( 'Sign in to access your reserved project documents and reports.', 'theme.template.page_client_login.sign_in_intro' ) ); ?>
                    </p>

                    <?php if ( isset( $_GET['registered'] ) && '1' === sanitize_key( wp_unslash( $_GET['registered'] ) ) ) : ?>
                        <?php $crm_sync = isset( $_GET['crm_sync'] ) ? sanitize_key( wp_unslash( $_GET['crm_sync'] ) ) : 'ok'; ?>
                        <div class="client-login-success" role="status">
                            <?php if ( 'pending' === $crm_sync ) : ?>
                                <?php echo esc_html( pera_ml_ui( 'Your account has been created. Please sign in. If account linking is still processing, our team will complete it shortly.', 'theme.template.page_client_login.registration_pending' ) ); ?>
                            <?php else : ?>
                                <?php echo esc_html( pera_ml_ui( 'Your account has been created. Please sign in.', 'theme.template.page_client_login.registration_success' ) ); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php $verify_status = isset( $_GET['verify_status'] ) ? sanitize_key( wp_unslash( $_GET['verify_status'] ) ) : ''; ?>
                    <?php if ( 'success' === $verify_status ) : ?>
                        <div class="client-login-success" role="status">
                            <?php echo esc_html( pera_ml_ui( 'Email verified successfully. You can now access the client portal.', 'theme.template.page_client_login.email_verified' ) ); ?>
                        </div>
                    <?php elseif ( 'required' === $verify_status ) : ?>
                        <div class="client-login-error" role="alert">
                            <?php echo esc_html( pera_ml_ui( 'Please verify your email before accessing the client portal.', 'theme.template.page_client_login.email_verification_required' ) ); ?>
                        </div>
                    <?php elseif ( 'invalid' === $verify_status ) : ?>
                        <div class="client-login-error" role="alert">
                            <?php echo esc_html( pera_ml_ui( 'Verification link is invalid or expired. Please contact support.', 'theme.template.page_client_login.verification_link_invalid' ) ); ?>
                        </div>
                    <?php elseif ( 'expired' === $verify_status ) : ?>
                        <div class="client-login-error" role="alert">
                            <?php echo esc_html( pera_ml_ui( 'Verification link has expired (48 hours). Please contact support for a new verification email.', 'theme.template.page_client_login.verification_link_expired' ) ); ?>
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
                        'label_username' => pera_ml_ui( 'Email or Username', 'theme.template.page_client_login.email_or_username_label' ),
                        'label_password' => pera_ml_ui( 'Password', 'theme.template.page_client_login.password_label' ),
                        'label_remember' => pera_ml_ui( 'Remember me', 'theme.template.page_client_login.remember_me_label' ),
                        'label_log_in'   => pera_ml_ui( 'Login', 'theme.template.page_client_login.login_button' ),
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
                          <?php echo esc_html( pera_ml_ui( 'Lost your password?', 'theme.template.page_client_login.lost_password' ) ); ?>
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
