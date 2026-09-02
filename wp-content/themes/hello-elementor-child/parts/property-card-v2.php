<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Template Part: Property Card (V2)
 * Uses ACF repeater v2_units with:
 * - v2_bedrooms
 * - v2_gross_size_min / v2_gross_size_max
 * - v2_price_usd_min / v2_price_usd_max
 *
 * Summary mode (no v2_beds): shows bed range + price range + size range
 * Single mode (v2_beds set): shows aggregate for that bedroom across matching rows
 */

$card_args = get_query_var( 'pera_property_card_args' );
$card_args = is_array( $card_args ) ? $card_args : array();

$variant = isset( $card_args['variant'] ) ? sanitize_key( $card_args['variant'] ) : 'archive';

$show_badges   = array_key_exists( 'show_badges',  $card_args ) ? (bool) $card_args['show_badges']  : true;
$show_admin    = array_key_exists( 'show_admin',   $card_args ) ? (bool) $card_args['show_admin']   : true;
$show_excerpt  = array_key_exists( 'show_excerpt', $card_args ) ? (bool) $card_args['show_excerpt'] : true;
$excerpt_words = isset( $card_args['excerpt_words'] ) ? max( 0, (int) $card_args['excerpt_words'] ) : 24;
$image_size    = isset( $card_args['image_size'] ) ? sanitize_key( $card_args['image_size'] ) : 'pera-card';

$extra_classes = isset( $card_args['card_classes'] ) ? sanitize_text_field( $card_args['card_classes'] ) : '';
$card_classes  = trim( $extra_classes . ' property-card property-card--' . $variant );

$post_id          = get_the_ID();
$v2_beds_selected = isset( $card_args['v2_beds'] ) ? (int) $card_args['v2_beds'] : 0;
$title            = get_the_title( $post_id );

/* ============================================================
   URL (Option A)
   - If v2_beds is active in archive, carry it into the single page
   ============================================================ */

$card_url = get_permalink( $post_id );
if ( $v2_beds_selected > 0 ) {
  $card_url = add_query_arg(
    array( 'unit_key' => $v2_beds_selected ),
    $card_url
  );
}

/* ============================================================
   DATA
   ============================================================ */

// ACF
$main_image   = function_exists( 'get_field' ) ? get_field( 'main_image', $post_id ) : null;
$project_name = function_exists( 'get_field' ) ? (string) get_field( 'project_name', $post_id ) : '';

// Repeater (use the shared reader so every card rendering path uses the same data rules).
$units = function_exists( 'pera_v2_get_units_rows' ) ? pera_v2_get_units_rows( $post_id ) : array();

// District / Region
if ( function_exists( 'pera_get_property_card_location_terms' ) ) {
  $location_terms = pera_get_property_card_location_terms( $post_id );
  $district_term  = isset( $location_terms['district_term'] ) ? $location_terms['district_term'] : null;
  $region_term    = isset( $location_terms['region_term'] ) ? $location_terms['region_term'] : null;
} else {
  $district_terms = get_the_terms( $post_id, 'district' );
  $region_terms   = get_the_terms( $post_id, 'region' );

  $district_term = ( ! empty( $district_terms ) && ! is_wp_error( $district_terms ) ) ? $district_terms[0] : null;
  $region_term   = ( ! empty( $region_terms ) && ! is_wp_error( $region_terms ) ) ? $region_terms[0] : null;
}

// Specials (optional pill + tooltip, kept)
$specials_terms = get_the_terms( $post_id, 'special' );
$specials_term  = null;
$specials_by_slug = array();

if ( ! empty( $specials_terms ) && ! is_wp_error( $specials_terms ) ) {
  foreach ( $specials_terms as $term ) {
    if ( isset( $term->slug ) && $term->slug !== 'citizenship' ) {
      $specials_by_slug[ $term->slug ] = $term;
    }
  }

  if ( isset( $specials_by_slug['resales'] ) ) {
    $specials_term = $specials_by_slug['resales'];
  } elseif ( isset( $specials_by_slug['project'] ) ) {
    $specials_term = $specials_by_slug['project'];
  } elseif ( ! empty( $specials_by_slug ) ) {
    $specials_term = reset( $specials_by_slug );
  }
}

$specials_label = $specials_term ? ( function_exists( 'pera_ml_term' ) ? pera_ml_term( $specials_term ) : $specials_term->name ) : '';
$specials_slug  = $specials_term ? $specials_term->slug : '';
$has_project    = isset( $specials_by_slug['project'] );
$has_resale     = isset( $specials_by_slug['resales'] );
$show_project_price = $has_project && ! $has_resale;

