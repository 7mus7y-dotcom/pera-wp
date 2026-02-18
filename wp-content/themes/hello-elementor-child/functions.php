<?php
/**
 * Pera Property – Hello Elementor Child Theme
 * Clean, optimised, production-ready
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Access control helpers shared by admin + front-end.
 */
require_once get_stylesheet_directory() . '/inc/access-control.php';

/**
 * Load taxonomy term meta (term excerpt + featured image).
 * Used by inc/seo-all.php for term meta descriptions + social images.
 */
require_once get_stylesheet_directory() . '/inc/taxonomy-meta.php';
/**
 * SEO helper functions used by templates.
 */
require_once get_stylesheet_directory() . '/inc/seo-helpers.php';

/**
 * Enforce district ancestors for property assignments.
 */
require_once get_stylesheet_directory() . '/inc/district-ancestors.php';
/**
 * Favourites (property)
 */
require_once get_stylesheet_directory() . '/inc/favourites.php';
require_once get_stylesheet_directory() . '/inc/property-pagination.php';
require_once get_stylesheet_directory() . '/inc/property-archive-query.php';
require_once get_stylesheet_directory() . '/inc/property-card-helpers.php';
require_once get_stylesheet_directory() . '/inc/client-portal.php';
require_once get_stylesheet_directory() . '/inc/crm-data.php';
require_once get_stylesheet_directory() . '/inc/crm-router.php';
require_once get_stylesheet_directory() . '/inc/crm-client-view.php';
require_once get_stylesheet_directory() . '/inc/disable-hello-parent-loads.php';
require_once get_stylesheet_directory() . '/inc/portfolio-token.php';

/**
 * Hotfix: prevent taxonomy query vars from arriving as arrays in WP core.
 *
 * Multi-select filters may submit district[] / property_tags[] in the URL.
 * Those names match taxonomy query vars, and WP core expects scalar strings
 * while parsing the main query. Unsetting array values here prevents fatals
 * without affecting template/AJAX filtering that reads from $_GET.
 */
add_filter( 'request', function ( $vars ) {
  // Strip any array-valued taxonomy query-vars for the "property" object type.
  // Prevents WP core parse_tax_query() from fatalling when it encounters array-shaped vars.
  foreach ( $vars as $k => $v ) {
    if ( ! is_array( $v ) ) {
      continue;
    }
    if ( taxonomy_exists( $k ) && is_object_in_taxonomy( 'property', $k ) ) {
      unset( $vars[ $k ] );
    }
  }

  return $vars;
}, 1 );

/**
 * Canonicalize legacy array taxonomy params on the property archive URL.
 *
 * This runs in functions.php (template_redirect, priority 1) so it executes
 * before template output and also on HEAD requests.
 */
add_action( 'template_redirect', function () {
  if ( is_admin() ) {
    return;
  }

  $request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
  $request_path = wp_parse_url( $request_uri, PHP_URL_PATH );

  $property_path = wp_parse_url( home_url( '/property/' ), PHP_URL_PATH );
  $is_exact_property_path = is_string( $request_path )
    && is_string( $property_path )
    && trailingslashit( $request_path ) === trailingslashit( $property_path );

  $is_property_archive_page = is_page_template( 'archive-property.php' ) || $is_exact_property_path;

  if ( ! $is_property_archive_page || empty( $_GET ) ) {
    return;
  }

  $taxonomy_keys = get_object_taxonomies( 'property', 'names' );
  $taxonomy_keys = is_array( $taxonomy_keys ) ? $taxonomy_keys : array();
  $taxonomy_keys = array_values( array_unique( array_merge( array( 'district', 'property_tags' ), $taxonomy_keys ) ) );

  $canonical_query = $_GET;
  $needs_redirect  = false;

  foreach ( $taxonomy_keys as $taxonomy_key ) {
    if ( ! isset( $_GET[ $taxonomy_key ] ) || ! is_array( $_GET[ $taxonomy_key ] ) ) {
      continue;
    }

    $normalized_values = array();

    foreach ( $_GET[ $taxonomy_key ] as $raw_value ) {
      $value = sanitize_title( wp_unslash( (string) $raw_value ) );

      if ( $value === '' || in_array( $value, $normalized_values, true ) ) {
        continue;
      }

      $normalized_values[] = $value;
    }

    if ( ! empty( $normalized_values ) ) {
      $canonical_query[ $taxonomy_key ] = implode( ',', $normalized_values );
    } else {
      unset( $canonical_query[ $taxonomy_key ] );
    }

    $needs_redirect = true;
  }

  if ( ! $needs_redirect ) {
    return;
  }

  $base_url      = trailingslashit( home_url( '/property/' ) );
  $canonical_url = add_query_arg( $canonical_query, $base_url );

  wp_safe_redirect( $canonical_url, 301 );
  exit;
}, 1 );



