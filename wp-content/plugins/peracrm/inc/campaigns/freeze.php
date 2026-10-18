<?php
if (!defined('ABSPATH')) { exit; }

/** No routes/workers. Each call is one bounded transaction; callers retain returned version. */
function peracrm_campaign_freeze_counts()
{
    return array_fill_keys(['eligible', 'suppression', 'consent', 'linked_client', 'language', 'topic', 'rules', 'duplicate'], 0);
}

/** @internal Draft is always the first lock, shared with draft saves and content capture. */
function peracrm_campaign_freeze_lock($campaign_id, $nonce)
{
    global $wpdb;
    if (!is_int($campaign_id) || $campaign_id < 1 || !is_string($nonce)
        || !wp_verify_nonce($nonce, 'peracrm_campaign_freeze_' . $campaign_id)) { throw new InvalidArgumentException('Invalid freeze request.'); }
    $draft = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . peracrm_table('peracrm_campaign_drafts') . ' WHERE id=%d FOR UPDATE', $campaign_id), ARRAY_A);
    peracrm_campaigns_db_require(true);
    if (!$draft) { throw new UnexpectedValueException(); }
    return $draft;
}

/** @internal Never resolves current content sources or inserts personal data into HTML. */
function peracrm_campaign_freeze_binding($draft, $attempt)
{
    global $wpdb;
    $snapshot = peracrm_campaign_snapshot_get((int)$draft['id'], (int)$attempt['snapshot_id']);
    $configuration = $wpdb->get_var('SELECT revision FROM ' . peracrm_table('peracrm_campaign_configuration') . ' WHERE id=1 FOR UPDATE');
    peracrm_campaigns_db_require(true);
    $content = $snapshot['payload']['content'] ?? [];
    $doc = peracrm_campaign_draft_document(json_decode($draft['document_json'], true));
    if (!$snapshot || (int)$draft['revision'] !== (int)$attempt['draft_revision']
        || (int)$snapshot['draft_revision'] !== (int)$attempt['draft_revision']
        || (int)$snapshot['actor_user_id'] !== (int)$attempt['actor_user_id']
        || !hash_equals($snapshot['content_hash'], $attempt['content_hash'])
        || (int)$configuration !== (int)$attempt['configuration_revision']
        || ($content['configuration_revision'] ?? null) !== (int)$attempt['configuration_revision']
        || ($content['document_hash'] ?? '') !== hash('sha256', peracrm_campaign_snapshot_json($doc))
        || peracrm_campaign_snapshot_json($content['rules'] ?? []) !== $attempt['rules_json']) { throw new UnexpectedValueException(); }
    return $snapshot;
}

function peracrm_campaign_freeze_start($campaign_id, $revision, $snapshot_id, $content_hash, $document, $nonce)
{
    return peracrm_campaign_draft_transaction(static function () use ($campaign_id, $revision, $snapshot_id, $content_hash, $document, $nonce) {
        global $wpdb;
        $draft = peracrm_campaign_freeze_lock($campaign_id, $nonce);
        if (!is_int($revision) || $revision < 1 || !is_int($snapshot_id) || $snapshot_id < 1 || !is_string($content_hash)
            || (int)$draft['revision'] !== $revision || $draft['status'] !== 'draft'
            || peracrm_campaign_draft_document($document) !== peracrm_campaign_draft_document(json_decode($draft['document_json'], true))) { throw new UnexpectedValueException(); }
        $table = peracrm_table('peracrm_campaign_freeze_attempts');
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE campaign_id=%d AND draft_revision=%d FOR UPDATE", $campaign_id, $revision), ARRAY_A);
        peracrm_campaigns_db_require(true);
        // Terminal revisions stay fenced independently of retained or newly captured content.
        if ($existing && $existing['state'] !== 'materializing') { throw new UnexpectedValueException(); }
        $snapshot = peracrm_campaign_snapshot_get($campaign_id, $snapshot_id);
        $now = gmdate('Y-m-d H:i:s');
        $attempt = ['campaign_id'=>$campaign_id, 'draft_revision'=>$revision, 'snapshot_id'=>$snapshot_id, 'content_hash'=>$content_hash,
            'configuration_revision'=>(int)($snapshot['payload']['content']['configuration_revision'] ?? 0),
            'rules_json'=>peracrm_campaign_snapshot_json($snapshot['payload']['content']['rules'] ?? []),
            'actor_user_id'=>peracrm_get_actor_user_id(), 'updated_by'=>peracrm_get_actor_user_id(), 'state'=>'materializing', 'version'=>1,
            'boundary_id'=>0, 'last_id'=>0, 'complete'=>0, 'considered'=>0,
            'counts_json'=>wp_json_encode(peracrm_campaign_freeze_counts()), 'created_at'=>$now, 'updated_at'=>$now];
        peracrm_campaign_freeze_binding($draft, $attempt);
        if ($existing) {
            foreach (['snapshot_id','content_hash','actor_user_id'] as $key) { if ((string)$existing[$key] !== (string)$attempt[$key]) { throw new UnexpectedValueException(); } }
            return $existing; // Lost start response, same immutable binding and original boundary.
        }
        $attempt['boundary_id'] = (int)$wpdb->get_var('SELECT COALESCE(MAX(id),0) FROM ' . peracrm_table('peracrm_subscribers'));
        peracrm_campaigns_db_require(true);
        peracrm_campaigns_db_require($wpdb->insert($table, $attempt));
        $attempt['id'] = (int)$wpdb->insert_id;
        return $attempt;
    });
}

