<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/providers/ses-operator-api.php';

final class PeraCRM_SES_Operator
{
    private array $pins;
    public function __construct(private PeraCRM_Campaign_Deployment $deployment, private array $settings,
        #[SensitiveParameter] private ?Closure $transport=null)
    {
        $keys=array_keys($settings); sort($keys);
        if ($keys!==['daily_cap','enabled','per_second_cap','role_name'] || $settings['enabled']!==true
            || !is_string($settings['role_name']) || preg_match('/\A[A-Za-z0-9+=,.@_-]{1,64}\z/',$settings['role_name'])!==1
            || !is_int($settings['per_second_cap']) || $settings['per_second_cap']<1 || $settings['per_second_cap']>1000
            || !is_int($settings['daily_cap']) || $settings['daily_cap']<1 || $settings['daily_cap']>10000000) {
            throw new RuntimeException('operator_configuration');
        }
        $deployment->fingerprint();
        $this->pins=array_intersect_key($deployment->binding(),array_flip(['account_id','region','deployment','blog_id']));
        PeraCRM_SES_Suppression_HTTP::validateScope($this->pins);
    }

    public static function authorize(): void
    {
        $actor=peracrm_get_actor_user_id();
        if ($actor<1 || !defined('WP_CLI') || !WP_CLI || peracrm_is_impersonating_crm_user()
            || !user_can($actor,'peracrm_view_campaign_reports') || !user_can($actor,'manage_options') || !user_can($actor,'peracrm_manage_campaigns')
            || !user_can($actor,'peracrm_manage_subscribers')) { throw new InvalidArgumentException(); }
    }

    private function api(): PeraCRM_SES_Operator_API
    {
        self::authorize();
        if (!peracrm_campaigns_schema_ready()) { throw new RuntimeException('operator_storage'); }
        global $wpdb;
        $quiet=$wpdb->suppress_errors(true);
        try {
            if ((int)$wpdb->get_var('SELECT @@autocommit')!==1 || $wpdb->last_error!==''
                || $wpdb->query('SAVEPOINT peracrm_operator_probe')===false
                || $wpdb->query('RELEASE SAVEPOINT peracrm_operator_probe')!==false
                || $wpdb->last_error!=='SAVEPOINT peracrm_operator_probe does not exist') {
                throw new RuntimeException('operator_storage');
            }
        } finally { $wpdb->suppress_errors($quiet); }
        $api=new PeraCRM_SES_Operator_API($this->pins,$this->settings['role_name'],$this->deployment->credentials(),$this->transport);
        $api->attest();
        return $api;
    }

    public function suppression(?int $pages,bool $restart): array
    {
        if ($pages!==null && ($pages<1 || $pages>10)) { throw new InvalidArgumentException(); }
        $reader=$pages!==null?$this->api()->reader():new class($this->pins) implements PeraCRM_SES_Suppression_Reader {
            public function __construct(private array $pins) {}
            public function scope(): array { return $this->pins; }
            public function page(#[SensitiveParameter] ?string $cursor): array { throw new LogicException('operator_health_only'); }
        };
        $service=new PeraCRM_SES_Suppression_Sync($this->pins,$reader);
        return ($pages===null?$service->health():$service->run($pages,$restart))+['production_enabled'=>false];
    }

    private function budget(bool $configure): PeraCRM_Sending_Budget
    {
        return new PeraCRM_Sending_Budget($this->pins,static fn()=>time(),function () use ($configure): bool {
            if (!$configure) { return false; }
            self::authorize();
            $this->deployment->fingerprint();
            return true;
        });
    }

    public function budgetHealth(): array { return $this->budget(false)->health(); }

    public function refreshQuota(): array
    {
        $api=$this->api();
        $observed=time();
        $account=$api->quota();
        [$second,$daily]=self::limits($account,$this->settings['per_second_cap'],$this->settings['daily_cap']);
        $this->budget(true)->configure($second,$daily,$observed,$observed+300);
        return ['state'=>'refreshed','per_second'=>$second,'daily'=>$daily,'expires_at'=>$observed+300,'production_enabled'=>false];
    }

    public static function limits(array $account,int $secondCap,int $dailyCap): array
    {
        if (($account['SendingEnabled']??null)!==true || !is_array($account['SendQuota']??null)) {
            throw new RuntimeException('operator_quota');
        }
        $quota=$account['SendQuota'];
        foreach (['MaxSendRate','Max24HourSend','SentLast24Hours'] as $key) {
            $n=$quota[$key]??null;
            if ((!is_int($n) && !is_float($n)) || !is_finite((float)$n) || $n<0) { throw new RuntimeException('operator_quota'); }
        }
        $second=(int)floor(min($secondCap,$quota['MaxSendRate']));
        $daily=(int)floor(min($dailyCap,$quota['Max24HourSend']-$quota['SentLast24Hours']));
        if ($second<1 || $daily<1) { throw new RuntimeException('operator_quota'); }
        return [$second,$daily];
    }
    public function __debugInfo(): array { return ['redacted'=>true]; }
    public function __serialize(): array { throw new LogicException('operator_serialization_disabled'); }
}

function peracrm_ses_operator_service(): ?PeraCRM_SES_Operator
{
    if (!defined('WP_CLI') || !WP_CLI || !defined('PERACRM_SES_OPERATOR')) { return null; }
    $settings=PERACRM_SES_OPERATOR;
    if ($settings===['enabled'=>false]) { return null; }
    $deployment=peracrm_campaign_deployment_service();
    if (!$deployment || !is_array($settings)) { throw new RuntimeException('operator_configuration'); }
    return new PeraCRM_SES_Operator($deployment,$settings);
}