/**
 * Conditionally load SEO modules (must load before wp_head fires)
 */
add_action( 'wp', function () {

  if ( is_admin() ) return;

  $inc = trailingslashit( get_stylesheet_directory() ) . 'inc/';

  // 1) Single Property
  if ( is_singular( 'property' ) ) {
    require_once $inc . 'seo-property.php';
    return;
  }
  
    $property_taxonomies = array(
    'district',
    'region',
    'property_type',
    'property_tags',
    'special',
  );
  $property_taxonomies = array_filter( $property_taxonomies, 'taxonomy_exists' );

  if ( ! empty( $property_taxonomies ) && is_tax( $property_taxonomies ) ) {
    require_once $inc . 'seo-property-archive.php';
    return;
  }

  // 2) Property Archive (your search page)
  if ( is_post_type_archive( 'property' ) ) {
    require_once $inc . 'seo-property-archive.php';
    return;
  }

  // 3) Everything else (pages, posts, taxonomies, etc.)
  require_once $inc . 'seo-all.php';

}, 1 );


/* =======================================================
   HELPERS
   ======================================================= */

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

/* =======================================================
   TEMPLATE ROUTING
   ======================================================= */

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
add_filter( 'template_include', 'pera_force_property_archive_template', 20 );

/* =======================================================
   GLOBAL: ENQUEUE CORE STYLES & SCRIPTS (ALL PAGES)
   ======================================================= */
