<?php
if (!defined('ABSPATH')) { exit; }
require_once dirname(__DIR__, 3).'/vendor-ses/autoload.php';

/** Explicit deployment-supplied identity only. No acquisition, refresh or discovery. */
final class PeraCRM_Campaign_SES_Credentials implements PeraCRM\SESDeps\Aws\Credentials\CredentialsInterface
{
    private string $account;
    private string $key;
    private string $secret;
    private ?string $token;
    private ?int $expires;

    public function __construct(string $account, #[SensitiveParameter] string $key, #[SensitiveParameter] string $secret,
        #[SensitiveParameter] ?string $token = null, ?int $expires = null)
    {
        if (isset($this->account) || preg_match('/\A[0-9]{12}\z/', $account) !== 1
            || preg_match('/\A[A-Z0-9]{16,128}\z/', $key) !== 1
            || preg_match('/\A[A-Za-z0-9\/+_=]{16,256}\z/', $secret) !== 1
            || ($token !== null && (strlen($token) < 1 || strlen($token) > 16384 || preg_match('/[\x00-\x20\x7f]/', $token)))
            || ($expires !== null && $expires <= time() + 20)) {
            throw new InvalidArgumentException('ses_identity_unavailable');
        }
        $this->account = $account; $this->key = $key; $this->secret = $secret;
        $this->token = $token; $this->expires = $expires;
    }

    public function account(): string { return $this->account; }
    public function getAccessKeyId() { return $this->key; }
    public function getSecretKey() { return $this->secret; }
    public function getSecurityToken() { return $this->token; }
    public function getExpiration() { return $this->expires; }
    public function isExpired() { return $this->expires !== null && time() + 20 >= $this->expires; }
    /** This optional SDK projection is deliberately unavailable to diagnostics. */
    public function toArray() { throw new LogicException('ses_identity_projection_disabled'); }
    public function __debugInfo(): array { return ['identity'=>'[redacted]']; }
    public function __serialize(): array { throw new LogicException('ses_identity_serialization_disabled'); }
    public function __unserialize(array $data): void { throw new LogicException('ses_identity_serialization_disabled'); }
    private function __clone() {}
}
