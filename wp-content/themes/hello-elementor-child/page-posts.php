<?php
/**
 * Template Name: Blog / Posts (Lean)
 * Description: Lean blog index using custom header/footer and main.css layout.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<main id="primary" class="site-main">

        <!-- =====================================================
             HERO – BLOG
             ====================================================== -->
        <section class="hero" id="blog-hero">
        
          <!-- Optional overlay (uses existing .hero-overlay styles) -->
          <div class="hero-overlay"></div>
        
          <div class="hero-content">
            <h1><?php the_title(); ?></h1>
        
            <?php if ( has_excerpt() ) : ?>
              <p class="lead"><?php echo get_the_excerpt(); ?></p>
            <?php else : ?>
              <p class="lead">
                Insights, market updates and guides from the Pera Property team.
              </p>
            <?php endif; ?>
        
            <!-- Optional: remove this block entirely if you don't want CTAs on blog hero -->
            <!--
            <div class="hero-actions">
              <a href="/property/" class="btn btn-primary">View properties</a>
              <a href="/contact/" class="btn btn-secondary">Contact us</a>
            </div>
            -->
          </div>
        
        </section>



    <!-- POSTS GRID -->
    <section class="section section-posts">
        <div class="container">
            <?php
            // Pagination vars
            $paged = get_query_var( 'paged' ) ? absint( get_query_var( 'paged' ) ) : 1;

            // Custom query for standard posts
            $posts_query = new WP_Query( array(
                'post_type'           => 'post',
                'posts_per_page'      => 9,
                'paged'               => $paged,
                'ignore_sticky_posts' => true,
            ) );

            if ( $posts_query->have_posts() ) : ?>
                <div class="posts-grid">
                    <?php
                    while ( $posts_query->have_posts() ) :
                        $posts_query->the_post();
                        ?>
                        <article <?php post_class( 'post-card' ); ?>>
                            <a href="<?php the_permalink(); ?>" class="post-card-thumb">
                                <?php if ( has_post_thumbnail() ) : ?>
                                    <?php the_post_thumbnail( 'medium_large', array(
                                        'loading'  => 'lazy',
                                        'decoding' => 'async',
                                    ) ); ?>
                                <?php else : ?>
                                    <div class="post-card-thumb-placeholder">
                                        <span><?php echo esc_html( wp_trim_words( get_the_title(), 6, '…' ) ); ?></span>
                                    </div>
                                <?php endif; ?>
                            </a>

                            <div class="post-card-body">
                                <div class="post-card-meta">
                                    <span class="post-card-date">
                                        <?php echo get_the_date(); ?>
                                    </span>
                                    <?php
                                    $cats = get_the_category();
                                    if ( ! empty( $cats ) ) :
                                        $cat      = $cats[0];
                                        $cat_link = get_category_link( $cat->term_id );
                                        ?>
                                        <a class="post-card-cat" href="<?php echo esc_url( $cat_link ); ?>">
                                            <?php echo esc_html( $cat->name ); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <h2 class="post-card-title">
                                    <a href="<?php the_permalink(); ?>">
                                        <?php the_title(); ?>
                                    </a>
                                </h2>

                                <p class="post-card-excerpt">
                                    <?php echo wp_trim_words( get_the_excerpt(), 28, '…' ); ?>
                                </p>

                                <a href="<?php the_permalink(); ?>" class="btnbtn btn--solid btn--black post-card-readmore">
                                    Read article
                                </a>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div> <!-- /.posts-grid -->

                <?php
                // Pagination
                $pagination = paginate_links( array(
                    'total'     => $posts_query->max_num_pages,
                    'current'   => $paged,
                    'type'      => 'list',
                    'mid_size'  => 2,
                    'prev_text' => '&laquo;',   // just «
                    'next_text' => '&raquo;',  // just »
                ) );



                if ( $pagination ) : ?>
                    <nav class="posts-pagination" aria-label="Blog navigation">
                        <?php echo $pagination; ?>
                    </nav>
                <?php endif; ?>

            <?php
            else :
                ?>
                <p>No articles found yet. Please check back soon.</p>
            <?php
            endif;

            wp_reset_postdata();
            ?>
        </div>
    </section>

</main>

<?php
get_footer();
