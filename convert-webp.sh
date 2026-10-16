#!/usr/bin/env bash
set -euo pipefail

uploads="$HOME/public_html/wp-content/uploads"
month="$(date +%Y/%m)"
dry_run=0

while (($#)); do
    case "$1" in
        --dry-run)
            dry_run=1
            shift
            ;;
        --all)
            month=""
            shift
            ;;
        --month)
            if [[ ! ${2:-} =~ ^[0-9]{4}/(0[1-9]|1[0-2])$ ]]; then
                echo "Usage: --month YYYY/MM" >&2
                exit 1
            fi
            month="$2"
            shift 2
            ;;
        *)
            echo "Unknown option: $1" >&2
            exit 1
            ;;
    esac
done

target="$uploads${month:+/$month}"
[[ -d "$target" ]] || {
    echo "Folder not found: $target" >&2
    exit 1
}

if (( ! dry_run )); then
    command -v convert >/dev/null || {
        echo "ImageMagick convert is not installed." >&2
        exit 1
    }
fi

tmp=""
cleanup() {
    [[ -z "$tmp" ]] || rm -f -- "$tmp"
}
file_list=""
finish() {
    cleanup
    [[ -z "$file_list" ]] || rm -f -- "$file_list"
}
trap finish EXIT
trap 'echo; echo "Stopped."; exit 130' INT
trap 'exit 143' TERM

# Materialize once: process substitution is unavailable on some cPanel hosts.
file_list=$(mktemp)
find "$target" -type f \
    \( -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.png' \) \
    -print0 > "$file_list"

# Reuse legacy basename.webp only if exactly one original maps to it.
declare -A source_count=()
while IFS= read -r -d '' img; do
    legacy="${img%.*}.webp"
    source_count["$legacy"]=$(( ${source_count["$legacy"]:-0} + 1 ))
done < "$file_list"

converted=0
skipped=0
pending=0
failed=0

echo "Scanning: $target"

while IFS= read -r -d '' img; do
    out="${img}.webp"
    legacy="${img%.*}.webp"
    if [[ ! -s "$out" && -s "$legacy" && ${source_count["$legacy"]} -eq 1 ]]; then
        out="$legacy"
    fi

    if [[ -s "$out" ]]; then
        if (( dry_run )); then
            if [[ $(stat -c '%a' -- "$out") != 644 ]]; then
                printf 'Would ensure permissions: %s\n' "$out"
            fi
        elif ! chmod 644 -- "$out"; then
            printf 'FAILED to repair permissions: %s\n' "$out" >&2
            failed=$((failed + 1))
            continue
        fi

        skipped=$((skipped + 1))
        continue
    fi

    pending=$((pending + 1))

    if (( dry_run )); then
        printf 'Would convert: %s\n' "$img"
        continue
    fi

    printf 'Converting: %s\n' "$img"

    # Use a temporary file so Ctrl+C leaves no partial final output.
    tmp=$(mktemp "${out}.tmp.XXXXXX")

    if convert "$img" -strip -quality 82 "webp:$tmp" &&
       [[ -s "$tmp" ]] &&
       chmod 644 -- "$tmp"; then
        mv -- "$tmp" "$out"
        tmp=""
        converted=$((converted + 1))
    else
        cleanup
        tmp=""
        printf 'FAILED: %s\n' "$img" >&2
        failed=$((failed + 1))
    fi
done < "$file_list"

printf '\nPending found: %d | Converted: %d | Skipped: %d | Failed: %d\n' \
    "$pending" "$converted" "$skipped" "$failed"

(( failed == 0 ))
