<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Pera SEO / Social meta (OG + Twitter) – No Yoast
 * - Applies to all public pages EXCEPT single Property CPT (handled separately)
 * - Adds <meta description>, canonical, OG, Twitter
 * - Adds document title filters (so <title> is correct)
 * - Adds "noindex,follow" via wp_robots for:
 *    - WP search results
 *    - Property archive URLs that are filtered via querystring (to avoid index bloat)
 */

/* =======================================================
   CONFIG
======================================================= */

/**
 * Optional: fallback share image (attachment ID).
 * Put a site-wide share image in Media Library and set its attachment ID here.
 * If 0, no fallback image is used.
 */
if ( ! defined('PERA_SEO_DEFAULT_OG_IMAGE_ID') ) {
  define('PERA_SEO_DEFAULT_OG_IMAGE_ID', 0);
}

/**
 * SEO safety net:
 * Ensure core title-tag support exists even if parent theme changes or is removed.
 */
add_action( 'after_setup_theme', function () {
  if ( ! current_theme_supports( 'title-tag' ) ) {
    add_theme_support( 'title-tag' );
  }
}, 5 );

/**
 * Safety: prevent parent theme (Hello Elementor) from outputting its own meta description
 * to avoid duplicates. No-op if the function doesn't exist.
 */
add_action( 'after_setup_theme', function () {
  if ( has_action( 'wp_head', 'hello_elementor_add_description_meta_tag' ) ) {
    remove_action( 'wp_head', 'hello_elementor_add_description_meta_tag' );
  }
}, 20 );


/* =======================================================
   HELPERS
======================================================= */

if ( ! function_exists('pera_seo_all_get_description') ) {
  function pera_seo_all_get_description( int $post_id ): string {

    $post_type = get_post_type( $post_id );

    if ( in_array( $post_type, array( 'post', 'page' ), true ) ) {
      $manual_desc = pera_seo_all_get_manual_post_text_field( $post_id, 'seo_meta_description' );
      if ( $manual_desc !== '' ) return $manual_desc;
    }

    $desc = pera_seo_all_normalize_description( (string) get_the_excerpt( $post_id ) );

    if ( $desc === '' ) {
      $content = get_post_field( 'post_content', $post_id );
      $content = (string) apply_filters( 'the_content', $content );
      $desc = pera_seo_all_normalize_description( $content );
    }

    return $desc;
  }
}

if ( ! function_exists('pera_seo_all_normalize_description') ) {
  /**
   * Clean and softly trim a description to search/social-friendly length.
   */
  function pera_seo_all_normalize_description( string $raw, int $max_len = 160 ): string {
    $desc = wp_strip_all_tags( $raw );
    $desc = trim( preg_replace( '/\s+/', ' ', $desc ) );

    if ( $desc === '' ) {
      return '';
    }

    if ( function_exists( 'mb_strlen' ) && mb_strlen( $desc ) <= $max_len ) {
      return $desc;
    }

    if ( ! function_exists( 'mb_strlen' ) && strlen( $desc ) <= $max_len ) {
      return $desc;
    }

    if ( function_exists( 'mb_substr' ) ) {
      $cut = mb_substr( $desc, 0, $max_len + 1 );
      $cut = preg_replace( '/\s+\S*$/u', '', $cut );
      $cut = trim( (string) $cut );
      return $cut !== '' ? $cut : mb_substr( $desc, 0, $max_len );
    }

    $cut = substr( $desc, 0, $max_len + 1 );
    $cut = preg_replace( '/\s+\S*$/', '', $cut );
    $cut = trim( (string) $cut );
    return $cut !== '' ? $cut : substr( $desc, 0, $max_len );
  }
}

if ( ! function_exists('pera_seo_all_get_image') ) {
  function pera_seo_all_get_image( int $post_id ): array {
    $url = '';
    $alt = '';
    $width  = 0;
    $height = 0;

    $thumb_id = get_post_thumbnail_id( $post_id );

    if ( $thumb_id ) {
      $url = (string) wp_get_attachment_image_url( (int) $thumb_id, 'full' );
      $image_meta = wp_get_attachment_metadata( (int) $thumb_id );
      if ( is_array( $image_meta ) ) {
        $width  = isset( $image_meta['width'] ) ? (int) $image_meta['width'] : 0;
        $height = isset( $image_meta['height'] ) ? (int) $image_meta['height'] : 0;
      }

      $alt_meta = get_post_meta( (int) $thumb_id, '_wp_attachment_image_alt', true );
      if ( is_string($alt_meta) && $alt_meta !== '' ) {
        $alt = $alt_meta;
      }
    }

    return array(
      'url' => $url ? esc_url($url) : '',
      'alt' => $alt ? trim($alt) : '',
      'width' => $width,
      'height' => $height,
      'attachment_id' => $thumb_id ? (int) $thumb_id : 0,
    );
  }
}

if ( ! function_exists( 'pera_seo_all_get_post_custom_title' ) ) {
  /**
   * Get a normalized custom SEO title for standard posts.
   */
  function pera_seo_all_get_post_custom_title( int $post_id ): string {
    if ( get_post_type( $post_id ) !== 'post' ) {
      return '';
    }

    $custom_title = pera_seo_all_get_manual_post_text_field( $post_id, 'seo_title' );

    $custom_title = wp_strip_all_tags( $custom_title );
    $custom_title = trim( preg_replace( '/\s+/', ' ', $custom_title ) );

    return $custom_title;
  }
}

if ( ! function_exists( 'pera_seo_all_get_manual_post_text_field' ) ) {
  /**
   * Resolve a text SEO field from ACF first, then raw post meta.
   */
  function pera_seo_all_get_manual_post_text_field( int $post_id, string $field_name ): string {
    if ( $post_id <= 0 || $field_name === '' ) return '';

    $value = '';
    if ( function_exists( 'get_field' ) ) {
      $acf_value = get_field( $field_name, $post_id );
      if ( is_scalar( $acf_value ) ) {
        $value = (string) $acf_value;
      }
    }

    if ( $value === '' ) {
      $value = (string) get_post_meta( $post_id, $field_name, true );
    }

    return pera_seo_all_normalize_description( $value, 300 );
  }
}

if ( ! function_exists( 'pera_seo_all_is_rent_with_pera_page' ) ) {
  function pera_seo_all_is_rent_with_pera_page( int $post_id = 0 ): bool {
    if ( ! is_page() ) {
      return false;
    }

    if ( $post_id <= 0 ) {
      $post_id = (int) get_queried_object_id();
    }

    return $post_id > 0 && is_page_template( 'page-rent-with-pera.php' );
  }
}


if ( ! function_exists( 'pera_seo_all_is_sell_with_pera_page' ) ) {
  function pera_seo_all_is_sell_with_pera_page( int $post_id = 0 ): bool {
    if ( ! is_page() ) {
      return false;
    }

    if ( $post_id <= 0 ) {
      $post_id = (int) get_queried_object_id();
    }

    return $post_id > 0 && is_page_template( 'page-sell-with-pera.php' );
  }
}

if ( ! function_exists( 'pera_seo_all_is_contact_page' ) ) {
  function pera_seo_all_is_contact_page( int $post_id = 0 ): bool {
    if ( ! is_page() ) {
      return false;
    }

    if ( $post_id <= 0 ) {
      $post_id = (int) get_queried_object_id();
    }

    return $post_id > 0 && ( is_page_template( 'page-contact.php' ) || is_page( 'contact-us' ) );
  }
}


