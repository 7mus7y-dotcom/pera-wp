<?php
if (!defined('ABSPATH')) { exit; }
require_once dirname(__DIR__).'/submission.php';

/**
 * Injected transport contract only. No SDK, HTTP client, credentials or factory.
 * Implementer must enforce options across credentials/DNS/TLS/request, perform
 * exactly one SendEmail call using the configured account's signing identity,
 * and assert complete/authenticated only for a full
 * response from the fixed SES endpoint (never a proxy response). Return status,
 * message_id or error_code; raw bodies and exception messages are never retained.
 */
interface PeraCRM_Campaign_SES_Transport
{
    public function send(array $request, array $options): array;
}

/** Mapper only; intentionally not the worker's production Provider implementation. */
final class PeraCRM_Campaign_SES_Mapper
{
    private $scope;
    private $transport;

    public function __construct(array $scope, PeraCRM_Campaign_SES_Transport $transport)
    {
        PeraCRM_CampaignSubmissionV1::validateScope($scope);
        $this->scope = [];
        foreach ($scope as $key=>$value) { $this->scope[$key] = $value; }
        $this->transport = $transport;
    }

    public function submit(PeraCRM_CampaignSubmissionV1 $submission): array
    {
        $data = $submission->fields();
        foreach ($this->scope as $key=>$value) {
            if ($data['scope'][$key] !== $value) { return ['kind'=>'permanent','code'=>'scope_mismatch','readiness_blocked'=>true]; }
        }
        $tags = [];
        foreach (['delivery'=>$data['delivery_key'],'attempt'=>$data['attempt_key'],'snapshot'=>$data['snapshot_hash'],'envelope'=>$submission->hash()] as $name=>$value) {
            $tags[] = ['Name'=>'peracrm_'.$name, 'Value'=>$value];
        }
        $name = self::displayName($data['sender_name']);
        $request = [
            'FromEmailAddress'=>$name.' <'.$data['sender_email'].'>',
            'ReplyToAddresses'=>[$data['reply_to']], 'Destination'=>['ToAddresses'=>[$data['mailbox']]],
            'ConfigurationSetName'=>$this->scope['configuration_set'], 'EmailTags'=>$tags,
            'Content'=>['Simple'=>['Subject'=>['Data'=>$data['subject'],'Charset'=>'UTF-8'],
                'Body'=>['Html'=>['Data'=>$data['html'],'Charset'=>'UTF-8'],'Text'=>['Data'=>$data['text'],'Charset'=>'UTF-8']]]],
        ];
        $options = ['provider'=>'ses_v2','account_id'=>$this->scope['account_id'],'region'=>$this->scope['region'],
            'endpoint'=>'https://email.'.$this->scope['region'].'.amazonaws.com',
            'configuration_version'=>$this->scope['configuration_version'],
            'http'=>['connect_timeout'=>3,'timeout'=>20,'verify'=>true,'allow_redirects'=>false], 'retries'=>0];
        $start = hrtime(true);
        try { $result = $this->transport->send($request, $options); }
        catch (Throwable $e) { return ['kind'=>'unknown','code'=>'ambiguous_submission']; }
        // A late synchronous result cannot prove timely completion/lease ownership.
        if ((hrtime(true) - $start) / 1e9 >= 20) { return ['kind'=>'unknown','code'=>'ambiguous_submission']; }
        return self::result($result);
    }

    /** RFC 2047 words stay below 75 bytes and never split a UTF-8 character. */
    private static function displayName(string $name): string
    {
        if (!preg_match('/[^\x20-\x7e]/', $name)) { return '"'.addcslashes($name, "\\\"").'"'; }
        $words = []; $chunk = '';
        foreach (preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY) as $char) {
            if (strlen($chunk.$char) > 42) { $words[] = '=?UTF-8?B?'.base64_encode($chunk).'?='; $chunk = ''; }
            $chunk .= $char;
        }
        if ($chunk !== '') { $words[] = '=?UTF-8?B?'.base64_encode($chunk).'?='; }
        return implode(' ', $words);
    }

    private static function result(array $result): array
    {
        $unknown = ['kind'=>'unknown','code'=>'ambiguous_submission'];
        if (($result['complete'] ?? null) !== true || ($result['authenticated'] ?? null) !== true) { return $unknown; }
        $status = $result['status'] ?? null;
        if ($status === 200 && is_string($result['message_id'] ?? null)
            && preg_match('/\A[A-Za-z0-9_-]{1,191}\z/', $result['message_id']) === 1 && !isset($result['error_code'])) {
            return ['kind'=>'accepted','message_id'=>$result['message_id'],'code'=>'provider_accepted'];
        }
        if (isset($result['message_id'])) { return $unknown; }
        $code = $result['error_code'] ?? null;
        if (($status === 429 && $code === 'TooManyRequestsException') || ($status === 400 && $code === 'LimitExceededException')) {
            return ['kind'=>'transient','code'=>'provider_rate_limit','retry_after'=>60,'readiness_blocked'=>true];
        }
        $rejections = ['BadRequestException'=>400,'MessageRejected'=>400,'MailFromDomainNotVerifiedException'=>400,
            'AccountSuspendedException'=>400,'SendingPausedException'=>400,'NotFoundException'=>404,'AccessDeniedException'=>403];
        if (is_string($code) && isset($rejections[$code]) && $status === $rejections[$code]) {
            return ['kind'=>'permanent','code'=>'provider_rejected','readiness_blocked'=>true];
        }
        return $unknown;
    }
}
