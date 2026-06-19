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
            array( 'q' => 'Does the map show every available property?', 'a' => 'The map shows published properties with confirmed map coordinates. Our team may also have off-market or newly added options that are not yet visible online.' ),
            array( 'q' => 'Which side of Istanbul is better for property investment?', 'a' => 'Both the European and Asian sides can work well. The right choice depends on budget, transport access, rental demand, resale potential and your ownership goals.' ),
            array( 'q' => 'Which Istanbul districts are popular with foreign buyers?', 'a' => 'Central districts such as Beşiktaş, Şişli, Beyoğlu and Kadıköy are often considered, while family and value buyers also compare areas such as Başakşehir, Bahçeşehir and Beylikdüzü.' ),
            array( 'q' => 'Can foreigners buy property in Istanbul?', 'a' => 'Foreign buyers can generally buy property in Turkey, subject to legal and location restrictions. Independent legal checks should be completed before purchase.' ),
            array( 'q' => 'Can I buy property remotely?', 'a' => 'Remote purchase can be possible with the correct legal representation, due diligence and secure payment process. We can help coordinate shortlisting and virtual viewings.' ),
            array( 'q' => 'Which properties may qualify for Turkish citizenship?', 'a' => 'Citizenship eligibility depends on legal rules, valuation and transaction structure. Treat eligibility as a separate legal and valuation check rather than relying on listing price alone.' ),
            array( 'q' => 'Are prices on the map kept up to date?', 'a' => 'We aim to keep published listing prices current, but availability and pricing can change. Please contact us to confirm the latest status before making decisions.' ),
            array( 'q' => 'Can Pera Property arrange property viewings?', 'a' => 'Yes. Share your budget, preferred areas and buying purpose, and our Istanbul team can help create a focused viewing shortlist.' ),
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
        if ( ! empty( $special_terms ) && ! is_wp_error( $special_terms ) ) {
            foreach ( $special_terms as $term ) {
                if ( in_array( $term->slug, array( 'project', 'projects' ), true ) ) {
                    $is_project = true;
                    break;
                }
            }
        }

        if ( function_exists( 'pera_units_get_display_data' ) ) {
            $units_data = pera_units_get_display_data( (int) $property_id, array( 'context' => 'map', 'unit_key' => 0, 'is_project' => $is_project ) );
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
            'district'      => $district_term ? $district_term->slug : '',
            'district_name' => $district_term ? $district_term->name : '',
            'type'          => $type_term instanceof WP_Term ? $type_term->slug : '',
            'type_name'     => $type_term instanceof WP_Term ? $type_term->name : '',
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
    'basaksehir'   => 'A planned family district with hospitals, schools, green space and modern residential compounds.',
    'bahcesehir'   => 'Popular with families seeking newer developments, green surroundings and more internal space.',
    'beylikduzu'   => 'A spacious value area with coastal access, family compounds and lower entry prices than central Istanbul.',
);
$area_cards = array();
foreach ( $area_copy as $slug => $copy ) {
    $term = get_term_by( 'slug', $slug, 'district' );
    if ( $term instanceof WP_Term ) {
        $link = get_term_link( $term );
        if ( ! is_wp_error( $link ) ) {
            $area_cards[] = array( 'name' => $term->name, 'copy' => $copy, 'url' => $link );
        }
    }
}

$intent_cards = array();
$add_intent_term = static function ( string $label, string $slug ) use ( &$intent_cards ) {
    $term = get_term_by( 'slug', $slug, 'district' );
    if ( $term instanceof WP_Term ) {
        $link = get_term_link( $term );
        if ( ! is_wp_error( $link ) ) {
            $intent_cards[] = array( 'label' => $label, 'url' => $link );
        }
    }
};
$add_intent_term( 'Central Istanbul living', 'besiktas' );
$add_intent_term( 'Family-friendly areas', 'basaksehir' );
$investment_term = get_term_by( 'slug', 'investment', 'property_tags' );
if ( $investment_term instanceof WP_Term ) {
    $link = get_term_link( $investment_term );
    if ( ! is_wp_error( $link ) ) {
        $intent_cards[] = array( 'label' => 'Rental investment', 'url' => $link );
    }
}
$sea_view_term = get_term_by( 'slug', 'sea-view', 'property_tags' );
if ( $sea_view_term instanceof WP_Term ) {
    $link = get_term_link( $sea_view_term );
    if ( ! is_wp_error( $link ) ) {
        $intent_cards[] = array( 'label' => 'Bosphorus views', 'url' => $link );
    }
}
if ( $property_archive ) {
    $intent_cards[] = array( 'label' => 'Lower entry prices', 'url' => add_query_arg( 'sort', 'price_asc', $property_archive ) );
}
$citizenship_page = get_page_by_path( 'turkish-citizenship-by-investment' );
if ( $citizenship_page instanceof WP_Post && 'publish' === $citizenship_page->post_status ) {
    $intent_cards[] = array( 'label' => 'Turkish citizenship property', 'url' => get_permalink( $citizenship_page ) );
}

