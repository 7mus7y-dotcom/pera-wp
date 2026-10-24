<?php
/** Explicit fixture authority is a process capability, not a logged-in user or request parameter. */
function worker_fixture_environment(): void
{
    if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE') || PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE !== true
        || !defined('DB_NAME') || DB_NAME !== 'campaign_fixture' || wp_get_environment_type() !== 'local'
        || !defined('WP_CLI') || !WP_CLI) { throw new RuntimeException('Disposable local CLI fixture required.'); }
}
worker_fixture_environment();
$GLOBALS['worker_fixture_in_transaction']=false;
add_filter('query', static function ($sql) { if ($sql === 'START TRANSACTION') { $GLOBALS['worker_fixture_in_transaction']=true; } if (in_array($sql,['COMMIT','ROLLBACK'],true)) { $GLOBALS['worker_fixture_in_transaction']=false; } return $sql; });

final class PeraCRM_Fixture_Worker extends PeraCRM_Campaign_Worker
{
    protected function acceptanceDeployment(): string { return worker_fixture_target(static fn()=>(string)get_option('worker_evidence_deployment','worker-fixture')); }
    private string $authority;
    public function __construct(string $authority) { $this->authority = $authority; $this->authorize(); }
    protected function authorize(): void
    {
        worker_fixture_environment();
        $expected = peracrm_with_target_blog(static function () { return get_option('campaign_worker_fixture_authority'); });
        if (!is_string($expected) || strlen($expected) !== 64 || !hash_equals($expected, $this->authority)) { throw new RuntimeException('Worker authority required.'); }
    }
    protected function gate(): bool { $this->authorize(); return !peracrm_campaign_queue_dispatch_allowed(); }
    protected function table(string $entity): string
    {
        $this->authorize();
        if ((is_multisite() && (peracrm_get_target_blog_id() < 1 || get_current_blog_id() !== peracrm_get_target_blog_id())) || !in_array($entity, ['jobs','deliveries','events'], true)) { throw new RuntimeException('Target fixture ledger required.'); }
        return peracrm_table('peracrm_fixture_worker_'.$entity);
    }
}

/** Local deterministic simulator: lookup evidence is HMAC verified; no external provider guarantees implied. */
final class PeraCRM_Fake_Campaign_Provider implements PeraCRM_Campaign_Provider
{
    private string $secret;
    public string $mode = 'accepted';
    public int $calls = 0;
    /** Synthetic elapsed database time, confined to this disposable connection; no wall-clock sleeps. */
    public int $elapsed_seconds = 0;
    public ?Closure $on_submit = null;
    public array $timeouts = [];
    public function __construct() { worker_fixture_environment(); $this->secret = wp_salt('auth'); }
    public function submit(array $submission): array
    {
        worker_fixture_environment(); $this->calls++;
        global $wpdb;
        if ($GLOBALS['worker_fixture_in_transaction']) { throw new LogicException('Provider invoked inside transaction.'); }
        $this->timeouts[] = $submission['timeout_seconds'] ?? null;
        if ($this->on_submit) { ($this->on_submit)($submission); }
        if ($this->elapsed_seconds) { worker_fixture_advance($this->elapsed_seconds); }
        $key = $submission['delivery_key']; $attempt = $submission['attempt_key'];
        $prior = $this->lookup($key, $attempt);
        if ($prior && $prior['kind'] === 'accepted') { return $prior; } // fixture-specific same-key/attempt idempotency
        $kind = in_array($this->mode, ['accepted','crash_after_accept','timeout_after_accept'], true) ? 'accepted' : $this->mode;
        $evidence = ['kind'=>$kind, 'message_id'=>$kind === 'accepted' ? 'fake-'.substr($key,0,32) : '', 'retry_after'=>120,
            'delivery_key'=>$key, 'attempt_key'=>$attempt];
        $evidence['proof'] = hash_hmac('sha256', wp_json_encode($evidence), $this->secret);
        peracrm_with_target_blog(static function () use ($key,$evidence) { update_option('campaign_worker_fake_'.substr($key,0,48), $evidence, false); });
        if ($this->mode === 'crash_after_accept') { exit(73); }
        if ($this->mode === 'timeout_after_accept' || $this->mode === 'unknown') { throw new RuntimeException('Ambiguous connection loss.'); }
        return $evidence;
    }
    public function lookup(string $delivery_key, string $attempt_key): ?array
    {
        worker_fixture_environment();
        $e = peracrm_with_target_blog(static function () use ($delivery_key) { return get_option('campaign_worker_fake_'.substr($delivery_key,0,48)); });
        return is_array($e) && $e['attempt_key'] === $attempt_key ? $e : null;
    }
    public function verify(array $evidence, string $delivery_key, string $attempt_key): bool
    {
        worker_fixture_environment(); $proof = $evidence['proof'] ?? ''; unset($evidence['proof']);
        return is_string($proof) && $evidence['delivery_key'] === $delivery_key && $evidence['attempt_key'] === $attempt_key
            && hash_equals(hash_hmac('sha256', wp_json_encode($evidence), $this->secret), $proof);
    }
}

