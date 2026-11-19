<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/ses.php';
require_once __DIR__.'/ses-credentials.php';

use PeraCRM\SESDeps\Aws\Signature\SignatureV4;
use PeraCRM\SESDeps\GuzzleHttp\Psr7\Request;

/**
 * SES v2 REST/JSON with the packaged AWS SDK SigV4 signer and one cURL execution.
 * Loaded only by fixtures and the explicit internal mailbox probe.
 * The injected executor is a trusted test seam: (URL, cURL options) => raw result
 * {errno,status,headers,body}. It must obey the supplied cURL security policy.
 */
final class PeraCRM_Campaign_SES_SDK_Transport implements PeraCRM_Campaign_SES_Transport
{
    private array $scope;
    private PeraCRM_Campaign_SES_Credentials $credentials;
    private ?Closure $execute;
    private Closure $clock;
    private ?array $lastDiagnostic = null;
    private const UNKNOWN = ['complete'=>false, 'authenticated'=>false];

    public function __construct(array $scope, #[SensitiveParameter] PeraCRM_Campaign_SES_Credentials $credentials,
        ?Closure $execute = null, ?Closure $clock = null)
    {
        if (isset($this->scope) || PHP_VERSION_ID < 80200 || !extension_loaded('curl')
            || !(curl_version()['features'] & CURL_VERSION_ASYNCHDNS)
            || !(curl_version()['features'] & CURL_VERSION_SSL)) {
            throw new RuntimeException('ses_transport_unavailable');
        }
        PeraCRM_CampaignSubmissionV1::validateScope($scope);
        if ($credentials->account() !== $scope['account_id'] || $credentials->isExpired()) {
            throw new RuntimeException('ses_identity_unavailable');
        }
        $this->scope = [];
        foreach ($scope as $key=>$value) { $this->scope[$key] = $value; }
        $this->credentials = $credentials;
        $this->execute = $execute;
        $this->clock = $clock ?? static fn(): int => hrtime(true);
    }

    /** Fixed scalar projection only: never request/response text, addresses or credentials. */
    public function lastDiagnostic(): ?array { return $this->lastDiagnostic; }

