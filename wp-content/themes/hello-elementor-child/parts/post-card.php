<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Template Part: Post Card
 * Public API: variant, card_classes, show_readmore
 */

$card_args = get_query_var( 'pera_post_card_args' );
$card_args = is_array( $card_args ) ? $card_args : array();

$variant       = isset( $card_args['variant'] ) ? sanitize_key( $card_args['variant'] ) : 'grid';
$extra_classes = isset( $card_args['card_classes'] ) ? sanitize_text_field( $card_args['card_classes'] ) : '';
$show_readmore = array_key_exists( 'show_readmore', $card_args ) ? (bool) $card_args['show_readmore'] : true;

$card_classes = trim( $extra_classes . ' post-card post-card--' . $variant );

$post_id = get_the_ID();
$post_permalink = get_permalink( $post_id );
$post_title     = wp_strip_all_tags( get_the_title( $post_id ) );
$read_more_aria_label = sprintf(
  /* translators: %s: post title. */
  __( 'Read more about %s', 'peraproperty' ),
  $post_title
);

$cats        = get_the_category( $post_id );
$primary_cat = ( ! empty( $cats ) && ! is_wp_error( $cats ) ) ? $cats[0] : null;
$cat_name    = $primary_cat ? $primary_cat->name : '';
$cat_link    = $primary_cat ? get_category_link( $primary_cat->term_id ) : '';

$post_subtitle = trim( (string) get_post_meta( $post_id, 'post_subtitle', true ) );
?>

<article <?php post_class( $card_classes . ' pera-card-shell', $post_id ); ?>>

  <div class="post-card-media">
    <?php if ( has_post_thumbnail( $post_id ) ) : ?>
      <a href="<?php echo esc_url( $post_permalink ); ?>" class="post-card-thumb">
        <?php
        echo get_the_post_thumbnail(
          $post_id,
          'medium_large',
          array(
            'loading'  => 'lazy',
            'decoding' => 'async',
          )
        );
        ?>
      </a>
    <?php else : ?>
      <div class="post-card-thumb-placeholder post-card-thumb-logo" aria-hidden="true">
        <img
          src="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/pera-logo.svg' ); ?>"
          alt=""
          class="post-card-placeholder-logo"
          loading="lazy"
          decoding="async"
        >
      </div>
    <?php endif; ?>

    <?php if ( $cat_name || '' !== $post_subtitle ) : ?>
      <div class="post-card-thumb-overlay flex flex-col items-start">
        <?php if ( $cat_name ) : ?>
          <a href="<?php echo esc_url( $cat_link ); ?>" class="pill pill--green">
            <?php echo esc_html( $cat_name ); ?>
          </a>
        <?php endif; ?>

        <?php if ( '' !== $post_subtitle ) : ?>
          <div class="post-card-subtitle pill pill--blue"><?php echo esc_html( $post_subtitle ); ?></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="post-card-body">
    <h2 class="post-card-title">
      <a href="<?php echo esc_url( $post_permalink ); ?>">
        <?php the_title(); ?>
      </a>
    </h2>

    <div class="post-card-excerpt">
      <?php echo esc_html( wp_trim_words( get_the_excerpt(), 22, '…' ) ); ?>
    </div>

    <div class="post-card-date">
      <?php echo esc_html( get_the_date( 'M d, Y' ) ); ?>
    </div>

    <?php if ( $show_readmore ) : ?>
      <div class="post-card-readmore">
        <a href="<?php echo esc_url( $post_permalink ); ?>" class="btn btn--solid btn--blue" aria-label="<?php echo esc_attr( $read_more_aria_label ); ?>">
          <?php esc_html_e( 'Read more', 'peraproperty' ); ?>
        </a>
      </div>
    <?php endif; ?>
  </div>
</article>
