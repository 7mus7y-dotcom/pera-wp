<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/recipient-tokens.php';
require_once __DIR__.'/renderer.php';

/** Immutable recipient copy. Contains bearers: explicit access only, no debug/serialization. */
final class PeraCRM_CampaignRecipientOutput
{
    private $html;
    private $text;
    private $snapshotHash;
    private $bindingHash;

    public function __construct(#[\SensitiveParameter] string $html, #[\SensitiveParameter] string $text, string $snapshotHash, string $bindingHash)
    {
        $this->html = $html; $this->text = $text;
        $this->snapshotHash = $snapshotHash; $this->bindingHash = $bindingHash;
    }

    public function fields(): array
    {
        return ['personalization_version'=>1, 'snapshot_hash'=>$this->snapshotHash,
            'recipient_binding_hash'=>$this->bindingHash, 'html'=>$this->html, 'text'=>$this->text];
    }

    public function hash(): string { return hash('sha256', json_encode($this->fields(), JSON_THROW_ON_ERROR)); }
    public function __debugInfo(): array { return ['value'=>'peracrm_recipient_output', 'redacted'=>true]; }
    public function __serialize(): array { throw new LogicException('recipient_service_unavailable'); }
    public function __unserialize(array $data): void { throw new LogicException('recipient_service_unavailable'); }
}

/**
 * Caller supplies an authoritative origin and stored approved content/hash after
 * authorization. A matching hash proves integrity, not staff/worker authority.
 * This service performs no storage reads/writes and is not bootstrapped.
 */
final class PeraCRM_CampaignRecipientExpansion
{
    private $origin;
    private $tokens;

    public function __construct(string $authoritativeOrigin, PeraCRM_CampaignRecipientTokens $tokens)
    {
        // Canonical HTTPS DNS origin only: no ports, credentials, paths, queries,
        // fragments, IDN ambiguity, numeric host aliases or request-host fallback.
        peracrm_campaign_recipient_require(strlen($authoritativeOrigin) <= 261
            && preg_match('~\Ahttps://(?=.{1,253}\z)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\z~', $authoritativeOrigin) === 1);
        $host = substr($authoritativeOrigin, 8);
        peracrm_campaign_recipient_require(!preg_match('/(?:\A|\.)(?:localhost|local|internal|invalid|test|onion)\z/', $host));
        $this->origin = $authoritativeOrigin;
        $this->tokens = $tokens;
    }

    public function expand(array $content, string $approvedHash, #[\SensitiveParameter] array $binding, int $now): PeraCRM_CampaignRecipientOutput
    {
        try {
            $bindingBytes = peracrm_campaign_recipient_binding($binding);
            // Use the exact snapshot encoding, never re-render/normalize captured content.
            $json = wp_json_encode($content);
            peracrm_campaign_recipient_require(is_string($json) && strlen($json) <= 2097152
                && preg_match('/\A[a-f0-9]{64}\z/', $approvedHash) === 1
                && hash_equals($approvedHash, hash('sha256', $json)) && $binding['snapshot_hash'] === $approvedHash
                && ($content['schema_version'] ?? null) === 2 && ($content['template_version'] ?? null) === 2
                && ($content['purpose'] ?? null) === 'deliverable_template'
                && ($content['renderer_version'] ?? null) === 'peracrm_email_v2' && ($content['slot_version'] ?? null) === 1);
            peracrm_campaign_deliverable_validate($content['output'] ?? [], $content['slots'] ?? []);
            $html = $content['output']['html']; $text = $content['output']['text'];
            // Slot-looking anchors inside inert/raw HTML contexts are never URL slots.
            peracrm_campaign_recipient_require(!preg_match('~<!--|<\s*(?:script|style|textarea|xmp|iframe|template)\b~i', $html));
            $slotHtml = preg_replace('~<title\b[^>]*>.*?</title\s*>~is', '', $html);
            peracrm_campaign_deliverable_validate(array_replace($content['output'], ['html'=>$slotHtml]), $content['slots']);
            foreach ($content['slots'] as $name => $slot) {
                $token = $this->tokens->issue($slot['purpose'], $binding, $now);
                // Fixed future paths; no route is registered by this service.
                $path = $slot['purpose'] === 'preferences' ? '/email-preferences/' : '/email-unsubscribe/';
                $url = $this->origin.$path.'?v=1&token='.rawurlencode($token);
                $marker = '{{'.$name.'}}';
                $anchor = '<a href="'.$marker.'">'.esc_html($slot['label']).'</a>';
                peracrm_campaign_recipient_require(self::atTagBoundary($html, strpos($html, $anchor)));
                $html = str_replace($anchor, '<a href="'.htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true).'">'.esc_html($slot['label']).'</a>', $html);
                $line = $slot['label']."\n".$marker."\n";
                $text = str_replace($line, $slot['label']."\n".$url."\n", $text);
            }
            peracrm_campaign_recipient_require(!peracrm_campaign_deliverable_has_slot([$html, $text]));
            return new PeraCRM_CampaignRecipientOutput($html, $text, $approvedHash, hash('sha256', $bindingBytes));
        } catch (Throwable $error) { throw new InvalidArgumentException('recipient_service_unavailable'); }
    }

    /** An exact anchor string inside another tag's quoted attribute is not a slot. */
    private static function atTagBoundary(string $html, $offset): bool
    {
        if (!is_int($offset)) { return false; }
        $tag = false; $quote = '';
        for ($i = 0; $i < $offset; ++$i) {
            $char = $html[$i];
            if ($quote !== '') { if ($char === $quote) { $quote = ''; } continue; }
            if ($tag && ($char === '"' || $char === "'")) { $quote = $char; continue; }
            if ($char === '<') { if ($tag) { return false; } $tag = true; }
            elseif ($char === '>') { $tag = false; }
        }
        return !$tag && $quote === '';
    }

    public function __debugInfo(): array { return ['service'=>'peracrm_recipient_expansion', 'redacted'=>true]; }
    public function __serialize(): array { throw new LogicException('recipient_service_unavailable'); }
    public function __unserialize(array $data): void { throw new LogicException('recipient_service_unavailable'); }
}
