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
    wp rewrite structure '/%postname%/' --hard
    wp --url=http://fixture.test/crm rewrite structure '/%postname%/' --hard
    wp plugin activate peracrm --network
else
    wp core install --url=http://fixture.test --title=Fixture --admin_user=fixture-admin --admin_password=fixture-admin-only --admin_email=admin@example.test --skip-email
    wp rewrite structure '/%postname%/' --hard
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
wp eval-file /tests/drafts.php
rm -f /tmp/peracrm-draft-held /tmp/peracrm-draft-result-*
wp eval-file /tests/draft-worker.php first &
draft_first_pid=$!
wait_for_migration_marker /tmp/peracrm-draft-held
wp eval-file /tests/draft-worker.php second &
draft_second_pid=$!
wait "$draft_first_pid"
wait "$draft_second_pid"
wp eval-file /tests/draft-worker.php verify
if [ "$fixture_mode" = multi ]; then
    wp plugin activate pera-multilingual --network
else
    wp plugin activate pera-multilingual
fi
wp eval-file /tests/previews.php
wp eval-file /tests/snapshots.php
for persistence_race in identity identical conflict token-identical token-conflict revoke; do
    rm -f /tmp/peracrm-persistence-held /tmp/peracrm-persistence-winner /tmp/peracrm-persistence-loser
    wp eval-file /tests/recipient-persistence-race.php prepare "$persistence_race"
    wp eval-file /tests/recipient-persistence-race.php winner "$persistence_race" &
    persistence_winner_pid=$!
    wait_for_migration_marker /tmp/peracrm-persistence-held
    wp eval-file /tests/recipient-persistence-race.php loser "$persistence_race" &
    persistence_loser_pid=$!
    wait "$persistence_winner_pid"
    wait "$persistence_loser_pid"
    wp eval-file /tests/recipient-persistence-race.php verify "$persistence_race"
done
# Distinct PHP invocations prove recovery after process memory is gone.
for atomic_race in identical conflict identity-first preparation-first; do
    rm -f /tmp/peracrm-atomic-held /tmp/peracrm-atomic-winner /tmp/peracrm-atomic-loser
    wp eval-file /tests/atomic-preparation-race.php prepare "$atomic_race"
    wp eval-file /tests/atomic-preparation-race.php winner "$atomic_race" &
    atomic_winner_pid=$!
    wait_for_migration_marker /tmp/peracrm-atomic-held
    wp eval-file /tests/atomic-preparation-race.php loser "$atomic_race" &
    atomic_loser_pid=$!
    wait "$atomic_winner_pid"
    wait "$atomic_loser_pid"
    wp eval-file /tests/atomic-preparation-race.php verify "$atomic_race"
done
wp eval-file /tests/output-recovery.php prepare restart
wp eval-file /tests/output-recovery.php recover restart
wp eval-file /tests/output-recovery.php size-create
wp eval-file /tests/output-recovery.php size-recover
for output_race in identical conflict; do
    rm -f /tmp/peracrm-output-held /tmp/peracrm-output-winner /tmp/peracrm-output-loser
    wp eval-file /tests/output-recovery.php prepare "$output_race"
    wp eval-file /tests/output-recovery.php winner "$output_race" &
    output_winner_pid=$!
    wait_for_migration_marker /tmp/peracrm-output-held
    wp eval-file /tests/output-recovery.php loser "$output_race" &
    output_loser_pid=$!
    wait "$output_winner_pid"
    wait "$output_loser_pid"
    wp eval-file /tests/output-recovery.php verify "$output_race"