if ( ! function_exists( 'pera_seo_all_resolve_social_image_from_value' ) ) {
  /**
   * Resolve image URL + attachment metadata from flexible ACF/meta value shapes.
   *
   * @return array{url:string,alt:string,width:int,height:int,attachment_id:int}
   */
  function pera_seo_all_resolve_social_image_from_value( $value ): array {
    $attachment_id = 0;
    $url = '';
    $alt = '';
    $width = 0;
    $height = 0;

    if ( is_array( $value ) ) {
      if ( isset( $value['ID'] ) ) {
        $attachment_id = (int) $value['ID'];
      } elseif ( isset( $value['id'] ) ) {
        $attachment_id = (int) $value['id'];
      }

      if ( isset( $value['url'] ) && is_string( $value['url'] ) ) {
        $url = $value['url'];
      }

      if ( isset( $value['alt'] ) && is_string( $value['alt'] ) ) {
        $alt = trim( $value['alt'] );
      }
    } elseif ( is_numeric( $value ) ) {
      $attachment_id = (int) $value;
    } elseif ( is_string( $value ) ) {
      $value = trim( $value );
      if ( $value !== '' ) {
        if ( preg_match( '/^\d+$/', $value ) ) {
          $attachment_id = (int) $value;
        } else {
          $url = $value;
        }
      }
    }

    if ( $attachment_id > 0 ) {
      $resolved_url = wp_get_attachment_image_url( $attachment_id, 'full' );
      if ( is_string( $resolved_url ) && $resolved_url !== '' ) {
        $url = $resolved_url;
      }

      $meta = wp_get_attachment_metadata( $attachment_id );
      if ( is_array( $meta ) ) {
        $width = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
        $height = isset( $meta['height'] ) ? (int) $meta['height'] : 0;
      }

      if ( $alt === '' ) {
        $candidate_alt = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
        if ( $candidate_alt !== '' ) {
          $alt = trim( $candidate_alt );
        }
      }
    }

    return array(
      'url' => $url !== '' ? esc_url( $url ) : '',
      'alt' => $alt,
      'width' => $width,
      'height' => $height,
      'attachment_id' => $attachment_id > 0 ? $attachment_id : 0,
    );
  }
}

if ( ! function_exists( 'pera_seo_all_get_post_manual_social_image' ) ) {
  /**
   * Read optional post/page seo_social_image from ACF/meta when available.
   *
   * @return array{url:string,alt:string,width:int,height:int,attachment_id:int}
   */
  function pera_seo_all_get_post_manual_social_image( int $post_id ): array {
    if ( $post_id <= 0 ) {
      return array( 'url' => '', 'alt' => '', 'width' => 0, 'height' => 0, 'attachment_id' => 0 );
    }

    $raw = null;

    if ( function_exists( 'get_field' ) ) {
      $raw = get_field( 'seo_social_image', $post_id );
    }

    if ( empty( $raw ) ) {
      $raw = get_post_meta( $post_id, 'seo_social_image', true );
    }

    return pera_seo_all_resolve_social_image_from_value( $raw );
  }
}

if ( ! function_exists( 'pera_seo_all_build_post_document_title' ) ) {
  /**
   * Build a clean, deterministic document title for standard blog posts.
   */
  function pera_seo_all_build_post_document_title( int $post_id, string $fallback_title ): string {
    $custom_title = pera_seo_all_get_post_custom_title( $post_id );
    if ( $custom_title !== '' ) {
      return $custom_title;
    }

    $post_title = trim( wp_strip_all_tags( (string) get_the_title( $post_id ) ) );
    if ( $post_title === '' ) {
      return $fallback_title;
    }

    $site_name = trim( wp_strip_all_tags( (string) get_bloginfo( 'name' ) ) );
    if ( $site_name === '' ) {
      return $post_title;
    }

    $site_name_pattern = preg_quote( $site_name, '/' );
    $already_suffixed  = (bool) preg_match( '/(?:\||-|—|–|:)?\s*' . $site_name_pattern . '\s*$/iu', $post_title );

    $title = $already_suffixed
      ? $post_title
      : $post_title . ' | ' . $site_name;

    $title = trim( preg_replace( '/\s+/', ' ', $title ) );

    return $title !== '' ? $title : $fallback_title;
  }
}

if ( ! function_exists( 'pera_seo_all_build_blog_archive_document_title' ) ) {
  /**
   * Build concise document titles for standard blog archive surfaces only.
   */
  function pera_seo_all_build_blog_archive_document_title( string $fallback_title ): string {
    $site_name = trim( wp_strip_all_tags( (string) get_bloginfo( 'name' ) ) );
    $base      = '';

    if ( is_home() && ! is_front_page() ) {
      $posts_page_id = (int) get_option( 'page_for_posts' );
      if ( $posts_page_id > 0 ) {
        $base = trim( wp_strip_all_tags( (string) get_the_title( $posts_page_id ) ) );
      }

      if ( $base === '' ) {
        $base = __( 'Blog', 'peraproperty' );
      }
    } elseif ( is_category() ) {
      $term_name = trim( wp_strip_all_tags( (string) single_cat_title( '', false ) ) );
      if ( $term_name !== '' ) {
        $base = sprintf( __( '%s Articles', 'peraproperty' ), $term_name );
      }
    } elseif ( is_tag() ) {
      $term_name = trim( wp_strip_all_tags( (string) single_tag_title( '', false ) ) );
      if ( $term_name !== '' ) {
        $base = sprintf( __( '%s Articles', 'peraproperty' ), $term_name );
      }
    } elseif ( is_date() ) {
      $archive_label = trim( wp_strip_all_tags( (string) get_the_archive_title() ) );
      if ( $archive_label !== '' ) {
        $base = sprintf( __( '%s Articles', 'peraproperty' ), $archive_label );
      }
    } elseif ( is_author() ) {
      $author = get_queried_object();
      if ( $author instanceof WP_User ) {
        $author_name = trim( wp_strip_all_tags( (string) $author->display_name ) );
        if ( $author_name !== '' ) {
          $base = sprintf( __( 'Articles by %s', 'peraproperty' ), $author_name );
        }
      }
    }

    if ( $base === '' ) {
      return $fallback_title;
    }

    $paged = max( 1, (int) get_query_var( 'paged' ) );
    if ( $paged > 1 ) {
      $base .= sprintf( __( ' (Page %d)', 'peraproperty' ), $paged );
    }

    if ( $site_name !== '' ) {
      return $base . ' | ' . $site_name;
    }

    return $base;
  }
}

if ( ! function_exists( 'pera_seo_all_get_context_key' ) ) {
  /**
   * Explicit non-property context keys for seo-all ownership.
   */
  function pera_seo_all_get_context_key(): string {
    if ( is_front_page() ) return 'homepage';
    if ( is_singular( 'post' ) ) return 'blog_post';
    if ( is_page() ) return 'static_page';
    if ( is_home() && ! is_front_page() ) return 'blog_home';
    if ( is_category() ) return 'blog_category';
    if ( is_tag() ) return 'blog_tag';
    if ( is_date() ) return 'blog_date';
    if ( is_author() ) return 'blog_author';
    return 'fallback_other';
  }
}