/** @internal Serializes with start/save/cancel/publish. Stale versions never advance progress. */
function peracrm_campaign_freeze_operation($campaign_id, $attempt_id, $version, $nonce, $operation)
{
    return peracrm_campaign_draft_transaction(static function () use ($campaign_id, $attempt_id, $version, $nonce, $operation) {
        global $wpdb;
        $draft=peracrm_campaign_freeze_lock($campaign_id, $nonce);
        if (!is_int($attempt_id) || $attempt_id < 1 || !is_int($version) || $version < 1) { throw new InvalidArgumentException('Invalid attempt version.'); }
        $table=peracrm_table('peracrm_campaign_freeze_attempts');
        $attempt=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE campaign_id=%d AND id=%d FOR UPDATE", $campaign_id, $attempt_id), ARRAY_A);
        peracrm_campaigns_db_require(true);
        if (!$attempt || (int)$attempt['version'] !== $version || (int)$attempt['actor_user_id'] !== peracrm_get_actor_user_id()) { throw new UnexpectedValueException(); }
        $result=$operation($draft, $attempt);
        $result['updated_by']=peracrm_get_actor_user_id(); $result['version']=$version+1; $result['updated_at']=gmdate('Y-m-d H:i:s');
        $update=array_intersect_key($result, array_flip(['state','version','last_id','complete','considered','counts_json','updated_at','updated_by']));
        peracrm_campaigns_db_require($wpdb->update($table, $update, ['id'=>$attempt_id, 'version'=>$version]));
        return $result;
    });
}

function peracrm_campaign_freeze_continue($campaign_id, $attempt_id, $version, $nonce, $limit=200)
{
    if (!is_int($limit) || $limit < 1 || $limit > 200) { return new WP_Error('campaign_invalid', 'Batch size must be between 1 and 200.'); }
    $result=peracrm_campaign_freeze_operation($campaign_id, $attempt_id, $version, $nonce, static function ($draft, $attempt) use ($limit) {
        global $wpdb;
        if ($attempt['state'] !== 'materializing') { throw new UnexpectedValueException(); }
        peracrm_campaign_freeze_binding($draft, $attempt);
        if ((int)$attempt['complete']) { return $attempt; }
        $rows=peracrm_campaign_audience_batch((int)$attempt['last_id'], (int)$attempt['boundary_id'], $limit+1);
        $counts=json_decode($attempt['counts_json'], true);
        $rules=json_decode($attempt['rules_json'], true); unset($rules['version']);
        // Bounded existing-mailbox set also handles a subscriber changing to an already processed mailbox.
        $mailboxes=array_column(array_slice($rows,0,$limit), 'normalized_email');
        $seen=[];
        if ($mailboxes) {
            $sql='SELECT mailbox FROM '.peracrm_table('peracrm_campaign_frozen_recipients').' WHERE attempt_id=%d AND mailbox IN ('.implode(',',array_fill(0,count($mailboxes),'%s')).')';
            $seen=array_fill_keys($wpdb->get_col($wpdb->prepare($sql, array_merge([(int)$attempt['id']],$mailboxes))),true);
            peracrm_campaigns_db_require(true);
        }
        foreach (array_slice($rows,0,$limit) as $row) {
            $reason=peracrm_campaigns_eligibility_reason($row, ['post_type'=>$row['linked_type'],'post_status'=>$row['linked_status'],'disposition'=>$row['linked_disposition']], $rules);
            $mailbox=peracrm_campaigns_normalize_email($row['normalized_email']);
            if ($reason==='eligible' && isset($seen[$mailbox])) { $reason='duplicate'; }
            $now=gmdate('Y-m-d H:i:s');
            // Current template needs only explicit language. No client email substitution or profile lookup.
            $personalization=wp_json_encode(['language'=>$row['preferred_language']]);
            $data=['attempt_id'=>(int)$attempt['id'],'subscriber_id'=>(int)$row['id'],'client_id'=>$row['client_id'],
                'mailbox'=>$mailbox,'personalization_json'=>$personalization,'evaluated_at'=>$now,'evaluation_version'=>1];
            peracrm_campaigns_db_require($wpdb->insert(peracrm_table('peracrm_campaign_recipient_evaluations'), $data+['reason'=>$reason,'subscriber_version'=>(int)$row['version']]));
            if ($reason==='eligible') {
                peracrm_campaigns_db_require($wpdb->insert(peracrm_table('peracrm_campaign_frozen_recipients'), $data));
                $seen[$mailbox]=true;
            }
            $counts[$reason]++; $attempt['considered']++; $attempt['last_id']=(int)$row['id'];
        }
        $attempt['complete']=count($rows)<=$limit ? 1 : 0;
        $attempt['counts_json']=wp_json_encode($counts);
        return $attempt;
    });
    // An SQL failure rolls the entire batch back. Fence a recoverable failure in a separate bounded operation.
    // Authorization/conflict/validation failures never change another request's state.
    if (is_wp_error($result) && $result->get_error_code()==='campaign_unavailable') {
        peracrm_campaign_freeze_operation($campaign_id,$attempt_id,$version,$nonce,static function ($draft,$attempt) {
            if ($attempt['state']!=='materializing') { throw new UnexpectedValueException(); }
            $attempt['state']='failed'; return $attempt;
        });
    }
    return $result;
}