$specials_tooltip = '';
if ( $specials_slug === 'resales' || $specials_slug === 'resale' ) {
  $specials_tooltip = pera_ml_ui( 'Resale: offered by an individual owner (private seller).', 'theme.property_card.resale_tooltip' );
} elseif ( $specials_slug === 'project' || $specials_slug === 'projects' ) {
  $specials_tooltip = pera_ml_ui( 'Project: sold by the developer; multiple unit types may be available.', 'theme.property_card.project_tooltip' );
}

// Image ID
$image_id = ( is_array( $main_image ) && ! empty( $main_image['ID'] ) ) ? (int) $main_image['ID'] : 0;

// Published date
$published_ts  = get_the_time( 'U', $post_id );
$last_update_txt = $published_ts ? date_i18n( 'M d, Y', $published_ts ) : '';



$bed_label = function( int $beds ) {
  return (string) $beds;
};

/* ============================================================
   V2 AGGREGATION
   - Summary mode (no v2_beds): compute global ranges across all rows
   - Single mode (v2_beds): compute ranges across matching rows
   ============================================================ */

$unit_totals = function_exists( 'pera_v2_units_aggregate' )
  ? pera_v2_units_aggregate( $units, $v2_beds_selected )
  : array();

$beds_min  = isset( $unit_totals['beds_min'] ) ? (int) $unit_totals['beds_min'] : 0;
$beds_max  = isset( $unit_totals['beds_max'] ) ? (int) $unit_totals['beds_max'] : 0;
$price_min = isset( $unit_totals['price_min'] ) ? (int) $unit_totals['price_min'] : 0;
$price_max = isset( $unit_totals['price_max'] ) ? (int) $unit_totals['price_max'] : 0;
$size_min  = isset( $unit_totals['size_min'] ) ? (float) $unit_totals['size_min'] : 0;
$size_max  = isset( $unit_totals['size_max'] ) ? (float) $unit_totals['size_max'] : 0;

// Build badge text
$beds_badge_txt = '';
if ( $v2_beds_selected > 0 ) {
  $beds_badge_txt = $bed_label( $v2_beds_selected );
} elseif ( $beds_min > 0 ) {
  $beds_badge_txt = ( $beds_min === $beds_max )
    ? $bed_label( $beds_min )
    : ( $bed_label( $beds_min ) . '–' . $bed_label( $beds_max ) );
}

// Price display (V2 rules)
// - Project: "From $MIN" only
// - Resale: "$MIN" or "$MIN–$MAX" (no "From")
$price_mode = $show_project_price ? 'from' : ( $price_max > 0 && $price_max !== $price_min ? 'range' : 'single' );
$display_price = function_exists( 'pera_property_display_price' )
  ? pera_property_display_price( $price_min, $price_max, $price_mode )
  : array();

// Size display
$size_txt = function_exists( 'pera_v2_units_format_size_text' )
  ? pera_v2_units_format_size_text( $size_min, $size_max )
  : '';
?>

