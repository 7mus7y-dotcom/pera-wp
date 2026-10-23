<?php
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required.'); }
require_once WP_PLUGIN_DIR.'/peracrm/tests/recipient-persistence-fixture.php';
require_once __DIR__.'/atomic-preparation.php';
$mode=$args[0]??''; $scenario=$args[1]??'';
if (!in_array($scenario,['identical','conflict','identity-first','preparation-first'],true)) { throw new RuntimeException('Invalid atomic race.'); }
peracrm_with_target_blog(static function () use ($mode,$scenario) {
    global $wpdb;
    $content=get_option('atomic_preparation_content');
    [$binding,$envelope]=atomic_preparation_values($content);
    $deployment='fixture-atomic-race-'.$scenario;
    $store=new PeraCRM_CampaignRecipientPersistence($deployment,get_current_blog_id());
    if ($mode==='prepare') { $store->ensureIdentity($binding['subscriber_id'],$binding['mailbox']); return; }
    if ($mode==='verify') {
        $a=json_decode(file_get_contents('/tmp/peracrm-atomic-winner'),true);
        $b=json_decode(file_get_contents('/tmp/peracrm-atomic-loser'),true);
        $denied=in_array($scenario,['conflict','identity-first'],true);
        if ($a['connection']===$b['connection'] || $b['duration']<0.5 || $a['result']!=='ok'
            || $b['result']!==($denied?'rejected':'ok')) { throw new RuntimeException('Atomic blocking race failed.'); }
        $expected=$scenario==='identity-first'?[0,0,0]:[2,1,1]; $actual=[];
        foreach (['recipient_tokens','submission_attempts','submission_outputs'] as $suffix) {
            $actual[]=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.peracrm_table('peracrm_campaign_'.$suffix).' WHERE deployment=%s',$deployment));
        }
        if ($actual!==$expected) { throw new RuntimeException('Atomic race left partial or duplicate preparation.'); }
        if ($scenario==='identical' && $a['hash']!==$b['hash']) { throw new RuntimeException('Concurrent replay regenerated output.'); }
        echo 'PASS atomic race '.$scenario.': independent blocking SQL connections and immutable bytes'."\n"; return;
    }
    if (!in_array($mode,['winner','loser'],true)) { throw new RuntimeException('Invalid mode.'); }
    if ($mode==='winner') {
        add_filter('query',static function ($sql) {
            if ($sql==='COMMIT') { file_put_contents('/tmp/peracrm-atomic-held','locked'); usleep(1500000); }
            return $sql;
        });
    }
    $connection=(int)$wpdb->get_var('SELECT CONNECTION_ID()'); $started=microtime(true); $result='ok'; $hash='';
    try {
        if (($scenario==='identity-first' && $mode==='winner') || ($scenario==='preparation-first' && $mode==='loser')) {
            $store->changeIdentity($binding['subscriber_id'],1,$binding['mailbox'],false);
        } else {
            if ($scenario==='conflict' && $mode==='loser') { $envelope['subject']='conflict'; }
            [$submission]=$store->prepareOutput($content,$envelope,$binding,'https://example.org',
                ['k1'=>str_repeat('synthetic-key-',4)],'k1',1800000000,
                new PeraCRM_CampaignOutputCipher(['e1'=>str_repeat('s',32)],'e1'));
            $hash=$submission->hash();
        }
    } catch (Throwable $e) {
        if ($e->getMessage()!=='recipient_persistence_unavailable') { throw $e; }
        $result='rejected';
    }
    file_put_contents('/tmp/peracrm-atomic-'.$mode,json_encode(['connection'=>$connection,'duration'=>microtime(true)-$started,'result'=>$result,'hash'=>$hash]));
});
