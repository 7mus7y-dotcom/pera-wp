<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/recipient-tokens.php';
require_once __DIR__.'/recipient-expansion.php';
require_once __DIR__.'/submission.php';

/**
 * Internal, unbootstrapped metadata store. Call only with trusted frozen bindings
 * and deployment configuration, in the target blog, outside another transaction.
 * This is not worker authority, eligibility, transport permission or body storage.
 * Every mutator returns only after COMMIT. No transport callback is accepted.
 */
final class PeraCRM_CampaignRecipientPersistence
{
    private $deployment;
    private $blog;
    private $busy = false;

    public function __construct(string $deployment, int $blog)
    {
        peracrm_campaign_recipient_require(!isset($this->deployment)
            && preg_match('/\A[a-zA-Z0-9_-]{1,64}\z/', $deployment) === 1 && $blog > 0);
        $this->deployment = $deployment; $this->blog = $blog;
    }

    private function table(string $suffix): string { return peracrm_table('peracrm_campaign_'.$suffix); }
    private function scope(): array { return ['deployment'=>$this->deployment, 'blog_id'=>$this->blog]; }
    private static function hashValue(string $value): void
    {
        peracrm_campaign_recipient_require(preg_match('/\A[a-f0-9]{64}\z/', $value) === 1);
    }
    private function checkBinding(#[\SensitiveParameter] array $binding): void
    {
        peracrm_campaign_recipient_binding($binding);
        peracrm_campaign_recipient_require($binding['blog_id'] === $this->blog);
    }
    private static function db($result)
    {
        global $wpdb;
        peracrm_campaign_recipient_require($result !== false && $wpdb->last_error === '');
        return $result;
    }
    private function transaction(callable $operation)
    {
        global $wpdb;
        $started = false; $owned = false; $previous = $wpdb->suppress_errors(true);
        try {
            peracrm_campaign_recipient_require(!$this->busy && get_current_blog_id() === $this->blog
                && (!is_multisite() || (int)peracrm_get_target_blog_id() === $this->blog) && peracrm_campaigns_schema_ready());
            // SAVEPOINT outside a transaction is a no-op; RELEASE then fails.
            // Inside a transaction RELEASE succeeds: reject without committing or
            // rolling back the caller's work. Also reject autocommit=0 sessions.
            peracrm_campaign_recipient_require((int)self::db($wpdb->get_var('SELECT @@autocommit')) === 1);
            self::db($wpdb->query('SAVEPOINT peracrm_recipient_top_level_probe'));
            peracrm_campaign_recipient_require($wpdb->query('RELEASE SAVEPOINT peracrm_recipient_top_level_probe') === false);
            self::db($wpdb->query('START TRANSACTION')); $started = true; $this->busy = true; $owned = true;
            $result = $operation();
            self::db($wpdb->query('COMMIT')); $started = false;
            return $result;
        } catch (Throwable $error) {
            if ($started) { $wpdb->query('ROLLBACK'); }
            throw new RuntimeException('recipient_persistence_unavailable');
        } finally {
            if ($owned) { $this->busy = false; }
            $wpdb->suppress_errors($previous);
        }
    }

    private function identity(int $subscriber): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.$this->table('recipient_identities')
            .' WHERE deployment=%s AND blog_id=%d AND subscriber_id=%d FOR UPDATE', $this->deployment, $this->blog, $subscriber), ARRAY_A);
        self::db(true);
        return $row;
    }
    private function requireIdentity(#[\SensitiveParameter] array $binding): void
    {
        $row = $this->identity($binding['subscriber_id']);
        peracrm_campaign_recipient_require($row && (int)$row['active'] === 1
            && $row['mailbox'] === $binding['mailbox'] && (int)$row['token_epoch'] === $binding['token_epoch']);
    }

    /** Same mailbox mutex as staff/import lifecycle writes, before identity locks. */
    private function requireSubscriber(int $subscriber, string $mailbox): void
    {
        [$row] = peracrm_campaigns_repository_lock_mailbox($mailbox);
        peracrm_campaign_recipient_require($row && (int)$row['id'] === $subscriber
            && $row['normalized_email'] === $mailbox);
    }

    /** First trusted identity registration. Existing different identities fail closed. */
    public function ensureIdentity(int $subscriber, #[\SensitiveParameter] string $mailbox): int
    {
        return $this->transaction(function () use ($subscriber, $mailbox) {
            global $wpdb;
            $this->checkBinding(['blog_id'=>$this->blog, 'subscriber_id'=>$subscriber, 'mailbox'=>$mailbox,
                'token_epoch'=>1, 'attempt_key'=>str_repeat('0',64), 'snapshot_hash'=>str_repeat('0',64)]);
            $this->requireSubscriber($subscriber, $mailbox);
            self::db($wpdb->query($wpdb->prepare('INSERT INTO '.$this->table('recipient_identities')
                .' (deployment,blog_id,subscriber_id,mailbox,token_epoch,active) VALUES (%s,%d,%d,%s,1,1)'
                .' ON DUPLICATE KEY UPDATE subscriber_id=subscriber_id', $this->deployment,$this->blog,$subscriber,$mailbox)));
            $row = $this->identity($subscriber);
            peracrm_campaign_recipient_require($row && $row['mailbox'] === $mailbox && (int)$row['active'] === 1);
            return (int)$row['token_epoch'];
        });
    }

    /** Mailbox change, deletion or explicit identity revocation advances the epoch.
     * Re-enabling never restores an old epoch, even when returning to an old address. */
    public function changeIdentity(int $subscriber, int $expectedEpoch, #[\SensitiveParameter] string $mailbox, bool $active): int
    {
        return $this->transaction(function () use ($subscriber,$expectedEpoch,$mailbox,$active) {
            global $wpdb;
            $this->checkBinding(['blog_id'=>$this->blog,'subscriber_id'=>$subscriber,'mailbox'=>$mailbox,
                'token_epoch'=>$expectedEpoch,'attempt_key'=>str_repeat('0',64),'snapshot_hash'=>str_repeat('0',64)]);
            peracrm_campaign_recipient_require($expectedEpoch < PHP_INT_MAX);
            // This API revokes identity only; it is not a subscriber mailbox editor.
            // Active identities must match the committed current subscriber.
            if ($active) { $this->requireSubscriber($subscriber, $mailbox); }
            $row = $this->identity($subscriber);
            peracrm_campaign_recipient_require($row && (int)$row['token_epoch'] === $expectedEpoch);
            self::db($wpdb->update($this->table('recipient_identities'),
                ['mailbox'=>$mailbox,'token_epoch'=>$expectedEpoch+1,'active'=>(int)$active],
                $this->scope()+['subscriber_id'=>$subscriber,'token_epoch'=>$expectedEpoch]));
            return $expectedEpoch+1;
        });
    }

    /** Immutable insert-or-compare. No update of stored binding bytes on replay. */
    private function bind(string $suffix, array $key, #[\SensitiveParameter] array $metadata, array $extra = []): array
    {
        global $wpdb;
        $json = json_encode($metadata, JSON_THROW_ON_ERROR);
        $data = $this->scope()+$key+$extra+['binding_json'=>$json,'binding_hash'=>hash('sha256',$json)];
        $table = $this->table($suffix);
        $columns = implode(',',array_keys($data));
        $placeholders = implode(',',array_fill(0,count($data),'%s'));
        $noop = array_key_first($key);
        self::db($wpdb->query($wpdb->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders}) ON DUPLICATE KEY UPDATE {$noop}={$noop}", array_values($data))));
        $row = $this->find($suffix,$key);
        peracrm_campaign_recipient_require($row && $row['binding_json'] === $json && hash_equals($row['binding_hash'],hash('sha256',$json)));
        return $metadata;
    }
    private function find(string $suffix, array $key): ?array
    {
        global $wpdb;
        $column = array_key_first($key);
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.$this->table($suffix)
            ." WHERE deployment=%s AND blog_id=%d AND {$column}=%s FOR UPDATE",$this->deployment,$this->blog,$key[$column]),ARRAY_A);
        self::db(true);
        return $row;
    }

    /** Digest and nonsecret protocol metadata only; never pass the raw bearer. */
    public function registerToken(#[\SensitiveParameter] string $digest, string $purpose, string $keyId, int $issued, int $expires, #[\SensitiveParameter] array $binding): void
    {
        $this->transaction(function () use ($digest,$purpose,$keyId,$issued,$expires,$binding) {
            self::hashValue($digest); $this->checkBinding($binding);
            peracrm_campaign_recipient_require(preg_match('/\Ak[0-9]{1,8}\z/',$keyId) === 1
                && $issued > 0 && $issued <= PHP_INT_MAX-86400
                && (($purpose === 'preferences' && $expires === $issued+86400) || ($purpose === 'unsubscribe' && $expires === 0)));
            $this->requireSubscriber($binding['subscriber_id'], $binding['mailbox']);
            $this->requireIdentity($binding);
            $canonical = json_decode(peracrm_campaign_recipient_binding($binding),true,512,JSON_THROW_ON_ERROR);
            $this->bind('recipient_tokens',['digest'=>$digest],
                ['protocol'=>'pc1','purpose'=>$purpose,'key_id'=>$keyId,'issued'=>$issued,'expires'=>$expires,'binding'=>$canonical],['subscriber_id'=>$binding['subscriber_id']]);
        });
    }

    /** Trusted locator; callers still MUST cryptographically verify before acting. */
    public function locate(#[\SensitiveParameter] string $digest): ?array
    {
        return $this->transaction(function () use ($digest) {
            self::hashValue($digest);
            // Read without a token lock to learn the identity; all mutations then
            // lock identity -> token, avoiding the opposite lock ordering.
            global $wpdb;
            $subscriber = $wpdb->get_var($wpdb->prepare('SELECT subscriber_id FROM '.$this->table('recipient_tokens')
                .' WHERE deployment=%s AND blog_id=%d AND digest=%s',$this->deployment,$this->blog,$digest)); self::db(true);
            if (!$subscriber) { return null; }
            $identity = $this->identity((int)$subscriber);
            $row = $this->find('recipient_tokens',['digest'=>$digest]);
            if (!$row || (int)$row['revoked'] !== 0 || !$identity || (int)$identity['active'] !== 1) { return null; }
            peracrm_campaign_recipient_require(hash_equals($row['binding_hash'],hash('sha256',$row['binding_json'])));
            $data = json_decode($row['binding_json'],true,512,JSON_THROW_ON_ERROR);
            $this->checkBinding($data['binding']);
            return $data['binding']['subscriber_id'] === (int)$subscriber
                && $data['binding']['mailbox'] === $identity['mailbox']
                && $data['binding']['token_epoch'] === (int)$identity['token_epoch'] ? $data : null;
        });
    }
    public function activeBinding(#[\SensitiveParameter] string $digest, string $purpose, #[\SensitiveParameter] array $binding, array $protocol = []): bool
    {
        try {
            $data = $this->locate($digest);
            return $data !== null && $data['purpose'] === $purpose
                && $protocol === ['protocol'=>$data['protocol'],'deployment'=>$this->deployment,'key_id'=>$data['key_id'],'issued'=>$data['issued'],'expires'=>$data['expires']]
                && peracrm_campaign_recipient_binding($data['binding']) === peracrm_campaign_recipient_binding($binding);
        } catch (Throwable $error) { return false; }
    }
    public function revokeToken(#[\SensitiveParameter] string $digest): void
    {
        $this->transaction(function () use ($digest) {
            global $wpdb;
            self::hashValue($digest);
            // UPDATE is monotonic, including repeated revocation. No identity
            // state is changed and no lock is subsequently taken on identity.
            peracrm_campaign_recipient_require($this->find('recipient_tokens',['digest'=>$digest]) !== null);
            self::db($wpdb->update($this->table('recipient_tokens'),['revoked'=>1],$this->scope()+['digest'=>$digest]));
        });
    }

    /** Persist before any future transport. A hash receipt grants no send authority.
     * Does not retain bodies or support retry reconstruction from random tokens. */
    public function bindAttempt(#[\SensitiveParameter] PeraCRM_CampaignSubmissionV1 $submission, #[\SensitiveParameter] array $binding, #[\SensitiveParameter] PeraCRM_CampaignRecipientOutput $output): string
    {
        return $this->transaction(function () use ($submission,$binding,$output) {
            $this->checkBinding($binding); $this->requireIdentity($binding);
            $metadata = self::attemptMetadata($submission,$binding,$output);
            $this->bind('submission_attempts',['attempt_key'=>$metadata['attempt_key']],$metadata,['delivery_key'=>$metadata['delivery_key']]);
            return $metadata['envelope_hash'];
        });
    }
    /** Historical metadata remains readable after revocation/cancellation. Never a retry permit. */
    public function attempt(string $key): ?array
    {
        return $this->transaction(function () use ($key) {
            self::hashValue($key);
            $row = $this->find('submission_attempts',['attempt_key'=>$key]);
            if (!$row) { return null; }
            peracrm_campaign_recipient_require(hash_equals($row['binding_hash'],hash('sha256',$row['binding_json'])));
            return json_decode($row['binding_json'],true,512,JSON_THROW_ON_ERROR);
        });
    }
    /** Pure defensive projection: explicit access contains identity metadata only. */
    public static function attemptMetadata(#[\SensitiveParameter] PeraCRM_CampaignSubmissionV1 $submission, #[\SensitiveParameter] array $binding, #[\SensitiveParameter] PeraCRM_CampaignRecipientOutput $output): array
    {
        peracrm_campaign_recipient_binding($binding);
        // Revalidate a defensive snapshot, independent of caller-held object state.
        $copy = new PeraCRM_CampaignSubmissionV1($submission->fields()); $fields = $copy->fields();
        $expanded = $output->fields();
        foreach (['blog_id','mailbox','attempt_key','snapshot_hash'] as $key) {
            peracrm_campaign_recipient_require($fields[$key] === $binding[$key]);
        }
        peracrm_campaign_recipient_require($expanded['snapshot_hash'] === $binding['snapshot_hash']
            && $expanded['recipient_binding_hash'] === hash('sha256',peracrm_campaign_recipient_binding($binding))
            && $expanded['html'] === $fields['html'] && $expanded['text'] === $fields['text']);
        $metadata = [];
        foreach (['version','scope','blog_id','campaign_id','job_id','revision_id','snapshot_id','recipient_id','snapshot_hash','delivery_key','attempt_key','personalization_version','mailbox'] as $key) { $metadata[$key] = $fields[$key]; }
        $metadata['subscriber_id'] = $binding['subscriber_id']; $metadata['token_epoch'] = $binding['token_epoch'];
        $metadata['expanded_output_hash'] = $output->hash(); $metadata['envelope_hash'] = $copy->hash();
        return $metadata;
    }
    public function __debugInfo(): array { return ['service'=>'peracrm_recipient_persistence','redacted'=>true]; }
    public function __serialize(): array { throw new LogicException('recipient_persistence_unavailable'); }
    public function __unserialize(array $data): void { throw new LogicException('recipient_persistence_unavailable'); }
}
