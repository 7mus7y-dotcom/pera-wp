<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/ses-suppression-reader.php';

/** Fixed regional reads using an explicitly supplied temporary identity. */
final class PeraCRM_SES_Operator_API
{
    public function __construct(private array $pins, private string $role,
        #[SensitiveParameter] private PeraCRM_Campaign_SES_Credentials $credentials,
        #[SensitiveParameter] private ?Closure $transport=null) {}

    public function __debugInfo(): array { return ['redacted'=>true]; }
    public function __serialize(): array { throw new LogicException('operator_serialization_disabled'); }

    private function read(string $service,string $path): string
    {
        if ($this->credentials->isExpired()) { throw new RuntimeException('operator_identity'); }
        $region=$this->pins['region'];
        $host=$service==='sts'?'sts':'email';
        $request=(new PeraCRM\SESDeps\Aws\Signature\SignatureV4($service,$region))->signRequest(
            new PeraCRM\SESDeps\GuzzleHttp\Psr7\Request('GET','https://'.$host.'.'.$region.'.amazonaws.com'.$path),$this->credentials);
        $response=$this->execute($request);
        if (!$response['complete'] || $response['status']!==200) { throw new RuntimeException('operator_read'); }
        return $response['body'];
    }

    public function attest(): void
    {
        PeraCRM_SES_Suppression_HTTP::validateScope($this->pins);
        if ($this->credentials->account()!==$this->pins['account_id'] || !$this->credentials->getSecurityToken()
            || $this->credentials->isExpired() || $this->credentials->getExpiration()>time()+3600) {
            throw new RuntimeException('operator_identity');
        }
        $xml=$this->read('sts','/?Action=GetCallerIdentity&Version=2011-06-15');
        if (preg_match('/<!|[^\x09\x0a\x0d\x20-\x7e]/',$xml)) { throw new RuntimeException('operator_identity'); }
        $previous=libxml_use_internal_errors(true);
        try { $root=simplexml_load_string($xml,SimpleXMLElement::class,LIBXML_NONET); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $ns='https://sts.amazonaws.com/doc/2011-06-15/';
        if (!$root || $root->getName()!=='GetCallerIdentityResponse' || ($root->getDocNamespaces()['']??null)!==$ns) {
            throw new RuntimeException('operator_identity');
        }
        $result=$root->children($ns)->GetCallerIdentityResult;
        $account=$this->pins['account_id'];
        $prefix='arn:aws:sts::'.$account.':assumed-role/'.$this->role.'/';
        if (count($result)!==1 || count($result->Account)!==1 || count($result->Arn)!==1
            || (string)$result->Account!==$account || !str_starts_with((string)$result->Arn,$prefix)
            || preg_match('/\A[A-Za-z0-9_=,.@+-]{2,64}\z/',substr((string)$result->Arn,strlen($prefix)))!==1) {
            throw new RuntimeException('operator_identity');
        }
    }

    public function quota(): array
    {
        return PeraCRM_SES_Suppression_HTTP::decodeJsonObject($this->read('ses','/v2/email/account'));
    }

    public function reader(): PeraCRM_SES_Suppression_Reader
    {
        return new PeraCRM_SES_Suppression_HTTP($this->pins,$this->credentials,fn($request)=>$this->execute($request));
    }

    private function execute(#[SensitiveParameter] $request): array
    {
        $raw=$this->transport?($this->transport)($request):self::curl($request);
        $raw['authenticated']=$raw['complete'];
        $raw['code']=null;
        if ($raw['complete'] && $raw['status']===400) {
            $error=json_decode($raw['body'],true,8);
            $type=$raw['error_type']??($error['__type']??null);
            if (is_string($type) && preg_match('/(?:\A|#)InvalidNextTokenException(?::|\z)/',$type)) { $raw['code']='InvalidNextTokenException'; }
        }
        return $raw;
    }

    private static function curl(#[SensitiveParameter] $request): array
    {
        $result=['complete'=>false,'status'=>0,'body'=>''];
        if (!function_exists('curl_version') || (curl_version()['features']&(CURL_VERSION_ASYNCHDNS|CURL_VERSION_SSL))
            !==(CURL_VERSION_ASYNCHDNS|CURL_VERSION_SSL)) { return $result; }
        $handle=curl_init((string)$request->getUri());
        if (!$handle) { return $result; }
        $headers=[];
        foreach ($request->getHeaders() as $name=>$values) { $headers[]=$name.': '.implode(',',$values); }
        $body=''; $size=0; $encoding=false; $errorType=null;
        try {
            $ok=curl_setopt_array($handle,[CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>$headers,
                CURLOPT_TIMEOUT_MS=>3000,CURLOPT_CONNECTTIMEOUT_MS=>1000,CURLOPT_NOSIGNAL=>true,
                CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
                CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROXY=>'',CURLOPT_NETRC=>CURL_NETRC_IGNORED,
                CURLOPT_HTTPAUTH=>CURLAUTH_NONE,CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_1_1,CURLOPT_VERBOSE=>false,
                CURLOPT_WRITEFUNCTION=>static function ($h,string $chunk) use (&$body): int {
                    if (strlen($body)+strlen($chunk)>32768) { return 0; }
                    $body.=$chunk; return strlen($chunk);
                },
                CURLOPT_HEADERFUNCTION=>static function ($h,string $line) use (&$size,&$encoding,&$errorType): int {
                    $size+=strlen($line);
                    if ($size>16384) { return 0; }
                    if (stripos($line,'Content-Encoding:')===0 && strtolower(trim(substr($line,17)))!=='identity') { $encoding=true; }
                    if (stripos($line,'x-amzn-errortype:')===0) { $errorType=trim(substr($line,17)); }
                    return strlen($line);
                }]);
            if (!$ok || curl_exec($handle)!==true || $encoding) { return $result; }
            return ['complete'=>true,'status'=>(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE),'body'=>$body,'error_type'=>$errorType];
        } finally { curl_close($handle); }
    }
}
