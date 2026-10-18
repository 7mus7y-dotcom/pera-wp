<?php
/** Bounded parser/validation regressions; SQL and public HTTP are exercised in integration. */
require __DIR__ . '/campaign-foundations-test.php';
require dirname(__DIR__) . '/inc/campaigns/management.php';
require dirname(__DIR__) . '/inc/campaigns/import.php';
$map = array_flip(['email','state','source','source_ref','evidence_ref','policy_version','occurred_at']);
function subscriber_stream($text) { $stream = fopen('php://temp','w+'); fwrite($stream,$text); rewind($stream); return $stream; }
function subscriber_rejected(callable $fn) { try { $fn(); return false; } catch (InvalidArgumentException $error) { return true; } }
$csv = "email,state,source,source_ref,evidence_ref,policy_version,occurred_at\nMixed+Tag@Example.test,subscribed,archive,ref,consent,v1,2020-01-02 03:04:05\n";
$stream = subscriber_stream($csv);
$parsed = peracrm_subscribers_parse_csv($stream,$map); fclose($stream);
$row = peracrm_subscribers_validate_import_row($parsed[0]);
campaign_expect($row['email'] === 'mixed+tag@example.test' && $row['evidence']['occurred_at'] === '2020-01-02 03:04:05', 'subscriber CSV preserves identity tags and historical UTC evidence');
$raw = $parsed[0]; $raw['evidence_ref'] = '';
campaign_expect(peracrm_subscribers_validate_import_row($raw)['missing_evidence'], 'empty consent evidence is explicit in preview');
$raw['occurred_at'] = '2020-02-30 00:00:00';
campaign_expect(subscriber_rejected(static function () use ($raw) { peracrm_subscribers_validate_import_row($raw); }), 'invalid historical calendar date rejected');
$stream = subscriber_stream($csv);
campaign_expect(subscriber_rejected(static function () use ($stream) { peracrm_subscribers_parse_csv($stream,['email'=>0]); }), 'mapping cannot be guessed from headings'); fclose($stream);
$stream = subscriber_stream($csv); $bad = $map; $bad['state'] = 0;
campaign_expect(subscriber_rejected(static function () use ($stream,$bad) { peracrm_subscribers_parse_csv($stream,$bad); }), 'duplicate mapped columns rejected'); fclose($stream);
$stream = subscriber_stream(str_repeat('a',1048577));
campaign_expect(subscriber_rejected(static function () use ($stream,$map) { peracrm_subscribers_parse_csv($stream,$map); }), 'one MiB stream limit enforced'); fclose($stream);
$stream = subscriber_stream(explode("\n",$csv)[0] . "\n" . str_repeat(explode("\n",$csv)[1] . "\n",1001));
campaign_expect(subscriber_rejected(static function () use ($stream,$map) { peracrm_subscribers_parse_csv($stream,$map); }), '1001st row rejected'); fclose($stream);
foreach (['=cmd',' +1','@cmd',"\t-1",'\r=cmd','-12','+2'] as $value) {
    if ($value === '\r=cmd') { $value = "\r=cmd"; }
    campaign_expect(str_starts_with(peracrm_subscribers_csv_cell($value), "'"), 'CSV formula prefix neutralized');
}
$source = file_get_contents(dirname(__DIR__) . '/inc/campaigns/import.php');
campaign_expect(!str_contains($source,'peracrm_import_apply_row') && !str_contains($source,'wp_insert_user') && !str_contains($source,'wp_insert_post'), 'subscriber import has no sales or user write path');
echo "PASS subscriber parsing and validation regressions\n";

function get_post_stati($args) { return ['publish', 'draft', 'private', 'pending', 'future']; }
class Subscriber_Preview_DB extends Campaign_Memory_DB
{
    public $preview_queries = [];
    public $fail_preview = false;
    public function __get($name) { return in_array($name, ['posts', 'postmeta'], true) ? $this->prefix . $name : parent::__get($name); }
    public function get_results($sql, $format) {
        $this->preview_queries[] = $sql;
        if ($this->fail_preview) { $this->last_error = 'synthetic preview failure'; return null; }
        if (str_contains($sql, 'COUNT(DISTINCT p.ID)')) {
            $rows = str_contains($sql, "'batch-0@example.test'") ? [
                ['normalized_email' => 'batch-0@example.test', 'matches' => 2],
                ['normalized_email' => 'batch-1@example.test', 'matches' => 1],
            ] : [];
            return array_values(array_filter($rows, static function ($row) use ($sql) { return str_contains($sql, "'" . $row['normalized_email'] . "'"); }));
        }
        $rows = str_contains($sql, "'batch-0@example.test'") ? [
            ['normalized_email' => 'batch-0@example.test', 'existing' => 1, 'unsubscribe' => 0],
            ['normalized_email' => 'batch-0@example.test', 'existing' => 0, 'unsubscribe' => 1],
            ['normalized_email' => 'batch-2@example.test', 'existing' => 0, 'complaint' => 1],
        ] : [];
        return array_values(array_filter($rows, static function ($row) use ($sql) { return str_contains($sql, "'" . $row['normalized_email'] . "'"); }));
    }
}
$original_db = $wpdb;
$wpdb = new Subscriber_Preview_DB();
$wpdb->tables = $original_db->tables;
$emails = array_map(static function ($n) { return 'batch-' . $n . '@example.test'; }, range(0, 999));
$states = peracrm_subscribers_preview_states($emails);
campaign_expect(count($states) === 1000 && count($wpdb->preview_queries) === 20, '1000-row preview uses 20 bounded lookup queries');
campaign_expect($states[$emails[0]] === ['existing' => true, 'suppressed' => true, 'client_match' => 'ambiguous_unresolved'], 'union preserves identity and suppression without choosing ambiguous client');
campaign_expect($states[$emails[1]]['client_match'] === 'available_unlinked' && $states[$emails[2]]['suppressed'] && !$states[$emails[2]]['existing'], 'single match and suppression without subscriber remain distinct');
campaign_expect($states[$emails[999]] === ['existing' => false, 'suppressed' => false, 'client_match' => 'none'], 'missing mailbox data stays explicitly absent');
campaign_expect(get_current_blog_id() === 1 && !array_filter($wpdb->preview_queries, static function ($sql) { return str_contains($sql, 'wp_1_'); }), 'preview lookups use target tables and restore context');
$wpdb->preview_queries = [];
peracrm_subscribers_preview_states([$emails[0], strtoupper($emails[0])]);
campaign_expect(count($wpdb->preview_queries) === 2, 'duplicate normalized mailbox lookup is performed once');
campaign_expect(subscriber_rejected(static function () use ($emails) { peracrm_subscribers_preview_states(array_merge($emails, ['overflow@example.test'])); }), 'lookup input bound enforced');
$wpdb->fail_preview = true;
$lookup_failed = false;
try { peracrm_subscribers_preview_states([$emails[0]]); } catch (RuntimeException $error) { $lookup_failed = true; }
campaign_expect($lookup_failed && get_current_blog_id() === 1, 'lookup errors fail closed and restore blog');
$wpdb = $original_db;
echo "PASS bounded subscriber preview lookup regressions\n";
