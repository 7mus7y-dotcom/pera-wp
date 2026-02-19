<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

$card_args = get_query_var( 'pera_related_taxonomy_card_args' );
$card_args = is_array( $card_args ) ? $card_args : array();

$term = isset( $card_args['term'] ) && ( $card_args['term'] instanceof WP_Term ) ? $card_args['term'] : null;
if ( ! ( $term instanceof WP_Term ) ) {
  return;
}

$context_tax_label = isset( $card_args['context_tax_label'] ) ? sanitize_key( (string) $card_args['context_tax_label'] ) : '';
$term_link = get_term_link( $term );
if ( is_wp_error( $term_link ) ) {
  return;
}

$term_image_id = 0;
$acf_ref       = $term->taxonomy . '_' . $term->term_id;

if ( $term->taxonomy === 'district' && function_exists( 'get_field' ) ) {
  $district_image = get_field( 'district_image', $acf_ref );

  if ( is_array( $district_image ) && ! empty( $district_image['ID'] ) ) {
    $term_image_id = (int) $district_image['ID'];
  } elseif ( is_numeric( $district_image ) ) {
    $term_image_id = (int) $district_image;
  }
}

if ( ! $term_image_id ) {
  if ( function_exists( 'pera_get_term_featured_image_id' ) ) {
    $term_image_id = (int) pera_get_term_featured_image_id( (int) $term->term_id, (string) $term->taxonomy );
  } else {
    $meta_key      = defined( 'PERA_TERM_IMAGE_KEY' ) ? PERA_TERM_IMAGE_KEY : 'pera_term_featured_image_id';
    $term_image_id = (int) get_term_meta( (int) $term->term_id, $meta_key, true );
  }
}

$excerpt_meta_key = defined( 'PERA_TERM_EXCERPT_KEY' ) ? PERA_TERM_EXCERPT_KEY : 'pera_term_excerpt';
$excerpt          = trim( (string) get_term_meta( (int) $term->term_id, $excerpt_meta_key, true ) );

if ( $excerpt === '' ) {
  $description = term_description( (int) $term->term_id, (string) $term->taxonomy );
  $excerpt     = trim( wp_strip_all_tags( (string) $description ) );
}

$excerpt = $excerpt !== '' ? wp_trim_words( $excerpt, 24, '…' ) : '';

$button_label = 'View';
if ( $context_tax_label !== '' ) {
  $button_label = sprintf( 'View %s', $context_tax_label );
}
?>

<article class="property-card property-card--archive related-taxonomy-card">
  <div class="property-card__inner">
    <div class="property-card__media">
      <a
        href="<?php echo esc_url( $term_link ); ?>"
        class="property-card__media-link"
        aria-label="<?php echo esc_attr( sprintf( 'View term: %s', $term->name ) ); ?>"
      >
        <?php if ( $term_image_id ) : ?>
          <?php
            echo wp_get_attachment_image(
              $term_image_id,
              'pera-card',
              false,
              array(
                'alt'      => esc_attr( $term->name ),
                'loading'  => 'lazy',
                'decoding' => 'async',
              )
            );
          ?>
        <?php else : ?>
          <span class="property-card__media-placeholder" aria-hidden="true"></span>
        <?php endif; ?>
      </a>
    </div>

    <div class="property-card__body">
      <h3 class="property-card__title">
        <a href="<?php echo esc_url( $term_link ); ?>" class="property-card__title-link">
          <?php echo esc_html( $term->name ); ?>
        </a>
      </h3>

      <?php if ( $excerpt !== '' ) : ?>
        <p class="property-card__excerpt"><?php echo esc_html( $excerpt ); ?></p>
      <?php endif; ?>
    </div>

    <div class="property-card__footer">
      <a class="btn btn--solid btn--green" href="<?php echo esc_url( $term_link ); ?>">
        <?php echo esc_html( $button_label ); ?>
      </a>
    </div>
  </div>
</article>
