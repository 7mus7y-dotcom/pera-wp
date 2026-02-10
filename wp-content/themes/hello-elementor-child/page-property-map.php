<?php
/**
 * Template Name: Property Map
 * Description: Map view of properties with ACF map markers.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

$markers = array();
$acf_loaded = function_exists( 'get_field' );

if ( $acf_loaded ) {
    $property_query = new WP_Query(
        array(
            'post_type'      => 'property',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        )
    );

    if ( $property_query->have_posts() ) {
        foreach ( $property_query->posts as $property_id ) {
            $map = get_field( 'map', $property_id );
            if ( ! is_array( $map ) ) {
                continue;
            }

            $lat = $map['lat'] ?? $map['latitude'] ?? null;
            $lng = $map['lng'] ?? $map['longitude'] ?? null;

            $lat = is_numeric( $lat ) ? (float) $lat : null;
            $lng = is_numeric( $lng ) ? (float) $lng : null;

            if ( null === $lat || null === $lng ) {
                continue;
            }

            $thumb = '';
            $main_image = get_field( 'main_image', $property_id );
            if ( is_array( $main_image ) && ! empty( $main_image['ID'] ) ) {
                $thumb = wp_get_attachment_image_url( (int) $main_image['ID'], 'pera-card' );
                if ( ! $thumb ) {
                    $thumb = wp_get_attachment_image_url( (int) $main_image['ID'], 'medium' );
                }
            }

            $price_text = '';
            $is_project = false;
            $special_terms = get_the_terms( $property_id, 'special' );
            if ( ! empty( $special_terms ) && ! is_wp_error( $special_terms ) ) {
                foreach ( $special_terms as $term ) {
                    if ( in_array( $term->slug, array( 'project', 'projects' ), true ) ) {
                        $is_project = true;
                        break;
                    }
                }
            }

            if ( ! function_exists( 'pera_units_get_display_data' ) ) {
                $v2_helper_path = get_stylesheet_directory() . '/inc/v2-units-index.php';
                if ( file_exists( $v2_helper_path ) ) {
                    require_once $v2_helper_path;
                }
            }

            if ( function_exists( 'pera_units_get_display_data' ) ) {
                $units_data = pera_units_get_display_data(
                    $property_id,
                    array(
                        'context'    => 'map',
                        'unit_key'   => 0,
                        'is_project' => $is_project,
                    )
                );
                $price_text = $units_data['price_text'] ?? '';
            }

            $markers[] = array(
                'id'         => $property_id,
                'title'      => get_the_title( $property_id ),
                'url'        => get_permalink( $property_id ),
                'lat'        => $lat,
                'lng'        => $lng,
                'thumb'      => $thumb,
                'price_text' => $price_text,
            );
        }
    }

    wp_reset_postdata();
}

echo "\n<!-- property-map debug: markers=" . count( $markers ) . " acf=" . ( $acf_loaded ? 'yes' : 'no' ) . " -->\n";
if ( ! empty( $markers[0] ) ) {
    echo "<!-- property-map debug first: lat={$markers[0]['lat']} lng={$markers[0]['lng']} -->\n";
}

if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( sprintf( '[property-map] markers=%d acf=%s', count( $markers ), $acf_loaded ? 'yes' : 'no' ) );
    if ( ! empty( $markers[0] ) ) {
        error_log( sprintf( '[property-map] first lat=%s lng=%s', $markers[0]['lat'], $markers[0]['lng'] ) );
    }
}
?>

<main id="primary" class="site-main">

    <!-- =====================================================
     HERO – PROPERTY MAP
     ====================================================== -->
    <section class="hero" id="property-map-hero">
      <div class="hero-content">
        <h1><?php the_title(); ?></h1>

        <?php if ( has_excerpt() ) : ?>
          <p class="lead"><?php echo get_the_excerpt(); ?></p>
        <?php else : ?>
          <p class="lead">
            Explore every listing on the map and click a marker to preview the property.
          </p>
        <?php endif; ?>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="property-map">
          <div
            id="property-map"
            class="property-map__canvas"
          ></div>
          <script type="application/json" id="property-map-data"><?php echo wp_json_encode( $markers ); ?></script>

          <div class="property-map__selected" aria-live="polite">
            <div class="content-panel-box">
              <p class="text-sm muted">Click a marker to view the listing.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

</main>

<?php get_footer(); ?>
