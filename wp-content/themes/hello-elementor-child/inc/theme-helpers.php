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
