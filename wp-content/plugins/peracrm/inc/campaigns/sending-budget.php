<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/providers/ses-suppression-reader.php';

final class PeraCRM_Sending_Budget_Error extends RuntimeException
{
    public function __construct(string $code='budget_storage')
    {
        parent::__construct(in_array($code,['budget_storage','budget_scope','budget_quota','budget_clock','budget_overlap',
            'budget_pending','budget_exhausted','budget_breaker','budget_attempt','budget_readiness','budget_authority'],true)?$code:'budget_storage');
    }
}

/** Internal prerequisite only. No worker, provider, cron or production factory.
 * All users of an account/region must share this target-blog database and owner.
 * Adopting callers reserve, begin before transport, then settle outcome/signals
 * BEFORE recipient persistence. A reservation or latch is never send authority.
 */
final class PeraCRM_Sending_Budget
{
    private array $pins;
    private string $scope;
    private string $lock;
    private Closure $clock;
    private Closure $ready;
    private static array $active=[];
    public function __construct(array $pins,Closure $clock,Closure $ready)
    {
        if (isset($this->pins)) { throw new PeraCRM_Sending_Budget_Error('budget_scope'); }
        try { PeraCRM_SES_Suppression_HTTP::validateScope($pins); }
        catch (Throwable $error) { throw new PeraCRM_Sending_Budget_Error('budget_scope'); }
        $this->pins=$pins; $this->clock=$clock; $this->ready=$ready;
        // Deployment/campaign/configuration are deliberately NOT partitions.
        $this->scope=hash('sha256',json_encode(['ses-budget-v1',$pins['account_id'],$pins['region']],JSON_THROW_ON_ERROR));
        $this->lock='peracrm-budget-'.substr($this->scope,0,46);
    }
    private static function db($value)
    {
        global $wpdb;
        if ($value===false || $wpdb->last_error!=='') { throw new PeraCRM_Sending_Budget_Error(); }
        return $value;
    }
    private function table(string $kind): string { return peracrm_table('peracrm_campaign_budget_'.$kind); }
    private function now(): int
    {
        $now=($this->clock)();
        if (!is_int($now) || $now<1 || $now>PHP_INT_MAX-86400) { throw new PeraCRM_Sending_Budget_Error('budget_clock'); }
        return $now;
    }
    private function context(): void
    {
        if (get_current_blog_id()!==$this->pins['blog_id'] || (is_multisite() && (int)peracrm_get_target_blog_id()!==$this->pins['blog_id'])
            || !peracrm_campaigns_schema_ready()) { throw new PeraCRM_Sending_Budget_Error('budget_scope'); }
    }
    private function readiness(): void
    {
        if (($this->ready)()!==true) { throw new PeraCRM_Sending_Budget_Error('budget_readiness'); }
    }
    private function authorize(): int
    {
        $actor=peracrm_get_actor_user_id();
        if ($actor<1 || peracrm_is_impersonating_crm_user() || !user_can($actor,'peracrm_view_campaign_reports')
            || !user_can($actor,'peracrm_manage_campaigns') || !user_can($actor,'peracrm_manage_subscribers')) {
            throw new PeraCRM_Sending_Budget_Error('budget_authority');
        }
        return $actor;
    }
    private function session(int $connection): void
    {
        global $wpdb;
        if ((int)self::db($wpdb->get_var('SELECT CONNECTION_ID()'))!==$connection
            || (int)self::db($wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)',$this->lock)))!==$connection) { throw new PeraCRM_Sending_Budget_Error(); }
    }
    private function guard(int $connection,string $token): string
    {
        global $wpdb;
        return $wpdb->prepare('CONNECTION_ID()=%d AND IS_USED_LOCK(%s)=%d AND @peracrm_budget_guard=%s',$connection,$this->lock,$connection,$token);
    }
    /** Reject ambient transactions; every mutation fences reconnect/replayed SQL. */
    private function transaction(callable $callback)
    {
        global $wpdb;
        $quiet=$wpdb->suppress_errors(true); $owned=false; $started=false;
        try {
            $this->context();
            if (isset(self::$active[$this->lock])) { throw new PeraCRM_Sending_Budget_Error('budget_overlap'); }
            if ((int)self::db($wpdb->get_var('SELECT @@autocommit'))!==1) { throw new PeraCRM_Sending_Budget_Error(); }
            self::db($wpdb->query('SAVEPOINT peracrm_budget_probe'));
            if ($wpdb->query('RELEASE SAVEPOINT peracrm_budget_probe')!==false || $wpdb->last_error!=='SAVEPOINT peracrm_budget_probe does not exist') { throw new PeraCRM_Sending_Budget_Error(); }
            $connection=(int)self::db($wpdb->get_var('SELECT CONNECTION_ID()'));
            if ($connection<1) { throw new PeraCRM_Sending_Budget_Error(); }
            if ((string)self::db($wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$this->lock)))!=='1') { throw new PeraCRM_Sending_Budget_Error('budget_overlap'); }
            $owned=true; self::$active[$this->lock]=true; $this->session($connection);
            $token=bin2hex(random_bytes(32));
            self::db($wpdb->query($wpdb->prepare('SET @peracrm_budget_guard=IF(CONNECTION_ID()=%d,%s,NULL)',$connection,$token)));
            self::db($wpdb->query('START TRANSACTION')); $started=true;
            self::db($wpdb->query('SAVEPOINT peracrm_budget_owned')); $this->session($connection);
            self::db($wpdb->query('RELEASE SAVEPOINT peracrm_budget_owned'));
            self::db($wpdb->query('SAVEPOINT peracrm_budget_owned_check'));
            $guard=$this->guard($connection,$token);
            // The transactional token disappears/restores on a silent ROLLBACK.
            // A session variable alone would survive and permit autocommit writes.
            self::db($wpdb->query($wpdb->prepare('INSERT INTO '.$this->table('guards')
                .' (scope_hash,guard_token) SELECT %s,%s WHERE '.$guard
                .' ON DUPLICATE KEY UPDATE guard_token=VALUES(guard_token)',$this->scope,$token)));
            self::db($wpdb->query('RELEASE SAVEPOINT peracrm_budget_owned_check'));
            self::db($wpdb->query('SAVEPOINT peracrm_budget_guard_check'));
            $guard.=$wpdb->prepare(' AND EXISTS (SELECT 1 FROM '.$this->table('guards').' budget_guard WHERE budget_guard.scope_hash=%s AND budget_guard.guard_token=%s)',$this->scope,$token);
            $result=$callback($guard);
            self::db($wpdb->query('RELEASE SAVEPOINT peracrm_budget_guard_check'));
            $this->session($connection);
            self::db($wpdb->query('COMMIT')); $started=false; $this->session($connection);
            $committed=self::db($wpdb->get_var($wpdb->prepare('SELECT guard_token FROM '.$this->table('guards').' WHERE scope_hash=%s',$this->scope)));
            if (!is_string($committed) || !hash_equals($token,$committed)) { throw new PeraCRM_Sending_Budget_Error(); }
            return $result;
        } catch (PeraCRM_Sending_Budget_Error $error) { throw $error; }
        catch (Throwable $error) { throw new PeraCRM_Sending_Budget_Error(); }
        finally {
            if ($started) { $wpdb->query('ROLLBACK'); }
            if ($owned) { unset(self::$active[$this->lock]); $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$this->lock)); }
            $wpdb->suppress_errors($quiet);
        }
    }
    private function row(bool $locked=true): ?array
    {
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.$this->table('accounts').' WHERE scope_hash=%s'.($locked?' FOR UPDATE':''),$this->scope),ARRAY_A);
        self::db(true);
        if ($row && $row['deployment']!==$this->pins['deployment']) { throw new PeraCRM_Sending_Budget_Error('budget_scope'); }
        return $row;
    }
    private function clockCheck(array $row,int $now): void
    {
        if ($now<(int)$row['last_clock']) { throw new PeraCRM_Sending_Budget_Error('budget_clock'); }
    }
    private function quota(array $row,int $now): void
    {
        $this->clockCheck($row,$now);
        self::validateQuota((int)$row['per_second'],(int)$row['daily'],(int)$row['observed_at'],(int)$row['expires_at'],$now);
    }
    public static function validateQuota($second,$daily,$observed,$expires,int $now): void
    {
        if (!is_int($second) || !is_int($daily) || !is_int($observed) || !is_int($expires)
            || $second<1 || $second>1000 || $daily<1 || $daily>10000000 || $observed<1 || $observed>$now
            || $expires<=$now || $expires<=$observed || $expires-$observed>3600) { throw new PeraCRM_Sending_Budget_Error('budget_quota'); }
    }
    private function audit(string $event,int $actor,int $now,string $guard): void
    {
        global $wpdb;
        self::db($wpdb->query($wpdb->prepare('INSERT INTO '.$this->table('events')
            .' (scope_hash,event_code,actor_user_id,recorded_at) SELECT %s,%s,%d,%d WHERE '.$guard,$this->scope,$event,$actor,$now)));
    }
    /** Trusted explicit quota attestation. Never clears breakers/history or changes owner. */
    public function configure($second,$daily,$observed,$expires): void
    {
        $this->transaction(function ($guard) use ($second,$daily,$observed,$expires) {
            global $wpdb;
            $actor=$this->authorize(); $this->readiness(); $now=$this->now();
            self::validateQuota($second,$daily,$observed,$expires,$now); $row=$this->row();
            if ($row) { $this->clockCheck($row,$now); }
            self::db($wpdb->query($wpdb->prepare('INSERT INTO '.$this->table('accounts')
                .' (scope_hash,deployment,per_second,daily,observed_at,expires_at,last_clock) SELECT %s,%s,%d,%d,%d,%d,%d WHERE '.$guard
                .' ON DUPLICATE KEY UPDATE per_second=VALUES(per_second),daily=VALUES(daily),observed_at=VALUES(observed_at),expires_at=VALUES(expires_at),last_clock=VALUES(last_clock)',
                $this->scope,$this->pins['deployment'],$second,$daily,$observed,$expires,$now)));
            $this->audit('quota_configured',$actor,$now,$guard);
        });
    }
    private static function hashKey(string $value): void
    {
        if (preg_match('/\A[a-f0-9]{64}\z/',$value)!==1) { throw new PeraCRM_Sending_Budget_Error('budget_attempt'); }
    }
    private function usage(int $now): array
    {
        global $wpdb;
        $counts=$wpdb->get_row($wpdb->prepare('SELECT SUM(completed_at=0 OR completed_at>=%d) AS seconds_used,COUNT(*) AS daily_used,SUM(completed_at=0) AS pending FROM '
            .$this->table('reservations').' WHERE scope_hash=%s AND (completed_at=0 OR completed_at>=%d)',$now-1,$this->scope,$now-86400),ARRAY_A); self::db(true);
        return ['seconds_used'=>(int)$counts['seconds_used'],'daily_used'=>(int)$counts['daily_used'],'pending'=>(int)$counts['pending']];
    }
    /** One reservation per globally unique attempt; duplicates never return a permit.
     * Pending reservations count forever in BOTH windows. Completed reservations
     * count from completion, conservatively covering stalls before/during transport.
     * No refund API, lease expiry, automatic retry or pending-reservation recovery.
     */
    public function reserve(string $attempt,string $owner): void
    {
        self::hashKey($attempt); self::hashKey($owner);
        $this->transaction(function ($guard) use ($attempt,$owner) {
            global $wpdb;
            $this->readiness(); $row=$this->row(); $now=$this->now();
            if (!$row) { throw new PeraCRM_Sending_Budget_Error('budget_quota'); }
            $this->quota($row,$now);
            $this->submissionAvailable($row,$now);
            $counts=$this->usage($now);
            if ((int)$counts['seconds_used']>=(int)$row['per_second'] || (int)$counts['daily_used']>=(int)$row['daily']) { throw new PeraCRM_Sending_Budget_Error('budget_exhausted'); }
            self::db($wpdb->query($wpdb->prepare('INSERT INTO '.$this->table('reservations')
                .' (scope_hash,attempt_hash,owner_hash,reserved_at,completed_at) SELECT %s,%s,%s,%d,0 WHERE '.$guard,$this->scope,$attempt,$owner,$now)));
            self::db($wpdb->query($wpdb->prepare('UPDATE '.$this->table('accounts').' SET last_clock=%d WHERE scope_hash=%s AND '.$guard,$now,$this->scope)));
        });
    }
    /** Account-wide latch, not a process-local failure flag. Reservations may
     * precede it, but only one submission may have an unsettled outcome.
     */
    private function submissionAvailable(array $row,int $now): void
    {
        if ($row['pending_attempt']!=='' || $row['pending_owner']!=='') { throw new PeraCRM_Sending_Budget_Error('budget_pending'); }
        if ($row['breaker']==='operator' || ($row['breaker']==='throttle' && $now<=(int)$row['cooldown_until'])
            || !in_array($row['breaker'],['closed','throttle'],true)) { throw new PeraCRM_Sending_Budget_Error('budget_breaker'); }
    }
    private function reservation(string $attempt,string $owner): array
    {
        global $wpdb;
        $reservation=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.$this->table('reservations')
            .' WHERE scope_hash=%s AND attempt_hash=%s FOR UPDATE',$this->scope,$attempt),ARRAY_A); self::db(true);
        if (!$reservation || !hash_equals($reservation['owner_hash'],$owner)) { throw new PeraCRM_Sending_Budget_Error('budget_attempt'); }
        return $reservation;
    }
    /** Commit immediately BEFORE the external boundary, outside worker SQL.
     * Rechecks readiness, quota, clock and breaker after reservation. A successful
     * return is single-use; never retry begin or transport following an exception.
     * Caller still owes fresh lease/eligibility/sync authorization; this is NOT it.
     */
    public function beginSubmission(string $attempt,string $owner): void
    {
        self::hashKey($attempt); self::hashKey($owner);
        $this->transaction(function ($guard) use ($attempt,$owner) {
            global $wpdb;
            $this->readiness(); $row=$this->row(); $now=$this->now();
            if (!$row) { throw new PeraCRM_Sending_Budget_Error('budget_quota'); }
            $this->quota($row,$now); $this->submissionAvailable($row,$now);
            $reservation=$this->reservation($attempt,$owner);
            if ((int)$reservation['completed_at']!==0 || $reservation['outcome_code']!=='' || $reservation['evidence_hash']!=='') {
                throw new PeraCRM_Sending_Budget_Error('budget_attempt');
            }
            self::db($wpdb->query($wpdb->prepare('UPDATE '.$this->table('accounts')
                .' SET pending_attempt=%s,pending_owner=%s,last_clock=%d WHERE scope_hash=%s AND '.$guard,$attempt,$owner,$now,$this->scope)));
            $this->audit('submission_started',0,$now,$guard);
        });
    }
    /** Durable outcome evidence clears the latch only in the SAME transaction as
     * the signal and completion. No recipient writes participate. SQL failure or
     * death leaves the earlier latch visible to every worker. Evidence is an
     * opaque immutable digest supplied by a trusted outcome classifier, not raw
     * response bytes. This API authenticates no provider evidence by itself.
     * If outcome evidence was lost, settle as unknown: operator recovery remains
     * mandatory and this attempt can never begin again. Never infer nonacceptance.
     */
    public function settleSubmission(string $attempt,string $owner,string $code,string $evidence): void
    {
        self::hashKey($attempt); self::hashKey($owner); self::hashKey($evidence);
        if (!in_array($code,['provider_accepted','provider_rejected','provider_rate_limit','submission_unknown','preflight_denied'],true)) {
            throw new PeraCRM_Sending_Budget_Error('budget_attempt');
        }
        $this->transaction(function ($guard) use ($attempt,$owner,$code,$evidence) {
            global $wpdb;
            $row=$this->row(); $now=$this->now();
            if (!$row) { throw new PeraCRM_Sending_Budget_Error('budget_quota'); }
            $this->clockCheck($row,$now); $reservation=$this->reservation($attempt,$owner);
            if ($reservation['outcome_code']!=='') {
                if ((int)$reservation['completed_at']<1 || (int)$reservation['completed_at']<$reservation['reserved_at']
                    || (int)$reservation['completed_at']>$now || $reservation['outcome_code']!==$code
                    || !hash_equals($reservation['evidence_hash'],$evidence)) {
                    throw new PeraCRM_Sending_Budget_Error('budget_attempt');
                }
                return; // Exact evidence replay cannot clear a newer submission.
            }
            if ($reservation['evidence_hash']!=='' || (int)$reservation['completed_at']!==0) {
                throw new PeraCRM_Sending_Budget_Error('budget_attempt');
            }
            if (!hash_equals($row['pending_attempt'],$attempt) || !hash_equals($row['pending_owner'],$owner)) {
                throw new PeraCRM_Sending_Budget_Error('budget_attempt');
            }
            if (in_array($code,['provider_rejected','provider_rate_limit','submission_unknown'],true)) {
                $this->persistSignal($row,$code,$now,$guard);
            }
            self::db($wpdb->query($wpdb->prepare('UPDATE '.$this->table('reservations')
                .' SET outcome_code=%s,evidence_hash=%s,completed_at=%d WHERE scope_hash=%s AND attempt_hash=%s AND '.$guard,
                $code,$evidence,$now,$this->scope,$attempt)));
            self::db($wpdb->query($wpdb->prepare('UPDATE '.$this->table('accounts')
                ." SET pending_attempt='',pending_owner='',last_clock=%d WHERE scope_hash=%s AND ".$guard,$now,$this->scope)));
            $this->audit('submission_settled',0,$now,$guard);
        });
    }
    private function persistSignal(array $row,string $code,int $now,string $guard): void
    {
        global $wpdb;
        $operator=$code!=='provider_rate_limit' || $row['breaker']==='operator';
        self::db($wpdb->query($wpdb->prepare('UPDATE '.$this->table('accounts')
            .' SET breaker=%s,cooldown_until=%d,breaker_version=breaker_version+1,last_clock=%d WHERE scope_hash=%s AND '.$guard,
            $operator?'operator':'throttle',$code==='provider_rate_limit'?max((int)$row['cooldown_until'],$now+60):(int)$row['cooldown_until'],$now,$this->scope)));
        $this->audit($code,0,$now,$guard);
    }
    /** Persist signals BEFORE any recipient result/lease write. Conservative mapped
     * provider rejection covers authentication, disabled/suspended and account
     * rejection alike. Unknown responses never establish safe non-acceptance.
     */
    public function signal(string $code): void
    {
        if (!in_array($code,['provider_rejected','provider_rate_limit'],true)) { throw new PeraCRM_Sending_Budget_Error('budget_breaker'); }
        $this->transaction(function ($guard) use ($code) {
            global $wpdb;
            $row=$this->row(); $now=$this->now();
            if (!$row) { throw new PeraCRM_Sending_Budget_Error('budget_quota'); }
            $this->clockCheck($row,$now);
            $this->persistSignal($row,$code,$now,$guard);
        });
    }
    /** No outcome distinction can refund a reservation, even proven non-acceptance. */
    public function finish(string $attempt,string $owner): void
    {
        self::hashKey($attempt); self::hashKey($owner);
        $this->transaction(function ($guard) use ($attempt,$owner) {
            global $wpdb;
            $row=$this->row(); $now=$this->now();
            if (!$row) { throw new PeraCRM_Sending_Budget_Error('budget_quota'); }
            $this->clockCheck($row,$now);
            if ($row['pending_attempt']!=='' || $row['pending_owner']!=='') { throw new PeraCRM_Sending_Budget_Error('budget_pending'); }
            $reservation=$this->reservation($attempt,$owner);
            if ((int)$reservation['completed_at']!==0) { return; }
            self::db($wpdb->query($wpdb->prepare('UPDATE '.$this->table('reservations')
                .' SET completed_at=%d WHERE scope_hash=%s AND attempt_hash=%s AND '.$guard,$now,$this->scope,$attempt)));
            self::db($wpdb->query($wpdb->prepare('UPDATE '.$this->table('accounts').' SET last_clock=%d WHERE scope_hash=%s AND '.$guard,$now,$this->scope)));
        });
    }
    /** Stale operator recovery cannot erase a newer signal. Readiness/quota MUST
     * pass; no recipient/attempt/reservation is changed or released by recovery.
     */
    public function recover(int $version): void
    {
        $this->transaction(function ($guard) use ($version) {
            global $wpdb;
            $actor=$this->authorize(); $this->readiness(); $row=$this->row(); $now=$this->now();
            if (!$row) { throw new PeraCRM_Sending_Budget_Error('budget_quota'); }
            $this->quota($row,$now);
            if ($row['pending_attempt']!=='' || $row['pending_owner']!=='') { throw new PeraCRM_Sending_Budget_Error('budget_pending'); }
            if ($version<1 || (int)$row['breaker_version']!==$version || $row['breaker']!=='operator') { throw new PeraCRM_Sending_Budget_Error('budget_breaker'); }
            self::db($wpdb->query($wpdb->prepare('UPDATE '.$this->table('accounts')
                ." SET breaker=%s,breaker_version=breaker_version+1,last_clock=%d WHERE scope_hash=%s AND ".$guard,
                $now<=(int)$row['cooldown_until']?'throttle':'closed',$now,$this->scope)));
            $this->audit('operator_recovery',$actor,$now,$guard);
        });
    }
    /** Read-only allowlisted summary, no account, deployment, attempt or owner IDs. */
    public function health(): array
    {
        global $wpdb;
        $quiet=$wpdb->suppress_errors(true);
        try {
            $this->context(); $row=$this->row(false); $now=$this->now(); $quota=false;
            if ($row) { try { $this->quota($row,$now); $quota=true; } catch (PeraCRM_Sending_Budget_Error $e) {} }
            if ($row && !in_array($row['breaker'],['closed','operator','throttle'],true)) { throw new PeraCRM_Sending_Budget_Error(); }
            return ['submission_pending'=>$row && ($row['pending_attempt']!=='' || $row['pending_owner']!==''),'usage'=>$row?$this->usage($now):null,'cooldown_active'=>$row && $row['breaker']==='throttle' && $now<=(int)$row['cooldown_until'],
                'configured'=>$row!==null,'quota_fresh'=>$quota,'breaker'=>$row['breaker']??'unconfigured',
                'breaker_version'=>(int)($row['breaker_version']??0),'cooldown_until'=>(int)($row['cooldown_until']??0),
                'production_enabled'=>false,'account_quota_verified'=>false];
        } finally { $wpdb->suppress_errors($quiet); }
    }
    public function __debugInfo(): array { return ['redacted'=>true]; }
    public function __serialize(): array { throw new LogicException('budget_serialization_disabled'); }
    public function __unserialize(array $data): void { throw new LogicException('budget_serialization_disabled'); }
    private function __clone() {}
}
function peracrm_sending_budget_service(): ?PeraCRM_Sending_Budget { return null; }
