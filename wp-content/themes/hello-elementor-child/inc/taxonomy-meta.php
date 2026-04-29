<?php
/**
 * Taxonomy Term Meta: Excerpt + Featured Image (ALL TAXONOMIES)
 * Location: /inc/taxonomy-meta.php
 *
 * Adds term meta fields on ALL public, UI-enabled taxonomies:
 * - Term Excerpt (pera_term_excerpt)
 * - Term Featured Image (pera_term_featured_image_id)
 *
 * Back-compat (read-only fallback):
 * - category_excerpt
 * - category_featured_image_id
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}




/* ============================================================
   CONFIG
   ============================================================ */

if ( ! defined( 'PERA_TERM_EXCERPT_KEY' ) ) define( 'PERA_TERM_EXCERPT_KEY', 'pera_term_excerpt' );
if ( ! defined( 'PERA_TERM_IMAGE_KEY' ) )   define( 'PERA_TERM_IMAGE_KEY',   'pera_term_featured_image_id' );

if ( ! defined( 'PERA_TERM_ARCHIVE_SUBTITLE_KEY' ) ) define( 'PERA_TERM_ARCHIVE_SUBTITLE_KEY', 'archive_subtitle' );
if ( ! defined( 'PERA_TERM_ARCHIVE_BODY_KEY' ) )     define( 'PERA_TERM_ARCHIVE_BODY_KEY',     'archive_body_content' );
if ( ! defined( 'PERA_TERM_ARCHIVE_H1_TITLE_KEY' ) ) define( 'PERA_TERM_ARCHIVE_H1_TITLE_KEY', 'archive_h1_title' );
if ( ! defined( 'PERA_TERM_SEO_TITLE_KEY' ) )        define( 'PERA_TERM_SEO_TITLE_KEY',        'seo_title' );
if ( ! defined( 'PERA_TERM_SEO_DESC_KEY' ) )         define( 'PERA_TERM_SEO_DESC_KEY',         'seo_meta_description' );

/**
 * Taxonomies that support custom property-archive term fields.
 */
function pera_property_archive_meta_taxonomies(): array {
  $taxonomies = array(
    'district',
    'region',
    'property_type',
    'property_tags',
  );

  return array_values( array_filter( $taxonomies, 'taxonomy_exists' ) );
}

function pera_taxonomy_supports_property_archive_meta( string $taxonomy ): bool {
  return in_array( $taxonomy, pera_property_archive_meta_taxonomies(), true );
}
/**
 * Supported taxonomies: public + show_ui
 */
function pera_supported_taxonomies(): array {
  $tax = get_taxonomies(
    array(
      'public'  => true,
      'show_ui' => true,
    ),
    'names'
  );

  // Defensive excludes (rarely needed with public+show_ui, but safe)
  $exclude = array(
    'post_format',
  );

  $tax = array_values( array_diff( (array) $tax, $exclude ) );

  return apply_filters( 'pera_supported_taxonomies', $tax );
}

/**
 * Can current user manage terms for taxonomy?
 */
function pera_can_manage_terms( string $taxonomy ): bool {
  $obj = get_taxonomy( $taxonomy );
  if ( ! $obj || empty( $obj->cap->manage_terms ) ) return false;
  return current_user_can( $obj->cap->manage_terms );
}

/* ============================================================
   ADMIN ENQUEUE (media picker) - only on term screens
   ============================================================ */

