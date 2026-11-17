# Campaign retention maintenance

Owner-approved starting periods: 90 days for detailed diagnostics and 12 months
for aggregate reports. These periods do not authorize deletion of consent,
suppression, token verification or duplicate-send protections.

## Implemented maintenance

Administrator WP-CLI only, using the existing target-blog/operator capabilities.
No HTTP endpoint, AWS credentials, provider calls, sending or activation.

```sh
# Default is SELECT-only. Counts are candidates in this bounded batch, not totals.
wp --path=/home/d21882/public_html --url=https://www.peraproperty.com --user=1 \
  peracrm campaigns retention --limit=25 --format=json

# Explicitly retire the eligible data reported by a new evaluation.
wp --path=/home/d21882/public_html --url=https://www.peraproperty.com --user=1 \
  peracrm campaigns retention --limit=25 \
  --confirm=retire-expired-diagnostics --format=json
```

Each apply transaction handles at most 25 SNS bodies and 25 import count
summaries. Repeat bounded calls while either count is nonzero. Save the JSON
output privately as maintenance evidence. Dry run reserves nothing; apply
reevaluates eligibility under locks. There is no automatically installed cron.

* SNS bodies are retired only after 90 days since receipt and last processing
  activity, with terminal `completed`/`quarantined` progress and a durable,
  matching-policy normalization. Pending, failed, waiting, conflict, exhausted,
  manual-review and recently reprocessed receipts are held. Policy mismatch,
  missing normalization or event conflict also prevents retirement. Review held
  data separately; age is not authority to erase unresolved security evidence.
* Retirement empties `canonical_payload`, marking `authentication_version` as
  `sns-v2-retired`. The original hash, certificate digest, scope, SNS ID and
  processing provenance remain. An authenticated identical duplicate compares
  its original digest, never rehydrates the body, and cannot create a new send.
  Conflicting or forged deliveries remain rejected. Existing normalizations
  and suppression/acceptance evidence can still be inspected and verified.
  A retired body cannot be normalized into new facts.
* Standalone import count summaries are cleared 12 calendar months after batch
  expiry, only for terminal batches. Batch identity, status and total remain.
  Existing import completion/expiry already removes import-row payloads; this
  command does not keep those payloads for 90 days or reopen imports.

## Protected evidence and remaining scope

No rows are deleted from subscribers, consent/suppression history, normalized
event facts, acceptance evidence, provider-message associations, recipient-token
registries, encrypted submission outputs, deliveries, queue jobs or cancellation
and attempt ledgers. Those are operational evidence, not disposable diagnostics.
Keys and private files are never read or deleted by this command.

There is currently no separate materialized aggregate **campaign report** store
to expire. The 12-month cleanup above applies to existing standalone import
count summaries; it does not misclassify the delivery ledger as an aggregate
report. Future materialized reports must implement their own 12-month expiry.

Private diagnostic captures, backup/key lifecycles and a regular maintenance
schedule remain operator tasks. This change does not by itself attest complete
retention readiness, resolve host logging or restore readiness, record external
evidence, or enable production dispatch.
