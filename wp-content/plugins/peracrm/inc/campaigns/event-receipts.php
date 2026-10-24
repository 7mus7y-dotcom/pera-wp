<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/providers/sns.php';

/** Internal prerequisite. No endpoint, bootstrap, inner parsing, application or sending.
 * Mandatory verifier authenticates every request before storage. Returns only after
 * COMMIT; a future HTTP adapter must map dependency/storage to 503, never 2xx.
 */
final class PeraCRM_SNS_Receipts
{
    private PeraCRM_SNS_Verifier $verifier;
    private int $blog;
    private string $scope;
    private bool $busy=false;
    public function __construct(PeraCRM_SNS_Verifier $verifier, string $deployment, int $blog)
    {
        if (!preg_match('/\A[A-Za-z0-9_-]{1,64}\z/',$deployment) || $blog<=0) { throw new PeraCRM_SNS_Error('sns_configuration'); }
        $this->verifier=$verifier; $this->blog=$blog;
        $this->scope=hash('sha256',json_encode(['provider'=>'ses_sns','deployment'=>$deployment,'blog'=>$blog]+$verifier->scope(),JSON_THROW_ON_ERROR));
    }
    private static function db($value)
    {
        global $wpdb;
        if ($value===false || $wpdb->last_error!=='') { throw new PeraCRM_SNS_Error('sns_storage'); }
        return $value;
    }
    public function receive(#[\SensitiveParameter] string $body, int $now): string
    {
        $receipt=$this->verifier->verify($body,$now);
        global $wpdb;
        $started=false; $owned=false; $quiet=$wpdb->suppress_errors(true);
        try {
            if ($this->busy || get_current_blog_id()!==$this->blog
                || (is_multisite() && (int)peracrm_get_target_blog_id()!==$this->blog)
                || !peracrm_campaigns_schema_ready() || (int)self::db($wpdb->get_var('SELECT @@autocommit'))!==1) {
                throw new PeraCRM_SNS_Error('sns_storage');
            }
            self::db($wpdb->query('SAVEPOINT peracrm_sns_top_level_probe'));
            if ($wpdb->query('RELEASE SAVEPOINT peracrm_sns_top_level_probe')!==false) { throw new PeraCRM_SNS_Error('sns_storage'); }
            self::db($wpdb->query('START TRANSACTION')); $started=true; $this->busy=true; $owned=true;
            $connection=(int)self::db($wpdb->get_var('SELECT CONNECTION_ID()'));
            if ($connection<=0) { throw new PeraCRM_SNS_Error('sns_storage'); }
            $table=peracrm_table('peracrm_campaign_sns_receipts');
            $reason=$receipt['timestamp']>$now+300?'future_timestamp':($receipt['timestamp']<$now-86400?'stale_timestamp':'application_pending');
            self::db($wpdb->query($wpdb->prepare("INSERT INTO {$table}
                (scope_hash,message_id,payload_hash,canonical_payload,certificate_hash,authentication_version,quarantine_reason,received_at)
                VALUES (%s,%s,%s,%s,%s,'sns-v2-sha256',%s,%s) ON DUPLICATE KEY UPDATE message_id=message_id",
                $this->scope,$receipt['id'],$receipt['hash'],$receipt['canonical'],$receipt['certificate_hash'],$reason,gmdate('Y-m-d H:i:s',$now))));
            $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE scope_hash=%s AND message_id=%s FOR UPDATE",$this->scope,$receipt['id']),ARRAY_A);
            self::db($row?:false);
            if (!hash_equals($row['payload_hash'],$receipt['hash']) || $row['canonical_payload']!==$receipt['canonical']) {
                throw new PeraCRM_SNS_Error('sns_conflict');
            }
            // wpdb can transparently reconnect and replay COMMIT on a new session.
            // A successful empty COMMIT must never acknowledge a lost receipt.
            if ((int)self::db($wpdb->get_var('SELECT CONNECTION_ID()'))!==$connection) { throw new PeraCRM_SNS_Error('sns_storage'); }
            self::db($wpdb->query('COMMIT')); $started=false;
            if ((int)self::db($wpdb->get_var('SELECT CONNECTION_ID()'))!==$connection) { throw new PeraCRM_SNS_Error('sns_storage'); }
            return 'quarantined';
        } catch (Throwable $error) {
            if ($started) { $wpdb->query('ROLLBACK'); }
            throw new PeraCRM_SNS_Error($error instanceof PeraCRM_SNS_Error && $error->getMessage()==='sns_conflict'?'sns_conflict':'sns_storage');
        } finally {
            if ($owned) { $this->busy=false; }
            $wpdb->suppress_errors($quiet);
        }
    }
}
