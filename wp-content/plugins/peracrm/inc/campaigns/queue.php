<?php
if (!defined('ABSPATH')) { exit; }

/** Deliberately unconditional: database options, constants and staging clones cannot enable dispatch. */
function peracrm_campaign_queue_dispatch_allowed() { return false; }
function peracrm_campaign_queue_require_dispatch()
{
    return new WP_Error('campaign_dispatch_disabled', 'Campaign dispatch is not implemented or authorized.');
}

/** Explicit RFC3339 seconds with Z or numeric offset. No naive/local timezone interpretation. */
function peracrm_campaign_queue_schedule_utc($input)
{
    if (!is_string($input) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-](?:0\d|1[0-4]):[0-5]\d)$/D', $input)
        || str_ends_with($input, '-00:00') || preg_match('/[+-]14:(?!00)/', $input)) {
        throw new InvalidArgumentException('Use RFC3339 seconds with an explicit known UTC offset or Z.');
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', str_replace('Z', '+00:00', $input));
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $date->getTimestamp() <= time()) {
        throw new InvalidArgumentException('Use a valid future instant with an explicit offset.');
    }
    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

/** @internal Immutable published binding only; never consult current draft/configuration or sources. */
function peracrm_campaign_queue_binding($campaign_id, $revision_id, $job = null)
{
    global $wpdb;
    $revision = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_frozen_revisions').' WHERE campaign_id=%d AND id=%d', $campaign_id, $revision_id), ARRAY_A);
    peracrm_campaigns_db_require(true);
    if (!$revision) { throw new UnexpectedValueException(); }
    $attempt = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_freeze_attempts').' WHERE id=%d', $revision['attempt_id']), ARRAY_A);
    peracrm_campaigns_db_require(true);
    $snapshot = peracrm_campaign_snapshot_get($campaign_id, (int)$revision['snapshot_id']);
    $counts = json_decode($revision['counts_json'], true);
    if (!$attempt || $attempt['state'] !== 'frozen' || !(int)$attempt['complete'] || !$snapshot
        || (int)$snapshot['draft_revision'] !== (int)$revision['draft_revision']
        || (int)$attempt['campaign_id'] !== $campaign_id || (int)$attempt['draft_revision'] !== (int)$revision['draft_revision']
        || (int)$attempt['snapshot_id'] !== (int)$revision['snapshot_id']
        || !hash_equals($revision['content_hash'], $snapshot['content_hash']) || !hash_equals($revision['content_hash'], $attempt['content_hash'])
        || !is_array($counts) || !is_int($counts['eligible'] ?? null) || $counts['eligible'] < 1
        || $revision['counts_json'] !== $attempt['counts_json'] || array_sum($counts) !== (int)$attempt['considered']) { throw new UnexpectedValueException(); }
    if ($job) {
        foreach (['campaign_id','attempt_id','snapshot_id','content_hash'] as $key) {
            if ((string)$job[$key] !== (string)$revision[$key]) { throw new UnexpectedValueException(); }
        }
        if ((int)$job['expected_count'] !== $counts['eligible']) { throw new UnexpectedValueException(); }
    }
    return $revision;
}

/** @internal Staff-only nonce-bound transaction; target is configured, never caller supplied. */
function peracrm_campaign_queue_transaction($campaign_id, $nonce, callable $callback)
{
    return peracrm_campaign_draft_transaction(static function () use ($campaign_id, $nonce, $callback) {
        if (!is_int($campaign_id) || $campaign_id < 1 || !is_string($nonce)
            || !wp_verify_nonce($nonce, 'peracrm_campaign_queue_'.$campaign_id)) { throw new InvalidArgumentException('Invalid queue request.'); }
        return $callback();
    });
}

/** @internal Audit must commit with the state/progress it describes. */
function peracrm_campaign_queue_audit($job, $operation, $previous)
{
    global $wpdb;
    peracrm_campaigns_db_require($wpdb->insert(peracrm_table('peracrm_campaign_queue_events'), [
        'job_id'=>(int)$job['id'], 'version'=>(int)$job['version'], 'operation'=>$operation,
        'previous_state'=>$previous, 'state'=>$job['state'], 'actor_user_id'=>peracrm_get_actor_user_id(),
        'occurred_at'=>$job['updated_at'], 'scheduled_at'=>$job['scheduled_at'],
        'last_id'=>(int)$job['last_id'], 'prepared_count'=>(int)$job['prepared_count'],
    ]));
}

