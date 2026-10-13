# Real OSM name validation follow-up

Run [38387490352](https://github.com/7mus7y-dotcom/pera-wp/actions/runs/38387490352) downloaded Geofabrik Türkiye, extracted metropolitan Istanbul and built **28,880 POIs from 47,616 candidates**. Offline validation then raised `Invalid source name/translation`. Database acceptance and delivery were skipped. The failed run used commit `96084871f2101f9433ae406f5e92e288dda796bf`.

The old diagnostics do not identify the actual failing OSM ID/key/value, and the rejected dataset was not retained. **The exact offending record is therefore unknown.** No OSM download was retried in Codex.

## Cause and targeted correction

`build.py::tags_of()` keeps `name` and all `name:*` tags. `normalize.php` passes those tags to the existing PHP classifier/normalizer; the classifier uses primary/Turkish names for category detection, while normalization copies every string `name:*` tag. Python and PHP validators previously allowed only one colon. Real OSM compound keys such as `name:en:pronunciation` or `name:tr:loc` could therefore be emitted but rejected. Non-string values or values over 4,096 UTF-8 bytes were the other possible causes of the same generic error. Normal OSM tag values are usually much shorter; the logs cannot distinguish these causes.

The shared offline text contract and matching production PHP contract now use these rules:

| Field | Rule |
| --- | --- |
| Name key | `name`, or `name:` followed by one or more nonempty colon-separated ASCII `[A-Za-z0-9_-]+` segments; maximum 255 bytes. |
| Primary name | Nonempty UTF-8 string; the literal string `0` is valid. |
| Translation/qualified value | UTF-8 string, including empty optional strings; maximum **4,096 UTF-8 bytes**. No truncation or value rewriting. |
| Encoded names | At most **65,535 bytes** using the production PHP JSON encoding, including escaped slashes/control characters/line separators. |
| Aliases | List of valid `node/`, `way/`, or `relation/` positive OSM IDs, each with at most 19 digits; encoded aliases also fit the existing TEXT limit. |
| Artifact line | Existing **65,536-byte** line limit remains in force. |

Examples preserved unchanged: `name:zh-Hant`, `name:sr-Latn`, `name:be-tarask`, `name:zh_pinyin`, `name:en:pronunciation`, Arabic/Turkish values and slash-containing names. This validates the safe OSM name namespace, not membership in a fixed language registry. Keys are data, never SQL identifiers or filesystem paths. Additional qualifiers do not alter category selection or renderer language lookup.

The offline PHP adapter validates normalized text before returning it. The builder validates incoming POIs, merged translations/aliases and final serialized records. The offline validator checks the same text rules independently of the body checksum. Production PHP validates cached/local records and streamed manual/batched imports with the matching grammar, UTF-8 and column budgets.

Errors identify a bounded, JSON-escaped OSM ID and key plus the reason/byte count, e.g. `OSM "node/123" key "name:tr": name value exceeds 4096 UTF-8 bytes (4098 bytes)`. Values are never dumped. Bad keys, malformed UTF-8, oversize text and oversize merged records still stop the build/import; they are not silently dropped to pass acceptance.

Other observed discrepancies addressed: offline validation previously ignored alias identity/column constraints; deduplication could exceed TEXT capacity after merging otherwise-valid translations; PHP's `empty()` discarded the legitimate primary name `0`. The failed run's default Bash shell lacked `pipefail`, so `tee` masked the validator exit and produced a secondary missing-result error. The build step now stops at the original failure. Workflow jobs, schedule, production guards, geographic/category/count/source/checksum checks and OSM/ODbL attribution/licensing remain in place.

## Focused verification

Using existing local PHP **8.4.24** and Python tools, without installing dependencies or downloading OSM:

```sh
PHP=php python3 -m unittest discover -s tools/nearby-poi -p 'test_*.py'
python3 tools/nearby-poi/create-test-fixtures.py work/update-fixtures
NEARBY_UPDATE_FIXTURES="$PWD/work/update-fixtures" php wp-content/themes/hello-elementor-child/tests/nearby-updates-test.php
```

Coverage includes unusual multilingual/compound keys through PHP normalization, builder storage and a rechecksummed accepted synthetic artifact; Python/PHP contract parity; UTF-8 byte boundaries; invalid keys/types/UTF-8; per-value and encoded-column limits; dedup overflow; alias constraints; bounded diagnostics; importer preservation and failure isolation. Existing provider/render/airport/import/replay/rollback/cache contracts also pass. PHP lint, Python compilation and workflow YAML/Bash syntax are checked. These are synthetic and simulated-DB tests; the first real artifact and MariaDB acceptance must run again on GitHub.

## Exact GitHub Actions run with the fix

**Do not click Re-run jobs on run 38387490352:** GitHub reruns the original commit, which lacks this fix. Start a new manual run on the fix branch. No merge is needed for this test because the dispatch workflow already exists on `main`.

1. Open [Monthly Istanbul POI refresh](https://github.com/7mus7y-dotcom/pera-wp/actions/workflows/nearby-poi-monthly.yml).
2. Click **Run workflow** and set **Use workflow from** to **`fix/nearby-poi-name-validation`**. Confirm the branch includes this PR's latest commit.
3. Set **`bootstrap=true`**, **`force=false`**, **`deliver=false`**. Keep repository variable `POI_DELIVERY_ENABLED` unset or `false`. Bootstrap's existing guard also prohibits delivery even if that variable is enabled.
4. Click **Run workflow**. Monitor `build` for download/extract/build/text/source/coverage validation, then `database-acceptance` for disposable WordPress/MariaDB import, nearest-three results, index plans, cache, timing and PHP memory. `deliver` must remain skipped.
5. On success, download `nearby-poi-<version>` and `nearby-poi-db-<version>` from Summary → Artifacts. Verify SHA256SUMS and both reports' version/pass status; review real category/district counts and OSM transport samples. Source-derived counts may differ from the prior run because the latest source can change.
6. On failure, inspect the first failed step, `nearby-poi-failed-<run_id>` (`failure.json`/`build.log` when available), or the retained DB report/log. Name errors now identify the offending OSM ID/key/reason. Investigate that specific record/constraint; do not weaken integrity/coverage gates or discard translations blindly.

CLI equivalent, for the operator to run outside Codex:

```sh
gh workflow run nearby-poi-monthly.yml --repo 7mus7y-dotcom/pera-wp \
  --ref fix/nearby-poi-name-validation \
  -f bootstrap=true -f force=false -f deliver=false
```

No run was dispatched by Codex. No merge, deployment, live dataset/DB change or frontend template/CSS/JavaScript change is included. Production acceptance and human source review remain separate gates.
