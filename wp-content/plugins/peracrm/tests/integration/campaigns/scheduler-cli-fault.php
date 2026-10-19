<?php
require_once __DIR__.'/worker-fixture.php';
worker_fixture_target(static function () use ($args) {
    if (($args[0] ?? '')==='block') {
        update_option('campaign_scheduler_saved_readiness',get_option('peracrm_campaigns_schema_status'),false);
        update_option('peracrm_campaigns_schema_status',['state'=>'failed','version'=>PERACRM_SCHEMA_VERSION],false);
    } elseif (($args[0] ?? '')==='restore') {
        update_option('peracrm_campaigns_schema_status',get_option('campaign_scheduler_saved_readiness'),false);
        delete_option('campaign_scheduler_saved_readiness');
    } else { throw new RuntimeException('Unknown disposable readiness fixture.'); }
});