add_action( 'wp_enqueue_scripts', function () {

  /* =========================
     0) ALWAYS
  ========================= */

  // main.css everywhere
  wp_enqueue_style(
    'pera-main-css',
    get_stylesheet_directory_uri() . '/css/main.css',
    array(),
    pera_get_asset_version( '/css/main.css' )
  );

  // main.js everywhere
  wp_enqueue_script(
    'pera-main-js',
    get_stylesheet_directory_uri() . '/js/main.js',
    array(),
    pera_get_asset_version( '/js/main.js' ),
    true
  );

  /* =========================
     1) VIEW FLAGS
  ========================= */

  $is_home = is_front_page() || is_page_template( 'home-page.php' );

    $is_property_archive = is_post_type_archive( 'property' ) || is_tax( array(
      'property_type',
      'region',
      'district',
      'special',
      'property_tags',
    ) );

  $is_single_property = is_singular( 'property' );

  $is_blog_page    = is_page_template( 'page-posts.php' ) || is_page( 'blog' );
  $is_single_post  = is_singular( 'post' );
  $is_blog_archive = function_exists( 'pera_is_blog_archive' ) ? pera_is_blog_archive() : false;

  // Specific templates
  $is_contact_page = is_page_template( 'page-contact.php' );
  $is_about_new    = is_page_template( 'page-about-new.php' );
  $is_favourites_page = is_page_template( 'page-favourites.php' );
  $is_property_map = is_page_template( 'page-property-map.php' );
  $is_enquiry_page = is_page_template( 'page-citizenship.php' ) ||
    is_page_template( 'page-rent-with-pera.php' ) ||
    is_page_template( 'page-sell-with-pera.php' ) ||
    is_page_template( 'page-book-a-consultancy.php' ) ||
    $is_favourites_page ||
    is_singular( 'property' ) ||
    is_singular( 'bodrum-property' );

/* =========================
   2) slider.css
   Rule: home, single-property, single-post, contact, about-new, single-bodrum-property
   NOT on property archives / general archives
========================= */

$is_single_bodrum_property = is_singular( 'bodrum-property' );

$needs_slider = (
  $is_home ||
  $is_single_property ||
  $is_single_bodrum_property ||
  $is_single_post ||
  $is_contact_page ||
  $is_about_new
);

if ( $needs_slider ) {
  wp_enqueue_style(
    'pera-slider-css',
    get_stylesheet_directory_uri() . '/css/slider.css',
    array( 'pera-main-css' ),
    pera_get_asset_version( '/css/slider.css' )
  );
}


  /* =========================
     3) property.css
     Rule: property archive OR single property OR home
  ========================= */

  if ( $is_property_archive || $is_single_property || $is_single_bodrum_property || $is_home ) {
    wp_enqueue_style(
      'pera-property-css',
      get_stylesheet_directory_uri() . '/css/property.css',
      array( 'pera-main-css' ),
      pera_get_asset_version( '/css/property.css' )
    );
  }

  /* =========================
     4) property-card.css
     Rule: home OR property archive OR single property OR single post
  ========================= */

  if ( $is_home || $is_property_archive || $is_single_property || $is_single_post || $is_favourites_page || $is_property_map ) {

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

  /* =========================
     5) blog.css
     Rule: blog page OR single post OR blog archive
  ========================= */

  if ( $is_blog_page || $is_single_post || $is_blog_archive ) {

    $deps = array( 'pera-main-css' );
    if ( $needs_slider ) {
      $deps[] = 'pera-slider-css';
    }

    wp_enqueue_style(
      'pera-blog-css',
      get_stylesheet_directory_uri() . '/css/blog.css',
      $deps,
      pera_get_asset_version( '/css/blog.css' )
    );
  }

  /* =========================
     6) posts.css
     Rule: blog page OR single post OR blog archive OR single property
  ========================= */

  if ( $is_blog_page || $is_single_post || $is_blog_archive || $is_single_property ) {

    $deps = array( 'pera-main-css' );
    if ( $needs_slider ) {
      $deps[] = 'pera-slider-css';
    }

    wp_enqueue_style(
      'pera-posts-css',
      get_stylesheet_directory_uri() . '/css/posts.css',
      $deps,
      pera_get_asset_version( '/css/posts.css' )
    );
  }

  /* =========================
     7) favourites.js
     Rule: home OR property archive OR single property
  ========================= */

  if ( $is_home || $is_property_archive || $is_single_property || $is_single_post || $is_favourites_page ) {
    wp_enqueue_script(
      'pera-favourites',
      get_stylesheet_directory_uri() . '/js/favourites.js',
      array(),
      pera_get_asset_version( '/js/favourites.js' ),
      true
    );

    wp_localize_script(
      'pera-favourites',
      'peraFavourites',
      array(
        'ajax_url'     => admin_url( 'admin-ajax.php' ),
        'nonce'        => wp_create_nonce( 'pera_favourites' ),
        'is_logged_in' => is_user_logged_in(),
      )
    );
  }

  if ( $is_enquiry_page ) {
    wp_enqueue_script(
      'pera-enquiry-nonce',
      get_stylesheet_directory_uri() . '/js/enquiry-nonce.js',
      array(),
      pera_get_asset_version( '/js/enquiry-nonce.js' ),
      true
    );

    wp_localize_script(
      'pera-enquiry-nonce',
      'peraEnquiryNonce',
      array(
        'ajax_url'          => admin_url( 'admin-ajax.php' ),
        'action'            => 'pera_get_enquiry_nonces',
        'issued_at'         => time(),
        'max_age_seconds'   => 900,
      )
    );
  }

  /* =========================
     8) property-map.js (Property Map template only)
  ========================= */

  if ( $is_property_map ) {
    $google_maps_url = 'https://maps.googleapis.com/maps/api/js';
    if ( defined( 'PERA_GOOGLE_MAPS_KEY' ) && PERA_GOOGLE_MAPS_KEY ) {
      $google_maps_url = add_query_arg(
        array( 'key' => rawurlencode( PERA_GOOGLE_MAPS_KEY ) ),
        $google_maps_url
      );
    }

    wp_enqueue_script(
      'pera-google-maps',
      $google_maps_url,
      array(),
      null,
      true
    );

    wp_enqueue_script(
      'pera-property-map',
      get_stylesheet_directory_uri() . '/js/property-map.js',
      array( 'pera-google-maps' ),
      pera_get_asset_version( '/js/property-map.js' ),
      true
    );
  }

}, 20 );


/**
 * Dequeue parent Hello Elementor CSS that constrains .site-main width
 * (safe: leaves your child + main.css intact)
 */
add_action( 'wp_enqueue_scripts', function () {

  // Parent Hello Elementor handles commonly used:
  // - hello-elementor
  // - hello-elementor-style
  // - hello-elementor-theme-style
  // (Dequeue whichever are actually enqueued on your site.)

  wp_dequeue_style( 'hello-elementor' );
  wp_deregister_style( 'hello-elementor' );

  wp_dequeue_style( 'hello-elementor-style' );
  wp_deregister_style( 'hello-elementor-style' );

  wp_dequeue_style( 'hello-elementor-theme-style' );
  wp_deregister_style( 'hello-elementor-theme-style' );

}, 20 );


/**
 * Remove Gutenberg block styles on frontend
 * Safe for lean / non-block themes
 */
add_action( 'wp_enqueue_scripts', function () {

    if ( is_admin() ) {
        return;
    }

    wp_dequeue_style( 'wp-block-library' );
    wp_dequeue_style( 'wp-block-library-theme' );
    wp_dequeue_style( 'wc-block-style' ); // WooCommerce blocks (safe even if WC inactive)

}, 100 );

    /* =======================================================
    DEFER SCRIPTS
    ======================================================= */


add_filter( 'style_loader_tag', function ( $html, $handle ) {

  // Only optimise homepage
  if ( ! ( is_front_page() || is_page_template( 'home-page.php' ) ) ) {
    return $html;
  }

  $defer_styles = [
    'pera-slider-css',
    'pera-property-card',
  ];

  if ( ! in_array( $handle, $defer_styles, true ) ) {
    return $html;
  }

  $original = $html;

  $html = preg_replace(
    '/rel=(["\'])stylesheet\1/i',
    'rel=$1stylesheet$1 media="print" onload="this.media=\'all\'"',
    $html,
    1
  );

  $html .= '<noscript>' . $original . '</noscript>';

  return $html;

}, 10, 2 );

add_filter( 'script_loader_tag', function ( $tag, $handle ) {
  $defer_scripts = array(
    'pera-favourites',
    'pera-home-hero-search',
  );

  if ( ! in_array( $handle, $defer_scripts, true ) ) {
    return $tag;
  }

  if ( false !== strpos( $tag, ' defer' ) ) {
    return $tag;
  }

  return str_replace( ' src=', ' defer src=', $tag );
}, 10, 2 );


/* =======================================================
   6. REGISTER 450px card size
   ======================================================= */


add_action( 'after_setup_theme', function () {
  add_image_size( 'pera-card', 800, 450, true ); // 16:9 crop, good for cards
});


/* =======================================================
   6. REGISTER MENUS
   ======================================================= */
add_action( 'after_setup_theme', function() {
    register_nav_menus( array(
        'footer_menu'   => __( 'Footer Menu', 'hello-elementor-child' ),
        'guidance'      => __( 'Guidance Menu', 'hello-elementor-child' ),
        'main_menu_v1'  => __( 'Main Menu v1', 'hello-elementor-child' ),
    ) );
});

/* =======================================================
   LOGIN SCREEN (wp-login.php): login.css + BRANDING
   ======================================================= */
add_action( 'login_enqueue_scripts', function () {

  $css_rel  = '/css/login.css';
  $css_url  = get_stylesheet_directory_uri() . $css_rel;

  // Cache-bust using file modified time (falls back to theme version)
  $ver = pera_get_asset_version( $css_rel );

  wp_enqueue_style( 'pera-login', $css_url, array(), $ver );

  // Optional: load your theme font if your login.css relies on it
  // wp_enqueue_style( 'pera-fonts', get_stylesheet_directory_uri() . '/css/fonts.css', array(), $ver );
}, 20 );

add_filter( 'login_headerurl', function () {
  return home_url( '/' );
} );

/* =======================================================
   GLOBAL: INLINE SVG SPRITE (icons.svg)
   ======================================================= */
add_action( 'wp_footer', function () {
  $sprite_path = get_stylesheet_directory() . '/logos-icons/icons.svg';

  if ( ! file_exists( $sprite_path ) ) {
    return;
  }

  $svg = file_get_contents( $sprite_path );
  if ( ! $svg ) {
    return;
  }

  $svg = preg_replace( '/<\?xml[^>]*\?>/i', '', $svg );

  if ( preg_match( '/<svg\b[^>]*>/i', $svg, $match ) ) {
    $svg_tag = $match[0];
    $new_tag = $svg_tag;

    if ( stripos( $new_tag, 'style=' ) !== false ) {
      $new_tag = preg_replace(
        '/style=(["\'])(.*?)\1/i',
        'style=$1$2;position:absolute;width:0;height:0;overflow:hidden$1',
        $new_tag,
        1
      );
    } else {
      $new_tag = rtrim( substr( $new_tag, 0, -1 ) ) . ' style="position:absolute;width:0;height:0;overflow:hidden">';
    }

    if ( stripos( $new_tag, 'aria-hidden=' ) === false ) {
      $new_tag = rtrim( substr( $new_tag, 0, -1 ) ) . ' aria-hidden="true">';
    }

    if ( stripos( $new_tag, 'focusable=' ) === false ) {
      $new_tag = rtrim( substr( $new_tag, 0, -1 ) ) . ' focusable="false">';
    }

    $svg = preg_replace( '/<svg\b[^>]*>/i', $new_tag, $svg, 1 );
  }

  echo $svg;
}, 20 );

add_filter( 'login_headertext', function () {
  return 'Pera Property – Client Login';
} );

add_filter( 'login_redirect', function ( $redirect_to, $requested_redirect_to, $user ) {
  if ( ! $user || is_wp_error( $user ) ) {
    return $redirect_to;
  }

  if ( user_can( $user, 'manage_options' ) ) {
    if ( ! empty( $redirect_to ) ) {
      return $redirect_to;
    }

    if ( ! empty( $requested_redirect_to ) ) {
      return $requested_redirect_to;
    }

    return admin_url();
  }

  if ( ! empty( $requested_redirect_to ) ) {
    return $requested_redirect_to;
  }

  if ( ! empty( $redirect_to ) ) {
    return $redirect_to;
  }

  return home_url( '/my-favourites/' );
}, 10, 3 );


add_action('login_enqueue_scripts', function () {

  $bg = wp_get_attachment_image_url(55484, 'full');

  if ($bg) {
    wp_add_inline_style('pera-login', "
      body.login {
        background-image: url('{$bg}');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
      }
      body.login:before {
        content:'';
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.55); /* brand overlay */
        z-index: -1;
      }
    ");
  }

}, 30);

/* =======================================================
   Allow registration
   ======================================================= */

add_action('login_init', function () {
  if (!empty($_GET['action'])) {
    error_log('WP-LOGIN ACTION: ' . sanitize_text_field($_GET['action']));
  }
});


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
add_action( 'wp_head', 'pera_preload_fonts', 1 );


/* =======================================================
   8. Floating WhatsApp Button (global except client login)
   (currently disabled by comment)
   ======================================================= */

function pera_floating_whatsapp_button() {

    // Do not output on wp-login.php
    if ( isset( $GLOBALS['pagenow'] ) && $GLOBALS['pagenow'] === 'wp-login.php' ) {
        return;
    }

    $is_crm_route = function_exists( 'pera_is_crm_route' ) && pera_is_crm_route();

    if ( $is_crm_route && is_user_logged_in() && function_exists( 'pera_crm_user_can_access' ) && pera_crm_user_can_access() ) {
        $crm_overdue_count = function_exists( 'pera_crm_get_overdue_reminders_count_for_current_user' )
            ? (int) pera_crm_get_overdue_reminders_count_for_current_user()
            : 0;
        $crm_label = $crm_overdue_count > 0
            ? sprintf( 'CRM (%d overdue reminders)', $crm_overdue_count )
            : 'CRM';
        ?>
        <a href="<?php echo esc_url( home_url( '/crm' ) ); ?>"
           class="header-crm-toggle crm-floating-toggle"
           aria-label="<?php echo esc_attr( $crm_label ); ?>">
            <svg class="icon" aria-hidden="true">
                <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-users-group' ); ?>"></use>
            </svg>
            <?php if ( $crm_overdue_count > 0 ) : ?>
                <span class="header-icon-dot" aria-hidden="true"></span>
            <?php endif; ?>
        </a>
        <?php
        return;
    }

    ?>
    <a href="https://wa.me/905452054356?text=Hello%20Pera%20Property%2C%20I%27d%20like%20to%20learn%20more%20about%20your%20Istanbul%20properties."
       class="floating-whatsapp"
       id="floating-whatsapp"
       aria-label="Chat on WhatsApp"
       target="_blank"
       rel="noopener">

        <span class="floating-whatsapp__tooltip">
            Chat on WhatsApp
        </span>

        <svg class="icon" aria-hidden="true">
            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-whatsapp' ); ?>"></use>
        </svg>

    </a>
    <?php
}
add_action( 'wp_footer', 'pera_floating_whatsapp_button' );

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
add_action( 'after_switch_theme', 'pera_register_forgot_password_page' );



/**
 * home-page-dev JS (hero search logic)
 */
 
 add_action( 'wp_enqueue_scripts', function () {

  if ( ! is_page_template( 'home-page.php' ) ) {
    return;
  }

  wp_enqueue_script(
    'pera-home-hero-search',
    get_stylesheet_directory_uri() . '/js/home-hero-search.js',
    array(),
    pera_get_asset_version( '/js/home-hero-search.js' ),
    true
  );

}, 40 );

/**
 * -------------------------------------------------
 * V2 Search / Index System (isolated, non-breaking)
 * -------------------------------------------------
 */
/**
 * TEMP: Homepage test template assets
 * Safe to delete after testing
 */
require_once get_stylesheet_directory() . '/inc/home-page-test-assets.php';

require_once get_stylesheet_directory() . '/inc/v2-units-index.php';
require_once get_stylesheet_directory() . '/inc/ajax-property-archive.php';

if ( is_admin() ) {
  require_once get_stylesheet_directory() . '/inc/filter-for-admin-panel.php';
}


/**
 * functions.php (or your existing loader section)
 * Conditionally load /inc/enquiry.php only on:
 * - page-citizenship.php
 * - page-rent-with-pera.php
 * - page-sell-with-pera.php
 * - single-property.php
 */
/**
 * Conditionally load enquiry handler early enough for init hook.
 * Location: functions.php
 */
add_action( 'init', function () {

  if ( wp_doing_ajax() && isset( $_REQUEST['action'] ) && sanitize_key( (string) wp_unslash( $_REQUEST['action'] ) ) === 'pera_get_enquiry_nonces' ) {
    require_once get_stylesheet_directory() . '/inc/enquiry.php';
    return;
  }

  // Always load if this is a relevant POST (so submissions work even if template checks fail)
  if ( $_SERVER['REQUEST_METHOD'] === 'POST' && ( isset( $_POST['sr_action'] ) || isset( $_POST['pera_citizenship_action'] ) || isset( $_POST['fav_enquiry_action'] ) ) ) {
    require_once get_stylesheet_directory() . '/inc/enquiry.php';
    return;
  }

  // Otherwise load only on relevant front-end views
  if ( is_admin() ) {
    return;
  }

  // Single property
  if ( is_singular( 'property' ) || is_singular( 'bodrum-property' ) ) {
    require_once get_stylesheet_directory() . '/inc/enquiry.php';
    return;
  }

  // Pages by template (only works if these are actual template filenames in your theme)
  if (
    is_page_template( 'page-citizenship.php' ) ||
    is_page_template( 'page-rent-with-pera.php' ) ||
    is_page_template( 'page-sell-with-pera.php' ) ||
    is_page_template( 'page-book-a-consultancy.php' ) ||
    is_page_template( 'page-favourites.php' )
  ) {
    require_once get_stylesheet_directory() . '/inc/enquiry.php';
    return;
  }

  // Safety fallback: if your pages are not using those exact filenames, load by slug as well
  if ( is_page( array( 'citizenship-by-investment', 'rent-with-pera', 'sell-with-pera', 'my-favourites' ) ) ) {
    require_once get_stylesheet_directory() . '/inc/enquiry.php';
    return;
  }

}, 1 );




/* =======================================================
    GOOGLE MAPS
======================================================= */

add_filter('acf/fields/google_map/api', function ($api) {
  if ( defined('PERA_GOOGLE_MAPS_KEY') && PERA_GOOGLE_MAPS_KEY ) {
    $api['key'] = PERA_GOOGLE_MAPS_KEY;
  }
  return $api;
});

/**
 * Remove language switcher from wp-login.php
 */
add_filter( 'login_display_language_dropdown', '__return_false' );


/**
 * Enable Excerpt field on Pages (for SEO meta descriptions).
 */
add_action( 'init', function () {
  add_post_type_support( 'page', 'excerpt' );
}, 20 );



add_action( 'wp_enqueue_scripts', function () {
  if ( ! is_user_logged_in() || ! function_exists( 'pera_is_crm_route' ) || ! pera_is_crm_route() ) {
    return;
  }

  wp_enqueue_script(
    'pera-crm-push',
    get_stylesheet_directory_uri() . '/js/crm-push.js',
    array(),
    pera_get_asset_version( '/js/crm-push.js' ),
    true
  );

  $public_key = function_exists( 'peracrm_push_get_public_config' ) ? peracrm_push_get_public_config() : array();

  wp_localize_script(
    'pera-crm-push',
    'peraCrmPush',
    array(
      'swUrl'          => esc_url_raw( (string) ( $public_key['swUrl'] ?? home_url( '/peracrm-sw.js' ) ) ),
      'publicKey'      => (string) ( $public_key['publicKey'] ?? ( defined( 'PERACRM_VAPID_PUBLIC_KEY' ) ? (string) PERACRM_VAPID_PUBLIC_KEY : '' ) ),
      'subscribeUrl'   => esc_url_raw( (string) ( $public_key['subscribeUrl'] ?? rest_url( 'peracrm/v1/push/subscribe' ) ) ),
      'unsubscribeUrl' => esc_url_raw( (string) ( $public_key['unsubscribeUrl'] ?? rest_url( 'peracrm/v1/push/unsubscribe' ) ) ),
      'digestRunUrl'   => esc_url_raw( (string) ( $public_key['digestRunUrl'] ?? rest_url( 'peracrm/v1/push/digest/run' ) ) ),
      'debugUrl'       => esc_url_raw( (string) ( $public_key['debugUrl'] ?? rest_url( 'peracrm/v1/push/debug' ) ) ),
      'canRunDigest'   => isset( $public_key['canRunDigest'] ) ? (bool) $public_key['canRunDigest'] : ( function_exists( 'peracrm_push_user_can_run_digest' ) ? (bool) peracrm_push_user_can_run_digest( get_current_user_id() ) : false ),
      'isConfigured'   => isset( $public_key['isConfigured'] ) ? (bool) $public_key['isConfigured'] : false,
      'missingReasons' => isset( $public_key['missingReasons'] ) && is_array( $public_key['missingReasons'] ) ? $public_key['missingReasons'] : array(),
      'debug'          => function_exists( 'peracrm_push_debug_snapshot' ) ? peracrm_push_debug_snapshot( get_current_user_id() ) : array(),
      'clickUrl'       => (string) ( $public_key['clickUrl'] ?? '/crm/tasks/' ),
      'restNonce'      => wp_create_nonce( 'wp_rest' ),
    )
  );
}, 45 );
