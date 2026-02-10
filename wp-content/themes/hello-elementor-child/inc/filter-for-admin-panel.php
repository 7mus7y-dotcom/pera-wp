<?php
/**
 * Admin-only filters/actions (wp-admin list tables, quick edit, columns, sorting).
 *
 * NOTE: This file is the single home for wp-admin customisations.
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( defined( 'PERA_ADMIN_PANEL_FILTERS_LOADED' ) ) {
  return;
}

define( 'PERA_ADMIN_PANEL_FILTERS_LOADED', true );

/* ==============================
 * Admin Only Boot
 * ============================== */

if ( ! is_admin() ) {
  return;
}

if ( ! function_exists( 'pera_block_employee_admin_access' ) ) {
  function pera_block_employee_admin_access(): void {
    if ( ! is_user_logged_in() ) {
      return;
    }

    if ( ! pera_is_employee() ) {
      return;
    }

    $user = wp_get_current_user();
    if ( ! $user || ! $user->exists() ) {
      return;
    }

    if ( in_array( 'administrator', (array) $user->roles, true ) ) {
      return;
    }

    if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
      return;
    }

    if ( wp_doing_cron() ) {
      return;
    }

    if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
      return;
    }

    wp_safe_redirect( home_url( '/' ) );
    exit;
  }
}

add_action( 'admin_init', 'pera_block_employee_admin_access', 1 );

if ( ! function_exists( 'pera_admin_bootstrap' ) ) {
  function pera_admin_bootstrap(): void {
    /* ==============================
     * Property List Table (Columns + Sorting)
     * ============================== */
    add_filter( 'manage_property_posts_columns', 'pera_admin_property_columns', 999 );
    add_action( 'manage_property_posts_custom_column', 'pera_admin_property_column_content', 10, 2 );
    add_filter( 'manage_edit-property_sortable_columns', 'pera_admin_property_sortable_columns' );
    add_action( 'pre_get_posts', 'pera_admin_property_sortable_orderby' );
    add_filter( 'post_row_actions', 'pera_admin_property_row_actions', 10, 2 );

    /* ==============================
     * Quick Edit (District add + Specials removal)
     * ============================== */
    add_action( 'quick_edit_custom_box', 'pera_admin_property_quick_edit_fields', 10, 2 );
    add_action( 'save_post_property', 'pera_admin_property_quick_edit_save', 10, 2 );
    add_action( 'admin_enqueue_scripts', 'pera_admin_property_quick_edit_assets' );
    add_action( 'bulk_edit_custom_box', 'pera_admin_property_bulk_edit_fields', 10, 2 );

    /**
     * Specials appears in Quick Edit because the taxonomy is registered with
     * show_in_quick_edit enabled (WP defaults to true unless explicitly disabled).
     * We suppress it for property rows using quick_edit_show_taxonomy.
     */
    add_filter( 'quick_edit_show_taxonomy', 'pera_admin_hide_special_quick_edit', 10, 3 );

    /* ==============================
     * Admin Uploads
     * ============================== */
    add_filter( 'upload_mimes', 'pera_allow_svg_uploads' );
  }
}

add_action( 'admin_init', 'pera_admin_bootstrap', 5 );

/* ==============================
 * Property Columns
 * ============================== */

if ( ! function_exists( 'pera_admin_property_columns' ) ) {
  function pera_admin_property_columns( array $columns ): array {

    // If plugin already added any obvious project-name column, do nothing.
    foreach ( $columns as $key => $label ) {
      $k = strtolower( (string) $key );
      $l = strtolower( (string) $label );

      if (
        $k === 'project_name' ||
        $k === 'project-name' ||
        $k === 'pera_project_name' ||
        ( strpos( $k, 'project' ) !== false && strpos( $k, 'name' ) !== false ) ||
        $l === 'project name' ||
        $l === 'project_name'
      ) {
        return $columns;
      }
    }

    // Otherwise, inject after Title.
    $new = array();
    foreach ( $columns as $key => $label ) {
      $new[ $key ] = $label;
      if ( $key === 'title' ) {
        $new['pera_project_name'] = 'Project name';
      }
    }

    return $new;
  }
}

