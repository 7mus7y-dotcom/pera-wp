<?php
/** Synthetic credentials and injected HTTP only. Native cURL is never executed. */
require __DIR__.'/campaign-snapshots-test.php';
require __DIR__.'/recipient-persistence-fixture.php';
require_once dirname(__DIR__).'/inc/campaigns/providers/ses-sdk-transport.php';

$vendor = dirname(__DIR__).'/vendor-ses/';
$lock = json_decode(file_get_contents($vendor.'dependencies.lock.json'),true,32,JSON_THROW_ON_ERROR);
foreach ($lock['packages'] as $package) {
    foreach ($package['files'] as $file) {
        campaign_expect(hash_file('sha256',$vendor.$file['target']) === $file['packaged_sha256'], 'locked dependency bytes '.$file['target']);
        if (str_ends_with($file['target'], '.php')) {
            $source=file_get_contents($vendor.$file['target']);
            campaign_expect(preg_match('/^namespace ([^;]+);$/m', $source, $namespace) === 1
                && str_starts_with($namespace[1], 'PeraCRM\\SESDeps\\'), 'all packaged declarations are privately namespaced '.$file['target']);
        }
    }
}

$checksumException = new PeraCRM\SESDeps\Aws\Exception\CouldNotCreateChecksumException('sha256');
campaign_expect($checksumException instanceof PeraCRM\SESDeps\Aws\MonitoringEventsInterface
    && $checksumException->getMonitoringEvents() === [], 'checksum exception resolves its private monitoring interface and trait');
campaign_expect(!trait_exists('Aws\\HasMonitoringEventsTrait', false) && !interface_exists('Aws\\MonitoringEventsInterface', false),
    'loading root monitoring dependencies never publishes public AWS symbols');

[$binding,$output,$submission] = peracrm_test_persistence_values($saved['payload']['content'], 2, 2);
$fields = $submission->fields(); $scope = $fields['scope'];
$key = 'AKIDEXAMPLE1234567890'; $secret = 'syntheticSecretKeyForMockSigningOnly123456'; $token = 'synthetic-session-token';
$identity = new PeraCRM_Campaign_SES_Credentials($scope['account_id'], $key, $secret, $token);
$raw = static function (int $status = 200, string $body = '{"MessageId":"fixture-sdk-id"}', array $extra = []): array {
    $headers = ['Content-Type'=>'application/json','x-amzn-RequestId'=>'synthetic-request-123','Content-Length'=>(string) strlen($body)];
    $headers = array_replace($headers, $extra);
    $lines = "HTTP/1.1 $status Fixture\r\n";
    foreach ($headers as $name=>$value) { if ($value !== null) { $lines .= "$name: $value\r\n"; } }
    return ['errno'=>0,'status'=>$status,'body'=>$body,'headers'=>$lines."\r\n"];
};
$response = $raw(); $calls = [];
$execute = static function (string $url, array $options) use (&$calls, &$response): array {
    $calls[] = [$url,$options]; return $response;
};
$transport = new PeraCRM_Campaign_SES_SDK_Transport($scope, $identity, $execute);
$mapper = new PeraCRM_Campaign_SES_Mapper($scope, $transport);
campaign_expect($mapper->submit($submission) === ['kind'=>'accepted','message_id'=>'fixture-sdk-id','code'=>'provider_accepted'] && count($calls) === 1, 'one SDK-signed HTTP submission');
[$url,$curl] = $calls[0];
campaign_expect($url === 'https://email.eu-west-1.amazonaws.com/v2/email/outbound-emails', 'fixed regional SES v2 endpoint');
$request = json_decode($curl[CURLOPT_POSTFIELDS], true, 16, JSON_THROW_ON_ERROR);
$simple = $request['Content']['Simple'];
campaign_expect($simple['Subject']['Data'] === $fields['subject'] && $simple['Body']['Html']['Data'] === $fields['html']
    && $simple['Body']['Text']['Data'] === $fields['text'] && $simple['Headers'] === [
        ['Name'=>'List-Unsubscribe','Value'=>'<'.$fields['unsubscribe_url'].'>'],
        ['Name'=>'List-Unsubscribe-Post','Value'=>'List-Unsubscribe=One-Click']], 'exact frozen subject, HTML/text and prepared V2 headers');
campaign_expect($request['FromEmailAddress'] === '"Fixture" <'.$fields['sender_email'].'>' && $request['ReplyToAddresses'] === [$fields['reply_to']]
    && $request['Destination'] === ['ToAddresses'=>[$fields['mailbox']]] && $request['ConfigurationSetName'] === $scope['configuration_set'], 'captured sender, reply-to, recipient and configuration');
