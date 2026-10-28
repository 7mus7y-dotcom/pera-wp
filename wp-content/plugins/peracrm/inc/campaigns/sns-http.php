<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/event-processing.php';
require_once __DIR__.'/sns-provisioning-runtime.php';

/** Plugin-owned, explicitly configured intake. No sending or subscription authority. */
final class PeraCRM_SNS_HTTP
{
    public const LIMIT=262144;
    private PeraCRM_SNS_Verifier $verifier;
    private PeraCRM_SNS_Receipts $receipts;
    private PeraCRM_SES_Processing $processing;
    public function __construct(array $config, callable $certificate)
    {
        if (isset($this->verifier)) { throw new PeraCRM_SNS_Error('sns_configuration'); }
        $keys=array_keys($config); sort($keys);
        if ($keys!==['account','blog','configuration_set','configuration_version','deployment','enabled','region','topic']
            || $config['enabled']!==true || !is_int($config['blog']) || $config['blog']<=0
            || $config['blog']!==get_current_blog_id()
            || (is_multisite() && $config['blog']!==(int)peracrm_get_target_blog_id())
            || !is_string($config['deployment']) || preg_match('/\A[A-Za-z0-9_-]{1,64}\z/',$config['deployment'])!==1) {
            throw new PeraCRM_SNS_Error('sns_configuration');
        }
        $this->verifier=new PeraCRM_SNS_Verifier(['account'=>$config['account'],'region'=>$config['region'],'topic'=>$config['topic']],$certificate);
        $scope=['provider'=>'ses_v2','account_id'=>$config['account'],'region'=>$config['region'],
            'configuration_set'=>$config['configuration_set'],'configuration_version'=>$config['configuration_version']];
        $args=[$this->verifier,$config['deployment'],$config['blog']];
        $this->receipts=new PeraCRM_SNS_Receipts(...$args);
        $this->processing=new PeraCRM_SES_Processing(...[...$args,$scope]);
    }
    public function __debugInfo(): array { return ['redacted'=>true]; }
    public function __serialize(): array { throw new LogicException('http_serialization_disabled'); }
    /** Validate cheap HTTP constraints before opening php://input or retrieving a certificate. */
    public static function preflight(string $method,string $type,?string $length): int
    {
        if ($method!=='POST') { return 405; }
        if (!preg_match('~\A(?:text/plain|application/json|text/xml)(?:\s*;\s*charset\s*=\s*(?:UTF-8|"UTF-8"))?\s*\z~iD',$type)) { return 415; }
        if ($length!==null && (preg_match('/\A[0-9]{1,10}\z/',$length)!==1 || (float)$length>self::LIMIT)) { return 413; }
        return 0;
    }
    /** Every stage owns and commits its existing transaction. Replays always resume
     * normalization/matching/application; receipt existence is never completion.
     * Quarantine success denotes durable acceptance only, never application.
     */
    public function handle(string $method,string $type,?string $length,#[\SensitiveParameter] string $body,int $now): int
    {
        $status=self::preflight($method,$type,$length);
        if ($status) { return $status; }
        if (strlen($body)>self::LIMIT) { return 413; }
        try {
            $this->receipts->receive($body,$now);
            // Bounded parsing has already authenticated this exact envelope. Do not
            // expose or use inner caller data; downstream reads durable provenance.
            $id=json_decode($body,true,8,JSON_THROW_ON_ERROR)['MessageId'];
            $this->processing->receipt($id);
            return 202;
        } catch (PeraCRM_SNS_Error $error) {
            return match ($error->getMessage()) {
                'sns_invalid'=>400, 'sns_authentication'=>403, 'sns_conflict'=>409,
                default=>503,
            };
        } catch (Throwable $error) { return 503; }
    }
}

/** Deployment config only; no database option, request field or enabling filter.
 * The injected retriever is internal; production uses the strict bounded helper.
 */
function peracrm_sns_http_service(?callable $certificate=null): ?PeraCRM_SNS_HTTP
{
    if (!defined('PERACRM_SES_SNS_HTTP') || !is_array(PERACRM_SES_SNS_HTTP)) { return null; }
    try {
        $config=PERACRM_SES_SNS_HTTP;
        if ($certificate===null) {
            $verifier=new PeraCRM_SNS_Verifier(['account'=>$config['account']??null,'region'=>$config['region']??null,'topic'=>$config['topic']??null],static fn()=>null);
            $certificate=static fn($url)=>peracrm_sns_certificate_fetch($verifier,$url);
        }
        return new PeraCRM_SNS_HTTP($config,$certificate);
    } catch (Throwable $error) { return null; }
}

/** Early plugin response bypasses theme, canonical redirects and analytics hooks.
 * No rewrite activation/flush and no automatic SNS subscription confirmation.
 */
function peracrm_sns_http_route(): void
{
    $path=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);
    $expected=parse_url(home_url('/peracrm-ses-sns/'),PHP_URL_PATH);
    if (!is_string($path) || $path!==$expected) { return; }
    $service=peracrm_sns_http_service(); $method=$_SERVER['REQUEST_METHOD']??'';
    $type=$_SERVER['CONTENT_TYPE']??''; $length=$_SERVER['CONTENT_LENGTH']??null;
    $provisioning=peracrm_sns_provisioning_service();
    // Provisioning takes precedence while explicitly enabled; notifications stay unavailable.
    $status=($service || $provisioning)?PeraCRM_SNS_HTTP::preflight($method,$type,$length):404;
    if ($status===0) {
        $input=fopen('php://input','rb');
        $body=$input?stream_get_contents($input,PeraCRM_SNS_HTTP::LIMIT+1):false;
        if ($input) { fclose($input); }
        if (!is_string($body)) { $status=503; }
        elseif (strlen($body)>PeraCRM_SNS_HTTP::LIMIT) { $status=413; }
        elseif ($provisioning) {
            try { $provisioning->receive($body,time()); $status=202; }
            catch (PeraCRM_SNS_Error $e) {
                $status=match($e->getMessage()) { 'sns_invalid'=>400,'sns_authentication'=>403,'sns_conflict'=>409,default=>503 };
            } catch (Throwable $e) { $status=503; }
        } else { $status=$service->handle($method,$type,$length,$body,time()); }
    }
    status_header($status);
    header('Cache-Control: no-store, private, max-age=0'); header('Pragma: no-cache');
    header('Content-Type: text/plain; charset=UTF-8'); header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer'); header('X-Robots-Tag: noindex, nofollow');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
    if ($status===405) { header('Allow: POST'); }
    if ($status===503) { header('Retry-After: 60'); }
    if ($method!=='HEAD') { echo $status===202?'Accepted.':'Request unavailable.'; }
    exit;
}
add_action('parse_request','peracrm_sns_http_route',-1000);
