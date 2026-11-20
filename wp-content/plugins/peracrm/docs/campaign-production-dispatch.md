# Administrator CLI campaign dispatch

Installing this change does not enable sending. The default remains disabled.
Production dispatch uses the existing frozen-content, encrypted-output, recipient-token,
account-budget and acceptance-evidence pipeline. It does not use the diagnostic probe,
one-off rehearsal permit, generic fixture provider or `wp_mail`.

## Deployment and identity

Keep the existing deployment manifest, host instance ID and signing/encryption material.
Keep the private HTTP loader before `wp-settings.php`. It must configure the branded
withdrawal route and signed SNS intake on the authoritative HTTPS origin. Do not place
AWS credentials in that HTTP loader, a database option, shell history or `public_html`.

The worker reads an explicit JSON file outside `ABSPATH`, owned by the CLI account,
mode 0600, in a private directory mode 0700. It accepts a flat object (or STS
`Credentials` wrapper) with `AccessKeyId`, `SecretAccessKey`, optional `SessionToken`,
and mandatory ISO8601 `Expiration`. The deadline is a local rotation deadline for
static IAM keys, not an AWS expiry promise. Static-key deadlines cannot exceed 90 days;
assumed-role sessions cannot exceed one hour. Missing, expired or insecure files block.
Renew credentials by atomic replacement, maintaining ownership and permissions.
No ambient AWS credentials, profile, metadata service or default provider chain is used.

Create a dedicated least-privilege production principal separately, then pin its exact
IAM ARN. The worker authenticates it using regional STS before claiming recipients.
The principal needs `ses:SendEmail` for the verified sender identity and exact
configuration-set ARN, `ses:GetAccount`, and `ses:ListSuppressedDestinations`. Restrict
region and `ses:FromAddress`. SES permissions may need both verified domain and
separately verified mailbox identity ARNs. Read-only account actions use `Resource: *`;
send permissions must remain scoped. `sts:GetCallerIdentity` is the identity check.
A role also needs independently configured trust and credential renewal; this code does
not assume or renew roles. No SNS, SQS, IAM modification or unrestricted send permission
is required by this worker. Account-region budget ownership must be exclusive to this
installation; other senders sharing the SES quota need separate operational accounting.

## Explicit server configuration

Get the deployment fingerprint from an administrator CLI invocation of
`peracrm_campaign_deployment_service()->fingerprint()` in the target blog. Keep that
value pinned; do not compute it dynamically in the activation configuration.

```php
// Verification endpoints stay available when sending is disabled or credentials expire.
define('PERACRM_CAMPAIGN_PUBLIC_ENDPOINTS', [
    'enabled' => true,
    'deployment_fingerprint' => 'REPLACE_WITH_REVIEWED_DEPLOYMENT_SHA256',
]);
define('PERACRM_CAMPAIGN_WITHDRAWAL_HTTP', true);

define('PERACRM_CAMPAIGN_DISPATCH', [
    'enabled' => false,
    'deployment_fingerprint' => 'REPLACE_WITH_REVIEWED_DEPLOYMENT_SHA256',
    'credentials_file' => '/home/ACCOUNT/private-peracrm-ses/production-identity.json',
    'principal_arn' => 'arn:aws:iam::ACCOUNT_ID:user/PeraCRMCampaignSender',
    'sender_email' => 'info@peraproperty.com',
    'job_ids' => [],
    'per_second_cap' => 1,
    'daily_cap' => 5000,
]);
```

`PERACRM_SES_SNS_HTTP` and `PERACRM_SES_EVENT_OPERATIONS` must match every deployment,
account, blog, region, topic and configuration-set pin. Subscription provisioning must
be off. The public endpoints flag enables verification for all properly signed,
stored recipient tokens, rather than only the two rehearsal mailboxes. It does not
issue tokens, grant consent or enable sending. Preserve retained signing keys so old
unsubscribe links keep working. Preferences edits never restore withdrawn consent.

The exact `job_ids` allowlist (maximum 25) is a separate deployment approval. An enabled
configuration requires at least one ID. Review its immutable content, frozen sender,
recipient list, current consent and schedule before adding an ID. Cancelled jobs cannot
be reused. Reserved fixture domains (`.test`, `.invalid`, `.localhost`, example domains)
are always excluded. Do not approve the earlier fixture or cancelled test jobs.

## Readiness before activation

Use a real administrator with reports, campaign and subscriber management capabilities;
impersonation, anonymous HTTP and native WP-Cron cannot invoke this worker.

```sh
wp --path=/path/to/public_html --url=https://www.peraproperty.com --user=ADMIN \
  peracrm campaigns dispatch-refresh --confirm=refresh-provider-state --format=json
wp --path=/path/to/public_html --url=https://www.peraproperty.com --user=ADMIN \
  peracrm campaigns readiness --format=json
```

Refresh imports SES account suppression as denies only. It processes one bounded page
per invocation; repeat until completed. An invalid/stale cursor requires explicit
`--restart=abandon-cursor`; a partial checkpoint never authorizes sending. Quota
observations last five minutes. Both refresh and run authenticate the production
principal and require SES production access, healthy enforcement and sending enabled.
The local cap is the lower of the configured limit and observed SES capacity. The
account-region ledger conservatively counts pending and recent reservations across
jobs and invocations; refreshing quota never clears spend, pending ownership or breakers.

