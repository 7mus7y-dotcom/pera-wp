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
                <?php echo esc_html( pera_ml_ui( 'Insights, market updates and guides from the Pera Property team.', 'theme.template.page_posts.insights_market_updates_and_guides_from_the_pera_property_team' ) ); ?>
              </p>
            <?php endif; ?>
        
            <!-- Optional: remove this block entirely if you don't want CTAs on blog hero -->
            <!--
            <div class="hero-actions">
              <a href="/property/" class="btn btn-primary"><?php echo esc_html( pera_ml_ui( 'View properties', 'theme.template.page_posts.view_properties' ) ); ?></a>
              <a href="/contact/" class="btn btn-secondary"><?php echo esc_html( pera_ml_ui( 'Contact us', 'theme.template.page_posts.contact_us' ) ); ?></a>
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
                        set_query_var( 'pera_post_card_args', array(
                            'variant' => 'grid',
                        ) );

                        get_template_part( 'parts/post-card' );
                    endwhile;

                    set_query_var( 'pera_post_card_args', null );
                    ?>
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
                    <nav class="posts-pagination" aria-label="<?php echo esc_attr( pera_ml_ui( 'Blog navigation', 'theme.template.page_posts.aria_label.blog_navigation' ) ); ?>">
                        <?php echo $pagination; ?>
                    </nav>
                <?php endif; ?>

            <?php
            else :
                ?>
                <p><?php echo esc_html( pera_ml_ui( 'No articles found yet. Please check back soon.', 'theme.template.page_posts.no_articles_found_yet_please_check_back_soon' ) ); ?></p>
            <?php
            endif;

            wp_reset_postdata();
            ?>
        </div>
    </section>

</main>

<?php
get_footer();