done
wp eval-file /tests/subscriber-lifecycle.php
for lifecycle_race in delete-register register-delete edit-register register-edit delete-create scope-delete; do
    rm -f /tmp/peracrm-lifecycle-held /tmp/peracrm-lifecycle-winner /tmp/peracrm-lifecycle-loser
    wp eval-file /tests/subscriber-lifecycle-race.php prepare "$lifecycle_race"
    wp eval-file /tests/subscriber-lifecycle-race.php winner "$lifecycle_race" &
    lifecycle_winner_pid=$!
    wait_for_migration_marker /tmp/peracrm-lifecycle-held
    wp eval-file /tests/subscriber-lifecycle-race.php loser "$lifecycle_race" &
    lifecycle_loser_pid=$!
    wait "$lifecycle_winner_pid"
    wait "$lifecycle_loser_pid"
    wp eval-file /tests/subscriber-lifecycle-race.php verify "$lifecycle_race"
done
wp eval-file /tests/freeze.php
wp eval-file /tests/queue.php
for queue_race in prepare-prepare batch-batch batch-cancel cancel-batch pause-schedule cancel-resume; do
    rm -f /tmp/peracrm-queue-held /tmp/peracrm-queue-winner /tmp/peracrm-queue-loser
    wp eval-file /tests/queue-race.php prepare "$queue_race"
    wp eval-file /tests/queue-race.php winner "$queue_race" &
    queue_winner_pid=$!
    wait_for_migration_marker /tmp/peracrm-queue-held
    wp eval-file /tests/queue-race.php loser "$queue_race" &
    queue_loser_pid=$!
    wait "$queue_winner_pid"
    wait "$queue_loser_pid"
    wp eval-file /tests/queue-race.php verify "$queue_race"
done
# Dedicated prepared synthetic source; fake outcomes are confined to separate fixture ledgers.
wp eval-file /tests/queue-race.php prepare pause-schedule
wp eval-file /tests/queue-worker.php
wp eval-file /tests/prepared-worker.php
wp eval-file /tests/sns-receipts.php
wp eval-file /tests/ses-normalization.php
for ses_scenario in duplicate logical conflict withdraw; do
    rm -f /tmp/peracrm-ses-held /tmp/peracrm-ses-winner /tmp/peracrm-ses-loser
    wp eval-file /tests/ses-normalization-race.php prepare "$ses_scenario"
    wp eval-file /tests/ses-normalization-race.php winner "$ses_scenario" &
    ses_winner_pid=$!
    wait_for_migration_marker /tmp/peracrm-ses-held
    wp eval-file /tests/ses-normalization-race.php loser "$ses_scenario" &
    ses_loser_pid=$!
    wait "$ses_winner_pid"
    wait "$ses_loser_pid"
    wp eval-file /tests/ses-normalization-race.php verify "$ses_scenario"
done
wp eval-file /tests/ses-matching.php
wp eval-file /tests/acceptance.php
wp eval-file /tests/worker-acceptance.php
wp eval-file /tests/ses-suppression.php
for suppress_scenario in duplicate logical bounce-complaint complaint-bounce bounce-withdraw withdraw-bounce complaint-withdraw withdraw-complaint triple bounce-delete delete-bounce bounce-identity identity-bounce; do
    rm -f /tmp/peracrm-suppress-held /tmp/peracrm-suppress-winner /tmp/peracrm-suppress-loser /tmp/peracrm-suppress-third
    wp eval-file /tests/ses-suppression-race.php prepare "$suppress_scenario"
    wp eval-file /tests/ses-suppression-race.php winner "$suppress_scenario" &
    suppress_winner_pid=$!
    wait_for_migration_marker /tmp/peracrm-suppress-held
    wp eval-file /tests/ses-suppression-race.php loser "$suppress_scenario" &
    suppress_loser_pid=$!
    if [ "$suppress_scenario" = triple ]; then
        wp eval-file /tests/ses-suppression-race.php third "$suppress_scenario" &
        suppress_third_pid=$!
    fi
    wait "$suppress_winner_pid"
    wait "$suppress_loser_pid"
    if [ "$suppress_scenario" = triple ]; then wait "$suppress_third_pid"; fi
    wp eval-file /tests/ses-suppression-race.php verify "$suppress_scenario"
