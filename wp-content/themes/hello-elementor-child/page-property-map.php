<?php
/**
 * Template Name: Property Map
 * Description: Map-led Istanbul property landing page with ACF map markers.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'pera_property_map_is_current_template' ) ) {
    function pera_property_map_is_current_template(): bool {
        return is_page_template( 'page-property-map.php' );
    }
}

if ( ! function_exists( 'pera_property_map_faq_items' ) ) {
    function pera_property_map_faq_items(): array {
        return array(
            array( 'q' => 'How do I use the Istanbul property map?', 'a' => 'Use the filters to narrow listings by district, budget, bedrooms and property type, then click a marker to preview the property and open the full listing.' ),
            array( 'q' => 'Can Pera Property arrange property viewings?', 'a' => 'Yes. Share your budget, preferred areas and buying purpose, and our Istanbul team can help create a focused viewing shortlist.' ),
            array( 'q' => 'Does the map show every available property?', 'a' => 'The map shows published properties with confirmed map coordinates. Our team may also have off-market or newly added options that are not yet visible online.' ),
            array( 'q' => 'Which side of Istanbul is better for property investment?', 'a' => 'The Asian side is less dense compared to the European side hence is generally the choice of families. The European side is where most of the international work is. The right choice depends on budget, transport access, rental demand, resale potential and your ownership goals.' ),
            array( 'q' => 'Which Istanbul districts are popular with foreign buyers?', 'a' => 'Central districts such as Beşiktaş, Şişli, Beyoğlu and Kadıköy are often considered, while family and value buyers also compare areas such as Bahçeşehir and Beylikdüzü.' ),
            array( 'q' => 'Can foreigners buy property in Istanbul?', 'a' => 'Foreign buyers can generally buy property in Turkey. Independent legal checks should be completed before purchase.' ),
            array( 'q' => 'Are prices on the map kept up to date?', 'a' => 'We aim to keep published listing prices current, but availability and pricing can change. Please contact us to confirm the latest status before making decisions.' ),
        );
    }
}

add_action(
    'wp_head',
    static function () {
        if ( ! pera_property_map_is_current_template() || ! function_exists( 'pera_property_map_faq_items' ) ) {
            return;
        }

        $main_entity = array();
        foreach ( pera_property_map_faq_items() as $item ) {
            $main_entity[] = array(
                '@type'          => 'Question',
                'name'           => $item['q'],
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text'  => $item['a'],
                ),
            );
        }

        echo '<script type="application/ld+json">' . wp_json_encode(
            array(
                '@context'   => 'https://schema.org',
                '@type'      => 'FAQPage',
                'mainEntity' => $main_entity,
            ),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) . '</script>' . "\n";
    },
    25
);

get_header();

$page_id    = get_queried_object_id();
$markers    = array();
$acf_loaded = function_exists( 'get_field' );

if ( ! function_exists( 'pera_units_get_display_data' ) ) {
    $v2_helper_path = get_stylesheet_directory() . '/inc/v2-units-index.php';
    if ( file_exists( $v2_helper_path ) ) {
        require_once $v2_helper_path;
    }
}

if ( $acf_loaded ) {
    $property_query = new WP_Query(
        array(
            'post_type'      => 'property',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        )
    );

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

        $location_terms = function_exists( 'pera_get_property_card_location_terms' ) ? pera_get_property_card_location_terms( (int) $property_id ) : array();
        $district_term  = isset( $location_terms['district_term'] ) && $location_terms['district_term'] instanceof WP_Term ? $location_terms['district_term'] : null;
        $type_terms     = get_the_terms( $property_id, 'property_type' );
        $type_term      = ( ! empty( $type_terms ) && ! is_wp_error( $type_terms ) ) ? $type_terms[0] : null;
        $price_min      = (int) get_post_meta( $property_id, 'v2_price_usd_min', true );
        $price_max      = (int) get_post_meta( $property_id, 'v2_price_usd_max', true );
        $bedrooms       = array();
        $price_text     = '';

        $special_terms = get_the_terms( $property_id, 'special' );
        $is_project    = false;
        $is_resale     = false;
        if ( ! empty( $special_terms ) && ! is_wp_error( $special_terms ) ) {
            foreach ( $special_terms as $term ) {
                if ( in_array( $term->slug, array( 'project', 'projects' ), true ) ) {
                    $is_project = true;
                }
                if ( in_array( $term->slug, array( 'resale', 'resales' ), true ) ) {
                    $is_resale = true;
                }
            }
        }

        // Match the shared property-card rule: resale wins if both terms exist.
        $show_project_price = $is_project && ! $is_resale;

        if ( function_exists( 'pera_units_get_display_data' ) ) {
            $units_data = pera_units_get_display_data( (int) $property_id, array( 'context' => 'map', 'unit_key' => 0, 'is_project' => $show_project_price ) );
            $price_text = (string) ( $units_data['price_text'] ?? '' );
            if ( $price_min < 1 ) {
                $price_min = (int) ( $units_data['price_min'] ?? 0 );
            }
            if ( $price_max < 1 ) {
                $price_max = (int) ( $units_data['price_max'] ?? 0 );
            }
            foreach ( (array) ( $units_data['aggregated_by_beds'] ?? array() ) as $bed_key => $bed_data ) {
                $bed_key = (int) $bed_key;
                if ( $bed_key > 0 ) {
                    $bedrooms[] = $bed_key;
                }
            }
        }

        $markers[] = array(
            'id'            => (int) $property_id,
            'title'         => get_the_title( $property_id ),
            'url'           => get_permalink( $property_id ),
            'lat'           => $lat,
            'lng'           => $lng,
            'price_text'    => $price_text,
            'price_min'     => $price_min,
            'price_max'     => $price_max > 0 ? $price_max : $price_min,
            'price_mode'    => $show_project_price ? 'from' : ( $price_max > 0 && $price_max !== $price_min ? 'range' : 'single' ),
            'district'      => $district_term ? $district_term->slug : '',
            'district_name' => $district_term ? ( function_exists( 'pera_ml_term' ) ? pera_ml_term( $district_term ) : $district_term->name ) : '',
            'type'          => $type_term instanceof WP_Term ? $type_term->slug : '',
            'type_name'     => $type_term instanceof WP_Term ? ( function_exists( 'pera_ml_term' ) ? pera_ml_term( $type_term ) : $type_term->name ) : '',
            'bedrooms'      => array_values( array_unique( $bedrooms ) ),
        );
    }
    wp_reset_postdata();
}

$district_options = array();
$type_options     = array();
$bed_options      = array();
foreach ( $markers as $marker ) {
    if ( ! empty( $marker['district'] ) && ! empty( $marker['district_name'] ) ) {
        $district_options[ $marker['district'] ] = $marker['district_name'];
    }
    if ( ! empty( $marker['type'] ) && ! empty( $marker['type_name'] ) ) {
        $type_options[ $marker['type'] ] = $marker['type_name'];
    }
    foreach ( (array) $marker['bedrooms'] as $beds ) {
        $bed_options[ (int) $beds ] = (int) $beds;
    }
}
asort( $district_options );
asort( $type_options );
ksort( $bed_options );

$whatsapp_context = function_exists( 'pera_get_whatsapp_context' ) ? pera_get_whatsapp_context() : array();
$whatsapp_url     = function_exists( 'pera_get_whatsapp_url' ) ? pera_get_whatsapp_url() : (string) ( $whatsapp_context['whatsapp_url'] ?? '' );
$property_archive = get_post_type_archive_link( 'property' );

$area_copy = array(
    'besiktas'     => 'Central, prestigious and highly connected, with strong demand from both local and international buyers.',
    'sisli'        => 'A central business and lifestyle district with metro access, established neighbourhoods and modern developments.',
    'beyoglu'      => 'Historic, cultural and walkable, suited to buyers who want city life and characterful neighbourhoods.',
    'kagithane'    => 'A changing central district with improving transport links and comparatively accessible entry prices.',
    'sariyer'      => 'Green, coastal and premium, with villa areas, Bosphorus neighbourhoods and international-school access.',
    'kadikoy'      => 'A lively Asian-side hub with strong local demand, ferries, metro access and a mature lifestyle scene.',
    'uskudar'      => 'Historic waterfront living on the Asian side with fast cross-city connections and Bosphorus appeal.',
    'atasehir'     => 'A modern Asian-side business and residential centre popular with professionals and families.',
    'umraniye'     => 'A practical Asian-side area with metro links, family housing and developing business demand.',
    'kucukcekmece' => 'A value-focused European-side district with larger projects and improving transport corridors.',
    'bahcesehir'   => 'Popular with families seeking newer developments, green surroundings and more internal space.',
    'beylikduzu'   => 'A spacious value area with coastal access, family compounds and lower entry prices than central Istanbul.',
);
$area_cards = array();
foreach ( $area_copy as $slug => $copy ) {
    $term = get_term_by( 'slug', $slug, 'district' );
    if ( $term instanceof WP_Term ) {
        $link = get_term_link( $term );
        if ( ! is_wp_error( $link ) ) {
            $area_cards[] = array( 'name' => ( function_exists( 'pera_ml_term' ) ? pera_ml_term( $term ) : $term->name ), 'copy' => $copy, 'url' => $link );
        }
    }
}
?>

<main id="primary" class="site-main property-map-page">

    <!-- =====================================================
     HERO – PROPERTY MAP PAGE
     Canonical structure + WP image ID 55756 fallback
     ===================================================== -->
    <section class="hero hero--left hero--property-map" id="property-map-hero">
        <div class="hero__media" aria-hidden="true">
            <?php
            // Prefer the page featured image; otherwise fallback to vopbesiktas.svg (ID 55756).
            // TODO: Replace this fallback with a Property Map-specific Istanbul search hero image when one is available.
            $hero_img_id = get_post_thumbnail_id( $page_id );

            if ( $hero_img_id ) {
                echo wp_get_attachment_image(
                    $hero_img_id,
                    'full',
                    false,
                    array(
                        'class'    => 'hero-media',
                        'loading'  => 'eager',
                        'decoding' => 'async',
                    )
                );
            } else {
                echo wp_get_attachment_image(
                    55756,
                    'full',
                    false,
                    array(
                        'class'         => 'hero-media',
                        'fetchpriority' => 'high',
                        'loading'       => 'eager',
                        'decoding'      => 'async',
                    )
                );
            }
            ?>
            <div class="hero-overlay" aria-hidden="true"></div>
        </div>

        <div class="hero-content">
            <h1><?php echo esc_html( pera_ml_ui( 'Istanbul Property Map', 'theme.template.page_property_map.istanbul_property_map' ) ); ?></h1>
            <p class="lead"><?php echo esc_html( pera_ml_ui( 'Explore apartments, villas and investment properties for sale across Istanbul. Use the interactive map to compare locations, neighbourhoods and available listings.', 'theme.template.page_property_map.explore_apartments_villas_and_investment_properties_for_sale_across_ista' ) ); ?></p>
            <div class="hero-actions">
                <a class="btn btn--solid btn--green" href="#property-map-explorer" data-map-track="hero_explore_map"><?php echo esc_html( pera_ml_ui( 'Explore the map', 'theme.template.page_property_map.explore_the_map' ) ); ?></a>
                <a class="btn btn--solid btn--blue" href="#property-map-assistance" data-map-track="hero_ask_where_to_buy"><?php echo esc_html( pera_ml_ui( 'Ask us where to buy', 'theme.template.page_property_map.ask_us_where_to_buy' ) ); ?></a>
            </div>
        </div>
    </section>

    <section class="section" id="property-map-explorer">
        <div class="container">
            <div class="property-map-card content-panel-box">
                <div class="property-map-card__header">
                    <div><p class="eyebrow"><?php echo esc_html( pera_ml_ui( 'Interactive search', 'theme.template.page_property_map.interactive_search' ) ); ?></p><h2><?php echo esc_html( pera_ml_ui( 'Explore Istanbul by map', 'theme.template.page_property_map.explore_istanbul_by_map' ) ); ?></h2></div>
                    <p class="property-map-count" id="property-map-count" aria-live="polite"><?php echo esc_html( sprintf( _n( '%s property shown', '%s properties shown', count( $markers ), 'hello-elementor-child' ), number_format_i18n( count( $markers ) ) ) ); ?></p>
                </div>

                <form class="property-map-filters" id="property-map-filters" aria-label="<?php echo esc_attr( pera_ml_ui( 'Filter map properties', 'theme.template.page_property_map.aria_label.filter_map_properties' ) ); ?>">
                    <div class="field"><label for="map-filter-district"><?php echo esc_html( pera_ml_ui( 'District', 'theme.template.page_property_map.district' ) ); ?></label><select id="map-filter-district" name="district"><option value=""><?php echo esc_html( pera_ml_ui( 'All districts', 'theme.template.page_property_map.all_districts' ) ); ?></option><?php foreach ( $district_options as $slug => $name ) : ?><option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label for="map-filter-min-price"><?php echo esc_html( pera_ml_ui( 'Minimum price', 'theme.template.page_property_map.minimum_price' ) ); ?> <span data-map-filter-currency dir="ltr">(USD)</span></label><input id="map-filter-min-price" data-map-display-price="min" type="number" inputmode="numeric" min="0" step="1000" placeholder="<?php echo esc_attr( pera_ml_ui( 'No min', 'theme.template.page_property_map.placeholder.no_min' ) ); ?>"><input data-map-canonical-price="min" name="min_price" type="hidden" value=""></div>
                    <div class="field"><label for="map-filter-max-price"><?php echo esc_html( pera_ml_ui( 'Maximum price', 'theme.template.page_property_map.maximum_price' ) ); ?> <span data-map-filter-currency dir="ltr">(USD)</span></label><input id="map-filter-max-price" data-map-display-price="max" type="number" inputmode="numeric" min="0" step="1000" placeholder="<?php echo esc_attr( pera_ml_ui( 'No max', 'theme.template.page_property_map.placeholder.no_max' ) ); ?>"><input data-map-canonical-price="max" name="max_price" type="hidden" value=""></div>
                    <div class="field"><label for="map-filter-bedrooms"><?php echo esc_html( pera_ml_ui( 'Bedrooms', 'theme.template.page_property_map.bedrooms' ) ); ?></label><select id="map-filter-bedrooms" name="bedrooms"><option value=""><?php echo esc_html( pera_ml_ui( 'Any beds', 'theme.template.page_property_map.any_beds' ) ); ?></option><?php foreach ( $bed_options as $beds ) : ?><option value="<?php echo esc_attr( (string) $beds ); ?>"><?php echo esc_html( (string) $beds ); ?>+</option><?php endforeach; ?></select></div>
                    <div class="field"><label for="map-filter-type"><?php echo esc_html( pera_ml_ui( 'Property type', 'theme.template.page_property_map.property_type' ) ); ?></label><select id="map-filter-type" name="type"><option value=""><?php echo esc_html( pera_ml_ui( 'All types', 'theme.template.page_property_map.all_types' ) ); ?></option><?php foreach ( $type_options as $slug => $name ) : ?><option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option><?php endforeach; ?></select></div>
                    <button type="reset" class="btn btn--solid btn--red property-map-filters__reset"><?php echo esc_html( pera_ml_ui( 'Reset filters', 'theme.template.page_property_map.reset_filters' ) ); ?></button>
                </form>
                <p class="no-results property-map-empty" id="property-map-empty" hidden><?php echo esc_html( pera_ml_ui( 'No properties match these filters. Reset the filters or ask us to help shortlist options.', 'theme.template.page_property_map.no_properties_match_these_filters_reset_the_filters_or_ask_us_to_help_sh' ) ); ?></p>

                <div class="property-map-mobile-toggle" role="group" aria-label="<?php echo esc_attr( pera_ml_ui( 'Choose map or list view', 'theme.template.page_property_map.aria_label.choose_map_or_list_view' ) ); ?>"><button type="button" class="is-active" data-map-view="map"><?php echo esc_html( pera_ml_ui( 'Map', 'theme.template.page_property_map.map' ) ); ?></button><button type="button" data-map-view="list"><?php echo esc_html( pera_ml_ui( 'List', 'theme.template.page_property_map.list' ) ); ?></button></div>
                <div class="property-map-layout" data-active-view="map">
                    <div id="property-map" class="property-map__canvas"></div>
                    <script type="application/json" id="property-map-data"><?php echo wp_json_encode( $markers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?></script>
                    <aside class="property-map__selected" aria-live="polite" aria-label="<?php echo esc_attr( pera_ml_ui( 'Selected property', 'theme.template.page_property_map.aria_label.selected_property' ) ); ?>"><div id="property-map-results" class="cards-grid"><p class="no-results"><?php echo esc_html( pera_ml_ui( 'Click a marker to view the listing.', 'theme.template.page_property_map.click_a_marker_to_view_the_listing' ) ); ?></p></div></aside>
                </div>
            </div>
        </div>
    </section>

    <section class="section property-map-assistance" id="property-map-assistance">
        <div class="container property-map-assistance__inner content-panel-box">
            <div><p class="eyebrow"><?php echo esc_html( pera_ml_ui( 'Assisted search', 'theme.template.page_property_map.assisted_search' ) ); ?></p><h2><?php echo esc_html( pera_ml_ui( 'Not sure which part of Istanbul is right for you?', 'theme.template.page_property_map.not_sure_which_part_of_istanbul_is_right_for_you' ) ); ?></h2><p class="text-soft"><?php echo esc_html( pera_ml_ui( 'Tell us your budget, preferred property type and reason for buying. Our local team will suggest suitable neighbourhoods and properties.', 'theme.template.page_property_map.tell_us_your_budget_preferred_property_type_and_reason_for_buying_our_lo' ) ); ?></p></div>
            <form class="property-map-assist-form" id="property-map-assist-form" data-whatsapp-url="<?php echo esc_url( $whatsapp_url ); ?>">
                <label><?php echo esc_html( pera_ml_ui( 'Name', 'theme.template.page_property_map.name' ) ); ?><input name="name" type="text" autocomplete="name" required></label><label><?php echo esc_html( pera_ml_ui( 'WhatsApp number', 'theme.template.page_property_map.whatsapp_number' ) ); ?><input name="phone" type="tel" autocomplete="tel" required></label><label><?php echo esc_html( pera_ml_ui( 'Budget', 'theme.template.page_property_map.budget' ) ); ?><input name="budget" type="text" placeholder="<?php echo esc_attr( pera_ml_ui( 'e.g. $350,000', 'theme.template.page_property_map.placeholder.e_g_350_000' ) ); ?>"></label><label><?php echo esc_html( pera_ml_ui( 'Buying purpose', 'theme.template.page_property_map.buying_purpose' ) ); ?><select name="purpose"><option><?php echo esc_html( pera_ml_ui( 'Home', 'theme.template.page_property_map.home' ) ); ?></option><option><?php echo esc_html( pera_ml_ui( 'Investment', 'theme.template.page_property_map.investment' ) ); ?></option><option><?php echo esc_html( pera_ml_ui( 'Rental income', 'theme.template.page_property_map.rental_income' ) ); ?></option><option><?php echo esc_html( pera_ml_ui( 'Turkish citizenship', 'theme.template.page_property_map.turkish_citizenship' ) ); ?></option><option><?php echo esc_html( pera_ml_ui( 'Not sure yet', 'theme.template.page_property_map.not_sure_yet' ) ); ?></option></select></label><label><?php echo esc_html( pera_ml_ui( 'Preferred area, optional', 'theme.template.page_property_map.preferred_area_optional' ) ); ?><input name="area" type="text" placeholder="<?php echo esc_attr( pera_ml_ui( 'e.g. Kadıköy, Şişli or not sure', 'theme.template.page_property_map.placeholder.e_g_kad_k_y_i_li_or_not_sure' ) ); ?>"></label><button class="btn btn--solid btn--green" type="submit" data-map-track="assisted_search_submit"><?php echo esc_html( pera_ml_ui( 'Get personalised recommendations', 'theme.template.page_property_map.get_personalised_recommendations' ) ); ?></button>
                <p class="text-xs text-soft"><?php echo esc_html( pera_ml_ui( 'Submitting opens WhatsApp with your requirements so our team can respond directly.', 'theme.template.page_property_map.submitting_opens_whatsapp_with_your_requirements_so_our_team_can_respond' ) ); ?></p>
            </form>
        </div>
    </section>

    <?php if ( ! empty( $area_cards ) ) : ?><section class="section property-map-areas"><div class="container"><h2><?php echo esc_html( pera_ml_ui( 'Browse property by area', 'theme.template.page_property_map.browse_property_by_area' ) ); ?></h2><div class="property-map-card-grid"><?php foreach ( $area_cards as $card ) : ?><article class="content-panel-box"><h3><?php echo esc_html( $card['name'] ); ?></h3><p class="text-soft"><?php echo esc_html( $card['copy'] ); ?></p><a href="<?php echo esc_url( $card['url'] ); ?>">View properties in <?php echo esc_html( $card['name'] ); ?></a></article><?php endforeach; ?></div></div></section><?php endif; ?>


    <section class="section"><div class="container property-map-two-col"><div><h2><?php echo esc_html( pera_ml_ui( 'Buying property in Istanbul', 'theme.template.page_property_map.buying_property_in_istanbul' ) ); ?></h2><p class="text-soft"><?php echo esc_html( pera_ml_ui( 'Location, transport access and resale demand matter as much as the development itself. Before buying, checks should cover title deed status, planning, debts, valuation and any location-specific restrictions for foreign ownership.', 'theme.template.page_property_map.location_transport_access_and_resale_demand_matter_as_much_as_the_develo' ) ); ?></p><p class="text-soft"><?php echo esc_html( pera_ml_ui( 'Foreign buyers can generally purchase property in Turkey, subject to legal and location restrictions. Citizenship eligibility requires separate legal and valuation checks. Pera Property can help shortlist, inspect and compare suitable options before you travel.', 'theme.template.page_property_map.foreign_buyers_can_generally_purchase_property_in_turkey_subject_to_lega' ) ); ?></p></div><div><h2><?php echo esc_html( pera_ml_ui( 'Why buy with Pera Property?', 'theme.template.page_property_map.why_buy_with_pera_property' ) ); ?></h2><ul class="property-map-checks"><li><?php echo esc_html( pera_ml_ui( 'Istanbul-based team', 'theme.template.page_property_map.istanbul_based_team' ) ); ?></li><li><?php echo esc_html( pera_ml_ui( 'Operating since 2016', 'theme.template.page_property_map.operating_since_2016' ) ); ?></li><li><?php echo esc_html( pera_ml_ui( 'Access to properties from multiple developers and owners', 'theme.template.page_property_map.access_to_properties_from_multiple_developers_and_owners' ) ); ?></li><li><?php echo esc_html( pera_ml_ui( 'Legal and due-diligence coordination', 'theme.template.page_property_map.legal_and_due_diligence_coordination' ) ); ?></li><li><?php echo esc_html( pera_ml_ui( 'After-sales and rental-management support', 'theme.template.page_property_map.after_sales_and_rental_management_support' ) ); ?></li><li><?php echo esc_html( pera_ml_ui( 'Experience assisting international buyers', 'theme.template.page_property_map.experience_assisting_international_buyers' ) ); ?></li></ul></div></div></section>
    
    <section class="faq-section mt-md property-map-faq">
        <div class="container">
            <div class="section-header mb-md">
                <h2><?php echo esc_html( pera_ml_ui( 'Frequently Asked Questions', 'theme.template.page_property_map.frequently_asked_questions' ) ); ?></h2>
            </div>
    
            <div class="faq-accordion">
                <?php foreach ( pera_property_map_faq_items() as $index => $item ) : ?>
                    <details class="faq-item" <?php echo 0 === $index ? 'open' : ''; ?>>
                        <summary><?php echo esc_html( $item['q'] ); ?></summary>
                        <div class="faq-answer">
                            <p><?php echo esc_html( $item['a'] ); ?></p>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="section property-map-final"><div class="container content-panel-box">
        <h2><?php echo esc_html( pera_ml_ui( 'Let us help you shortlist the right properties', 'theme.template.page_property_map.let_us_help_you_shortlist_the_right_properties' ) ); ?></h2>
        <p class="text-soft"><?php echo esc_html( pera_ml_ui( 'Share your budget and requirements, and we will prepare a focused selection before your viewing trip to Istanbul.', 'theme.template.page_property_map.share_your_budget_and_requirements_and_we_will_prepare_a_focused_selecti' ) ); ?></p><div class="hero-actions property-map-final__actions">
            <a class="btn btn--solid btn--green" href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener" data-whatsapp="1" data-whatsapp-type="property_map_final" data-track-channel="whatsapp" data-track-intent="high" data-track-source="page" data-track-context="property_map_final" data-track-ga4-event="whatsapp_click" data-track-crm-event="whatsapp_click" data-map-track="final_whatsapp"><?php echo esc_html( pera_ml_ui( 'Message us on WhatsApp', 'theme.template.page_property_map.message_us_on_whatsapp' ) ); ?></a>
            <a class="btn btn--solid btn--blue" href="#property-map-assistance" data-map-track="final_shortlist"><?php echo esc_html( pera_ml_ui( 'Request a property shortlist', 'theme.template.page_property_map.request_a_property_shortlist' ) ); ?></a>
            <a class="btn btn--ghost btn--green" href="<?php echo esc_url( home_url( '/book-a-consultancy/' ) ); ?>"><?php echo esc_html( pera_ml_ui( 'Book a consultancy', 'theme.template.page_property_map.book_a_consultancy' ) ); ?></a>
        </div>
    </div>
  </section>
</main>

<?php get_footer(); ?>
