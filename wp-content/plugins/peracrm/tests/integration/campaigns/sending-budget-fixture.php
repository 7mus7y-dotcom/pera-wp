<?php
require_once __DIR__.'/suppression-sync-fixture.php';
require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/sending-budget.php';
function budget_pins(): array {
    return ['account_id'=>(string)random_int(100000000000,999999999999),'region'=>'eu-west-1','deployment'=>'budget-fixture','blog_id'=>get_current_blog_id()];
}
function budget_service(array $pins,int &$now,bool &$ready): PeraCRM_Sending_Budget {
    return new PeraCRM_Sending_Budget($pins,static function () use (&$now) { return $now; },static function () use (&$ready) { return $ready; });
}
function budget_actor(): void { wp_set_current_user((int)get_option('campaign_fixture_administrator')); }
function budget_key(): string { return bin2hex(random_bytes(32)); }
function budget_denied(callable $fn,string $code): void {
    try { $fn(); } catch (PeraCRM_Sending_Budget_Error $e) { sns_expect($e->getMessage()===$code && $e->getPrevious()===null,'fixed '.$code); return; }
    throw new RuntimeException('Expected budget denial '.$code);
}
function budget_snapshot(): string {
    $out=[];
    foreach (['accounts','reservations','events'] as $name) { $out[$name]=suppress_rows('peracrm_campaign_budget_'.$name); }
    return hash('sha256',wp_json_encode($out));
}