if ( ! function_exists( 'pera_seo_all_get_term_manual_text_field' ) ) {
  function pera_seo_all_get_term_manual_text_field( WP_Term $term, string $field_name ): string {
    if ( $field_name === '' ) return '';

    $value = '';

    if ( function_exists( 'get_field' ) ) {
      $candidates = array(
        $term,
        $term->taxonomy . '_' . (int) $term->term_id,
        'term_' . (int) $term->term_id,
        (int) $term->term_id,
      );

      foreach ( $candidates as $candidate ) {
        $candidate_value = get_field( $field_name, $candidate );
        if ( is_scalar( $candidate_value ) ) {
          $value = (string) $candidate_value;
          if ( trim( $value ) !== '' ) break;
        }
      }
    }

    if ( trim( $value ) === '' ) {
      $value = (string) get_term_meta( $term->term_id, $field_name, true );
    }

    return pera_seo_all_normalize_description( $value, 300 );
  }
}

if ( ! function_exists( 'pera_seo_all_get_term_manual_social_image' ) ) {
  /**
   * @return array{url:string,alt:string,width:int,height:int,attachment_id:int}
   */
  function pera_seo_all_get_term_manual_social_image( WP_Term $term ): array {
    $raw = null;

    if ( function_exists( 'get_field' ) ) {
      $candidates = array(
        $term,
        $term->taxonomy . '_' . (int) $term->term_id,
        'term_' . (int) $term->term_id,
        (int) $term->term_id,
      );

      foreach ( $candidates as $candidate ) {
        $candidate_value = get_field( 'seo_social_image', $candidate );
        if ( ! empty( $candidate_value ) ) {
          $raw = $candidate_value;
          break;
        }
      }
    }

    if ( empty( $raw ) ) {
      $raw = get_term_meta( $term->term_id, 'seo_social_image', true );
    }

    return pera_seo_all_resolve_social_image_from_value( $raw );
  }
}

if ( ! function_exists( 'pera_seo_all_is_citizenship_page' ) ) {
  function pera_seo_all_is_citizenship_page(): bool {
    return is_page( 'citizenship-by-investment' ) || is_page_template( 'page-citizenship.php' );
  }
}

if ( ! function_exists( 'pera_seo_all_citizenship_faq_items' ) ) {
  /**
   * IMPORTANT:
   * This function is the single source of truth for the citizenship FAQ.
   * It is used BOTH for:
   * 1) Visible FAQ rendering (partials/faq-citizenship.php)
   * 2) FAQPage JSON-LD schema
   *
   * Any changes here MUST be reflected in the UI output automatically.
   * Do NOT duplicate FAQ content elsewhere.
   *
   * @return array<int,array{question:string,answer:string}>
   */
  function pera_seo_all_citizenship_faq_items(): array {
    return array(
      array(
        'question' => 'Q: Can I buy multiple properties to qualify?',
        'answer'   => 'Yes. You can combine multiple eligible properties as long as the total qualifying value is at least USD 400,000 and the purchases comply with current citizenship rules.',
      ),
      array(
        'question' => 'Q: Can off-plan property qualify for citizenship?',
        'answer'   => 'In many cases, yes. Off-plan units may qualify if the project and title status meet the legal criteria in force at the time of application, and the investment is properly documented. Because eligibility can vary by project structure, legal checks should be completed before committing.',
      ),
      array(
        'question' => 'Q: Can I buy commercial property?',
        'answer'   => 'Commercial property may qualify, provided it meets the applicable valuation, transfer and compliance requirements in force at the time of application.',
      ),
      array(
        'question' => 'Q: What is the Certificate of Conformity?',
        'answer'   => 'The Certificate of Conformity is an official confirmation that your investment meets the legal conditions of the citizenship-by-investment program. It is a key document required before citizenship approval.',
      ),
      array(
        'question' => 'Q: What is the foreign currency requirement (DAB)?',
        'answer'   => 'For Turkish citizenship by investment, the purchase funds are generally required to be brought in as foreign currency and converted through the banking system in line with current rules. The DAB (Döviz Alım Belgesi) is the supporting currency-exchange document typically requested in the title transfer and conformity process.',
      ),
      array(
        'question' => 'Q: What happens if the valuation is below $400,000?',
        'answer'   => 'If the official valuation used for your application is below USD 400,000, the property will not qualify under the real estate citizenship route.',
      ),
      array(
        'question' => 'Q: Can I sell the property after 3 years?',
        'answer'   => 'Yes. Once the mandatory 3-year holding period and registry commitment are completed, you can usually sell the property without cancelling citizenship already granted.',
      ),
      array(
        'question' => 'Q: Do I need to visit Turkey during the process?',
        'answer'   => 'In most cases, applicants must be physically present in Turkey for biometric processing connected to the investor residency stage. With the fast-track option, the residency application, biometrics, and citizenship submission can often be completed during a single visit.',
      ),
      array(
        'question' => 'Q: Is there a fast-track option for Turkish citizenship by investment?',
        'answer'   => 'Yes. A fast-track option is now available for investor residency applications linked to citizenship-by-investment cases. This can reduce the number of in-person steps by allowing residency processing, biometrics, and citizenship submission to be handled in a shorter timeframe.',
      ),
      array(
        'question' => 'Q: How many times do I need to visit Turkey?',
        'answer'   => 'At least one visit is usually required for biometric processing. With the fast-track option, the required in-person stages can often be completed in a single visit.',
      ),
      array(
        'question' => 'Q: Can I include my family?',
        'answer'   => 'Yes. The main applicant’s spouse and dependent children under 18 are generally included in the same citizenship-by-investment application.',
      ),
      array(
        'question' => 'Q: Does Turkey allow dual nationality?',
        'answer'   => 'Turkey generally permits dual nationality. Whether you can keep your original nationality also depends on the laws of your current country.',
      ),
      array(
        'question' => 'Q: Do I need to learn Turkish or take a test?',
        'answer'   => 'No language exam is usually required under the real-estate citizenship route. Applicants should still verify current documentation and interview practice at the time of submission.',
      ),
      array(
        'question' => 'Q: How long does Turkish citizenship by investment take?',
        'answer'   => 'The overall process typically takes several months from property purchase to passport issuance. With the fast-track route, the residency and citizenship submission stages can be completed much faster at the start of the process, reducing delays and the need for multiple appointments.',
      ),
    );
  }
}

