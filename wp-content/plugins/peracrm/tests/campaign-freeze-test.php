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
// The actual cleanup service must preserve its unique terminal row after all payloads are gone.
if (!defined('DAY_IN_SECONDS')) { define('DAY_IN_SECONDS',86400); }
class Freeze_Cleanup_DB extends Snapshot_Test_DB {
    public $payloads=['peracrm_campaign_recipient_evaluations'=>151,'peracrm_campaign_frozen_recipients'=>51];
    public function get_row($sql,$format) {
        if (str_contains($sql,'peracrm_campaign_freeze_attempts')) { return $this->tables[$this->prefix.'peracrm_campaign_freeze_attempts'][77] ?? null; }
        return parent::get_row($sql,$format);
    }
    public function get_var($sql) { if (str_contains($sql,'peracrm_campaign_frozen_revisions')) { return null; } return parent::get_var($sql); }
    public function query($sql) {
        if (preg_match('/DELETE FROM \w+_(peracrm_campaign_(?:recipient_evaluations|frozen_recipients)) WHERE attempt_id=77 LIMIT (\d+)/',$sql,$m)) {
            $deleted=min($this->payloads[$m[1]],(int)$m[2]); $this->payloads[$m[1]]-=$deleted; return $deleted;
        }
        return parent::query($sql);
    }
}
$GLOBALS['wpdb']=new Freeze_Cleanup_DB();
$tombstone=['id'=>77,'campaign_id'=>10,'draft_revision'=>2,'state'=>'cancelled','version'=>3,'actor_user_id'=>1,'updated_at'=>'2000-01-01 00:00:00'];
$GLOBALS['wpdb']->tables['wp_2_peracrm_campaign_freeze_attempts'][77]=$tombstone;
$nonce='fixture:peracrm_campaign_freeze_10'; $total=0;
for ($i=0;$i<4;$i++) {
    $clean=peracrm_campaign_freeze_cleanup(10,77,$nonce);
    campaign_expect(!is_wp_error($clean) && $clean['deleted_rows']<=100 && !$clean['deleted_attempt'],'cleanup only deletes a bounded payload batch');
    $total+=$clean['deleted_rows'];
}
campaign_expect($total===202 && $clean['complete'] && peracrm_campaign_freeze_get(10,77)===$tombstone,'complete and repeated cleanup retain exact cancelled revision tombstone');
$restart=peracrm_campaign_freeze_start(10,2,1,str_repeat('a',64),$document,$nonce);
campaign_expect(is_wp_error($restart) && $restart->get_error_code()==='campaign_conflict','retained terminal fence rejects restart before capture lookup');
campaign_expect(is_wp_error(peracrm_campaign_freeze_resume(10,77,3,$nonce)) && is_wp_error(peracrm_campaign_freeze_publish(10,77,3,$nonce)),'cleaned tombstone cannot resume or publish');
echo "PASS freeze focused regressions (synthetic, no SQL race claim)\n";