campaign_expect($request['EmailTags'] === [
    ['Name'=>'peracrm_delivery','Value'=>$fields['delivery_key']],['Name'=>'peracrm_attempt','Value'=>$fields['attempt_key']],
    ['Name'=>'peracrm_snapshot','Value'=>$fields['snapshot_hash']],['Name'=>'peracrm_envelope','Value'=>$submission->hash()]], 'exact immutable correlation tags');
campaign_expect($curl[CURLOPT_POSTFIELDS] === json_encode($request, JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), 'exact JSON request bytes');
campaign_expect($curl[CURLOPT_SSL_VERIFYPEER] === true && $curl[CURLOPT_SSL_VERIFYHOST] === 2 && $curl[CURLOPT_FOLLOWLOCATION] === false
    && $curl[CURLOPT_MAXREDIRS] === 0 && $curl[CURLOPT_PROTOCOLS] === CURLPROTO_HTTPS && $curl[CURLOPT_PROXY] === ''
    && $curl[CURLOPT_NETRC] === CURL_NETRC_IGNORED && $curl[CURLOPT_HTTPAUTH] === CURLAUTH_NONE && $curl[CURLOPT_VERBOSE] === false
    && $curl[CURLOPT_TIMEOUT_MS] > 0 && $curl[CURLOPT_TIMEOUT_MS] <= 20000 && $curl[CURLOPT_CONNECTTIMEOUT_MS] === 3000, 'TLS, no redirect/proxy credentials and bounded timeouts');
$headers = [];
foreach ($curl[CURLOPT_HTTPHEADER] as $line) { [$name,$value] = explode(':',$line,2); $headers[strtolower($name)] = trim($value); }
campaign_expect($headers['x-amz-security-token'] === $token && $headers['content-type'] === 'application/json'
    && $headers['host'] === 'email.eu-west-1.amazonaws.com' && $headers['expect'] === '', 'actual signed request headers');
// Independently verify the actual SDK-produced signature against exact captured bytes.
$date = $headers['x-amz-date']; $day = substr($date,0,8); $signingScope = "$day/eu-west-1/ses/aws4_request";
$names = 'content-type;host;x-amz-date;x-amz-security-token';
$canonicalHeaders = '';
foreach (explode(';',$names) as $name) { $canonicalHeaders .= "$name:{$headers[$name]}\n"; }
$canonical = "POST\n/v2/email/outbound-emails\n\n".$canonicalHeaders."\n$names\n".hash('sha256',$curl[CURLOPT_POSTFIELDS]);
$signingKey = hash_hmac('sha256',$day,'AWS4'.$secret,true);
foreach (['eu-west-1','ses','aws4_request'] as $value) { $signingKey = hash_hmac('sha256',$value,$signingKey,true); }
$signature = hash_hmac('sha256',"AWS4-HMAC-SHA256\n$date\n$signingScope\n".hash('sha256',$canonical),$signingKey);
campaign_expect($headers['authorization'] === "AWS4-HMAC-SHA256 Credential=$key/$signingScope, SignedHeaders=$names, Signature=$signature", 'SDK signature signs fixed region/service, session token, content type and exact payload');

$cases = [[429,'TooManyRequestsException','transient'],[400,'LimitExceededException','transient'],[400,'ThrottlingException','transient'],
    [400,'BadRequestException','permanent'],[400,'MessageRejected','permanent'],[400,'MailFromDomainNotVerifiedException','permanent'],
    [400,'AccountSuspendedException','permanent'],[400,'SendingPausedException','permanent'],[404,'NotFoundException','permanent'],[403,'AccessDeniedException','permanent'],
    [500,'InternalServiceErrorException','unknown'],[503,'ServiceUnavailableException','unknown'],[403,'UnrecognizedClientException','unknown'],[400,'FutureError','unknown'],[500,'MessageRejected','unknown']];
