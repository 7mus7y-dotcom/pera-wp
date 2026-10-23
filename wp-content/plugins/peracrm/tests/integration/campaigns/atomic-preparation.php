<?php
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required.'); }
require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/providers/ses.php';
function atomic_preparation_values(array $content): array
{
    [$binding,$output,$submission]=peracrm_test_persistence_values($content,get_current_blog_id());
    $envelope=$submission->fields(); unset($envelope['html'],$envelope['text']);
    return [$binding,$envelope];
}
function atomic_preparation_sql(array $content): void
{
    global $wpdb;
    [$binding,$envelope]=atomic_preparation_values($content);
    $store=new PeraCRM_CampaignRecipientPersistence('fixture-atomic',get_current_blog_id());
    $store->ensureIdentity($binding['subscriber_id'],$binding['mailbox']);
    $cipher=new PeraCRM_CampaignOutputCipher(['e1'=>str_repeat('s',32)],'e1');
    $prepare=static fn($data,$headers,$identity)=>$store->prepareOutput($data,$headers,$identity,'https://example.org',
        ['k1'=>str_repeat('synthetic-key-',4)],'k1',1800000000,$cipher);
    $counts=static function () use ($wpdb) {
        $counts=[];
        foreach (['recipient_tokens','submission_attempts','submission_outputs'] as $suffix) {
            $counts[]=(int)$wpdb->get_var("SELECT COUNT(*) FROM ".peracrm_table('peracrm_campaign_'.$suffix)." WHERE deployment='fixture-atomic'");
        }
        return $counts;
    };
    $transport=new class($counts) implements PeraCRM_Campaign_SES_Transport {
        public $calls=0; private $counts;
        public function __construct(callable $counts) { $this->counts=$counts; }
        public function send(array $request,array $options): array {
            global $wpdb; ++$this->calls;
            persistence_expect(($this->counts)()===[2,1,1],'both digests, binding and ciphertext exist at mocked SES boundary');
            $quiet=$wpdb->suppress_errors(true);
            $wpdb->query('SAVEPOINT atomic_transport_probe');
            $outside=$wpdb->query('RELEASE SAVEPOINT atomic_transport_probe')===false;
            $wpdb->suppress_errors($quiet);
            persistence_expect($outside,'COMMIT precedes injected transport');
            return ['complete'=>true,'authenticated'=>true,'status'=>200,'message_id'=>'fixture-atomic-accepted'];
        }
    };
    $mapper=new PeraCRM_Campaign_SES_Mapper($envelope['scope'],$transport);
    foreach (['first-token','second-token','attempt','output','commit'] as $fault) {
        $tokens=0;
        $filter=static function ($sql) use ($fault,&$tokens) {
            if (str_starts_with($sql,'INSERT INTO') && str_contains($sql,'recipient_tokens')) { ++$tokens; }
            $fail=($fault==='first-token' && $tokens===1 && str_starts_with($sql,'INSERT INTO'))
                || ($fault==='second-token' && $tokens===2 && str_starts_with($sql,'INSERT INTO'))
                || ($fault==='attempt' && str_starts_with($sql,'INSERT INTO') && str_contains($sql,'submission_attempts'))
                || ($fault==='output' && str_starts_with($sql,'INSERT INTO') && str_contains($sql,'submission_outputs'))
                || ($fault==='commit' && $sql==='COMMIT');
            return $fail ? 'INVALID_ATOMIC_PREPARATION_SQL' : $sql;
        };
        add_filter('query',$filter);
        try { persistence_reject(static function () use ($prepare,$content,$envelope,$binding,$mapper) {
            [$submission]=$prepare($content,$envelope,$binding); $mapper->submit($submission);
        }); } finally { remove_filter('query',$filter); }
        persistence_expect($counts()===[0,0,0] && $transport->calls===0,'atomic rollback and zero transport after '.$fault);
    }
    $historical=$content; $historical['schema_version']=1;
    persistence_reject(fn()=>$prepare($historical,$envelope,$binding));
    persistence_expect($counts()===[0,0,0],'historical/mismatched capture cannot create durable preparation');
    [$submission,$output]=$prepare($content,$envelope,$binding);
    $rows=$wpdb->get_results("SELECT * FROM ".peracrm_table('peracrm_campaign_submission_outputs')." WHERE deployment='fixture-atomic'",ARRAY_A);
    // Replay cannot require issuance keys/current rendering, or generate new bytes.
    [$replay,$replayedOutput]=$store->prepareOutput([],array_reverse($envelope,true),$binding,'invalid-origin',[],
        'missing',0,$cipher);
    persistence_expect($replay->fields()===$submission->fields() && $replayedOutput->fields()===$output->fields()
        && $counts()===[2,1,1],'existing attempt recovers exact bytes without issuance inputs');
    persistence_expect($rows===$wpdb->get_results("SELECT * FROM ".peracrm_table('peracrm_campaign_submission_outputs')." WHERE deployment='fixture-atomic'",ARRAY_A),'replay preserves ciphertext');
    $changed=$envelope; $changed['subject']='Conflicting';
    persistence_reject(fn()=>$prepare($content,$changed,$binding));
    persistence_expect($transport->calls===0 && $counts()===[2,1,1],'conflicting replay does not overwrite or submit');
    persistence_expect($mapper->submit($submission)['kind']==='accepted' && $transport->calls===1,'atomic output passes SES mapper with mock only');
    // A prior metadata-only attempt is an incomplete legacy attempt, never a mint permit.
    $wpdb->delete(peracrm_table('peracrm_campaign_submission_outputs'),['deployment'=>'fixture-atomic']);
    persistence_reject(fn()=>$prepare($content,$envelope,$binding));
    persistence_expect($counts()===[2,1,0] && $transport->calls===1,'missing existing output fails closed without replacement issuance or transport');
}
