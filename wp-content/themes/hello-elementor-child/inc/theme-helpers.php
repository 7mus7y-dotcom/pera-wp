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

if ( ! function_exists( 'pera_is_standalone_auth_page' ) ) {
  /**
   * Detect the theme-owned standalone client auth templates.
   */
  function pera_is_standalone_auth_page(): bool {
    if ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'] ) {
      return false;
    }

    return is_page_template( array(
      'page-client-login.php',
      'page-client-forgot-password.php',
      'page-register.php',
      'page-client-portal.php',
    ) );
  }
}


if ( ! function_exists( 'pera_get_term_acf_field' ) ) {
  /**
   * Get an ACF field value for a taxonomy term using resilient candidate keys.
   *
   * @return mixed|null
   */
  function pera_get_term_acf_field( string $field_name, ?WP_Term $term = null ) {
    if ( $field_name === '' || ! function_exists( 'get_field' ) ) {
      return null;
    }

    if ( ! ( $term instanceof WP_Term ) ) {
      $queried = get_queried_object();
      if ( $queried instanceof WP_Term ) {
        $term = $queried;
      }
    }

    if ( ! ( $term instanceof WP_Term ) ) {
      return null;
    }

    if ( empty( $term->taxonomy ) || empty( $term->term_id ) ) {
      return null;
    }

    $term_id    = (int) $term->term_id;
    $candidates = array(
      $term,
      $term->taxonomy . '_' . $term_id,
      'term_' . $term_id,
      $term_id,
    );

    foreach ( $candidates as $candidate ) {
      $value = get_field( $field_name, $candidate );
      if ( $value !== null && $value !== false && $value !== '' && $value !== array() ) {
        return $value;
      }
    }

    return null;
  }
}

if ( ! function_exists( 'pera_get_term_short_label' ) ) {
  /**
   * Get a frontend-friendly taxonomy term label, falling back to the full name.
   *
   * @param mixed $term WP_Term-like object.
   */
  function pera_get_term_short_label( $term ): string {
    if ( ! $term || is_wp_error( $term ) ) {
      return '';
    }

    $label = '';

    if ( function_exists( 'get_field' ) ) {
      $label = get_field( 'short_label', $term );

      if ( ! $label && ! empty( $term->taxonomy ) && ! empty( $term->term_id ) ) {
        $label = get_field( 'short_label', $term->taxonomy . '_' . $term->term_id );
      }
    }

    $label = is_string( $label ) ? trim( $label ) : '';

    return $label !== '' ? $label : (string) $term->name;
  }
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
 * Get normalized featured guide post IDs for a category term.
 *
 * @param int $term_id Category term ID.
 * @return int[]
 */
function pera_get_featured_guide_post_ids_for_term( int $term_id ): array {
    if ( $term_id <= 0 || ! function_exists( 'get_field' ) ) {
        return array();
    }

    $raw = get_field( 'featured_guide_links', 'category_' . $term_id );

    if ( ! is_array( $raw ) ) {
        return array();
    }

    $ids = array();
    foreach ( $raw as $item ) {
        if ( $item instanceof WP_Post ) {
            $ids[] = (int) $item->ID;
        } elseif ( is_numeric( $item ) ) {
            $ids[] = (int) $item;
        }
    }

    return array_values( array_unique( array_filter( $ids ) ) );
}

if ( ! function_exists( 'pera_get_login_background_image_url' ) ) {
  /**
   * Get the shared background image URL for standalone auth pages.
   */
  function pera_get_login_background_image_url(): string {
    $attachment_id = 59332;
    $url           = wp_get_attachment_image_url( $attachment_id, 'full' );

    return is_string( $url ) ? $url : '';
  }
}

if ( ! function_exists( 'pera_user_can_view_property' ) ) {
  /**
   * Determine whether the current visitor may view an individual property.
   */
  function pera_user_can_view_property( int $post_id ): bool {
    if ( $post_id <= 0 ) {
      return false;
    }

    if ( 'property' !== get_post_type( $post_id ) ) {
      return true;
    }

    if ( current_user_can( 'edit_post', $post_id ) ) {
      return true;
    }

    $visibility = get_post_meta( $post_id, '_pera_visibility', true );
    if ( '' === $visibility ) {
      $visibility = 'public';
    }

    return 'private' !== $visibility;
  }
}
