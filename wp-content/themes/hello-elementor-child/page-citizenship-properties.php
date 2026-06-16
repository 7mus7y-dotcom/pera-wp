<?php
/**
 * Template Name: Turkish Citizenship Properties
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_enqueue_style(
	'pera-property-css',
	get_stylesheet_directory_uri() . '/css/property.css',
	array( 'pera-main-css' ),
	function_exists( 'pera_get_asset_version' ) ? pera_get_asset_version( '/css/property.css' ) : null
);

wp_enqueue_style(
	'pera-slider-css',
	get_stylesheet_directory_uri() . '/css/slider.css',
	array( 'pera-main-css' ),
	function_exists( 'pera_get_asset_version' ) ? pera_get_asset_version( '/css/slider.css' ) : null
);

wp_enqueue_style(
	'pera-posts-css',
	get_stylesheet_directory_uri() . '/css/posts.css',
	array( 'pera-main-css', 'pera-card-typography', 'pera-slider-css' ),
	function_exists( 'pera_get_asset_version' ) ? pera_get_asset_version( '/css/posts.css' ) : null
);

wp_enqueue_style(
	'pera-leaflet',
	get_stylesheet_directory_uri() . '/vendor/leaflet/leaflet.css',
	array(),
	'1.9.4'
);
wp_enqueue_script(
	'pera-leaflet',
	get_stylesheet_directory_uri() . '/vendor/leaflet/leaflet.js',
	array(),
	'1.9.4',
	false
);

get_header();

if ( ! function_exists( 'pera_render_property_pagination' ) ) {
	$property_pagination_path = get_stylesheet_directory() . '/inc/property-pagination.php';
	if ( file_exists( $property_pagination_path ) ) {
		require_once $property_pagination_path;
	}
}

$requested_view     = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( (string) $_GET['view'] ) ) : '';
$has_requested_view = in_array( $requested_view, array( 'cards', 'map' ), true );
$initial_view       = 'map' === $requested_view ? 'map' : 'cards';
$requested_sort     = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( (string) $_GET['sort'] ) ) : 'date_desc';
$selected_sort      = function_exists( 'pera_latest_offers_normalize_sort' )
	? pera_latest_offers_normalize_sort( $requested_sort )
	: ( in_array( $requested_sort, array( 'date_desc', 'date_asc', 'price_asc', 'price_desc' ), true ) ? $requested_sort : 'date_desc' );
$paged              = max(
	1,
	(int) get_query_var( 'paged' ),
	isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1
);

$card_page = function_exists( 'pera_latest_offers_collect_paginated_cards' )
	? pera_latest_offers_collect_paginated_cards(
		12,
		$paged,
		function_exists( 'pera_latest_offers_citizenship_query_args' ) ? pera_latest_offers_citizenship_query_args() : array(),
		$selected_sort,
		11
	)
	: array(
		'cards'       => array(),
		'total_cards' => 0,
		'total_pages' => 0,
	);

$cards       = isset( $card_page['cards'] ) && is_array( $card_page['cards'] ) ? $card_page['cards'] : array();
$marker_cards = isset( $card_page['all_cards'] ) && is_array( $card_page['all_cards'] ) ? $card_page['all_cards'] : $cards;
$total_pages = isset( $card_page['total_pages'] ) ? max( 0, (int) $card_page['total_pages'] ) : 0;

$pagination_query                = new WP_Query();
$pagination_query->max_num_pages = $total_pages;

$pagination_html = function_exists( 'pera_render_property_pagination' )
	? pera_render_property_pagination(
		$pagination_query,
		$paged,
		array_filter(
			array(
			'view' => 'map' === $initial_view ? 'map' : '',
			'sort' => 'date_desc' !== $selected_sort ? $selected_sort : '',
			)
		),
		get_permalink()
	)
	: '';

$map_markers = function_exists( 'pera_latest_offers_marker_dtos_from_cards' )
	? pera_latest_offers_marker_dtos_from_cards( $marker_cards )
	: array();

$map_json           = wp_json_encode( array_values( $map_markers ) );
if ( ! is_string( $map_json ) ) {
	$map_json = '[]';
}

$hero_title     = __( 'Turkish Citizenship Properties in Istanbul', 'hello-elementor-child' );
$hero_desc_html = '<p class="text-light">' . esc_html__( 'Browse selected Istanbul properties suitable for Turkish citizenship by investment. All listings on this page are selected for the citizenship route and reviewed for price, location, title deed status, valuation logic and resale potential before recommendation.', 'hello-elementor-child' ) . '</p>';
?>

<main id="primary" class="site-main">
	<style>
		.pera-citizenship-properties .citizenship-properties-toolbar{display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin:0 0 16px;}
		.pera-citizenship-properties .citizenship-properties-view-toggle{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:0;}
		.pera-citizenship-properties .citizenship-view-btn{min-width:120px;}
		.citizenship-hero-trust-strip{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:18px;color:#fff;font-size:14px;font-weight:700;letter-spacing:.01em;}
		.citizenship-hero-trust-strip span{display:inline-flex;align-items:center;gap:8px;}
		.citizenship-hero-trust-strip span:not(:last-child)::after{content:"•";opacity:.7;}
		.pera-citizenship-properties .citizenship-properties-intro-panel{margin:0 0 18px;}
		.pera-citizenship-properties .citizenship-properties-check-grid{margin:0 0 18px;}
		.pera-citizenship-properties .citizenship-map-debug{margin:0 0 16px;padding:10px 12px;border:1px solid #d9deea;border-radius:10px;background:#f8faff;font:12px/1.45 ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,Liberation Mono,monospace;color:#1e293b;white-space:pre-wrap;}
		#citizenship-properties-map-panel[hidden],
		#citizenship-properties-cards-panel[hidden],
		#citizenship-properties-map-empty[hidden]{display:none !important;}
		#citizenship-properties-map-panel{background:#fff;border:1px solid #e4e8ef;border-radius:16px;padding:14px;}
		#citizenship-properties-map-canvas{height:600px;border-radius:12px;overflow:hidden;background:#f4f6fb;}
		#citizenship-properties-map-empty{margin:0;padding:12px 0;color:#4c5565;}
		.leaflet-popup-content{margin:8px 10px;}
		.citizenship-map-popup{max-width:260px;display:grid;gap:6px;}
		.citizenship-map-popup .pera-latest-offer-card__pills{gap:4px;margin:0;}
		.citizenship-map-popup .pera-latest-offer-card__title{margin:0;line-height:1.25;}
		.citizenship-map-popup .pera-latest-offer-card__summary{padding:4px 6px;gap:2px;}
		.citizenship-map-popup .pera-latest-offer-card__summary p,
		.citizenship-map-popup .pera-latest-offer-card__meta{margin:0;}
		.citizenship-map-popup .pera-latest-offer-card__img{display:block;width:100%;height:110px;object-fit:cover;border-radius:8px;margin:0;}
		.citizenship-map-popup .pera-latest-offer-card__utility{gap:4px;margin-top:2px;}
		@media (max-width: 767px){
			#citizenship-properties-map-canvas{height:460px;}
		}
	</style>
	<section class="hero hero--left property-archive-hero">
		<?php
		$term = get_queried_object();
		$term_id = ( isset( $term->term_id ) ) ? (int) $term->term_id : 0;
		$acf_ref = ( $term_id && ! empty( $term->taxonomy ) ) ? ( $term->taxonomy . '_' . $term_id ) : '';

		$district_image = ( function_exists( 'get_field' ) && $acf_ref )
			? get_field( 'district_image', $acf_ref )
			: null;

		$district_img_id = 0;
		if ( is_array( $district_image ) && ! empty( $district_image['ID'] ) ) {
			$district_img_id = (int) $district_image['ID'];
		} elseif ( is_numeric( $district_image ) ) {
			$district_img_id = (int) $district_image;
		}

		$fallback_img_id = 55482;
		$hero_img_id     = $district_img_id ?: $fallback_img_id;
		?>

		<?php if ( $hero_img_id ) : ?>
			<div class="hero__media" aria-hidden="true">
				<?php
				echo wp_get_attachment_image(
					$hero_img_id,
					'full',
					false,
					array(
						'class'         => 'hero-media',
						'loading'       => 'eager',
						'decoding'      => 'async',
						'fetchpriority' => 'high',
					)
				);
				?>
				<div class="hero-overlay" aria-hidden="true"></div>
			</div>
		<?php endif; ?>

		<div class="hero-content">
			<h1><?php echo esc_html( $hero_title ); ?></h1>
			<?php if ( '' !== $hero_desc_html ) : ?>
				<?php echo $hero_desc_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
			<div class="citizenship-hero-trust-strip" aria-label="<?php esc_attr_e( 'Citizenship property highlights', 'hello-elementor-child' ); ?>">
				<span><?php esc_html_e( '$400,000+ citizenship route', 'hello-elementor-child' ); ?></span>
				<span><?php esc_html_e( 'Istanbul-focused shortlist', 'hello-elementor-child' ); ?></span>
				<span><?php esc_html_e( 'Eligibility checked before reservation', 'hello-elementor-child' ); ?></span>
				<span><?php esc_html_e( 'Family application support', 'hello-elementor-child' ); ?></span>
			</div>
		</div>
	</section>

	<section class="section content-panel content-panel--overlap-hero pera-citizenship-properties">
		<div class="container">
			<div class="content-panel-box citizenship-properties-intro-panel">
				<div class="section-header">
					<p class="u-eyebrow"><?php esc_html_e( 'Citizenship property shortlist', 'hello-elementor-child' ); ?></p>
					<h2><?php esc_html_e( 'Need help choosing the right citizenship property?', 'hello-elementor-child' ); ?></h2>
					<p><?php esc_html_e( 'Every property listed here is selected for Turkish citizenship buyers, but the best option depends on your budget, family plans, preferred location and exit strategy. Tell us what you are looking for and we will prepare a focused shortlist.', 'hello-elementor-child' ); ?></p>
				</div>
				<div class="hero-actions">
					<a href="<?php echo esc_url( 'https://www.peraproperty.com/citizenship-by-investment/#citizenship-callback' ); ?>" class="btn btn--solid btn--green"><?php esc_html_e( 'Get a citizenship-safe property shortlist', 'hello-elementor-child' ); ?></a>
					<a href="<?php echo esc_url( 'https://wa.me/905320639978?text=Hello%20Pera%20Property%2C%20I%27m%20interested%20in%20Turkish%20citizenship%20properties.%20Can%20you%20send%20me%20a%20shortlist%3F' ); ?>" class="btn btn--solid btn--blue" target="_blank" rel="noopener" data-whatsapp="1" data-whatsapp-type="citizenship_properties_page" data-track-channel="whatsapp" data-track-intent="high" data-track-source="page" data-track-context="citizenship_properties_intro" data-track-ga4-event="whatsapp_click" data-track-crm-event="whatsapp_click"><?php esc_html_e( 'WhatsApp our citizenship team', 'hello-elementor-child' ); ?></a>
					<a href="<?php echo esc_url( 'https://www.peraproperty.com/citizenship-by-investment/' ); ?>" class="btn btn--ghost btn--black"><?php esc_html_e( 'Read the Turkish citizenship guide', 'hello-elementor-child' ); ?></a>
				</div>
			</div>

			<div class="feature-grid feature-grid--tablet-3 citizenship-properties-check-grid">
				<article class="feature-card">
					<div class="feature-card-header">
						<h3><?php esc_html_e( 'Citizenship-ready budget', 'hello-elementor-child' ); ?></h3>
					</div>
					<div class="feature-card-body">
						<p><?php esc_html_e( 'Listings are selected around the Turkish citizenship property route and the USD 400,000+ investment requirement.', 'hello-elementor-child' ); ?></p>
					</div>
				</article>
				<article class="feature-card">
					<div class="feature-card-header">
						<h3><?php esc_html_e( 'Eligibility reviewed', 'hello-elementor-child' ); ?></h3>
					</div>
					<div class="feature-card-body">
						<p><?php esc_html_e( 'Before recommendation, we check title deed status, seller suitability, valuation logic and payment-route requirements.', 'hello-elementor-child' ); ?></p>
					</div>
				</article>
				<article class="feature-card">
					<div class="feature-card-header">
						<h3><?php esc_html_e( 'Exit and rental logic', 'hello-elementor-child' ); ?></h3>
					</div>
					<div class="feature-card-body">
						<p><?php esc_html_e( 'We prioritise Istanbul properties with practical resale potential, rental demand and long-term ownership logic.', 'hello-elementor-child' ); ?></p>
					</div>
				</article>
			</div>

			<div class="content-panel-box citizenship-properties-process-panel">
				<div class="section-header">
					<p class="u-eyebrow"><?php esc_html_e( 'Before we recommend a citizenship property', 'hello-elementor-child' ); ?></p>
					<h2><?php esc_html_e( 'How we check citizenship property suitability', 'hello-elementor-child' ); ?></h2>
					<p><?php esc_html_e( 'A property can look suitable on price alone, but citizenship buyers need more than a listing. Before we recommend an option, we check the legal, valuation and exit-risk details that affect the application.', 'hello-elementor-child' ); ?></p>
				</div>
				<ul class="checklist">
					<li><?php esc_html_e( 'Title deed status and seller suitability', 'hello-elementor-child' ); ?></li>
					<li><?php esc_html_e( 'Whether the property can support the Turkish citizenship route', 'hello-elementor-child' ); ?></li>
					<li><?php esc_html_e( 'Valuation logic against the declared sale price', 'hello-elementor-child' ); ?></li>
					<li><?php esc_html_e( 'Payment route, bank transfer and DAB requirements', 'hello-elementor-child' ); ?></li>
					<li><?php esc_html_e( 'Rental and resale potential after the holding period', 'hello-elementor-child' ); ?></li>
					<li><?php esc_html_e( 'Whether the property fits your family application plan', 'hello-elementor-child' ); ?></li>
				</ul>
				<p class="content-note"><strong><?php esc_html_e( 'Important:', 'hello-elementor-child' ); ?></strong> <?php esc_html_e( 'The listings below are examples of properties we currently consider suitable for citizenship buyers. Final suitability depends on availability, title deed status, valuation, payment route and your personal application structure.', 'hello-elementor-child' ); ?></p>
			</div>

			<div class="citizenship-properties-toolbar">
				<div class="citizenship-properties-view-toggle" role="group" aria-label="<?php esc_attr_e( 'Property view mode', 'hello-elementor-child' ); ?>">
					<button
						type="button"
						class="btn btn--solid btn--black citizenship-view-btn"
						data-citizenship-view="cards"
						aria-pressed="<?php echo 'cards' === $initial_view ? 'true' : 'false'; ?>"
					>
						<?php esc_html_e( 'Cards', 'hello-elementor-child' ); ?>
					</button>
					<button
						type="button"
						class="btn btn--solid btn--black citizenship-view-btn"
						data-citizenship-view="map"
						aria-pressed="<?php echo 'map' === $initial_view ? 'true' : 'false'; ?>"
					>
						<?php esc_html_e( 'Map', 'hello-elementor-child' ); ?>
					</button>
				</div>

				<div class="property-sort" data-sort-menu>
					<input type="hidden" name="view" value="<?php echo esc_attr( $initial_view ); ?>" data-citizenship-sort-view>
					<input type="hidden" name="sort" id="sort-input" value="<?php echo esc_attr( $selected_sort ); ?>">
					<button
						type="button"
						class="btn btn--solid btn--black property-sort__trigger"
						aria-haspopup="menu"
						aria-expanded="false"
						aria-controls="property-sort-menu"
						data-sort-trigger
					>
						<svg class="icon" aria-hidden="true" width="18" height="18">
							<use href="#icon-sort" xlink:href="#icon-sort"></use>
						</svg>
						<?php esc_html_e( 'Sort', 'hello-elementor-child' ); ?>
					</button>
					<div class="property-sort__menu" id="property-sort-menu" role="menu" data-sort-dropdown hidden>
						<?php
						$citizenship_sort_options = array(
							'date_desc'  => __( 'Newest', 'hello-elementor-child' ),
							'date_asc'   => __( 'Oldest', 'hello-elementor-child' ),
							'price_asc'  => __( 'Price ↑', 'hello-elementor-child' ),
							'price_desc' => __( 'Price ↓', 'hello-elementor-child' ),
						);
						foreach ( $citizenship_sort_options as $sort_value => $sort_label ) :
							$is_active = $selected_sort === $sort_value;
							?>
							<button
								type="button"
								class="property-sort__option <?php echo $is_active ? 'is-active' : ''; ?>"
								role="menuitemradio"
								aria-checked="<?php echo $is_active ? 'true' : 'false'; ?>"
								data-sort-option
								data-sort="<?php echo esc_attr( $sort_value ); ?>"
							>
								<?php echo esc_html( $sort_label ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<?php if ( ! empty( $cards ) ) : ?>
				<div id="citizenship-properties-cards-panel" <?php echo 'map' === $initial_view ? 'hidden' : ''; ?>>
					<div class="pera-latest-offers-card-list pera-latest-offers-card-list--grid-4">
						<?php
						$citizenship_cta_position = min( 7, count( $cards ) );
						$rendered_cards            = 0;
						foreach ( $cards as $card ) :
							if ( is_array( $card ) ) {
								$card['tracking_context'] = 'citizenship_property_card';
							}
							pera_latest_offers_render_card( $card );
							$rendered_cards++;
							if ( 1 === $paged && $rendered_cards === $citizenship_cta_position && function_exists( 'pera_latest_offers_render_citizenship_mid_list_cta' ) ) {
								pera_latest_offers_render_citizenship_mid_list_cta();
							}
						endforeach;
						?>
					</div>

					<div class="flex-center mt-sm mb-sm">
						<nav
							class="property-pagination <?php echo $pagination_html !== '' ? '' : 'is-hidden'; ?>"
							aria-label="<?php esc_attr_e( 'Citizenship property results pages', 'hello-elementor-child' ); ?>"
						>
							<?php if ( $pagination_html !== '' ) : ?>
								<?php echo $pagination_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endif; ?>
						</nav>

					</div>
				</div>

				<div id="citizenship-properties-map-panel" <?php echo 'cards' === $initial_view ? 'hidden' : ''; ?>>
					<div id="citizenship-properties-map-canvas" aria-label="<?php esc_attr_e( 'Property map', 'hello-elementor-child' ); ?>"></div>
					<p id="citizenship-properties-map-empty" hidden>
						<?php esc_html_e( 'No mappable citizenship properties are available right now.', 'hello-elementor-child' ); ?>
					</p>
						<script type="application/json" id="citizenship-properties-map-data"><?php echo $map_json; ?></script>
					</div>
			<?php else : ?>
				<p><?php esc_html_e( 'No citizenship-tagged property offers are available right now. Please check back soon.', 'hello-elementor-child' ); ?></p>
			<?php endif; ?>
		</div>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<pre id="citizenship-properties-map-debug" class="citizenship-map-debug" aria-live="polite"><?php echo esc_html__( 'Map debug panel: waiting for runtime state…', 'hello-elementor-child' ); ?></pre>
			<?php endif; ?>
	</section>

	<section id="citizenship-property-faq" class="section section-soft citizenship-properties-faq">
		<div class="container">
			<div class="section-header">
				<p class="u-eyebrow"><?php esc_html_e( 'Citizenship property FAQ', 'hello-elementor-child' ); ?></p>
				<h2><?php esc_html_e( 'Common questions before choosing a citizenship property', 'hello-elementor-child' ); ?></h2>
				<p><?php esc_html_e( 'These are the questions most buyers ask before reserving a property for Turkish citizenship by investment.', 'hello-elementor-child' ); ?></p>
			</div>

			<div class="feature-grid">
				<article class="feature-card">
					<div class="feature-card-header">
						<h3><?php esc_html_e( 'Are all properties on this page suitable for citizenship?', 'hello-elementor-child' ); ?></h3>
					</div>
					<div class="feature-card-body">
						<p><?php esc_html_e( 'They are selected for citizenship buyers, but final suitability must be confirmed before reservation. Availability, title deed status, valuation and payment route all need to be checked.', 'hello-elementor-child' ); ?></p>
					</div>
				</article>
				<article class="feature-card">
					<div class="feature-card-header">
						<h3><?php esc_html_e( 'Can I buy more than one property to reach $400,000?', 'hello-elementor-child' ); ?></h3>
					</div>
					<div class="feature-card-body">
						<p><?php esc_html_e( 'Yes, buyers can often combine more than one eligible property, provided the total qualifying value and legal process meet the citizenship requirements.', 'hello-elementor-child' ); ?></p>
					</div>
				</article>
				<article class="feature-card">
					<div class="feature-card-header">
						<h3><?php esc_html_e( 'How do you check valuation report risk?', 'hello-elementor-child' ); ?></h3>
					</div>
					<div class="feature-card-body">
						<p><?php esc_html_e( 'We look at comparable sales, the declared purchase price, project pricing, district liquidity and whether the property value is likely to support the citizenship file.', 'hello-elementor-child' ); ?></p>
					</div>
				</article>
				<article class="feature-card">
					<div class="feature-card-header">
						<h3><?php esc_html_e( 'Can my family be included in the application?', 'hello-elementor-child' ); ?></h3>
					</div>
					<div class="feature-card-body">
						<p><?php esc_html_e( 'In most standard cases, the main applicant can include a spouse and children under 18. Family structure should be reviewed before choosing the property route.', 'hello-elementor-child' ); ?></p>
					</div>
				</article>
			</div>

			<div class="section-cta">
				<a href="<?php echo esc_url( 'https://www.peraproperty.com/citizenship-by-investment/' ); ?>" class="btn btn--solid btn--blue"><?php esc_html_e( 'Read the full citizenship guide', 'hello-elementor-child' ); ?></a>
				<a href="<?php echo esc_url( 'https://www.peraproperty.com/citizenship-by-investment/#citizenship-callback' ); ?>" class="btn btn--solid btn--green"><?php esc_html_e( 'Request a private shortlist', 'hello-elementor-child' ); ?></a>
			</div>
		</div>
	</section>

	<?php get_template_part( 'parts/citizenship-guide-posts' ); ?>
</main>

<script>
	(function () {
		var root = document.querySelector('.pera-citizenship-properties');
		if (!root) return;

		var cardsPanel = document.getElementById('citizenship-properties-cards-panel');
		var mapPanel = document.getElementById('citizenship-properties-map-panel');
		var mapCanvas = document.getElementById('citizenship-properties-map-canvas');
		var mapEmpty = document.getElementById('citizenship-properties-map-empty');
		var jsonEl = document.getElementById('citizenship-properties-map-data');
		var cardsList = root.querySelector('.pera-latest-offers-card-list');
		var paginationNav = root.querySelector('.property-pagination');
		var buttons = Array.prototype.slice.call(root.querySelectorAll('[data-citizenship-view]'));
		var sortMenu = root.querySelector('[data-sort-menu]');
		var sortTrigger = sortMenu ? sortMenu.querySelector('[data-sort-trigger]') : null;
		var sortDropdown = sortMenu ? sortMenu.querySelector('[data-sort-dropdown]') : null;
		var sortOptions = sortMenu ? Array.prototype.slice.call(sortMenu.querySelectorAll('[data-sort-option]')) : [];
		var sortInput = document.getElementById('sort-input');
		var sortViewInput = root.querySelector('[data-citizenship-sort-view]');
		var storageKey = 'peraCitizenshipPropertiesView';
		var mapInstance = null;
		var mapBooted = false;
		var markers = [];
		var mapBounds = [];
		var leafletMarkers = [];
		var hasRenderableMarkers = false;
		var hasMapError = false;
		var lastErrorMessage = '';
		var parseSucceeded = false;
		var currentView = 'cards';
		var debugEl = document.getElementById('citizenship-properties-map-debug');
		var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
		var ajaxNonce = '<?php echo esc_js( wp_create_nonce( 'pera_citizenship_latest_offers' ) ); ?>';

		if (!cardsPanel || !mapPanel || !buttons.length) return;

		try {
			markers = jsonEl ? JSON.parse(jsonEl.textContent || '[]') : [];
			if (!Array.isArray(markers)) markers = [];
			parseSucceeded = true;
		} catch (e) {
			markers = [];
			parseSucceeded = false;
		}

		function setDebugState(stage) {
			if (!debugEl) return;
			var lines = [
				'stage: ' + String(stage || ''),
				'markers.length: ' + String(Array.isArray(markers) ? markers.length : 0),
				'json.parse.succeeded: ' + (parseSucceeded ? 'true' : 'false'),
				'window.L.exists: ' + (window.L ? 'true' : 'false'),
				'hasMapError: ' + (hasMapError ? 'true' : 'false'),
				'typeof window.L: ' + String(typeof window.L),
				'typeof window.L.map: ' + String(window.L ? typeof window.L.map : 'undefined'),
				'typeof window.L.tileLayer: ' + String(window.L ? typeof window.L.tileLayer : 'undefined'),
				'typeof window.L.marker: ' + String(window.L ? typeof window.L.marker : 'undefined'),
				'mapBooted: ' + (mapBooted ? 'true' : 'false'),
				'mapInstance.exists: ' + (mapInstance ? 'true' : 'false'),
				'mapBounds.length: ' + String(Array.isArray(mapBounds) ? mapBounds.length : 0),
				'hasRenderableMarkers: ' + (hasRenderableMarkers ? 'true' : 'false'),
				'mapCanvas.clientWidth: ' + (mapCanvas ? String(mapCanvas.clientWidth) : 'n/a'),
				'mapCanvas.clientHeight: ' + (mapCanvas ? String(mapCanvas.clientHeight) : 'n/a'),
				'lastErrorMessage: ' + String(lastErrorMessage || ''),
				'mapCanvas.hidden: ' + (mapCanvas ? String(!!mapCanvas.hidden) : 'n/a'),
				'mapEmpty.hidden: ' + (mapEmpty ? String(!!mapEmpty.hidden) : 'n/a'),
				'currentView: ' + String(currentView || '')
			];
			debugEl.textContent = lines.join('\n');
		}
		setDebugState('after_parse');

		function keyForLatLng(lat, lng) {
			return lat.toFixed(6) + ',' + lng.toFixed(6);
		}

		function offsetLatLng(lat, lng, duplicateIndex) {
			if (duplicateIndex <= 0) return [lat, lng];
			var radius = 0.00003 * duplicateIndex;
			var angle = (duplicateIndex * 137.508) * (Math.PI / 180);
			return [
				lat + (Math.sin(angle) * radius),
				lng + (Math.cos(angle) * radius)
			];
		}

		function initMapIfNeeded() {
			if (mapBooted || hasMapError) return;
			mapBooted = true;
			try {
				if (!window.L || !mapCanvas) {
					hasMapError = true;
					showView('cards', false);
					return;
				}

				setDebugState('before_map_create');
				mapInstance = window.L.map(mapCanvas, {
					scrollWheelZoom: false
				});
				setDebugState('after_map_create');

				window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
					maxZoom: 19,
					attribution: '&copy; OpenStreetMap contributors'
				}).addTo(mapInstance);
				setDebugState('after_tile_layer');

				mapBounds = [];
				leafletMarkers = [];
				var duplicateCounts = Object.create(null);
				markers.forEach(function (item) {
					var lat = Number(item && item.lat);
					var lng = Number(item && item.lng);
					if (!isFinite(lat) || !isFinite(lng)) return;
					var duplicateKey = keyForLatLng(lat, lng);
					var duplicateIndex = Number(duplicateCounts[duplicateKey] || 0);
					duplicateCounts[duplicateKey] = duplicateIndex + 1;
					var adjusted = offsetLatLng(lat, lng, duplicateIndex);
					var marker = window.L.marker(adjusted).addTo(mapInstance);
					marker.bindPopup(String(item && item.popup_html ? item.popup_html : ''));
					leafletMarkers.push(marker);
					mapBounds.push(adjusted);
				});

				hasRenderableMarkers = mapBounds.length > 0;
				setDebugState('after_initMapIfNeeded');
			} catch (e) {
				hasMapError = true;
				lastErrorMessage = e && e.message ? String(e.message) : String(e);
				setDebugState('init_exception');
				showView('cards', false);
			}
		}

		function rebuildMapMarkers(nextMarkers) {
			markers = Array.isArray(nextMarkers) ? nextMarkers : [];
			parseSucceeded = true;
			mapBounds = [];
			hasRenderableMarkers = false;

			if (!mapInstance || !window.L) {
				mapBooted = false;
				setDebugState('after_marker_data_update');
				return;
			}

			leafletMarkers.forEach(function (marker) {
				try { mapInstance.removeLayer(marker); } catch (e) {}
			});
			leafletMarkers = [];

			var duplicateCounts = Object.create(null);
			markers.forEach(function (item) {
				var lat = Number(item && item.lat);
				var lng = Number(item && item.lng);
				if (!isFinite(lat) || !isFinite(lng)) return;
				var duplicateKey = keyForLatLng(lat, lng);
				var duplicateIndex = Number(duplicateCounts[duplicateKey] || 0);
				duplicateCounts[duplicateKey] = duplicateIndex + 1;
				var adjusted = offsetLatLng(lat, lng, duplicateIndex);
				var marker = window.L.marker(adjusted).addTo(mapInstance);
				marker.bindPopup(String(item && item.popup_html ? item.popup_html : ''));
				leafletMarkers.push(marker);
				mapBounds.push(adjusted);
			});

			hasRenderableMarkers = mapBounds.length > 0;
			syncMapVisibility();
			if (currentView === 'map') {
				window.requestAnimationFrame(function () {
					setTimeout(refreshMapLayout, 30);
				});
			}
			setDebugState('after_rebuild_markers');
		}

		function syncMapVisibility() {
			if (mapEmpty) mapEmpty.hidden = hasRenderableMarkers;
			if (mapCanvas) mapCanvas.hidden = !hasRenderableMarkers;
		}

		function refreshMapLayout() {
			if (!mapInstance || !hasRenderableMarkers) return;
			mapInstance.invalidateSize();
			mapInstance.fitBounds(mapBounds, {padding: [36, 36]});
		}

		function setUrlView(view) {
			if (!window.history || !window.history.replaceState) return;
			var url = new URL(window.location.href);
			if (view === 'map') {
				url.searchParams.set('view', 'map');
			} else {
				url.searchParams.delete('view');
			}
			window.history.replaceState({}, '', url.toString());
		}

		function setSortViewInput(view) {
			if (!sortViewInput) return;
			sortViewInput.value = view === 'map' ? 'map' : 'cards';
		}

		function setButtons(view) {
			buttons.forEach(function (button) {
				var isActive = button.getAttribute('data-citizenship-view') === view;
				button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
				button.classList.toggle('btn--green', isActive);
				button.classList.toggle('btn--black', !isActive);
			});
		}

		function showView(view, persist) {
			var nextView = view === 'map' ? 'map' : 'cards';
			currentView = nextView;
			cardsPanel.hidden = nextView !== 'cards';
			mapPanel.hidden = nextView !== 'map';
			setButtons(nextView);
			setSortViewInput(nextView);

			if (nextView === 'map') {
				initMapIfNeeded();
				syncMapVisibility();
				if (mapInstance && hasRenderableMarkers) {
					window.requestAnimationFrame(function () {
						setTimeout(refreshMapLayout, 30);
					});
				}
			}

			if (persist !== false) {
				try { window.localStorage.setItem(storageKey, nextView); } catch (e) {}
				setUrlView(nextView);
			}
			setDebugState('after_showView');
		}

		buttons.forEach(function (button) {
			button.addEventListener('click', function () {
				showView(button.getAttribute('data-citizenship-view') || 'cards', true);
			});
		});

		function bindSortMenu() {
			if (!sortMenu || !sortTrigger || !sortDropdown || !sortOptions.length || !sortInput) return;

			function getSortValue() {
				return String(sortInput.value || 'date_desc').trim() || 'date_desc';
			}

			function paintSortOptions() {
				var current = getSortValue();
				sortOptions.forEach(function (button) {
					var isActive = String(button.getAttribute('data-sort') || '').trim() === current;
					button.classList.toggle('is-active', isActive);
					button.setAttribute('aria-checked', isActive ? 'true' : 'false');
				});
			}

			function setSortMenuOpen(isOpen) {
				sortMenu.classList.toggle('is-open', isOpen);
				sortTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
				sortDropdown.hidden = !isOpen;
				if (isOpen) {
					var active = sortOptions.filter(function (button) { return button.classList.contains('is-active'); })[0] || sortOptions[0];
					if (active) active.focus();
				}
			}

			function buildSortUrl(sort) {
				var url = new URL('<?php echo esc_js( get_permalink() ); ?>');
				if (currentView === 'map') {
					url.searchParams.set('view', 'map');
				}
				if (sort && sort !== 'date_desc') {
					url.searchParams.set('sort', sort);
				}
				return url;
			}

			function refreshSort(sort) {
				var url = buildSortUrl(sort);
				if (!window.fetch || !cardsList || !paginationNav) {
					window.location.href = url.toString();
					return;
				}

				root.classList.add('is-loading');
				var body = new URLSearchParams();
				body.set('action', 'pera_citizenship_latest_offers');
				body.set('nonce', ajaxNonce);
				body.set('sort', sort || 'date_desc');
				body.set('view', currentView === 'map' ? 'map' : 'cards');

				fetch(ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
					body: body.toString()
				})
					.then(function (response) {
						if (!response.ok) throw new Error('AJAX response was not OK');
						return response.json();
					})
					.then(function (json) {
						if (!json || !json.success || !json.data) throw new Error('AJAX response was unsuccessful');
						cardsList.innerHTML = String(json.data.cards_html || '');
						paginationNav.innerHTML = String(json.data.pagination_html || '');
						paginationNav.classList.toggle('is-hidden', !String(json.data.pagination_html || ''));
						if (jsonEl) jsonEl.textContent = JSON.stringify(json.data.markers || []);
						rebuildMapMarkers(json.data.markers || []);
						if (window.history && window.history.pushState) {
							window.history.pushState({}, '', url.toString());
						}
					})
					.catch(function () {
						window.location.href = url.toString();
					})
					.finally(function () {
						root.classList.remove('is-loading');
					});
			}

			sortTrigger.addEventListener('click', function (event) {
				event.preventDefault();
				setSortMenuOpen(!sortMenu.classList.contains('is-open'));
			});

			sortOptions.forEach(function (button) {
				button.addEventListener('click', function (event) {
					event.preventDefault();
					var sort = String(button.getAttribute('data-sort') || '').trim();
					if (!sort) return;
					sortInput.value = sort;
					paintSortOptions();
					setSortMenuOpen(false);
					refreshSort(sort);
				});
			});

			document.addEventListener('click', function (event) {
				if (!sortMenu.classList.contains('is-open')) return;
				if (!sortMenu.contains(event.target)) setSortMenuOpen(false);
			});

			document.addEventListener('keydown', function (event) {
				if (!sortMenu.classList.contains('is-open')) return;
				if (event.key === 'Escape') {
					event.preventDefault();
					setSortMenuOpen(false);
					sortTrigger.focus();
				}
			});

			paintSortOptions();
		}
		bindSortMenu();

		var initial = '<?php echo esc_js( $initial_view ); ?>';
		var hasRequestedView = <?php echo $has_requested_view ? 'true' : 'false'; ?>;

		try {
			if (!hasRequestedView) {
				var stored = window.localStorage.getItem(storageKey);
				if (stored === 'map' || stored === 'cards') initial = stored;
			}
		} catch (e) {}

		showView(initial, false);
	})();
</script>

<?php
get_footer();
