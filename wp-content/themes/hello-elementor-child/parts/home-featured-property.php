<?php
/**
 * Template Part: Home – Featured Property
 * Location: /parts/home-featured-property.php
 *
 * Args:
 * - property_id   (int)    REQUIRED
 * - kicker        (string) Optional e.g. "Featured Villa" / "Featured Apartment"
 * - guide_url     (string) Optional
 * - guide_label   (string) Optional (default "Read area guide")
 * - points        (array)  Optional bullet list
 * - primary_cta   (array)  Optional ['label' => 'View listing', 'url' => '...']
 * - secondary_cta (array)  Optional ['label' => 'Request details', 'url' => '...']
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$property_id = isset($args['property_id']) ? (int) $args['property_id'] : 0;
if ( ! $property_id ) return;

$p = get_post( $property_id );
if ( ! $p || $p->post_type !== 'property' || $p->post_status !== 'publish' ) return;

$title     = get_the_title( $property_id );
$permalink = get_permalink( $property_id );

/* Excerpt fallback */
$excerpt = has_excerpt( $property_id )
  ? get_the_excerpt( $property_id )
  : wp_trim_words( wp_strip_all_tags( get_post_field('post_content', $property_id ) ), 26, '…' );

/* Image: ACF main_image then featured image */
$main_image = function_exists('get_field') ? get_field('main_image', $property_id) : null;

$img_id  = 0;
$img_alt = $title;

if ( is_array( $main_image ) ) {

  // If ACF returns an "Image Array", it should include ID and alt.
  if ( ! empty( $main_image['ID'] ) ) {
    $img_id = (int) $main_image['ID'];
  }

  if ( ! empty( $main_image['alt'] ) ) {
    $img_alt = (string) $main_image['alt'];
  }

} elseif ( is_numeric( $main_image ) ) {
  // If ACF field is set to return "Image ID"
  $img_id = (int) $main_image;
}

if ( ! $img_id && has_post_thumbnail( $property_id ) ) {
  $img_id  = (int) get_post_thumbnail_id( $property_id );
  $img_alt = $title;
}


/* Price */
$project_summary = function_exists('get_field') ? (string) get_field('project_summary', $property_id ) : '';
$project_summary_items = array();

if ( ! empty( $project_summary ) ) {
  $summary_html = wp_kses_post( $project_summary );
  $summary_dom = new DOMDocument();
  libxml_use_internal_errors( true );
  $summary_dom->loadHTML(
    '<?xml encoding="utf-8" ?>' . $summary_html,
    LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
  );
  libxml_clear_errors();

  foreach ( $summary_dom->getElementsByTagName( 'li' ) as $summary_li ) {
    $item_html = '';
    foreach ( $summary_li->childNodes as $summary_child ) {
      $item_html .= $summary_dom->saveHTML( $summary_child );
    }
    $item_html = trim( $item_html );
    if ( $item_html !== '' ) {
      $project_summary_items[] = $item_html;
    }
    if ( count( $project_summary_items ) >= 3 ) {
      break;
    }
  }
}

$price_label = '';
$is_project = has_term( 'project', 'special', $property_id );

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
      'context'    => 'featured',
      'unit_key'   => 0,
      'is_project' => $is_project,
    )
  );
  $price_label = $units_data['price_text'] ?? '';
}

/* Taxonomies (optional output) */
$district_terms = get_the_terms( $property_id, 'district' );
$region_terms   = get_the_terms( $property_id, 'region' );
$district = ( ! empty($district_terms) && ! is_wp_error($district_terms) ) ? $district_terms[0] : null;
$region   = ( ! empty($region_terms) && ! is_wp_error($region_terms) ) ? $region_terms[0] : null;

$bed_terms = get_the_terms( $property_id, 'bedrooms' );
$beds      = ( ! empty($bed_terms) && ! is_wp_error($bed_terms) ) ? $bed_terms[0]->name : '';

$special_terms = get_the_terms( $property_id, 'special' );
$special       = ( ! empty($special_terms) && ! is_wp_error($special_terms) ) ? $special_terms[0]->name : '';

/* Args */
$kicker      = isset($args['kicker']) ? (string) $args['kicker'] : 'Featured property';
$guide_url   = isset($args['guide_url']) ? (string) $args['guide_url'] : '';
$guide_label = isset($args['guide_label']) ? (string) $args['guide_label'] : 'Read area guide';

