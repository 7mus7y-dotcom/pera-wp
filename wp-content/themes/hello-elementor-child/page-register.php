<?php
/**
 * Template Name: Register
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( is_user_logged_in() ) {
    wp_safe_redirect( home_url( '/client-portal/' ) );
    exit;
}

$register_error = isset( $_GET['register_error'] ) ? sanitize_key( wp_unslash( $_GET['register_error'] ) ) : '';

$error_messages = array(
    'invalid_request'  => __( 'Invalid request. Please try again.', 'hello-elementor-child' ),
    'invalid_nonce'    => __( 'Your session expired. Please submit the form again.', 'hello-elementor-child' ),
    'validation'       => __( 'Please complete all required fields correctly.', 'hello-elementor-child' ),
    'email_exists'     => __( 'An account with this email already exists. Please sign in.', 'hello-elementor-child' ),
    'create_failed'    => __( 'We could not create your account right now. Please try again.', 'hello-elementor-child' ),
    'membership_failed'=> __( 'Your account was created, but site access could not be completed. Please contact support.', 'hello-elementor-child' ),
    'rate_limited'     => __( 'Too many attempts. Please wait and try again.', 'hello-elementor-child' ),
);

get_header();
?>

<div class="client-login-wrapper">
    <main id="primary" class="site-main">
        <section class="client-login-section">
            <div class="client-login-container">
                <h1 class="client-login-title"><?php esc_html_e( 'Create your account', 'hello-elementor-child' ); ?></h1>
                <p class="client-login-subtitle"><?php esc_html_e( 'Register to access your client portal.', 'hello-elementor-child' ); ?></p>

                <?php if ( $register_error && isset( $error_messages[ $register_error ] ) ) : ?>
                    <div class="client-login-error" role="alert">
                        <?php echo esc_html( $error_messages[ $register_error ] ); ?>
                    </div>
                <?php endif; ?>

                <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
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
                        <input type="password" name="password" id="pera_register_password" required autocomplete="new-password" />
                    </p>

                    <p>
                        <label for="pera_register_password_confirm"><?php esc_html_e( 'Confirm password', 'hello-elementor-child' ); ?></label>
                        <input type="password" name="password_confirm" id="pera_register_password_confirm" required autocomplete="new-password" />
                    </p>

                    <p class="pera-hp" aria-hidden="true" style="position:absolute;left:-9999px;opacity:0;">
                        <label for="pera_register_company"><?php esc_html_e( 'Company', 'hello-elementor-child' ); ?></label>
                        <input type="text" name="company" id="pera_register_company" tabindex="-1" autocomplete="off" />
                    </p>

                    <p class="submit">
                        <button type="submit" class="btn btn--solid btn--blue"><?php esc_html_e( 'Create account', 'hello-elementor-child' ); ?></button>
                    </p>
                </form>

                <div class="client-login-links">
                    <a href="<?php echo esc_url( home_url( '/client-login/' ) ); ?>">
                        <?php esc_html_e( 'Already have an account? Sign in', 'hello-elementor-child' ); ?>
                    </a>
                </div>
            </div>
        </section>
    </main>
</div>

<?php
get_footer();