if ( ! function_exists( 'pera_admin_property_column_content' ) ) {
  function pera_admin_property_column_content( string $column, int $post_id ): void {
    if ( $column !== 'pera_project_name' ) {
      return;
    }

    $project_name = '';

    if ( function_exists( 'get_field' ) ) {
      $project_name = (string) get_field( 'project_name', $post_id );
    }

    if ( $project_name === '' ) {
      $project_name = (string) get_post_meta( $post_id, 'project_name', true );
    }

    echo esc_html( $project_name );
  }
}

if ( ! function_exists( 'pera_admin_property_sortable_columns' ) ) {
  function pera_admin_property_sortable_columns( array $columns ): array {
    $columns['pera_project_name'] = 'pera_project_name';
    return $columns;
  }
}

if ( ! function_exists( 'pera_admin_property_sortable_orderby' ) ) {
  function pera_admin_property_sortable_orderby( WP_Query $query ): void {
    if ( ! is_admin() || ! $query->is_main_query() ) {
      return;
    }

    $orderby = $query->get( 'orderby' );
    if ( $orderby !== 'pera_project_name' ) {
      return;
    }

    $query->set( 'meta_key', 'project_name' );
    $query->set( 'orderby', 'meta_value' );
  }
}

if ( ! function_exists( 'pera_admin_property_row_actions' ) ) {
  function pera_admin_property_row_actions( array $actions, WP_Post $post ): array {
    if ( $post->post_type !== 'property' ) {
      return $actions;
    }

    if ( ! taxonomy_exists( 'district' ) ) {
      return $actions;
    }

    $district_id = 0;
    $district_terms = wp_get_post_terms( $post->ID, 'district', array( 'fields' => 'ids' ) );
    if ( ! is_wp_error( $district_terms ) && ! empty( $district_terms ) ) {
      $district_id = (int) $district_terms[0];
    }

    $district_marker = '<span class="pera-district-term" data-district-id="' . esc_attr( $district_id ) . '" style="display:none;"></span>';
    if ( isset( $actions['edit'] ) ) {
      $actions['edit'] .= $district_marker;
    } else {
      $actions['pera_district'] = $district_marker;
    }

    return $actions;
  }
}

/* ==============================
 * Quick Edit (District + Specials)
 * ============================== */

if ( ! function_exists( 'pera_admin_property_quick_edit_fields' ) ) {
  function pera_admin_property_quick_edit_fields( string $column_name, string $post_type ): void {
    if ( $post_type !== 'property' ) {
      return;
    }

    static $printed = false;
    if ( $printed ) {
      return;
    }
    $printed = true;

    if ( ! taxonomy_exists( 'district' ) ) {
      return;
    }

    $dropdown = wp_dropdown_categories( array(
      'taxonomy'         => 'district',
      'name'             => 'pera_district_term',
      'show_option_none' => '— No district —',
      'option_none_value'=> '0',
      'hide_empty'       => 0,
      'hierarchical'     => 1,
      'show_count'       => 0,
      'echo'             => 0,
    ) );

    if ( ! $dropdown ) {
      return;
    }
    ?>
    <!-- PERA DEBUG: district quick edit rendered -->
    <fieldset class="inline-edit-col-right">
      <div class="inline-edit-col">
        <label class="alignleft">
          <span class="title">District</span>
          <span class="input-text-wrap">
            <?php echo $dropdown; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
          </span>
        </label>
      </div>
    </fieldset>
    <?php
  }
}

