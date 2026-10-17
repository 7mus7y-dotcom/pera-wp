#!/bin/sh
set -eu
cd "$(dirname "$0")"
# Each invocation owns new volumes; cleanup never targets an existing deployment.
fixture_project="peracrm-campaigns-$(date +%s)-$$"
cleanup() { docker compose -p "$fixture_project" down --volumes --remove-orphans; }
trap cleanup EXIT HUP INT TERM
for fixture_mode in single multi; do
    docker compose -p "$fixture_project" up -d --wait db wordpress
    docker compose -p "$fixture_project" run --rm cli "$fixture_mode"
    cleanup
done
