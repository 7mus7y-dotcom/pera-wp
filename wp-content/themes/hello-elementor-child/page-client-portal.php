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

$updated = isset( $_GET['updated'] ) && '1' === $_GET['updated'];

get_header();
?>

<main id="primary" class="site-main section">
  <div class="container" style="max-width:720px;">
    <h1><?php echo esc_html( pera_ml_ui( 'My Account', 'theme.template.page_client_portal.my_account' ) ); ?></h1>
    <p class="text-soft"><?php echo esc_html( pera_ml_ui( 'Manage your profile and preferences for property updates.', 'theme.template.page_client_portal.profile_intro' ) ); ?></p>

    <?php if ( $updated ) : ?>
      <div class="form-success"><?php echo esc_html( pera_ml_ui( 'Your profile has been updated.', 'theme.template.page_client_portal.profile_updated' ) ); ?></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>" class="enquiry-cta-form">
      <input type="hidden" name="action" value="pera_client_portal_update_profile">
      <?php wp_nonce_field( 'pera_client_portal_update', 'pera_client_portal_nonce' ); ?>

      <div class="cta-fieldset">
        <div class="cta-field">
          <label class="cta-label" for="first_name"><?php echo esc_html( pera_ml_ui( 'First name', 'theme.template.page_client_portal.first_name_label' ) ); ?></label>
          <input class="cta-control" type="text" id="first_name" name="first_name" value="<?php echo esc_attr( $first_name ); ?>" required>
        </div>

        <div class="cta-field">
          <label class="cta-label" for="last_name"><?php echo esc_html( pera_ml_ui( 'Last name', 'theme.template.page_client_portal.last_name_label' ) ); ?></label>
          <input class="cta-control" type="text" id="last_name" name="last_name" value="<?php echo esc_attr( $last_name ); ?>" required>
        </div>

        <div class="cta-field">
          <label class="cta-label" for="email"><?php echo esc_html( pera_ml_ui( 'Email', 'theme.template.page_client_portal.email_label' ) ); ?></label>
          <input class="cta-control" type="email" id="email" value="<?php echo esc_attr( $current_user->user_email ); ?>" disabled>
        </div>

        <div class="cta-field">
          <label class="cta-label" for="phone"><?php echo esc_html( pera_ml_ui( 'Phone', 'theme.template.page_client_portal.phone_label' ) ); ?></label>
          <input class="cta-control" type="text" id="phone" name="phone" value="<?php echo esc_attr( $phone ); ?>" placeholder="<?php echo esc_attr( pera_ml_ui( '+90...', 'theme.template.page_client_portal.phone_placeholder' ) ); ?>">
        </div>

        <div class="cta-field">
          <label class="cta-label" for="preferred_contact"><?php echo esc_html( pera_ml_ui( 'Preferred contact', 'theme.template.page_client_portal.preferred_contact_label' ) ); ?></label>
          <select class="cta-control" id="preferred_contact" name="preferred_contact">
            <option value=""><?php echo esc_html( pera_ml_ui( 'Select', 'theme.template.page_client_portal.select_contact_option' ) ); ?></option>
            <option value="phone" <?php selected( $preferred_contact, 'phone' ); ?>><?php echo esc_html( pera_ml_ui( 'Phone', 'theme.template.page_client_portal.phone_option' ) ); ?></option>
            <option value="whatsapp" <?php selected( $preferred_contact, 'whatsapp' ); ?>><?php echo esc_html( pera_ml_ui( 'WhatsApp', 'theme.template.page_client_portal.whatsapp_option' ) ); ?></option>
            <option value="email" <?php selected( $preferred_contact, 'email' ); ?>><?php echo esc_html( pera_ml_ui( 'Email', 'theme.template.page_client_portal.email_option' ) ); ?></option>
          </select>
        </div>

        <div class="cta-field">
          <label class="cta-label" for="budget_min_usd"><?php echo esc_html( pera_ml_ui( 'Budget min (USD)', 'theme.template.page_client_portal.budget_min_label' ) ); ?></label>
          <input class="cta-control" type="number" id="budget_min_usd" name="budget_min_usd" min="0" step="1000" value="<?php echo esc_attr( $budget_min_usd ); ?>">
        </div>

        <div class="cta-field">
          <label class="cta-label" for="budget_max_usd"><?php echo esc_html( pera_ml_ui( 'Budget max (USD)', 'theme.template.page_client_portal.budget_max_label' ) ); ?></label>
          <input class="cta-control" type="number" id="budget_max_usd" name="budget_max_usd" min="0" step="1000" value="<?php echo esc_attr( $budget_max_usd ); ?>">
        </div>
      </div>

      <p>
        <button type="submit" class="btn btn--solid btn--blue"><?php echo esc_html( pera_ml_ui( 'Save profile', 'theme.template.page_client_portal.save_profile' ) ); ?></button>
        <a class="btn btn--solid btn--black" href="<?php echo esc_url( home_url( '/my-favourites/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Go to favourites', 'theme.template.page_client_portal.go_to_favourites' ) ); ?></a>
      </p>
    </form>
  </div>
</main>

<?php
get_footer();
