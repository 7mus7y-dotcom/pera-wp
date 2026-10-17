#!/bin/sh
set -eu
cd /var/www/html
fixture_mode="${1:?single or multi required}"
case "$fixture_mode" in single|multi) ;; *) exit 2 ;; esac
for attempt in $(seq 1 60); do
    [ -f wp-config.php ] && break
    sleep 1
done
[ -f wp-config.php ]
if [ "$fixture_mode" = multi ]; then
    wp core multisite-install --url=http://fixture.test --title=Fixture --admin_user=fixture-admin --admin_password=fixture-admin-only --admin_email=admin@example.test --skip-email
    wp site create --slug=crm --title=CRM --email=admin@example.test
    wp config set PERACRM_TARGET_BLOG_ID 2 --raw
    wp plugin activate peracrm --network
else
    wp core install --url=http://fixture.test --title=Fixture --admin_user=fixture-admin --admin_password=fixture-admin-only --admin_email=admin@example.test --skip-email
    wp plugin activate peracrm
fi
wp eval-file /tests/assertions.php migrations
rm -f /tmp/peracrm-campaign-holder-ready /tmp/peracrm-campaign-duration-*
wp eval-file /tests/worker.php hold &
holder_pid=$!
for attempt in $(seq 1 100); do
    [ -f /tmp/peracrm-campaign-holder-ready ] && break
    sleep 0.1
done
[ -f /tmp/peracrm-campaign-holder-ready ]
worker_pids=''
for reason in unsubscribe hard_bounce complaint administrative; do
    wp eval-file /tests/worker.php "$reason" &
    worker_pids="$worker_pids $!"
done
wait "$holder_pid"
for worker_pid in $worker_pids; do wait "$worker_pid"; done
wp eval-file /tests/assertions.php concurrency
# Race before acquisition, during DDL, and after a newer successful publication.
wait_for_migration_marker() {
    for attempt in $(seq 1 600); do
        [ -f "$1" ] && return 0
        sleep 0.05
    done
    echo "Missing migration barrier: $1" >&2
    return 1
}
rm -f /tmp/peracrm-migration-*
wp eval-file /tests/migration-worker.php prepare
PERACRM_MIGRATION_WORKER_MODE=race-loser wp eval-file /tests/migration-worker.php race-loser &
migration_loser_pid=$!
wait_for_migration_marker /tmp/peracrm-migration-loser-paused
PERACRM_MIGRATION_WORKER_MODE=race-owner wp eval-file /tests/migration-worker.php race-owner &
migration_owner_pid=$!
wait_for_migration_marker /tmp/peracrm-migration-owner-ready
wait_for_migration_marker /tmp/peracrm-migration-loser-returned
touch /tmp/peracrm-migration-owner-release
wait "$migration_owner_pid"
wait "$migration_loser_pid"
PERACRM_MIGRATION_WORKER_MODE=stale-owner wp eval-file /tests/migration-worker.php stale-owner &
migration_stale_pid=$!
wait_for_migration_marker /tmp/peracrm-migration-stale-released
PERACRM_MIGRATION_WORKER_MODE=successor wp eval-file /tests/migration-worker.php successor &
migration_successor_pid=$!
wait "$migration_stale_pid"
wait "$migration_successor_pid"
wp eval-file /tests/migration-worker.php verify
wp eval-file /tests/management.php
rm -f /tmp/peracrm-phase2-held
wp eval-file /tests/import-worker.php withdraw &
phase2_withdraw_pid=$!
wait_for_migration_marker /tmp/peracrm-phase2-held
wp eval-file /tests/import-worker.php import &
phase2_import_pid=$!
wait "$phase2_withdraw_pid"
wait "$phase2_import_pid"
wp eval-file /tests/import-worker.php setup-public-race
rm -f /tmp/peracrm-phase2-import-held
PERACRM_PHASE2_IMPORT_PAUSE=1 wp eval-file /tests/import-worker.php import-first &
phase2_import_pid=$!
wait_for_migration_marker /tmp/peracrm-phase2-import-held
wp eval-file /tests/import-worker.php public-withdraw &
phase2_public_pid=$!
wait "$phase2_import_pid"
wait "$phase2_public_pid"
wp eval-file /tests/import-worker.php verify
printf 'PASS disposable %s WordPress/MySQL fixtures\n' "$fixture_mode"
