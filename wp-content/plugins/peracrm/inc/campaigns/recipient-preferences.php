<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/recipient-withdrawal.php';

/** Injected keys only. Public edits cannot provision keys or issue recipient tokens. */
final class PeraCRM_CampaignRecipientPreferences
{
    private $store;
    private $keys;
    private $deployment;
    public function __construct(PeraCRM_CampaignRecipientPersistence $store, #[\SensitiveParameter] array $keys, string $deployment)
    {
        // Validate the same bounded key ring, even before the first request.
        new PeraCRM_CampaignRecipientTokens($keys,(string)array_key_first($keys),$deployment,[$store,'activeBinding']);
        $this->store=$store; $this->keys=$keys; $this->deployment=$deployment;
    }
    /** Stateless form proof: dedicated domain, bearer digest, expiry and nonce.
     * No cookie, login, WP guest nonce, registry write or extension of token expiry.
     */
    public function proof(#[\SensitiveParameter] string $token, int $now): string
    {
        $id=(string)array_key_first($this->keys);
        $body=$id.'.'.($now+900).'.'.bin2hex(random_bytes(16));
        return $body.'.'.hash_hmac('sha256','peracrm-preference-form-v1|'.$this->deployment.'|'.hash('sha256',$token).'|'.$body,$this->keys[$id]);
    }
    private function validProof(#[\SensitiveParameter] string $token, $proof, int $now): bool
    {
        if (!is_string($proof) || preg_match('/\A(k[0-9]{1,8})\.([1-9][0-9]{0,18})\.([a-f0-9]{32})\.([a-f0-9]{64})\z/',$proof,$m)!==1
            || !isset($this->keys[$m[1]]) || (string)(int)$m[2]!==$m[2] || (int)$m[2]<=$now || (int)$m[2]>$now+900) { return false; }
        $body=substr($proof,0,strrpos($proof,'.'));
        return hash_equals(hash_hmac('sha256','peracrm-preference-form-v1|'.$this->deployment.'|'.hash('sha256',$token).'|'.$body,$this->keys[$m[1]]),$m[4]);
    }
    public function request(string $method, #[\SensitiveParameter] string $token, string $contentType,
        #[\SensitiveParameter] array $body, string $origin, string $expectedOrigin, string $fetchSite): array
    {
        $failed=[400,'This request could not be completed. Please use the original preferences link.',null];
        if (!in_array($method,['GET','HEAD','POST'],true)) { return [405,'Method unavailable.',null]; }
        if (strlen($token)>256) { return $failed; }
        if ($method==='POST') {
            $fields=array_keys($body); sort($fields);
            if ($fields!==['language','proof','token','topics'] || ($body['token']??null)!==$token
                || strtolower(trim(explode(';',$contentType)[0]))!=='application/x-www-form-urlencoded'
                || !peracrm_campaign_recipient_preferences_same_origin($origin,$expectedOrigin,$fetchSite)
                || !$this->validProof($token,$body['proof']??null,time())) { return $failed; }
            try { $profile=peracrm_campaign_recipient_preferences_profile(['language'=>$body['language'],'topics'=>$body['topics']]); }
            catch (Throwable $error) { return $failed; }
            $data=$this->store->preferencesToken($token,$this->keys,$this->deployment,$profile);
            return $data===null ? $failed : [200,'Your preferences have been saved. This does not change global email permission.',$data];
        }
        $data=$this->store->preferencesToken($token,$this->keys,$this->deployment);
        return $data===null ? $failed : [200,'',$data];
    }
    /** Separate authority; no parsing, locating or verification of an expired preference token. */
    public function withdrawalAvailable(#[\SensitiveParameter] string $token): bool
    {
        return (new PeraCRM_CampaignRecipientWithdrawal($this->store,$this->keys,$this->deployment))
            ->request('GET',false,$token,'',[])[0]===200;
    }
    public function __debugInfo(): array { return ['service'=>'peracrm_recipient_preferences','redacted'=>true]; }
    public function __serialize(): array { throw new LogicException('recipient_service_unavailable'); }
    public function __unserialize(array $data): void { throw new LogicException('recipient_service_unavailable'); }
}

/** Blocked pending old-mailbox/deletion policy, host logs, keys, retention and restore readiness. */
function peracrm_campaign_recipient_preferences_service(): ?PeraCRM_CampaignRecipientPreferences { return null; }

function peracrm_campaign_recipient_preferences_respond(?PeraCRM_CampaignRecipientPreferences $service): void
{
    peracrm_subscribers_private_headers();
    header("Content-Security-Policy: default-src 'none'; style-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    header('Content-Type: text/html; charset=UTF-8');
    $method=$_SERVER['REQUEST_METHOD']??'';
    if (!peracrm_subscribers_on_authoritative_domain()) { status_header(400); if ($method!=='HEAD') { echo 'Request unavailable.'; } return; }
    $input=$method==='POST' ? wp_unslash($_POST) : wp_unslash($_GET);
    $token=is_string($input['token']??null) ? $input['token'] : '';
    $withdrawal=is_string($input['withdrawal_token']??null) ? $input['withdrawal_token'] : '';
    $url=peracrm_subscribers_preferences_url(); $parts=wp_parse_url($url);
    $origin=$parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    // Withdrawal authority is carried only for navigation, never submitted as an edit field.
    unset($input['withdrawal_token']);
    if ($method==='POST' && !isset($input['topics'])) { $input['topics']=[]; }
    [$status,$message,$data]=$service===null ? [503,'This service is unavailable.',null]
        : $service->request($method,$token,(string)($_SERVER['CONTENT_TYPE']??''),$input,
            (string)($_SERVER['HTTP_ORIGIN']??''),$origin,(string)($_SERVER['HTTP_SEC_FETCH_SITE']??''));
    status_header($status);
    if ($status===405) { header('Allow: GET, HEAD, POST'); }
    if ($method==='HEAD') { return; }
    $withdrawalURL=$service && $service->withdrawalAvailable($withdrawal)
        ? add_query_arg('token',$withdrawal,home_url('/email-unsubscribe/')) : '';
    $proof=$data!==null ? $service->proof($token,time()) : '';
    include __DIR__.'/../views/pages/recipient-preferences.php';
}

/** Fetch serializes Origin as null for a no-referrer form navigation in Chromium.
 * Accept that privacy-preserving case only with browser-enforced same-origin
 * Fetch Metadata. Every submission separately requires the bearer-bound proof.
 * Missing Origin, foreign origins, same-site and cross-site remain denied.
 */
function peracrm_campaign_recipient_preferences_same_origin(string $origin, string $expected, string $site): bool
{
    return $expected !== '' && in_array($site,['','same-origin'],true)
        && (($origin !== '' && hash_equals($expected,$origin)) || ($origin === 'null' && $site === 'same-origin'));
}
