<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/submission.php';

/** Internal durable evidence only. No transport, factory, endpoint or state resolution. */
final class PeraCRM_Campaign_Acceptance
{
    public static function namespace(string $deployment, int $blog, array $provider): string
    {
        return hash('sha256',json_encode(['deployment'=>$deployment,'blog'=>$blog,
            'provider'=>$provider['provider'],'account'=>$provider['account_id'],'region'=>$provider['region']],JSON_THROW_ON_ERROR));
    }
    private static function db($value)
    {
        global $wpdb;
        if ($value===false || $wpdb->last_error!=='') { throw new RuntimeException('acceptance_storage'); }
        return $value;
    }
    /** Caller owns transaction and attempt lock; injected writer fences SQL-session replacement.
     * Both response and event paths use this exact insert-or-compare contract.
     */
    public static function associate(array $data, callable $insert): string
    {
        global $wpdb;
        $insert('peracrm_campaign_provider_messages',$data);
        $table=peracrm_table('peracrm_campaign_provider_messages');
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE (provider_scope=%s AND message_id=%s)
            OR (deployment=%s AND blog_id=%d AND attempt_key=%s) FOR UPDATE",$data['provider_scope'],$data['message_id'],
            $data['deployment'],$data['blog_id'],$data['attempt_key']),ARRAY_A);
        self::db($rows?:false);
        if (count($rows)!==1) { return 'message_conflict'; }
        foreach (['provider_scope','message_id','deployment','blog_id','attempt_key','binding_hash'] as $key) {
            if ((string)$rows[0][$key] !== (string)$data[$key]) { return 'message_conflict'; }
        }
        return 'accepted';
    }
    public static function evidence(array $association, string $source, string $sourceKey, string $decision, callable $insert): void
    {
        $data=array_intersect_key($association,array_flip(['deployment','blog_id','attempt_key','binding_hash','provider_scope','message_id']));
        $data+=['source'=>$source,'source_key'=>$sourceKey,'decision'=>$decision,'recorded_at'=>gmdate('Y-m-d H:i:s')];
        $insert('peracrm_campaign_acceptance_evidence',$data);
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_acceptance_evidence')
            .' WHERE deployment=%s AND blog_id=%d AND attempt_key=%s AND source=%s AND source_key=%s FOR UPDATE',
            $data['deployment'],$data['blog_id'],$data['attempt_key'],$source,$sourceKey),ARRAY_A);
        self::db($row?:false);
        foreach ($data as $key=>$value) {
            if ($key!=='recorded_at' && (string)$row[$key] !== (string)$value) { throw new RuntimeException('acceptance_storage'); }
        }
    }
    /** Existing staff reconciliation additionally requires exact durable positive
     * evidence for prepared attempts. A verified negative claim cannot erase any
     * acceptance/conflict, and a missing row/lookup is never negative evidence.
     */
    public static function requireResolution(string $deployment, array $delivery, array $result): void
    {
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_submission_attempts')
            .' WHERE deployment=%s AND blog_id=%d AND attempt_key=%s FOR UPDATE',
            $deployment,get_current_blog_id(),$delivery['attempt_key']),ARRAY_A);
        self::db(true);
        if (!$row) { return; } // Existing abstract-provider fixtures have no prepared attempt.
        if ($result['kind']!=='accepted') {
            // No durable proof-of-non-acceptance adapter exists for prepared SES attempts.
            throw new RuntimeException('acceptance_evidence_required');
        }
        if ($row['delivery_key']!==$delivery['delivery_key'] || !hash_equals($row['binding_hash'],hash('sha256',$row['binding_json']))) {
            throw new RuntimeException('acceptance_storage');
        }
        $evidence=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_acceptance_evidence')
            .' WHERE deployment=%s AND blog_id=%d AND attempt_key=%s FOR UPDATE',
            $deployment,get_current_blog_id(),$delivery['attempt_key']),ARRAY_A);
        self::db($evidence);
        if (!$evidence) { throw new RuntimeException('acceptance_evidence_required'); }
        foreach ($evidence as $item) {
            if ($item['decision']!=='accepted' || $item['binding_hash']!==$row['binding_hash']
                || $item['message_id']!==$result['message_id']) { throw new RuntimeException('acceptance_conflict'); }
        }
    }
    /** Positive durable evidence or conflict fences every attempt of this delivery.
     * Lock attempts in stable order, shared with event/response writers. Absence grants
     * no authority: the worker must independently enforce state, owner and version.
     */
    public static function fenced(string $deployment, string $delivery): bool
    {
        global $wpdb;
        $attempts=peracrm_table('peracrm_campaign_submission_attempts');
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$attempts} WHERE deployment=%s AND blog_id=%d AND delivery_key=%s ORDER BY attempt_key FOR UPDATE",
            $deployment,get_current_blog_id(),$delivery),ARRAY_A);
        self::db($rows);
        foreach ($rows as $row) {
            if (!hash_equals($row['binding_hash'],hash('sha256',$row['binding_json']))) { throw new RuntimeException('acceptance_storage'); }
            $evidence=$wpdb->get_var($wpdb->prepare('SELECT source_key FROM '.peracrm_table('peracrm_campaign_acceptance_evidence')
                .' WHERE deployment=%s AND blog_id=%d AND attempt_key=%s LIMIT 1 FOR UPDATE',
                $row['deployment'],$row['blog_id'],$row['attempt_key']));
            self::db(true);
            if ($evidence!==null) { return true; }
            // Associations created before schema 34 retain authenticated event
            // provenance and must fence retries before any explicit evidence redrive.
            $message=$wpdb->get_var($wpdb->prepare('SELECT message_id FROM '.peracrm_table('peracrm_campaign_provider_messages')
                .' WHERE deployment=%s AND blog_id=%d AND attempt_key=%s AND binding_hash=%s FOR UPDATE',
                $row['deployment'],$row['blog_id'],$row['attempt_key'],$row['binding_hash']));
            self::db(true);
            if ($message!==null) { return true; }
        }
        return false;
    }
}
