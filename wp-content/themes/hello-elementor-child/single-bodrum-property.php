<?php
/**
 * Single Bodrum Property
 * Template for bodrum-property CPT.
 */

/* =====================================
   SECURITY GUARD
===================================== */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* =====================================
   HELPERS: NORMALIZE ATTACHMENT IDS
===================================== */

if ( ! function_exists( 'pera_bp_normalize_ids' ) ) {
    /**
     * Normalize gallery/attachment fields into an array of attachment IDs.
     *
     * @param mixed $items Gallery field value.
     * @return int[]
     */
    function pera_bp_normalize_ids( $items ) {
        $ids = array();

        if ( empty( $items ) ) {
            return $ids;
        }

        if ( is_string( $items ) ) {
            $maybe_ids = array_filter( array_map( 'trim', explode( ',', $items ) ) );
            foreach ( $maybe_ids as $maybe_id ) {
                if ( is_numeric( $maybe_id ) ) {
                    $ids[] = (int) $maybe_id;
                }
            }
            return array_values( array_unique( array_filter( $ids ) ) );
        }

        if ( is_array( $items ) ) {
            foreach ( $items as $item ) {
                if ( is_numeric( $item ) ) {
                    $ids[] = (int) $item;
                    continue;
                }

                if ( is_array( $item ) ) {
                    if ( ! empty( $item['ID'] ) ) {
                        $ids[] = (int) $item['ID'];
                        continue;
                    }
                    if ( ! empty( $item['id'] ) ) {
                        $ids[] = (int) $item['id'];
                        continue;
                    }
                }

                if ( is_object( $item ) && ! empty( $item->ID ) ) {
                    $ids[] = (int) $item->ID;
                }
            }
        }

        return array_values( array_unique( array_filter( $ids ) ) );
    }
}

/* =====================================
   HELPERS: COLLECT REPEATER TEXT
===================================== */

if ( ! function_exists( 'pera_bp_collect_repeater_text' ) ) {
    /**
     * Collect text values from a repeater field.
     *
     * @param string $field_name Repeater field name.
     * @param array  $subfields  Possible sub field keys to check.
     * @param int    $post_id    Optional post ID.
     * @return string[]
     */
    function pera_bp_collect_repeater_text( $field_name, array $subfields, $post_id = 0 ) {
        $items = array();

        if ( ! function_exists( 'have_rows' ) || ! have_rows( $field_name, $post_id ) ) {
            return $items;
        }

        while ( have_rows( $field_name, $post_id ) ) {
            the_row();
            $value = '';

            foreach ( $subfields as $subfield ) {
                $candidate = get_sub_field( $subfield );
                if ( is_string( $candidate ) ) {
                    $candidate = trim( $candidate );
                }
                if ( ! empty( $candidate ) ) {
                    $value = $candidate;
                    break;
                }
            }

            if ( ! empty( $value ) ) {
                $items[] = $value;
            }
        }

        return $items;
    }
}

/* =====================================
   HEADER
===================================== */
get_header();
?>

<main id="primary" class="site-main content-rail single-bodrum-property">
<!-- =====================================
   LOOP START
===================================== -->

