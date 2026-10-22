<?php
if (!defined('ABSPATH')) { exit; }

/** Unwired service contracts. No secret discovery, persistence, routes or delivery authority. */
function peracrm_campaign_recipient_require($ok): void
{
    if (!$ok) { throw new InvalidArgumentException('recipient_service_unavailable'); }
}

/** Trusted frozen identity plus current revocation epoch; never put this projection in a URL. */
function peracrm_campaign_recipient_binding(#[\SensitiveParameter] array $binding): string
{
    $keys = array_keys($binding); sort($keys);
    peracrm_campaign_recipient_require($keys === ['attempt_key','blog_id','mailbox','snapshot_hash','subscriber_id','token_epoch']);
    foreach (['blog_id','subscriber_id','token_epoch'] as $key) {
        peracrm_campaign_recipient_require(is_int($binding[$key]) && $binding[$key] > 0);
    }
    peracrm_campaign_recipient_require(is_string($binding['mailbox']) && strlen($binding['mailbox']) <= 254
        && filter_var($binding['mailbox'], FILTER_VALIDATE_EMAIL) !== false
        && !preg_match('/[^\x21-\x7e]/', $binding['mailbox']));
    foreach (['attempt_key','snapshot_hash'] as $key) {
        peracrm_campaign_recipient_require(is_string($binding[$key]) && preg_match('/\A[a-f0-9]{64}\z/', $binding[$key]) === 1);
    }
    // Copy values in a fixed order, removing caller-held references.
    $ordered = [];
    foreach ($keys as $key) { $ordered[$key] = $binding[$key]; }
    return json_encode($ordered, JSON_THROW_ON_ERROR);
}

/**
 * HMAC-SHA256 with deployment/purpose domain separation. The bearer contains only
 * protocol/key versions, purpose, times and random nonce; identities stay external.
 * The required callback must consult trusted revocation/identity state, returning
 * literal true only for an active token digest and binding (including epoch).
 * No default key, WP salt, production factory, locator or issuance hook exists.
 */
final class PeraCRM_CampaignRecipientTokens
{
    private $keys;
    private $active;
    private $deployment;
    private $activeBinding;

    public function __construct(#[\SensitiveParameter] array $keys, string $active, string $deployment, callable $activeBinding)
    {
        peracrm_campaign_recipient_require(!isset($this->keys));
        peracrm_campaign_recipient_require(count($keys) > 0 && count($keys) <= 8
            && preg_match('/\A[a-zA-Z0-9_-]{1,64}\z/', $deployment) === 1 && isset($keys[$active]));
        $this->keys = [];
        foreach ($keys as $id => $secret) {
            peracrm_campaign_recipient_require(is_string($id) && preg_match('/\Ak[0-9]{1,8}\z/', $id) === 1
                && is_string($secret) && strlen($secret) >= 32 && strlen($secret) <= 128);
            $this->keys[$id] = $secret;
        }
        $this->active = $active;
        $this->deployment = $deployment;
        $this->activeBinding = $activeBinding;
    }

    private function key(string $id, string $purpose): string
    {
        peracrm_campaign_recipient_require(in_array($purpose, ['preferences','unsubscribe'], true) && isset($this->keys[$id]));
        return hash_hmac('sha256', 'peracrm-recipient-v1|'.$this->deployment.'|'.$purpose, $this->keys[$id], true);
    }

    public function issue(string $purpose, #[\SensitiveParameter] array $binding, int $now): string
    {
        peracrm_campaign_recipient_require($now > 0 && $now <= PHP_INT_MAX - 86400);
        $context = peracrm_campaign_recipient_binding($binding);
        $key = $this->key($this->active, $purpose);
        // Preferences: 24 hours. Withdrawal: no time expiry, independent of job state.
        $expires = $purpose === 'preferences' ? $now + 86400 : 0;
        $body = implode('.', ['pc1', $this->active, $purpose, $now, $expires, bin2hex(random_bytes(24))]);
        return $body.'.'.hash_hmac('sha256', $body.'|'.$context, $key);
    }

    public function verify(#[\SensitiveParameter] string $token, string $purpose, #[\SensitiveParameter] array $binding, int $now): bool
    {
        try {
            peracrm_campaign_recipient_require($now > 0 && strlen($token) <= 256);
            $context = peracrm_campaign_recipient_binding($binding);
            peracrm_campaign_recipient_require(preg_match('/\Apc1\.(k[0-9]{1,8})\.(preferences|unsubscribe)\.([1-9][0-9]{0,18})\.(0|[1-9][0-9]{0,18})\.([a-f0-9]{48})\.([a-f0-9]{64})\z/', $token, $m) === 1);
            peracrm_campaign_recipient_require($m[2] === $purpose && (string)(int)$m[3] === $m[3] && (string)(int)$m[4] === $m[4]);
            $issued = (int)$m[3]; $expires = (int)$m[4];
            peracrm_campaign_recipient_require($issued <= $now && ($purpose === 'preferences'
                ? ($issued <= PHP_INT_MAX - 86400 && $expires === $issued + 86400 && $now < $expires)
                : $expires === 0));
            $body = substr($token, 0, strrpos($token, '.'));
            peracrm_campaign_recipient_require(hash_equals(hash_hmac('sha256', $body.'|'.$context, $this->key($m[1], $purpose)), $m[6]));
            return ($this->activeBinding)(hash('sha256', $token), $purpose, $binding,
                ['protocol'=>'pc1','deployment'=>$this->deployment,'key_id'=>$m[1],'issued'=>$issued,'expires'=>$expires]) === true;
        } catch (Throwable $error) { return false; }
    }

    public function __debugInfo(): array { return ['service'=>'peracrm_recipient_tokens', 'redacted'=>true]; }
    public function __serialize(): array { throw new LogicException('recipient_service_unavailable'); }
    public function __unserialize(array $data): void { throw new LogicException('recipient_service_unavailable'); }
}

/** Public editor supports explicit language tags and the two existing topic choices. */
function peracrm_campaign_recipient_preferences_profile(array $profile): array
{
    $fields = array_keys($profile); sort($fields);
    peracrm_campaign_recipient_require($fields === ['language','topics']);
    $language = $profile['language']; $topics = $profile['topics'];
    peracrm_campaign_recipient_require(is_string($language) && strlen($language) <= 35
        && ($language === '' || preg_match('/\A[a-zA-Z]{2,8}(?:-[a-zA-Z0-9]{1,8})*\z/',$language) === 1)
        && is_array($topics) && array_is_list($topics) && count($topics) <= 2);
    foreach ($topics as $topic) { peracrm_campaign_recipient_require(is_string($topic) && in_array($topic,['news','properties'],true)); }
    peracrm_campaign_recipient_require(count(array_unique($topics)) === count($topics));
    sort($topics);
    return ['language'=>strtolower($language),'topics'=>$topics];
}