function peracrm_campaign_freeze_resume($campaign_id, $attempt_id, $version, $nonce)
{
    return peracrm_campaign_freeze_operation($campaign_id,$attempt_id,$version,$nonce,static function ($draft,$attempt) {
        if ($attempt['state']!=='failed') { throw new UnexpectedValueException(); }
        peracrm_campaign_freeze_binding($draft,$attempt);
        $attempt['state']='materializing'; return $attempt;
    });
}

function peracrm_campaign_freeze_cancel($campaign_id, $attempt_id, $version, $nonce)
{
    return peracrm_campaign_freeze_operation($campaign_id,$attempt_id,$version,$nonce,static function ($draft,$attempt) {
        if (!in_array($attempt['state'],['materializing','failed'],true)) { throw new UnexpectedValueException(); }
        $attempt['state']='cancelled'; return $attempt;
    });
}

function peracrm_campaign_freeze_publish($campaign_id, $attempt_id, $version, $nonce)
{
    return peracrm_campaign_freeze_operation($campaign_id,$attempt_id,$version,$nonce,static function ($draft,$attempt) {
        global $wpdb;
        if ($attempt['state']!=='materializing' || !(int)$attempt['complete']) { throw new UnexpectedValueException(); }
        peracrm_campaign_freeze_binding($draft,$attempt);
        $counts=peracrm_campaign_freeze_counts();
        $rows=$wpdb->get_results($wpdb->prepare('SELECT reason,COUNT(*) AS total FROM '.peracrm_table('peracrm_campaign_recipient_evaluations').' WHERE attempt_id=%d GROUP BY reason',$attempt['id']),ARRAY_A);
        peracrm_campaigns_db_require(true);
        foreach ($rows as $row) { if (!array_key_exists($row['reason'],$counts)) { throw new UnexpectedValueException(); } $counts[$row['reason']]=(int)$row['total']; }
        $members=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.peracrm_table('peracrm_campaign_frozen_recipients').' WHERE attempt_id=%d',$attempt['id']));
        peracrm_campaigns_db_require(true);
        if ($counts!==json_decode($attempt['counts_json'],true) || array_sum($counts)!==(int)$attempt['considered'] || $members!==$counts['eligible'] || $members===0) { throw new UnexpectedValueException(); }
        // Recheck exhaustion; never publish merely because a caller supplied a progress flag.
        if (peracrm_campaign_audience_batch((int)$attempt['last_id'],(int)$attempt['boundary_id'],1)) { throw new UnexpectedValueException(); }
        peracrm_campaigns_db_require($wpdb->insert(peracrm_table('peracrm_campaign_frozen_revisions'),[
            'campaign_id'=>(int)$attempt['campaign_id'],'draft_revision'=>(int)$attempt['draft_revision'], 'attempt_id'=>(int)$attempt['id'],
            'snapshot_id'=>(int)$attempt['snapshot_id'],'content_hash'=>$attempt['content_hash'],'counts_json'=>$attempt['counts_json'],
            'published_by'=>peracrm_get_actor_user_id(),'published_at'=>gmdate('Y-m-d H:i:s')]));
        peracrm_campaigns_db_require($wpdb->update(peracrm_table('peracrm_campaign_drafts'),['status'=>'frozen'],['id'=>(int)$draft['id']]));
        $attempt['state']='frozen'; return $attempt;
    });
}

