<?php
/** Focused pure/authorization checks. SQL rollback/races run in the real topology suites. */
require __DIR__.'/campaign-snapshots-test.php';
require $root.'/inc/campaigns/freeze.php';
$counts=peracrm_campaign_freeze_counts();
campaign_expect(array_sum($counts)===0 && array_keys($counts)===['eligible','suppression','consent','linked_client','language','topic','rules','duplicate'],'freeze outcomes are exhaustive and mutually exclusive');
foreach ([0,3] as $actor) {
    $GLOBALS['campaign_actor']=$actor;
    foreach (['continue','resume','cancel','publish'] as $op) {
        $fn='peracrm_campaign_freeze_'.$op;
        campaign_expect(is_wp_error($fn(10,1,1,'')),'freeze '.$op.' denies unauthorized actor');
    }
    campaign_expect(is_wp_error(peracrm_campaign_freeze_start(10,2,1,'',$document,'')),'freeze start denies unauthorized actor');
    campaign_expect(snapshot_rejects(static function () { peracrm_campaign_freeze_get(10,1); }) && snapshot_rejects(static function () { peracrm_campaign_freeze_recipients(10,1); }) && snapshot_rejects(static function () { peracrm_campaign_frozen_get(10,2); }),'all freeze reads authorize');
}
$GLOBALS['campaign_actor']=1; $GLOBALS['campaign_impersonating']=true;
campaign_expect(is_wp_error(peracrm_campaign_freeze_start(10,2,1,'',$document,'')) && snapshot_rejects(static function () { peracrm_campaign_frozen_get(10,2); }),'freeze denies impersonated write and read');
$GLOBALS['campaign_impersonating']=false;
foreach ([0,201,'200',-1] as $limit) { campaign_expect(is_wp_error(peracrm_campaign_freeze_continue(10,1,1,'',$limit)),'freeze enforces bounded integer batch size'); }
$GLOBALS['campaign_actor']=2;
campaign_expect(is_wp_error(peracrm_campaign_freeze_cleanup(10,1,'')),'manual cleanup requires administrator');
// Exercise the real draft-save guard with a locked active attempt in a focused stub.
class Freeze_Edit_DB extends Snapshot_Test_DB {
    public $active=true;
    public function get_var($sql) { if (str_contains($sql,'peracrm_campaign_freeze_attempts')) { return $this->active ? 1 : null; } return parent::get_var($sql); }
}
$GLOBALS['wpdb']=new Freeze_Edit_DB(); $GLOBALS['campaign_actor']=1;
$GLOBALS['snapshot_draft']['revision']=2;
$blocked=peracrm_campaign_draft_save(10,2,$document);
campaign_expect(is_wp_error($blocked) && $blocked->get_error_code()==='campaign_invalid' && campaign_count('peracrm_campaign_draft_revisions')===0,'draft-save service itself rejects active freeze before writing history');
echo "PASS freeze focused regressions (synthetic, no SQL race claim)\n";
