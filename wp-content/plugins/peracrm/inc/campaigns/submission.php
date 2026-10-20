<?php
if (!defined('ABSPATH')) { exit; }

/** Fixed diagnostics only: never include rejected content, addresses or bearer URLs. */
function peracrm_campaign_submission_require(bool $valid): void
{
    if (!$valid) { throw new InvalidArgumentException('invalid_submission'); }
}

function peracrm_campaign_submission_string($value, int $max, bool $header = false): bool
{
    return is_string($value) && $value !== '' && strlen($value) <= $max
        && preg_match('//u', $value) === 1 && strpos($value, "\0") === false
        && (!$header || !preg_match('/[\x00-\x1f\x7f]/', $value));
}

function peracrm_campaign_submission_mailbox($value): bool
{
    return peracrm_campaign_submission_string($value, 254, true)
        && filter_var($value, FILTER_VALIDATE_EMAIL) !== false
        && !preg_match('/[^\x21-\x7e]/', $value);
}

/** Immutable value, not a send authorization. No renderer or live configuration lookup. */
final class PeraCRM_CampaignSubmissionV1
{
    private $data;

    public function __construct(array $data)
    {
        $keys = ['version','scope','blog_id','campaign_id','job_id','revision_id','snapshot_id',
            'recipient_id','snapshot_hash','delivery_key','attempt_key','personalization_version',
            'mailbox','subject','sender_name','sender_email','reply_to','html','text','timeout_seconds'];
        $actual = array_keys($data); sort($actual); sort($keys);
        peracrm_campaign_submission_require($keys === $actual && $data['version'] === 1
            && $data['personalization_version'] === 1 && $data['timeout_seconds'] === 20);
        foreach (['blog_id','campaign_id','job_id','revision_id','snapshot_id','recipient_id'] as $key) {
            peracrm_campaign_submission_require(is_int($data[$key]) && $data[$key] > 0);
        }
        foreach (['snapshot_hash','delivery_key','attempt_key'] as $key) {
            peracrm_campaign_submission_require(is_string($data[$key]) && preg_match('/\A[a-f0-9]{64}\z/', $data[$key]) === 1);
        }
        self::validateScope($data['scope']);
        foreach (['mailbox','sender_email','reply_to'] as $key) {
            peracrm_campaign_submission_require(peracrm_campaign_submission_mailbox($data[$key]));
        }
        foreach (['subject'=>998,'sender_name'=>200,'html'=>1048576,'text'=>1048576] as $key=>$max) {
            peracrm_campaign_submission_require(peracrm_campaign_submission_string($data[$key], $max, in_array($key, ['subject','sender_name'], true)));
            // V1 has no interpolation implementation. Typed recipient URL slots are dependent work.
            peracrm_campaign_submission_require(strpos($data[$key], '{{') === false && strpos($data[$key], '}}') === false);
        }
        peracrm_campaign_submission_require(strlen($data['html']) + strlen($data['text']) <= 1572864);
        // Canonical field order; scope order is also fixed before hashing.
        $ordered = [];
        foreach ($keys as $key) { $ordered[$key] = $data[$key]; }
        $ordered['scope'] = [];
        foreach (['provider','account_id','region','configuration_set','configuration_version'] as $key) {
            $ordered['scope'][$key] = $data['scope'][$key]; // strip caller-held PHP references too
        }
        $this->data = $ordered;
    }

    public static function validateScope($scope): void
    {
        peracrm_campaign_submission_require(is_array($scope));
        $keys = array_keys($scope); sort($keys);
        peracrm_campaign_submission_require($keys === ['account_id','configuration_set','configuration_version','provider','region']
            && $scope['provider'] === 'ses_v2'
            && is_string($scope['account_id']) && preg_match('/\A[0-9]{12}\z/', $scope['account_id']) === 1
            && is_string($scope['region']) && in_array($scope['region'], ['eu-west-1','eu-west-2','eu-west-3','eu-central-1','eu-north-1','us-east-1','us-east-2','us-west-2'], true)
            && is_string($scope['configuration_set']) && preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $scope['configuration_set']) === 1
            && is_int($scope['configuration_version']) && $scope['configuration_version'] > 0);
    }

    /** Copy-on-write; never return references. Contains sensitive transport input. */
    public function fields(): array { return $this->data; }
    public function hash(): string { return hash('sha256', json_encode($this->data, JSON_THROW_ON_ERROR)); }
    public function __debugInfo(): array { return ['version'=>1, 'redacted'=>true]; }
    public function __serialize(): array { throw new LogicException('submission_serialization_disabled'); }
    public function __unserialize(array $data): void { throw new LogicException('submission_serialization_disabled'); }
}

