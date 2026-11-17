<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/event-processing.php';
require_once __DIR__.'/ses-operator.php';

/** Explicit CLI maintenance. No send, consent or token authority.
 * SNS bodies: 90 days after receipt AND last processing. Standalone import
 * count summaries: 12 calendar months after expiry. All durable fences survive.
 */
final class PeraCRM_Campaign_Retention
{
    public function __construct(private array $binding)
    {
        $keys=array_keys($binding); sort($keys);
        if ($keys!==['blog','policy','scope'] || !is_int($binding['blog']) || $binding['blog']<1
            || !is_string($binding['scope']) || preg_match('/\A[a-f0-9]{64}\z/',$binding['scope'])!==1
            || !is_string($binding['policy']) || preg_match('/\A[a-f0-9]{64}\z/',$binding['policy'])!==1) {
            throw new InvalidArgumentException('retention_configuration');
        }
    }
    private static function db($result)
    {
        global $wpdb;
        if ($result===false || $wpdb->last_error!=='') { throw new RuntimeException('retention_storage'); }
        return $result;
    }
    private function session(int $connection,string $guard): void
    {
        global $wpdb;
        if ((int)self::db($wpdb->get_var('SELECT CONNECTION_ID()'))!==$connection
            || self::db($wpdb->get_var('SELECT @peracrm_retention_guard'))!==$guard) { throw new RuntimeException('retention_session'); }
    }
    public function run(bool $apply=false,int $limit=25): array
    {
        PeraCRM_SES_Operator::authorize();
        if ($limit<1 || $limit>25 || get_current_blog_id()!==$this->binding['blog']
            || (is_multisite() && (int)peracrm_get_target_blog_id()!==$this->binding['blog'])
            || !peracrm_campaigns_schema_ready()) { throw new RuntimeException('retention_scope'); }
        global $wpdb;
        $quiet=$wpdb->suppress_errors(true); $started=false;
        try {
            if ($apply) {
                if ((int)self::db($wpdb->get_var('SELECT @@autocommit'))!==1) { throw new RuntimeException('retention_transaction'); }
                self::db($wpdb->query('SAVEPOINT peracrm_retention_probe'));
                if ($wpdb->query('RELEASE SAVEPOINT peracrm_retention_probe')!==false
                    || $wpdb->last_error!=='SAVEPOINT peracrm_retention_probe does not exist') { throw new RuntimeException('retention_transaction'); }
                self::db($wpdb->query('START TRANSACTION')); $started=true;
                self::db($wpdb->query('SAVEPOINT peracrm_retention_owned'));
                $connection=(int)self::db($wpdb->get_var('SELECT CONNECTION_ID()'));
                if ($connection<1) { throw new RuntimeException('retention_session'); }
                self::db($wpdb->query('RELEASE SAVEPOINT peracrm_retention_owned'));
                $guard=bin2hex(random_bytes(32));
                self::db($wpdb->query($wpdb->prepare('SET @peracrm_retention_guard=IF(CONNECTION_ID()=%d,%s,NULL)',$connection,$guard)));
                $this->session($connection,$guard);
            }
            $cutoffs=self::db($wpdb->get_row('SELECT UTC_TIMESTAMP() AS now_utc,
                UTC_TIMESTAMP()-INTERVAL 90 DAY AS diagnostics_before,
                UTC_TIMESTAMP()-INTERVAL 12 MONTH AS summaries_before',ARRAY_A));
            if (!$cutoffs) { throw new RuntimeException('retention_storage'); }
            $r=peracrm_table('peracrm_campaign_sns_receipts'); $p=peracrm_table('peracrm_campaign_ses_progress');
            $n=peracrm_table('peracrm_campaign_ses_normalizations'); $b=peracrm_table('peracrm_subscriber_batches');
            $lock=$apply?' FOR UPDATE':'';
            // Manual/conflicting/unresolved work and recent activity are held.
            $receipts=self::db($wpdb->get_results($wpdb->prepare("SELECT r.* FROM {$r} r
                JOIN {$p} p ON p.scope_hash=r.scope_hash AND p.receipt_id=r.message_id
                JOIN {$n} n ON n.scope_hash=r.scope_hash AND n.receipt_id=r.message_id
                WHERE r.scope_hash=%s AND p.policy_hash=%s AND n.policy_hash=%s
                  AND n.payload_hash=r.payload_hash AND n.normalization_version='ses-v1'
                  AND n.quarantine_reason<>'event_conflict' AND p.state IN ('completed','quarantined')
                  AND r.authentication_version='sns-v2-sha256'
                  AND r.received_at<%s AND p.last_attempt_at<%s
                  AND (p.last_redrive_at IS NULL OR p.last_redrive_at<%s)
                ORDER BY r.received_at,r.message_id LIMIT %d{$lock}",
                $this->binding['scope'],$this->binding['policy'],$this->binding['policy'],
                $cutoffs['diagnostics_before'],$cutoffs['diagnostics_before'],$cutoffs['diagnostics_before'],$limit),ARRAY_A));
            // IDs, terminal status and totals remain. Import payloads are already
            // removed by terminal import handling, independently of this command.
            $batches=self::db($wpdb->get_results($wpdb->prepare("SELECT id FROM {$b}
                WHERE status IN ('complete','partial_failed','expired','cancelled')
                  AND expires_at<%s AND summary_json<>'{}'
                ORDER BY expires_at,id LIMIT %d{$lock}",$cutoffs['summaries_before'],$limit),ARRAY_A));
            foreach ($receipts as $receipt) {
                if (!peracrm_sns_receipt_integrity($receipt)) { throw new RuntimeException('retention_integrity'); }
            }
            if ($apply) {
                foreach ($receipts as $receipt) {
                    $this->session($connection,$guard);
                    $changed=self::db($wpdb->query($wpdb->prepare("UPDATE {$r}
                        SET canonical_payload='',authentication_version='sns-v2-retired'
                        WHERE scope_hash=%s AND message_id=%s AND payload_hash=%s
                          AND authentication_version='sns-v2-sha256'
                          AND CONNECTION_ID()=%d AND @peracrm_retention_guard=%s",
                        $receipt['scope_hash'],$receipt['message_id'],$receipt['payload_hash'],$connection,$guard)));
                    if ($changed!==1) { throw new RuntimeException('retention_conflict'); }
                }
                foreach ($batches as $batch) {
                    $this->session($connection,$guard);
                    $changed=self::db($wpdb->query($wpdb->prepare("UPDATE {$b} SET summary_json='{}'
                        WHERE id=%s AND CONNECTION_ID()=%d AND @peracrm_retention_guard=%s",
                        $batch['id'],$connection,$guard)));
                    if ($changed!==1) { throw new RuntimeException('retention_conflict'); }
                }
                $this->session($connection,$guard);
                self::db($wpdb->query('COMMIT')); $started=false;
                $this->session($connection,$guard);
            }
            return ['state'=>'completed','dry_run'=>!$apply,'checked_at'=>$cutoffs['now_utc'],
                'diagnostics_before'=>$cutoffs['diagnostics_before'],'summaries_before'=>$cutoffs['summaries_before'],
                'batch_limit_per_category'=>$limit,'sns_bodies'=>count($receipts),'import_count_summaries'=>count($batches),
                'counts_are_bounded'=>true,'protected_evidence_preserved'=>true,
                'dispatch_enabled'=>peracrm_campaign_queue_dispatch_allowed()];
        } finally {
            if ($started) { $wpdb->query('ROLLBACK'); }
            $wpdb->suppress_errors($quiet);
        }
    }
}

function peracrm_campaign_retention(bool $apply=false,int $limit=25): array
{
    return peracrm_with_target_blog(static function () use ($apply,$limit) {
        $processing=peracrm_ses_processing_service();
        if (!$processing) { throw new RuntimeException('retention_configuration'); }
        return (new PeraCRM_Campaign_Retention($processing->retentionBinding()))->run($apply,$limit);
    });
}
