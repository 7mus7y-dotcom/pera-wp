<?php
/** Source ownership guard; real default-theme boot is covered by integration fixtures. */
$plugin = dirname(__DIR__);
foreach (glob($plugin . '/inc/campaigns/*.php') as $file) {
    $source = file_get_contents($file);
    if (preg_match('/\b(?:get_template_directory(?:_uri)?|get_stylesheet_directory(?:_uri)?|get_template_part|locate_template|pera_render_property_card|pera_theme_\w+)\s*\(/', $source)
        || str_contains($source, 'wp-content/themes/')) {
        fwrite(STDERR, 'FAIL: campaign theme dependency in ' . basename($file) . "\n");
        exit(1);
    }
}
$assets = file_get_contents($plugin . '/inc/frontend/assets.php');
foreach (['assets/frontend/crm.css', 'assets/frontend/crm.js'] as $path) {
    if (!is_file($plugin . '/' . $path) || !str_contains($assets, $path)) {
        fwrite(STDERR, 'FAIL: missing plugin-owned asset loading: ' . $path . "\n");
        exit(1);
    }
}
echo "PASS: campaign sources have no theme helper/template dependencies; CRM assets are plugin-owned\n";