/**
 * Narrow background read boundary, deliberately abstract and not bootstrapped.
 * No production source or supported deliverable renderer exists in this PR.
 * Implementations must authorize the exact inflight job/delivery/attempt under
 * worker authority (target blog, owner/version, lease, gate and final eligibility),
 * then load ONLY its bound rows. Never use staff snapshot_get or current drafts.
 */
abstract class PeraCRM_Campaign_Frozen_Content_Resolver
{
    abstract protected function authorize(array $binding): void;
    /**
     * job, revision, preparation, recipient, snapshot, delivery: trusted bound
     * storage projections with native integer IDs/booleans, not raw SQL strings.
     * The projection must not invent publication, completeness or content purpose.
     */
    abstract protected function load(array $binding): array;
    /** Default deny until a separately reviewed deliverable capture/decoder exists. */
    protected function supportsRenderer(string $version): bool { return false; }

    final public function resolve(array $binding, array $scope): PeraCRM_CampaignSubmissionV1
    {
        try {
            $this->authorize($binding); // before any storage read, including malformed calls
            $rows = $this->load($binding);
            foreach (['job','revision','preparation','recipient','snapshot','delivery'] as $name) {
                peracrm_campaign_submission_require(is_array($rows[$name] ?? null));
            }
            $job=$rows['job']; $rev=$rows['revision']; $prep=$rows['preparation'];
            $member=$rows['recipient']; $snapshot=$rows['snapshot']; $delivery=$rows['delivery'];
            peracrm_campaign_submission_require(($delivery['state'] ?? null) === 'inflight'
                && is_int($delivery['subscriber_id'] ?? null) && $delivery['subscriber_id'] > 0
                && ($member['subscriber_id'] ?? null) === $delivery['subscriber_id']);
            foreach (['blog_id','campaign_id','job_id','revision_id','snapshot_id','recipient_id','snapshot_hash','mailbox','delivery_key','attempt_key'] as $key) {
                peracrm_campaign_submission_require(isset($binding[$key]) && ($delivery[$key] ?? null) === $binding[$key]);
            }
            peracrm_campaign_submission_require(($job['id'] ?? null) === $binding['job_id']
                && ($job['complete'] ?? null) === true && ($job['state'] ?? null) === 'queued'
                && ($rev['id'] ?? null) === $binding['revision_id'] && ($rev['published'] ?? null) === true
                && ($prep['complete'] ?? null) === true && is_int($prep['id'] ?? null) && $prep['id'] > 0
                && $prep['id'] === ($rev['preparation_id'] ?? null)
                && ($member['preparation_id'] ?? null) === $prep['id'] && ($member['id'] ?? null) === $binding['recipient_id']
                && ($member['mailbox'] ?? null) === $binding['mailbox']
                && ($snapshot['id'] ?? null) === $binding['snapshot_id']);
            foreach ([$job,$rev,$snapshot,$prep] as $row) {
                foreach (['blog_id','campaign_id','snapshot_id','snapshot_hash'] as $key) {
                    peracrm_campaign_submission_require(($row[$key] ?? null) === $binding[$key]);
                }
            }
            peracrm_campaign_submission_require(($job['revision_id'] ?? null) === $binding['revision_id']
                && ($job['preparation_id'] ?? null) === $prep['id']);
            $content = $snapshot['content'] ?? [];
            // Existing schema_version=1 / unpublished_content review artifacts always fail.
            peracrm_campaign_submission_require(($snapshot['purpose'] ?? null) === 'deliverable'
                && ($content['schema'] ?? null) === 'delivery_content_v1'
                && is_string($content['renderer_version'] ?? null) && $this->supportsRenderer($content['renderer_version'])
                && hash_equals($binding['snapshot_hash'], hash('sha256', json_encode($content, JSON_THROW_ON_ERROR))));
            $data = array_intersect_key($binding, array_flip(['blog_id','campaign_id','job_id','revision_id','snapshot_id','recipient_id','snapshot_hash','mailbox','delivery_key','attempt_key']));
            foreach (['subject','sender_name','sender_email','reply_to','html','text'] as $key) { $data[$key] = $content[$key] ?? null; }
            $this->authorize($binding); // reading/decoding must not outlive worker authority
            return new PeraCRM_CampaignSubmissionV1($data + ['version'=>1,'scope'=>$scope,'personalization_version'=>1,'timeout_seconds'=>20]);
        } catch (Throwable $e) {
            // Storage/authority exceptions can themselves contain SQL or PII. Do not chain them.
            throw new RuntimeException('frozen_content_unavailable');
        }
    }
}