add_filter( 'pre_get_document_title', function ( $title ) {
  $context = pera_seo_all_get_context_key();

  if ( $context === 'blog_post' ) {
    $post_id = (int) get_queried_object_id();
    if ( $post_id > 0 ) {
      return pera_seo_all_build_post_document_title( $post_id, (string) $title );
    }

    return $title;
  }

  if ( in_array( $context, array( 'blog_home', 'blog_category', 'blog_tag', 'blog_date', 'blog_author' ), true ) ) {
    if ( $context === 'blog_home' ) {
      $posts_page_id = (int) get_option( 'page_for_posts' );
      if ( $posts_page_id > 0 ) {
        $manual_title = pera_seo_all_get_manual_post_text_field( $posts_page_id, 'seo_title' );
        if ( $manual_title !== '' ) {
          return $manual_title;
        }
      }
    } elseif ( in_array( $context, array( 'blog_category', 'blog_tag' ), true ) ) {
      $term = get_queried_object();
      if ( $term instanceof WP_Term ) {
        $manual_title = pera_seo_all_get_term_manual_text_field( $term, 'seo_title' );
        if ( $manual_title !== '' ) {
          return $manual_title;
        }
      }
    }

    return pera_seo_all_build_blog_archive_document_title( (string) $title );
  }

  if ( in_array( $context, array( 'homepage', 'static_page' ), true ) ) {
    $post_id = (int) get_queried_object_id();
    if ( $post_id > 0 ) {
      if ( pera_seo_all_is_rent_with_pera_page( $post_id ) ) {
        if ( current_user_can( 'manage_options' ) ) {
          return 'Property Management Istanbul | Rent Out Your Istanbul Property';
        }
        return 'Property Management Istanbul | Rent Out Your Property with Pera';
      }

      if ( pera_seo_all_is_sell_with_pera_page( $post_id ) ) {
        return 'Sell Property in Istanbul | Expert Local Agency Services';
      }

      if ( pera_seo_all_is_contact_page( $post_id ) ) {
        return 'Contact Pera Property | Istanbul Real Estate Consultants';
      }

      $manual_title = pera_seo_all_get_manual_post_text_field( $post_id, 'seo_title' );
      if ( $manual_title !== '' ) {
        return $manual_title;
      }
    }
  }

  return $title;
}, 20 );

if ( ! function_exists('pera_seo_default_image') ) {
  function pera_seo_default_image(): array {
    $id = (int) PERA_SEO_DEFAULT_OG_IMAGE_ID;
    if ( ! $id ) return array('url' => '', 'alt' => '');

    $url = (string) wp_get_attachment_image_url( $id, 'full' );
    $alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );

    return array(
      'url' => $url ? esc_url($url) : '',
      'alt' => $alt ? trim($alt) : '',
    );
  }
}

if ( ! function_exists( 'pera_seo_post_breadcrumb_items' ) ) {
  /**
   * Build a deterministic breadcrumb trail for standard blog posts.
   *
   * @return array<int, array{name:string,url:string}>
   */
  function pera_seo_post_breadcrumb_items( int $post_id ): array {
    if ( $post_id <= 0 || get_post_type( $post_id ) !== 'post' ) {
      return array();
    }

    $items = array(
      array(
        'name' => __( 'Home', 'hello-elementor-child' ),
        'url'  => (string) home_url( '/' ),
      ),
    );

    $posts_page_id    = (int) get_option( 'page_for_posts' );
    $posts_page_title = '';
    $posts_page_url   = '';

    if ( $posts_page_id > 0 ) {
      $posts_page_title = trim( (string) get_the_title( $posts_page_id ) );
      $posts_page_url   = (string) get_permalink( $posts_page_id );
    } else {
      $posts_page_url = (string) get_post_type_archive_link( 'post' );
    }

    if ( $posts_page_title === '' ) {
      $posts_page_title = __( 'Blog', 'hello-elementor-child' );
    }

    if ( $posts_page_url !== '' ) {
      $items[] = array(
        'name' => $posts_page_title,
        'url'  => $posts_page_url,
      );
    }

    $primary_category = null;
    $categories       = get_the_category( $post_id );
    if ( ! empty( $categories ) && is_array( $categories ) ) {
      $primary_category = $categories[0];
    }

    if ( $primary_category instanceof WP_Term ) {
      $category_url = get_category_link( $primary_category->term_id );
      if ( ! is_wp_error( $category_url ) && $category_url ) {
        $items[] = array(
          'name' => $primary_category->name,
          'url'  => (string) $category_url,
        );
      }
    }

    $post_title = trim( (string) get_the_title( $post_id ) );
    if ( $post_title !== '' ) {
      $items[] = array(
        'name' => $post_title,
        'url'  => '',
      );
    }

    return $items;
  }
}

/**
 * Canonical fallback (keeps scheme/host consistent).
 */
if ( ! function_exists('pera_seo_all_canonical_fallback') ) {
  function pera_seo_all_canonical_fallback(): string {
    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
    $request_uri = $request_uri ?: '/';
    $request_uri = preg_replace( '/#.*/', '', $request_uri );
    $request_uri = (string) strtok( $request_uri, '?' );
    return esc_url( home_url( $request_uri ) );
  }
}

if ( ! function_exists( 'pera_seo_all_should_apply_query_noindex' ) ) {
  function pera_seo_all_should_apply_query_noindex(): bool {
    if ( is_search() ) {
      return true;
    }

    return is_archive() && ! empty( $_GET );
  }
}

/**
 * Detect “filtered property archive” requests (querystring facets & sort).
 * These should almost always be NOINDEX to avoid index bloat.
 */
if ( ! function_exists('pera_is_filtered_property_archive') ) {
  function pera_is_filtered_property_archive(): bool {
    return function_exists( 'pera_property_archive_is_filtered_request' )
      ? pera_property_archive_is_filtered_request( $_GET )
      : false;
  }
}

if ( ! function_exists( 'pera_seo_all_should_apply_query_noindex' ) ) {
  function pera_seo_all_should_apply_query_noindex(): bool {
    if ( is_search() ) {
      return true;
    }

    if ( is_archive() && ! empty( $_GET ) ) {
      return true;
    }

    return false;
  }
}

/**
 * For property contexts, return the stable base URL you want to canonical to
 * when filters are present.
 */
if ( ! function_exists('pera_property_archive_base_url') ) {
  function pera_property_archive_base_url(): string {
    $qo = get_queried_object();

    // Taxonomy archive canonical should usually be itself (term link),
    // but for FILTERED taxonomy archive URLs, we still canonical to the term page.
    if ( is_tax() && $qo && ! is_wp_error($qo) ) {
      $term_link = get_term_link( $qo );
      if ( ! is_wp_error($term_link) && $term_link ) return (string) $term_link;
    }

    return (string) get_post_type_archive_link( 'property' );
  }
}

/* =======================================================
   ROBOTS RULES (wp_robots)
======================================================= */

add_filter( 'wp_robots', function ( array $robots ): array {

  if ( is_admin() ) return $robots;

  // Defensive ownership guard: property archive SEO belongs to
  // seo-property-archive.php and should not be handled in this module.
  if ( function_exists( 'pera_is_property_archive_context' ) && pera_is_property_archive_context() ) {
    return $robots;
  }

  // Exclude your single-property SEO module
  if ( is_singular('property') ) return $robots;

  if ( pera_seo_all_should_apply_query_noindex() ) {
    $robots['noindex'] = true;
    $robots['follow'] = true;
  }

  if ( pera_seo_all_should_apply_query_noindex() ) {
    $robots['noindex'] = true;
    $robots['follow'] = true;
  }

  // Media attachment pages: noindex (prevents index bloat)
  if ( is_attachment() ) {
    $robots['noindex'] = true;
    $robots['follow'] = true;
  }

  // Filtered property archive URLs: noindex (prevents index bloat)
  if ( pera_is_filtered_property_archive() ) {
    $robots['noindex'] = true;
    $robots['follow'] = true;
  }

  return $robots;
} );

/* =======================================================
   HEAD META (description, canonical, OG, Twitter)
======================================================= */

