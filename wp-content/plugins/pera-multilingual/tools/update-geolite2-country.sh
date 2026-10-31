#!/usr/bin/env bash
# User-level maintenance only. No WordPress bootstrap or redirect activation.
set +x
set -Eeuo pipefail
umask 077
export PATH=/usr/local/bin:/usr/bin:/bin

directory=${PERA_GEO_DIRECTORY:-/home/d21882/private/pera-geo}
updater=${PERA_GEO_UPDATER:-/home/d21882/bin/geoipupdate}
php=${PERA_GEO_PHP:-php}
update_timeout=${PERA_GEO_UPDATE_TIMEOUT:-300}
validate_timeout=${PERA_GEO_VALIDATE_TIMEOUT:-60}
candidate=''
child=''
logging=0

log() {
    local level=$1 message=$2
    if (( logging )); then
        printf '%s %s %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" "$level" "$message" >&3 || true
    fi
    if [[ $level == ERROR ]]; then printf 'GeoLite2 maintenance: %s\n' "$message" >&2; fi
}
fail() { local code=$1; log ERROR "$2"; exit "$code"; }
cleanup() {
    local result=$?
    trap - EXIT
    if [[ -n $child ]]; then
        kill -TERM "$child" 2>/dev/null || true
        wait "$child" 2>/dev/null || true
    fi
    if [[ -n $candidate && -e $candidate ]]; then
        if ! rm -f -- "$candidate" 2>/dev/null; then log ERROR 'Private temporary candidate cleanup failed; operator cleanup required.'; fi
    fi
    exit "$result"
}
trap cleanup EXIT
trap 'fail 70 "Unexpected maintenance failure; inspect private files and permissions."' ERR
trap 'fail 130 "Interrupted; unfinished candidate will not be promoted."' INT
trap 'fail 143 "Terminated; unfinished candidate will not be promoted."' TERM

# Timeout owns and terminates its subprocess group; only fixed messages/statuses are logged.
run_step() {
    local duration=$1 result=0
    shift
    timeout --signal=TERM --kill-after=15s "${duration}s" "$@" >/dev/null 2>&1 &
    child=$!
    wait "$child" || result=$?
    child=''
    return "$result"
}
secure_directory() {
    local mode
    [[ -d $1 && ! -L $1 && -O $1 && -w $1 ]] || return 1
    mode=$(stat -c '%a' -- "$1" 2>/dev/null) || return 1
    (( (8#$mode & 0022) == 0 ))
}

[[ -z ${REQUEST_METHOD:-} && -z ${GATEWAY_INTERFACE:-} ]] || fail 10 'CLI maintenance only; HTTP/CGI invocation refused.'
[[ $directory == /* ]] || fail 10 'Private database directory must be absolute.'
for tool in flock timeout stat mktemp cp chmod mv rm date; do
    command -v "$tool" >/dev/null 2>&1 || fail 10 'Required user-level maintenance utility is unavailable.'
done
secure_directory "$directory" || fail 10 'Private directory must exist, be owned/writable by this user and not be group/world writable or a symlink.'
directory=$(cd -P -- "$directory" && pwd)
script_directory=$(cd -P -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
webroot=$(cd -P -- "$script_directory/../../../.." && pwd)
[[ $directory != "$webroot" && $directory != "$webroot/"* ]] || fail 10 'Database maintenance directory must be outside the WordPress webroot.'
config=$directory/GeoIP.conf
staging=$directory/staging
staged=$staging/GeoLite2-Country.mmdb
active=$directory/GeoLite2-Country.mmdb
validator=$script_directory/validate-geoip-db.php
lock=$directory/maintenance.lock
logfile=$directory/maintenance.log

[[ -f $config && ! -L $config && -r $config && -O $config ]] || fail 10 'Private updater configuration must be a readable, user-owned regular file.'
config_mode=$(stat -c '%a' -- "$config" 2>/dev/null) || fail 10 'Cannot inspect private configuration permissions.'
(( (8#$config_mode & 0077) == 0 )) || fail 10 'Private updater configuration must not grant group/other permissions (use 0600).'
secure_directory "$staging" || fail 10 'Private staging directory must exist with safe ownership and permissions.'
[[ ! -e $active || ( -f $active && ! -L $active && -O $active ) ]] || fail 10 'Active database must be a user-owned regular file, not a directory or symlink.'
[[ ! -L $active && ! -L $staged ]] || fail 10 'Database paths must not be symlinks.'
[[ -x $updater && -f $updater ]] || fail 10 'Configured geoipupdate executable is unavailable.'
command -v "$php" >/dev/null 2>&1 || fail 10 'Configured CLI PHP executable is unavailable.'
[[ -r $validator && -f $validator ]] || fail 10 'Database validator is unavailable.'
[[ $update_timeout =~ ^[1-9][0-9]{0,3}$ && $validate_timeout =~ ^[1-9][0-9]{0,2}$ ]] || fail 10 'Invalid maintenance timeout configuration.'
(( update_timeout <= 3600 && validate_timeout <= 300 )) || fail 10 'Maintenance timeouts exceed supported bounds.'
for path in "$lock" "$logfile"; do
    [[ ! -L $path && ( ! -e $path || ( -f $path && -O $path ) ) ]] || fail 10 'Lock and log paths must be user-owned regular files, not symlinks.'
done
exec 9>>"$lock"
if flock -n -E 75 9; then :; else
    result=$?
    if (( result == 75 )); then fail 75 'Another maintenance run holds the lock; this run was skipped.'; fi
    fail 10 'Cannot acquire the maintenance lock.'
fi
exec 3>>"$logfile"
logging=1
chmod 600 -- "$lock" "$logfile" 2>/dev/null || fail 10 'Cannot secure maintenance lock/log permissions.'

# -d explicitly forces staging even if GeoIP.conf has a different DatabaseDirectory.
if run_step "$update_timeout" "$updater" -f "$config" -d "$staging"; then :; else
    result=$?
    fail 20 "Database download failed (status $result); active database retained."
fi
[[ -f $staged && ! -L $staged && -r $staged && -O $staged ]] || fail 30 'No safe staged database was produced; active database retained.'
if run_step "$validate_timeout" "$php" "$validator" "$staged"; then :; else
    result=$?
    fail 30 "Staged database validation failed (status $result); active database retained."
fi

# mktemp in the active directory guarantees a same-filesystem rename. Never move
# the download directly: validate the exact snapshot that will be promoted too.
candidate=$(mktemp "$directory/.GeoLite2-Country.candidate.XXXXXXXX") || fail 40 'Cannot create private promotion candidate; active database retained.'
cp -- "$staged" "$candidate" 2>/dev/null || fail 40 'Cannot copy staged snapshot; active database retained.'
chmod 600 -- "$candidate" 2>/dev/null || fail 40 'Cannot secure promotion candidate; active database retained.'
if run_step "$validate_timeout" "$php" "$validator" "$candidate"; then :; else
    result=$?
    fail 30 "Promotion snapshot validation failed (status $result); active database retained."
fi
[[ ! -L $active && ( ! -e $active || ( -f $active && -O $active ) ) ]] || fail 40 'Active path changed during maintenance; promotion refused.'
mv -fT -- "$candidate" "$active" 2>/dev/null || fail 40 'Atomic promotion failed; active database retained.'
candidate=''
log INFO 'Validated Country database promoted atomically.'
exit 0
