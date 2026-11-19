# Draft tests and editor controls

Draft tests are explicit administrator CLI operations. SES credentials retain their existing protected-file, CLI-only boundary. The campaign editor queues a test for the current saved revision and a configured test inbox. A dedicated CLI runner sends it in the background. Save changes before clicking Send test email. The draft need not have an audience count or frozen revision.

Configure a separate test recipient allowlist (at most five normalized inboxes) in wp-config.php:

```php
define('PERACRM_CAMPAIGN_TEST_SEND', [
    'enabled' => true,
    'deployment_fingerprint' => 'EXISTING_DEPLOYMENT_FINGERPRINT',
    'recipients' => ['mus7y@yahoo.co.uk'],
]);
```

Use the existing PERACRM_CAMPAIGN_DISPATCH sender identity, credentials and account caps. Its enabled flag and job allowlist are not changed by tests. No additional AWS identity or public endpoint is installed.

```sh
wp --path=/path/to/wordpress --url=https://www.peraproperty.com --user=ADMIN \
  peracrm campaigns draft-test --campaign=6 --revision=SAVED_REVISION \
  --to=mus7y@yahoo.co.uk --request-id=preview-20261119-01 --format=json
```

This is read-only. Add `--confirm=send-one-test` to submit one message. Each request ID belongs to the administrator, target blog, campaign, saved revision, recipient, rendered content and deployment. Repeating it returns its recorded state; it never sends again. A changed recipient/content with a used ID is rejected. A new test requires a new request ID. Unknown/started attempts must be reviewed, not automatically retried.

Messages use the same deliverable renderer and signed regional SES transport, with `[TEST]` in the subject and a visible test notice. Public article, CTA and corporate links work. Private preference/unsubscribe links are inert labels; no subscriber tokens or one-click headers are issued. Tests do not alter consent, suppression, client links, queues, frozen revisions or dispatch. Explicit operational tests may go to an inbox that opted out of marketing. Local hard-bounce, complaint and administrative blocks still deny delivery, and SES remains authoritative about its own suppressions.

Sending authenticates the configured production principal, observes SES account readiness/quota, and participates in the shared budget reservation and submission journal. SES acceptance is reported separately from receipt in the mailbox. Test SNS events have a `peracrm_draft_test` tag and are retained as `draft_test_message` diagnostics, never matched to a production delivery or used to modify consent.

## Editing

- Published-content search debounces typing by 300 ms and uses the existing nonce-protected AJAX endpoint. Enter/Search remains available; exact numeric IDs and `#ID` search only published, public, password-free content of the selected kind.
- Selected blocks show canonical source titles and IDs. Preview/freeze still revalidate publication and translations; titles are not trusted document input.
- Introduction/main text can opt into basic formatting: `**bold**`, `*italic*`, `[small]text[/small]`, `[large]text[/large]`. Toolbar controls insert those markers around selected text. Only fixed escaped HTML is generated; arbitrary HTML/CSS is unsupported. Sizes are 13px, default inherited body size and 20px. Plain-text alternatives omit the markers. Existing plain drafts and captured frozen content retain their original interpretation.
- CTA heading and supporting text are optional. The button label and absolute HTTP(S) URL remain required. Older CTA blocks remain valid.
- Audience preview processes up to 2,000 candidates per click with the existing signed, revision-bound fixed-ID boundary. This changes advisory counting only, not freeze, queue or sending batch sizes.
- Draft-table cells are vertically centred; horizontal alignment is unchanged. Audience action buttons share the existing action gap.

## Frontend sending

The editor now has a **Test email address** field and **Send test email** button below Save draft. It submits a nonce-protected administrator request and polls for the result, including across reloads. Unsaved fields, revision drift and changed rendered content are rejected. The inbox field is outside the draft form so an invalid test address cannot prevent saving the campaign.

Requests are private, non-autoloaded target-blog option records bound to actor, campaign, saved revision, recipient and captured content hash. Duplicate submits reuse a single request. A separate CLI runner atomically claims one queued request per invocation, rechecks the initiating administrator and saved content, then calls the existing SES draft-test service. It never invokes normal campaign dispatch. Processing/unknown requests are never automatically retried. Requests expire after 15 minutes; stale processing is shown as unconfirmed rather than failed safely.

One-time setup on the live host, after deployment:

```sh
python3 /home/d21882/_work/pera-wp/wp-content/plugins/peracrm/tools/install-draft-test-runner.py \
  --path /home/d21882/public_html --url https://www.peraproperty.com \
  --user 1 --to mus7y@yahoo.co.uk
```

The installer configures this permitted test inbox against the current deployment fingerprint, writes an owner-only runner outside the web root and installs one marked cron entry every minute with `flock`. Other cron entries, including the normal campaign dispatcher, are preserved. Re-running replaces only the marked test-runner entry. It initializes a heartbeat; the frontend refuses to enqueue when the heartbeat is over three minutes old. SES credential loading remains CLI-only. No PHP subprocess or credential access is added to HTTP handlers.

For diagnostics only, the runner command is `wp ... peracrm campaigns draft-test-process --format=json`. Users do not run a command for each test email.


The installer records `PHP_BINARY` from the successfully bootstrapped WP-CLI process and pins both that interpreter and the WP-CLI path in the private runner. Cron therefore uses the same PHP runtime even when its PATH omits `/usr/local/bin` or resolves `php` to an older system version. Re-run the installer after deploying this fix to regenerate an existing runner; the marked cron entry remains unique.
