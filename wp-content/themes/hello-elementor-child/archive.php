<?php
/**
 * Archive Template (Blog, Categories, Tags, Authors, Dates)
 * Uses lean header/footer and main.css + blog.css layout.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<main id="primary" class="site-main">

    <?php
    // Work out archive title & description.
    $archive_title       = '';
    $archive_subtitle    = '';
    $archive_description = '';

    if ( is_home() && ! is_front_page() ) {

        $posts_page_id = (int) get_option( 'page_for_posts' );

        if ( $posts_page_id > 0 ) {
            $archive_title = trim( (string) get_the_title( $posts_page_id ) );

            $posts_excerpt = (string) get_post_field( 'post_excerpt', $posts_page_id );
            $posts_excerpt = trim( wp_strip_all_tags( $posts_excerpt ) );
            if ( $posts_excerpt !== '' ) {
                $archive_subtitle = $posts_excerpt;
            }

            $posts_content = (string) get_post_field( 'post_content', $posts_page_id );
            if ( trim( wp_strip_all_tags( $posts_content ) ) !== '' ) {
                $archive_description = (string) apply_filters( 'the_content', $posts_content );
            }
        }

        if ( $archive_title === '' ) {
            $archive_title = __( 'Blog', 'peraproperty' );
        }

    } elseif ( is_category() ) {

        $term = get_queried_object();

        $archive_title = single_cat_title( '', false );

        if ( $term instanceof WP_Term ) {
            $term_id      = (int) $term->term_id;
            $excerpt_key  = defined( 'PERA_TERM_EXCERPT_KEY' ) ? PERA_TERM_EXCERPT_KEY : 'pera_term_excerpt';
            $term_excerpt = (string) get_term_meta( $term_id, $excerpt_key, true );

            if ( $term_excerpt === '' ) {
                $term_excerpt = (string) get_term_meta( $term_id, 'category_excerpt', true );
            }

            $term_excerpt = trim( wp_strip_all_tags( $term_excerpt ) );
            if ( $term_excerpt !== '' ) {
                $archive_subtitle = $term_excerpt;
            }

            $archive_description = (string) term_description( $term->term_id, $term->taxonomy );
        }

    } elseif ( is_tag() ) {

        $archive_title       = single_tag_title( '', false );
        $archive_description = (string) term_description();

    } elseif ( is_author() ) {

        $author_obj          = get_queried_object();
        $archive_title       = sprintf(
            __( 'Articles by %s', 'peraproperty' ),
            esc_html( $author_obj->display_name )
        );
        $archive_subtitle    = __( 'Insights and commentary from this author', 'peraproperty' );
        $archive_description = (string) get_the_author_meta( 'description', $author_obj->ID );

    } elseif ( is_year() ) {

        $archive_title = get_the_date( _x( 'Y', 'yearly archives date format', 'peraproperty' ) );

    } elseif ( is_month() ) {

        $archive_title = get_the_date( _x( 'F Y', 'monthly archives date format', 'peraproperty' ) );

    } elseif ( is_day() ) {

        $archive_title = get_the_date();

    } elseif ( is_post_type_archive() && 'post' !== get_post_type() ) {

        $archive_title    = post_type_archive_title( '', false );
        $archive_subtitle = __( 'Archive', 'peraproperty' );

    } else {

        $archive_title = __( 'Articles & Insights', 'peraproperty' );

    }

    if ( $archive_subtitle !== '' ) {
        $normalized_subtitle = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $archive_subtitle ) ) );
        $normalized_title    = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $archive_title ) ) );

        if ( strcasecmp( $normalized_subtitle, $normalized_title ) === 0 ) {
            $archive_subtitle = '';
        }
    }

    $blog_quick_links = array();
    if ( is_home() && ! is_front_page() ) {
        $blog_quick_link_slugs = array( 'buyer-guides', 'regional-guides', 'investment-advice', 'buyer-guides' );

        foreach ( $blog_quick_link_slugs as $blog_quick_link_slug ) {
            $blog_quick_link_term = get_category_by_slug( $blog_quick_link_slug );
            if ( ! ( $blog_quick_link_term instanceof WP_Term ) ) {
                continue;
            }

            $blog_quick_link_url = get_term_link( $blog_quick_link_term );
            if ( is_wp_error( $blog_quick_link_url ) ) {
                continue;
            }

            $blog_quick_links[] = array(
                'url'  => $blog_quick_link_url,
                'name' => $blog_quick_link_term->name,
            );
        }
    }
    ?>

    <section class="hero hero--left hero--archive" id="archive-hero">
      <div class="hero__media" aria-hidden="true">
        <?php
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
        ?>
        <div class="hero-overlay" aria-hidden="true"></div>
      </div>

      <div class="hero-content">
        <h1><?php echo esc_html( $archive_title ); ?></h1>

        <?php if ( $archive_subtitle !== '' ) : ?>
          <p class="lead"><?php echo esc_html( $archive_subtitle ); ?></p>
        <?php endif; ?>


        <?php if ( is_home() && ! is_front_page() ) : ?>
          <p class="lead"><?php esc_html_e( 'Stay up to date with the latest developments in the Istanbul real estate market. Our blog covers everything from district guides and new developments to investment strategies and legal considerations for buying property in Istanbul.', 'peraproperty' ); ?></p>

          <?php if ( ! empty( $blog_quick_links ) ) : ?>
            <nav class="archive-quick-links" aria-label="<?php esc_attr_e( 'Blog quick links', 'peraproperty' ); ?>">
              <?php foreach ( $blog_quick_links as $blog_quick_link ) : ?>
                <a class="archive-quick-links__link" href="<?php echo esc_url( $blog_quick_link['url'] ); ?>"><?php echo esc_html( $blog_quick_link['name'] ); ?></a>
              <?php endforeach; ?>
            </nav>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </section>

    <?php if ( $archive_description !== '' ) : ?>
      <section class="section section-soft">
        <div class="container">
          <div class="card-shell">
            <div class="lead">
              <?php echo wp_kses_post( wpautop( $archive_description ) ); ?>
            </div>
          </div>
        </div>
      </section>
    <?php endif; ?>


    <section class="section section-posts content-panel--overlap-hero">
        <div class="container">
            <?php if ( have_posts() ) : ?>

                <?php
                if ( function_exists( 'pera_render_blog_archive_sort_control' ) ) {
                    pera_render_blog_archive_sort_control();
                }
                ?>

                <div class="cards-masonry">
                  <?php
                  while ( have_posts() ) :
                    the_post();

                    set_query_var( 'pera_post_card_args', array(
                      'variant'      => 'grid',
                      'card_classes' => '',
                    ) );

                    get_template_part( 'parts/post-card' );

                  endwhile;

                  set_query_var( 'pera_post_card_args', null );
                  ?>
                </div><!-- /.cards-masonry -->

                <?php
                global $wp_query;

                $big   = 999999999;
                $links = paginate_links(
                    array(
                        'base'      => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
                        'format'    => '?paged=%#%',
                        'current'   => max( 1, get_query_var( 'paged' ) ),
                        'total'     => (int) $wp_query->max_num_pages,
                        'type'      => 'array',
                        'mid_size'  => 1,
                        'end_size'  => 1,
                        'prev_text' => __( 'Previous', 'peraproperty' ),
                        'next_text' => __( 'Next', 'peraproperty' ),
                    )
                );

                if ( ! empty( $links ) ) : ?>
                    <nav class="posts-pagination" aria-label="<?php esc_attr_e( 'Posts pagination', 'peraproperty' ); ?>">
                        <ul>
                            <?php foreach ( $links as $link ) : ?>
                                <li><?php echo $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php else : ?>

                <div class="no-posts">
                    <p><?php esc_html_e( 'No articles found in this archive.', 'peraproperty' ); ?></p>
                    <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                        <?php esc_html_e( 'Back to homepage', 'peraproperty' ); ?>
                    </a>
                </div>

            <?php endif; ?>

        </div><!-- /.container -->
    </section>

    <?php
    if ( is_category() ) :

      $current_cat = get_queried_object();
      $current_id  = isset( $current_cat->term_id ) ? (int) $current_cat->term_id : 0;

      $all_cats = get_categories( array(
        'taxonomy'   => 'category',
        'hide_empty' => true,
        'orderby'    => 'name',
        'order'      => 'ASC',
      ) );

      $other_cats = array_filter( $all_cats, function( $cat ) use ( $current_id ) {
        return (int) $cat->term_id !== $current_id;
      } );

      $other_cats = array_filter( $other_cats, function( $cat ) {
        return $cat->slug !== 'uncategorized';
      } );

      $other_cats = array_slice( $other_cats, 0, 6 );

      if ( ! empty( $other_cats ) ) : ?>
        <section class="section section-archive-cats">
          <div class="container archive-cats">
            <h2 class="archive-cats-title"><?php esc_html_e( 'Explore other topics', 'peraproperty' ); ?></h2>

            <div class="archive-cats-grid cards-scroll-mobile">
              <?php foreach ( $other_cats as $cat ) :

                $cat_link = get_category_link( $cat->term_id );

                $desc = ! empty( $cat->description )
                  ? wp_trim_words( wp_strip_all_tags( $cat->description ), 24, '…' )
                  : sprintf( '%d post%s', (int) $cat->count, $cat->count === 1 ? '' : 's' );
                ?>
                <article class="archive-cat-card card-shell">

                  <h3 class="post-card-title">
                    <a href="<?php echo esc_url( $cat_link ); ?>">
                      <?php echo esc_html( $cat->name ); ?>
                    </a>
                  </h3>

                  <p class="archive-cat-desc">
                    <?php echo esc_html( $desc ); ?>
                  </p>

                  <div class="card-meta-row">
                      <a href="<?php echo esc_url( $cat_link ); ?>" class="btn btn--ghost btn--black btn-card" aria-label="<?php echo esc_attr( sprintf( __( 'View posts in %s', 'peraproperty' ), $cat->name ) ); ?>">
                        <?php esc_html_e( 'View posts', 'peraproperty' ); ?>
                      </a>
                  </div>

                </article>
              <?php endforeach; ?>
            </div>
          </div>
        </section>
      <?php endif; ?>

    <?php endif; ?>

    <?php get_template_part( 'parts/contact-cta' ); ?>
</main>

<?php
get_footer();
