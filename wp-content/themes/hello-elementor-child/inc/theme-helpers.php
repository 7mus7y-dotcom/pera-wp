<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Get a cache-busting asset version based on file modification time.
 * Falls back to theme version when the file is missing.
 */
function pera_get_asset_version( string $relative_path ): string {
  $path = get_stylesheet_directory() . '/' . ltrim( $relative_path, '/' );

  if ( file_exists( $path ) ) {
    return (string) filemtime( $path );
  }

  return wp_get_theme()->get( 'Version' );
}



if ( ! function_exists( 'pera_get_site_logo_markup' ) ) {
  /**
   * Build site logo markup with custom logo as primary source.
   *
   * @param array $args Optional overrides.
   */
  function pera_get_site_logo_markup( array $args = array() ): string {
    $defaults = array(
      'link_class'  => 'site-logo logo-pera',
      'img_class'   => '',
      'aria_label'  => get_bloginfo( 'name' ),
      'title'       => get_bloginfo( 'name' ),
      'home_url'      => home_url( '/' ),
      'fallback_width' => 120,
      'show_since'     => false,
      'since_text'     => 'SINCE 2016',
    );

    $args = wp_parse_args( $args, $defaults );

    $link_class = trim( (string) $args['link_class'] );
    $img_class  = trim( (string) $args['img_class'] );
    $aria_label = (string) $args['aria_label'];
    $title      = (string) $args['title'];
    $home_url      = (string) $args['home_url'];
    $fallback_width = (int) $args['fallback_width'];
    $show_since     = ! empty( $args['show_since'] );
    $since_text     = trim( (string) $args['since_text'] );

    $logo_id    = (int) get_theme_mod( 'custom_logo' );
    $logo_width = $fallback_width > 0 ? $fallback_width : 120;

    if ( $logo_id > 0 ) {
      $image_classes = trim( 'custom-logo pera-site-logo-image ' . $img_class );
      $image_html    = wp_get_attachment_image(
        $logo_id,
        'full',
        false,
        array(
          'class'   => $image_classes,
          'loading' => 'eager',
        )
      );

      if ( $image_html ) {
        $logo_inner = sprintf(
          '<span class="site-logo__mark" aria-hidden="true">%1$s</span>',
          $image_html
        );

        if ( $show_since && $since_text !== '' ) {
          $logo_inner .= sprintf(
            '<span class="site-logo__since">%1$s</span>',
            esc_html( $since_text )
          );
        }

        return sprintf(
          '<a href="%1$s" class="%2$s" aria-label="%3$s" title="%4$s">%5$s</a>',
          esc_url( $home_url ),
          esc_attr( $link_class ),
          esc_attr( $aria_label ),
          esc_attr( $title ),
          $logo_inner
        );
      }
    }

    $logo_path = get_stylesheet_directory() . '/logos-icons/pera-logo.svg';

    if ( file_exists( $logo_path ) ) {
      $logo_inner = sprintf(
        '<span class="site-logo__mark" aria-hidden="true">%1$s</span>',
        file_get_contents( $logo_path )
      );

      if ( $show_since && $since_text !== '' ) {
        $logo_inner .= sprintf(
          '<span class="site-logo__since">%1$s</span>',
          esc_html( $since_text )
        );
      }

      return sprintf(
        '<a href="%1$s" class="%2$s" aria-label="%3$s" title="%4$s">%5$s</a>',
        esc_url( $home_url ),
        esc_attr( $link_class ),
        esc_attr( $aria_label ),
        esc_attr( $title ),
        $logo_inner
      );
    }

    $fallback_logo = sprintf(
      '<span class="site-logo__mark" aria-hidden="true"><img src="%1$s" alt="%2$s" width="%3$d" class="%4$s" /></span>',
      esc_url( get_stylesheet_directory_uri() . '/logos-icons/logo-white.svg' ),
      esc_attr( get_bloginfo( 'name' ) ),
      $logo_width,
      esc_attr( $img_class )
    );

    if ( $show_since && $since_text !== '' ) {
      $fallback_logo .= sprintf(
        '<span class="site-logo__since">%1$s</span>',
        esc_html( $since_text )
      );
    }

    return sprintf(
      '<a href="%1$s" class="%2$s" aria-label="%3$s" title="%4$s">%5$s</a>',
      esc_url( $home_url ),
      esc_attr( $link_class ),
      esc_attr( $aria_label ),
      esc_attr( $title ),
      $fallback_logo
    );
  }
}
/**
 * Helper: are we on a BLOG archive (not property archives)?
 * - Category / Tag / Author / Date archives for posts
 * - Excludes custom post type "property" archives and property taxonomies
 */
 