$points = ( isset($args['points']) && is_array($args['points']) ) ? $args['points'] : array();

$primary_cta = isset($args['primary_cta']) && is_array($args['primary_cta'])
  ? $args['primary_cta']
  : array('label' => 'View listing', 'url' => $permalink);

$secondary_cta = isset($args['secondary_cta']) && is_array($args['secondary_cta'])
  ? $args['secondary_cta']
  : array('label' => 'Request details', 'url' => $permalink . '#contact-form');
?>


<section class="section home-featured">
  <article class="property-card property-card--archive">
    <div class="property-card__inner">
      <p class="home-featured__kicker text-upper text-xs">
        <?php echo esc_html( $kicker ); ?>
      </p>

      <h2 class="property-card__title">
        <a href="<?php echo esc_url( $permalink ); ?>" class="property-card__title-link">
          <?php echo esc_html( $title ); ?>
        </a>
      </h2>

      <?php if ( $img_id ) : ?>
        <div class="property-card__media">
          <a
            href="<?php echo esc_url( $permalink ); ?>"
            class="property-card__media-link"
            aria-label="<?php echo esc_attr( sprintf( 'View property: %s', $title ) ); ?>"
          >
            <?php
              echo wp_get_attachment_image(
                $img_id,
                'pera-card',
                false,
                [
                  'alt'      => esc_attr( $img_alt ),
                  'loading'  => 'lazy',
                  'decoding' => 'async',
                  'sizes'    => '(max-width: 768px) 92vw, 520px',
                ]
              );
            ?>
          </a>
        </div>
      <?php endif; ?>

      <div class="home-featured__content property-card__body">

        <?php if ( $district || $region ) : ?>
          <div class="property-card__location">
            <?php if ( $district ) : ?>
              <a href="<?php echo esc_url( get_term_link( $district ) ); ?>" class="property-card__location-link">
                <?php echo esc_html( $district->name ); ?>
              </a>
            <?php endif; ?>

            <?php if ( $region ) : ?>
              <a href="<?php echo esc_url( get_term_link( $region ) ); ?>" class="property-card__location-link">
                <?php echo esc_html( $region->name ); ?>
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ( $excerpt ) : ?>
          <p class="property-card__excerpt">
            <?php echo esc_html( $excerpt ); ?>
          </p>
        <?php endif; ?>

      </div><!-- /.home-featured__content -->

      <div class="property-card__footer">

        <?php if ( $price_label ) : ?>
          <div class="property-card__footer-row property-card__footer-row--top">
            <span class="property-card__price">
              <?php echo esc_html( $price_label ); ?>
            </span>
          </div>
        <?php endif; ?>

        <?php if ( ! empty( $project_summary_items ) ) : ?>
          <ul class="checklist">
            <?php foreach ( $project_summary_items as $summary_item ) : ?>
              <li>
                <svg class="icon-check checklist-icon" aria-hidden="true" focusable="false" width="18" height="18">
                  <use href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logos-icons/icons.svg#icon-check' ); ?>"></use>
                </svg>
                <span class="text-sm"><?php echo wp_kses_post( $summary_item ); ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <?php if ( ! empty( $points ) ) : ?>
          <ul class="home-featured__points text-sm">
            <?php foreach ( $points as $pt ) : ?>
              <li><?php echo esc_html( $pt ); ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <?php if ( $guide_url ) : ?>
          <p class="home-featured__guide">
            <a href="<?php echo esc_url( $guide_url ); ?>" target="_blank" rel="noopener">
              <?php echo esc_html( $guide_label ); ?>
            </a>
          </p>
        <?php endif; ?>

        <div class="home-featured__actions">
          <a class="btn btn--solid btn--blue" href="<?php echo esc_url( $primary_cta['url'] ); ?>">
            <?php echo esc_html( $primary_cta['label'] ); ?>
          </a>

          <a class="btn btn--ghost btn--green" href="<?php echo esc_url( $secondary_cta['url'] ); ?>">
            <?php echo esc_html( $secondary_cta['label'] ); ?>
          </a>
        </div>

      </div><!-- /.property-card__footer -->
    </div><!-- /.property-card__inner -->
  </article>
</section>
