#!/bin/bash
set -euo pipefail

REPO="${PERA_SYNC_REPO:-/home/peraukco/_work/pera-wp}"
LIVE="${PERA_SYNC_LIVE:-/home/peraukco/public_html}"

rsync_options=()
case "${1:-}" in
  '') ;;
  --dry-run) rsync_options+=(--dry-run) ;;
  *) echo "Usage: $0 [--dry-run]" >&2; exit 1 ;;
esac
if (( $# > 1 )); then
  echo "Usage: $0 [--dry-run]" >&2
  exit 1
fi

cd "$REPO"
if [[ ! -d "$LIVE/wp-content" ]]; then
  echo "Live WordPress wp-content directory not found: $LIVE/wp-content" >&2
  exit 1
fi

file_list=$(mktemp)
trap 'rm -f "$file_list"' EXIT

# HEAD reflog entries describe pulls, not successful deployments. Always include
# every tracked content file so missed additions are picked up on the next sync.
git ls-files -z -- wp-content/ > "$file_list"
if [[ ! -s "$file_list" ]]; then
  echo "No Git-tracked wp-content files found; nothing to sync."
  exit 0
fi

# Checksums detect changes even when size/mtime match. No --delete: live-only
# uploads, caches, plugins and utilities remain untouched. NUL paths handle spaces.
echo "Syncing Git-tracked wp-content files (new or changed content only)..."
rsync -av --checksum --from0 --files-from="$file_list" "${rsync_options[@]}" ./ "$LIVE/"

echo
echo "Done."
