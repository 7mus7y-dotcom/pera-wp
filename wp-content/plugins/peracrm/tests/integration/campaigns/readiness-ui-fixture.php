<?php
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE') || !defined('WP_CLI') || !WP_CLI) { throw new RuntimeException('Disposable CLI only.'); }
peracrm_with_target_blog(static function () {
    wp_set_current_user((int)get_option('campaign_fixture_administrator'));
    wp_set_password('fixture-manager-only',(int)get_option('campaign_fixture_manager'));
    $pins=['account'=>'123456789012','region'=>'eu-west-1','topic'=>'arn:aws:sns:eu-west-1:123456789012:fixture-readiness',
        'configuration_set'=>'fixture','configuration_version'=>1,'deployment'=>'readiness-browser','blog'=>get_current_blog_id()];
    file_put_contents('/var/www/html/readiness-pins.json',wp_json_encode($pins));
    echo "PASS synthetic readiness UI scope seeded; no provider access\n";
});
