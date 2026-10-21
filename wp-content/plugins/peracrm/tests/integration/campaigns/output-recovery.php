<?php
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required.'); }
require_once WP_PLUGIN_DIR.'/peracrm/tests/output-recovery-fixture.php';
require_once WP_PLUGIN_DIR.'/peracrm/tests/recipient-persistence-fixture.php';
$mode = $args[0] ?? ''; $scenario = $args[1] ?? 'restart';
if (!in_array($scenario,['restart','identical','conflict'],true)) { throw new RuntimeException('Unknown output scenario'); }
function output_expect(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException('FAIL output: '.$label); }
    echo 'PASS output: '.$label."\n";
}
function output_reject(callable $fn): void {
    try { $fn(); } catch (Throwable $e) {
        output_expect($e->getMessage() === 'recipient_persistence_unavailable' && $e->getPrevious() === null,'fixed unchained failure'); return;
    }
    throw new RuntimeException('Expected output rejection');
}
peracrm_with_target_blog(static function () use ($mode,$scenario) {
    global $wpdb;
    $blog = get_current_blog_id(); $deployment = 'fixture-output-'.$scenario;
    [$binding,$output,$submission,$metadata] = output_fixture($blog,$scenario);
    $store = new PeraCRM_CampaignRecipientPersistence($deployment,$blog); $cipher = output_cipher();
    $table = peracrm_table('peracrm_campaign_submission_outputs');
    $where = ['deployment'=>$deployment,'blog_id'=>$blog,'attempt_key'=>$binding['attempt_key']];
    $read = static fn()=>$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE deployment=%s AND blog_id=%d AND attempt_key=%s",...array_values($where)),ARRAY_A);
    if ($mode === 'prepare') {
        peracrm_test_persistence_subscriber(19001,$binding['mailbox']);
        $store->ensureIdentity(19001,$binding['mailbox']); $store->bindAttempt($submission,$binding,$output);
        if ($scenario === 'restart') {
            output_expect($store->persistOutput($submission,$binding,$output,$cipher) === $submission->hash(),'committed exact creation');
            // Only hashes leave this process; no bearer/body in files or logs.
            file_put_contents('/tmp/peracrm-output-hashes',json_encode([$submission->hash(),$output->hash()]));
        }
        return;
    }
    if ($mode === 'winner' || $mode === 'loser') {
        $start = microtime(true); $connection = (int)$wpdb->get_var('SELECT CONNECTION_ID()'); $result = 'ok';
        if ($mode === 'winner') {
            add_filter('query',static function ($sql) {
                if ($sql === 'COMMIT') { file_put_contents('/tmp/peracrm-output-held','held'); usleep(1500000); }
                return $sql;
            });
        }
        try {
            if ($mode === 'loser' && $scenario === 'conflict') {
                $data=$submission->fields(); $data['scope']['configuration_version']=2; $submission=new PeraCRM_CampaignSubmissionV1($data);
            }
            $store->persistOutput($submission,$binding,$output,output_cipher($mode==='loser'));
        } catch (Throwable $e) {
            if ($e->getMessage() !== 'recipient_persistence_unavailable') { throw $e; } $result='rejected';
        }
        file_put_contents('/tmp/peracrm-output-'.$mode,json_encode(['connection'=>$connection,'duration'=>microtime(true)-$start,'result'=>$result]));
        return;
    }
    if ($mode === 'verify') {
        $a=json_decode(file_get_contents('/tmp/peracrm-output-winner'),true); $b=json_decode(file_get_contents('/tmp/peracrm-output-loser'),true);
        output_expect($a['connection']!==$b['connection'] && $b['duration']>=0.5 && $a['result']==='ok'
            && $b['result']===($scenario==='conflict'?'rejected':'ok'),'independent blocking connections: '.$scenario);
        output_expect((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE deployment=%s",$deployment))===1,'one immutable output');
    } elseif ($mode !== 'recover') { throw new RuntimeException('Unknown output mode'); }
    [$restored,$expanded]=$store->recoverOutput($binding,$metadata,output_cipher(true));
    output_expect($restored->fields()===$submission->fields() && $expanded->fields()===$output->fields(),'new process exact envelope and expanded bytes: '.$scenario);
    if ($scenario !== 'restart') { return; }
    output_expect(json_decode(file_get_contents('/tmp/peracrm-output-hashes'),true)===[$restored->hash(),$expanded->hash()],'restart verifies independent saved hashes');
    $before=$read();
    $store->persistOutput($submission,$binding,$output,output_cipher(true));
    output_expect($before===$read(),'identical creation after rotation retains original ciphertext/key version');
    output_reject(fn()=>$store->recoverOutput($binding,$metadata,new PeraCRM_CampaignOutputCipher(['e2'=>str_repeat('b',32)],'e2')));
    output_reject(fn()=>$store->recoverOutput($binding,$metadata,new PeraCRM_CampaignOutputCipher(['e1'=>str_repeat('z',32)],'e1')));
    foreach (['nonce'=>base64_encode(str_repeat('x',24)),'ciphertext'=>base64_encode(str_repeat('x',32)),'key_id'=>'e2'] as $key=>$value) {
        $wpdb->update($table,[$key=>$value],$where); output_reject(fn()=>$store->recoverOutput($binding,$metadata,$cipher)); $wpdb->update($table,[$key=>$before[$key]],$where);
    }
    foreach (['recipient_id'=>72,'subscriber_id'=>19002,'token_epoch'=>2,'envelope_hash'=>str_repeat('0',64),'expanded_output_hash'=>str_repeat('0',64)] as $key=>$value) {
        output_reject(fn()=>$store->recoverOutput($binding,array_replace($metadata,[$key=>$value]),$cipher));
    }
    foreach (['account_id'=>'999999999999','region'=>'eu-west-2','configuration_set'=>'other','configuration_version'=>2] as $key=>$value) {
        $bad=$metadata; $bad['scope'][$key]=$value; output_reject(fn()=>$store->recoverOutput($binding,$bad,$cipher));
    }
    foreach (['blog_id'=>$blog+1,'subscriber_id'=>19002,'token_epoch'=>2,'attempt_key'=>str_repeat('0',64)] as $key=>$value) {
        output_reject(fn()=>$store->recoverOutput(array_replace($binding,[$key=>$value]),$metadata,$cipher));
    }
    output_reject(fn()=>(new PeraCRM_CampaignRecipientPersistence('wrong-deployment',$blog))->recoverOutput($binding,$metadata,$cipher));
    // Copy ciphertext into another deployment even with matching metadata: AAD denies.
    $other=new PeraCRM_CampaignRecipientPersistence('fixture-output-copy',$blog);
    $other->ensureIdentity(19001,$binding['mailbox']); $other->bindAttempt($submission,$binding,$output);
    $wpdb->insert($table,array_replace($before,['deployment'=>'fixture-output-copy']));
    output_reject(fn()=>$other->recoverOutput($binding,$metadata,$cipher));
    // Authenticated but mismatched plaintext must still fail both independent hashes.
    $context=json_encode(['deployment'=>$deployment,'blog_id'=>$blog,'attempt'=>$metadata],JSON_THROW_ON_ERROR);
    foreach (['envelope','output'] as $part) {
        $data=['envelope'=>$submission->fields(),'output'=>$output->fields()]; $data[$part]['html'].='changed';
        $wpdb->update($table,$cipher->seal(json_encode($data,JSON_THROW_ON_ERROR),$context),$where);
        output_reject(fn()=>$store->recoverOutput($binding,$metadata,$cipher));
    }
    $wpdb->update($table,array_intersect_key($before,array_flip(['key_id','nonce','ciphertext'])),$where);
    // Conflicting fresh expansion and full-envelope header changes cannot replace bytes.
    $data=$submission->fields(); $data['subject']='conflict';
    output_reject(fn()=>$store->persistOutput(new PeraCRM_CampaignSubmissionV1($data),$binding,$output,$cipher));
    // Rollback both insertion errors and COMMIT errors; never leave an output receipt.
    [$b,$o,$s,$m]=output_fixture($blog,'rollback'); $store->bindAttempt($s,$b,$o);
    foreach (['insert','commit'] as $failure) {
        $fault=static fn($sql)=>($failure==='commit' && $sql==='COMMIT') || ($failure==='insert' && str_starts_with($sql,'INSERT INTO') && str_contains($sql,'submission_outputs')) ? 'INVALID_OUTPUT_FIXTURE' : $sql;
        add_filter('query',$fault); output_reject(fn()=>$store->persistOutput($s,$b,$o,$cipher)); remove_filter('query',$fault);
        output_reject(fn()=>$store->recoverOutput($b,$m,$cipher));
    }
    $store->persistOutput($s,$b,$o,output_cipher(true));
    [$rotated]=$store->recoverOutput($b,$m,new PeraCRM_CampaignOutputCipher(['e2'=>str_repeat('b',32)],'e2'));
    output_expect($rotated->hash()===$s->hash(),'new records use active rotated key without requiring old key');
    $wpdb->query('START TRANSACTION');
    output_reject(fn()=>$store->recoverOutput($binding,$metadata,$cipher));
    output_expect($wpdb->query('SAVEPOINT output_outer_survives')!==false,'caller transaction preserved'); $wpdb->query('ROLLBACK');
    $store->changeIdentity(19001,1,$binding['mailbox'],false);
    [$historical]=$store->recoverOutput($binding,$metadata,$cipher);
    output_expect($historical->hash()===$submission->hash() && $before===$read(),'revocation preserves historical recovery without rewriting output');
    output_expect(!peracrm_campaign_queue_dispatch_allowed(),'dispatch remains disabled');
});
