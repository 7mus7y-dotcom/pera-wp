<?php
require __DIR__.'/campaign-snapshots-test.php';
require __DIR__.'/output-recovery-fixture.php';
[$binding,$output,$submission,$metadata] = output_fixture(2);
$cipher = output_cipher(); $payload = json_encode(['envelope'=>$submission->fields(),'output'=>$output->fields()],JSON_THROW_ON_ERROR);
$context = json_encode($metadata); $sealed = $cipher->seal($payload,$context);
$reject = static function (callable $fn): void {
    try { $fn(); } catch (Throwable $e) {
        campaign_expect($e->getMessage() === 'recipient_output_unavailable' && $e->getPrevious() === null,'fixed unchained cipher failure'); return;
    }
    throw new RuntimeException('Expected output rejection');
};
campaign_expect($cipher->open($sealed,$context) === $payload,'exact UTF8/CRLF/body/bearer envelope recovery');
campaign_expect(output_cipher(true)->open($sealed,$context) === $payload,'rotation retains old decrypt key');
$new = output_cipher(true)->seal($payload,$context);
campaign_expect($new['key_id'] === 'e2' && $sealed !== $cipher->seal($payload,$context),'active version and fresh nonces');
$reject(fn()=>(new PeraCRM_CampaignOutputCipher(['e2'=>str_repeat('b',32)],'e2'))->open($sealed,$context));
$reject(fn()=>(new PeraCRM_CampaignOutputCipher(['e1'=>str_repeat('z',32)],'e1'))->open($sealed,$context));
$reject(fn()=>new PeraCRM_CampaignOutputCipher([],'e1'));
$reject(fn()=>new PeraCRM_CampaignOutputCipher(['e1'=>'short'],'e1'));
$reject(fn()=>$cipher->open($sealed,$context.'changed'));
foreach (['key_id'=>'e2','nonce'=>base64_encode(str_repeat('x',24)),'ciphertext'=>base64_encode(str_repeat('x',32))] as $key=>$value) {
    $reject(fn()=>$cipher->open(array_replace($sealed,[$key=>$value]),$context));
}
$reject(fn()=>serialize($cipher)); $reject(fn()=>$cipher->__construct(['e1'=>str_repeat('z',32)],'e1'));
ob_start(); var_dump($cipher); $debug = ob_get_clean();
campaign_expect(!str_contains($debug,str_repeat('a',32)) && !str_contains($debug,'bearer'),'cipher diagnostics redact keys and content');
echo "PASS output cipher integrity, scope, rotation and exact bytes\n";

// Exact cipher boundary, including tag/base64 overhead; one extra payload byte denies.
$bytes = str_repeat('x',PeraCRM_CampaignOutputCipher::MAX_PAYLOAD_BYTES);
$record = $cipher->seal($bytes,$context);
campaign_expect(strlen($record['ciphertext']) === PeraCRM_CampaignOutputCipher::MAX_CIPHERTEXT_BYTES
    && $cipher->open($record,$context) === $bytes,'exact encoded ciphertext boundary recovers');
unset($record);
$reject(fn()=>$cipher->seal($bytes.'x',$context));
unset($bytes);
foreach (['unicode','control'] as $kind) {
    foreach ([false,true] as $oversized) {
        [$b,$o,$s,$m] = output_size_fixture(2,$kind,$oversized);
        $encoded = json_encode(['envelope'=>$s->fields(),'output'=>$o->fields()],JSON_THROW_ON_ERROR);
        campaign_expect((strlen($encoded)>PeraCRM_CampaignOutputCipher::MAX_PAYLOAD_BYTES)===$oversized,
            'fixture crosses encoded payload cap: '.$kind.($oversized?' rejected':' accepted'));
        if ($oversized) { $reject(fn()=>$cipher->seal($encoded,$context)); }
        else {
            $record=$cipher->seal($encoded,$context);
            campaign_expect($cipher->open($record,$context)===$encoded,'near-limit encoded byte recovery: '.$kind);
            unset($record);
        }
        unset($b,$o,$s,$m,$encoded);
    }
}
echo "PASS output encoded size boundary and JSON-expanding Unicode/control bodies\n";
