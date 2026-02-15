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
    <h1>My Account</h1>
    <p class="text-soft">Manage your profile and preferences for property updates.</p>

    <?php if ( $updated ) : ?>
      <div class="form-success">Your profile has been updated.</div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( home_url( '/wp-admin/admin-post.php' ) ); ?>" class="enquiry-cta-form">
      <input type="hidden" name="action" value="pera_client_portal_update_profile">
      <?php wp_nonce_field( 'pera_client_portal_update', 'pera_client_portal_nonce' ); ?>

      <div class="cta-fieldset">
        <div class="cta-field">
          <label class="cta-label" for="first_name">First name</label>
          <input class="cta-control" type="text" id="first_name" name="first_name" value="<?php echo esc_attr( $first_name ); ?>" required>
        </div>

        <div class="cta-field">
          <label class="cta-label" for="last_name">Last name</label>
          <input class="cta-control" type="text" id="last_name" name="last_name" value="<?php echo esc_attr( $last_name ); ?>" required>
        </div>

        <div class="cta-field">
          <label class="cta-label" for="email">Email</label>
          <input class="cta-control" type="email" id="email" value="<?php echo esc_attr( $current_user->user_email ); ?>" disabled>
        </div>

        <div class="cta-field">
          <label class="cta-label" for="phone">Phone</label>
          <input class="cta-control" type="text" id="phone" name="phone" value="<?php echo esc_attr( $phone ); ?>" placeholder="+90...">
        </div>

        <div class="cta-field">
          <label class="cta-label" for="preferred_contact">Preferred contact</label>
          <select class="cta-control" id="preferred_contact" name="preferred_contact">
            <option value="">Select</option>
            <option value="phone" <?php selected( $preferred_contact, 'phone' ); ?>>Phone</option>
            <option value="whatsapp" <?php selected( $preferred_contact, 'whatsapp' ); ?>>WhatsApp</option>
            <option value="email" <?php selected( $preferred_contact, 'email' ); ?>>Email</option>
          </select>
        </div>

        <div class="cta-field">
          <label class="cta-label" for="budget_min_usd">Budget min (USD)</label>
          <input class="cta-control" type="number" id="budget_min_usd" name="budget_min_usd" min="0" step="1000" value="<?php echo esc_attr( $budget_min_usd ); ?>">
        </div>

        <div class="cta-field">
          <label class="cta-label" for="budget_max_usd">Budget max (USD)</label>
          <input class="cta-control" type="number" id="budget_max_usd" name="budget_max_usd" min="0" step="1000" value="<?php echo esc_attr( $budget_max_usd ); ?>">
        </div>
      </div>

      <p>
        <button type="submit" class="btn btn--solid btn--blue">Save profile</button>
        <a class="btn btn--solid btn--black" href="<?php echo esc_url( home_url( '/my-favourites/' ) ); ?>">Go to favourites</a>
      </p>
    </form>
  </div>
</main>

<?php
get_footer();
