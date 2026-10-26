<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/acceptance.php';
require_once __DIR__.'/submission-response.php';
require_once __DIR__.'/worker-preflight.php';

/** No production adapter, factory, switch, request route or scheduler exists in 4B. */
function peracrm_campaign_worker_run($limit = 10)
{
    return peracrm_campaign_queue_require_dispatch();
}

/** Non-acceptance must be proven. An exception, malformed response or timeout is unknown. */
interface PeraCRM_Campaign_Provider
{
    /** Must bound the transport call by submission.timeout_seconds; timeout/lost connection is unknown. */
    public function submit(array $submission): array;
    /** Verified evidence bound to delivery/attempt keys, or null. No assumed provider guarantees. */
    public function lookup(string $delivery_key, string $attempt_key): ?array;
    /** Must authenticate exact durable evidence, not merely trust caller fields or a missing lookup. */
    public function verify(array $evidence, string $delivery_key, string $attempt_key): bool;
}

function peracrm_campaign_worker_result(array $result): array
{
    $kind = $result['kind'] ?? 'unknown';
    if (!in_array($kind, ['accepted', 'transient', 'permanent', 'unknown'], true)
        || ($kind === 'accepted' && (!is_string($result['message_id'] ?? null) || $result['message_id'] === '' || strlen($result['message_id']) > 191))) {
        return ['kind'=>'unknown'];
    }
    if ($kind === 'unknown') { return ['kind'=>'unknown']; }
    return ['kind'=>$kind, 'message_id'=>$kind === 'accepted' ? $result['message_id'] : '',
        'retry_after'=>is_int($result['retry_after'] ?? null) ? max(0, min(86400, $result['retry_after'])) : 0];
}

/** Bounded deterministic jitter is reproducible across restarts; only transient outcomes use it. */
function peracrm_campaign_worker_delay(int $attempt, string $key, int $retry_after = 0): int
{
    $base = min(3600, 30 * (2 ** max(0, min(7, $attempt - 1))));
    $jitter = hexdec(substr(hash('sha256', $key.'|'.$attempt), 0, 4)) % (intdiv($base, 4) + 1);
    return max($base + $jitter, max(0, min(86400, $retry_after)));
}

/**
 * Explicit dependency boundary. There is deliberately no concrete production store.
 * The fixture implements authority, gate and table mapping for an isolated test ledger.
 * Every entry validates authority and target, independent of current browser user.
 */
abstract class PeraCRM_Campaign_Worker
{
    protected const BATCH = 10;
    protected const CONCURRENCY = 10; // per job; no more than one bounded job per invocation
    protected const ATTEMPTS = 4;
    protected const LEASE = 60;
    // Leave 40 seconds for persistence/lock contention. Adapters must enforce the transport timeout.
    // A synchronous dependency cannot be forcibly interrupted here; lease fencing still rejects a late return.
    protected const PROVIDER_TIMEOUT = 20;

    private int $sqlConnection=0;
    private string $sqlGuard="";

    abstract protected function authorize(): void;
    abstract protected function acceptanceDeployment(): string;
    abstract protected function gate(): bool;
    abstract protected function table(string $entity): string;