?>

<main id="primary" class="site-main property-map-page">
    <div class="container property-map-breadcrumbs" aria-label="Breadcrumb">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a><span>/</span><a href="<?php echo esc_url( $property_archive ); ?>">Property for Sale</a><span>/</span><span>Istanbul Property Map</span>
    </div>

    <section class="property-map-hero section section-soft" id="property-map-hero">
        <div class="container property-map-hero__inner">
            <p class="eyebrow">Pera Property Istanbul</p>
            <h1>Istanbul Property Map</h1>
            <p class="lead">Explore apartments, villas and investment properties for sale across Istanbul. Use the interactive map to compare locations, neighbourhoods and available listings.</p>
            <div class="property-map-hero__actions">
                <a class="btn btn--solid btn--green" href="#property-map-explorer" data-map-track="hero_explore_map">Explore the map</a>
                <a class="btn btn--ghost" href="#property-map-assistance" data-map-track="hero_ask_where_to_buy">Ask us where to buy</a>
            </div>
            <ul class="property-map-trust" aria-label="Trust points">
                <li>Properties across Istanbul</li><li>Local English-speaking agents</li><li>Established in Istanbul since 2016</li>
            </ul>
        </div>
    </section>

    <section class="section" id="property-map-explorer">
        <div class="container">
            <div class="property-map-card content-panel-box">
                <div class="property-map-card__header">
                    <div><p class="eyebrow">Interactive search</p><h2>Explore Istanbul by map</h2></div>
                    <p class="property-map-count" id="property-map-count" aria-live="polite"><?php echo esc_html( sprintf( _n( '%s property shown', '%s properties shown', count( $markers ), 'hello-elementor-child' ), number_format_i18n( count( $markers ) ) ) ); ?></p>
                </div>

                <form class="property-map-filters" id="property-map-filters" aria-label="Filter map properties">
                    <div class="field"><label for="map-filter-district">District</label><select id="map-filter-district" name="district"><option value="">All districts</option><?php foreach ( $district_options as $slug => $name ) : ?><option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label for="map-filter-min-price">Minimum price</label><input id="map-filter-min-price" name="min_price" type="number" inputmode="numeric" min="0" step="50000" placeholder="No min"></div>
                    <div class="field"><label for="map-filter-max-price">Maximum price</label><input id="map-filter-max-price" name="max_price" type="number" inputmode="numeric" min="0" step="50000" placeholder="No max"></div>
                    <div class="field"><label for="map-filter-bedrooms">Bedrooms</label><select id="map-filter-bedrooms" name="bedrooms"><option value="">Any beds</option><?php foreach ( $bed_options as $beds ) : ?><option value="<?php echo esc_attr( (string) $beds ); ?>"><?php echo esc_html( (string) $beds ); ?>+</option><?php endforeach; ?></select></div>
                    <div class="field"><label for="map-filter-type">Property type</label><select id="map-filter-type" name="type"><option value="">All types</option><?php foreach ( $type_options as $slug => $name ) : ?><option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option><?php endforeach; ?></select></div>
                    <button type="reset" class="btn btn--ghost property-map-filters__reset">Reset filters</button>
                </form>
                <p class="no-results property-map-empty" id="property-map-empty" hidden>No properties match these filters. Reset the filters or ask us to help shortlist options.</p>

                <div class="property-map-mobile-toggle" role="group" aria-label="Choose map or list view"><button type="button" class="is-active" data-map-view="map">Map</button><button type="button" data-map-view="list">List</button></div>
                <div class="property-map-layout" data-active-view="map">
                    <div id="property-map" class="property-map__canvas"></div>
                    <script type="application/json" id="property-map-data"><?php echo wp_json_encode( $markers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?></script>
                    <aside class="property-map__selected" aria-live="polite" aria-label="Selected property"><div id="property-map-results" class="cards-grid"><p class="no-results">Click a marker to view the listing.</p></div></aside>
                </div>
            </div>
        </div>
    </section>

    <section class="section property-map-assistance" id="property-map-assistance">
        <div class="container property-map-assistance__inner content-panel-box">
            <div><p class="eyebrow">Assisted search</p><h2>Not sure which part of Istanbul is right for you?</h2><p class="text-soft">Tell us your budget, preferred property type and reason for buying. Our local team will suggest suitable neighbourhoods and properties.</p></div>
            <form class="property-map-assist-form" id="property-map-assist-form" data-whatsapp-url="<?php echo esc_url( $whatsapp_url ); ?>">
                <label>Name<input name="name" type="text" autocomplete="name" required></label><label>WhatsApp number<input name="phone" type="tel" autocomplete="tel" required></label><label>Budget<input name="budget" type="text" placeholder="e.g. $350,000"></label><label>Buying purpose<select name="purpose"><option>Home</option><option>Investment</option><option>Rental income</option><option>Turkish citizenship</option><option>Not sure yet</option></select></label><label>Preferred area, optional<input name="area" type="text" placeholder="e.g. Kadıköy, Şişli or not sure"></label><button class="btn btn--solid btn--green" type="submit" data-map-track="assisted_search_submit">Get personalised recommendations</button>
                <p class="text-xs text-soft">Submitting opens WhatsApp with your requirements so our team can respond directly.</p>
            </form>
        </div>
    </section>

    <?php if ( ! empty( $area_cards ) ) : ?><section class="section property-map-areas"><div class="container"><h2>Browse property by area</h2><div class="property-map-card-grid"><?php foreach ( $area_cards as $card ) : ?><article class="content-panel-box"><h3><?php echo esc_html( $card['name'] ); ?></h3><p class="text-soft"><?php echo esc_html( $card['copy'] ); ?></p><a href="<?php echo esc_url( $card['url'] ); ?>">View properties in <?php echo esc_html( $card['name'] ); ?></a></article><?php endforeach; ?></div></div></section><?php endif; ?>

    <?php if ( ! empty( $intent_cards ) ) : ?><section class="section section-soft"><div class="container property-map-editorial"><h2>Where should you buy in Istanbul?</h2><div class="property-map-card-grid"><?php foreach ( $intent_cards as $intent_card ) : ?><a class="content-panel-box" href="<?php echo esc_url( $intent_card['url'] ); ?>"><?php echo esc_html( $intent_card['label'] ); ?></a><?php endforeach; ?></div></div></section><?php endif; ?>

    <section class="section"><div class="container property-map-two-col"><div><h2>Buying property in Istanbul</h2><p class="text-soft">Location, transport access and resale demand matter as much as the development itself. Before buying, checks should cover title deed status, planning, debts, valuation and any location-specific restrictions for foreign ownership.</p><p class="text-soft">Foreign buyers can generally purchase property in Turkey, subject to legal and location restrictions. Citizenship eligibility requires separate legal and valuation checks. Pera Property can help shortlist, inspect and compare suitable options before you travel.</p></div><div><h2>Why buy with Pera Property?</h2><ul class="property-map-checks"><li>Istanbul-based team</li><li>Operating since 2016</li><li>Access to properties from multiple developers and owners</li><li>Legal and due-diligence coordination</li><li>After-sales and rental-management support</li><li>Experience assisting international buyers</li></ul></div></div></section>

    <section class="section property-map-faq"><div class="container"><h2>Frequently asked questions</h2><?php foreach ( pera_property_map_faq_items() as $item ) : ?><details class="content-panel-box"><summary><?php echo esc_html( $item['q'] ); ?></summary><p class="text-soft"><?php echo esc_html( $item['a'] ); ?></p></details><?php endforeach; ?></div></section>

    <section class="section property-map-final"><div class="container content-panel-box"><h2>Let us help you shortlist the right properties</h2><p class="text-soft">Share your budget and requirements, and we will prepare a focused selection before your viewing trip to Istanbul.</p><div class="property-map-hero__actions"><a class="btn btn--solid btn--green" href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener" data-whatsapp="1" data-whatsapp-type="property_map_final" data-track-channel="whatsapp" data-track-intent="high" data-track-source="page" data-track-context="property_map_final" data-track-ga4-event="whatsapp_click" data-track-crm-event="whatsapp_click" data-map-track="final_whatsapp">Message us on WhatsApp</a><a class="btn btn--ghost" href="#property-map-assistance" data-map-track="final_shortlist">Request a property shortlist</a></div></div></section>
</main>

<?php get_footer(); ?>