if ( ! function_exists( 'pera_admin_property_bulk_edit_fields' ) ) {
  function pera_admin_property_bulk_edit_fields( string $column_name, string $post_type ): void {
    if ( $post_type !== 'property' ) {
      return;
    }

    static $printed = false;
    if ( $printed ) {
      return;
    }
    $printed = true;

    if ( ! taxonomy_exists( 'district' ) ) {
      return;
    }

    $terms = get_terms( array(
      'taxonomy'   => 'district',
      'hide_empty' => 0,
    ) );

    if ( is_wp_error( $terms ) ) {
      return;
    }

    $walker  = new Walker_CategoryDropdown();
    $options = $walker->walk(
      $terms,
      0,
      array(
        'taxonomy'     => 'district',
        'hide_empty'   => 0,
        'hierarchical' => 1,
        'show_count'   => 0,
        'selected'     => 0,
      )
    );
    ?>
    <fieldset class="inline-edit-col-right">
      <div class="inline-edit-col">
        <label class="alignleft">
          <span class="title">District</span>
          <select name="pera_bulk_district_term" class="pera-bulk-district-select">
            <option value="-1">— No Change —</option>
            <option value="0">— No district —</option>
            <?php echo $options; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
          </select>
        </label>
      </div>
    </fieldset>
    <?php
  }
}

if ( ! function_exists( 'pera_admin_property_quick_edit_assets' ) ) {
  function pera_admin_property_quick_edit_assets( string $hook_suffix ): void {
    if ( $hook_suffix !== 'edit.php' ) {
      return;
    }

    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || $screen->post_type !== 'property' ) {
      return;
    }

    wp_enqueue_script(
      'pera-admin-property-quickedit',
      get_stylesheet_directory_uri() . '/assets/js/admin-property-quickedit.js',
      array( 'jquery', 'inline-edit-post' ),
      pera_get_asset_version( '/assets/js/admin-property-quickedit.js' ),
      true
    );

    $terms = get_terms(
      array(
        'taxonomy'   => 'district',
        'hide_empty' => 0,
      )
    );

    $district_ancestors = array();
    if ( ! is_wp_error( $terms ) ) {
      foreach ( $terms as $term ) {
        $district_ancestors[ $term->term_id ] = array_map(
          'intval',
          get_ancestors( $term->term_id, 'district', 'taxonomy' )
        );
      }
    }

    wp_localize_script(
      'pera-admin-property-quickedit',
      'PERA_DISTRICT_ANCESTORS',
      $district_ancestors
    );
  }
}

if ( ! function_exists( 'pera_admin_property_quick_edit_save' ) ) {
  function pera_admin_property_quick_edit_save( int $post_id, WP_Post $post ): void {
    if ( $post->post_type !== 'property' ) {
      return;
    }

    if ( isset( $_POST['pera_bulk_district_term'] ) ) {
      return;
    }

    if ( ! taxonomy_exists( 'district' ) ) {
      return;
    }

    if ( ! isset( $_POST['pera_district_term'] ) ) {
      return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
      return;
    }

    if ( wp_is_post_revision( $post_id ) ) {
      return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
      return;
    }

    if (
      ! isset( $_POST['_inline_edit'] ) ||
      ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_inline_edit'] ) ), 'inlineeditnonce' )
    ) {
      return;
    }

    $term_id = absint( wp_unslash( $_POST['pera_district_term'] ) );
    if ( $term_id > 0 ) {
      // WordPress does not auto-assign parent terms; include ancestors for consistent display/filtering.
      $ancestors = get_ancestors( $term_id, 'district', 'taxonomy' );
      $term_ids  = array_unique( array_merge( array( $term_id ), $ancestors ) );
      $term_ids  = array_map( 'intval', $term_ids );

      wp_set_object_terms( $post_id, $term_ids, 'district', false );
      return;
    }

    wp_set_object_terms( $post_id, array(), 'district', false );
  }
}

if ( ! function_exists( 'pera_admin_hide_special_quick_edit' ) ) {
  function pera_admin_hide_special_quick_edit( bool $show, string $taxonomy, string $post_type ): bool {
    if ( $post_type === 'property' && $taxonomy === 'special' ) {
      return false;
    }

    return $show;
  }
}

/* ==============================
 * Admin Uploads
 * ============================== */

if ( ! function_exists( 'pera_allow_svg_uploads' ) ) {
  function pera_allow_svg_uploads( array $mimes ): array {
    $mimes['svg']  = 'image/svg+xml';
    $mimes['svgz'] = 'image/svg+xml';
    return $mimes;
  }
}
