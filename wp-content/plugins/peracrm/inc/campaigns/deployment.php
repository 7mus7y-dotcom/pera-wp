<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/worker-content.php';

/** Explicit deployment inputs only. No transport, secret discovery, refresh,
 * default keys, options, request input, activation, issuance or public routes.
 * "Configured" means local validation, never principal/host/provider verification.
 */
final class PeraCRM_Campaign_Deployment
{
    private array $manifest;
    private array $material;
    private Closure $clock;
    private Closure $runtime;
    public function __construct(array $manifest,#[\SensitiveParameter] array $material,Closure $clock,Closure $runtime)
    {
        try {
            if (isset($this->manifest)) { throw new LogicException(); }
            self::fields($manifest,['provider','account_id','region','configuration_set','configuration_version',
                'deployment','blog_id','origin','instance_id','identity_version','signing_key_id','encryption_key_id']);
            PeraCRM_CampaignSubmissionV1::validateScope(array_intersect_key($manifest,array_flip([
                'provider','account_id','region','configuration_set','configuration_version'])));
            PeraCRM_CampaignRecipientExpansion::validateOrigin($manifest['origin']);
            if (!is_int($manifest['blog_id']) || $manifest['blog_id']<1 || !is_int($manifest['identity_version'])
                || $manifest['identity_version']<1 || !is_string($manifest['deployment'])
                || preg_match('/\A[a-zA-Z0-9_-]{1,64}\z/',$manifest['deployment'])!==1
                || !is_string($manifest['instance_id']) || preg_match('/\A[a-zA-Z0-9_-]{16,64}\z/',$manifest['instance_id'])!==1
                || !is_string($manifest['signing_key_id']) || preg_match('/\Ak[0-9]{1,8}\z/',$manifest['signing_key_id'])!==1
                || !is_string($manifest['encryption_key_id']) || preg_match('/\Ae[0-9]{1,8}\z/',$manifest['encryption_key_id'])!==1
                || array_diff(array_keys($material),['identity','signing','encryption'])) { throw new LogicException(); }
            ksort($manifest); $this->manifest=[];
            foreach ($manifest as $key=>$value) { $this->manifest[$key]=$value; }
            $nodes=0; $bytes=0; $this->material=self::copy($material,0,$nodes,$bytes);
            $this->clock=$clock; $this->runtime=$runtime;
        } catch (Throwable $e) { throw new RuntimeException('deployment_configuration'); }
    }
    private static function fields(array $value,array $expected): void
    {
        $keys=array_keys($value); sort($keys); sort($expected);
        if ($keys!==$expected) { throw new LogicException(); }
    }
    private static function copy(#[\SensitiveParameter] array $source,int $depth,int &$nodes,int &$bytes): array
    {
        if ($depth>3 || count($source)>64) { throw new LogicException(); }
        $out=[];
        foreach ($source as $key=>$value) {
            if (++$nodes>256 || !is_string($key)) { throw new LogicException(); }
            $bytes+=strlen($key)+(is_string($value)?strlen($value):0);
            if ($bytes>65536) { throw new LogicException(); }
            if (is_array($value)) { $out[$key]=self::copy($value,$depth+1,$nodes,$bytes); }
            elseif (is_string($value) || is_int($value) || $value===null) { $out[$key]=$value; }
            else { throw new LogicException(); }
        }
        return $out;
    }
    private function now(): int
    {
        $now=($this->clock)();
        if (!is_int($now) || $now<1 || $now>PHP_INT_MAX-86400) { throw new RuntimeException('deployment_configuration'); }
        return $now;
    }
    public function binding(): array
    {
        $out=[];
        foreach (['provider','account_id','region','configuration_set','configuration_version','deployment','blog_id'] as $key) {
            $out[$key]=$this->manifest[$key];
        }
        return $out;
    }
    private function scope(): array { return array_diff_key($this->binding(),['deployment'=>true,'blog_id'=>true]); }
    private function requireScope(): void
    {
        try {
            $runtime=($this->runtime)();
            if (!is_array($runtime)) { throw new LogicException(); }
            self::fields($runtime,['instance_id','origin','blog_id']);
            foreach ($runtime as $key=>$value) {
                if ($value!==$this->manifest[$key]) { throw new LogicException(); }
            }
            if (get_current_blog_id()!==$this->manifest['blog_id']
                || (is_multisite() && (int)peracrm_get_target_blog_id()!==$this->manifest['blog_id'])) { throw new LogicException(); }
            if (defined('PERACRM_SES_EVENT_OPERATIONS')) {
                $c=PERACRM_SES_EVENT_OPERATIONS;
                if (!is_array($c)) { throw new LogicException(); }
                foreach (['account'=>'account_id','blog'=>'blog_id','deployment'=>'deployment','region'=>'region',
                    'configuration_set'=>'configuration_set','configuration_version'=>'configuration_version'] as $old=>$key) {
                    if (($c[$old]??null)!==$this->manifest[$key]) { throw new LogicException(); }
                }
            }
        } catch (Throwable $e) { throw new RuntimeException('deployment_scope'); }
    }
    public function fingerprint(): string
    {
        $this->requireScope();
        return hash('sha256',json_encode($this->manifest,JSON_THROW_ON_ERROR));
    }
    private function window(array $value,bool $fresh): void
    {
        $now=$this->now();
        if (!is_int($value['not_before']??null) || !is_int($value['expires_at']??null)
            || $value['not_before']<1 || $value['not_before']>$now || $value['expires_at']<=$value['not_before']
            || ($fresh && $value['expires_at']<=$now+20)) { throw new LogicException(); }
    }
    public function credentials(): PeraCRM_Campaign_SES_Credentials
    {
        $this->requireScope();
        try {
            $v=$this->material['identity']??[];
            self::fields($v,['account_id','version','access_key','secret_key','session_token','not_before','expires_at']);
            $this->window($v,true);
            if ($v['account_id']!==$this->manifest['account_id'] || $v['version']!==$this->manifest['identity_version']
                || !is_string($v['access_key']) || !is_string($v['secret_key'])
                || ($v['session_token']!==null && !is_string($v['session_token']))) { throw new LogicException(); }
            require_once __DIR__.'/providers/ses-credentials.php';
            return new PeraCRM_Campaign_SES_Credentials($v['account_id'],$v['access_key'],$v['secret_key'],$v['session_token'],$v['expires_at']);
        } catch (Throwable $e) { throw new RuntimeException('deployment_identity'); }
    }
    /** Freshness gates new preparation. Retained historical keys can only be
     * used through recoverOutput/verifyToken below, never returned as a new issuer.
     */
    private function ring(string $kind,bool $fresh=true): array
    {
        $v=$this->material[$kind]??[];
        self::fields($v,['active','keys','not_before','expires_at']); $this->window($v,$fresh);
        $limit=$kind==='signing'?8:32; $pattern=$kind==='signing'?'/\Ak[0-9]{1,8}\z/':'/\Ae[0-9]{1,8}\z/';
        if (!is_array($v['keys']) || count($v['keys'])<1 || count($v['keys'])>$limit
            || $v['active']!==$this->manifest[$kind==='signing'?'signing_key_id':'encryption_key_id']
            || !isset($v['keys'][$v['active']])) { throw new LogicException(); }
        $seen=[];
        $other=$this->material[$kind==='signing'?'encryption':'signing']['keys']??[];
        foreach ($v['keys'] as $id=>$bytes) {
            if (!is_string($id) || preg_match($pattern,$id)!==1 || !is_string($bytes)
                || strlen($bytes)<32 || strlen($bytes)>($kind==='signing'?128:32)
                || in_array($bytes,$seen,true) || (is_array($other) && in_array($bytes,$other,true))
                || $bytes===($this->material['identity']['secret_key']??null)) { throw new LogicException(); }
            $seen[]=$bytes;
        }
        if ($kind==='signing') {
            new PeraCRM_CampaignRecipientTokens($v['keys'],$v['active'],$this->manifest['deployment'],static fn()=>false);
        } else { new PeraCRM_CampaignOutputCipher($v['keys'],$v['active']); }
        return $v;
    }
    public function checks(): array
    {
        $out=array_fill_keys(['deployment_scope','provider_identity','token_signing_keys','output_encryption_keys'],'unavailable');
        try { $this->requireScope(); $this->now(); $out['deployment_scope']='validated'; } catch (Throwable $e) { return $out; }
        try { $this->credentials(); $out['provider_identity']='configured'; } catch (Throwable $e) {}
        foreach (['signing'=>'token_signing_keys','encryption'=>'output_encryption_keys'] as $kind=>$name) {
            try { $this->ring($kind); $out[$name]='configured'; } catch (Throwable $e) {}
        }
        return $out;
    }
    private function requireDependencies(): void
    {
        foreach ($this->checks() as $state) {
            if (!in_array($state,['validated','configured'],true)) { throw new RuntimeException('deployment_unavailable'); }
        }
    }
    /** SQL-only diagnostic adapter; never the disabled production budget factory. */
    public function diagnosticBudget(): PeraCRM_Sending_Budget
    {
        $this->requireScope();
        return new PeraCRM_Sending_Budget(array_intersect_key($this->binding(),array_flip(['account_id','region','deployment','blog_id'])),
            $this->clock,fn()=>!array_diff($this->checks(),['validated','configured']));
    }
    public function preparation(): PeraCRM_Campaign_Worker_Preparation
    {
        $this->requireDependencies(); $signing=$this->ring('signing'); $encryption=$this->ring('encryption');
        return new PeraCRM_Campaign_Worker_Preparation($this->manifest['deployment'],$this->scope(),$this->manifest['origin'],
            $signing['keys'],$signing['active'],new PeraCRM_CampaignOutputCipher($encryption['keys'],$encryption['active']),
            $this->clock,function () { $this->requireDependencies(); });
    }
    public function recoverOutput(array $binding,array $expected): array
    {
        $this->requireScope();
        if (($binding['blog_id']??null)!==$this->manifest['blog_id'] || ($expected['scope']??null)!==$this->scope()) {
            throw new RuntimeException('deployment_scope');
        }
        try { $ring=$this->ring('encryption',false); } catch (Throwable $e) { throw new RuntimeException('deployment_encryption'); }
        return (new PeraCRM_CampaignRecipientPersistence($this->manifest['deployment'],$this->manifest['blog_id']))
            ->recoverOutput($binding,$expected,new PeraCRM_CampaignOutputCipher($ring['keys'],$ring['active']));
    }
    public function verifyToken(#[\SensitiveParameter] string $token,string $purpose,array $binding,Closure $activeBinding): bool
    {
        $this->requireScope();
        if (($binding['blog_id']??null)!==$this->manifest['blog_id']) { throw new RuntimeException('deployment_scope'); }
        try {
            $ring=$this->ring('signing',false);
            return (new PeraCRM_CampaignRecipientTokens($ring['keys'],$ring['active'],$this->manifest['deployment'],$activeBinding))
                ->verify($token,$purpose,$binding,$this->now());
        } catch (Throwable $e) { return false; }
    }
    public function __debugInfo(): array { return ['service'=>'campaign_deployment','redacted'=>true]; }
    public function __serialize(): array { throw new LogicException('deployment_projection_disabled'); }
    public function __unserialize(array $data): void { throw new LogicException('deployment_projection_disabled'); }
    private function __clone() {}
}

/** Deployment-managed constants only, absent by default. No filters, UI, env/
 * metadata credential chain, files, remote requests or default secrets are read.
 */
function peracrm_campaign_deployment_service(): ?PeraCRM_Campaign_Deployment
{
    if (!defined('PERACRM_CAMPAIGN_DEPLOYMENT')) { return null; }
    if (!is_array(PERACRM_CAMPAIGN_DEPLOYMENT)
        || (defined('PERACRM_CAMPAIGN_MATERIAL') && !is_array(PERACRM_CAMPAIGN_MATERIAL))) { throw new RuntimeException('deployment_configuration'); }
    return new PeraCRM_Campaign_Deployment(PERACRM_CAMPAIGN_DEPLOYMENT,
        defined('PERACRM_CAMPAIGN_MATERIAL')?PERACRM_CAMPAIGN_MATERIAL:[],static fn()=>time(),static function () {
            $url=parse_url(home_url('/'));
            $origin=is_array($url) && ($url['scheme']??null)==='https'
                && !array_intersect(['port','user','pass','query','fragment'],array_keys($url))
                ? 'https://'.strtolower($url['host']??'') : null;
            return ['instance_id'=>defined('PERACRM_CAMPAIGN_INSTANCE_ID')?PERACRM_CAMPAIGN_INSTANCE_ID:null,
                'origin'=>$origin,'blog_id'=>get_current_blog_id()];
        });
}