function worker_fixture_target(callable $fn) { return peracrm_with_target_blog($fn); }
function worker_fixture_advance(int $seconds): void
{
    worker_fixture_environment(); global $wpdb;
    $timestamp=(int)$wpdb->get_var('SELECT UNIX_TIMESTAMP()');
    peracrm_campaigns_db_require($wpdb->query('SET timestamp = '.($timestamp+$seconds)));
}
function worker_fixture_authority(): string { return worker_fixture_target(static function () { return get_option('campaign_worker_fixture_authority'); }); }
function worker_fixture(): PeraCRM_Fixture_Worker { return new PeraCRM_Fixture_Worker(worker_fixture_authority()); }
function worker_fixture_rows(int $job): array
{
    return worker_fixture_target(static function () use ($job) { global $wpdb; return $wpdb->get_results($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_fixture_worker_deliveries').' WHERE job_id=%d ORDER BY id', $job), ARRAY_A); });
}
function worker_fixture_expire(int $job): void
{
    worker_fixture_target(static function () use ($job) { global $wpdb; $wpdb->query($wpdb->prepare('UPDATE '.peracrm_table('peracrm_fixture_worker_deliveries')." SET lease_until='2000-01-01 00:00:00' WHERE job_id=%d AND state IN ('claimed','inflight')", $job)); });
}
function worker_fixture_reset(int $limit = 3): int
{
    worker_fixture_environment();
    return worker_fixture_target(static function () use ($limit) {
        global $wpdb;
        update_option('worker_evidence_deployment','worker-fixture',false);
        foreach (['jobs'=>'peracrm_campaign_queue_jobs','deliveries'=>'peracrm_campaign_deliveries','events'=>'peracrm_campaign_worker_events'] as $name=>$source) {
            $table = peracrm_table('peracrm_fixture_worker_'.$name);
            $wpdb->query('DROP TABLE IF EXISTS '.$table);
            peracrm_campaigns_db_require($wpdb->query('CREATE TABLE '.$table.' LIKE '.peracrm_table($source)));
        }
        $job = $wpdb->get_row('SELECT * FROM '.peracrm_table('peracrm_campaign_queue_jobs')." WHERE state='queued' AND complete=1 AND expected_count=".(int)$limit." ORDER BY id DESC LIMIT 1", ARRAY_A);
        if (!$job) { throw new RuntimeException('Prepared synthetic queue required.'); }
        $source = (int)$job['id'];
        peracrm_campaigns_db_require($wpdb->insert(peracrm_table('peracrm_fixture_worker_jobs'),$job));
        peracrm_campaigns_db_require($wpdb->query($wpdb->prepare('INSERT INTO '.peracrm_table('peracrm_fixture_worker_deliveries').' SELECT * FROM '.peracrm_table('peracrm_campaign_deliveries')." WHERE job_id=%d ORDER BY id LIMIT %d", $source,$limit)));
        $rows = worker_fixture_rows($source);
        foreach ($rows as $row) { delete_option('campaign_worker_fake_'.substr($row['delivery_key'],0,48)); }
        return $source;
    });
}

function worker_fixture_state(int $job, string $state, ?string $schedule = null): void
{
    // Exercise the actual staff lifecycle service, remapping only its job/event SQL to fixture ledgers.
    $manager=worker_fixture_target(static function () { return (int)get_option('campaign_fixture_manager'); });
    wp_set_current_user($manager);
    $map=static function ($sql) {
        return str_replace([peracrm_table('peracrm_campaign_queue_jobs'),peracrm_table('peracrm_campaign_queue_events')],
            [peracrm_table('peracrm_fixture_worker_jobs'),peracrm_table('peracrm_fixture_lifecycle_events')],$sql);
    };
    worker_fixture_target(static function () { global $wpdb; $wpdb->query('CREATE TABLE IF NOT EXISTS '.peracrm_table('peracrm_fixture_lifecycle_events').' LIKE '.peracrm_table('peracrm_campaign_queue_events')); $wpdb->query('TRUNCATE TABLE '.peracrm_table('peracrm_fixture_lifecycle_events')); });
    $j=worker_fixture_target(static function () use ($job) { global $wpdb; return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_fixture_worker_jobs').' WHERE id=%d',$job),ARRAY_A); });
    add_filter('query',$map);
    try { $result=peracrm_campaign_queue_transition((int)$j['campaign_id'],$job,(int)$j['version'],wp_create_nonce('peracrm_campaign_queue_'.$j['campaign_id']),$state,$schedule); }
    finally { remove_filter('query',$map); }
    if (is_wp_error($result)) { throw new RuntimeException('Fixture lifecycle failed: '.$result->get_error_code()); }
    wp_set_current_user(0);
}
