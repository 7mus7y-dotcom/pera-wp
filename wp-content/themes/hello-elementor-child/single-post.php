<?php
/**
 * Single Post (Lean)
 * Uses the lean header/footer and main.css layout.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<main id="primary" class="site-main">

    <?php if ( have_posts() ) : ?>
        <?php while ( have_posts() ) : the_post(); ?>

	            <?php
	                // Primary category (for hero + related posts)
	                $cats         = get_the_category();
	                $primary_cat  = ! empty( $cats ) ? $cats[0] : null;
	                $primary_name = $primary_cat ? $primary_cat->name : '';
	                $primary_link = $primary_cat ? get_category_link( $primary_cat->term_id ) : '';
                  $posts_page_id = (int) get_option( 'page_for_posts' );
                  $posts_page_link = $posts_page_id > 0 ? get_permalink( $posts_page_id ) : get_post_type_archive_link( 'post' );
                  if ( ! $posts_page_link ) {
                    $posts_page_link = home_url( '/' );
                  }
                  $breadcrumb_items = function_exists( 'pera_seo_post_breadcrumb_items' )
                    ? pera_seo_post_breadcrumb_items( get_the_ID() )
                    : array();
	                $published_ts = (int) get_post_time( 'U' );
	                $modified_ts  = (int) get_post_modified_time( 'U' );
                  $has_editorial_updated_date = function_exists( 'pera_get_editorial_updated_date_raw' )
                    && '' !== pera_get_editorial_updated_date_raw( get_the_ID() );
	                $show_updated = $has_editorial_updated_date || $modified_ts > $published_ts;

                  if ( function_exists( 'pera_get_public_updated_date' ) ) {
                    $updated_date_display = pera_get_public_updated_date( '', get_the_ID() );
                    $updated_datetime     = pera_get_public_updated_datetime_attr( get_the_ID() );
                  } else {
                    $updated_date_display = get_the_modified_date();
                    $updated_datetime     = get_the_modified_date( DATE_W3C );
                  }

                  $author_id    = (int) get_the_author_meta( 'ID' );
                  $tag_terms    = get_the_tags();

                  $post_faq_raw = '';

                  if ( function_exists( 'get_field' ) ) {
                    $post_faq_value = get_field( 'seo_faq_v2', get_the_ID() );

                    if ( is_scalar( $post_faq_value ) ) {
                      $post_faq_raw = trim( (string) $post_faq_value );
                    }
                  }

                  if ( '' === $post_faq_raw ) {
                    $post_faq_value = get_post_meta( get_the_ID(), 'seo_faq_v2', true );

                    if ( is_scalar( $post_faq_value ) ) {
                      $post_faq_raw = trim( (string) $post_faq_value );
                    }
                  }

                  $post_faq_items = (
                    '' !== $post_faq_raw
                    && function_exists( 'pera_parse_faq_pipe_text' )
                  )
                    ? pera_parse_faq_pipe_text( $post_faq_raw )
                    : array();
	                
	                // Related posts query: categories first, tags as fallback when no category match exists.
	                $related_query = null;

                  $category_ids = array();
                  if ( ! empty( $cats ) ) {
                    $category_ids = wp_list_pluck( $cats, 'term_id' );
                    $category_ids = array_map( 'intval', $category_ids );
                  }

                  $tag_ids = array();
                  if ( ! empty( $tag_terms ) && is_array( $tag_terms ) ) {
                    $tag_ids = wp_list_pluck( $tag_terms, 'term_id' );
                    $tag_ids = array_map( 'intval', $tag_ids );
                  }

                  if ( ! empty( $category_ids ) ) {
                    $related_query = new WP_Query( array(
                      'post_type'              => 'post',
                      'posts_per_page'         => 3,
                      'post__not_in'           => array( get_the_ID() ),
                      'ignore_sticky_posts'    => true,
                      'no_found_rows'          => true,
                      'update_post_meta_cache' => false,
                      'update_post_term_cache' => true,
                      'tax_query'              => array(
                        array(
                          'taxonomy' => 'category',
                          'field'    => 'term_id',
                          'terms'    => $category_ids,
                        ),
                      ),
                    ) );
                  }

                  if (
                    ! empty( $tag_ids )
                    && (
                      ! ( $related_query instanceof WP_Query )
                      || (int) $related_query->post_count < 1
                    )
                  ) {
                    $exclude_ids = array( get_the_ID() );
                    if ( $related_query instanceof WP_Query && ! empty( $related_query->posts ) ) {
                      $exclude_ids = array_merge( $exclude_ids, wp_list_pluck( $related_query->posts, 'ID' ) );
                    }

                    $related_query = new WP_Query( array(
                      'post_type'              => 'post',
                      'posts_per_page'         => 3,
                      'post__not_in'           => array_map( 'intval', array_unique( $exclude_ids ) ),
                      'ignore_sticky_posts'    => true,
                      'no_found_rows'          => true,
                      'update_post_meta_cache' => false,
                      'update_post_term_cache' => true,
                      'tax_query'              => array(
                        array(
                          'taxonomy' => 'post_tag',
                          'field'    => 'term_id',
                          'terms'    => $tag_ids,
                        ),
                      ),
                    ) );
                  }

            ?>

            <!-- =====================================
             HERO (SINGLE POST)
             ===================================== -->
            <section class="hero hero--left hero--post" id="post-hero">
              <div class="hero__media" aria-hidden="true">
                <?php
                  $hero_img_id = get_post_thumbnail_id();
                  $hero_attachment_id = $hero_img_id ? (int) $hero_img_id : 55756;
                  $hero_image_size = 'full';

                  if ( image_get_intermediate_size( $hero_attachment_id, '2048x2048' ) ) {
                    $hero_image_size = '2048x2048';
                  } elseif ( image_get_intermediate_size( $hero_attachment_id, 'large' ) ) {
                    $hero_image_size = 'large';
                  }

                  $hero_image_attrs = array(
                    'class'         => 'hero-media',
                    'loading'       => 'eager',
                    'decoding'      => 'async',
                    'fetchpriority' => 'high',
                    'sizes'         => '100vw',
                  );
            
                  if ( $hero_img_id ) {
                    echo wp_get_attachment_image(
                      $hero_img_id,
                      $hero_image_size,
                      false,
                      $hero_image_attrs
                    );
                  } else {
                    // Fallback background (vopbesiktas.svg uploaded to WP)
                    echo wp_get_attachment_image(
                      $hero_attachment_id,
                      $hero_image_size,
                      false,
                      $hero_image_attrs
                    );
                  }
                ?>
                <div class="hero-overlay" aria-hidden="true"></div>
              </div>
            
              <div class="hero-content">
                <?php if ( ! empty( $breadcrumb_items ) ) : ?>
                  <nav class="post-breadcrumbs" aria-label="Breadcrumb">
                    <ol class="post-breadcrumbs__list">
                      <?php foreach ( $breadcrumb_items as $index => $breadcrumb_item ) : ?>
                        <?php
                          $is_last = $index === count( $breadcrumb_items ) - 1;
                          $name    = isset( $breadcrumb_item['name'] ) ? (string) $breadcrumb_item['name'] : '';
                          $url     = isset( $breadcrumb_item['url'] ) ? (string) $breadcrumb_item['url'] : '';
                        ?>
                        <?php if ( $name !== '' ) : ?>
                          <li class="post-breadcrumbs__item<?php echo $is_last ? ' is-current' : ''; ?>">
                            <?php if ( ! $is_last && $url !== '' ) : ?>
                              <a href="<?php echo esc_url( $url ); ?>">
                                <?php echo esc_html( $name ); ?>
                              </a>
                            <?php else : ?>
                              <span<?php echo $is_last ? ' aria-current="page"' : ''; ?>>
                                <?php echo esc_html( $name ); ?>
                              </span>
                            <?php endif; ?>
                          </li>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    </ol>
                  </nav>
                <?php endif; ?>

                <div class="article-meta-top">
                  <?php if ( $primary_cat ) : ?>
                    <a class="article-meta-cat" href="<?php echo esc_url( $primary_link ); ?>">
                      <?php echo esc_html( $primary_name ); ?>
                    </a>
                  <?php endif; ?>
                </div>
            
                <h1><?php the_title(); ?></h1>
            
                <?php if ( has_excerpt() ) : ?>
                  <p class="lead"><?php echo get_the_excerpt(); ?></p>
                <?php endif; ?>
            
                <div class="article-meta-secondary">
                  <span class="article-meta-item">
                    Date uploaded
                    <time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>">
                      <?php echo esc_html( get_the_date() ); ?>
                    </time>
                  </span>
                  <?php if ( $show_updated ) : ?>
                    <span class="article-meta-separator"> / </span>
                    <span class="article-meta-item">
                      Updated
                      <time datetime="<?php echo esc_attr( $updated_datetime ); ?>">
                        <?php echo esc_html( $updated_date_display ); ?>
                      </time>
                    </span>
                  <?php endif; ?>
                  <span class="article-meta-separator"> / </span>
                  <span class="article-meta-item author vcard">
                    Written by
                    <a class="url fn n" rel="author" href="<?php echo esc_url( get_author_posts_url( $author_id ) ); ?>">
                      <?php echo esc_html( get_the_author() ); ?>
                    </a>
                  </span>
                </div>
              </div>
            </section>



            <!-- MAIN ARTICLE + SIDEBAR -->
            <section class="section section-article">
                <div class="container article-layout">

                    <!-- LEFT: ARTICLE CONTENT -->
                    <div class="article-main">
                        <article <?php post_class( 'article-body' ); ?>>
                            <?php the_content(); ?>
                        </article>

                        <?php if ( ! empty( $post_faq_items ) && function_exists( 'pera_render_faq_html' ) ) : ?>
                          <?php pera_render_faq_html( $post_faq_items, 'Frequently Asked Questions' ); ?>
                        <?php endif; ?>

                        <?php if ( ! empty( $tag_terms ) && is_array( $tag_terms ) ) : ?>
                          <section class="post-tags" aria-label="Article tags">
                            <h2 class="post-tags__title">Tags</h2>
                            <ul class="post-tags__list">
                              <?php foreach ( $tag_terms as $tag_term ) : ?>
                                <?php $tag_link = get_tag_link( $tag_term->term_id ); ?>
                                <?php if ( ! is_wp_error( $tag_link ) ) : ?>
                                  <li class="post-tags__item">
                                    <a href="<?php echo esc_url( $tag_link ); ?>">
                                      <?php echo esc_html( $tag_term->name ); ?>
                                    </a>
                                  </li>
                                <?php endif; ?>
                              <?php endforeach; ?>
                            </ul>
                          </section>
                        <?php endif; ?>

                        <?php
                          $previous_post = get_previous_post();
                          $next_post     = get_next_post();
                        ?>
                        <?php if ( $previous_post || $next_post ) : ?>
                          <nav class="post-adjacent-nav" aria-label="Article navigation">
                            <?php if ( $previous_post instanceof WP_Post ) : ?>
                              <a class="post-adjacent-nav__link post-adjacent-nav__link--prev" href="<?php echo esc_url( get_permalink( $previous_post ) ); ?>">
                                <span class="post-adjacent-nav__label">Previous article</span>
                                <span class="post-adjacent-nav__title"><?php echo esc_html( get_the_title( $previous_post ) ); ?></span>
                              </a>
                            <?php endif; ?>

                            <?php if ( $next_post instanceof WP_Post ) : ?>
                              <a class="post-adjacent-nav__link post-adjacent-nav__link--next" href="<?php echo esc_url( get_permalink( $next_post ) ); ?>">
                                <span class="post-adjacent-nav__label">Next article</span>
                                <span class="post-adjacent-nav__title"><?php echo esc_html( get_the_title( $next_post ) ); ?></span>
                              </a>
                            <?php endif; ?>
                          </nav>
                        <?php endif; ?>
                    </div>


                    <!-- RIGHT: SIDEBAR -->
                    <aside class="article-sidebar" aria-label="Article sidebar">

                      <?php if ( isset( $related_query ) && $related_query instanceof WP_Query && $related_query->have_posts() ) : ?>
                    
                        <section class="sidebar-block sidebar-block--related">
                          <h3>Related articles</h3>
                    
                          <div class="cards-slider cards-slider--sidebar">
                            <div class="slider-track">
                              <?php
                              while ( $related_query->have_posts() ) :
                                  $related_query->the_post();
                                
                                  set_query_var( 'pera_post_card_args', array(
                                    'variant'       => 'sidebar',
                                    'card_classes'  => 'slider-card',
                                    'show_readmore' => false,
                                  ) );
                                
                                  get_template_part( 'parts/post-card' );
                                
                                endwhile;
                                
                                set_query_var( 'pera_post_card_args', null );
                                wp_reset_postdata();

                              ?>
                            </div><!-- /.slider-track -->
                          </div><!-- /.cards-slider -->
                    
                          <div class="sidebar-cta">
                            <a class="btn btn--solid btn--blue" href="<?php echo esc_url( $primary_link ? $primary_link : $posts_page_link ); ?>">
                              See all posts
                            </a>
                          </div>
                    
                        </section>
                    
                      <?php endif; ?>


                    
                        <!-- 2. SELL WITH PERA -->
                        <section class="sidebar-block sidebar-block--sell">
                            <h3>Sell Your Property in Istanbul</h3>
                            <p class="sidebar-text">
                                Get a realistic Istanbul property valuation, professional marketing, qualified buyer viewings and end-to-end support through negotiation and the title deed process.
                            </p>
                            <div class="sidebar-cta">
                                <a class="btn btn--solid btn--blue"
                                   href="<?php echo esc_url( home_url( '/sell-your-istanbul-real-estate/' ) ); ?>">
                                    Get a Free Valuation
                                </a>
                            </div>
                        </section>
                    
                        <!-- 3. RENT WITH PERA -->
                        <section class="sidebar-block sidebar-block--rent">
                            <h3>Rent Out Your Property in Istanbul</h3>
                            <p class="sidebar-text">
                                Pera Property helps local and overseas owners find reliable tenants, manage contracts, coordinate maintenance and protect rental income with hands-on Istanbul property management.
                            </p>
                            <div class="sidebar-cta">
                                <a class="btn btn--solid btn--blue"
                                   href="<?php echo esc_url( home_url( '/rent-your-istanbul-real-estate/' ) ); ?>">
                                    Explore Property Management
                                </a>
                            </div>
                        </section>
                    
                        <?php
                          $is_guide_like_post = function_exists( 'pera_schema_is_guide_like_post' )
                            ? pera_schema_is_guide_like_post( get_the_ID() )
                            : has_category( 'regional-guides', get_the_ID() );
                        ?>
                        <?php if ( ! $is_guide_like_post ) : ?>
                          <!-- 4. LATEST PROPERTIES -->
                          <section class="sidebar-block sidebar-block--properties">
                            <h3>Latest properties</h3>
                          
                            <?php
                            $properties = new WP_Query( array(
                              'post_type'              => 'property',
                              'posts_per_page'         => 3,
                              'post_status'            => 'publish',
                              'no_found_rows'          => true,
                              'ignore_sticky_posts'    => true,
                              'update_post_meta_cache' => false,
                              'update_post_term_cache' => true,
                            ) );
                          
                            if ( $properties->have_posts() ) : ?>
                              
                              <div class="cards-slider cards-slider--sidebar cards-slider--snap">
                                <div class="slider-track">
                                  <?php
                                  while ( $properties->have_posts() ) :
                                    $properties->the_post();
                          
                                    pera_render_property_card( array(
                                      'variant'       => 'sidebar',
                                      'card_classes'  => 'slider-card',
                                      'show_badges'   => true,
                                      'show_admin'    => false,
                                      'show_excerpt'  => true,
                                      'excerpt_words' => 18,
                                      'image_size'    => 'large',
                                    ) );
                          
                                  endwhile;
                                  wp_reset_postdata();
                                  ?>
                                </div>
                              </div>
                          
                            <?php endif; ?>
                          </section>
                        <?php endif; ?>


                
                    </aside><!-- /.article-sidebar -->
                
                
                </div><!-- /.container.article-layout -->
            </section><!-- /.section-article -->


            <!-- BOTTOM CONTACT SECTION (outside article layout) -->
            <?php get_template_part( 'parts/contact-cta' ); ?>


        <?php endwhile; ?>

    <?php else : ?>

        <section class="section section-article">
            <div class="container narrow">
                <p>Sorry, we couldn’t find this article.</p>
                <a class="btn btn--ghost btn--blue" href="<?php echo esc_url( home_url( '/' ) ); ?>">Go to homepage</a>
            </div>
        </section>

    <?php endif; ?>

</main>

<?php
get_footer();
