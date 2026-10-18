<?php
/** Source ownership guard; real default-theme boot is covered by integration fixtures. */
$plugin = dirname(__DIR__);
foreach (array_merge(glob($plugin . '/inc/campaigns/*.php'), [$plugin . '/inc/views/pages/crm-campaigns.php']) as $file) {
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

if (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }
require $plugin . '/inc/campaigns/draft-schema.php';
if (array_keys(peracrm_campaigns_draft_definitions()) !== ['peracrm_campaign_drafts','peracrm_campaign_draft_revisions','peracrm_campaign_configuration']) { throw new RuntimeException('Phase 3A must create draft/configuration tables only.'); }
foreach (['drafts.php','draft-routes.php','draft-schema.php','snapshots.php','snapshot-schema.php'] as $name) {
    $source = file_get_contents($plugin . '/inc/campaigns/' . $name);
    if (preg_match('/\\b(?:wp_mail|wp_remote_\\w+|wp_schedule_\\w+|wp_next_scheduled|curl_exec)\\s*\\(/', $source)) { throw new RuntimeException('Unexpected delivery/network/scheduler path in draft module.'); }
}
echo "PASS: Phase 3A schema has only drafts/revisions/configuration and no delivery/network/scheduler paths\n";
