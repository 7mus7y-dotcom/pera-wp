#!/usr/bin/env bash
# Install this reviewed script OUTSIDE web root; use as the SSH forced command.
# It only accepts: receive <version>, with a tar.gz bundle on stdin.
set -euo pipefail
umask 077
BASE=/home/d21882/nearby-poi
WP=/home/d21882/public_html
WPCLI=/usr/local/bin/wp
command=${SSH_ORIGINAL_COMMAND:-}
if [[ ! "$command" =~ ^receive\ ([A-Za-z0-9_-]{1,80})$ ]]; then
  echo 'Only receive <version> is permitted.' >&2; exit 2
fi
version=${BASH_REMATCH[1]}
mkdir -p "$BASE/incoming" "$BASE/logs"
exec 9>"$BASE/update.lock"
flock -n 9 || { echo 'Another dataset receive/import is running.' >&2; exit 3; }
export WP_CLI_PHP_ARGS='-d memory_limit=128M'
finish() {
  code=$?
  if [[ -n "${upload:-}" ]]; then rm -rf "$upload"; fi
  if [[ "$code" -ne 0 ]]; then
    "$WPCLI" --path="$WP" pera nearby record-error "Monthly receive/import failed for $version (exit $code); inspect private update log." >/dev/null 2>&1 || true
  fi
  exit "$code"
}
trap finish EXIT
exec > >(tee -a "$BASE/logs/$version.log") 2>&1
free_kb=$(df -Pk "$BASE" | awk 'NR==2 {print $4}')
[[ "$free_kb" -ge 1048576 ]] || { echo 'Require 1 GiB free account filesystem disk before transfer/import.'; exit 4; }
upload=$(mktemp -d "$BASE/incoming/.upload-XXXXXXXX")
# Cap untrusted upload bytes. Neither archive nor PBF is ever evaluated as code.
php -d memory_limit=32M -r '
$f=fopen($argv[1],"wb"); $n=0;
while(!feof(STDIN)){ $b=fread(STDIN,1048576); $n+=strlen($b); if($n>115343360){exit(4);} fwrite($f,$b); }
fclose($f);' "$upload/bundle.tar.gz"
printf '%s\n' LICENSE.txt SHA256SUMS dataset.poi.ndjson report.json summary.txt > "$upload/expected"
tar -tzf "$upload/bundle.tar.gz" | LC_ALL=C sort > "$upload/actual"
cmp "$upload/expected" "$upload/actual"
# Reject expanded-size bombs before any extraction; GNU tar supplies size in field 3.
tar -tvzf "$upload/bundle.tar.gz" | awk '$3!~/^[0-9]+$/ || $3>104857600 {bad=1} {total+=$3} END {exit (bad || total>105200000)?1:0}'
# Reject directory, symlink and hardlink entries; only five regular files may extract.
if tar -tvzf "$upload/bundle.tar.gz" | awk 'substr($0,1,1)!="-" {bad=1} END {exit bad?0:1}'; then
  echo 'Archive contains non-regular entries.'; exit 5
fi
mkdir "$upload/files"
tar -xzf "$upload/bundle.tar.gz" --no-same-owner --no-same-permissions -C "$upload/files"
(
  cd "$upload/files"
  # Manifest may name only these three fixed files, never an arbitrary server path.
  [[ $(wc -l < SHA256SUMS) -eq 3 ]]
  awk 'NF!=2 || $1!~/^[a-f0-9]+$/ || length($1)!=64 || ($2!="dataset.poi.ndjson" && $2!="report.json" && $2!="LICENSE.txt") {bad=1} END{exit bad}' SHA256SUMS
  sha256sum --check --strict SHA256SUMS
)
php -r '$r=json_decode(file_get_contents($argv[1]),true); if(($r["version"]??null)!==$argv[2]||($r["passed"]??null)!==true) exit(6);' "$upload/files/report.json" "$version"
if [[ -d "$BASE/incoming/$version" ]]; then
  cmp "$upload/files/SHA256SUMS" "$BASE/incoming/$version/SHA256SUMS"
else
  mv "$upload/files" "$BASE/incoming/$version"
fi
artifact="$BASE/incoming/$version/dataset.poi.ndjson"
report="$BASE/incoming/$version/report.json"
"$WPCLI" --path="$WP" pera nearby validate-import "$artifact" --report="$report"
# Public ODbL access must be available BEFORE a generation is served. No scripts or credentials copied.
public="$WP/poi-data/$version"
mkdir -p "$public"
chmod 755 "$WP/poi-data" "$public"
cp "$artifact" "$public/dataset.poi.ndjson.tmp"
cp "$BASE/incoming/$version/LICENSE.txt" "$public/LICENSE.txt.tmp"
chmod 644 "$public/"*.tmp
mv "$public/dataset.poi.ndjson.tmp" "$public/dataset.poi.ndjson"
mv "$public/LICENSE.txt.tmp" "$public/LICENSE.txt"
# Index is only static reviewed paths derived from the restricted version alphabet.
index=$(mktemp "$WP/poi-data/.index-XXXXXXXX")
printf '%s\n' '<!doctype html><html lang="en"><meta charset="utf-8"><title>Pera OpenStreetMap POI datasets</title><h1>OpenStreetMap POI datasets</h1><p>© OpenStreetMap contributors. Databases licensed under <a href="https://opendatacommons.org/licenses/odbl/1-0/">ODbL 1.0</a>.</p><ul>' > "$index"
for directory in "$WP/poi-data/"*/; do
  name=${directory%/}; name=${name##*/}
  if [[ "$name" =~ ^[A-Za-z0-9_-]{1,80}$ ]]; then
    printf '<li><a href="%s/dataset.poi.ndjson">%s: entire filtered database</a> (<a href="%s/LICENSE.txt">source and licence</a>)</li>\n' "$name" "$name" "$name" >> "$index"
  fi
done
printf '%s\n' '</ul></html>' >> "$index"
chmod 644 "$index"; mv "$index" "$WP/poi-data/index.html"
site=$("$WPCLI" --path="$WP" option get home)
curl --fail --head --proto '=https' --proto-redir '=https' --location --connect-timeout 10 --max-time 30 "${site%/}/poi-data/$version/dataset.poi.ndjson" >/dev/null
# A hard cap on iterations bounds a single job; checkpoints remain for the next retry.
complete=false
for ((batch=0; batch<600; batch++)); do
  result=$("$WPCLI" --path="$WP" pera nearby import "$artifact" --report="$report" --batch-size=1000)
  printf '%s\n' "$result"
  if printf '%s' "$result" | php -r '$r=json_decode(stream_get_contents(STDIN),true); exit(($r["complete"]??false)?0:1);'; then
    complete=true; break
  fi
done
[[ "$complete" == true ]] || { echo 'Import incomplete; retry identical bundle to resume.'; exit 7; }
"$WPCLI" --path="$WP" pera nearby dataset
"$WPCLI" --path="$WP" pera nearby status
"$WPCLI" --path="$WP" pera nearby cleanup
# The site-specific page cache adapter is installed locally by the operator, never in upload bundles.
if [[ -x "$BASE/bin/purge-property-cache" ]]; then "$BASE/bin/purge-property-cache"; fi
printf 'Accepted dataset %s\n' "$version"
