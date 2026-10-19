<?php
if (!defined('ABSPATH')) { exit; }

/** Sole production cron boundary. No recurrence, installation or enable switch in Phase 4C. */
function peracrm_campaign_scheduler_invoke()
{
    return (new PeraCRM_Campaign_Operations())->invoke();
}
add_action('peracrm_campaign_scheduler', 'peracrm_campaign_scheduler_invoke');

/** Repeated init/activation may inspect ownership but cannot install dispatch while disabled. */
function peracrm_campaign_scheduler_registration()
{
    return peracrm_with_target_blog(static function () {
        // Future activation requires a separate reviewed change; do not touch other CRM cron tasks.
        return false;
    });
}
add_action('init', 'peracrm_campaign_scheduler_registration');
