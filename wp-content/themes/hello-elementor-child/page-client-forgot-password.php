<?php
/**
 * Template Name: Client Forgot Password
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Logged-in users should use their dashboard instead of the password reset form.
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

$background_image   = function_exists( 'pera_get_login_background_image_url' )
    ? pera_get_login_background_image_url()
    : '';
$back_to_login_page = get_page_by_path( 'client-login' );
$back_to_login_url  = $back_to_login_page ? get_permalink( $back_to_login_page ) : wp_login_url();

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
                    <h1 class="client-login-title"><?php esc_html_e( 'Reset your password', 'hello-elementor-child' ); ?></h1>
                    <p class="client-login-subtitle"><?php esc_html_e( 'Enter your username or email address and we’ll send you a link to reset your password.', 'hello-elementor-child' ); ?></p>

                    <form name="lostpasswordform" id="lostpasswordform" action="<?php echo esc_url( wp_lostpassword_url() ); ?>" method="post">
                        <p>
                            <label for="user_login"><?php esc_html_e( 'Username or Email', 'hello-elementor-child' ); ?></label>
                            <input type="text" name="user_login" id="user_login" class="input" autocomplete="username">
                        </p>

                        <?php do_action( 'lostpassword_form' ); ?>

                        <p class="submit">
                            <input type="submit" name="wp-submit" id="wp-submit" class="button button-primary" value="<?php esc_attr_e( 'Get new password', 'hello-elementor-child' ); ?>">
                        </p>
                    </form>

                    <div class="client-login-links">
                        <a href="<?php echo esc_url( $back_to_login_url ); ?>"><?php esc_html_e( 'Back to client login', 'hello-elementor-child' ); ?></a>
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