foreach ($cases as [$status,$code,$kind]) {
    foreach (['header','body','both'] as $mode) {
        $json = ['message'=>'secret diagnostic recipient@example.test '.$token];
        if ($mode !== 'header') { $json['__type'] = 'com.amazonaws.ses#'.$code; }
        $response = $raw($status,json_encode($json),$mode !== 'body' ? ['x-amzn-errortype'=>$code.':detail'] : []);
        $before = count($calls); $result = $mapper->submit($submission);
        campaign_expect($result['kind'] === $kind && count($calls) === $before + 1, 'documented/ambiguous error maps with no automatic retries '.$mode.' '.$code);
        campaign_expect(!str_contains(json_encode($result),$token) && !str_contains(json_encode($result),'recipient@'), 'provider messages are never retained');
    }
}
$ambiguous = [
    $raw(200,'{"MessageId":'),$raw(200,'{}'),$raw(200,'[]'),$raw(200,'{"MessageId":123}'),$raw(200,'{"MessageId":"x","extra":"y"}'),
    $raw(200,'{"MessageId":"x","MessageId":"y"}'),$raw(200,'{"MessageId":"x","Message\\u0049d":"y"}'),
    $raw(200,'{"MessageId":"x"} garbage'),$raw(200,'{"MessageId":"x"}', ['Content-Length'=>'999']),
    $raw(429,'{"message":"slow"}'),$raw(429,'{"__type":"TooManyRequestsException"}', ['x-amzn-errortype'=>'BadRequestException']),
    $raw(429,'{"__type":"TooManyRequestsException"}', ['x-amzn-RequestId'=>null]),
    $raw(429,'{"__type":"TooManyRequestsException"}', ['Content-Type'=>'text/html']),
    $raw(200,'{"MessageId":"x"}', ['Content-Encoding'=>'gzip']),$raw(302,'{"MessageId":"x"}'),
    $raw(200,'{"MessageId":"x"}', ['x-amzn-errortype'=>'MessageRejected']),
    array_replace($raw(),['errno'=>28]),array_replace($raw(),['errno'=>56]),array_replace($raw(),['errno'=>7]),
    array_replace($raw(),['headers'=>"HTTP/1.1 200 OK\r\n"]),array_replace($raw(),['headers'=>$raw()['headers'].$raw()['headers']]),
    array_replace($raw(),['headers'=>str_replace("\r\n\r\n","\r\nContent-Type: application/json\r\n\r\n",$raw()['headers'])]),
    $raw(200,str_repeat('x',65537)),
];
foreach ($ambiguous as $response) {
    $before=count($calls);
    campaign_expect($mapper->submit($submission) === ['kind'=>'unknown','code'=>'ambiguous_submission'] && count($calls) === $before+1, 'ambiguous response stays unknown after exactly one request');
}
$response=$raw(); $response['headers']="HTTP/1.1 100 Continue\r\n\r\n".$response['headers'];
campaign_expect($mapper->submit($submission)['kind'] === 'accepted', 'complete final response after interim 100');
$throwing = new PeraCRM_Campaign_SES_SDK_Transport($scope,$identity,static function () use ($secret,$token): array { throw new RuntimeException($secret.$token); });
campaign_expect((new PeraCRM_Campaign_SES_Mapper($scope,$throwing))->submit($submission)['kind']==='unknown','thrown HTTP exception is unknown and redacted');

