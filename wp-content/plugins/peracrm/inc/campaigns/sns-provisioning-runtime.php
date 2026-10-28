<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/sns-provisioning.php';

/** Separate deployment-only control-plane switch. Event intake need not be enabled. */
function peracrm_sns_provisioning_service(?Closure $api=null,?callable $certificate=null): ?PeraCRM_SNS_Provisioning
{
    if (!defined('PERACRM_SES_SNS_PROVISIONING') || !is_array(PERACRM_SES_SNS_PROVISIONING)) { return null; }
    try {
        $config=PERACRM_SES_SNS_PROVISIONING;
        if (($config['endpoint']??null)!==home_url('/peracrm-ses-sns/')) { return null; }
        $v=new PeraCRM_SNS_Verifier(['account'=>$config['account']??null,'region'=>$config['region']??null,'topic'=>$config['topic']??null],static fn()=>null);
        $certificate??=static fn($url)=>peracrm_sns_certificate_fetch($v,$url);
        // Public ingress never loads credentials. API is available only to an explicit CLI action.
        $api??=static function () { throw new PeraCRM_SNS_Error('sns_authority'); };
        $key=hash_hkdf('sha256',wp_salt('auth'),32,'peracrm-sns-provision-v1',json_encode($config,JSON_THROW_ON_ERROR));
        return new PeraCRM_SNS_Provisioning($config,$certificate,$api,$key);
    } catch (Throwable $e) { return null; }
}

/** CLI authority is a real administrator, explicit literal confirmation and target --url.
 * Credentials are short-lived, explicitly supplied process variables; no discovery chain.
 */
function peracrm_sns_provisioning_cli(array $args,array $assoc): void
{
    try {
        $action=$args[0]??'';
        $confirmation=$assoc['confirm']??'';
        if (count($args)!==1 || array_diff(array_keys($assoc),['confirm']) || !in_array($action,['arm','subscribe','confirm','status'],true) || $confirmation!=='provision-exact-sns-subscription') { throw new PeraCRM_SNS_Error('sns_authority'); }
        $service=peracrm_sns_provisioning_service();
        if (!$service) { throw new PeraCRM_SNS_Error('sns_configuration'); }
        // Authenticate before reading any credential variable or constructing the API.
        $service->status($confirmation);
        if (in_array($action,['subscribe','confirm'],true)) {
            require_once __DIR__.'/providers/sns-api.php';
            $config=PERACRM_SES_SNS_PROVISIONING;
            $expiry=getenv('PERACRM_SNS_CREDENTIAL_EXPIRY');
            if (!is_string($expiry) || preg_match('/\A[0-9]{10}\z/',$expiry)!==1) { throw new PeraCRM_SNS_Error('sns_configuration'); }
            $credentials=new PeraCRM_Campaign_SES_Credentials($config['account'],(string)getenv('PERACRM_SNS_ACCESS_KEY_ID'),
                (string)getenv('PERACRM_SNS_SECRET_ACCESS_KEY'),(string)getenv('PERACRM_SNS_SESSION_TOKEN'),(int)$expiry);
            $api=new PeraCRM_SNS_API(['account'=>$config['account'],'region'=>$config['region'],'topic'=>$config['topic']],$credentials);
            $service=peracrm_sns_provisioning_service(Closure::fromCallable($api));
            if (!$service) { throw new PeraCRM_SNS_Error('sns_configuration'); }
        }
        $result=match($action) {
            'arm'=>$service->request($confirmation,time()),
            'subscribe'=>$service->subscribe($confirmation,time()),
            'confirm'=>$service->confirm($confirmation,time()),
            'status'=>$service->status($confirmation),
        };
        WP_CLI::line(is_array($result)?wp_json_encode($result):$result);
    } catch (Throwable $e) { WP_CLI::error('SNS provisioning unavailable. Check authority, scope, pending state, storage and network.'); }
}
if (defined('WP_CLI') && WP_CLI) { WP_CLI::add_command('peracrm campaigns sns-subscription','peracrm_sns_provisioning_cli'); }
