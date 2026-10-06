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
trap cleanup EXIT
trap 'echo; echo "Stopped."; exit 130' INT
trap 'exit 143' TERM

# Refuse ambiguous conversions such as hero.jpg and hero.png, because both
# would otherwise map to hero.webp and could display the wrong attachment.
declare -A source_for_output=()
collision_found=0

while IFS= read -r -d '' img; do
    out="${img%.*}.webp"

    if [[ -n ${source_for_output["$out"]+present} ]] &&
       [[ ${source_for_output["$out"]} != "$img" ]]; then
        printf 'COLLISION: %s and %s both map to %s\n' \
            "${source_for_output["$out"]}" "$img" "$out" >&2
        collision_found=1
        continue
    fi

    source_for_output["$out"]="$img"
done < <(
    find "$target" -type f \
        \( -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.png' \) \
        -print0
)

if (( collision_found )); then
    echo "No files converted because ambiguous WebP output names were found." >&2
    exit 1
fi

converted=0
skipped=0
pending=0
failed=0

echo "Scanning: $target"

while IFS= read -r -d '' img; do
    out="${img%.*}.webp"

    if [[ -s "$out" ]]; then
        if (( dry_run )); then
            printf 'Would ensure permissions: %s\n' "$out"
        elif ! chmod --reference="$img" "$out"; then
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
       chmod --reference="$img" "$tmp"; then
        mv -- "$tmp" "$out"
        tmp=""
        converted=$((converted + 1))
    else
        cleanup
        tmp=""
        printf 'FAILED: %s\n' "$img" >&2
        failed=$((failed + 1))
    fi
done < <(
    find "$target" -type f \
        \( -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.png' \) \
        -print0
)

printf '\nPending found: %d | Converted: %d | Skipped: %d | Failed: %d\n' \
    "$pending" "$converted" "$skipped" "$failed"

(( failed == 0 ))
