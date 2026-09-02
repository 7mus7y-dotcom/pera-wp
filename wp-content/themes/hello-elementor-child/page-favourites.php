<?php
/**
 * Template Name: Favourites
 * Description: Display saved favourite properties.
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! defined( 'DONOTCACHEPAGE' ) ) {
  define( 'DONOTCACHEPAGE', true );
}

nocache_headers();

$logged_in = is_user_logged_in();

$favourites = $logged_in ? pera_get_user_favourites( get_current_user_id() ) : array();

$favourites = array_map( 'absint', $favourites );
$favourites = array_filter( $favourites );
$favourites = array_values( array_unique( $favourites ) );

$favourites_count = count( $favourites );
$favourites_query = null;
$rendered_count = 0;

if ( $logged_in && $favourites_count > 0 ) {
  $favourites_query = new WP_Query(
    array(
      'post_type'      => 'property',
      'post_status'    => 'publish',
      'post__in'       => $favourites,
      'orderby'        => 'post__in',
      'posts_per_page' => min( 48, $favourites_count ),
    )
  );

  $rendered_count = (int) $favourites_query->post_count;
}

$hero_heading = pera_ml_ui( 'Your favourites', 'theme.template.page_favourites.hero_heading' );

$hero_subtext_logged_has = pera_ml_ui( 'Saved properties are kept to help you compare options and request full details when you’re ready.', 'theme.template.page_favourites.logged_in_saved_description' );
$hero_subtext_logged_empty = pera_ml_ui( 'You haven’t saved any properties yet. Tap the heart icon on any listing to build a shortlist.', 'theme.template.page_favourites.logged_in_empty_description' );
$hero_subtext_guest_has = pera_ml_ui( 'This shortlist is saved on this device. Create an account to keep it synced and accessible across devices later.', 'theme.template.page_favourites.guest_saved_description' );
$hero_subtext_guest_empty = pera_ml_ui( 'Tap the heart icon on any listing to build a shortlist. For now it’s saved on this device.', 'theme.template.page_favourites.guest_empty_description' );

if ( $logged_in ) {
  $hero_subtext = $rendered_count > 0 ? $hero_subtext_logged_has : $hero_subtext_logged_empty;
} else {
  $hero_subtext = $hero_subtext_guest_empty;
}

$first_name = '';
$last_name = '';
$email = '';
$phone = '';
$favourites_ids_csv = $logged_in && $favourites ? implode( ',', $favourites ) : '';

if ( $logged_in ) {
  $current_user = wp_get_current_user();
  $first_name = get_user_meta( $current_user->ID, 'first_name', true );
  $last_name  = get_user_meta( $current_user->ID, 'last_name', true );
  $email      = $current_user->user_email;

  $phone_keys = array( 'phone', 'mobile', 'billing_phone' );
  foreach ( $phone_keys as $phone_key ) {
    $candidate = get_user_meta( $current_user->ID, $phone_key, true );
    if ( $candidate ) {
      $phone = $candidate;
      break;
    }
  }
}

$first_name = trim( (string) $first_name );
$last_name  = trim( (string) $last_name );
$email      = trim( (string) $email );
$phone      = trim( (string) $phone );

$favourites_success = isset( $_GET['enquiry'] ) && $_GET['enquiry'] === 'sent';
$favourites_failed  = isset( $_GET['enquiry'] ) && $_GET['enquiry'] === 'failed';

// Guest favourites can add the first property cards over AJAX after page load.
if ( function_exists( 'pera_property_display_price_enqueue_assets' ) ) {
  pera_property_display_price_enqueue_assets();
}

get_header();
?>

<main id="primary" class="site-main">

  <!-- =====================================================
   HERO – FAVOURITES PAGE
   Canonical structure + WP image ID 55756
   ===================================================== -->
  <section class="hero hero--left" id="favourites-hero">

    <div class="hero__media" aria-hidden="true">
      <?php
        $hero_img_id = get_post_thumbnail_id();

        if ( $hero_img_id ) {
          echo wp_get_attachment_image(
            $hero_img_id,
            'full',
            false,
            array(
              'class'    => 'hero-media',
              'loading'  => 'eager',
              'decoding' => 'async',
            )
          );
        } else {
          echo wp_get_attachment_image(
            55756,
            'full',
            false,
            array(
              'class'         => 'hero-media',
              'fetchpriority' => 'high',
              'loading'       => 'eager',
              'decoding'      => 'async',
            )
          );
        }
      ?>
      <div class="hero-overlay" aria-hidden="true"></div>
    </div>

    <div class="hero-content">
      <h1><?php echo esc_html( $hero_heading ); ?></h1>

      <p
        class="lead"
        id="favourites-hero-subtext"
        data-guest-empty="<?php echo esc_attr( $hero_subtext_guest_empty ); ?>"
        data-guest-has="<?php echo esc_attr( $hero_subtext_guest_has ); ?>"
        data-logged-empty="<?php echo esc_attr( $hero_subtext_logged_empty ); ?>"
        data-logged-has="<?php echo esc_attr( $hero_subtext_logged_has ); ?>"
      >
        <?php echo esc_html( $hero_subtext ); ?>
      </p>

      <p class="lead">
        <span data-favourites-count><?php echo esc_html( (string) $rendered_count ); ?></span> <?php echo esc_html( pera_ml_ui( 'saved', 'theme.template.page_favourites.saved' ) ); ?>
      </p>
    </div>

  </section>

  <section class="section" id="favourites-enquiry">
    <div class="container">
      <header class="section-header">
        <h2><?php echo esc_html( pera_ml_ui( 'Enquire on your saved properties', 'theme.template.page_favourites.enquire_on_your_saved_properties' ) ); ?></h2>
        <?php if ( $logged_in ) : ?>
          <p><?php echo esc_html( pera_ml_ui( 'Your details are prefilled from your account. Send one message and we’ll come back with availability, pricing, and options.', 'theme.template.page_favourites.your_details_are_prefilled_from_your_account_send_one_message_and_we_ll_' ) ); ?></p>
        <?php else : ?>
          <p><?php echo esc_html( pera_ml_ui( 'Send one message for all saved properties. Your shortlist is saved on this device.', 'theme.template.page_favourites.send_one_message_for_all_saved_properties_your_shortlist_is_saved_on_thi' ) ); ?></p>
        <?php endif; ?>
      </header>

      <a class="btn btn--solid btn--blue" href="#favourites-enquiry"><?php echo esc_html( pera_ml_ui( 'Make an enquiry on all favourites', 'theme.template.page_favourites.make_an_enquiry_on_all_favourites' ) ); ?></a>

      <?php if ( $favourites_success ) : ?>
        <div class="form-success">
          <?php echo esc_html( pera_ml_ui( 'Thank you – we have received your favourites enquiry. A Pera consultant will contact you shortly.', 'theme.template.page_favourites.thank_you_we_have_received_your_favourites_enquiry_a_pera_consultant_wil' ) ); ?>
        </div>
      <?php endif; ?>

      <?php if ( $favourites_failed ) : ?>
        <div class="citizenship-alert citizenship-alert--error">
          <p><?php echo esc_html( pera_ml_ui( 'Sorry, your enquiry could not be submitted. Please try again.', 'theme.template.page_favourites.sorry_your_enquiry_could_not_be_submitted_please_try_again' ) ); ?></p>
        </div>
      <?php endif; ?>

      <style>
        .fav-hp-field {
          position: absolute;
          left: -9999px;
          width: 1px;
          height: 1px;
          overflow: hidden;
        }
      </style>

      <form class="enquiry-cta-form m-sm" action="" method="post">
        <input type="hidden" name="fav_enquiry_action" value="1">
        <?php wp_nonce_field( 'pera_favourites_enquiry', 'fav_nonce' ); ?>

        <div class="fav-hp-field" aria-hidden="true">
          <label for="fav_company"><?php echo esc_html( pera_ml_ui( 'Company', 'theme.template.page_favourites.company' ) ); ?></label>
          <input type="text" name="fav_company" id="fav_company" value="" autocomplete="off" tabindex="-1">
        </div>

        <input type="hidden" name="fav_post_ids" id="fav_post_ids" value="<?php echo esc_attr( $favourites_ids_csv ); ?>">

        <div class="cta-fieldset">
          <?php if ( ! $first_name ) : ?>
            <div class="cta-field">
              <label class="cta-label" for="fav_first_name"><?php echo esc_html( pera_ml_ui( 'First name', 'theme.template.page_favourites.first_name' ) ); ?></label>
              <input type="text" name="fav_first_name" id="fav_first_name" class="cta-control" required placeholder="<?php echo esc_attr( pera_ml_ui( 'Your first name', 'theme.template.page_favourites.placeholder.your_first_name' ) ); ?>">
            </div>
          <?php else : ?>
            <input type="hidden" name="fav_first_name" value="<?php echo esc_attr( $first_name ); ?>">
          <?php endif; ?>

          <?php if ( ! $last_name ) : ?>
            <div class="cta-field">
              <label class="cta-label" for="fav_last_name"><?php echo esc_html( pera_ml_ui( 'Last name', 'theme.template.page_favourites.last_name' ) ); ?></label>
              <input type="text" name="fav_last_name" id="fav_last_name" class="cta-control" required placeholder="<?php echo esc_attr( pera_ml_ui( 'Your last name', 'theme.template.page_favourites.placeholder.your_last_name' ) ); ?>">
            </div>
          <?php else : ?>
            <input type="hidden" name="fav_last_name" value="<?php echo esc_attr( $last_name ); ?>">
          <?php endif; ?>

          <?php if ( ! $email ) : ?>
            <div class="cta-field">
              <label class="cta-label" for="fav_email"><?php echo esc_html( pera_ml_ui( 'Email', 'theme.template.page_favourites.email' ) ); ?></label>
              <input type="email" name="fav_email" id="fav_email" class="cta-control" required placeholder="<?php echo esc_attr( pera_ml_ui( 'name@example.com', 'theme.template.page_favourites.placeholder.name_example_com' ) ); ?>">
            </div>
          <?php else : ?>
            <input type="hidden" name="fav_email" value="<?php echo esc_attr( $email ); ?>">
          <?php endif; ?>

          <?php if ( ! $phone ) : ?>
            <div class="cta-field">
              <label class="cta-label" for="fav_phone"><?php echo esc_html( pera_ml_ui( 'Mobile', 'theme.template.page_favourites.mobile' ) ); ?></label>
              <input type="text" name="fav_phone" id="fav_phone" class="cta-control" required placeholder="<?php echo esc_attr( pera_ml_ui( '+90 … or your international number', 'theme.template.page_favourites.placeholder.90_or_your_international_number' ) ); ?>">
            </div>
          <?php else : ?>
            <input type="hidden" name="fav_phone" value="<?php echo esc_attr( $phone ); ?>">
          <?php endif; ?>

          <div class="cta-field">
            <label class="cta-label" for="fav_message"><?php echo esc_html( pera_ml_ui( 'Message (optional)', 'theme.template.page_favourites.message_optional' ) ); ?></label>
            <textarea name="fav_message" id="fav_message" rows="4" class="cta-control" placeholder="<?php echo esc_attr( pera_ml_ui( 'Tell us what you need (availability, brochure request, viewing, questions, etc.).', 'theme.template.page_favourites.placeholder.tell_us_what_you_need_availability_brochure_request_viewing_questions_et' ) ); ?>"></textarea>
          </div>

          <div class="enquiry-cta-footer">
            <button type="submit" class="btn btn--solid btn--green">
              <?php echo esc_html( pera_ml_ui( 'Send enquiry', 'theme.template.page_favourites.send_enquiry' ) ); ?>
            </button>
          </div>
        </div>
      </form>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <header class="section-header">
        <h2><?php echo esc_html( pera_ml_ui( 'Favourite properties', 'theme.template.page_favourites.favourite_properties' ) ); ?></h2>
        <p><?php echo esc_html( pera_ml_ui( 'Click any property to view full details, or remove it using the heart icon.', 'theme.template.page_favourites.click_any_property_to_view_full_details_or_remove_it_using_the_heart_ico' ) ); ?></p>
      </header>

      <div
        id="favourites-grid"
        class="cards-grid"
        data-fav-hydrate="1"
      >
        <?php if ( $logged_in && $favourites_query && $favourites_query->have_posts() ) : ?>
          <?php while ( $favourites_query->have_posts() ) : $favourites_query->the_post(); ?>
            <?php
              pera_render_property_card( array(
                'variant' => 'archive',
              ) );
            ?>
          <?php endwhile; ?>
          <?php wp_reset_postdata(); ?>
        <?php endif; ?>
      </div>

      <?php $show_empty_state = ! $logged_in || $rendered_count === 0; ?>
      <div id="favourites-empty" class="text-soft"<?php echo $show_empty_state ? '' : ' hidden'; ?>>
        <p><?php echo esc_html( pera_ml_ui( 'You haven’t saved any properties yet.', 'theme.template.page_favourites.you_haven_t_saved_any_properties_yet' ) ); ?></p>
        <a href="<?php echo esc_url( get_post_type_archive_link( 'property' ) ); ?>" class="btn btn--solid btn--blue">
          Browse properties
        </a>
      </div>
    </div>
  </section>

</main>

<?php
get_footer();
