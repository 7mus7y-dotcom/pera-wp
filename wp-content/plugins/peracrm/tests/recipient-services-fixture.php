<?php
/** Shared synthetic-only assertions; callable under standalone PHP and actual WordPress. */
function peracrm_test_recipient_services(array $content, callable $expect, int $blogId = 2): void
{
    require_once dirname(__DIR__).'/inc/campaigns/recipient-expansion.php';
    $reject = static function (callable $fn) use ($expect) {
        try { $fn(); } catch (Throwable $e) {
            $expect($e->getMessage() === 'recipient_service_unavailable' && $e->getPrevious() === null, 'fixed unchained diagnostic'); return;
        }
        throw new RuntimeException('Expected recipient service rejection');
    };
    $hash = hash('sha256', wp_json_encode($content));
    $binding = ['blog_id'=>$blogId, 'subscriber_id'=>17, 'mailbox'=>'synthetic@example.org',
        'token_epoch'=>1, 'attempt_key'=>str_repeat('a',64), 'snapshot_hash'=>$hash];
    $active = true; $calls = 0;
    $check = static function ($digest, $purpose, $identity) use (&$active, &$calls, $expect) {
        ++$calls;
        $expect(preg_match('/\A[a-f0-9]{64}\z/', $digest) === 1 && in_array($purpose,['preferences','unsubscribe'],true)
            && $identity['subscriber_id'] === 17, 'revocation sees digest and trusted identity only');
        return $active;
    };
    // Explicit test material only. Deployment must provision independently random secrets.
    $old = str_repeat('synthetic-old-key-', 3); $new = str_repeat('synthetic-new-key-', 3);
    $service = new PeraCRM_CampaignRecipientTokens(['k1'=>$old], 'k1', 'fixture-deploy', $check);
    $now = 1800000000;
    $pref = $service->issue('preferences', $binding, $now);
    $withdraw = $service->issue('unsubscribe', $binding, $now);
    $expect($service->verify($pref, 'preferences', $binding, $now) && $service->verify($withdraw, 'unsubscribe', $binding, $now), 'both purposes verify');
    $expect(!str_contains($pref,'synthetic') && !str_contains($pref,'@') && !str_contains($withdraw,'example.org'), 'bearers contain no mailbox identity');
    $expect($pref !== $service->issue('preferences',$binding,$now), 'independent nonce per issuance');
    $expect(!$service->verify($pref,'unsubscribe',$binding,$now) && !$service->verify($withdraw,'preferences',$binding,$now), 'purpose separation');
    foreach (['blog_id'=>$blogId+1,'subscriber_id'=>18,'mailbox'=>'other@example.org','token_epoch'=>2,'attempt_key'=>str_repeat('b',64),'snapshot_hash'=>str_repeat('c',64)] as $key=>$value) {
        $expect(!$service->verify($withdraw,'unsubscribe',array_replace($binding,[$key=>$value]),$now), 'reject wrong '.$key);
    }
    foreach ([0,4,8,22,strlen($withdraw)-1] as $offset) {
        $bad = $withdraw; $bad[$offset] = $bad[$offset] === 'a' ? 'b' : 'a';
        $expect(!$service->verify($bad,'unsubscribe',$binding,$now), 'tampered token rejected');
    }
    foreach (['', $withdraw.'.extra', strtoupper($withdraw), str_repeat('a',257), str_replace('.0.','.1.',$withdraw)] as $bad) {
        $expect(!$service->verify($bad,'unsubscribe',$binding,$now), 'malformed bearer rejected');
    }
    $expect(!$service->verify($pref,'preferences',$binding,$now-1), 'future issuance rejected');
    $expect($service->verify($pref,'preferences',$binding,$now+86399) && !$service->verify($pref,'preferences',$binding,$now+86400), '24-hour preference boundary');
    $expect($service->verify($withdraw,'unsubscribe',$binding,$now+86400*365*20), 'preference expiry leaves withdrawal valid');
    $active = false;
    $expect(!$service->verify($withdraw,'unsubscribe',$binding,$now), 'digest revocation denies');
    $active = 1;
    $expect(!$service->verify($withdraw,'unsubscribe',$binding,$now), 'nonboolean revocation response denies');
    $active = true;
    $unavailable = new PeraCRM_CampaignRecipientTokens(['k1'=>$old],'k1','fixture-deploy',static function () { throw new RuntimeException('secret'); });
    $expect(!$unavailable->verify($withdraw,'unsubscribe',$binding,$now), 'revocation outage denies');
    $rotated = new PeraCRM_CampaignRecipientTokens(['k1'=>$old,'k2'=>$new],'k2','fixture-deploy',$check);
    $expect($rotated->verify($withdraw,'unsubscribe',$binding,$now) && str_starts_with($rotated->issue('unsubscribe',$binding,$now),'pc1.k2.'), 'rotation retains verification and uses new issuer');
    $removed = new PeraCRM_CampaignRecipientTokens(['k2'=>$new],'k2','fixture-deploy',$check);
    $expect(!$removed->verify($withdraw,'unsubscribe',$binding,$now), 'retired key denies');
    $wrongKey = new PeraCRM_CampaignRecipientTokens(['k1'=>$new],'k1','fixture-deploy',$check);
    $otherDeploy = new PeraCRM_CampaignRecipientTokens(['k1'=>$old],'k1','fixture-clone',$check);
    $expect(!$wrongKey->verify($withdraw,'unsubscribe',$binding,$now) && !$otherDeploy->verify($withdraw,'unsubscribe',$binding,$now), 'wrong key and deployment deny');
    $reject(fn()=>new PeraCRM_CampaignRecipientTokens([], 'k1','fixture-deploy',$check));
    $reject(fn()=>new PeraCRM_CampaignRecipientTokens(['k1'=>'short'], 'k1','fixture-deploy',$check));
    $reject(fn()=>$service->issue('unknown',$binding,$now));
    $reject(fn()=>serialize($service));
    ob_start(); var_dump($service); $debug = ob_get_clean();
    $expect(!str_contains($debug,$old) && !str_contains($debug,'synthetic@example'), 'service debug redacted');
    foreach (['http://example.org','https://example.org/','https://example.org:443','https://user@example.org','https://example.org?x=y','https://example.org#x','https://127.0.0.1','https://127.1','https://0x7f000001','https://localhost','https://host.internal','https://example.org.','https://EXAMPLE.org','https://example.org\\evil',"https://example.org\n"] as $origin) {
        $reject(fn()=>new PeraCRM_CampaignRecipientExpansion($origin,$service));
    }
    $expander = new PeraCRM_CampaignRecipientExpansion('https://example.org',$service);
    $before = wp_json_encode($content);
    $result = $expander->expand($content,$hash,$binding,$now);
    $fields = $result->fields();
    $expect($fields['snapshot_hash'] === $hash && $fields['personalization_version'] === 1 && !str_contains($fields['html'],'{{') && !str_contains($fields['text'],'{{'), 'resolved output retains approval hash');
    foreach (['preferences','unsubscribe'] as $purpose) {
        $path = $purpose === 'preferences' ? '/email-preferences/' : '/email-unsubscribe/';
        preg_match('~https://example.org'.$path.'\?v=1&token=([^\s]+)~',$fields['text'],$m);
        $expect(isset($m[1]) && $service->verify(rawurldecode($m[1]),$purpose,$binding,$now), 'raw text URL has correct purpose and binding');
        $expect(str_contains($fields['html'],htmlspecialchars($m[0],ENT_QUOTES,'UTF-8')), 'HTML URL is attribute escaped');
    }
    $expect(!str_contains($fields['text'],'&amp;') && !str_contains($fields['html'],'?v=1&token='), 'separate HTML/text escaping');
    foreach (['html','text'] as $part) {
        $original = $content['output'][$part]; $expanded = $fields[$part];
        foreach ($content['slots'] as $name=>$slot) {
            $original = str_replace('{{'.$name.'}}','SLOT',$original);
            $expanded = preg_replace('~https://example.org/email-(?:preferences|unsubscribe)/\?v=1(?:&amp;|&)token=[a-zA-Z0-9._-]+~','SLOT',$expanded);
        }
        $expect($original === $expanded, 'only typed markers change in '.$part);
    }
    $stableHash = $result->hash(); $fields['html'] = 'mutated';
    $expect($result->hash() === $stableHash && $result->fields()['html'] !== 'mutated', 'result immutable copy');
    $expect($before === wp_json_encode($content) && hash('sha256',$before) === $hash, 'shared capture and hash unchanged');
    $second = $expander->expand($content,$hash,array_replace($binding,['subscriber_id'=>19,'mailbox'=>'second@example.org']),$now);
    $expect($second->hash() !== $stableHash && $result->hash() === $stableHash, 'distinct recipient output leaves first unchanged');
    ob_start(); var_dump($result); $debug = ob_get_clean();
    $expect(!str_contains($debug,'token=') && !str_contains($debug,$pref), 'output debug hides bearers');
    $reject(fn()=>serialize($result));
    $reject(fn()=>serialize($expander));
    $reject(fn()=>$expander->expand($content,str_repeat('0',64),$binding,$now));
    $bad = $content; $bad['output']['text'] .= 'changed';
    $reject(fn()=>$expander->expand($bad,$hash,$binding,$now));
    $variants = [];
    $bad = $content; $bad['slots'] = []; $variants[] = $bad;
    $bad = $content; $bad['slots']['unknown'] = reset($bad['slots']); $variants[] = $bad;
    $bad = $content; $bad['slots']['recipient_preferences_url']['purpose'] = 'unsubscribe'; $variants[] = $bad;
    foreach (['html','text'] as $part) {
        foreach (['', '{{unknown}}', '{{recipient_preferences_url}}{{recipient_preferences_url}}', '{recipient_preferences_url}}'] as $replacement) {
            $bad = $content; $bad['output'][$part] = str_replace('{{recipient_preferences_url}}',$replacement,$bad['output'][$part]); $variants[] = $bad;
        }
    }
    foreach (['<script>%s</script>','<!--%s-->','<title>%s</title>',"<div data-slot='%s'>wrapped</div>"] as $wrapper) {
        $bad = $content; $anchor = '<a href="{{recipient_preferences_url}}">Preferences</a>';
        $bad['output']['html'] = str_replace($anchor,sprintf($wrapper,$anchor),$bad['output']['html']); $variants[] = $bad;
    }
    $bad = $content; $bad['schema_version'] = 1; $variants[] = $bad;
    foreach ($variants as $bad) {
        $badHash = hash('sha256',wp_json_encode($bad));
        $reject(fn()=>$expander->expand($bad,$badHash,array_replace($binding,['snapshot_hash'=>$badHash]),$now));
    }
    $expect($calls > 0, 'verification always checks injected revocation after authentication');
}
