<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/../submission.php';

/** Fixed diagnostics. Parsed data is never authenticated authority or a retry permit. */
final class PeraCRM_SES_Event_Error extends RuntimeException {}

/** Pure, bounded projection. Unknown fields are ignored, authority-bearing fields are not.
 * Internal/unbootstrapped. It cannot match attempts, apply suppression or send mail.
 */
final class PeraCRM_SES_Event_Normalizer
{
    private array $scope;
    public function __construct(array $scope)
    {
        PeraCRM_CampaignSubmissionV1::validateScope($scope);
        $this->scope=$scope;
    }
    private static function need(bool $ok, string $reason='invalid_event'): void
    {
        if (!$ok) { throw new PeraCRM_SES_Event_Error($reason); }
    }
    private static function object($value): stdClass
    {
        self::need($value instanceof stdClass);
        return $value;
    }
    private static function text($value, int $limit): string
    {
        self::need(is_string($value) && strlen($value)>0 && strlen($value)<=$limit
            && !preg_match('/[\x00-\x1f\x7f]/',$value));
        return $value;
    }
    private static function timestamp($value): string
    {
        self::need(is_string($value) && preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?Z\z/',$value)===1);
        $format=str_contains($value,'.')?'!Y-m-d\TH:i:s.u\Z':'!Y-m-d\TH:i:s\Z';
        $date=DateTimeImmutable::createFromFormat($format,$value,new DateTimeZone('UTC'));
        $errors=DateTimeImmutable::getLastErrors();
        self::need($date!==false && ($errors===false || (!$errors['warning_count'] && !$errors['error_count']))
            && $date->format('Y')>='2000');
        return $date->format('Y-m-d\TH:i:s.u\Z');
    }
    private static function mailbox($value): string
    {
        self::need(peracrm_campaign_submission_mailbox($value));
        // Same case/trim policy as the local subscriber service, without repairing input.
        return strtolower($value);
    }
    private static function recipients($values, bool $objects=false): array
    {
        self::need(is_array($values) && array_is_list($values) && count($values)>0 && count($values)<=50);
        $out=[];
        foreach ($values as $value) {
            if ($objects) { $value=self::object($value)->emailAddress ?? null; }
            $out[]=self::mailbox($value);
        }
        self::need(count(array_unique($out))===count($out));
        sort($out,SORT_STRING); return $out;
    }
    private static function tag(stdClass $tags, string $name): string
    {
        $value=$tags->{$name} ?? null;
        self::need(is_array($value) && count($value)===1 && array_is_list($value),'missing_tags');
        return self::text($value[0],191);
    }
    /** Returns recipient-specific, canonical facts; these remain quarantined until matched.
     * Complaint recipients are candidates, not proof of which individual complained.
     */
    public function normalize(#[\SensitiveParameter] string $message): array
    {
        try {
            self::need(strlen($message)>0 && strlen($message)<=262144);
            $event=self::object(json_decode($message,false,16,JSON_THROW_ON_ERROR));
            $type=$event->eventType ?? $event->notificationType ?? null;
            self::need(is_string($type));
            if (isset($event->eventType,$event->notificationType)) { self::need($event->eventType===$event->notificationType); }
            self::need(in_array($type,['Send','Delivery','Bounce','Complaint','DeliveryDelay'],true),'unsupported_event');
            $mail=self::object($event->mail ?? null);
            self::need(($mail->sendingAccountId ?? null)===$this->scope['account_id'],'wrong_scope');
            $messageId=self::text($mail->messageId ?? null,191);
            self::need(preg_match('/\A[A-Za-z0-9_-]+\z/',$messageId)===1);
            $destinations=self::recipients($mail->destination ?? null);
            $tags=self::object($mail->tags ?? null);
            self::need(self::tag($tags,'ses:configuration-set')===$this->scope['configuration_set'],'wrong_scope');
            $binding=[];
            foreach (['delivery','attempt','snapshot','envelope'] as $key) {
                $value=self::tag($tags,'peracrm_'.$key);
                self::need(preg_match('/\A[a-f0-9]{64}\z/',$value)===1,'invalid_tags');
                $binding[$key]=$value;
            }
            $mailTime=self::timestamp($mail->timestamp ?? null);
            $detail=self::object($event->{lcfirst($type)} ?? null);
            $feedback=''; $subtype=''; $bounce=''; $candidate=false;
            if ($type==='Send') {
                // AWS documents an empty object; allow additive fields without granting authority.
                $recipients=$destinations; $occurred=$mailTime;
            } elseif ($type==='Delivery') {
                $recipients=self::recipients($detail->recipients ?? null);
                $occurred=self::timestamp($detail->timestamp ?? null);
            } elseif ($type==='Bounce') {
                $recipients=self::recipients($detail->bouncedRecipients ?? null,true);
                $occurred=self::timestamp($detail->timestamp ?? null);
                $bounce=$detail->bounceType ?? null;
                self::need(in_array($bounce,['Permanent','Transient','Undetermined'],true));
                $subtype=self::text($detail->bounceSubType ?? null,64);
                $feedback=self::text($detail->feedbackId ?? null,191);
            } elseif ($type==='Complaint') {
                $recipients=self::recipients($detail->complainedRecipients ?? null,true);
                $occurred=self::timestamp($detail->timestamp ?? null);
                $feedback=self::text($detail->feedbackId ?? null,191);
                $subtype=isset($detail->complaintFeedbackType)?self::text($detail->complaintFeedbackType,64):'unspecified';
                $candidate=true;
            } else {
                $recipients=self::recipients($detail->delayedRecipients ?? null,true);
                $occurred=self::timestamp($detail->timestamp ?? null);
                $subtype=self::text($detail->delayType ?? null,64);
            }
            self::need(!array_diff($recipients,$destinations),'wrong_recipient');
            $out=[];
            foreach ($recipients as $recipient) {
                $fact=['version'=>1,'account'=>$this->scope['account_id'],'region'=>$this->scope['region'],
                    'configuration_set'=>$this->scope['configuration_set'],'message_id'=>$messageId,
                    'type'=>$type,'recipient'=>$recipient,'recipient_is_candidate'=>$candidate,
                    'tags'=>$binding,'mail_timestamp'=>$mailTime,'occurred_at'=>$occurred,
                    'feedback_id'=>$feedback,'bounce_type'=>$bounce,'subtype'=>$subtype];
                // feedbackId identifies one logical feedback fact; changed facts under that
                // identity conflict. Without it use the complete stable normalized projection.
                $identity=$feedback!==''?array_intersect_key($fact,array_flip([
                    'version','account','region','configuration_set','message_id','type','recipient','feedback_id'])):$fact;
                $out[]=['key'=>hash('sha256',json_encode($identity,JSON_THROW_ON_ERROR)),
                    'json'=>json_encode($fact,JSON_THROW_ON_ERROR)];
            }
            usort($out,static fn($a,$b)=>strcmp($a['key'],$b['key']));
            return $out;
        } catch (PeraCRM_SES_Event_Error $error) { throw $error; }
        catch (Throwable $error) { throw new PeraCRM_SES_Event_Error('invalid_event'); }
    }
}
