<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/providers/ses-suppression-reader.php';
require_once __DIR__.'/repository.php';
require_once __DIR__.'/service.php';

/** Internal additive account/region deny import. Never subscriber/profile creation.
 * A trusted deployment must explicitly authorize account-wide knowledge for this
 * blog; SES does not supply a deployment/blog/configuration-set filter.
 */
final class PeraCRM_SES_Suppression_Sync
{
    private array $pins;
    private string $scope;
    private string $lock;
    private PeraCRM_SES_Suppression_Reader $reader;
    private static array $active=[];
    /** Submission preflight, with caller-owned transaction/lock lifetime. A prior
     * completed run cannot authorize a running or failed successor. No reader call.
     */
    public static function requireComplete(array $pins,$now): void
    {
        global $wpdb;
        PeraCRM_SES_Suppression_HTTP::validateScope($pins);
        if (!is_int($now) || $now<1 || get_current_blog_id()!==$pins['blog_id']
            || (is_multisite() && (int)peracrm_get_target_blog_id()!==$pins['blog_id'])
            || !peracrm_campaigns_schema_ready()) { throw new PeraCRM_SES_Sync_Error('sync_scope'); }
        $ordered=[];
        foreach (['account_id','region','deployment','blog_id'] as $key) { $ordered[$key]=$pins[$key]; }
        $scope=hash('sha256',json_encode(['policy'=>'ses-account-deny-v1']+$ordered,JSON_THROW_ON_ERROR));
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_ses_sync')
            .' WHERE scope_hash=%s FOR UPDATE',$scope),ARRAY_A); self::db(true);
        if (!$row || $row['state']!=='completed' || $row['cursor_value']!==null || $row['error_code']!==''
            || preg_match('/\A[a-f0-9]{64}\z/',$row['run_id'])!==1 || (int)$row['pages']<1) {
            throw new PeraCRM_SES_Sync_Error('sync_storage');
        }
        $times=[];
        foreach (['started_at','updated_at','last_complete_at'] as $key) {
            $value=$row[$key]; $time=is_string($value)?strtotime($value.' UTC'):false;
            if ($time===false || gmdate('Y-m-d H:i:s',$time)!==$value || $time>$now) {
                throw new PeraCRM_SES_Sync_Error('sync_storage');
            }
            $times[$key]=$time;
        }
        if ($times['started_at']>$times['updated_at'] || $times['updated_at']>$times['last_complete_at']
            || $now-$times['last_complete_at']>=86400) { throw new PeraCRM_SES_Sync_Error('sync_stale_cursor'); }
        $pages=(int)self::db($wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.peracrm_table('peracrm_campaign_ses_sync_pages')
            .' WHERE scope_hash=%s AND run_id=%s',$scope,$row['run_id'])));
        if ($pages!==(int)$row['pages']) { throw new PeraCRM_SES_Sync_Error('sync_storage'); }
    }
    public function __construct(array $pins, PeraCRM_SES_Suppression_Reader $reader)
    {
        if (isset($this->pins)) { throw new PeraCRM_SES_Sync_Error('sync_scope'); }
        PeraCRM_SES_Suppression_HTTP::validateScope($pins);
        $ordered=[];
        foreach (['account_id','region','deployment','blog_id'] as $key) {
            if (($reader->scope()[$key]??null)!==$pins[$key]) { throw new PeraCRM_SES_Sync_Error('sync_scope'); }
            $ordered[$key]=$pins[$key];
        }
        if (count($reader->scope())!==4) { throw new PeraCRM_SES_Sync_Error('sync_scope'); }
        $this->pins=$ordered; $this->reader=$reader;
        $this->scope=hash('sha256',json_encode(['policy'=>'ses-account-deny-v1']+$ordered,JSON_THROW_ON_ERROR));
        $this->lock='peracrm-sync-'.substr($this->scope,0,48);
    }
    private static function db($value)
    {
        global $wpdb;
        if ($value===false || $wpdb->last_error!=='') { throw new PeraCRM_SES_Sync_Error(); }
        return $value;
    }
    private function context(): void
    {
        if (get_current_blog_id()!==$this->pins['blog_id'] || (is_multisite() && (int)peracrm_get_target_blog_id()!==$this->pins['blog_id'])
            || !peracrm_campaigns_schema_ready()) { throw new PeraCRM_SES_Sync_Error('sync_scope'); }
    }
    private function session(int $connection): void
    {
        global $wpdb;
        if ((int)self::db($wpdb->get_var('SELECT CONNECTION_ID()'))!==$connection
            || (int)self::db($wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)',$this->lock)))!==$connection) { throw new PeraCRM_SES_Sync_Error(); }
    }
    private function guard(int $connection,string $guard): string
    {
        global $wpdb;
        return $wpdb->prepare('CONNECTION_ID()=%d AND IS_USED_LOCK(%s)=%d AND @peracrm_sync_guard=%s',$connection,$this->lock,$connection,$guard);
    }
    private function insert(string $name,array $data,int $connection,string $guard,bool $dedupe=true): void
    {
        global $wpdb;
        $first=array_key_first($data);
        self::db($wpdb->query($wpdb->prepare('INSERT INTO '.peracrm_table($name).' ('.implode(',',array_keys($data)).') SELECT '
            .implode(',',array_fill(0,count($data),'%s')).' WHERE '.$this->guard($connection,$guard)
            .($dedupe?" ON DUPLICATE KEY UPDATE {$first}={$first}":''),array_values($data))));
        $this->session($connection);
    }
    private function transaction(int $connection,string $guard,callable $callback)
    {
        global $wpdb;
        $this->session($connection); $started=false;
        try {
            self::db($wpdb->query('START TRANSACTION')); $started=true;
            self::db($wpdb->query('SAVEPOINT peracrm_sync_owned'));
            $this->session($connection);
            self::db($wpdb->query('RELEASE SAVEPOINT peracrm_sync_owned'));
            $result=$callback();
            $this->session($connection);
            self::db($wpdb->query('COMMIT')); $started=false;
            $this->session($connection);
            return $result;
        } finally { if ($started) { $wpdb->query('ROLLBACK'); } }
    }
    private function checkpoint(bool $locked=false): ?array
    {
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.peracrm_table('peracrm_campaign_ses_sync')
            .' WHERE scope_hash=%s'.($locked?' FOR UPDATE':''),$this->scope),ARRAY_A);
        self::db(true); return $row;
    }
    private static function cursorHash(?string $cursor): string { return hash('sha256',$cursor===null?'initial':'cursor:'.$cursor); }
    /** Fixed privacy-safe projection. No cursor, mailbox, raw error or scope values. */
    public function health(): array
    {
        global $wpdb;
        $quiet=$wpdb->suppress_errors(true);
        try {
            $this->context(); $row=$this->checkpoint();
            if ($row && !in_array($row['state'],['running','failed','completed'],true)) { throw new PeraCRM_SES_Sync_Error(); }
            $complete=$row['last_complete_at']??null;
            if ($complete!==null && preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\z/',$complete)!==1) { throw new PeraCRM_SES_Sync_Error(); }
            return ['state'=>$row['state']??'never','pages'=>(int)($row['pages']??0),'entries'=>(int)($row['entries']??0),
                'last_complete_at'=>$complete,'stale'=>($row['state']??null)!=='completed' || ($row['cursor_value']??null)!==null
                    || !empty($row['error_code']) || $complete===null || strtotime($complete.' UTC')<=time()-86400,
                'error_code'=>empty($row['error_code'])?null:(new PeraCRM_SES_Sync_Error($row['error_code']))->getMessage()];
        } finally { $wpdb->suppress_errors($quiet); }
    }
    /** Max 10 pages/250 entries per explicit invocation, 25-second work-start
     * budget including two bounded read attempts. Cursor/run stored per page.
     * Restart explicitly abandons only pagination progress, never deny history.
     */
    public function run(int $pages=1,bool $restart=false): array
    {
        if ($pages<1 || $pages>10) { throw new InvalidArgumentException('sync_bounds'); }
        global $wpdb;
        $quiet=$wpdb->suppress_errors(true); $owned=false; $connection=0; $guard=''; $runId=null; $start=hrtime(true);
        try {
            $this->context();
            if (isset(self::$active[$this->lock])) { throw new PeraCRM_SES_Sync_Error('sync_overlap'); }
            if ((int)self::db($wpdb->get_var('SELECT @@autocommit'))!==1) { throw new PeraCRM_SES_Sync_Error(); }
            self::db($wpdb->query('SAVEPOINT peracrm_sync_probe'));
            if ($wpdb->query('RELEASE SAVEPOINT peracrm_sync_probe')!==false || $wpdb->last_error!=='SAVEPOINT peracrm_sync_probe does not exist') { throw new PeraCRM_SES_Sync_Error(); }
            $connection=(int)self::db($wpdb->get_var('SELECT CONNECTION_ID()'));
            if ($connection<1) { throw new PeraCRM_SES_Sync_Error(); }
            if ((string)self::db($wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$this->lock)))!=='1') { throw new PeraCRM_SES_Sync_Error('sync_overlap'); }
            $owned=true; self::$active[$this->lock]=true; $this->session($connection);
            $guard=bin2hex(random_bytes(32));
            self::db($wpdb->query($wpdb->prepare('SET @peracrm_sync_guard=IF(CONNECTION_ID()=%d,%s,NULL)',$connection,$guard)));
            $row=$this->transaction($connection,$guard,function () use ($connection,$guard,$restart) {
                global $wpdb;
                $row=$this->checkpoint(true); $now=gmdate('Y-m-d H:i:s');
                if ($row && (!in_array($row['state'],['running','failed','completed'],true)
                    || preg_match('/\A[a-f0-9]{64}\z/',$row['run_id'])!==1)) { throw new PeraCRM_SES_Sync_Error(); }
                if (!$row) {
                    $this->insert('peracrm_campaign_ses_sync',['scope_hash'=>$this->scope,'run_id'=>bin2hex(random_bytes(32)),
                        'state'=>'running','started_at'=>$now,'updated_at'=>$now],$connection,$guard);
                } elseif ($restart || $row['state']==='completed') {
                    self::db($wpdb->query($wpdb->prepare('UPDATE '.peracrm_table('peracrm_campaign_ses_sync')
                        ." SET run_id=%s,cursor_value=NULL,pages=0,entries=0,state='running',error_code='',started_at=%s,updated_at=%s WHERE scope_hash=%s AND "
                        .$this->guard($connection,$guard),bin2hex(random_bytes(32)),$now,$now,$this->scope)));
                }
                $this->session($connection); return $this->checkpoint(true);
            });
            $runId=$row['run_id'];
            if (in_array($row['error_code'],['sync_invalid_cursor','sync_stale_cursor','sync_cursor_cycle'],true)) { throw new PeraCRM_SES_Sync_Error($row['error_code']); }
            if (strtotime($row['started_at'].' UTC')<time()-86400) { throw new PeraCRM_SES_Sync_Error('sync_stale_cursor'); }
            for ($n=0;$n<$pages && hrtime(true)-$start<19000000000;$n++) {
                PeraCRM_SES_Suppression_HTTP::cursor($row['cursor_value']);
                $page=PeraCRM_SES_Suppression_HTTP::validatePage($this->reader->page($row['cursor_value']));
                $this->session($connection);
                $row=$this->commitPage($row,$page,$connection,$guard);
                if ($row['state']==='completed') { break; }
            }
            return $this->health();
        } catch (Throwable $error) {
            $code=$error instanceof PeraCRM_SES_Sync_Error?$error->getMessage():'sync_storage';
            if ($owned && $runId!==null) {
                // Lost-session/replayed writes cannot overwrite a successor or
                // publish success. Error persistence itself is session fenced.
                $wpdb->query($wpdb->prepare('UPDATE '.peracrm_table('peracrm_campaign_ses_sync')
                    ." SET state='failed',error_code=%s,updated_at=UTC_TIMESTAMP() WHERE scope_hash=%s AND run_id=%s AND state IN ('running','failed') AND ".$this->guard($connection,$guard),$code,$this->scope,$runId));
            }
            throw new PeraCRM_SES_Sync_Error($code);
        } finally {
            if ($owned) { unset(self::$active[$this->lock]); $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$this->lock)); }
            $wpdb->suppress_errors($quiet);
        }
    }
    private function commitPage(array $expected,#[SensitiveParameter] array $page,int $connection,string $guard): array
    {
        return $this->transaction($connection,$guard,function () use ($expected,$page,$connection,$guard) {
            global $wpdb;
            $row=$this->checkpoint(true);
            if ($row!==$expected) { throw new PeraCRM_SES_Sync_Error(); }
            $cursor=self::cursorHash($row['cursor_value']); $next=$page['NextToken'];
            $seen=peracrm_table('peracrm_campaign_ses_sync_pages');
            if ($next!==null && (self::cursorHash($next)===$cursor || self::db($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$seen} WHERE scope_hash=%s AND run_id=%s AND cursor_hash=%s",$this->scope,$row['run_id'],self::cursorHash($next))))>0)) {
                throw new PeraCRM_SES_Sync_Error('sync_cursor_cycle');
            }
            $now=gmdate('Y-m-d H:i:s'); $entries=[];
            foreach ($page['SuppressedDestinationSummaries'] as $entry) {
                $entry['normalized_email']=peracrm_campaigns_normalize_email($entry['EmailAddress']);
                $entry['reason']=$entry['Reason']==='BOUNCE'?'hard_bounce':'complaint';
                $entry['updated']=json_encode($entry['LastUpdateTime'],JSON_THROW_ON_ERROR);
                $entry['hash']=hash('sha256',json_encode([$entry['EmailAddress'],$entry['Reason'],$entry['updated']],JSON_THROW_ON_ERROR));
                $entries[]=$entry;
            }
            usort($entries,static fn($a,$b)=>strcmp($a['normalized_email'],$b['normalized_email']));
            $insert=fn($name,$data)=>$this->insert($name,$data,$connection,$guard);
            // Lock all normalized mailboxes in the existing repository order.
            foreach (array_unique(array_column($entries,'normalized_email')) as $mailbox) { peracrm_campaigns_repository_lock_mailbox($mailbox,$insert); }
            foreach ($entries as $entry) {
                $facts=peracrm_table('peracrm_campaign_ses_sync_facts');
                $prior=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$facts} WHERE scope_hash=%s AND fact_hash=%s FOR UPDATE",$this->scope,$entry['hash']),ARRAY_A); self::db(true);
                $data=['scope_hash'=>$this->scope,'fact_hash'=>$entry['hash'],'provider_mailbox'=>$entry['EmailAddress'],
                    'normalized_email'=>$entry['normalized_email'],'reason'=>$entry['reason'],'provider_updated'=>$entry['updated'],'recorded_at'=>$now];
                if ($prior) {
                    foreach ($data as $key=>$value) { if ($key!=='recorded_at' && $prior[$key]!==$value) { throw new PeraCRM_SES_Sync_Error(); } }
                }
                $reason=$entry['reason']; $mailbox=$entry['normalized_email'];
                self::db($wpdb->query($wpdb->prepare('UPDATE '.peracrm_table('peracrm_email_suppressions')
                    ." SET {$reason}=1,version=version+1,updated_at=%s WHERE normalized_email=%s AND {$reason}=0 AND ".$this->guard($connection,$guard),$now,$mailbox)));
                $this->session($connection);
                if ($prior) { continue; }
                $this->insert('peracrm_email_suppression_events',['normalized_email'=>$mailbox,'reason'=>$reason,'source'=>'ses_account_sync',
                    'source_ref'=>$this->scope.':'.$entry['hash'],'actor_user_id'=>0,
                    'occurred_at'=>gmdate('Y-m-d H:i:s',(int)$entry['LastUpdateTime']),'recorded_at'=>$now],$connection,$guard,false);
                $this->insert('peracrm_campaign_ses_sync_facts',$data,$connection,$guard,false);
            }
            $this->insert('peracrm_campaign_ses_sync_pages',['scope_hash'=>$this->scope,'run_id'=>$row['run_id'],
                'cursor_hash'=>$cursor,'recorded_at'=>$now],$connection,$guard,false);
            $table=peracrm_table('peracrm_campaign_ses_sync');
            $terminal=$next===null;
            self::db($wpdb->query($wpdb->prepare("UPDATE {$table} SET cursor_value=NULLIF(%s,''),pages=pages+1,entries=entries+%d,state=%s,error_code='',updated_at=%s"
                .($terminal?',last_complete_at=UTC_TIMESTAMP()':'').' WHERE scope_hash=%s AND '.$this->guard($connection,$guard),
                $next??'',count($entries),$terminal?'completed':'running',$now,$this->scope)));
            $this->session($connection); return $this->checkpoint(true);
        });
    }
    public function __debugInfo(): array { return ['redacted'=>true]; }
    public function __serialize(): array { throw new LogicException('sync_serialization_disabled'); }
    public function __unserialize(array $data): void { throw new LogicException('sync_serialization_disabled'); }
    private function __clone() {}
}

/** No credentials, executor, runtime hook or filter can enable production sync. */
function peracrm_ses_suppression_sync_service(): ?PeraCRM_SES_Suppression_Sync { return null; }
