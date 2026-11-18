<?php
require __DIR__.'/campaign-foundations-test.php';
require_once $root.'/inc/campaigns/management.php';
require_once $root.'/inc/campaigns/client-mailing.php';
class Client_Mailing_DB extends Campaign_Memory_DB {
    public $profiles = [];
    public function __get($name) { return in_array($name, ['posts','postmeta'], true) ? $this->prefix.$name : parent::__get($name); }
    public function get_col($sql) {
        if (preg_match('/WHERE post_id=(\d+) AND meta_key=/', $sql, $m)) { return $this->profiles[(int)$m[1]] ?? []; }
        if (preg_match('/AND ID>(\d+) AND ID<=(\d+)/', $sql, $m)) { return array_slice(array_values(array_filter(array_keys($this->profiles), static fn($id) => $id > (int)$m[1] && $id <= (int)$m[2])), 0, 500); }
        return parent::get_col($sql);
    }
    public function get_row($sql, $format) {
        if (str_starts_with($sql, 'SELECT post_type, post_status')) { return ['post_type'=>'crm_client','post_status'=>'publish']; }
        return parent::get_row($sql, $format);
    }
    public function get_var($sql) {
        if (str_starts_with($sql, 'SELECT MAX(ID)')) { return $this->profiles ? max(array_keys($this->profiles)) : null; }
        return parent::get_var($sql);
    }
}
$old_db = $GLOBALS['wpdb'];
$db = new Client_Mailing_DB(); $db->ddl = $old_db->ddl;
$GLOBALS['wpdb'] = $db;
$GLOBALS['campaign_actor'] = 1; $GLOBALS['campaign_impersonating'] = false;
$GLOBALS['campaign_client_status'] = 'publish'; $GLOBALS['campaign_denied_clients'] = [];
$GLOBALS['campaign_roles'][2]['administrator']->add_cap('manage_options');
$mail_before = $GLOBALS['campaign_mail'];
$db->profiles = [10=>[' New.Client@Example.test '], 11=>[], 12=>['bad email@example.test'], 13=>['blocked-client@example.test'], 14=>['duplicate@example.test','other@example.test']];
peracrm_subscribers_readiness(['enabled'=>true,'consent_mapping'=>'fixture','suppression_sources'=>'fixture','retention'=>'fixture']);
$before = $db->tables;
campaign_expect(peracrm_client_mailing_status(10)['status'] === 'missing' && peracrm_client_mailing_status(11)['status'] === 'missing_email'
    && peracrm_client_mailing_status(12)['status'] === 'invalid_email' && peracrm_client_mailing_status(14)['status'] === 'invalid_email'
    && $before === $db->tables, 'client mailing status is read-only and rejects invalid/ambiguous email without repair');
campaign_expect(is_wp_error(peracrm_client_mailing_confirm(10,'new.client@example.test',false,'client reply'))
    && is_wp_error(peracrm_client_mailing_confirm(10,'new.client@example.test',true,''))
    && is_wp_error(peracrm_client_mailing_confirm(10,'stale@example.test',true,'client reply')), 'explicit permission/reference and current mailbox are required');
$db->fail_insert = 'peracrm_subscription_events';
campaign_expect(is_wp_error(peracrm_client_mailing_confirm(10,'new.client@example.test',true,'client WhatsApp reply'))
    && peracrm_client_mailing_status(10)['status'] === 'missing', 'event storage failure rolls back subscriber creation and confirmation');
$db->fail_insert = '';
campaign_expect(peracrm_client_mailing_confirm(10,'new.client@example.test',true,'client WhatsApp reply') === 'saved', 'confirmed client can join mailing list atomically');
$row = peracrm_with_target_blog(static fn() => peracrm_campaigns_repository_identity('new.client@example.test'));
campaign_expect(peracrm_client_mailing_status(10)['status'] === 'subscribed' && empty($row['client_id'])
    && $row['source']==='crm_staff_confirmation' && $row['source_ref']==='crm-client:10' && $row['evidence_ref']==='client WhatsApp reply', 'mailbox match and staff evidence do not silently create a client link');
$before = $db->tables;
campaign_expect(peracrm_client_mailing_confirm(10,'new.client@example.test',true,'duplicate request') === 'already_subscribed'
    && $before === $db->tables, 'repeated confirmation preserves existing evidence and profile');
peracrm_campaigns_add_suppression('blocked-client@example.test','unsubscribe',['source'=>'fixture']);
campaign_expect(peracrm_client_mailing_status(13)['status']==='suppressed'
    && is_wp_error(peracrm_client_mailing_confirm(13,'blocked-client@example.test',true,'client reply'))
    && peracrm_client_mailing_status(13)['subscriber_id']===0, 'suppression-only tombstone cannot be enrolled');
peracrm_campaigns_withdraw('new.client@example.test',['source'=>'fixture']);
campaign_expect(is_wp_error(peracrm_client_mailing_confirm(10,'new.client@example.test',true,'later reply')), 'client panel cannot reverse a prior withdrawal');
$db->profiles[15]=['intake-disabled@example.test'];
peracrm_subscribers_readiness(['enabled'=>false,'consent_mapping'=>'fixture','suppression_sources'=>'fixture','retention'=>'fixture']);
campaign_expect(is_wp_error(peracrm_client_mailing_confirm(15,'intake-disabled@example.test',true,'client reply'))
    && peracrm_client_mailing_status(15)['status']==='missing', 'closed intake gate cannot create mailing permission');
$GLOBALS['campaign_denied_clients']=[10];
campaign_expect(is_wp_error(peracrm_client_mailing_confirm(10,'new.client@example.test',true,'client reply')), 'client authority enforced independently of subscriber capability');
$GLOBALS['campaign_denied_clients']=[];
$GLOBALS['campaign_impersonating']=true;
campaign_expect(is_wp_error(peracrm_client_mailing_confirm(10,'new.client@example.test',true,'client reply')), 'impersonation cannot mutate client mailing permission');
$GLOBALS['campaign_impersonating']=false;
$_SERVER['REQUEST_METHOD']='POST'; $_POST=['client_id'=>'10'];
$nonce_rejected=false; try { peracrm_client_mailing_post(); } catch (RuntimeException $e) { $nonce_rejected=$e->getMessage()==='nonce_rejected'; }
campaign_expect($nonce_rejected, 'POST rejects missing/invalid client-specific nonce before mutation');
$GLOBALS['campaign_actor']=3;
campaign_expect(is_wp_error(peracrm_client_mailing_confirm(10,'new.client@example.test',true,'client reply')), 'employee cannot grant mailing permission');
$GLOBALS['campaign_actor']=1;
for ($id=100;$id<601;$id++) { $db->profiles[$id]=['shared-audit@example.test']; }
$before=$db->tables; $audit=peracrm_client_mailing_audit();
campaign_expect($audit['read_only'] && $audit['client_records']===507 && array_sum($audit['counts'])===507 && $audit['counts']['missing']===502
    && $before===$db->tables && !str_contains(json_encode($audit),'@'), 'coverage audit counts every client without writes or mailbox disclosure');
campaign_expect(get_current_blog_id()===1 && !$GLOBALS['campaign_stack'] && $GLOBALS['campaign_mail']===$mail_before, 'client mailing operations restore target scope and send no email');
echo "PASS client mailing permission and coverage regressions\n";