function pera_is_blog_archive() {
    // Only archives
    if ( ! is_archive() ) {
        return false;
    }

    // Exclude property CPT archive
    if ( is_post_type_archive( 'property' ) ) {
        return false;
    }

    // Exclude property taxonomies
    if ( is_tax( array(
        'property_type',
        'region',
        'district',
        'special',
        'property_tags',
    ) ) ) {
        return false;
    }

    return true;
}

/**
 * Are we on a PROPERTY archive (CPT or its taxonomies)?
 */
function pera_is_property_archive() {
    return is_post_type_archive( 'property' ) || is_tax( array(
        'property_type',
        'region',
        'district',
        'special',
        'property_tags',
    ) );
}

/**
 * Enqueue the shared V2 property archive CSS bundle.
 *
 * @param bool $needs_slider Whether slider.css should be added as a dependency.
 */
function pera_enqueue_property_archive_assets( bool $needs_slider = false ): void {
  wp_enqueue_style(
    'pera-card-typography',
    get_stylesheet_directory_uri() . '/css/card-typography.css',
    array( 'pera-main-css' ),
    pera_get_asset_version( '/css/card-typography.css' )
  );

  wp_enqueue_style(
    'pera-property-css',
    get_stylesheet_directory_uri() . '/css/property.css',
    array( 'pera-main-css' ),
    pera_get_asset_version( '/css/property.css' )
  );

  $deps = array( 'pera-main-css', 'pera-card-typography' );
  if ( $needs_slider ) {
    $deps[] = 'pera-slider-css';
  }

  wp_enqueue_style(
    'pera-property-card',
    get_stylesheet_directory_uri() . '/css/property-card.css',
    $deps,
    pera_get_asset_version( '/css/property-card.css' )
  );
}

/**
 * Get the current taxonomy archive context (taxonomy + term ID).
 *
 * @param array $allowed_taxonomies Optional allowlist of taxonomies.
 * @return array{taxonomy:string,term_id:int}|array
 */
function pera_get_taxonomy_archive_context( array $allowed_taxonomies = array() ): array {
  if ( ! is_tax() ) {
    return array();
  }

  $qo = get_queried_object();
  if ( ! ( $qo instanceof WP_Term ) || is_wp_error( $qo ) ) {
    return array();
  }

  $taxonomy = isset( $qo->taxonomy ) ? (string) $qo->taxonomy : '';
  $term_id  = isset( $qo->term_id ) ? (int) $qo->term_id : 0;

  if ( $taxonomy === '' || $term_id <= 0 ) {
    return array();
  }

  if ( ! empty( $allowed_taxonomies ) && ! in_array( $taxonomy, $allowed_taxonomies, true ) ) {
    return array();
  }

  return array(
    'taxonomy' => $taxonomy,
    'term_id'  => $term_id,
  );
}

/**
 * Get taxonomy context for property tax term archives.
 *
 * @return array{taxonomy:string,term_id:int}|array
 */
