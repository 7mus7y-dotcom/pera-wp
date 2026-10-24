<?php
require_once __DIR__.'/../inc/campaigns/event-receipts.php';
function sns_expect($ok,string $label): void { if (!$ok) { throw new RuntimeException('FAIL SNS '.$label); } echo 'PASS SNS '.$label."\n"; }
function sns_reject(callable $fn,string $code): void
{
    try { $fn(); } catch (PeraCRM_SNS_Error $error) { sns_expect($error->getMessage()===$code && $error->getPrevious()===null,$code); return; }
    throw new RuntimeException('FAIL SNS expected '.$code);
}
function sns_values(): array
{
    $key=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
    $csr=openssl_csr_new(['commonName'=>'synthetic.example.test'],$key,['digest_alg'=>'sha256']);
    $cert=openssl_csr_sign($csr,null,$key,2,['digest_alg'=>'sha256']);
    openssl_x509_export($cert,$pem); openssl_pkey_export($key,$private);
    return ['pem'=>$pem,'key'=>$private,'now'=>time(),'scope'=>['account'=>'123456789012','region'=>'eu-west-1','topic'=>'arn:aws:sns:eu-west-1:123456789012:fixture']];
}
function sns_body(array $v,array $changes=[]): string
{
    $e=array_replace(['Type'=>'Notification','MessageId'=>'12345678-1234-1234-1234-123456789abc',
        'TopicArn'=>$v['scope']['topic'],'Message'=>'{"eventType":"Send","mail":{"messageId":"synthetic"}}',
        'Timestamp'=>gmdate('Y-m-d\TH:i:s\Z',$v['now']),'SignatureVersion'=>'2',
        'SigningCertURL'=>'https://sns.eu-west-1.amazonaws.com/SimpleNotificationService-'.str_repeat('a',32).'.pem'], $changes);
    openssl_sign(PeraCRM_SNS_Verifier::canonical($e),$signature,$v['key'],OPENSSL_ALGO_SHA256);
    $e['Signature']=base64_encode($signature); return json_encode($e,JSON_THROW_ON_ERROR);
}
function sns_verifier(array $v): PeraCRM_SNS_Verifier { return new PeraCRM_SNS_Verifier($v['scope'],static fn($url)=>$v['pem']); }
