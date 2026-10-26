#!/bin/sh
set -eu
# No authenticated read without explicit target-blog report user.
expect_exit() {
    expected="$1"; shift
    actual=0
    "$@" > /tmp/peracrm-scheduler-cli.json 2> /tmp/peracrm-scheduler-cli.stderr || actual=$?
    [ "$actual" = "$expected" ] || { cat /tmp/peracrm-scheduler-cli.json; echo "Unexpected CLI exit: $actual expected $expected" >&2; exit 1; }
    [ ! -s /tmp/peracrm-scheduler-cli.stderr ] || { cat /tmp/peracrm-scheduler-cli.stderr; exit 1; }
    php -r '$r=json_decode(file_get_contents("/tmp/peracrm-scheduler-cli.json"),true,512,JSON_THROW_ON_ERROR); if (!is_array($r) || preg_match("/@|owner_token|content_hash|attempt_key|credentials/",json_encode($r)) || array_key_exists("mailbox",$r)) { exit(1); }'
}
expect_exit 3 wp peracrm campaigns health --format=json
expect_exit 3 wp peracrm campaigns readiness --format=json
expect_exit 3 wp --user=campaign-fixture-manager peracrm campaigns readiness --force --format=json
expect_exit 4 wp --user=campaign-fixture-manager peracrm campaigns readiness --format=json
php -r '$r=json_decode(file_get_contents("/tmp/peracrm-scheduler-cli.json"),true,512,JSON_THROW_ON_ERROR); if ($r["production_ready"] || $r["dispatch_enabled"] || $r["checks"]["dns_dkim_dmarc"]["state"]!=="unverified") { exit(1); }'
expect_exit 3 wp peracrm campaigns fixture-run --format=json
expect_exit 3 wp peracrm campaigns fixture-recover --confirm-disposable=wrong --format=json
expect_exit 2 wp --user=campaign-fixture-manager peracrm campaigns health --format=json
expect_exit 2 wp --user=campaign-fixture-manager peracrm campaigns status --format=json
expect_exit 0 wp --user=campaign-fixture-manager peracrm campaigns due --limit=1 --format=json
expect_exit 3 wp --user=campaign-fixture-manager peracrm campaigns due --limit=26 --format=json
expect_exit 3 wp --user=campaign-fixture-manager peracrm campaigns due --force --format=json
expect_exit 0 wp peracrm campaigns fixture-recover --confirm-disposable=campaign_fixture --format=json
expect_exit 0 wp peracrm campaigns fixture-run --confirm-disposable=campaign_fixture --format=json
# A real schema outage/storage failure is operational exit 4; invalid/anonymous remains 3.
wp eval-file /tests/scheduler-cli-fault.php block
expect_exit 4 wp --user=campaign-fixture-manager peracrm campaigns due --format=json
expect_exit 4 wp --user=campaign-fixture-manager peracrm campaigns health --format=json
expect_exit 3 wp peracrm campaigns due --format=json
wp eval-file /tests/scheduler-cli-fault.php restore
expect_exit 4 env PERACRM_SCHEDULER_STORAGE_FAILURE=1 wp --user=campaign-fixture-manager peracrm campaigns due --format=json
expect_exit 4 env PERACRM_SCHEDULER_STORAGE_FAILURE=1 wp --user=campaign-fixture-manager peracrm campaigns health --format=json
expect_exit 3 wp peracrm campaigns fixture-run --confirm-disposable=campaign_fixture --limit=1 --format=json
expect_exit 4 env PERACRM_SCHEDULER_MODULE_DISABLED=1 wp --user=campaign-fixture-manager peracrm campaigns health --format=json
php -r '$r=json_decode(file_get_contents("/tmp/peracrm-scheduler-cli.json"),true,512,JSON_THROW_ON_ERROR); if (!$r["schema_ready"] || $r["module_enabled"] || $r["queue_ready"] || !in_array("module_disabled",$r["diagnostics"],true)) { exit(1); }'
printf '%s\n' 'PASS scheduler CLI: real JSON, exit codes, explicit fixture authority, bounded read, no private payloads'