done
for accept_scenario in event-response response-event duplicate conflict retry-fence; do
    rm -f /tmp/peracrm-accept-held /tmp/peracrm-accept-winner /tmp/peracrm-accept-loser
    wp eval-file /tests/acceptance-race.php prepare "$accept_scenario"
    wp eval-file /tests/acceptance-race.php winner "$accept_scenario" &
    accept_winner_pid=$!
    wait_for_migration_marker /tmp/peracrm-accept-held
    wp eval-file /tests/acceptance-race.php loser "$accept_scenario" &
    accept_loser_pid=$!
    wait "$accept_winner_pid"
    wait "$accept_loser_pid"
    wp eval-file /tests/acceptance-race.php verify "$accept_scenario"
done
for match_scenario in duplicate logical message attempt; do
    rm -f /tmp/peracrm-match-held /tmp/peracrm-match-winner /tmp/peracrm-match-loser
    wp eval-file /tests/ses-matching-race.php prepare "$match_scenario"
    wp eval-file /tests/ses-matching-race.php winner "$match_scenario" &
    match_winner_pid=$!
    wait_for_migration_marker /tmp/peracrm-match-held
    wp eval-file /tests/ses-matching-race.php loser "$match_scenario" &
    match_loser_pid=$!
    wait "$match_winner_pid"
    wait "$match_loser_pid"
    wp eval-file /tests/ses-matching-race.php verify "$match_scenario"
done
for sns_scenario in duplicate conflict; do
    rm -f /tmp/peracrm-sns-held /tmp/peracrm-sns-winner /tmp/peracrm-sns-loser
    wp eval-file /tests/sns-race.php prepare "$sns_scenario"
    wp eval-file /tests/sns-race.php winner "$sns_scenario" &
    sns_winner_pid=$!
    wait_for_migration_marker /tmp/peracrm-sns-held
    wp eval-file /tests/sns-race.php loser "$sns_scenario" &
    sns_loser_pid=$!
    wait "$sns_winner_pid"
    wait "$sns_loser_pid"
    wp eval-file /tests/sns-race.php verify "$sns_scenario"
done

for prepared_race in concurrent withdraw cancel interrupted; do
    rm -f /tmp/peracrm-prepared-held /tmp/peracrm-prepared-winner /tmp/peracrm-prepared-loser
    wp eval-file /tests/prepared-worker-race.php prepare "$prepared_race"
    wp eval-file /tests/prepared-worker-race.php winner "$prepared_race" &
    prepared_winner_pid=$!
    wait_for_migration_marker /tmp/peracrm-prepared-held
    if [ "$prepared_race" = interrupted ]; then
        prepared_exit=0
        wait "$prepared_winner_pid" || prepared_exit=$?
        [ "$prepared_exit" -eq 77 ]
        wp eval-file /tests/prepared-worker-race.php loser "$prepared_race"
    else
        wp eval-file /tests/prepared-worker-race.php loser "$prepared_race" &
        prepared_loser_pid=$!
        wait "$prepared_winner_pid"
        wait "$prepared_loser_pid"
    fi
    wp eval-file /tests/prepared-worker-race.php verify "$prepared_race"
done
wp eval-file /tests/scheduler.php
sh /tests/scheduler-cli.sh
sh /tests/scheduler-race.sh
for worker_race in claim-claim lease-stale crash-before crash-after claim-pause claim-cancel authorize-pause authorize-unsubscribe; do
    rm -f /tmp/peracrm-worker-held /tmp/peracrm-worker-winner /tmp/peracrm-worker-loser
    wp eval-file /tests/worker-race.php prepare "$worker_race"
    if [ "$worker_race" = crash-before ] || [ "$worker_race" = crash-after ]; then
        expected_crash=72
        [ "$worker_race" != crash-after ] || expected_crash=73
        actual_crash=0
        wp eval-file /tests/worker-race.php winner "$worker_race" || actual_crash=$?
        [ "$actual_crash" -eq "$expected_crash" ]
        printf '{}' > /tmp/peracrm-worker-winner
        printf '{}' > /tmp/peracrm-worker-loser
    else
        wp eval-file /tests/worker-race.php winner "$worker_race" &
        worker_winner_pid=$!
        wait_for_migration_marker /tmp/peracrm-worker-held
        wp eval-file /tests/worker-race.php loser "$worker_race" &
        worker_loser_pid=$!
        wait "$worker_winner_pid"
        wait "$worker_loser_pid"
    fi
    wp eval-file /tests/worker-race.php verify "$worker_race"
