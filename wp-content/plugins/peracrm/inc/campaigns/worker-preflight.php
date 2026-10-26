<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/sending-budget.php';
require_once __DIR__.'/suppression-sync.php';

/** Explicit internal dependencies. No factory, hooks or production dispatch. */
final class PeraCRM_Campaign_Worker_Preflight
{
    private PeraCRM_Sending_Budget $budget;
    private Closure $clock;
    public function __construct(PeraCRM_Sending_Budget $budget,Closure $clock)
    {
        $this->budget=$budget; $this->clock=$clock;
    }
    public function requireScope(PeraCRM_Campaign_Worker_Preparation $preparation): void
    {
        $pins=$this->budget->scope(); $scope=$preparation->scope();
        if ($pins['account_id']!==$scope['account_id'] || $pins['region']!==$scope['region']
            || $pins['deployment']!==$preparation->deployment() || $pins['blog_id']!==get_current_blog_id()) {
            throw new RuntimeException('worker_preflight_scope');
        }
    }
    /** Called under the worker transaction BEFORE mailbox locks. The checkpoint
     * lock serializes final authorization with sync restart/failure/page commits.
     * Freshness comes from the injected trusted wall clock, never health().
     */
    public function requireSync(): void
    {
        PeraCRM_SES_Suppression_Sync::requireComplete($this->budget->scope(),($this->clock)());
    }
    public function reserve(array $claim): void { $this->budget->reserve($claim['attempt_key'],$claim['owner_token']); }
    public function begin(array $claim): void { $this->budget->beginSubmission($claim['attempt_key'],$claim['owner_token']); }
    public function requireSubmission(array $claim): void { $this->budget->requireSubmission($claim['attempt_key'],$claim['owner_token']); }
    public function record(array $claim,array $evidence): void
    {
        $this->budget->recordSubmission($claim['attempt_key'],$claim['owner_token'],$evidence[0],$evidence[1]);
    }
    public function settle(array $claim,array $evidence): void
    {
        $this->budget->settleSubmission($claim['attempt_key'],$claim['owner_token'],$evidence[0],$evidence[1]);
    }
    public function recover(array $claim): void
    {
        $this->requireSync();
        $this->budget->recoverSubmission($claim['attempt_key'],$claim['owner_token']);
    }
    public function requireRecoveryReadiness(array $claim): void
    {
        $this->budget->requireRecoveryReadiness($claim['attempt_key'],$claim['owner_token']);
    }
    public static function evidence(PeraCRM_CampaignSubmissionV1 $submission,array $result): array
    {
        $code=$result['code']??'submission_unknown';
        if (!in_array($code,['provider_accepted','provider_rejected','provider_rate_limit','preflight_denied'],true)) {
            $code='submission_unknown';
        }
        return [$code,hash('sha256',json_encode(['worker-outcome-v1',$submission->hash(),$code,
            peracrm_campaign_worker_result($result)],JSON_THROW_ON_ERROR))];
    }
}
