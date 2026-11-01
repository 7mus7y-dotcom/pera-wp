<?php
/** Article-owned coordinates; no REST surface or changes to the archive query. */
defined( 'ABSPATH' ) || exit;

function pera_regional_map_coordinate( $value, $limit ) {
	if ( ! is_scalar( $value ) || ! is_numeric( $value ) ) return null;
	$value = (float) $value;
	return is_finite( $value ) && abs( $value ) <= $limit ? $value : null;
}

/** Shared canonical post metadata is deliberately independent of Pera ML translations. */
function pera_regional_map_guides() {
	$posts = get_posts( array(
		'post_type' => 'post', 'post_status' => 'publish', 'has_password' => false,
		'category_name' => 'regional-guides', 'posts_per_page' => -1,
		'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true,
		'update_post_term_cache' => false,
		'meta_query' => array( array( 'key' => '_pera_guide_map_enabled', 'value' => '1' ) ),
	) );
	$guides = array();
	foreach ( $posts as $post ) {
		$lat = pera_regional_map_coordinate( get_post_meta( $post->ID, '_pera_guide_map_lat', true ), 90 );
		$lng = pera_regional_map_coordinate( get_post_meta( $post->ID, '_pera_guide_map_lng', true ), 180 );
		if ( null === $lat || null === $lng || 'publish' !== $post->post_status ) continue;
		$url = get_permalink( $post->ID );
		$guides[] = array(
			'lat' => $lat, 'lng' => $lng,
			'title' => get_the_title( $post->ID ),
			'label' => (string) get_post_meta( $post->ID, '_pera_guide_map_label', true ),
			'url' => function_exists( 'pera_ml_url' ) ? pera_ml_url( $url ) : $url,
		);
	}
	return $guides;
}

/** WGS84 orientation points. Sources and maintenance instructions: docs/regional-guides-map.md. */
function pera_regional_map_landmarks() {
	return array(
		array( 'lat' => 41.02566, 'lng' => 28.97416, 'title' => pera_ml_ui( 'Galata Tower', 'theme.regional_map.galata' ) ),
		array( 'lat' => 41.01152, 'lng' => 28.98338, 'title' => pera_ml_ui( 'Topkapı Palace', 'theme.regional_map.topkapi' ) ),
		array( 'lat' => 41.00546, 'lng' => 28.97682, 'title' => pera_ml_ui( 'Sultanahmet Mosque', 'theme.regional_map.sultanahmet' ) ),
	);
}

function pera_regional_map_assets() {
	wp_enqueue_style( 'pera-leaflet', get_stylesheet_directory_uri() . '/vendor/leaflet/leaflet.css', array(), '1.9.4' );
	wp_enqueue_script( 'pera-leaflet', get_stylesheet_directory_uri() . '/vendor/leaflet/leaflet.js', array(), '1.9.4', true );
	wp_enqueue_script( 'pera-regional-map', get_stylesheet_directory_uri() . '/js/regional-guides-map.js', array( 'pera-leaflet' ), pera_get_asset_version( 'js/regional-guides-map.js' ), true );
}
add_action( 'wp_enqueue_scripts', function () {
	if ( is_category( 'regional-guides' ) ) pera_regional_map_assets();
} );

function pera_render_regional_guides_map() {
	if ( ! is_category( 'regional-guides' ) ) return;
	$guides = pera_regional_map_guides();
	$data = array( 'guides' => $guides, 'landmarks' => pera_regional_map_landmarks(), 'read' => pera_ml_ui( 'Read guide', 'theme.regional_map.read' ) );
	?>
	<section class="section regional-map" aria-labelledby="regional-map-heading" data-regional-map data-map-config="<?php echo esc_attr( wp_json_encode( $data ) ); ?>">
		<div class="container">
			<h2 id="regional-map-heading"><?php echo esc_html( pera_ml_ui( 'Explore Istanbul by neighbourhood', 'theme.regional_map.heading' ) ); ?></h2>
			<p><?php echo esc_html( pera_ml_ui( 'Select a location on the map to explore our neighbourhood and district guides.', 'theme.regional_map.intro' ) ); ?></p>
			<button type="button" class="btn btn--ghost btn--blue regional-map__toggle" hidden aria-pressed="false"><?php echo esc_html( pera_ml_ui( 'Enable map interaction', 'theme.regional_map.enable' ) ); ?></button>
			<div class="regional-map__canvas" data-map-canvas role="region" aria-labelledby="regional-map-heading"></div>
			<p data-map-status role="status"><?php echo esc_html( pera_ml_ui( 'If the map is unavailable, explore the guide links below.', 'theme.regional_map.fallback' ) ); ?></p>
			<details class="regional-map__links">
				<summary><?php echo esc_html( pera_ml_ui( 'Browse mapped guides', 'theme.regional_map.browse' ) ); ?></summary>
				<ul><?php foreach ( $guides as $guide ) : ?><li><a href="<?php echo esc_url( $guide['url'] ); ?>"><?php echo esc_html( $guide['title'] ); ?></a></li><?php endforeach; ?></ul>
			</details>
		</div>
	</section>
	<?php
}

