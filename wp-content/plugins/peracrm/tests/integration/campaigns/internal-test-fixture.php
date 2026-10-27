<?php
require_once __DIR__.'/prepared-worker-fixture.php';
require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/internal-test.php';

/** Build exact internal membership using normal consent/capture/freeze/queue services. */
function internal_test_source(int $size=1,bool $unknown=false): int
{
    return worker_fixture_target(static function () use ($size,$unknown) {
        global $wpdb;
        wp_set_current_user((int)get_option('campaign_fixture_administrator'));
        $topic='internal'.bin2hex(random_bytes(8));
        for ($i=1;$i<=$size;$i++) {
            $mailbox=$unknown?'unauthorized@example.test':'internal-test-'.$i.'@example.test';
            $saved=peracrm_campaigns_save_subscriber($mailbox,['preferred_language'=>'en','preferences'=>[$topic]]);
            $consent=peracrm_campaigns_record_consent($mailbox,'subscribed',['source'=>'synthetic_fixture',
                'source_ref'=>'internal','evidence_ref'=>'synthetic','policy_version'=>'v1','occurred_at'=>'2020-01-01 00:00:00']);
            prepared_expect(!is_wp_error($saved) && !is_wp_error($consent),'internal source consent');
        }
        $configuration=peracrm_campaign_configuration(true);
        $senders=array_values(array_filter($configuration['document']['senders'],static fn($sender)=>$sender['approved']===true));
        prepared_expect((bool)$senders,'current approved synthetic sender exists');
        $doc=['internal_name'=>'Controlled synthetic internal test','subject'=>'Synthetic internal test',
            'sender_id'=>$senders[0]['id'],'language'=>'en','main_text'=>'Immutable synthetic internal test content',
            'audience'=>['version'=>1,'topic'=>$topic]];
        $d=peracrm_campaign_draft_save(0,0,$doc);
        $review=peracrm_campaign_snapshot_review($d['id'],1,$d['document']);
        $s=peracrm_campaign_snapshot_capture($d['id'],1,$d['document'],$review['review_proof'],wp_create_nonce('peracrm_campaign_snapshot_'.$d['id'].'_1'));
        $nonce=wp_create_nonce('peracrm_campaign_freeze_'.$d['id']);
        $a=peracrm_campaign_freeze_start($d['id'],1,(int)$s['id'],$s['content_hash'],$d['document'],$nonce);
        while (!(int)$a['complete']) { $a=peracrm_campaign_freeze_continue($d['id'],(int)$a['id'],(int)$a['version'],$nonce); }
        $a=peracrm_campaign_freeze_publish($d['id'],(int)$a['id'],(int)$a['version'],$nonce);
        $r=peracrm_campaign_frozen_get($d['id'],1); $nonce=wp_create_nonce('peracrm_campaign_queue_'.$d['id']);
        $j=peracrm_campaign_queue_prepare($d['id'],(int)$r['id'],$nonce);
        while (!(int)$j['complete']) { $j=peracrm_campaign_queue_materialize($d['id'],(int)$j['id'],(int)$j['version'],$nonce); }
        prepared_expect(!is_wp_error($j) && (int)$j['expected_count']===$size,'exact synthetic frozen source');
        foreach (['jobs'=>'peracrm_campaign_queue_jobs','deliveries'=>'peracrm_campaign_deliveries','events'=>'peracrm_campaign_worker_events'] as $kind=>$origin) {
            $target=peracrm_table('peracrm_fixture_worker_'.$kind);
            $wpdb->query('DROP TABLE IF EXISTS '.$target);
            peracrm_campaigns_db_require($wpdb->query('CREATE TABLE '.$target.' LIKE '.peracrm_table($origin)));
        }
        peracrm_campaigns_db_require($wpdb->insert(peracrm_table('peracrm_fixture_worker_jobs'),$j));
        peracrm_campaigns_db_require($wpdb->query('INSERT INTO '.peracrm_table('peracrm_fixture_worker_deliveries')
            .' SELECT * FROM '.peracrm_table('peracrm_campaign_deliveries').' WHERE job_id='.(int)$j['id']));
        // Later suites select their latest queued source by size. Keep this
        // private copy queued, but retire the original through the staff API so
        // reused allowlist profiles cannot contaminate those independent suites.
        $cancelled=peracrm_campaign_queue_transition($d['id'],(int)$j['id'],(int)$j['version'],$nonce,'cancel');
        prepared_expect(!is_wp_error($cancelled) && $cancelled['state']==='cancelled','internal source isolated from ordinary queued selectors');
        wp_set_current_user((int)get_option('campaign_fixture_administrator'));
        return (int)$j['id'];
    });
}
function internal_test_service(string $deployment,PeraCRM_Campaign_Internal_Test_Transport $transport,?Closure $clock=null): PeraCRM_Campaign_Internal_Test
{
    return worker_fixture_target(static function () use ($deployment,$transport,$clock) {
        $p=prepared_service($deployment); $clock=$clock??static fn()=>prepared_clock();
        $budget=new PeraCRM_Sending_Budget(prepared_pins($deployment),$clock,static fn()=>true);
        return new PeraCRM_Campaign_Internal_Test(new PeraCRM_Campaign_Readiness(
            prepared_scope()+['deployment'=>$deployment,'blog_id'=>get_current_blog_id()],$budget,$clock),
            worker_fixture(),$p,new PeraCRM_Campaign_Worker_Preflight($budget,$clock),$transport,$clock);
    });
}
function internal_test_authorize(PeraCRM_Campaign_Internal_Test $s,int $job): string
{
    $binding=$s->proposal($job)['binding'];
    return $s->authorize($job,$binding,wp_create_nonce('peracrm_internal_test_'.$binding),'synthetic-only');
}