/** Authorized, bounded reads. Published output is stored capture bytes, never rerendered. */
function peracrm_campaign_freeze_get($campaign_id, $attempt_id)
{
    peracrm_campaign_drafts_require(true);
    return peracrm_with_target_blog(static function () use ($campaign_id,$attempt_id) {
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_freeze_attempts').' WHERE campaign_id=%d AND id=%d',$campaign_id,$attempt_id),ARRAY_A);
        peracrm_campaigns_db_require(true); return $row;
    });
}

function peracrm_campaign_frozen_get($campaign_id, $revision)
{
    peracrm_campaign_drafts_require(true);
    return peracrm_with_target_blog(static function () use ($campaign_id,$revision) {
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_frozen_revisions').' WHERE campaign_id=%d AND draft_revision=%d',$campaign_id,$revision),ARRAY_A);
        peracrm_campaigns_db_require(true);
        if ($row) {
            $row['content']=peracrm_campaign_snapshot_get($campaign_id,(int)$row['snapshot_id']);
            if (!$row['content'] || !hash_equals($row['content_hash'],$row['content']['content_hash'])) { throw new RuntimeException('Frozen content is unavailable.'); }
        }
        return $row;
    });
}

function peracrm_campaign_freeze_recipients($campaign_id, $attempt_id, $after=0, $evaluations=false)
{
    peracrm_campaign_drafts_require(true);
    return peracrm_with_target_blog(static function () use ($campaign_id,$attempt_id,$after,$evaluations) {
        global $wpdb;
        if (!peracrm_campaign_freeze_get($campaign_id,$attempt_id)) { return []; }
        $table=peracrm_table($evaluations ? 'peracrm_campaign_recipient_evaluations' : 'peracrm_campaign_frozen_recipients');
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE attempt_id=%d AND id>%d ORDER BY id LIMIT 200",$attempt_id,max(0,(int)$after)),ARRAY_A);
        peracrm_campaigns_db_require(true); return $rows;
    });
}

/** Administrator-only manual retention: fence abandoned attempts before bounded deletion.
 * One request deletes at most 100 child rows. The cancelled attempt is retained
 * as the durable campaign/revision tombstone, including after complete cleanup. Captures and
 * all published/consent/suppression history are retained. No scheduled cleanup.
 */
function peracrm_campaign_freeze_cleanup($campaign_id, $attempt_id, $nonce)
{
    return peracrm_campaign_draft_transaction(static function () use ($campaign_id,$attempt_id,$nonce) {
        global $wpdb;
        peracrm_campaign_freeze_lock($campaign_id,$nonce);
        $table=peracrm_table('peracrm_campaign_freeze_attempts');
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE campaign_id=%d AND id=%d FOR UPDATE",$campaign_id,$attempt_id),ARRAY_A);
        peracrm_campaigns_db_require(true);
        if (!$row || $row['state']==='frozen' || $row['updated_at']>=gmdate('Y-m-d H:i:s',time()-30*DAY_IN_SECONDS)) { throw new UnexpectedValueException(); }
        $published=$wpdb->get_var($wpdb->prepare('SELECT id FROM '.peracrm_table('peracrm_campaign_frozen_revisions').' WHERE attempt_id=%d',$attempt_id));
        peracrm_campaigns_db_require(true);
        if ($published) { throw new UnexpectedValueException(); }
        if ($row['state']!=='cancelled') {
            peracrm_campaigns_db_require($wpdb->update($table,['state'=>'cancelled','version'=>(int)$row['version']+1,'updated_by'=>peracrm_get_actor_user_id()],['id'=>$attempt_id]));
        }
        $remaining=100;
        foreach (['peracrm_campaign_recipient_evaluations','peracrm_campaign_frozen_recipients'] as $name) {
            if (!$remaining) { break; }
            $deleted=$wpdb->query($wpdb->prepare('DELETE FROM '.peracrm_table($name).' WHERE attempt_id=%d LIMIT %d',$attempt_id,$remaining));
            peracrm_campaigns_db_require($deleted); $remaining-=$deleted;
        }
        // Never remove the unique campaign/revision fence. Empty retries are no-ops;
        // cancellation remains terminal even while the original capture is retained.
        return ['deleted_rows'=>100-$remaining,'deleted_attempt'=>false,'complete'=>$remaining===100];
    },true);
}