Readiness evidence remains required for DNS, IAM/quota, SNS/DLQ, retention,
old-mailbox policy and restore verification. Host/log protection is a non-blocking
deployment advisory: unverified, stale or insufficient evidence remains visible in
`checks` and `warnings`, rather than being reported as verified or blocking dispatch.
Private preference/unsubscribe tokens may remain in server logs until host logging
is hardened; log readers may be able to use those links. This policy does not open
dispatch, approve job IDs, clear suppression, or relax provider/runtime checks.
Record evidence only
after actual verification using the administrator readiness page. Evidence expires after
30 days and is bound to the CRM configuration, deployment, public endpoint pins,
principal, sender and local limits. Changing approved jobs, the credential-file path,
or enable/disable does not invalidate otherwise matching evidence. This permits staging
and reviewing readiness while dispatch is disabled. No code here fabricates evidence.

## Separate activation and bounded execution

Only after review, add the approved job IDs and set `enabled` true in server
configuration. Before claiming, the worker checks public withdrawal/preferences and SNS
availability using invalid-token GETs and an empty-event POST (expected HTTP 400),
without exposing a real token or creating an event. It also verifies the frozen sender
matches the pinned sender. Readiness is checked again before each claim. The worker
rechecks lifecycle, schedule, current consent, suppression, identity, owner fences and
capacity at the existing submission boundaries.

Start with one recipient from an approved real test job:

```sh
wp --path=/path/to/public_html --url=https://www.peraproperty.com --user=ADMIN \
  peracrm campaigns dispatch-run --job=APPROVED_JOB_ID --limit=1 \
  --confirm=send-approved-job --format=json
```

`accepted` is SES acceptance, not delivery. Check matched signed delivery events and the
mailbox before expanding. Limits are 1–100 recipients per command, within a roughly
55-second worker loop. Each send can take up to 20 seconds. Conservative pacing leaves
headroom for the integer-second budget ledger. Overall CLI time also includes bounded
provider reads and endpoint checks before the loop.

For unattended sending, separately install a host cron under the site account that runs
`dispatch-refresh` before `dispatch-scheduled`, with the same target `--url` and explicit
administrator user. `dispatch-scheduled --confirm=send-approved-job --format=json` picks
one currently due, claimable job from the configured list. There is no automatic cron
registration, no native WP-Cron administrator impersonation, and no global queue scan.
A regional account lock prevents overlapping workers. Retain restricted, redacted
command summaries and monitor failures. Never place credential values in cron commands.

## Stop and recover

Set `enabled` false and stop the external cron to stop future claims. Already inflight
network requests may have reached SES; disabling cannot recall email. Keep verification
endpoints and signing keys available. Pause/cancel fences are also checked before send.

```sh
wp --path=/path/to/public_html --url=https://www.peraproperty.com --user=ADMIN \
  peracrm campaigns dispatch-recover --job=APPROVED_JOB_ID --limit=1 \
  --confirm=recover-without-resend --format=json
```

Recovery requires explicit dispatch configuration, current identity, suppression,
quota and external evidence. It allows a pending latch or operator breaker solely for
recovery. A pending submission must match the selected job and original attempt owner;
unexpired leases are not stolen. Recovery never sends, refunds spend, clears acceptance
evidence or resets ambiguous outcomes to retry. Expired inflight outcomes become unknown;
accounting recovery leaves an operator breaker. Expired claims without a begun account
submission can return to held. Investigate unknown outcomes and provider evidence before
any separately audited breaker-only recovery. Do not reset permits, ledgers or receipts.

Exit codes: 0 completed/idle/refreshed or bounded progress; 2 disabled; 3 invalid,
unauthorized or configuration/provider read failure; 4 readiness/overlap/execution block.
Only fixed error codes and bounded summaries are printed, never provider bodies or secrets.
The CLI scheduler metadata distinguishes invocation, successful processing and provider
acceptance. No delivery claim is inferred from scheduler invocation.

## Raising the rate of an active job

Scheduled production runs now attempt up to 100 recipients, subject to the existing
55-second execution bound and 20-second send reserve. Explicit `dispatch-run` limits
accept 1–100; the default for a manually requested run remains one. Claims remain
serial and account-locked. The conservative two-second budget window and pacing,
provider quota checks, suppression checks and ambiguous-outcome breaker remain active.
100 is a ceiling, not a promised number per minute.

After deploying, the site owner can adjust an already active, single-job policy:

```sh
python3 /home/d21882/public_html/wp-content/plugins/peracrm/tools/configure-dispatch-rate.py \
  --path /home/d21882/public_html \
  --url https://www.peraproperty.com --user 1 --job 4 \
  --per-second 10 --daily 10000
```

The tool requires current readiness and the exact existing single-job allowlist. It
pauses dispatch, waits for the account worker lock, attests the configured principal,
checks fresh available AWS quotas, and refreshes suppression state. It changes only
the two caps. A private review artifact retains the old configuration and original
evidence. Rate-only review rebinds existing evidence with its original verification
dates and expiry; IAM/account quota evidence receives a fresh provider observation.
The tool rechecks readiness before restoring the prior enabled state. It does not
send, clear breakers, rewrite recipient records, retry unknown deliveries, alter
cron, or reset rolling usage. The existing cron resumes the queue.

Failures after pausing restore the old caps and leave dispatch disabled. Inspect the
printed private attempt directory (`wp-error.log` contains WP-CLI failure details)
before restoring dispatch. Repeating an already successful request reports unchanged.
A previously created target evidence binding is retained and requires review rather
than being overwritten. This helper is only for a reviewed rate increase; it cannot
activate an inactive installation, change identity/sender/scope, or expand job access.
