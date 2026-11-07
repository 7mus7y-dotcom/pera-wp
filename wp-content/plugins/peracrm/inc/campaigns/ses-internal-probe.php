<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/providers/ses-sdk-transport.php';

/** Fixed internal mailbox probes with independent one-time permits. */
final class PeraCRM_SES_Internal_Probe
{
    public static function run(int $attempt=1): array
    {
        PeraCRM_SES_Operator::authorize();
        self::key($attempt);
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

        $record=self::claim($fingerprint,$attempt);
        $response=$transport->sendInternalProbe();
        $result=['state'=>'unconfirmed','retry_allowed'=>false];
        if (($response['complete']??false)===true && ($response['authenticated']??false)===true) {
            if (($response['status']??null)===200 && isset($response['message_id'])) {
                $result=['state'=>'accepted','message_id'=>$response['message_id']];
            } elseif (in_array($response['status']??null,[400,401,403,404,429],true) && isset($response['error_code'])) {
                $result=['state'=>'rejected','http_status'=>$response['status'],'error_code'=>$response['error_code'],
                    'retry_allowed'=>false];
            }
        }
        self::finish($record,$result,$attempt);
        return $result;
    }

    private static function key(int $attempt): string
    {
        if (!in_array($attempt,[1,2,3],true)) { throw new InvalidArgumentException(); }
        return 'peracrm_ses_internal_probe_consumed_v'.$attempt;
    }

    private static function read(int $attempt): ?array
    {
        global $wpdb;
        $raw=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",self::key($attempt)));
        if ($wpdb->last_error!=='') { throw new RuntimeException('probe_storage'); }
        if ($raw===null) { return null; }
        $record=json_decode($raw,true,8,JSON_THROW_ON_ERROR);
        if (!is_array($record)) { throw new RuntimeException('probe_storage'); }
        return $record;
    }

    public static function status(): array
    {
        PeraCRM_SES_Operator::authorize();
        $out=[];
        foreach (['first'=>1,'second'=>2,'third'=>3] as $name=>$attempt) {
            $record=self::read($attempt);
            $out[$name]=$record===null?['state'=>'unused']:
                (array_intersect_key($record,array_flip(['actor','at','finished_at','state','message_id','http_status','error_code']))
                    +['state'=>'unconfirmed']);
        }
        return $out;
    }

    private static function finish(string $claim,array $result,int $attempt): void
    {
        global $wpdb;
        $record=wp_json_encode($result+['finished_at'=>time()]+json_decode($claim,true,8,JSON_THROW_ON_ERROR));
        if (!$record || $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value=%s
            WHERE option_name=%s AND BINARY option_value=%s",$record,self::key($attempt),$claim))!==1
            || $wpdb->last_error!=='') { throw new RuntimeException('probe_storage'); }
    }

    private static function claim(string $fingerprint,int $attempt=1): string
    {
        global $wpdb;
        if ((int)$wpdb->get_var('SELECT @@autocommit')!==1 || $wpdb->last_error!==''
            || $wpdb->query('SAVEPOINT peracrm_probe_guard')===false
            || $wpdb->query('RELEASE SAVEPOINT peracrm_probe_guard')!==false
            || $wpdb->last_error!=='SAVEPOINT peracrm_probe_guard does not exist') {
            throw new RuntimeException('probe_storage');
        }
        if ($attempt===2) {
            $first=self::read(1);
            if ($first===null || in_array($first['state']??null,['started','accepted'],true)) {
                throw new RuntimeException('probe_second_not_allowed');
            }
        }
        if ($attempt===3) {
            $previous=self::read(2);
            if (($previous['state']??null)!=='rejected' || ($previous['http_status']??null)!==403
                || ($previous['error_code']??null)!=='AccessDeniedException'
                || !is_int($previous['finished_at']??null)) {
                throw new RuntimeException('probe_third_not_allowed');
            }
        }
        // Plain INSERT with a unique option name is atomic. Do not use an
        // upsert: a duplicate, reconnect replay or ambiguous write cannot send.
        $record=wp_json_encode(['actor'=>peracrm_get_actor_user_id(),'at'=>time(),'binding'=>$fingerprint,'state'=>'started']);
        if (!$record || $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->options}
            (option_name, option_value, autoload) VALUES (%s,%s,'no')",
            self::key($attempt),$record))!==1 || $wpdb->last_error!=='') {
            throw new RuntimeException('probe_consumed_or_storage');
        }
        return $record;
    }
}