add_action( 'admin_enqueue_scripts', function ( $hook_suffix ) {

  if ( ! in_array( $hook_suffix, array( 'edit-tags.php', 'term.php' ), true ) ) {
    return;
  }

  $taxonomy = '';
  if ( isset( $_GET['taxonomy'] ) ) {
    $taxonomy = sanitize_key( (string) $_GET['taxonomy'] );
  } elseif ( isset( $_POST['taxonomy'] ) ) {
    $taxonomy = sanitize_key( (string) $_POST['taxonomy'] );
  }

  if ( ! $taxonomy ) return;

  $supported = pera_supported_taxonomies();
  if ( ! in_array( $taxonomy, $supported, true ) ) return;
  if ( ! pera_can_manage_terms( $taxonomy ) ) return;

  wp_enqueue_media();
  wp_enqueue_script( 'jquery' );

  wp_add_inline_style( 'common', '
    .pera-term-image { margin-top: 8px; }
    .pera-term-image img { max-width: 180px; height: auto; display: block; border: 1px solid #ccd0d4; background: #fff; padding: 4px; border-radius: 4px; }
    .pera-term-image-actions { margin-top: 8px; display: flex; gap: 8px; flex-wrap: wrap; }
  ' );

  wp_add_inline_script( 'jquery', "
    (function($){

      function getPreviewUrl(attachment){
        if (attachment.sizes && attachment.sizes.thumbnail) {
          return attachment.sizes.thumbnail.url;
        }
        return attachment.url || '';
      }

      function openFrame(wrap){
        var frame = wp.media({
          title: 'Select Featured Image',
          button: { text: 'Use this image' },
          multiple: false
        });

        frame.on('select', function(){
          var attachment = frame.state().get('selection').first().toJSON();
          var url = getPreviewUrl(attachment);

          wrap.find('.pera-term-image-id').val(attachment.id);
          wrap.find('.pera-term-image-preview').html(url ? '<img src=\"' + url + '\" />' : '');
          wrap.find('.pera-term-image-remove').prop('disabled', false);
        });

        frame.open();
      }

      $(document).on('click', '.pera-term-image-select', function(e){
        e.preventDefault();
        openFrame($(this).closest('.pera-term-image-wrap'));
      });

      $(document).on('click', '.pera-term-image-remove', function(e){
        e.preventDefault();
        var wrap = $(this).closest('.pera-term-image-wrap');
        wrap.find('.pera-term-image-id').val('');
        wrap.find('.pera-term-image-preview').empty();
        $(this).prop('disabled', true);
      });

    })(jQuery);
  " );

}, 20 );

/* ============================================================
   ADMIN UI - render fields
   ============================================================ */

function pera_term_meta_render_add_fields( string $taxonomy ): void {
  if ( ! pera_can_manage_terms( $taxonomy ) ) return;

  wp_nonce_field( 'pera_term_meta_save', 'pera_term_meta_nonce' );
  ?>
  <div class="form-field">
    <label for="pera_term_excerpt"><?php esc_html_e( 'Term Excerpt', 'pera' ); ?></label>
    <textarea
      name="pera_term_excerpt"
      id="pera_term_excerpt"
      rows="3"
      maxlength="240"
      placeholder="<?php echo esc_attr__( 'Short excerpt for archive headers, cards, and meta descriptions', 'pera' ); ?>"
    ></textarea>
    <p class="description"><?php esc_html_e( 'Recommended: used as meta description on term archives.', 'pera' ); ?></p>
  </div>

  <?php if ( pera_taxonomy_supports_property_archive_meta( $taxonomy ) ) : ?>
  <div class="form-field">
    <label for="pera_archive_subtitle"><?php esc_html_e( 'Archive Subtitle', 'pera' ); ?></label>
    <input type="text" name="pera_archive_subtitle" id="pera_archive_subtitle" value="" maxlength="220" />
  </div>

  <div class="form-field">
    <label for="pera_archive_body_content"><?php esc_html_e( 'Archive Body Content', 'pera' ); ?></label>
    <textarea name="pera_archive_body_content" id="pera_archive_body_content" rows="6"></textarea>
  </div>

  <?php if ( $taxonomy === 'property_tags' ) : ?>
  <div class="form-field">
    <label for="pera_archive_h1_title"><?php esc_html_e( 'Archive H1 Title', 'pera' ); ?></label>
    <input type="text" name="pera_archive_h1_title" id="pera_archive_h1_title" value="" maxlength="220" />
  </div>
  <?php endif; ?>

  <div class="form-field">
    <label for="pera_seo_title"><?php esc_html_e( 'SEO Title', 'pera' ); ?></label>
    <input type="text" name="pera_seo_title" id="pera_seo_title" value="" maxlength="300" />
  </div>

  <div class="form-field">
    <label for="pera_seo_meta_description"><?php esc_html_e( 'SEO Meta Description', 'pera' ); ?></label>
    <textarea name="pera_seo_meta_description" id="pera_seo_meta_description" rows="3" maxlength="300"></textarea>
  </div>
  <?php endif; ?>

  <div class="form-field pera-term-image-wrap">
    <label><?php esc_html_e( 'Term Featured Image', 'pera' ); ?></label>

    <input type="hidden" class="pera-term-image-id" name="pera_term_featured_image_id" value="" />
    <div class="pera-term-image pera-term-image-preview"></div>

    <div class="pera-term-image-actions">
      <button type="button" class="button pera-term-image-select"><?php esc_html_e( 'Select Image', 'pera' ); ?></button>
      <button type="button" class="button pera-term-image-remove" disabled><?php esc_html_e( 'Remove Image', 'pera' ); ?></button>
    </div>

    <p class="description"><?php esc_html_e( 'Used for cards, headers, and Open Graph / Twitter previews.', 'pera' ); ?></p>
  </div>
  <?php
}

function pera_term_meta_render_edit_fields( WP_Term $term, string $taxonomy ): void {
  if ( ! pera_can_manage_terms( $taxonomy ) ) return;

  $term_id = (int) $term->term_id;

  $excerpt  = (string) get_term_meta( $term_id, PERA_TERM_EXCERPT_KEY, true );
  $image_id = (int) get_term_meta( $term_id, PERA_TERM_IMAGE_KEY, true );

  $archive_subtitle    = (string) get_term_meta( $term_id, PERA_TERM_ARCHIVE_SUBTITLE_KEY, true );
  $archive_body        = (string) get_term_meta( $term_id, PERA_TERM_ARCHIVE_BODY_KEY, true );
  $archive_h1_title    = (string) get_term_meta( $term_id, PERA_TERM_ARCHIVE_H1_TITLE_KEY, true );
  $seo_title           = (string) get_term_meta( $term_id, PERA_TERM_SEO_TITLE_KEY, true );
  $seo_meta_desc       = (string) get_term_meta( $term_id, PERA_TERM_SEO_DESC_KEY, true );

  // Back-compat read fallback for category
  if ( $taxonomy === 'category' ) {
    if ( $excerpt === '' ) {
      $old = (string) get_term_meta( $term_id, 'category_excerpt', true );
      if ( $old !== '' ) $excerpt = $old;
    }
    if ( ! $image_id ) {
      $old_id = (int) get_term_meta( $term_id, 'category_featured_image_id', true );
      if ( $old_id ) $image_id = $old_id;
    }
  }

  $thumb = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';

  wp_nonce_field( 'pera_term_meta_save', 'pera_term_meta_nonce' );
  ?>
  <tr class="form-field">
    <th scope="row">
      <label for="pera_term_excerpt"><?php esc_html_e( 'Term Excerpt', 'pera' ); ?></label>
    </th>
    <td>
      <textarea
        name="pera_term_excerpt"
        id="pera_term_excerpt"
        rows="3"
        maxlength="240"
      ><?php echo esc_textarea( $excerpt ); ?></textarea>
      <p class="description"><?php esc_html_e( 'Recommended: used as meta description on term archives.', 'pera' ); ?></p>
    </td>
  </tr>

  <?php if ( pera_taxonomy_supports_property_archive_meta( $taxonomy ) ) : ?>
  <tr class="form-field">
    <th scope="row"><label for="pera_archive_subtitle"><?php esc_html_e( 'Archive Subtitle', 'pera' ); ?></label></th>
    <td><input type="text" name="pera_archive_subtitle" id="pera_archive_subtitle" value="<?php echo esc_attr( $archive_subtitle ); ?>" maxlength="220" class="regular-text" /></td>
  </tr>

  <tr class="form-field">
    <th scope="row"><label for="pera_archive_body_content"><?php esc_html_e( 'Archive Body Content', 'pera' ); ?></label></th>
    <td><textarea name="pera_archive_body_content" id="pera_archive_body_content" rows="6" class="large-text"><?php echo esc_textarea( $archive_body ); ?></textarea></td>
  </tr>

  <?php if ( $taxonomy === 'property_tags' ) : ?>
  <tr class="form-field">
    <th scope="row"><label for="pera_archive_h1_title"><?php esc_html_e( 'Archive H1 Title', 'pera' ); ?></label></th>
    <td><input type="text" name="pera_archive_h1_title" id="pera_archive_h1_title" value="<?php echo esc_attr( $archive_h1_title ); ?>" maxlength="220" class="regular-text" /></td>
  </tr>
  <?php endif; ?>

  <tr class="form-field">
    <th scope="row"><label for="pera_seo_title"><?php esc_html_e( 'SEO Title', 'pera' ); ?></label></th>
    <td><input type="text" name="pera_seo_title" id="pera_seo_title" value="<?php echo esc_attr( $seo_title ); ?>" maxlength="300" class="regular-text" /></td>
  </tr>

  <tr class="form-field">
    <th scope="row"><label for="pera_seo_meta_description"><?php esc_html_e( 'SEO Meta Description', 'pera' ); ?></label></th>
    <td><textarea name="pera_seo_meta_description" id="pera_seo_meta_description" rows="3" maxlength="300" class="large-text"><?php echo esc_textarea( $seo_meta_desc ); ?></textarea></td>
  </tr>
  <?php endif; ?>

  <tr class="form-field pera-term-image-wrap">
    <th scope="row"><label><?php esc_html_e( 'Term Featured Image', 'pera' ); ?></label></th>
    <td>
      <input type="hidden" class="pera-term-image-id" name="pera_term_featured_image_id" value="<?php echo esc_attr( $image_id ); ?>" />
      <div class="pera-term-image pera-term-image-preview">
        <?php if ( $thumb ) : ?><img src="<?php echo esc_url( $thumb ); ?>" alt="" /><?php endif; ?>
      </div>

      <div class="pera-term-image-actions">
        <button type="button" class="button pera-term-image-select"><?php esc_html_e( 'Select Image', 'pera' ); ?></button>
        <button type="button" class="button pera-term-image-remove" <?php disabled( ! $image_id ); ?>><?php esc_html_e( 'Remove Image', 'pera' ); ?></button>
      </div>

      <p class="description"><?php esc_html_e( 'Used for cards, headers, and Open Graph / Twitter previews.', 'pera' ); ?></p>
    </td>
  </tr>
  <?php
}

/* ============================================================
   REGISTER UI HOOKS FOR ALL SUPPORTED TAXONOMIES
   ============================================================ */

add_action( 'init', function () {

  $taxonomies = pera_supported_taxonomies();

  foreach ( $taxonomies as $taxonomy ) {

    // Add screen
    add_action( "{$taxonomy}_add_form_fields", function () use ( $taxonomy ) {
      pera_term_meta_render_add_fields( $taxonomy );
    }, 10 );

    // Edit screen
    add_action( "{$taxonomy}_edit_form_fields", function ( $term ) use ( $taxonomy ) {
      if ( $term instanceof WP_Term ) {
        pera_term_meta_render_edit_fields( $term, $taxonomy );
      }
    }, 10, 1 );
  }

}, 20 );

/* ============================================================
   SAVE HANDLER (created_term / edited_term)
   ============================================================ */

function pera_term_meta_save( int $term_id, int $tt_id, string $taxonomy ): void {
  if ( $term_id <= 0 || $taxonomy === '' ) return;

  $supported = pera_supported_taxonomies();
  if ( ! in_array( $taxonomy, $supported, true ) ) return;
  if ( ! pera_can_manage_terms( $taxonomy ) ) return;

  // Require nonce on admin term screens (prevents accidental saves elsewhere)
  if (
    ! isset( $_POST['pera_term_meta_nonce'] ) ||
    ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pera_term_meta_nonce'] ) ), 'pera_term_meta_save' )
  ) {
    return;
  }

  // Excerpt
  if ( isset( $_POST['pera_term_excerpt'] ) ) {
    $excerpt = sanitize_textarea_field( wp_unslash( $_POST['pera_term_excerpt'] ) );
    if ( $excerpt === '' ) {
      delete_term_meta( $term_id, PERA_TERM_EXCERPT_KEY );
    } else {
      update_term_meta( $term_id, PERA_TERM_EXCERPT_KEY, $excerpt );
    }
  }


  if ( pera_taxonomy_supports_property_archive_meta( $taxonomy ) ) {
    if ( isset( $_POST['pera_archive_subtitle'] ) ) {
      $archive_subtitle = sanitize_text_field( wp_unslash( $_POST['pera_archive_subtitle'] ) );
      if ( $archive_subtitle === '' ) {
        delete_term_meta( $term_id, PERA_TERM_ARCHIVE_SUBTITLE_KEY );
      } else {
        update_term_meta( $term_id, PERA_TERM_ARCHIVE_SUBTITLE_KEY, $archive_subtitle );
      }
    }

    if ( isset( $_POST['pera_archive_body_content'] ) ) {
      $archive_body = wp_kses_post( wp_unslash( $_POST['pera_archive_body_content'] ) );
      if ( trim( wp_strip_all_tags( $archive_body ) ) === '' ) {
        delete_term_meta( $term_id, PERA_TERM_ARCHIVE_BODY_KEY );
      } else {
        update_term_meta( $term_id, PERA_TERM_ARCHIVE_BODY_KEY, $archive_body );
      }
    }

    if ( $taxonomy === 'property_tags' && isset( $_POST['pera_archive_h1_title'] ) ) {
      $archive_h1_title = sanitize_text_field( wp_unslash( $_POST['pera_archive_h1_title'] ) );
      if ( $archive_h1_title === '' ) {
        delete_term_meta( $term_id, PERA_TERM_ARCHIVE_H1_TITLE_KEY );
      } else {
        update_term_meta( $term_id, PERA_TERM_ARCHIVE_H1_TITLE_KEY, $archive_h1_title );
      }
    }

    if ( isset( $_POST['pera_seo_title'] ) ) {
      $seo_title = sanitize_text_field( wp_unslash( $_POST['pera_seo_title'] ) );
      if ( $seo_title === '' ) {
        delete_term_meta( $term_id, PERA_TERM_SEO_TITLE_KEY );
      } else {
        update_term_meta( $term_id, PERA_TERM_SEO_TITLE_KEY, $seo_title );
      }
    }

    if ( isset( $_POST['pera_seo_meta_description'] ) ) {
      $seo_meta_description = sanitize_textarea_field( wp_unslash( $_POST['pera_seo_meta_description'] ) );
      if ( $seo_meta_description === '' ) {
        delete_term_meta( $term_id, PERA_TERM_SEO_DESC_KEY );
      } else {
        update_term_meta( $term_id, PERA_TERM_SEO_DESC_KEY, $seo_meta_description );
      }
    }
  }

  // Featured image ID
  if ( isset( $_POST['pera_term_featured_image_id'] ) ) {
    $image_id = (int) wp_unslash( $_POST['pera_term_featured_image_id'] );
    if ( $image_id <= 0 ) {
      delete_term_meta( $term_id, PERA_TERM_IMAGE_KEY );
    } else {
      update_term_meta( $term_id, PERA_TERM_IMAGE_KEY, $image_id );
    }
  }
}

add_action( 'created_term', 'pera_term_meta_save', 10, 3 );
add_action( 'edited_term',  'pera_term_meta_save', 10, 3 );

/* ============================================================
   HELPERS (frontend / SEO module)
   ============================================================ */

/**
 * Get raw term excerpt (no trimming). Prefers meta, fallback to description.
 */
function pera_get_term_excerpt_raw( int $term_id, string $taxonomy = '' ): string {
  $term_id = (int) $term_id;
  if ( $term_id <= 0 ) return '';

  $excerpt = (string) get_term_meta( $term_id, PERA_TERM_EXCERPT_KEY, true );

  // Back-compat: category old key
  if ( $excerpt === '' && $taxonomy === 'category' ) {
    $old = (string) get_term_meta( $term_id, 'category_excerpt', true );
    if ( $old !== '' ) $excerpt = $old;
  }

  $excerpt = trim( wp_strip_all_tags( $excerpt ) );
  if ( $excerpt !== '' ) return $excerpt;

  // Fallback: term description
  if ( $taxonomy ) {
    $term = get_term( $term_id, $taxonomy );
  } else {
    $term = get_term( $term_id );
  }

  if ( $term && ! is_wp_error( $term ) && ! empty( $term->description ) ) {
    return trim( wp_strip_all_tags( (string) $term->description ) );
  }

  return '';
}

/**
 * Get trimmed excerpt (word-based).
 */
function pera_get_term_excerpt( int $term_id, string $taxonomy = '', int $words = 28 ): string {
  $raw = pera_get_term_excerpt_raw( $term_id, $taxonomy );
  if ( $raw === '' ) return '';
  return wp_trim_words( $raw, max( 1, (int) $words ), '…' );
}

/**
 * Featured image ID (prefers meta).
 */
function pera_get_term_featured_image_id( int $term_id, string $taxonomy = '' ): int {
  $term_id = (int) $term_id;
  if ( $term_id <= 0 ) return 0;

  $id = (int) get_term_meta( $term_id, PERA_TERM_IMAGE_KEY, true );

  // Back-compat: category old key
  if ( ! $id && $taxonomy === 'category' ) {
    $old = (int) get_term_meta( $term_id, 'category_featured_image_id', true );
    if ( $old ) $id = $old;
  }

  return $id;
}

/**
 * Featured image URL.
 */
function pera_get_term_featured_image_url( int $term_id, string $taxonomy = '', string $size = 'full' ): string {
  $id = pera_get_term_featured_image_id( $term_id, $taxonomy );
  if ( ! $id ) return '';
  $url = wp_get_attachment_image_url( $id, $size );
  return $url ? (string) $url : '';
}
