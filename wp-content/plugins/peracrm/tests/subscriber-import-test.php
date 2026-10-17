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