// Capture the mapper's exact existing request/options contract without sending.
$capture = new class implements PeraCRM_Campaign_SES_Transport {
    public array $args;
    public function send(array $request,array $options): array { $this->args=[$request,$options]; return []; }
};
(new PeraCRM_Campaign_SES_Mapper($scope,$capture))->submit($submission);
[$prepared,$options] = $capture->args;
foreach (['endpoint'=>'https://attacker.test','account_id'=>'999999999999','region'=>'us-east-1','retries'=>1,'configuration_version'=>2] as $field=>$value) {
    $before=count($calls); $transport->send($prepared,array_replace($options,[$field=>$value]));
    campaign_expect(count($calls)===$before,'scope/retry tampering rejected before HTTP');
}
foreach (['timeout'=>21,'connect_timeout'=>4,'verify'=>false,'allow_redirects'=>true] as $field=>$value) {
    $bad=$options; $bad['http'][$field]=$value; $before=count($calls); $transport->send($prepared,$bad);
    campaign_expect(count($calls)===$before,'HTTP security option tampering rejected before HTTP');
}
foreach (['legacy','multiple','managed','config'] as $case) {
    $bad=$prepared;
    if ($case==='legacy') { unset($bad['Content']['Simple']['Headers']); }
    if ($case==='multiple') { $bad['Destination']['ToAddresses'][]='second@example.test'; }
    if ($case==='managed') { $bad['ListManagementOptions']=[]; }
    if ($case==='config') { $bad['ConfigurationSetName']='other'; }
    $before=count($calls); $transport->send($bad,$options);
    campaign_expect(count($calls)===$before,'V1 or changed request scope rejected before HTTP');
}
$ticks=[0,500000000,600000000];
$budget = new PeraCRM_Campaign_SES_SDK_Transport($scope,$identity,$execute,static function () use (&$ticks): int { return array_shift($ticks); });
$response=$raw(); $budget->send($prepared,$options); $last=$calls[array_key_last($calls)][1];
campaign_expect($last[CURLOPT_TIMEOUT_MS]===19500 && $last[CURLOPT_CONNECTTIMEOUT_MS]===3000,'signing/preparation time consumes the existing total budget');
$ticks=[0,20000000000]; $before=count($calls);
$budget = new PeraCRM_Campaign_SES_SDK_Transport($scope,$identity,$execute,static function () use (&$ticks): int { return array_shift($ticks); });
$budget->send($prepared,$options); campaign_expect(count($calls)===$before,'exhausted budget never starts HTTP');
foreach ([200,429] as $status) {
    $ticks=[0,0,20000000000]; $response=$status===200 ? $raw() : $raw(429,'{"__type":"TooManyRequestsException"}');
    $budget = new PeraCRM_Campaign_SES_SDK_Transport($scope,$identity,$execute,static function () use (&$ticks): int { return array_shift($ticks); });
    $result=$budget->send($prepared,$options);
    campaign_expect($status===200 ? isset($result['message_id']) : $result['complete']===false,'late acceptance retained; late negative becomes unknown');
}
$reject = static function (callable $action): void {
    try { $action(); } catch (Throwable $e) { campaign_expect(!str_contains($e->getMessage(),'synthetic') && $e->getPrevious()===null,'fixed unchained identity/transport diagnostic'); return; }
    throw new RuntimeException('Expected rejection');
};
$reject(fn()=>new PeraCRM_Campaign_SES_Credentials($scope['account_id'],$key,$secret,$token,time()+1));
$other = new PeraCRM_Campaign_SES_Credentials('999999999999',$key,$secret);
$reject(fn()=>new PeraCRM_Campaign_SES_SDK_Transport($scope,$other,$execute));
$reject(fn()=>$identity->__construct($scope['account_id'],$key,$secret));
$reject(fn()=>$transport->__construct($scope,$identity,$execute));
$reject(fn()=>serialize($identity)); $reject(fn()=>serialize($transport)); $reject(fn()=>$identity->toArray());
ob_start(); var_dump($identity,$transport); $debug=ob_get_clean();
foreach ([$key,$secret,$token,$fields['mailbox'],$fields['unsubscribe_url']] as $private) { campaign_expect(!str_contains($debug,$private),'debug projection excludes secrets and recipient authority'); }
campaign_expect(!class_exists('Aws\\Signature\\SignatureV4',false) && !class_exists('GuzzleHttp\\Psr7\\Request',false),'private autoload does not publish public AWS/Guzzle classes');
campaign_expect($submission->fields()===$fields && $output->fields()['unsubscribe_url']===$fields['unsubscribe_url'],'transport never mutates prepared content or issues replacement tokens');
$unicode=$fields; $unicode['subject']='Frozen café / 東京'; $unicode['sender_name']='Équipe 東京';
$unicode['html']='<p>Exact café &amp; 東京</p>'; $unicode['text']="Exact café & 東京\n";
$unicode=new PeraCRM_CampaignSubmissionV1($unicode); $response=$raw();
campaign_expect($mapper->submit($unicode)['kind']==='accepted','UTF-8 prepared content signs and submits');
$unicodeRequest=json_decode($calls[array_key_last($calls)][1][CURLOPT_POSTFIELDS],true,16,JSON_THROW_ON_ERROR);
campaign_expect($unicodeRequest['Content']['Simple']['Subject']['Data']===$unicode->fields()['subject']
    && $unicodeRequest['Content']['Simple']['Body']['Html']['Data']===$unicode->fields()['html']
    && $unicodeRequest['Content']['Simple']['Body']['Text']['Data']===$unicode->fields()['text']
    && str_starts_with($unicodeRequest['FromEmailAddress'],'=?UTF-8?B?'),'UTF-8 subject/body and existing RFC 2047 sender mapping preserved');
$permanentIdentity=new PeraCRM_Campaign_SES_Credentials($scope['account_id'],$key,$secret);
$withoutToken=new PeraCRM_Campaign_SES_SDK_Transport($scope,$permanentIdentity,$execute);
(new PeraCRM_Campaign_SES_Mapper($scope,$withoutToken))->submit($submission);
campaign_expect(!str_contains(implode("\n",$calls[array_key_last($calls)][1][CURLOPT_HTTPHEADER]),'X-Amz-Security-Token'),'explicit static credentials do not acquire a session token');
echo "PASS SES SDK signing, fixed HTTP policy, decoding, ambiguity and redaction\n";
