<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/providers/sns.php';

/** Control-plane only. No dispatch/intake authority. Tokens never leave this service.
 * The API is trusted injection: fixed regional, SigV4 authenticated SNS operations.
 */
final class PeraCRM_SNS_Provisioning
{
    private array $config;
    private PeraCRM_SNS_Verifier $verifier;
    private Closure $api;
    private string $key;
    private ?array $lock=null;
    public function __construct(array $config,callable $certificate,Closure $api,#[SensitiveParameter] string $key)
    {
        $keys=array_keys($config); sort($keys);
        if ($keys!==['account','blog','deployment','enabled','endpoint','region','topic'] || $config['enabled']!==true
            || !is_int($config['blog']) || $config['blog']<1
            || !is_string($config['deployment']) || preg_match('/\A[A-Za-z0-9_-]{1,64}\z/',$config['deployment'])!==1
            || !is_string($config['endpoint']) || !str_starts_with($config['endpoint'],'https://')
            || filter_var($config['endpoint'],FILTER_VALIDATE_URL)===false
            || parse_url($config['endpoint'],PHP_URL_USER)!==null || parse_url($config['endpoint'],PHP_URL_QUERY)!==null
            || parse_url($config['endpoint'],PHP_URL_FRAGMENT)!==null
            || strlen($key)!==SODIUM_CRYPTO_SECRETBOX_KEYBYTES) { throw new PeraCRM_SNS_Error('sns_configuration'); }
        $this->verifier=new PeraCRM_SNS_Verifier(['account'=>$config['account'],'region'=>$config['region'],'topic'=>$config['topic']],$certificate);
        $this->config=$config; $this->api=$api; $this->key=$key;
    }
    public function __debugInfo(): array { return ['redacted'=>true]; }
    public function __serialize(): array { throw new LogicException('sns_serialization_disabled'); }
    public function __unserialize(array $data): void { throw new LogicException('sns_serialization_disabled'); }
    private function __clone() {}
    private function context(): void
    {
        if (get_current_blog_id()!==$this->config['blog'] || (is_multisite() && (int)peracrm_get_target_blog_id()!==$this->config['blog'])) {
            throw new PeraCRM_SNS_Error('sns_configuration');
        }
    }
    private function actor(string $confirmation): int
    {
        $this->context(); $id=peracrm_get_actor_user_id();
        if ($confirmation!=='provision-exact-sns-subscription' || $id<1 || peracrm_is_impersonating_crm_user()
            || !user_can($id,'manage_options') || !user_can($id,'peracrm_manage_campaigns')) { throw new PeraCRM_SNS_Error('sns_authority'); }
        return $id;
    }
    private function option(): string { return 'peracrm_sns_provision_v1_'.hash('sha256',$this->config['deployment']); }
    private function binding(): string { return hash('sha256',json_encode($this->config,JSON_THROW_ON_ERROR)); }
    private static function db($value)
    {
        global $wpdb;
        if ($value===false || $wpdb->last_error!=='') { throw new PeraCRM_SNS_Error('sns_storage'); }
        return $value;
    }
    private function guard(): void
    {
        global $wpdb;
        if (!$this->lock || (int)self::db($wpdb->get_var('SELECT CONNECTION_ID()'))!==$this->lock[1]
            || (int)self::db($wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)',$this->lock[0])))!==$this->lock[1]) {
            throw new PeraCRM_SNS_Error('sns_storage');
        }
    }
    private function exclusive(Closure $operation)
    {
        $this->context(); global $wpdb; $quiet=$wpdb->suppress_errors(true);
        try {
            if ($this->lock || (int)self::db($wpdb->get_var('SELECT @@autocommit'))!==1) { throw new PeraCRM_SNS_Error('sns_storage'); }
            self::db($wpdb->query('SAVEPOINT peracrm_sns_provision_probe'));
            if ($wpdb->query('RELEASE SAVEPOINT peracrm_sns_provision_probe')!==false
                || $wpdb->last_error!=='SAVEPOINT peracrm_sns_provision_probe does not exist') { throw new PeraCRM_SNS_Error('sns_storage'); }
            $name='peracrm-sns-'.substr(hash('sha256',$wpdb->options.$this->option()),0,48);
            $connection=(int)self::db($wpdb->get_var('SELECT CONNECTION_ID()'));
            if ($connection<1 || (string)self::db($wpdb->get_var($wpdb->prepare(
                'SELECT IF(IS_USED_LOCK(%s)=CONNECTION_ID(),0,GET_LOCK(%s,0))',$name,$name)))!=='1') { throw new PeraCRM_SNS_Error('sns_storage'); }
            $this->lock=[$name,$connection];
            try {
                $raw=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",$this->option()));
                self::db($wpdb->last_error===''?true:false);
                $old=$raw===null?[]:maybe_unserialize($raw);
                if (!is_array($old) || ($old && (($old['binding']??null)!==$this->binding() || !is_array($old['requests']??null)))) {
                    throw new PeraCRM_SNS_Error('sns_conflict');
                }
                return $operation($old);
            } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$name)); $this->lock=null; }
        } catch (PeraCRM_SNS_Error $e) { throw $e; }
        catch (Throwable $e) { throw new PeraCRM_SNS_Error('sns_storage'); }
        finally { $wpdb->suppress_errors($quiet); }
    }
    private function save(array $old,array $new): void
    {
        global $wpdb; $this->guard();
        // A retry by the same actor may already have durably entered confirming.
        // The locked authoritative read plus session guard verifies that state;
        // do not mistake MySQL's zero changed rows for a failed persistence.
        if ($old===$new) { return; }
        if (!$old) { $ok=add_option($this->option(),$new,'',false); }
        else { $ok=self::db($wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s AND BINARY option_value=BINARY %s",
            maybe_serialize($new),$this->option(),maybe_serialize($old))))===1; }
        wp_cache_delete($this->option(),'options');
        if (!$ok) { throw new PeraCRM_SNS_Error('sns_storage'); }
        $this->guard();
    }
    private function call(string $action,#[SensitiveParameter] array $parameters): array
    {
        $this->guard();
        try { $out=($this->api)($action,$parameters); }
        catch (Throwable $e) { throw new PeraCRM_SNS_Error('sns_dependency'); }
        $this->guard();
        if (!is_array($out)) { throw new PeraCRM_SNS_Error('sns_dependency'); }
        return $out;
    }
    private function tokenKey(array $r): string
    {
        return hash_hkdf('sha256',$this->key,32,'peracrm-sns-token-v1',json_encode([
            $this->binding(),$r['created'],$r['expires'],$r['id'],$r['digest']],JSON_THROW_ON_ERROR));
    }
    private static function time(int $now): void { if ($now<1) { throw new PeraCRM_SNS_Error('sns_invalid'); } }
    /** Persist pending authority BEFORE Subscribe. Same request may retry after network ambiguity. */
    public function request(string $confirmation,int $now): string
    {
        $actor=$this->actor($confirmation); self::time($now);
        return $this->exclusive(function (array $old) use ($actor,$now) {
            $new=$old?:['binding'=>$this->binding(),'requests'=>[]]; $i=count($new['requests'])-1;
            if ($i>=0) {
                $last=$new['requests'][$i];
                if ($now<$last['created']) { throw new PeraCRM_SNS_Error('sns_invalid'); }
                if ($last['state']==='confirmed') { return 'confirmed'; }
                if ($now<=$last['expires']) { return $last['state']; }
                unset($new['requests'][$i]['sealed']); // expired authority cannot retain usable tokens
            }
            if (count($new['requests'])>=32) { throw new PeraCRM_SNS_Error('sns_storage'); }
            $new['requests'][]=['created'=>$now,'expires'=>$now+3600,'actor'=>$actor,'state'=>'armed'];
            $this->save($old,$new);
            return 'armed';
        });
    }
    /** Explicit operator network action, separate from arming to permit delivery during Subscribe. */
    public function subscribe(string $confirmation,int $now): string
    {
        $actor=$this->actor($confirmation); self::time($now);
        // Do not hold a DB lock across Subscribe: SNS can deliver confirmation synchronously.
        $this->exclusive(function (array $old) use ($now,$actor) {
            $i=count($old['requests']??[])-1; $r=$old['requests'][$i]??null;
            if (!$r || $r['state']!=='armed' || $now<$r['created'] || $now>$r['expires']) { throw new PeraCRM_SNS_Error('sns_conflict'); }
            $new=$old; $new['requests'][$i]['subscribe_actor']=$actor;
            $new['requests'][$i]['subscribe_attempted_at']=$now;
            $this->save($old,$new);
        });
        try {
            $out=($this->api)('Subscribe',['TopicArn'=>$this->config['topic'],'Protocol'=>'https',
                'Endpoint'=>$this->config['endpoint'],'ReturnSubscriptionArn'=>'false']);
            if (!is_array($out) || ($out['SubscriptionArn']??null)!=='pending confirmation') { throw new RuntimeException(); }
        } catch (Throwable $e) { throw new PeraCRM_SNS_Error('sns_dependency'); }
        return 'requested';
    }
    /** Public control-plane intake authenticates before any journal access. */
    public function receive(#[SensitiveParameter] string $body,int $now): string
    {
        self::time($now); $receipt=$this->verifier->verifyConfirmation($body,$now);
        return $this->exclusive(function (array $old) use ($receipt,$now) {
            foreach ($old['requests']??[] as $r) {
                if (($r['id']??null)===$receipt['id']) {
                    if (!hash_equals($r['digest'],$receipt['hash'])) { throw new PeraCRM_SNS_Error('sns_conflict'); }
                    return 'duplicate';
                }
            }
            $i=count($old['requests']??[])-1; $r=$old['requests'][$i]??null;
            if (!$r || $r['state']!=='armed' || $now<$r['created'] || $now>$r['expires']
                || $receipt['timestamp']<$r['created']-300 || $receipt['timestamp']>$now+300
                || $receipt['timestamp']<$now-3600) { throw new PeraCRM_SNS_Error('sns_conflict'); }
            $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $new=$old; $new['requests'][$i]+= ['id'=>$receipt['id'],'digest'=>$receipt['hash']];
            $new['requests'][$i]['sealed']=base64_encode($nonce.sodium_crypto_secretbox(
                $receipt['token'],$nonce,$this->tokenKey($new['requests'][$i])));
            $new['requests'][$i]['state']='received';
            $this->save($old,$new); return 'received';
        });
    }
    public function confirm(string $confirmation,int $now): string
    {
        $actor=$this->actor($confirmation); self::time($now);
        return $this->exclusive(function (array $old) use ($actor,$now) {
            $i=count($old['requests']??[])-1; $r=$old['requests'][$i]??null;
            if (!$r || $now<$r['created']) { throw new PeraCRM_SNS_Error('sns_conflict'); }
            if ($r['state']==='confirmed') { return 'confirmed'; }
            if (!in_array($r['state'],['received','confirming'],true) || $now>$r['expires']) { throw new PeraCRM_SNS_Error('sns_conflict'); }
            $sealed=base64_decode($r['sealed'],true);
            if ($sealed===false || strlen($sealed)<=SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) { throw new PeraCRM_SNS_Error('sns_storage'); }
            $token=sodium_crypto_secretbox_open(substr($sealed,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),substr($sealed,0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),$this->tokenKey($r));
            if ($token===false) { throw new PeraCRM_SNS_Error('sns_storage'); }
            $new=$old; $new['requests'][$i]['state']='confirming'; $new['requests'][$i]['confirm_actor']=$actor;
            $this->save($old,$new); // durable ambiguous state precedes any remote mutation
            try { $out=$this->call('ConfirmSubscription',['TopicArn'=>$this->config['topic'],'Token'=>$token,'AuthenticateOnUnsubscribe'=>'true']); }
            finally { sodium_memzero($token); }
            $arn=$out['SubscriptionArn']??null;
            if (!is_string($arn) || preg_match('/\A'.preg_quote($this->config['topic'],'/').':[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\z/',$arn)!==1) { throw new PeraCRM_SNS_Error('sns_dependency'); }
            $attributes=$this->call('GetSubscriptionAttributes',['SubscriptionArn'=>$arn]);
            if (($attributes['TopicArn']??null)!==$this->config['topic'] || ($attributes['Owner']??null)!==$this->config['account']
                || ($attributes['Endpoint']??null)!==$this->config['endpoint'] || ($attributes['Protocol']??null)!=='https'
                || ($attributes['ConfirmationWasAuthenticated']??null)!=='true') { throw new PeraCRM_SNS_Error('sns_conflict'); }
            $done=$new; unset($done['requests'][$i]['sealed']);
            $done['requests'][$i]['state']='confirmed'; $done['requests'][$i]['confirmed_at']=$now;
            $done['requests'][$i]['subscription']=$arn; $this->save($new,$done);
            return 'confirmed';
        });
    }
    public function status(string $confirmation): array
    {
        $this->actor($confirmation);
        return $this->exclusive(static function (array $old) {
            return array_map(static fn($r)=>array_intersect_key($r,array_flip([
                'state','created','expires','actor','subscribe_actor','subscribe_attempted_at','confirm_actor','confirmed_at','subscription'])),$old['requests']??[]);
        });
    }
}
