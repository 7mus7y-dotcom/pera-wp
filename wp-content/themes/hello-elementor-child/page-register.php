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
    'invalid_request'          => pera_ml_ui( 'Invalid request. Please try again.', 'theme.template.page_register.invalid_request_please_try_again' ),
    'invalid_nonce'            => pera_ml_ui( 'Your session expired. Please submit the form again.', 'theme.template.page_register.your_session_expired_please_submit_the_form_again' ),
    'validation'               => pera_ml_ui( 'Please complete all required fields correctly.', 'theme.template.page_register.please_complete_all_required_fields_correctly' ),
    'weak_password'            => pera_ml_ui( 'Please use a password with at least 8 characters.', 'theme.template.page_register.please_use_a_password_with_at_least_8_characters' ),
    'email_exists'             => pera_ml_ui( 'An account with this email already exists. Please sign in.', 'theme.template.page_register.an_account_with_this_email_already_exists_please_sign_in' ),
    'create_failed'            => pera_ml_ui( 'We could not create your account right now. Please try again.', 'theme.template.page_register.we_could_not_create_your_account_right_now_please_try_again' ),
    'membership_failed'        => pera_ml_ui( 'Your account was created, but site access could not be completed. Please contact support.', 'theme.template.page_register.your_account_was_created_but_site_access_could_not_be_completed_please_c' ),
    'rate_limited'             => pera_ml_ui( 'Too many attempts. Please wait and try again.', 'theme.template.page_register.too_many_attempts_please_wait_and_try_again' ),
    'consent_required'         => pera_ml_ui( 'You must accept the Privacy Policy and Terms to continue.', 'theme.template.page_register.you_must_accept_the_privacy_policy_and_terms_to_continue' ),
    'turnstile_not_configured' => pera_ml_ui( 'Registration is temporarily unavailable. Please contact support.', 'theme.template.page_register.registration_is_temporarily_unavailable_please_contact_support' ),
    'turnstile_failed'         => pera_ml_ui( 'Security check failed. Please try again.', 'theme.template.page_register.security_check_failed_please_try_again' ),
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
                            <span class="site-logo__since"><?php echo esc_html( pera_ml_ui( 'SINCE 2016', 'theme.template.page_register.since_2016' ) ); ?></span>
                        </a>
                        <?php
                    }
                    ?>
                </div>

                <div class="client-login-container">
                    <h1 class="client-login-title"><?php echo esc_html( pera_ml_ui( 'Create your account', 'theme.template.page_register.create_your_account' ) ); ?></h1>
                    <p class="client-login-subtitle"><?php echo esc_html( pera_ml_ui( 'Register to access your client portal.', 'theme.template.page_register.register_to_access_your_client_portal' ) ); ?></p>

                    <?php if ( $register_error && isset( $error_messages[ $register_error ] ) ) : ?>
                        <div class="client-login-error" role="alert">
                            <?php echo esc_html( $error_messages[ $register_error ] ); ?>
                        </div>
                    <?php endif; ?>

                    <form id="pera-client-register-form" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>" method="post">
                        <input type="hidden" name="action" value="pera_public_register" />
                        <?php wp_nonce_field( 'pera_public_register_action', 'pera_public_register_nonce' ); ?>

                        <p>
                            <label for="pera_register_first_name"><?php echo esc_html( pera_ml_ui( 'First name', 'theme.template.page_register.first_name' ) ); ?></label>
                            <input type="text" name="first_name" id="pera_register_first_name" required autocomplete="given-name" />
                        </p>

                        <p>
                            <label for="pera_register_last_name"><?php echo esc_html( pera_ml_ui( 'Last name', 'theme.template.page_register.last_name' ) ); ?></label>
                            <input type="text" name="last_name" id="pera_register_last_name" required autocomplete="family-name" />
                        </p>

                        <p>
                            <label for="pera_register_email"><?php echo esc_html( pera_ml_ui( 'Email', 'theme.template.page_register.email' ) ); ?></label>
                            <input type="email" name="email" id="pera_register_email" required autocomplete="email" />
                        </p>

                        <p>
                            <label for="pera_register_password"><?php echo esc_html( pera_ml_ui( 'Password', 'theme.template.page_register.password' ) ); ?></label>
                            <input type="password" name="password" id="pera_register_password" required autocomplete="new-password" minlength="8" />
                            <small><?php echo esc_html( pera_ml_ui( 'Use at least 8 characters. A mix of letters, numbers, and symbols is recommended.', 'theme.template.page_register.use_at_least_8_characters_a_mix_of_letters_numbers_and_symbols_is_recomm' ) ); ?></small>
                        </p>

                        <p>
                            <label for="pera_register_password_confirm"><?php echo esc_html( pera_ml_ui( 'Confirm password', 'theme.template.page_register.confirm_password' ) ); ?></label>
                            <input type="password" name="password_confirm" id="pera_register_password_confirm" required autocomplete="new-password" minlength="8" />
                        </p>

                        <p class="pera-hp" aria-hidden="true" style="position:absolute;left:-9999px;opacity:0;">
                            <label for="pera_register_company"><?php echo esc_html( pera_ml_ui( 'Company', 'theme.template.page_register.company' ) ); ?></label>
                            <input type="text" name="company" id="pera_register_company" tabindex="-1" autocomplete="off" />
                        </p>

                        <p>
                            <label for="pera_register_consent">
                                <input type="checkbox" name="privacy_terms_consent" id="pera_register_consent" value="1" required />
                                <?php
                                printf(
                                    /* translators: 1: privacy policy URL, 2: terms URL */
                                    wp_kses_post(
                                        pera_ml_ui( 'I agree to the <a href="%1$s" target="_blank" rel="noopener noreferrer">Privacy Policy</a> and <a href="%2$s" target="_blank" rel="noopener noreferrer">Terms and Conditions</a>.', 'theme.template.page_register.i_agree_to_the_a_href_1_s_target_blank_rel_noopener_noreferrer_privacy_p' )
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
                            <button type="submit" class="button button-primary"><?php echo esc_html( pera_ml_ui( 'Create account', 'theme.template.page_register.create_account' ) ); ?></button>
                        </p>
                    </form>

                    <div class="client-login-links">
                        <a href="<?php echo esc_url( home_url( '/client-login/' ) ); ?>">
                            <?php echo esc_html( pera_ml_ui( 'Already have an account? Sign in', 'theme.template.page_register.already_have_an_account_sign_in' ) ); ?>
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