<article <?php post_class( $card_classes ); ?>>
  <div class="property-card__inner pera-card-shell">

    <div class="property-card__media">

      <?php if ( $show_badges && ( $beds_badge_txt || $specials_label ) ) : ?>
        <div class="property-card__badge">

          <?php if ( $beds_badge_txt ) : ?>
            <span class="pill pill--green property-card__badge-inner">
              <svg class="icon icon-bed" aria-hidden="true">
                <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-bed' ); ?>"></use>
              </svg>
              <span class="property-card__badge-text">
                <?php echo esc_html( $beds_badge_txt ); ?>
              </span>
            </span>
          <?php endif; ?>

          <?php if ( $specials_label ) : ?>
            <div class="property-card__specials-row">

              <span class="pill pill--green pill--sm">
                <?php echo esc_html( $specials_label ); ?>
              </span>

              <?php if ( $specials_tooltip ) : ?>
                <span class="property-card__tooltip-wrap">
                  <button
                    type="button"
                    class="property-card__tooltip-btn"
                    aria-label="<?php echo esc_attr( sprintf( pera_ml_ui( 'More info about %s', 'theme.property_card.more_info' ), $specials_label ) ); ?>"
                  >
                    i
                  </button>

                  <span class="property-card__tooltip text-xs" role="tooltip">
                    <?php echo esc_html( $specials_tooltip ); ?>
                  </span>
                </span>
              <?php endif; ?>

            </div>
          <?php endif; ?>

        </div>
      <?php endif; ?>

      <div class="property-card__topright">
        <button
          class="fav-toggle"
          type="button"
          aria-pressed="false"
          aria-label="<?php echo esc_attr( pera_ml_ui( 'Add to favourites', 'theme.property_card.add_favourite' ) ); ?>"
          data-label-add="<?php echo esc_attr( pera_ml_ui( 'Add to favourites', 'theme.property_card.add_favourite' ) ); ?>"
          data-label-remove="<?php echo esc_attr( pera_ml_ui( 'Remove from favourites', 'theme.property_card.remove_favourite' ) ); ?>"
          data-post-id="<?php echo esc_attr( $post_id ); ?>"
        >
          <span class="fav-toggle__icon" aria-hidden="true">
            <svg class="icon icon-heart icon-heart--outline" aria-hidden="true" focusable="false">
              <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-heart' ); ?>"></use>
            </svg>
            <svg class="icon icon-heart icon-heart--filled" aria-hidden="true" focusable="false">
              <use
                href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-heart' ); ?>"
                fill="currentColor"
                stroke="none"
              ></use>
            </svg>
          </span>
          <span class="fav-minus" aria-hidden="true">
            <svg class="icon icon-minus" aria-hidden="true" focusable="false">
              <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-heart-remove' ); ?>"></use>
            </svg>
          </span>
        </button>

        <?php if ( $show_admin && pera_is_frontend_admin_equivalent() ) : ?>
          <div class="property-card__admin-pills">

            <?php if ( $project_name !== '' ) : ?>
              <div class="pill pill--brand pill--sm">
                <?php echo esc_html( $project_name ); ?>
              </div>
            <?php endif; ?>

            <a
              href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>"
              class="pill pill--brand text-xs"
              target="_blank"
              rel="noopener"
            >
              Edit
            </a>

          </div>
        <?php endif; ?>
      </div>

      <a
        href="<?php echo esc_url( $card_url ); ?>"
        class="property-card__media-link"
        aria-label="<?php echo esc_attr( sprintf( pera_ml_ui( 'View property: %s', 'theme.property_card.view_property' ), $title ) ); ?>"
      >
        <?php if ( $image_id ) : ?>
          <?php
            echo wp_get_attachment_image(
              $image_id,
              $image_size,
              false,
              array(
                'alt'      => esc_attr( $title ),
                'loading'  => 'lazy',
                'decoding' => 'async',
              )
            );
          ?>
        <?php else : ?>
          <span class="property-card__media-placeholder" aria-hidden="true">
            <img
              src="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/logo-white.svg' ); ?>"
              alt=""
              loading="lazy"
              decoding="async"
              class="property-card__media-placeholder-logo"
            >
          </span>
        <?php endif; ?>
      </a>

    </div><!-- /.property-card__media -->

    <div class="property-card__body">

      <?php if ( $district_term || $region_term ) : ?>
        <div class="property-card__location">

          <?php if ( $district_term ) : ?>
            <a href="<?php echo esc_url( get_term_link( $district_term ) ); ?>" class="pill pill--subtle property-card__location-link">
              <?php echo esc_html( ( function_exists( 'pera_ml_term' ) ? pera_ml_term( $district_term ) : $district_term->name ) ); ?>
            </a>
          <?php endif; ?>

          <?php if ( $region_term ) : ?>
            <a href="<?php echo esc_url( get_term_link( $region_term ) ); ?>" class="pill pill--subtle property-card__location-link">
              <?php echo esc_html( ( function_exists( 'pera_ml_term' ) ? pera_ml_term( $region_term ) : $region_term->name ) ); ?>
            </a>
          <?php endif; ?>

        </div>
      <?php endif; ?>

      <h3 class="property-card__title">
        <a href="<?php echo esc_url( $card_url ); ?>" class="property-card__title-link">
          <?php the_title(); ?>
        </a>
      </h3>

      <?php if ( $show_excerpt && has_excerpt() ) : ?>
        <p class="property-card__excerpt">
          <?php echo esc_html( wp_trim_words( get_the_excerpt(), $excerpt_words, '…' ) ); ?>
        </p>
      <?php endif; ?>

    </div><!-- /.property-card__body -->

    <div class="property-card__footer">

      <div class="property-card__footer-row property-card__footer-row--top">
        <?php if ( ! empty( $display_price['valid'] ) ) : ?>
          <span class="property-card__price">
            <?php if ( 'from' === $price_mode ) : ?>
              <span class="price-prefix"><?php echo esc_html( pera_ml_ui( 'From', 'theme.template.single_property.from' ) ); ?></span>
            <?php endif; ?>
            <?php echo pera_property_display_price_html( $display_price ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
          </span>
        <?php endif; ?>
    
        <?php if ( $size_txt ) : ?>
          <span class="property-card__size property-card__meta-sm">
            <?php echo esc_html( $size_txt ); ?>
          </span>
        <?php endif; ?>
      </div>
    
      <?php if ( $last_update_txt ) : ?>
        <div class="property-card__footer-row property-card__footer-row--bottom">
          <span class="property-card__updated property-card__meta-sm">
            <?php echo esc_html( $last_update_txt ); ?>
          </span>
        </div>
      <?php endif; ?>
    
    </div><!-- /.property-card__footer -->


  </div><!-- /.property-card__inner -->
</article>
