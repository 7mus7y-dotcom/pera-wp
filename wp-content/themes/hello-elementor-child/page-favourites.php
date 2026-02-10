<?php
/**
 * Template Name: Favourites
 * Description: Display saved favourite properties.
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

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

$hero_heading = 'Your favourites';

$hero_subtext_logged_has = 'Saved properties are kept to help you compare options and request full details when you’re ready.';
$hero_subtext_logged_empty = 'You haven’t saved any properties yet. Tap the heart icon on any listing to build a shortlist.';
$hero_subtext_guest_has = 'This shortlist is saved on this device. Create an account to keep it synced and accessible across devices later.';
$hero_subtext_guest_empty = 'Tap the heart icon on any listing to build a shortlist. For now it’s saved on this device.';

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

      <p class="text-soft">
        <span data-favourites-count><?php echo esc_html( (string) $rendered_count ); ?></span> saved
      </p>
    </div>

  </section>

  <section class="section" id="favourites-enquiry">
    <div class="container">
      <header class="section-header">
        <h2>Enquire on your saved properties</h2>
        <?php if ( $logged_in ) : ?>
          <p>Your details are prefilled from your account. Send one message and we’ll come back with availability, pricing, and options.</p>
        <?php else : ?>
          <p>Send one message for all saved properties. Your shortlist is saved on this device.</p>
        <?php endif; ?>
      </header>

      <a class="btn btn--solid btn--blue" href="#favourites-enquiry">Make an enquiry on all favourites</a>

      <?php if ( $favourites_success ) : ?>
        <div class="form-success">
          Thank you – we have received your favourites enquiry. A Pera consultant will contact you shortly.
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
          <label for="fav_company">Company</label>
          <input type="text" name="fav_company" id="fav_company" value="" autocomplete="off" tabindex="-1">
        </div>

        <input type="hidden" name="fav_post_ids" id="fav_post_ids" value="<?php echo esc_attr( $favourites_ids_csv ); ?>">

        <div class="cta-fieldset">
          <?php if ( ! $first_name ) : ?>
            <div class="cta-field">
              <label class="cta-label" for="fav_first_name">First name</label>
              <input type="text" name="fav_first_name" id="fav_first_name" class="cta-control" required placeholder="Your first name">
            </div>
          <?php else : ?>
            <input type="hidden" name="fav_first_name" value="<?php echo esc_attr( $first_name ); ?>">
          <?php endif; ?>

          <?php if ( ! $last_name ) : ?>
            <div class="cta-field">
              <label class="cta-label" for="fav_last_name">Last name</label>
              <input type="text" name="fav_last_name" id="fav_last_name" class="cta-control" required placeholder="Your last name">
            </div>
          <?php else : ?>
            <input type="hidden" name="fav_last_name" value="<?php echo esc_attr( $last_name ); ?>">
          <?php endif; ?>

          <?php if ( ! $email ) : ?>
            <div class="cta-field">
              <label class="cta-label" for="fav_email">Email</label>
              <input type="email" name="fav_email" id="fav_email" class="cta-control" required placeholder="name@example.com">
            </div>
          <?php else : ?>
            <input type="hidden" name="fav_email" value="<?php echo esc_attr( $email ); ?>">
          <?php endif; ?>

          <?php if ( ! $phone ) : ?>
            <div class="cta-field">
              <label class="cta-label" for="fav_phone">Mobile</label>
              <input type="text" name="fav_phone" id="fav_phone" class="cta-control" required placeholder="+90 … or your international number">
            </div>
          <?php else : ?>
            <input type="hidden" name="fav_phone" value="<?php echo esc_attr( $phone ); ?>">
          <?php endif; ?>

          <div class="cta-field">
            <label class="cta-label" for="fav_message">Message (optional)</label>
            <textarea name="fav_message" id="fav_message" rows="4" class="cta-control" placeholder="Tell us what you need (availability, brochure request, viewing, questions, etc.)."></textarea>
          </div>

          <div class="enquiry-cta-footer">
            <button type="submit" class="btn btn--solid btn--green">
              Send enquiry
            </button>
          </div>
        </div>
      </form>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <header class="section-header">
        <h2>Favourite properties</h2>
        <p>Click any property to view full details, or remove it using the heart icon.</p>
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
        <p>You haven’t saved any properties yet.</p>
        <a href="<?php echo esc_url( get_post_type_archive_link( 'property' ) ); ?>" class="btn btn--solid btn--blue">
          Browse properties
        </a>
      </div>
    </div>
  </section>

</main>

<?php
get_footer();
