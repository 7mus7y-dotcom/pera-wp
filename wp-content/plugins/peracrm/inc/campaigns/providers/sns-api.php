<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/ses-credentials.php';
require_once __DIR__.'/sns.php';

/** Fixed SNS Query API; no SubscribeURL, discovery, redirects, retries or SDK client logging. */
final class PeraCRM_SNS_API
{
    private array $scope;
    private PeraCRM_Campaign_SES_Credentials $credentials;
    private ?Closure $execute;
    public function __construct(array $scope,#[SensitiveParameter] PeraCRM_Campaign_SES_Credentials $credentials,?Closure $execute=null)
    {
        new PeraCRM_SNS_Verifier($scope,static fn()=>null);
        if ($credentials->account()!==$scope['account'] || $credentials->isExpired()) { throw new PeraCRM_SNS_Error('sns_configuration'); }
        $this->scope=$scope; $this->credentials=$credentials; $this->execute=$execute;
    }
    public function __debugInfo(): array { return ['redacted'=>true]; }
    public function __serialize(): array { throw new LogicException('sns_serialization_disabled'); }
    public function __invoke(string $action,#[SensitiveParameter] array $parameters): array
    {
        try {
            if (!in_array($action,['Subscribe','ConfirmSubscription','GetSubscriptionAttributes'],true) || $this->credentials->isExpired()) { throw new RuntimeException(); }
            $keys=array_keys($parameters); sort($keys);
            $expected=match($action) {
                'Subscribe'=>['Endpoint','Protocol','ReturnSubscriptionArn','TopicArn'],
                'ConfirmSubscription'=>['AuthenticateOnUnsubscribe','Token','TopicArn'],
                default=>['SubscriptionArn'],
            };
            if ($keys!==$expected || ($action!=='GetSubscriptionAttributes' && ($parameters['TopicArn']??null)!==$this->scope['topic'])
                || ($action==='GetSubscriptionAttributes' && !str_starts_with($parameters['SubscriptionArn'],$this->scope['topic'].':'))
                || ($action==='Subscribe' && ($parameters['Protocol']!=='https' || !str_starts_with($parameters['Endpoint'],'https://')))
                || ($action==='ConfirmSubscription' && $parameters['AuthenticateOnUnsubscribe']!=='true')) { throw new RuntimeException(); }
            $url='https://sns.'.$this->scope['region'].'.amazonaws.com/';
            $body=http_build_query(['Action'=>$action,'Version'=>'2010-03-31']+$parameters,'','&',PHP_QUERY_RFC3986);
            $request=new PeraCRM\SESDeps\GuzzleHttp\Psr7\Request('POST',$url,['Content-Type'=>'application/x-www-form-urlencoded; charset=utf-8'],$body);
            $signed=(new PeraCRM\SESDeps\Aws\Signature\SignatureV4('sns',$this->scope['region']))->signRequest($request,$this->credentials);
            $headers=[];
            foreach ($signed->getHeaders() as $name=>$values) { $headers[$name]=implode(',',$values); }
            $options=['method'=>'POST','body'=>$body,'headers'=>$headers,'timeout'=>10,'redirection'=>0,'sslverify'=>true,
                'reject_unsafe_urls'=>true,'limit_response_size'=>65537,'cookies'=>[]];
            $response=$this->execute?($this->execute)($url,$options):wp_safe_remote_post($url,$options);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response)!==200) { throw new RuntimeException(); }
            $xml=wp_remote_retrieve_body($response);
            if (!is_string($xml) || strlen($xml)>65536 || stripos($xml,'<!DOCTYPE')!==false || stripos($xml,'<!ENTITY')!==false) { throw new RuntimeException(); }
            $previous=libxml_use_internal_errors(true);
            try { $root=simplexml_load_string($xml,SimpleXMLElement::class,LIBXML_NONET); }
            finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
            if (!$root || $root->getName()!==$action.'Response') { throw new RuntimeException(); }
            $result=$root->{$action.'Result'};
            if ($action!=='GetSubscriptionAttributes') { return ['SubscriptionArn'=>(string)$result->SubscriptionArn]; }
            $out=[];
            foreach ($result->Attributes->entry as $entry) {
                $key=(string)$entry->key;
                if (isset($out[$key])) { throw new RuntimeException(); }
                $out[$key]=(string)$entry->value;
            }
            return $out;
        } catch (Throwable $e) { throw new PeraCRM_SNS_Error('sns_dependency'); }
    }
}
