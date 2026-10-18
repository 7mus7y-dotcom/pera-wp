#!/bin/sh
set -eu
cd "$(dirname "$0")"
# Each invocation owns new volumes; cleanup never targets an existing deployment.
fixture_project="peracrm-campaigns-$(date +%s)-$$"
cleanup() { docker compose -p "$fixture_project" down --volumes --remove-orphans; }
trap cleanup EXIT HUP INT TERM
case "${1:-both}" in
    both) fixture_modes='single multi' ;;
    single|multi) fixture_modes="$1" ;;
    *) echo 'Usage: sh run.sh [both|single|multi]' >&2; exit 2 ;;
esac
for fixture_mode in $fixture_modes; do
    docker compose -p "$fixture_project" up -d --wait db wordpress
    docker compose -p "$fixture_project" run --rm cli "$fixture_mode"
    if [ "${PERACRM_BROWSER_QA:-0}" = 1 ]; then
        docker compose -p "$fixture_project" run --rm --entrypoint wp cli rewrite structure '/%postname%/' --hard
        if [ "$fixture_mode" = multi ]; then
            docker compose -p "$fixture_project" run --rm --entrypoint wp cli --url=http://fixture.test/crm rewrite structure '/%postname%/' --hard
        fi
        docker compose -p "$fixture_project" run --rm browser
    fi
    cleanup
done
