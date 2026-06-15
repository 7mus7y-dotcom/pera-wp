<?php
/**
 * Template Name: Register
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Logged-in users should use their dashboard instead of the public auth form.
if ( is_user_logged_in() ) {
    wp_safe_redirect( home_url( '/client-portal/' ) );
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

$register_error = isset( $_GET['register_error'] ) ? sanitize_key( wp_unslash( $_GET['register_error'] ) ) : '';

$error_messages = array(
    'invalid_request'          => __( 'Invalid request. Please try again.', 'hello-elementor-child' ),
    'invalid_nonce'            => __( 'Your session expired. Please submit the form again.', 'hello-elementor-child' ),
    'validation'               => __( 'Please complete all required fields correctly.', 'hello-elementor-child' ),
    'weak_password'            => __( 'Please use a password with at least 8 characters.', 'hello-elementor-child' ),
    'email_exists'             => __( 'An account with this email already exists. Please sign in.', 'hello-elementor-child' ),
    'create_failed'            => __( 'We could not create your account right now. Please try again.', 'hello-elementor-child' ),
    'membership_failed'        => __( 'Your account was created, but site access could not be completed. Please contact support.', 'hello-elementor-child' ),
    'rate_limited'             => __( 'Too many attempts. Please wait and try again.', 'hello-elementor-child' ),
    'consent_required'         => __( 'You must accept the Privacy Policy and Terms to continue.', 'hello-elementor-child' ),
    'turnstile_not_configured' => __( 'Registration is temporarily unavailable. Please contact support.', 'hello-elementor-child' ),
    'turnstile_failed'         => __( 'Security check failed. Please try again.', 'hello-elementor-child' ),
);
$turnstile_site_key = function_exists( 'pera_public_register_turnstile_site_key' ) ? pera_public_register_turnstile_site_key() : '';
$privacy_url        = home_url( '/privacy-policy/' );
$terms_url          = home_url( '/terms-and-conditions/' );

?><!doctype html>
<html <?php language_attributes(); ?> class="client-login-standalone-html">
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class( 'client-login-standalone client-register-standalone' ); ?>>
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

                <div class="client-login-container">
                    <h1 class="client-login-title"><?php esc_html_e( 'Create your account', 'hello-elementor-child' ); ?></h1>
                    <p class="client-login-subtitle"><?php esc_html_e( 'Register to access your client portal.', 'hello-elementor-child' ); ?></p>

                    <?php if ( $register_error && isset( $error_messages[ $register_error ] ) ) : ?>
                        <div class="client-login-error" role="alert">
                            <?php echo esc_html( $error_messages[ $register_error ] ); ?>
                        </div>
                    <?php endif; ?>

                    <form id="pera-client-register-form" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>" method="post">
                        <input type="hidden" name="action" value="pera_public_register" />
                        <?php wp_nonce_field( 'pera_public_register_action', 'pera_public_register_nonce' ); ?>

                        <p>
                            <label for="pera_register_first_name"><?php esc_html_e( 'First name', 'hello-elementor-child' ); ?></label>
                            <input type="text" name="first_name" id="pera_register_first_name" required autocomplete="given-name" />
                        </p>

                        <p>
                            <label for="pera_register_last_name"><?php esc_html_e( 'Last name', 'hello-elementor-child' ); ?></label>
                            <input type="text" name="last_name" id="pera_register_last_name" required autocomplete="family-name" />
                        </p>

                        <p>
                            <label for="pera_register_email"><?php esc_html_e( 'Email', 'hello-elementor-child' ); ?></label>
                            <input type="email" name="email" id="pera_register_email" required autocomplete="email" />
                        </p>

                        <p>
                            <label for="pera_register_password"><?php esc_html_e( 'Password', 'hello-elementor-child' ); ?></label>
                            <input type="password" name="password" id="pera_register_password" required autocomplete="new-password" minlength="8" />
                            <small><?php esc_html_e( 'Use at least 8 characters. A mix of letters, numbers, and symbols is recommended.', 'hello-elementor-child' ); ?></small>
                        </p>

                        <p>
                            <label for="pera_register_password_confirm"><?php esc_html_e( 'Confirm password', 'hello-elementor-child' ); ?></label>
                            <input type="password" name="password_confirm" id="pera_register_password_confirm" required autocomplete="new-password" minlength="8" />
                        </p>

                        <p class="pera-hp" aria-hidden="true" style="position:absolute;left:-9999px;opacity:0;">
                            <label for="pera_register_company"><?php esc_html_e( 'Company', 'hello-elementor-child' ); ?></label>
                            <input type="text" name="company" id="pera_register_company" tabindex="-1" autocomplete="off" />
                        </p>

                        <p>
                            <label for="pera_register_consent">
                                <input type="checkbox" name="privacy_terms_consent" id="pera_register_consent" value="1" required />
                                <?php
                                printf(
                                    /* translators: 1: privacy policy URL, 2: terms URL */
                                    wp_kses_post(
                                        __( 'I agree to the <a href="%1$s" target="_blank" rel="noopener noreferrer">Privacy Policy</a> and <a href="%2$s" target="_blank" rel="noopener noreferrer">Terms and Conditions</a>.', 'hello-elementor-child' )
                                    ),
                                    esc_url( $privacy_url ),
                                    esc_url( $terms_url )
                                );
                                ?>
                            </label>
                        </p>

                        <?php if ( '' !== $turnstile_site_key ) : ?>
                            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                            <div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $turnstile_site_key ); ?>"></div>
                        <?php endif; ?>

                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php esc_html_e( 'Create account', 'hello-elementor-child' ); ?></button>
                        </p>
                    </form>

                    <div class="client-login-links">
                        <a href="<?php echo esc_url( home_url( '/client-login/' ) ); ?>">
                            <?php esc_html_e( 'Already have an account? Sign in', 'hello-elementor-child' ); ?>
                        </a>
                    </div>
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