function pera_get_property_tax_archive_context(): array {
  if ( ! is_tax() ) {
    return array();
  }

  $qo = get_queried_object();
  if ( ! ( $qo instanceof WP_Term ) || is_wp_error( $qo ) ) {
    return array();
  }

  $taxonomy = isset( $qo->taxonomy ) ? (string) $qo->taxonomy : '';
  $term_id  = isset( $qo->term_id ) ? (int) $qo->term_id : 0;

  if ( $taxonomy === '' || $term_id <= 0 ) {
    return array();
  }

  if ( ! taxonomy_exists( $taxonomy ) ) {
    return array();
  }

  if ( ! is_object_in_taxonomy( 'property', $taxonomy ) ) {
    return array();
  }

  return array(
    'taxonomy' => $taxonomy,
    'term_id'  => $term_id,
  );
}

/**
 * Force all PROPERTY archives (CPT + taxonomies)
 * to use our archive-property.php template.
 */
function pera_force_property_archive_template( $template ) {

    // Only affect the front end
    if ( is_admin() ) {
        return $template;
    }

    if ( pera_is_property_archive() ) {
        $custom = get_stylesheet_directory() . '/archive-property.php';

        if ( file_exists( $custom ) ) {
            return $custom;
        }
    }

    return $template;
}

/* =======================================================
   PRELOAD MONTSERRAT (ALL PAGES)
   ======================================================= */
function pera_preload_fonts() {
  $base_uri  = get_stylesheet_directory_uri() . '/fonts/';
  $base_path = get_stylesheet_directory() . '/fonts/';
  $fonts     = array(
    'Montserrat-Regular.woff2',
    'Montserrat-Bold.woff2',
    'Montserrat-ExtraBold.woff2',
  );

  foreach ( $fonts as $font ) {
    if ( ! file_exists( $base_path . $font ) ) {
      continue;
    }

    echo '<link rel="preload" as="font" href="' . esc_url( $base_uri . $font ) . '" type="font/woff2" crossorigin>' . "\n";
  }
}

/* =======================================================
   9. Ensure the "Forgot Password" page exists with correct slug and template
   ======================================================= */
function pera_register_forgot_password_page() {

    $page_slug     = 'client-forgot-password';
    $page_title    = 'Forgot Password';
    $template_file = 'page-client-forgot-password.php';

    $existing_page = get_page_by_path( $page_slug );

    if ( ! $existing_page ) {

        $page_id = wp_insert_post( array(
            'post_title'   => $page_title,
            'post_name'    => $page_slug,
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ) );

        if ( ! is_wp_error( $page_id ) ) {
            update_post_meta( $page_id, '_wp_page_template', $template_file );
        }

    } else {

        update_post_meta( $existing_page->ID, '_wp_page_template', $template_file );

        if ( $existing_page->post_name !== $page_slug ) {
            wp_update_post( array(
                'ID'        => $existing_page->ID,
                'post_name' => $page_slug,
            ) );
        }
    }
}


if ( ! function_exists( 'pera_normalize_related_district_term' ) ) {
  /**
   * Normalize an ACF value into a district term.
   *
   * @param mixed $value Raw ACF value.
   * @return WP_Term|null
   */
  function pera_normalize_related_district_term( $value ) {
    if ( $value instanceof WP_Term ) {
      return ( $value->taxonomy === 'district' ) ? $value : null;
    }

    if ( is_numeric( $value ) ) {
      $term = get_term( (int) $value, 'district' );
      return ( $term instanceof WP_Term && ! is_wp_error( $term ) ) ? $term : null;
    }

    if ( is_object( $value ) && isset( $value->term_id ) ) {
      $term_id = (int) $value->term_id;
      if ( $term_id > 0 ) {
        $term = get_term( $term_id, 'district' );
        return ( $term instanceof WP_Term && ! is_wp_error( $term ) ) ? $term : null;
      }
    }

    if ( is_array( $value ) ) {
      if ( isset( $value['term_id'] ) ) {
        $term = get_term( (int) $value['term_id'], 'district' );
        return ( $term instanceof WP_Term && ! is_wp_error( $term ) ) ? $term : null;
      }

      foreach ( $value as $item ) {
        $term = pera_normalize_related_district_term( $item );
        if ( $term instanceof WP_Term ) {
          return $term;
        }
      }
    }

    return null;
  }
}

