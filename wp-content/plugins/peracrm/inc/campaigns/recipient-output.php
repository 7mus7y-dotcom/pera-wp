<?php
if (!defined('ABSPATH')) { exit; }

/** Deployment-injected encryption keys, separate from token signing keys.
 * No default key, WP salt, secret lookup, production factory or fallback cipher.
 * Key IDs are permanent names for 32 random bytes; never reuse an ID.
 */
final class PeraCRM_CampaignOutputCipher
{
    // Base64 includes the 16-byte AEAD tag: 4 * ceil((payload + 16) / 3).
    // This is an encoded-storage contract, independent of raw body limits.
    public const MAX_CIPHERTEXT_BYTES = 12582912; // 12 MiB
    public const MAX_PAYLOAD_BYTES = 9437168; // (12 MiB / 4 * 3) - 16

    public static function requirePayload(#[\SensitiveParameter] string $bytes): void
    {
        if (strlen($bytes) > self::MAX_PAYLOAD_BYTES) {
            throw new RuntimeException('recipient_output_unavailable');
        }
    }

    private $keys;
    private $active;

    public function __construct(#[\SensitiveParameter] array $keys, string $active)
    {
        try {
            peracrm_campaign_recipient_require(!isset($this->keys)
                && function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')
                && count($keys) > 0 && count($keys) <= 32 && isset($keys[$active]));
            $copy = [];
            foreach ($keys as $id=>$key) {
                peracrm_campaign_recipient_require(is_string($id) && preg_match('/\Ae[0-9]{1,8}\z/',$id) === 1
                    && is_string($key) && strlen($key) === 32);
                $copy[$id] = (string)$key;
            }
            $this->keys = $copy; $this->active = $active;
        } catch (Throwable $e) { throw new RuntimeException('recipient_output_unavailable'); }
    }

    private function aad(string $context, string $id): string
    {
        return json_encode(['protocol'=>'peracrm_output_v1','key_id'=>$id,'context'=>$context],JSON_THROW_ON_ERROR);
    }
    public function seal(#[\SensitiveParameter] string $bytes, string $context): array
    {
        try {
            self::requirePayload($bytes);
            $nonce = random_bytes(24);
            return ['key_id'=>$this->active,'nonce'=>base64_encode($nonce),
                'ciphertext'=>base64_encode(sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                    $bytes,$this->aad($context,$this->active),$nonce,$this->keys[$this->active]))];
        } catch (Throwable $e) { throw new RuntimeException('recipient_output_unavailable'); }
    }
    public function open(#[\SensitiveParameter] array $record, string $context): string
    {
        try {
            $id = $record['key_id'] ?? null;
            peracrm_campaign_recipient_require(is_string($id) && isset($this->keys[$id])
                && is_string($record['nonce'] ?? null) && is_string($record['ciphertext'] ?? null)
                && strlen($record['ciphertext']) <= self::MAX_CIPHERTEXT_BYTES);
            $nonce = base64_decode($record['nonce'],true); $cipher = base64_decode($record['ciphertext'],true);
            peracrm_campaign_recipient_require(is_string($nonce) && strlen($nonce) === 24
                && base64_encode($nonce) === $record['nonce'] && is_string($cipher) && strlen($cipher) >= 16
                && base64_encode($cipher) === $record['ciphertext']);
            $bytes = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($cipher,$this->aad($context,$id),$nonce,$this->keys[$id]);
            peracrm_campaign_recipient_require(is_string($bytes));
            self::requirePayload($bytes);
            return $bytes;
        } catch (Throwable $e) { throw new RuntimeException('recipient_output_unavailable'); }
    }
    public function __debugInfo(): array { return ['service'=>'peracrm_output_cipher','redacted'=>true]; }
    public function __serialize(): array { throw new LogicException('recipient_output_unavailable'); }
    public function __unserialize(array $data): void { throw new LogicException('recipient_output_unavailable'); }
}