    protected function transaction(callable $fn)
    {
        $this->authorize();
        return peracrm_with_target_blog(function () use ($fn) {
            $this->authorize();
            if (!peracrm_campaigns_module_enabled()) { throw new RuntimeException('Campaign schema is not ready.'); }
            global $wpdb;
            peracrm_campaigns_db_require((int)$wpdb->get_var('SELECT @@autocommit')===1);
            $quiet=$wpdb->suppress_errors(true);
            try {
                peracrm_campaigns_db_require($wpdb->query('SAVEPOINT peracrm_worker_top_level_probe'));
                if ($wpdb->query('RELEASE SAVEPOINT peracrm_worker_top_level_probe')!==false
                    || $wpdb->last_error!=='SAVEPOINT peracrm_worker_top_level_probe does not exist') { throw new RuntimeException('worker_storage'); }
            } finally { $wpdb->suppress_errors($quiet); }
            peracrm_campaigns_db_require($wpdb->query('START TRANSACTION'));
            try {
                peracrm_campaigns_db_require($wpdb->query('SAVEPOINT peracrm_worker_owned'));
                $this->sqlConnection=(int)$wpdb->get_var('SELECT CONNECTION_ID()');
                peracrm_campaigns_db_require($this->sqlConnection>0);
                peracrm_campaigns_db_require($wpdb->query('RELEASE SAVEPOINT peracrm_worker_owned'));
                $this->sqlGuard=bin2hex(random_bytes(32));
                peracrm_campaigns_db_require($wpdb->query($wpdb->prepare('SET @peracrm_worker_guard=IF(CONNECTION_ID()=%d,%s,NULL)',
                    $this->sqlConnection,$this->sqlGuard)));
                $result = $fn();
                $this->requireConnection();
                peracrm_campaigns_db_require($wpdb->query('COMMIT'));
                $this->requireConnection();
                return $result;
            } catch (Throwable $e) { $wpdb->query('ROLLBACK'); throw $e; }
            finally { $this->sqlConnection=0; $this->sqlGuard=''; }
        });
    }

    private function requireConnection(): void
    {
        global $wpdb;
        peracrm_campaigns_db_require((int)$wpdb->get_var('SELECT CONNECTION_ID()')===$this->sqlConnection && $this->sqlConnection>0);
    }
    private function guardedUpdate(string $table,array $changes,array $where): int
    {
        global $wpdb;
        $set=[]; $values=[]; $conditions=[];
        foreach ($changes as $key=>$value) {
            $set[]='`'.$key.'`'.($value===null?'=NULL':'=%s');
            if ($value!==null) { $values[]=$value; }
        }
        foreach ($where as $key=>$value) { $conditions[]='`'.$key.'`=%s'; $values[]=$value; }
        $values[]=$this->sqlConnection; $values[]=$this->sqlGuard;
        $changed=$wpdb->query($wpdb->prepare('UPDATE `'.$table.'` SET '.implode(',',$set).' WHERE '.implode(' AND ',$conditions)
            .' AND CONNECTION_ID()=%d AND @peracrm_worker_guard=%s',$values));
        peracrm_campaigns_db_require($changed!==false);
        $this->requireConnection();
        return (int)$changed;
    }

    protected function now(): string
    {
        global $wpdb;
        $now = $wpdb->get_var('SELECT UTC_TIMESTAMP()');
        peracrm_campaigns_db_require($now ?: false);
        return $now;
    }

