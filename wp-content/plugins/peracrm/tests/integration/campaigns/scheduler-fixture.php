<?php
require_once __DIR__.'/worker-fixture.php';

/** The only scheduler with a concrete worker/provider maps solely to the disposable ledger. */
final class PeraCRM_Fixture_Scheduler extends PeraCRM_Campaign_Operations
{
    public ?PeraCRM_Fake_Campaign_Provider $fake = null;
    public bool $readiness = true;
    public function __construct() { worker_fixture_environment(); worker_fixture(); }
    protected function authorize_read(): void { worker_fixture_environment(); worker_fixture(); }
    protected function ready(): bool { return $this->readiness && parent::ready(); }
    protected function dispatch(): bool { worker_fixture_environment(); worker_fixture(); return true; }
    protected function table(string $entity): string { worker_fixture_environment(); return peracrm_table('peracrm_fixture_worker_'.$entity); }
    protected function metadata_key(): string { return 'peracrm_fixture_scheduler_last'; }
    protected function worker(): PeraCRM_Campaign_Worker { return worker_fixture(); }
    protected function provider(): PeraCRM_Campaign_Provider { return $this->fake ?? ($this->fake=new PeraCRM_Fake_Campaign_Provider()); }
    protected function clock(): float { global $wpdb; return (float)$wpdb->get_var('SELECT UNIX_TIMESTAMP()'); }
    public function lock(): string { return peracrm_with_target_blog(function () { return $this->lock_name(); }); }
}
