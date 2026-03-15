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
    );

    $args = wp_parse_args( $args, $defaults );

    $link_class = trim( (string) $args['link_class'] );
    $img_class  = trim( (string) $args['img_class'] );
    $aria_label = (string) $args['aria_label'];
    $title      = (string) $args['title'];
    $home_url      = (string) $args['home_url'];
    $fallback_width = (int) $args['fallback_width'];

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
        return sprintf(
          '<a href="%1$s" class="%2$s" aria-label="%3$s" title="%4$s">%5$s</a>',
          esc_url( $home_url ),
          esc_attr( $link_class ),
          esc_attr( $aria_label ),
          esc_attr( $title ),
          $image_html
        );
      }
    }

    $logo_path = get_stylesheet_directory() . '/logos-icons/pera-logo.svg';

    if ( file_exists( $logo_path ) ) {
      return sprintf(
        '<a href="%1$s" class="%2$s" aria-label="%3$s" title="%4$s">%5$s</a>',
        esc_url( $home_url ),
        esc_attr( $link_class ),
        esc_attr( $aria_label ),
        esc_attr( $title ),
        file_get_contents( $logo_path )
      );
    }

    return sprintf(
      '<a href="%1$s" class="%2$s" aria-label="%3$s" title="%4$s"><img src="%5$s" alt="%6$s" width="%7$d" class="%8$s" /></a>',
      esc_url( $home_url ),
      esc_attr( $link_class ),
      esc_attr( $aria_label ),
      esc_attr( $title ),
      esc_url( get_stylesheet_directory_uri() . '/logos-icons/logo-white.svg' ),
      esc_attr( get_bloginfo( 'name' ) ),
      $logo_width,
      esc_attr( $img_class )
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
    'pera-property-css',
    get_stylesheet_directory_uri() . '/css/property.css',
    array( 'pera-main-css' ),
    pera_get_asset_version( '/css/property.css' )
  );

  $deps = array( 'pera-main-css' );
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
