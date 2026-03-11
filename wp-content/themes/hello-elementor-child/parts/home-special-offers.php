<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

$offers_query = new WP_Query( array(
  'post_type'           => 'property',
  'post_status'         => 'publish',
  'posts_per_page'      => 6,
  'orderby'             => 'modified',
  'order'               => 'DESC',
  'ignore_sticky_posts' => true,
  'no_found_rows'       => true,
  'meta_query'          => array(
    'relation' => 'AND',
    array(
      'key'     => 'special_offer',
      'compare' => 'EXISTS',
    ),
    array(
      'key'     => 'special_offer',
      'value'   => '',
      'compare' => '!=',
    ),
  ),
) );

$resolve_special_offer_media = static function ( $value ): array {
  $attachment_id = 0;
  $image_url     = '';

  if ( is_array( $value ) ) {
    if ( ! empty( $value['ID'] ) ) {
      $attachment_id = absint( $value['ID'] );
    }

    if ( empty( $image_url ) && ! empty( $value['url'] ) && is_string( $value['url'] ) ) {
      $image_url = trim( $value['url'] );
    }
  } elseif ( is_numeric( $value ) ) {
    $attachment_id = absint( $value );
  } elseif ( is_string( $value ) ) {
    $maybe_url = trim( $value );

    if ( $maybe_url !== '' ) {
      if ( preg_match( '#^https?://#i', $maybe_url ) ) {
        $image_url = $maybe_url;
      } else {
        $attachment_id = absint( $maybe_url );
      }
    }
  }

  if ( $attachment_id > 0 && ! wp_attachment_is_image( $attachment_id ) ) {
    $attachment_id = 0;
  }

  if ( $attachment_id <= 0 && $image_url !== '' ) {
    $resolved_id = attachment_url_to_postid( $image_url );
    if ( $resolved_id > 0 && wp_attachment_is_image( $resolved_id ) ) {
      $attachment_id = $resolved_id;
    }
  }

  if ( $attachment_id <= 0 && $image_url === '' ) {
    return array();
  }

  return array(
    'attachment_id' => $attachment_id,
    'image_url'     => $image_url,
  );
};

$offers = array();

if ( $offers_query->have_posts() ) {
  while ( $offers_query->have_posts() ) {
    $offers_query->the_post();

    $property_id    = get_the_ID();
    $special_offer  = function_exists( 'get_field' ) ? get_field( 'special_offer', $property_id ) : get_post_meta( $property_id, 'special_offer', true );
    $resolved_media = $resolve_special_offer_media( $special_offer );

    if ( empty( $resolved_media ) ) {
      continue;
    }

    $district_terms = get_the_terms( $property_id, 'district' );
    $district_term  = ( ! empty( $district_terms ) && ! is_wp_error( $district_terms ) ) ? $district_terms[0] : null;

    $offers[] = array(
      'post_id'        => $property_id,
      'title'          => get_the_title( $property_id ),
      'permalink'      => get_permalink( $property_id ),
      'district_label' => $district_term ? $district_term->name : '',
      'attachment_id'  => (int) $resolved_media['attachment_id'],
      'image_url'      => (string) $resolved_media['image_url'],
    );
  }
}

wp_reset_postdata();

if ( empty( $offers ) ) {
  return;
}
?>

<section class="section home-special-offers">
  <div class="container">

    <header class="section-header section-header--center">
      <p class="text-xs text-upper muted">Home highlights</p>
      <h2>Special Offers</h2>
    </header>

    <div class="cards-slider cards-slider--snap" aria-label="Special offers">
      <?php foreach ( $offers as $offer ) : ?>
        <article class="slider-card special-offers-card">

          <a
            href="<?php echo esc_url( $offer['permalink'] ); ?>"
            class="special-offers-card__media"
            aria-label="<?php echo esc_attr( sprintf( 'View special offer: %s', $offer['title'] ) ); ?>"
          >
            <?php if ( $offer['attachment_id'] > 0 ) : ?>
              <?php
                echo wp_get_attachment_image(
                  $offer['attachment_id'],
                  'large',
                  false,
                  array(
                    'alt'      => esc_attr( $offer['title'] ),
                    'loading'  => 'lazy',
                    'decoding' => 'async',
                  )
                );
              ?>
            <?php else : ?>
              <img
                src="<?php echo esc_url( $offer['image_url'] ); ?>"
                alt="<?php echo esc_attr( $offer['title'] ); ?>"
                loading="lazy"
                decoding="async"
              >
            <?php endif; ?>
          </a>

          <div class="special-offers-card__body">
            <?php if ( $offer['district_label'] !== '' ) : ?>
              <p class="special-offers-card__location"><?php echo esc_html( $offer['district_label'] ); ?></p>
            <?php endif; ?>

            <h3 class="special-offers-card__title">
              <a href="<?php echo esc_url( $offer['permalink'] ); ?>">
                <?php echo esc_html( $offer['title'] ); ?>
              </a>
            </h3>
          </div>

        </article>
      <?php endforeach; ?>
    </div>

  </div>
</section>