    public function send(#[SensitiveParameter] array $request, array $options): array
    {
        return $this->submit($request, $options, false);
    }

    /** Explicit operational preview. Normal campaign validation remains unchanged. */
    public function sendDraftTest(#[SensitiveParameter] array $request): array
    {
        try {
        $keys=array_keys($request); sort($keys);
        $simple=$request['Content']['Simple'] ?? [];
        if ($keys!==['ConfigurationSetName','Content','Destination','EmailTags','FromEmailAddress','ReplyToAddresses']
            || $request['ConfigurationSetName']!==$this->scope['configuration_set']
            || array_keys($request['Destination'])!==['ToAddresses'] || count($request['Destination']['ToAddresses'])!==1
            || !peracrm_campaign_submission_mailbox($request['Destination']['ToAddresses'][0])
            || count($request['ReplyToAddresses'])!==1 || !peracrm_campaign_submission_mailbox($request['ReplyToAddresses'][0])
            || !is_string($request['FromEmailAddress']) || preg_match('/[\r\n]/',$request['FromEmailAddress'])
            || array_keys($request['Content'])!==['Simple'] || array_keys($simple)!==['Subject','Body']
            || array_keys($simple['Body'])!==['Html','Text']
            || count($request['EmailTags'])!==1 || ($request['EmailTags'][0]['Name'] ?? '')!=='peracrm_draft_test'
            || !preg_match('/\A[a-f0-9]{64}\z/',$request['EmailTags'][0]['Value'] ?? '')) { return self::UNKNOWN; }
        foreach ([$simple['Subject'],$simple['Body']['Html'],$simple['Body']['Text']] as $part) {
            if (array_keys($part)!==['Data','Charset'] || !is_string($part['Data']) || $part['Charset']!=='UTF-8' || strlen($part['Data'])>1048576) { return self::UNKNOWN; }
        }
        if (!str_starts_with($simple['Subject']['Data'],'[TEST] ') || preg_match('/[\r\n]/',$simple['Subject']['Data'])
            || str_contains($simple['Body']['Html']['Data'],'{{') || str_contains($simple['Body']['Text']['Data'],'{{')) { return self::UNKNOWN; }
        return $this->submit($request,$this->options(),true);
        } catch (Throwable $error) { return self::UNKNOWN; }
    }

    /** Fixed operational message, outside the subscriber campaign contract. */
    public function sendInternalProbe(): array
    {
        if ($this->scope['account_id'] !== '398074591385' || $this->scope['region'] !== 'eu-north-1'
            || $this->scope['configuration_set'] !== 'peracrm-campaigns') { return self::UNKNOWN; }
        $part = static fn(string $data): array => ['Data'=>$data,'Charset'=>'UTF-8'];
        $url = 'https://www.peraproperty.com/';
        return $this->submit([
            'FromEmailAddress'=>'info@peraproperty.com',
            'Destination'=>['ToAddresses'=>['info@peraproperty.com']],
            'Content'=>['Simple'=>['Subject'=>$part('PeraCRM SES internal delivery probe'),
                'Body'=>['Html'=>$part('<p>Internal delivery probe. <a href="'.$url.'">Pera Property</a></p>'),
                    'Text'=>$part('Internal delivery probe: '.$url)]]],
            'ConfigurationSetName'=>'peracrm-internal-probe',
            'EmailTags'=>[['Name'=>'peracrm_probe','Value'=>'internal']],
        ], $this->options(), true);
    }

    private function options(): array
    {
        return ['provider'=>'ses_v2','account_id'=>$this->scope['account_id'],'region'=>$this->scope['region'],
            'endpoint'=>'https://email.'.$this->scope['region'].'.amazonaws.com',
            'configuration_version'=>$this->scope['configuration_version'],
            'http'=>['connect_timeout'=>3,'timeout'=>20,'verify'=>true,'allow_redirects'=>false],'retries'=>0];
    }

    private function submit(#[SensitiveParameter] array $request, array $options, bool $probe): array
    {
        $start = ($this->clock)();
        $this->lastDiagnostic = ['code'=>'local_validation'];
        try {
            $endpoint = 'https://email.'.$this->scope['region'].'.amazonaws.com';
            if ($options !== $this->options() || $this->credentials->isExpired()
                || (!$probe && !$this->validRequest($request))) { return self::UNKNOWN; }
            $body = json_encode($request, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            // No client middleware, retry handler, credential provider, endpoint discovery or V4a.
            $signed = (new SignatureV4('ses', $this->scope['region']))->signRequest(
                new Request('POST', $endpoint.'/v2/email/outbound-emails', ['Content-Type'=>'application/json'], $body),
                $this->credentials
            );
            $remaining = 20000 - (int) ceil((($this->clock)() - $start) / 1000000);
            if ($remaining <= 0) { $this->lastDiagnostic=['code'=>'preparation_timeout']; return self::UNKNOWN; }
            $headers = [];
            foreach ($signed->getHeaders() as $name=>$values) {
                foreach ($values as $value) { $headers[] = $name.': '.$value; }
            }
            // Suppress libcurl's implicit Expect handshake and response decompression.
            $headers[] = 'Expect:';
            $curl = [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$body, CURLOPT_HTTPHEADER=>$headers,
                CURLOPT_TIMEOUT_MS=>$remaining, CURLOPT_CONNECTTIMEOUT_MS=>min(3000, $remaining),
                CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
                CURLOPT_FOLLOWLOCATION=>false, CURLOPT_MAXREDIRS=>0, CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS, CURLOPT_PROXY=>'', CURLOPT_NETRC=>CURL_NETRC_IGNORED,
                CURLOPT_HTTPAUTH=>CURLAUTH_NONE, CURLOPT_UNRESTRICTED_AUTH=>false,
                CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_1_1, CURLOPT_VERBOSE=>false, CURLOPT_HEADER=>false];
            $this->lastDiagnostic = ['code'=>'executor_exception'];
            $raw = $this->execute !== null ? ($this->execute)((string) $signed->getUri(), $curl) : self::curl((string) $signed->getUri(), $curl);
            $this->lastDiagnostic = self::diagnosticSummary($raw);
            $failure = null;
            $decoded = self::decode($raw, $failure);
            $this->lastDiagnostic['code'] = $failure ?? ($decoded['status']===200 ? 'accepted_response' : 'provider_response');
            if ((($this->clock)() - $start) >= 20000000000 && !isset($decoded['message_id'])) {
                $this->lastDiagnostic['code']='submission_timeout'; return self::UNKNOWN;
            }
            return $decoded;
        } catch (Throwable $error) {
            // Never retain/rethrow the exception, request, bearer, credential or provider body.
            return self::UNKNOWN;
        }
    }

    private function validRequest(array $request): bool
    {
        $keys = array_keys($request); sort($keys);
        if ($keys !== ['ConfigurationSetName','Content','Destination','EmailTags','FromEmailAddress','ReplyToAddresses']
            || $request['ConfigurationSetName'] !== $this->scope['configuration_set']
            || array_keys($request['Destination']) !== ['ToAddresses']
            || !is_array($request['Destination']['ToAddresses']) || count($request['Destination']['ToAddresses']) !== 1
            || array_keys($request['Content']) !== ['Simple']) { return false; }
        $simple = $request['Content']['Simple'];
        $keys = array_keys($simple); sort($keys);
        if ($keys !== ['Body','Headers','Subject'] || array_keys($simple['Body']) !== ['Html','Text']) { return false; }
        foreach ([$simple['Subject'], $simple['Body']['Html'], $simple['Body']['Text']] as $part) {
            if (array_keys($part) !== ['Data','Charset'] || !is_string($part['Data']) || $part['Charset'] !== 'UTF-8') { return false; }
        }
        $headers = $simple['Headers'];
        return is_array($headers) && count($headers) === 2
            && array_keys($headers[0]) === ['Name','Value'] && ($headers[0]['Name'] ?? null) === 'List-Unsubscribe'
            && is_string($headers[0]['Value'] ?? null)
            && preg_match('~\A<https://[^\s<>]+>\z~', $headers[0]['Value']) === 1
            && $headers[1] === ['Name'=>'List-Unsubscribe-Post','Value'=>'List-Unsubscribe=One-Click']
            && count($request['EmailTags']) === 4;
    }

    /** Native cURL returns success only after the full TLS-authenticated transfer. */
    private static function curl(string $url, array $options): array
    {
        $handle = curl_init($url);
        if ($handle === false) { return []; }
        $body = ''; $headers = ''; $overflow = false;
        $options[CURLOPT_WRITEFUNCTION] = static function ($handle, string $chunk) use (&$body, &$overflow): int {
            if (strlen($body) + strlen($chunk) > 65536) { $overflow = true; return 0; }
            $body .= $chunk; return strlen($chunk);
        };
        $options[CURLOPT_HEADERFUNCTION] = static function ($handle, string $line) use (&$headers, &$overflow): int {
            if (strlen($headers) + strlen($line) > 16384) { $overflow = true; return 0; }
            $headers .= $line; return strlen($line);
        };
        try {
            if (!curl_setopt_array($handle, $options)) { return []; }
            $ok = curl_exec($handle);
            $errno=curl_errno($handle);
            if ($errno===0 && ($ok===false || $overflow)) { $errno=$overflow ? 23 : 1; }
            return ['errno'=>$errno,
                'status'=>curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'headers'=>$headers, 'body'=>$body,
                'timings_ms'=>[
                    'dns'=>(int)round(curl_getinfo($handle,CURLINFO_NAMELOOKUP_TIME)*1000),
                    'connect'=>(int)round(curl_getinfo($handle,CURLINFO_CONNECT_TIME)*1000),
                    'tls'=>(int)round(curl_getinfo($handle,CURLINFO_APPCONNECT_TIME)*1000),
                    'total'=>(int)round(curl_getinfo($handle,CURLINFO_TOTAL_TIME)*1000),
                ],
                'uploaded_bytes'=>(int)curl_getinfo($handle,CURLINFO_SIZE_UPLOAD)];
        } finally { curl_close($handle); }
    }

    private static function diagnosticSummary(array $raw): array
    {
        $out=['code'=>'response_invalid'];
        foreach (['errno'=>999,'status'=>599,'uploaded_bytes'=>2097152] as $key=>$max) {
            $value=$raw[$key]??null;
            $out[$key]=is_int($value) && $value>=0 && $value<=$max ? $value : null;
        }
        foreach (['headers'=>16384,'body'=>65536] as $key=>$max) {
            $value=$raw[$key]??null;
            $out[$key.'_bytes']=is_string($value) ? min(strlen($value),$max+1) : null;
        }
        foreach (['dns','connect','tls','total'] as $key) {
            $value=$raw['timings_ms'][$key]??null;
            $out[$key.'_ms']=is_int($value) && $value>=0 && $value<=60000 ? $value : null;
        }
        return $out;
    }

    private static function undecodable(string $code, ?string &$failure): array
    {
        $failure=$code; return self::UNKNOWN;
    }

    private static function decode(array $raw, ?string &$failure=null): array
    {
        if (($raw['errno'] ?? null) !== 0 || !is_int($raw['status'] ?? null)
            || !is_string($raw['headers'] ?? null) || !is_string($raw['body'] ?? null)
            || strlen($raw['headers']) > 16384 || strlen($raw['body']) > 65536) { return self::undecodable('transfer_incomplete',$failure); }
        // Require one complete final response; interim 100 is permitted, redirects/proxy handshakes are not.
        $blocks = explode("\r\n\r\n", $raw['headers']);
        if (array_pop($blocks) !== '') { return self::undecodable('headers_incomplete',$failure); }
        while (count($blocks) > 1 && preg_match('~\AHTTP/1\.[01] 100(?: |\r\n|$)~', $blocks[0])) { array_shift($blocks); }
        if (count($blocks) !== 1) { return self::undecodable('response_blocks',$failure); }
        $lines = explode("\r\n", $blocks[0]);
        $status = array_shift($lines);
        if (!preg_match('~\AHTTP/1\.[01] ([0-9]{3})(?: [^\r\n]*)?\z~', $status, $matches) || (int) $matches[1] !== $raw['status']) { return self::undecodable('status_line',$failure); }
        $headers = [];
        foreach ($lines as $line) {
            if (!preg_match('/\A([!#$%&\x27*+.^_`|~0-9A-Za-z-]+):[ \t]*([^\r\n]*)\z/', $line, $match)) { return self::undecodable('header_format',$failure); }
            $name = strtolower($match[1]);
            if (isset($headers[$name])) { return self::undecodable('duplicate_header',$failure); }
            $headers[$name] = trim($match[2]);
        }
        if (preg_match('~\Aapplication/(?:x-amz-json-1\.1|json)(?:;\s*charset=utf-8)?\z~i', $headers['content-type'] ?? '') !== 1
            || preg_match('/\A[A-Za-z0-9-]{1,128}\z/', $headers['x-amzn-requestid'] ?? '') !== 1
            || isset($headers['content-encoding'])
            || (isset($headers['content-length']) && $headers['content-length'] !== (string) strlen($raw['body']))) { return self::undecodable('response_headers',$failure); }
        // SES returns a flat string-valued object. Reject duplicate (including
        // escaped duplicate) keys rather than trusting json_decode's last value.
        $string = '"(?:[^"\\\\\x00-\x1f]|\\\\(?:["\\\\/bfnrt]|u[0-9a-fA-F]{4}))*"';
        if (!preg_match('~\A\s*\{\s*(?:'.$string.'\s*:\s*'.$string.'\s*(?:,\s*'.$string.'\s*:\s*'.$string.'\s*)*)?\}\s*\z~', $raw['body'])) { return self::undecodable('response_json',$failure); }
        preg_match_all('~('.$string.')\s*:\s*'.$string.'~', $raw['body'], $pairs);
        $seen = [];
        foreach ($pairs[1] as $key) {
            $key = json_decode($key, true, 2, JSON_THROW_ON_ERROR);
            if (isset($seen[$key])) { return self::undecodable('duplicate_json_key',$failure); }
            $seen[$key] = true;
        }
        $data = json_decode($raw['body'], false, 8, JSON_THROW_ON_ERROR);
        if (!$data instanceof stdClass) { return self::undecodable('response_object',$failure); }
        $result = ['complete'=>true, 'authenticated'=>true, 'status'=>$raw['status']];
        if ($raw['status'] === 200) {
            if (isset($headers['x-amzn-errortype']) || array_keys(get_object_vars($data)) !== ['MessageId'] || !is_string($data->MessageId)
                || preg_match('/\A[A-Za-z0-9_-]{1,191}\z/', $data->MessageId) !== 1) { return self::undecodable('acceptance_format',$failure); }
            return $result + ['message_id'=>$data->MessageId];
        }
        // SES REST/JSON may identify errors in x-amzn-errortype or JSON __type.
        $headerCode = isset($headers['x-amzn-errortype']) ? explode(':', $headers['x-amzn-errortype'], 2)[0] : null;
        $bodyCode = isset($data->__type) && is_string($data->__type) ? substr(strrchr('#'.$data->__type, '#'), 1) : null;
        if (($headerCode !== null && $bodyCode !== null && $headerCode !== $bodyCode) || isset($data->MessageId)) { return self::undecodable('error_response_conflict',$failure); }
        $code = $headerCode ?? $bodyCode;
        if (!is_string($code) || preg_match('/\A[A-Za-z]{1,80}\z/', $code) !== 1) { return self::undecodable('error_response_format',$failure); }
        return $result + ['error_code'=>$code];
    }

    public function __debugInfo(): array { return ['transport'=>'ses_v2', 'identity'=>'[redacted]']; }
    public function __serialize(): array { throw new LogicException('ses_transport_serialization_disabled'); }
    public function __unserialize(array $data): void { throw new LogicException('ses_transport_serialization_disabled'); }
    private function __clone() {}
}
