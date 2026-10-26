<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/ses-credentials.php';

final class PeraCRM_SES_Sync_Error extends RuntimeException
{
    public function __construct(string $code = 'sync_storage')
    {
        parent::__construct(in_array($code, ['sync_storage','sync_scope','sync_invalid_page','sync_unsupported_reason',
            'sync_read_failed','sync_invalid_cursor','sync_stale_cursor','sync_cursor_cycle','sync_overlap'], true) ? $code : 'sync_storage');
    }
}

/** Trusted internal reader, never populated by request JSON or a verified flag. */
interface PeraCRM_SES_Suppression_Reader
{
    public function scope(): array;
    public function page(#[SensitiveParameter] ?string $cursor): array;
}

/** Mock-only construction. No native executor, discovery, writes or production factory.
 * Uses #1485's private AWS signer. Read policy: two attempts, each <=3 seconds,
 * no backoff/sleep; retry only incomplete transport, 429 or 5xx. Send is untouched.
 */
final class PeraCRM_SES_Suppression_HTTP implements PeraCRM_SES_Suppression_Reader
{
    private array $pins;
    private PeraCRM_Campaign_SES_Credentials $credentials;
    private Closure $execute;
    public function __construct(array $pins, #[SensitiveParameter] PeraCRM_Campaign_SES_Credentials $credentials,
        #[SensitiveParameter] Closure $execute)
    {
        if (isset($this->pins)) { throw new PeraCRM_SES_Sync_Error('sync_scope'); }
        self::validateScope($pins);
        if ($credentials->account() !== $pins['account_id'] || $credentials->isExpired()) { throw new PeraCRM_SES_Sync_Error('sync_scope'); }
        $this->pins=$pins; $this->credentials=$credentials; $this->execute=$execute;
    }
    public static function validateScope(array $pins): void
    {
        $keys=array_keys($pins); sort($keys);
        if ($keys!==['account_id','blog_id','deployment','region'] || !is_int($pins['blog_id']) || $pins['blog_id']<1
            || !is_string($pins['deployment']) || preg_match('/\A[A-Za-z0-9_-]{1,64}\z/',$pins['deployment'])!==1
            || !is_string($pins['account_id']) || preg_match('/\A[0-9]{12}\z/',$pins['account_id'])!==1
            || !is_string($pins['region']) || preg_match('/\A(?:us|eu|ap|sa|ca|me|af|il|mx)-(?:[a-z]+-)[0-9]\z/',$pins['region'])!==1) {
            throw new PeraCRM_SES_Sync_Error('sync_scope');
        }
    }
    public static function cursor(#[SensitiveParameter] ?string $cursor): void
    {
        if ($cursor!==null && (strlen($cursor)<1 || strlen($cursor)>4096 || preg_match('/[^\x21-\x7e]/',$cursor))) {
            throw new PeraCRM_SES_Sync_Error('sync_invalid_cursor');
        }
    }
    public function scope(): array { return $this->pins; }
    public function page(#[SensitiveParameter] ?string $cursor): array
    {
        self::cursor($cursor);
        try {
            $url='https://email.'.$this->pins['region'].'.amazonaws.com/v2/email/suppression/addresses?PageSize=25';
            if ($cursor!==null) { $url.='&NextToken='.rawurlencode($cursor); }
            for ($attempt=0; $attempt<2; $attempt++) {
                if ($this->credentials->isExpired()) { throw new PeraCRM_SES_Sync_Error('sync_scope'); }
                $request=(new PeraCRM\SESDeps\Aws\Signature\SignatureV4('ses',$this->pins['region']))->signRequest(
                    new PeraCRM\SESDeps\GuzzleHttp\Psr7\Request('GET',$url),$this->credentials);
                // Trusted injected executor must enforce these options and complete TLS.
                $raw=($this->execute)($request, ['timeout_ms'=>3000,'connect_timeout_ms'=>1000,'verify'=>true,
                    'redirects'=>false,'proxy'=>false,'max_body_bytes'=>32768,'max_header_bytes'=>16384,'retries'=>0]);
                if (!is_array($raw) || ($raw['complete']??false)!==true || ($raw['authenticated']??false)!==true
                    || !is_int($raw['status']??null) || $raw['status']===429 || $raw['status']>=500) { continue; }
                if ($raw['status']===400 && ($raw['code']??null)==='InvalidNextTokenException') { throw new PeraCRM_SES_Sync_Error('sync_invalid_cursor'); }
                if ($raw['status']!==200 || !is_string($raw['body']??null) || strlen($raw['body'])>32768) { throw new PeraCRM_SES_Sync_Error('sync_read_failed'); }
                // Require a strict JSON object without duplicate keys at any depth.
                $data=self::json($raw['body']);
                return self::validatePage($data);
            }
        } catch (PeraCRM_SES_Sync_Error $error) { throw $error; }
        catch (Throwable $error) { throw new PeraCRM_SES_Sync_Error('sync_read_failed'); }
        throw new PeraCRM_SES_Sync_Error('sync_read_failed');
    }
    private static function json(#[SensitiveParameter] string $body): array
    {
        $data=json_decode($body,true,8,JSON_THROW_ON_ERROR);
        // Each object has globally unique field names except repeated summary fields.
        // Decode each key token and track object-local sets, including escaped keys.
        preg_match_all('/"(?:[^"\\\\\x00-\x1f]|\\\\.)*"|[{}\[\]:,]|[^\s{}\[\]:,]+/',$body,$tokens);
        $stack=[]; $key=false;
        foreach ($tokens[0] as $token) {
            if ($token==='{') { $stack[]=[]; $key=true; }
            elseif ($token==='[') { $stack[]=null; $key=false; }
            elseif ($token==='}' || $token===']') { array_pop($stack); $key=false; }
            elseif ($token===',') { $key=$stack && end($stack)!==null; }
            elseif ($token===':') { $key=false; }
            elseif ($key) {
                $name=json_decode($token,true,2,JSON_THROW_ON_ERROR); $i=count($stack)-1;
                if (isset($stack[$i][$name])) { throw new PeraCRM_SES_Sync_Error('sync_invalid_page'); }
                $stack[$i][$name]=true; $key=false;
            }
        }
        if (!is_array($data)) { throw new PeraCRM_SES_Sync_Error('sync_invalid_page'); }
        return $data;
    }
    public static function validatePage(#[SensitiveParameter] array $page): array
    {
        if (array_diff(array_keys($page),['SuppressedDestinationSummaries','NextToken'])
            || !is_array($page['SuppressedDestinationSummaries']??null)
            || !array_is_list($page['SuppressedDestinationSummaries']) || count($page['SuppressedDestinationSummaries'])>25) { throw new PeraCRM_SES_Sync_Error('sync_invalid_page'); }
        $cursor=$page['NextToken']??null;
        if ($cursor!==null && !is_string($cursor)) { throw new PeraCRM_SES_Sync_Error('sync_invalid_cursor'); }
        self::cursor($cursor);
        foreach ($page['SuppressedDestinationSummaries'] as $entry) {
            if (!is_array($entry)) { throw new PeraCRM_SES_Sync_Error('sync_invalid_page'); }
            $keys=array_keys($entry); sort($keys);
            if ($keys!==['EmailAddress','LastUpdateTime','Reason'] || !is_string($entry['EmailAddress'])
                || trim($entry['EmailAddress'])!==$entry['EmailAddress'] || strlen($entry['EmailAddress'])>254
                || !is_email($entry['EmailAddress']) || preg_match('/[^\x21-\x7e]/',$entry['EmailAddress'])
                || (!is_int($entry['LastUpdateTime']) && !is_float($entry['LastUpdateTime']))
                || !is_finite((float)$entry['LastUpdateTime']) || $entry['LastUpdateTime']<0
                || $entry['LastUpdateTime']>time()+300) { throw new PeraCRM_SES_Sync_Error('sync_invalid_page'); }
            if (!in_array($entry['Reason'],['BOUNCE','COMPLAINT'],true)) { throw new PeraCRM_SES_Sync_Error('sync_unsupported_reason'); }
        }
        return ['SuppressedDestinationSummaries'=>$page['SuppressedDestinationSummaries'],'NextToken'=>$cursor];
    }
    public function __debugInfo(): array { return ['redacted'=>true]; }
    public function __serialize(): array { throw new LogicException('sync_serialization_disabled'); }
    public function __unserialize(array $data): void { throw new LogicException('sync_serialization_disabled'); }
    private function __clone() {}
}
