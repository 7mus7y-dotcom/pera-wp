<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/providers/ses-sdk-transport.php';

/** One operational message per production site's ledger; no campaign or queue authority. */
final class PeraCRM_SES_Internal_Probe
{
    public static function run(): array
    {
        PeraCRM_SES_Operator::authorize();
        if (!defined('PERACRM_SES_INTERNAL_PROBE') || PERACRM_SES_INTERNAL_PROBE !== true) {
            return ['state'=>'disabled'];
        }

        $deployment=peracrm_campaign_deployment_service();
        if (!$deployment || !peracrm_campaigns_schema_ready()
            || peracrm_campaign_queue_dispatch_allowed() || defined('PERACRM_SES_SNS_HTTP')) {
            throw new RuntimeException('probe_unavailable');
        }
        $fingerprint=$deployment->fingerprint();
        $binding=$deployment->binding();
        $pins=array_intersect_key($binding,array_flip(['account_id','region','deployment','blog_id']));
        if ($pins['account_id']!=='398074591385'
            || $pins['region']!=='eu-north-1'
            || $pins['deployment']!=='pera-production' || $pins['blog_id']!==1
            || $binding['configuration_set']!=='peracrm-campaigns'
            || home_url('/')!=='https://www.peraproperty.com/') {
            throw new RuntimeException('probe_scope');
        }
        $credentials=$deployment->credentials();
        $scope=array_diff_key($binding,['deployment'=>true,'blog_id'=>true]);
        $transport=new PeraCRM_Campaign_SES_SDK_Transport($scope,$credentials);
        (new PeraCRM_SES_Operator_API($pins,'PeraCRMSESInternalProbe',$credentials))->attest();

        self::claim($fingerprint);
        $response=$transport->sendInternalProbe();
        if (isset($response['message_id'])) {
            return ['state'=>'accepted','message_id'=>$response['message_id']];
        }
        return ['state'=>'unconfirmed','retry_allowed'=>false];
    }

    private static function claim(string $fingerprint): void
    {
        global $wpdb;
        if ((int)$wpdb->get_var('SELECT @@autocommit')!==1 || $wpdb->last_error!==''
            || $wpdb->query('SAVEPOINT peracrm_probe_guard')===false
            || $wpdb->query('RELEASE SAVEPOINT peracrm_probe_guard')!==false
            || $wpdb->last_error!=='SAVEPOINT peracrm_probe_guard does not exist') {
            throw new RuntimeException('probe_storage');
        }
        // Plain INSERT with a unique option name is atomic. Do not use an
        // upsert: a duplicate, reconnect replay or ambiguous write cannot send.
        $record=wp_json_encode(['actor'=>peracrm_get_actor_user_id(),'at'=>time(),'binding'=>$fingerprint]);
        if (!$record || $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->options}
            (option_name, option_value, autoload) VALUES (%s,%s,'no')",
            'peracrm_ses_internal_probe_consumed_v1',$record))!==1 || $wpdb->last_error!=='') {
            throw new RuntimeException('probe_consumed_or_storage');
        }
    }
}