if ( ! function_exists( 'pera_get_related_guide_district_context' ) ) {
  /**
   * Resolve related district context for a guide-like post.
   *
   * @param int $post_id Post ID.
   * @return array{term:WP_Term|null,field_name:string,raw_type:string}
   */
  function pera_get_related_guide_district_context( int $post_id ): array {
    $context = array(
      'term'      => null,
      'field_name'=> '',
      'raw_type'  => '',
    );

    if ( $post_id <= 0 || ! function_exists( 'get_field' ) ) {
      return $context;
    }

    $field_names = apply_filters(
      'pera_related_guide_district_field_names',
      array( 'related_district', 'guide_district', 'district', 'related_districts' ),
      $post_id
    );

    if ( ! is_array( $field_names ) ) {
      return $context;
    }

    foreach ( $field_names as $field_name ) {
      $field_name = sanitize_key( (string) $field_name );
      if ( $field_name === '' ) {
        continue;
      }

      $raw_value = get_field( $field_name, $post_id );
      if ( empty( $raw_value ) ) {
        continue;
      }

      $term = pera_normalize_related_district_term( $raw_value );
      if ( $term instanceof WP_Term ) {
        $context['term']       = $term;
        $context['field_name'] = $field_name;
        $context['raw_type']   = gettype( $raw_value );
        return $context;
      }
    }

    return $context;
  }
}

if ( ! function_exists( 'pera_render_related_guide_property_block' ) ) {
  /**
   * Render a district-filtered latest property block for guide-like posts.
   *
   * @param int   $post_id Post ID.
   * @param array $args Optional args.
   * @return string
   */
  function pera_render_related_guide_property_block( int $post_id, array $args = array() ): string {
    if ( $post_id <= 0 ) {
      return '';
    }

    $is_guide_like = function_exists( 'pera_schema_is_guide_like_post' )
      ? pera_schema_is_guide_like_post( $post_id )
      : has_category( 'regional-guides', $post_id );

    if ( ! $is_guide_like ) {
      return '';
    }

    $defaults = array(
      'posts_per_page' => 4,
      'show_contact_cta'=> true,
      'container_class' => 'pera-guide-related-properties',
    );

    $args = wp_parse_args( $args, $defaults );
    $district_context = pera_get_related_guide_district_context( $post_id );
    $district_term    = isset( $district_context['term'] ) ? $district_context['term'] : null;

    if ( ! ( $district_term instanceof WP_Term ) || $district_term->taxonomy !== 'district' ) {
      return '';
    }

    $district_link = get_term_link( $district_term );
    if ( is_wp_error( $district_link ) || ! is_string( $district_link ) || $district_link === '' ) {
      return '';
    }

    $query_args = array(
      'post_type'              => 'property',
      'post_status'            => 'publish',
      'posts_per_page'         => max( 1, (int) $args['posts_per_page'] ),
      'ignore_sticky_posts'    => true,
      'no_found_rows'          => true,
      'update_post_meta_cache' => false,
      'update_post_term_cache' => true,
      'tax_query'              => array(
        array(
          'taxonomy'         => 'district',
          'field'            => 'term_id',
          'terms'            => array( (int) $district_term->term_id ),
          'include_children' => true,
        ),
      ),
    );

    $query_args = apply_filters( 'pera_related_guide_property_query_args', $query_args, $district_term, $post_id );

    $properties = new WP_Query( $query_args );

    try {
      if ( ! $properties->have_posts() ) {
        return '';
      }

      ob_start();
      ?>
      <section class="<?php echo esc_attr( $args['container_class'] ); ?>" aria-label="<?php echo esc_attr( sprintf( 'Latest properties for sale in %s', $district_term->name ) ); ?>">
        <h2><?php echo esc_html( sprintf( 'Latest Properties for Sale in %s', $district_term->name ) ); ?></h2>

        <div class="cards-slider cards-slider--sidebar cards-slider--snap">
          <div class="slider-track">
            <?php while ( $properties->have_posts() ) : $properties->the_post(); ?>
              <?php
              pera_render_property_card( array(
                'variant'       => 'sidebar',
                'card_classes'  => 'slider-card',
                'show_badges'   => true,
                'show_admin'    => false,
                'show_excerpt'  => true,
                'excerpt_words' => 18,
                'image_size'    => 'large',
              ) );
              ?>
            <?php endwhile; ?>

            <article class="slider-card post-card post-card--cta home-editorial-posts__cta" aria-label="<?php echo esc_attr( sprintf( 'Property actions for %s', $district_term->name ) ); ?>">
              <div class="post-card-body">
                <h3 class="post-card-title"><?php echo esc_html__( 'Like what you see?', 'hello-elementor-child' ); ?></h3>
                <div class="home-editorial-posts__cta-actions">
                  <a class="btn btn--solid btn--blue" href="<?php echo esc_url( $district_link ); ?>"><?php echo esc_html( sprintf( 'See all property for sale in %s', $district_term->name ) ); ?></a>
                  <a class="btn btn--ghost btn--green" href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>"><?php echo esc_html__( 'Contact us', 'hello-elementor-child' ); ?></a>
                </div>
              </div>
            </article>
          </div>
        </div>
      </section>
      <?php

      return (string) ob_get_clean();
    } finally {
      wp_reset_postdata();
    }
  }
}