<?php if ( have_posts() ) : ?>
    <?php while ( have_posts() ) : the_post(); ?>
        <?php
        /* =====================================
		   POST CONTEXT / TYPE GUARD
		===================================== */
        $post_id = get_the_ID();

        if ( get_post_type( $post_id ) !== 'bodrum-property' ) {
            ?>
            <section class="section">
                <div class="container">
                    <p><?php echo esc_html__( 'This template is for Bodrum Property posts only.', 'hello-elementor-child' ); ?></p>
                </div>
            </section>
            <?php
            continue;
        }
		/* =====================================
		   ACF: HERO FIELDS
		===================================== */
		
        $display_title = function_exists( 'get_field' ) ? get_field( 'bp_display_title', $post_id ) : '';
        if ( empty( $display_title ) ) {
            $display_title = get_the_title( $post_id );
        }

        $tagline          = function_exists( 'get_field' ) ? get_field( 'bp_tagline', $post_id ) : '';
        $status_badge     = function_exists( 'get_field' ) ? get_field( 'bp_status_badge', $post_id ) : '';
        $discretion_note  = function_exists( 'get_field' ) ? (bool) get_field( 'bp_discretion_note', $post_id ) : false;
        $hero_media_type  = function_exists( 'get_field' ) ? get_field( 'bp_hero_media_type', $post_id ) : '';
        $hero_image_id    = function_exists( 'get_field' ) ? (int) get_field( 'bp_hero_image', $post_id ) : 0;
        $hero_video_id    = function_exists( 'get_field' ) ? (int) get_field( 'bp_hero_video_mp4', $post_id ) : 0;
        $hero_poster_id   = function_exists( 'get_field' ) ? (int) get_field( 'bp_hero_video_poster', $post_id ) : 0;
        $hero_video_url   = $hero_video_id ? wp_get_attachment_url( $hero_video_id ) : '';
        $hero_poster_url  = $hero_poster_id ? wp_get_attachment_url( $hero_poster_id ) : '';
        $use_video        = ( 'video' === $hero_media_type && ! empty( $hero_video_url ) );
        $use_image        = ( ! $use_video && $hero_image_id );
        $has_hero_media   = $use_video || $use_image;
        $hero_highlights  = function_exists( 'get_field' )
            ? pera_bp_collect_repeater_text( 'bp_hero_highlights', array( 'bp_hl_text' ), $post_id )
            : array();


		/* =====================================
		   HERO: STATUS BADGE MAPPING
		===================================== */

        $status_label = '';
        $status_class = 'pill pill--outline';

        switch ( $status_badge ) {
            case 'for_sale':
                $status_label = __( 'For sale', 'hello-elementor-child' );
                $status_class = 'pill pill--green';
                break;
            case 'off_market':
                $status_label = __( 'Off market', 'hello-elementor-child' );
                $status_class = 'pill pill--outline';
                break;
            case 'sold':
                $status_label = __( 'Sold', 'hello-elementor-child' );
                $status_class = 'pill pill--red';
                break;
            case 'price_on_request':
                $status_label = __( 'Price on request', 'hello-elementor-child' );
                $status_class = 'pill pill--brand';
                break;
        }
		/* =====================================
		   ACF: KEY FACTS
		===================================== */

        $key_facts = function_exists( 'get_field' ) ? get_field( 'bp_key_facts', $post_id ) : array();
        $location_line     = is_array( $key_facts ) && ! empty( $key_facts['bp_location_line'] ) ? $key_facts['bp_location_line'] : ( function_exists( 'get_field' ) ? get_field( 'bp_location_line', $post_id ) : '' );
        $plot_area         = is_array( $key_facts ) && ! empty( $key_facts['bp_plot_area_sqm'] ) ? $key_facts['bp_plot_area_sqm'] : ( function_exists( 'get_field' ) ? get_field( 'bp_plot_area_sqm', $post_id ) : '' );
        $internal_area     = is_array( $key_facts ) && ! empty( $key_facts['bp_internal_area_sqm'] ) ? $key_facts['bp_internal_area_sqm'] : ( function_exists( 'get_field' ) ? get_field( 'bp_internal_area_sqm', $post_id ) : '' );
        $total_units       = is_array( $key_facts ) && ! empty( $key_facts['bp_total_units'] ) ? $key_facts['bp_total_units'] : ( function_exists( 'get_field' ) ? get_field( 'bp_total_units', $post_id ) : '' );
        $config_summary    = is_array( $key_facts ) && ! empty( $key_facts['bp_config_summary'] ) ? $key_facts['bp_config_summary'] : ( function_exists( 'get_field' ) ? get_field( 'bp_config_summary', $post_id ) : '' );
		/* =====================================
		   ACF: INTRO
		===================================== */

        $intro_heading = function_exists( 'get_field' ) ? get_field( 'bp_intro_heading', $post_id ) : '';
        $intro_text    = function_exists( 'get_field' ) ? get_field( 'bp_intro_text', $post_id ) : '';
		/* =====================================
		   ACF: GALLERY + DOWNLOAD
		===================================== */

        $gallery_items = function_exists( 'get_field' ) ? get_field( 'bp_gallery', $post_id ) : array();
        $gallery_ids   = pera_bp_normalize_ids( $gallery_items );
        $interior_gallery_items = function_exists( 'get_field' ) ? get_field( 'bp_interior_gallery', $post_id ) : array();
        $interior_gallery_ids   = pera_bp_normalize_ids( $interior_gallery_items );

        $gallery_download_enabled = function_exists( 'get_field' ) ? (bool) get_field( 'bp_gallery_download_enabled', $post_id ) : false;
        $gallery_download_field   = function_exists( 'get_field' ) ? get_field( 'bp_gallery_download_file', $post_id ) : null;
        $gallery_download_url     = '';

        if ( $gallery_download_enabled && $gallery_download_field ) {
            if ( is_array( $gallery_download_field ) && ! empty( $gallery_download_field['url'] ) ) {
                $gallery_download_url = $gallery_download_field['url'];
            } elseif ( is_numeric( $gallery_download_field ) ) {
                $gallery_download_url = wp_get_attachment_url( (int) $gallery_download_field );
            }
        }
		/* =====================================
		   ACF: FEATURES & AMENITIES
		===================================== */

        $features  = function_exists( 'get_field' )
            ? pera_bp_collect_repeater_text( 'bp_features', array( 'bp_feature', 'feature', 'bp_text', 'text' ), $post_id )
            : array();
        $amenities = function_exists( 'get_field' ) ? get_field( 'bp_amenities', $post_id ) : '';
		/* =====================================
		   ACF: DUAL USE
		===================================== */

        $dual_use_heading = function_exists( 'get_field' ) ? get_field( 'bp_dual_use_heading', $post_id ) : '';
        $dual_use_text    = function_exists( 'get_field' ) ? get_field( 'bp_dual_use_text', $post_id ) : '';
        $operations_note  = function_exists( 'get_field' ) ? get_field( 'bp_operations_note', $post_id ) : '';

        $hospitality_assets = array();
        if ( function_exists( 'have_rows' ) && have_rows( 'bp_hospitality_assets', $post_id ) ) {
            while ( have_rows( 'bp_hospitality_assets', $post_id ) ) {
                the_row();
                $asset_item = get_sub_field( 'bp_ha_item' );
                if ( is_string( $asset_item ) ) {
                    $asset_item = trim( $asset_item );
                }
                if ( ! empty( $asset_item ) ) {
                    $hospitality_assets[] = $asset_item;
                }
            }
        }
		/* =====================================
		   ACF: LOCATION / MAP
		===================================== */

        $map_mode        = function_exists( 'get_field' ) ? get_field( 'bp_map_mode', $post_id ) : '';
        $map_embed       = function_exists( 'get_field' ) ? get_field( 'bp_map_embed', $post_id ) : '';
        $map_image_id    = function_exists( 'get_field' ) ? (int) get_field( 'bp_map_image', $post_id ) : 0;
        $location_notes  = function_exists( 'get_field' )
            ? pera_bp_collect_repeater_text( 'bp_location_highlights', array( 'bp_location_highlight', 'bp_highlight', 'bp_text', 'text' ), $post_id )
            : array();
		/* =====================================
		   ACF: CTAS + ENQUIRY
		===================================== */

        $primary_cta_label   = function_exists( 'get_field' ) ? get_field( 'bp_primary_cta_label', $post_id ) : '';
        $secondary_cta_label = function_exists( 'get_field' ) ? get_field( 'bp_secondary_cta_label', $post_id ) : '';
        $enquiry_recipient   = function_exists( 'get_field' ) ? get_field( 'bp_enquiry_recipient', $post_id ) : '';
        $enquiry_gating_note = function_exists( 'get_field' ) ? get_field( 'bp_enquiry_gating_note', $post_id ) : '';

        $hero_cta_label      = $primary_cta_label ? $primary_cta_label : __( 'Request details', 'hello-elementor-child' );
        $primary_cta_label   = $primary_cta_label ? $primary_cta_label : __( 'Request details', 'hello-elementor-child' );
        $secondary_cta_label = $secondary_cta_label ? $secondary_cta_label : __( 'Arrange viewing', 'hello-elementor-child' );
        $primary_cta_url     = esc_url( site_url( '/book-a-consultancy/' ) );
        $secondary_cta_url   = '#enquiry';

        $render_gallery_row = function( array $row_ids, $fallback_title ) {
            if ( empty( $row_ids ) ) {
                return;
            }

            echo '<div class="property-gallery-strip__row" role="list">';

            foreach ( $row_ids as $image_id ) {
                $image_id = absint( $image_id );
                if ( ! $image_id ) {
                    continue;
                }

                if ( ! wp_get_attachment_image_url( $image_id, 'full' ) ) {
                    continue;
                }

                $alt_meta  = trim( (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) );
                $alt_label = $alt_meta !== '' ? $alt_meta : $fallback_title;

                echo '<div class="property-gallery-strip__item" role="listitem" aria-label="' . esc_attr( $alt_label ) . '">';

                echo wp_get_attachment_image(
                    $image_id,
                    'pera-card',
                    false,
                    array(
                        'loading'  => 'lazy',
                        'decoding' => 'async',
                        'alt'      => $alt_label,
                    )
                );

                echo '</div>';
            }

            echo '</div>';
        };
        ?>

		<!-- =====================================
		   HERO (BODRUM PROPERTY)
		===================================== -->
        <section class="hero hero--left property-hero" id="bodrum-hero">
            <?php if ( $has_hero_media ) : ?>
                <div class="hero__media" aria-hidden="true">
                    <?php if ( $use_video ) : ?>
                        <video
                            class="hero-media"
                            src="<?php echo esc_url( $hero_video_url ); ?>"
                            <?php if ( $hero_poster_url ) : ?>poster="<?php echo esc_url( $hero_poster_url ); ?>"<?php endif; ?>
                            controls
                            playsinline
                            muted
                            preload="metadata"
                        ></video>
                    <?php elseif ( $hero_image_id ) : ?>
                        <?php
                        echo wp_get_attachment_image(
                            $hero_image_id,
                            'full',
                            false,
                            array(
                                'class'    => 'hero-media',
                                'loading'  => 'eager',
                                'decoding' => 'async',
                            )
                        );
                        ?>
                    <?php endif; ?>
                    <div class="hero-overlay" aria-hidden="true"></div>
                </div>
            <?php endif; ?>
                    <!-- =====================================
                    		   HERO CONTENT WITH ACTIONS
                    		===================================== -->
            <div class="hero-content property-hero__content">
            
              <?php if ( $status_label ) : ?>
                <div class="property-hero__pills hero-pills">
                  <span class="<?php echo esc_attr( $status_class ); ?>">
                    <?php echo esc_html( $status_label ); ?>
                  </span>
                </div>
              <?php endif; ?>
            
              <?php if ( $display_title ) : ?>
                <h1 class="property-hero__title"><?php echo esc_html( $display_title ); ?></h1>
              <?php endif; ?>
            
              <?php if ( $tagline ) : ?>
                <p class="property-hero__excerpt text-light"><?php echo esc_html( $tagline ); ?></p>
              <?php endif; ?>
            
              <?php if ( $discretion_note ) : ?>
                <p class="text-light text-sm mb-md">
                  <?php echo esc_html__( 'Discreet marketing. Further details on request.', 'hello-elementor-child' ); ?>
                </p>
              <?php endif; ?>
            
              <div class="property-hero__meta">
            
                <div class="property-hero__cta">
                  <a class="btn btn--solid btn--blue" href="#contact-form">
                    <?php echo esc_html( $primary_cta_label ); ?>
                  </a>
                  <a class="btn btn--solid btn--green" href="<?php echo esc_url( $secondary_cta_url ); ?>">
                    <?php echo esc_html( $secondary_cta_label ); ?>
                  </a>
                </div>
            
                <?php if ( ! empty( $hero_highlights ) ) : ?>
                  <div class="property-hero__pills hero-pills mb-md">
                    <?php foreach ( $hero_highlights as $highlight ) : ?>
                      <span class="pill pill--brand">
                        <?php echo esc_html( $highlight ); ?>
                      </span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
            
                <div class="hero-actions hero-pills">
                  <a class="pill pill--green" href="#gallery">
                    <svg class="icon pill__icon" aria-hidden="true" width="16" height="16">
                      <use href="#icon-gallery-stack" xlink:href="#icon-gallery-stack"></use>
                    </svg>
                    <span class="hero-pill__text">Gallery</span>
                  </a>
            
                  <a class="pill pill--green" href="#location">
                    <svg class="icon pill__icon" aria-hidden="true" width="16" height="16">
                      <use href="#icon-map" xlink:href="#icon-map"></use>
                    </svg>
                    <span class="hero-pill__text">Map</span>
                  </a>
            
                  <a class="pill pill--green" href="#floorplans">
                    <svg class="icon pill__icon" aria-hidden="true" width="16" height="16">
                      <use href="#icon-floor-plan" xlink:href="#icon-floor-plan"></use>
                    </svg>
                    <span class="hero-pill__text">Floorplans</span>
                  </a>
                </div>
            
              </div>
            </div>

            
            
        </section>

		<!-- =====================================
		   KEY FACTS
		===================================== -->

        <?php
        $has_key_facts = $location_line || $plot_area || $internal_area || $total_units || $config_summary;
        if ( $has_key_facts ) :
            ?>
            <section class="section">
                <div class="container">
                    <header class="section-header">
                        <h2><?php echo esc_html__( 'Key facts', 'hello-elementor-child' ); ?></h2>
                    </header>

                    <div class="table-wrap">
                        <table>
                            <tbody>
                                <?php if ( $location_line ) : ?>
                                    <tr>
                                        <th><?php echo esc_html__( 'Location', 'hello-elementor-child' ); ?></th>
                                        <td><?php echo esc_html( $location_line ); ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if ( $plot_area ) : ?>
                                    <tr>
                                        <th><?php echo esc_html__( 'Plot area (sqm)', 'hello-elementor-child' ); ?></th>
                                        <td><?php echo esc_html( $plot_area ); ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if ( $internal_area ) : ?>
                                    <tr>
                                        <th><?php echo esc_html__( 'Internal area (sqm)', 'hello-elementor-child' ); ?></th>
                                        <td><?php echo esc_html( $internal_area ); ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if ( $total_units ) : ?>
                                    <tr>
                                        <th><?php echo esc_html__( 'Total units', 'hello-elementor-child' ); ?></th>
                                        <td><?php echo esc_html( $total_units ); ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if ( $config_summary ) : ?>
                                    <tr>
                                        <th><?php echo esc_html__( 'Configuration', 'hello-elementor-child' ); ?></th>
                                        <td><?php echo esc_html( $config_summary ); ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        <?php endif; ?>

		<!-- =====================================
		   INTRO
		===================================== -->

        <?php if ( $intro_heading || $intro_text ) : ?>
            <section class="section">
                <div class="container">
                    <header class="section-header">
                        <?php if ( $intro_heading ) : ?>
                            <h2><?php echo esc_html( $intro_heading ); ?></h2>
                        <?php endif; ?>
                        <?php if ( $intro_text ) : ?>
                            <div class="lead">
                                <?php echo wp_kses_post( $intro_text ); ?>
                            </div>
                        <?php endif; ?>
                    </header>
                </div>
            </section>
        <?php endif; ?>

        <!-- =====================================
		   GALLERY (2-ROW HORIZONTAL STRIP)
		===================================== -->

        <?php
        if ( ! empty( $gallery_ids ) ) :
            $row1 = array();
            $row2 = array();

            foreach ( $gallery_ids as $index => $image_id ) {
                $image_id = absint( $image_id );
                if ( ! $image_id ) {
                    continue;
                }

                if ( ! wp_get_attachment_image_url( $image_id, 'full' ) ) {
                    continue;
                }

                if ( $index % 2 === 0 ) {
                    $row1[] = $image_id;
                } else {
                    $row2[] = $image_id;
                }
            }

            $has_rows = ( ! empty( $row1 ) || ! empty( $row2 ) );
            if ( $has_rows ) :
                ?>
                <section class="section property-gallery" id="gallery">
                    <div class="container">
                        <header class="section-header">
                            <h2><?php echo esc_html__( 'Gallery', 'hello-elementor-child' ); ?></h2>
                        </header>

                        <div class="property-gallery-shell" aria-label="Property photos">
                            <button
                                class="property-gallery-nav property-gallery-nav--prev"
                                type="button"
                                aria-label="Scroll left"
                            >
                                <svg aria-hidden="true" width="22" height="22">
                                    <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-chevron-left' ); ?>"></use>
                                </svg>
                            </button>

                            <button
                                class="property-gallery-nav property-gallery-nav--next"
                                type="button"
                                aria-label="Scroll right"
                            >
                                <svg aria-hidden="true" width="22" height="22">
                                    <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-chevron-right' ); ?>"></use>
                                </svg>
                            </button>

                            <div class="property-gallery-strip" aria-label="Gallery photos">
                                <?php
                                $render_gallery_row( $row1, $display_title );
                                $render_gallery_row( $row2, $display_title );
                                ?>
                            </div><!-- /.property-gallery-strip -->
                        </div><!-- /.property-gallery-shell -->

                        <?php if ( $gallery_download_url ) : ?>
                            <div class="hero-actions">
                                <a class="btn btn--solid btn--blue" href="<?php echo esc_url( $gallery_download_url ); ?>">
                                    <?php echo esc_html__( 'Download gallery', 'hello-elementor-child' ); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php endif; ?>

		<!-- =====================================
		   STORY BLOCKS (FLEXIBLE CONTENT)
		===================================== -->

        <?php if ( function_exists( 'have_rows' ) && have_rows( 'bp_story_blocks', $post_id ) ) : ?>
            <section class="section">
                <div class="container">
                    <?php while ( have_rows( 'bp_story_blocks', $post_id ) ) : the_row(); ?>
                        <?php
                        $layout = get_row_layout();
                        ?>
                        <?php if ( 'bp_block_text' === $layout ) : ?>
                            <?php
                            $block_heading = get_sub_field( 'bp_block_heading' );
                            $block_text    = get_sub_field( 'bp_block_text' );
                            if ( $block_heading || $block_text ) :
                                ?>
                                <div class="content-panel-box mb-md">
                                    <div class="section-header">
                                        <?php if ( $block_heading ) : ?>
                                            <h3><?php echo esc_html( $block_heading ); ?></h3>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ( $block_text ) : ?>
                                        <div>
                                            <?php echo wp_kses_post( $block_text ); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php elseif ( 'bp_block_image_text' === $layout ) : ?>
                            <?php
                            $block_image      = get_sub_field( 'bp_block_image' );
                            $block_image_id   = 0;
                            if ( is_array( $block_image ) && ! empty( $block_image['ID'] ) ) {
                                $block_image_id = (int) $block_image['ID'];
                            } elseif ( is_numeric( $block_image ) ) {
                                $block_image_id = (int) $block_image;
                            }
                            $block_heading    = get_sub_field( 'bp_block_heading' );
                            $block_text       = get_sub_field( 'bp_block_text' );
                            $block_image_side = get_sub_field( 'bp_block_image_side' );

                            $has_block_content = $block_image_id || $block_heading || $block_text;
                            if ( $has_block_content ) :
                                $image_first = ( 'left' === $block_image_side );
                                ?>
                                <div class="content-panel-box mb-md">
                                    <div class="content-panel-grid p-sm">
                                        <?php if ( $image_first ) : ?>
                                            <?php if ( $block_image_id ) : ?>
                                                <div class="media-frame media-frame--image-fill">
                                                    <?php
                                                    echo wp_get_attachment_image(
                                                        $block_image_id,
                                                        'full',
                                                        false,
                                                        array(
                                                            'class'    => 'media-image',
                                                            'loading'  => 'lazy',
                                                            'decoding' => 'async',
                                                        )
                                                    );
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <div>
                                            <?php if ( $block_heading ) : ?>
                                                <h3><?php echo esc_html( $block_heading ); ?></h3>
                                            <?php endif; ?>
                                            <?php if ( $block_text ) : ?>
                                                <div>
                                                    <?php echo wp_kses_post( $block_text ); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ( ! $image_first ) : ?>
                                            <?php if ( $block_image_id ) : ?>
                                                <div class="media-frame media-frame--image-fill">
                                                    <?php
                                                    echo wp_get_attachment_image(
                                                        $block_image_id,
                                                        'full',
                                                        false,
                                                        array(
                                                            'class'    => 'media-image',
                                                            'loading'  => 'lazy',
                                                            'decoding' => 'async',
                                                        )
                                                    );
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php elseif ( 'bp_block_quote' === $layout ) : ?>
                            <?php
                            $quote_text  = get_sub_field( 'bp_quote' );
                            $quote_attrib = get_sub_field( 'bp_quote_attrib' );
                            if ( $quote_text || $quote_attrib ) :
                                ?>
                                <div class="content-panel-box mb-md">
                                    <?php if ( $quote_text ) : ?>
                                        <blockquote>
                                            <p><?php echo esc_html( $quote_text ); ?></p>
                                        </blockquote>
                                    <?php endif; ?>
                                    <?php if ( $quote_attrib ) : ?>
                                        <p class="text-soft"><?php echo esc_html( $quote_attrib ); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endwhile; ?>
                </div>
            </section>
        <?php endif; ?>

		
        
		<!-- =====================================
		   FEATURES & AMENITIES
		===================================== -->

        <?php
        $has_features_section = ! empty( $features ) || $amenities;
        if ( $has_features_section ) :
            $features_count = count( $features );
            $feature_split  = $features_count ? (int) ceil( $features_count / 2 ) : 0;
            $features_left  = $feature_split ? array_slice( $features, 0, $feature_split ) : array();
            $features_right = $feature_split ? array_slice( $features, $feature_split ) : array();
            ?>
            <section class="section section-soft">
                <div class="container">
                    <header class="section-header">
                        <h2><?php echo esc_html__( 'Features & amenities', 'hello-elementor-child' ); ?></h2>
                    </header>

                    <?php if ( $features ) : ?>
                        <div class="grid-2">
                            <ul class="checklist">
                                <?php foreach ( $features_left as $feature ) : ?>
                                    <li>
                                        <svg class="icon icon-tick" aria-hidden="true">
                                            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-check' ); ?>"></use>
                                        </svg>
                                        <?php echo esc_html( $feature ); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php if ( $features_right ) : ?>
                                <ul class="checklist">
                                    <?php foreach ( $features_right as $feature ) : ?>
                                        <li>
                                            <svg class="icon icon-tick" aria-hidden="true">
                                                <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-check' ); ?>"></use>
                                            </svg>
                                            <?php echo esc_html( $feature ); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ( $amenities ) : ?>
                        <p class="text-soft">
                            <?php echo wp_kses_post( $amenities ); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php
        $has_dual_use_section = $dual_use_heading || $dual_use_text || $hospitality_assets || $operations_note || ! empty( $interior_gallery_ids );
        if ( $has_dual_use_section ) :
            ?>
            <section class="section">
                <div class="container">
                    <div class="glass glass--card p-sm">
                        <header class="section-header">
                            <?php if ( $dual_use_heading ) : ?>
                                <h2><?php echo esc_html( $dual_use_heading ); ?></h2>
                            <?php else : ?>
                                <h2><?php echo esc_html__( 'Dual-use & hospitality capability', 'hello-elementor-child' ); ?></h2>
                            <?php endif; ?>
                        </header>

                        <?php if ( $dual_use_text ) : ?>
                                <?php echo wp_kses_post( $dual_use_text ); ?>
                        <?php endif; ?>

                        <?php if ( $hospitality_assets ) : ?>
                            <div class="property-facilities__pills mb-md" aria-label="<?php echo esc_attr__( 'Hospitality-grade infrastructure', 'hello-elementor-child' ); ?>">
                                <?php foreach ( $hospitality_assets as $asset ) : ?>
                                    <span class="pill pill--outline"><?php echo esc_html( $asset ); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ( $operations_note ) : ?>
                            <div class="text-sm text-soft">
                                <?php echo esc_html( $operations_note ); ?>
                            </div>
                        <?php endif; ?>
                    </div><!-- /.glass card -->

      <!-- =====================================
           INTERIOR GALLERY
        ===================================== -->

                    <?php
                    $interior_row1 = array();
                    $interior_row2 = array();

                    if ( ! empty( $interior_gallery_ids ) ) {
                        foreach ( $interior_gallery_ids as $index => $image_id ) {
                            $image_id = absint( $image_id );
                            if ( ! $image_id ) {
                                continue;
                            }

                            if ( ! wp_get_attachment_image_url( $image_id, 'full' ) ) {
                                continue;
                            }

                            if ( $index % 2 === 0 ) {
                                $interior_row1[] = $image_id;
                            } else {
                                $interior_row2[] = $image_id;
                            }
                        }
                    }

                    $has_interior_rows = ( ! empty( $interior_row1 ) || ! empty( $interior_row2 ) );
                    if ( $has_interior_rows ) :
                        ?>
                        <div class="section" id="property-interior-gallery">
                            <header class="section-header">
                                <h2><?php echo esc_html__( 'Interior gallery', 'hello-elementor-child' ); ?></h2>
                            </header>

                            <div class="property-gallery-shell" aria-label="<?php echo esc_attr__( 'Interior gallery photos', 'hello-elementor-child' ); ?>">
                                <button
                                    class="property-gallery-nav property-gallery-nav--prev"
                                    type="button"
                                    aria-label="<?php echo esc_attr__( 'Scroll left', 'hello-elementor-child' ); ?>"
                                >
                                    <svg aria-hidden="true" width="22" height="22">
                                        <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-chevron-left' ); ?>"></use>
                                    </svg>
                                </button>

                                <button
                                    class="property-gallery-nav property-gallery-nav--next"
                                    type="button"
                                    aria-label="<?php echo esc_attr__( 'Scroll right', 'hello-elementor-child' ); ?>"
                                >
                                    <svg aria-hidden="true" width="22" height="22">
                                        <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-chevron-right' ); ?>"></use>
                                    </svg>
                                </button>

                                <div class="property-gallery-strip" aria-label="<?php echo esc_attr__( 'Interior gallery photos', 'hello-elementor-child' ); ?>">
                                    <?php
                                    $render_gallery_row( $interior_row1, $display_title );
                                    $render_gallery_row( $interior_row2, $display_title );
                                    ?>
                                </div><!-- /.property-gallery-strip -->
                            </div><!-- /.property-gallery-shell -->
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- =====================================
           FLOORPLANS
        ===================================== -->

        <?php if ( function_exists( 'have_rows' ) && have_rows( 'bp_floorplans', $post_id ) ) : ?>
            <section class="section section-soft" id="floorplans">
                <div class="container">
                    <header class="section-header">
                        <h2><?php echo esc_html__( 'Floorplans', 'hello-elementor-child' ); ?></h2>
                    </header>

                    <div class="grid-3">
                        <?php while ( have_rows( 'bp_floorplans', $post_id ) ) : the_row(); ?>
                            <?php
                            $fp_label = get_sub_field( 'bp_fp_label' );
                            $fp_note  = get_sub_field( 'bp_fp_note' );
                            $fp_image = get_sub_field( 'bp_fp_image' );
                            $fp_image_id = 0;
                            if ( is_array( $fp_image ) && ! empty( $fp_image['ID'] ) ) {
                                $fp_image_id = (int) $fp_image['ID'];
                            } elseif ( is_numeric( $fp_image ) ) {
                                $fp_image_id = (int) $fp_image;
                            }

                            if ( ! $fp_label && ! $fp_note && ! $fp_image_id ) {
                                continue;
                            }
                            ?>
                            <div class="content-panel-box">
                                <?php if ( $fp_image_id ) : ?>
                                    <div class="media-frame mb-md">
                                        <a
                                            href="<?php echo esc_url( get_attachment_link( $fp_image_id ) ); ?>"
                                            target="_blank"
                                            rel="noopener"
                                            aria-label="<?php echo esc_attr( $fp_label ? $fp_label : get_the_title( $fp_image_id ) ); ?>"
                                        >
                                            <?php
                                            echo wp_get_attachment_image(
                                                $fp_image_id,
                                                'pera-card',
                                                false,
                                                array(
                                                    'class'    => 'media-image',
                                                    'loading'  => 'lazy',
                                                    'decoding' => 'async',
                                                )
                                            );
                                            ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if ( $fp_label ) : ?>
                                    <h3><?php echo esc_html( $fp_label ); ?></h3>
                                <?php endif; ?>
                                <?php if ( $fp_note ) : ?>
                                    <p class="text-soft"><?php echo esc_html( $fp_note ); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

		<!-- =====================================
		   MAP SANITIZATION (EMBED MODE)
		===================================== -->

        <?php
        $safe_map_embed = '';
        if ( 'embed' === $map_mode && $map_embed ) {
            $map_embed = preg_replace( '/<iframe\b(?![^>]*\bclass=)/i', '<iframe class="media-embed--map"', $map_embed, 1 );
            $map_embed = preg_replace( '/<iframe\b([^>]*\bclass=")(\s*[^"]*)"/i', '<iframe$1$2 media-embed--map"', $map_embed, 1 );

            $allowed_tags = array(
                'iframe' => array(
                    'src'             => true,
                    'width'           => true,
                    'height'          => true,
                    'frameborder'     => true,
                    'style'           => true,
                    'allow'           => true,
                    'allowfullscreen' => true,
                    'loading'         => true,
                    'referrerpolicy'  => true,
                    'title'           => true,
                    'class'           => true,
                ),
                'embed' => array(
                    'src'    => true,
                    'type'   => true,
                    'width'  => true,
                    'height' => true,
                    'class'  => true,
                ),
            );

            $safe_map_embed = wp_kses( $map_embed, $allowed_tags );
        }

		/* =====================================
		   LOCATION SECTION
		===================================== */

        $has_map_content = ( 'embed' === $map_mode && $safe_map_embed ) || ( 'image' === $map_mode && $map_image_id );
        $has_location_section = $has_map_content || $location_notes;
        if ( $has_location_section ) :
            ?>
            <section class="section" id="location">
                <div class="container">
                    <header class="section-header">
                        <h2><?php echo esc_html__( 'Location', 'hello-elementor-child' ); ?></h2>
                    </header>

                    <?php if ( $has_map_content && $location_notes ) : ?>
                        <div class="grid-2">
                            <div class="media-frame media-frame--map">
                                <?php if ( 'embed' === $map_mode && $safe_map_embed ) : ?>
                                    <?php echo $safe_map_embed; ?>
                                <?php elseif ( 'image' === $map_mode && $map_image_id ) : ?>
                                    <?php
                                    echo wp_get_attachment_image(
                                        $map_image_id,
                                        'pera-card',
                                        false,
                                        array(
                                            'class'    => 'media-image',
                                            'loading'  => 'lazy',
                                            'decoding' => 'async',
                                        )
                                    );
                                    ?>
                                <?php endif; ?>
                            </div>

                            <div>
                                <ul class="checklist">
                                    <?php foreach ( $location_notes as $note ) : ?>
                                        <li>
                                            <svg class="icon icon-tick" aria-hidden="true">
                                                <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-check' ); ?>"></use>
                                            </svg>
                                            <?php echo esc_html( $note ); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php elseif ( $has_map_content ) : ?>
                        <div class="media-frame media-frame--map">
                            <?php if ( 'embed' === $map_mode && $safe_map_embed ) : ?>
                                <?php echo $safe_map_embed; ?>
                            <?php elseif ( 'image' === $map_mode && $map_image_id ) : ?>
                                <?php
                                echo wp_get_attachment_image(
                                    $map_image_id,
                                    'pera-card',
                                    false,
                                    array(
                                        'class'    => 'media-image',
                                        'loading'  => 'lazy',
                                        'decoding' => 'async',
                                    )
                                );
                                ?>
                            <?php endif; ?>
                        </div>
                    <?php elseif ( $location_notes ) : ?>
                        <div>
                            <ul class="checklist">
                                <?php foreach ( $location_notes as $note ) : ?>
                                    <li>
                                        <svg class="icon icon-tick" aria-hidden="true">
                                            <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-check' ); ?>"></use>
                                        </svg>
                                        <?php echo esc_html( $note ); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

		<!-- =====================================
		   ENQUIRY CTA
		===================================== -->


        <section class="section section-soft" id="contact-form"<?php echo $enquiry_recipient ? ' data-recipient="' . esc_attr( $enquiry_recipient ) . '"' : ''; ?>>
            <div class="container">
                <div class="content-panel-box" id="enquiry">
                    <header class="section-header">
                        <h2><?php echo esc_html__( 'Start your Bodrum enquiry', 'hello-elementor-child' ); ?></h2>
                        <p><?php echo esc_html__( 'Speak with a consultant to receive full details, pricing, and availability.', 'hello-elementor-child' ); ?></p>
                    </header>

                    <?php if ( $enquiry_gating_note ) : ?>
                            <p class="pt-md"><?php echo esc_html( $enquiry_gating_note ); ?></p>
                    <?php endif; ?>

                    <hr class="content-panel-divider">

                    <?php
                    get_template_part(
                        'parts/enquiry-form',
                        null,
                        array(
                            'context'        => 'property',
                            'heading'        => '',
                            'intro'          => '',
                            'submit_label'   => __( 'Send enquiry', 'hello-elementor-child' ),
                            'form_context'   => 'property',
                            'property_id'    => $post_id,
                            'property_title' => $display_title,
                            'property_url'   => get_permalink( $post_id ),
                            'sr_context'     => 'bodrum_property',
                            'show_header'    => false,
                        )
                    );
                    ?>

                    <?php if ( isset( $_GET['sr_success'] ) && $_GET['sr_success'] === '1' ) : ?>
                        <div class="form-success">
                            <?php echo esc_html__( 'Thank you – we have received your details. A Pera consultant will contact you shortly.', 'hello-elementor-child' ); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

    <?php endwhile; ?>
<?php endif; ?>

<!-- =====================================
   LOOP END
===================================== -->

</main>


<!-- =====================================
   GALLERY NAV SCRIPT
===================================== -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const shells = document.querySelectorAll('.single-bodrum-property .property-gallery-shell');
    if (!shells.length) {
        return;
    }

    shells.forEach(function (shell) {
        const strip = shell.querySelector('.property-gallery-strip');
        if (!strip) {
            return;
        }

        const btnPrev = shell.querySelector('.property-gallery-nav--prev');
        const btnNext = shell.querySelector('.property-gallery-nav--next');

        function scrollByAmount(dir) {
            const amount = Math.max(240, Math.round(strip.clientWidth * 0.8));
            strip.scrollBy({ left: dir * amount, behavior: 'smooth' });
        }

        if (btnPrev) {
            btnPrev.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                scrollByAmount(-1);
            });
        }

        if (btnNext) {
            btnNext.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                scrollByAmount(1);
            });
        }
    });
});
</script>

<?php
/* =====================================
   FOOTER
===================================== */
get_footer();
