<?php
if (!defined('ABSPATH')) { exit; }

/** Fixed diagnostics never expose payloads, URLs, certificates or SQL. */
final class PeraCRM_SNS_Error extends RuntimeException
{
    public function __construct(string $code) { parent::__construct($code); }
}

/** SNS v2 verifier. Notifications and explicitly selected subscription confirmations only.
 * Certificate retrieval is mandatory injection. Production wiring must use the bounded
 * HTTPS helper below; automated fixtures supply local certificates and never use AWS.
 */
final class PeraCRM_SNS_Verifier
{
    private array $scope;
    private $certificate;
    public function __construct(array $scope, callable $certificate)
    {
        if (array_keys($scope) !== ['account','region','topic']
            || !is_string($scope['account']) || !preg_match('/\A[0-9]{12}\z/', $scope['account'])
            || !is_string($scope['region']) || !preg_match('/\A[a-z]{2}-[a-z]+-[0-9]\z/', $scope['region'])
            || !is_string($scope['topic']) || !preg_match('/\Aarn:aws:sns:'.preg_quote($scope['region'],'/').':'.$scope['account'].':[A-Za-z0-9_-]{1,256}\z/', $scope['topic'])) {
            throw new PeraCRM_SNS_Error('sns_configuration');
        }
        $this->scope=$scope; $this->certificate=$certificate;
    }
    public function scope(): array { return $this->scope; }
    public function certificateURL(string $url): bool
    {
        // Exact lexical allowlist excludes ports, userinfo, escapes, query, fragment,
        // alternate DNS spellings and path traversal before any retriever is invoked.
        return preg_match('~\Ahttps://sns\.'.preg_quote($this->scope['region'],'~').'\.amazonaws\.com/SimpleNotificationService-[a-fA-F0-9]{32}\.pem\z~D', $url) === 1;
    }
    public static function canonical(array $envelope): string
    {
        $fields=['Message','MessageId'];
        if (($envelope['Type']??'')==='SubscriptionConfirmation' && isset($envelope['Token'],$envelope['SubscribeURL'])) {
            $fields=array_merge($fields,['SubscribeURL','Timestamp','Token','TopicArn','Type']);
            $bytes='';
            foreach ($fields as $field) { $bytes.=$field."\n".$envelope[$field]."\n"; }
            return $bytes;
        }
        if (array_key_exists('Subject',$envelope)) { $fields[]='Subject'; }
        $fields=array_merge($fields,['Timestamp','TopicArn','Type']);
        $bytes='';
        foreach ($fields as $field) { $bytes.=$field."\n".$envelope[$field]."\n"; }
        return $bytes;
    }
    public function verify(#[\SensitiveParameter] string $body, int $now): array
    {
        return $this->verifyEnvelope($body,$now,false);
    }
    public function verifyConfirmation(#[\SensitiveParameter] string $body, int $now): array
    {
        return $this->verifyEnvelope($body,$now,true);
    }
    private function verifyEnvelope(#[\SensitiveParameter] string $body,int $now,bool $confirmation): array
    {
        try {
            if (strlen($body)>262144 || $now<=0) { throw new PeraCRM_SNS_Error('sns_invalid'); }
            $e=json_decode($body,false,8,JSON_THROW_ON_ERROR);
            if (!$e instanceof stdClass) { throw new PeraCRM_SNS_Error('sns_invalid'); }
            $e=(array)$e;
            $required=['Type','MessageId','TopicArn','Message','Timestamp','SignatureVersion','Signature','SigningCertURL'];
            if ($confirmation) { $required=array_merge($required,['Token','SubscribeURL']); }
            $optional=$confirmation?[]:['Subject','UnsubscribeURL'];
            if (array_diff($required,array_keys($e)) || array_diff(array_keys($e),array_merge($required,$optional))) {
                throw new PeraCRM_SNS_Error('sns_invalid');
            }
            foreach ($e as $value) { if (!is_string($value)) { throw new PeraCRM_SNS_Error('sns_invalid'); } }
            if ($e['Type']!==($confirmation?'SubscriptionConfirmation':'Notification') || $e['SignatureVersion']!=='2'
                || $e['TopicArn']!==$this->scope['topic'] || !$this->certificateURL($e['SigningCertURL'])
                || !preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/', $e['MessageId'])
                || strlen($e['Signature'])>2048 || (isset($e['Subject']) && strlen($e['Subject'])>100)
                || !preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{3})?Z\z/', $e['Timestamp'])) {
                throw new PeraCRM_SNS_Error('sns_invalid');
            }
            if ($confirmation && (preg_match('/\A[A-Za-z0-9_+\/=:-]{1,4096}\z/',$e['Token'])!==1
                || strlen($e['SubscribeURL'])>8192)) { throw new PeraCRM_SNS_Error('sns_invalid'); }
            $format=str_contains($e['Timestamp'],'.')?'!Y-m-d\TH:i:s.v\Z':'!Y-m-d\TH:i:s\Z';
            $date=DateTimeImmutable::createFromFormat($format,$e['Timestamp'],new DateTimeZone('UTC'));
            if (!$date || $date->format(substr($format,1))!==$e['Timestamp']) { throw new PeraCRM_SNS_Error('sns_invalid'); }
            $signature=base64_decode($e['Signature'],true);
            if ($signature===false || base64_encode($signature)!==$e['Signature']) { throw new PeraCRM_SNS_Error('sns_invalid'); }
            try { $pem=($this->certificate)($e['SigningCertURL']); }
            catch (Throwable $error) { throw new PeraCRM_SNS_Error('sns_dependency'); }
            if (!is_string($pem) || strlen($pem)>16384) { throw new PeraCRM_SNS_Error('sns_dependency'); }
            $cert=@openssl_x509_read($pem); $details=$cert?openssl_x509_parse($cert):false;
            $key=$cert?openssl_pkey_get_public($cert):false; $info=$key?openssl_pkey_get_details($key):false;
            if (!$details || !$info || $info['type']!==OPENSSL_KEYTYPE_RSA || $info['bits']<2048
                || $now<$details['validFrom_time_t'] || $now>$details['validTo_time_t']) { throw new PeraCRM_SNS_Error('sns_dependency'); }
            $canonical=self::canonical($e);
            if (openssl_verify($canonical,$signature,$key,OPENSSL_ALGO_SHA256)!==1) { throw new PeraCRM_SNS_Error('sns_authentication'); }
            // Do not decode the SES Message here. Receipt foundations quarantine all
            // authenticated messages; later binding/application must use durable attempts.
            return ['id'=>$e['MessageId'],'canonical'=>$canonical,'hash'=>hash('sha256',$canonical),
                'timestamp'=>$date->getTimestamp(),'certificate_hash'=>hash('sha256',$pem)]+($confirmation?['token'=>$e['Token']]:[]);
        } catch (PeraCRM_SNS_Error $error) { throw $error; }
        catch (Throwable $error) { throw new PeraCRM_SNS_Error('sns_invalid'); }
    }
}

/** No cache or default wiring. HTTPS peer/hostname validation authenticates the
 * AWS certificate origin. Never follow redirects or use an arbitrary URL caller.
 */
function peracrm_sns_certificate_fetch(PeraCRM_SNS_Verifier $verifier, string $url): string
{
    if (!$verifier->certificateURL($url)) { throw new PeraCRM_SNS_Error('sns_invalid'); }
    $response=wp_safe_remote_get($url,['timeout'=>5,'redirection'=>0,'sslverify'=>true,
        'reject_unsafe_urls'=>true,'limit_response_size'=>16385,'cookies'=>[]]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response)!==200) { throw new PeraCRM_SNS_Error('sns_dependency'); }
    $body=wp_remote_retrieve_body($response);
    if (strlen($body)>16384) { throw new PeraCRM_SNS_Error('sns_dependency'); }
    return $body;
}
