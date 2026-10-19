<?php
require __DIR__.'/campaign-worker-test.php';
require $root.'/inc/campaigns/operations.php';
require $root.'/inc/campaigns/scheduler.php';
class Campaign_Disabled_Probe extends PeraCRM_Campaign_Operations {
    protected function table(string $entity): string { throw new LogicException('Disabled entry touched delivery tables.'); }
    protected function timestamp(): string { return '2026-10-19 12:00:00'; }
    protected function ready(): bool { return false; }
}
$probe=new Campaign_Disabled_Probe();
for ($i=0;$i<3;$i++) {
    campaign_expect($probe->invoke()['result']==='disabled' && !peracrm_campaign_scheduler_registration(),'disabled entry gates before any delivery query, on repeated invocations');
}
campaign_expect(get_current_blog_id()===1 && empty($GLOBALS['campaign_options'][1]['peracrm_campaign_scheduler_last']),'production invocation restores context and metadata belongs to target');
$GLOBALS['campaign_actor']=1; $GLOBALS['campaign_impersonating']=false;
$h=$probe->health();
campaign_expect(!$h['schema_ready'] && !$h['dispatch_enabled'] && $h['scheduler_state']==='disabled' && $h['last_successful_processing']===null,'unready/disabled health does not claim success');
$GLOBALS['campaign_actor']=0;
try { $probe->health(); campaign_expect(false,'anonymous health forbidden'); } catch (RuntimeException $e) {}
if (!defined('WP_CLI')) { define('WP_CLI',true); }
class Campaign_CLI_Exit extends RuntimeException {}
class WP_CLI {
    public static array $commands=[];
    public static string $output='';
    public static function add_command($name,$callback) { self::$commands[$name]=$callback; }
    public static function line($line) { self::$output=$line; }
    public static function halt($code) { throw new Campaign_CLI_Exit('exit',$code); }
}
require $root.'/inc/cli/campaign_cli.php';
foreach (['fixture-run','fixture-recover','health','due'] as $op) {
    $exit=0;
    try { (WP_CLI::$commands['peracrm campaigns '.$op])([],['format'=>'json','confirm-disposable'=>'campaign_fixture']); }
    catch (Campaign_CLI_Exit $e) { $exit=$e->getCode(); }
    campaign_expect($exit===3 && json_decode(WP_CLI::$output,true)===['error'=>'campaign_command_rejected'],'CLI production guard/authority JSON exit 3: '.$op);
}
campaign_expect(count(WP_CLI::$commands)===5 && !isset(WP_CLI::$commands['peracrm campaigns send']),'no production send/reset/enable commands');
echo "PASS scheduler/CLI standalone contracts\n";