done
for freeze_race in start-start start-save continue-cancel cancel-continue continue-continue publish-cancel cancel-publish cleanup-start cleanup-resume; do
    rm -f /tmp/peracrm-freeze-held /tmp/peracrm-freeze-winner /tmp/peracrm-freeze-loser
    wp eval-file /tests/freeze-worker.php prepare "$freeze_race"
    wp eval-file /tests/freeze-worker.php winner "$freeze_race" &
    freeze_winner_pid=$!
    wait_for_migration_marker /tmp/peracrm-freeze-held
    wp eval-file /tests/freeze-worker.php loser "$freeze_race" &
    freeze_loser_pid=$!
    wait "$freeze_winner_pid"
    wait "$freeze_loser_pid"
    wp eval-file /tests/freeze-worker.php verify "$freeze_race"
done
wp eval-file /tests/freeze-worker.php restore
wp eval-file /tests/queue-ui-fixture.php
wp eval-file /tests/withdrawal.php
wp eval-file /tests/recipient-preferences.php
for withdrawal_race in withdraw-withdraw revoke-withdraw withdraw-revoke identity-withdraw withdraw-identity delete-withdraw withdraw-delete; do
    rm -f /tmp/peracrm-withdrawal-held /tmp/peracrm-withdrawal-winner /tmp/peracrm-withdrawal-loser
    wp eval-file /tests/withdrawal-race.php prepare "$withdrawal_race"
    wp eval-file /tests/withdrawal-race.php winner "$withdrawal_race" &
    withdrawal_winner_pid=$!
    wait_for_migration_marker /tmp/peracrm-withdrawal-held
    wp eval-file /tests/withdrawal-race.php loser "$withdrawal_race" &
    withdrawal_loser_pid=$!
    wait "$withdrawal_winner_pid"
    wait "$withdrawal_loser_pid"
    wp eval-file /tests/withdrawal-race.php verify "$withdrawal_race"
done
for preference_race in edit-edit revoke-edit edit-revoke identity-edit edit-identity delete-edit edit-delete withdraw-edit edit-withdraw; do
    rm -f /tmp/peracrm-preferences-held /tmp/peracrm-preferences-winner /tmp/peracrm-preferences-loser
    wp eval-file /tests/recipient-preferences-race.php prepare "$preference_race"
    wp eval-file /tests/recipient-preferences-race.php winner "$preference_race" &
    preference_winner_pid=$!
    wait_for_migration_marker /tmp/peracrm-preferences-held
    wp eval-file /tests/recipient-preferences-race.php loser "$preference_race" &
    preference_loser_pid=$!
    wait "$preference_winner_pid"
    wait "$preference_loser_pid"
    wp eval-file /tests/recipient-preferences-race.php verify "$preference_race"
done
wp eval-file /tests/sns-http.php
rm -f /tmp/peracrm-http-held /tmp/peracrm-http-winner /tmp/peracrm-http-loser
wp eval-file /tests/sns-http-race.php prepare
wp eval-file /tests/sns-http-race.php winner &
http_winner_pid=$!
wait_for_migration_marker /tmp/peracrm-http-held
wp eval-file /tests/sns-http-race.php loser &
http_loser_pid=$!
wait "$http_winner_pid"
wait "$http_loser_pid"
wp eval-file /tests/sns-http-race.php verify
wp eval-file /tests/ses-suppression-late.php
printf 'PASS disposable %s WordPress/MySQL fixtures\n' "$fixture_mode"