/** Idempotent for the lifetime of a published revision, including terminal cancelled scopes. */
function peracrm_campaign_queue_prepare($campaign_id, $revision_id, $nonce)
{
    return peracrm_campaign_queue_transaction($campaign_id, $nonce, static function () use ($campaign_id, $revision_id) {
        global $wpdb;
        if (!is_int($revision_id) || $revision_id < 1) { throw new InvalidArgumentException('Published revision ID required.'); }
        // Parent lock serializes the absent-job case. Frozen rows are never changed.
        $parent=$wpdb->get_var($wpdb->prepare('SELECT id FROM '.peracrm_table('peracrm_campaign_frozen_revisions').' WHERE campaign_id=%d AND id=%d FOR UPDATE',$campaign_id,$revision_id));
        peracrm_campaigns_db_require(true);
        if (!$parent) { throw new UnexpectedValueException(); }
        $table=peracrm_table('peracrm_campaign_queue_jobs');
        $existing=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE frozen_revision_id=%d FOR UPDATE",$revision_id),ARRAY_A);
        peracrm_campaigns_db_require(true);
        $revision=peracrm_campaign_queue_binding($campaign_id,$revision_id,$existing);
        if ($existing) { return $existing; }
        $now=gmdate('Y-m-d H:i:s');
        $job=['frozen_revision_id'=>$revision_id,'campaign_id'=>$campaign_id,'attempt_id'=>(int)$revision['attempt_id'],
            'snapshot_id'=>(int)$revision['snapshot_id'],'content_hash'=>$revision['content_hash'],'state'=>'preparing','resume_state'=>'',
            'version'=>1,'last_id'=>0,'prepared_count'=>0,'expected_count'=>json_decode($revision['counts_json'],true)['eligible'],
            'complete'=>0,'scheduled_at'=>null,'created_by'=>peracrm_get_actor_user_id(),'updated_by'=>peracrm_get_actor_user_id(),
            'created_at'=>$now,'updated_at'=>$now,'cancelled_at'=>null];
        peracrm_campaigns_db_require($wpdb->insert($table,$job)); $job['id']=(int)$wpdb->insert_id;
        peracrm_campaign_queue_audit($job,'prepare',''); return $job;
    });
}

/** @internal Job lock plus expected version fences every competing lifecycle request. */
function peracrm_campaign_queue_operation($campaign_id, $job_id, $version, $nonce, $operation, callable $callback)
{
    return peracrm_campaign_queue_transaction($campaign_id,$nonce,static function () use ($campaign_id,$job_id,$version,$operation,$callback) {
        global $wpdb;
        if (!is_int($job_id) || $job_id < 1 || !is_int($version) || $version < 1) { throw new InvalidArgumentException('Invalid job version.'); }
        $table=peracrm_table('peracrm_campaign_queue_jobs');
        $job=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE campaign_id=%d AND id=%d FOR UPDATE",$campaign_id,$job_id),ARRAY_A);
        peracrm_campaigns_db_require(true);
        if (!$job || (int)$job['version'] !== $version) { throw new UnexpectedValueException(); }
        $previous=$job['state']; $next=$callback($job);
        // Repeated cancellation with the current version is an audited-state no-op.
        if ($next === $job) { return $job; }
        $next['version']=$version+1; $next['updated_by']=peracrm_get_actor_user_id(); $next['updated_at']=gmdate('Y-m-d H:i:s');
        $update=array_intersect_key($next,array_flip(['state','resume_state','version','last_id','prepared_count','complete','scheduled_at','updated_by','updated_at','cancelled_at']));
        peracrm_campaigns_db_require($wpdb->update($table,$update,['id'=>$job_id,'version'=>$version]));
        peracrm_campaign_queue_audit($next,$operation,$previous); return $next;
    });
}

/** Deterministic within configured target blog + published revision + frozen mailbox; no resend. */
function peracrm_campaign_queue_delivery_key($revision_id, $mailbox)
{
    return hash('sha256', 'peracrm-delivery-v1|'.get_current_blog_id().'|'.$revision_id.'|'.$mailbox);
}