add_action( 'wp_head', function () {

  if ( is_admin() ) return;

  // Defensive ownership guard: property archive SEO is intentionally centralized
  // in seo-property-archive.php (loader should route these contexts away).
  if ( function_exists( 'pera_is_property_archive_context' ) && pera_is_property_archive_context() ) return;

  // Exclude your single-property SEO module
  if ( is_singular('property') ) return;

  // Usually not worth indexing/sharing like normal pages
  if ( is_404() ) return;

  $site_name = (string) get_bloginfo('name');
  $context   = pera_seo_all_get_context_key();
  $title     = wp_strip_all_tags( wp_get_document_title() );
  $post_id   = (int) get_queried_object_id();
  $schema_type = '';

  if ( $context === 'blog_home' ) {
    $posts_page_id = (int) get_option('page_for_posts');
    if ( $posts_page_id > 0 ) $post_id = $posts_page_id;
  }

  // ---------- Canonical ----------
  $canonical = '';
  $property_context = function_exists( 'pera_is_property_archive_context' ) && pera_is_property_archive_context();
  $has_query_string = ! empty( $_GET );
  $apply_query_noindex = pera_seo_all_should_apply_query_noindex();

  if ( $property_context ) {
    $canonical = function_exists( 'pera_property_archive_canonical_url' )
      ? pera_property_archive_canonical_url()
      : '';

    if ( $canonical === '' ) {
      $canonical = pera_seo_all_canonical_fallback();
    }
  } else {
    if ( function_exists('wp_get_canonical_url') ) {
      $canonical = wp_get_canonical_url( $post_id ?: null );
    }
    if ( ! $canonical ) {
      $canonical = pera_seo_all_canonical_fallback();
    }
  }

  if ( $apply_query_noindex ) {
    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
    $canonical = home_url( strtok( $request_uri, '?' ) );
  }

  // ---------- Description ----------
  $desc = '';

  switch ( $context ) {
    case 'homepage':
    case 'static_page':
      if ( $post_id > 0 ) {
        if ( pera_seo_all_is_rent_with_pera_page( $post_id ) ) {
          if ( current_user_can( 'manage_options' ) ) {
            $desc = 'Rent out your Istanbul property with Pera Property. Long-term rental management, tenant sourcing, rent collection, maintenance and support for overseas owners.';
          } else {
            $desc = 'Full-service property management in Istanbul for local and overseas owners. Pera Property handles tenant sourcing, contracts, rent collection, maintenance and renewals.';
          }
          break;
        }

        if ( pera_seo_all_is_sell_with_pera_page( $post_id ) ) {
          $desc = 'Sell your Istanbul property with Pera Property. Get a free valuation, professional marketing, qualified buyer viewings, negotiation support and title deed guidance.';
          break;
        }

        if ( pera_seo_all_is_contact_page( $post_id ) ) {
          $desc = 'Contact Pera Property for expert Istanbul real estate advice. Speak with our consultants about buying, selling, renting, investment property or Turkish citizenship options.';
          break;
        }

        $desc = pera_seo_all_get_description( $post_id );
      }
      $schema_type = 'WebPage';
      break;

    case 'blog_home':
      if ( $post_id > 0 ) {
        $manual = pera_seo_all_get_manual_post_text_field( $post_id, 'seo_meta_description' );
        if ( $manual !== '' ) {
          $desc = pera_seo_all_normalize_description( $manual );
        } else {
          $desc = pera_seo_all_get_description( $post_id );
        }
      }
      if ( $desc === '' ) {
        $desc = __( 'Latest articles, market insights, and guides from Pera Property.', 'peraproperty' );
      }
      $schema_type = 'CollectionPage';
      break;

    case 'blog_category':
    case 'blog_tag':
      $term = get_queried_object();
      if ( $term instanceof WP_Term ) {
        $manual = pera_seo_all_get_term_manual_text_field( $term, 'seo_meta_description' );
        if ( $manual === '' ) {
          $manual = function_exists( 'pera_get_term_excerpt' )
            ? pera_get_term_excerpt( (int) $term->term_id, (string) $term->taxonomy, 28 )
            : '';
        }
        if ( $manual === '' && ! empty( $term->description ) ) {
          $manual = (string) $term->description;
        }
        $desc = pera_seo_all_normalize_description( $manual );
      }
      $schema_type = 'CollectionPage';
      break;

    case 'blog_date':
      $date_title = trim( wp_strip_all_tags( (string) get_the_archive_title() ) );
      if ( $date_title !== '' ) {
        $desc = sprintf( __( 'Articles archived for %s.', 'peraproperty' ), $date_title );
      }
      $schema_type = 'CollectionPage';
      break;

    case 'blog_author':
      $author = get_queried_object();
      if ( $author instanceof WP_User ) {
        $author_name = trim( wp_strip_all_tags( (string) $author->display_name ) );
        if ( $author_name !== '' ) {
          $desc = sprintf( __( 'Articles by %s.', 'peraproperty' ), $author_name );
        }
        $bio = trim( wp_strip_all_tags( (string) get_the_author_meta( 'description', (int) $author->ID ) ) );
        if ( $bio !== '' ) {
          $desc = pera_seo_all_normalize_description( $bio );
        }
      }
      $schema_type = 'CollectionPage';
      break;

    case 'blog_post':
      if ( $post_id > 0 ) {
        $desc = pera_seo_all_get_description( $post_id );
      }
      break;

    default:
      if ( $post_id > 0 ) {
        $desc = pera_seo_all_get_description( $post_id );
      }
      break;
  }

  // ---------- Image ----------
  $img_url = '';
  $img_alt = $title;
  $img_width = 0;
  $img_height = 0;
  $img_attachment_id = 0;

  if ( in_array( $context, array( 'homepage', 'static_page', 'blog_home', 'blog_post' ), true ) && $post_id > 0 ) {
    $manual_img = pera_seo_all_get_post_manual_social_image( $post_id );
    if ( ! empty( $manual_img['url'] ) ) {
      $img_url = (string) $manual_img['url'];
      $img_alt = ! empty( $manual_img['alt'] ) ? (string) $manual_img['alt'] : $title;
      $img_width = (int) $manual_img['width'];
      $img_height = (int) $manual_img['height'];
      $img_attachment_id = (int) $manual_img['attachment_id'];
    }
  }

  if ( in_array( $context, array( 'blog_category', 'blog_tag' ), true ) ) {
    $term = get_queried_object();
    if ( $term instanceof WP_Term ) {
      $manual_term_img = pera_seo_all_get_term_manual_social_image( $term );
      if ( ! empty( $manual_term_img['url'] ) ) {
        $img_url = (string) $manual_term_img['url'];
        $img_alt = ! empty( $manual_term_img['alt'] ) ? (string) $manual_term_img['alt'] : $title;
        $img_width = (int) $manual_term_img['width'];
        $img_height = (int) $manual_term_img['height'];
      } else {
        $term_img = function_exists( 'pera_get_term_featured_image_url' )
          ? pera_get_term_featured_image_url( (int) $term->term_id, (string) $term->taxonomy, 'full' )
          : '';
        if ( $term_img !== '' ) {
          $img_url = esc_url( $term_img );
          $img_alt = $title;
        }
      }
    }
  }

  if ( $img_url === '' && $post_id ) {
    $img = pera_seo_all_get_image( $post_id );
    $img_url = $img['url'];
    $img_alt = $img['alt'] ?: $title;
    $img_width = isset( $img['width'] ) ? (int) $img['width'] : 0;
    $img_height = isset( $img['height'] ) ? (int) $img['height'] : 0;
    $img_attachment_id = isset( $img['attachment_id'] ) ? (int) $img['attachment_id'] : 0;
  }

  // Default fallback share image (optional)
  if ( ! $img_url ) {
    $fallback = pera_seo_default_image();
    if ( ! empty($fallback['url']) ) {
      $img_url = $fallback['url'];
      $img_alt = $fallback['alt'] ?: $title;
    }
  }

  $og_type = ( $context === 'blog_post' ) ? 'article' : 'website';

  echo "\n<!-- Pera: SEO / Social -->\n";

  if ( $desc !== '' ) {
    echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
  }

  // Canonical ownership note:
  // - core rel_canonical() is removed in performance cleanup.
  // - this module owns canonical output for all non-property contexts.
  echo '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";

  // Open Graph
  echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '">' . "\n";
  echo '<meta property="og:type" content="' . esc_attr($og_type) . '">' . "\n";
  echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
  $og_url = $canonical;
  if ( $apply_query_noindex && $has_query_string ) {
    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
    $og_url = home_url( add_query_arg( array(), $request_uri ) );
  }
  echo '<meta property="og:url" content="' . esc_url($og_url) . '">' . "\n";

  if ( $desc !== '' ) {
    echo '<meta property="og:description" content="' . esc_attr($desc) . '">' . "\n";
  }

  if ( $img_url ) {
    echo '<meta property="og:image" content="' . esc_url($img_url) . '">' . "\n";
    echo '<meta property="og:image:alt" content="' . esc_attr($img_alt) . '">' . "\n";
    if ( $img_width > 0 && $img_height > 0 ) {
      echo '<meta property="og:image:width" content="' . esc_attr( (string) $img_width ) . '">' . "\n";
      echo '<meta property="og:image:height" content="' . esc_attr( (string) $img_height ) . '">' . "\n";
    }
  }

  // Twitter
  echo '<meta name="twitter:card" content="' . ( $img_url ? 'summary_large_image' : 'summary' ) . '">' . "\n";
  echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";

  if ( $desc !== '' ) {
    echo '<meta name="twitter:description" content="' . esc_attr($desc) . '">' . "\n";
  }

  if ( $img_url ) {
    echo '<meta name="twitter:image" content="' . esc_url($img_url) . '">' . "\n";
    echo '<meta name="twitter:image:alt" content="' . esc_attr($img_alt) . '">' . "\n";
  }

  if ( $context === 'blog_post' && $post_id > 0 ) {
    $is_guide_like_post = function_exists( 'pera_schema_is_guide_like_post' )
      ? pera_schema_is_guide_like_post( $post_id )
      : false;

    $author_id      = (int) get_post_field( 'post_author', $post_id );
    $author_name    = $author_id > 0 ? trim( (string) get_the_author_meta( 'display_name', $author_id ) ) : '';
    $publisher     = (string) get_bloginfo( 'name' );
    $publisher_url = (string) home_url( '/' );
    $publisher_logo = '';
    $custom_logo_id = (int) get_theme_mod( 'custom_logo' );

    if ( $custom_logo_id > 0 ) {
      $publisher_logo = (string) wp_get_attachment_image_url( $custom_logo_id, 'full' );
    }

    $schema = array(
      '@context'         => 'https://schema.org',
      '@type'            => $is_guide_like_post ? 'Article' : 'BlogPosting',
      'mainEntityOfPage' => array(
        '@type' => 'WebPage',
        '@id'   => $canonical,
      ),
      'headline'         => get_the_title( $post_id ),
      'datePublished'    => get_post_time( DATE_W3C, true, $post_id ),
      'dateModified'     => get_post_modified_time( DATE_W3C, true, $post_id ),
      'publisher'        => array(
        '@type' => 'Organization',
        'name'  => $publisher,
        'url'   => $publisher_url,
      ),
    );

    if ( $desc !== '' ) {
      $schema['description'] = $desc;
    }

    if ( $author_name !== '' ) {
      $schema['author'] = array(
        '@type' => 'Person',
        'name'  => $author_name,
      );
      if ( $author_id > 0 ) {
        $schema['author']['url'] = get_author_posts_url( $author_id );
      }
    }

    if ( $publisher_logo !== '' ) {
      $schema['publisher']['logo'] = array(
        '@type' => 'ImageObject',
        'url'   => $publisher_logo,
      );
    }

    if ( $img_attachment_id > 0 && $img_url !== '' ) {
      $schema_image = array(
        '@type' => 'ImageObject',
        'url'   => $img_url,
      );
      if ( $img_width > 0 && $img_height > 0 ) {
        $schema_image['width'] = $img_width;
        $schema_image['height'] = $img_height;
      }
      $schema['image'] = $schema_image;
    }

    $article_schema_type = isset( $schema['@type'] ) ? (string) $schema['@type'] : 'BlogPosting';
    if (
      function_exists( 'pera_schema_should_emit_type' )
        ? pera_schema_should_emit_type(
            $article_schema_type,
            array(
              'context' => 'blog_post',
              'post_id' => $post_id,
            )
          )
        : true
    ) {
      echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
    }

    $breadcrumb_items = $is_guide_like_post && function_exists( 'pera_schema_guide_like_breadcrumb_items' )
      ? pera_schema_guide_like_breadcrumb_items( $post_id )
      : pera_seo_post_breadcrumb_items( $post_id );

    if (
      ! empty( $breadcrumb_items )
      && (
        function_exists( 'pera_schema_should_emit_type' )
          ? pera_schema_should_emit_type(
              'BreadcrumbList',
              array(
                'context' => 'blog_post',
                'post_id' => $post_id,
              )
            )
          : true
      )
    ) {
      $schema_breadcrumb_items = array();

      foreach ( $breadcrumb_items as $index => $item ) {
        if ( empty( $item['name'] ) ) {
          continue;
        }

        $schema_item = array(
          '@type'    => 'ListItem',
          'position' => count( $schema_breadcrumb_items ) + 1,
          'name'     => (string) $item['name'],
        );

        if ( ! empty( $item['url'] ) ) {
          $schema_item['item'] = (string) $item['url'];
        }

        $schema_breadcrumb_items[] = $schema_item;
      }

      if ( ! empty( $schema_breadcrumb_items ) ) {
        $breadcrumb_schema = array(
          '@context'         => 'https://schema.org',
          '@type'            => 'BreadcrumbList',
          '@id'              => $canonical . '#breadcrumb',
          'itemListElement'  => $schema_breadcrumb_items,
        );

        echo '<script type="application/ld+json">' . wp_json_encode( $breadcrumb_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
      }
    }

    if (
      $is_guide_like_post
      && (
        function_exists( 'pera_schema_should_emit_type' )
          ? pera_schema_should_emit_type(
              'FAQPage',
              array(
                'context' => 'guide_like_post',
                'post_id' => $post_id,
              )
            )
          : true
      )
      && function_exists( 'pera_schema_extract_visible_faq_items_from_post' )
    ) {
      $faq_items = pera_schema_extract_visible_faq_items_from_post( $post_id );
      if ( ! empty( $faq_items ) ) {
        $faq_entities = array();

        foreach ( $faq_items as $faq_item ) {
          $question = isset( $faq_item['question'] ) ? trim( (string) $faq_item['question'] ) : '';
          $answer   = isset( $faq_item['answer'] ) ? trim( (string) $faq_item['answer'] ) : '';
          if ( $question === '' || $answer === '' ) {
            continue;
          }

          $faq_entities[] = array(
            '@type' => 'Question',
            'name'  => $question,
            'acceptedAnswer' => array(
              '@type' => 'Answer',
              'text'  => $answer,
            ),
          );
        }

        if ( ! empty( $faq_entities ) ) {
          $faq_schema = array(
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $faq_entities,
          );

          $GLOBALS['pera_schema_faq_emitted'] = true;
          echo '<script type="application/ld+json">' . wp_json_encode( $faq_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
        }
      }
    }
  }

  if ( $context === 'static_page' && $post_id > 0 && pera_seo_all_is_citizenship_page() && $canonical !== '' ) {
    $publisher_name = 'Pera Property';
    $home_url       = (string) home_url( '/' );
    $website_id     = (string) home_url( '/#website' );
    $webpage_id     = $canonical . '#webpage';
    $organization_id = $home_url . '#organization';
    $agent_id       = $home_url . '#realestateagent';
    $service_id     = $canonical . '#service';
    $breadcrumb_id  = $canonical . '#breadcrumb';
    $publisher_logo = '';
    $custom_logo_id = (int) get_theme_mod( 'custom_logo' );

    if ( $custom_logo_id > 0 ) {
      $publisher_logo = (string) wp_get_attachment_image_url( $custom_logo_id, 'full' );
    }

    $schema_image = $img_url;
    if ( $schema_image === '' ) {
      $schema_image = (string) wp_get_attachment_image_url( 55756, 'full' );
    }

    $website_schema = array(
      '@type' => 'WebSite',
      '@id'   => $website_id,
      'url'   => $home_url,
      'name'  => $site_name,
      'publisher' => array(
        '@id' => $organization_id,
      ),
    );

    $organization_schema = array(
      '@type' => 'Organization',
      '@id'   => $organization_id,
      'name'  => $publisher_name,
      'url'   => $home_url,
    );

    if ( $publisher_logo !== '' ) {
      $organization_schema['logo'] = array(
        '@type' => 'ImageObject',
        'url'   => $publisher_logo,
      );
    }

    $real_estate_agent_schema = array(
      '@type' => 'RealEstateAgent',
      '@id'   => $agent_id,
      'name'  => $publisher_name,
      'url'   => $home_url,
      'description' => 'Istanbul-based real estate agency helping international buyers purchase property in Turkey, including citizenship-eligible real estate investments.',
      'telephone' => '+90 532 063 99 78',
      'email' => 'info@peraproperty.com',
      'parentOrganization' => array(
        '@id' => $organization_id,
      ),
      'address' => array(
        '@type' => 'PostalAddress',
        'streetAddress' => 'Gümüşsuyu Mah. Ankara Palas, İnönü Cd. No 59/1',
        'postalCode' => '34437',
        'addressLocality' => 'Beyoğlu',
        'addressRegion' => 'İstanbul',
        'addressCountry' => 'TR',
      ),
      'areaServed' => array(
        '@type' => 'Country',
        'name'  => 'Turkey',
      ),
    );

    if ( $schema_image !== '' ) {
      $real_estate_agent_schema['image'] = $schema_image;
    }

    $webpage_schema = array(
      '@type' => 'WebPage',
      '@id'   => $webpage_id,
      'url'   => $canonical,
      'name'  => $title,
      'isPartOf' => array(
        '@id' => $website_id,
      ),
      'about' => array(
        '@id' => $service_id,
      ),
      'mainEntity' => array(
        '@id' => $service_id,
      ),
      'breadcrumb' => array(
        '@id' => $breadcrumb_id,
      ),
      'publisher' => array(
        '@id' => $organization_id,
      ),
    );

    if ( $desc !== '' ) {
      $webpage_schema['description'] = $desc;
    }

    if ( $schema_image !== '' ) {
      $webpage_schema['image'] = $schema_image;
    }

    $service_schema = array(
      '@type' => 'Service',
      '@id'   => $service_id,
      'name'  => 'Turkish Citizenship by Investment Consultancy',
      'serviceType' => 'Citizenship by investment property advisory',
      'url'   => $canonical,
      'provider' => array(
        '@id' => $agent_id,
      ),
      'areaServed' => array(
        '@type' => 'Country',
        'name'  => 'Turkey',
      ),
      'audience' => array(
        '@type' => 'Audience',
        'audienceType' => 'International property investors and families applying for Turkish citizenship by investment',
      ),
      'mainEntityOfPage' => array(
        '@id' => $webpage_id,
      ),
      'availableChannel' => array(
        '@type' => 'ServiceChannel',
        'serviceUrl' => $canonical,
        'availableLanguage' => array(
          'English',
          'Turkish',
        ),
      ),
    );

    if ( $desc !== '' ) {
      $service_schema['description'] = $desc;
    }

    $breadcrumb_schema = array(
      '@type' => 'BreadcrumbList',
      '@id'   => $breadcrumb_id,
      'itemListElement' => array(
        array(
          '@type' => 'ListItem',
          'position' => 1,
          'name' => 'Home',
          'item' => $home_url,
        ),
        array(
          '@type' => 'ListItem',
          'position' => 2,
          'name' => 'Turkish Citizenship by Investment',
          'item' => $canonical,
        ),
      ),
    );

    $schema_graph = array(
      '@context' => 'https://schema.org',
      '@graph'   => array(
        $website_schema,
        $organization_schema,
        $real_estate_agent_schema,
        $webpage_schema,
        $service_schema,
        $breadcrumb_schema,
      ),
    );

    echo '<script type="application/ld+json">' . wp_json_encode( $schema_graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . '</script>' . "\n";

    $faq_items = pera_seo_all_citizenship_faq_items();
    if ( ! empty( $faq_items ) ) {
      $faq_entities = array();

      foreach ( $faq_items as $faq_item ) {
        if ( empty( $faq_item['question'] ) || empty( $faq_item['answer'] ) ) {
          continue;
        }

        $faq_question = preg_replace( '/^Q:\s*/', '', (string) $faq_item['question'] );

        $faq_entities[] = array(
          '@type' => 'Question',
          'name' => $faq_question,
          'acceptedAnswer' => array(
            '@type' => 'Answer',
            'text' => (string) $faq_item['answer'],
          ),
        );
      }

      if ( ! empty( $faq_entities ) ) {
        $faq_schema = array(
          '@context' => 'https://schema.org',
          '@type' => 'FAQPage',
          'mainEntity' => $faq_entities,
        );

        // Mark FAQ schema as emitted so other schema modules can avoid duplicate FAQPage output.
        $GLOBALS['pera_schema_faq_emitted'] = true;
        echo '<script type="application/ld+json">' . wp_json_encode( $faq_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . '</script>' . "\n";
      }
    }
  }



  if ( $context === 'static_page' && $post_id > 0 && pera_seo_all_is_contact_page( $post_id ) && $canonical !== '' ) {
    $contact_page_id = $canonical . '#contactpage';
    $business_id     = $canonical . '#realestateagent';

    $contact_page_schema = array(
      '@context' => 'https://schema.org',
      '@type' => 'ContactPage',
      '@id' => $contact_page_id,
      'url' => $canonical,
      'name' => $title,
      'mainEntity' => array(
        '@id' => $business_id,
      ),
    );

    if ( $desc !== '' ) {
      $contact_page_schema['description'] = $desc;
    }

    $business_schema = array(
      '@context' => 'https://schema.org',
      '@type' => 'RealEstateAgent',
      '@id' => $business_id,
      'name' => 'Pera Property',
      'url' => 'https://www.peraproperty.com/',
      'telephone' => '+90 532 063 99 78',
      'email' => 'info@peraproperty.com',
      'address' => array(
        '@type' => 'PostalAddress',
        'streetAddress' => 'Gümüşsuyu Mah. Ankara Palas, İnönü Cd. No 59/1',
        'postalCode' => '34437',
        'addressLocality' => 'Beyoğlu / İstanbul',
        'addressCountry' => 'TR',
      ),
      'areaServed' => array(
        '@type' => 'Place',
        'name' => 'Istanbul, Türkiye',
      ),
      'openingHours' => array(
        'Mo-Fr 09:30-18:00',
        'Sa-Su by appointment',
      ),
      'openingHoursSpecification' => array(
        array(
          '@type' => 'OpeningHoursSpecification',
          'dayOfWeek' => array(
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
          ),
          'opens' => '09:30',
          'closes' => '18:00',
        ),
      ),
      'makesOffer' => array(
        array(
          '@type' => 'Offer',
          'itemOffered' => array(
            '@type' => 'Service',
            'name' => 'Istanbul property sales',
          ),
        ),
        array(
          '@type' => 'Offer',
          'itemOffered' => array(
            '@type' => 'Service',
            'name' => 'Property buying consultancy',
          ),
        ),
        array(
          '@type' => 'Offer',
          'itemOffered' => array(
            '@type' => 'Service',
            'name' => 'Selling property in Istanbul',
          ),
        ),
        array(
          '@type' => 'Offer',
          'itemOffered' => array(
            '@type' => 'Service',
            'name' => 'Rental management',
          ),
        ),
        array(
          '@type' => 'Offer',
          'itemOffered' => array(
            '@type' => 'Service',
            'name' => 'Turkish citizenship by investment property advice',
          ),
        ),
      ),
    );

    $schema_graph = array(
      '@context' => 'https://schema.org',
      '@graph' => array(
        $contact_page_schema,
        $business_schema,
      ),
    );

    echo '<script type="application/ld+json">' . wp_json_encode( $schema_graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
  }


  if ( $schema_type !== '' && $canonical !== '' && ! ( $context === 'static_page' && $post_id > 0 && pera_seo_all_is_citizenship_page() ) ) {
    $schema = array(
      '@context' => 'https://schema.org',
      '@type'    => $schema_type,
      '@id'      => $canonical . '#webpage',
      'url'      => $canonical,
      'name'     => $title,
    );

    if ( $desc !== '' ) {
      $schema['description'] = $desc;
    }

    if ( $img_url !== '' ) {
      $schema['image'] = $img_url;
    }

    echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";

    if ( $context === 'homepage' ) {
      $org = array(
        '@context' => 'https://schema.org',
        '@type'    => 'Organization',
        'name'     => $site_name,
        'url'      => (string) home_url( '/' ),
      );
      echo '<script type="application/ld+json">' . wp_json_encode( $org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
    }
  }

  echo "<!-- /Pera: SEO / Social -->\n\n";

}, 12 );


/**
 * Optional term-level FAQ schema via ACF/meta field on blog category/tag archives.
 * Field key: seo_faq_schema_json (raw JSON only, without <script> tags).
 */
add_action( 'wp_head', function () {
  if ( is_admin() ) {
    return;
  }

  if ( ! is_category() && ! is_tag() ) {
    return;
  }

  if ( ! empty( $GLOBALS['pera_schema_faq_emitted'] ) ) {
    return;
  }

  if (
    function_exists( 'pera_schema_should_emit_type' )
    && ! pera_schema_should_emit_type(
      'FAQPage',
      array(
        'context' => 'manual_term_faq',
        'term_id' => (int) get_queried_object_id(),
      )
    )
  ) {
    return;
  }

  $term = get_queried_object();
  if ( ! ( $term instanceof WP_Term ) ) {
    return;
  }

  $faq_json = '';

  if ( function_exists( 'pera_get_term_acf_field' ) ) {
    $raw = pera_get_term_acf_field( 'seo_faq_schema_json', $term );
    if ( is_scalar( $raw ) ) {
      $faq_json = trim( (string) $raw );
    }
  }

  if ( $faq_json === '' ) {
    $faq_json = trim( (string) get_term_meta( (int) $term->term_id, 'seo_faq_schema_json', true ) );
  }

  if ( $faq_json === '' ) {
    return;
  }

  $decoded = json_decode( $faq_json, true );
  if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded ) ) {
    return;
  }

  $encoded = wp_json_encode( $decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
  if ( ! is_string( $encoded ) || $encoded === '' ) {
    return;
  }

  echo "<!-- FAQ Schema (Term ACF) -->\n";
  echo '<script type="application/ld+json">' . $encoded . '</script>' . "\n";
  $GLOBALS['pera_schema_faq_emitted'] = true;
}, 13 );

add_action( 'wp_head', function () {
  if ( is_admin() ) {
    return;
  }

  if ( ! is_front_page() ) {
    return;
  }

  if ( function_exists( 'pera_schema_has_active_seo_plugin' ) && pera_schema_has_active_seo_plugin() ) {
    return;
  }

  if ( ! empty( $GLOBALS['pera_schema_faq_emitted'] ) ) {
    return;
  }

  if (
    function_exists( 'pera_schema_should_emit_type' )
    && ! pera_schema_should_emit_type(
      'FAQPage',
      array(
        'context' => 'homepage',
      )
    )
  ) {
    return;
  }

  if ( ! function_exists( 'get_field' ) ) {
    return;
  }

  $front_page_id = (int) get_option( 'page_on_front' );
  if ( $front_page_id <= 0 ) {
    $front_page_id = (int) get_queried_object_id();
  }

  $faq_rows = $front_page_id > 0
    ? get_field( 'faq', $front_page_id )
    : get_field( 'faq' );
  if ( ! is_array( $faq_rows ) || empty( $faq_rows ) ) {
    return;
  }

  $faq_entities = array();

  foreach ( $faq_rows as $faq_row ) {
    $question = isset( $faq_row['question'] ) ? trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $faq_row['question'] ) ) ) : '';
    $answer   = isset( $faq_row['answer'] ) ? trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $faq_row['answer'] ) ) ) : '';

    if ( $question === '' || $answer === '' ) {
      continue;
    }

    $faq_entities[] = array(
      '@type' => 'Question',
      'name'  => $question,
      'acceptedAnswer' => array(
        '@type' => 'Answer',
        'text'  => $answer,
      ),
    );
  }

  if ( empty( $faq_entities ) ) {
    return;
  }

  $faq_schema = array(
    '@context'   => 'https://schema.org',
    '@type'      => 'FAQPage',
    'mainEntity' => $faq_entities,
  );

  $GLOBALS['pera_schema_faq_emitted'] = true;
  echo '<script type="application/ld+json">' . wp_json_encode( $faq_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}, 14 );
