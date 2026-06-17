<?php
/**
 * Template Part: Featured Guide Post Card
 * Compact archive featured-guide card using an explicit post ID.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$card_args = get_query_var( 'pera_featured_guide_card_args' );
$card_args = is_array( $card_args ) ? $card_args : array();

$post_id = isset( $card_args['post_id'] ) ? absint( $card_args['post_id'] ) : 0;

if ( $post_id <= 0 ) {
    return;
}

$featured_post = get_post( $post_id );

if ( ! ( $featured_post instanceof WP_Post ) || 'publish' !== get_post_status( $post_id ) || 'post' !== get_post_type( $post_id ) ) {
    return;
}

$extra_classes = '';
if ( isset( $card_args['card_classes'] ) && is_scalar( $card_args['card_classes'] ) ) {
    $extra_classes = implode(
        ' ',
        array_filter(
            array_map(
                'sanitize_html_class',
                preg_split( '/\s+/', (string) $card_args['card_classes'] )
            )
        )
    );
}

$article_classes = trim( 'archive-cat-card card-shell ' . $extra_classes );
$post_permalink  = get_permalink( $post_id );
$post_title      = wp_strip_all_tags( get_the_title( $post_id ) );
$post_excerpt    = wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post_id ) ), 24, '…' );
$read_article_aria_label = sprintf(
    /* translators: %s: post title. */
    __( 'Read article: %s', 'peraproperty' ),
    $post_title
);
?>
<article class="<?php echo esc_attr( $article_classes ); ?>">
  <h3 class="post-card-title"><a href="<?php echo esc_url( $post_permalink ); ?>"><?php echo esc_html( $post_title ); ?></a></h3>
  <p class="archive-cat-desc"><?php echo esc_html( $post_excerpt ); ?></p>
  <div class="card-meta-row"><a href="<?php echo esc_url( $post_permalink ); ?>" class="btn btn--solid btn--black btn-card" aria-label="<?php echo esc_attr( $read_article_aria_label ); ?>"><?php esc_html_e( 'Read article', 'peraproperty' ); ?></a></div>
</article>
