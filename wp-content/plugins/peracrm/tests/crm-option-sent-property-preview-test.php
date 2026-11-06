<?php
/**
 * Regression checks for saved "Options sent" property previews.
 */

$root = dirname(__DIR__);
$data_path = $root . '/inc/frontend-data/crm-client-view.php';
$view_path = $root . '/inc/views/pages/crm-client.php';

$data = file_get_contents($data_path);
$view = file_get_contents($view_path);

$expect = static function ($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL {$message}\n");
        exit(1);
    }
    fwrite(STDOUT, "PASS {$message}\n");
};

$expect(false !== strpos($data, "function pera_crm_client_view_property_preview"), 'property preview helper exists');
$expect(false !== strpos($data, "get_field( 'main_image', \$property_id )"), 'preview reads the ACF main_image field');
$expect(false !== strpos($data, "wp_get_attachment_image_url( \$image_id, 'thumbnail' )"), 'preview resolves an attachment thumbnail URL');
$expect(false !== strpos($data, "pera_crm_client_view_with_target_blog( \$resolve )"), 'preview resolves inside the property-site context');
$expect(false !== strpos($data, "'url'          => (string) get_permalink( \$property_id )"), 'preview includes the public listing permalink');
$expect(false !== strpos($view, "pera_crm_client_view_property_preview( \$linked_property_id )"), 'saved note uses normalized property preview data');
$expect(false !== strpos($view, 'class="crm-note-property-card" href="<?php echo esc_url( $linked_url ); ?>"'), 'saved preview links to the listing');
$expect(false !== strpos($view, 'class="crm-note-property-thumb"'), 'saved preview renders the main-image thumbnail');
$expect(false === strpos($view, "get_the_post_thumbnail( \$linked_property_id"), 'saved preview no longer relies on the featured image');

fwrite(STDOUT, "CRM option-sent property preview regression checks passed.\n");