    protected function job(int $id): array
    {
        global $wpdb;
        $job = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.$this->table('jobs').' WHERE id=%d FOR UPDATE', $id), ARRAY_A);
        peracrm_campaigns_db_require($job ?: false);
        return $job;
    }

    protected function due(array $job, string $now): bool
    {
        return $this->gate() && (int)$job['complete'] && !(int)$job['submission_complete']
            && in_array($job['state'], ['queued', 'scheduled'], true)
            && (!$job['scheduled_at'] || $job['scheduled_at'] <= $now);
    }

    protected function delivery(int $job, int $id): array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.$this->table('deliveries').' WHERE job_id=%d AND id=%d FOR UPDATE', $job, $id), ARRAY_A);
        peracrm_campaigns_db_require($row ?: false);
        return $row;
    }

    /** Every write (including recovery) uses CAS and appends a unique version audit in the same transaction. */
    protected function write(array $row, array $changes, int $actor = 0, string $evidence = ''): array
    {
        global $wpdb;
        $changes['version'] = (int)$row['version'] + 1;
        $changes['updated_at'] = $this->now();
        $where=['id'=>$row['id'],'job_id'=>$row['job_id'],'version'=>$row['version'],'owner_token'=>$row['owner_token'],'state'=>$row['state']];
        $changed=$this->guardedUpdate($this->table('deliveries'),$changes,$where);
        peracrm_campaigns_db_require($changed===1);
        $next=array_replace($row,$changes);
        $data=['job_id'=>$next['job_id'],'delivery_id'=>$next['id'],'version'=>$next['version'],
            'state'=>$next['state'],'reason'=>$next['reason'],'owner_token'=>$next['owner_token'],
            'actor_user_id'=>$actor,'evidence_hash'=>$evidence,'occurred_at'=>$next['updated_at']];
        $columns=implode(',',array_keys($data)); $values=implode(',',array_fill(0,count($data),'%s'));
        peracrm_campaigns_db_require($wpdb->query($wpdb->prepare('INSERT INTO '.$this->table('events')." ({$columns}) SELECT {$values}
            WHERE CONNECTION_ID()=%d AND @peracrm_worker_guard=%s",array_merge(array_values($data),[$this->sqlConnection,$this->sqlGuard])))===1);
        $this->requireConnection();
        return $next;
    }

    /** Job then delivery lock order is shared by claims, final authorization and lifecycle operations. */
    final public function claim(int $job_id, int $limit = 10): array
    {
        if ($limit < 1 || $limit > self::BATCH) { throw new InvalidArgumentException('Worker batch must be 1–10.'); }
        return $this->transaction(function () use ($job_id, $limit) {
            global $wpdb;
            $job = $this->job($job_id); $now = $this->now();
            if (!$this->due($job, $now)) { return []; }
            $table = $this->table('deliveries');
            // Count expired leases too: recovery must fence them before capacity is released.
            $active = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE job_id=%d AND state IN ('claimed','inflight')", $job_id));
            peracrm_campaigns_db_require(true);
            $capacity = min($limit, max(0, self::CONCURRENCY - $active));
            if (!$capacity) { return []; }
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE job_id=%d AND state IN ('held','retry') AND attempt_count<%d AND (next_attempt_at IS NULL OR next_attempt_at<=%s) ORDER BY id LIMIT %d FOR UPDATE", $job_id, self::ATTEMPTS, $now, $capacity), ARRAY_A);
            peracrm_campaigns_db_require(true);
            $out = [];
            foreach ($rows as $row) {
                if (PeraCRM_Campaign_Acceptance::fenced($this->acceptanceDeployment(),$row['delivery_key'])) {
                    $this->write($row,['state'=>'unknown','reason'=>'acceptance_evidence','owner_token'=>'','lease_until'=>null,'next_attempt_at'=>null]);
                    continue;
                }
                $attempt = (int)$row['attempt_count'] + 1;
                $out[] = $this->write($row, ['state'=>'claimed', 'owner_token'=>bin2hex(random_bytes(32)),
                    'lease_until'=>gmdate('Y-m-d H:i:s', strtotime($now.' UTC') + self::LEASE),
                    'attempt_count'=>$attempt, 'attempt_key'=>hash('sha256', $row['delivery_key'].'|'.$attempt),
                    'reason'=>'claimed', 'next_attempt_at'=>null]);
            }
            return $out;
        });
    }

    protected function owned(array $row, array $claim, string $now, string $state): void
    {
        if ($row['state'] !== $state || (int)$row['version'] !== (int)$claim['version']
            || !hash_equals($row['owner_token'], $claim['owner_token']) || !$row['lease_until'] || $row['lease_until'] <= $now) {
            throw new UnexpectedValueException('Expired or superseded worker.');
        }
    }

    /** Fresh locking mailbox reads serialize final authorization with consent/suppression writes. */
    protected function eligibility(array $job, array $row): string
    {
        global $wpdb;
        $member = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_frozen_recipients').' WHERE id=%d AND attempt_id=%d', $row['frozen_recipient_id'], $job['attempt_id']), ARRAY_A);
        $revision = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_frozen_revisions').' WHERE id=%d AND campaign_id=%d', $job['frozen_revision_id'], $job['campaign_id']), ARRAY_A);
        $snapshot = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_content_snapshots').' WHERE id=%d AND campaign_id=%d', $job['snapshot_id'], $job['campaign_id']), ARRAY_A);
        peracrm_campaigns_db_require(true);
        if (!$member || !$revision || !$snapshot || $member['mailbox'] !== $row['mailbox'] || $member['subscriber_id'] !== $row['subscriber_id']
            || $row['frozen_revision_id'] !== $job['frozen_revision_id'] || $row['snapshot_id'] !== $job['snapshot_id']
            || !hash_equals($snapshot['content_hash'], $row['content_hash'])
            || $revision['attempt_id'] !== $job['attempt_id'] || $revision['snapshot_id'] !== $row['snapshot_id']
            || !hash_equals($revision['content_hash'], $row['content_hash']) || !hash_equals($job['content_hash'], $row['content_hash'])
            || !hash_equals(peracrm_campaign_queue_delivery_key((int)$job['frozen_revision_id'], $row['mailbox']), $row['delivery_key'])) { return 'frozen_binding'; }
        $snapshot = peracrm_campaign_snapshot_decode($snapshot);
        try { $rules = peracrm_campaign_audience_rules($snapshot['payload']['content']['rules'] ?? []); }
        catch (InvalidArgumentException $e) { return 'rules'; }
        unset($rules['version']);
        $suppression = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_email_suppressions').' WHERE normalized_email=%s FOR UPDATE', $row['mailbox']), ARRAY_A);
        $subscriber = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_subscribers').' WHERE normalized_email=%s FOR UPDATE', $row['mailbox']), ARRAY_A);
        peracrm_campaigns_db_require(true);
        if (!$subscriber || (int)$subscriber['id'] !== (int)$row['subscriber_id']) { return 'mailbox_binding'; }
        $linked = [];
        if (!empty($subscriber['client_id'])) {
            $linked = $wpdb->get_row($wpdb->prepare('SELECT post_type,post_status FROM '.$wpdb->posts.' WHERE ID=%d FOR UPDATE', $subscriber['client_id']), ARRAY_A) ?: [];
            $linked['disposition'] = $wpdb->get_var($wpdb->prepare('SELECT disposition FROM '.peracrm_table('peracrm_party').' WHERE party_id=%d FOR UPDATE', $subscriber['client_id']));
            peracrm_campaigns_db_require(true);
        }
        return peracrm_campaigns_eligibility_reason($subscriber + ($suppression ?: []) + ['suppression_mailbox'=>$suppression['normalized_email'] ?? null], $linked, $rules);
    }

    /** Provider is invoked only after the inflight record commits; no transaction spans submit(). */
    final public function submit(array $claim, PeraCRM_Campaign_Provider $provider): array
    {
        $authorized = $this->transaction(function () use ($claim) {
            $job = $this->job((int)$claim['job_id']);
            $row = $this->delivery((int)$claim['job_id'], (int)$claim['id']);
            $now = $this->now(); $this->owned($row, $claim, $now, 'claimed');
            if (!$this->due($job, $now)) {
                // No submission occurred. A pause is not an attempt and cannot exhaust the queue.
                return $this->write($row, ['state'=>$job['state'] === 'cancelled' ? 'excluded' : 'held',
                    'attempt_count'=>max(0, (int)$row['attempt_count'] - 1),
                    'reason'=>$job['state'] === 'cancelled' ? 'cancelled' : 'job_gate', 'owner_token'=>'', 'lease_until'=>null]);
            }
            if (PeraCRM_Campaign_Acceptance::fenced($this->acceptanceDeployment(),$row['delivery_key'])) {
                return $this->write($row,['state'=>'unknown','reason'=>'acceptance_evidence','owner_token'=>'','lease_until'=>null]);
            }
            $reason = $this->eligibility($job, $row);
            if ($reason !== 'eligible') { return $this->write($row, ['state'=>'excluded', 'reason'=>$reason, 'owner_token'=>'', 'lease_until'=>null]); }
            // Mailbox lock contention may have consumed the original lease. Never authorize an expired owner.
            $now = $this->now(); $this->owned($row, $claim, $now, 'claimed');
            return $this->write($row, ['state'=>'inflight', 'reason'=>'authorized',
                'lease_until'=>gmdate('Y-m-d H:i:s', strtotime($now.' UTC') + self::LEASE)]);
        });
        if ($authorized['state'] !== 'inflight') { return $authorized; }
        // A pause/withdrawal after authorization cannot recall an already starting external request.
        // Recheck the independent gate at the boundary; no switch can open production dispatch.
        $this->authorize();
        if (!$this->gate()) { return $this->result($authorized, ['kind'=>'unknown']); }
        $now = peracrm_with_target_blog(function () { return $this->now(); });
        $this->owned($authorized, $authorized, $now, 'inflight');
        try { $result = $provider->submit(['mailbox'=>$authorized['mailbox'], 'delivery_key'=>$authorized['delivery_key'], 'attempt_key'=>$authorized['attempt_key'], 'timeout_seconds'=>self::PROVIDER_TIMEOUT]); }
        catch (Throwable $e) { $result = ['kind'=>'unknown']; }
        return $this->result($authorized, $result);
    }

    /** Internal integrated path. Preparation commits outside worker transactions;
     * only a fresh final authorization transitions claimed -> inflight.
     * Dependencies are explicit: no production factory, scheduler or transport.
     */
    final public function submitPrepared(array $claim, PeraCRM_Campaign_Worker_Preparation $preparation,
        PeraCRM_Campaign_SES_Mapper $mapper,PeraCRM_Campaign_Worker_Preflight $preflight): array
    {
        if ($this->acceptanceDeployment()!==$preparation->deployment()) { throw new RuntimeException('worker_deployment_mismatch'); }
        $inputs=$this->transaction(function () use ($claim,$preparation,$preflight) {
            $preflight->requireScope($preparation); $preflight->requireSync();
            $job=$this->job((int)$claim['job_id']);
            $row=$this->delivery((int)$claim['job_id'],(int)$claim['id']);
            $denied=$this->preparedBoundary($job,$row,$claim);
            if ($denied !== null) { return ['denied'=>$denied]; }
            [$content,$envelope]=PeraCRM_Campaign_Worker_Content::resolve($job,$row,$preparation->scope(),
                function () use ($job,$row,$claim) {
                    $this->authorize();
                    if (!$this->due($job,$this->now())) { throw new RuntimeException('worker_authority_unavailable'); }
                    $this->owned($row,$claim,$this->now(),'claimed');
                });
            return ['content'=>$content,'envelope'=>$envelope,'row'=>$row];
        });
        if (isset($inputs['denied'])) { return $inputs['denied']; }
        // No worker transaction may surround recipient persistence (it owns COMMIT).
        [$submission,$binding]=peracrm_with_target_blog(function () use ($preparation,$inputs) {
            $this->authorize();
            return $preparation->prepare($inputs['content'],$inputs['envelope'],$inputs['row']);
        });
        $denied=$this->transaction(function () use ($claim,$preparation,$preflight,$binding) {
            $preflight->requireScope($preparation); $preflight->requireSync();
            $job=$this->job((int)$claim['job_id']); $row=$this->delivery((int)$claim['job_id'],(int)$claim['id']);
            $denied=$this->preparedBoundary($job,$row,$claim);
            if ($denied===null) { $preparation->requireIdentity($binding); }
            return $denied;
        });
        if ($denied!==null) { return $denied; }
        // Accounting owns independent commits. A crash here strands capacity;
        // it never permits reuse of the reservation or transport retry.
        peracrm_with_target_blog(function () use ($preflight,$claim) {
            $this->authorize(); $preflight->reserve($claim); $preflight->begin($claim);
        });
        $authorized=$this->transaction(function () use ($claim,$preparation,$binding,$submission,$preflight) {
            $preflight->requireSubmission($claim);
            $preflight->requireScope($preparation); $preflight->requireSync();
            $job=$this->job((int)$claim['job_id']);
            $row=$this->delivery((int)$claim['job_id'],(int)$claim['id']);
            $denied=$this->preparedBoundary($job,$row,$claim);
            if ($denied !== null) { return $denied; }
            $preparation->requireIdentity($binding);
            $fields=$submission->fields();
            foreach (['mailbox','delivery_key','attempt_key'] as $key) {
                peracrm_campaign_submission_require($fields[$key]===$row[$key]);
            }
            $now=$this->now(); $this->owned($row,$claim,$now,'claimed');
            return $this->write($row,['state'=>'inflight','reason'=>'authorized',
                'lease_until'=>gmdate('Y-m-d H:i:s',strtotime($now.' UTC')+self::LEASE)]);
        });
        if ($authorized['state']!=='inflight') {
            $evidence=PeraCRM_Campaign_Worker_Preflight::evidence($submission,['kind'=>'unknown','code'=>'preflight_denied']);
            peracrm_with_target_blog(function () use ($preflight,$claim,$evidence) {
                $preflight->record($claim,$evidence); $preflight->settle($claim,$evidence);
            });
            return $authorized;
        }
        $this->authorize();
        if (!$this->gate()) { throw new RuntimeException('worker_gate_closed'); }
        $now=peracrm_with_target_blog(function () { return $this->now(); });
        $this->owned($authorized,$authorized,$now,'inflight');
        // Commit above is the submission boundary, exactly as in submit().
        try { $result=$mapper->submit($submission); }
        catch (Throwable $error) { $result=['kind'=>'unknown']; }
        $evidence=PeraCRM_Campaign_Worker_Preflight::evidence($submission,$result);
        // Independent immutable classification/signals commit before ANY recipient
        // result or acceptance write. Failures preserve the durable account latch.
        peracrm_with_target_blog(fn()=>$preflight->record($authorized,$evidence));
        if (($result['kind']??null)==='accepted') {
            // This independent transaction preserves positive evidence even if the
            // lease expired, cancellation won or a newer worker version exists.
            $decision=peracrm_with_target_blog(fn()=>PeraCRM_Campaign_Submission_Response::record(
                $preparation->deployment(),$submission,$result['message_id']));
            if ($decision!=='accepted' || !empty($result['late'])) { $result=['kind'=>'unknown']; }
        }
        $settled=$this->result($authorized,$result);
        peracrm_with_target_blog(fn()=>$preflight->settle($authorized,$evidence));
        return $settled;
    }

    /** Authorized recovery fences an expired inflight attempt as unknown before
     * accounting recovery. No outcome erasure, output regeneration or resend.
     */
    final public function recoverPrepared(array $claim,PeraCRM_Campaign_Worker_Preparation $preparation,
        PeraCRM_Campaign_Worker_Preflight $preflight): array
    {
        peracrm_with_target_blog(function () use ($preparation,$preflight) {
            $this->authorize(); $preflight->requireScope($preparation); $preflight->requireRecoveryReadiness();
        });
        $row=$this->transaction(function () use ($claim,$preparation,$preflight) {
            $preflight->requireScope($preparation); $preflight->requireSync();
            $this->job((int)$claim['job_id']); $row=$this->delivery((int)$claim['job_id'],(int)$claim['id']);
            if ($row['attempt_key']!==$claim['attempt_key'] || (int)$row['version']<(int)$claim['version']) {
                throw new RuntimeException('worker_recovery_binding');
            }
            if (in_array($row['state'],['claimed','inflight'],true)) {
                if ($row['owner_token']!==$claim['owner_token'] || $row['lease_until']>$this->now()) {
                    throw new RuntimeException('worker_recovery_owner');
                }
                return $this->write($row,['state'=>'unknown','reason'=>'expired_inflight','owner_token'=>'','lease_until'=>null]);
            }
            if ($row['state']==='held' && $row['owner_token']==='') {
                return $this->write($row,['state'=>'unknown','reason'=>'pending_submission','lease_until'=>null]);
            }
            if (!in_array($row['state'],['unknown','accepted','failed','retry','excluded'],true)) {
                throw new RuntimeException('worker_recovery_state');
            }
            return $row;
        });
        peracrm_with_target_blog(fn()=>$preflight->recover($claim));
        return $row;
    }

    private function preparedBoundary(array $job,array $row,array $claim): ?array
    {
        $this->owned($row,$claim,$this->now(),'claimed');
        if (!$this->due($job,$this->now())) {
            return $this->write($row,['state'=>$job['state']==='cancelled'?'excluded':'held',
                'attempt_count'=>max(0,(int)$row['attempt_count']-1),
                'reason'=>$job['state']==='cancelled'?'cancelled':'job_gate','owner_token'=>'','lease_until'=>null]);
        }
        if (PeraCRM_Campaign_Acceptance::fenced($this->acceptanceDeployment(),$row['delivery_key'])) {
            return $this->write($row,['state'=>'unknown','reason'=>'acceptance_evidence','owner_token'=>'','lease_until'=>null]);
        }
        // Shared mailbox mutex serializes with withdrawal, imports and lifecycle writes.
        peracrm_campaigns_repository_lock_mailbox($row['mailbox']);
        $reason=$this->eligibility($job,$row);
        if ($reason!=='eligible') {
            return $this->write($row,['state'=>'excluded','reason'=>$reason,'owner_token'=>'','lease_until'=>null]);
        }
        $this->owned($row,$claim,$this->now(),'claimed');
        return null;
    }

    final public function result(array $claim, array $result): array
    {
        return $this->transaction(function () use ($claim, $result) {
            $this->job((int)$claim['job_id']);
            $row = $this->delivery((int)$claim['job_id'], (int)$claim['id']);
            $this->owned($row, $claim, $this->now(), 'inflight');
            return $this->outcome($row, peracrm_campaign_worker_result($result));
        });
    }

    protected function outcome(array $row, array $result, int $actor = 0, string $evidence = ''): array
    {
        // Provider acceptance (including post-acceptance failures) is never a
        // non-acceptance result. Unknowns still require explicit reconciliation.
        if ($result['kind']!=='accepted' && PeraCRM_Campaign_Acceptance::fenced($this->acceptanceDeployment(),$row['delivery_key'])) {
            $result=['kind'=>'unknown'];
        }
        $kind = $result['kind'];
        $state = ['accepted'=>'accepted', 'transient'=>'retry', 'permanent'=>'failed', 'unknown'=>'unknown'][$kind];
        if ($state === 'retry' && (int)$row['attempt_count'] >= self::ATTEMPTS) { $state = 'failed'; }
        return $this->write($row, ['state'=>$state, 'reason'=>$kind === 'transient' && $state === 'failed' ? 'attempt_limit' : $kind,
            'message_id'=>$result['message_id'] ?? '', 'owner_token'=>'', 'lease_until'=>null,
            'next_attempt_at'=>$state === 'retry' ? gmdate('Y-m-d H:i:s', strtotime($this->now().' UTC') + peracrm_campaign_worker_delay((int)$row['attempt_count'], $row['delivery_key'], $result['retry_after'] ?? 0)) : null], $actor, $evidence);
    }

    /** Claimed means no submission was authorized. Inflight expiry is always ambiguous. */
    final public function recover(int $job_id, int $limit = 10): array
    {
        if ($limit < 1 || $limit > self::BATCH) { throw new InvalidArgumentException('Recovery batch must be 1–10.'); }
        return $this->transaction(function () use ($job_id, $limit) {
            global $wpdb;
            $this->job($job_id);
            $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM '.$this->table('deliveries')." WHERE job_id=%d AND state IN ('claimed','inflight') AND lease_until<=%s ORDER BY id LIMIT %d FOR UPDATE", $job_id, $this->now(), $limit), ARRAY_A);
            peracrm_campaigns_db_require(true); $out = [];
            foreach ($rows as $row) {
                $out[] = $this->write($row, ['state'=>$row['state'] === 'inflight' ? 'unknown' : 'held',
                    // A claimed row has never crossed submission authorization. Refund its reserved attempt.
                    'attempt_count'=>$row['state'] === 'claimed' ? max(0, (int)$row['attempt_count'] - 1) : (int)$row['attempt_count'],
                    'reason'=>$row['state'] === 'inflight' ? 'expired_inflight' : 'expired_before_submit', 'owner_token'=>'', 'lease_until'=>null]);
            }
            return $out;
        });
    }

    /** Staff action plus independently verified provider evidence. No reset-to-held operation. */
    final public function reconcile(int $job_id, int $id, int $version, string $nonce, array $evidence, PeraCRM_Campaign_Provider $provider): array
    {
        $this->authorize();
        peracrm_campaign_drafts_require(true);
        if (!peracrm_campaigns_staff_can('peracrm_manage_subscribers', true)) { throw new RuntimeException('Subscriber reconciliation authority required.'); }
        // Verification/lookup may contact a later real provider: always outside the transaction.
        $key = $evidence['delivery_key'] ?? ''; $attempt = $evidence['attempt_key'] ?? '';
        if (!$provider->verify($evidence, $key, $attempt)) { throw new InvalidArgumentException('Verified evidence required.'); }
        $result = peracrm_campaign_worker_result($evidence);
        if (!in_array($result['kind'], ['accepted','permanent','transient'], true)) { throw new InvalidArgumentException('Evidence must resolve acceptance.'); }
        $hash = hash('sha256', wp_json_encode($evidence));
        return $this->transaction(function () use ($job_id,$id,$version,$nonce,$key,$attempt,$result,$hash) {
            global $wpdb;
            $job = $this->job($job_id);
            if (!wp_verify_nonce($nonce, 'peracrm_campaign_queue_'.$job['campaign_id'])) { throw new InvalidArgumentException('Invalid reconciliation nonce.'); }
            $row = $this->delivery($job_id, $id);
            if ($row['delivery_key'] !== $key || $row['attempt_key'] !== $attempt) { throw new UnexpectedValueException('Evidence binding changed.'); }
            if ((int)$row['version'] === $version + 1) {
                $prior = $wpdb->get_var($wpdb->prepare('SELECT evidence_hash FROM '.$this->table('events').' WHERE delivery_id=%d AND version=%d', $id, $row['version']));
                peracrm_campaigns_db_require(true);
                if (is_string($prior) && hash_equals($prior, $hash)) { return $row; }
            }
            if ($row['state'] !== 'unknown' || (int)$row['version'] !== $version) { throw new UnexpectedValueException('Stale reconciliation.'); }
            if (PeraCRM_Campaign_Acceptance::fenced($this->acceptanceDeployment(),$row['delivery_key']) && $result['kind']!=='accepted') {
                throw new UnexpectedValueException('Acceptance evidence forbids retry.');
            }
            PeraCRM_Campaign_Acceptance::requireResolution($this->acceptanceDeployment(),$row,$result);
            return $this->outcome($row, $result, peracrm_get_actor_user_id(), $hash);
        });
    }

    /** Exact total and terminal counts; preparation complete is distinct from submission complete. */
    final public function completion(int $job_id): bool
    {
        return $this->transaction(function () use ($job_id) {
            global $wpdb;
            $job = $this->job($job_id);
            $counts = $wpdb->get_row($wpdb->prepare('SELECT COUNT(*) AS total, SUM(state IN (\'accepted\',\'excluded\',\'failed\')) AS terminal, SUM(lease_until IS NOT NULL OR owner_token<>\'\') AS leases FROM '.$this->table('deliveries').' WHERE job_id=%d', $job_id), ARRAY_A);
            peracrm_campaigns_db_require(true);
            $complete = (int)$job['complete'] && (int)$job['prepared_count'] === (int)$job['expected_count']
                && !(int)$counts['leases'] && (int)$counts['total'] === (int)$job['expected_count'] && (int)$counts['terminal'] === (int)$counts['total'];
            peracrm_campaigns_db_require($this->guardedUpdate($this->table('jobs'), ['submission_complete'=>(int)$complete], ['id'=>$job_id, 'version'=>$job['version']]));
            return (bool)$complete;
        });
    }

    final public function run(int $job_id, PeraCRM_Campaign_Provider $provider, int $limit = 10): array
    {
        if ($limit < 1 || $limit > self::BATCH) { throw new InvalidArgumentException('Worker invocation must be 1–10.'); }
        $out = [];
        // Do not lease future work while a sequential provider call is blocking. Claims still share
        // the same atomic per-job concurrency ceiling across independent invocations/connections.
        for ($processed = 0; $processed < $limit; $processed++) {
            $claims = $this->claim($job_id, 1);
            if (!$claims) { break; }
            $out[] = $this->submit($claims[0], $provider);
        }
        $this->completion($job_id);
        return $out;
    }
}
