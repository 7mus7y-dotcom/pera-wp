# Reciprocal hreflang sample verification — 17 November 2026

## Scope

The site owner ran `verify-hreflang.py` against the previously collected 130-property HTTP audit, sampling 12 properties spread across the catalogue. This document records the **owner-provided terminal output**, not an independently rerun audit or a copy of the generated JSON file.

Sampled property IDs: `7837`, `22720`, `33553`, `37875`, `41997`, `45795`, `51372`, `54770`, `55984`, `58487`, `59805`, `60339`.

For each sampled property, the script checked one URL for each of these five advertised language codes: `en`, `de-de`, `zh`, `ar`, `ru`.

| Metric | Result |
| --- | ---: |
| Source properties sampled | 12 |
| Hreflang URLs checked | 60 |
| HTTP 200 | 60 |
| Script-reported issues | 0 |

The script checks same-domain targets, successful HTTP status, no unexpected target redirect, a single self-referencing target canonical, and presence of a reciprocal hreflang entry pointing to the original English source. No issues were reported for any of the 60 checks.

## Limitations

- This is a **sample**, not exhaustive validation of all translated URLs for all 130 properties.
- The checker does not verify translation quality, language-region appropriateness, Google Search Console indexing, or every possible pairwise reciprocal language relationship.
- The script's reciprocal source-language check uses a code beginning with `en`; this should not be interpreted as full validation of every hreflang locale code.
- The generated output `/tmp/pera-hreflang-audit.json` remains on the hosting server and was not committed.

## Entity mapping outcome

The 130-property catalogue now links 128 properties to a district entity using explicit district taxonomy. Two properties (`39557`, `40298`) have only the Bahçeşehir region taxonomy; `area:bahcesehir` is registered as a neighbourhood/market area and their administrative district is deliberately not inferred.

No live WordPress content or configuration was changed.