add_action( 'add_meta_boxes_post', function () {
	add_meta_box( 'pera-guide-map', 'Map location — Regional Guides', 'pera_regional_map_metabox', 'post', 'side' );
} );
function pera_regional_map_metabox( $post ) {
	wp_nonce_field( 'pera_guide_map_save', 'pera_guide_map_nonce' );
	foreach ( array( 'lat' => 'Latitude (decimal)', 'lng' => 'Longitude (decimal)', 'label' => 'Optional map label' ) as $key => $label ) {
		$value = get_post_meta( $post->ID, '_pera_guide_map_' . $key, true );
		?>
		<p><label for="pera-guide-map-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
		<input class="widefat" id="pera-guide-map-<?php echo esc_attr( $key ); ?>" name="pera_guide_map[<?php echo esc_attr( $key ); ?>]" type="<?php echo 'label' === $key ? 'text' : 'number'; ?>" <?php if ( 'label' !== $key ) : ?>step="any" min="<?php echo 'lat' === $key ? '-90' : '-180'; ?>" max="<?php echo 'lat' === $key ? '90' : '180'; ?>"<?php endif; ?> value="<?php echo esc_attr( $value ); ?>"></p>
		<?php
	}
	?>
	<p><label><input type="checkbox" name="pera_guide_map[enabled]" value="1" <?php checked( get_post_meta( $post->ID, '_pera_guide_map_enabled', true ), '1' ); ?>> Show on Regional Guides map</label></p>
	<p class="description">Applies only to published articles in Regional Guides. Enter both coordinates manually, or use the optional picker.</p>
	<details data-regional-map-picker><summary>Choose location on map</summary><p>Click to set coordinates. Manual entry remains available.</p><div class="regional-map__editor" data-map-canvas role="region" aria-label="Article location picker"></div></details>
	<?php
}
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) return;
	$screen = get_current_screen();
	if ( ! $screen || 'post' !== $screen->post_type ) return;
	pera_regional_map_assets();
	wp_enqueue_style( 'pera-regional-map-admin', get_stylesheet_directory_uri() . '/assets/css/admin-regional-map.css', array( 'pera-leaflet' ), pera_get_asset_version( 'assets/css/admin-regional-map.css' ) );
} );

function pera_regional_map_save( $post_id ) {
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) return;
	if ( ! isset( $_POST['pera_guide_map_nonce'] ) || ! is_string( $_POST['pera_guide_map_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pera_guide_map_nonce'] ) ), 'pera_guide_map_save' ) ) return;
	if ( ! isset( $_POST['pera_guide_map'] ) || ! is_array( $_POST['pera_guide_map'] ) ) return;
	$input = wp_unslash( $_POST['pera_guide_map'] );
	$lat = pera_regional_map_coordinate( $input['lat'] ?? '', 90 );
	$lng = pera_regional_map_coordinate( $input['lng'] ?? '', 180 );
	foreach ( array( 'lat' => $lat, 'lng' => $lng ) as $key => $value ) {
		if ( null === $value ) delete_post_meta( $post_id, '_pera_guide_map_' . $key );
		else update_post_meta( $post_id, '_pera_guide_map_' . $key, (string) $value );
	}
	$label = isset( $input['label'] ) && is_string( $input['label'] ) ? sanitize_text_field( $input['label'] ) : '';
	update_post_meta( $post_id, '_pera_guide_map_label', $label );
	update_post_meta( $post_id, '_pera_guide_map_enabled', null !== $lat && null !== $lng && isset( $input['enabled'] ) && '1' === $input['enabled'] ? '1' : '0' );
}
add_action( 'save_post_post', 'pera_regional_map_save' );
