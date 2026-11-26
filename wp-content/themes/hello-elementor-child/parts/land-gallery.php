<?php
/**
 * Land media in the theme's shared filmstrip and lightbox UI.
 */

defined( 'ABSPATH' ) || exit;

$featured_id = (int) $args['featured_id'];
$poster_url  = $featured_id ? wp_get_attachment_image_url( $featured_id, 'large' ) : '';
$media_ids   = array_unique( array_map( 'absint', array_filter( array_merge( array( $featured_id ), $args['ids'] ), 'is_numeric' ) ) );
$items       = array();
$has_video   = false;

foreach ( $media_ids as $id ) {
	if ( ! $id || 'attachment' !== get_post_type( $id ) || 'trash' === get_post_status( $id ) ) {
		continue;
	}

	$mime = get_post_mime_type( $id );
	if ( wp_attachment_is_image( $id ) ) {
		$full = wp_get_attachment_image_src( $id, 'full' );
		if ( $full ) {
			$alt = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
			$items[] = array(
				'id'    => $id,
				'type'  => 'image',
				'url'   => $full[0],
				'ratio' => max( 1, (int) $full[1] ) . ' / ' . max( 1, (int) $full[2] ),
				'label' => $alt !== '' ? $alt : pera_ml_ui( 'Plot photo', 'theme.template.single_land.plot_photo' ),
			);
		}
	} elseif ( in_array( $mime, array( 'video/mp4', 'video/webm' ), true ) ) {
		$url = wp_get_attachment_url( $id );
		if ( $url ) {
			$items[] = array(
				'type'  => 'video',
				'url'   => $url,
				'mime'  => $mime,
				'label' => pera_ml_ui( 'Drone video', 'theme.template.single_land.drone_video' ),
			);
			$has_video = true;
		}
	}
}

if ( ! $items || ( count( $items ) === 1 && ( $items[0]['id'] ?? 0 ) === $featured_id ) ) {
	return;
}

$heading = pera_ml_ui( 'Plot gallery', 'theme.template.single_land.gallery' );
$previous = pera_ml_ui( 'Previous gallery item', 'theme.template.single_land.gallery_previous' );
$next = pera_ml_ui( 'Next gallery item', 'theme.template.single_land.gallery_next' );
$icons = get_stylesheet_directory_uri() . '/logos-icons/icons.svg#';
?>
<section class="section property-gallery" aria-labelledby="land-gallery-heading">
	<div class="container">
		<h2 id="land-gallery-heading"><?php echo esc_html( $heading ); ?></h2>
		<div class="property-gallery__strip-shell">
			<button type="button" class="property-gallery__strip-nav property-gallery__strip-nav--prev" aria-controls="land-gallery-strip" aria-label="<?php echo esc_attr( $previous ); ?>" hidden>
				<svg width="22" height="22" aria-hidden="true" focusable="false"><use href="<?php echo esc_url( $icons . 'icon-chevron-left' ); ?>"></use></svg>
			</button>
			<div class="property-gallery__strip" id="land-gallery-strip" role="list">
				<?php foreach ( $items as $item ) : ?>
					<?php if ( 'video' === $item['type'] ) : ?>
						<div class="property-gallery__item property-gallery__item--video" role="listitem">
							<button class="property-gallery__trigger property-gallery__video-trigger" type="button" data-gallery-type="video" data-gallery-src="<?php echo esc_url( $item['url'] ); ?>" data-gallery-mime="<?php echo esc_attr( $item['mime'] ); ?>" data-gallery-poster="<?php echo esc_url( $poster_url ); ?>" aria-label="<?php echo esc_attr( $item['label'] ); ?>">
								<?php if ( $featured_id && $poster_url ) : ?>
									<?php echo wp_get_attachment_image( $featured_id, 'medium_large', false, array( 'alt' => '', 'loading' => 'lazy' ) ); ?>
								<?php endif; ?>
								<span class="property-gallery__play" aria-hidden="true">
									<svg aria-hidden="true" focusable="false"><use href="<?php echo esc_url( $icons . 'icon-play' ); ?>"></use></svg>
								</span>
								<span class="property-gallery__video-label"><?php echo esc_html( $item['label'] ); ?></span>
							</button>
						</div>
					<?php else : ?>
						<div class="property-gallery__item property-gallery__item--image" role="listitem" style="<?php echo esc_attr( '--image-ratio: ' . $item['ratio'] ); ?>">
							<button class="property-gallery__trigger" type="button" data-gallery-type="image" data-gallery-src="<?php echo esc_url( $item['url'] ); ?>" aria-label="<?php echo esc_attr( $item['label'] ); ?>">
								<?php echo wp_get_attachment_image( $item['id'], 'medium_large', false, array( 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async' ) ); ?>
							</button>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
			<button type="button" class="property-gallery__strip-nav property-gallery__strip-nav--next" aria-controls="land-gallery-strip" aria-label="<?php echo esc_attr( $next ); ?>" hidden>
				<svg width="22" height="22" aria-hidden="true" focusable="false"><use href="<?php echo esc_url( $icons . 'icon-chevron-right' ); ?>"></use></svg>
			</button>
		</div>
	</div>
</section>

<div class="lightbox" id="property-lightbox" role="dialog" aria-modal="true" aria-labelledby="land-gallery-heading" aria-hidden="true">
	<div class="lightbox__backdrop" data-gallery-close></div>
	<div class="lightbox__content">
		<div class="lightbox__frame">
			<button class="lightbox__close" type="button" data-gallery-close aria-label="<?php echo esc_attr( pera_ml_ui( 'Close gallery', 'theme.template.single_land.gallery_close' ) ); ?>">
				<svg width="22" height="22" aria-hidden="true" focusable="false"><use href="<?php echo esc_url( $icons . 'icon-close' ); ?>"></use></svg>
			</button>
			<button class="lightbox__nav lightbox__nav--prev" type="button" data-gallery-prev aria-label="<?php echo esc_attr( $previous ); ?>">
				<svg class="lightbox__nav-icon" aria-hidden="true" focusable="false"><use href="<?php echo esc_url( $icons . 'icon-chevron-left' ); ?>"></use></svg>
			</button>
			<img class="lightbox__img" alt="" hidden>
			<?php if ( $has_video ) : ?>
				<video class="lightbox__video" controls playsinline preload="none" hidden><source></video>
				<button class="lightbox__video-pause" type="button" aria-label="<?php echo esc_attr( pera_ml_ui( 'Pause video', 'theme.template.single_land.pause_video' ) ); ?>" hidden></button>
				<button class="lightbox__video-play" type="button" aria-label="<?php echo esc_attr( pera_ml_ui( 'Play video', 'theme.template.single_land.play_video' ) ); ?>" hidden>
					<svg aria-hidden="true" focusable="false"><use href="<?php echo esc_url( $icons . 'icon-play' ); ?>"></use></svg>
				</button>
			<?php endif; ?>
			<button class="lightbox__nav lightbox__nav--next" type="button" data-gallery-next aria-label="<?php echo esc_attr( $next ); ?>">
				<svg class="lightbox__nav-icon" aria-hidden="true" focusable="false"><use href="<?php echo esc_url( $icons . 'icon-chevron-right' ); ?>"></use></svg>
			</button>
			<p class="lightbox__caption" aria-live="polite"></p>
		</div>
	</div>
</div>
