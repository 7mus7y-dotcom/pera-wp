#!/bin/sh
set -eu
rm -f /tmp/peracrm-scheduler-held /tmp/peracrm-scheduler-loser-done
wp eval-file /tests/scheduler-race.php prepare
wp eval-file /tests/scheduler-race.php winner &
scheduler_pid=$!
for attempt in $(seq 1 600); do
    [ -f /tmp/peracrm-scheduler-held ] && break
    sleep 0.05
done
[ -f /tmp/peracrm-scheduler-held ]
wp eval-file /tests/scheduler-race.php loser
wait "$scheduler_pid"
wp eval-file /tests/scheduler-race.php verify
wp eval-file /tests/scheduler-race.php prepare
actual=0
wp eval-file /tests/scheduler-race.php crash || actual=$?
[ "$actual" = 74 ]
wp eval-file /tests/scheduler-race.php recover
wp eval-file /tests/scheduler-race.php prepare
actual=0
wp eval-file /tests/scheduler-race.php crash-accepted || actual=$?
[ "$actual" = 73 ]
wp eval-file /tests/scheduler-race.php recover-accepted
