<?php
/**
 * Template Name: Turkish Citizenship Properties
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

$cards = function_exists( 'pera_latest_offers_collect_cards' )
	? pera_latest_offers_collect_cards(
		12,
		60,
		array(
			'tax_query' => array(
				array(
					'taxonomy' => 'special',
					'field'    => 'slug',
					'terms'    => array( 'citizenship' ),
				),
			),
		)
	)
	: array();

$map_markers = array();
foreach ( $cards as $card ) {
	if ( ! is_array( $card ) ) {
		continue;
	}

	$marker = function_exists( 'pera_latest_offers_marker_dto_from_card' )
		? pera_latest_offers_marker_dto_from_card( $card )
		: null;
	if ( ! is_array( $marker ) ) {
		continue;
	}

	$map_markers[] = $marker;
}

$requested_view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( (string) $_GET['view'] ) ) : '';
$initial_view   = 'map' === $requested_view ? 'map' : 'cards';
$map_json       = wp_json_encode( array_values( $map_markers ) );
if ( ! is_string( $map_json ) ) {
	$map_json = '[]';
}

$description_content = trim( (string) get_post_field( 'post_content', get_queried_object_id() ) );
$hero_title          = get_the_title();
$hero_desc_html      = '';

if ( '' !== trim( wp_strip_all_tags( (string) $description_content ) ) ) {
	$hero_desc_html = wpautop( wp_kses_post( $description_content ) );
	$hero_desc_html = str_replace( '<p>', '<p class="text-light">', $hero_desc_html );
} else {
	$hero_desc_html = '<p class="text-light">' . esc_html__( 'Browse latest property offers tagged for Turkish citizenship eligibility.', 'hello-elementor-child' ) . '</p>';
}
?>

<main id="primary" class="site-main">
	<style>
		.pera-citizenship-properties .citizenship-properties-view-toggle{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:0 0 16px;}
		.pera-citizenship-properties .citizenship-view-btn{min-width:120px;}
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
		</div>
	</section>

	<section class="section content-panel content-panel--overlap-hero pera-citizenship-properties">
		<div class="container">
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
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<pre id="citizenship-properties-map-debug" class="citizenship-map-debug" aria-live="polite"><?php echo esc_html__( 'Map debug panel: waiting for runtime state…', 'hello-elementor-child' ); ?></pre>
			<?php endif; ?>

			<?php if ( ! empty( $cards ) ) : ?>
				<div
					class="pera-latest-offers-card-list pera-latest-offers-card-list--grid-4"
					id="citizenship-properties-cards-panel"
					<?php echo 'map' === $initial_view ? 'hidden' : ''; ?>
				>
					<?php foreach ( $cards as $card ) : ?>
						<?php pera_latest_offers_render_card( $card ); ?>
					<?php endforeach; ?>
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
	</section>
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
		var buttons = Array.prototype.slice.call(root.querySelectorAll('[data-citizenship-view]'));
		var storageKey = 'peraCitizenshipPropertiesView';
		var mapInstance = null;
		var mapBooted = false;
		var markers = [];
		var mapBounds = [];
		var hasRenderableMarkers = false;
		var hasMapError = false;
		var lastErrorMessage = '';
		var parseSucceeded = false;
		var currentView = 'cards';
		var debugEl = document.getElementById('citizenship-properties-map-debug');

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

		var initial = '<?php echo esc_js( $initial_view ); ?>';
		try {
			if (initial !== 'map') {
				var stored = window.localStorage.getItem(storageKey);
				if (stored === 'map' || stored === 'cards') initial = stored;
			}
		} catch (e) {}

		showView(initial, false);
	})();
</script>

<?php
get_footer();
