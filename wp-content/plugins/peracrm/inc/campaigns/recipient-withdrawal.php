<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/recipient-persistence.php';

/** Explicitly injected verification keys. No provisioning, issuance or production factory. */
final class PeraCRM_CampaignRecipientWithdrawal
{
    private $store;
    private $keys;
    private $verifier;
    private $deployment;
    private bool $internalOnly;

    public function __construct(PeraCRM_CampaignRecipientPersistence $store, #[\SensitiveParameter] array $keys, string $deployment, bool $internalOnly=false)
    {
        peracrm_campaign_recipient_require(!isset($this->store));
        $this->verifier = new PeraCRM_CampaignRecipientTokens($keys, (string)array_key_first($keys), $deployment, [$store,'activeBinding']);
        $this->store = $store; $this->keys = $keys;
        $this->deployment = $deployment; $this->internalOnly=$internalOnly;
    }

    /** Read-only display authorization. Never acquire the inserting mailbox mutex. */
    private function valid(#[\SensitiveParameter] string $token): bool
    {
        try {
            if (strlen($token) > 256) { return false; }
            $data = $this->store->locate(hash('sha256', $token));
            return $data !== null && $data['purpose'] === 'unsubscribe'
                && (!$this->internalOnly || ($data['binding']['mailbox']??null)==='info@peraproperty.com')
                && $this->verifier->verify($token, 'unsubscribe', $data['binding'], time());
        } catch (Throwable $error) { return false; }
    }

    /** Request mapping only. Authentication/atomic commit belong to the store.
     * Machine body is raw, so duplicate and PHP-normalized field names cannot
     * accidentally authorize withdrawal. Human confirmation is a separate form.
     */
    public function request(string $method, bool $machine, #[\SensitiveParameter] string $token, string $contentType, #[\SensitiveParameter] $body): array
    {
        $failed = [400, 'This request could not be completed. Please use the original withdrawal link.'];
        if (!in_array($method, $machine ? ['POST'] : ['GET','HEAD','POST'], true)) { return [405, 'Method unavailable.']; }
        if ($method === 'GET' || $method === 'HEAD') {
            return $this->valid($token) ? [200, 'confirm'] : $failed;
        }
        $type = strtolower(trim(explode(';', $contentType)[0]));
        $confirmed = $type === 'application/x-www-form-urlencoded' && ($machine
            ? $body === 'List-Unsubscribe=One-Click'
            : is_array($body) && ($body['confirm'] ?? null) === 'withdraw');
        if (!$confirmed || ($this->internalOnly && !$this->valid($token)) || !$this->store->withdrawToken($token, $this->keys, $this->deployment)) { return $failed; }
        return [200, 'Your permission to receive marketing email has been withdrawn.'];
    }
    public function __debugInfo(): array { return ['service'=>'peracrm_recipient_withdrawal','redacted'=>true]; }
    public function __serialize(): array { throw new LogicException('recipient_service_unavailable'); }
    public function __unserialize(array $data): void { throw new LogicException('recipient_service_unavailable'); }
}

/** Explicit deployment-bound verification only. No token issuance, send authority,
 * credential discovery or caller-provided mailbox. Off unless configured. */
function peracrm_campaign_recipient_withdrawal_service(): ?PeraCRM_CampaignRecipientWithdrawal
{
    if (!defined('PERACRM_CAMPAIGN_WITHDRAWAL_HTTP') || PERACRM_CAMPAIGN_WITHDRAWAL_HTTP!==true) { return null; }
    try {
        require_once __DIR__.'/deployment.php';
        $deployment=peracrm_campaign_deployment_service();
        if (!$deployment) { return null; }
        return $deployment->internalWithdrawal();
    } catch (Throwable $error) { return null; }
}

/** Both production and disposable HTTP fixtures use this exact response boundary. */
function peracrm_campaign_recipient_withdrawal_respond(?PeraCRM_CampaignRecipientWithdrawal $service, bool $machine): void
{
    peracrm_subscribers_private_headers();
    header("Content-Security-Policy: default-src 'none'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    header('Content-Type: text/html; charset=UTF-8');
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    // Explicit route authority check precedes any token read or mutation.
    if (!peracrm_subscribers_on_authoritative_domain()) { status_header(400); echo 'Request unavailable.'; return; }
    $raw = $machine || $method !== 'POST' ? ($_GET['token'] ?? '') : ($_POST['token'] ?? '');
    $token = is_string($raw) ? wp_unslash($raw) : '';
    // Bound raw machine input; never log/echo it, even on parser failure.
    $body = $machine ? file_get_contents('php://input', false, null, 0, 128) : $_POST;
    [$status, $message] = $service === null ? [503, 'This service is unavailable.']
        : $service->request($method, $machine, $token, (string)($_SERVER['CONTENT_TYPE'] ?? ''), $body);
    status_header($status);
    if ($status === 405) { header('Allow: '.($machine ? 'POST' : 'GET, HEAD, POST')); }
    if ($method === 'HEAD') { return; }
    if ($machine) { echo $status === 200 ? 'Withdrawal recorded.' : 'Request unavailable.'; return; }
    // No theme hooks, scripts, analytics, stylesheets or third-party assets.
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="referrer" content="no-referrer"><title>Email withdrawal</title></head><body><main><h1>Email withdrawal</h1>';
    if ($message === 'confirm') {
        echo '<p>Opening this page makes no changes. Confirm to stop all marketing email.</p><form method="post" action="'.esc_url(home_url('/email-unsubscribe/')).'">';
        echo '<input type="hidden" name="token" value="'.esc_attr($token).'"><button type="submit" name="confirm" value="withdraw">Unsubscribe from all marketing email</button></form>';
    } else { echo '<p role="status">'.esc_html($message).'</p>'; }
    echo '</main></body></html>';
}
