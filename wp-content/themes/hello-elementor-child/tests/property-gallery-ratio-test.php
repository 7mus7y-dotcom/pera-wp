<?php
/** Execute the template's actual dimension resolution with stubbed attachment metadata. */
function expect_gallery_ratio( $condition, $label ) {
  if ( ! $condition ) { fwrite( STDERR, "FAIL {$label}\n" ); exit( 1 ); }
}
function wp_get_attachment_metadata( $id ) {
  $GLOBALS['metadata_calls']++;
  return $GLOBALS['test_metadata'];
}
$theme = dirname( __DIR__ );
$template = file_get_contents( $theme . '/single-property.php' );
$css = file_get_contents( $theme . '/css/slider.css' );
$start = strpos( $template, '          $dimensions = isset( $gallery_image_dimensions' );
$end = strpos( $template, '          $image_html = wp_get_attachment_image(', $start );
expect_gallery_ratio( false !== $start && false !== $end, 'dimension resolution exists' );
$code = substr( $template, $start, $end - $start );
$img_id = 42;
foreach ( array(
  array( array( 'width' => 800, 'height' => 600 ), false, '800 / 600', 0 ),
  array( array(), array( 'width' => 600, 'height' => 1200 ), '600 / 1200', 1 ),
  array( array( 'width' => 0, 'height' => 10 ), false, '4 / 3', 1 ),
  array( array( 'width' => array(), 'height' => 10 ), array(), '4 / 3', 1 ),
  array( array( 'width' => INF, 'height' => 10 ), false, '4 / 3', 1 ),
  array( array( 'width' => 10000, 'height' => 1000 ), false, '10000 / 1000', 0 ),
) as $case ) {
  $gallery_image_dimensions = array( 42 => $case[0] );
  $GLOBALS['test_metadata'] = $case[1];
  $GLOBALS['metadata_calls'] = 0;
  eval( $code );
  expect_gallery_ratio( $image_ratio === $case[2], 'valid ratio or safe fallback' );
  expect_gallery_ratio( $GLOBALS['metadata_calls'] === $case[3], 'ACF dimensions preferred over metadata lookup' );
}
expect_gallery_ratio( false !== strpos( $template, "esc_attr( '--image-ratio: ' . \$image_ratio . ';' )" ), 'ratio attribute escaped' );
expect_gallery_ratio( false !== strpos( substr( $template, $end, 150 ), "'full'" ), 'uncropped WordPress source requested' );
// Keep WordPress responsible for srcset generation; only override its sizes hint.
$image_call_end = strpos( $template, '          if ( ! $image_html )', $end );
$image_call = substr( $template, $end, $image_call_end - $end );
expect_gallery_ratio( false !== strpos( $image_call, "'sizes'    => '(min-width: 1024px) 56vw, (min-width: 768px) 68vw, 82vw'" ), 'inline image sizes match gallery breakpoints' );
expect_gallery_ratio( false === strpos( $image_call, "'srcset'" ), 'WordPress generated srcset is not overridden or disabled' );
expect_gallery_ratio( false !== strpos( $template, 'data-gallery-src="<?php echo esc_url( wp_get_attachment_image_url( $img_id, \'full\' ) ); ?>"' ), 'lightbox still opens the full attachment URL' );
expect_gallery_ratio( 1 === preg_match( '/\.property-gallery__item--image img\{[^}]*width: 100%;[^}]*object-fit: contain;/s', $css ), 'only photographs use contain' );
foreach ( array( 260, 340, 420 ) as $height ) {
  expect_gallery_ratio( 1 === preg_match( '/\.property-gallery__strip\{[^}]*height: ' . $height . 'px;/s', $css ), 'stable responsive height ' . $height );
}
foreach ( array( '82vw, 520px', '68vw, 760px', '56vw, 900px' ) as $cap ) {
  expect_gallery_ratio( false !== strpos( $css, 'max-width: min(' . $cap . ', 100%);' ), 'photo cap within viewport and container' );
}
expect_gallery_ratio( false !== strpos( $css, 'min-width: min(120px, 100%);' ), 'usable portrait minimum fits container' );
expect_gallery_ratio( 1 === preg_match( '/\.property-gallery__item--video\{\s*width: min\(82vw, 520px\);\s*min-width: 220px;/s', $css ), 'video sizing preserved' );
expect_gallery_ratio( false !== strpos( $css, 'overflow-x: auto;' ) && false !== strpos( $css, '-webkit-overflow-scrolling: touch;' ), 'native touch scroller preserved' );
echo "Property gallery ratio tests passed\n";
