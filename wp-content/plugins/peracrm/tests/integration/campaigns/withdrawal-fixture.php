<?php
if (!defined('PERACRM_DISPOSABLE_CAMPAIGN_FIXTURE')) { throw new RuntimeException('Disposable fixture required.'); }
require_once WP_PLUGIN_DIR.'/peracrm/inc/campaigns/recipient-withdrawal.php';
require_once WP_PLUGIN_DIR.'/peracrm/tests/recipient-persistence-fixture.php';
function withdrawal_store(): PeraCRM_CampaignRecipientPersistence
{
    return new PeraCRM_CampaignRecipientPersistence('fixture-withdrawal', get_current_blog_id());
}
function withdrawal_keys(): array { return ['k1'=>str_repeat('synthetic-withdrawal-',3)]; }
function withdrawal_service(): PeraCRM_CampaignRecipientWithdrawal
{
    return new PeraCRM_CampaignRecipientWithdrawal(withdrawal_store(), withdrawal_keys(), 'fixture-withdrawal');
}
function withdrawal_seed(int $id, string $name, string $purpose = 'unsubscribe', int $age = 60): array
{
    $mailbox = $name.'@example.test';
    peracrm_test_persistence_subscriber($id, $mailbox);
    $store = withdrawal_store(); $epoch = $store->ensureIdentity($id,$mailbox);
    $binding = ['blog_id'=>get_current_blog_id(),'subscriber_id'=>$id,'mailbox'=>$mailbox,'token_epoch'=>$epoch,
        'attempt_key'=>hash('sha256',$name),'snapshot_hash'=>hash('sha256','withdrawal-snapshot')];
    $issuer = new PeraCRM_CampaignRecipientTokens(withdrawal_keys(),'k1','fixture-withdrawal',[$store,'activeBinding']);
    $now = time()-$age; $token = $issuer->issue($purpose,$binding,$now);
    $store->registerToken(hash('sha256',$token),$purpose,'k1',$now,$purpose === 'preferences' ? $now+86400 : 0,$binding);
    return ['token'=>$token,'binding'=>$binding];
}
function withdrawal_snapshot(): string
{
    global $wpdb; $out = [];
    foreach (['peracrm_subscribers','peracrm_subscription_events','peracrm_email_suppressions','peracrm_email_suppression_events',
        'peracrm_campaign_recipient_identities','peracrm_campaign_recipient_tokens','peracrm_campaign_submission_attempts'] as $table) {
        $out[$table] = $wpdb->get_results('SELECT * FROM '.peracrm_table($table).' ORDER BY 1,2,3', ARRAY_A);
    }
    return hash('sha256',wp_json_encode($out));
}
function withdrawal_expect(bool $ok, string $label): void
{
    if (!$ok) { throw new RuntimeException('FAIL withdrawal: '.$label); }
    echo 'PASS withdrawal: '.$label."\n";
}
