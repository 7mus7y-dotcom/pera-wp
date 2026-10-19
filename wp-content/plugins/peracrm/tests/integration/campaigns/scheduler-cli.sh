#!/bin/sh
set -eu
# No authenticated read without explicit target-blog report user.
expect_exit() {
    expected="$1"; shift
    actual=0
    "$@" > /tmp/peracrm-scheduler-cli.json || actual=$?
    [ "$actual" = "$expected" ] || { cat /tmp/peracrm-scheduler-cli.json; echo "Unexpected CLI exit: $actual expected $expected" >&2; exit 1; }
    php -r '$r=json_decode(file_get_contents("/tmp/peracrm-scheduler-cli.json"),true,512,JSON_THROW_ON_ERROR); if (!is_array($r) || preg_match("/@|mailbox|owner_token|content_hash|attempt_key|credentials/",json_encode($r))) { exit(1); }'
}
expect_exit 3 wp peracrm campaigns health --format=json
expect_exit 3 wp peracrm campaigns fixture-run --format=json
expect_exit 3 wp peracrm campaigns fixture-recover --confirm-disposable=wrong --format=json
expect_exit 2 wp --user=campaign-fixture-manager peracrm campaigns health --format=json
expect_exit 2 wp --user=campaign-fixture-manager peracrm campaigns status --format=json
expect_exit 0 wp --user=campaign-fixture-manager peracrm campaigns due --limit=1 --format=json
expect_exit 3 wp --user=campaign-fixture-manager peracrm campaigns due --limit=26 --format=json
expect_exit 3 wp --user=campaign-fixture-manager peracrm campaigns due --force --format=json
expect_exit 0 wp peracrm campaigns fixture-recover --confirm-disposable=campaign_fixture --format=json
expect_exit 0 wp peracrm campaigns fixture-run --confirm-disposable=campaign_fixture --format=json
printf '%s\n' 'PASS scheduler CLI: real JSON, exit codes, explicit fixture authority, bounded read, no private payloads'
