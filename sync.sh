#!/bin/bash
set -e

REPO="/home/peraukco/_work/pera-wp"
LIVE="/home/peraukco/public_html"

cd "$REPO"

echo "Files changed since previous HEAD:"
git diff --name-status HEAD@{1} HEAD

echo
echo "Syncing changed/new wp-content files..."

git diff --diff-filter=AMCR --name-only HEAD@{1} HEAD \
  | grep '^wp-content/' \
  | rsync -av --files-from=- ./ "$LIVE/"

echo
echo "Done."