if ( ! function_exists( 'pera_inject_related_properties_into_guide_content' ) ) {
  /**
   * Inject district-matched latest properties into guide post content.
   *
   * @param string $content Post content.
   * @return string
   */
  function pera_inject_related_properties_into_guide_content( string $content ): string {
    if ( is_admin() || is_feed() || wp_doing_ajax() ) {
      return $content;
    }

    if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
      return $content;
    }

    $post_id = (int) get_the_ID();
    if ( $post_id <= 0 ) {
      return $content;
    }

    if ( strpos( $content, 'pera-guide-related-properties' ) !== false ) {
      return $content;
    }

    $block = pera_render_related_guide_property_block( $post_id, array(
      'posts_per_page' => 4,
      'show_contact_cta' => true,
    ) );

    if ( $block === '' ) {
      return $content;
    }

    $insert_after_paragraph = (int) apply_filters(
      'pera_related_guide_properties_insert_after_paragraph',
      10,
      $post_id
    );
    if ( $insert_after_paragraph < 1 ) {
      $insert_after_paragraph = 1;
    }

    $paragraph_closings = array();
    preg_match_all( '/<\/p>/i', $content, $paragraph_closings, PREG_OFFSET_CAPTURE );
    if (
      isset( $paragraph_closings[0] )
      && is_array( $paragraph_closings[0] )
      && isset( $paragraph_closings[0][ $insert_after_paragraph - 1 ][1] )
    ) {
      $match      = (string) $paragraph_closings[0][ $insert_after_paragraph - 1 ][0];
      $match_pos  = (int) $paragraph_closings[0][ $insert_after_paragraph - 1 ][1];
      $insert_pos = $match_pos + strlen( $match );
      return substr( $content, 0, $insert_pos ) . $block . substr( $content, $insert_pos );
    }

    if ( preg_match( '/<h2[^>]*>.*?<\/h2>/is', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
      $match      = $matches[0][0];
      $match_pos  = (int) $matches[0][1];
      $insert_pos = $match_pos + strlen( $match );
      return substr( $content, 0, $insert_pos ) . $block . substr( $content, $insert_pos );
    }

    return $content . $block;
  }

  add_filter( 'the_content', 'pera_inject_related_properties_into_guide_content', 20 );
}