/** One keyset batch, including progress and rows, commits atomically. Rows remain held. */
function peracrm_campaign_queue_materialize($campaign_id,$job_id,$version,$nonce,$limit=200)
{
    if (!is_int($limit) || $limit < 1 || $limit > 200) { return new WP_Error('campaign_invalid','Batch size must be between 1 and 200.'); }
    $result=peracrm_campaign_queue_operation($campaign_id,$job_id,$version,$nonce,'materialize',static function ($job) use ($limit) {
        global $wpdb;
        if ($job['state'] !== 'preparing' || (int)$job['complete']) { throw new UnexpectedValueException(); }
        peracrm_campaign_queue_binding((int)$job['campaign_id'],(int)$job['frozen_revision_id'],$job);
        $members=peracrm_table('peracrm_campaign_frozen_recipients');
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$members} WHERE attempt_id=%d AND id>%d ORDER BY id LIMIT %d",$job['attempt_id'],$job['last_id'],$limit+1),ARRAY_A);
        peracrm_campaigns_db_require(true);
        foreach (array_slice($rows,0,$limit) as $row) {
            $mailbox=peracrm_campaigns_normalize_email($row['mailbox']);
            $evaluation=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_recipient_evaluations').' WHERE attempt_id=%d AND subscriber_id=%d',$job['attempt_id'],$row['subscriber_id']),ARRAY_A);
            peracrm_campaigns_db_require(true);
            if ($mailbox !== $row['mailbox'] || !$evaluation || $evaluation['reason'] !== 'eligible') { throw new UnexpectedValueException(); }
            foreach (['mailbox','client_id','personalization_json','evaluated_at','evaluation_version'] as $key) {
                if ($row[$key] !== $evaluation[$key]) { throw new UnexpectedValueException(); }
            }
            $now=gmdate('Y-m-d H:i:s');
            peracrm_campaigns_db_require($wpdb->insert(peracrm_table('peracrm_campaign_deliveries'),[
                'job_id'=>(int)$job['id'],'frozen_revision_id'=>(int)$job['frozen_revision_id'],'frozen_recipient_id'=>(int)$row['id'],
                'subscriber_id'=>(int)$row['subscriber_id'],'snapshot_id'=>(int)$job['snapshot_id'],'content_hash'=>$job['content_hash'],
                'mailbox'=>$mailbox,'delivery_key'=>peracrm_campaign_queue_delivery_key((int)$job['frozen_revision_id'],$mailbox),
                'state'=>'held','version'=>1,'created_at'=>$now,'updated_at'=>$now]));
            $job['last_id']=(int)$row['id']; $job['prepared_count']++;
        }
        if (count($rows)<=$limit) {
            $total=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.peracrm_table('peracrm_campaign_deliveries').' WHERE job_id=%d',$job['id']));
            peracrm_campaigns_db_require(true);
            if ($total !== (int)$job['expected_count'] || $total !== (int)$job['prepared_count']) { throw new UnexpectedValueException(); }
            $job['complete']=1; $job['state']='queued';
        }
        return $job;
    });
    // Rollback preserves prior cursor/rows. Persist a fenced failure in a separate transaction.
    if (is_wp_error($result) && in_array($result->get_error_code(),['campaign_unavailable','campaign_conflict'],true)) {
        peracrm_campaign_queue_operation($campaign_id,$job_id,$version,$nonce,'fail',static function ($job) {
            if ($job['state'] !== 'preparing') { throw new UnexpectedValueException(); }
            $job['state']='failed'; return $job;
        });
    }
    return $result;
}

/** Scheduling is intent only. Pause/cancel are job-wide fences over held child rows. */
function peracrm_campaign_queue_transition($campaign_id,$job_id,$version,$nonce,$operation,$schedule=null)
{
    return peracrm_campaign_queue_operation($campaign_id,$job_id,$version,$nonce,$operation,static function ($job) use ($operation,$schedule) {
        if ($operation==='cancel') {
            if ($job['state']==='cancelled') { return $job; }
            if (!in_array($job['state'],['preparing','failed','queued','scheduled','paused'],true)) { throw new UnexpectedValueException(); }
            $job['state']='cancelled'; $job['resume_state']=''; $job['cancelled_at']=gmdate('Y-m-d H:i:s'); return $job;
        }
        peracrm_campaign_queue_binding((int)$job['campaign_id'],(int)$job['frozen_revision_id'],$job);
        if ($operation==='schedule' && in_array($job['state'],['queued','scheduled'],true) && (int)$job['complete']) {
            $job['scheduled_at']=peracrm_campaign_queue_schedule_utc($schedule); $job['state']='scheduled'; return $job;
        }
        if ($operation==='pause' && in_array($job['state'],['preparing','queued','scheduled'],true)) {
            $job['resume_state']=$job['state']; $job['state']='paused'; return $job;
        }
        if ($operation==='resume' && $job['state']==='failed') { $job['state']='preparing'; return $job; }
        if ($operation==='resume' && $job['state']==='paused' && in_array($job['resume_state'],['preparing','queued','scheduled'],true)) {
            $job['state']=$job['resume_state']; $job['resume_state']=''; return $job;
        }
        throw new UnexpectedValueException();
    });
}

/** Authorized bounded reads; recipient details additionally require subscriber authority. */
function peracrm_campaign_queue_get($campaign_id,$job_id)
{
    peracrm_campaign_drafts_require(true);
    return peracrm_with_target_blog(static function () use ($campaign_id,$job_id) {
        global $wpdb;
        $job=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_queue_jobs').' WHERE campaign_id=%d AND id=%d',$campaign_id,$job_id),ARRAY_A);
        peracrm_campaigns_db_require(true);
        if ($job) { $job['dispatch_allowed']=false; }
        return $job;
    });
}
function peracrm_campaign_queue_page($campaign_id,$job_id,$after=0,$events=false)
{
    peracrm_campaign_drafts_require(true);
    if (!$events && !peracrm_campaigns_staff_can('peracrm_manage_subscribers',true)) { throw new RuntimeException('Subscriber access required.'); }
    if (!is_int($after) || $after<0 || !is_bool($events)) { throw new InvalidArgumentException('Invalid cursor.'); }
    return peracrm_with_target_blog(static function () use ($campaign_id,$job_id,$after,$events) {
        global $wpdb;
        if (!peracrm_campaign_queue_get($campaign_id,$job_id)) { return []; }
        $table=peracrm_table($events ? 'peracrm_campaign_queue_events' : 'peracrm_campaign_deliveries');
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE job_id=%d AND id>%d ORDER BY id LIMIT 200",$job_id,$after),ARRAY_A);
        peracrm_campaigns_db_require(true); return $rows;
    });
}
